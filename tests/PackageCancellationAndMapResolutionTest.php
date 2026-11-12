<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Core\Geo;
use App\Models\Booking;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Listing;
use App\Services\PackageBookingService;
use App\Services\CancellationPolicyService;

echo "=== GuideMate Package Cancellation & Map Resolution Tests ===\n\n";

$passed = 0;
$failed = 0;

function assertTest(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo "  [PASS] {$msg}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$msg}\n";
        $failed++;
    }
}

// ---------------------------------------------------------------------
// TEST 1: Missing Location Messages Match Exact Requirements
// ---------------------------------------------------------------------
echo "1. Testing Missing and Unresolvable Location Messages...\n";
assertTest(
    Geo::missingLocationMessage('hotels') === 'No address is attached to this hotel.',
    'Hotel missing message matches prompt'
);
assertTest(
    Geo::missingLocationMessage('attractions') === 'No address is attached to this attraction.',
    'Attraction missing message matches prompt'
);
assertTest(
    Geo::missingLocationMessage('things-to-do') === 'No address is attached to this activity.',
    'Things-to-do missing message matches prompt'
);
assertTest(
    Geo::missingLocationMessage('rentals') === 'No pickup location is attached to this vehicle.',
    'Vehicle rental missing message matches prompt'
);
assertTest(
    Geo::missingLocationMessage('hotels', true) === 'The saved address could not be resolved to a valid map location.',
    'Unresolvable address message matches prompt'
);

// ---------------------------------------------------------------------
// TEST 2: Valid and Approximate Coordinates Resolution
// ---------------------------------------------------------------------
echo "\n2. Testing Coordinate Validation & Resolution...\n";
assertTest(
    Geo::isValidCoordinate(10.3157, 123.8854),
    'Valid Cebu coordinates recognized as valid'
);
assertTest(
    !Geo::isValidCoordinate(null, 123.8854),
    'Null latitude correctly flagged as invalid'
);
assertTest(
    !Geo::isValidCoordinate(0.0, 0.0),
    'Null island (0, 0) correctly flagged as invalid'
);
assertTest(
    !Geo::isValidCoordinate(51.5074, -0.1278),
    'London coordinates outside Cebu region correctly flagged as invalid'
);

// Listing with valid DB coordinates
$testListingWithCoords = [
    'id' => 99991,
    'latitude' => 10.3157,
    'longitude' => 123.8854,
    'address' => '',
    'area' => 'Cebu City',
    'title' => 'Test Listing',
];
$coords = Geo::forListing($testListingWithCoords);
assertTest(
    $coords !== null && abs($coords['latitude'] - 10.3157) < 0.0001,
    'Listing with valid DB coordinates resolves accurately without dummy fallback'
);

// Listing without address or coordinates
$testListingEmpty = [
    'id' => 99992,
    'latitude' => null,
    'longitude' => null,
    'address' => '',
    'area' => '',
    'title' => 'Empty Listing',
];
$coordsEmpty = Geo::forListing($testListingEmpty);
assertTest(
    $coordsEmpty === null,
    'Listing without address returns null (never dummy coordinates)'
);

// Vehicle rental uses actual pickup location
$testVehicle = [
    'name' => 'Yamaha NMAX',
    'location' => 'Mandaue City, Cebu',
    'address' => '',
];
$vCoords = Geo::forVehicle($testVehicle);
assertTest(
    $vCoords !== null && Geo::isValidCoordinate($vCoords['latitude'], $vCoords['longitude']),
    'Vehicle pickup location resolves to valid coordinates'
);

// ---------------------------------------------------------------------
// TEST 3: Package Cancellation Sync - Hotel Initiates Cancellation
// ---------------------------------------------------------------------
echo "\n3. Testing Package Cancellation Synchronization - Hotel Provider cancels...\n";

