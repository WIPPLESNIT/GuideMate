<?php
/** @var array<int,array<string,mixed>> $items */
use App\Models\Feedback;
?>
<h1>App feedback</h1>
<p class="hint">Feedback left by tourists from the GuideMate mobile app.</p>

<?php if ($items === []): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state" style="padding:2rem;">
                <p>No feedback yet. Messages left from the mobile app will appear here.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-body" style="padding:0;overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>From</th>
                        <th>Type</th>
                        <th>Rating</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $f): ?>
                    <?php
                    $rating = (int) ($f['rating'] ?? 0);
                    $status = (string) ($f['status'] ?? 'new');
                    ?>
                    <tr>
                        <td><?= (int) $f['id'] ?></td>
                        <td>
                            <strong><?= e((string) ($f['name'] ?? '') ?: 'Anonymous') ?></strong>
                            <?php if (!empty($f['email'])): ?><br><small class="hint"><?= e((string) $f['email']) ?></small><?php endif; ?>
                            <?php if (!empty($f['user_id'])): ?><br><small class="hint">Account #<?= (int) $f['user_id'] ?></small><?php endif; ?>
                        </td>
                        <td><?= e(Feedback::categoryLabel((string) ($f['category'] ?? 'general'))) ?></td>
                        <td><?php if ($rating > 0): ?><span class="fb-stars"><?= str_repeat('★', $rating) ?><span class="fb-stars-off"><?= str_repeat('★', 5 - $rating) ?></span></span><?php else: ?><span class="hint">—</span><?php endif; ?></td>
                        <td><p style="max-width:22rem;white-space:pre-wrap;margin:0;"><?= e((string) $f['message']) ?></p></td>
                        <td><small class="hint"><?= e(date('M j, Y g:i A', strtotime((string) $f['created_at']))) ?></small></td>
                        <td><span class="pill pill-fb-<?= e($status) ?>"><?= e(Feedback::statusLabel($status)) ?></span></td>
                        <td>
                            <form method="post" action="<?= e(url('/admin/feedback/' . $f['id'] . '/status')) ?>" class="dispute-btns">
                                <?= csrf_field() ?>
                                <button class="btn btn-primary btn-sm" type="submit" name="status" value="reviewed">Mark reviewed</button>
                                <button class="btn btn-ghost btn-sm" type="submit" name="status" value="archived">Archive</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
