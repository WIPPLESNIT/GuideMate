<?php
/**
 * Admin Guide Listings Review (Attractions & Things to Do)
 *
 * @var array<int,array<string,mixed>> $listings
 * @var string $status
 * @var string $category
 * @var int $pendingCount
 */

$status = (string) ($status ?? 'all');
$category = (string) ($category ?? 'all');
$q = (string) ($_GET['q'] ?? '');

$statusCounts = [
    'all' => count($listings),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];
$categoryCounts = [
    'all' => count($listings),
    'attractions' => 0,
    'things-to-do' => 0,
    'packages' => 0,
];

foreach ($listings as $l) {
    $st = (string) ($l['status'] ?? 'pending');
    if (isset($statusCounts[$st])) {
        $statusCounts[$st]++;
    }
    $cSlug = (string) ($l['category_slug'] ?? '');
    if ($cSlug === 'attractions' || $cSlug === 'attraction-and-tours') {
        $categoryCounts['attractions']++;
    } elseif ($cSlug === 'packages' || $cSlug === 'travel-packages') {
        $categoryCounts['packages']++;
    } else {
        $categoryCounts['things-to-do']++;
    }
}

$statusLabels = [
    'all' => 'All Listings',
    'pending' => 'Pending Review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];

$categoryLabels = [
    'all' => 'All Types',
    'attractions' => 'Attractions',
    'things-to-do' => 'Things to Do',
    'packages' => 'Package',
];

$activeStatusLabel = $statusLabels[$status] ?? 'All Listings';
$activeCategoryLabel = $categoryLabels[$category] ?? 'All Types';
$isStatusFiltered = ($status !== 'all');
$isCatFiltered = ($category !== 'all');
?>
<div class="section-head" style="margin-bottom:1.25rem;">
    <div>
        <h1 class="mb-0">Guide Listings Review</h1>
        <p class="hint mb-0">Review submitted attractions and activities from tour guides, inspect photos, schedules, and details, and approve or reject listings.</p>
    </div>
</div>

<!-- Unified Search & Filter Toolbar matching Users and Listings sections -->
<div class="users-toolbar listings-toolbar" style="margin-bottom:1.25rem;">
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
                   id="guideSearchInput"
                   class="filter-input users-search-input"
                   placeholder="Search"
                   value="<?= e($q) ?>"
                   aria-label="Search guide listings"
                   autocomplete="off"
                   spellcheck="false">
            <button type="button" id="guideSearchClear" class="users-search-clear" aria-label="Clear search" <?= $q === '' ? 'hidden' : '' ?>>&times;</button>
        </div>

        <!-- Status Custom Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="status" id="guideStatusFilterControl">
            <button type="button"
                    class="filter-dropdown-btn <?= $isStatusFiltered ? 'is-filtered' : '' ?>"
                    id="guideStatusBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="guideStatusPopover"
                    aria-label="Filter by listing status">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="6"></circle>
                        <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="guideStatusLabel"><?= e($activeStatusLabel) ?></span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="guideStatusPopover" role="listbox" aria-labelledby="guideStatusBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Status</span>
                    <button type="button" class="filter-popover-reset" id="guideStatusReset">Reset</button>
                </div>
                <div class="filter-popover-options">
                    <div class="filter-popover-item <?= $status === 'all' ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $status === 'all' ? 'true' : 'false' ?>" data-value="all" tabindex="<?= $status === 'all' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">All Listings</span>
                        <span class="filter-item-count"><?= (int) $statusCounts['all'] ?></span>
                    </div>
                    <div class="filter-popover-item <?= $status === 'pending' ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $status === 'pending' ? 'true' : 'false' ?>" data-value="pending" tabindex="<?= $status === 'pending' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Pending Review</span>
                        <span class="filter-item-count" style="<?= $statusCounts['pending'] > 0 ? 'background:rgba(239,68,68,0.2);color:#fca5a5;' : '' ?>"><?= (int) $statusCounts['pending'] ?></span>
                    </div>
                    <div class="filter-popover-item <?= $status === 'approved' ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $status === 'approved' ? 'true' : 'false' ?>" data-value="approved" tabindex="<?= $status === 'approved' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Approved</span>
                        <span class="filter-item-count"><?= (int) $statusCounts['approved'] ?></span>
                    </div>
                    <div class="filter-popover-item <?= $status === 'rejected' ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $status === 'rejected' ? 'true' : 'false' ?>" data-value="rejected" tabindex="<?= $status === 'rejected' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Rejected</span>
                        <span class="filter-item-count"><?= (int) $statusCounts['rejected'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category / Type Custom Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="category" id="guideCategoryFilterControl">
            <button type="button"
                    class="filter-dropdown-btn <?= $isCatFiltered ? 'is-filtered' : '' ?>"
                    id="guideCategoryBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="guideCategoryPopover"
                    aria-label="Filter by category">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="7" height="7" x="3" y="3" rx="1"></rect>
                        <rect width="7" height="7" x="14" y="3" rx="1"></rect>
                        <rect width="7" height="7" x="14" y="14" rx="1"></rect>
                        <rect width="7" height="7" x="3" y="14" rx="1"></rect>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="guideCategoryLabel"><?= e($activeCategoryLabel) ?></span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="guideCategoryPopover" role="listbox" aria-labelledby="guideCategoryBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Category</span>
                    <button type="button" class="filter-popover-reset" id="guideCategoryReset">Reset</button>
                </div>
                <div class="filter-popover-options">
                    <div class="filter-popover-item <?= $category === 'all' ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $category === 'all' ? 'true' : 'false' ?>" data-value="all" tabindex="<?= $category === 'all' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">All Types</span>
                        <span class="filter-item-count"><?= (int) $categoryCounts['all'] ?></span>
                    </div>
                    <div class="filter-popover-item <?= $category === 'attractions' ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $category === 'attractions' ? 'true' : 'false' ?>" data-value="attractions" tabindex="<?= $category === 'attractions' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Attractions</span>
                        <span class="filter-item-count"><?= (int) $categoryCounts['attractions'] ?></span>
                    </div>
                    <div class="filter-popover-item <?= $category === 'things-to-do' ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $category === 'things-to-do' ? 'true' : 'false' ?>" data-value="things-to-do" tabindex="<?= $category === 'things-to-do' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Things to Do</span>
                        <span class="filter-item-count"><?= (int) $categoryCounts['things-to-do'] ?></span>
                    </div>
                    <div class="filter-popover-item <?= $category === 'packages' ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $category === 'packages' ? 'true' : 'false' ?>" data-value="packages" tabindex="<?= $category === 'packages' ? '0' : '-1' ?>">
                        <span class="filter-item-indicator" aria-hidden="true"></span>
                        <span class="filter-item-text">Package</span>
                        <span class="filter-item-count"><?= (int) ($categoryCounts['packages'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-body vehicles-table-wrap" style="padding:0;">
        <?php if ($listings === []): ?>
            <div class="empty-state" style="padding:3rem 1rem;">
                <div class="big">🧭</div>
                <h3>No guide listings found</h3>
                <p>There are no guide listings submitted yet.</p>
            </div>
        <?php else: ?>
            <table class="table table-vehicles table-hotels table-guide-listings" id="guideListingsTable">
                <thead>
                    <tr>
                        <th class="col-vehicle">Listing</th>
                        <th class="col-partner">Guide</th>
                        <th class="col-type">Category / Rate</th>
                        <th class="col-status">Status</th>
                        <th class="col-audit">Dates / Audit</th>
                        <th class="col-actions" style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $initialVisibleCount = 0;
                foreach ($listings as $l):
                    $lStatus = (string) ($l['status'] ?? 'pending');
                    $thumb = !empty($l['cover_image']) ? $l['cover_image'] : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&q=80';
                    $cSlug = (string) ($l['category_slug'] ?? '');
                    $isAttraction = ($cSlug === 'attractions' || $cSlug === 'attraction-and-tours');
                    $isPackage = ($cSlug === 'packages' || $cSlug === 'travel-packages');
                    $catGroup = $isPackage ? 'packages' : ($isAttraction ? 'attractions' : 'things-to-do');
                    $schedCount = (int) ($l['schedule_count'] ?? 0);
                    $cap = (int) ($l['total_capacity'] ?? $l['capacity'] ?? 10);
                    $pkgOverallStatus = 'none';
                    if ($isPackage) {
                        $pkgSummary = \App\Models\PackageService::getProviderApprovalSummary((int) $l['id']);
                        $pkgOverallStatus = $pkgSummary['overall_status'];
                    }

                    $titleLower = strtolower((string) ($l['title'] ?? ''));
                    $guideLower = strtolower((string) (($l['guide_name'] ?? '') . ' ' . ($l['guide_email'] ?? '')));
                    $areaLower = strtolower((string) ($l['area'] ?? ''));

                    $matchesStatus = ($status === 'all' || $lStatus === $status);
                    $matchesCat = ($category === 'all' || $catGroup === $category);
                    $matchesSearch = true;
                    if ($q !== '') {
                        $qLower = strtolower($q);
                        $matchesSearch = (str_contains($titleLower, $qLower) || str_contains($guideLower, $qLower) || str_contains($areaLower, $qLower));
                    }
                    $rowMatches = ($matchesStatus && $matchesCat && $matchesSearch);
                    if ($rowMatches) {
                        $initialVisibleCount++;
                    }
                ?>
                    <tr id="listing-row-<?= (int) $l['id'] ?>"
                        data-id="<?= (int) $l['id'] ?>"
                        data-status="<?= e($lStatus) ?>"
                        data-category="<?= e($catGroup) ?>"
                        data-pkg-status="<?= e($pkgOverallStatus) ?>"
                        data-title="<?= e($titleLower) ?>"
                        data-guide="<?= e($guideLower) ?>"
                        data-area="<?= e($areaLower) ?>"
                        class="guide-listing-row"
                        <?= $rowMatches ? '' : 'hidden' ?>>
                        <td class="vehicle-cell col-vehicle" data-label="Listing">
                            <div class="vehicle-meta-wrap">
                                <img src="<?= e(img_src($thumb, 'listing-' . $l['id'])) ?>" alt="" class="vehicle-thumb" style="width:64px;height:48px;border-radius:6px;object-fit:cover;">
                                <div class="vehicle-info">
                                    <a href="<?= e(url('/admin/guide-listings/' . (int) $l['id'])) ?>"><strong class="vehicle-title"><?= e($l['title']) ?></strong></a>
                                </div>
                            </div>
                        </td>
                        <td class="col-partner" data-label="Guide">
                            <strong><?= e($l['guide_name'] ?? 'Guide Partner') ?></strong>
                            <div class="hint" style="font-size:0.8rem;word-break:break-all;"><?= e($l['guide_email'] ?? '') ?></div>
                        </td>
                        <td class="col-type" data-label="Category / Rate">
                            <div style="margin-bottom:3px;">
                                <?php if ($isPackage): ?>
                                    <span class="pill" style="font-size:0.72rem;padding:2px 7px;background:rgba(139,92,246,0.15);color:#a78bfa;border:1px solid rgba(139,92,246,0.3);font-weight:600;border-radius:20px;">
                                        Package
                                    </span>
                                <?php elseif ($isAttraction): ?>
                                    <span class="pill" style="font-size:0.72rem;padding:2px 7px;background:rgba(236,72,153,0.15);color:#f472b6;border:1px solid rgba(236,72,153,0.3);font-weight:600;border-radius:20px;">
                                        Attraction
                                    </span>
                                <?php else: ?>
                                    <span class="pill" style="font-size:0.72rem;padding:2px 7px;background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);font-weight:600;border-radius:20px;">
                                        Things to Do
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ((float) ($l['price'] ?? 0) > 0): ?>
                                <strong><?= money((float) $l['price']) ?></strong>
                                <?php if ($isPackage): ?>
                                    <small class="hint">Package Price</small>
                                <?php else: ?>
                                    <small class="hint">/ <?= e($l['price_unit'] ?? 'person') ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong style="color:var(--brand, #10B981);">Free</strong>
                            <?php endif; ?>
                            <div class="hint" style="font-size:0.75rem;margin-top:2px;">
                                <?php if ($schedCount > 0): ?>
                                    <?= $schedCount ?> session<?= $schedCount === 1 ? '' : 's' ?> · Max <?= $cap ?>
                                <?php else: ?>
                                    Capacity: <?= $cap ?> slots
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-status" data-label="Status">
                            <span class="pill <?= e(\App\Models\Listing::hotelStatusPillClass($lStatus)) ?>">
                                <?= e(\App\Models\Listing::hotelStatusLabel($lStatus)) ?>
                            </span>
                            <?php if ($isPackage): ?>
                                <div style="margin-top:6px;">
                                    <?php if ($pkgOverallStatus === 'agreed'): ?>
                                        <span class="pill" style="font-size:0.72rem;padding:3px 8px;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;font-weight:700;display:inline-flex;align-items:center;gap:3px;" title="All required providers have approved inclusion in this package">
                                            ✓ Agreed
                                        </span>
                                    <?php elseif ($pkgOverallStatus === 'rejected'): ?>
                                        <span class="pill" style="font-size:0.72rem;padding:3px 8px;background:#fef2f2;color:#b91c1c;border:1px solid #fca5a5;font-weight:700;display:inline-flex;align-items:center;gap:3px;" title="At least one provider has declined inclusion">
                                            ✕ Rejected
                                        </span>
                                    <?php else: ?>
                                        <span class="pill" style="font-size:0.72rem;padding:3px 8px;background:#fefce8;color:#a16207;border:1px solid #fde047;font-weight:700;display:inline-flex;align-items:center;gap:3px;" title="Waiting for provider approval responses">
                                            ⏳ Pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="col-audit" data-label="Dates / Audit" style="font-size:0.8rem;">
                            <?php if (!empty($l['submitted_at'])): ?>
                                <div><span class="hint">Submitted:</span> <?= e(date('M j, Y g:ia', strtotime((string) $l['submitted_at']))) ?></div>
                            <?php else: ?>
                                <div><span class="hint">Created:</span> <?= e(date('M j, Y g:ia', strtotime((string) $l['created_at']))) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($l['reviewed_at'])): ?>
                                <div><span class="hint">Reviewed:</span> <?= e(date('M j, Y g:ia', strtotime((string) $l['reviewed_at']))) ?></div>
                                <?php if (!empty($l['reviewer_name'])): ?>
                                    <div class="hint">by <?= e($l['reviewer_name']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="vehicle-actions-cell col-actions" data-label="Actions" style="text-align:right;vertical-align:middle;">
                            <div class="vehicle-actions-vertical">
                                <?php if ($isPackage): ?>
                                    <a href="<?= e(url('/admin/guide-listings/' . (int) $l['id'])) ?>" class="btn btn-ghost btn-sm btn-action-vertical" style="font-weight:700;color:#059669;border-color:#10b981;">Inspect</a>
                                <?php else: ?>
                                    <a href="<?= e(url('/admin/guide-listings/' . (int) $l['id'])) ?>" class="btn btn-ghost btn-sm btn-action-vertical">Inspect</a>
                                <?php endif; ?>

                                <?php if ($lStatus === 'approved'): ?>
                                    <button type="button" class="btn btn-ghost btn-sm btn-action-vertical btn-action-delete" onclick="openDeleteModal(<?= (int) $l['id'] ?>, '<?= e(addslashes((string) $l['title'])) ?>');">
                                        Delete
                                    </button>
                                <?php elseif ($lStatus === 'pending'): ?>
                                    <form method="post" action="<?= e(url('/admin/guide-listings/' . (int) $l['id'] . '/approve')) ?>" class="vehicle-action-form" onsubmit="return confirm('Approve and publish this guide listing? It will immediately become live for travelers.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary btn-sm btn-action-vertical">✓ Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-ghost btn-sm btn-action-vertical btn-action-reject" style="color:var(--coral-600);" onclick="openRejectModal(<?= (int) $l['id'] ?>, '<?= e(addslashes((string) $l['title'])) ?>');">
                                        Reject
                                    </button>
                                <?php elseif ($lStatus === 'rejected'): ?>
                                    <form method="post" action="<?= e(url('/admin/guide-listings/' . (int) $l['id'] . '/approve')) ?>" class="vehicle-action-form" onsubmit="return confirm('Approve and publish this guide listing? It will immediately become live for travelers.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary btn-sm btn-action-vertical">✓ Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-ghost btn-sm btn-action-vertical btn-action-delete" onclick="openDeleteModal(<?= (int) $l['id'] ?>, '<?= e(addslashes((string) $l['title'])) ?>');">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="guideFilterEmptyRow" <?= $initialVisibleCount === 0 ? '' : 'style="display:none;"' ?>>
                        <td colspan="6" style="padding:0;">
                            <div class="empty-state" style="padding:3rem 1rem;">
                                <div class="big">🧭</div>
                                <h3>No guide listings found</h3>
                                <p>There are no guide listings matching your current search and filter selection.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal dialog for entering optional rejection reason -->
<div id="adminRejectModal" class="gm-modal admin-reject-modal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" onclick="closeRejectModal()"></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle" style="max-width:500px;">
        <div class="gm-modal-head admin-reject-head">
            <div style="display:flex;align-items:center;gap:0.6rem;min-width:0;">
                <span class="admin-reject-icon" aria-hidden="true">✕</span>
                <h3 id="rejectModalTitle" class="admin-reject-title" style="margin:0;">Reject Guide Listing</h3>
            </div>
            <button type="button" class="gm-modal-close" onclick="closeRejectModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="adminRejectForm" method="post" action="">
            <?= csrf_field() ?>
            <div class="gm-modal-body admin-reject-body">
                <p class="hint admin-reject-hint" style="margin-top:0;">
                    Please provide an optional reason or specific correction request for the guide. This will be shown on their dashboard so they can revise and resubmit.
                </p>
                <div class="field-row" style="margin-bottom:0;">
                    <label for="rejectReasonInput" class="admin-reject-label">
                        Rejection Reason <small class="hint admin-reject-label-hint">(optional)</small>
                    </label>
                    <textarea class="input admin-reject-textarea" id="rejectReasonInput" name="reason" rows="4" placeholder="e.g. Please provide high-resolution photos of the tour activity and clarify the meeting point location."></textarea>
                </div>
            </div>
            <div class="gm-modal-foot admin-reject-foot">
                <button type="button" class="btn btn-ghost btn-cancel-reject" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-confirm-reject" onclick="return confirm('Confirm rejection of this guide listing?');">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal dialog for confirming permanent deletion of rejected listing -->
<div id="adminDeleteModal" class="gm-modal admin-delete-modal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" onclick="closeDeleteModal()"></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle" style="max-width:480px;">
        <div class="gm-modal-head admin-delete-head">
            <div style="display:flex;align-items:center;gap:0.6rem;min-width:0;">
                <span class="admin-delete-icon" aria-hidden="true">🗑</span>
                <h3 id="deleteModalTitle" class="admin-delete-title" style="margin:0;">Permanently Delete Listing</h3>
            </div>
            <button type="button" class="gm-modal-close" onclick="closeDeleteModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="adminDeleteForm" method="post" action="" onsubmit="handleDeleteSubmit(event)">
            <?= csrf_field() ?>
            <div class="gm-modal-body admin-delete-body">
                <div class="admin-delete-warning-box">
                    <p style="margin:0;font-weight:600;color:#fca5a5;font-size:0.95rem;">
                        ⚠️ This action is permanent and cannot be undone.
                    </p>
                    <p style="margin:0.5rem 0 0;font-size:0.88rem;color:#cbd5e1;line-height:1.5;">
                        Are you sure you want to permanently delete <strong id="deleteItemName" style="color:#ffffff;"></strong> from the system?
                        All associated photos, schedules, and listing records will be completely removed from the database.
                    </p>
                </div>
            </div>
            <div class="gm-modal-foot admin-delete-foot">
                <button type="button" class="btn btn-ghost btn-cancel-delete" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" id="confirmDeleteBtn" class="btn btn-danger btn-confirm-delete">
                    Permanently Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var activeListingId = null;
var pendingDeleteId = null;
var baseActionUrl = <?= json_encode(url('/admin/guide-listings')) ?>;

function openRejectModal(id, title) {
    activeListingId = id;
    var modal = document.getElementById('adminRejectModal');
    var form = document.getElementById('adminRejectForm');
    var titleEl = document.getElementById('rejectModalTitle');
    var input = document.getElementById('rejectReasonInput');
    if (!modal || !form) return;
    form.action = baseActionUrl + '/' + id + '/reject';
    if (titleEl) {
        titleEl.textContent = 'Reject: ' + title;
    }
    if (input) {
        input.value = '';
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    if (input) setTimeout(function () { input.focus(); }, 50);
}

function closeRejectModal() {
    var modal = document.getElementById('adminRejectModal');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    activeListingId = null;
}

function openDeleteModal(id, title) {
    pendingDeleteId = id;
    var modal = document.getElementById('adminDeleteModal');
    var form = document.getElementById('adminDeleteForm');
    var nameEl = document.getElementById('deleteItemName');
    if (!modal || !form) return;
    form.action = baseActionUrl + '/' + id + '/delete';
    if (nameEl) {
        nameEl.textContent = title;
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
}

function closeDeleteModal() {
    var modal = document.getElementById('adminDeleteModal');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    pendingDeleteId = null;
}

function handleDeleteSubmit(e) {
    e.preventDefault();
    if (!pendingDeleteId) return;
    var form = document.getElementById('adminDeleteForm');
    var btn = document.getElementById('confirmDeleteBtn');
    if (!form || !btn) return;
    var targetId = pendingDeleteId;
    btn.disabled = true;
    btn.textContent = 'Deleting...';
    var tokenInput = form.querySelector('input[name="_token"]') || form.querySelector('input[name="_csrf"]');
    var token = tokenInput ? tokenInput.value : '';
    fetch(form.action, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token
        },
        body: '_csrf=' + encodeURIComponent(token) + '&_token=' + encodeURIComponent(token)
    })
    .then(function (res) {
        return res.json().then(function (data) {
            return { ok: res.ok, status: res.status, data: data };
        }).catch(function () {
            return { ok: res.ok, status: res.status, data: {} };
        });
    })
    .then(function (result) {
        if (result.ok && result.data && result.data.success !== false) {
            var row = document.getElementById('listing-row-' + targetId);
            if (row) {
                row.remove();
            }
            closeDeleteModal();
        } else {
            var errorMsg = (result.data && (result.data.message || result.data.error)) || 'Error deleting listing.';
            alert(errorMsg);
            btn.disabled = false;
            btn.textContent = 'Permanently Delete';
        }
    })
    .catch(function () {
        form.submit();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('guideSearchInput');
    var searchClear = document.getElementById('guideSearchClear');
    var statusBtn = document.getElementById('guideStatusBtn');
    var statusPop = document.getElementById('guideStatusPopover');
    var statusLabel = document.getElementById('guideStatusLabel');
    var statusReset = document.getElementById('guideStatusReset');
    var catBtn = document.getElementById('guideCategoryBtn');
    var catPop = document.getElementById('guideCategoryPopover');
    var catLabel = document.getElementById('guideCategoryLabel');
    var catReset = document.getElementById('guideCategoryReset');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.guide-listing-row'));
    var emptyRow = document.getElementById('guideFilterEmptyRow');

    var curStatus = '<?= e($status) ?>';
    var curCategory = '<?= e($category) ?>';

    function closeAllPopovers() {
        if (statusPop) { statusPop.hidden = true; statusPop.classList.remove('open'); }
        if (statusBtn) { statusBtn.setAttribute('aria-expanded', 'false'); }
        if (catPop) { catPop.hidden = true; catPop.classList.remove('open'); }
        if (catBtn) { catBtn.setAttribute('aria-expanded', 'false'); }
    }

    function applyFilters() {
        var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
        if (searchClear) {
            searchClear.hidden = (q.length === 0);
        }

        var visibleCount = 0;
        rows.forEach(function (row) {
            var rowStatus = row.getAttribute('data-status') || '';
            var rowCat = row.getAttribute('data-category') || '';
            var matchesStatus = (curStatus === 'all' || rowStatus === curStatus);
            var matchesCat = (curCategory === 'all' || rowCat === curCategory);

            var matchesSearch = true;
            if (q !== '') {
                var title = row.getAttribute('data-title') || '';
                var guide = row.getAttribute('data-guide') || '';
                var area = row.getAttribute('data-area') || '';
                matchesSearch = (title.indexOf(q) !== -1 || guide.indexOf(q) !== -1 || area.indexOf(q) !== -1);
            }

            var show = matchesStatus && matchesCat && matchesSearch;
            row.hidden = !show;
            if (show) visibleCount++;
        });

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
        }

        try {
            var newUrl = new URL(window.location.href);
            if (curStatus === 'all') { newUrl.searchParams.delete('status'); } else { newUrl.searchParams.set('status', curStatus); }
            if (curCategory === 'all') { newUrl.searchParams.delete('category'); } else { newUrl.searchParams.set('category', curCategory); }
            if (q === '') { newUrl.searchParams.delete('q'); } else { newUrl.searchParams.set('q', q); }
            window.history.replaceState({}, '', newUrl.toString());
        } catch (err) {}
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) { searchInput.value = ''; searchInput.focus(); }
            applyFilters();
        });
    }

    // Status dropdown
    if (statusBtn && statusPop) {
        statusBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = statusPop.classList.contains('open');
            closeAllPopovers();
            if (!isOpen) {
                statusPop.hidden = false;
                statusPop.classList.add('open');
                statusBtn.setAttribute('aria-expanded', 'true');
                var sel = statusPop.querySelector('.filter-popover-item.is-selected');
                if (sel) sel.focus();
            }
        });

        statusPop.querySelectorAll('.filter-popover-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.stopPropagation();
                var val = item.getAttribute('data-value') || 'all';
                var textEl = item.querySelector('.filter-item-text');
                var text = textEl ? textEl.textContent.trim() : val;
                curStatus = val;
                statusBtn.classList.toggle('is-filtered', val !== 'all');
                if (statusLabel) {
                    statusLabel.textContent = text;
                }
                statusPop.querySelectorAll('.filter-popover-item').forEach(function (el) {
                    var isCur = (el.getAttribute('data-value') === val);
                    el.classList.toggle('is-selected', isCur);
                    el.setAttribute('aria-selected', isCur ? 'true' : 'false');
                    el.setAttribute('tabindex', isCur ? '0' : '-1');
                });
                applyFilters();
                closeAllPopovers();
                statusBtn.focus();
            });
        });

        if (statusReset) {
            statusReset.addEventListener('click', function (e) {
                e.stopPropagation();
                var allItem = statusPop.querySelector('.filter-popover-item[data-value="all"]');
                if (allItem) allItem.click();
            });
        }
    }

    // Category dropdown
    if (catBtn && catPop) {
        catBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = catPop.classList.contains('open');
            closeAllPopovers();
            if (!isOpen) {
                catPop.hidden = false;
                catPop.classList.add('open');
                catBtn.setAttribute('aria-expanded', 'true');
                var sel = catPop.querySelector('.filter-popover-item.is-selected');
                if (sel) sel.focus();
            }
        });

        catPop.querySelectorAll('.filter-popover-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.stopPropagation();
                var val = item.getAttribute('data-value') || 'all';
                var textEl = item.querySelector('.filter-item-text');
                var text = textEl ? textEl.textContent.trim() : val;
                curCategory = val;
                catBtn.classList.toggle('is-filtered', val !== 'all');
                if (catLabel) {
                    catLabel.textContent = text;
                }
                catPop.querySelectorAll('.filter-popover-item').forEach(function (el) {
                    var isCur = (el.getAttribute('data-value') === val);
                    el.classList.toggle('is-selected', isCur);
                    el.setAttribute('aria-selected', isCur ? 'true' : 'false');
                    el.setAttribute('tabindex', isCur ? '0' : '-1');
                });
                applyFilters();
                closeAllPopovers();
                catBtn.focus();
            });
        });

        if (catReset) {
            catReset.addEventListener('click', function (e) {
                e.stopPropagation();
                var allItem = catPop.querySelector('.filter-popover-item[data-value="all"]');
                if (allItem) allItem.click();
            });
        }
    }

    document.addEventListener('click', function (e) {
        if (!statusPop.contains(e.target) && e.target !== statusBtn && !statusBtn.contains(e.target) &&
            !catPop.contains(e.target) && e.target !== catBtn && !catBtn.contains(e.target)) {
            closeAllPopovers();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllPopovers();
        }
    });
});
</script>
