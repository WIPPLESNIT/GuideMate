<?php
/** @var array<string,string> $errors @var string $redirect */
$errors = $errors ?? [];
$redirect = $redirect ?? '';
?>
<a href="<?= e(url('/')) ?>" class="brand a-rise" style="margin-bottom:2rem;display:inline-flex;animation-delay:.05s;">
    <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
</a>
<h1 class="a-rise" style="animation-delay:.12s;">Welcome back</h1>
<p class="auth-switch a-rise" style="animation-delay:.18s;">New here? <a href="<?= e(url('/register')) ?>">Create an account</a></p>

<form id="loginForm" method="post" action="<?= e(url('/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($redirect !== ''): ?><input type="hidden" name="redirect" value="<?= e($redirect) ?>"><?php endif; ?>

    <div class="field-row a-rise" style="animation-delay:.24s;">
        <label for="email">Email <span class="req" style="color:#ef4444;">*</span></label>
        <div class="input-wrap <?= isset($errors['email']) ? 'is-invalid' : '' ?>">
            <span class="input-ic">✉️</span>
            <input class="input has-ic <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e(old('email')) ?>" placeholder="you@example.com" autofocus>
        </div>
        <div class="field-error" id="err-email"><?= isset($errors['email']) ? e($errors['email']) : '' ?></div>
    </div>
    <div class="field-row a-rise" style="animation-delay:.3s;">
        <label for="password">Password <span class="req" style="color:#ef4444;">*</span></label>
        <div class="input-wrap <?= isset($errors['password']) ? 'is-invalid' : '' ?>">
            <span class="input-ic">🔒</span>
            <input class="input has-ic has-toggle <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password" placeholder="••••••••">
            <button type="button" class="toggle-password" aria-label="Show password" title="Show password" tabindex="-1">
                <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <svg class="eye-closed" style="display:none;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
            </button>
        </div>
        <div class="field-error" id="err-password"><?= isset($errors['password']) ? e($errors['password']) : '' ?></div>
    </div>
    <div class="auth-row a-rise" style="animation-delay:.36s;">
        <label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <a href="<?= e(url('/forgot-password')) ?>" class="hint">Forgot password?</a>
    </div>
    <button class="btn btn-primary btn-block btn-lg a-rise" style="animation-delay:.42s;" type="submit">Log in</button>
</form>

<p class="hint a-rise" style="text-align:center;margin-top:1.5rem;animation-delay:.5s;">
    Want to host tours? <a href="<?= e(url('/register?role=guide')) ?>">Sign up here</a>
</p>

<script>
(function () {
    var form = document.getElementById('loginForm');

    // Toggle password visibility (hidden dots <-> plain text)
    if (typeof window.initPasswordToggles === 'function') {
        window.initPasswordToggles();
    } else {
        (window.initPasswordToggles = function () {
            document.querySelectorAll('.toggle-password').forEach(function (btn) {
                if (btn.dataset.toggleBound === '1') { return; }
                btn.dataset.toggleBound = '1';
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var wrap = btn.closest('.input-wrap') || btn.parentElement;
                    if (!wrap) { return; }
                    var input = wrap.querySelector('input');
                    if (!input) { return; }
                    var wasPassword = (input.type === 'password' || input.getAttribute('type') === 'password');
                    var nextType = wasPassword ? 'text' : 'password';
                    input.type = nextType;
                    input.setAttribute('type', nextType);
                    btn.setAttribute('aria-label', wasPassword ? 'Hide password' : 'Show password');
                    btn.setAttribute('title', wasPassword ? 'Hide password' : 'Show password');
                    var eyeOpen = btn.querySelector('.eye-open');
                    var eyeClosed = btn.querySelector('.eye-closed');
                    if (eyeOpen && eyeClosed) {
                        eyeOpen.style.display = wasPassword ? 'none' : 'block';
                        eyeClosed.style.display = wasPassword ? 'block' : 'none';
                    }
                    try { input.focus(); } catch (err) {}
                });
            });
        })();
    }

    if (!form) { return; }

    var emailInput = document.getElementById('email');
    var passwordInput = document.getElementById('password');
    var emailErr = document.getElementById('err-email');
    var passwordErr = document.getElementById('err-password');

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
        });
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            clearFieldError(passwordInput, passwordErr);
        });
    }

    form.addEventListener('submit', function (e) {
        var hasError = false;
        var firstInvalid = null;

        var emailVal = emailInput ? emailInput.value.trim() : '';
        if (emailVal === '') {
            setFieldError(emailInput, emailErr, 'Email is required.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = emailInput; }
        } else if (!emailRegex.test(emailVal)) {
            setFieldError(emailInput, emailErr, 'Please enter a valid email address.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = emailInput; }
        } else {
            clearFieldError(emailInput, emailErr);
        }

        var passVal = passwordInput ? passwordInput.value : '';
        if (!passVal || passVal.trim() === '') {
            setFieldError(passwordInput, passwordErr, 'Password is required.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = passwordInput; }
        } else {
            clearFieldError(passwordInput, passwordErr);
        }

        if (hasError) {
            e.preventDefault();
            if (firstInvalid) {
                try { firstInvalid.focus({ preventScroll: true }); } catch (err) { firstInvalid.focus(); }
            }
        }
    });
})();
</script>
