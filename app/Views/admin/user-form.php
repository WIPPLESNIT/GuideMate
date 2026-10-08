<?php
/**
 * @var array<string,mixed>|null $user
 * @var array<int,array<string,mixed>> $documents
 * @var array<string,string> $errors
 */

$editing = $user !== null;
$role = (string) old('role', $user['role'] ?? 'tourist');
$isActive = (int) old('is_active', (string) ($user['is_active'] ?? 1));
$guideStatus = (string) old('guide_status', $user['guide_status'] ?? 'none');
$isProvider = \App\Models\User::isProviderRole($role);
$documents = $documents ?? [];

$roleOptions = [
    'tourist' => 'Tourist',
    'guide' => 'Tour Guide',
    'hotel_admin' => 'Hotel Partner',
    'rental_admin' => 'Rental Partner',
    'admin' => 'Administrator',
];

$verifyBadge = [
    'approved' => ['cls' => 'pill-confirmed', 'label' => '✅ Verified'],
    'pending' => ['cls' => 'pill-pending', 'label' => '⏳ Under review'],
    'rejected' => ['cls' => 'pill-cancelled', 'label' => '⚠️ Rejected / Revoked'],
][$guideStatus] ?? ['cls' => 'pill-pending', 'label' => 'ℹ️ Not submitted'];
?>

<div class="breadcrumb" style="margin-top:0;">
    <a href="<?= e(url('/admin/users')) ?>">Users</a> &gt; <?= $editing ? 'Edit User' : 'Add User' ?>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h1 class="mb-0" style="margin:0;"><?= $editing ? 'Edit user: ' . e($user['name']) : 'Add new user' ?></h1>
    <a href="<?= e(url('/admin/users')) ?>" class="btn btn-ghost">← Back to users</a>
</div>

