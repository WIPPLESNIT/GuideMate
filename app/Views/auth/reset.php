<?php
/** @var string $token @var array<string,string> $errors */
$errors = $errors ?? [];
?>
<a href="<?= e(url('/login')) ?>" class="brand a-rise" style="margin-bottom:2rem;display:inline-flex;">
    <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
</a>
<h1>Reset password</h1>
<p class="auth-switch">Choose a new password for your account.</p>

<form id="resetForm" method="post" action="<?= e(url('/reset-password/' . $token)) ?>" novalidate>
    <?= csrf_field() ?>

    <div id="formError" class="form-alert" role="alert" style="display:none;"></div>

    <div class="field-row">
        <label for="password">New password <span class="req" style="color:#ef4444;">*</span></label>
        <div class="input-wrap <?= isset($errors['password']) ? 'is-invalid' : '' ?>">
            <span class="input-ic">🔒</span>
            <input class="input has-ic <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password" placeholder="8–12 characters" minlength="8" maxlength="12" autofocus>
        </div>
        <div class="field-error" id="err-password"><?= isset($errors['password']) ? e($errors['password']) : '' ?></div>
    </div>
    <div class="field-row">
        <label for="password_confirm">Confirm password <span class="req" style="color:#ef4444;">*</span></label>
        <div class="input-wrap <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>">
            <span class="input-ic">🔒</span>
            <input class="input has-ic <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" type="password" id="password_confirm" name="password_confirm" placeholder="Repeat password" minlength="8" maxlength="12">
        </div>
        <div class="field-error" id="err-password_confirm"><?= isset($errors['password_confirm']) ? e($errors['password_confirm']) : '' ?></div>
    </div>
    <p class="hint" style="margin-top:-.4rem;margin-bottom:1.1rem;">
        Password must be <strong>8–12 characters</strong> and include an uppercase letter, a number, and a special character.
    </p>
    <button class="btn btn-primary btn-block" type="submit">Update password</button>
</form>

<script>
(function () {
    var form = document.getElementById('resetForm');
    var box = document.getElementById('formError');
    if (!form) { return; }

    var passwordInput = document.getElementById('password');
    var confirmInput = document.getElementById('password_confirm');
    var passwordErr = document.getElementById('err-password');
    var confirmErr = document.getElementById('err-password_confirm');

    function setFieldError(input, errEl, message) {
        if (!input) { return; }
        var wrap = input.closest('.input-wrap') || input;
        wrap.classList.add('is-invalid');
        input.classList.add('is-invalid');
        if (errEl) {
            errEl.textContent = message;
        }
    }

    function clearFieldError(input, errEl) {
        if (!input) { return; }
        var wrap = input.closest('.input-wrap') || input;
        wrap.classList.remove('is-invalid');
        input.classList.remove('is-invalid');
        if (errEl) {
            errEl.textContent = '';
        }
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            clearFieldError(passwordInput, passwordErr);
            if (box) { box.style.display = 'none'; }
        });
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', function () {
            clearFieldError(confirmInput, confirmErr);
            if (box) { box.style.display = 'none'; }
        });
    }

    form.addEventListener('submit', function (e) {
        var hasError = false;
        var firstInvalid = null;

        var passVal = passwordInput ? passwordInput.value : '';
        if (!passVal || passVal.trim() === '') {
            setFieldError(passwordInput, passwordErr, 'Password is required.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = passwordInput; }
        } else if (passVal.length < 8 || passVal.length > 12) {
            setFieldError(passwordInput, passwordErr, 'Password must be 8 to 12 characters long.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = passwordInput; }
        } else if (!/[A-Z]/.test(passVal)) {
            setFieldError(passwordInput, passwordErr, 'Password must include at least one uppercase letter.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = passwordInput; }
        } else if (!/[0-9]/.test(passVal)) {
            setFieldError(passwordInput, passwordErr, 'Password must include at least one number.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = passwordInput; }
        } else if (![^A-Za-z0-9]/.test(passVal)) {
            setFieldError(passwordInput, passwordErr, 'Password must include at least one special character.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = passwordInput; }
        } else {
            clearFieldError(passwordInput, passwordErr);
        }

        var confVal = confirmInput ? confirmInput.value : '';
        if (!confVal || confVal.trim() === '') {
            setFieldError(confirmInput, confirmErr, 'Confirm Password is required.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = confirmInput; }
        } else if (passVal && passVal !== confVal) {
            setFieldError(confirmInput, confirmErr, 'Passwords do not match.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = confirmInput; }
        } else {
            clearFieldError(confirmInput, confirmErr);
        }

        if (hasError) {
            e.preventDefault();
            if (box) {
                box.textContent = 'Please fix the errors below and try again.';
                box.style.display = 'flex';
            }
            if (firstInvalid) {
                try { firstInvalid.focus({ preventScroll: true }); } catch (err) { firstInvalid.focus(); }
            }
        } else {
            if (box) { box.style.display = 'none'; }
        }
    });
})();
</script>
