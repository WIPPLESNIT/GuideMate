<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Account suspension appeals submitted by suspended users.
 */
final class AccountAppeal
{
    private static bool $ready = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public static function ensureTable(): void
    {
        if (self::$ready) {
            return;
        }

        Database::run(
            'CREATE TABLE IF NOT EXISTS account_appeals (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                name VARCHAR(120) NOT NULL DEFAULT \'\',
                email VARCHAR(190) NOT NULL DEFAULT \'\',
                suspension_reason VARCHAR(255) NOT NULL DEFAULT \'\',
                appeal_message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                admin_notes TEXT NULL,
                reviewed_at TIMESTAMP NULL DEFAULT NULL,
                reviewed_by INT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_appeals_user (user_id),
                KEY idx_appeals_status (status),
                KEY idx_appeals_created (created_at),
                CONSTRAINT fk_appeals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$ready = true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        self::ensureTable();
        return Database::insert(
            'INSERT INTO account_appeals (user_id, name, email, suspension_reason, appeal_message, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'] ?? null,
                (string) ($data['name'] ?? ''),
                (string) ($data['email'] ?? ''),
                (string) ($data['suspension_reason'] ?? ''),
                (string) ($data['appeal_message'] ?? ''),
                (string) ($data['status'] ?? self::STATUS_PENDING),
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        self::ensureTable();
        return Database::all('SELECT * FROM account_appeals ORDER BY created_at DESC');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureTable();
        return Database::first('SELECT * FROM account_appeals WHERE id = ?', [$id]);
    }

    /**
     * Find latest appeal for a user.
     *
     * @return array<string, mixed>|null
     */
    public static function latestForUser(int $userId): ?array
    {
        self::ensureTable();
        return Database::first(
            'SELECT * FROM account_appeals WHERE user_id = ? ORDER BY created_at DESC LIMIT 1',
            [$userId]
        );
    }

    /**
     * Find pending appeal for a user.
     *
     * @return array<string, mixed>|null
     */
    public static function pendingForUser(int $userId): ?array
    {
        self::ensureTable();
        return Database::first(
            'SELECT * FROM account_appeals WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1',
            [$userId, self::STATUS_PENDING]
        );
    }

    /**
     * Find pending appeal for an email.
     *
     * @return array<string, mixed>|null
     */
    public static function pendingForEmail(string $email): ?array
    {
        self::ensureTable();
        return Database::first(
            'SELECT * FROM account_appeals WHERE email = ? AND status = ? ORDER BY created_at DESC LIMIT 1',
            [$email, self::STATUS_PENDING]
        );
    }

    public static function pendingCount(): int
    {
        self::ensureTable();
        $row = Database::first('SELECT COUNT(*) AS c FROM account_appeals WHERE status = ?', [self::STATUS_PENDING]);
        return (int) ($row['c'] ?? 0);
    }

    public static function updateStatus(int $id, string $status, ?string $adminNotes = null, ?int $adminId = null): void
    {
        self::ensureTable();
        Database::run(
            'UPDATE account_appeals SET status = ?, admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?',
            [$status, $adminNotes, $adminId, $id]
        );
    }
}
