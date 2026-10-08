<?php
/** @var array<string,mixed> $booking @var ?array<string,mixed> $payment */
$paid = $payment && $payment['status'] === 'paid';
$errors = errors();
?>
<section class="page-head">
    <div class="container"><h1>Checkout</h1><p>Pay now. The partner still needs to confirm your booking afterwards.</p></div>
</section>
<section class="section">
    <div class="container" style="max-width:880px;">
        <div class="grid-2">
            <div>
                <div class="card-soft">
                    <h3>Order summary</h3>
                    <div class="guide-mini">
                        <img src="<?= e(img_src($booking['cover_image'] ?? null, 'listing' . $booking['listing_id'])) ?>" alt="" style="width:64px;height:64px;border-radius:12px;">
                        <div>
                            <strong><?= e($booking['listing_title']) ?></strong><br>
                            <small class="hint"><?= e(date('M j, Y', strtotime($booking['booking_date']))) ?> · <?= (int) $booking['guests'] ?> guest(s)</small>
                        </div>
                    </div>
                    <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0;">
                    <div class="result-bar" style="margin:0;"><span>Subtotal</span><span><?= money($booking['total_amount']) ?></span></div>
                    <div class="result-bar" style="margin:.4rem 0 0;"><strong>Total due</strong><strong style="font-size:1.3rem;color:var(--teal-900);"><?= money($booking['total_amount']) ?></strong></div>
                    <p class="hint mt-2 mb-0">By paying, you agree that included services must be honored and guides must not ask for undeclared extra fees. <a href="<?= e(url('/policy')) ?>">Platform policy</a></p>
                </div>
            </div>

            <div>
                <?php if ($paid): ?>
                    <div class="card-soft text-center">
                        <div class="big" style="font-size:3rem;">✅</div>
                        <h3>Payment complete</h3>
                        <p class="hint">Reference: <strong><?= e($payment['reference']) ?></strong></p>
                        <a href="<?= e(url('/bookings')) ?>" class="btn btn-primary mt-2">View my bookings</a>
                    </div>
                <?php else: ?>
                    <div class="card-soft">
                        <h3>Payment details</h3>
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
                            <button class="btn btn-primary btn-block btn-lg" type="submit">Pay <?= money($booking['total_amount']) ?></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
