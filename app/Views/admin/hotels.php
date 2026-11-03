<?php
/**
 * Admin Hotel Listings Review
 *
 * @var array<int,array<string,mixed>> $hotels
 * @var string $status
 * @var int $pendingCount
 */

$status = (string) ($status ?? 'all');
$q = (string) ($_GET['q'] ?? '');

$statusCounts = [
    'all' => count($hotels),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

foreach ($hotels as $h) {
    $st = (string) ($h['status'] ?? 'pending');
    if (isset($statusCounts[$st])) {
        $statusCounts[$st]++;
    }
}

$statusLabels = [
    'all' => 'All Listings',
    'pending' => 'Pending Review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];

$activeStatusLabel = $statusLabels[$status] ?? 'All Listings';
$isStatusFiltered = ($status !== 'all');
?>
<div class="section-head" style="margin-bottom:1.25rem;">
    <div>
        <h1 class="mb-0">Hotel Listings Review</h1>
        <p class="hint mb-0">Review submitted hotels from accommodation partners, inspect photos and amenities, and approve or reject listings.</p>
    </div>
</div>

<!-- Unified Search & Filter Toolbar matching Users, Guide, and Vehicle sections -->
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
                   id="hotelSearchInput"
                   class="filter-input users-search-input"
                   placeholder="Search"
                   value="<?= e($q) ?>"
                   aria-label="Search hotel listings"
                   autocomplete="off"
                   spellcheck="false">
            <button type="button" id="hotelSearchClear" class="users-search-clear" aria-label="Clear search" <?= $q === '' ? 'hidden' : '' ?>>&times;</button>
        </div>

        <!-- Status Custom Filter Dropdown -->
        <div class="filter-control filter-custom-dropdown" data-filter="status" id="hotelStatusFilterControl">
            <button type="button"
                    class="filter-dropdown-btn <?= $isStatusFiltered ? 'is-filtered' : '' ?>"
                    id="hotelStatusBtn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="hotelStatusPopover"
                    aria-label="Filter by listing status">
                <span class="filter-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="6"></circle>
                        <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>
                    </svg>
                </span>
                <span class="filter-dropdown-label" id="hotelStatusLabel"><?= e($activeStatusLabel) ?></span>
                <span class="filter-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="filter-dropdown-popover" id="hotelStatusPopover" role="listbox" aria-labelledby="hotelStatusBtn" hidden>
                <div class="filter-popover-head">
                    <span class="filter-popover-title">Filter by Status</span>
                    <button type="button" class="filter-popover-reset" id="hotelStatusReset">Reset</button>
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
    </div>
</div>

<div class="panel">
    <div class="panel-body vehicles-table-wrap" style="padding:0;">
        <?php if ($hotels === []): ?>
            <div class="empty-state" style="padding:3rem 1rem;">
                <div class="big">🏨</div>
                <h3>No hotel listings found</h3>
                <p>There are no hotel listings submitted yet.</p>
            </div>
        <?php else: ?>
            <table class="table table-vehicles table-hotels" id="hotelListingsTable">
                <thead>
                    <tr>
                        <th class="col-vehicle">Hotel</th>
                        <th class="col-partner">Partner</th>
                        <th class="col-type">Location / Rate</th>
                        <th class="col-status">Status</th>
                        <th class="col-audit">Dates / Audit</th>
                        <th class="col-actions" style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $initialVisibleCount = 0;
                foreach ($hotels as $h):
                    $hStatus = (string) ($h['status'] ?? 'pending');
                    $amenities = !empty($h['included']) ? array_map('trim', explode(',', (string) $h['included'])) : [];
                    $roomCount = (int) ($h['room_count'] ?? 0);
                    $thumb = !empty($h['cover_image']) ? $h['cover_image'] : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';

                    $areaRaw = strtolower((string) ($h['area'] ?? ''));
                    $titleLower = strtolower((string) ($h['title'] ?? ''));
                    $partnerLower = strtolower((string) (($h['provider_name'] ?? '') . ' ' . ($h['provider_email'] ?? '')));
                    $areaLower = $areaRaw;

                    $matchesStatus = ($status === 'all' || $hStatus === $status);
                    $matchesSearch = true;
                    if ($q !== '') {
                        $qLower = strtolower($q);
                        $matchesSearch = (str_contains($titleLower, $qLower) || str_contains($partnerLower, $qLower) || str_contains($areaLower, $qLower));
                    }
                    $rowMatches = ($matchesStatus && $matchesSearch);
                    if ($rowMatches) {
                        $initialVisibleCount++;
                    }
                ?>
                    <tr id="hotel-row-<?= (int) $h['id'] ?>"
                        data-id="<?= (int) $h['id'] ?>"
                        data-status="<?= e($hStatus) ?>"
                        data-title="<?= e($titleLower) ?>"
                        data-partner="<?= e($partnerLower) ?>"
                        data-area="<?= e($areaLower) ?>"
                        class="hotel-listing-row"
                        <?= $rowMatches ? '' : 'hidden' ?>>
                        <td class="vehicle-cell col-vehicle" data-label="Hotel">
                            <div class="vehicle-meta-wrap">
                                <img src="<?= e(img_src($thumb, 'hotel-' . $h['id'])) ?>" alt="" class="vehicle-thumb" style="width:64px;height:48px;border-radius:6px;object-fit:cover;">
                                <div class="vehicle-info">
                                    <a href="<?= e(url('/admin/hotels/' . (int) $h['id'])) ?>"><strong class="vehicle-title"><?= e($h['title']) ?></strong></a>
                                </div>
                            </div>
                        </td>
                        <td class="col-partner" data-label="Partner">
                            <strong><?= e($h['provider_name']) ?></strong>
                            <div class="hint" style="font-size:0.8rem;word-break:break-all;"><?= e($h['provider_email']) ?></div>
                        </td>
                        <td class="col-type" data-label="Location / Rate">
                            <div><?= e($h['area']) ?></div>
                            <?php if ((float) ($h['price'] ?? 0) > 0): ?>
                                <strong><?= money((float) $h['price']) ?></strong> <small class="hint">/ night</small>
                            <?php else: ?>
                                <span class="hint" style="color:var(--text-muted, #64748B);font-style:italic;">Price unavailable</span>
                            <?php endif; ?>
                            <div style="margin-top:3px;">
                                <span class="pill <?= $roomCount > 0 ? 'pill-approved' : 'pill-pending' ?>" style="font-size:0.7rem;padding:2px 6px;">
                                    <?= $roomCount ?> <?= $roomCount === 1 ? 'room' : 'rooms' ?>
                                </span>
                            </div>
                        </td>
                        <td class="col-status" data-label="Status">
                            <span class="pill <?= e(\App\Models\Listing::hotelStatusPillClass($hStatus)) ?>">
                                <?= e(\App\Models\Listing::hotelStatusLabel($hStatus)) ?>
                            </span>
                        </td>
                        <td class="col-audit" data-label="Dates / Audit" style="font-size:0.8rem;">
                            <?php if (!empty($h['submitted_at'])): ?>
                                <div><span class="hint">Submitted:</span> <?= e(date('M j, Y g:ia', strtotime((string) $h['submitted_at']))) ?></div>
                            <?php else: ?>
                                <div><span class="hint">Created:</span> <?= e(date('M j, Y g:ia', strtotime((string) $h['created_at']))) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($h['reviewed_at'])): ?>
                                <div><span class="hint">Reviewed:</span> <?= e(date('M j, Y g:ia', strtotime((string) $h['reviewed_at']))) ?></div>
                                <?php if (!empty($h['reviewer_name'])): ?>
                                    <div class="hint">by <?= e($h['reviewer_name']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="vehicle-actions-cell col-actions" data-label="Actions" style="text-align:right;vertical-align:middle;">
                            <div class="vehicle-actions-vertical">
                                <a href="<?= e(url('/admin/hotels/' . (int) $h['id'])) ?>" class="btn btn-ghost btn-sm btn-action-vertical">Inspect</a>

                                <?php if ($hStatus !== 'approved'): ?>
                                    <form method="post" action="<?= e(url('/admin/hotels/' . (int) $h['id'] . '/approve')) ?>" class="vehicle-action-form" onsubmit="return confirm('Approve and publish this hotel listing? It will become visible to travelers and the partner can manage room types.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary btn-sm btn-action-vertical">✓ Approve</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($hStatus !== 'rejected'): ?>
                                    <button type="button" class="btn btn-ghost btn-sm btn-action-vertical btn-action-reject" style="color:var(--coral-600);" onclick="openRejectModal(<?= (int) $h['id'] ?>, '<?= e(addslashes((string) $h['title'])) ?>');">
                                        Reject
                                    </button>
                                <?php endif; ?>

                                <?php if ($hStatus === 'rejected'): ?>
                                    <button type="button" class="btn btn-ghost btn-sm btn-action-vertical btn-action-delete" onclick="openDeleteModal(<?= (int) $h['id'] ?>, '<?= e(addslashes((string) $h['title'])) ?>');">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="hotelFilterEmptyRow" <?= $initialVisibleCount === 0 ? '' : 'style="display:none;"' ?>>
                        <td colspan="6" style="padding:0;">
                            <div class="empty-state" style="padding:3rem 1rem;">
                                <div class="big">🏨</div>
                                <h3>No hotel listings found</h3>
                                <p>There are no hotel listings matching your current search and filter selection.</p>
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
                <h3 id="rejectModalTitle" class="admin-reject-title" style="margin:0;">Reject Hotel Listing</h3>
            </div>
            <button type="button" class="gm-modal-close" onclick="closeRejectModal()" aria-label="Close dialog">&times;</button>
        </div>
        <form id="adminRejectForm" method="post" action="">
            <?= csrf_field() ?>
            <div class="gm-modal-body admin-reject-body">
                <p class="hint admin-reject-hint" style="margin-top:0;">
                    Please provide an optional reason or specific correction request for the hotel partner. This will be shown on their dashboard so they can revise and resubmit.
                </p>
                <div class="field-row" style="margin-bottom:0;">
                    <label for="rejectReasonInput" class="admin-reject-label">
                        Rejection Reason <small class="hint admin-reject-label-hint">(optional)</small>
                    </label>
                    <textarea class="input admin-reject-textarea" id="rejectReasonInput" name="reason" rows="4" placeholder="e.g. Please provide high-resolution photos of the property exterior and specify exact check-in hours."></textarea>
                </div>
            </div>
            <div class="gm-modal-foot admin-reject-foot">
                <button type="button" class="btn btn-ghost btn-cancel-reject" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-confirm-reject" onclick="return confirm('Confirm rejection of this hotel listing?');">Confirm Rejection</button>
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
                <h3 id="deleteModalTitle" class="admin-delete-title" style="margin:0;">Permanently Delete Hotel</h3>
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
                        All associated photos, configured room types, and listing details will be completely removed from the database.
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

function openRejectModal(hotelId, hotelTitle) {
    var modal = document.getElementById('adminRejectModal');
    var form = document.getElementById('adminRejectForm');
    var title = document.getElementById('rejectModalTitle');
    var input = document.getElementById('rejectReasonInput');
    
    form.action = '<?= e(url('/admin/hotels/')) ?>' + hotelId + '/reject';
    title.textContent = 'Reject: ' + hotelTitle;
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

function openDeleteModal(hotelId, hotelTitle) {
    currentDeleteId = hotelId;
    var modal = document.getElementById('adminDeleteModal');
    var form = document.getElementById('adminDeleteForm');
    var nameSpan = document.getElementById('deleteItemName');
    var btn = document.getElementById('confirmDeleteBtn');
    
    form.action = '<?= e(url('/admin/hotels/')) ?>' + hotelId + '/delete';
    nameSpan.textContent = hotelTitle;
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
        showAdminAlert('success', res.data.message || 'Hotel listing permanently deleted.');
        
        var row = document.getElementById('hotel-row-' + targetId) || document.querySelector('tr[data-id="' + targetId + '"]');
        if (row) {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            setTimeout(function() {
                row.remove();
                var remaining = document.querySelectorAll('.hotel-listing-row:not([hidden])');
                var emptyRow = document.getElementById('hotelFilterEmptyRow');
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
    var searchInput = document.getElementById('hotelSearchInput');
    var searchClear = document.getElementById('hotelSearchClear');
    var statusBtn = document.getElementById('hotelStatusBtn');
    var statusPop = document.getElementById('hotelStatusPopover');
    var statusLabel = document.getElementById('hotelStatusLabel');
    var statusReset = document.getElementById('hotelStatusReset');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.hotel-listing-row'));
    var emptyRow = document.getElementById('hotelFilterEmptyRow');

    var curStatus = '<?= e($status) ?>';

    function closeAllPopovers() {
        if (statusPop) { statusPop.hidden = true; statusPop.classList.remove('open'); }
        if (statusBtn) { statusBtn.setAttribute('aria-expanded', 'false'); }
    }

    function applyFilters() {
        var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
        if (searchClear) {
            searchClear.hidden = (q.length === 0);
        }

        var visibleCount = 0;
        rows.forEach(function (row) {
            var rowStatus = row.getAttribute('data-status') || '';
            var matchesStatus = (curStatus === 'all' || rowStatus === curStatus);

            var matchesSearch = true;
            if (q !== '') {
                var title = row.getAttribute('data-title') || '';
                var partner = row.getAttribute('data-partner') || '';
                var area = row.getAttribute('data-area') || '';
                matchesSearch = (title.indexOf(q) !== -1 || partner.indexOf(q) !== -1 || area.indexOf(q) !== -1);
            }

            var show = matchesStatus && matchesSearch;
            row.hidden = !show;
            if (show) visibleCount++;
        });

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
        }

        try {
            var newUrl = new URL(window.location.href);
            if (curStatus === 'all') { newUrl.searchParams.delete('status'); } else { newUrl.searchParams.set('status', curStatus); }
            newUrl.searchParams.delete('location');
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
