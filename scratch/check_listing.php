<?php
require_once __DIR__ . '/../bootstrap.php';
$l = App\Core\Database::first('SELECT id, title, capacity, price, price_unit FROM listings WHERE title LIKE ?', ['%Moalboal%']);
print_r($l);
$b = App\Core\Database::all('SELECT id, listing_id, booking_date, guests, status FROM bookings WHERE listing_id = ?', [$l['id']]);
print_r($b);
