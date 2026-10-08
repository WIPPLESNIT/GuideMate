<?php /** @var array<string,string> $errors */ $errors = $errors ?? []; ?>
<div class="auth-card" style="max-width:420px;margin:3rem auto;">
    <h1>Two-factor authentication</h1>
    <p class="hint">Enter the 6-digit code from your authenticator app.</p>
    <form method="post" action="<?= e(url('/admin/2fa')) ?>">
        <?= csrf_field() ?>
        <div class="field-row">
            <label for="code">Authentication code</label>
            <input class="input" id="code" type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus>
            <?php if (isset($errors['code'])): ?><div class="field-error"><?= e($errors['code']) ?></div><?php endif; ?>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Verify</button>
    </form>
    <p class="hint mt-2"><a href="<?= e(url('/admin/security')) ?>">Manage 2FA settings</a></p>
</div>
