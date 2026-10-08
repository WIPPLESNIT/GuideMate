<?php /** @var array<string,string> $errors */ $errors = $errors ?? []; ?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <h1><?= e(__('g_verify_tourist', 'Verify tourist')) ?></h1>
            <p class="hint"><?= e(__('g_verify_hint', "Enter the booking QR code or token shown on the tourist's phone.")) ?></p>
            <div class="panel">
                <div class="panel-body">
                    <form method="post" action="<?= e(url('/dashboard/bookings/verify')) ?>">
                        <?= csrf_field() ?>
                        <div class="field-row">
                            <label for="verify_token"><?= e(__('g_booking_code', 'Booking code')) ?></label>
                            <input class="input" id="verify_token" name="verify_token" placeholder="<?= e(__('g_scan_ph', 'Scan or paste code')) ?>" required autofocus>
                            <?php if (isset($errors['verify_token'])): ?><div class="field-error"><?= e($errors['verify_token']) ?></div><?php endif; ?>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= e(__('g_verify', 'Verify')) ?></button>
                        <a class="btn btn-ghost" href="<?= e(url('/dashboard/bookings')) ?>"><?= e(__('g_back', 'Back')) ?></a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
