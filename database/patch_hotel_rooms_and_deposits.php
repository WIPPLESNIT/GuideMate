<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "=== Applying Hotel Rooms & Partial Deposit Migration ===\n";

// 1. Create hotel_rooms table
Database::run("
CREATE TABLE IF NOT EXISTS `hotel_rooms` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `description` TEXT NULL,
    `room_size` VARCHAR(60) DEFAULT '32 m²',
    `bed_type` VARCHAR(80) DEFAULT '1 King Bed',
    `max_guests` INT UNSIGNED DEFAULT 2,
    `price_per_night` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deposit_percentage` INT UNSIGNED NOT NULL DEFAULT 20,
    `total_units` INT UNSIGNED NOT NULL DEFAULT 1,
    `available_units` INT UNSIGNED NOT NULL DEFAULT 1,
    `status` ENUM('available', 'unavailable', 'blocked', 'fully_booked') NOT NULL DEFAULT 'available',
    `amenities` TEXT NULL,
    `image` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_rooms_listing` (`listing_id`),
    KEY `idx_hotel_rooms_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ hotel_rooms table verified / created.\n";

// 2. Add columns to bookings table if they do not exist
$columns = [
    'room_id' => "ALTER TABLE `bookings` ADD COLUMN `room_id` INT UNSIGNED NULL AFTER `listing_id`",
    'check_in_date' => "ALTER TABLE `bookings` ADD COLUMN `check_in_date` DATE NULL AFTER `booking_date`",
    'check_out_date' => "ALTER TABLE `bookings` ADD COLUMN `check_out_date` DATE NULL AFTER `check_in_date`",
    'nights' => "ALTER TABLE `bookings` ADD COLUMN `nights` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `check_out_date`",
    'deposit_amount' => "ALTER TABLE `bookings` ADD COLUMN `deposit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `total_amount`",
    'balance_amount' => "ALTER TABLE `bookings` ADD COLUMN `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `deposit_amount`",
    'payment_stage' => "ALTER TABLE `bookings` ADD COLUMN `payment_stage` ENUM('full', 'deposit_paid', 'balance_due', 'settled') NOT NULL DEFAULT 'full' AFTER `balance_amount`",
    'hotel_status' => "ALTER TABLE `bookings` ADD COLUMN `hotel_status` ENUM('pending', 'deposit_paid', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show') NULL AFTER `status`",
];

