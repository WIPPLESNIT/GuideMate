<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Authentication for ADMINS only — a completely separate portal from the
 * public user system (Auth).
 *
 * State is stored under the session key "admin_id", which is independent of
 * the public "user_id". This means signing into the admin panel does NOT log
 * in (or out) any tourist/guide using the same browser, and never touches any
 * user account. The CSRF token is shared session-wide.
 */
final class AdminAuth
{
    private const SESSION_KEY = 'admin_id';

    private static ?array $cachedAdmin = null;

    /**
     * Attempt an admin login. ONLY accounts with the "admin" role and an
     * active status may authenticate here.
     */
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if ($user === null || !password_verify($password, $user['password'])) {
            return false;
        }
        if ($user['role'] !== 'admin' || (int) $user['is_active'] === 0) {
            return false;
        }
        self::login($user);
        return true;
    }

    /**
     * @param array<string, mixed> $admin
     */
    public static function login(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int) $admin['id'];
        self::$cachedAdmin = $admin;
    }

    /**
     * Log out only the admin — leaves any public user session intact.
     */
    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY], $_SESSION['_admin_2fa_ok'], $_SESSION['_admin_totp_setup']);
        self::$cachedAdmin = null;
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$cachedAdmin !== null) {
            return self::$cachedAdmin;
        }
        $admin = User::find((int) $_SESSION[self::SESSION_KEY]);
        // Safety: if the account is no longer an admin, drop the session.
        if ($admin === null || $admin['role'] !== 'admin') {
            self::logout();
            return null;
        }
        self::$cachedAdmin = $admin;
        return $admin;
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION[self::SESSION_KEY] : null;
    }

    public static function forgetCache(): void
    {
        self::$cachedAdmin = null;
    }

    public static function twoFactorSatisfied(): bool
    {
        $admin = self::user();
        if ($admin === null) {
            return false;
        }
        if ((int) ($admin['admin_totp_enabled'] ?? 0) !== 1) {
            return true;
        }
        return !empty($_SESSION['_admin_2fa_ok']);
    }

    public static function markTwoFactorOk(): void
    {
        $_SESSION['_admin_2fa_ok'] = true;
    }

    public static function clearTwoFactorOk(): void
    {
        unset($_SESSION['_admin_2fa_ok']);
    }
}
