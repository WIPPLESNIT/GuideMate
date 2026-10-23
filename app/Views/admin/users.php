<?php
/** @var array<int,array<string,mixed>> $users */
$pendingAppeals = $pendingAppeals ?? [];

// Labels + pill colours for each account type so the roles read clearly and
// are visually distinct in the table.
$roleLabels = [
    'tourist' => 'Tourist',
    'guide' => 'Tour Guide',
    'hotel_admin' => 'Hotel Partner',
    'rental_provider' => 'Rental Partner',
    'rental_admin' => 'Rental Partner',
    'admin' => 'Admin',
];
$rolePillMap = [
    'admin' => 'completed',
    'guide' => 'confirmed',
    'hotel_admin' => 'refunded',
    'rental_provider' => 'resolved_warning',
    'rental_admin' => 'resolved_warning',
    'tourist' => 'pending',
];

// Count how many users fall under each role for the filter options.
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

// Status counts for dropdown badges
$statusCounts = [
    'active' => 0,
    'suspended' => 0,
    'warned' => $warnedCount,
    'appeal' => count($pendingAppeals),
];
foreach ($users as $u) {
    $r = (string) ($u['role'] ?? 'tourist');
    $isW = \App\Models\User::isProviderRole($r) && (int) ($u['guide_warned'] ?? 0) === 1;
    if (!$isW) {
        if ((int) ($u['is_active'] ?? 1) === 1) {
            $statusCounts['active']++;
        } else {
            $statusCounts['suspended']++;
        }
    }
}
?>
<div class="section-head" style="margin-bottom:1.25rem;">
    <div>
        <h1 class="mb-0">Manage users</h1>
    </div>
</div>

