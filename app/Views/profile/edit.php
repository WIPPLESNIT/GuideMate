<?php
/**
 * @var array<string,mixed> $user
 * @var array<string,string> $errors
 * @var array<int,array<string,mixed>> $documents
 * @var bool $openEditModal
 */
$errors = $errors ?? [];
$documents = $documents ?? [];
$openEditModal = true;
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

$bioPlaceholder = ($user['role'] === 'guide')
    ? 'Tell travelers about your background, experience, tour specialties, and what makes your trips memorable...'
    : 'Tell us a little bit about yourself, what you enjoy doing, and your favorite travel experiences...';

if ($hasBio) {
    $paragraphs = preg_split('/\r\n|\r|\n/', $bioText) ?: [$bioText];
    $bioLineCount = 0;
    foreach ($paragraphs as $p) {
        $pLen = mb_strlen(rtrim($p));
        $bioLineCount += max(1, (int) ceil($pLen / 65));
    }
} else {
    $bioLineCount = 1;
}

$alignedBioHeight = max(48, ($bioLineCount * 24) + 28);
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div style="margin-bottom:1.5rem;">
                <h1 class="mb-0">My profile</h1>
            </div>

            <div class="panel profile-details-panel" id="profileDetailsContainer" style="max-width:680px;position:relative;">
                <div class="profile-details-corner-action">
                    <button type="button" class="profile-corner-control has-tooltip" aria-label="Edit profile details" title="Edit profile details" data-tooltip="Edit profile details" id="editProfileCornerBtn">
                        <svg class="ico-edit-pencil" width="20" height="20" viewBox="126 126 260 260" fill="none" stroke="currentColor" stroke-width="26" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M 265 173 H 146 a 6 6 0 0 0 -6 6 v 186 a 6 6 0 0 0 6 6 h 186 a 6 6 0 0 0 6 -6 V 250" />
                            <path d="M 186 328 l 18 -52 L 344 136 l 34 34 L 238 310 Z" />
                        </svg>
                    </button>
                </div>
                <div class="panel-body">
                    <div style="display:flex;align-items:center;gap:1.25rem;padding-bottom:1.5rem;border-bottom:1px solid var(--line);margin-bottom:1.5rem;padding-right:3rem;">
                        <img src="<?= e(img_src($user['avatar'] ?? null, 'avatar' . $user['id'])) ?>" alt="<?= e($user['name']) ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--line);">
                        <div>
                            <h2 style="margin:0 0 .3rem;font-size:1.35rem;"><?= e($user['name']) ?></h2>
                            <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                                <?php if ($isVerified): ?>
                                    <span class="pill pill-confirmed" style="background:#dcfce7;color:#15803d;font-weight:700;">✔ Verified Guide</span>
                                <?php endif; ?>
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
                        <?php if ($isGuide): ?>
                            <div>
                                <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Experience</span>
                                <div style="font-weight:600;color:var(--ink-900);"><?= !empty($user['experience']) ? e($user['experience']) : '<span class="hint">Not provided</span>' ?></div>
                            </div>
                            <div>
                                <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Languages</span>
                                <div style="font-weight:600;color:var(--ink-900);"><?= !empty($user['languages']) ? e($user['languages']) : '<span class="hint">Not provided</span>' ?></div>
                            </div>
                            <div>
                                <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Rating</span>
                                <div style="font-weight:600;color:var(--ink-900);">
                                    <?php
                                    $avgRating = (float) ($guideStats['avg_rating'] ?? 0);
                                    $revCount = (int) ($guideStats['review_count'] ?? 0);
                                    ?>
                                    <?= $avgRating > 0 ? '⭐ ' . number_format($avgRating, 1) . ' / 5.0' . ($revCount > 0 ? ' (' . $revCount . ')' : '') : '⭐ New Guide' ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div>
                            <span class="hint" style="display:block;margin-bottom:.25rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Member since</span>
                            <div style="font-weight:600;color:var(--ink-900);"><?= !empty($user['created_at']) ? e(date('F Y', strtotime((string) $user['created_at']))) : '—' ?></div>
                        </div>
                    </div>

                    <?php if ($isGuide): ?>
                        <div style="margin-bottom:1.25rem;">
                            <span class="hint" style="display:block;margin-bottom:.4rem;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Specialties</span>
                            <?php
                            $specs = array_filter(array_map('trim', explode(',', (string) ($user['specialties'] ?? ''))));
                            ?>
                            <?php if ($specs !== []): ?>
                                <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
                                    <?php foreach ($specs as $spec): ?>
                                        <span style="display:inline-flex;align-items:center;padding:0.3rem 0.8rem;border-radius:999px;background:var(--sand-100, #f1f5f9);border:1px solid var(--line, #e2e8f0);font-size:0.85rem;font-weight:600;color:var(--ink-800);"><?= e($spec) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="hint">No specialties listed yet. Edit profile to add your specialties.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

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

