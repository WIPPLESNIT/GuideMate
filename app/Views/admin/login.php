<?php
/** @var array<string,string> $errors */
$errors = $errors ?? [];
?>
<div class="admin-auth">
    <div class="admin-auth-card">
        <div class="admin-badge">ADMIN PORTAL</div>
        <a href="<?= e(url('/')) ?>" class="brand brand-light" style="justify-content:center;margin-bottom:1rem;">
            <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
        </a>
        <h1 style="color:#fff;text-align:center;font-size:1.5rem;">Administrator sign in</h1>
        <p style="text-align:center;color:rgba(255,255,255,.6);margin-bottom:1.8rem;">Restricted area — staff only.</p>

        <form method="post" action="<?= e(url('/admin/login')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field-row">
                <label style="color:rgba(255,255,255,.8);" for="email">Admin email</label>
                <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" placeholder="you@email.com" autofocus>
                <?php if (isset($errors['email'])): ?><div class="field-error" style="color:#ffb4a3;"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>
            <div class="field-row">
                <label style="color:rgba(255,255,255,.8);" for="password">Password</label>
                <div class="input-wrap <?= isset($errors['password']) ? 'is-invalid' : '' ?>">
                    <input class="input has-toggle" type="password" id="password" name="password" placeholder="••••••••">
                    <button type="button" class="toggle-password" aria-label="Show password" title="Show password" tabindex="-1">
                        <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="eye-closed" style="display:none;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </button>
                </div>
                <?php if (isset($errors['password'])): ?><div class="field-error" style="color:#ffb4a3;"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in to admin</button>
        </form>

        <p style="text-align:center;margin-top:1.5rem;">
            <a href="<?= e(url('/login')) ?>" style="color:rgba(255,255,255,.6);font-size:.9rem;">← Public user login</a>
        </p>
    </div>
</div>
