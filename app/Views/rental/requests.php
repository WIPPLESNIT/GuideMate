<?php
/** @var array<int,array<string,mixed>> $requests */
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0">Rental requests</h1>
                    <p class="hint mb-0">Paid reservations stay awaiting confirmation until you confirm or decline them.</p>
                </div>
            </div>

            <?php if ($requests === []): ?>
                <div class="panel">
                    <div class="panel-body">
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p>No rental requests yet. Requests from the mobile app will appear here.</p>
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
                                    <th>Vehicle</th>
                                    <th>Customer</th>
                                    <th>Pickup</th>
                                    <th>Payment</th>
                                    <th>Verification&nbsp;ID</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($requests as $r): ?>
                                <?php
                                    $paymentStatus = (string) ($r['payment_status'] ?? 'unpaid');
                                    $reportStatus = (string) ($r['report_status'] ?? 'none');
                                ?>
                                <tr<?= (string) $r['status'] === 'pending' ? ' style="background:#FEF3C7;"' : '' ?>>
                                    <td><?= (int) $r['id'] ?></td>
                                    <td>
                                        <strong><?= e($r['vehicle_name']) ?></strong><br>
                                        <small class="hint"><?= e($r['vehicle_type']) ?> · <?= e($r['shop_name']) ?></small>
                                    </td>
                                    <td>
                                        <?= e($r['customer_name']) ?><br>
                                        <small class="hint"><?= e($r['customer_email']) ?></small><br>
                                        <small class="hint"><?= e((string) ($r['customer_phone'] ?? '')) ?></small>
                                    </td>
                                    <td>
                                        <?= e(date('M j, Y', strtotime((string) $r['pickup_date']))) ?><br>
                                        <small class="hint"><?= (int) $r['rental_days'] ?> day(s)</small>
                                    </td>
                                    <td>
                                        <strong><?= money((float) $r['total_amount']) ?></strong><br>
                                        <?php if ($paymentStatus === 'paid'): ?>
                                            <span class="pill pill-approved">Paid</span>
                                            <small class="hint d-block"><?= e(ucfirst((string) ($r['payment_method'] ?? ''))) ?></small>
                                        <?php elseif ($paymentStatus === 'refunded'): ?>
                                            <span class="pill pill-cancelled">Refunded</span>
                                        <?php else: ?>
                                            <span class="pill pill-pending">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['id_document'])): ?>
                                            <a href="<?= e(url($r['id_document'])) ?>" target="_blank" rel="noopener" class="doc-link">
                                                📎 <?= e((string) ($r['id_type'] ?? 'ID')) ?> ↗
                                            </a>
                                            <?php if (!empty($r['id_number'])): ?>
                                                <br><small class="hint">No. <?= e((string) $r['id_number']) ?></small>
                                            <?php endif; ?>
                                            <br><small class="hint">Held until unit is returned</small>
                                        <?php else: ?>
                                            <small class="hint">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="pill pill-<?= e($r['status']) ?>"><?= e(\App\Models\RentalRequest::statusLabel((string) $r['status'])) ?></span></td>
                                    <td>
                                        <?php if ($reportStatus === 'open'): ?>
                                            <div class="panel" style="border-color:#ef4444;margin-bottom:.5rem;">
                                                <div class="panel-body" style="padding:.6rem .75rem;">
                                                    <strong style="color:#ef4444;">⚠ Problem reported</strong><br>
                                                    <small class="hint"><?= e(\App\Models\RentalRequest::reportTypeLabel((string) ($r['report_type'] ?? ''))) ?></small>
                                                    <p class="hint" style="max-width:16rem;margin:.35rem 0 0;"><?= e((string) ($r['report_message'] ?? '')) ?></p>
                                                </div>
                                            </div>
                                            <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/refund')) ?>" class="dispute-btns">
                                                <?= csrf_field() ?>
                                                <input class="input" type="text" name="owner_note" placeholder="Note to customer (optional)">
                                                <button class="btn btn-primary btn-sm" type="submit" onclick="return confirm('Refund the full payment for this reservation?')">Refund customer</button>
                                            </form>
                                            <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/reject-report')) ?>" style="margin-top:.35rem;">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-ghost btn-sm" type="submit">Dismiss report</button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($reportStatus === 'refunded'): ?>
                                                <span class="pill pill-cancelled">Refunded after report</span><br>
                                            <?php elseif ($reportStatus === 'rejected'): ?>
                                                <span class="pill pill-pending">Report dismissed</span><br>
                                            <?php endif; ?>
                                            <?php if (!empty($r['notes'])): ?>
                                                <p class="hint" style="max-width:14rem;"><?= e($r['notes']) ?></p>
                                            <?php endif; ?>
                                            <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="dispute-btns" style="margin-top:.5rem;">
                                                <?= csrf_field() ?>
                                                <input class="input" type="text" name="admin_note" placeholder="Note to yourself" value="<?= e((string) ($r['admin_note'] ?? '')) ?>">
                                                <?php if ((string) $r['status'] === 'pending'): ?>
                                                    <button class="btn btn-primary btn-sm" type="submit" name="status" value="approved">Confirm booking</button>
                                                    <button class="btn btn-ghost btn-sm" type="submit" name="status" value="cancelled">Decline</button>
                                                <?php else: ?>
                                                    <button class="btn btn-ghost btn-sm" type="submit" name="status" value="contacted">Mark contacted</button>
                                                    <button class="btn btn-ghost btn-sm" type="submit" name="status" value="completed">Complete</button>
                                                    <button class="btn btn-ghost btn-sm" type="submit" name="status" value="cancelled">Cancel</button>
                                                <?php endif; ?>
                                            </form>
                                            <?php if ($paymentStatus === 'paid' && $r['status'] !== 'refunded'): ?>
                                                <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/refund')) ?>" style="margin-top:.35rem;">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Refund the full payment for this reservation?')">Refund</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
