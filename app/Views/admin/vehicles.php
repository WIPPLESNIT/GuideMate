<?php
/**
 * @var array<int,array<string,mixed>> $vehicles
 * @var string $status
 * @var int $pendingCount
 */
?>
<div class="section-head" style="margin-bottom:1.25rem;">
    <div>
        <h1 class="mb-0">Vehicle Listings Review</h1>
        <p class="hint mb-0">Review submitted vehicles from rental partners, inspect photos and specifications, and approve or reject listings.</p>
    </div>
</div>

<div class="tabs" style="display:flex;gap:0.5rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">
    <a href="<?= e(url('/admin/vehicles?status=pending')) ?>" class="btn btn-sm <?= $status === 'pending' ? 'btn-primary' : 'btn-ghost' ?>">
        Pending Review <?php if ($pendingCount > 0): ?><span class="badge" style="background:#ef4444;color:#fff;margin-left:4px;"><?= $pendingCount ?></span><?php endif; ?>
    </a>
    <a href="<?= e(url('/admin/vehicles?status=approved')) ?>" class="btn btn-sm <?= $status === 'approved' ? 'btn-primary' : 'btn-ghost' ?>">Approved</a>
    <a href="<?= e(url('/admin/vehicles?status=rejected')) ?>" class="btn btn-sm <?= $status === 'rejected' ? 'btn-primary' : 'btn-ghost' ?>">Rejected</a>
    <a href="<?= e(url('/admin/vehicles?status=all')) ?>" class="btn btn-sm <?= $status === 'all' ? 'btn-primary' : 'btn-ghost' ?>">All Listings</a>
</div>

<div class="panel">
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <?php if ($vehicles === []): ?>
            <div class="empty-state" style="padding:3rem 1rem;">
                <div class="big">🚗</div>
                <h3>No vehicle listings found</h3>
                <p>There are no vehicle listings matching the current filter (<?= e(ucfirst($status)) ?>).</p>
            </div>
        <?php else: ?>
            <table class="table table-vehicles">
                <thead>
                    <tr>
                        <th class="col-vehicle">Vehicle</th>
                        <th>Partner</th>
                        <th>Type / Rate</th>
                        <th>Status</th>
                        <th>Dates / Audit</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($vehicles as $v):
                    $vStatus = (string) ($v['status'] ?? 'pending');
                    $specs = \App\Models\Vehicle::parseSpecs($v['specs'] ?? null);
                ?>
                    <tr>
                        <td class="vehicle-cell">
                            <div class="vehicle-meta-wrap">
                                <img src="<?= e(img_src($v['image'] ?? null, 'vehicle-' . $v['id'])) ?>" alt="" class="vehicle-thumb" style="width:64px;height:48px;">
                                <div class="vehicle-info">
                                    <a href="<?= e(url('/admin/vehicles/' . (int) $v['id'])) ?>"><strong class="vehicle-title"><?= e($v['name']) ?></strong></a>
                                    <div class="hint vehicle-sub"><?= e($v['location']) ?> · <?= (int) $v['year'] ?> <?= e($v['make']) ?></div>
                                    <?php if ($specs !== []): ?>
                                        <div class="hint" style="font-size:0.75rem;margin-top:2px;">
                                            <?= e(implode(' · ', array_slice($specs, 0, 3))) ?>
                                        </div>
                                    <?php endif; ?>
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
                        <td>
                            <strong><?= e($v['provider_name']) ?></strong>
                            <div class="hint" style="font-size:0.8rem;"><?= e($v['provider_email']) ?></div>
                        </td>
                        <td>
                            <div><?= e($v['type']) ?></div>
                            <strong><?= money((float) $v['price_per_day']) ?></strong> <small class="hint">/ day</small>
                        </td>
                        <td>
                            <span class="pill <?= e(\App\Models\Vehicle::statusPillClass($vStatus)) ?>">
                                <?= e(\App\Models\Vehicle::statusLabel($vStatus)) ?>
                            </span>
                        </td>
                        <td style="font-size:0.8rem;">
                            <?php if (!empty($v['submitted_at'])): ?>
                                <div><span class="hint">Submitted:</span> <?= e(date('M j, Y g:ia', strtotime((string) $v['submitted_at']))) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($v['reviewed_at'])): ?>
                                <div><span class="hint">Reviewed:</span> <?= e(date('M j, Y g:ia', strtotime((string) $v['reviewed_at']))) ?></div>
                                <?php if (!empty($v['reviewer_name'])): ?>
                                    <div class="hint">by <?= e($v['reviewer_name']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;text-align:right;">
                            <a href="<?= e(url('/admin/vehicles/' . (int) $v['id'])) ?>" class="btn btn-ghost btn-sm">Inspect</a>

                            <?php if ($vStatus !== 'approved'): ?>
                                <form method="post" action="<?= e(url('/admin/vehicles/' . (int) $v['id'] . '/approve')) ?>" style="display:inline;" onsubmit="return confirm('Approve and publish this vehicle listing? It will become visible to tourists immediately.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-primary btn-sm">✓ Approve</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($vStatus !== 'rejected'): ?>
                                <button type="button" class="btn btn-ghost btn-sm" style="color:var(--coral-600);" onclick="openRejectModal(<?= (int) $v['id'] ?>, '<?= e(addslashes((string) $v['name'])) ?>');">
                                    Reject
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal dialog for entering optional rejection reason -->
<div id="adminRejectModal" class="gm-modal" hidden aria-hidden="true">
    <div class="gm-modal-backdrop" onclick="closeRejectModal()"></div>
    <div class="gm-modal-dialog" role="dialog" aria-modal="true" style="max-width:480px;">
        <div class="gm-modal-head">
            <h3 id="rejectModalTitle" style="margin:0;">Reject Vehicle Listing</h3>
            <button type="button" class="gm-modal-close" onclick="closeRejectModal()" aria-label="Close">&times;</button>
        </div>
        <form id="adminRejectForm" method="post" action="">
            <?= csrf_field() ?>
            <div class="gm-modal-body">
                <p class="hint" style="margin-top:0;">
                    Please provide an optional reason or specific correction request for the rental partner. This will be shown on their dashboard so they can revise and resubmit.
                </p>
                <div class="field-row">
                    <label for="rejectReasonInput">Rejection Reason <small class="hint">(optional)</small></label>
                    <textarea class="input" id="rejectReasonInput" name="reason" rows="4" placeholder="e.g. Please provide a clearer photo of the vehicle registration and ensure helmet inclusions are noted."></textarea>
                </div>
            </div>
            <div class="gm-modal-foot" style="display:flex;justify-content:flex-end;gap:0.75rem;padding:1rem 1.5rem;border-top:1px solid var(--border);">
                <button type="button" class="btn btn-ghost" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:var(--coral-600);border-color:var(--coral-600);" onclick="return confirm('Confirm rejection of this listing?');">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
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
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('is-open');
}
</script>
