<?php

require __DIR__ . '/patch_helper.php';

// Add createHotelReservation right after create()
$createHotelMethod = '    public static function createHotelReservation(
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
        string $notes = ""
    ): int {
        $token = bin2hex(random_bytes(16));
        return Database::insert(
            "INSERT INTO bookings 
                (listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status, notes, verify_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \"deposit_paid\", \"pending\", \"pending\", ?, ?)",
            [
                $listingId,
                $roomId,
                $userId,
                $checkIn,
                $checkIn,
                $checkOut,
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
';

patchFile(
    'app/Models/Booking.php',
    '        return $id;' . "\n" . '    }',
    '        return $id;' . "\n" . '    }' . "\n\n" . $createHotelMethod
);

patchFile(
    'app/Models/Booking.php',
    'SELECT b.*, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.user_id AS guide_id,
                    l.price_unit, u.name AS customer_name, u.email AS customer_email
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             WHERE b.id = ?',
    'SELECT b.*, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.user_id AS guide_id,
                    l.price_unit, hr.name AS room_name, hr.price_per_night AS room_price,
                    u.name AS customer_name, u.email AS customer_email
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             LEFT JOIN hotel_rooms hr ON hr.id = b.room_id
             WHERE b.id = ?'
);

patchFile(
    'app/Models/Booking.php',
    'SELECT b.*, l.title AS listing_title, l.slug AS listing_slug,
                    u.name AS customer_name, p.status AS payment_status
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             LEFT JOIN payments p ON p.booking_id = b.id
             WHERE l.user_id = ?',
    'SELECT b.*, l.title AS listing_title, l.slug AS listing_slug, hr.name AS room_name,
                    u.name AS customer_name, u.email AS customer_email, p.status AS payment_status
             FROM bookings b
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = b.user_id
             LEFT JOIN hotel_rooms hr ON hr.id = b.room_id
             LEFT JOIN payments p ON p.booking_id = b.id
             WHERE l.user_id = ?'
);
