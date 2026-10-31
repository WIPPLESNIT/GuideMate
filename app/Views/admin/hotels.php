<?php
/**
 * Admin Hotel Listings Review
 *
 * @var array<int,array<string,mixed>> $hotels
 * @var string $status
 * @var int $pendingCount
 */
?>
<div class="section-head" style="margin-bottom:1.25rem;">
    <div>
        <h1 class="mb-0">Hotel Listings Review</h1>
        <p class="hint mb-0">Review submitted hotels from accommodation partners, inspect photos and amenities, and approve or reject listings.</p>
    </div>
</div>

<div class="tabs" style="display:flex;gap:0.5rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">
    <a href="<?= e(url('/admin/hotels?status=pending')) ?>" class="btn btn-sm <?= $status === 'pending' ? 'btn-primary' : 'btn-ghost' ?>">
        Pending Review <?php if ($pendingCount > 0): ?><span class="badge" style="background:#ef4444;color:#fff;margin-left:4px;"><?= $pendingCount ?></span><?php endif; ?>
    </a>
    <a href="<?= e(url('/admin/hotels?status=approved')) ?>" class="btn btn-sm <?= $status === 'approved' ? 'btn-primary' : 'btn-ghost' ?>">Approved</a>
    <a href="<?= e(url('/admin/hotels?status=rejected')) ?>" class="btn btn-sm <?= $status === 'rejected' ? 'btn-primary' : 'btn-ghost' ?>">Rejected</a>
    <a href="<?= e(url('/admin/hotels?status=all')) ?>" class="btn btn-sm <?= $status === 'all' ? 'btn-primary' : 'btn-ghost' ?>">All Listings</a>
</div>

<div class="panel">
    <div class="panel-body vehicles-table-wrap" style="padding:0;">
        <?php if ($hotels === []): ?>
            <div class="empty-state" style="padding:3rem 1rem;">
                <div class="big">🏨</div>
                <h3>No hotel listings found</h3>
                <p>There are no hotel listings matching the current filter (<?= e(ucfirst($status)) ?>).</p>
            </div>
        <?php else: ?>
            <table class="table table-vehicles table-hotels">
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
                <?php foreach ($hotels as $h):
                    $hStatus = (string) ($h['status'] ?? 'pending');
                    $amenities = !empty($h['included']) ? array_map('trim', explode(',', (string) $h['included'])) : [];
                    $roomCount = (int) ($h['room_count'] ?? 0);
                    $thumb = !empty($h['cover_image']) ? $h['cover_image'] : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';
                ?>
                    <tr id="hotel-row-<?= (int) $h['id'] ?>" data-id="<?= (int) $h['id'] ?>" data-status="<?= e($hStatus) ?>">
                        <td class="vehicle-cell col-vehicle" data-label="Hotel">
                            <div class="vehicle-meta-wrap">
                                <img src="<?= e(img_src($thumb, 'hotel-' . $h['id'])) ?>" alt="" class="vehicle-thumb" style="width:64px;height:48px;border-radius:6px;object-fit:cover;">
                                <div class="vehicle-info">
                                    <a href="<?= e(url('/admin/hotels/' . (int) $h['id'])) ?>"><strong class="vehicle-title"><?= e($h['title']) ?></strong></a>
                                    <div class="hint vehicle-sub"><?= e($h['area']) ?><?php if (!empty($h['address'])): ?> · <?= e($h['address']) ?><?php endif; ?></div>
                                    <?php if ($amenities !== []): ?>
                                        <div class="hint" style="font-size:0.75rem;margin-top:2px;">
                                            <?= e(implode(' · ', array_slice($amenities, 0, 3))) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="col-partner" data-label="Partner">
                            <strong><?= e($h['provider_name']) ?></strong>
                            <div class="hint" style="font-size:0.8rem;word-break:break-all;"><?= e($h['provider_email']) ?></div>
                        </td>
                        <td class="col-type" data-label="Location / Rate">
                            <div><?= e($h['area']) ?></div>
                            <strong><?= money((float) $h['price']) ?></strong> <small class="hint">/ night</small>
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
    var tokenInput = form.querySelector('input[name="_token"]');
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
        body: '_token=' + encodeURIComponent(token)
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
                var tbody = document.querySelector('.table-hotels tbody');
                if (tbody && tbody.querySelectorAll('tr').length === 0) {
                    var wrap = document.querySelector('.vehicles-table-wrap');
                    if (wrap) {
                        wrap.innerHTML = '<div class="empty-state" style="padding:3rem 1rem;"><div class="big">🏨</div><h3>No hotel listings found</h3><p>There are no hotel listings matching the current filter.</p></div>';
                    }
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
</script>