<div class="panel" style="max-width:760px;">
    <div class="panel-head">
        <h3><?= $editing ? 'User account details' : 'Create new account' ?></h3>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $editing ? e(url('/admin/users/' . $user['id'] . '/edit')) : e(url('/admin/users')) ?>" novalidate id="userForm">
            <?= csrf_field() ?>

            <div class="field-row">
                <label for="name">Full name <span class="req" style="color:#ef4444;">*</span></label>
                <input class="input <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e(old('name', $user['name'] ?? '')) ?>" placeholder="e.g. Juan Dela Cruz" required>
                <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
            </div>

            <div class="field-row">
                <label for="email">Email address <span class="req" style="color:#ef4444;">*</span></label>
                <input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" placeholder="name@example.com" required>
                <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>

            <?php if ($editing): ?>
                <div class="field-row">
                    <label for="password">Password <span class="hint" style="font-weight:normal;color:rgba(255,255,255,.55);">(Not editable)</span></label>
                    <input class="input" type="password" id="password" value="••••••••••••" disabled readonly style="background:rgba(255,255,255,.04);color:rgba(255,255,255,.45);cursor:not-allowed;border-color:rgba(255,255,255,.12);" title="Password cannot be edited here">
                    <div class="hint" style="font-size:.82rem;margin-top:.35rem;color:rgba(255,255,255,.55);">Password cannot be modified by admins. Users must manage their credentials directly.</div>
                </div>
            <?php else: ?>
                <div class="field-row">
                    <label for="password">Password <span class="req" style="color:#ef4444;">*</span></label>
                    <input class="input <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password" placeholder="Minimum 8 characters" required>
                    <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="form-grid-2">
                <div class="field-row">
                    <label for="role">Role <span class="req" style="color:#ef4444;">*</span></label>
                    <select class="input" id="role" name="role" style="background-color:#11252c;color:#ffffff;border-color:rgba(255,255,255,.25);">
                        <?php foreach ($roleOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $role === $key ? 'selected' : '' ?> style="background-color:#0d1e24;color:#ffffff;"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-row">
                    <label for="phone">Phone number</label>
                    <input class="input" id="phone" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" placeholder="e.g. +63 912 345 6789">
                </div>
            </div>

            <div class="field-row">
                <label>Account status <span class="req" style="color:#ef4444;">*</span></label>
                <div style="display:flex;gap:1.5rem;align-items:center;margin-top:.4rem;flex-wrap:wrap;">
                    <label style="display:inline-flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:normal;">
                        <input type="radio" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?>>
                        <span class="pill pill-approved">Active</span>
                        <span class="hint" style="font-size:.85rem;">User can freely access the account</span>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:normal;">
                        <input type="radio" name="is_active" value="0" <?= $isActive === 0 ? 'checked' : '' ?>>
                        <span class="pill pill-cancelled">Inactive</span>
                        <span class="hint" style="font-size:.85rem;">User can no longer access account (blocked from login)</span>
                    </label>
                </div>
                <?php if ($editing && !empty($user['suspension_reason'])): ?>
                    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:.6rem .85rem;margin-top:.75rem;font-size:.88rem;color:#fca5a5;">
                        <strong>Suspension reason:</strong> <?= e($user['suspension_reason']) ?>
                        <?php if (!empty($user['suspended_at'])): ?>
                            <span style="opacity:.7;font-size:.8rem;margin-left:.5rem;">(<?= e(date('M j, Y g:i A', strtotime($user['suspended_at']))) ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="field-row">
                <label for="location">Location</label>
                <input class="input" id="location" name="location" value="<?= e(old('location', $user['location'] ?? '')) ?>" placeholder="e.g. Cebu City, Philippines">
            </div>

            <div class="field-row">
                <label for="bio">Bio / About</label>
                <textarea class="input" id="bio" name="bio" rows="4" placeholder="Brief description or background..."><?= e(old('bio', $user['bio'] ?? '')) ?></textarea>
            </div>

            <?php if ($editing && $isProvider): ?>
                <div style="border-top:1px solid rgba(255,255,255,.1);padding-top:1.5rem;margin-top:1.5rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
                        <h4 style="margin:0;font-size:1.05rem;">Partner verification &amp; documents</h4>
                        <span class="pill <?= $verifyBadge['cls'] ?>"><?= e($verifyBadge['label']) ?></span>
                    </div>

                    <div class="field-row">
                        <label for="guide_status">Verification status</label>
                        <select class="input" id="guide_status" name="guide_status" style="background-color:#11252c;color:#ffffff;border-color:rgba(255,255,255,.25);">
                            <option value="approved" <?= $guideStatus === 'approved' ? 'selected' : '' ?> style="background-color:#0d1e24;color:#ffffff;">Verified (Approved)</option>
                            <option value="pending" <?= $guideStatus === 'pending' ? 'selected' : '' ?> style="background-color:#0d1e24;color:#ffffff;">Pending Review</option>
                            <option value="rejected" <?= $guideStatus === 'rejected' ? 'selected' : '' ?> style="background-color:#0d1e24;color:#ffffff;">Rejected / Revoked</option>
                            <option value="none" <?= $guideStatus === 'none' ? 'selected' : '' ?> style="background-color:#0d1e24;color:#ffffff;">None</option>
                        </select>
                    </div>

                    <?php if ($guideStatus === 'approved'): ?>
                        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:1rem;margin-top:1rem;">
                            <div style="font-weight:700;color:#fca5a5;margin-bottom:.3rem;">Revoke verification</div>
                            <p class="hint" style="margin:0 0 .8rem;font-size:.85rem;">Revoking removes this partner's verified badge and listings approval. They will need to re-submit documents.</p>
                            <button type="submit" form="revokeForm" class="btn btn-ghost btn-sm" style="color:var(--coral-500);border-color:rgba(239,68,68,.4);">⚠️ Revoke Verification</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($documents !== []): ?>
                        <div style="margin-top:1.5rem;">
                            <label style="display:block;margin-bottom:.65rem;font-weight:600;color:#fff;">Uploaded documents</label>
                            <div class="admin-doc-list" style="display:flex;flex-direction:column;gap:.75rem;">
                                <?php foreach ($documents as $d): ?>
                                    <div class="admin-doc-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 16px;display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                                        <span class="admin-doc-type" style="background:#e2ede6;color:#1e3a2b;font-weight:700;font-size:0.88rem;padding:6px 14px;border-radius:999px;line-height:1.2;display:inline-flex;align-items:center;">
                                            <?= e(\App\Models\GuideDocument::typeLabel($d['doc_type'])) ?>
                                        </span>
                                        <a href="<?= e(url($d['file_path'])) ?>" target="_blank" rel="noopener" class="admin-doc-link" style="color:#0b7843;font-weight:700;font-size:0.95rem;text-decoration:none;word-break:break-all;display:inline-flex;align-items:center;gap:4px;">
                                            📎 <?= e($d['label'] ?: basename($d['file_path'])) ?> ↗
                                        </a>
                                        <?php if ($guideStatus === 'approved'): ?>
                                            <span class="admin-doc-status" style="background:#dbf1e5;color:#0b7843;font-weight:700;font-size:0.85rem;padding:6px 14px;border-radius:999px;line-height:1.2;display:inline-flex;align-items:center;">✓ Verified</span>
                                        <?php elseif ($guideStatus === 'pending'): ?>
                                            <span class="admin-doc-status" style="background:#fef3c7;color:#92400e;font-weight:700;font-size:0.85rem;padding:6px 14px;border-radius:999px;line-height:1.2;display:inline-flex;align-items:center;">⏳ Under review</span>
                                        <?php elseif ($guideStatus === 'rejected'): ?>
                                            <span class="admin-doc-status" style="background:#fee2e2;color:#b91c1c;font-weight:700;font-size:0.85rem;padding:6px 14px;border-radius:999px;line-height:1.2;display:inline-flex;align-items:center;">⚠️ Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="margin-top:1.2rem;">
                            <label style="display:block;margin-bottom:.4rem;font-weight:600;color:#fff;">Uploaded documents</label>
                            <p class="hint" style="margin:0;color:rgba(255,255,255,.55);">No documents uploaded yet by this partner.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:2rem;flex-wrap:wrap;border-top:1px solid rgba(255,255,255,.08);padding-top:1.5rem;">
                <div style="display:flex;align-items:center;gap:.75rem;">
                    <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create user' ?></button>
                    <a href="<?= e(url('/admin/users')) ?>" class="btn btn-ghost">Cancel</a>
                </div>

                <?php if ($editing && (int) $user['id'] !== (int) \App\Core\AdminAuth::id()): ?>
                    <button type="submit" form="deleteForm" class="btn btn-ghost btn-sm" style="color:var(--coral-500);border-color:rgba(239,68,68,.3);" onclick="return confirm('Are you sure you want to permanently delete <?= e(addslashes($user['name'])) ?>? This cannot be undone.');">
                        Delete user
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($editing): ?>
            <?php if ($isProvider && $guideStatus === 'approved'): ?>
                <form id="revokeForm" method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/revoke')) ?>" onsubmit="return confirm('Revoke this partner\'s verification? They will need to re-submit documents.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return" value="/admin/users/<?= (int) $user['id'] ?>/edit">
                </form>
            <?php endif; ?>

            <?php if ((int) $user['id'] !== (int) \App\Core\AdminAuth::id()): ?>
                <form id="deleteForm" method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/delete')) ?>">
                    <?= csrf_field() ?>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
