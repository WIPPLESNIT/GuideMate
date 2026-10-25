<?php
/** @var array<int,array<string,mixed>> $bookings */
?>
<section class="page-head">
    <div class="container" style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div><h1>My bookings</h1><p class="mb-0">Track and manage your Cebu adventures.</p></div>
        <a href="<?= e(url('/bookings/map')) ?>" class="btn btn-primary btn-sm">🗺️ Trip map</a>
    </div>
</section>
<section class="section">
    <div class="container">
        <?php if ($bookings === []): ?>
            <div class="empty-state">
                <div class="big">🧳</div>
                <h3>No bookings yet</h3>
                <p>When you book a tour, guide, stay or table, it shows up here.</p>
                <a href="<?= e(url('/listings')) ?>" class="btn btn-primary mt-2">Find something to do</a>
            </div>
        <?php else: ?>
            <div class="panel">
                <div class="panel-body" style="padding:0;overflow-x:auto;">
                    <table class="table table-bookings">
                        <thead>
                            <tr><th>Listing</th><th>Date</th><th>Guests</th><th>Total</th><th>Status</th><th>Payment</th><th class="col-booking-actions">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <?php
                            $isHotel = !empty($b['room_id']) || !empty($b['hotel_status']) || (!empty($b['deposit_amount']) && (float) $b['deposit_amount'] > 0);
                            $hotelStatus = (string) ($b['hotel_status'] ?? '');
                            $status = (string) $b['status'];
                            $total = (float) $b['total_amount'];
                            $deposit = (float) ($b['deposit_amount'] ?? 0);
                            $balance = (float) ($b['balance_amount'] ?? max(0, $total - $deposit));
                            $isConfirmed = $hotelStatus === 'confirmed' || $status === 'confirmed';
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('/listing/' . $b['listing_slug'])) ?>"><strong><?= e($b['listing_title']) ?></strong></a>
                                    <?php if ($isHotel && !empty($b['room_name'])): ?>
                                        <div style="margin-top:0.25rem;">
                                            <span class="badge" style="background:#EFF6FF;color:#1D4ED8;font-size:0.75rem;padding:2px 8px;border-radius:6px;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                                                🛏️ <?= e($b['room_name']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <small class="hint" style="display:block;margin-top:0.2rem;"><?= e($b['area']) ?></small>
                                </td>
                                <td>
                                    <?php if ($isHotel && (!empty($b['check_in_date']) || !empty($b['check_out_date']))): ?>
                                        <div style="font-weight:700;font-size:0.85rem;color:var(--ink-400, #111827);">
                                            In: <?= e(date('M j, Y', strtotime($b['check_in_date'] ?? $b['booking_date']))) ?>
                                        </div>
                                        <div style="font-size:0.82rem;color:var(--ink-200, #6B7280);margin-top:1px;">
                                            Out: <?= e(date('M j, Y', strtotime($b['check_out_date'] ?? $b['booking_date']))) ?>
                                        </div>
                                        <small class="hint"><?= (int) ($b['nights'] ?? 1) ?> night<?= ((int) ($b['nights'] ?? 1) > 1 ? 's' : '') ?></small>
                                    <?php else: ?>
                                        <?= e(date('M j, Y', strtotime($b['booking_date']))) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $b['guests'] ?></td>
                                <td>
                                    <strong><?= money($total) ?></strong>
                                    <?php if ($isHotel && $deposit > 0): ?>
                                        <div style="font-size:0.75rem;color:#059669;font-weight:700;margin-top:3px;">
                                            ✓ <?= money($deposit) ?> deposit paid
                                        </div>
                                        <?php if ($balance > 0 && !in_array($hotelStatus, ['cancelled', 'completed'], true)): ?>
                                            <div style="font-size:0.75rem;color:#D97706;font-weight:700;margin-top:1px;">
                                                Due: <?= money($balance) ?>
                                            </div>
                                            <small class="hint" style="font-size:0.7rem;display:block;line-height:1.2;margin-top:2px;">
                                                Pay at front desk on check-in
                                            </small>
                                        <?php elseif ($hotelStatus === 'completed'): ?>
                                            <small class="hint" style="color:#059669;font-weight:600;display:block;margin-top:2px;">Fully settled</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isHotel && !empty($hotelStatus)): ?>
                                        <?php
                                        $hotelStatusStyle = match ($hotelStatus) {
                                            'confirmed' => ['bg' => '#DCFCE7', 'color' => '#14532D', 'border' => '#86EFAC', 'label' => '✓ Confirmed'],
                                            'pending' => ['bg' => '#FEF3C7', 'color' => '#92400E', 'border' => '#FCD34D', 'label' => 'Pending Review'],
                                            'accepted' => ['bg' => '#EEF2FF', 'color' => '#3730A3', 'border' => '#C7D2FE', 'label' => 'Accepted'],
                                            'deposit_paid' => ['bg' => '#ECFDF5', 'color' => '#065F46', 'border' => '#A7F3D0', 'label' => 'Deposit Paid'],
                                            'checked_in' => ['bg' => '#E0F2FE', 'color' => '#075985', 'border' => '#7DD3FC', 'label' => 'Checked In'],
                                            'completed' => ['bg' => '#F1F5F9', 'color' => '#334155', 'border' => '#CBD5E1', 'label' => 'Completed'],
                                            'cancelled' => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'border' => '#FCA5A5', 'label' => 'Cancelled'],
                                            'no_show' => ['bg' => '#FEE2E2', 'color' => '#7F1D1D', 'border' => '#FCA5A5', 'label' => 'No-Show'],
                                            default => ['bg' => '#F1F5F9', 'color' => '#334155', 'border' => '#CBD5E1', 'label' => ucfirst($hotelStatus)],
                                        };
                                        ?>
                                        <span class="pill" style="background:<?= $hotelStatusStyle['bg'] ?>;color:<?= $hotelStatusStyle['color'] ?>;border:1px solid <?= $hotelStatusStyle['border'] ?>;font-weight:700;">
                                            <?= e($hotelStatusStyle['label']) ?>
                                        </span>
                                        <?php if ($hotelStatus === 'confirmed'): ?>
                                            <small class="hint" style="color:#059669;display:block;margin-top:3px;font-weight:600;">Room Reserved &amp; Guaranteed</small>
                                        <?php elseif ($hotelStatus === 'deposit_paid'): ?>
                                            <small class="hint" style="display:block;margin-top:3px;">Deposit paid · Awaiting verification</small>
                                        <?php elseif ($hotelStatus === 'accepted'): ?>
                                            <small class="hint" style="display:block;margin-top:3px;">Reservation accepted by hotel</small>
                                        <?php elseif ($hotelStatus === 'pending'): ?>
                                            <small class="hint" style="display:block;margin-top:3px;">Waiting for hotel partner to confirm</small>
                                        <?php elseif ($hotelStatus === 'checked_in'): ?>
                                            <small class="hint" style="display:block;margin-top:3px;">Currently checked in</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="pill pill-<?= e($b['status']) ?>"><?= e(status_label((string) $b['status'])) ?></span>
                                        <?php if ($b['status'] === 'pending'): ?><br><small class="hint">Waiting for the partner to confirm</small><?php endif; ?>
                                        <?php if ($b['status'] === 'refunded'): ?><br><small class="hint">Guide cancelled — payment returned</small><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isHotel && $deposit > 0): ?>
                                        <span class="pill pill-paid">Deposit Paid</span>
                                        <?php if ($balance > 0 && !in_array($hotelStatus, ['cancelled', 'completed'], true)): ?>
                                            <div style="font-size:0.74rem;color:#D97706;font-weight:700;margin-top:4px;">
                                                Pay <?= money($balance) ?> at hotel
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="pill pill-<?= e($b['payment_status'] ?? 'pending') ?>"><?= e(ucfirst($b['payment_status'] ?? 'unpaid')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="booking-actions-cell">
                                    <div class="booking-actions-row">
                                    <?php if (($b['payment_status'] ?? '') === 'paid' && in_array($b['status'], ['confirmed', 'completed'], true) && !empty($b['verify_token'])): ?>
                                        <div class="booking-qr-wrap">
                                            <button type="button" class="booking-qr-btn" aria-expanded="false" aria-label="Show booking code">
                                                <?php $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($b['verify_token']); ?>
                                                <img src="<?= e($qrUrl) ?>" alt="" width="64" height="64">
                                            </button>
                                            <small class="hint booking-qr-hint">Tap QR to show code</small>
                                            <div class="booking-code-panel" hidden>
                                                <span class="hint booking-code-label">Booking code</span>
                                                <code class="booking-code"><?= e($b['verify_token']) ?></code>
                                                <button type="button" class="btn btn-ghost btn-sm booking-code-copy" data-code="<?= e($b['verify_token']) ?>">Copy code</button>
                                            </div>
                                            <?php if (!empty($b['verified_at'])): ?><small class="hint">Verified</small><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="booking-actions-btns">
                                    <?php if (($b['payment_status'] ?? '') !== 'paid' && $b['status'] !== 'cancelled'): ?>
                                        <a class="btn btn-primary btn-sm booking-action-btn" href="<?= e(url('/checkout/' . $b['id'])) ?>">Pay</a>
                                    <?php endif; ?>
                                    <?php if ($b['status'] === 'pending' || $hotelStatus === 'pending'): ?>
                                        <form method="post" class="booking-action-form" action="<?= e(url('/bookings/' . $b['id'] . '/cancel')) ?>" onsubmit="return confirm('Cancel this reservation?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-ghost btn-sm booking-action-btn" type="submit" style="color:#DC2626;border-color:#FECACA;">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((($b['payment_status'] ?? '') === 'paid' || $deposit > 0) && in_array($b['status'], ['confirmed', 'completed'], true)): ?>
                                        <a class="btn btn-ghost btn-sm booking-action-btn" href="<?= e(url('/bookings/map?booking=' . $b['id'])) ?>">Navigate</a>
                                    <?php endif; ?>
                                    <?php if (($b['payment_status'] ?? '') === 'paid' && in_array($b['status'], ['confirmed', 'completed', 'disputed'], true)): ?>
                                        <?php if (!empty($b['dispute_id'])): ?>
                                            <?php
                                             $disputeLabel = match ($b['dispute_status'] ?? '') {
                                                'resolved_warning' => 'Report reviewed — partner warned',
                                                'resolved_refund' => 'Report reviewed — refunded',
                                                'rejected' => 'Report reviewed — rejected',
                                                'reviewing' => 'Report under review',
                                                default => 'Report submitted',
                                            };
                                            ?>
                                            <span class="hint booking-dispute-label"><?= e($disputeLabel) ?></span>
                                        <?php else: ?>
                                            <a class="btn btn-ghost btn-sm booking-action-btn" href="<?= e(url('/bookings/' . $b['id'] . '/report')) ?>">Report problem</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    </div>
                                    </div>
                                    <?php if ($isHotel && $isConfirmed && $balance > 0): ?>
                                        <div style="margin-top:6px;">
                                            <div style="font-size:0.74rem;background:#FFFBEB;border:1px solid #FDE68A;color:#92400E;padding:3px 7px;border-radius:6px;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                                                <span>🛎️</span> Pay <?= money($balance) ?> at front desk check-in
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="hint mt-2">Paid on GuideMate but a guide asked for extra money? Use <strong>Report problem</strong>. <a href="<?= e(url('/policy')) ?>">Read our policy</a></p>
        <?php endif; ?>
    </div>
</section>
