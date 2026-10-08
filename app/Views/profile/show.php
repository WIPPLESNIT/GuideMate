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
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
                <h1 class="mb-0">My profile</h1>
                <a href="<?= e(url('/profile/edit')) ?>" class="btn btn-primary">✏️ Edit profile</a>
            </div>

            <div class="panel" style="max-width:680px;">
                <div class="panel-body">
                    <div style="display:flex;align-items:center;gap:1.25rem;padding-bottom:1.5rem;border-bottom:1px solid var(--line);margin-bottom:1.5rem;">
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
