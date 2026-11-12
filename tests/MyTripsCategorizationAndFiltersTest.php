<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Controllers\ApiController;

echo "=== GuideMate My Trips Categorization & Filters Verification Tests ===\n\n";

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

$api = new ApiController();
$refBookingPayload = new ReflectionMethod($api, 'bookingPayload');

// --------------------------------------------------------------------
// TEST 1: Booking::find includes category_slug
// --------------------------------------------------------------------
echo "1. Testing Booking::find returns category_slug...\n";
$kawasanBooking = Database::first("SELECT id FROM bookings WHERE listing_id = 1193 LIMIT 1");
if ($kawasanBooking !== null) {
    $found = Booking::find((int) $kawasanBooking['id']);
    assertCondition($found !== null, "Booking::find succeeds");
    assertCondition(isset($found['category_slug']) && $found['category_slug'] === 'things-to-do', "Booking::find contains category_slug 'things-to-do'");
}

// --------------------------------------------------------------------
// TEST 2: ApiController::bookingPayload returns correct kind and is_hotel
// --------------------------------------------------------------------
echo "\n2. Testing ApiController::bookingPayload classifications...\n";

// A. Things to Do Booking (Kawasan Falls #459 / 1193)
$thingsBooking = Database::first("SELECT b.*, c.slug AS category_slug, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.area, l.duration AS listing_duration, l.address AS listing_address, l.included AS listing_included, l.description AS listing_description, u.id AS guide_id, u.name AS guide_name, u.avatar AS guide_avatar, u.phone AS guide_phone, hr.name AS room_name FROM bookings b LEFT JOIN listings l ON l.id = b.listing_id LEFT JOIN categories c ON c.id = l.category_id LEFT JOIN users u ON u.id = l.user_id LEFT JOIN hotel_rooms hr ON hr.id = b.room_id WHERE l.category_id = 1 ORDER BY b.id DESC LIMIT 1");

if ($thingsBooking !== null) {
    $p = $refBookingPayload->invoke($api, $thingsBooking, (int) $thingsBooking['user_id']);
    assertCondition($p['kind'] === 'activity', "Things to Do booking is classified as kind: 'activity'");
    assertCondition($p['is_hotel'] === false, "Things to Do booking has is_hotel === false");
    assertCondition($p['is_package'] === false, "Things to Do booking has is_package === false");
    assertCondition($p['deposit_amount'] === 0.0, "Things to Do booking has deposit_amount === 0.0");
    assertCondition($p['category_slug'] === 'things-to-do', "Things to Do booking has category_slug === 'things-to-do'");
}

// B. Attractions Booking (Magellan's Cross #458 / 1196)
$attrBooking = Database::first("SELECT b.*, c.slug AS category_slug, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.area, l.duration AS listing_duration, l.address AS listing_address, l.included AS listing_included, l.description AS listing_description, u.id AS guide_id, u.name AS guide_name, u.avatar AS guide_avatar, u.phone AS guide_phone, hr.name AS room_name FROM bookings b LEFT JOIN listings l ON l.id = b.listing_id LEFT JOIN categories c ON c.id = l.category_id LEFT JOIN users u ON u.id = l.user_id LEFT JOIN hotel_rooms hr ON hr.id = b.room_id WHERE l.category_id = 2 ORDER BY b.id DESC LIMIT 1");

if ($attrBooking !== null) {
    $p = $refBookingPayload->invoke($api, $attrBooking, (int) $attrBooking['user_id']);
    assertCondition($p['kind'] === 'attraction', "Attraction booking is classified as kind: 'attraction'");
    assertCondition($p['is_hotel'] === false, "Attraction booking has is_hotel === false");
    assertCondition($p['is_package'] === false, "Attraction booking has is_package === false");
    assertCondition($p['deposit_amount'] === 0.0, "Attraction booking has deposit_amount === 0.0");
    assertCondition($p['category_slug'] === 'attractions', "Attraction booking has category_slug === 'attractions'");
}

