<?php

declare(strict_types=1);

/**
 * Seeds famous Cebu "Things to Do" listings so they appear in the mobile app
 * (and become editable from the web admin).
 *
 * Usage:  php database/seed_things_to_do.php
 *
 * Idempotent: listings are matched by slug and skipped if they already exist.
 * Cover images must already be copied to public/uploads/listings/seed/.
 */

if (PHP_SAPI !== 'cli') {
    exit('Run from CLI: php database/seed_things_to_do.php');
}

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "Seeding Things to Do (Cebu)\n===========================\n";

// 1. Category: Things to Do
$category = Database::first('SELECT id FROM categories WHERE slug = ?', ['things-to-do']);
if ($category === null) {
    exit("✗ Category 'things-to-do' not found. Run database/migrate.php first.\n");
}
$categoryId = (int) $category['id'];

// 2. Owner: prefer an existing guide, otherwise any user (e.g. admin).
$owner = Database::first("SELECT id, name FROM users WHERE role = 'guide' ORDER BY id LIMIT 1")
    ?? Database::first('SELECT id, name FROM users ORDER BY id LIMIT 1');
if ($owner === null) {
    exit("✗ No users found. Register a guide first, then re-run.\n");
}
$ownerId = (int) $owner['id'];
echo "Owner: {$owner['name']} (id {$ownerId})\n";

// 3. The places.
$places = [
    [
        'title' => 'Moalboal Sardine Run',
        'slug' => 'moalboal-sardine-run',
        'summary' => 'Snorkel just off the shore with millions of sardines swirling in a massive bait ball.',
        'description' => "Panagsama Beach in Moalboal is famous for its year-round sardine run. Step off the shore and within minutes you are surrounded by millions of sardines moving as one giant, shimmering wall. Great for snorkelers and free divers, with sea turtles often spotted nearby.",
        'area' => 'Moalboal, Cebu',
        'price' => 500.00,
        'duration' => '2-3 hours',
        'lat' => 9.9466,
        'lng' => 123.3889,
        'image' => 'sardine.jpg',
        'included' => 'Snorkel gear, life vest, local guide',
    ],
    [
        'title' => 'Kawasan Falls Canyoneering',
        'slug' => 'kawasan-falls-canyoneering',
        'summary' => 'Jump, swim and trek through turquoise canyons to the iconic Kawasan Falls.',
        'description' => "One of Cebu's most thrilling adventures. Trek and swim through the crystal-clear turquoise river of Badian, leaping off cliffs and natural slides before arriving at the breathtaking Kawasan Falls. Helmets and life vests provided.",
        'area' => 'Badian, Cebu',
        'price' => 1500.00,
        'duration' => '4-5 hours',
        'lat' => 9.8189,
        'lng' => 123.3877,
        'image' => 'kawasan.jpg',
        'included' => 'Helmet, life vest, guide, entrance fees',
    ],
    [
        'title' => 'Oslob Whale Shark Watching',
        'slug' => 'oslob-whale-shark-watching',
        'summary' => 'Swim alongside gentle giant whale sharks in the waters of Oslob.',
        'description' => "Get up close with the gentle giants of the sea. In Oslob you can snorkel or dive beside butanding (whale sharks) in their natural feeding grounds. An unforgettable, once-in-a-lifetime encounter.",
        'area' => 'Oslob, Cebu',
        'price' => 1000.00,
        'duration' => '1-2 hours',
        'lat' => 9.4626,
        'lng' => 123.3787,
        'image' => 'whaleshark.jpg',
        'included' => 'Boat, snorkel gear, briefing',
    ],
    [
        'title' => 'Osmeña Peak Sunrise Trek',
        'slug' => 'osmena-peak-sunrise-trek',
        'summary' => 'Hike to the highest peak in Cebu for jagged hills and a sea of clouds.',
        'description' => "Osmeña Peak is the highest point in Cebu, offering a stunning panorama of jagged, saw-tooth hills that resemble Bohol's Chocolate Hills. A short but rewarding trek, best done at sunrise.",
        'area' => 'Dalaguete, Cebu',
        'price' => 800.00,
        'duration' => '3-4 hours',
        'lat' => 9.8167,
        'lng' => 123.3000,
        'image' => 'osmena.jpg',
        'included' => 'Local guide, registration fee',
    ],
    [
        'title' => "Magellan's Cross",
        'slug' => 'magellans-cross',
        'summary' => 'Visit the historic cross planted by Magellan in 1521 in the heart of Cebu City.',
        'description' => "A treasured Cebu landmark housed in a chapel beside the Basilica del Santo Niño. Magellan's Cross marks the spot where the first Cebuanos were baptized into Christianity in 1521. A must-see for history and culture lovers.",
        'area' => 'Cebu City, Cebu',
        'price' => 0.00,
        'duration' => '1 hour',
        'lat' => 10.2935,
        'lng' => 123.9018,
        'image' => 'magellan.jpg',
        'included' => 'Free public landmark',
    ],
    [
        'title' => 'Temple of Leah',
        'slug' => 'temple-of-leah',
        'summary' => 'Explore the grand "Taj Mahal of Cebu" with sweeping city views.',
        'description' => "A majestic Roman-inspired temple built as a symbol of undying love. The Temple of Leah features grand staircases, statues and galleries, with panoramic views over Cebu City — a favorite spot for photos.",
        'area' => 'Cebu City, Cebu',
        'price' => 120.00,
        'price_unit' => 'per entry',
        'duration' => '1-2 hours',
        'lat' => 10.3585,
        'lng' => 123.8470,
        'image' => 'templeofleah.jpg',
        'included' => 'Entrance fee',
    ],
    [
        'title' => 'Bantayan Island Getaway',
        'slug' => 'bantayan-island-getaway',
        'summary' => 'Relax on powdery white-sand beaches and crystal-clear turquoise water.',
        'description' => "Bantayan Island is a slice of paradise in northern Cebu, known for its long stretches of powdery white sand, laid-back island vibe and stunning sunsets. Perfect for swimming, island hopping and unwinding.",
        'area' => 'Bantayan Island, Cebu',
        'price' => 2500.00,
        'duration' => 'Full day',
        'lat' => 11.1530,
        'lng' => 123.7720,
        'image' => 'bantayan.jpg',
        'included' => 'Boat transfers, guide',
    ],
];

