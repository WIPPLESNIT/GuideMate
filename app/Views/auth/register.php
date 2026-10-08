<?php
/** @var array<string,string> $errors @var string $role */

use App\Models\GuideDocument;
use App\Models\User;

$errors = $errors ?? [];
// The web portal is for service providers. The applicant picks which kind.
$selectedRole = old('role') !== '' ? old('role') : ($role ?? 'guide');
if (!User::isProviderRole((string) $selectedRole)) {
    $selectedRole = 'guide';
}
$providerLabels = User::PROVIDER_LABELS;
?>
<a href="<?= e(url('/')) ?>" class="brand a-rise" style="margin-bottom:1.5rem;display:inline-flex;animation-delay:.05s;">
    <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
</a>
<h1 class="a-rise" style="animation-delay:.12s;">Create your account</h1>
<p class="auth-switch a-rise" style="animation-delay:.18s;">Already have one? <a href="<?= e(url('/login')) ?>">Log in</a></p>

<form id="registerForm" method="post" action="<?= e(url('/register')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <div id="formError" class="form-alert a-rise" role="alert" style="display:none;animation-delay:.2s;"></div>

    <div class="field-row a-rise" style="animation-delay:.24s;">
        <label for="role">I want to join as</label>
        <div class="input-wrap">
            <span class="input-ic">🧭</span>
            <select class="input has-ic" id="role" name="role">
                <?php foreach ($providerLabels as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $selectedRole === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="hint mb-0" id="roleHint"></p>
    </div>

    <div class="field-row a-rise" style="animation-delay:.3s;">
        <label for="name">Full name <span class="req" style="color:#ef4444;">*</span></label>
        <div class="input-wrap <?= isset($errors['name']) ? 'is-invalid' : '' ?>">
            <span class="input-ic">👤</span>
            <input class="input has-ic <?= isset($errors['name']) ? 'is-invalid' : '' ?>" type="text" id="name" name="name" value="<?= e(old('name')) ?>" placeholder="Juan Dela Cruz">
        </div>
        <div class="field-error" id="err-name"><?= isset($errors['name']) ? e($errors['name']) : '' ?></div>
    </div>
    <div class="field-row a-rise" style="animation-delay:.36s;">
        <label for="email">Email <span class="req" style="color:#ef4444;">*</span></label>
        <div class="input-wrap <?= isset($errors['email']) ? 'is-invalid' : '' ?>">
            <span class="input-ic">✉️</span>
            <input class="input has-ic <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e(old('email')) ?>" placeholder="you@example.com">
        </div>
        <div class="field-error" id="err-email"><?= isset($errors['email']) ? e($errors['email']) : '' ?></div>
    </div>
    <div class="form-grid-2 a-rise" style="animation-delay:.42s;">
        <div class="field-row">
            <label for="password">Password <span class="req" style="color:#ef4444;">*</span></label>
            <div class="input-wrap <?= isset($errors['password']) ? 'is-invalid' : '' ?>">
                <span class="input-ic">🔒</span>
                <input class="input has-ic <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password" placeholder="8–12 characters" minlength="8" maxlength="12">
            </div>
            <div class="field-error" id="err-password"><?= isset($errors['password']) ? e($errors['password']) : '' ?></div>
        </div>
        <div class="field-row">
            <label for="password_confirm">Confirm <span class="req" style="color:#ef4444;">*</span></label>
            <div class="input-wrap <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>">
                <span class="input-ic">🔒</span>
                <input class="input has-ic <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" type="password" id="password_confirm" name="password_confirm" placeholder="Repeat password" minlength="8" maxlength="12">
            </div>
            <div class="field-error" id="err-password_confirm"><?= isset($errors['password_confirm']) ? e($errors['password_confirm']) : '' ?></div>
        </div>
    </div>
    <p class="hint a-rise" style="margin-top:-.4rem;margin-bottom:1.1rem;animation-delay:.48s;">
        Password must be <strong>8–12 characters</strong> and include an uppercase letter, a number, and a special character.
    </p>

    <!-- Provider verification — every provider role is reviewed by an admin -->
    <div id="guideDocs" class="guide-docs a-rise" style="animation-delay:.54s;">
        <div class="guide-docs-head">
            <span class="guide-docs-badge">🛡️ Partner verification</span>
            <p class="hint mb-0">To keep travelers safe, every partner is reviewed by our admin team before going live. Upload clear photos or PDFs (max 5MB each). Accepted: ID, business permit, license, accreditation, certificates, employment verification, or association membership.</p>
        </div>

        <div class="field-row">
            <label for="valid_id">Valid government ID <span class="req" style="color:#ef4444;">*</span></label>
            <input class="file-input <?= isset($errors['valid_id']) ? 'is-invalid' : '' ?>" type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png,.webp">
            <div class="field-error" id="err-valid_id"><?= isset($errors['valid_id']) ? e($errors['valid_id']) : '' ?></div>
        </div>

        <div class="field-row">
            <label for="credential_type">Credential type <span class="req" style="color:#ef4444;">*</span></label>
            <select class="input <?= isset($errors['credential_type']) ? 'is-invalid' : '' ?>" id="credential_type" name="credential_type">
                <option value="">Select credential type</option>
                <?php foreach (GuideDocument::TYPES as $key => $label): ?>
                    <?php if ($key === 'valid_id' || $key === 'other') { continue; } ?>
                    <option value="<?= e($key) ?>" <?= old('credential_type') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="field-error" id="err-credential_type"><?= isset($errors['credential_type']) ? e($errors['credential_type']) : '' ?></div>
        </div>
        <div class="field-row">
            <label for="credential">Credential document <span class="req" style="color:#ef4444;">*</span></label>
            <input class="file-input <?= isset($errors['credential']) ? 'is-invalid' : '' ?>" type="file" id="credential" name="credential" accept=".pdf,.jpg,.jpeg,.png,.webp">
            <div class="field-error" id="err-credential"><?= isset($errors['credential']) ? e($errors['credential']) : '' ?></div>
        </div>

        <div class="field-row">
            <label for="extra_docs">Additional documents <span class="hint">(optional)</span></label>
            <input class="file-input <?= isset($errors['extra_docs']) ? 'is-invalid' : '' ?>" type="file" id="extra_docs" name="extra_docs[]" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple>
            <div class="field-error" id="err-extra_docs"><?= isset($errors['extra_docs']) ? e($errors['extra_docs']) : '' ?></div>
        </div>
    </div>

    <div class="field-row a-rise" style="animation-delay:.58s;">
        <label class="checkbox">
            <input type="checkbox" id="accept_terms" name="accept_terms" value="1" required <?= old('accept_terms') ? 'checked' : '' ?> class="<?= isset($errors['accept_terms']) ? 'is-invalid' : '' ?>">
            I agree to the <a href="<?= e(url('/terms')) ?>" target="_blank">Terms of Service</a> and <a href="<?= e(url('/privacy')) ?>" target="_blank">Privacy Policy</a> <span class="req" style="color:#ef4444;">*</span>
        </label>
        <div class="field-error" id="err-accept_terms"><?= isset($errors['accept_terms']) ? e($errors['accept_terms']) : '' ?></div>
    </div>

    <button class="btn btn-primary btn-block btn-lg a-rise" style="animation-delay:.6s;" type="submit">Create account</button>
</form>

<script>
(function () {
    var hints = {
        guide: 'Lead tours and manage your own experience listings and bookings.',
        rental_admin: 'List rental vehicles and handle incoming rental requests.',
        hotel_admin: 'List your hotel / accommodation and reply to guest inquiries.'
    };
    var select = document.getElementById('role');
    var hint = document.getElementById('roleHint');
    function sync() { if (select && hint) { hint.textContent = hints[select.value] || ''; } }
    if (select) { select.addEventListener('change', sync); sync(); }

    var form = document.getElementById('registerForm');
    var box = document.getElementById('formError');
    if (!form) { return; }

    var nameInput = document.getElementById('name');
    var emailInput = document.getElementById('email');
    var passwordInput = document.getElementById('password');
    var confirmInput = document.getElementById('password_confirm');
    var validIdInput = document.getElementById('valid_id');
    var credTypeInput = document.getElementById('credential_type');
    var credentialInput = document.getElementById('credential');
    var termsInput = document.getElementById('accept_terms');

    var nameErr = document.getElementById('err-name');
    var emailErr = document.getElementById('err-email');
    var passwordErr = document.getElementById('err-password');
    var confirmErr = document.getElementById('err-password_confirm');
    var validIdErr = document.getElementById('err-valid_id');
    var credTypeErr = document.getElementById('err-credential_type');
    var credentialErr = document.getElementById('err-credential');
    var termsErr = document.getElementById('err-accept_terms');

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

    if (nameInput) {
        nameInput.addEventListener('input', function () {
            clearFieldError(nameInput, nameErr);
            if (box) { box.style.display = 'none'; }
        });
    }
    if (emailInput) {
        emailInput.addEventListener('input', function () {
            clearFieldError(emailInput, emailErr);
            if (box) { box.style.display = 'none'; }
        });
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
    if (validIdInput) {
        validIdInput.addEventListener('change', function () {
            clearFieldError(validIdInput, validIdErr);
            if (box) { box.style.display = 'none'; }
        });
    }
    if (credTypeInput) {
        credTypeInput.addEventListener('change', function () {
            clearFieldError(credTypeInput, credTypeErr);
            if (box) { box.style.display = 'none'; }
        });
    }
    if (credentialInput) {
        credentialInput.addEventListener('change', function () {
            clearFieldError(credentialInput, credentialErr);
            if (box) { box.style.display = 'none'; }
        });
    }
    if (termsInput) {
        termsInput.addEventListener('change', function () {
            clearFieldError(termsInput, termsErr);
            if (box) { box.style.display = 'none'; }
        });
    }

    form.addEventListener('submit', function (e) {
        var hasError = false;
        var firstInvalid = null;

        // Full name
        var nameVal = nameInput ? nameInput.value.trim() : '';
        if (nameVal === '') {
            setFieldError(nameInput, nameErr, 'Full Name is required.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = nameInput; }
        } else {
            clearFieldError(nameInput, nameErr);
        }

        // Email
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

        // Password
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
        } else if (!/[^A-Za-z0-9]/.test(passVal)) {
            setFieldError(passwordInput, passwordErr, 'Password must include at least one special character.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = passwordInput; }
        } else {
            clearFieldError(passwordInput, passwordErr);
        }

        // Confirm Password
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

        // Valid ID file
        if (validIdInput && (!validIdInput.files || validIdInput.files.length === 0)) {
            setFieldError(validIdInput, validIdErr, 'Valid government ID is required.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = validIdInput; }
        } else {
            clearFieldError(validIdInput, validIdErr);
        }

        // Credential type
        var credTypeVal = credTypeInput ? credTypeInput.value.trim() : '';
        if (credTypeVal === '') {
            setFieldError(credTypeInput, credTypeErr, 'Credential type is required.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = credTypeInput; }
        } else {
            clearFieldError(credTypeInput, credTypeErr);
        }

        // Credential document file
        if (credentialInput && (!credentialInput.files || credentialInput.files.length === 0)) {
            setFieldError(credentialInput, credentialErr, 'Credential document is required.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = credentialInput; }
        } else {
            clearFieldError(credentialInput, credentialErr);
        }

        // Terms
        if (termsInput && !termsInput.checked) {
            setFieldError(termsInput, termsErr, 'You must agree to the Terms of Service and Privacy Policy.');
            hasError = true;
            if (!firstInvalid) { firstInvalid = termsInput; }
        } else {
            clearFieldError(termsInput, termsErr);
        }

        if (hasError) {
            e.preventDefault();
            if (box) {
                box.textContent = 'Please fix the errors below and try again.';
                box.style.display = 'flex';
                box.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
