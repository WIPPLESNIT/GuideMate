<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\User;
use App\Services\CancellationPolicyService;

echo "========================================================\n";
echo " GuideMate Hotel Check-in / Check-out Times Sync Tests\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertCond(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] $msg\n";
        $passed++;
    } else {
        echo " [FAIL] $msg\n";
        $failed++;
    }
}

// ---------------------------------------------------------
// Test 1: Parser Logic (Listing::parseCheckInOutTimes)
// ---------------------------------------------------------
echo "--- Test 1: Time Parsing & Formatting Logic ---\n";

$p1 = Listing::parseCheckInOutTimes('10:00PM - 12:00PM');
assertCond($p1['valid'] === true, '10:00PM - 12:00PM is recognized as valid');
assertCond($p1['check_in_time'] === '22:00:00', '10:00PM check-in is 22:00:00');
assertCond($p1['check_out_time'] === '12:00:00', '12:00PM check-out is 12:00:00');
assertCond($p1['check_in_time_label'] === '10:00 PM', '10:00 PM formatted check-in label');
assertCond($p1['check_out_time_label'] === '12:00 PM', '12:00 PM formatted check-out label');
assertCond($p1['check_in_out_label'] === '10:00 PM / 12:00 PM', 'Combined label 10:00 PM / 12:00 PM');
assertCond($p1['summary_label'] === 'Check-in from 10:00 PM · Check-out until 12:00 PM', 'Schedule summary label matches');

$p2 = Listing::parseCheckInOutTimes('Check-in 2:00 PM / Check-out 12:00 PM');
assertCond($p2['valid'] === true, 'Standard Check-in 2:00 PM / Check-out 12:00 PM is valid');
assertCond($p2['check_in_time'] === '14:00:00', '2:00 PM check-in is 14:00:00');
assertCond($p2['check_out_time'] === '12:00:00', '12:00 PM check-out is 12:00:00');

$p3 = Listing::parseCheckInOutTimes('3:00 PM - 11:00 AM');
assertCond($p3['check_in_time'] === '15:00:00', '3:00 PM check-in is 15:00:00');
assertCond($p3['check_out_time'] === '11:00:00', '11:00 AM check-out is 11:00:00');
assertCond($p3['check_in_time_label'] === '3:00 PM', '3:00 PM check-in label');
assertCond($p3['check_out_time_label'] === '11:00 AM', '11:00 AM check-out label');

$pEmpty = Listing::parseCheckInOutTimes('');
assertCond($pEmpty['valid'] === true, 'Empty duration defaults gracefully');
assertCond($pEmpty['check_in_time'] === '14:00:00', 'Default check-in is 14:00:00 (2:00 PM)');
assertCond($pEmpty['check_out_time'] === '12:00:00', 'Default check-out is 12:00:00 (12:00 PM)');

$pInvalid = Listing::parseCheckInOutTimes('unparseable string');
assertCond($pInvalid['valid'] === false, 'Invalid string flagged with valid = false');
assertCond($pInvalid['check_in_time'] === '14:00:00', 'Invalid string safely falls back to default 14:00:00');

// ---------------------------------------------------------
// Test 2: Database Persistence & Update Flow
// ---------------------------------------------------------
echo "\n--- Test 2: Database Persistence & Update Flow ---\n";

