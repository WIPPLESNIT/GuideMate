<?php
/** @var array<int,array<string,mixed>> $users */

// Labels + pill colours for each account type so the roles read clearly and
// are visually distinct in the table.
$roleLabels = [
    'tourist' => 'Tourist',
    'guide' => 'Tour Guide',
    'hotel_admin' => 'Hotel Partner',
    'rental_admin' => 'Rental Partner',
    'admin' => 'Admin',
];
$rolePillMap = [
    'admin' => 'completed',
    'guide' => 'confirmed',
    'hotel_admin' => 'refunded',
    'rental_admin' => 'resolved_warning',
    'tourist' => 'pending',
];

// Count how many users fall under each role for the filter tabs.
$roleCounts = [];
$warnedCount = 0;
foreach (array_keys($roleLabels) as $rk) {
    $roleCounts[$rk] = 0;
}
foreach ($users as $u) {
    $r = (string) ($u['role'] ?? 'tourist');
    if (!isset($roleCounts[$r])) {
        $roleCounts[$r] = 0;
    }
    $roleCounts[$r]++;
    if (\App\Models\User::isProviderRole($r) && (int) ($u['guide_warned'] ?? 0) === 1) {
        $warnedCount++;
    }
}
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h1 style="margin:0;">Manage users</h1>
    <a href="<?= e(url('/admin/users/create')) ?>" class="btn btn-primary btn-sm">+ Add User</a>
</div>

