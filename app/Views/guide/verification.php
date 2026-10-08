<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $documents
 * @var array<string,string> $errors
 */

use App\Models\GuideDocument;

$errors = $errors ?? [];
$status = (string) ($user['guide_status'] ?? 'none');
$banner = [
    'pending' => ['cls' => 'banner-amber', 'icon' => '⏳', 'title' => __('g_ver_pending_title', 'Application under review'), 'text' => __('g_ver_pending_text', 'Our admin team is reviewing your documents. You can publish listings once your account is approved.')],
    'approved' => ['cls' => 'banner-green', 'icon' => '✅', 'title' => __('g_ver_approved_title', 'Verified'), 'text' => ''],
    'rejected' => ['cls' => 'banner-red', 'icon' => '⚠️', 'title' => __('g_ver_rejected_title', 'Application not approved'), 'text' => __('g_ver_rejected_text', 'Please review the note below, then upload updated documents to re-apply.')],
][$status] ?? ['cls' => 'banner-amber', 'icon' => 'ℹ️', 'title' => __('g_ver_default_title', 'Verification'), 'text' => __('g_ver_default_text', 'Submit your documents to become a verified guide.')];

$breadcrumbTitle = match ((string) ($user['role'] ?? '')) {
    'hotel_admin' => 'Hotel Partner Verification',
    'rental_admin' => 'Rental Partner Verification',
    default => 'Guide Verification',
};
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="breadcrumb" style="margin-top:0;"><a href="<?= e(url('/profile')) ?>"><?= e(__('d_profile', 'Profile')) ?></a> &gt; <?= e($breadcrumbTitle) ?></div>
            <h1 style="margin-bottom:1rem;"><?= e($heading ?? $breadcrumbTitle) ?></h1>

            <div class="status-banner <?= $banner['cls'] ?>">
                <?php if ($banner['icon'] !== ''): ?><span class="status-icon"><?= $banner['icon'] ?></span><?php endif; ?>
                <div>
                    <strong><?= e($banner['title']) ?></strong>
                    <?php if ($banner['text'] !== ''): ?><p class="mb-0"><?= e($banner['text']) ?></p><?php endif; ?>
                    <?php if ($status === 'rejected' && !empty($user['guide_review_note'])): ?>
                        <p class="note-box" style="margin-top:.6rem;"><strong><?= e(__('g_admin_note', 'Admin note:')) ?></strong> <?= e($user['guide_review_note']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h3><?= e(__('g_submitted_docs', 'Your submitted documents')) ?></h3></div>
                <div class="panel-body">
                    <?php if ($documents === []): ?>
                        <p class="hint mb-0"><?= e(__('g_no_docs', 'No documents on file yet.')) ?></p>
                    <?php else: ?>
                        <ul class="doc-list">
                            <?php foreach ($documents as $d): ?>
                                <li>
                                    <span class="doc-type"><?= e(GuideDocument::typeLabel($d['doc_type'])) ?></span>
                                    <a href="<?= e(url($d['file_path'])) ?>" target="_blank" rel="noopener" class="doc-link">📎 <?= e($d['label'] ?: basename($d['file_path'])) ?> ↗</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($status !== 'approved'): ?>
                <div class="panel">
                    <div class="panel-head"><h3><?= $status === 'rejected' ? e(__('g_resubmit_docs', 'Re-submit documents')) : e(__('g_update_docs', 'Update documents')) ?></h3></div>
                    <div class="panel-body">
                        <p class="hint"><?= e(__('g_upload_hint', 'Upload clear photos or PDFs (max 5MB each). Re-submitting replaces your previous files and sends your application back for review.')) ?></p>
                        <form method="post" action="<?= e(url('/dashboard/verification')) ?>" enctype="multipart/form-data" novalidate>
                            <?= csrf_field() ?>
                            <div class="field-row">
                                <label for="valid_id"><?= e(__('g_valid_id', 'Valid government ID')) ?> <span class="req">*</span></label>
                                <input class="file-input" type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <?php if (isset($errors['valid_id'])): ?><div class="field-error"><?= e($errors['valid_id']) ?></div><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <label for="credential_type"><?= e(__('g_cred_type', 'Credential type')) ?> <span class="req">*</span></label>
                                <select class="input" id="credential_type" name="credential_type">
                                    <?php foreach (GuideDocument::TYPES as $key => $label): ?>
                                        <?php if ($key === 'valid_id' || $key === 'other') { continue; } ?>
                                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-row">
                                <label for="credential"><?= e(__('g_cred_doc', 'Credential document')) ?> <span class="req">*</span></label>
                                <input class="file-input" type="file" id="credential" name="credential" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <?php if (isset($errors['credential'])): ?><div class="field-error"><?= e($errors['credential']) ?></div><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <label for="extra_docs"><?= e(__('g_extra_docs', 'Additional documents')) ?> <span class="hint"><?= e(__('g_optional', '(optional)')) ?></span></label>
                                <input class="file-input" type="file" id="extra_docs" name="extra_docs[]" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple>
                                <?php if (isset($errors['extra_docs'])): ?><div class="field-error"><?= e($errors['extra_docs']) ?></div><?php endif; ?>
                            </div>
                            <button class="btn btn-primary" type="submit"><?= e(__('g_submit_review', 'Submit for review')) ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
