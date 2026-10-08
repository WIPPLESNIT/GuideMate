<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Review
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forListing(int $listingId): array
    {
        return Database::all(
            'SELECT r.*, u.name AS user_name, u.avatar AS user_avatar
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.listing_id = ? ORDER BY r.created_at DESC',
            [$listingId]
        );
    }

    /**
     * Rating summary: average + count + breakdown by star.
     *
     * @return array{avg: float, count: int, breakdown: array<int, int>}
     */
    public static function summary(int $listingId): array
    {
        $rows = Database::all(
            'SELECT rating, COUNT(*) AS c FROM reviews WHERE listing_id = ? GROUP BY rating',
            [$listingId]
        );
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $total = 0;
        $sum = 0;
        foreach ($rows as $row) {
            $star = (int) $row['rating'];
            $count = (int) $row['c'];
            $breakdown[$star] = $count;
            $total += $count;
            $sum += $star * $count;
        }
        return [
            'avg' => $total > 0 ? round($sum / $total, 1) : 0.0,
            'count' => $total,
            'breakdown' => $breakdown,
        ];
    }

    public static function create(int $listingId, int $userId, int $rating, string $title, string $comment, ?string $visitedOn): int
    {
        return Database::insert(
            'INSERT INTO reviews (listing_id, user_id, rating, title, comment, visited_on)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$listingId, $userId, $rating, $title, $comment, $visitedOn]
        );
    }

    public static function userHasReviewed(int $listingId, int $userId): bool
    {
        return Database::first(
            'SELECT id FROM reviews WHERE listing_id = ? AND user_id = ?',
            [$listingId, $userId]
        ) !== null;
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM reviews WHERE id = ?', [$id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 10): array
    {
        return Database::all(
            'SELECT r.*, u.name AS user_name, l.title AS listing_title, l.slug AS listing_slug
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             JOIN listings l ON l.id = r.listing_id
             ORDER BY r.created_at DESC LIMIT ' . (int) $limit
        );
    }

    /**
     * Tourist reviews left on listings owned by this provider
     * (guide, hotel partner, or rental partner).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forOwner(int $ownerId): array
    {
        return Database::all(
            'SELECT r.*, u.name AS user_name, u.avatar AS user_avatar,
                    l.title AS listing_title, l.slug AS listing_slug
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             JOIN listings l ON l.id = r.listing_id
             WHERE l.user_id = ?
             ORDER BY r.created_at DESC',
            [$ownerId]
        );
    }
}