// C. Hotel Booking (Bluewater Maribago #460 / #431 / 1211)
$hotelBooking = Database::first("SELECT b.*, c.slug AS category_slug, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.area, l.duration AS listing_duration, l.address AS listing_address, l.included AS listing_included, l.description AS listing_description, u.id AS guide_id, u.name AS guide_name, u.avatar AS guide_avatar, u.phone AS guide_phone, hr.name AS room_name FROM bookings b LEFT JOIN listings l ON l.id = b.listing_id LEFT JOIN categories c ON c.id = l.category_id LEFT JOIN users u ON u.id = l.user_id LEFT JOIN hotel_rooms hr ON hr.id = b.room_id WHERE l.category_id = 3 ORDER BY b.id DESC LIMIT 1");

if ($hotelBooking !== null) {
    $p = $refBookingPayload->invoke($api, $hotelBooking, (int) $hotelBooking['user_id']);
    assertCondition($p['kind'] === 'hotel', "Hotel booking is classified as kind: 'hotel'");
    assertCondition($p['is_hotel'] === true, "Hotel booking has is_hotel === true");
    assertCondition($p['is_package'] === false, "Hotel booking has is_package === false");
    assertCondition($p['category_slug'] === 'hotels', "Hotel booking has category_slug === 'hotels'");
}

// D. Package Booking (3D2N South Cebu #430 / 1212)
$pkgBooking = Database::first("SELECT b.*, c.slug AS category_slug, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.area, l.duration AS listing_duration, l.address AS listing_address, l.included AS listing_included, l.description AS listing_description, u.id AS guide_id, u.name AS guide_name, u.avatar AS guide_avatar, u.phone AS guide_phone, hr.name AS room_name FROM bookings b LEFT JOIN listings l ON l.id = b.listing_id LEFT JOIN categories c ON c.id = l.category_id LEFT JOIN users u ON u.id = l.user_id LEFT JOIN hotel_rooms hr ON hr.id = b.room_id WHERE l.category_id = 5 OR b.notes LIKE '%package_booking%' ORDER BY b.id DESC LIMIT 1");

if ($pkgBooking !== null) {
    $p = $refBookingPayload->invoke($api, $pkgBooking, (int) $pkgBooking['user_id']);
    assertCondition($p['kind'] === 'package', "Package booking is classified as kind: 'package'");
    assertCondition($p['is_package'] === true, "Package booking has is_package === true");
    assertCondition($p['is_hotel'] === false, "Package booking has is_hotel === false");
}

// --------------------------------------------------------------------
// TEST 3: User Customer Bookings Filtering
// --------------------------------------------------------------------
echo "\n3. Testing Tourist Bookings Filtering & Category Segregation...\n";
$touristBookings = Booking::forCustomer(2475);
$payloads = array_map(fn($b) => $refBookingPayload->invoke($api, $b, 2475), $touristBookings);

$packageBookings = array_filter($payloads, fn($p) => $p['kind'] === 'package');
$hotelBookings = array_filter($payloads, fn($p) => $p['kind'] === 'hotel');
$activityBookings = array_filter($payloads, fn($p) => $p['kind'] === 'activity');
$attractionBookings = array_filter($payloads, fn($p) => $p['kind'] === 'attraction');

// Verify that hotel bookings ONLY contain actual hotels
foreach ($hotelBookings as $h) {
    assertCondition($h['category_slug'] === 'hotels' || $h['is_hotel'] === true, "Hotel booking '{$h['listing_title']}' is actually a hotel");
    assertCondition($h['category_slug'] !== 'things-to-do', "Hotel filter does NOT contain Things to Do");
    assertCondition($h['category_slug'] !== 'attractions', "Hotel filter does NOT contain Attractions");
}

// Verify that activity bookings ONLY contain Things to Do
foreach ($activityBookings as $act) {
    assertCondition($act['category_slug'] === 'things-to-do', "Activity booking '{$act['listing_title']}' has category 'things-to-do'");
    assertCondition($act['is_hotel'] === false, "Activity booking is not a hotel");
}

// Verify that attraction bookings ONLY contain Attractions
foreach ($attractionBookings as $attr) {
    assertCondition($attr['category_slug'] === 'attractions', "Attraction booking '{$attr['listing_title']}' has category 'attractions'");
    assertCondition($attr['is_hotel'] === false, "Attraction booking is not a hotel");
}

echo "\n=======================================================\n";
echo "Test Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
