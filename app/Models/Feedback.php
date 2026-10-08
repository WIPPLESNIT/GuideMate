<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * In-app feedback left by tourists about the GuideMate mobile app.
 */
final class Feedback
{
    private static bool $ready = false;

    /** Allowed feedback categories. */
    public const CATEGORIES = ['general', 'bug', 'feature', 'praise'];

    /** Allowed moderation statuses. */
    public const STATUSES = ['new', 'reviewed', 'archived'];

    public static function ensureTable(): void
    {
        if (self::$ready) {
            return;
        }
        Database::run(
            'CREATE TABLE IF NOT EXISTS app_feedback (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                name VARCHAR(120) NOT NULL DEFAULT \'\',
                email VARCHAR(190) NOT NULL DEFAULT \'\',
                rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
                category VARCHAR(40) NOT NULL DEFAULT \'general\',
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'new\',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_app_feedback_created (created_at),
                KEY idx_app_feedback_status (status),
                CONSTRAINT fk_app_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
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
            'INSERT INTO app_feedback (user_id, name, email, rating, category, message)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'] ?? null,
                (string) ($data['name'] ?? ''),
                (string) ($data['email'] ?? ''),
                (int) ($data['rating'] ?? 0),
                (string) ($data['category'] ?? 'general'),
                (string) ($data['message'] ?? ''),
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        self::ensureTable();
        return Database::all('SELECT * FROM app_feedback ORDER BY created_at DESC');
    }

    /**
     * Feedback submitted by a specific account (for the mobile "My feedback"
     * screen so tourists can see whether it was reviewed).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(int $userId): array
    {
        self::ensureTable();
        return Database::all(
            'SELECT * FROM app_feedback WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }

    public static function markStatus(int $id, string $status): void
    {
        self::ensureTable();
        Database::run('UPDATE app_feedback SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function countNew(): int
    {
        self::ensureTable();
        $row = Database::first('SELECT COUNT(*) AS c FROM app_feedback WHERE status = \'new\'');
        return (int) ($row['c'] ?? 0);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'reviewed' => 'Reviewed',
            'archived' => 'Archived',
            default => 'New',
        };
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'bug' => 'Bug report',
            'feature' => 'Feature request',
            'praise' => 'Praise',
            default => 'General',
        };
    }
}