<!-- Centered Profile Edit Pop-up Modal Overlay (Active by default on edit route) -->
<div class="profile-modal-overlay is-active" id="profileEditModal" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
    <div class="profile-modal-backdrop" id="profileModalBackdrop" aria-label="Close modal"></div>
    <div class="profile-modal-dialog">
        <div class="profile-modal-header">
            <div class="profile-modal-title-wrap">
                <h2 id="profileModalTitle">Edit Profile</h2>
                <p class="profile-modal-subtitle">Update your personal details, contact information, and public bio.</p>
            </div>
            <a href="<?= e(url('/profile')) ?>" class="profile-modal-close-btn" id="closeProfileModalBtn" aria-label="Close dialog">&times;</a>
        </div>
        
        <form method="post" action="<?= e(url('/profile')) ?>" enctype="multipart/form-data" id="profileEditForm" class="profile-modal-form">
            <?= csrf_field() ?>
            
            <div class="profile-modal-body">
                <!-- Personal Information Header -->
                <div style="margin-bottom:0.75rem;padding-bottom:0.35rem;border-bottom:1px solid var(--line);">
                    <h3 style="font-size:0.88rem;font-weight:700;color:var(--ink-700);text-transform:uppercase;letter-spacing:0.04em;margin:0;">Personal Information</h3>
                </div>

                <!-- Avatar Section -->
                <div class="profile-modal-avatar-section">
                    <img id="modalAvatarPreview" class="profile-modal-avatar-preview" src="<?= e(img_src($user['avatar'] ?? null, 'avatar' . $user['id'])) ?>" alt="<?= e($user['name']) ?>">
                    <div class="profile-modal-avatar-info">
                        <div class="profile-modal-avatar-title">Profile Photo</div>
                        <div class="profile-modal-avatar-actions">
                            <label for="modalAvatarInput" class="profile-modal-avatar-btn">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                <span>Change Photo</span>
                            </label>
                            <input type="file" id="modalAvatarInput" name="avatar" accept=".jpg,.jpeg,.png,.webp" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;">
                        </div>
                        <p class="profile-modal-avatar-hint">JPG, PNG, or WEBP, up to 5MB.</p>
                        <?php if (isset($errors['avatar'])): ?>
                            <div class="field-error" style="margin-top:0.25rem;"><?= e($errors['avatar']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Full Name -->
                <div class="profile-modal-field">
                    <label for="modalNameInput" class="profile-modal-label">Full Name <span class="req">*</span></label>
                    <input class="input" type="text" id="modalNameInput" name="name" value="<?= e(old('name', $user['name'])) ?>" placeholder="e.g. Marc Repuesto" required>
                    <?php if (isset($errors['name'])): ?>
                        <div class="field-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Email Address -->
                <div class="profile-modal-field">
                    <label for="modalEmailInput" class="profile-modal-label">Email Address <span class="req">*</span></label>
                    <input class="input" type="email" id="modalEmailInput" name="email" value="<?= e(old('email', $user['email'])) ?>" placeholder="guide.moalboal@guidemate.local" required>
                    <span class="hint" style="font-size:0.78rem;display:block;margin-top:0.25rem;">Used for account notifications and bookings.</span>
                    <?php if (isset($errors['email'])): ?>
                        <div class="field-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Phone and Location (2 columns) -->
                <div class="profile-modal-grid-2">
                    <div class="profile-modal-field">
                        <label for="modalPhoneInput" class="profile-modal-label">Phone Number</label>
                        <input class="input" type="tel" id="modalPhoneInput" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" placeholder="e.g. +63 912 345 6789">
                        <?php if (isset($errors['phone'])): ?>
                            <div class="field-error"><?= e($errors['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-modal-field">
                        <label for="modalLocationInput" class="profile-modal-label">Location</label>
                        <input class="input" type="text" id="modalLocationInput" name="location" value="<?= e(old('location', $user['location'] ?? '')) ?>" placeholder="e.g. Moalboal, Cebu">
                        <?php if (isset($errors['location'])): ?>
                            <div class="field-error"><?= e($errors['location']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isGuide): ?>
                    <!-- Professional Information Header -->
                    <div style="margin:1.25rem 0 0.75rem;padding-bottom:0.35rem;border-bottom:1px solid var(--line);">
                        <h3 style="font-size:0.88rem;font-weight:700;color:var(--ink-700);text-transform:uppercase;letter-spacing:0.04em;margin:0;">Professional Information</h3>
                    </div>

                    <!-- Years of Experience & Languages Spoken (2 columns) -->
                    <div class="profile-modal-grid-2">
                        <div class="profile-modal-field">
                            <label for="modalExperienceInput" class="profile-modal-label">Years of Experience</label>
                            <input class="input" type="text" id="modalExperienceInput" name="experience" value="<?= e(old('experience', $user['experience'] ?? '')) ?>" placeholder="e.g. 3+ Years">
                            <?php if (isset($errors['experience'])): ?>
                                <div class="field-error"><?= e($errors['experience']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-modal-field">
                            <label for="modalLanguagesInput" class="profile-modal-label">Languages Spoken</label>
                            <input class="input" type="text" id="modalLanguagesInput" name="languages" value="<?= e(old('languages', $user['languages'] ?? '')) ?>" placeholder="e.g. English, Cebuano">
                            <?php if (isset($errors['languages'])): ?>
                                <div class="field-error"><?= e($errors['languages']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Areas of Specialization / Expertise -->
                    <div class="profile-modal-field">
                        <label for="modalSpecialtiesInput" class="profile-modal-label">Areas of Specialization / Expertise</label>
                        <input class="input" type="text" id="modalSpecialtiesInput" name="specialties" value="<?= e(old('specialties', $user['specialties'] ?? '')) ?>" placeholder="e.g. Snorkeling, Island Tours, Local Dining, Adventure Tours">
                        <span class="hint" style="font-size:0.78rem;display:block;margin-top:0.25rem;">Separate multiple specialties or areas of expertise with commas.</span>
                        <?php if (isset($errors['specialties'])): ?>
                            <div class="field-error"><?= e($errors['specialties']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- About You / Professional Biography -->
                <div class="profile-modal-field">
                    <label for="modalBioInput" class="profile-modal-label"><?= ($user['role'] === 'guide') ? 'About You / Professional Biography' : 'About you / Bio' ?></label>
                    <textarea class="input" id="modalBioInput" name="bio" rows="4" placeholder="<?= e($bioPlaceholder) ?>" style="min-height:96px;line-height:1.55;resize:vertical;"><?= e(old('bio', $user['bio'] ?? '')) ?></textarea>
                    <?php if (isset($errors['bio'])): ?>
                        <div class="field-error"><?= e($errors['bio']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="profile-modal-footer">
                <a href="<?= e(url('/profile')) ?>" class="btn btn-ghost" id="cancelProfileModalBtn">Cancel</a>
                <button type="submit" class="btn btn-primary" id="saveProfileBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('profileEditModal');
    var openBtn = document.getElementById('editProfileCornerBtn');
    var closeBtn = document.getElementById('closeProfileModalBtn');
    var cancelBtn = document.getElementById('cancelProfileModalBtn');
    var backdrop = document.getElementById('profileModalBackdrop');
    var nameInput = document.getElementById('modalNameInput');
    var avatarInput = document.getElementById('modalAvatarInput');
    var avatarPreview = document.getElementById('modalAvatarPreview');

    document.body.classList.add('profile-modal-open');
    document.documentElement.classList.add('profile-modal-open');
    if (nameInput) {
        setTimeout(function () {
            nameInput.focus();
        }, 60);
    }

    function closeModal() {
        document.body.classList.remove('profile-modal-open');
        document.documentElement.classList.remove('profile-modal-open');
        window.location.href = '<?= e(url('/profile')) ?>';
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeModal);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            if (modal) {
                modal.classList.add('is-active');
                modal.removeAttribute('aria-hidden');
                document.body.classList.add('profile-modal-open');
                document.documentElement.classList.add('profile-modal-open');
            }
            if (nameInput) {
                nameInput.focus();
            }
        });
    }

    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            var file = avatarInput.files && avatarInput.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    avatarPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>
