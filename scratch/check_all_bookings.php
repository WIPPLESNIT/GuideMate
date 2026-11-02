<?php
require_once __DIR__ . '/../bootstrap.php';
$rows = App\Core\Database::all('SELECT b.id, b.listing_id, l.title, b.booking_date, b.guests, b.status FROM bookings b JOIN listings l ON l.id = b.listing_id ORDER BY b.id DESC LIMIT 15');
print_r($rows);