<div class="role-tabs" role="tablist">
    <button type="button" class="role-tab is-active" data-role-filter="all">
        All <span class="count"><?= count($users) ?></span>
    </button>
    <?php foreach ($roleLabels as $roleKey => $roleName): ?>
        <?php if (($roleCounts[$roleKey] ?? 0) > 0): ?>
            <button type="button" class="role-tab" data-role-filter="<?= e($roleKey) ?>">
                <?= e($roleName) ?>s <span class="count"><?= (int) $roleCounts[$roleKey] ?></span>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($warnedCount > 0): ?>
        <button type="button" class="role-tab" data-role-filter="warned">
            Restricted <span class="count"><?= (int) $warnedCount ?></span>
        </button>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <table class="table table-users">
            <thead>
                <tr>
                    <th class="col-name">Name</th>
                    <th class="col-email">Email</th>
                    <th class="col-joined">Joined</th>
                    <th class="col-status">Status</th>
                    <th class="col-actions"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php $role = (string) ($u['role'] ?? 'tourist'); ?>
                <?php $isWarned = \App\Models\User::isProviderRole($role) && (int) ($u['guide_warned'] ?? 0) === 1; ?>
                <tr data-role="<?= e($role) ?>" data-warned="<?= $isWarned ? '1' : '0' ?>">
                    <td class="col-name">
                        <div class="table-user-cell">
                            <img src="<?= e(img_src($u['avatar'] ?? null, 'avatar' . $u['id'])) ?>" alt="">
                            <span class="table-user-name" title="<?= e($u['name']) ?>"><?= e($u['name']) ?></span>
                        </div>
                    </td>
                    <td class="col-email">
                        <?php if (!empty($u['oauth_provider'])): ?>
                            <?php $prov = $u['oauth_provider'] === 'facebook' ? 'Facebook' : 'Google'; ?>
                            <span class="table-user-email" title="Signed in with <?= e($prov) ?> — email kept private">🔒 <?= e($prov) ?> account (private)</span>
                        <?php else: ?>
                            <span class="table-user-email" title="<?= e($u['email']) ?>"><?= e($u['email']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="col-joined"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td class="col-status">
                        <?php if ($isWarned): ?>
                            <span class="pill pill-disputed">Restricted</span>
                        <?php elseif ((int) $u['is_active']): ?>
                            <span class="pill pill-approved">Active</span>
                        <?php else: ?>
                            <span class="pill pill-cancelled" <?= !empty($u['suspension_reason']) ? 'title="Reason: ' . e($u['suspension_reason']) . '" style="cursor:help;"' : '' ?>>Suspended</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-actions">
                        <div class="user-actions">
                            <a href="<?= e(url('/admin/users/' . $u['id'] . '/edit')) ?>" class="btn btn-ghost btn-sm">Edit</a>
                            <?php if ((int) $u['id'] !== (int) \App\Core\AdminAuth::id() && $u['role'] !== 'admin'): ?>
                                <?php if ((int) $u['is_active'] === 1): ?>
                                    <button type="button"
                                            class="btn btn-ghost btn-sm btn-icon-action btn-suspend-user"
                                            title="Suspend <?= e($u['name']) ?>"
                                            aria-label="Suspend <?= e($u['name']) ?>"
                                            style="color:#f59e0b;border-color:rgba(245,158,11,.35);"
                                            data-user-id="<?= (int) $u['id'] ?>"
                                            data-user-name="<?= e($u['name']) ?>"
                                            data-user-email="<?= e($u['email']) ?>">
                                        <?= admin_icon('suspend', 15) ?>
                                    </button>
                                <?php else: ?>
                                    <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/unsuspend')) ?>" style="display:inline;" onsubmit="return confirm('Reactivate <?= e(addslashes($u['name'])) ?>? The user will be able to log in again.');">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                class="btn btn-ghost btn-sm btn-icon-action"
                                                title="Reactivate / Unsuspend <?= e($u['name']) ?>"
                                                aria-label="Reactivate <?= e($u['name']) ?>"
                                                style="color:#10b981;border-color:rgba(16,185,129,.35);">
                                            <?= admin_icon('unsuspend', 15) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" onsubmit="return confirm('Are you sure you want to delete <?= e(addslashes($u['name'])) ?>? This cannot be undone.');" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--coral-500);border-color:rgba(239,68,68,.3);">Delete</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($isWarned): ?>
                                <?= \App\Core\View::partial('partials/admin-unrestrict', [
                                    'id' => (int) $u['id'],
                                    'name' => (string) $u['name'],
                                    'return' => '/admin/users',
                                ]) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
                <tr id="usersEmptyRow" hidden>
                    <td colspan="5" class="empty-state" style="text-align:center;padding:2rem;">No users in this group yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Suspend User Modal -->
<div class="gm-modal" id="suspendUserModal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" data-close-modal></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="suspendModalTitle" style="max-width:500px;background:#ffffff;border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.3);border:1px solid #e2e8f0;overflow:hidden;">
        <div class="gm-modal-head" style="padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#ffffff;">
            <div style="display:flex;align-items:center;gap:.6rem;">
                <span style="color:#d97706;display:inline-flex;align-items:center;"><?= admin_icon('alert-triangle', 20) ?></span>
                <h3 id="suspendModalTitle" style="margin:0;color:#0f172a;font-size:1.08rem;font-weight:700;">Suspend User Account</h3>
            </div>
            <button type="button" class="gm-modal-close" data-close-modal aria-label="Close" style="color:#94a3b8;font-size:1.5rem;cursor:pointer;background:none;border:none;padding:0 4px;line-height:1;">&times;</button>
        </div>
        <form method="post" id="suspendUserForm" action="" novalidate>
            <?= csrf_field() ?>
            <div class="gm-modal-body" style="padding:1.25rem 1.25rem;">
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.85rem 1rem;margin-bottom:1.15rem;">
                    <div style="font-weight:700;color:#92400e;margin-bottom:.3rem;display:flex;align-items:center;gap:.4rem;font-size:.92rem;">
                        <span>⚠️</span> Warning: Account Suspension
                    </div>
                    <p style="margin:0;font-size:.87rem;color:#78350f;line-height:1.45;">
                        You are about to suspend <strong id="suspendUserName" style="color:#451a03;font-weight:700;"></strong> (<span id="suspendUserEmail" style="color:#92400e;"></span>).
                        Suspending this user will immediately deactivate their account, revoke their active access, and block them from logging into GuideMate.
                    </p>
                </div>

                <div class="field-row" style="margin-bottom:0;">
                    <label for="suspensionReasonInput" style="display:block;margin-bottom:.45rem;font-weight:700;color:#0f172a;font-size:.9rem;">
                        Reason for suspension <span class="req" style="color:#ef4444;">*</span>
                    </label>
                    <textarea class="input"
                              id="suspensionReasonInput"
                              name="reason"
                              rows="3"
                              style="width:100%;box-sizing:border-box;background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;color:#0f172a;font-size:.9rem;padding:.55rem .75rem;resize:vertical;line-height:1.4;"
                              placeholder="Please provide the reason for suspending this user (e.g. Terms of Service violation, fraudulent behavior, safety report)..."
                              required></textarea>
                    <div id="suspensionReasonError" style="color:#ef4444;font-size:.82rem;margin-top:.35rem;display:none;font-weight:600;">A reason for suspension is required.</div>
                    <div class="hint" style="font-size:.8rem;margin-top:.4rem;color:#64748b;">
                        This reason is recorded in the admin audit logs and provided to the user upon failed login.
                    </div>
                </div>
            </div>
            <div class="gm-modal-foot" style="border-top:1px solid #e2e8f0;padding:.85rem 1.25rem;display:flex;justify-content:flex-end;align-items:center;gap:.6rem;margin:0;background:#f8fafc;">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal style="color:#475569;border:1px solid #cbd5e1;background:#ffffff;font-size:.84rem;padding:.35rem .85rem;border-radius:6px;font-weight:600;min-width:auto;cursor:pointer;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#ffffff;border:none;font-weight:600;font-size:.84rem;padding:.35rem .85rem;border-radius:6px;min-width:auto;cursor:pointer;box-shadow:0 1px 2px rgba(220,38,38,.2);">
                    Suspend User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('.role-tab[data-role-filter]');
    var rows = document.querySelectorAll('tr[data-role]');
    var emptyRow = document.getElementById('usersEmptyRow');

    function applyFilter(filter) {
        var visible = 0;
        rows.forEach(function (row) {
            var show = filter === 'all'
                || (filter === 'warned' && row.getAttribute('data-warned') === '1')
                || row.getAttribute('data-role') === filter;
            row.hidden = !show;
            if (show) { visible++; }
        });
        if (emptyRow) { emptyRow.hidden = visible > 0; }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            applyFilter(tab.getAttribute('data-role-filter'));
        });
    });

    // Suspend Modal Handling
    var suspendModal = document.getElementById('suspendUserModal');
    var suspendForm = document.getElementById('suspendUserForm');
    var suspendNameEl = document.getElementById('suspendUserName');
    var suspendEmailEl = document.getElementById('suspendUserEmail');
    var suspendReasonInput = document.getElementById('suspensionReasonInput');
    var suspendReasonError = document.getElementById('suspensionReasonError');

    function openSuspendModal(userId, userName, userEmail) {
        suspendForm.action = '<?= e(url('/admin/users')) ?>/' + userId + '/suspend';
        suspendNameEl.textContent = userName;
        suspendEmailEl.textContent = userEmail;
        suspendReasonInput.value = '';
        suspendReasonInput.classList.remove('is-invalid');
        suspendReasonError.style.display = 'none';

        suspendModal.hidden = false;
        suspendModal.setAttribute('aria-hidden', 'false');
        setTimeout(function () {
            suspendReasonInput.focus();
        }, 50);
    }

    function closeSuspendModal() {
        suspendModal.hidden = true;
        suspendModal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.btn-suspend-user').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-user-id');
            var name = btn.getAttribute('data-user-name');
            var email = btn.getAttribute('data-user-email');
            openSuspendModal(id, name, email);
        });
    });

    suspendModal.querySelectorAll('[data-close-modal]').forEach(function (el) {
        el.addEventListener('click', closeSuspendModal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !suspendModal.hidden) {
            closeSuspendModal();
        }
    });

    suspendForm.addEventListener('submit', function (e) {
        if (!suspendReasonInput.value.trim()) {
            e.preventDefault();
            suspendReasonInput.classList.add('is-invalid');
            suspendReasonError.style.display = 'block';
            suspendReasonInput.focus();
        }
    });

    suspendReasonInput.addEventListener('input', function () {
        if (suspendReasonInput.value.trim()) {
            suspendReasonInput.classList.remove('is-invalid');
            suspendReasonError.style.display = 'none';
        }
    });
})();
</script>
