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
                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <a href="<?= e(url('/dashboard/vehicles/create')) ?>" class="btn btn-primary" data-action="new-listing" data-open-new-listing>+ List a Vehicle</a>
                    <?php if (!$isVerified): ?>
                        <a href="<?= e(url('/documents')) ?>" class="btn btn-ghost"><?= admin_icon('guides', 16) ?> Complete Verification</a>
                    <?php endif; ?>
                </div>
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

            <div class="stat-grid" style="margin-bottom:1.5rem;">
                <div class="stat stat-ico-card">
                    <span class="stat-ico"><?= admin_icon('listings', 18) ?></span>
                    <div class="label">Total Vehicles</div>
                    <div class="value"><?= (int) ($stats['total'] ?? 0) ?></div>
                </div>
                <div class="stat stat-ico-card">
                    <span class="stat-ico"><?= admin_icon('check-circle', 18) ?></span>
                    <div class="label">Approved &amp; Available</div>
                    <div class="value"><?= (int) ($stats['approved'] ?? 0) ?></div>
                </div>
                <div class="stat stat-ico-card">
                    <span class="stat-ico"><?= admin_icon('clock', 18) ?></span>
                    <div class="label">Pending Approval</div>
                    <div class="value"><?= (int) ($stats['pending'] ?? 0) ?></div>
                </div>
                <div class="stat stat-ico-card <?= ($stats['rejected'] ?? 0) > 0 ? 'stat-alert' : '' ?>">
                    <span class="stat-ico"><?= admin_icon('disputes', 18) ?></span>
                    <div class="label">Action Needed</div>
                    <div class="value"><?= (int) ($stats['rejected'] ?? 0) ?></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h3>Vehicle Inventory (<?= count($vehicles) ?>)</h3>
                    <a href="<?= e(url('/dashboard/vehicles/create')) ?>" class="btn btn-primary btn-sm" data-action="new-listing" data-open-new-listing>+ List Vehicle</a>
                </div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($vehicles === []): ?>
                        <div class="empty-state" style="padding:3rem 1rem;">
                            <div class="big">🚗</div>
                            <h3>No vehicle listings yet</h3>
                            <p>Add your scooters, motorcycles, cars, or vans so tourists traveling in Cebu can rent from you.</p>
                            <a href="<?= e(url('/dashboard/vehicles/create')) ?>" class="btn btn-primary mt-2" data-action="new-listing" data-open-new-listing>+ List your first vehicle</a>
                        </div>
                    <?php else: ?>
                        <table class="table table-vehicles">
                            <thead>
                                <tr>
                                    <th class="col-vehicle">Vehicle</th>
                                    <th>Type</th>
                                    <th>Daily Rate</th>
                                    <th>Status</th>
                                    <th>Availability</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($vehicles as $v):
                                $vStatus = (string) ($v['status'] ?? 'draft');
                                $isAvail = (int) ($v['is_available'] ?? 0) === 1;
                                $canToggle = in_array($vStatus, ['approved', 'unavailable'], true);
                            ?>
                                <tr>
                                    <td class="vehicle-cell">
                                        <div class="vehicle-meta-wrap">
                                            <img src="<?= e(img_src($v['image'] ?? null, 'vehicle-' . $v['id'])) ?>" alt="" class="vehicle-thumb">
                                            <div class="vehicle-info">
                                                <strong class="vehicle-title"><?= e($v['name']) ?></strong>
                                                <div class="hint vehicle-sub"><?= e($v['location']) ?> · <?= (int) $v['year'] ?> <?= e($v['make']) ?></div>
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
                                    <td><?= e($v['type']) ?></td>
                                    <td><strong><?= money((float) $v['price_per_day']) ?></strong> <small class="hint">/ day</small></td>
                                    <td>
                                        <span class="pill <?= e(\App\Models\Vehicle::statusPillClass($vStatus)) ?>">
                                            <?= e(\App\Models\Vehicle::statusLabel($vStatus)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($canToggle): ?>
                                            <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/toggle-availability')) ?>" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-xs <?= $isAvail ? 'btn-ghost' : 'btn-primary' ?>" title="Click to toggle availability">
                                                    <?= $isAvail ? '🟢 Available' : '⏸️ Unavailable' ?>
                                                </button>
                                            </form>
                                        <?php elseif ($vStatus === 'pending'): ?>
                                            <span class="hint" style="font-size:0.8rem;">🔒 Pending Admin Approval</span>
                                        <?php elseif ($vStatus === 'rejected'): ?>
                                            <span class="hint" style="font-size:0.8rem;color:var(--coral-600);">Requires revision</span>
                                        <?php else: ?>
                                            <span class="hint" style="font-size:0.8rem;">Draft (Unsubmitted)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;text-align:right;">
                                        <a href="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/edit')) ?>" class="btn btn-ghost btn-sm">Edit</a>
                                        <?php if (in_array($vStatus, ['rejected', 'draft'], true)): ?>
                                            <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/resubmit')) ?>" style="display:inline;" onsubmit="return confirm('Submit this vehicle for administrator review?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/delete')) ?>" style="display:inline;" onsubmit="return confirm('Delete this vehicle listing? This action cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--coral-600);">Delete</button>
                                        </form>
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
