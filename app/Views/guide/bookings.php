<?php
/** @var array<int,array<string,mixed>> $bookings @var bool $isWarned @var string $warningNote */
$isWarned = $isWarned ?? false;
$warningNote = $warningNote ?? '';
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <h1><?= e(__('g_bookings_received', 'Bookings received')) ?></h1>

            <?php if ($isWarned): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.2rem;">
                    <span class="status-icon">⚠️</span>
                    <div>
                        <strong><?= e(__('g_warn_title', 'Administrator warning — actions restricted')) ?></strong>
                        <p class="mb-0">
                            <?= e(__('g_warn_body1', 'You received a warning from the Administrator')) ?><?= $warningNote !== '' ? ': ' . e($warningNote) : '.' ?>
                            <?= e(__('g_warn_body2', 'You cannot use booking actions (Confirm, Complete, or Cancel) until an admin clears this warning.')) ?>
                            <a href="<?= e(url('/messages')) ?>"><?= e(__('g_view_message', 'View message')) ?> →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <?php if ($bookings === []): ?>
                        <div class="empty-state" style="padding:3rem 1rem;"><div class="big">📅</div><p><?= e(__('d_no_bookings', 'No bookings yet.')) ?></p></div>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr><th><?= e(__('th_customer', 'Customer')) ?></th><th><?= e(__('th_listing', 'Listing')) ?></th><th><?= e(__('th_date', 'Date')) ?></th><th><?= e(__('g_guests', 'Guests')) ?></th><th><?= e(__('th_total', 'Total')) ?></th><th><?= e(__('g_payment', 'Payment')) ?></th><th><?= e(__('th_status', 'Status')) ?></th><th><?= e(__('d_action', 'Action')) ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($bookings as $b): ?>
                                <tr<?= $b['status'] === 'pending' ? ' style="background:#FEF3C7;"' : '' ?>>
                                    <td><?= e($b['customer_name']) ?></td>
                                    <td><a href="<?= e(url('/listing/' . $b['listing_slug'])) ?>"><?= e($b['listing_title']) ?></a></td>
                                    <td><?= e(loc_date((string) $b['booking_date'])) ?></td>
                                    <td><?= (int) $b['guests'] ?></td>
                                    <td><?= money($b['total_amount']) ?></td>
                                    <td><span class="pill pill-<?= e($b['payment_status'] ?? 'pending') ?>"><?= e(status_label((string) ($b['payment_status'] ?? 'unpaid'))) ?></span></td>
                                    <td><span class="pill pill-<?= e($b['status']) ?>"><?= e(status_label((string) $b['status'])) ?></span></td>
                                    <td style="white-space:nowrap;">
                                        <?php if ($isWarned): ?>
                                            <span class="hint"><?= e(__('g_restricted', 'Restricted')) ?></span>
                                        <?php elseif (!in_array($b['status'], ['completed', 'cancelled', 'refunded', 'disputed'], true)): ?>
                                            <form method="post" action="<?= e(url('/dashboard/bookings/' . $b['id'] . '/status')) ?>" style="display:inline;" class="guide-booking-action" data-paid="<?= ($b['payment_status'] ?? '') === 'paid' ? '1' : '0' ?>" data-amount="<?= e((string) $b['total_amount']) ?>">
                                                <?= csrf_field() ?>
                                                <select name="status" class="input guide-status-select" style="padding:.3rem .5rem;width:auto;display:inline-block;">
                                                    <?php if ($b['status'] === 'pending'): ?><option value="confirmed"><?= e(__('g_confirm', 'Confirm')) ?></option><?php endif; ?>
                                                    <?php if ($b['status'] === 'confirmed'): ?><option value="completed"><?= e(__('g_complete', 'Complete')) ?></option><?php endif; ?>
                                                    <option value="cancelled"><?= e(__('booking_cancel', 'Cancel')) ?></option>
                                                </select>
                                                <button class="btn btn-ghost btn-sm" type="submit"><?= e(__('g_go', 'Go')) ?></button>
                                            </form>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
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
