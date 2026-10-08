<?php
/** @var array<int,array<string,mixed>> $disputes */
?>
<h1>Disputes</h1>

<?php if ($disputes === []): ?>
    <div class="panel"><div class="panel-body"><div class="empty-state" style="padding:2rem;"><p>No open disputes.</p></div></div></div>
<?php else: ?>
    <?php foreach ($disputes as $d): ?>
        <div class="panel" style="margin-bottom:1rem;">
            <div class="panel-head" style="flex-wrap:wrap;gap:.5rem;">
                <div>
                    <h3 style="margin:0;">#<?= (int) $d['id'] ?> · <?= e($d['listing_title']) ?></h3>
                    <small class="hint"><?= e($d['tourist_name']) ?> vs <?= e($d['guide_name']) ?> · <?= e(date('M j, Y', strtotime($d['booking_date']))) ?></small>
                </div>
                <span class="pill pill-<?= e($d['status']) ?>"><?= e(\App\Models\Dispute::statusLabel((string) $d['status'])) ?></span>
            </div>
            <div class="panel-body">
                <p><strong>Problem:</strong> <?= e(\App\Models\Dispute::typeLabel((string) $d['problem_type'])) ?></p>
                <?php if ($d['amount_requested'] !== null): ?>
                    <p><strong>Extra amount claimed:</strong> <?= money((float) $d['amount_requested']) ?></p>
                <?php endif; ?>
                <p><strong>Booking total:</strong> <?= money((float) $d['total_amount']) ?></p>
                <div class="note-box"><?= nl2br(e($d['description'])) ?></div>
                <?php
                $files = \App\Models\DisputeFile::forDispute((int) $d['id']);
                if ($files !== []): ?>
                    <p><strong>Evidence:</strong></p>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
                        <?php foreach ($files as $f): ?>
                            <a href="<?= e(url('/' . ltrim((string) $f['file_path'], '/'))) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url('/' . ltrim((string) $f['file_path'], '/'))) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($d['admin_note'])): ?>
                    <p class="hint mt-2"><strong>Admin note:</strong> <?= e($d['admin_note']) ?></p>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/admin/disputes/' . $d['id'] . '/resolve')) ?>" class="dispute-actions">
                    <?= csrf_field() ?>
                    <input class="input" type="text" name="admin_note" placeholder="Admin note (optional)">
                    <div class="dispute-btns">
                        <button class="btn btn-ghost btn-sm" type="submit" name="action" value="reviewing">Mark reviewing</button>
                        <button class="btn btn-primary btn-sm" type="submit" name="action" value="refund">Refund tourist</button>
                        <button class="btn btn-ghost btn-sm" type="submit" name="action" value="warning">Warn guide</button>
                        <button class="btn btn-ghost btn-sm" type="submit" name="action" value="reject">Reject report</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
