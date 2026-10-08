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
                            <tr>
                                <td><a href="<?= e(url('/listing/' . $b['listing_slug'])) ?>"><strong><?= e($b['listing_title']) ?></strong></a><br><small class="hint"><?= e($b['area']) ?></small></td>
                                <td><?= e(date('M j, Y', strtotime($b['booking_date']))) ?></td>
                                <td><?= (int) $b['guests'] ?></td>
                                <td><strong><?= money($b['total_amount']) ?></strong></td>
                                <td><span class="pill pill-<?= e($b['status']) ?>"><?= e(status_label((string) $b['status'])) ?></span>
                                    <?php if ($b['status'] === 'pending'): ?><br><small class="hint">Waiting for the partner to confirm</small><?php endif; ?>
                                    <?php if ($b['status'] === 'refunded'): ?><br><small class="hint">Guide cancelled — payment returned</small><?php endif; ?>
                                </td>
                                <td><span class="pill pill-<?= e($b['payment_status'] ?? 'pending') ?>"><?= e(ucfirst($b['payment_status'] ?? 'unpaid')) ?></span></td>
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
                                    <?php if ($b['status'] === 'pending'): ?>
                                        <form method="post" class="booking-action-form" action="<?= e(url('/bookings/' . $b['id'] . '/cancel')) ?>" onsubmit="return confirm('Cancel this booking?');">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-ghost btn-sm booking-action-btn" type="submit">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (($b['payment_status'] ?? '') === 'paid' && in_array($b['status'], ['confirmed', 'completed'], true)): ?>
                                        <a class="btn btn-ghost btn-sm booking-action-btn" href="<?= e(url('/bookings/map?booking=' . $b['id'])) ?>">Navigate</a>
                                    <?php endif; ?>
                                    <?php if (($b['payment_status'] ?? '') === 'paid' && in_array($b['status'], ['confirmed', 'completed', 'disputed'], true)): ?>
                                        <?php if (!empty($b['dispute_id'])): ?>
                                            <?php
                                            $disputeLabel = match ($b['dispute_status'] ?? '') {
                                                'resolved_warning' => 'Report reviewed — guide warned',
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
