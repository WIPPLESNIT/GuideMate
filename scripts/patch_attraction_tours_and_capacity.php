<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "=== GuideMate Migration: Attraction & Tours + Listing Capacity ===\n";

// 1. Update Category ID 2
Database::run("UPDATE categories SET name = 'Attraction & Tours', slug = 'attraction-and-tours' WHERE id = 2");
echo "1. Updated Category ID 2 to 'Attraction & Tours' (slug: 'attraction-and-tours').\n";

// 2. Add capacity column to listings table if not present
$col = Database::first("SHOW COLUMNS FROM listings LIKE 'capacity'");
if (!$col) {
    Database::run("ALTER TABLE listings ADD COLUMN capacity INT UNSIGNED NOT NULL DEFAULT 10 AFTER duration");
    echo "2. Added 'capacity' column to 'listings' table.\n";
} else {
    echo "2. 'capacity' column already exists in 'listings' table.\n";
}

// 3. Backfill any listing that might have capacity 0 or null
Database::run("UPDATE listings SET capacity = 10 WHERE capacity IS NULL OR capacity = 0");
echo "3. Backfilled listing capacity defaults.\n";

// Verification
$categories = Database::all("SELECT id, name, slug FROM categories ORDER BY id");
echo "\nCurrent Categories:\n";
foreach ($categories as $cat) {
    echo " - [ID {$cat['id']}] {$cat['name']} (slug: {$cat['slug']})\n";
}

$colCheck = Database::first("SHOW COLUMNS FROM listings LIKE 'capacity'");
echo "\nListings 'capacity' column:\n";
print_r($colCheck);

echo "\nMigration completed successfully!\n";

// Normalize line endings
$filesToNormalize = [
    __DIR__ . '/../app/Models/Listing.php',
    __DIR__ . '/../app/Models/Category.php',
    __DIR__ . '/../app/Controllers/GuideController.php',
    __DIR__ . '/../app/Views/guide/listings.php',
    __DIR__ . '/../app/Views/guide/form.php',
    __DIR__ . '/../app/Controllers/ApiController.php',
];
foreach ($filesToNormalize as $f) {
    if (file_exists($f)) {
        file_put_contents($f, str_replace("\r\n", "\n", file_get_contents($f)));
        echo "Normalized $f\n";
    }
}

file_put_contents(__DIR__ . '/../app/Controllers/BookingController.php', str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../app/Controllers/BookingController.php')));
echo "Normalized BookingController.php\n";

file_put_contents(__DIR__ . '/../app/(tabs)/index.tsx', str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../app/(tabs)/index.tsx')));
echo "Normalized index.tsx\n";

file_put_contents(__DIR__ . '/../app/listing/[slug].tsx', str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../app/listing/[slug].tsx')));
echo "Normalized [slug].tsx\n";
