<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $listings
 * @var array<int,array<string,mixed>> $inquiries
 * @var int $unread
 * @var array<string,mixed> $reviewStats
 */
$approved = array_filter($listings, fn($l) => $l['status'] === 'approved');
$status = (string) ($user['guide_status'] ?? 'none');
$isVerified = $status === 'approved';
$unread = (int) ($unread ?? 0);
$inquiries = $inquiries ?? [];
$reviewStats = $reviewStats ?? ['avg_rating' => 0, 'review_count' => 0];
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0"><?= !empty($isFirstLogin) ? 'Welcome to GuideMate' : 'Welcome back' ?>, <?= e(explode(' ', (string) $user['name'])[0]) ?></h1>
                    <p class="hint mb-0">Hotel Partner dashboard — guests inquire by message. Listings are not bookable.</p>
                </div>
                <?php if ($isVerified): ?>
                    <a href="<?= e(url('/dashboard/listings/create')) ?>" class="btn btn-primary">+ New hotel listing</a>
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
                                ? 'Your hotel partner application was not approved. '
                                : 'An admin is reviewing your documents. ' ?>
                            You can publish hotel listings once verified.
                            <a href="<?= e(url('/dashboard/verification')) ?>">View status →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="stat-grid">
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('listings', 18) ?></span><div class="label">Listings</div><div class="value"><?= count($listings) ?></div></div>
                <div class="stat stat-ico-card"><span class="stat-ico"><?= admin_icon('check-circle', 18) ?></span><div class="label">Live</div><div class="value"><?= count($approved) ?></div></div>
                <a href="<?= e(url('/messages')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('messages', 18) ?></span>
                    <div class="label">Unread inquiries</div>
                    <div class="value"><?= $unread ?></div>
                    <div class="stat-action">Open inbox →</div>
                </a>
                <a href="<?= e(url('/dashboard/reviews')) ?>" class="stat stat-ico-card stat-clickable">
                    <span class="stat-ico"><?= admin_icon('feedback', 18) ?></span>
                    <div class="label">Reviews</div>
                    <div class="value"><?= (int) ($reviewStats['review_count'] ?? 0) ?></div>
                    <div class="stat-action"><?= e((string) ($reviewStats['avg_rating'] ?? 0)) ?> avg · See comments →</div>
                </a>
            </div>

            <div class="panel">
                <div class="panel-head"><h3>Recent inquiries</h3><a href="<?= e(url('/messages')) ?>" class="btn btn-ghost btn-sm">Inbox</a></div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($inquiries === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;"><p>No guest messages yet. Travelers reach you from your hotel listing.</p></div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th>Guest</th><th>Last message</th><th>When</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($inquiries as $c): ?>
                                <?php
                                $snippet = trim(preg_replace('/\s+/', ' ', (string) ($c['last_body'] ?? '')) ?? '');
                                if (function_exists('mb_strlen') && mb_strlen($snippet) > 72) {
                                    $snippet = mb_substr($snippet, 0, 69) . '…';
                                } elseif (strlen($snippet) > 72) {
                                    $snippet = substr($snippet, 0, 69) . '…';
                                }
                                $unreadHere = (int) ($c['unread'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <?= e((string) $c['partner_name']) ?>
                                        <?php if ($unreadHere > 0): ?>
                                            <span class="badge"><?= $unreadHere ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hint"><?= e($snippet !== '' ? $snippet : '—') ?></td>
                                    <td><?= !empty($c['last_at']) ? e(loc_date((string) $c['last_at'])) : '—' ?></td>
                                    <td><a class="btn btn-ghost btn-sm" href="<?= e(url('/messages/' . (int) $c['partner_id'])) ?>">Reply</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h3>Your hotel listings</h3><a href="<?= e(url('/dashboard/listings')) ?>" class="btn btn-ghost btn-sm">View all</a></div>
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($listings === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;"><p>You haven't created any listings yet. <a href="<?= e(url('/dashboard/listings/create')) ?>">Create your first →</a></p></div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th>Title</th><th>Category</th><th>From</th><th>Rating</th><th>Status</th></tr></thead>
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
