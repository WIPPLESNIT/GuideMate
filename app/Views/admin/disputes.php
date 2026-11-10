<?php
/** @var array<int,array<string,mixed>> $disputes */
?>
<div class="section-head" style="margin-bottom:1.25rem;">
    <div>
        <h1 class="mb-0">Complaints</h1>
        <p class="hint mb-0">Review reported issues, process refunds, and issue contextual administrator warnings.</p>
    </div>
</div>

<?php if ($disputes === []): ?>
    <div class="panel"><div class="panel-body"><div class="empty-state" style="padding:2rem;"><p>No open complaints.</p></div></div></div>
<?php else: ?>
    <?php foreach ($disputes as $d): ?>
        <?php
        $info = \App\Models\Dispute::providerAccountInfo($d);
        ?>
        <div class="panel" style="margin-bottom:1.25rem;">
            <div class="panel-head" style="flex-wrap:wrap;gap:.75rem;align-items:flex-start;">
                <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.25rem;">
                        <h3 style="margin:0;font-size:1.05rem;">#<?= (int) $d['id'] ?> · <?= e($d['listing_title']) ?></h3>
                        <span class="badge" style="background:#11252c;color:#10b981;border:1px solid rgba(16,185,129,0.3);font-size:0.75rem;padding:2px 8px;border-radius:6px;font-weight:700;">
                            <?= e($info['account_type_label']) ?>
                        </span>
                    </div>
                    <small class="hint" style="font-size:0.82rem;color:#94a3b8;">
                        <strong>Tourist:</strong> <?= e($d['tourist_name']) ?> &nbsp;vs&nbsp;
                        <strong><?= e($info['provider_title']) ?>:</strong> <?= e($d['guide_name']) ?> (<?= e($info['account_type_label']) ?>)
                        &nbsp;·&nbsp; <?= e(date('M j, Y', strtotime($d['booking_date']))) ?>
                    </small>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="pill pill-<?= e($d['status']) ?>"><?= e(\App\Models\Dispute::statusLabel((string) $d['status'])) ?></span>
                </div>
            </div>
            <div class="panel-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:0.75rem;margin-bottom:1rem;background:rgba(255,255,255,0.02);padding:0.75rem 1rem;border-radius:8px;border:1px solid rgba(255,255,255,0.06);">
                    <div>
                        <small class="hint" style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;">Reported Account Type</small>
                        <strong style="font-size:0.88rem;color:#f8fafc;"><?= e($info['account_type_label']) ?> (<?= e($info['provider_title']) ?>)</strong>
                    </div>
                    <div>
                        <small class="hint" style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;">Problem Type</small>
                        <strong style="font-size:0.88rem;color:#f8fafc;"><?= e(\App\Models\Dispute::typeLabel((string) $d['problem_type'])) ?></strong>
                    </div>
                    <?php if ($d['amount_requested'] !== null): ?>
                        <div>
                            <small class="hint" style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;">Extra Amount Claimed</small>
                            <strong style="font-size:0.88rem;color:#ef4444;"><?= money((float) $d['amount_requested']) ?></strong>
                        </div>
                    <?php endif; ?>
                    <div>
                        <small class="hint" style="display:block;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;">Booking Total</small>
                        <strong style="font-size:0.88rem;color:#10b981;"><?= money((float) $d['total_amount']) ?></strong>
                    </div>
                </div>

                <div class="note-box" style="margin-bottom:1rem;"><?= nl2br(e($d['description'])) ?></div>
                <?php
                $files = \App\Models\DisputeFile::forDispute((int) $d['id']);
                if ($files !== []): ?>
                    <p style="margin-bottom:0.4rem;font-weight:600;font-size:0.85rem;">Evidence:</p>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
                        <?php foreach ($files as $f): ?>
                            <a href="<?= e(url('/' . ltrim((string) $f['file_path'], '/'))) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url('/' . ltrim((string) $f['file_path'], '/'))) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,0.1);">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($d['admin_note'])): ?>
                    <p class="hint mt-2" style="margin-bottom:1rem;"><strong>Admin note:</strong> <?= e($d['admin_note']) ?></p>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/admin/disputes/' . $d['id'] . '/resolve')) ?>" class="dispute-actions">
                    <?= csrf_field() ?>
                    <input class="input" type="text" name="admin_note" placeholder="Admin note (optional)" style="margin-bottom:0.75rem;">
                    <div class="dispute-btns" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <button class="btn btn-ghost btn-sm" type="submit" name="action" value="reviewing">Mark reviewing</button>
                        <button class="btn btn-primary btn-sm" type="submit" name="action" value="refund" style="background:#059669;color:#ffffff;">Refund tourist</button>
                        <button class="btn btn-ghost btn-sm btn-warn-provider" type="submit" name="action" value="warning" style="color:#f59e0b;border-color:rgba(245,158,11,0.4);"><?= e($info['warn_btn_label']) ?></button>
                        <button class="btn btn-ghost btn-sm" type="submit" name="action" value="reject">Reject report</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
