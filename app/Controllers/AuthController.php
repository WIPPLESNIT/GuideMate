<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\HandlesGuideDocuments;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\AccountAppeal;
use App\Models\GuideDocument;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\NotificationService;
use RuntimeException;

final class AuthController extends Controller
{
    use HandlesGuideDocuments;

    public function showLogin(): void
    {
        $suspendedUser = $_SESSION['suspended_user'] ?? null;
        unset($_SESSION['suspended_user']);

        $this->view('auth/login', [
            'title' => 'Log in',
            'errors' => errors(),
            // Carry a safe "return to" path so users land back where they were
            // (e.g. a listing page) after signing in.
            'redirect' => self::safeRedirect((string) $this->input('redirect', '')),
            'suspendedUser' => $suspendedUser,
        ], 'auth');
    }

    /**
     * Only allow same-site relative paths as post-login redirects, to avoid
     * open-redirect attacks. Returns '' when the value is unsafe/empty.
     */
    private static function safeRedirect(string $path): string
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '';
        }
        return $path;
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $email = (string) $this->input('email', '');
        $password = (string) $this->input('password', '');

        $errors = $this->requireFields(['email', 'password']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($errors !== []) {
            flash_keep_old($errors, ['email' => $email], '/login');
        }

        // Admins must use their own separate portal.
        $existing = User::findByEmail($email);
        if ($existing !== null && $existing['role'] === 'admin') {
            flash('info', 'Administrators sign in through the admin portal.');
            redirect('/admin/login');
        }

        // Suspended / inactive account check: show violation popup & appeal option
        if ($existing !== null && (int) $existing['is_active'] === 0) {
            $reason = !empty($existing['suspension_reason'])
                ? (string) $existing['suspension_reason']
                : 'Violation of Community Guidelines';

            $_SESSION['suspended_user'] = [
                'id' => (int) $existing['id'],
                'name' => (string) ($existing['name'] ?? ''),
                'email' => (string) ($existing['email'] ?? $email),
                'reason' => $reason,
                'suspended_at' => $existing['suspended_at'] ?? null,
                'has_pending_appeal' => AccountAppeal::pendingForUser((int) $existing['id']) !== null,
            ];
            flash_keep_old([], ['email' => $email], '/login');
        }

        // The web portal is for tour guides. Tourists use the mobile app.
        if ($existing !== null && $existing['role'] === 'tourist') {
            flash_keep_old(
                ['email' => 'You are a tourist, you cannot log in your account here. Please use the GuideMate mobile app.'],
                ['email' => $email],
                '/login'
            );
        }

        if (!Auth::attempt($email, $password)) {
            flash_keep_old(['email' => 'Those credentials do not match our records.'], ['email' => $email], '/login');
        }

        $userId = (int) $existing['id'];
        $firstName = explode(' ', (string) ($existing['name'] ?? ''))[0];
        $isFirstLogin = User::recordLogin($userId);
        $_SESSION['is_first_login'] = $isFirstLogin;

        if ($isFirstLogin) {
            flash('success', 'Welcome to GuideMate' . ($firstName !== '' ? ", {$firstName}" : '') . '!');
        } else {
            flash('success', 'Welcome back' . ($firstName !== '' ? ", {$firstName}" : '') . '!');
        }

        $redirect = self::safeRedirect((string) $this->input('redirect', ''));
        redirect($redirect !== '' ? $redirect : '/dashboard');
    }

    public function showRegister(): void
    {
        $this->view('auth/register', [
            'title' => '',
            'errors' => errors(),
            'role' => $this->input('role', 'guide'),
        ], 'auth');
    }

    public function register(): void
    {
        $this->verifyCsrf();
        $name = (string) $this->input('name', '');
        $email = (string) $this->input('email', '');
        $password = (string) $this->input('password', '');
        $confirm = (string) $this->input('password_confirm', '');
        // The web portal is for service providers only (tourists use the mobile
        // app). Accept the three provider roles; anything else defaults to guide.
        $requestedRole = (string) $this->input('role', 'guide');
        $role = User::isProviderRole($requestedRole) ? $requestedRole : 'guide';

        $errors = $this->requireFields(['name', 'email', 'password', 'password_confirm']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif ($email !== '' && User::emailExists($email)) {
            $errors['email'] = 'That email is already registered.';
        }

        if ($password !== '') {
            $passwordError = $this->validatePasswordPolicy($password);
            if ($passwordError !== null) {
                $errors['password'] = $passwordError;
            } elseif ($confirm !== '' && $password !== $confirm) {
                $errors['password_confirm'] = 'Passwords do not match.';
            }
        }

        // Providers (guides, rental & hotel partners) submit verification
        // documents up front so an admin can review them.
        if (User::isProviderRole($role)) {
            $errors += $this->guideDocErrors();
        }

        if (!$this->input('accept_terms')) {
            $errors['accept_terms'] = 'You must agree to the Terms of Service and Privacy Policy.';
        }

        if ($errors !== []) {
            flash_keep_old($errors, ['name' => $name, 'email' => $email, 'role' => $role, 'credential_type' => (string) $this->input('credential_type', '')], '/register');
        }

        $id = User::create($name, $email, $password, $role);

        if (User::isProviderRole($role)) {
            try {
                $this->storeGuideDocs($id);
            } catch (RuntimeException $e) {
                // Roll back so the applicant can try again cleanly.
                GuideDocument::deleteForUser($id);
                User::delete($id);
                flash_keep_old(
                    ['valid_id' => 'We could not save your documents: ' . $e->getMessage()],
                    ['name' => $name, 'email' => $email, 'role' => $role],
                    '/register'
                );
            }
        }

        Auth::login(User::find($id) ?? []);
        User::recordLogin($id);
        $_SESSION['is_first_login'] = true;

        NotificationService::welcome($email, explode(' ', $name)[0]);

        if (User::isProviderRole($role)) {
            $roleLabel = User::PROVIDER_LABELS[$role] ?? 'provider';
            flash('success', 'Thanks, ' . explode(' ', $name)[0] . '! Your ' . strtolower($roleLabel) . ' application and documents were submitted and are now pending admin review.');
        } else {
            flash('success', 'Welcome to GuideMate, ' . explode(' ', $name)[0] . '!');
        }
        redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        unset($_SESSION['is_first_login']);
        flash('success', 'You have been logged out successfully.');
        redirect('/');
    }

    public function showForgotPassword(): void
    {
        $this->view('auth/forgot', ['title' => 'Forgot password', 'errors' => errors()], 'auth');
    }

    public function sendResetLink(): void
    {
        $this->verifyCsrf();
        $email = strtolower(trim((string) $this->input('email', '')));
        $errors = $this->requireFields(['email']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($errors !== []) {
            flash_keep_old($errors, ['email' => $email], '/forgot-password');
        }

        $user = User::findByEmail($email);
        if ($user !== null && $user['role'] !== 'admin') {
            $token = PasswordReset::create($email);
            NotificationService::passwordReset($email, $token);
        }

        flash('success', 'If that email is registered, we sent a password reset link.');
        redirect('/login');
    }

    public function showResetPassword(string $token): void
    {
        if (PasswordReset::findValid($token) === null) {
            flash('error', 'This reset link is invalid or has expired.');
            redirect('/forgot-password');
        }
        $this->view('auth/reset', ['title' => 'Reset password', 'token' => $token, 'errors' => errors()], 'auth');
    }

    public function resetPassword(string $token): void
    {
        $this->verifyCsrf();
        $row = PasswordReset::findValid($token);
        if ($row === null) {
            flash('error', 'This reset link is invalid or has expired.');
            redirect('/forgot-password');
        }

        $password = (string) $this->input('password', '');
        $confirm = (string) $this->input('password_confirm', '');
        $errors = $this->requireFields(['password', 'password_confirm']);
        if ($password !== '') {
            $policy = $this->validatePasswordPolicy($password);
            if ($policy !== null) {
                $errors['password'] = $policy;
            } elseif ($confirm !== '' && $password !== $confirm) {
                $errors['password_confirm'] = 'Passwords do not match.';
            }
        }
        if ($errors !== []) {
            flash_keep_old($errors, [], '/reset-password/' . $token);
        }

        $user = User::findByEmail((string) $row['email']);
        if ($user === null) {
            flash('error', 'Account not found.');
            redirect('/login');
        }

        User::updatePassword((int) $user['id'], $password);
        PasswordReset::delete((string) $row['email']);
        flash('success', 'Password updated. You can log in now.');
        redirect('/login');
    }

    /**
     * Enforce the password policy:
     *   - 8 to 12 characters
     *   - at least one uppercase letter
     *   - at least one number
     *   - at least one special character
     *
     * Returns an error message, or null when the password is valid.
     */
    private function validatePasswordPolicy(string $password): ?string
    {
        $length = strlen($password);
        if ($length < 8 || $length > 12) {
            return 'Password must be 8 to 12 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must include at least one uppercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must include at least one number.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must include at least one special character.';
        }
        return null;
    }

    /**
     * Handle an account suspension appeal submission.
     */
    public function submitAppeal(): void
    {
        $this->verifyCsrf();

        $email = trim((string) $this->input('email', ''));
        $message = trim((string) $this->input('appeal_message', ''));
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(422);
                echo json_encode(['error' => 'A valid email address is required.']);
                exit;
            }
            flash_keep_old(['email' => 'A valid email address is required.'], ['email' => $email], '/login');
        }

        if (mb_strlen($message) < 5) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(422);
                echo json_encode(['error' => 'Please provide at least 5 characters explaining your appeal.']);
                exit;
            }
            flash_keep_old(['appeal' => 'Please provide at least 5 characters explaining your appeal.'], ['email' => $email], '/login');
        }

        $user = User::findByEmail($email);
        $userId = $user !== null ? (int) $user['id'] : null;
        $name = $user !== null ? (string) ($user['name'] ?? '') : '';
        $suspensionReason = $user !== null ? (string) ($user['suspension_reason'] ?? 'Violation of Community Guidelines') : '';

        // Prevent repeated appeals if one is already pending
        if ($userId !== null && AccountAppeal::pendingForUser($userId) !== null) {
            $msg = 'You already have an appeal under review by our administration team.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(429);
                echo json_encode(['error' => $msg]);
                exit;
            }
            flash('info', $msg);
            redirect('/login');
        }

        AccountAppeal::create([
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'suspension_reason' => $suspensionReason,
            'appeal_message' => $message,
            'status' => AccountAppeal::STATUS_PENDING,
        ]);

        // Clear the suspended user session prompt
        unset($_SESSION['suspended_user']);

        $successMsg = 'Your appeal has been submitted successfully. Our administration team will review your account.';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => $successMsg]);
            exit;
        }

        flash('success', $successMsg);
        redirect('/login');
    }
}
