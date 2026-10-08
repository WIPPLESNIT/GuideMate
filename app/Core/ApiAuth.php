<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Bearer-token authentication for the mobile API.
 */
final class ApiAuth
{
    public static function issue(int $userId): string
    {
        self::ensureTable();
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
        Database::run(
            'INSERT INTO api_tokens (user_id, token, expires_at) VALUES (?, ?, ?)',
            [$userId, $token, $expires]
        );
        return $token;
    }

    public static function bearer(): ?string
    {
        $header = self::authorizationHeader();
        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches) === 1) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Read the Authorization header from every place Apache/FastCGI may expose
     * it. Under mod_rewrite it can arrive as REDIRECT_HTTP_AUTHORIZATION, and on
     * some SAPIs it is only visible via apache_request_headers().
     */
    private static function authorizationHeader(): string
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }
        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        $token = self::bearer();
        if ($token === null) {
            return null;
        }
        self::ensureTable();
        $row = Database::first(
            'SELECT user_id FROM api_tokens WHERE token = ? AND expires_at > NOW()',
            [$token]
        );
        if ($row === null) {
            return null;
        }
        $user = User::find((int) $row['user_id']);
        if ($user === null || (int) ($user['is_active'] ?? 1) === 0) {
            return null;
        }

        // Refresh presence once per request so chat can show online/offline.
        static $touched = [];
        $uid = (int) $user['id'];
        if (!isset($touched[$uid])) {
            $touched[$uid] = true;
            User::touchLastSeen($uid);
        }

        return $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    public static function revoke(?string $token = null): void
    {
        $token ??= self::bearer();
        if ($token === null) {
            return;
        }
        self::ensureTable();
        Database::run('DELETE FROM api_tokens WHERE token = ?', [$token]);
    }

    private static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        Database::run(
            'CREATE TABLE IF NOT EXISTS api_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_api_tokens_token (token),
                KEY idx_api_tokens_user (user_id),
                CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $ready = true;
    }
}