foreach ($columns as $col => $sql) {
    $exists = Database::first("
        SELECT COUNT(*) as cnt 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'bookings' 
          AND COLUMN_NAME = ?
    ", [$col]);

    if ((int) ($exists['cnt'] ?? 0) === 0) {
        Database::run($sql);
        echo "✓ Added column bookings.{$col}\n";
    } else {
        echo "· Column bookings.{$col} already exists.\n";
    }
}

// 3. Seed initial rooms for the existing 6 hotels
$seedData = [
    'la-joya-farm-resort-spa' => [
        [
            'name' => 'Premier Hilltop Villa',
            'description' => 'Overlooking the lush rolling hills of Aloguinsan. Features a private sunset balcony, handcrafted timber furnishings, and organic bath amenities.',
            'room_size' => '42 m² (452 sq ft)',
            'bed_type' => '1 King Bed',
            'max_guests' => 2,
            'price_per_night' => 3500.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Air Conditioning, Free High-Speed Wi-Fi, Private Hilltop Balcony, Hot Rain Shower, Mini-Bar, Daily Organic Breakfast for 2',
            'image' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80',
        ],
        [
            'name' => 'Farm View Family Cottage',
            'description' => 'Spacious standalone cottage surrounded by organic farm gardens. Ideal for families and small groups visiting the Bojo River eco-tour.',
            'room_size' => '58 m² (624 sq ft)',
            'bed_type' => '2 Queen Beds',
            'max_guests' => 4,
            'price_per_night' => 4800.00,
            'deposit_percentage' => 25,
            'status' => 'available',
            'amenities' => 'Air Conditioning, Free Wi-Fi, Garden Patio, Hot Shower, Kitchenette, Tea & Coffee Maker, Farm Tour Pass',
            'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80',
        ],
        [
            'name' => 'Eco-Glamping Suite',
            'description' => 'A cozy safari-style glamping tent on a raised wooden platform. Perfect for nature lovers wanting peaceful night breezes.',
            'room_size' => '28 m² (301 sq ft)',
            'bed_type' => '1 Queen Bed',
            'max_guests' => 2,
            'price_per_night' => 2600.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Silent Eco-Fan & Inverter AC, Private Open-Air Rain Shower, Stargazing Deck, Free Wi-Fi at Lounge',
            'image' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',
        ],
    ],
    'kasai-village-dive-resort' => [
        [
            'name' => 'Deluxe Oceanfront Bungalow',
            'description' => 'Step directly out toward the Tañon Strait. Features breathtaking sunset views, high ceiling bamboo accents, and dive equipment drying racks.',
            'room_size' => '38 m² (409 sq ft)',
            'bed_type' => '1 King Bed',
            'max_guests' => 2,
            'price_per_night' => 2800.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Direct Beachfront, Starlink Wi-Fi (150 Mbps), Inverter AC, Hot Rain Shower, Mini-Fridge, Free Snorkel Gear',
            'image' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',
        ],
        [
            'name' => 'Sea View Diver Suite',
            'description' => 'Upper-level suite with panoramic views of Pescador Island. Includes dedicated gear storage, workstation, and daily breakfast.',
            'room_size' => '46 m² (495 sq ft)',
            'bed_type' => '1 King Bed + 1 Daybed',
            'max_guests' => 3,
            'price_per_night' => 3600.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Panoramic Sea View, Work Desk, Air Conditioning, Safe, Tea & Coffee, Dive Center Priority Booking',
            'image' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80',
        ],
        [
            'name' => 'Standard Garden Room',
            'description' => 'Tranquil room nestled in the resort tropical garden. Quiet, cool, and comfortable after an active day of diving.',
            'room_size' => '30 m² (322 sq ft)',
            'bed_type' => '2 Twin Beds',
            'max_guests' => 2,
            'price_per_night' => 2200.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Garden View, Silent AC, En-suite Bathroom with Hot Water, Free Wi-Fi, Fresh Beach Towels',
            'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
        ],
    ],
    'badian-island-wellness-resort' => [
        [
            'name' => 'Thalasso Island Pool Villa',
            'description' => 'Ultra-luxurious private island villa with ionized seawater pool and unobstructed vistas of the Badian bay and mountains.',
            'room_size' => '65 m² (700 sq ft)',
            'bed_type' => '1 King Bed',
            'max_guests' => 2,
            'price_per_night' => 6500.00,
            'deposit_percentage' => 30,
            'status' => 'available',
            'amenities' => 'Private Plunge Pool, Butler Service, Luxury Soaking Tub, High-speed Wi-Fi, Spa Treatments Discount, Free Island Boat Transfer',
            'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80',
        ],
        [
            'name' => 'Deluxe Island Badian Suite',
            'description' => 'Spacious open-concept suite adorned with native marble and Philippine hardwood. Expansive balcony overlooking the white sand beach.',
            'room_size' => '52 m² (560 sq ft)',
            'bed_type' => '1 King Bed + 1 Daybed',
            'max_guests' => 3,
            'price_per_night' => 5200.00,
            'deposit_percentage' => 25,
            'status' => 'available',
            'amenities' => 'Sea View Balcony, Air Conditioning, Marble Bathroom, Free Breakfast for 2, Organic Toiletries',
            'image' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80',
        ],
    ],
    'mjs-beach-resort-oslob' => [
        [
            'name' => 'Beachfront Standard Queen',
            'description' => 'Clean, cozy beachfront room 5 minutes from the Tan-awan whale shark briefing center. Listen to the ocean waves as you sleep.',
            'room_size' => '24 m² (258 sq ft)',
            'bed_type' => '1 Queen Bed',
            'max_guests' => 2,
            'price_per_night' => 1800.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Beachfront Access, Air Conditioning, Hot Shower, Cable TV, Wi-Fi in Public Areas, Early Whale Shark Queue Assistance',
            'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
        ],
        [
            'name' => 'Family Seaside Suite',
            'description' => 'Two double beds with ample floor space for luggage and gear. Perfect jump-off point for Sumilon Island day trips.',
            'room_size' => '36 m² (387 sq ft)',
            'bed_type' => '2 Double Beds',
            'max_guests' => 4,
            'price_per_night' => 2800.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Air Conditioning, Refrigerator, Private Bathroom, Sea View Porch, Free Parking',
            'image' => 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80',
        ],
    ],
    'casa-nostra-mountain-view' => [
        [
            'name' => 'Highland Mountain Cabin',
            'description' => 'Rustic wooden cabin in cool Mantalongon. Enjoy the mountain breeze and stunning sunrises before hiking Osmeña Peak.',
            'room_size' => '26 m² (280 sq ft)',
            'bed_type' => '1 Queen Bed',
            'max_guests' => 2,
            'price_per_night' => 2200.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Mountain View Deck, Hot Shower, Electric Kettle & Tea Selection, Mountain Trekking Guide Referral',
            'image' => 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80',
        ],
        [
            'name' => 'Osmeña Peak View Family Room',
            'description' => 'Generously sized highland room with picture windows framing Dalaguete’s jagged limestone peaks.',
            'room_size' => '40 m² (430 sq ft)',
            'bed_type' => '2 Queen Beds',
            'max_guests' => 4,
            'price_per_night' => 3200.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Balcony with Peak View, Hot Water Shower, Dining Table, Wi-Fi, Complimentary Native Hot Chocolate',
            'image' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80',
        ],
    ],
    'cebu-hilltop-hotel-busay' => [
        [
            'name' => 'Skyline City View Deluxe',
            'description' => 'Perched on the Busay hillside with sparkling night views of Cebu City. Minutes from Temple of Leah and Tops Lookout.',
            'room_size' => '32 m² (344 sq ft)',
            'bed_type' => '1 King Bed',
            'max_guests' => 2,
            'price_per_night' => 3200.00,
            'deposit_percentage' => 20,
            'status' => 'available',
            'amenities' => 'Panoramic Skyline View, 43" Smart TV with Netflix, High-Speed Wi-Fi, Air Conditioning, Rainfall Shower',
            'image' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80',
        ],
        [
            'name' => 'Executive Busay Suite',
            'description' => 'Corner suite featuring wrap-around floor-to-ceiling glass windows, living area, and private jacuzzi tub.',
            'room_size' => '48 m² (516 sq ft)',
            'bed_type' => '1 King Bed + 1 Sofa Bed',
            'max_guests' => 3,
            'price_per_night' => 4200.00,
            'deposit_percentage' => 25,
            'status' => 'available',
            'amenities' => 'Private Jacuzzi, City View Terrace, Mini Bar, Bathrobes & Slippers, Breakfast for 2',
            'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80',
        ],
    ],
];

$totalSeeded = 0;
foreach ($seedData as $slug => $rooms) {
    $hotel = Database::first("SELECT id, title FROM listings WHERE slug = ?", [$slug]);
    if ($hotel === null) {
        continue;
    }
    $listingId = (int) $hotel['id'];

    foreach ($rooms as $r) {
        $existing = Database::first(
            "SELECT id FROM hotel_rooms WHERE listing_id = ? AND name = ?",
            [$listingId, $r['name']]
        );

        if ($existing === null) {
            Database::insert("
                INSERT INTO hotel_rooms 
                (listing_id, name, description, room_size, bed_type, max_guests, price_per_night, deposit_percentage, status, amenities, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $listingId,
                $r['name'],
                $r['description'],
                $r['room_size'],
                $r['bed_type'],
                $r['max_guests'],
                $r['price_per_night'],
                $r['deposit_percentage'],
                $r['status'],
                $r['amenities'],
                $r['image'],
            ]);
            $totalSeeded++;
            echo "  + Added room '{$r['name']}' for {$hotel['title']}\n";
        }
    }
}

echo "✓ Seeded {$totalSeeded} room types.\n";
echo "=== Migration Complete ===\n";
