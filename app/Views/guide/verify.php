<?php
/** @var array<string,string> $errors @var bool $isWarned @var string $warningNote */
$errors = $errors ?? [];
$isWarned = $isWarned ?? \App\Models\User::isGuideWarned((int) (auth_user()['id'] ?? 0));
$warningNote = $warningNote ?? (string) (auth_user()['guide_warning_note'] ?? '');
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div>
            <h1><?= e(__('g_verify_tourist', 'Verify tourist')) ?></h1>
            <p class="hint"><?= e(__('g_verify_hint', "Enter the booking QR code or token shown on the tourist's phone.")) ?></p>

            <?php if ($isWarned): ?>
                <div class="status-banner banner-red" style="margin-bottom:1.2rem;">
                    <span class="status-icon">⚠️</span>
                    <div>
                        <strong>Administrator warning — booking actions restricted</strong>
                        <p class="mb-0">
                            <?= $warningNote !== '' ? e($warningNote) . ' — ' : '' ?>
                            Booking verification and completion actions are restricted until an administrator clears this warning.
                            <a href="<?= e(url('/messages')) ?>">View administrator message →</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-body">
                    <form method="post" action="<?= e(url('/dashboard/bookings/verify')) ?>">
                        <?= csrf_field() ?>
                        <div class="field-row">
                            <label for="verify_token"><?= e(__('g_booking_code', 'Booking code')) ?></label>
                            <input class="input" id="verify_token" name="verify_token" placeholder="<?= e(__('g_scan_ph', 'Scan or paste code')) ?>" required autofocus<?= $isWarned ? ' disabled' : '' ?>>
                            <?php if (isset($errors['verify_token'])): ?><div class="field-error"><?= e($errors['verify_token']) ?></div><?php endif; ?>
                        </div>
                        <button class="btn btn-primary" type="submit"<?= $isWarned ? ' disabled title="Unavailable: Active administrator warning on your account." style="opacity:0.6;cursor:not-allowed;"' : '' ?>><?= e(__('g_verify', 'Verify')) ?></button>
                        <a class="btn btn-ghost" href="<?= e(url('/dashboard/bookings')) ?>"><?= e(__('g_back', 'Back')) ?></a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
