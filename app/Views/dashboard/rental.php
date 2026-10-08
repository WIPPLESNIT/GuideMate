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
                    <a href="<?= e(url('/dashboard/rentals')) ?>" class="btn btn-primary">View rental requests</a>
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

            <div class="stat-grid">
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('rentals', 18) ?></span><div class="label">Total requests</div><div class="value"><?= (int) ($stats['total'] ?? 0) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('clock', 18) ?></span><div class="label">Pending</div><div class="value"><?= (int) ($stats['pending'] ?? 0) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('check-circle', 18) ?></span><div class="label">Active / approved</div><div class="value"><?= (int) ($stats['approved'] ?? 0) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('revenue', 18) ?></span><div class="label">Revenue</div><div class="value" style="font-size:1.4rem;"><?= money((float) ($stats['revenue'] ?? 0)) ?></div></div>
            </div>

            <div class="stat-grid" style="margin-top:1rem;">
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('star', 18) ?></span><div class="label">Avg rating</div><div class="value"><?= e((string) ($guideStats['avg_rating'] ?? 0)) ?></div></div>
                <a href="<?= e(url('/dashboard/reviews')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('feedback', 18) ?></span>
                    <div class="label">Reviews</div>
                    <div class="value"><?= (int) ($guideStats['review_count'] ?? 0) ?></div>
                    <div class="stat-action">See comments →</div>
                </a>
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
