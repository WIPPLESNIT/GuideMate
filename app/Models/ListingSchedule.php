<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A guide-plotted schedule of sessions for a listing (date + start time + end time).
 * Replaces the old "block a date" approach with positive scheduling.
 */
final class ListingSchedule
{
    public static function add(
        int $listingId,
        string $date,
        ?string $time,
        mixed $endTime = null,
        mixed $capacity = null,
        ?string $note = null
    ): int {
        // Backwards compatibility if called with (listingId, date, time, capacity, note)
        if (is_int($endTime) && $note === null) {
            $note = is_string($capacity) ? $capacity : null;
            $capacity = $endTime;
            $endTime = null;
        }

        return Database::insert(
            'INSERT INTO listing_schedules (listing_id, schedule_date, start_time, end_time, capacity, note)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $listingId,
                $date,
                $time !== '' && $time !== null ? $time : null,
                is_string($endTime) && $endTime !== '' ? $endTime : null,
                is_int($capacity) ? $capacity : (is_numeric($capacity) ? (int) $capacity : null),
                $note !== '' && $note !== null ? $note : null,
            ]
        );
    }

    public static function update(
        int $listingId,
        int $id,
        string $date,
        ?string $time,
        ?string $endTime = null,
        ?int $capacity = null,
        ?string $note = null
    ): void {
        Database::run(
            'UPDATE listing_schedules
             SET schedule_date = ?, start_time = ?, end_time = ?, capacity = ?, note = ?
             WHERE id = ? AND listing_id = ?',
            [
                $date,
                $time !== '' && $time !== null ? $time : null,
                $endTime !== '' && $endTime !== null ? $endTime : null,
                $capacity,
                $note !== '' && $note !== null ? $note : null,
                $id,
                $listingId,
            ]
        );
    }

    public static function find(int $listingId, int $id): ?array
    {
        return Database::first(
            'SELECT * FROM listing_schedules WHERE id = ? AND listing_id = ?',
            [$id, $listingId]
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
             ORDER BY schedule_date, start_time, end_time',
            [$listingId]
        );
    }
}
