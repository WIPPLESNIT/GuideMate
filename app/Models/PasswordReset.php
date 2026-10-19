<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PasswordReset
{
    /** Expiration duration in minutes (15–30 minutes) */
    public const DEFAULT_EXPIRY_MINUTES = 30;

    /**
     * Generate a cryptographically secure, single-use token and store its SHA-256 hash.
     *
     * @param string $email
     * @param int $minutes Expiration window in minutes (default: 30)
     * @return string Raw plaintext token to be embedded in the email link
     */
    public static function create(string $email, int $minutes = self::DEFAULT_EXPIRY_MINUTES): string
    {
        $token = bin2hex(random_bytes(32));
        Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);
        Database::run(
            'INSERT INTO password_resets (email, token, expires_at, created_at, used_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW(), NULL)',
            [$email, hash('sha256', $token), $minutes]
        );
        return $token;
    }

    /**
     * Find a valid, unexpired, and unused password reset record for the given raw token.
     *
     * @param string $token Plaintext token
     * @return array<string, mixed>|null
     */
    public static function findValid(string $token): ?array
    {
        $hash = hash('sha256', $token);
        return Database::first(
            'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL',
            [$hash]
        );
    }

    /**
     * Find any password reset record by token, regardless of expiration or usage status.
     * Used to distinguish between already-used tokens, expired tokens, and invalid tokens.
     *
     * @param string $token Plaintext token
     * @return array<string, mixed>|null
     */
    public static function findByToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        return Database::first(
            'SELECT * FROM password_resets WHERE token = ?',
            [$hash]
        );
    }

    /**
     * Mark a reset token as used so it cannot be reused.
     *
     * @param string $token Plaintext token
     */
    public static function markUsed(string $token): void
    {
        $hash = hash('sha256', $token);
        Database::run(
            'UPDATE password_resets SET used_at = NOW() WHERE token = ?',
            [$hash]
        );
    }

    /**
     * Delete any reset records for an email address.
     *
     * @param string $email
     */
    public static function delete(string $email): void
    {
        Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);
    }
}
