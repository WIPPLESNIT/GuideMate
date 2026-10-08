<?php
/** @var array<int,array<string,mixed>> $bookingsByMonth @var array<int,array<string,mixed>> $topListings
 * @var array<int,array<string,mixed>> $usersByMonth @var array{bookings:int,disputes:int,rate:float} $disputeStats
 * @var float $revenue @var array<int,array<string,mixed>> $auditLog */

$bkLabels = array_map(static fn ($r) => (string) $r['month'], $bookingsByMonth);
$bkValues = array_map(static fn ($r) => (int) $r['total'], $bookingsByMonth);
$usLabels = array_map(static fn ($r) => (string) $r['month'], $usersByMonth);
$usValues = array_map(static fn ($r) => (int) $r['total'], $usersByMonth);
$topLabels = array_map(static fn ($r) => (string) $r['title'], array_slice($topListings, 0, 6));
$topValues = array_map(static fn ($r) => (int) $r['booking_count'], array_slice($topListings, 0, 6));
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<h1>Analytics</h1>

<div class="stat-grid" style="margin-bottom:1.5rem;">
    <div class="stat"><div class="label">Platform revenue</div><div class="value"><?= money($revenue) ?></div></div>
    <div class="stat"><div class="label">Dispute rate</div><div class="value"><?= e((string) $disputeStats['rate']) ?>%</div></div>
    <div class="stat"><div class="label">Total disputes</div><div class="value"><?= (int) $disputeStats['disputes'] ?></div></div>
    <div class="stat"><div class="label">Paid bookings base</div><div class="value"><?= (int) $disputeStats['bookings'] ?></div></div>
</div>

<div class="chart-grid">
    <div class="panel">
        <div class="panel-head"><h3>Bookings per month</h3></div>
        <div class="panel-body">
            <?php if ($bookingsByMonth === []): ?>
                <p class="hint mb-0">No booking data yet.</p>
            <?php else: ?>
                <div class="chart-box"><canvas id="chartBookings"></canvas></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h3>New users per month</h3></div>
        <div class="panel-body">
            <?php if ($usersByMonth === []): ?>
                <p class="hint mb-0">No user registrations yet.</p>
            <?php else: ?>
                <div class="chart-box"><canvas id="chartUsers"></canvas></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($topValues !== [] && array_sum($topValues) > 0): ?>
<div class="panel" style="margin-bottom:1rem;">
    <div class="panel-head"><h3>Top listings by bookings</h3></div>
    <div class="panel-body"><div class="chart-box chart-box-wide"><canvas id="chartTop"></canvas></div></div>
</div>
<?php endif; ?>

<div class="panel" style="margin-bottom:1rem;">
    <div class="panel-head"><h3>Top listings</h3></div>
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <table class="table">
            <thead><tr><th>Listing</th><th>Area</th><th>Bookings</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($topListings as $l): ?>
                <tr>
                    <td><a href="<?= e(url('/listing/' . $l['slug'])) ?>"><?= e($l['title']) ?></a></td>
                    <td><?= e($l['area']) ?></td>
                    <td><?= (int) $l['booking_count'] ?></td>
                    <td><?= money((float) $l['revenue']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h3>Recent admin actions</h3></div>
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <table class="table">
            <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Entity</th></tr></thead>
            <tbody>
            <?php foreach ($auditLog as $a): ?>
                <tr>
                    <td><?= e(date('M j, Y g:i a', strtotime($a['created_at']))) ?></td>
                    <td><?= e($a['admin_name']) ?></td>
                    <td><?= e($a['action']) ?></td>
                    <td><?= e($a['entity_type']) ?> #<?= (int) ($a['entity_id'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="hint mt-2">
    <a href="<?= e(url('/admin/export/bookings')) ?>">Export bookings CSV</a> ·
    <a href="<?= e(url('/admin/export/users')) ?>">Export users CSV</a> ·
    <a href="<?= e(url('/admin/export/disputes')) ?>">Export disputes CSV</a>
</p>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js" defer></script>
<script>
(function () {
    var data = {
        bkLabels: <?= json_encode($bkLabels, $jsonFlags) ?>,
        bkValues: <?= json_encode($bkValues, $jsonFlags) ?>,
        usLabels: <?= json_encode($usLabels, $jsonFlags) ?>,
        usValues: <?= json_encode($usValues, $jsonFlags) ?>,
        topLabels: <?= json_encode($topLabels, $jsonFlags) ?>,
        topValues: <?= json_encode($topValues, $jsonFlags) ?>
    };

    function init() {
        if (typeof Chart === 'undefined') { return; }

        var green = '#1faa4e';
        var greenSoft = 'rgba(31,170,78,0.18)';
        var grid = 'rgba(255,255,255,0.08)';
        var tick = 'rgba(255,255,255,0.6)';

        Chart.defaults.color = tick;
        Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";

        var baseOpts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'transparent' }, ticks: { color: tick } },
                y: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick, precision: 0 } }
            }
        };

        var elBookings = document.getElementById('chartBookings');
        if (elBookings) {
            new Chart(elBookings, {
                type: 'bar',
                data: { labels: data.bkLabels, datasets: [{
                    data: data.bkValues, backgroundColor: green, borderRadius: 6, maxBarThickness: 34
                }] },
                options: baseOpts
            });
        }

        var elUsers = document.getElementById('chartUsers');
        if (elUsers) {
            new Chart(elUsers, {
                type: 'line',
                data: { labels: data.usLabels, datasets: [{
                    data: data.usValues, borderColor: green, backgroundColor: greenSoft,
                    fill: true, tension: 0.35, pointRadius: 3, pointBackgroundColor: green, borderWidth: 2
                }] },
                options: baseOpts
            });
        }

        var elTop = document.getElementById('chartTop');
        if (elTop) {
            new Chart(elTop, {
                type: 'bar',
                data: { labels: data.topLabels, datasets: [{
                    data: data.topValues, backgroundColor: green, borderRadius: 6, maxBarThickness: 26
                }] },
                options: Object.assign({}, baseOpts, {
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick, precision: 0 } },
                        y: { grid: { color: 'transparent' }, ticks: { color: tick } }
                    }
                })
            });
        }
    }

    if (document.readyState === 'loading') {
        window.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
    } else {
        setTimeout(init, 0);
    }
    window.addEventListener('load', init);
})();
</script>
