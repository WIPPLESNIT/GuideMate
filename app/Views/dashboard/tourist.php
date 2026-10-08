<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $bookings
 * @var int $bookingCount
 * @var array<int,array<string,mixed>> $favorites
 * @var int $unread
 */
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <h1><?= !empty($isFirstLogin) ? 'Welcome to GuideMate' : 'Hi' ?>, <?= e(explode(' ', $user['name'])[0]) ?> 👋</h1>
            <p class="hint">Here's what's happening with your Cebu trips.</p>

            <div class="stat-grid">
                <div class="stat"><div class="label">Bookings</div><div class="value"><?= $bookingCount ?></div></div>
                <div class="stat"><div class="label">Saved</div><div class="value"><?= count($favorites) ?></div></div>
                <div class="stat"><div class="label">Unread messages</div><div class="value"><?= $unread ?></div></div>
                <div class="stat"><div class="label">Member since</div><div class="value" style="font-size:1.1rem;"><?= e(date('M Y', strtotime($user['created_at']))) ?></div></div>
            </div>

            <div class="panel">
                <div class="panel-head"><h3>Recent bookings</h3>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                        <a href="<?= e(url('/bookings/map')) ?>" class="btn btn-ghost btn-sm">🗺️ Trip map</a>
                        <a href="<?= e(url('/bookings')) ?>" class="btn btn-ghost btn-sm">View all</a>
                    </div>
                </div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($bookings === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;"><p>No bookings yet. <a href="<?= e(url('/listings')) ?>">Explore Cebu →</a></p></div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th>Listing</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td><a href="<?= e(url('/listing/' . $b['listing_slug'])) ?>"><?= e($b['listing_title']) ?></a></td>
                                    <td><?= e(date('M j, Y', strtotime($b['booking_date']))) ?></td>
                                    <td><?= money($b['total_amount']) ?></td>
                                    <td><span class="pill pill-<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($favorites !== []): ?>
                <div class="panel-head" style="padding-left:0;"><h3>Saved listings</h3></div>
                <div class="card-grid">
                    <?php foreach (array_slice($favorites, 0, 3) as $l): ?>
                        <?= \App\Core\View::partial('partials/listing-card', ['l' => $l, 'favIds' => array_map(fn($f) => (int) $f['id'], $favorites)]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