// Ensure a test user exists
$testUser = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1");
if (!$testUser) {
    $touristId = Database::insert("INSERT INTO users (name, email, password, role, created_at) VALUES ('Test Tourist', 'tourist_sync_test@gm.local', 'hash', 'tourist', NOW())");
} else {
    $touristId = (int) $testUser['id'];
}

$guideUser = Database::first("SELECT id FROM users WHERE role = 'guide' LIMIT 1");
$guideId = $guideUser ? (int) $guideUser['id'] : 1;

$hotelUser = Database::first("SELECT id FROM users WHERE role = 'partner' LIMIT 1");
$hotelProviderId = $hotelUser ? (int) $hotelUser['id'] : 2;

$pkgRef = 'GM-PKG-SYNC-' . time() . '-' . rand(100, 999);

// Ensure an existing listing exists
$existingListing = Database::first("SELECT id, user_id FROM listings LIMIT 1");
if (!$existingListing) {
    echo "No listings in database!\n";
    exit(1);
}
$listingId = (int) $existingListing['id'];

// 1. Create Parent Package Booking
$parentBookingId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, status,
        package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-01', 2, 10000.00, 'pending', ?, 'pending', '{\"type\":\"package_booking\"}', NOW(), NOW())
", [$listingId, $touristId, $pkgRef]);

// 2. Create Hotel Sub-Booking
$hotelBookingId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount,
        status, hotel_status, package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, ?, '2026-12-01', 2, 4000.00, 4000.00, 0.00, 'pending', 'pending', ?, 'pending', NOW(), NOW())
", [$listingId, $touristId, $pkgRef]);

// 3. Create Rental Request
$rentalRequestId = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
        total_amount, customer_name, customer_email, status, package_booking_ref,
        package_booking_id, created_at, updated_at
    ) VALUES (?, 'Honda Beat', 'Scooter', 'Cebu City', '2026-12-01', 2, 1500.00, 'Test Tourist', 'test@test.com', 'pending', ?, ?, NOW(), NOW())
", [$touristId, $pkgRef, $parentBookingId]);

// 4. Create package_booking_items
Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide Services', ?, 4500.00, 'pending', NOW())
", [$pkgRef, $parentBookingId, $listingId, $guideId, $parentBookingId]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Room', ?, 4000.00, 'pending', NOW())
", [$pkgRef, $parentBookingId, $listingId, $hotelProviderId, $hotelBookingId]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Vehicle Rental', ?, 1500.00, 'pending', NOW())
", [$pkgRef, $parentBookingId, $listingId, 3, $rentalRequestId]);

// Execute: Hotel Provider cancels
PackageBookingService::onHotelBookingUpdated($hotelBookingId, 'cancel', $hotelProviderId);

// Verify Synchronization:
$parentCheck = Database::first("SELECT status, package_overall_status FROM bookings WHERE id = ?", [$parentBookingId]);
$hotelCheck = Database::first("SELECT status, hotel_status FROM bookings WHERE id = ?", [$hotelBookingId]);
$rentalCheck = Database::first("SELECT status FROM rental_requests WHERE id = ?", [$rentalRequestId]);
$itemsCheck = Database::all("SELECT service_type, status FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef]);

assertTest(
    ($parentCheck['status'] ?? '') === 'cancelled' && ($parentCheck['package_overall_status'] ?? '') === 'cancelled',
    'Parent package booking status changed to Cancelled'
);
assertTest(
    ($hotelCheck['status'] ?? '') === 'cancelled' && ($hotelCheck['hotel_status'] ?? '') === 'cancelled',
    'Hotel reservation status changed to Cancelled'
);
assertTest(
    ($rentalCheck['status'] ?? '') === 'cancelled',
    'Associated vehicle rental request automatically changed to Cancelled'
);
$allItemsCancelled = true;
foreach ($itemsCheck as $it) {
    if ($it['status'] !== 'cancelled') {
        $allItemsCancelled = false;
    }
}
assertTest($allItemsCancelled, 'All package service items automatically synchronized to Cancelled');

// ---------------------------------------------------------------------
// TEST 4: Standalone Individual Bookings are NOT Affected
// ---------------------------------------------------------------------
echo "\n4. Testing Individual Bookings Isolation...\n";
$standaloneBookingId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, status,
        notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-10', 2, 2500.00, 'confirmed', 'Individual Tour Booking', NOW(), NOW())
", [$listingId, $touristId]);

$standaloneRentalId = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
        total_amount, customer_name, customer_email, status, created_at, updated_at
    ) VALUES (?, 'Yamaha Aerox', 'Scooter', 'Cebu City', '2026-12-10', 1, 800.00, 'Test Tourist', 'test@test.com', 'approved', NOW(), NOW())
