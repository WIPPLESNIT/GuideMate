<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\HotelRoom;

$hotels = Database::all(
    "SELECT l.id, l.title, l.price 
     FROM listings l 
     JOIN categories c ON c.id = l.category_id 
     WHERE c.slug = 'hotels'"
);

echo "Found " . count($hotels) . " hotel listings to sync.\n";

foreach ($hotels as $h) {
    $hotelId = (int) $h['id'];
    $oldPrice = (float) $h['price'];
    $newPrice = HotelRoom::syncHotelStartingPrice($hotelId);
    echo "Hotel #{$hotelId} [{$h['title']}]: old ₱{$oldPrice} -> synced ₱" . ($newPrice !== null ? number_format($newPrice, 2) : '0.00 (Price unavailable)') . "\n";
}

echo "Hotel starting price sync completed successfully.\n";
