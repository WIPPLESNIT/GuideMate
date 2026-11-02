const fs = require('fs');
let c = fs.readFileSync('app/Models/Booking.php', 'utf8');

const target = `    public static function hasActivePaidBooking(int $listingId, int $userId, ?string $date = null): bool`;

const additions = `    /**
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

    public static function hasActivePaidBooking(int $listingId, int $userId, ?string $date = null): bool`;

c = c.replace(target, additions);

fs.writeFileSync('app/Models/Booking.php', c, 'utf8');
console.log('Booking.php updated with getActiveBookingForListing');
