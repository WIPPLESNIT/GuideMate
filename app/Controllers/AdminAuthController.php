<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AdminAuth;
use App\Core\Controller;
use App\Core\Totp;
use App\Models\User;

final class AdminAuthController extends Controller
{
    public function showLogin(): void
    {
        if (AdminAuth::check() && AdminAuth::twoFactorSatisfied()) {
            redirect('/admin');
        }
        $this->view('admin/login', ['title' => 'Admin Sign In', 'errors' => errors()], 'blank');
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $email = (string) $this->input('email', '');
        $password = (string) $this->input('password', '');

        $errors = $this->requireFields(['email', 'password']);
        if ($errors !== []) {
            flash_keep_old($errors, ['email' => $email], '/admin/login');
        }

        $existing = \App\Models\User::findByEmail($email);
        if ($existing !== null && (int) ($existing['is_active'] ?? 1) === 0) {
            flash_keep_old(
                ['email' => 'This account is inactive. Please contact admin to activate it.'],
                ['email' => $email],
                '/admin/login'
            );
        }

        if (!AdminAuth::attempt($email, $password)) {
            flash_keep_old(
                ['email' => 'Invalid admin credentials, or this account is not an administrator.'],
                ['email' => $email],
                '/admin/login'
            );
        }

        AdminAuth::clearTwoFactorOk();
        $admin = AdminAuth::user();
        if ($admin !== null && (int) ($admin['admin_totp_enabled'] ?? 0) === 1) {
            flash('info', 'Enter your two-factor authentication code.');
            redirect('/admin/2fa');
        }

        flash('success', 'Welcome to the admin panel.');
        redirect('/admin');
    }

    public function showTwoFa(): void
    {
        if (!AdminAuth::check()) {
            redirect('/admin/login');
        }
        if (AdminAuth::twoFactorSatisfied()) {
            redirect('/admin');
        }
        $this->view('admin/twofa', ['title' => 'Two-Factor Auth', 'errors' => errors()], 'blank');
    }

    public function verifyTwoFa(): void
    {
        $this->verifyCsrf();
        if (!AdminAuth::check()) {
            redirect('/admin/login');
        }

        $admin = AdminAuth::user();
        $code = (string) $this->input('code', '');
        $secret = (string) ($admin['admin_totp_secret'] ?? '');
        if ($secret === '' || !Totp::verify($secret, $code)) {
            flash_keep_old(['code' => 'Invalid authentication code.'], [], '/admin/2fa');
        }

        AdminAuth::markTwoFactorOk();
        flash('success', 'Two-factor verification complete.');
        redirect('/admin');
    }

    public function showSecurity(): void
    {
        $admin = AdminAuth::user();
        if ($admin === null) {
            redirect('/admin/login');
        }
        $this->view('admin/security', [
            'title' => 'Admin Security',
            'admin' => $admin,
            'setupSecret' => $_SESSION['_admin_totp_setup'] ?? null,
            'errors' => errors(),
        ], 'admin');
    }

    public function setupTwoFa(): void
    {
        $this->verifyCsrf();
        $admin = AdminAuth::user();
        if ($admin === null) {
            redirect('/admin/login');
        }
        $secret = Totp::generateSecret();
        User::setTotpSecret((int) $admin['id'], $secret);
        $_SESSION['_admin_totp_setup'] = $secret;
        AdminAuth::clearTwoFactorOk();
        AdminAuth::forgetCache();
        flash('info', 'Scan the secret in your authenticator app, then confirm with a code.');
        redirect('/admin/security');
    }

    public function enableTwoFa(): void
    {
        $this->verifyCsrf();
        $admin = AdminAuth::user();
        if ($admin === null) {
            redirect('/admin/login');
        }
        $secret = (string) ($admin['admin_totp_secret'] ?? $_SESSION['_admin_totp_setup'] ?? '');
        $code = (string) $this->input('code', '');
        if ($secret === '' || !Totp::verify($secret, $code)) {
            flash_keep_old(['code' => 'Invalid code. Try again.'], [], '/admin/security');
        }
        User::enableTotp((int) $admin['id']);
        unset($_SESSION['_admin_totp_setup']);
        AdminAuth::markTwoFactorOk();
        AdminAuth::forgetCache();
        flash('success', 'Two-factor authentication enabled.');
        redirect('/admin/security');
    }

    public function disableTwoFa(): void
    {
        $this->verifyCsrf();
        $admin = AdminAuth::user();
        if ($admin === null) {
            redirect('/admin/login');
        }
        $code = (string) $this->input('code', '');
        $secret = (string) ($admin['admin_totp_secret'] ?? '');
        if ($secret === '' || !Totp::verify($secret, $code)) {
            flash_keep_old(['code' => 'Invalid code.'], [], '/admin/security');
        }
        User::disableTotp((int) $admin['id']);
        AdminAuth::clearTwoFactorOk();
        unset($_SESSION['_admin_totp_setup']);
        AdminAuth::forgetCache();
        flash('info', 'Two-factor authentication disabled.');
        redirect('/admin/security');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        AdminAuth::logout();
        flash('info', 'You have signed out of the admin panel.');
        redirect('/admin/login');
    }
}
