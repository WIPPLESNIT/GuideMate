<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class HotelRoom
{
    /** Allowed room availability statuses */
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_FULLY_BOOKED = 'fully_booked';

    public static function forListing(int $listingId, bool $onlyAvailable = false): array
    {
        $sql = "SELECT r.*, l.title as hotel_title, l.slug as hotel_slug, l.area as hotel_area
                FROM hotel_rooms r
                JOIN listings l ON l.id = r.listing_id
                WHERE r.listing_id = ?";
        if ($onlyAvailable) {
            $sql .= " AND r.status = 'available'";
        }
        $sql .= " ORDER BY r.price_per_night ASC, r.id ASC";

        return Database::all($sql, [$listingId]);
    }

    public static function forOwner(int $userId): array
    {
        return Database::all(
            "SELECT r.*, l.title as hotel_title, l.slug as hotel_slug, l.area as hotel_area
             FROM hotel_rooms r
             JOIN listings l ON l.id = r.listing_id
             WHERE l.user_id = ?
             ORDER BY l.id ASC, r.price_per_night ASC",
            [$userId]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            "SELECT r.*, l.title as hotel_title, l.slug as hotel_slug, l.user_id as owner_id, l.area as hotel_area
             FROM hotel_rooms r
             JOIN listings l ON l.id = r.listing_id
             WHERE r.id = ?",
            [$id]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert(
            "INSERT INTO hotel_rooms 
                (listing_id, name, description, room_size, bed_type, max_guests, price_per_night, deposit_percentage, total_units, available_units, status, amenities, image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                (int) $data['listing_id'],
                trim((string) $data['name']),
                $data['description'] ?? null,
                $data['room_size'] ?? '32 m²',
                $data['bed_type'] ?? '1 King Bed',
                max(1, (int) ($data['max_guests'] ?? 2)),
                max(0.0, (float) ($data['price_per_night'] ?? 0)),
                max(5, min(100, (int) ($data['deposit_percentage'] ?? 20))),
                max(1, (int) ($data['total_units'] ?? 1)),
                max(0, (int) ($data['available_units'] ?? 1)),
                $data['status'] ?? self::STATUS_AVAILABLE,
                $data['amenities'] ?? null,
                $data['image'] ?? null,
            ]
        );
    }

    public static function update(int $id, array $data): bool
    {
        Database::run(
            "UPDATE hotel_rooms 
             SET name = ?, description = ?, room_size = ?, bed_type = ?, max_guests = ?,
                 price_per_night = ?, deposit_percentage = ?, status = ?, amenities = ?, image = COALESCE(?, image)
             WHERE id = ?",
            [
                trim((string) $data['name']),
                $data['description'] ?? null,
                $data['room_size'] ?? '32 m²',
                $data['bed_type'] ?? '1 King Bed',
                max(1, (int) ($data['max_guests'] ?? 2)),
                max(0.0, (float) ($data['price_per_night'] ?? 0)),
                max(5, min(100, (int) ($data['deposit_percentage'] ?? 20))),
                $data['status'] ?? self::STATUS_AVAILABLE,
                $data['amenities'] ?? null,
                $data['image'] ?? null,
                $id,
            ]
        );
        return true;
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $allowed = [self::STATUS_AVAILABLE, self::STATUS_UNAVAILABLE, self::STATUS_BLOCKED, self::STATUS_FULLY_BOOKED];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        Database::run(
            "UPDATE hotel_rooms SET status = ? WHERE id = ?",
            [$status, $id]
        );
        return true;
    }

    public static function delete(int $id): bool
    {
        Database::run("DELETE FROM hotel_rooms WHERE id = ?", [$id]);
        return true;
    }

    public static function deleteForListing(int $listingId): bool
    {
        Database::run("DELETE FROM hotel_rooms WHERE listing_id = ?", [$listingId]);
        return true;
    }

    /**
     * Check if a room is available for a specified date range.
     * Prevents overlapping active reservations on the same room.
     */
    public static function isAvailableForDates(int $roomId, string $checkIn, string $checkOut): bool
    {
        $room = self::find($roomId);
        if ($room === null || $room['status'] !== self::STATUS_AVAILABLE) {
            return false;
        }

        // Check if there are overlapping active bookings (pending, deposit_paid, confirmed, checked_in)
        // Overlap condition: NOT (existing.check_out <= new.check_in OR existing.check_in >= new.check_out)
        $conflicts = Database::first(
            "SELECT COUNT(*) as cnt 
             FROM bookings 
             WHERE room_id = ?
               AND status NOT IN ('cancelled', 'refunded')
               AND (hotel_status IS NULL OR hotel_status NOT IN ('cancelled', 'no_show'))
               AND (check_in_date < ? AND check_out_date > ?)",
            [$roomId, $checkOut, $checkIn]
        );

        $bookedCount = (int) ($conflicts['cnt'] ?? 0);
        $totalUnits = max(1, (int) ($room['total_units'] ?? 1));

        return $bookedCount < $totalUnits;
    }

    /**
     * Calculate deposit amount and remaining balance based on room rules.
     */
    public static function calculateBreakdown(float $nightlyRate, int $nights, int $depositPercentage = 20): array
    {
        $nights = max(1, $nights);
        $totalStay = round($nightlyRate * $nights, 2);
        $depositPct = max(5, min(100, $depositPercentage));
        $depositAmount = round(($totalStay * $depositPct) / 100, 2);
        $balanceAmount = round($totalStay - $depositAmount, 2);

        return [
            'nightly_rate' => $nightlyRate,
            'nights' => $nights,
            'total_amount' => $totalStay,
            'deposit_percentage' => $depositPct,
            'deposit_amount' => $depositAmount,
            'balance_amount' => $balanceAmount,
        ];
    }
}
