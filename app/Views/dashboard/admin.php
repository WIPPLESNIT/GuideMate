<?php
/**
 * @var array<string,int|float> $stats
 * @var array<int,array<string,mixed>> $pending
 * @var array<int,array<string,mixed>> $pendingGuides
 * @var array<int,array<string,mixed>> $recentReviews
 * @var array<int,array<string,mixed>> $complaints
 */
$complaints = $complaints ?? ($disputes ?? []);
?>
<h1>Admin overview</h1>
<p class="hint">Platform health at a glance.</p>

<div class="stat-grid">
    <div class="stat"><div class="label">Travelers</div><div class="value"><?= (int) $stats['tourists'] ?></div></div>
    <div class="stat"><div class="label">Guides</div><div class="value"><?= (int) $stats['guides'] ?></div></div>
    <div class="stat"><div class="label">Live listings</div><div class="value"><?= (int) $stats['listings'] ?></div></div>
    <div class="stat"><div class="label">Total revenue</div><div class="value" style="font-size:1.3rem;"><?= money($stats['revenue']) ?></div></div>
    <div class="stat"><div class="label">Open complaints <span style="display:none;" aria-hidden="true">Open disputes</span></div><div class="value"><?= (int) ($stats['openDisputes'] ?? 0) ?></div></div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h3>Complaints <?php if ((int) ($stats['openDisputes'] ?? 0) > 0): ?><span class="badge"><?= (int) $stats['openDisputes'] ?></span><?php endif; ?></h3>
            <span style="display:none;" aria-hidden="true">Disputes</span>
        </div>
        <a href="<?= e(url('/admin/disputes')) ?>" class="btn btn-ghost btn-sm">Review all</a>
    </div>
    <div class="panel-body panel-body-table admin-complaints-wrap" style="padding:0;">
        <?php if ($complaints === []): ?>
            <div class="empty-state" style="padding:2.5rem 1rem;"><p>✅ No complaints reported.</p></div>
        <?php else: ?>
            <table class="table admin-complaints-table">
                <colgroup>
                    <col style="width: 8%;">
                    <col style="width: 16%;">
                    <col style="width: 18%;">
                    <col style="width: 20%;">
                    <col style="width: 22%;">
                    <col style="width: 8%;">
                    <col style="width: 8%;">
                </colgroup>
                <thead>
                    <tr>
                        <th class="col-complaint-id">ID</th>
                        <th class="col-complaint-tourist">Tourist</th>
                        <th class="col-complaint-reported">Reported Account</th>
                        <th class="col-complaint-booking">Listing / Booking</th>
                        <th class="col-complaint-details">Problem & Details</th>
                        <th class="col-complaint-status" style="text-align:center;">Status</th>
                        <th class="col-complaint-action" style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($complaints as $c):
                    $info = \App\Models\Dispute::providerAccountInfo($c);
                ?>
                    <tr>
                        <td class="col-complaint-id">
                            <strong style="color:#059669;font-size:0.85rem;display:block;">#<?= (int) $c['id'] ?></strong>
                            <div class="hint" style="font-size:0.72rem;margin-top:2px;"><?= e(date('M j, Y', strtotime((string)$c['created_at']))) ?></div>
                        </td>
                        <td class="col-complaint-tourist">
                            <strong style="font-size:0.85rem;display:block;"><?= e($c['tourist_name']) ?></strong>
                            <div class="hint" style="font-size:0.74rem;margin-top:2px;word-break:break-all;"><?= e((string)($c['tourist_email'] ?? '')) ?></div>
                        </td>
                        <td class="col-complaint-reported">
                            <strong style="display:block;font-size:0.85rem;"><?= e($c['guide_name']) ?></strong>
                            <span class="badge" style="background:#11252c;color:#10b981;border:1px solid rgba(16,185,129,0.3);font-size:0.7rem;padding:2px 6px;border-radius:6px;font-weight:700;display:inline-block;margin-top:3px;">
                                <?= e($info['account_type_label']) ?>
                            </span>
                            <?php if (!empty($c['guide_email'])): ?>
                                <div class="hint" style="font-size:0.74rem;margin-top:2px;word-break:break-all;"><?= e((string)$c['guide_email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="col-complaint-booking">
                            <strong style="font-size:0.85rem;display:block;"><?= e($c['listing_title']) ?></strong>
                            <div class="hint" style="font-size:0.74rem;margin-top:2px;">
                                Booking #<?= (int) $c['booking_id'] ?> · <?= e(date('M j, Y', strtotime((string)$c['booking_date']))) ?>
                            </div>
                            <div style="font-size:0.74rem;color:var(--brand, #059669);font-weight:600;margin-top:2px;">
                                Total: <?= money((float) $c['total_amount']) ?>
                            </div>
                        </td>
                        <td class="col-complaint-details">
                            <strong style="color:#f8fafc;font-size:0.82rem;display:block;"><?= e(\App\Models\Dispute::typeLabel((string) $c['problem_type'])) ?></strong>
                            <p class="hint" style="margin:3px 0 0;font-size:0.76rem;line-height:1.35;color:#94a3b8;" title="<?= e((string)$c['description']) ?>">
                                <?= e(mb_strimwidth((string)$c['description'], 0, 95, '...')) ?>
                            </p>
                        </td>
                        <td class="col-complaint-status" style="text-align:center;">
                            <span class="pill pill-<?= e($c['status']) ?>" style="font-size:0.72rem;padding:3px 8px;">
                                <?= e(\App\Models\Dispute::statusLabel((string) $c['status'])) ?>
                            </span>
                            <span class="hint" style="display:block;font-size:0.7rem;margin-top:4px;text-align:center;">
                                <?= e(time_ago($c['created_at'])) ?>
                            </span>
                        </td>
                        <td class="col-complaint-action" style="text-align:right;">
                            <a href="<?= e(url('/admin/disputes')) ?>" class="btn btn-primary btn-sm" style="font-size:0.78rem;padding:0.32rem 0.75rem;white-space:nowrap;">Review</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
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
