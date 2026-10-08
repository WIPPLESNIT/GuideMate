<?php
/**
 * @var array<string,int|float> $stats
 * @var array<int,array<string,mixed>> $pending
 * @var array<int,array<string,mixed>> $pendingGuides
 * @var array<int,array<string,mixed>> $recentReviews
 */
?>
<h1>Admin overview</h1>
<p class="hint">Platform health at a glance.</p>

<div class="stat-grid">
    <div class="stat"><div class="label">Travelers</div><div class="value"><?= (int) $stats['tourists'] ?></div></div>
    <div class="stat"><div class="label">Guides</div><div class="value"><?= (int) $stats['guides'] ?></div></div>
    <div class="stat"><div class="label">Live listings</div><div class="value"><?= (int) $stats['listings'] ?></div></div>
    <div class="stat"><div class="label">Total revenue</div><div class="value" style="font-size:1.3rem;"><?= money($stats['revenue']) ?></div></div>
    <div class="stat"><div class="label">Open disputes</div><div class="value"><?= (int) ($stats['openDisputes'] ?? 0) ?></div></div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3>Disputes <?php if ((int) ($stats['openDisputes'] ?? 0) > 0): ?><span class="badge"><?= (int) $stats['openDisputes'] ?></span><?php endif; ?></h3>
        <a href="<?= e(url('/admin/disputes')) ?>" class="btn btn-ghost btn-sm">Review all</a>
    </div>
    <div class="panel-body">
        <p class="hint mb-0">Tourist reports about extra payment requests, no-shows, and service issues on paid bookings.</p>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3>Partner applications <?php if ((int) $stats['pendingGuides'] > 0): ?><span class="badge"><?= (int) $stats['pendingGuides'] ?></span><?php endif; ?></h3>
        <a href="<?= e(url('/admin/guides')) ?>" class="btn btn-ghost btn-sm">Review all</a>
    </div>
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <?php if ($pendingGuides === []): ?>
            <div class="empty-state" style="padding:2.5rem 1rem;"><p>✅ No partner applications awaiting review.</p></div>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Applicant</th><th>Type</th><th>Email</th><th>Applied</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pendingGuides as $g): ?>
                    <tr>
                        <td><strong><?= e($g['name']) ?></strong></td>
                        <td><?= e(\App\Models\User::PROVIDER_LABELS[$g['role']] ?? ucfirst((string) $g['role'])) ?></td>
                        <td><?= e($g['email']) ?></td>
                        <td><?= e(time_ago($g['created_at'])) ?></td>
                        <td style="white-space:nowrap;"><a href="<?= e(url('/admin/guides')) ?>" class="btn btn-primary btn-sm">Review documents</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3>Pending approvals <?php if ((int) $stats['pending'] > 0): ?><span class="badge"><?= (int) $stats['pending'] ?></span><?php endif; ?></h3>
        <a href="<?= e(url('/admin/listings')) ?>" class="btn btn-ghost btn-sm">Manage all</a>
    </div>
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <?php if ($pending === []): ?>
            <div class="empty-state" style="padding:2.5rem 1rem;"><p>🎉 Nothing pending — all caught up!</p></div>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Title</th><th>Guide</th><th>Category</th><th>Submitted</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pending as $l): ?>
                    <tr>
                        <td><a href="<?= e(url('/listing/' . $l['slug'])) ?>" target="_blank"><?= e($l['title']) ?></a></td>
                        <td><?= e($l['owner_name']) ?></td>
                        <td><?= e($l['category_name']) ?></td>
                        <td><?= e(time_ago($l['created_at'])) ?></td>
                        <td style="white-space:nowrap;">
                            <form method="post" action="<?= e(url('/admin/listings/' . $l['id'] . '/status')) ?>" style="display:inline;">
                                <?= csrf_field() ?><input type="hidden" name="status" value="approved">
                                <button class="btn btn-primary btn-sm">Approve</button>
                            </form>
                            <form method="post" action="<?= e(url('/admin/listings/' . $l['id'] . '/status')) ?>" style="display:inline;">
                                <?= csrf_field() ?><input type="hidden" name="status" value="rejected">
                                <button class="btn btn-ghost btn-sm">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h3>Latest reviews</h3></div>
    <div class="panel-body">
        <?php if ($recentReviews === []): ?>
            <p class="hint mb-0">No reviews yet.</p>
        <?php else: ?>
            <?php foreach ($recentReviews as $r): ?>
                <div style="padding:.6rem 0;border-bottom:1px solid var(--line);">
                    <span class="rating"><?= e(stars((float) $r['rating'])) ?></span>
                    <strong><?= e($r['user_name']) ?></strong> on
                    <a href="<?= e(url('/listing/' . $r['listing_slug'])) ?>" target="_blank"><?= e($r['listing_title']) ?></a>
                    <span class="hint">· <?= e(time_ago($r['created_at'])) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
