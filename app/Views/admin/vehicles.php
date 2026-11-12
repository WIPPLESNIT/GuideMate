<?php
/**
 * Admin Vehicle Listings Review
 *
 * @var array<int,array<string,mixed>> $vehicles
 * @var string $status
 * @var string $type
 * @var int $pendingCount
 */

$status = (string) ($status ?? 'all');
$type = (string) ($type ?? 'all');
$q = (string) ($_GET['q'] ?? '');

$statusCounts = [
    'all' => count($vehicles),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

$typeMap = [
    'all' => 'All Types',
    'scooter' => 'Scooter',
    'motorcycle' => 'Motorcycle',
    'sedan' => 'Sedan',
    'suv' => 'SUV',
    'van' => 'Van',
    'other' => 'Other Types',
];

$typeCounts = [
    'all' => count($vehicles),
    'scooter' => 0,
    'motorcycle' => 0,
    'sedan' => 0,
    'suv' => 0,
    'van' => 0,
    'other' => 0,
];

foreach ($vehicles as $v) {
    $st = (string) ($v['status'] ?? 'pending');
    if (isset($statusCounts[$st])) {
        $statusCounts[$st]++;
    }
    $tRaw = strtolower(trim((string) ($v['type'] ?? '')));
    if ($tRaw === 'scooter') {
        $typeKey = 'scooter';
    } elseif ($tRaw === 'motorcycle' || $tRaw === 'motorbike') {
        $typeKey = 'motorcycle';
    } elseif ($tRaw === 'sedan') {
        $typeKey = 'sedan';
    } elseif ($tRaw === 'suv') {
        $typeKey = 'suv';
    } elseif ($tRaw === 'van') {
        $typeKey = 'van';
    } else {
        $typeKey = 'other';
    }
    if (isset($typeCounts[$typeKey])) {
        $typeCounts[$typeKey]++;
    }
}

$statusLabels = [
    'all' => 'All Listings',
    'pending' => 'Pending Review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];

$activeStatusLabel = $statusLabels[$status] ?? 'All Listings';
$activeTypeLabel = $typeMap[$type] ?? 'All Types';
$isStatusFiltered = ($status !== 'all');
$isTypeFiltered = ($type !== 'all');
?>
<div class="section-head" style="margin-bottom:1.25rem;">
    <div>
        <h1 class="mb-0">Vehicle Listings Review</h1>
        <p class="hint mb-0">Review submitted vehicles from rental partners, inspect photos and specifications, and approve or reject listings.</p>
    </div>
</div>

<!-- Unified Search & Filter Toolbar matching Users, Guide Reviews, and Hotel sections -->
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
                   id="vehicleSearchInput"
                   class="filter-input users-search-input"
                   placeholder="Search"
                   value="<?= e($q) ?>"
                   aria-label="Search vehicles"
                   autocomplete="off"
                   spellcheck="false">
            <button type="button" id="vehicleSearchClear" class="users-search-clear" aria-label="Clear search" <?= $q === '' ? 'hidden' : '' ?>>&times;</button>
        </div>

        <!-- Status Custom Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="status" id="vehicleStatusFilterControl">
            <button type="button"
                    class="filter-dropdown-btn <?= $isStatusFiltered ? 'is-filtered' : '' ?>"
                    id="vehicleStatusBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="vehicleStatusPopover"
                    aria-label="Filter by listing status">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="6"></circle>
                        <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="vehicleStatusLabel"><?= e($activeStatusLabel) ?></span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="vehicleStatusPopover" role="listbox" aria-labelledby="vehicleStatusBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Status</span>
                    <button type="button" class="filter-popover-reset" id="vehicleStatusReset">Reset</button>
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

        <!-- Vehicle Type Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="type" id="vehicleTypeFilterControl">
            <button type="button"
                    class="filter-dropdown-btn <?= $isTypeFiltered ? 'is-filtered' : '' ?>"
                    id="vehicleTypeBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="vehicleTypePopover"
                    aria-label="Filter by vehicle type">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"></path>
                        <circle cx="7" cy="17" r="2"></circle>
                        <path d="M9 17h6"></path>
                        <circle cx="17" cy="17" r="2"></circle>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="vehicleTypeLabel"><?= e($activeTypeLabel) ?></span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="vehicleTypePopover" role="listbox" aria-labelledby="vehicleTypeBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Vehicle Type</span>
                    <button type="button" class="filter-popover-reset" id="vehicleTypeReset">Reset</button>
                </div>
                <div class="filter-popover-options">
                    <?php foreach ($typeMap as $tKey => $tLabel): ?>
                        <div class="filter-popover-item <?= $type === $tKey ? 'is-selected' : '' ?>" role="option" aria-selected="<?= $type === $tKey ? 'true' : 'false' ?>" data-value="<?= e($tKey) ?>" tabindex="<?= $type === $tKey ? '0' : '-1' ?>">
                            <span class="filter-item-indicator" aria-hidden="true"></span>
                            <span class="filter-item-text"><?= e($tLabel) ?></span>
                            <span class="filter-item-count"><?= (int) ($typeCounts[$tKey] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-body vehicles-table-wrap" style="padding:0;">
        <?php if ($vehicles === []): ?>
            <div class="empty-state" style="padding:3rem 1rem;">
                <div class="big">🚗</div>
                <h3>No vehicle listings found</h3>
                <p>There are no vehicle listings submitted yet.</p>
            </div>
        <?php else: ?>
            <table class="table table-vehicles" id="vehicleListingsTable">
                <thead>
                    <tr>
                        <th class="col-vehicle">Vehicle</th>
                        <th class="col-partner">Partner</th>
                        <th class="col-type">Type / Rate</th>
                        <th class="col-status">Status</th>
                        <th class="col-audit">Dates / Audit</th>
                        <th class="col-actions" style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $initialVisibleCount = 0;
                foreach ($vehicles as $v):
                    $vStatus = (string) ($v['status'] ?? 'pending');
                    $specs = \App\Models\Vehicle::parseSpecs($v['specs'] ?? null);

                    $tRaw = strtolower(trim((string) ($v['type'] ?? '')));
                    if ($tRaw === 'scooter') {
                        $typeKey = 'scooter';
                    } elseif ($tRaw === 'motorcycle' || $tRaw === 'motorbike') {
                        $typeKey = 'motorcycle';
                    } elseif ($tRaw === 'sedan') {
                        $typeKey = 'sedan';
                    } elseif ($tRaw === 'suv') {
                        $typeKey = 'suv';
                    } elseif ($tRaw === 'van') {
                        $typeKey = 'van';
                    } else {
                        $typeKey = 'other';
                    }

                    $titleLower = strtolower((string) ($v['name'] ?? ''));
                    $partnerLower = strtolower((string) (($v['provider_name'] ?? '') . ' ' . ($v['provider_email'] ?? '')));

                    $matchesStatus = ($status === 'all' || $vStatus === $status);
                    $matchesType = ($type === 'all' || $typeKey === $type);
                    $matchesSearch = true;
                    if ($q !== '') {
                        $qLower = strtolower($q);
                        $matchesSearch = (str_contains($titleLower, $qLower) || str_contains($partnerLower, $qLower) || str_contains($tRaw, $qLower));
                    }
                    $rowMatches = ($matchesStatus && $matchesType && $matchesSearch);
                    if ($rowMatches) {
                        $initialVisibleCount++;
                    }
                ?>
                    <tr id="vehicle-row-<?= (int) $v['id'] ?>"
                        data-id="<?= (int) $v['id'] ?>"
                        data-status="<?= e($vStatus) ?>"
                        data-type="<?= e($typeKey) ?>"
                        data-title="<?= e($titleLower) ?>"
                        data-partner="<?= e($partnerLower) ?>"
                        class="vehicle-listing-row"
                        <?= $rowMatches ? '' : 'hidden' ?>>
                        <td class="vehicle-cell col-vehicle" data-label="Vehicle">
                            <div class="vehicle-meta-wrap">
                                <img src="<?= e(img_src($v['image'] ?? null, 'vehicle-' . $v['id'])) ?>" alt="" class="vehicle-thumb" style="width:64px;height:48px;">
                                <div class="vehicle-info">
                                    <a href="<?= e(url('/admin/vehicles/' . (int) $v['id'])) ?>"><strong class="vehicle-title"><?= e($v['name']) ?></strong></a>
                                    <?php if ($vStatus === 'rejected' && !empty($v['rejection_reason'])):
                                        $reasonText = (string) $v['rejection_reason'];
                                        $lineCount = substr_count(trim($reasonText), "\n") + 1;
                                        $charCount = mb_strlen(trim($reasonText));
                                        $isLong = $lineCount >= 3 || $charCount > 90;
                                    ?>
                                        <div class="admin-note-card <?= $isLong ? 'is-long' : '' ?>">
                                            <div class="admin-note-header">
                                                <span class="admin-note-label">
                                                    <svg class="admin-note-icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                                    </svg>
                                                    Reason:
                                                </span>
                                                <?php if ($isLong): ?>
                                                    <button type="button" class="admin-note-toggle" aria-expanded="false" title="Expand or collapse rejection reason">
                                                        <span class="toggle-text">Read more</span>
                                                        <svg class="toggle-arrow" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="6 9 12 15 18 9"></polyline>
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="admin-note-body <?= $isLong ? 'is-clamped' : '' ?>" tabindex="0"><?= nl2br(e($reasonText)) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="col-partner" data-label="Partner">
                            <strong><?= e($v['provider_name']) ?></strong>
                            <div class="hint" style="font-size:0.8rem;word-break:break-all;"><?= e($v['provider_email']) ?></div>
                        </td>
                        <td class="col-type" data-label="Type / Rate">
                            <div><?= e($v['type']) ?></div>
                            <strong><?= money((float) $v['price_per_day']) ?></strong> <small class="hint">/ day</small>
                        </td>
                        <td class="col-status" data-label="Status">
                            <span class="pill <?= e(\App\Models\Vehicle::statusPillClass($vStatus)) ?>">
                                <?= e(\App\Models\Vehicle::statusLabel($vStatus)) ?>
                            </span>
                        </td>
                        <td class="col-audit" data-label="Dates / Audit" style="font-size:0.8rem;">
                            <?php if (!empty($v['submitted_at'])): ?>
                                <div><span class="hint">Submitted:</span> <?= e(date('M j, Y g:ia', strtotime((string) $v['submitted_at']))) ?></div>
                            <?php else: ?>
                                <div><span class="hint">Created:</span> <?= e(date('M j, Y g:ia', strtotime((string) $v['created_at']))) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($v['reviewed_at'])): ?>
                                <div><span class="hint">Reviewed:</span> <?= e(date('M j, Y g:ia', strtotime((string) $v['reviewed_at']))) ?></div>
                                <?php if (!empty($v['reviewer_name'])): ?>
                                    <div class="hint">by <?= e($v['reviewer_name']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="vehicle-actions-cell col-actions" data-label="Actions" style="text-align:right;vertical-align:middle;">
                            <div class="vehicle-actions-vertical">
                                <a href="<?= e(url('/admin/vehicles/' . (int) $v['id'])) ?>" class="btn btn-ghost btn-sm btn-action-vertical">Inspect</a>

                                <?php if ($vStatus === 'approved'): ?>
                                    <button type="button" class="btn btn-ghost btn-sm btn-action-vertical btn-action-delete" onclick="openDeleteModal(<?= (int) $v['id'] ?>, '<?= e(addslashes((string) $v['name'])) ?>');">
                                        Delete
                                    </button>
                                <?php elseif ($vStatus === 'pending'): ?>
                                    <form method="post" action="<?= e(url('/admin/vehicles/' . (int) $v['id'] . '/approve')) ?>" class="vehicle-action-form" onsubmit="return confirm('Approve and publish this vehicle listing? It will become visible to tourists.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary btn-sm btn-action-vertical">✓ Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-ghost btn-sm btn-action-vertical btn-action-reject" style="color:var(--coral-600);" onclick="openRejectModal(<?= (int) $v['id'] ?>, '<?= e(addslashes((string) $v['name'])) ?>');">
                                        Reject
                                    </button>
                                <?php elseif ($vStatus === 'rejected'): ?>
                                    <form method="post" action="<?= e(url('/admin/vehicles/' . (int) $v['id'] . '/approve')) ?>" class="vehicle-action-form" onsubmit="return confirm('Approve and publish this vehicle listing? It will become visible to tourists.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary btn-sm btn-action-vertical">✓ Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-ghost btn-sm btn-action-vertical btn-action-delete" onclick="openDeleteModal(<?= (int) $v['id'] ?>, '<?= e(addslashes((string) $v['name'])) ?>');">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="vehicleFilterEmptyRow" <?= $initialVisibleCount === 0 ? '' : 'style="display:none;"' ?>>
                        <td colspan="6" style="padding:0;">
                            <div class="empty-state" style="padding:3rem 1rem;">
                                <div class="big">🚗</div>
                                <h3>No vehicle listings found</h3>
                                <p>There are no vehicle listings matching your current search and filter selection.</p>
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
                <h3 id="rejectModalTitle" class="admin-reject-title" style="margin:0;">Reject Vehicle Listing</h3>
            </div>
            <button type="button" class="gm-modal-close" onclick="closeRejectModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="adminRejectForm" method="post" action="">
            <?= csrf_field() ?>
            <div class="gm-modal-body admin-reject-body">
                <p class="hint admin-reject-hint" style="margin-top:0;">
                    Please provide an optional reason or specific correction request for the rental partner. This will be shown on their dashboard so they can revise and resubmit.
                </p>
                <div class="field-row" style="margin-bottom:0;">
                    <label for="rejectReasonInput" class="admin-reject-label">
                        Rejection Reason <small class="hint admin-reject-label-hint">(optional)</small>
                    </label>
                    <textarea class="input admin-reject-textarea" id="rejectReasonInput" name="reason" rows="4" placeholder="e.g. Please upload clear photos of the vehicle registration and specify transmission type."></textarea>
                </div>
            </div>
            <div class="gm-modal-foot admin-reject-foot">
                <button type="button" class="btn btn-ghost btn-cancel-reject" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-confirm-reject" onclick="return confirm('Confirm rejection of this vehicle listing?');">Confirm Rejection</button>
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
                <h3 id="deleteModalTitle" class="admin-delete-title" style="margin:0;">Permanently Delete Vehicle</h3>
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
                        All associated vehicle photos, specifications, and rental records will be completely removed from the database.
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
let currentDeleteId = null;

function openRejectModal(vehicleId, vehicleName) {
    var modal = document.getElementById('adminRejectModal');
    var form = document.getElementById('adminRejectForm');
    var title = document.getElementById('rejectModalTitle');
    var input = document.getElementById('rejectReasonInput');
    
    form.action = '<?= e(url('/admin/vehicles/')) ?>' + vehicleId + '/reject';
    title.textContent = 'Reject: ' + vehicleName;
    input.value = '';
    
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open');
    input.focus();
}

function closeRejectModal() {
    var modal = document.getElementById('adminRejectModal');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('is-open');
}

function openDeleteModal(vehicleId, vehicleName) {
    currentDeleteId = vehicleId;
    var modal = document.getElementById('adminDeleteModal');
    var form = document.getElementById('adminDeleteForm');
    var nameSpan = document.getElementById('deleteItemName');
    var btn = document.getElementById('confirmDeleteBtn');
    
    form.action = '<?= e(url('/admin/vehicles/')) ?>' + vehicleId + '/delete';
    nameSpan.textContent = vehicleName;
    btn.disabled = false;
    btn.textContent = 'Permanently Delete';
    
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open');
    btn.focus();
}

function closeDeleteModal() {
    var modal = document.getElementById('adminDeleteModal');
    if (modal) {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
    }
    currentDeleteId = null;
}

function handleDeleteSubmit(event) {
    event.preventDefault();
    if (!currentDeleteId) return;
    
    var form = document.getElementById('adminDeleteForm');
    var btn = document.getElementById('confirmDeleteBtn');
    var targetId = currentDeleteId;
    var tokenInput = form.querySelector('input[name="_csrf"]') || form.querySelector('input[name="_token"]');
    var token = tokenInput ? tokenInput.value : '';
    
    btn.disabled = true;
    btn.textContent = 'Deleting...';
    
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
    .then(function(res) {
        return res.json().then(function(data) {
            return { ok: res.ok, status: res.status, data: data };
        });
    })
    .then(function(res) {
        if (!res.ok || !res.data.success) {
            throw new Error((res.data && res.data.message) ? res.data.message : 'Deletion failed with status ' + res.status);
        }
        
        closeDeleteModal();
        showAdminAlert('success', res.data.message || 'Vehicle listing permanently deleted.');
        
        var row = document.getElementById('vehicle-row-' + targetId) || document.querySelector('tr[data-id="' + targetId + '"]');
        if (row) {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            setTimeout(function() {
                row.remove();
                var remaining = document.querySelectorAll('.vehicle-listing-row:not([hidden])');
                var emptyRow = document.getElementById('vehicleFilterEmptyRow');
                if (emptyRow && remaining.length === 0) {
                    emptyRow.style.display = '';
                }
            }, 300);
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Permanently Delete';
        showAdminAlert('error', err.message || 'Failed to delete listing. Please try again.');
    });
}

function showAdminAlert(type, message) {
    var container = document.getElementById('adminAlertContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'adminAlertContainer';
        container.style.cssText = 'position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.75rem;max-width:420px;pointer-events:none;';
        document.body.appendChild(container);
    }
    var isSuccess = type === 'success';
    var alert = document.createElement('div');
    alert.className = 'status-banner ' + (isSuccess ? 'banner-green' : 'banner-red');
    alert.style.cssText = 'pointer-events:auto;margin:0;box-shadow:0 10px 30px rgba(0,0,0,0.5);border-radius:10px;padding:0.9rem 1.25rem;display:flex;align-items:center;gap:0.75rem;background:' + (isSuccess ? '#064e3b' : '#7f1d1d') + ';color:#ffffff;border:1px solid ' + (isSuccess ? '#059669' : '#dc2626') + ';animation:gmModalIn 0.2s ease-out;';
    alert.innerHTML = '<span style="font-size:1.25rem;">' + (isSuccess ? '✓' : '⚠️') + '</span>' +
        '<div style="flex:1;font-size:0.9rem;font-weight:500;line-height:1.4;">' + message + '</div>' +
        '<button type="button" style="background:none;border:none;color:rgba(255,255,255,0.7);cursor:pointer;font-size:1.25rem;line-height:1;padding:0 4px;" onclick="this.parentElement.remove()">&times;</button>';
    container.appendChild(alert);
    setTimeout(function() {
        alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(function() { alert.remove(); }, 400);
    }, 5000);
}

// Global Escape key listener for modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var rejectModal = document.getElementById('adminRejectModal');
        if (rejectModal && !rejectModal.hidden) {
            closeRejectModal();
        }
        var deleteModal = document.getElementById('adminDeleteModal');
        if (deleteModal && !deleteModal.hidden) {
            closeDeleteModal();
        }
    }
});

// Interactive Search and Filter Dropdowns
document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('vehicleSearchInput');
    var searchClear = document.getElementById('vehicleSearchClear');
    var statusBtn = document.getElementById('vehicleStatusBtn');
    var statusPop = document.getElementById('vehicleStatusPopover');
    var statusLabel = document.getElementById('vehicleStatusLabel');
    var statusReset = document.getElementById('vehicleStatusReset');
    var typeBtn = document.getElementById('vehicleTypeBtn');
    var typePop = document.getElementById('vehicleTypePopover');
    var typeLabel = document.getElementById('vehicleTypeLabel');
    var typeReset = document.getElementById('vehicleTypeReset');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.vehicle-listing-row'));
    var emptyRow = document.getElementById('vehicleFilterEmptyRow');

    var curStatus = '<?= e($status) ?>';
    var curType = '<?= e($type) ?>';

    function closeAllPopovers() {
        if (statusPop) { statusPop.hidden = true; statusPop.classList.remove('open'); }
        if (statusBtn) { statusBtn.setAttribute('aria-expanded', 'false'); }
        if (typePop) { typePop.hidden = true; typePop.classList.remove('open'); }
        if (typeBtn) { typeBtn.setAttribute('aria-expanded', 'false'); }
    }

    function applyFilters() {
        var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
        if (searchClear) {
            searchClear.hidden = (q.length === 0);
        }

        var visibleCount = 0;
        rows.forEach(function (row) {
            var rowStatus = row.getAttribute('data-status') || '';
            var rowType = row.getAttribute('data-type') || '';
            var matchesStatus = (curStatus === 'all' || rowStatus === curStatus);
            var matchesType = (curType === 'all' || rowType === curType);

            var matchesSearch = true;
            if (q !== '') {
                var title = row.getAttribute('data-title') || '';
                var partner = row.getAttribute('data-partner') || '';
                matchesSearch = (title.indexOf(q) !== -1 || partner.indexOf(q) !== -1 || rowType.indexOf(q) !== -1);
            }

            var show = matchesStatus && matchesType && matchesSearch;
            row.hidden = !show;
            if (show) visibleCount++;
        });

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
        }

        try {
            var newUrl = new URL(window.location.href);
            if (curStatus === 'all') { newUrl.searchParams.delete('status'); } else { newUrl.searchParams.set('status', curStatus); }
            if (curType === 'all') { newUrl.searchParams.delete('type'); } else { newUrl.searchParams.set('type', curType); }
            if (q === '') { newUrl.searchParams.delete('q'); } else { newUrl.searchParams.set('q', q); }
            window.history.replaceState({}, '', newUrl.toString());
        } catch (e) {}
    }

    // Toggle Status Popover
    if (statusBtn && statusPop) {
        statusBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = !statusPop.hidden;
            closeAllPopovers();
            if (!isOpen) {
                statusPop.hidden = false;
                statusPop.classList.add('open');
                statusBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // Toggle Type Popover
    if (typeBtn && typePop) {
        typeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = !typePop.hidden;
            closeAllPopovers();
            if (!isOpen) {
                typePop.hidden = false;
                typePop.classList.add('open');
                typeBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // Click outside to close popovers
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.filter-custom-dropdown')) {
            closeAllPopovers();
        }
    });

    // Option selection for Status
    if (statusPop) {
        statusPop.querySelectorAll('.filter-popover-item').forEach(function (item) {
            item.addEventListener('click', function () {
                var val = this.getAttribute('data-value') || 'all';
                curStatus = val;
                statusPop.querySelectorAll('.filter-popover-item').forEach(function (el) {
                    el.classList.remove('is-selected');
                    el.setAttribute('aria-selected', 'false');
                });
                this.classList.add('is-selected');
                this.setAttribute('aria-selected', 'true');
                if (statusLabel) {
                    statusLabel.textContent = this.querySelector('.filter-item-text').textContent.trim();
                }
                if (statusBtn) {
                    if (val !== 'all') {
                        statusBtn.classList.add('is-filtered');
                    } else {
                        statusBtn.classList.remove('is-filtered');
                    }
                }
                closeAllPopovers();
                applyFilters();
            });
        });
    }

    // Reset button for Status
    if (statusReset) {
        statusReset.addEventListener('click', function (e) {
            e.stopPropagation();
            curStatus = 'all';
            if (statusPop) {
                statusPop.querySelectorAll('.filter-popover-item').forEach(function (el) {
                    var isAll = el.getAttribute('data-value') === 'all';
                    el.classList.toggle('is-selected', isAll);
                    el.setAttribute('aria-selected', isAll ? 'true' : 'false');
                });
            }
            if (statusLabel) { statusLabel.textContent = 'All Listings'; }
            if (statusBtn) { statusBtn.classList.remove('is-filtered'); }
            closeAllPopovers();
            applyFilters();
        });
    }

    // Option selection for Type
    if (typePop) {
        typePop.querySelectorAll('.filter-popover-item').forEach(function (item) {
            item.addEventListener('click', function () {
                var val = this.getAttribute('data-value') || 'all';
                curType = val;
                typePop.querySelectorAll('.filter-popover-item').forEach(function (el) {
                    el.classList.remove('is-selected');
                    el.setAttribute('aria-selected', 'false');
                });
                this.classList.add('is-selected');
                this.setAttribute('aria-selected', 'true');
                if (typeLabel) {
                    typeLabel.textContent = this.querySelector('.filter-item-text').textContent.trim();
                }
                if (typeBtn) {
                    if (val !== 'all') {
                        typeBtn.classList.add('is-filtered');
                    } else {
                        typeBtn.classList.remove('is-filtered');
                    }
                }
                closeAllPopovers();
                applyFilters();
            });
        });
    }

    // Reset button for Type
    if (typeReset) {
        typeReset.addEventListener('click', function (e) {
            e.stopPropagation();
            curType = 'all';
            if (typePop) {
                typePop.querySelectorAll('.filter-popover-item').forEach(function (el) {
                    var isAll = el.getAttribute('data-value') === 'all';
                    el.classList.toggle('is-selected', isAll);
                    el.setAttribute('aria-selected', isAll ? 'true' : 'false');
                });
            }
            if (typeLabel) { typeLabel.textContent = 'All Types'; }
            if (typeBtn) { typeBtn.classList.remove('is-filtered'); }
            closeAllPopovers();
            applyFilters();
        });
    }

    // Search input typing
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            applyFilters();
        });
    }

    // Search clear button
    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            applyFilters();
        });
    }
});
</script>
