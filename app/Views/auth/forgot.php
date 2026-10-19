<?php
/**
 * @var array<string,string> $errors
 * @var string|null $sentEmail
 * @var string|null $devResetUrl
 */
$errors = $errors ?? [];
$sentEmail = $sentEmail ?? null;
$devResetUrl = $devResetUrl ?? null;
?>
<a href="<?= e(url('/login')) ?>" class="brand a-rise" style="margin-bottom:2rem;display:inline-flex;">
    <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
</a>

<?php if ($sentEmail): ?>
    <div style="text-align:center;padding:0.5rem 0;">
        <div style="font-size:3rem;margin-bottom:1rem;line-height:1;">✅</div>
        <h1 style="margin-bottom:0.5rem;">Check your email</h1>
        <p class="auth-switch" style="margin-bottom:1.5rem;line-height:1.6;">
            We've sent a password reset link to <br><strong><?= e($sentEmail) ?></strong>.<br>
            Please check your inbox and follow the instructions.
        </p>

        <?php if ($devResetUrl): ?>
            <div style="margin:1.5rem 0;padding:1.25rem;background:rgba(5,150,105,0.08);border:1px dashed #059669;border-radius:12px;text-align:center;">
                <p style="font-size:0.85rem;color:#059669;font-weight:700;margin:0 0 0.6rem 0;">
                    ⚡ Direct Reset Link (App &amp; Dev Mode)
                </p>
                <p style="font-size:0.8rem;color:rgba(0, 0, 0, 0.7);margin:0 0 0.85rem 0;">
                    Since SMTP is currently in local logging mode, click below to set your new password immediately:
                </p>
                <a href="<?= e($devResetUrl) ?>" class="btn btn-primary btn-block" style="text-decoration:none;display:inline-block;">
                    Open reset link now &rarr;
                </a>
            </div>
        <?php endif; ?>

        <a href="<?= e(url('/login')) ?>" class="btn btn-secondary btn-block" style="text-decoration:none;margin-top:1rem;display:block;">Back to log in</a>
        <p class="hint" style="margin-top:1.25rem;">
            Didn't receive it? <a href="<?= e(url('/forgot-password')) ?>">Send again</a>
        </p>
    </div>
<?php else: ?>
    <h1>Forgot password</h1>
    <p class="auth-switch">Enter your email and we will send a reset link.</p>

    <form id="forgotForm" method="post" action="<?= e(url('/forgot-password')) ?>" novalidate>
        <?= csrf_field() ?>

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
            });
        }

        form.addEventListener('submit', function (e) {
            var val = String(emailInput ? emailInput.value : '').trim();
            if (val === '') {
                e.preventDefault();
                setFieldError(emailInput, emailErr, 'Email is required.');
                if (emailInput) {
                    try { emailInput.focus({ preventScroll: true }); } catch (err) { emailInput.focus(); }
                }
                return;
            }

            if (!emailRegex.test(val)) {
                e.preventDefault();
                setFieldError(emailInput, emailErr, 'Please enter a valid email address.');
                if (emailInput) {
                    try { emailInput.focus({ preventScroll: true }); } catch (err) { emailInput.focus(); }
                }
                return;
            }

            clearFieldError(emailInput, emailErr);
        });
    })();
    </script>
<?php endif; ?>
