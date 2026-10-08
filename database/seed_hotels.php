<?php

declare(strict_types=1);

/**
 * Seed a set of real Cebu hotels (category "hotels") positioned near the
 * existing tour areas, so the mobile app can surface the NEAREST hotel to any
 * experience (e.g. La Joya Farm Resort & Spa for Bojo River).
 *
 * Safe to re-run: hotels are inserted only if their slug does not exist yet.
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

// ── 1) Ensure a hotel-partner owner account exists to own these listings ──
$ownerEmail = 'stays@guidemate.local';
$owner = Database::first('SELECT id FROM users WHERE email = ?', [$ownerEmail]);
if ($owner === null) {
    $ownerId = Database::insert(
        'INSERT INTO users (name, email, password, role, guide_status, is_active, location, bio)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            'GuideMate Stays',
            $ownerEmail,
            password_hash('Stays#' . bin2hex(random_bytes(4)), PASSWORD_DEFAULT),
            'hotel_admin',
            'approved',
            1,
            'Cebu, Philippines',
            'Verified accommodation partner on GuideMate.',
        ]
    );
    echo "Created hotel owner #$ownerId ($ownerEmail)\n";
} else {
    $ownerId = (int) $owner['id'];
    echo "Using existing hotel owner #$ownerId ($ownerEmail)\n";
}

// hotels category id
$cat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'");
if ($cat === null) {
    echo "ERROR: 'hotels' category not found.\n";
    return;
}
$categoryId = (int) $cat['id'];

// ── 2) The hotels, each placed near an existing tour area ──
$hotels = [
    [
        'title' => 'La Joya Farm Resort & Spa',
        'slug' => 'la-joya-farm-resort-spa',
        'summary' => 'Hilltop farm resort & spa minutes from the Bojo River, Aloguinsan.',
        'description' => "A tranquil hilltop retreat overlooking Aloguinsan and the Bojo River. Enjoy farm-to-table dining, an infinity pool, and full-service spa — the closest premium stay to the Bojo River eco-tour.",
        'area' => 'Aloguinsan, Cebu',
        'address' => 'Bojo, Aloguinsan, Cebu',
        'latitude' => 10.2246,
        'longitude' => 123.5585,
        'price' => 3500,
        'image' => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80',
    ],
    [
        'title' => 'Kasai Village Dive Resort',
        'slug' => 'kasai-village-dive-resort',
        'summary' => 'Beachfront dive resort on Panagsama Beach, Moalboal.',
        'description' => "Steps from the famous Moalboal sardine run and Panagsama Beach. Cozy rooms, a freediving school, and a seafront restaurant — perfect for snorkeling and diving trips.",
        'area' => 'Moalboal, Cebu',
        'address' => 'Panagsama Beach, Moalboal, Cebu',
        'latitude' => 9.9490,
        'longitude' => 123.3800,
        'price' => 2800,
        'image' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',
    ],
    [
        'title' => 'Badian Island Wellness Resort',
        'slug' => 'badian-island-wellness-resort',
        'summary' => 'Private island wellness resort near Kawasan Falls, Badian.',
        'description' => "An exclusive island resort with white-sand beach, wellness spa, and easy access to Kawasan Falls canyoneering. Ideal for a restful stay after your adventure.",
        'area' => 'Badian, Cebu',
        'address' => 'Badian Island, Badian, Cebu',
        'latitude' => 9.8000,
        'longitude' => 123.3700,
        'price' => 6500,
        'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80',
    ],
    [
        'title' => "MJ's Beach Resort Oslob",
        'slug' => 'mjs-beach-resort-oslob',
        'summary' => 'Simple beachfront rooms beside the Oslob whale shark point.',
        'description' => "Affordable beachfront rooms in Tan-awan, Oslob — a short walk from the whale shark watching area and Sumilon Island boats.",
        'area' => 'Oslob, Cebu',
        'address' => 'Tan-awan, Oslob, Cebu',
        'latitude' => 9.4590,
        'longitude' => 123.3880,
        'price' => 1800,
        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
    ],
    [
        'title' => 'Casa Nostra Mountain View',
        'slug' => 'casa-nostra-mountain-view',
        'summary' => 'Cool-climate mountain lodge near Osmeña Peak, Dalaguete.',
        'description' => "A cozy highland lodge in Mantalongon, Dalaguete — the jump-off to Osmeña Peak sunrise hikes and Casay Beach day trips.",
        'area' => 'Dalaguete, Cebu',
        'address' => 'Mantalongon, Dalaguete, Cebu',
        'latitude' => 9.7900,
        'longitude' => 123.5200,
        'price' => 2200,
        'image' => 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80',
    ],
    [
        'title' => 'Cebu Hilltop Hotel Busay',
        'slug' => 'cebu-hilltop-hotel-busay',
        'summary' => 'Panoramic city-view hotel in Busay, near Temple of Leah.',
        'description' => "Perched in Busay with sweeping views of Cebu City. Walking distance to Temple of Leah and Sirao Flower Garden — a great base for uptown sightseeing.",
        'area' => 'Busay, Cebu City',
        'address' => 'Busay, Cebu City',
        'latitude' => 10.3720,
        'longitude' => 123.8730,
        'price' => 3200,
        'image' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80',
    ],
];

$added = 0;
foreach ($hotels as $h) {
    $exists = Database::first('SELECT id FROM listings WHERE slug = ?', [$h['slug']]);
    if ($exists !== null) {
        echo "Skip (exists): {$h['title']}\n";
        continue;
    }
    Database::insert(
        'INSERT INTO listings
            (user_id, category_id, title, slug, summary, description, area, address, latitude, longitude,
             price, price_unit, duration, included, not_included, cover_image, status, is_featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $ownerId, $categoryId, $h['title'], $h['slug'], $h['summary'], $h['description'],
            $h['area'], $h['address'], $h['latitude'], $h['longitude'],
            $h['price'], 'per night', '', 'Room accommodation', 'Tours and meals unless stated',
            $h['image'], 'approved', 0,
        ]
    );
    echo "Added hotel: {$h['title']} ({$h['area']})\n";
    $added++;
}

echo "Done. $added hotel(s) added.\n";
