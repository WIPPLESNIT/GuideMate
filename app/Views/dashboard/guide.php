<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $listings
 * @var array<int,array<string,mixed>> $recentBookings
 * @var int $bookingCount @var float $revenue @var int $unread
 * @var bool $isWarned
 */
$approved = array_filter($listings, fn($l) => $l['status'] === 'approved');
$guideStatus = (string) ($user['guide_status'] ?? 'none');
$isVerified = $guideStatus === 'approved';
$isWarned = $isWarned ?? \App\Models\User::isGuideWarned((int) ($user['id'] ?? 0));
$warningNote = (string) ($user['guide_warning_note'] ?? '');

$earningsByMonth = $earningsByMonth ?? [];
$curCode = strtoupper((string) ($_SESSION['_currency'] ?? 'USD'));
$curRow = \App\Core\LocaleCatalog::currency($curCode);
$curRate = $curRow ? (float) $curRow['php_per_unit'] : 1.0;
$curSymbol = $curRow['symbol'] ?? '₱';
$earnLabels = array_map(static fn ($r) => date('M', strtotime(((string) $r['month']) . '-01')), $earningsByMonth);
$earnValues = array_map(static fn ($r) => round(((float) $r['total']) / ($curRate ?: 1), 2), $earningsByMonth);
$hasEarnings = array_sum($earnValues) > 0;
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0"><?= e(!empty($isFirstLogin) ? __('d_welcome_first', 'Welcome to GuideMate') : __('d_welcome', 'Welcome back')) ?>, <?= e(explode(' ', $user['name'])[0]) ?></h1>
                </div>
                <?php if ($isVerified): ?>
                    <a href="<?= e(url('/dashboard/listings/create')) ?>" class="btn btn-primary">+ <?= e(__('d_new_listing', 'New listing')) ?></a>
                <?php else: ?>
                    <a href="<?= e(url('/dashboard/verification')) ?>" class="btn btn-ghost"><?= admin_icon('guides', 16) ?><?= e(__('d_verification', 'Verification')) ?></a>
                <?php endif; ?>
            </div>

            <?php if ($isWarned): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= admin_icon('disputes', 22) ?></span>
                    <div>
                        <strong><?= e(__('d_warning_title', 'Administrator warning')) ?></strong>
                        <p class="mb-0">
                            <?= $warningNote !== '' ? e($warningNote) . ' ' : '' ?>
                            <?= e(__('d_warning_body', 'Booking actions are restricted until an admin clears this warning.')) ?>
                            <a href="<?= e(url('/messages')) ?>"><?= e(__('d_read_admin_msg', 'Read administrator message')) ?> →</a>
                        </p>
                    </div>
                </div>
            <?php elseif (!$isVerified): ?>
                <div class="status-banner <?= $guideStatus === 'rejected' ? 'banner-red' : 'banner-amber' ?>" style="margin-bottom:1.2rem;">
                    <span class="status-icon"><?= $guideStatus === 'rejected' ? admin_icon('disputes', 22) : admin_icon('clock', 22) ?></span>
                    <div>
                        <strong><?= $guideStatus === 'rejected' ? e(__('d_app_not_approved', 'Application not approved')) : e(__('d_verif_pending', 'Verification pending')) ?></strong>
                        <p class="mb-0">
                            <?= $guideStatus === 'rejected'
                                ? e(__('d_app_not_approved_body', 'Your application was not approved.')) . ' '
                                : e(__('d_verif_pending_body', 'An admin is reviewing your documents.')) . ' ' ?>
                            <?= e(__('d_publish_once_verified', 'You can publish listings once verified.')) ?>
                            <a href="<?= e(url('/dashboard/verification')) ?>"><?= e(__('d_view_status', 'View status')) ?> →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="stat-grid">
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('listings', 18) ?></span><div class="label"><?= e(__('d_stat_listings', 'Listings')) ?></div><div class="value"><?= count($listings) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('check-circle', 18) ?></span><div class="label"><?= e(__('d_stat_live', 'Live')) ?></div><div class="value"><?= count($approved) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('bookings', 18) ?></span><div class="label"><?= e(__('d_stat_bookings', 'Bookings')) ?></div><div class="value"><?= $bookingCount ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('revenue', 18) ?></span><div class="label"><?= e(__('d_stat_revenue', 'Revenue')) ?></div><div class="value" style="font-size:1.4rem;"><?= money($revenue) ?></div></div>
            </div>

            <?php if ($isVerified): ?>
            <div class="stat-grid" style="margin-top:1rem;">
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('star', 18) ?></span><div class="label"><?= e(__('d_stat_avg_rating', 'Avg rating')) ?></div><div class="value"><?= e((string) ($guideStats['avg_rating'] ?? 0)) ?></div></div>
                <a href="<?= e(url('/dashboard/reviews')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('feedback', 18) ?></span>
                    <div class="label"><?= e(__('d_stat_reviews', 'Reviews')) ?></div>
                    <div class="value"><?= (int) ($guideStats['review_count'] ?? 0) ?></div>
                    <div class="stat-action"><?= e(__('d_see_reviews', 'See comments')) ?> →</div>
                </a>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('check-circle', 18) ?></span><div class="label"><?= e(__('d_stat_completed', 'Completed tours')) ?></div><div class="value"><?= (int) ($guideStats['completed'] ?? 0) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('trend', 18) ?></span><div class="label"><?= e(__('d_stat_completion', 'Completion rate')) ?></div><div class="value"><?= e((string) ($guideStats['conversion'] ?? 0)) ?>%</div></div>
            </div>
            <?php if (!empty($guideBadge)): ?>
                <p class="hint mt-1"><?= e(__('d_your_badge', 'Your badge')) ?>: <strong><?= e($guideBadge) ?></strong></p>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($hasEarnings): ?>
            <div class="panel" style="margin-top:1.5rem;">
                <div class="panel-head"><h3><?= e(__('d_earnings_chart', 'Earnings (last 6 months)')) ?></h3></div>
                <div class="panel-body"><div class="chart-box"><canvas id="chartEarnings"></canvas></div></div>
            </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-head"><h3><?= e(__('d_recent_bookings', 'Recent bookings')) ?></h3><a href="<?= e(url('/dashboard/bookings')) ?>" class="btn btn-ghost btn-sm"><?= e(__('d_manage', 'Manage')) ?></a></div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($recentBookings === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;"><p><?= e(__('d_no_bookings', 'No bookings yet.')) ?></p></div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th><?= e(__('th_customer', 'Customer')) ?></th><th><?= e(__('th_listing', 'Listing')) ?></th><th><?= e(__('th_date', 'Date')) ?></th><th><?= e(__('th_total', 'Total')) ?></th><th><?= e(__('th_status', 'Status')) ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($recentBookings as $b): ?>
                                <tr>
                                    <td><?= e($b['customer_name']) ?></td>
                                    <td><?= e($b['listing_title']) ?></td>
                                    <td><?= e(loc_date((string) $b['booking_date'])) ?></td>
                                    <td><?= money($b['total_amount']) ?></td>
                                    <td><span class="pill pill-<?= e($b['status']) ?>"><?= e(status_label((string) $b['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h3><?= e(__('d_your_listings', 'Your listings')) ?></h3><a href="<?= e(url('/dashboard/listings')) ?>" class="btn btn-ghost btn-sm"><?= e(__('d_view_all', 'View all')) ?></a></div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($listings === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;"><p><?= e(__('d_no_listings', "You haven't created any listings yet.")) ?> <a href="<?= e(url('/dashboard/listings/create')) ?>"><?= e(__('d_create_first', 'Create your first')) ?> →</a></p></div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th><?= e(__('th_title', 'Title')) ?></th><th><?= e(__('th_category', 'Category')) ?></th><th><?= e(__('th_price', 'Price')) ?></th><th><?= e(__('th_rating', 'Rating')) ?></th><th><?= e(__('th_status', 'Status')) ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($listings as $l): ?>
                                <tr>
                                    <td><a href="<?= e(url('/listing/' . $l['slug'])) ?>"><?= e($l['title']) ?></a></td>
                                    <td><?= e($l['category_name']) ?></td>
                                    <td><?= money($l['price']) ?></td>
                                    <td><?= (int) $l['review_count'] > 0 ? number_format((float) $l['avg_rating'], 1) . ' ★' : '—' ?></td>
                                    <td><span class="pill pill-<?= e($l['status']) ?>"><?= e(status_label((string) $l['status'])) ?></span></td>
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

<?php if ($hasEarnings): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js" defer></script>
<script>
(function () {
    var labels = <?= json_encode($earnLabels, $jsonFlags) ?>;
    var values = <?= json_encode($earnValues, $jsonFlags) ?>;
    var symbol = <?= json_encode($curSymbol, $jsonFlags) ?>;

    function init() {
        if (typeof Chart === 'undefined') { return; }
        var el = document.getElementById('chartEarnings');
        if (!el) { return; }
        var green = '#1faa4e';
        new Chart(el, {
            type: 'bar',
            data: { labels: labels, datasets: [{
                data: values, backgroundColor: green, borderRadius: 6, maxBarThickness: 40
            }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return symbol + Number(c.parsed.y).toLocaleString(); } } }
                },
                scales: {
                    x: { grid: { color: 'transparent' } },
                    y: { beginAtZero: true, ticks: { callback: function (v) { return symbol + Number(v).toLocaleString(); } } }
                }
            }
        });
    }
    if (document.readyState === 'loading') {
        window.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
    } else { setTimeout(init, 0); }
    window.addEventListener('load', init);
})();
</script>
<?php endif; ?>
