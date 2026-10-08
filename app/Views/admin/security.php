<?php
/** @var array<string,mixed> $admin @var ?string $setupSecret @var array<string,string> $errors */
$enabled = (int) ($admin['admin_totp_enabled'] ?? 0) === 1;
$errors = $errors ?? [];
?>
<h1>Admin security</h1>
<div class="panel">
    <div class="panel-head"><h3>Two-factor authentication (TOTP)</h3></div>
    <div class="panel-body">
        <?php if ($enabled): ?>
            <p>2FA is <strong>enabled</strong> on your admin account.</p>
            <form method="post" action="<?= e(url('/admin/security/disable')) ?>">
                <?= csrf_field() ?>
                <div class="field-row">
                    <label for="code_disable">Enter current code to disable</label>
                    <input class="input" id="code_disable" type="text" name="code" inputmode="numeric" maxlength="6" required>
                </div>
                <button class="btn btn-ghost" type="submit">Disable 2FA</button>
            </form>
        <?php elseif ($setupSecret): ?>
            <p>Scan this secret in Google Authenticator or similar: <code><?= e($setupSecret) ?></code></p>
            <p class="hint">Or enter the secret manually. Then confirm with a code below.</p>
            <form method="post" action="<?= e(url('/admin/security/enable')) ?>">
                <?= csrf_field() ?>
                <div class="field-row">
                    <label for="code_enable">6-digit code</label>
                    <input class="input" id="code_enable" type="text" name="code" inputmode="numeric" maxlength="6" required>
                    <?php if (isset($errors['code'])): ?><div class="field-error"><?= e($errors['code']) ?></div><?php endif; ?>
                </div>
                <button class="btn btn-primary" type="submit">Enable 2FA</button>
            </form>
        <?php else: ?>
            <p>Protect the admin portal with a time-based one-time password.</p>
            <form method="post" action="<?= e(url('/admin/security/setup')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit">Set up 2FA</button>
            </form>
        <?php endif; ?>
    </div>
</div>
