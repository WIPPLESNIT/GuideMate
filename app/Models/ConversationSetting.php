<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ConversationSetting
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forPair(int $userId, int $partnerId): ?array
    {
        return Database::first(
            'SELECT * FROM conversation_settings WHERE user_id = ? AND partner_id = ?',
            [$userId, $partnerId]
        );
    }

    public static function setPinned(int $userId, int $partnerId, bool $pinned): void
    {
        self::upsert($userId, $partnerId, ['is_pinned' => $pinned ? 1 : 0]);
    }

    public static function setArchived(int $userId, int $partnerId, bool $archived): void
    {
        self::upsert($userId, $partnerId, ['is_archived' => $archived ? 1 : 0]);
    }

    public static function remove(int $userId, int $partnerId): void
    {
        Database::run(
            'DELETE FROM conversation_settings WHERE user_id = ? AND partner_id = ?',
            [$userId, $partnerId]
        );
    }

    /**
     * @param array<string, int> $fields
     */
    private static function upsert(int $userId, int $partnerId, array $fields): void
    {
        $existing = self::forPair($userId, $partnerId);
        if ($existing === null) {
            Database::insert(
                'INSERT INTO conversation_settings (user_id, partner_id, is_pinned, is_archived) VALUES (?, ?, ?, ?)',
                [
                    $userId,
                    $partnerId,
                    $fields['is_pinned'] ?? 0,
                    $fields['is_archived'] ?? 0,
                ]
            );
            return;
        }

        $sets = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $userId;
        $params[] = $partnerId;
        Database::run(
            'UPDATE conversation_settings SET ' . implode(', ', $sets) . ' WHERE user_id = ? AND partner_id = ?',
            $params
        );
    }
}
