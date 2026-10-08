<?php
/** @var array<int,array<string,mixed>> $pending @var array<int,array<string,mixed>> $all */
?>
<h1>Manage listings</h1>

<div class="panel">
    <div class="panel-head"><h3>Pending approval <?php if ($pending !== []): ?><span class="badge"><?= count($pending) ?></span><?php endif; ?></h3></div>
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <?php if ($pending === []): ?>
            <div class="empty-state" style="padding:2rem 1rem;"><p>🎉 No pending listings.</p></div>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Title</th><th>Guide</th><th>Category</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pending as $l): ?>
                    <tr>
                        <td><a href="<?= e(url('/listing/' . $l['slug'])) ?>" target="_blank"><?= e($l['title']) ?></a></td>
                        <td><?= e($l['owner_name']) ?></td>
                        <td><?= e($l['category_name']) ?></td>
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
    <div class="panel-head"><h3>All live listings</h3></div>
    <div class="panel-body" style="padding:0;overflow-x:auto;">
        <table class="table">
            <thead><tr><th>Title</th><th>Guide</th><th>Rating</th><th>Featured</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($all as $l): ?>
                <tr>
                    <td><a href="<?= e(url('/listing/' . $l['slug'])) ?>" target="_blank"><?= e($l['title']) ?></a></td>
                    <td><?= e($l['owner_name']) ?></td>
                    <td><?= (int) $l['review_count'] > 0 ? number_format((float) $l['avg_rating'], 1) . ' ★' : '—' ?></td>
                    <td><?= (int) $l['is_featured'] ? '⭐ Yes' : 'No' ?></td>
                    <td style="white-space:nowrap;">
                        <form method="post" action="<?= e(url('/admin/listings/' . $l['id'] . '/feature')) ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm"><?= (int) $l['is_featured'] ? 'Unfeature' : 'Feature' ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/admin/listings/' . $l['id'] . '/status')) ?>" style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="status" value="rejected">
                            <button class="btn btn-ghost btn-sm" style="color:var(--coral-600);">Unpublish</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
