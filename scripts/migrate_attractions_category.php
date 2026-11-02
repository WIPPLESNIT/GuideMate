<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "=== GuideMate Category Migration: Attractions ===\n\n";

// 1. Check current categories
$categories = Database::all('SELECT id, name, slug FROM categories');
echo "Current categories:\n";
foreach ($categories as $c) {
    echo " - [ID {$c['id']}] {$c['name']} (slug: {$c['slug']})\n";
}

// 2. Update Category ID 2 to 'Attractions' / 'attractions'
Database::run(
    "UPDATE categories SET name = 'Attractions', slug = 'attractions', icon = 'ticket', description = 'Tours, heritage sites, and premier attractions across Cebu' WHERE id = 2"
);

echo "\nUpdated Category ID 2 to 'Attractions' (slug: 'attractions').\n";

// 3. Verify listings mapping
$listings = Database::all(
    "SELECT l.id, l.title, l.category_id, c.name AS category_name, c.slug AS category_slug 
     FROM listings l 
     JOIN categories c ON c.id = l.category_id 
     WHERE l.category_id IN (1, 2)"
);

echo "\nListings under Things to Do (ID 1) and Attractions (ID 2):\n";
foreach ($listings as $l) {
    echo " - [Listing ID {$l['id']}] '{$l['title']}' -> Category: '{$l['category_name']}' (slug: '{$l['category_slug']}')\n";
}

echo "\nDatabase migration completed successfully!\n";
