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
                    <div class="panel-body rental-requests-wrap" style="padding:0;">
                        <table class="table rental-requests-table">
                            <thead>
                                <tr>
                                    <th class="col-vehicle">Vehicle</th>
                                    <th class="col-customer">Customer</th>
                                    <th class="col-pickup">Pickup</th>
                                    <th class="col-payment">Payment</th>
                                    <th class="col-id-doc">Verification ID</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($requests as $r):
                                $paymentStatus = (string) ($r['payment_status'] ?? 'unpaid');
                                $reportStatus = (string) ($r['report_status'] ?? 'none');
                                $reqStatus = (string) ($r['status'] ?? 'pending');
                                $isPending = $reqStatus === 'pending';
                            ?>
                                <tr<?= $isPending ? ' style="background:#FEF3C7;"' : '' ?>>
                                    <td class="col-vehicle">
                                        <strong class="vehicle-title"><?= e($r['vehicle_name']) ?></strong>
                                        <div class="hint" style="font-size:0.8rem;margin-top:2px;">
                                            <?= e($r['vehicle_type']) ?> · <?= e($r['shop_name']) ?>
                                        </div>
                                    </td>
                                    <td class="col-customer">
                                        <strong class="customer-name"><?= e($r['customer_name']) ?></strong>
                                        <div class="customer-email"><?= e($r['customer_email']) ?></div>
                                        <?php if (!empty($r['customer_phone'])): ?>
                                            <div class="hint" style="font-size:0.78rem;"><?= e((string) $r['customer_phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($r['notes'])): ?>
                                            <div class="hint" style="font-size:0.75rem;margin-top:3px;color:#0369a1;" title="Customer note: <?= e($r['notes']) ?>">
                                                💬 <?= e(mb_strimwidth((string)$r['notes'], 0, 32, '...')) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-pickup">
                                        <strong style="display:block;font-size:0.88rem;"><?= e(date('M j, Y', strtotime((string) $r['pickup_date']))) ?></strong>
                                        <small class="hint"><?= (int) $r['rental_days'] ?> day(s)</small>
                                    </td>
                                    <td class="col-payment">
                                        <strong style="color:var(--brand, #059669);display:block;"><?= money((float) $r['total_amount']) ?></strong>
                                        <div style="margin-top:2px;">
                                            <?php if ($paymentStatus === 'paid'): ?>
                                                <span class="pill pill-approved" style="font-size:0.72rem;padding:2px 8px;">Paid</span>
                                                <small class="hint d-block" style="font-size:0.72rem;margin-top:2px;"><?= e(ucfirst((string) ($r['payment_method'] ?? ''))) ?></small>
                                            <?php elseif ($paymentStatus === 'refunded'): ?>
                                                <span class="pill pill-cancelled" style="font-size:0.72rem;padding:2px 8px;">Refunded</span>
                                            <?php else: ?>
                                                <span class="pill pill-pending" style="font-size:0.72rem;padding:2px 8px;">Unpaid</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="col-id-doc">
                                        <?php if (!empty($r['id_document'])): ?>
                                            <a href="<?= e(url($r['id_document'])) ?>" target="_blank" rel="noopener" class="doc-link" style="font-weight:600;font-size:0.84rem;">
                                                📎 <?= e((string) ($r['id_type'] ?? 'ID')) ?> ↗
                                            </a>
                                            <?php if (!empty($r['id_number'])): ?>
                                                <div class="hint" style="font-size:0.76rem;">No. <?= e((string) $r['id_number']) ?></div>
                                            <?php endif; ?>
                                            <div class="hint" style="font-size:0.72rem;color:var(--ink-300);">Held until return</div>
                                        <?php else: ?>
                                            <small class="hint">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-status">
                                        <span class="pill pill-<?= e($reqStatus) ?>" style="font-size:0.75rem;padding:3px 10px;">
                                            <?= e(\App\Models\RentalRequest::statusLabel($reqStatus)) ?>
                                        </span>
                                        <?php if ($reportStatus === 'open'): ?>
                                            <div style="margin-top:4px;">
                                                <span class="pill pill-cancelled" style="font-size:0.7rem;padding:2px 6px;" title="<?= e((string) ($r['report_message'] ?? '')) ?>">
                                                    ⚠ Problem Reported
                                                </span>
                                            </div>
                                        <?php elseif ($reportStatus === 'refunded'): ?>
                                            <div style="margin-top:4px;">
                                                <span class="pill pill-cancelled" style="font-size:0.7rem;padding:2px 6px;">Refunded after report</span>
                                            </div>
                                        <?php elseif ($reportStatus === 'rejected'): ?>
                                            <div style="margin-top:4px;">
                                                <span class="pill pill-pending" style="font-size:0.7rem;padding:2px 6px;">Report dismissed</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-actions">
                                        <div class="res-action-wrap">
                                            <button type="button"
                                                        class="btn-res-action-trigger"
                                                        id="rentalReqBtn_<?= (int) $r['id'] ?>"
                                                        aria-haspopup="true"
                                                        aria-expanded="false"
                                                        aria-controls="rentalReqMenu_<?= (int) $r['id'] ?>"
                                                        title="Actions for request #<?= (int) $r['id'] ?>"
                                                        aria-label="Actions for request #<?= (int) $r['id'] ?>">
                                                    <?= admin_icon('more-vertical', 16) ?>
                                                </button>
                                                <div class="res-action-dropdown"
                                                     id="rentalReqMenu_<?= (int) $r['id'] ?>"
                                                     role="menu"
                                                     aria-labelledby="rentalReqBtn_<?= (int) $r['id'] ?>">
                                                    
                                                    <?php if ($reportStatus === 'open'): ?>
                                                        <div style="padding:6px 10px 4px;font-size:0.72rem;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:0.04em;">
                                                            Problem Reported
                                                        </div>
                                                        <?php if (!empty($r['report_message'])): ?>
                                                            <div style="padding:0 10px 6px;font-size:0.78rem;color:#475569;line-height:1.35;max-width:240px;word-break:break-word;">
                                                                "<?= e((string)$r['report_message']) ?>"
                                                            </div>
                                                        <?php endif; ?>
                                                        <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/refund')) ?>" class="res-action-form" onsubmit="return confirm('Refund full payment of <?= money((float) $r['total_amount']) ?> to the customer?');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                <?= admin_icon('revenue', 14) ?> Refund Customer
                                                            </button>
                                                        </form>
                                                        <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/reject-report')) ?>" class="res-action-form" onsubmit="return confirm('Dismiss this problem report?');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                <?= admin_icon('check-circle', 14) ?> Dismiss Report
                                                            </button>
                                                        </form>
                                                        <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                    <?php endif; ?>

                                                    <?php if ($isPending): ?>
                                                        <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Confirm and approve this rental booking?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                <?= admin_icon('check-circle', 14) ?> Confirm Booking
                                                            </button>
                                                        </form>
                                                        <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Decline this booking? Any payment made will be refunded.');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                <?= admin_icon('x-circle', 14) ?> Decline Booking
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <?php if ($reqStatus !== 'completed' && $reqStatus !== 'cancelled'): ?>
                                                            <?php if ($reqStatus !== 'contacted'): ?>
                                                                <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="status" value="contacted">
                                                                    <button type="submit" class="res-action-item action-confirm" role="menuitem">
                                                                        <?= admin_icon('messages', 14) ?> Mark Contacted
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Mark this rental as completed?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="status" value="completed">
                                                                <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                <?= admin_icon('check-circle', 14) ?> Complete Rental
                                                                </button>
                                                            </form>
                                                            <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Cancel this reservation?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="status" value="cancelled">
                                                                <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                    <?= admin_icon('x-circle', 14) ?> Cancel Rental
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <?php if ($paymentStatus === 'paid' && !in_array($reqStatus, ['cancelled', 'refunded'], true) && $reportStatus !== 'open'): ?>
                                                        <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                        <form method="post" action="<?= e(url('/dashboard/rentals/' . $r['id'] . '/refund')) ?>" class="res-action-form" onsubmit="return confirm('Refund the full payment of <?= money((float) $r['total_amount']) ?> for this reservation?');">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                <?= admin_icon('revenue', 14) ?> Refund Payment
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if (!empty($r['id_document'])): ?>
                                                        <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                        <a href="<?= e(url($r['id_document'])) ?>" target="_blank" rel="noopener" class="res-action-item" role="menuitem">
                                                            <?= admin_icon('documents', 14) ?> View Verification ID
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
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
