<?php
/** @var array<string,string> $errors */
$errors = $errors ?? [];
?>
<a href="<?= e(url('/login')) ?>" class="brand a-rise" style="margin-bottom:2rem;display:inline-flex;">
    <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
</a>
<h1>Forgot password</h1>
<p class="auth-switch">Enter your email and we will send a reset link.</p>

<form id="forgotForm" method="post" action="<?= e(url('/forgot-password')) ?>" novalidate>
    <?= csrf_field() ?>

    <div id="formError" class="form-alert" role="alert" style="display:none;"></div>

    <div class="field-row">
        <label for="email">Email <span class="req" style="color:#ef4444;">*</span></label>
        <div class="input-wrap <?= isset($errors['email']) ? 'is-invalid' : '' ?>">
            <span class="input-ic">✉️</span>
            <input class="input has-ic <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e(old('email')) ?>" placeholder="you@example.com" autofocus>
        </div>
        <div class="field-error" id="err-email"><?= isset($errors['email']) ? e($errors['email']) : '' ?></div>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
</form>
<p class="hint" style="margin-top:1rem;"><a href="<?= e(url('/login')) ?>">Back to log in</a></p>

<script>
(function () {
    var form = document.getElementById('forgotForm');
    var box = document.getElementById('formError');
    if (!form) { return; }

    var emailInput = document.getElementById('email');
    var emailErr = document.getElementById('err-email');
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

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

    if (emailInput) {
        emailInput.addEventListener('input', function () {
            clearFieldError(emailInput, emailErr);
            if (box) { box.style.display = 'none'; }
        });
    }

    form.addEventListener('submit', function (e) {
        var val = String(emailInput ? emailInput.value : '').trim();
        if (val === '') {
            e.preventDefault();
            setFieldError(emailInput, emailErr, 'Email is required.');
            if (box) {
                box.textContent = 'Please enter your email, and try again.';
                box.style.display = 'flex';
            }
            if (emailInput) {
                try { emailInput.focus({ preventScroll: true }); } catch (err) { emailInput.focus(); }
            }
            return;
        }

        if (!emailRegex.test(val)) {
            e.preventDefault();
            setFieldError(emailInput, emailErr, 'Please enter a valid email address.');
            if (box) {
                box.textContent = 'Please enter a valid email address.';
                box.style.display = 'flex';
            }
            if (emailInput) {
                try { emailInput.focus({ preventScroll: true }); } catch (err) { emailInput.focus(); }
            }
            return;
        }

        clearFieldError(emailInput, emailErr);
        if (box) { box.style.display = 'none'; }
    });
})();
</script>
