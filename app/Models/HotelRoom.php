<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class HotelRoom
{

    /**
     * Return up to 3 distinct photos for a hotel room.
     *
     * @param array<string, mixed> $room
     * @return array<int, string>
     */
    public static function getPhotos(array $room): array
    {
        $photos = [];
        if (!empty($room['images'])) {
            $decoded = json_decode((string) $room['images'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    if (is_string($p) && trim($p) !== '') {
                        $photos[] = trim($p);
                    }
                }
            }
        }
        if ($photos === []) {
            $raw = (string) ($room['image'] ?? '');
            if (str_starts_with(trim($raw), '[') && str_ends_with(trim($raw), ']')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $p) {
                        if (is_string($p) && trim($p) !== '') {
                            $photos[] = trim($p);
                        }
                    }
                }
            } elseif ($raw !== '') {
                foreach (explode(',', $raw) as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $photos[] = $p;
                    }
                }
            }
        }
        return array_values(array_slice(array_unique($photos), 0, 1));
    }

    /**
     * Enrich a hotel room row with full image URLs and photo arrays.
     *
     * @param array<string, mixed> $room
     */
    public static function enrichRoomData(array &$room): void
    {
        $room['photos'] = self::getPhotos($room);
        $primaryPhoto = !empty($room['image']) ? $room['image'] : (!empty($room['photos'][0]) ? $room['photos'][0] : null);
        $room['image_url'] = !empty($primaryPhoto) ? img_src($primaryPhoto) : '';
        $room['photo_urls'] = array_values(array_map(static fn($p) => img_src($p), $room['photos']));
    }

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

        $rows = Database::all($sql, [$listingId]);
        foreach ($rows as &$r) {
            self::enrichRoomData($r);
        }
        unset($r);
        return $rows;
    }

    public static function forOwner(int $userId): array
    {
        $rows = Database::all(
            "SELECT r.*, l.title as hotel_title, l.slug as hotel_slug, l.area as hotel_area
             FROM hotel_rooms r
             JOIN listings l ON l.id = r.listing_id
             WHERE l.user_id = ?
             ORDER BY l.id ASC, r.price_per_night ASC",
            [$userId]
        );
        foreach ($rows as &$r) {
            self::enrichRoomData($r);
        }
        unset($r);
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $row = Database::first(
            "SELECT r.*, l.title as hotel_title, l.slug as hotel_slug, l.user_id as owner_id, l.area as hotel_area
             FROM hotel_rooms r
             JOIN listings l ON l.id = r.listing_id
             WHERE r.id = ?",
            [$id]
        );
        if ($row !== null) {
            self::enrichRoomData($row);
        }
        return $row;
    }

    public static function getLowestRoomPrice(int $listingId): ?float
    {
        $row = Database::first(
            "SELECT MIN(price_per_night) AS min_p FROM hotel_rooms WHERE listing_id = ? AND status = 'available' AND price_per_night > 0",
            [$listingId]
        );
        if ($row && $row['min_p'] !== null && (float) $row['min_p'] > 0) {
            return (float) $row['min_p'];
        }

        $fallback = Database::first(
            "SELECT MIN(price_per_night) AS min_p FROM hotel_rooms WHERE listing_id = ? AND price_per_night > 0",
            [$listingId]
        );
        if ($fallback && $fallback['min_p'] !== null && (float) $fallback['min_p'] > 0) {
            return (float) $fallback['min_p'];
        }

        return null;
    }

    public static function syncHotelStartingPrice(int $listingId): ?float
    {
        $lowest = self::getLowestRoomPrice($listingId);
        Database::run(
            "UPDATE listings SET price = ?, price_unit = 'per night' WHERE id = ?",
            [$lowest ?? 0.0, $listingId]
        );
        return $lowest;
    }

    public static function create(array $data): int
    {
        $photos = [];
        if (isset($data['photos']) && is_array($data['photos'])) {
            $photos = $data['photos'];
        } elseif (isset($data['images'])) {
            if (is_array($data['images'])) {
                $photos = $data['images'];
            } else {
                $decoded = json_decode((string) $data['images'], true);
                $photos = is_array($decoded) ? $decoded : [(string) $data['images']];
            }
        } elseif (!empty($data['image'])) {
            $photos = [(string) $data['image']];
        }

        $cleanPhotos = [];
        foreach ($photos as $p) {
            $p = trim((string) $p);
            if ($p !== '' && !in_array($p, $cleanPhotos, true)) {
                $cleanPhotos[] = $p;
            }
        }
        $cleanPhotos = array_values(array_slice($cleanPhotos, 0, 3));
        $primary = $cleanPhotos[0] ?? (trim((string) ($data['image'] ?? '')) ?: null);
        $imagesJson = $cleanPhotos !== [] ? json_encode($cleanPhotos) : ($primary !== null ? json_encode([$primary]) : null);

        $id = Database::insert(
            "INSERT INTO hotel_rooms 
                (listing_id, name, description, room_size, bed_type, max_guests, price_per_night, deposit_percentage, total_units, available_units, status, amenities, image, images)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                (int) $data['listing_id'],
                trim((string) $data['name']),
                $data['description'] ?? null,
                $data['room_size'] ?? null,
                $data['bed_type'] ?? '1 King Bed',
                max(1, (int) ($data['max_guests'] ?? 2)),
                max(0.0, (float) ($data['price_per_night'] ?? 0)),
                max(5, min(100, (int) ($data['deposit_percentage'] ?? 20))),
                max(1, (int) ($data['total_units'] ?? 1)),
                max(0, (int) ($data['available_units'] ?? 1)),
                $data['status'] ?? self::STATUS_AVAILABLE,
                $data['amenities'] ?? null,
                $primary,
                $imagesJson,
            ]
        );

        self::syncHotelStartingPrice((int) $data['listing_id']);

        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $room = self::find($id);

        $cleanPhotos = null;
        if (isset($data['photos']) && is_array($data['photos'])) {
            $cleanPhotos = [];
            foreach ($data['photos'] as $p) {
                $p = trim((string) $p);
                if ($p !== '' && !in_array($p, $cleanPhotos, true)) {
                    $cleanPhotos[] = $p;
                }
            }
            $cleanPhotos = array_values(array_slice($cleanPhotos, 0, 3));
        } elseif (isset($data['images'])) {
            if (is_array($data['images'])) {
                $cleanPhotos = array_values(array_slice(array_filter(array_map('trim', $data['images'])), 0, 3));
            } else {
                $decoded = json_decode((string) $data['images'], true);
                if (is_array($decoded)) {
                    $cleanPhotos = array_values(array_slice(array_filter(array_map('trim', $decoded)), 0, 3));
                }
            }
        }

        $hasImageKey = array_key_exists('image', $data) || array_key_exists('images', $data) || $cleanPhotos !== null;
        $primary = null;
        $imagesJson = null;
        if ($cleanPhotos !== null) {
            $primary = $cleanPhotos[0] ?? null;
            $imagesJson = $cleanPhotos !== [] ? json_encode($cleanPhotos) : null;
        } elseif (array_key_exists('image', $data)) {
            $primary = trim((string) ($data['image'] ?? '')) ?: null;
            $imagesJson = $primary !== null ? json_encode([$primary]) : null;
        } elseif (array_key_exists('images', $data)) {
            $primary = null;
            $imagesJson = null;
        } else {
            $primary = $room['image'] ?? null;
            $imagesJson = $room['images'] ?? null;
        }

        $imageSql = $hasImageKey ? "image = ?, images = ?" : "image = COALESCE(?, image), images = COALESCE(?, images)";

        Database::run(
            "UPDATE hotel_rooms 
             SET name = ?, description = ?, room_size = ?, bed_type = ?, max_guests = ?,
                 price_per_night = ?, deposit_percentage = ?, status = ?, amenities = ?,
                 {$imageSql}
             WHERE id = ?",
            [
                trim((string) $data['name']),
                $data['description'] ?? null,
                $data['room_size'] ?? ($room['room_size'] ?? null),
                $data['bed_type'] ?? '1 King Bed',
                max(1, (int) ($data['max_guests'] ?? 2)),
                max(0.0, (float) ($data['price_per_night'] ?? 0)),
                max(5, min(100, (int) ($data['deposit_percentage'] ?? 20))),
                $data['status'] ?? self::STATUS_AVAILABLE,
                $data['amenities'] ?? null,
                $primary,
                $imagesJson,
                $id,
            ]
        );

        if ($room) {
            self::syncHotelStartingPrice((int) $room['listing_id']);
        }

        return true;
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $allowed = [self::STATUS_AVAILABLE, self::STATUS_UNAVAILABLE, self::STATUS_BLOCKED, self::STATUS_FULLY_BOOKED];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $room = self::find($id);

        Database::run(
            "UPDATE hotel_rooms SET status = ? WHERE id = ?",
            [$status, $id]
        );

        if ($room) {
            self::syncHotelStartingPrice((int) $room['listing_id']);
        }

        return true;
    }

    public static function delete(int $id): bool
    {
        $room = self::find($id);
        Database::run("DELETE FROM hotel_rooms WHERE id = ?", [$id]);

        if ($room) {
            self::syncHotelStartingPrice((int) $room['listing_id']);
        }

        return true;
    }

    public static function deleteForListing(int $listingId): bool
    {
        Database::run("DELETE FROM hotel_rooms WHERE listing_id = ?", [$listingId]);
        self::syncHotelStartingPrice($listingId);
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