", [$touristId]);

// Trigger package cancellation on an unrelated package
$dummyPkgRef = 'GM-PKG-DUMMY-' . time();
$dummyParentId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, status,
        package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-05', 2, 5000.00, 'pending', ?, 'pending', '{\"type\":\"package_booking\"}', NOW(), NOW())
", [$listingId, $touristId, $dummyPkgRef]);
PackageBookingService::onBookingUpdated($dummyParentId, 'cancelled', $guideId);

// Check standalone records
$standB = Database::first("SELECT status FROM bookings WHERE id = ?", [$standaloneBookingId]);
$standR = Database::first("SELECT status FROM rental_requests WHERE id = ?", [$standaloneRentalId]);

assertTest(
    ($standB['status'] ?? '') === 'confirmed',
    'Individual tour booking remains confirmed (unaffected by package cancellation)'
);
assertTest(
    ($standR['status'] ?? '') === 'approved',
    'Individual vehicle rental request remains approved (unaffected by package cancellation)'
);

// ---------------------------------------------------------------------
// TEST 5: Rental Provider Cancels Travel Package Request
// ---------------------------------------------------------------------
echo "\n5. Testing Package Cancellation Synchronization - Rental Provider cancels...\n";
$pkgRef2 = 'GM-PKG-SYNC2-' . time() . '-' . rand(100, 999);

$parent2Id = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, status,
        package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-01', 2, 12000.00, 'confirmed', ?, 'confirmed', '{\"type\":\"package_booking\"}', NOW(), NOW())
", [$listingId, $touristId, $pkgRef2]);

$hotel2Id = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount,
        status, hotel_status, package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, ?, '2026-12-01', 2, 5000.00, 5000.00, 0.00, 'confirmed', 'confirmed', ?, 'confirmed', NOW(), NOW())
", [$listingId, $touristId, $pkgRef2]);

$rental2Id = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
        total_amount, customer_name, customer_email, status, package_booking_ref,
        package_booking_id, created_at, updated_at
    ) VALUES (?, 'Honda Click', 'Scooter', 'Cebu City', '2026-12-01', 2, 2000.00, 'Test Tourist', 'test@test.com', 'approved', ?, ?, NOW(), NOW())
", [$touristId, $pkgRef2, $parent2Id]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide Services', ?, 5000.00, 'confirmed', NOW())
", [$pkgRef2, $parent2Id, $listingId, $guideId, $parent2Id]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Room', ?, 5000.00, 'confirmed', NOW())
", [$pkgRef2, $parent2Id, $listingId, $hotelProviderId, $hotel2Id]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, 3, 'rental_provider', 'vehicle', 'Vehicle Rental', ?, 2000.00, 'confirmed', NOW())
", [$pkgRef2, $parent2Id, $listingId, $rental2Id]);

// Execute: Rental Provider cancels
PackageBookingService::onRentalUpdated($rental2Id, 'cancelled', 3);

$parent2Check = Database::first("SELECT status, package_overall_status FROM bookings WHERE id = ?", [$parent2Id]);
$hotel2Check = Database::first("SELECT status, hotel_status FROM bookings WHERE id = ?", [$hotel2Id]);
$rental2Check = Database::first("SELECT status FROM rental_requests WHERE id = ?", [$rental2Id]);

