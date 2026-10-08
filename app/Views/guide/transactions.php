<?php
/**
 * @var array<int,array<string,mixed>> $transactions
 * @var float $paidTotal
 */
$transactions = $transactions ?? [];
$paidTotal = $paidTotal ?? 0.0;

$payBadge = static function (string $status): array {
    return [
        'paid' => ['cls' => 'pill-confirmed', 'label' => 'Paid'],
        'refunded' => ['cls' => 'pill-cancelled', 'label' => 'Refunded'],
        'pending' => ['cls' => 'pill-pending', 'label' => 'Pending'],
    ][$status] ?? ['cls' => 'pill-pending', 'label' => 'Unpaid'];
};
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0">Transactions</h1>
                    <p class="hint mb-0">Every booking and payment for your listings — including transactions made from the mobile app.</p>
                </div>
                <div class="stat" style="min-width:180px;">
                    <div class="label">Total received</div>
                    <div class="value"><?= e(money($paidTotal)) ?></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-body" style="padding:0;">
                    <?php if ($transactions === []): ?>
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p class="mb-0">No transactions yet. Bookings and payments will appear here as travelers reserve your listings.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Listing</th>
                                        <th>Customer</th>
                                        <th>Booking</th>
                                        <th>Amount</th>
                                        <th>Reference</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $t): ?>
                                        <?php
                                        $status = (string) ($t['payment_status'] ?? '');
                                        $badge = $payBadge($status);
                                        $when = $t['paid_at'] ?: $t['created_at'];
                                        $amount = $t['paid_amount'] !== null ? (float) $t['paid_amount'] : (float) ($t['total_amount'] ?? 0);
                                        ?>
                                        <tr>
                                            <td><?= e($when ? date('M j, Y g:i a', strtotime((string) $when)) : '—') ?></td>
                                            <td><?= e((string) ($t['listing_title'] ?? '')) ?></td>
                                            <td><?= e((string) ($t['customer_name'] ?? '')) ?></td>
                                            <td>
                                                <?= e($t['booking_date'] ? date('M j, Y', strtotime((string) $t['booking_date'])) : '—') ?>
                                                <?php if (!empty($t['booking_time'])): ?>
                                                    · <?= e(date('g:i a', strtotime((string) $t['booking_time']))) ?>
                                                <?php endif; ?>
                                                <div class="hint" style="margin:0;"><?= (int) ($t['guests'] ?? 0) ?> guest(s)</div>
                                            </td>
                                            <td><strong><?= e(money($amount)) ?></strong></td>
                                            <td><span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.85rem;"><?= e((string) ($t['payment_reference'] ?? '—')) ?></span></td>
                                            <td>
                                                <span class="pill <?= $badge['cls'] ?>"><?= e($badge['label']) ?></span>
                                                <?php if (!empty($t['payment_method'])): ?>
                                                    <div class="hint" style="margin:0;"><?= e(ucfirst((string) $t['payment_method'])) ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
