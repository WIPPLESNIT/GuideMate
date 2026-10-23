<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $documents
 */
$documents = $documents ?? [];
$isGuide = \App\Models\User::isProviderRole((string) ($user['role'] ?? ''));
$guideStatus = (string) ($user['guide_status'] ?? 'none');
$isVerified = $guideStatus === 'approved';
$verifyBadge = [
    'approved' => ['cls' => 'pill-confirmed', 'label' => '✅ Verified'],
    'pending' => ['cls' => 'pill-pending', 'label' => '⏳ Under review'],
    'rejected' => ['cls' => 'pill-cancelled', 'label' => '⚠️ Not approved'],
][$guideStatus] ?? ['cls' => 'pill-pending', 'label' => 'ℹ️ Not submitted'];

$bioText = trim((string) ($user['bio'] ?? ''));
$hasBio = $bioText !== '';

if ($hasBio) {
    // Calculate line count based on both explicit newlines and line wrapping from character length
    $paragraphs = preg_split('/\r\n|\r|\n/', $bioText) ?: [$bioText];
    $bioLineCount = 0;
    foreach ($paragraphs as $p) {
        $pLen = mb_strlen(rtrim($p));
        // Container fits ~65 characters per line
        $bioLineCount += max(1, (int) ceil($pLen / 65));
    }
} else {
    $bioLineCount = 1;
}

// Line-height is ~1.55 (~24px per line) plus 28px vertical padding (0.85rem top + 0.85rem bottom)
$alignedBioHeight = max(48, ($bioLineCount * 24) + 28);
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div style="margin-bottom:1.5rem;">
                <h1 class="mb-0">My profile</h1>
            </div>

            <div class="panel profile-details-panel" id="profileDetailsSection" style="max-width:680px;position:relative;">
                <div class="profile-details-corner-action">
                    <a href="<?= e(url('/profile/edit')) ?>" class="profile-corner-control has-tooltip" aria-label="Edit profile" title="Edit profile" data-tooltip="Edit profile" id="editProfileBtn">
                        <svg class="ico-edit-pencil" width="18" height="18" viewBox="126 126 260 260" fill="none" stroke="currentColor" stroke-width="26" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M 265 173 H 146 a 6 6 0 0 0 -6 6 v 186 a 6 6 0 0 0 6 6 h 186 a 6 6 0 0 0 6 -6 V 250" />
                            <path d="M 186 328 l 18 -52 L 344 136 l 34 34 L 238 310 Z" />
                        </svg>
                    </a>
                </div>
                <div class="panel-body">
                    <div style="display:flex;align-items:center;gap:1.25rem;padding-bottom:1.5rem;border-bottom:1px solid var(--line);margin-bottom:1.5rem;padding-right:3rem;">
                        <img src="<?= e(img_src($user['avatar'] ?? null, 'avatar' . $user['id'])) ?>" alt="<?= e($user['name']) ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--line);">
                        <div>
                            <h2 style="margin:0 0 .3rem;font-size:1.35rem;"><?= e($user['name']) ?></h2>
                            <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                                <span class="pill pill-confirmed"><?= e(\App\Models\User::PROVIDER_LABELS[$user['role']] ?? ucfirst($user['role'])) ?></span>
                                <?php if (!empty($user['location'])): ?>
                                    <span class="hint">📍 <?= e($user['location']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1.25rem;margin-bottom:1.5rem;">
                        <div>
                            <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Full name</span>
                            <div style="font-weight:600;color:var(--ink-900);"><?= e($user['name']) ?></div>
                        </div>
                        <div>
                            <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Email address</span>
                            <div style="font-weight:600;color:var(--ink-900);word-break:break-all;"><?= e($user['email']) ?></div>
                        </div>
                        <div>
                            <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Phone number</span>
                            <div style="font-weight:600;color:var(--ink-900);"><?= !empty($user['phone']) ? e($user['phone']) : '<span class="hint">Not provided</span>' ?></div>
                        </div>
                        <div>
                            <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Location</span>
                            <div style="font-weight:600;color:var(--ink-900);"><?= !empty($user['location']) ? e($user['location']) : '<span class="hint">Not provided</span>' ?></div>
                        </div>
                        <div>
                            <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Member since</span>
                            <div style="font-weight:600;color:var(--ink-900);"><?= !empty($user['created_at']) ? e(date('F Y', strtotime((string) $user['created_at']))) : '—' ?></div>
                        </div>
                    </div>

                    <div>
                        <span class="hint" style="display:block;margin-bottom:.4rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;"><?= ($user['role'] === 'guide') ? 'About you (shown to travelers)' : 'Bio' ?></span>
                        <div style="background:var(--sand-100, #f8fafc);padding:0.85rem 1.15rem;border-radius:10px;line-height:1.55;color:var(--ink-800);min-height:<?= $alignedBioHeight ?>px;word-break:break-word;overflow-wrap:anywhere;box-sizing:border-box;"><?= $hasBio ? nl2br(e($bioText)) : '<em class="hint">No bio provided yet. Click "Edit profile" to add your introduction.</em>' ?></div>
                    </div>
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
                                        <?php
                                        $hasFile = !empty($d['file_path']);
                                        $docUrl = $hasFile ? url($d['file_path']) : url('/documents/' . (int) $d['id']);
                                        $docLabel = !empty($d['label']) ? $d['label'] : ($hasFile ? basename($d['file_path']) : 'Document #' . $d['id']);
                                        ?>
                                        <a href="<?= e($docUrl) ?>" target="_blank" rel="noopener" class="doc-link"
                                           data-doc-preview="1"
                                           data-doc-id="<?= (int) $d['id'] ?>"
                                           data-doc-url="<?= e($docUrl) ?>"
                                           data-doc-label="<?= e($docLabel) ?>"
                                           data-doc-type="<?= e(\App\Models\GuideDocument::typeLabel($d['doc_type'])) ?>"
                                           data-doc-status="<?= e($guideStatus) ?>"
                                           data-doc-owner="<?= e($user['name'] ?? '') ?>">📎 <?= e($docLabel) ?></a>
                                        <?php if ($isVerified): ?>
                                            <span class="pill pill-confirmed" style="margin-left:.5rem;">✓ Verified</span>
                                        <?php elseif ($guideStatus === 'pending'): ?>
                                            <span class="pill pill-pending" style="margin-left:.5rem;">Pending</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <a href="<?= e(url('/documents')) ?>" class="btn btn-ghost" style="margin-top:1rem;">
                            Submit or Update Documents
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
