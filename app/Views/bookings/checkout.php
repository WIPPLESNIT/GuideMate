<?php
/** @var array<string,mixed> $booking @var ?array<string,mixed> $payment */
$paid = $payment && $payment['status'] === 'paid';
$errors = errors();
$isHotel = !empty($booking['room_id']) || ((float) ($booking['deposit_amount'] ?? 0) > 0);
$depositAmount = (float) ($booking['deposit_amount'] ?? 0);
$balanceAmount = (float) ($booking['balance_amount'] ?? 0);
$payNowAmount = $isHotel && $depositAmount > 0 ? $depositAmount : (float) $booking['total_amount'];
?>
<section class="page-head">
    <div class="container">
        <h1><?= $isHotel ? 'Reserve Room — Secure Deposit' : 'Checkout' ?></h1>
        <p><?= $isHotel ? 'Pay a partial deposit online to lock in your room. The balance is paid at the hotel.' : 'Pay now. The partner still needs to confirm your booking afterwards.' ?></p>
    </div>
</section>
<section class="section">
    <div class="container" style="max-width:880px;">
        <div class="grid-2">
            <div>
                <div class="card-soft">
                    <h3><?= $isHotel ? 'Reservation summary' : 'Order summary' ?></h3>
                    <div class="guide-mini">
                        <img src="<?= e(img_src($booking['cover_image'] ?? null, 'listing' . $booking['listing_id'])) ?>" alt="" style="width:64px;height:64px;border-radius:12px;object-fit:cover;">
                        <div>
                            <strong><?= e($booking['listing_title']) ?></strong><br>
                            <?php if ($isHotel && !empty($booking['room_name'])): ?>
                                <span class="badge" style="background:#E0F2FE;color:#0369A1;font-weight:600;margin:2px 0;display:inline-block;">🛏️ <?= e($booking['room_name']) ?></span><br>
                            <?php endif; ?>
                            <small class="hint">
                                <?php if ($isHotel && !empty($booking['check_in_date']) && !empty($booking['check_out_date'])): ?>
                                    📅 <?= e(date('M j', strtotime($booking['check_in_date']))) ?> – <?= e(date('M j, Y', strtotime($booking['check_out_date']))) ?> (<?= (int) ($booking['nights'] ?? 1) ?> night<?= (int) ($booking['nights'] ?? 1) > 1 ? 's' : '' ?>) · <?= (int) $booking['guests'] ?> guest(s)
                                <?php else: ?>
                                    <?= e(date('M j, Y', strtotime($booking['booking_date']))) ?> · <?= (int) $booking['guests'] ?> guest(s)
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0;">
                    
                    <?php if ($isHotel && $depositAmount > 0): ?>
                        <div class="result-bar" style="margin:0;"><span>Total Room Stay</span><span><?= money($booking['total_amount']) ?></span></div>
                        <div class="result-bar" style="margin:.4rem 0 0;color:#059669;"><strong>Deposit Due Online (Now)</strong><strong style="font-size:1.25rem;color:#059669;"><?= money($depositAmount) ?></strong></div>
                        <div class="result-bar" style="margin:.4rem 0 0;color:#D97706;"><span>Remaining Balance at Check-in</span><span style="font-weight:700;color:#D97706;"><?= money($balanceAmount) ?></span></div>
                        
                        <div class="card-soft mt-3" style="background:#F0FDF4;border-color:#BBF7D0;padding:0.75rem 1rem;">
                            <strong style="color:#166534;font-size:0.9rem;">🏨 Front Desk Payment Policy</strong>
                            <p class="hint mb-0" style="color:#15803D;font-size:0.85rem;margin-top:4px;">
                                Your online deposit secures your room instantly. The remaining <strong><?= money($balanceAmount) ?></strong> is paid directly to the hotel front desk upon arrival via Cash, GCash, or Card.
                            </p>
                        </div>

                        <div class="card-soft mt-2" style="background:#F8FAFC;border-color:#E2E8F0;padding:0.75rem 1rem;">
                            <strong style="color:#334155;font-size:0.85rem;">🛡️ Cancellation Terms</strong>
                            <p class="hint mb-0" style="font-size:0.82rem;margin-top:2px;">
                                Free cancellation up to 48 hours before check-in (100% deposit refund). Non-refundable within 48 hours of arrival.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="result-bar" style="margin:0;"><span>Subtotal</span><span><?= money($booking['total_amount']) ?></span></div>
                        <div class="result-bar" style="margin:.4rem 0 0;"><strong>Total due</strong><strong style="font-size:1.3rem;color:var(--teal-900);"><?= money($booking['total_amount']) ?></strong></div>
                        <p class="hint mt-2 mb-0">By paying, you agree that included services must be honored and guides must not ask for undeclared extra fees. <a href="<?= e(url('/policy')) ?>">Platform policy</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <?php if ($paid): ?>
                    <div class="card-soft text-center">
                        <div class="big" style="font-size:3rem;">✅</div>
                        <h3><?= $isHotel ? 'Room Deposit Paid!' : 'Payment complete' ?></h3>
                        <p class="hint">Payment Reference: <strong><?= e($payment['reference']) ?></strong></p>
                        <?php if ($isHotel && $balanceAmount > 0): ?>
                            <div class="card-soft mt-2 mb-2" style="background:#FEF3C7;border-color:#FDE68A;">
                                <strong style="color:#92400E;">Balance Due at Check-in: <?= money($balanceAmount) ?></strong>
                                <p class="mb-0 hint" style="color:#78350F;font-size:0.85rem;">Please present this booking confirmation at the front desk upon check-in.</p>
                            </div>
                        <?php endif; ?>
                        <a href="<?= e(url('/bookings')) ?>" class="btn btn-primary mt-2">View my bookings</a>
                    </div>
                <?php else: ?>
                    <div class="card-soft">
                        <h3><?= $isHotel ? 'Pay Room Deposit' : 'Payment details' ?></h3>
                        <p class="hint">This is a <strong>simulated</strong> payment for demo purposes — no real charge is made. Use any test card number.</p>
                        <form method="post" action="<?= e(url('/checkout/' . $booking['id'])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="method" value="card">
                            <div class="field-row">
                                <label for="card_name">Name on card</label>
                                <input class="input" id="card_name" name="card_name" value="<?= e($booking['customer_name']) ?>">
                            </div>
                            <div class="field-row">
                                <label for="card_number">Card number</label>
                                <input class="input" id="card_number" name="card_number" placeholder="4242 4242 4242 4242" inputmode="numeric">
                                <?php if (isset($errors['card_number'])): ?><div class="field-error"><?= e($errors['card_number']) ?></div><?php endif; ?>
                            </div>
                            <div class="form-grid-2">
                                <div class="field-row"><label for="exp">Expiry</label><input class="input" id="exp" name="exp" placeholder="MM/YY"></div>
                                <div class="field-row">
                                    <label for="cvc">CVC</label><input class="input" id="cvc" name="cvc" placeholder="123">
                                </div>
                            </div>
                            <div class="field-row">
                                <label for="promo_code">Promo code (optional)</label>
                                <input class="input" id="promo_code" name="promo_code" placeholder="e.g. CEBU10" value="<?= e(old('promo_code')) ?>">
                                <?php if (isset($errors['promo_code'])): ?><div class="field-error"><?= e($errors['promo_code']) ?></div><?php endif; ?>
                            </div>
                            <p class="hint">By paying you agree to our <a href="<?= e(url('/terms')) ?>">Terms</a> and <a href="<?= e(url('/privacy')) ?>">Privacy Policy</a>.</p>
                            <button class="btn btn-primary btn-block btn-lg" type="submit">
                                <?= $isHotel && $depositAmount > 0 ? 'Pay Deposit ' . money($payNowAmount) : 'Pay ' . money($payNowAmount) ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
