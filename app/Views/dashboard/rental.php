<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $requests
 * @var array<string,mixed> $stats
 * @var array<string,mixed> $guideStats
 * @var int $unread
 */
$status = (string) ($user['guide_status'] ?? 'none');
$isVerified = $status === 'approved';
$guideStats = $guideStats ?? [];
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0"><?= !empty($isFirstLogin) ? 'Welcome to GuideMate' : 'Welcome back' ?>, <?= e(explode(' ', (string) $user['name'])[0]) ?></h1>
                    <p class="hint mb-0">Rental Partner dashboard</p>
                </div>
                <?php if ($isVerified): ?>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <a href="<?= e(url('/dashboard/vehicles/create')) ?>" class="btn btn-primary">+ List a Vehicle</a>
                        <a href="<?= e(url('/dashboard/vehicles')) ?>" class="btn btn-ghost"><?= admin_icon('listings', 16) ?> My Vehicles (<?= (int) ($vehicleStats['total'] ?? 0) ?>)</a>
                        <a href="<?= e(url('/dashboard/rentals')) ?>" class="btn btn-ghost"><?= admin_icon('rentals', 16) ?> Requests</a>
                    </div>
                <?php else: ?>
                    <a href="<?= e(url('/dashboard/verification')) ?>" class="btn btn-ghost"><?= admin_icon('guides', 16) ?>Verification</a>
                <?php endif; ?>
            </div>

            <?php if (!$isVerified): ?>
                <div class="status-banner <?= $status === 'rejected' ? 'banner-red' : 'banner-amber' ?>" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= $status === 'rejected' ? admin_icon('disputes', 22) : admin_icon('clock', 22) ?></span>
                    <div>
                        <strong><?= $status === 'rejected' ? 'Application not approved' : 'Verification pending' ?></strong>
                        <p class="mb-0">
                            <?= $status === 'rejected'
                                ? 'Your rental partner application was not approved. '
                                : 'An admin is reviewing your documents. ' ?>
                            You can manage rental requests once verified.
                            <a href="<?= e(url('/dashboard/verification')) ?>">View status →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($vehicleStats['rejected'])): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= admin_icon('disputes', 22) ?></span>
                    <div>
                        <strong>Action Needed: Rejected Vehicle Listing(s)</strong>
                        <p class="mb-0">
                            You have <?= (int) $vehicleStats['rejected'] ?> vehicle listing(s) that require revision.
                            <a href="<?= e(url('/dashboard/vehicles')) ?>">Review admin feedback & resubmit →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="stat-grid">
                <a href="<?= e(url('/dashboard/vehicles')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('listings', 18) ?></span>
                    <div class="label">Total vehicles</div>
                    <div class="value"><?= (int) ($vehicleStats['total'] ?? 0) ?></div>
                    <div class="stat-action">Manage listings →</div>
                </a>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('check-circle', 18) ?></span><div class="label">Approved / Available</div><div class="value"><?= (int) ($vehicleStats['approved'] ?? 0) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('clock', 18) ?></span><div class="label">Pending review</div><div class="value"><?= (int) ($vehicleStats['pending'] ?? 0) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('revenue', 18) ?></span><div class="label">Revenue</div><div class="value" style="font-size:1.4rem;"><?= money((float) ($stats['revenue'] ?? 0)) ?></div></div>
            </div>

            <div class="stat-grid" style="margin-top:1rem;">
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('rentals', 18) ?></span><div class="label">Total requests</div><div class="value"><?= (int) ($stats['total'] ?? 0) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('star', 18) ?></span><div class="label">Avg rating</div><div class="value"><?= e((string) ($guideStats['avg_rating'] ?? 0)) ?></div></div>
                <a href="<?= e(url('/dashboard/reviews')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('feedback', 18) ?></span>
                    <div class="label">Reviews</div>
                    <div class="value"><?= (int) ($guideStats['review_count'] ?? 0) ?></div>
                    <div class="stat-action">See comments →</div>
                </a>
            </div>

            <div class="panel" style="margin-bottom:1.5rem;">
                <div class="panel-head">
                    <h3>My Vehicle Listings</h3>
                    <div>
                        <?php if ($isVerified): ?>
                            <a href="<?= e(url('/dashboard/vehicles/create')) ?>" class="btn btn-primary btn-sm">+ List Vehicle</a>
                        <?php endif; ?>
                        <a href="<?= e(url('/dashboard/vehicles')) ?>" class="btn btn-ghost btn-sm">View All</a>
                    </div>
                </div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if (empty($vehicles)): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p>You have not listed any vehicles yet.</p>
                            <?php if ($isVerified): ?>
                                <a href="<?= e(url('/dashboard/vehicles/create')) ?>" class="btn btn-primary btn-sm" style="margin-top:0.5rem;">List your first vehicle</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th>Vehicle</th><th>Type</th><th>Daily Rate</th><th>Location</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($vehicles as $v): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:0.75rem;">
                                            <img src="<?= e(img_src($v['image'] ?? null, 'vehicle-' . $v['id'])) ?>" alt="" style="width:48px;height:36px;object-fit:cover;border-radius:4px;">
                                            <strong><?= e($v['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td><?= e($v['type']) ?></td>
                                    <td><?= money((float) $v['price_per_day']) ?>/day</td>
                                    <td><?= e($v['location']) ?></td>
                                    <td><span class="pill <?= e(\App\Models\Vehicle::statusPillClass((string) $v['status'])) ?>"><?= e(\App\Models\Vehicle::statusLabel((string) $v['status'])) ?></span></td>
                                    <td><a href="<?= e(url('/dashboard/vehicles/' . (int) $v['id'] . '/edit')) ?>" class="btn btn-ghost btn-xs">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h3>Recent requests</h3><a href="<?= e(url('/dashboard/rentals')) ?>" class="btn btn-ghost btn-sm">Manage</a></div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($requests === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;"><p>No rental requests yet.</p></div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th>Customer</th><th>Vehicle</th><th>Pickup</th><th>Total</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?= e($r['customer_name']) ?></td>
                                    <td><?= e($r['vehicle_name']) ?></td>
                                    <td><?= e(date('M j, Y', strtotime((string) $r['pickup_date']))) ?></td>
                                    <td><?= money((float) $r['total_amount']) ?></td>
                                    <td><span class="pill pill-<?= e($r['status']) ?>"><?= e(\App\Models\RentalRequest::statusLabel((string) $r['status'])) ?></span></td>
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
