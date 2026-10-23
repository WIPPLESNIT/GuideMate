<?php
/**
 * Documents / Verification Documents View
 * 
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $documents
 * @var array{cls:string,label:string} $verifyBadge
 * @var string $guideStatus
 * @var bool $isVerified
 * @var array<string,string> $errors
 * @var bool $showReupload
 */
$isProvider = \App\Models\User::isProviderRole((string) ($user['role'] ?? ''));
$errors = $errors ?? [];
$showReupload = $showReupload ?? (!empty($errors) || (isset($_GET['action']) && $_GET['action'] === 'reupload') || (isset($_GET['reupload']) && $_GET['reupload'] === '1'));
?>
<div class="container">
    <div class="<?= $isProvider ? 'dash-layout' : '' ?>" style="<?= !$isProvider ? 'max-width:860px;margin:2rem auto;' : '' ?>">
        <?php if ($isProvider): ?>
            <?= \App\Core\View::partial('partials/dash-nav') ?>
        <?php endif; ?>

        <div>
            <!-- Breadcrumb matching Image 2 -->
            <div class="breadcrumb" style="margin-top:0;margin-bottom:0.4rem;font-size:0.9rem;color:var(--ink-400);">
                <a href="<?= e(url('/profile')) ?>" style="color:var(--ink-500);text-decoration:none;"><?= e(__('d_profile', 'Profile')) ?></a> &gt; Documents
            </div>

            <!-- Page Section Heading -->
            <div class="section-head" style="margin-bottom:1.25rem;">
                <div>
                    <h1 class="mb-0">My Documents</h1>
                    <p class="hint mb-0">Attached account and verification documents on file.</p>
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <span class="pill <?= e($verifyBadge['cls']) ?>" style="font-size:0.85rem;padding:0.4rem 0.85rem;"><?= e($verifyBadge['label']) ?></span>
                    <a href="<?= e(url('/profile')) ?>" class="btn btn-ghost btn-sm">Back to Profile</a>
                </div>
            </div>

            <!-- Verification Status Banners -->
            <?php if ($isVerified): ?>
                <div class="status-banner banner-green" style="margin-bottom:1.5rem;background:#f0fdf4;border-color:#bbf7d0;color:#166534;">
                    <span class="status-icon">✓</span>
                    <div>
                        <strong>Account Verified</strong>
                        <p class="mb-0">Your submitted documents were reviewed and verified by our administrator team.</p>
                    </div>
                </div>
            <?php elseif ($guideStatus === 'pending'): ?>
                <div class="status-banner banner-amber" style="margin-bottom:1.5rem;">
                    <span class="status-icon">⏳</span>
                    <div>
                        <strong>Verification in Progress</strong>
                        <p class="mb-0">An administrator is currently reviewing your submitted documents. You will receive an update once approved.</p>
                    </div>
                </div>
            <?php elseif ($guideStatus === 'rejected'): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.5rem;">
                    <span class="status-icon">⚠️</span>
                    <div>
                        <strong>Application Requires Attention</strong>
                        <p class="mb-0">Your documents were not approved. Please review administrator notes below and re-upload updated documents.</p>
                        <?php if (!empty($user['guide_review_note'])): ?>
                            <p class="note-box" style="margin-top:0.6rem;background:rgba(239,68,68,0.08);border-left:3px solid #ef4444;padding:0.6rem 0.85rem;border-radius:4px;color:#991b1b;">
                                <strong>Admin note:</strong> <?= e($user['guide_review_note']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Submitted Documents Panel -->
            <div class="panel">
                <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="margin:0;">Submitted Documents (<?= count($documents) ?>)</h3>
                    <a href="<?= e(url('/profile')) ?>" class="btn btn-ghost btn-sm">Profile Details</a>
                </div>
                <div class="panel-body">
                    <?php if ($documents === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;text-align:center;">
                            <div style="font-size:3rem;margin-bottom:.5rem;">📁</div>
                            <h3 style="margin-bottom:.4rem;">No documents attached yet</h3>
                            <p class="hint" style="max-width:440px;margin:0 auto 1.2rem;">
                                You haven't submitted any verification documents for your account. Upload a valid government ID and credentials to become verified.
                            </p>
                            <button type="button" class="btn btn-primary" onclick="openReuploadForm()">
                                + Submit Verification Documents
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:0.75rem;">
                            <?php foreach ($documents as $d):
                                $docId = (int) $d['id'];
                                $hasFile = !empty($d['file_path']);
                                $fileExists = !empty($d['file_exists']);
                                $viewUrl = url('/documents/' . $docId);
                                $fileUrl = $hasFile ? url($d['file_path']) : $viewUrl;
                                $downloadUrl = url('/documents/download/' . $docId);
                                $label = !empty($d['label']) ? $d['label'] : ($hasFile ? basename($d['file_path']) : 'Document #' . $docId);
                                $ext = !empty($d['ext']) ? strtoupper($d['ext']) : 'FILE';
                            ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.9rem 1.1rem;background:var(--sand-100);border:1px solid var(--line);border-radius:12px;flex-wrap:wrap;">
                                    <div style="display:flex;align-items:center;gap:0.85rem;min-width:0;flex:1;">
                                        <div style="width:42px;height:42px;border-radius:10px;background:#fff;border:1px solid var(--line);display:grid;place-items:center;font-weight:800;font-size:0.75rem;color:var(--teal-700);flex-shrink:0;">
                                            <?= e($ext) ?>
                                        </div>
                                        <div style="min-width:0;">
                                            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                                <a href="<?= e($fileUrl) ?>"
                                                   data-doc-preview="1"
                                                   data-doc-id="<?= (int) $d['id'] ?>"
                                                   data-doc-url="<?= e($fileUrl) ?>"
                                                   data-doc-label="<?= e($label) ?>"
                                                   data-doc-type="<?= e(\App\Models\GuideDocument::typeLabel($d['doc_type'])) ?>"
                                                   data-doc-status="<?= e($guideStatus) ?>"
                                                   data-doc-owner="<?= e($user['name'] ?? '') ?>"
                                                   style="font-weight:700;color:var(--ink-900);text-decoration:none;word-break:break-all;">
                                                    <?= e($label) ?>
                                                </a>
                                                <span class="pill pill-pending" style="font-size:0.72rem;padding:0.15rem 0.5rem;">
                                                    <?= e(\App\Models\GuideDocument::typeLabel($d['doc_type'])) ?>
                                                </span>
                                            </div>
                                            <div style="font-size:0.8rem;color:var(--ink-400);margin-top:0.15rem;">
                                                Uploaded <?= e(date('M j, Y g:i a', strtotime($d['created_at']))) ?>
                                                <?php if ($fileExists && !empty($d['file_size'])): ?>
                                                    · <?= e(round($d['file_size'] / 1024, 1)) ?> KB
                                                <?php elseif (!$fileExists): ?>
                                                    · <span style="color:#dc2626;font-weight:600;">File missing from disk</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:flex;align-items:center;gap:0.4rem;flex-shrink:0;">
                                        <!-- Preview button removed per requirement 5 -->
                                        <?php if ($fileExists): ?>
                                            <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="Open file directly"
                                               data-doc-preview="1"
                                               data-doc-id="<?= (int) $d['id'] ?>"
                                               data-doc-url="<?= e($fileUrl) ?>"
                                               data-doc-label="<?= e($label) ?>"
                                               data-doc-type="<?= e(\App\Models\GuideDocument::typeLabel($d['doc_type'])) ?>"
                                               data-doc-status="<?= e($guideStatus) ?>"
                                               data-doc-owner="<?= e($user['name'] ?? '') ?>">
                                                Open ↗
                                            </a>
                                            <a href="<?= e($downloadUrl) ?>" class="btn btn-ghost btn-sm" title="Download file" download>
                                                Download
                                            </a>
                                        <?php else: ?>
                                            <span class="pill pill-disputed" style="font-size:0.75rem;">Unavailable</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Action controls at bottom of list: duplicate "Back to Profile" button removed per requirement 3 -->
                        <div style="margin-top:1.25rem;display:flex;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                            <button type="button" class="btn btn-ghost" id="toggleReuploadBtn" onclick="toggleReuploadForm()">
                                Re-upload or Update Documents
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Re-upload or Update Documents Form Panel (Requirement 4) -->
            <div id="reuploadFormPanel" class="panel" style="<?= $showReupload ? 'display:block;' : 'display:none;' ?>margin-top:1.5rem;border:1px solid rgba(15,118,110,0.3);box-shadow:0 4px 18px rgba(0,0,0,0.04);">
                <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;background:var(--sand-100);">
                    <h3 style="margin:0;font-size:1.1rem;color:var(--teal-800);">Re-upload or Update Documents</h3>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="toggleReuploadForm()" title="Cancel and close">&times; Close</button>
                </div>
                <div class="panel-body">
                    <p class="hint" style="margin-top:0;margin-bottom:1.25rem;">
                        Upload clear photos or PDFs (max 5MB each). Submitting replaces your previous files and sends your application to the administrator team for review.
                    </p>
                    <form method="post" action="<?= e(url('/documents/upload')) ?>" enctype="multipart/form-data" novalidate>
                        <?= csrf_field() ?>
                        <div class="field-row" style="margin-bottom:1.25rem;">
                            <label for="valid_id" style="display:block;margin-bottom:0.35rem;font-weight:600;">Valid government ID <span class="req" style="color:#ef4444;">*</span></label>
                            <input class="file-input" type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png,.webp" required style="width:100%;padding:0.45rem;border:1px solid var(--line);border-radius:8px;background:#fff;">
                            <span class="hint" style="display:block;margin-top:0.25rem;font-size:0.8rem;">Accepted formats: JPG, PNG, WEBP, PDF (max 5MB)</span>
                            <?php if (isset($errors['valid_id'])): ?><div class="field-error" style="color:#ef4444;font-size:0.85rem;margin-top:0.25rem;"><?= e($errors['valid_id']) ?></div><?php endif; ?>
                        </div>

                        <div class="field-row" style="margin-bottom:1.25rem;">
                            <label for="credential_type" style="display:block;margin-bottom:0.35rem;font-weight:600;">Credential type <span class="req" style="color:#ef4444;">*</span></label>
                            <select class="input" id="credential_type" name="credential_type" style="width:100%;max-width:400px;padding:0.6rem 0.85rem;border:1px solid var(--line);border-radius:8px;background:#fff;">
                                <?php foreach (\App\Models\GuideDocument::TYPES as $key => $lbl): ?>
                                    <?php if ($key === 'valid_id' || $key === 'other') { continue; } ?>
                                    <option value="<?= e($key) ?>"><?= e($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['credential_type'])): ?><div class="field-error" style="color:#ef4444;font-size:0.85rem;margin-top:0.25rem;"><?= e($errors['credential_type']) ?></div><?php endif; ?>
                        </div>

                        <div class="field-row" style="margin-bottom:1.25rem;">
                            <label for="credential" style="display:block;margin-bottom:0.35rem;font-weight:600;">Credential document <span class="req" style="color:#ef4444;">*</span></label>
                            <input class="file-input" type="file" id="credential" name="credential" accept=".pdf,.jpg,.jpeg,.png,.webp" required style="width:100%;padding:0.45rem;border:1px solid var(--line);border-radius:8px;background:#fff;">
                            <span class="hint" style="display:block;margin-top:0.25rem;font-size:0.8rem;">Tour guide license, DTI/SEC registration, DOT accreditation, or business permit.</span>
                            <?php if (isset($errors['credential'])): ?><div class="field-error" style="color:#ef4444;font-size:0.85rem;margin-top:0.25rem;"><?= e($errors['credential']) ?></div><?php endif; ?>
                        </div>

                        <div class="field-row" style="margin-bottom:1.5rem;">
                            <label for="extra_docs" style="display:block;margin-bottom:0.35rem;font-weight:600;">Additional documents <span class="hint" style="font-weight:normal;color:var(--ink-400);">(optional)</span></label>
                            <input class="file-input" type="file" id="extra_docs" name="extra_docs[]" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple style="width:100%;padding:0.45rem;border:1px solid var(--line);border-radius:8px;background:#fff;">
                            <span class="hint" style="display:block;margin-top:0.25rem;font-size:0.8rem;">You may select multiple supporting documents (e.g. certificates, insurance, permits).</span>
                            <?php if (isset($errors['extra_docs'])): ?><div class="field-error" style="color:#ef4444;font-size:0.85rem;margin-top:0.25rem;"><?= e($errors['extra_docs']) ?></div><?php endif; ?>
                        </div>

                        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                            <button class="btn btn-primary" type="submit">Submit Documents for Review</button>
                            <button class="btn btn-ghost" type="button" onclick="toggleReuploadForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleReuploadForm() {
    var p = document.getElementById('reuploadFormPanel');
    if (!p) return;
    if (p.style.display === 'none' || p.style.display === '') {
        p.style.display = 'block';
        p.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        p.style.display = 'none';
    }
}
function openReuploadForm() {
    var p = document.getElementById('reuploadFormPanel');
    if (p) {
        p.style.display = 'block';
        p.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>
