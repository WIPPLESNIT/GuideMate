<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class GuideAvailability
{
    public static function block(int $listingId, string $date, ?string $note = null): void
    {
        Database::run(
            'INSERT IGNORE INTO guide_availability (listing_id, blocked_date, note) VALUES (?, ?, ?)',
            [$listingId, $date, $note]
        );
    }

    public static function unblock(int $listingId, string $date): void
    {
        Database::run(
            'DELETE FROM guide_availability WHERE listing_id = ? AND blocked_date = ?',
            [$listingId, $date]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forListing(int $listingId): array
    {
        return Database::all(
            'SELECT * FROM guide_availability WHERE listing_id = ? AND blocked_date >= CURDATE() ORDER BY blocked_date',
            [$listingId]
        );
    }

    public static function isBlocked(int $listingId, string $date): bool
    {
        return Database::first(
            'SELECT id FROM guide_availability WHERE listing_id = ? AND blocked_date = ?',
            [$listingId, $date]
        ) !== null;
    }

    /**
     * @return array<int, string>
     */
    public static function blockedDates(int $listingId): array
    {
        $rows = Database::all(
            'SELECT blocked_date FROM guide_availability WHERE listing_id = ? AND blocked_date >= CURDATE() ORDER BY blocked_date',
            [$listingId]
        );
        return array_map(static fn($r) => (string) $r['blocked_date'], $rows);
    }

    /**
     * @return array<int, string>
     */
    public static function blockedDatesForGuide(int $guideId): array
    {
        $rows = Database::all(
            'SELECT DISTINCT ga.blocked_date
             FROM guide_availability ga
             JOIN listings l ON l.id = ga.listing_id
             WHERE l.user_id = ? AND ga.blocked_date >= CURDATE()
             ORDER BY ga.blocked_date',
            [$guideId]
        );
        return array_map(static fn($r) => (string) $r['blocked_date'], $rows);
    }

    public static function isGuideBlocked(int $guideId, string $date): bool
    {
        return Database::first(
            'SELECT ga.id FROM guide_availability ga
             JOIN listings l ON l.id = ga.listing_id
             WHERE l.user_id = ? AND ga.blocked_date = ?',
            [$guideId, $date]
        ) !== null;
    }
}