assertTest(($parent2Check['status'] ?? '') === 'cancelled', 'When Rental cancels, Parent package becomes Cancelled');
assertTest(($hotel2Check['status'] ?? '') === 'cancelled', 'When Rental cancels, Hotel reservation becomes Cancelled');
assertTest(($rental2Check['status'] ?? '') === 'cancelled', 'When Rental cancels, Rental request becomes Cancelled');

// ---------------------------------------------------------------------
// TEST 6: Guide Cancels Travel Package Booking
// ---------------------------------------------------------------------
echo "\n6. Testing Package Cancellation Synchronization - Guide cancels...\n";
$pkgRef3 = 'GM-PKG-SYNC3-' . time() . '-' . rand(100, 999);

$parent3Id = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, status,
        package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-01', 2, 12000.00, 'confirmed', ?, 'confirmed', '{\"type\":\"package_booking\"}', NOW(), NOW())
", [$listingId, $touristId, $pkgRef3]);

$hotel3Id = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount,
        status, hotel_status, package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, ?, '2026-12-01', 2, 5000.00, 5000.00, 0.00, 'confirmed', 'confirmed', ?, 'confirmed', NOW(), NOW())
", [$listingId, $touristId, $pkgRef3]);

$rental3Id = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
        total_amount, customer_name, customer_email, status, package_booking_ref,
        package_booking_id, created_at, updated_at
    ) VALUES (?, 'Honda Click', 'Scooter', 'Cebu City', '2026-12-01', 2, 2000.00, 'Test Tourist', 'test@test.com', 'approved', ?, ?, NOW(), NOW())
", [$touristId, $pkgRef3, $parent3Id]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide Services', ?, 5000.00, 'confirmed', NOW())
", [$pkgRef3, $parent3Id, $listingId, $guideId, $parent3Id]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Room', ?, 5000.00, 'confirmed', NOW())
", [$pkgRef3, $parent3Id, $listingId, $hotelProviderId, $hotel3Id]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, 3, 'rental_provider', 'vehicle', 'Vehicle Rental', ?, 2000.00, 'confirmed', NOW())
", [$pkgRef3, $parent3Id, $listingId, $rental3Id]);

// Execute: Guide cancels
PackageBookingService::onBookingUpdated($parent3Id, 'cancelled', $guideId);

$parent3Check = Database::first("SELECT status, package_overall_status FROM bookings WHERE id = ?", [$parent3Id]);
$hotel3Check = Database::first("SELECT status, hotel_status FROM bookings WHERE id = ?", [$hotel3Id]);
$rental3Check = Database::first("SELECT status FROM rental_requests WHERE id = ?", [$rental3Id]);

assertTest(($parent3Check['status'] ?? '') === 'cancelled', 'When Guide cancels, Parent package becomes Cancelled');
assertTest(($hotel3Check['status'] ?? '') === 'cancelled', 'When Guide cancels, Hotel reservation becomes Cancelled');
assertTest(($rental3Check['status'] ?? '') === 'cancelled', 'When Guide cancels, Rental request becomes Cancelled');

// Clean up test records
Database::run("DELETE FROM package_booking_items WHERE package_booking_ref IN (?, ?, ?, ?)", [$pkgRef, $dummyPkgRef, $pkgRef2, $pkgRef3]);
Database::run("DELETE FROM bookings WHERE id IN (?, ?, ?, ?, ?, ?, ?, ?)", [$parentBookingId, $hotelBookingId, $standaloneBookingId, $dummyParentId, $parent2Id, $hotel2Id, $parent3Id, $hotel3Id]);
Database::run("DELETE FROM rental_requests WHERE id IN (?, ?, ?, ?)", [$rentalRequestId, $standaloneRentalId, $rental2Id, $rental3Id]);

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
if ($failed === 0) {
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
