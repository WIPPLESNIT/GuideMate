<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PasswordReset
{
    public static function create(string $email): string
    {
        $token = bin2hex(random_bytes(32));
        Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);
        Database::run(
            'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
            [$email, hash('sha256', $token)]
        );
        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findValid(string $token): ?array
    {
        $hash = hash('sha256', $token);
        return Database::first(
            'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()',
            [$hash]
        );
    }

    public static function delete(string $email): void
    {
        Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);
    }
}
