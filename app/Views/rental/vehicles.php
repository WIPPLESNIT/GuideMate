<?php
/**
 * @var array<int,array<string,mixed>> $vehicles
 * @var array<string,int> $stats
 * @var bool $isVerified
 * @var array<string,mixed> $user
 */
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0">My Vehicles</h1>
                    <p class="hint mb-0">Manage your rental fleet and track approval statuses.</p>
                </div>
                <?php if (!$isVerified): ?>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <a href="<?= e(url('/documents')) ?>" class="btn btn-ghost"><?= admin_icon('guides', 16) ?> Complete Verification</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$isVerified): ?>
                <div class="status-banner banner-amber" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= admin_icon('clock', 22) ?></span>
                    <div>
                        <strong>Rental Partner Verification Pending</strong>
                        <p class="mb-0">
                            Your rental partner documents are currently under administrative review. Once your account is verified, you will be able to list vehicles and accept reservations.
                            <a href="<?= e(url('/documents')) ?>">View verification status →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (($stats['rejected'] ?? 0) > 0): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= admin_icon('disputes', 22) ?></span>
                    <div class="status-content">
                        <strong class="status-title">Action Required: Rejected Vehicle Listing(s)</strong>
                        <p class="status-desc mb-0">
                            An administrator has requested changes on <?= (int) $stats['rejected'] ?> of your listings. Click <strong>Edit</strong> on the rejected vehicle to review the administrator's feedback and resubmit.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-head">
                    <h3>Vehicle Inventory (<?= count($vehicles) ?>)</h3>
                    <a href="<?= e(url('/dashboard/vehicles/create')) ?>" class="btn btn-primary btn-sm" data-action="new-listing" data-open-new-listing>+ List Vehicle</a>
                </div>
                <div class="panel-body rental-inventory-wrap" style="padding:0;">
                    <?php if ($vehicles === []): ?>
                        <div class="empty-state" style="padding:3rem 1rem;">
                            <div class="big">🚗</div>
                            <h3>No vehicle listings yet</h3>
                            <p>Add your scooters, motorcycles, cars, or vans so tourists traveling in Cebu can rent from you.</p>
                            <a href="<?= e(url('/dashboard/vehicles/create')) ?>" class="btn btn-primary mt-2" data-action="new-listing" data-open-new-listing>+ List your first vehicle</a>
                        </div>
                    <?php else: ?>
                        <table class="table rental-inventory-table">
                            <thead>
                                <tr>
                                    <th class="col-vehicle">Vehicle</th>
                                    <th class="col-type">Type</th>
                                    <th class="col-rate">Daily Rate</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-availability">Availability</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($vehicles as $v):
                                $vStatus = (string) ($v['status'] ?? 'draft');
                                $isAvail = (int) ($v['is_available'] ?? 0) === 1;
                                $canToggle = in_array($vStatus, ['approved', 'unavailable'], true);
                            ?>
                                <tr>
                                    <td class="col-vehicle">
                                        <div style="display:flex;align-items:center;gap:0.75rem;">
                                            <img src="<?= e(img_src($v['image'] ?? null, 'vehicle-' . $v['id'])) ?>" alt=""
                                                 style="width:52px;height:52px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#f1f5f9;">
                                            <div style="min-width:0;flex:1 1 auto;">
                                                <strong style="display:block;font-weight:700;color:var(--text, #0f172a);line-height:1.35;word-break:break-word;"><?= e($v['name']) ?></strong>
                                                <div class="hint" style="font-size:0.8rem;margin-top:0.15rem;word-break:break-word;">
                                                    <?= e($v['location']) ?> · <?= (int) $v['year'] ?> <?= e($v['make']) ?>
                                                </div>
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
                                                                Admin Note:
                                                            </span>
                                                            <?php if ($isLong): ?>
                                                                <button type="button" class="admin-note-toggle" aria-expanded="false" title="Expand or collapse admin note">
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
                                    <td class="col-type"><?= e($v['type']) ?></td>
                                    <td class="col-rate">
                                        <strong style="color:var(--brand, #059669);font-weight:700;"><?= money((float) $v['price_per_day']) ?></strong>
                                        <small class="hint">/ day</small>
                                    </td>
                                    <td class="col-status">
                                        <span class="pill <?= e(\App\Models\Vehicle::statusPillClass($vStatus)) ?>">
                                            <?= e(\App\Models\Vehicle::statusLabel($vStatus)) ?>
                                        </span>
                                    </td>
                                    <td class="col-availability">
                                        <?php if ($canToggle): ?>
                                            <span class="pill <?= $isAvail ? 'pill-available' : 'pill-unavailable' ?>" style="font-size:0.75rem;padding:3px 10px;">
                                                <?= $isAvail ? '● Available' : '● Unavailable' ?>
                                            </span>
                                        <?php elseif ($vStatus === 'pending'): ?>
                                            <span class="hint" style="font-size:0.8rem;white-space:nowrap;">Pending Approval</span>
                                        <?php elseif ($vStatus === 'rejected'): ?>
                                            <span class="hint" style="font-size:0.8rem;color:var(--coral-600);white-space:nowrap;">Requires revision</span>
                                        <?php else: ?>
                                            <span class="hint" style="font-size:0.8rem;white-space:nowrap;">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-actions">
                                        <div class="res-action-wrap">
                                            <button type="button"
                                                    class="btn-res-action-trigger"
                                                    id="vehicleActionBtn_<?= (int) $v['id'] ?>"
                                                    aria-haspopup="true"
                                                    aria-expanded="false"
                                                    aria-controls="vehicleActionMenu_<?= (int) $v['id'] ?>"
                                                    title="Actions for <?= e($v['name']) ?>"
                                                    aria-label="Actions for <?= e($v['name']) ?>">
                                                <?= admin_icon('more-vertical', 16) ?>
                                            </button>
                                            <div class="res-action-dropdown"
                                                 id="vehicleActionMenu_<?= (int) $v['id'] ?>"
                                                 role="menu"
                                                 aria-labelledby="vehicleActionBtn_<?= (int) $v['id'] ?>">
                                                <a href="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/edit')) ?>" class="res-action-item" role="menuitem">
                                                    <?= admin_icon('edit', 14) ?> Edit Vehicle
                                                </a>
                                                <?php if ($canToggle): ?>
                                                    <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/toggle-availability')) ?>" class="res-action-form">
                                                        <?= csrf_field() ?>
                                                        <?php if ($isAvail): ?>
                                                            <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                <?= admin_icon('x-circle', 14) ?> Mark as Unavailable
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                <?= admin_icon('check-circle', 14) ?> Mark as Available
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (in_array($vStatus, ['rejected', 'draft'], true)): ?>
                                                    <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/resubmit')) ?>" class="res-action-form" onsubmit="return confirm('Submit this vehicle for administrator review?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                            <?= admin_icon('check-circle', 14) ?> Submit for Review
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($vStatus === 'approved'): ?>
                                                    <a href="<?= e(url('/rentals/' . ($v['slug'] ?? $v['id']))) ?>" target="_blank" class="res-action-item" role="menuitem">
                                                        <?= admin_icon('eye', 14) ?> View Live Listing
                                                    </a>
                                                <?php endif; ?>
                                                <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/delete')) ?>" class="res-action-form" onsubmit="return confirm('Delete this vehicle listing? This action cannot be undone.');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                        <?= admin_icon('trash', 14) ?> Delete Vehicle
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