$inserted = 0;
$skipped = 0;

foreach ($places as $p) {
    $exists = Database::first('SELECT id FROM listings WHERE slug = ?', [$p['slug']]);
    if ($exists !== null) {
        echo "• Skipped (exists): {$p['title']}\n";
        $skipped++;
        continue;
    }

    Database::insert(
        'INSERT INTO listings
            (user_id, category_id, title, slug, summary, description, area, address,
             latitude, longitude, price, price_unit, duration, included, not_included,
             cover_image, status, is_featured)
         VALUES
            (:user_id, :category_id, :title, :slug, :summary, :description, :area, :address,
             :latitude, :longitude, :price, :price_unit, :duration, :included, :not_included,
             :cover_image, :status, :is_featured)',
        [
            'user_id' => $ownerId,
            'category_id' => $categoryId,
            'title' => $p['title'],
            'slug' => $p['slug'],
            'summary' => $p['summary'],
            'description' => $p['description'],
            'area' => $p['area'],
            'address' => $p['area'],
            'latitude' => $p['lat'],
            'longitude' => $p['lng'],
            'price' => $p['price'],
            'price_unit' => $p['price_unit'] ?? 'per person',
            'duration' => $p['duration'],
            'included' => $p['included'] ?? '',
            'not_included' => '',
            'cover_image' => 'uploads/listings/seed/' . $p['image'],
            'status' => 'approved',
            'is_featured' => 1,
        ]
    );
    echo "✓ Added: {$p['title']}\n";
    $inserted++;
}

echo "\nDone. Inserted {$inserted}, skipped {$skipped}.\n";