try {
    $hotelListing = Database::first("SELECT l.* FROM listings l JOIN categories c ON c.id = l.category_id WHERE c.slug = 'hotels' ORDER BY l.id DESC LIMIT 1");
    if (!$hotelListing) {
        $hotelCat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'") ?? ['id' => 3];
        $owner = Database::first("SELECT id FROM users WHERE role IN ('hotel_provider', 'admin') LIMIT 1") ?? ['id' => 1];
        $testListingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, address, price, price_unit, duration, status)
             VALUES (?, ?, 'Hotel Sync Test Property', 'hotel-sync-test-prop', 'Test summary', 'Test description', 'Moalboal', 'Panagsama', 1500.00, 'per night', 'Check-in 2:00 PM / Check-out 12:00 PM', 'approved')",
            [(int) $owner['id'], (int) $hotelCat['id']]
        );
        $hotelListing = Listing::find($testListingId);
    }

    $hotelId = (int) $hotelListing['id'];
    $originalDuration = $hotelListing['duration'] ?? 'Check-in 2:00 PM / Check-out 12:00 PM';

    // Simulate editing hotel times to "10:00PM - 12:00PM" (as shown in user screenshot)
    Database::run(
        "UPDATE listings SET duration = ? WHERE id = ?",
        ['10:00PM - 12:00PM', $hotelId]
    );

    $updatedHotel = Listing::find($hotelId);
    assertCond(($updatedHotel['duration'] ?? '') === '10:00PM - 12:00PM', 'Hotel duration correctly persisted as 10:00PM - 12:00PM');

    // ---------------------------------------------------------
    // Test 3: ApiController Payload Synchronization
    // ---------------------------------------------------------
    echo "\n--- Test 3: API Controller Payload Synchronization ---\n";

    $api = new \App\Controllers\ApiController();
    $reflection = new \ReflectionClass($api);
    $listingPayloadMethod = $reflection->getMethod('listingPayload');
    $listingPayloadMethod->setAccessible(true);

    $apiPayload = $listingPayloadMethod->invoke($api, $updatedHotel, true);
    assertCond($apiPayload['duration'] === '10:00PM - 12:00PM', 'API listing payload duration matches updated string');
    assertCond($apiPayload['check_in_time'] === '22:00:00', 'API listing payload has check_in_time = 22:00:00');
    assertCond($apiPayload['check_out_time'] === '12:00:00', 'API listing payload has check_out_time = 12:00:00');
    assertCond($apiPayload['check_in_time_label'] === '10:00 PM', 'API listing payload has check_in_time_label = 10:00 PM');
    assertCond($apiPayload['check_out_time_label'] === '12:00 PM', 'API listing payload has check_out_time_label = 12:00 PM');
    assertCond($apiPayload['check_in_out_label'] === '10:00 PM / 12:00 PM', 'API listing payload has check_in_out_label = 10:00 PM / 12:00 PM');

    // ---------------------------------------------------------
    // Test 4: Hotel Reservation Creation & Booking Times
    // ---------------------------------------------------------
    echo "\n--- Test 4: Booking Creation with Dynamic Times ---\n";

    $room = Database::first("SELECT * FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [$hotelId]);
    if (!$room) {
        $roomId = Database::insert(
            "INSERT INTO hotel_rooms (listing_id, name, price_per_night, deposit_percentage, status)
             VALUES (?, 'Test Deluxe Suite', 2000.00, 20, 'available')",
            [$hotelId]
        );
        $room = HotelRoom::find($roomId);
    }
    $roomId = (int) $room['id'];
    $tourist = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1") ?? ['id' => 1];
    $touristId = (int) $tourist['id'];

    $checkInDate = date('Y-m-d', strtotime('+7 days'));
    $checkOutDate = date('Y-m-d', strtotime('+9 days'));

    $bookingId = Booking::createHotelReservation(
        $hotelId,
        $roomId,
        $touristId,
        $checkInDate,
        $checkOutDate,
        2,
        2,
        4000.00,
        800.00,
        3200.00,
        'Test reservation with dynamic check-in times',
        '22:00:00',
        '12:00:00'
    );

    $bookingRow = Booking::find($bookingId);
    assertCond($bookingRow !== null, 'Booking successfully created in database');
    assertCond(($bookingRow['booking_time'] ?? '') === '22:00:00', 'Booking stored hotel check-in time 22:00:00');
    assertCond(($bookingRow['booking_end_time'] ?? '') === '12:00:00', 'Booking stored hotel check-out time 12:00:00');

    $bookingPayloadMethod = $reflection->getMethod('bookingPayload');
    $bookingPayloadMethod->setAccessible(true);
    $bPayload = $bookingPayloadMethod->invoke($api, $bookingRow, $touristId);

    assertCond($bPayload['check_in_time_label'] === '10:00 PM', 'Booking API payload check_in_time_label is 10:00 PM');
    assertCond($bPayload['check_out_time_label'] === '12:00 PM', 'Booking API payload check_out_time_label is 12:00 PM');
    assertCond($bPayload['check_in_out_label'] === '10:00 PM / 12:00 PM', 'Booking API payload check_in_out_label is 10:00 PM / 12:00 PM');

    // ---------------------------------------------------------
    // Test 5: Cancellation Policy Evaluation uses Hotel Check-in Time
    // ---------------------------------------------------------
    echo "\n--- Test 5: Cancellation Policy respects Hotel Check-in Time ---\n";

    $policy = CancellationPolicyService::compute($bookingRow, 'hotel');
    assertCond(str_contains($policy['scheduled_start'] ?? '', '22:00:00'), 'Cancellation scheduled start uses hotel check-in time 22:00:00');
    assertCond(str_contains($policy['deadline'] ?? '', '22:00:00'), '72h cancellation deadline computed relative to 22:00:00');

    // Clean up test booking
    Database::run("DELETE FROM bookings WHERE id = ?", [$bookingId]);

    // Restore original duration
    Database::run("UPDATE listings SET duration = ? WHERE id = ?", [$originalDuration, $hotelId]);
} catch (\Throwable $e) {
    echo " [ERROR] Exception during tests: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
}

// ---------------------------------------------------------
// Test 6: Mobile and Web UI Static Code Inspection
// ---------------------------------------------------------
echo "\n--- Test 6: Mobile & Web UI Code Inspection ---\n";

$slugTsx = file_get_contents(__DIR__ . '/../app/listing/[slug].tsx');
assertCond(strpos($slugTsx, 'listing.check_in_out_label') !== false, 'app/listing/[slug].tsx uses listing.check_in_out_label');
assertCond(strpos($slugTsx, 'listing.check_in_time_label') !== false, 'app/listing/[slug].tsx uses listing.check_in_time_label');
assertCond(strpos($slugTsx, 'listing.check_out_time_label') !== false, 'app/listing/[slug].tsx uses listing.check_out_time_label');

$tripsTsx = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');
assertCond(strpos($tripsTsx, 'selectedBookingDetails?.check_in_time_label') !== false, 'app/(tabs)/trips.tsx uses check_in_time_label');
assertCond(strpos($tripsTsx, 'selectedBookingDetails?.check_out_time_label') !== false, 'app/(tabs)/trips.tsx uses check_out_time_label');

$apiTs = file_get_contents(__DIR__ . '/../services/api.ts');
assertCond(strpos($apiTs, 'check_in_time_label?: string | null') !== false, 'services/api.ts includes check_in_time_label');
assertCond(strpos($apiTs, 'check_out_time_label?: string | null') !== false, 'services/api.ts includes check_out_time_label');

$webShow = file_get_contents(__DIR__ . '/../app/Views/listings/show.php');
assertCond(strpos($webShow, 'parseCheckInOutTimes') !== false, 'Web show.php uses parseCheckInOutTimes');

$webCheckout = file_get_contents(__DIR__ . '/../app/Views/bookings/checkout.php');
assertCond(strpos($webCheckout, 'parseCheckInOutTimes') !== false, 'Web checkout.php uses parseCheckInOutTimes');

echo "\n========================================================\n";
echo " Test Summary: $passed Passed, $failed Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
