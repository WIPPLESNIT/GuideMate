<?php
/**
 * @var array<string,mixed> $user
 * @var array<string,string> $errors
 * @var array<int,array<string,mixed>> $documents
 */
$errors = $errors ?? [];
$documents = $documents ?? [];
$isGuide = \App\Models\User::isProviderRole((string) ($user['role'] ?? ''));
$guideStatus = (string) ($user['guide_status'] ?? 'none');
$isVerified = $guideStatus === 'approved';
$verifyBadge = [
    'approved' => ['cls' => 'pill-confirmed', 'label' => '✅ Verified'],
    'pending' => ['cls' => 'pill-pending', 'label' => '⏳ Under review'],
    'rejected' => ['cls' => 'pill-cancelled', 'label' => '⚠️ Not approved'],
][$guideStatus] ?? ['cls' => 'pill-pending', 'label' => 'ℹ️ Not submitted'];

$bioValue = (string) old('bio', $user['bio'] ?? '');
$bioTrimmed = trim($bioValue);
$paragraphs = $bioTrimmed !== '' ? (preg_split('/\r\n|\r|\n/', $bioTrimmed) ?: [$bioTrimmed]) : [];
$bioLineCount = 0;
foreach ($paragraphs as $p) {
    $bioLineCount += max(1, (int) ceil(mb_strlen(rtrim($p)) / 65));
}
$bioRows = max(4, min(30, max(1, $bioLineCount)));
$bioPlaceholder = ($user['role'] === 'guide')
    ? 'Tell travelers about your background, experience, tour specialties, and what makes your trips memorable...'
    : 'Tell us a little bit about yourself, what you enjoy doing, and your favorite travel experiences...';
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
                <h1 class="mb-0">Edit profile</h1>
                <a href="<?= e(url('/profile')) ?>" class="btn btn-ghost">← Back to profile</a>
            </div>
            <div class="panel" style="max-width:680px;">
                <div class="panel-body">
                    <form method="post" action="<?= e(url('/profile/edit')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="field-row">
                            <label for="avatar">Profile photo</label>
                            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:.8rem;">
                                <img id="avatarPreview" src="<?= e(img_src($user['avatar'] ?? null, 'avatar' . $user['id'])) ?>" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid var(--line);">
                                <input class="file-input" type="file" id="avatar" name="avatar" accept=".jpg,.jpeg,.png,.webp" style="flex:1;">
                            </div>
                            <span class="hint">JPG, PNG or WEBP, up to 5MB.</span>
                            <?php if (isset($errors['avatar'])): ?><div class="field-error"><?= e($errors['avatar']) ?></div><?php endif; ?>
                        </div>
                        <div class="field-row">
                            <label for="name">Full name <span class="req" style="color:#ef4444;">*</span></label>
                            <input class="input" id="name" name="name" value="<?= e(old('name', $user['name'])) ?>" placeholder="e.g. Juan Dela Cruz" required>
                            <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                        <div class="field-row">
                            <label>Email</label>
                            <input class="input" value="<?= e($user['email']) ?>" placeholder="you@example.com" disabled>
                            <span class="hint">Email cannot be changed.</span>
                        </div>
                        <div class="form-grid-2">
                            <div class="field-row">
                                <label for="phone">Phone</label>
                                <input class="input" id="phone" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" placeholder="e.g. +63 912 345 6789">
                            </div>
                            <div class="field-row">
                                <label for="location">Location</label>
                                <input class="input" id="location" name="location" value="<?= e(old('location', $user['location'] ?? '')) ?>" placeholder="e.g. Cebu City, Philippines">
                            </div>
                        </div>
                        <div class="field-row">
                            <label for="bio"><?= ($user['role'] === 'guide') ? 'About you (shown to travelers)' : 'Bio' ?></label>
                            <textarea class="input" id="bio" name="bio" rows="<?= $bioRows ?>" placeholder="<?= e($bioPlaceholder) ?>" style="min-height:120px;line-height:1.6;resize:vertical;"><?= e($bioValue) ?></textarea>
                        </div>
                        <div style="display:flex;align-items:center;gap:.75rem;">
                            <button class="btn btn-primary" type="submit">Save changes</button>
                            <a href="<?= e(url('/profile')) ?>" class="btn btn-ghost">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($isGuide): ?>
                <div class="panel" style="max-width:680px;margin-top:1.5rem;">
                    <div class="panel-head" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;">
                        <h3 style="margin:0;">Verification &amp; documents</h3>
                        <span class="pill <?= $verifyBadge['cls'] ?>"><?= e($verifyBadge['label']) ?></span>
                    </div>
                    <div class="panel-body">
                        <?php if ($isVerified): ?>
                            <p class="hint" style="margin-top:0;">These documents were reviewed and verified by our admin team.</p>
                        <?php elseif ($guideStatus === 'pending'): ?>
                            <p class="hint" style="margin-top:0;">Your documents are being reviewed by our admin team.</p>
                        <?php else: ?>
                            <p class="hint" style="margin-top:0;">Submit your documents to become a verified guide.</p>
                        <?php endif; ?>

                        <?php if ($documents === []): ?>
                            <p class="hint mb-0">No documents on file yet.</p>
                        <?php else: ?>
                            <ul class="doc-list">
                                <?php foreach ($documents as $d): ?>
                                    <li>
                                        <span class="doc-type"><?= e(\App\Models\GuideDocument::typeLabel($d['doc_type'])) ?></span>
                                        <a href="<?= e(url($d['file_path'])) ?>" target="_blank" rel="noopener" class="doc-link">📎 <?= e($d['label'] ?: basename($d['file_path'])) ?> ↗</a>
                                        <?php if ($isVerified): ?>
                                            <span class="pill pill-confirmed" style="margin-left:.5rem;">✓ Verified</span>
                                        <?php elseif ($guideStatus === 'pending'): ?>
                                            <span class="pill pill-pending" style="margin-left:.5rem;">Pending</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <a href="<?= e(url('/dashboard/verification')) ?>" class="btn btn-ghost" style="margin-top:1rem;">
                            Submit or Update Documents
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var bio = document.getElementById('bio');
    if (!bio) return;

    function autoResizeBio() {
        bio.style.height = 'auto';
        var borderOffset = (bio.offsetHeight - bio.clientHeight) || 4;
        bio.style.height = Math.max(120, bio.scrollHeight + borderOffset) + 'px';
    }

    bio.addEventListener('input', autoResizeBio);
    bio.addEventListener('change', autoResizeBio);
    bio.addEventListener('paste', function () {
        setTimeout(autoResizeBio, 0);
    });
    window.addEventListener('resize', autoResizeBio);

    autoResizeBio();
    window.addEventListener('load', autoResizeBio);
});
</script>
