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
                <input class="input" type="password" id="password" name="password" placeholder="••••••••">
                <?php if (isset($errors['password'])): ?><div class="field-error" style="color:#ffb4a3;"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in to admin</button>
        </form>

        <p style="text-align:center;margin-top:1.5rem;">
            <a href="<?= e(url('/login')) ?>" style="color:rgba(255,255,255,.6);font-size:.9rem;">← Public user login</a>
        </p>
    </div>
</div>
