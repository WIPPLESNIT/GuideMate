<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A guide-plotted schedule of sessions for a listing (date + start time).
 * Replaces the old "block a date" approach with positive scheduling.
 */
final class ListingSchedule
{
    public static function add(int $listingId, string $date, ?string $time, ?int $capacity, ?string $note): int
    {
        return Database::insert(
            'INSERT INTO listing_schedules (listing_id, schedule_date, start_time, capacity, note)
             VALUES (?, ?, ?, ?, ?)',
            [$listingId, $date, $time !== '' ? $time : null, $capacity, $note !== '' ? $note : null]
        );
    }

    public static function remove(int $listingId, int $id): void
    {
        Database::run(
            'DELETE FROM listing_schedules WHERE id = ? AND listing_id = ?',
            [$id, $listingId]
        );
    }

    /**
     * Upcoming schedule entries for a listing, soonest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forListing(int $listingId): array
    {
        return Database::all(
            'SELECT * FROM listing_schedules
             WHERE listing_id = ? AND schedule_date >= CURDATE()
             ORDER BY schedule_date, start_time',
            [$listingId]
        );
    }
}
