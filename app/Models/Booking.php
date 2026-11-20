<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Geo;

final class Booking
{
    /**
     * Selectable start times (24h) offered by the mobile booking form. Kept in
     * sync with TIME_SLOTS in app/listing/[slug].tsx so a whole-day lock (guide
     * availability or a legacy time-less booking) can be expressed as slots.
     */
    public const TIME_SLOTS = [
        '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
        '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
        '18:00', '19:00', '20:00',
    ];

    public static function create(
        int $listingId,
        int $userId,
        string $date,
        int $guests,
        float $total,
        string $notes,
        ?string $time = null,
        ?array $dates = null,
        ?string $endTime = null
    ): int {
        $token = bin2hex(random_bytes(16));
        $datesJson = ($dates !== null && $dates !== []) ? json_encode(array_values($dates)) : null;
        $id = Database::insert(
            'INSERT INTO bookings (listing_id, user_id, booking_date, booking_time, booking_end_time, booking_dates, guests, total_amount, notes, verify_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $listingId,
                $userId,
                $date,
                ($time !== null && $time !== '' ? $time : null),
                ($endTime !== null && $endTime !== '' ? $endTime : null),
                $datesJson,
                $guests,
                $total,
                $notes,
                $token,
            ]
        );
        return $id;
    }

    public static function createHotelReservation(
        int $listingId,
        int $roomId,
        int $userId,
        string $checkIn,
        string $checkOut,
        int $nights,
        int $guests,
        float $totalAmount,
        float $depositAmount,
        float $balanceAmount,
        string $notes = "",
        ?string $checkInTime = null,
        ?string $checkOutTime = null
    ): int {
        if ($checkInTime === null || $checkOutTime === null) {
            $listing = Listing::find($listingId);
            $parsed = Listing::parseCheckInOutTimes($listing['duration'] ?? null);
            $checkInTime = $checkInTime ?? $parsed['check_in_time'];
            $checkOutTime = $checkOutTime ?? $parsed['check_out_time'];
        }
        $token = bin2hex(random_bytes(16));
        return Database::insert(
            "INSERT INTO bookings 
                (listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, booking_time, booking_end_time, nights, guests, total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status, notes, verify_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \"deposit_paid\", \"pending\", \"pending\", ?, ?)",
            [
                $listingId,
                $roomId,
                $userId,
                $checkIn,
                $checkIn,
                $checkOut,
                $checkInTime,
                $checkOutTime,
                $nights,
                $guests,
                $totalAmount,
                $depositAmount,
                $balanceAmount,
                $notes,
                $token,
            ]
        );
    }


    public static function ensureVerifyToken(int $id): string
    {
        $row = Database::first('SELECT verify_token FROM bookings WHERE id = ?', [$id]);
        if ($row !== null && !empty($row['verify_token'])) {
            return (string) $row['verify_token'];
        }
        $token = bin2hex(random_bytes(16));
        Database::run('UPDATE bookings SET verify_token = ? WHERE id = ?', [$token, $id]);
        return $token;
    }

    public static function verifyByToken(string $token, int $guideId): bool
    {
        $row = Database::first(
            'SELECT b.id FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE b.verify_token = ? AND l.user_id = ? AND b.status IN ("confirmed","completed")',
            [$token, $guideId]
        );
        if ($row === null) {
            return false;
        }
        Database::run('UPDATE bookings SET verified_at = NOW() WHERE id = ?', [(int) $row['id']]);
        return true;
    }

    public static function applyPromo(int $id, int $promoId, float $discount): void
    {
        $booking = self::find($id);
        if ($booking === null) {
            return;
        }
        $newTotal = max(0, (float) $booking['total_amount'] - $discount);
        Database::run(
            'UPDATE bookings SET promo_code_id = ?, discount_amount = ?, total_amount = ? WHERE id = ?',
            [$promoId, $discount, $newTotal, $id]
        );
    }

    public static function attachPromo(int $id, int $promoId, float $discount): void
    {
        Database::run(
            'UPDATE bookings SET promo_code_id = ?, discount_amount = ? WHERE id = ?',
            [$promoId, $discount, $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT b.*, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.user_id AS guide_id,
                    l.price_unit, l.duration AS listing_duration, c.slug AS category_slug, hr.name AS room_name, hr.price_per_night AS room_price,
                    u.name AS customer_name, u.email AS customer_email
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             LEFT JOIN categories c ON c.id = l.category_id
             JOIN users u ON u.id = b.user_id
             LEFT JOIN hotel_rooms hr ON hr.id = b.room_id
             WHERE b.id = ?',
            [$id]
        );
    }

    /**
     * Bookings made by a tourist.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forCustomer(int $userId): array
    {
        return Database::all(
            'SELECT b.*, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.area,
                    l.duration AS listing_duration, l.address AS listing_address,
                    l.included AS listing_included, l.description AS listing_description,
                    c.slug AS category_slug,
                    u.id AS guide_id, u.name AS guide_name, u.avatar AS guide_avatar, u.phone AS guide_phone,
                    hr.name AS room_name,
                    (SELECT CASE
                        WHEN b.status IN ("cancelled", "refunded") AND (b.package_overall_status = "cancelled" OR b.package_overall_status IS NULL) AND NOT EXISTS (SELECT 1 FROM payments WHERE booking_id = b.id AND status = "paid") THEN "refunded"
                        WHEN EXISTS (SELECT 1 FROM payments WHERE booking_id = b.id AND status = "paid") THEN "paid"
                        ELSE (SELECT status FROM payments WHERE booking_id = b.id ORDER BY id DESC LIMIT 1)
                     END) AS payment_status,
                    (SELECT method FROM payments WHERE booking_id = b.id ORDER BY (CASE WHEN status = "paid" THEN 0 ELSE 1 END), id DESC LIMIT 1) AS payment_method,
                    (SELECT reference FROM payments WHERE booking_id = b.id ORDER BY (CASE WHEN status = "paid" THEN 0 ELSE 1 END), id DESC LIMIT 1) AS payment_reference,
                    (SELECT paid_at FROM payments WHERE booking_id = b.id ORDER BY (CASE WHEN status = "paid" THEN 0 ELSE 1 END), id DESC LIMIT 1) AS payment_paid_at,
                    (SELECT id FROM disputes WHERE booking_id = b.id ORDER BY id DESC LIMIT 1) AS dispute_id,
                    (SELECT status FROM disputes WHERE booking_id = b.id ORDER BY id DESC LIMIT 1) AS dispute_status
             FROM bookings b
             LEFT JOIN listings l ON l.id = b.listing_id
             LEFT JOIN categories c ON c.id = l.category_id
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN hotel_rooms hr ON hr.id = b.room_id
             WHERE b.user_id = ?
               AND (b.notes NOT LIKE ? OR b.notes IS NULL)
               AND NOT EXISTS (
                   SELECT 1 FROM package_booking_items pbi
                   WHERE pbi.sub_booking_id = b.id
                     AND pbi.sub_booking_id != pbi.parent_booking_id
               )
             ORDER BY b.created_at DESC',
            [$userId, '%package_sub_booking%']
        );
    }

    /**
     * Bookings with map coordinates for tourist navigation (regardless of status).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function navigableForCustomer(int $userId): array
    {
        $rows = Database::all(
            'SELECT b.id, b.booking_date, b.booking_time, b.booking_end_time, b.status, b.guests, b.listing_id, b.notes,
                    l.title AS listing_title, l.slug AS listing_slug, l.area, l.address,
                    l.latitude, l.longitude, c.slug AS category_slug
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             LEFT JOIN categories c ON c.id = l.category_id
             WHERE b.user_id = ?
             ORDER BY b.booking_date ASC, b.id ASC',
            [$userId]
        );

        $out = [];
        foreach ($rows as $row) {
            $catSlug = (string) ($row['category_slug'] ?? '');
            $hasAddress = !empty(trim((string) ($row['address'] ?? '')));
            $coords = Geo::forListing($row);

            if ($coords === null) {
                $row['latitude'] = null;
                $row['longitude'] = null;
                $row['has_valid_location'] = false;
                $row['missing_location_message'] = Geo::missingLocationMessage($catSlug, $hasAddress);
            } else {
                $row['latitude'] = $coords['latitude'];
                $row['longitude'] = $coords['longitude'];
                $row['approximate'] = $coords['approximate'];
                $row['has_valid_location'] = true;
                $row['missing_location_message'] = null;
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function navigableForCustomerById(int $userId, int $bookingId): ?array
    {
        $row = Database::first(
            'SELECT b.id, b.booking_date, b.booking_time, b.booking_end_time, b.status, b.guests, b.listing_id, b.notes,
                    l.title AS listing_title, l.slug AS listing_slug, l.area, l.address,
                    l.latitude, l.longitude, c.slug AS category_slug
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             LEFT JOIN categories c ON c.id = l.category_id
             WHERE b.user_id = ? AND b.id = ?',
            [$userId, $bookingId]
        );
        if ($row === null) {
            return null;
        }
        $catSlug = (string) ($row['category_slug'] ?? '');
        $hasAddress = !empty(trim((string) ($row['address'] ?? '')));
        $coords = Geo::forListing($row);

        if ($coords === null) {
            $row['latitude'] = null;
            $row['longitude'] = null;
            $row['has_valid_location'] = false;
            $row['missing_location_message'] = Geo::missingLocationMessage($catSlug, $hasAddress);
        } else {
            $row['latitude'] = $coords['latitude'];
            $row['longitude'] = $coords['longitude'];
            $row['approximate'] = $coords['approximate'];
            $row['has_valid_location'] = true;
            $row['missing_location_message'] = null;
        }
        return $row;
    }

    /**
     * Paid, confirmed bookings with map pins for a browse category (things-to-do, hotels, etc.).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mapListingsForCustomer(int $userId, string $categorySlug): array
    {
        $rows = Database::all(
            'SELECT l.id, l.title, l.slug, l.area, l.address, l.latitude, l.longitude, l.price
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN categories c ON c.id = l.category_id
             JOIN payments p ON p.booking_id = b.id AND p.status = "paid"
             WHERE b.user_id = ? AND c.slug = ?
               AND b.status IN ("confirmed", "completed")
             ORDER BY b.booking_date ASC, b.id ASC',
            [$userId, $categorySlug]
        );

        $out = [];
        foreach ($rows as $row) {
            $coords = Geo::forListing($row);
            if ($coords === null) {
                continue;
            }
            $row['latitude'] = $coords['latitude'];
            $row['longitude'] = $coords['longitude'];
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Active or incomplete booking for a user on this listing.
     * Prevents duplicate bookings while a previous booking is pending, confirmed, or disputed.
     * Once a booking is marked 'completed' or 'cancelled'/'refunded', this returns null
     * allowing the account to rebook the tour.
     */
    public static function getActiveBookingForListing(int $listingId, int $userId): ?array
    {
        return Database::first(
            'SELECT b.id, b.status, b.hotel_status, b.booking_date, b.booking_time, b.booking_end_time, b.guests, b.total_amount
             FROM bookings b
             WHERE b.listing_id = ?
               AND b.user_id = ?
               AND b.status IN ("pending", "confirmed", "disputed")
               AND (b.hotel_status IS NULL OR b.hotel_status IN ("pending", "deposit_paid", "confirmed", "checked_in"))
             ORDER BY b.id DESC
             LIMIT 1',
            [$listingId, $userId]
        );
    }

    public static function hasActiveBookingForListing(int $listingId, int $userId): bool
    {
        return self::getActiveBookingForListing($listingId, $userId) !== null;
    }

    /**
     * Active incomplete package booking for a user.
     * Prevents duplicate package bookings while one is in progress.
     */
    public static function getActiveBookingForPackage(int $packageId, int $userId): ?array
    {
        return Database::first("
            SELECT id, status, package_overall_status, package_booking_ref, booking_date
            FROM bookings
            WHERE user_id = ?
              AND listing_id = ?
              AND (
                  status IN ('pending', 'confirmed')
                  OR package_overall_status IN ('pending', 'partially_confirmed', 'confirmed')
              )
              AND status NOT IN ('completed', 'cancelled', 'refunded')
            ORDER BY id DESC
            LIMIT 1
        ", [$userId, $packageId]);
    }

    public static function hasActiveBookingForPackage(int $packageId, int $userId): bool
    {
        return self::getActiveBookingForPackage($packageId, $userId) !== null;
    }

    /**
     * Checks if a date has an existing booking schedule (active booking, listing schedule, or guide block)
     * for a listing or package.
     */
    public static function hasBookingScheduleOnDate(int $listingId, string $date, ?int $excludeBookingId = null): bool
    {
        return self::isDateBooked($listingId, $date, $excludeBookingId);
    }

    public static function hasActivePaidBooking(int $listingId, int $userId, ?string $date = null): bool
    {
        $sql = 'SELECT b.id FROM bookings b
             JOIN payments p ON p.booking_id = b.id AND p.status = "paid"
             WHERE b.listing_id = ? AND b.user_id = ?
               AND b.status IN ("pending", "confirmed")';
        $params = [$listingId, $userId];
        if ($date !== null) {
            $sql .= ' AND (b.booking_date = ? OR b.booking_dates LIKE ?)';
            $params[] = $date;
            $params[] = '%"' . $date . '"%';
        } else {
            $sql .= ' AND b.booking_date >= CURDATE()';
        }
        return Database::first($sql, $params) !== null;
    }

    /**
     * Bookings received by a guide (across all their listings).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forGuide(int $guideId): array
    {
        return Database::all(
            'SELECT b.*, l.title AS listing_title, l.slug AS listing_slug, hr.name AS room_name,
                    u.name AS customer_name, u.email AS customer_email,
                    (SELECT CASE
                        WHEN b.status IN ("cancelled", "refunded") AND (b.package_overall_status = "cancelled" OR b.package_overall_status IS NULL) AND NOT EXISTS (SELECT 1 FROM payments WHERE booking_id = b.id AND status = "paid") THEN "refunded"
                        WHEN EXISTS (SELECT 1 FROM payments WHERE booking_id = b.id AND status = "paid") THEN "paid"
                        ELSE (SELECT status FROM payments WHERE booking_id = b.id ORDER BY id DESC LIMIT 1)
                     END) AS payment_status
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             LEFT JOIN hotel_rooms hr ON hr.id = b.room_id
             WHERE l.user_id = ?
             ORDER BY FIELD(b.status, "pending", "confirmed", "completed", "disputed", "cancelled", "refunded"), b.created_at DESC',
            [$guideId]
        );
    }

    /**
     * Transactions for a provider's listings — every booking with its payment
     * details. Bookings placed from the mobile app land in the same `bookings`
     * / `payments` tables, so they show up here automatically ("connected").
     *
     * @return array<int, array<string, mixed>>
     */
    public static function transactionsForGuide(int $guideId): array
    {
        return \App\Services\RevenueService::getCompletedTransactionsForProvider($guideId);
    }

    public static function updateStatus(int $id, string $status): void
    {
        if ($status === 'completed') {
            Database::run("
                UPDATE bookings
                SET status = 'completed',
                    package_overall_status = CASE WHEN package_booking_ref IS NOT NULL THEN 'completed' ELSE package_overall_status END
                WHERE id = ?
            ", [$id]);
            \App\Services\RevenueService::recordBookingCompletion($id);
        } else {
            Database::run('UPDATE bookings SET status = ? WHERE id = ?', [$status, $id]);
            if (in_array($status, ['cancelled', 'refunded'], true)) {
                $b = self::find($id);
                if ($b) {
                    $l = Listing::find((int) $b['listing_id']);
                    if ($l && !empty($l['user_id'])) {
                        \App\Services\RevenueService::syncUserRevenue((int) $l['user_id']);
                    }
                }
            }
        }
    }

    /**
     * Guide cancels a booking. If the tourist already paid, the payment is
     * refunded automatically and the booking is marked refunded.
     *
     * @return array{refunded: bool, amount: float}
     */
    public static function cancelByGuide(int $id): array
    {
        $booking = self::find($id);
        if ($booking !== null && User::isGuideWarned((int) $booking['guide_id'])) {
            return ['refunded' => false, 'amount' => 0.0, 'blocked' => true];
        }

        $isPkg = $booking !== null && (!empty($booking['package_booking_ref']) || str_contains((string) ($booking['notes'] ?? ''), 'package_booking'));
        if ($isPkg) {
            \App\Services\PackageBookingService::onBookingUpdated($id, 'cancelled', (int) ($booking['guide_id'] ?? 0));
            return ['refunded' => false, 'amount' => 0.0, 'is_package' => true];
        }

        $payment = Payment::forBooking($id);
        $wasPaid = $payment !== null && $payment['status'] === 'paid';
        $amount = (float) ($payment['amount'] ?? 0);

        if ($wasPaid) {
            Payment::refund($id);
            self::updateStatus($id, 'refunded');
        } else {
            self::updateStatus($id, 'cancelled');
        }

        return ['refunded' => $wasPaid, 'amount' => $amount];
    }

    public static function isPaid(int $id): bool
    {
        return Payment::isPaid($id);
    }

    /**
     * Fix bookings cancelled before auto-refund existed: still marked paid
     * while the booking is cancelled. Refund the tourist and set status refunded.
     */
    public static function reconcileOrphanRefunds(): void
    {
        $rows = Database::all(
            'SELECT b.id FROM bookings b
             INNER JOIN payments p ON p.booking_id = b.id
             WHERE b.status = "cancelled" AND p.status = "paid"'
        );
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            Payment::refund($id);
            self::updateStatus($id, 'refunded');
        }
    }

    /**
     * Is this listing already taken on the given date? Paid and unpaid pending
     * bookings hold the slot until the owner declines, so two tourists cannot
     * pay for the same date. Cancelled and refunded bookings do not hold it.
     */
    public static function isDateBooked(int $listingId, string $date, ?int $excludeBookingId = null): bool
    {
        $listing = Listing::find($listingId);
        if ($listing === null) {
            return false;
        }

        $isHotel = ((string) ($listing['category_slug'] ?? '')) === 'hotels';
        if ($isHotel) {
            if (GuideAvailability::isBlocked($listingId, $date)) {
                return true;
            }
            $sql = 'SELECT id FROM bookings
                    WHERE listing_id = ?
                      AND (booking_date = ? OR booking_dates LIKE ?)
                      AND status IN ("pending", "confirmed")
                      AND (hotel_status IS NULL OR hotel_status NOT IN ("cancelled", "no_show", "completed"))';
            $params = [$listingId, $date, '%"' . $date . '"%'];
            if ($excludeBookingId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeBookingId;
            }
            return Database::first($sql, $params) !== null;
        }

        $catSlug = (string) ($listing['category_slug'] ?? '');
        $catId = (int) ($listing['category_id'] ?? 0);
        $isActivityOrAttraction = in_array($catSlug, ['things-to-do', 'attractions'], true) || in_array($catId, [1, 2], true);

        if ($isActivityOrAttraction) {
            if (GuideAvailability::isBlocked($listingId, $date)) {
                return true;
            }
            $guideId = (int) ($listing['user_id'] ?? 0);
            if ($guideId > 0 && GuideAvailability::isGuideBlocked($guideId, $date)) {
                return true;
            }
            return self::getRemainingSlots($listingId, $date, $excludeBookingId) <= 0;
        }

        if (GuideAvailability::isBlocked($listingId, $date)) {
            return true;
        }
        $guideId = (int) ($listing['user_id'] ?? 0);
        if ($guideId > 0 && GuideAvailability::isGuideBlocked($guideId, $date)) {
            return true;
        }

        $booked = self::bookedDates($listingId, null, $excludeBookingId);
        return in_array($date, $booked, true);
    }

    /**
     * Get remaining slot capacity for a listing on a specific date.
     * Strictly scopes to active bookings (pending, confirmed).
     * Completed bookings release their slots.
     */
    public static function getRemainingSlots(int $listingId, string $date, ?int $excludeBookingId = null): int
    {
        $listing = Listing::find($listingId);
        if ($listing === null) {
            return 0;
        }
        if (GuideAvailability::isBlocked($listingId, $date)) {
            return 0;
        }
        $guideId = (int) ($listing['user_id'] ?? 0);
        if ($guideId > 0 && GuideAvailability::isGuideBlocked($guideId, $date)) {
            return 0;
        }
        $capacity = max(1, (int) ($listing['capacity'] ?? 10));

        $sql = 'SELECT booking_date, booking_dates, guests
                FROM bookings
                WHERE listing_id = ?
                  AND status IN ("pending", "confirmed")
                  AND (booking_date = ? OR booking_dates LIKE ?)';
        $params = [$listingId, $date, '%"' . $date . '"%'];
        if ($excludeBookingId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeBookingId;
        }
        $rows = Database::all($sql, $params);
        $bookedCount = 0;
        foreach ($rows as $r) {
            $guests = max(1, (int) ($r['guests'] ?? 1));
            $dates = [];
            if (!empty($r['booking_date'])) {
                $dates[] = (string) $r['booking_date'];
            }
            if (!empty($r['booking_dates'])) {
                $extra = json_decode((string) $r['booking_dates'], true);
                if (is_array($extra)) {
                    foreach ($extra as $d) {
                        $dates[] = (string) $d;
                    }
                }
            }
            if (in_array($date, $dates, true)) {
                $bookedCount += $guests;
            }
        }

        return max(0, $capacity - $bookedCount);
    }

    /**
     * Calculate daily slot availability breakdown for a listing across all active dates.
     *
     * @return array<string, array{capacity: int, booked: int, remaining: int, is_fully_booked: bool}>
     */
    public static function dailySlotAvailability(int $listingId): array
    {
        $listing = Listing::find($listingId);
        if ($listing === null) {
            return [];
        }
        $capacity = max(1, (int) ($listing['capacity'] ?? 10));

        $sql = 'SELECT booking_date, booking_dates, guests
                FROM bookings
                WHERE listing_id = ?
                  AND status IN ("pending", "confirmed")
                  AND (booking_date >= CURDATE() OR booking_date IS NULL)';
        $rows = Database::all($sql, [$listingId]);

        $dateCounts = [];
        foreach ($rows as $r) {
            $guests = max(1, (int) ($r['guests'] ?? 1));
            $dates = [];
            if (!empty($r['booking_date'])) {
                $dates[] = (string) $r['booking_date'];
            }
            if (!empty($r['booking_dates'])) {
                $extra = json_decode((string) $r['booking_dates'], true);
                if (is_array($extra)) {
                    foreach ($extra as $d) {
                        $dates[] = (string) $d;
                    }
                }
            }
            foreach (array_unique($dates) as $d) {
                $dateCounts[$d] = ($dateCounts[$d] ?? 0) + $guests;
            }
        }

        $result = [];
        foreach ($dateCounts as $d => $count) {
            $remaining = max(0, $capacity - $count);
            $result[$d] = [
                'capacity' => $capacity,
                'booked' => $count,
                'remaining' => $remaining,
                'is_fully_booked' => ($remaining <= 0),
            ];
        }

        $blocked = GuideAvailability::blockedDates($listingId);
        $guideId = (int) ($listing['user_id'] ?? 0);
        if ($guideId > 0) {
            $blocked = array_merge($blocked, GuideAvailability::blockedDatesForGuide($guideId));
        }
        foreach (array_unique($blocked) as $bd) {
            if ($bd >= date('Y-m-d')) {
                $result[$bd] = [
                    'capacity' => $capacity,
                    'booked' => $capacity,
                    'remaining' => 0,
                    'is_fully_booked' => true,
                ];
            }
        }

        return $result;
    }

    /**
     * Upcoming dates already taken (confirmed/completed) for a listing, so the
     * booking form can stop tourists from picking them.
     * Strictly scoped per listing (and optionally per room). Multi-day stays
     * are expanded across each occupied night of the booking.
     *
     * @return array<int, string>
     */
    public static function bookedDates(int $listingId, ?int $roomId = null, ?int $excludeBookingId = null): array
    {
        $listing = Listing::find($listingId);
        $isHotel = ((string) ($listing['category_slug'] ?? '')) === 'hotels';

        if ($isHotel && $roomId !== null && $roomId > 0) {
            $room = HotelRoom::find($roomId);
            $totalUnits = max(1, (int) ($room['total_units'] ?? 1));

            $sql = 'SELECT booking_date, booking_dates, check_in_date, check_out_date
                    FROM bookings
                    WHERE listing_id = ?
                      AND (room_id = ? OR room_id IS NULL)
                      AND status NOT IN ("cancelled", "refunded", "completed")
                      AND (hotel_status IS NULL OR hotel_status NOT IN ("cancelled", "no_show", "completed"))
                      AND (booking_date >= CURDATE() OR check_out_date >= CURDATE())';
            $params = [$listingId, $roomId];
            if ($excludeBookingId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeBookingId;
            }
            $sql .= ' ORDER BY booking_date';

            $rows = Database::all($sql, $params);

            $dateCounts = [];
            foreach ($rows as $r) {
                $occupied = [];
                if (!empty($r['check_in_date']) && !empty($r['check_out_date'])) {
                    try {
                        $curr = new \DateTime((string) $r['check_in_date']);
                        $end = new \DateTime((string) $r['check_out_date']);
                        while ($curr < $end) {
                            $occupied[] = $curr->format('Y-m-d');
                            $curr->modify('+1 day');
                        }
                    } catch (\Throwable $e) {
                        $occupied[] = (string) $r['check_in_date'];
                    }
                } elseif (!empty($r['booking_date'])) {
                    $occupied[] = (string) $r['booking_date'];
                }
                if (!empty($r['booking_dates'])) {
                    $extra = json_decode((string) $r['booking_dates'], true);
                    if (is_array($extra)) {
                        foreach ($extra as $d) {
                            $occupied[] = (string) $d;
                        }
                    }
                }

                foreach (array_unique($occupied) as $od) {
                    $dateCounts[$od] = ($dateCounts[$od] ?? 0) + 1;
                }
            }

            $booked = [];
            foreach ($dateCounts as $od => $cnt) {
                if ($cnt >= $totalUnits) {
                    $booked[] = $od;
                }
            }

            $blocked = GuideAvailability::blockedDates($listingId);
            return array_values(array_unique(array_merge($booked, $blocked)));
        }

        if ($isHotel) {
            $sql = 'SELECT booking_date, booking_dates, check_in_date, check_out_date, room_id
                    FROM bookings
                    WHERE listing_id = ?
                      AND status NOT IN ("cancelled", "refunded", "completed")
                      AND (hotel_status IS NULL OR hotel_status NOT IN ("cancelled", "no_show", "completed"))
                      AND (booking_date >= CURDATE() OR check_out_date >= CURDATE())';
            $params = [$listingId];
            if ($excludeBookingId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeBookingId;
            }
            $sql .= ' ORDER BY booking_date';
            $rows = Database::all($sql, $params);

            $booked = [];
            foreach ($rows as $r) {
                if (!empty($r['booking_date'])) {
                    $booked[] = (string) $r['booking_date'];
                }
                if (!empty($r['booking_dates'])) {
                    $extra = json_decode((string) $r['booking_dates'], true);
                    if (is_array($extra)) {
                        foreach ($extra as $d) {
                            $booked[] = (string) $d;
                        }
                    }
                }
                if (!empty($r['check_in_date']) && !empty($r['check_out_date'])) {
                    try {
                        $curr = new \DateTime((string) $r['check_in_date']);
                        $end = new \DateTime((string) $r['check_out_date']);
                        while ($curr < $end) {
                            $booked[] = $curr->format('Y-m-d');
                            $curr->modify('+1 day');
                        }
                    } catch (\Throwable $e) {
                        $booked[] = (string) $r['check_in_date'];
                    }
                }
            }
            return array_values(array_unique(array_merge($booked, GuideAvailability::blockedDates($listingId))));
        }

        $catSlug = (string) ($listing['category_slug'] ?? '');
        $catId = (int) ($listing['category_id'] ?? 0);
        $isActivityOrAttraction = in_array($catSlug, ['things-to-do', 'attractions'], true) || in_array($catId, [1, 2], true);

        if ($isActivityOrAttraction) {
            $capacity = max(1, (int) ($listing['capacity'] ?? 10));

            $sql = 'SELECT booking_date, booking_dates, guests
                    FROM bookings
                    WHERE listing_id = ?
                      AND status IN ("pending", "confirmed")
                      AND (booking_date >= CURDATE() OR booking_date IS NULL)';
            $params = [$listingId];
            if ($excludeBookingId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeBookingId;
            }
            $rows = Database::all($sql, $params);

            $dateCounts = [];
            foreach ($rows as $r) {
                $guests = max(1, (int) ($r['guests'] ?? 1));
                $dates = [];
                if (!empty($r['booking_date'])) {
                    $dates[] = (string) $r['booking_date'];
                }
                if (!empty($r['booking_dates'])) {
                    $extra = json_decode((string) $r['booking_dates'], true);
                    if (is_array($extra)) {
                        foreach ($extra as $d) {
                            $dates[] = (string) $d;
                        }
                    }
                }
                foreach (array_unique($dates) as $d) {
                    $dateCounts[$d] = ($dateCounts[$d] ?? 0) + $guests;
                }
            }

            $booked = [];
            foreach ($dateCounts as $d => $count) {
                if ($count >= $capacity) {
                    $booked[] = $d;
                }
            }

            $blocked = GuideAvailability::blockedDates($listingId);
            $guideId = (int) ($listing['user_id'] ?? 0);
            if ($guideId > 0) {
                $blocked = array_merge($blocked, GuideAvailability::blockedDatesForGuide($guideId));
            }

            $today = date('Y-m-d');
            $filtered = array_filter(
                array_unique(array_merge($booked, $blocked)),
                static fn($d) => $d >= $today
            );
            sort($filtered);
            return array_values($filtered);
        }

        // Guide / Package availability: Check guide schedule across all listings and package items
        $guideId = (int) ($listing['user_id'] ?? 0);

        $sql = 'SELECT b.id, b.booking_date, b.booking_dates, b.check_in_date, b.check_out_date, b.notes
                FROM bookings b
                LEFT JOIN listings l ON l.id = b.listing_id
                WHERE (b.listing_id = ?' . ($guideId > 0 ? ' OR l.user_id = ?' : '') . ')
                  AND b.status IN ("pending", "confirmed")
                  AND (b.package_overall_status IS NULL OR b.package_overall_status NOT IN ("cancelled", "refunded", "completed"))
                  AND (b.booking_date >= CURDATE() OR b.check_out_date >= CURDATE())';
        $params = $guideId > 0 ? [$listingId, $guideId] : [$listingId];
        if ($excludeBookingId !== null) {
            $sql .= ' AND b.id <> ?';
            $params[] = $excludeBookingId;
        }
        $rows = Database::all($sql, $params);

        $guideItemRows = [];
        if ($guideId > 0) {
            try {
                $itemSql = 'SELECT pbi.id, pbi.parent_booking_id, pbi.rescheduled_date,
                                   b.booking_date, b.check_in_date, b.check_out_date, b.notes
                            FROM package_booking_items pbi
                            JOIN bookings b ON b.id = pbi.parent_booking_id
                            WHERE pbi.provider_id = ?
                              AND pbi.provider_role = "guide"
                              AND pbi.status NOT IN ("cancelled", "removed", "completed")
                              AND b.status IN ("pending", "confirmed")
                              AND (b.package_overall_status IS NULL OR b.package_overall_status NOT IN ("cancelled", "refunded", "completed"))
                              AND (b.booking_date >= CURDATE() OR pbi.rescheduled_date >= CURDATE() OR b.check_out_date >= CURDATE())';
                $itemParams = [$guideId];
                if ($excludeBookingId !== null) {
                    $itemSql .= ' AND b.id <> ?';
                    $itemParams[] = $excludeBookingId;
                }
                $guideItemRows = Database::all($itemSql, $itemParams);
            } catch (\Throwable) {}
        }

        $allRows = array_merge($rows, $guideItemRows);
        $booked = [];

        foreach ($allRows as $r) {
            if (!empty($r['rescheduled_date'])) {
                $booked[] = (string) $r['rescheduled_date'];
                continue;
            }

            $startDate = !empty($r['booking_date']) ? (string) $r['booking_date'] : (!empty($r['check_in_date']) ? (string) $r['check_in_date'] : '');
            if ($startDate !== '') {
                $booked[] = $startDate;
            }

            // Expand multi-day package durations
            $durationDays = 1;
            if (!empty($r['notes'])) {
                $noteData = json_decode((string) $r['notes'], true);
                if (is_array($noteData) && !empty($noteData['duration_days'])) {
                    $durationDays = max(1, (int) $noteData['duration_days']);
                }
            }

            if ($startDate !== '' && $durationDays > 1) {
                for ($d = 1; $d < $durationDays; $d++) {
                    $booked[] = date('Y-m-d', strtotime("{$startDate} +{$d} days"));
                }
            }

            if (!empty($r['booking_dates'])) {
                $extra = json_decode((string) $r['booking_dates'], true);
                if (is_array($extra)) {
                    foreach ($extra as $d) {
                        $booked[] = (string) $d;
                    }
                }
            }

            if (!empty($r['check_in_date']) && !empty($r['check_out_date'])) {
                try {
                    $curr = new \DateTime((string) $r['check_in_date']);
                    $end = new \DateTime((string) $r['check_out_date']);
                    while ($curr <= $end) {
                        $booked[] = $curr->format('Y-m-d');
                        $curr->modify('+1 day');
                    }
                } catch (\Throwable $e) {
                    $booked[] = (string) $r['check_in_date'];
                }
            }
        }

        $manualBlocks = GuideAvailability::blockedDates($listingId);
        if ($guideId > 0) {
            $manualBlocks = array_merge($manualBlocks, GuideAvailability::blockedDatesForGuide($guideId));
        }

        // Existing booking schedules plotted in listing_schedules: preserve as unavailable
        $existingSchedules = [];
        try {
            $schedSql = 'SELECT DISTINCT ls.schedule_date
                         FROM listing_schedules ls
                         LEFT JOIN listings l ON l.id = ls.listing_id
                         WHERE (ls.listing_id = ?' . ($guideId > 0 ? ' OR l.user_id = ?' : '') . ')
                           AND ls.schedule_date >= CURDATE()';
            $schedParams = $guideId > 0 ? [$listingId, $guideId] : [$listingId];
            $schedRows = Database::all($schedSql, $schedParams);
            foreach ($schedRows as $sr) {
                if (!empty($sr['schedule_date'])) {
                    $existingSchedules[] = (string) $sr['schedule_date'];
                }
            }
        } catch (\Throwable) {}

        $today = date('Y-m-d');
        $filtered = array_filter(
            array_unique(array_merge($booked, $manualBlocks, $existingSchedules)),
            static fn($d) => $d >= $today
        );
        sort($filtered);
        return array_values($filtered);
    }

    /**
     * Upcoming date + start-time slots already taken for a listing, so the
     * mobile booking form can disable them. A booking with no stored time (older
     * data) and any guide-blocked date lock the whole day, expressed as every
     * standard slot for that date.
     *
     * @return array<int, array{date: string, time: string}>
     */
    public static function bookedSlots(int $listingId): array
    {
        $rows = Database::all(
            'SELECT booking_date, booking_time, booking_end_time, booking_dates FROM bookings
             WHERE listing_id = ? AND status IN ("pending","confirmed","completed") AND booking_date >= CURDATE()',
            [$listingId]
        );

        $slots = [];
        $seen = [];
        $add = static function (string $date, string $time) use (&$slots, &$seen): void {
            $key = $date . '|' . $time;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $slots[] = ['date' => $date, 'time' => $time];
            }
        };

        foreach ($rows as $row) {
            $dates = [(string) $row['booking_date']];
            if (!empty($row['booking_dates'])) {
                $extra = json_decode((string) $row['booking_dates'], true);
                if (is_array($extra)) {
                    $dates = array_unique(array_merge($dates, $extra));
                }
            }
            $time = trim((string) ($row['booking_time'] ?? ''));
            $endTime = trim((string) ($row['booking_end_time'] ?? ''));
            foreach ($dates as $date) {
                if ($time === '') {
                    foreach (self::TIME_SLOTS as $slot) {
                        $add($date, $slot);
                    }
                } else {
                    $startSlot = substr($time, 0, 5);
                    $endSlot = $endTime !== '' ? substr($endTime, 0, 5) : '';
                    if ($endSlot !== '' && $endSlot > $startSlot) {
                        foreach (self::TIME_SLOTS as $slot) {
                            if ($slot >= $startSlot && $slot < $endSlot) {
                                $add($date, $slot);
                            }
                        }
                    } else {
                        $add($date, $startSlot);
                    }
                }
            }
        }

        // Dates the guide manually blocked are fully unavailable.
        foreach (GuideAvailability::blockedDates($listingId) as $blocked) {
            foreach (self::TIME_SLOTS as $slot) {
                $add((string) $blocked, $slot);
            }
        }

        return $slots;
    }

    /**
     * Is this exact date + start-time slot already taken by a booking? A
     * guide-blocked date counts as fully booked.
     */
    public static function isSlotBooked(int $listingId, string $date, string $time, ?int $excludeBookingId = null): bool
    {
        if (GuideAvailability::isBlocked($listingId, $date)) {
            return true;
        }
        $time = substr(trim($time), 0, 5);
        $sql = 'SELECT id, booking_time, booking_end_time FROM bookings
                WHERE listing_id = ? AND (booking_date = ? OR booking_dates LIKE ?) AND status IN ("pending","confirmed","completed")';
        $params = [$listingId, $date, '%"' . $date . '"%'];
        if ($excludeBookingId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeBookingId;
        }

        $rows = Database::all($sql, $params);
        foreach ($rows as $row) {
            $bStart = trim((string) ($row['booking_time'] ?? ''));
            $bEnd = trim((string) ($row['booking_end_time'] ?? ''));
            if ($bStart === '') {
                return true;
            }
            $bStart = substr($bStart, 0, 5);
            $bEnd = $bEnd !== '' ? substr($bEnd, 0, 5) : '';
            if ($bEnd !== '' && $bEnd > $bStart) {
                if ($time >= $bStart && $time < $bEnd) {
                    return true;
                }
            } elseif ($time === $bStart) {
                return true;
            }
        }
        return false;
    }

    /**
     * Upcoming date + start-time slots where the GUIDE is unavailable — across
     * every listing they own, plus any dates they manually blocked.
     *
     * @return array<int, array{date: string, time: string}>
     */
    public static function guideBookedSlots(int $guideId): array
    {
        $rows = Database::all(
            'SELECT b.booking_date, b.booking_time, b.booking_end_time, b.booking_dates
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE l.user_id = ? AND b.status IN ("pending","confirmed") AND b.booking_date >= CURDATE()',
            [$guideId]
        );

        $slots = [];
        $seen = [];
        $add = static function (string $date, string $time) use (&$slots, &$seen): void {
            $key = $date . '|' . $time;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $slots[] = ['date' => $date, 'time' => $time];
            }
        };

        foreach ($rows as $row) {
            $dates = [(string) $row['booking_date']];
            if (!empty($row['booking_dates'])) {
                $extra = json_decode((string) $row['booking_dates'], true);
                if (is_array($extra)) {
                    $dates = array_unique(array_merge($dates, $extra));
                }
            }
            $time = trim((string) ($row['booking_time'] ?? ''));
            $endTime = trim((string) ($row['booking_end_time'] ?? ''));
            foreach ($dates as $date) {
                if ($time === '') {
                    foreach (self::TIME_SLOTS as $slot) {
                        $add($date, $slot);
                    }
                } else {
                    $startSlot = substr($time, 0, 5);
                    $endSlot = $endTime !== '' ? substr($endTime, 0, 5) : '';
                    if ($endSlot !== '' && $endSlot > $startSlot) {
                        foreach (self::TIME_SLOTS as $slot) {
                            if ($slot >= $startSlot && $slot < $endSlot) {
                                $add($date, $slot);
                            }
                        }
                    } else {
                        $add($date, $startSlot);
                    }
                }
            }
        }

        // Dates the guide manually blocked on any of their listings.
        $blocked = Database::all(
            'SELECT DISTINCT ga.blocked_date
             FROM guide_availability ga
             JOIN listings l ON l.id = ga.listing_id
             WHERE l.user_id = ? AND ga.blocked_date >= CURDATE()',
            [$guideId]
        );
        foreach ($blocked as $b) {
            foreach (self::TIME_SLOTS as $slot) {
                $add((string) $b['blocked_date'], $slot);
            }
        }

        return $slots;
    }

    /**
     * Is the GUIDE (owner) already taken for this date + start-time slot on any
     * of their listings? Used to stop a second tourist double-booking the guide.
     */
    public static function isGuideSlotBooked(int $guideId, string $date, string $time, ?int $excludeBookingId = null, ?int $excludeListingId = null): bool
    {
        $time = substr(trim($time), 0, 5);
        $sql = 'SELECT b.id, b.booking_time, b.booking_end_time FROM bookings b
                JOIN listings l ON l.id = b.listing_id
                WHERE l.user_id = ? AND (b.booking_date = ? OR b.booking_dates LIKE ?) AND b.status IN ("pending","confirmed")';
        $params = [$guideId, $date, '%"' . $date . '"%'];
        if ($excludeBookingId !== null) {
            $sql .= ' AND b.id <> ?';
            $params[] = $excludeBookingId;
        }
        if ($excludeListingId !== null) {
            $sql .= ' AND l.id <> ?';
            $params[] = $excludeListingId;
        }
        $rows = Database::all($sql, $params);
        foreach ($rows as $row) {
            $bStart = trim((string) ($row['booking_time'] ?? ''));
            $bEnd = trim((string) ($row['booking_end_time'] ?? ''));
            if ($bStart === '') {
                return true;
            }
            $bStart = substr($bStart, 0, 5);
            $bEnd = $bEnd !== '' ? substr($bEnd, 0, 5) : '';
            if ($bEnd !== '' && $bEnd > $bStart) {
                if ($time >= $bStart && $time < $bEnd) {
                    return true;
                }
            } elseif ($time === $bStart) {
                return true;
            }
        }

        // A manual block on any of the guide's listings locks the whole day.
        return Database::first(
            'SELECT ga.id FROM guide_availability ga
             JOIN listings l ON l.id = ga.listing_id
             WHERE l.user_id = ? AND ga.blocked_date = ?',
            [$guideId, $date]
        ) !== null;
    }

    /**
     * Is the GUIDE taken anywhere on this date (for time-less/whole-day bookings)?
     */
    public static function isGuideDateBooked(int $guideId, string $date, ?int $excludeBookingId = null, ?int $excludeListingId = null): bool
    {
        if (GuideAvailability::isGuideBlocked($guideId, $date)) {
            return true;
        }

        $sql = 'SELECT b.id, b.booking_date, b.booking_dates, b.check_in_date, b.check_out_date, b.notes
                FROM bookings b
                JOIN listings l ON l.id = b.listing_id
                WHERE l.user_id = ?
                  AND b.status IN ("pending", "confirmed")
                  AND (b.package_overall_status IS NULL OR b.package_overall_status NOT IN ("cancelled", "refunded", "completed"))';
        $params = [$guideId];
        if ($excludeBookingId !== null) {
            $sql .= ' AND b.id <> ?';
            $params[] = $excludeBookingId;
        }
        if ($excludeListingId !== null) {
            $sql .= ' AND l.id <> ?';
            $params[] = $excludeListingId;
        }
        $rows = Database::all($sql, $params);

        foreach ($rows as $r) {
            $startDate = !empty($r['booking_date']) ? (string) $r['booking_date'] : (!empty($r['check_in_date']) ? (string) $r['check_in_date'] : '');
            if ($startDate === $date) {
                return true;
            }

            $durationDays = 1;
            if (!empty($r['notes'])) {
                $noteData = json_decode((string) $r['notes'], true);
                if (is_array($noteData) && !empty($noteData['duration_days'])) {
                    $durationDays = max(1, (int) $noteData['duration_days']);
                }
            }

            if ($startDate !== '' && $durationDays > 1) {
                for ($d = 1; $d < $durationDays; $d++) {
                    if (date('Y-m-d', strtotime("{$startDate} +{$d} days")) === $date) {
                        return true;
                    }
                }
            }

            if (!empty($r['booking_dates'])) {
                $extra = json_decode((string) $r['booking_dates'], true);
                if (is_array($extra) && in_array($date, $extra, true)) {
                    return true;
                }
            }

            if (!empty($r['check_in_date']) && !empty($r['check_out_date'])) {
                if ($date >= (string) $r['check_in_date'] && $date <= (string) $r['check_out_date']) {
                    return true;
                }
            }
        }

        try {
            $itemSql = 'SELECT pbi.id, pbi.rescheduled_date, b.booking_date, b.check_in_date, b.check_out_date, b.notes
                        FROM package_booking_items pbi
                        JOIN bookings b ON b.id = pbi.parent_booking_id
                        WHERE pbi.provider_id = ?
                          AND pbi.provider_role = "guide"
                          AND pbi.status NOT IN ("cancelled", "removed", "completed")
                          AND b.status IN ("pending", "confirmed")
                          AND (b.package_overall_status IS NULL OR b.package_overall_status NOT IN ("cancelled", "refunded", "completed"))';
            $itemParams = [$guideId];
            if ($excludeBookingId !== null) {
                $itemSql .= ' AND b.id <> ?';
                $itemParams[] = $excludeBookingId;
            }
            $items = Database::all($itemSql, $itemParams);

            foreach ($items as $it) {
                if (!empty($it['rescheduled_date']) && (string) $it['rescheduled_date'] === $date) {
                    return true;
                }
                $startDate = !empty($it['booking_date']) ? (string) $it['booking_date'] : (!empty($it['check_in_date']) ? (string) $it['check_in_date'] : '');
                if ($startDate === $date) {
                    return true;
                }
                $durationDays = 1;
                if (!empty($it['notes'])) {
                    $noteData = json_decode((string) $it['notes'], true);
                    if (is_array($noteData) && !empty($noteData['duration_days'])) {
                        $durationDays = max(1, (int) $noteData['duration_days']);
                    }
                }
                if ($startDate !== '' && $durationDays > 1) {
                    for ($d = 1; $d < $durationDays; $d++) {
                        if (date('Y-m-d', strtotime("{$startDate} +{$d} days")) === $date) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Throwable) {}

        // Check if guide has an existing booking schedule in listing_schedules on this date
        try {
            $schedSql = 'SELECT ls.id FROM listing_schedules ls
                         JOIN listings l ON l.id = ls.listing_id
                         WHERE l.user_id = ? AND ls.schedule_date = ?' . ($excludeListingId !== null ? ' AND l.id <> ?' : '') . ' LIMIT 1';
            $schedParams = $excludeListingId !== null ? [$guideId, $date, $excludeListingId] : [$guideId, $date];
            if (Database::first($schedSql, $schedParams) !== null) {
                return true;
            }
        } catch (\Throwable) {}

        return false;
    }

    /**
     * Is the guide currently on a tour today? True while they have a confirmed
     * (paid, not yet completed) booking dated today. Once the tour is marked
     * completed — or the day passes — the guide is available again. Drives the
     * "Available / On a tour" badge in the Tour Guides directory.
     */
    public static function isGuideBusyToday(int $guideId): bool
    {
        return Database::first(
            'SELECT b.id FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE l.user_id = ? AND b.booking_date = CURDATE() AND b.status = "confirmed"',
            [$guideId]
        ) !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function dueReminders(): array
    {
        return Database::all(
            'SELECT b.*, l.title AS listing_title, u.email AS customer_email, g.email AS guide_email
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             JOIN users g ON g.id = l.user_id
             WHERE b.status = "confirmed" AND b.booking_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND b.reminder_sent = 0'
        );
    }

    public static function markReminderSent(int $id): void
    {
        Database::run('UPDATE bookings SET reminder_sent = 1 WHERE id = ?', [$id]);
    }

    public static function countCompletedForGuide(int $guideId): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS c FROM bookings b JOIN listings l ON l.id = b.listing_id WHERE l.user_id = ? AND b.status = "completed"',
            [$guideId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function hasCompletedBooking(int $listingId, int $userId): bool
    {
        return Database::first(
            'SELECT id FROM bookings
             WHERE listing_id = ?
               AND user_id = ?
               AND (status = "completed" OR package_overall_status = "completed" OR unit_completed_at IS NOT NULL)
               AND status NOT IN ("cancelled", "refunded")
               AND (package_overall_status IS NULL OR package_overall_status NOT IN ("cancelled", "refunded"))
             LIMIT 1',
            [$listingId, $userId]
        ) !== null;
    }

    public static function getEligibleUnreviewedBooking(int $listingId, int $userId): ?array
    {
        return Database::first(
            'SELECT b.* FROM bookings b
             LEFT JOIN reviews r ON r.booking_id = b.id
             WHERE b.listing_id = ?
               AND b.user_id = ?
               AND (b.status = "completed" OR b.package_overall_status = "completed" OR b.unit_completed_at IS NOT NULL)
               AND b.status NOT IN ("cancelled", "refunded")
               AND (b.package_overall_status IS NULL OR b.package_overall_status NOT IN ("cancelled", "refunded"))
               AND r.id IS NULL
             ORDER BY b.id DESC
             LIMIT 1',
            [$listingId, $userId]
        );
    }

    public static function hasUnreviewedCompletedBooking(int $listingId, int $userId): bool
    {
        return self::getEligibleUnreviewedBooking($listingId, $userId) !== null;
    }

    public static function countForGuide(int $guideId): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS c FROM bookings b JOIN listings l ON l.id = b.listing_id WHERE l.user_id = ?',
            [$guideId]
        );
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Total revenue generated for a service provider (guide or hotel owner)
     * from eligible completed bookings with verified payments.
     */
    public static function revenueForProvider(int $providerId): float
    {
        return \App\Services\RevenueService::getProviderRevenue($providerId);
    }

    public static function revenueForGuide(int $guideId): float
    {
        return self::revenueForProvider($guideId);
    }

    public static function revenueForHotelOwner(int $ownerId): float
    {
        return self::revenueForProvider($ownerId);
    }

    /**
     * Paid earnings per month for a single guide (for the dashboard chart).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function earningsPerMonth(int $guideId, int $months = 6): array
    {
        return Database::all(
            'SELECT DATE_FORMAT(b.created_at, "%Y-%m") AS month, COALESCE(SUM(b.total_amount),0) AS total
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE l.user_id = ? AND b.status = "completed"
               AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
               AND EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.id AND p.status = "paid")
             GROUP BY month ORDER BY month ASC',
            [$guideId, $months]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function bookingsPerMonth(int $months = 12): array
    {
        return Database::all(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS month, COUNT(*) AS total
             FROM bookings
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY month ORDER BY month ASC',
            [$months]
        );
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function exportRows(): array
    {
        $rows = [];
        foreach (Database::all(
            'SELECT b.id, l.title, u.name AS customer, b.booking_date, b.guests, b.total_amount, b.status, b.created_at
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             ORDER BY b.created_at DESC'
        ) as $b) {
            $rows[] = [
                $b['id'],
                $b['title'],
                $b['customer'],
                $b['booking_date'],
                $b['guests'],
                $b['total_amount'],
                $b['status'],
                $b['created_at'],
            ];
        }
        return $rows;
    }

    public static function guideRatingStats(int $guideId): array
    {
        $row = Database::first(
            'SELECT AVG(COALESCE(r.provider_rating, r.rating)) AS avg_rating, COUNT(r.id) AS review_count
             FROM reviews r
             LEFT JOIN listings l ON l.id = r.listing_id
             WHERE l.user_id = ? OR r.provider_id = ?',
            [$guideId, $guideId]
        );
        $completed = self::countCompletedForGuide($guideId);
        $bookings = self::countForGuide($guideId);
        return [
            'avg_rating' => round((float) ($row['avg_rating'] ?? 0), 1),
            'review_count' => (int) ($row['review_count'] ?? 0),
            'completed' => $completed,
            'conversion' => $bookings > 0 ? round($completed / $bookings * 100, 1) : 0.0,
        ];
    }

    public static function delete(int $id): bool
    {
        $booking = Database::first('
            SELECT b.*, l.user_id AS listing_owner_id
            FROM bookings b
            LEFT JOIN listings l ON l.id = b.listing_id
            WHERE b.id = ?
        ', [$id]);

        if (!$booking) {
            return false;
        }

        $affectedProviderIds = [];
        if (!empty($booking['listing_owner_id'])) {
            $affectedProviderIds[] = (int) $booking['listing_owner_id'];
        }
        if (!empty($booking['guide_id'])) {
            $affectedProviderIds[] = (int) $booking['guide_id'];
        }

        $pkgRef = $booking['package_booking_ref'] ?? null;
        $items = Database::all('
            SELECT provider_id FROM package_booking_items
            WHERE parent_booking_id = ? OR sub_booking_id = ?' . ($pkgRef ? ' OR package_booking_ref = ?' : ''),
            $pkgRef ? [$id, $id, $pkgRef] : [$id, $id]
        );
        foreach ($items as $it) {
            if (!empty($it['provider_id'])) {
                $affectedProviderIds[] = (int) $it['provider_id'];
            }
        }

        try {
            Database::run('UPDATE reviews SET booking_id = NULL WHERE booking_id = ?', [$id]);
        } catch (\Throwable $e) {}

        try {
            Database::run('DELETE FROM disputes WHERE booking_id = ?', [$id]);
        } catch (\Throwable $e) {}

        try {
            Database::run('DELETE FROM payments WHERE booking_id = ?', [$id]);
        } catch (\Throwable $e) {}

        try {
            Database::run('DELETE FROM package_booking_items WHERE parent_booking_id = ? OR sub_booking_id = ?', [$id, $id]);
            if ($pkgRef) {
                try {
                    Database::run('DELETE FROM payments WHERE booking_id IN (SELECT b2.id FROM (SELECT id FROM bookings WHERE package_booking_ref = ?) b2)', [$pkgRef]);
                } catch (\Throwable $e) {}
                try {
                    Database::run('DELETE FROM disputes WHERE booking_id IN (SELECT b2.id FROM (SELECT id FROM bookings WHERE package_booking_ref = ?) b2)', [$pkgRef]);
                } catch (\Throwable $e) {}
                try {
                    Database::run('UPDATE reviews SET booking_id = NULL WHERE booking_id IN (SELECT b2.id FROM (SELECT id FROM bookings WHERE package_booking_ref = ?) b2)', [$pkgRef]);
                } catch (\Throwable $e) {}
                try {
                    Database::run('DELETE FROM rental_report_files WHERE rental_id IN (SELECT r2.id FROM (SELECT id FROM rental_requests WHERE package_booking_ref = ?) r2)', [$pkgRef]);
                } catch (\Throwable $e) {}
                try {
                    Database::run('DELETE FROM rental_reviews WHERE rental_id IN (SELECT r2.id FROM (SELECT id FROM rental_requests WHERE package_booking_ref = ?) r2)', [$pkgRef]);
                } catch (\Throwable $e) {}
                try {
                    Database::run('DELETE FROM package_booking_items WHERE package_booking_ref = ?', [$pkgRef]);
                } catch (\Throwable $e) {}
                try {
                    Database::run('DELETE FROM bookings WHERE package_booking_ref = ? AND id != ?', [$pkgRef, $id]);
                } catch (\Throwable $e) {}
                try {
                    Database::run('DELETE FROM rental_requests WHERE package_booking_ref = ?', [$pkgRef]);
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {}

        $deleted = Database::run('DELETE FROM bookings WHERE id = ?', [$id])->rowCount() > 0;

        \App\Services\RevenueService::recordBookingDeletion($id, [
            'listing_owner_id' => $booking['listing_owner_id'] ?? null,
            'guide_id' => $booking['guide_id'] ?? null,
            'provider_ids' => $affectedProviderIds,
        ]);

        return $deleted;
    }
}