<!-- Filtering Toolbar matching reference layout -->
<div class="users-toolbar">
    <div class="users-filters-group">
        <!-- Search Field -->
        <div class="filter-control users-search-wrap">
            <span class="filter-icon" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </span>
            <input type="text"
                   id="userSearchInput"
                   class="filter-input users-search-input"
                   placeholder="Search"
                   aria-label="Search accounts"
                   autocomplete="off"
                   spellcheck="false">
            <button type="button" id="userSearchClear" class="users-search-clear" aria-label="Clear search" hidden>&times;</button>
        </div>

        <!-- Role Custom Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="role">
            <button type="button"
                    class="filter-dropdown-btn"
                    id="roleFilterBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="roleFilterPopover"
                    aria-label="Filter by role">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="roleFilterLabel">Role</span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="roleFilterPopover" role="listbox" aria-labelledby="roleFilterBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Role</span>
                    <button type="button" class="filter-popover-reset" data-reset-target="userRoleFilter">Reset</button>
                </div>
                <div class="filter-popover-options">
                    <div class="filter-popover-item is-selected" role="option" aria-selected="true" data-value="all" tabindex="0">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">All Roles</span>
                        <span class="filter-item-count"><?= count($users) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="tourist" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Tourists</span>
                        <span class="filter-item-count"><?= (int) ($roleCounts['tourist'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="guide" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Tour Guides</span>
                        <span class="filter-item-count"><?= (int) ($roleCounts['guide'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="hotel_admin" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Hotel Partners</span>
                        <span class="filter-item-count"><?= (int) ($roleCounts['hotel_admin'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="rental_provider" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Rental Partners</span>
                        <span class="filter-item-count"><?= (int) ($roleCounts['rental_provider'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="admin" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Admins</span>
                        <span class="filter-item-count"><?= (int) ($roleCounts['admin'] ?? 0) ?></span>
                    </div>
                    <?php if ($warnedCount > 0): ?>
                        <div class="filter-popover-item" role="option" aria-selected="false" data-value="warned" tabindex="-1">
                            <span class="filter-item-indicator" aria-hidden="true"></span>
                            <span class="filter-item-text">Restricted</span>
                            <span class="filter-item-count"><?= (int) $warnedCount ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Synchronized backing select for accessibility and automated test compatibility -->
            <select id="userRoleFilter" class="filter-select filter-select-hidden" aria-hidden="true" tabindex="-1">
                <option value="all" selected>Role</option>
                <option value="tourist">Tourists</option>
                <option value="guide">Tour Guides</option>
                <option value="hotel_admin">Hotel Partners</option>
                <option value="rental_provider">Rental Partners</option>
                <option value="admin">Admins</option>
                <?php if ($warnedCount > 0): ?>
                    <option value="warned">Restricted</option>
                <?php endif; ?>
            </select>
        </div>

        <!-- Status Custom Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="status">
            <button type="button"
                    class="filter-dropdown-btn"
                    id="statusFilterBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="statusFilterPopover"
                    aria-label="Filter by status">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="6"></circle>
                        <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="statusFilterLabel">Status</span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="statusFilterPopover" role="listbox" aria-labelledby="statusFilterBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Status</span>
                    <button type="button" class="filter-popover-reset" data-reset-target="userStatusFilter">Reset</button>
                </div>
                <div class="filter-popover-options">
                    <div class="filter-popover-item is-selected" role="option" aria-selected="true" data-value="all" tabindex="0">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">All Statuses</span>
                        <span class="filter-item-count"><?= count($users) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="active" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Active</span>
                        <span class="filter-item-count"><?= (int) ($statusCounts['active'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="suspended" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Suspended</span>
                        <span class="filter-item-count"><?= (int) ($statusCounts['suspended'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="warned" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Restricted</span>
                        <span class="filter-item-count"><?= (int) ($statusCounts['warned'] ?? 0) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="appeal" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Appeal Pending</span>
                        <span class="filter-item-count"><?= (int) ($statusCounts['appeal'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            <!-- Synchronized backing select for accessibility and automated test compatibility -->
            <select id="userStatusFilter" class="filter-select filter-select-hidden" aria-hidden="true" tabindex="-1">
                <option value="all" selected>Status</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="warned">Restricted</option>
                <option value="appeal">Appeal Pending</option>
            </select>
        </div>

        <!-- Date Custom Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="date">
            <button type="button"
                    class="filter-dropdown-btn"
                    id="dateFilterBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="dateFilterPopover"
                    aria-label="Filter by date joined">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="dateFilterLabel">Date</span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="dateFilterPopover" role="listbox" aria-labelledby="dateFilterBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Date</span>
                    <button type="button" class="filter-popover-reset" data-reset-target="userDateFilter">Reset</button>
                </div>
                <div class="filter-popover-options">
                    <div class="filter-popover-item is-selected" role="option" aria-selected="true" data-value="all" tabindex="0">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">All Dates</span>
                        <span class="filter-item-count"><?= count($users) ?></span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="today" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Today</span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="7days" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Last 7 Days</span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="30days" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Last 30 Days</span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="this_year" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">This Year</span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="sort_desc" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Newest First</span>
                    </div>
                    <div class="filter-popover-item" role="option" aria-selected="false" data-value="sort_asc" tabindex="-1">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Oldest First</span>
                    </div>
                </div>
            </div>
            <!-- Synchronized backing select for accessibility and automated test compatibility -->
            <select id="userDateFilter" class="filter-select filter-select-hidden" aria-hidden="true" tabindex="-1">
                <option value="all" selected>Date</option>
                <option value="today">Today</option>
                <option value="7days">Last 7 Days</option>
                <option value="30days">Last 30 Days</option>
                <option value="this_year">This Year</option>
                <option value="sort_desc">Newest First</option>
                <option value="sort_asc">Oldest First</option>
            </select>
        </div>
    </div>

    <!-- Actions / Add User -->
    <div class="users-toolbar-actions">
        <a href="<?= e(url('/admin/users/create')) ?>" class="btn btn-primary btn-sm btn-add-user">+ Add User</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <table class="table table-users">
            <thead>
                <tr>
                    <th class="col-name">Full Name</th>
                    <th class="col-email">Email</th>
                    <th class="col-status">Status</th>
                    <th class="col-role">Role</th>
                    <th class="col-joined">Joined Date</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php $role = (string) ($u['role'] ?? 'tourist'); ?>
                <?php $isWarned = \App\Models\User::isProviderRole($role) && (int) ($u['guide_warned'] ?? 0) === 1; ?>
                <tr data-role="<?= e($role) ?>"
                    data-warned="<?= $isWarned ? '1' : '0' ?>"
                    data-status="<?= $isWarned ? 'warned' : ((int) $u['is_active'] ? 'active' : 'suspended') ?>"
                    data-has-appeal="<?= isset($pendingAppeals[(int) $u['id']]) ? '1' : '0' ?>"
                    data-name="<?= e($u['name']) ?>"
                    data-email="<?= e($u['email']) ?>"
                    data-role-label="<?= e($roleLabels[$role] ?? $role) ?>"
                    data-created="<?= strtotime((string) $u['created_at']) ?>">
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
                    <td class="col-status">
                        <?php if ($isWarned): ?>
                            <span class="pill pill-disputed">Restricted</span>
                        <?php elseif ((int) $u['is_active']): ?>
                            <span class="pill pill-approved">Active</span>
                        <?php else: ?>
                            <span class="pill pill-cancelled" <?= !empty($u['suspension_reason']) ? 'title="Reason: ' . e($u['suspension_reason']) . '" style="cursor:help;"' : '' ?>>Suspended</span>
                            <?php if (isset($pendingAppeals[(int) $u['id']])): ?>
                                <span class="pill pill-pending" style="margin-top:3px;display:inline-block;cursor:pointer;" title="Appeal: <?= e($pendingAppeals[(int) $u['id']]['appeal_message']) ?>">Appeal Pending</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="col-role">
                        <span class="pill <?= e($rolePillMap[$role] ?? 'pending') ?>">
                            <?= e($roleLabels[$role] ?? ucfirst($role)) ?>
                        </span>
                    </td>
                    <td class="col-joined"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td class="col-actions">
                        <div class="user-action-menu-wrap">
                            <button type="button"
                                    class="btn-user-menu-trigger"
                                    id="userActionBtn_<?= (int) $u['id'] ?>"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    aria-controls="userActionMenu_<?= (int) $u['id'] ?>"
                                    title="Actions for <?= e($u['name']) ?>"
                                    aria-label="Actions for <?= e($u['name']) ?>">
                                <?= admin_icon('more-vertical', 16) ?>
                            </button>

                            <div class="user-action-dropdown"
                                 id="userActionMenu_<?= (int) $u['id'] ?>"
                                 role="menu"
                                 aria-labelledby="userActionBtn_<?= (int) $u['id'] ?>">

                                <!-- Edit -->
                                <a href="<?= e(url('/admin/users/' . $u['id'] . '/edit')) ?>" class="user-action-item user-action-edit" role="menuitem">
                                    <span class="action-item-icon" aria-hidden="true"><?= admin_icon('edit', 15) ?></span>
                                    <span class="action-item-label">Edit User</span>
                                </a>

                                <!-- Documents (for service providers) -->
                                <?php if (\App\Models\User::isProviderRole($role)): ?>
                                    <a href="<?= e(url('/admin/users/' . $u['id'] . '/documents')) ?>" class="user-action-item user-action-docs" role="menuitem">
                                        <span class="action-item-icon" aria-hidden="true"><?= admin_icon('documents', 15) ?></span>
                                        <span class="action-item-label">View Documents</span>
                                    </a>
                                <?php endif; ?>

                                <!-- Unrestrict (if restricted/warned) -->
                                <?php if ($isWarned): ?>
                                    <form method="post" action="<?= e(url('/admin/users/' . (int) $u['id'] . '/clear-warning')) ?>" class="user-action-form js-unrestrict" data-name="<?= e($u['name']) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return" value="/admin/users">
                                        <button type="submit" class="user-action-item user-action-unrestrict" role="menuitem">
                                            <span class="action-item-icon" aria-hidden="true"><?= admin_icon('unlock', 15) ?></span>
                                            <span class="action-item-label">Remove Restriction</span>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Suspend or Reactivate (non-admin, not self) -->
                                <?php if ((int) $u['id'] !== (int) \App\Core\AdminAuth::id() && $u['role'] !== 'admin'): ?>
                                    <div class="action-dropdown-divider" role="separator"></div>

                                    <?php if ((int) $u['is_active'] === 1): ?>
                                        <button type="button"
                                                class="user-action-item user-action-suspend btn-suspend-user"
                                                role="menuitem"
                                                data-user-id="<?= (int) $u['id'] ?>"
                                                data-user-name="<?= e($u['name']) ?>"
                                                data-user-email="<?= e($u['email']) ?>">
                                            <span class="action-item-icon" aria-hidden="true"><?= admin_icon('suspend', 15) ?></span>
                                            <span class="action-item-label">Suspend Account</span>
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/unsuspend')) ?>" class="user-action-form" onsubmit="return confirm('Reactivate <?= e(addslashes($u['name'])) ?>? The user will be able to log in again.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="user-action-item user-action-unsuspend" role="menuitem">
                                                <span class="action-item-icon" aria-hidden="true"><?= admin_icon('unsuspend', 15) ?></span>
                                                <span class="action-item-label">Reactivate Account</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Delete -->
                                    <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" class="user-action-form" onsubmit="return confirm('Are you sure you want to delete <?= e(addslashes($u['name'])) ?>? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="user-action-item user-action-delete" role="menuitem">
                                            <span class="action-item-icon" aria-hidden="true"><?= admin_icon('trash', 15) ?></span>
                                            <span class="action-item-label">Delete Account</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
                <tr id="usersEmptyRow" hidden>
                    <td colspan="6" class="empty-state users-empty-state" style="text-align:center;padding:2.5rem 1rem;">
                        <div style="font-size:1.5rem;margin-bottom:0.4rem;opacity:0.6;">🔍</div>
                        <div id="usersEmptyMsg" style="font-weight:600;font-size:0.95rem;color:rgba(255,255,255,0.8);">No accounts found.</div>
                        <div id="usersEmptySub" class="hint" style="font-size:0.82rem;margin-top:0.25rem;">Try adjusting your search query or clearing active filters.</div>
                        <button type="button" id="clearAllFiltersBtn" class="btn btn-ghost btn-sm" style="margin-top:0.85rem;border-radius:999px;font-size:0.82rem;padding:0.4rem 1rem;color:var(--coral-500);border-color:rgba(31,170,78,.35);background:rgba(31,170,78,.08);">Clear all filters</button>
                    </td>
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
    var searchInput = document.getElementById('userSearchInput');
    var searchClear = document.getElementById('userSearchClear');
    var roleFilter = document.getElementById('userRoleFilter');
    var statusFilter = document.getElementById('userStatusFilter');
    var dateFilter = document.getElementById('userDateFilter');
    var tableBody = document.querySelector('.table-users tbody');
    var emptyRow = document.getElementById('usersEmptyRow');
    var emptyMsg = document.getElementById('usersEmptyMsg');

    var rows = Array.prototype.slice.call(document.querySelectorAll('tr[data-role]'));

    function applyFilter() {
        closeUserMenu();
        var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
        var selectedRole = roleFilter ? roleFilter.value : 'all';
        var selectedStatus = statusFilter ? statusFilter.value : 'all';
        var selectedDate = dateFilter ? dateFilter.value : 'all';

        // Update active filter visual styling
        if (roleFilter) { roleFilter.classList.toggle('is-filtered', selectedRole !== 'all'); }
        if (statusFilter) { statusFilter.classList.toggle('is-filtered', selectedStatus !== 'all'); }
        if (dateFilter) { dateFilter.classList.toggle('is-filtered', selectedDate !== 'all'); }
        if (searchClear) { searchClear.hidden = q.length === 0; }

        var now = Math.floor(Date.now() / 1000);
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var startOfToday = Math.floor(today.getTime() / 1000);
        var sevenDaysAgo = now - (7 * 86400);
        var thirtyDaysAgo = now - (30 * 86400);
        var startOfYear = Math.floor(new Date(today.getFullYear(), 0, 1).getTime() / 1000);

        var visibleCount = 0;

        rows.forEach(function (row) {
            // 1. Role match
            var role = row.getAttribute('data-role') || '';
            var isWarned = row.getAttribute('data-warned') === '1';
            var matchesRole = selectedRole === 'all'
                || (selectedRole === 'warned' && isWarned)
                || role === selectedRole;

            if (!matchesRole) {
                row.hidden = true;
                return;
            }

            // 2. Status match
            var status = row.getAttribute('data-status') || '';
            var hasAppeal = row.getAttribute('data-has-appeal') === '1';
            var matchesStatus = selectedStatus === 'all'
                || (selectedStatus === 'appeal' && hasAppeal)
                || (selectedStatus === 'warned' && isWarned)
                || status === selectedStatus;

            if (!matchesStatus) {
                row.hidden = true;
                return;
            }

            // 3. Date match
            var created = parseInt(row.getAttribute('data-created') || '0', 10);
            var matchesDate = true;
            if (selectedDate === 'today') {
                matchesDate = created >= startOfToday;
            } else if (selectedDate === '7days') {
                matchesDate = created >= sevenDaysAgo;
            } else if (selectedDate === '30days') {
                matchesDate = created >= thirtyDaysAgo;
            } else if (selectedDate === 'this_year') {
                matchesDate = created >= startOfYear;
            }

            if (!matchesDate) {
                row.hidden = true;
                return;
            }

            // 4. Search text match (case-insensitive)
            var matchesSearch = true;
            if (q !== '') {
                var name = (row.getAttribute('data-name') || '').toLowerCase();
                var email = (row.getAttribute('data-email') || '').toLowerCase();
                var roleLabel = (row.getAttribute('data-role-label') || '').toLowerCase();
                var statusText = (row.querySelector('.col-status') ? row.querySelector('.col-status').textContent : '').toLowerCase();

                matchesSearch = name.indexOf(q) !== -1
                    || email.indexOf(q) !== -1
                    || roleLabel.indexOf(q) !== -1
                    || statusText.indexOf(q) !== -1;
            }

            row.hidden = !matchesSearch;
            if (matchesSearch) {
                visibleCount++;
            }
        });

        // 5. Handle sorting by date if requested
        if (selectedDate === 'sort_desc' || selectedDate === 'sort_asc') {
            var sortAsc = selectedDate === 'sort_asc';
            rows.sort(function (a, b) {
                var tA = parseInt(a.getAttribute('data-created') || '0', 10);
                var tB = parseInt(b.getAttribute('data-created') || '0', 10);
                return sortAsc ? (tA - tB) : (tB - tA);
            });
            rows.forEach(function (row) {
                if (emptyRow && tableBody) {
                    tableBody.insertBefore(row, emptyRow);
                } else if (tableBody) {
                    tableBody.appendChild(row);
                }
            });
        }

        if (emptyRow) {
            emptyRow.hidden = visibleCount > 0;
            if (emptyMsg) {
                emptyMsg.textContent = 'No accounts found.';
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
    }
    if (searchClear) {
        searchClear.addEventListener('click', function () {
            searchInput.value = '';
            searchClear.hidden = true;
            searchInput.focus();
            applyFilter();
        });
    }
    if (roleFilter) {
        roleFilter.addEventListener('change', applyFilter);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilter);
    }
    if (dateFilter) {
        dateFilter.addEventListener('change', applyFilter);
    }

    // Custom Filter Dropdowns Interactive Controller
    var activeFilterPopover = null;
    var activeFilterTrigger = null;

    function closeAllFilterPopovers() {
        document.querySelectorAll('.filter-dropdown-popover').forEach(function (pop) {
            pop.classList.remove('open');
            pop.hidden = true;
        });
        document.querySelectorAll('.filter-dropdown-btn').forEach(function (b) {
            b.setAttribute('aria-expanded', 'false');
        });
        activeFilterPopover = null;
        activeFilterTrigger = null;
    }

    var filterConfigs = {
        role: {
            btnId: 'roleFilterBtn',
            popoverId: 'roleFilterPopover',
            labelId: 'roleFilterLabel',
            pillId: 'roleActivePill',
            selectId: 'userRoleFilter',
            defaultLabel: 'Role',
            prefix: 'Role: '
        },
        status: {
            btnId: 'statusFilterBtn',
            popoverId: 'statusFilterPopover',
            labelId: 'statusFilterLabel',
            pillId: 'statusActivePill',
            selectId: 'userStatusFilter',
            defaultLabel: 'Status',
            prefix: 'Status: '
        },
        date: {
            btnId: 'dateFilterBtn',
            popoverId: 'dateFilterPopover',
            labelId: 'dateFilterLabel',
            pillId: 'dateActivePill',
            selectId: 'userDateFilter',
            defaultLabel: 'Date',
            prefix: 'Date: '
        }
    };

    Object.keys(filterConfigs).forEach(function (key) {
        var cfg = filterConfigs[key];
        var btn = document.getElementById(cfg.btnId);
        var pop = document.getElementById(cfg.popoverId);
        var labelEl = document.getElementById(cfg.labelId);
        var pillEl = document.getElementById(cfg.pillId);
        var selectEl = document.getElementById(cfg.selectId);
        if (!btn || !pop || !selectEl) return;

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeUserMenu();
            var isOpen = pop.classList.contains('open');
            closeAllFilterPopovers();

            if (!isOpen) {
                pop.hidden = false;
                pop.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
                activeFilterPopover = pop;
                activeFilterTrigger = btn;

                // Focus selected item
                var selectedItem = pop.querySelector('.filter-popover-item.is-selected');
                if (selectedItem) {
                    selectedItem.focus();
                }
            }
        });

        // Item selection
        pop.querySelectorAll('.filter-popover-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.stopPropagation();
                var val = item.getAttribute('data-value');
                var text = item.querySelector('.filter-item-text') ? item.querySelector('.filter-item-text').textContent.trim() : val;

                // Update selected class
                pop.querySelectorAll('.filter-popover-item').forEach(function (el) {
                    var isCur = el === item;
                    el.classList.toggle('is-selected', isCur);
                    el.setAttribute('aria-selected', isCur ? 'true' : 'false');
                    el.setAttribute('tabindex', isCur ? '0' : '-1');
                });

                // Update trigger button UI
                var isFiltered = val !== 'all';
                btn.classList.toggle('is-filtered', isFiltered);
                if (pillEl) { pillEl.hidden = !isFiltered; }

                if (labelEl) {
                    labelEl.textContent = isFiltered ? cfg.prefix + text : cfg.defaultLabel;
                }

                // Sync backing select
                selectEl.value = val;
                closeAllFilterPopovers();
                btn.focus();

                // Dispatch filter
                applyFilter();
            });

            // Keyboard navigation inside popover
            item.addEventListener('keydown', function (e) {
                var items = Array.prototype.slice.call(pop.querySelectorAll('.filter-popover-item'));
                var idx = items.indexOf(item);
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    var next = items[(idx + 1) % items.length];
                    if (next) next.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    var prev = items[(idx - 1 + items.length) % items.length];
                    if (prev) prev.focus();
                } else if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    item.click();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closeAllFilterPopovers();
                    btn.focus();
                }
            });
        });

        // Reset button inside popover header
        pop.querySelectorAll('.filter-popover-reset').forEach(function (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var allOption = pop.querySelector('.filter-popover-item[data-value="all"]');
                if (allOption) {
                    allOption.click();
                }
            });
        });
    });

    // Global clear all filters button in empty state
    var clearAllBtn = document.getElementById('clearAllFiltersBtn');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function () {
            if (searchInput) { searchInput.value = ''; }
            if (searchClear) { searchClear.hidden = true; }
            Object.keys(filterConfigs).forEach(function (k) {
                var c = filterConfigs[k];
                var p = document.getElementById(c.popoverId);
                if (p) {
                    var allOpt = p.querySelector('.filter-popover-item[data-value="all"]');
                    if (allOpt) allOpt.click();
                }
            });
            applyFilter();
        });
    }

    // Close on click outside
    document.addEventListener('click', function (e) {
        if (activeFilterPopover && !activeFilterPopover.contains(e.target) && !activeFilterTrigger.contains(e.target)) {
            closeAllFilterPopovers();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activeFilterPopover) {
            var trg = activeFilterTrigger;
            closeAllFilterPopovers();
            if (trg) trg.focus();
        }
    });

    // Compact Vertical Action Dropdown Menu handling
    var activeDropdown = null;
    var activeBtn = null;
    var activeRow = null;

    function closeUserMenu() {
        if (activeDropdown) {
            activeDropdown.classList.remove('open', 'drop-up');
            if (activeBtn) {
                activeBtn.setAttribute('aria-expanded', 'false');
            }
            if (activeRow) {
                activeRow.classList.remove('is-menu-open');
            }
            activeDropdown = null;
            activeBtn = null;
            activeRow = null;
        }
    }

    document.querySelectorAll('.btn-user-menu-trigger').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var menuId = btn.getAttribute('aria-controls');
            var menu = document.getElementById(menuId);
            if (!menu) return;

            if (menu === activeDropdown) {
                closeUserMenu();
                return;
            }

            closeUserMenu();

            // Determine if dropdown should open upwards (near viewport bottom)
            var btnRect = btn.getBoundingClientRect();
            var spaceBelow = window.innerHeight - btnRect.bottom;
            if (spaceBelow < 230) {
                menu.classList.add('drop-up');
            } else {
                menu.classList.remove('drop-up');
            }

            menu.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
            var row = btn.closest('tr');
            if (row) {
                row.classList.add('is-menu-open');
                activeRow = row;
            }
            activeDropdown = menu;
            activeBtn = btn;
        });
    });

    document.addEventListener('click', function (e) {
        if (activeDropdown && !activeDropdown.contains(e.target) && e.target !== activeBtn) {
            closeUserMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activeDropdown) {
            var b = activeBtn;
            closeUserMenu();
            if (b) b.focus();
        }
    });

    // Suspend Modal Handling
    var suspendModal = document.getElementById('suspendUserModal');
    var suspendForm = document.getElementById('suspendUserForm');
    var suspendNameEl = document.getElementById('suspendUserName');
    var suspendEmailEl = document.getElementById('suspendUserEmail');
    var suspendReasonInput = document.getElementById('suspensionReasonInput');
    var suspendReasonError = document.getElementById('suspensionReasonError');

    function openSuspendModal(userId, userName, userEmail) {
        closeUserMenu();
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