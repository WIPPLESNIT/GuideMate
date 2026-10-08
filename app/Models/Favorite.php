<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Favorite
{
    /**
     * Toggle a favorite. Returns true if now favorited, false if removed.
     */
    public static function toggle(int $userId, int $listingId): bool
    {
        if (self::exists($userId, $listingId)) {
            Database::run('DELETE FROM favorites WHERE user_id = ? AND listing_id = ?', [$userId, $listingId]);
            return false;
        }
        Database::run('INSERT INTO favorites (user_id, listing_id) VALUES (?, ?)', [$userId, $listingId]);
        return true;
    }

    public static function exists(int $userId, int $listingId): bool
    {
        return Database::first(
            'SELECT id FROM favorites WHERE user_id = ? AND listing_id = ?',
            [$userId, $listingId]
        ) !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(int $userId): array
    {
        return Database::all(
            'SELECT l.*, c.name AS category_name, c.slug AS category_slug,
                    COALESCE(AVG(r.rating),0) AS avg_rating, COUNT(DISTINCT r.id) AS review_count
             FROM favorites f
             JOIN listings l ON l.id = f.listing_id
             JOIN categories c ON c.id = l.category_id
             LEFT JOIN reviews r ON r.listing_id = l.id
             WHERE f.user_id = ? AND c.slug NOT IN ("restaurants")
             GROUP BY l.id
             ORDER BY f.created_at DESC',
            [$userId]
        );
    }

    /**
     * Set of listing ids favorited by the user (for quick lookup in lists).
     *
     * @return array<int, int>
     */
    public static function idsForUser(int $userId): array
    {
        $rows = Database::all('SELECT listing_id FROM favorites WHERE user_id = ?', [$userId]);
        return array_map(static fn ($r) => (int) $r['listing_id'], $rows);
    }
}
