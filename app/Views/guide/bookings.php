<?php
/** @var array<int,array<string,mixed>> $bookings @var bool $isWarned @var string $warningNote @var bool $isHotel */
$isWarned = $isWarned ?? false;
$warningNote = $warningNote ?? '';
$currentUser = auth_user();
$isHotel = $isHotel ?? in_array(($currentUser['role'] ?? ''), ['hotel_provider', 'hotel_admin'], true);
$heading = $isHotel ? 'Hotel Reservations' : e(__('g_bookings_received', 'Bookings received'));
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div class="dash-main">
            <h1><?= $heading ?></h1>

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

            <div class="panel <?= $isHotel ? 'hotel-reservations-panel' : 'guide-bookings-panel' ?>">
                <div class="panel-body <?= $isHotel ? 'hotel-reservations-wrap' : 'guide-bookings-wrap' ?>" style="padding:0;">
                    <?php if ($bookings === []): ?>
                        <div class="empty-state" style="padding:3rem 1rem;"><div class="big"><?= $isHotel ? '🏨' : '📅' ?></div><p><?= $isHotel ? 'No hotel reservations yet.' : e(__('d_no_bookings', 'No bookings yet.')) ?></p></div>
                    <?php elseif ($isHotel): ?>
                        <table class="table hotel-table hotel-reservations-table" style="vertical-align:middle;width:100%;">
                            <thead>
                                <tr>
                                    <th style="width:16%;">Guest</th>
                                    <th style="width:20%;">Property</th>
                                    <th style="width:21%;">Room &amp; Stay</th>
                                    <th style="width:16%;">Total &amp; Deposit</th>
                                    <th style="width:13%;">Balance Due</th>
                                    <th style="width:9%;">Status</th>
                                    <th class="col-actions" style="width:5%;text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                    <?php
                                    $hotelStatus = (string) ($b['hotel_status'] ?? $b['status'] ?? 'pending');
                                    $checkIn = !empty($b['check_in_date']) ? $b['check_in_date'] : $b['booking_date'];
                                    $checkOut = !empty($b['check_out_date']) ? $b['check_out_date'] : $b['booking_date'];
                                    $nights = max(1, (int) ($b['nights'] ?? 1));
                                    $deposit = (float) ($b['deposit_amount'] ?? 0);
                                    $balance = (float) ($b['balance_amount'] ?? 0);
                                    $totalStay = (float) ($b['total_amount'] ?? 0);
                                    $customerName = (string) ($b['customer_name'] ?? 'Guest');
                                    $propertyName = (string) ($b['listing_title'] ?? 'Hotel Property');
                                    $roomName = (string) (!empty($b['room_name']) ? $b['room_name'] : 'Standard Room');

                                    $resPillClass = match ($hotelStatus) {
                                        'confirmed' => 'pill-approved',
                                        'deposit_paid' => 'pill-paid',
                                        'accepted' => 'pill-completed',
                                        'checked_in' => 'pill-completed',
                                        'pending' => 'pill-pending',
                                        'cancelled' => 'pill-cancelled',
                                        'no_show' => 'pill-refunded',
                                        default => 'pill-secondary',
                                    };
                                    $resPillLabel = match ($hotelStatus) {
                                        'pending' => 'Pending',
                                        'accepted' => 'Accepted',
                                        'deposit_paid' => 'Deposit Paid',
                                        'confirmed' => 'Confirmed',
                                        'checked_in' => 'Checked In',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                        'no_show' => 'No-Show',
                                        default => ucfirst($hotelStatus),
                                    };
                                    ?>
                                    <tr>
                                        <!-- 1. GUEST -->
                                        <td>
                                            <strong style="color:var(--ink-400);font-size:0.88rem;display:block;line-height:1.2;"><?= e($customerName) ?></strong>
                                        </td>

                                        <!-- 2. PROPERTY -->
                                        <td>
                                            <strong style="color:var(--ink-400);font-size:0.88rem;display:block;line-height:1.25;"><?= e($propertyName) ?></strong>
                                        </td>

                                        <!-- 3. ROOM & STAY -->
                                        <td>
                                            <strong style="color:var(--teal-700);font-size:0.88rem;display:block;line-height:1.25;"><?= e($roomName) ?></strong>
                                            <small class="hint" style="font-size:0.75rem;display:block;margin-top:2px;">
                                                <?= e(date('M j', strtotime($checkIn))) ?> – <?= e(date('M j, Y', strtotime($checkOut))) ?> · <?= $nights ?> <?= $nights > 1 ? 'nights' : 'night' ?> · <?= (int) ($b['guests'] ?? 1) ?> <?= (int) ($b['guests'] ?? 1) > 1 ? 'guests' : 'guest' ?>
                                            </small>
                                        </td>

                                        <!-- 3. TOTAL & DEPOSIT -->
                                        <td>
                                            <strong style="font-size:0.9rem;color:var(--ink-400);display:block;line-height:1.2;"><?= money($totalStay) ?></strong>
                                            <span class="badge" style="background:#ECFDF5;color:#047857;border:1px solid #A7F3D0;font-size:0.72rem;font-weight:600;padding:1px 6px;border-radius:5px;white-space:nowrap;margin-top:2px;display:inline-block;"><?= money($deposit > 0 ? $deposit : $totalStay) ?> deposit</span>
                                        </td>

                                        <!-- 4. BALANCE DUE AT HOTEL -->
                                        <td>
                                            <?php if ($balance > 0 && !in_array($hotelStatus, ['completed', 'cancelled', 'no_show'], true)): ?>
                                                <strong style="font-size:0.92rem;color:#D97706;display:block;line-height:1.2;"><?= money($balance) ?></strong>
                                                <small class="hint" style="font-size:0.72rem;color:#B45309;font-weight:600;white-space:nowrap;display:block;margin-top:2px;">At front desk</small>
                                            <?php elseif ($hotelStatus === 'completed'): ?>
                                                <span class="pill pill-approved" style="font-size:0.74rem;padding:2px 7px;">Settled</span>
                                            <?php else: ?>
                                                <span class="hint">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 5. STATUS -->
                                        <td>
                                            <span class="pill <?= $resPillClass ?>" style="display:inline-flex;align-items:center;justify-content:center;padding:0.25rem 0.65rem;font-size:0.75rem;font-weight:600;line-height:1.2;white-space:nowrap;"><?= e($resPillLabel) ?></span>
                                        </td>

                                        <!-- 6. ACTIONS (3-DOT DROPDOWN MENU) -->
                                        <td class="col-actions" style="text-align:right;white-space:nowrap;">
                                            <div class="res-action-wrap">
                                                <button type="button"
                                                        class="btn-res-action-trigger"
                                                        id="resActionBtnBk_<?= (int) $b['id'] ?>"
                                                        aria-haspopup="true"
                                                        aria-expanded="false"
                                                        aria-controls="resActionMenuBk_<?= (int) $b['id'] ?>"
                                                        title="Actions for <?= e($customerName) ?>"
                                                        aria-label="Actions for <?= e($customerName) ?>">
                                                    <?= admin_icon('more-vertical', 16) ?>
                                                </button>
                                                <div class="res-action-dropdown"
                                                     id="resActionMenuBk_<?= (int) $b['id'] ?>"
                                                     role="menu"
                                                     aria-labelledby="resActionBtnBk_<?= (int) $b['id'] ?>">
                                                    <?php if ($hotelStatus === 'pending'): ?>
                                                        <!-- Accept Reservation -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="redirect_to" value="/dashboard/bookings">
                                                            <button type="submit" name="action" value="accept" class="res-action-item action-accept" role="menuitem">
                                                                Accept Reservation
                                                            </button>
                                                        </form>
                                                        <!-- Confirm Deposit -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="redirect_to" value="/dashboard/bookings">
                                                            <button type="submit" name="action" value="confirm_deposit" class="res-action-item action-confirm" role="menuitem">
                                                                Confirm Deposit
                                                            </button>
                                                        </form>
                                                        <!-- Cancel Reservation -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Cancel this pending reservation for <?= e(addslashes($customerName)) ?>?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="redirect_to" value="/dashboard/bookings">
                                                            <button type="submit" name="action" value="cancel" class="res-action-item action-cancel" role="menuitem">
                                                                Cancel Reservation
                                                            </button>
                                                        </form>

                                                    <?php elseif ($hotelStatus === 'accepted' || $hotelStatus === 'deposit_paid'): ?>
                                                        <!-- Confirm Deposit -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="redirect_to" value="/dashboard/bookings">
                                                            <button type="submit" name="action" value="confirm_deposit" class="res-action-item action-confirm" role="menuitem">
                                                                Confirm Deposit
                                                            </button>
                                                        </form>
                                                        <!-- Cancel Reservation -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Cancel this reservation for <?= e(addslashes($customerName)) ?>?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="redirect_to" value="/dashboard/bookings">
                                                            <button type="submit" name="action" value="cancel" class="res-action-item action-cancel" role="menuitem">
                                                                Cancel Reservation
                                                            </button>
                                                        </form>

                                                    <?php elseif ($hotelStatus === 'confirmed'): ?>
                                                        <!-- Check In -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="redirect_to" value="/dashboard/bookings">
                                                            <button type="submit" name="action" value="check_in" class="res-action-item action-checkin" role="menuitem">
                                                                Check In
                                                            </button>
                                                        </form>
                                                        <!-- Cancel Reservation -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Cancel this confirmed reservation for <?= e(addslashes($customerName)) ?>?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="redirect_to" value="/dashboard/bookings">
                                                            <button type="submit" name="action" value="cancel" class="res-action-item action-cancel" role="menuitem">
                                                                Cancel Reservation
                                                            </button>
                                                        </form>

                                                    <?php elseif ($hotelStatus === 'checked_in'): ?>
                                                        <!-- Settle Balance & Check Out -->
                                                        <form method="post" action="<?= e(url('/dashboard/hotel/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form" onsubmit="return confirm('Collect outstanding balance of <?= money($balance) ?> and complete check-out?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="redirect_to" value="/dashboard/bookings">
                                                            <button type="submit" name="action" value="settle_balance" class="res-action-item action-settle" role="menuitem">
                                                                Settle &amp; Check Out
                                                            </button>
                                                        </form>

                                                    <?php else: ?>
                                                        <div style="padding: 8px 12px; font-size: 0.8rem; color: #94a3b8; font-weight: 500;">No actions available</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <table class="table guide-bookings-table" style="vertical-align:middle;width:100%;margin:0;">
                            <thead>
                                <tr>
                                    <th class="col-customer" style="width:16%;"><?= e(__('th_customer', 'Customer')) ?></th>
                                    <th class="col-listing" style="width:20%;"><?= e(__('th_listing', 'Listing')) ?></th>
                                    <th class="col-date" style="width:11%;white-space:nowrap;"><?= e(__('th_date', 'Date')) ?></th>
                                    <th class="col-guests" style="width:7%;text-align:center;white-space:nowrap;"><?= e(__('g_guests', 'Guests')) ?></th>
                                    <th class="col-total" style="width:11%;white-space:nowrap;"><?= e(__('th_total', 'Total')) ?></th>
                                    <th class="col-payment" style="width:9%;white-space:nowrap;"><?= e(__('g_payment', 'Payment')) ?></th>
                                    <th class="col-status" style="width:18%;white-space:nowrap;"><?= e(__('th_status', 'Status')) ?></th>
                                    <th class="col-actions" style="width:8%;text-align:right;padding-right:1.5rem;" aria-label="<?= e(__('th_actions', 'Actions')) ?>"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bookings as $b): ?>
                                <tr<?= $b['status'] === 'pending' ? ' class="row-pending" style="background:#FFFBEB;"' : '' ?>>
                                    <!-- 1. CUSTOMER -->
                                    <td class="col-customer">
                                        <strong class="booking-customer-name" title="<?= e($b['customer_name']) ?>"><?= e($b['customer_name']) ?></strong>
                                        <?php if (!empty($b['customer_email'])): ?>
                                            <small class="hint booking-customer-email" title="<?= e($b['customer_email']) ?>"><?= e($b['customer_email']) ?></small>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 2. LISTING -->
                                    <td class="col-listing">
                                        <a href="<?= e(url('/listing/' . ($b['listing_slug'] ?? $b['listing_id']))) ?>" target="_blank" class="booking-listing-link" title="<?= e($b['listing_title']) ?>">
                                            <?= e($b['listing_title']) ?>
                                        </a>
                                    </td>

                                    <!-- 3. DATE -->
                                    <td class="col-date" style="white-space:nowrap;font-weight:500;color:var(--ink-400, #334155);font-size:0.82rem;">
                                        <?= e(loc_date((string) $b['booking_date'])) ?>
                                    </td>

                                    <!-- 4. GUESTS -->
                                    <td class="col-guests" style="text-align:center;font-weight:600;color:var(--ink-400, #334155);font-size:0.84rem;">
                                        <?= (int) $b['guests'] ?>
                                    </td>

                                    <!-- 5. TOTAL -->
                                    <td class="col-total" style="white-space:nowrap;">
                                        <strong style="font-size:0.88rem;font-weight:700;color:var(--text, #0f172a);"><?= money($b['total_amount']) ?></strong>
                                    </td>

                                    <!-- 6. PAYMENT -->
                                    <td class="col-payment" style="white-space:nowrap;">
                                        <span class="pill pill-<?= e($b['payment_status'] ?? 'pending') ?>" style="display:inline-flex;align-items:center;justify-content:center;padding:0.22rem 0.55rem;font-size:0.72rem;font-weight:600;line-height:1.2;white-space:nowrap;border-radius:9999px;"><?= e(status_label((string) ($b['payment_status'] ?? 'unpaid'))) ?></span>
                                    </td>

                                    <!-- 7. STATUS -->
                                    <td class="col-status" style="white-space:nowrap;">
                                        <span class="pill pill-<?= e($b['status']) ?>" style="display:inline-flex;align-items:center;justify-content:center;padding:0.22rem 0.55rem;font-size:0.72rem;font-weight:600;line-height:1.2;white-space:nowrap;border-radius:9999px;"><?= e(status_label((string) $b['status'])) ?></span>
                                    </td>

                                    <!-- 8. ACTIONS (3-DOT OVERFLOW MENU MATCHING MY LISTING) -->
                                    <td class="col-actions" style="text-align:right;white-space:nowrap;padding-right:1.5rem;">
                                        <div style="display:inline-flex;align-items:center;justify-content:flex-end;">
                                            <div class="res-action-wrap">
                                                <button type="button"
                                                        class="btn-res-action-trigger"
                                                        id="bookingActionBtn_<?= (int) $b['id'] ?>"
                                                        aria-haspopup="true"
                                                        aria-expanded="false"
                                                        aria-controls="bookingActionMenu_<?= (int) $b['id'] ?>"
                                                        title="Actions for booking #<?= (int) $b['id'] ?>"
                                                        aria-label="Actions for booking #<?= (int) $b['id'] ?>">
                                                    <?= admin_icon('more-vertical', 16) ?>
                                                </button>
                                                <div class="res-action-dropdown"
                                                     id="bookingActionMenu_<?= (int) $b['id'] ?>"
                                                     role="menu"
                                                     aria-labelledby="bookingActionBtn_<?= (int) $b['id'] ?>">
                                                    <?php if ($isWarned): ?>
                                                        <div style="padding:8px 12px;font-size:0.8rem;color:#dc2626;font-weight:600;display:flex;align-items:center;gap:6px;">
                                                            <?= admin_icon('alert-triangle', 14) ?> <?= e(__('g_restricted', 'Actions restricted')) ?>
                                                        </div>
                                                    <?php elseif ($b['status'] === 'pending'): ?>
                                                        <form method="post" action="<?= e(url('/dashboard/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form guide-booking-action" data-action="confirm">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="status" class="guide-status-select" value="confirmed">
                                                            <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                <?= admin_icon('check-circle', 14) ?> <?= e(__('g_confirm', 'Confirm')) ?>
                                                            </button>
                                                        </form>
                                                        <form method="post" action="<?= e(url('/dashboard/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form guide-booking-action" data-action="cancel" data-paid="<?= ($b['payment_status'] ?? '') === 'paid' ? '1' : '0' ?>" data-amount="<?= e((string) $b['total_amount']) ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="status" class="guide-status-select" value="cancelled">
                                                            <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                <?= admin_icon('x-circle', 14) ?> <?= e(__('booking_cancel', 'Cancel')) ?>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($b['status'] === 'confirmed'): ?>
                                                        <form method="post" action="<?= e(url('/dashboard/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form guide-booking-action" data-action="complete">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="status" class="guide-status-select" value="completed">
                                                            <button type="submit" class="res-action-item action-accept" role="menuitem">
                                                                <?= admin_icon('check-circle', 14) ?> <?= e(__('g_complete', 'Complete')) ?>
                                                            </button>
                                                        </form>
                                                        <form method="post" action="<?= e(url('/dashboard/bookings/' . $b['id'] . '/status')) ?>" class="res-action-form guide-booking-action" data-action="cancel" data-paid="<?= ($b['payment_status'] ?? '') === 'paid' ? '1' : '0' ?>" data-amount="<?= e((string) $b['total_amount']) ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="status" class="guide-status-select" value="cancelled">
                                                            <button type="submit" class="res-action-item action-cancel" role="menuitem">
                                                                <?= admin_icon('x-circle', 14) ?> <?= e(__('booking_cancel', 'Cancel')) ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if (!$isWarned && in_array($b['status'], ['pending', 'confirmed'], true)): ?>
                                                        <div style="border-top:1px solid var(--line, #e2e8f0);margin:2px 0;"></div>
                                                    <?php endif; ?>
                                                    <a href="<?= e(url('/listing/' . ($b['listing_slug'] ?? $b['listing_id']))) ?>" target="_blank" class="res-action-item" role="menuitem">
                                                        <?= admin_icon('eye', 14) ?> <?= e(__('d_view_live', 'View live listing')) ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
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
