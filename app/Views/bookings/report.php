<?php
/** @var array<string,mixed> $booking @var ?array<string,mixed> $dispute @var array<string,string> $types @var array<string,string> $errors */
$errors = $errors ?? [];
?>
<section class="page-head">
    <div class="container">
        <h1>Report a problem</h1>
        <p>Tell us what happened with your paid booking. Our team will review within 24–72 hours.</p>
    </div>
</section>
<section class="section">
    <div class="container" style="max-width:720px;">
        <div class="card-soft">
            <div class="guide-mini" style="margin-bottom:1.2rem;">
                <img src="<?= e(img_src($booking['cover_image'] ?? null, 'listing' . $booking['listing_id'])) ?>" alt="" style="width:56px;height:56px;border-radius:10px;object-fit:cover;">
                <div>
                    <strong><?= e($booking['listing_title']) ?></strong><br>
                    <small class="hint"><?= e(date('M j, Y', strtotime($booking['booking_date']))) ?> · <?= money($booking['total_amount']) ?></small>
                </div>
            </div>

            <?php if ($dispute !== null): ?>
                <div class="status-banner banner-amber">
                    <span class="status-icon">⏳</span>
                    <div>
                        <strong>Report already submitted</strong>
                        <p class="mb-0">Status: <?= e(\App\Models\Dispute::statusLabel((string) $dispute['status'])) ?>. We will notify you once reviewed.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="policy-note" style="margin-bottom:1.2rem;">
                    <strong>If a guide asked for extra money</strong>
                    <p class="mb-0">Do not pay again if the cost was not listed before booking. Submit this report with details and any proof from your messages.</p>
                </div>

                <form method="post" action="<?= e(url('/bookings/' . $booking['id'] . '/report')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="field-row">
                        <label for="problem_type">What happened?</label>
                        <select class="input" id="problem_type" name="problem_type" required>
                            <option value="">Choose...</option>
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= old('problem_type') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['problem_type'])): ?><div class="field-error"><?= e($errors['problem_type']) ?></div><?php endif; ?>
                    </div>
                    <div class="field-row" id="amountField" style="display:none;">
                        <label for="amount_requested">Extra amount requested (₱)</label>
                        <input class="input" type="number" step="0.01" min="0" id="amount_requested" name="amount_requested" value="<?= e(old('amount_requested')) ?>" placeholder="e.g. 500">
                        <?php if (isset($errors['amount_requested'])): ?><div class="field-error"><?= e($errors['amount_requested']) ?></div><?php endif; ?>
                    </div>
                    <div class="field-row">
                        <label for="description">Describe what happened</label>
                        <textarea class="input" id="description" name="description" rows="5" required placeholder="Include when it happened, what the guide said, and any details that help us review."><?= e(old('description')) ?></textarea>
                        <?php if (isset($errors['description'])): ?><div class="field-error"><?= e($errors['description']) ?></div><?php endif; ?>
                    </div>
                    <div class="field-row">
                        <label for="evidence">Evidence photos (optional)</label>
                        <input class="input" id="evidence" type="file" name="evidence[]" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <button class="btn btn-primary" type="submit">Submit report</button>
                    <a class="btn btn-ghost" href="<?= e(url('/bookings')) ?>">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
