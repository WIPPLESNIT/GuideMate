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

    public static function create(int $listingId, int $userId, string $date, int $guests, float $total, string $notes, ?string $time = null): int
    {
        $token = bin2hex(random_bytes(16));
        $id = Database::insert(
            'INSERT INTO bookings (listing_id, user_id, booking_date, booking_time, guests, total_amount, notes, verify_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$listingId, $userId, $date, ($time !== null && $time !== '' ? $time : null), $guests, $total, $notes, $token]
        );
        return $id;
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
                    l.price_unit, u.name AS customer_name, u.email AS customer_email
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
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
                    p.status AS payment_status,
                    d.id AS dispute_id, d.status AS dispute_status
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             LEFT JOIN payments p ON p.booking_id = b.id
             LEFT JOIN disputes d ON d.booking_id = b.id
             WHERE b.user_id = ? ORDER BY b.created_at DESC',
            [$userId]
        );
    }

    /**
     * Paid bookings with map coordinates for tourist navigation.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function navigableForCustomer(int $userId): array
    {
        $rows = Database::all(
            'SELECT b.id, b.booking_date, b.status, b.guests, b.listing_id,
                    l.title AS listing_title, l.slug AS listing_slug, l.area, l.address,
                    l.latitude, l.longitude
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN payments p ON p.booking_id = b.id AND p.status = "paid"
             WHERE b.user_id = ?
               AND b.status IN ("confirmed", "completed")
             ORDER BY b.booking_date ASC, b.id ASC',
            [$userId]
        );

        $out = [];
        foreach ($rows as $row) {
            $coords = Geo::forListing($row);
            if ($coords === null) {
                continue;
            }
            $row['latitude'] = $coords['latitude'];
            $row['longitude'] = $coords['longitude'];
            $row['approximate'] = $coords['approximate'];
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
            'SELECT b.id, b.booking_date, b.status, b.guests, b.listing_id,
                    l.title AS listing_title, l.slug AS listing_slug, l.area, l.address,
                    l.latitude, l.longitude
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN payments p ON p.booking_id = b.id AND p.status = "paid"
             WHERE b.user_id = ? AND b.id = ?
               AND b.status IN ("confirmed", "completed")',
            [$userId, $bookingId]
        );
        if ($row === null) {
            return null;
        }
        $coords = Geo::forListing($row);
        if ($coords === null) {
            return null;
        }
        $row['latitude'] = $coords['latitude'];
        $row['longitude'] = $coords['longitude'];
        $row['approximate'] = $coords['approximate'];
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

    public static function hasActivePaidBooking(int $listingId, int $userId): bool
    {
        return Database::first(
            'SELECT b.id FROM bookings b
             JOIN payments p ON p.booking_id = b.id AND p.status = "paid"
             WHERE b.listing_id = ? AND b.user_id = ?
               AND b.status IN ("pending", "confirmed", "completed")',
            [$listingId, $userId]
        ) !== null;
    }

    /**
     * Bookings received by a guide (across all their listings).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forGuide(int $guideId): array
    {
        return Database::all(
            'SELECT b.*, l.title AS listing_title, l.slug AS listing_slug,
                    u.name AS customer_name, p.status AS payment_status
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             LEFT JOIN payments p ON p.booking_id = b.id
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
        return Database::all(
            'SELECT b.id, b.booking_date, b.booking_time, b.guests, b.status,
                    b.total_amount, b.created_at,
                    l.title AS listing_title, l.slug AS listing_slug,
                    u.name AS customer_name,
                    p.amount AS paid_amount, p.method AS payment_method,
                    p.status AS payment_status, p.reference AS payment_reference,
                    p.paid_at AS paid_at
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             LEFT JOIN payments p ON p.booking_id = b.id
             WHERE l.user_id = ?
             ORDER BY COALESCE(p.paid_at, b.created_at) DESC',
            [$guideId]
        );
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::run('UPDATE bookings SET status = ? WHERE id = ?', [$status, $id]);
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
        if (GuideAvailability::isBlocked($listingId, $date)) {
            return true;
        }
        $sql = 'SELECT id FROM bookings
                WHERE listing_id = ? AND booking_date = ? AND status IN ("pending","confirmed","completed")';
        $params = [$listingId, $date];
        if ($excludeBookingId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeBookingId;
        }
        return Database::first($sql, $params) !== null;
    }

    /**
     * Upcoming dates already taken (confirmed/completed) for a listing, so the
     * booking form can stop tourists from picking them.
     *
     * @return array<int, string>
     */
    public static function bookedDates(int $listingId): array
    {
        $booked = array_map(static fn($r) => (string) $r['booking_date'], Database::all(
            'SELECT booking_date FROM bookings
             WHERE listing_id = ? AND status IN ("pending","confirmed","completed") AND booking_date >= CURDATE()
             ORDER BY booking_date',
            [$listingId]
        ));
        return array_values(array_unique(array_merge($booked, GuideAvailability::blockedDates($listingId))));
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
            'SELECT booking_date, booking_time FROM bookings
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
            $date = (string) $row['booking_date'];
            $time = trim((string) ($row['booking_time'] ?? ''));
            if ($time === '') {
                foreach (self::TIME_SLOTS as $slot) {
                    $add($date, $slot);
                }
            } else {
                $add($date, substr($time, 0, 5));
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
     * Is this exact date + start-time slot already taken by a paid booking? A
     * guide-blocked date counts as fully booked.
     */
    public static function isSlotBooked(int $listingId, string $date, string $time, ?int $excludeBookingId = null): bool
    {
        if (GuideAvailability::isBlocked($listingId, $date)) {
            return true;
        }
        $time = substr(trim($time), 0, 5);
        $sql = 'SELECT id FROM bookings
                WHERE listing_id = ? AND booking_date = ? AND status IN ("pending","confirmed","completed")
                  AND (booking_time = ? OR booking_time IS NULL OR booking_time = "")';
        $params = [$listingId, $date, $time];
        if ($excludeBookingId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeBookingId;
        }
        return Database::first($sql, $params) !== null;
    }

    /**
     * Upcoming date + start-time slots where the GUIDE is unavailable — across
     * every listing they own, plus any dates they manually blocked. A guide can
     * only run one tour at a time, so a slot booked on one of their listings
     * makes them unavailable on all the others (prevents double-booking).
     *
     * @return array<int, array{date: string, time: string}>
     */
    public static function guideBookedSlots(int $guideId): array
    {
        $rows = Database::all(
            'SELECT b.booking_date, b.booking_time
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE l.user_id = ? AND b.status IN ("pending","confirmed","completed") AND b.booking_date >= CURDATE()',
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
            $date = (string) $row['booking_date'];
            $time = trim((string) ($row['booking_time'] ?? ''));
            if ($time === '') {
                foreach (self::TIME_SLOTS as $slot) {
                    $add($date, $slot);
                }
            } else {
                $add($date, substr($time, 0, 5));
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
    public static function isGuideSlotBooked(int $guideId, string $date, string $time, ?int $excludeBookingId = null): bool
    {
        $time = substr(trim($time), 0, 5);
        $sql = 'SELECT b.id FROM bookings b
                JOIN listings l ON l.id = b.listing_id
                WHERE l.user_id = ? AND b.booking_date = ? AND b.status IN ("pending","confirmed","completed")
                  AND (b.booking_time = ? OR b.booking_time IS NULL OR b.booking_time = "")';
        $params = [$guideId, $date, $time];
        if ($excludeBookingId !== null) {
            $sql .= ' AND b.id <> ?';
            $params[] = $excludeBookingId;
        }
        if (Database::first($sql, $params) !== null) {
            return true;
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
    public static function isGuideDateBooked(int $guideId, string $date): bool
    {
        if (Database::first(
            'SELECT b.id FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             WHERE l.user_id = ? AND b.booking_date = ? AND b.status IN ("pending","confirmed","completed")',
            [$guideId, $date]
        ) !== null) {
            return true;
        }
        return Database::first(
            'SELECT ga.id FROM guide_availability ga
             JOIN listings l ON l.id = ga.listing_id
             WHERE l.user_id = ? AND ga.blocked_date = ?',
            [$guideId, $date]
        ) !== null;
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
            'SELECT id FROM bookings WHERE listing_id = ? AND user_id = ? AND status IN ("completed","confirmed")',
            [$listingId, $userId]
        ) !== null;
    }

    public static function countForGuide(int $guideId): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS c FROM bookings b JOIN listings l ON l.id = b.listing_id WHERE l.user_id = ?',
            [$guideId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function revenueForGuide(int $guideId): float
    {
        $row = Database::first(
            'SELECT COALESCE(SUM(b.total_amount),0) AS total
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN payments p ON p.booking_id = b.id
             WHERE l.user_id = ? AND p.status = "paid" AND b.status IN ("confirmed", "completed")',
            [$guideId]
        );
        return (float) ($row['total'] ?? 0);
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
             JOIN payments p ON p.booking_id = b.id
             WHERE l.user_id = ? AND p.status = "paid" AND b.status IN ("confirmed", "completed")
               AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
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
            'SELECT AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
             FROM reviews r JOIN listings l ON l.id = r.listing_id WHERE l.user_id = ?',
            [$guideId]
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
}
