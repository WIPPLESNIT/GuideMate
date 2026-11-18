<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PackageBookingService;

function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
        echo "  [FAIL] $message\n";
        throw new \RuntimeException("Test failed: $message");
    }
    echo "  [PASS] $message\n";
}

echo "=== GuideMate Provider Service Status Synchronization & Vehicle Replacement Test Suite ===\n\n";

try {
    PackageBookingService::ensureTables();
    Database::run("DELETE FROM bookings WHERE notes LIKE '%Sync Tourist%' OR notes LIKE '%travel_package%' OR user_id IN (SELECT id FROM users WHERE email LIKE '%sync%')");
    Database::run("DELETE FROM rental_requests WHERE package_booking_ref IS NOT NULL OR user_id IN (SELECT id FROM users WHERE email LIKE '%sync%')");
    Database::run("DELETE FROM package_booking_items WHERE parent_booking_id NOT IN (SELECT id FROM bookings)");

    // 0. Setup accounts
    $suffix = time();
    $tourist = Database::first("SELECT id, email, name FROM users WHERE email = 'tourist_sync@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Sync Tourist', 'tourist_sync@test.com', 'hash', 'tourist', 1, NOW())"), 'email' => 'tourist_sync@test.com', 'name' => 'Sync Tourist'];

    $guide = Database::first("SELECT id, email, name FROM users WHERE email = 'guide_sync@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, is_active, guide_status, created_at) VALUES ('Sync Guide Host', 'guide_sync@test.com', 'hash', 'guide', 1, 'approved', NOW())"), 'email' => 'guide_sync@test.com', 'name' => 'Sync Guide Host'];

    $hotelProv = Database::first("SELECT id, email, name FROM users WHERE email = 'hotel_sync@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, is_active, guide_status, created_at) VALUES ('Sync Hotel Partner', 'hotel_sync@test.com', 'hash', 'hotel_provider', 1, 'approved', NOW())"), 'email' => 'hotel_sync@test.com', 'name' => 'Sync Hotel Partner'];

    $origRentalProv = Database::first("SELECT id, email, name FROM users WHERE email = 'rental_orig@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, is_active, guide_status, created_at) VALUES ('Original Rental Partner', 'rental_orig@test.com', 'hash', 'rental_provider', 1, 'approved', NOW())"), 'email' => 'rental_orig@test.com', 'name' => 'Original Rental Partner'];

    $repRentalProv = Database::first("SELECT id, email, name FROM users WHERE email = 'rental_rep@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, is_active, guide_status, created_at) VALUES ('Replacement Rental Partner', 'rental_rep@test.com', 'hash', 'rental_provider', 1, 'approved', NOW())"), 'email' => 'rental_rep@test.com', 'name' => 'Replacement Rental Partner'];

    $catPackages = (int) (Database::first("SELECT id FROM categories WHERE slug IN ('packages', 'travel-packages') LIMIT 1")['id'] ?? 5);
    $catHotels = (int) (Database::first("SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1")['id'] ?? 3);

    // Hotel listing & room
    $hotelListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = ? LIMIT 1", [(int) $hotelProv['id'], $catHotels])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, ?, 'Sync Resort', 'sync-resort-$suffix', 'Beach resort', 'Moalboal', 2000.00, 'approved', NOW())", [(int) $hotelProv['id'], $catHotels]), 'title' => 'Sync Resort', 'price' => 2000.00];

    $room = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [(int) $hotelListing['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests, total_units) VALUES (?, 'Sync Room', 2000.00, 4, 1)", [(int) $hotelListing['id']]), 'name' => 'Sync Room', 'price_per_night' => 2000.00];

    // Vehicles: Original Vehicle (Scooter, 1000/day)
    $origVehicle = Database::first("SELECT id, name, price_per_day FROM vehicles WHERE user_id = ? AND slug LIKE 'orig-scooter%' LIMIT 1", [(int) $origRentalProv['id']])
        ?? ['id' => Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Honda', 'Click 125i', 'Orig Honda Click', 'orig-scooter-$suffix', 'Scooter', 'Scooter', 1000.00, 'Moalboal', 'approved', 1)", [(int) $origRentalProv['id']]), 'name' => 'Orig Honda Click', 'price_per_day' => 1000.00];

    // Eligible Replacement Vehicle from repRentalProv (Scooter, 850/day <= 1000)
    $repVehicle = Database::first("SELECT id, name, price_per_day FROM vehicles WHERE user_id = ? AND slug LIKE 'rep-scooter%' LIMIT 1", [(int) $repRentalProv['id']]);
    if ($repVehicle) {
        Database::run("UPDATE vehicles SET passenger_capacity = 2 WHERE id = ?", [(int) $repVehicle['id']]);
    } else {
        $repVehicle = ['id' => Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available) VALUES (?, 'Yamaha', 'Mio i125', 'Rep Yamaha Mio', 'rep-scooter-$suffix', 'Scooter', 'Scooter', 2, 850.00, 'Moalboal', 'approved', 1)", [(int) $repRentalProv['id']]), 'name' => 'Rep Yamaha Mio', 'price_per_day' => 850.00];
    }

    // Package listing
    $packageListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = ? LIMIT 1", [(int) $guide['id'], $catPackages])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, capacity, status, created_at) VALUES (?, ?, '3D2N Sync Tour Package', '3d2n-sync-tour-$suffix', 'Complete 3D2N Tour', 'Moalboal', 9500.00, '3 Days / 2 Nights', 6, 'approved', NOW())", [(int) $guide['id'], $catPackages]), 'title' => '3D2N Sync Tour Package', 'price' => 9500.00];

    // Link package services
    Database::run("DELETE FROM package_services WHERE package_id = ?", [(int) $packageListing['id']]);
    Database::insert("INSERT INTO package_services (package_id, service_type, provider_id, listing_id, hotel_room_id, agreed_rate, status) VALUES (?, 'hotel', ?, ?, ?, 4000.00, 'agreed')", [
        (int) $packageListing['id'],
        (int) $hotelProv['id'],
        (int) $hotelListing['id'],
        (int) $room['id'],
    ]);
    Database::insert("INSERT INTO package_services (package_id, service_type, provider_id, vehicle_id, agreed_rate, status) VALUES (?, 'vehicle', ?, ?, 3000.00, 'agreed')", [
        (int) $packageListing['id'],
        (int) $origRentalProv['id'],
        (int) $origVehicle['id'],
    ]);

    echo "--- Scenario 1: Guide Booking Confirmed Synchronizes Providers Service Status to Confirmed ---\n";
    $travelDate1 = date('Y-m-d', strtotime('+15 days'));
    $bRes1 = PackageBookingService::createPackageBooking((int) $packageListing['id'], (int) $tourist['id'], [
        'guestsCount' => 2,
        'travelDate' => $travelDate1,
        'contactName' => 'Sync Tourist',
        'contactPhone' => '09123456789',
        'contactEmail' => 'tourist_sync@test.com',
    ]);
    $pkgRef1 = $bRes1['package_booking_ref'];
    $parentBookingId1 = (int) $bRes1['parent_booking_id'];

    // Verify initial guide item status is pending
    $summaryBefore = PackageBookingService::getPackageBookingSummary($pkgRef1);
    $guideItemBefore = null;
    foreach ($summaryBefore['items'] as $it) {
        if ($it['service_type'] === 'guide') {
            $guideItemBefore = $it;
        }
    }
    assertCondition($guideItemBefore !== null && $guideItemBefore['status'] === 'pending', 'Initial guide item status is pending in DB');

    // Guide confirms their booking via host/guide workflow
    Booking::updateStatus($parentBookingId1, 'confirmed');
    PackageBookingService::onBookingUpdated($parentBookingId1, 'confirmed', (int) $guide['id']);

    // Re-check summary
    $summaryAfter = PackageBookingService::getPackageBookingSummary($pkgRef1);
    $guideItemAfter = null;
    foreach ($summaryAfter['items'] as $it) {
        if ($it['service_type'] === 'guide') {
            $guideItemAfter = $it;
        }
    }
    assertCondition($guideItemAfter !== null && $guideItemAfter['status'] === 'confirmed', 'Guide item status in package_booking_items updated to confirmed');

    // Simulate App trips.tsx status resolution for Providers Service Status
    $overallStatus1 = $summaryAfter['overall_status'];
    $guideItem1 = $summaryAfter['active_items'][0] ?? null; // guide
    foreach ($summaryAfter['active_items'] as $ai) {
        if ($ai['service_type'] === 'guide') $guideItem1 = $ai;
    }
    $rawGuideStatus1 = strtolower(trim((string) ($guideItem1['status'] ?? $guideItem1['sub_booking_status'] ?? '')));
    $guideStApp1 = ($rawGuideStatus1 !== '' && $rawGuideStatus1 !== 'pending')
        ? $rawGuideStatus1
        : ($overallStatus1 === 'completed'
            ? 'completed'
            : (($overallStatus1 === 'confirmed' || $rawGuideStatus1 === 'confirmed')
                ? 'confirmed'
                : 'pending'));
    assertCondition($guideStApp1 === 'confirmed', 'App Providers Service Status correctly evaluates guide to confirmed');

    echo "--- Scenario 2: Guide Status Remains Synchronized During Subsequent Status Changes (Completed) ---\n";
    // Now hotel and vehicle confirm
    PackageBookingService::onHotelBookingUpdated((int) Database::first("SELECT sub_booking_id FROM package_booking_items WHERE package_booking_ref = ? AND service_type = 'hotel'", [$pkgRef1])['sub_booking_id'], 'confirm', (int) $hotelProv['id']);
    PackageBookingService::onRentalUpdated((int) Database::first("SELECT rental_request_id FROM package_booking_items WHERE package_booking_ref = ? AND service_type = 'vehicle'", [$pkgRef1])['rental_request_id'], 'approved', (int) $origRentalProv['id']);

    $summaryAllConfirmed = PackageBookingService::getPackageBookingSummary($pkgRef1);
    assertCondition($summaryAllConfirmed['overall_status'] === 'confirmed', 'All providers confirmed -> Package is confirmed');

    // Guide completes booking
    Booking::updateStatus($parentBookingId1, 'completed');
    PackageBookingService::onBookingUpdated($parentBookingId1, 'completed', (int) $guide['id']);

    $summaryGuideCompleted = PackageBookingService::getPackageBookingSummary($pkgRef1);
    $guideItemComp = null;
    foreach ($summaryGuideCompleted['items'] as $it) {
        if ($it['service_type'] === 'guide') $guideItemComp = $it;
    }
    assertCondition($guideItemComp['status'] === 'completed', 'Guide item status successfully synchronized to completed');

    echo "--- Scenario 3: Vehicle Rental Provider Cancels -> Disruption Action Required & Option A Available ---\n";
    $travelDate2 = date('Y-m-d', strtotime('+20 days'));
    $bRes2 = PackageBookingService::createPackageBooking((int) $packageListing['id'], (int) $tourist['id'], [
        'guestsCount' => 2,
        'travelDate' => $travelDate2,
        'contactName' => 'Sync Tourist',
        'contactPhone' => '09123456789',
        'contactEmail' => 'tourist_sync@test.com',
    ]);
    $pkgRef2 = $bRes2['package_booking_ref'];
    $parentBookingId2 = (int) $bRes2['parent_booking_id'];

    $vItem2 = Database::first("SELECT id, rental_request_id, allocated_amount FROM package_booking_items WHERE package_booking_ref = ? AND service_type = 'vehicle'", [$pkgRef2]);
    $rentalReqId2 = (int) $vItem2['rental_request_id'];
    $vItemId2 = (int) $vItem2['id'];

    // Vehicle provider cancels
    RentalRequest::updateStatus($rentalReqId2, 'cancelled', 'Vehicle unavailable on scheduled dates');
    PackageBookingService::onRentalUpdated($rentalReqId2, 'cancelled', (int) $origRentalProv['id']);

    $summaryCancelled = PackageBookingService::getPackageBookingSummary($pkgRef2);
    assertCondition($summaryCancelled['overall_status'] === 'action_required', 'Package overall status changed to action_required');
    assertCondition($summaryCancelled['has_cancellation'] === true, 'Package summary indicates has_cancellation is true');
    assertCondition(!empty($summaryCancelled['cancelled_items']), 'Cancelled items contains vehicle item');
    assertCondition($summaryCancelled['cancelled_items'][0]['service_type'] === 'vehicle', 'Cancelled item service type is vehicle');
    assertCondition(isset($summaryCancelled['alternative_options']['replace']), 'Option A (Replace) is available in summary');

    echo "--- Scenario 4: All Eligible Available Replacement Vehicles Appear Inside Existing Modal ---\n";
    $altsRes = PackageBookingService::getAvailableAlternatives($pkgRef2, $vItemId2);
    assertCondition(!empty($altsRes['alternatives']), 'Alternatives list is not empty');

    $foundRepVehicle = false;
    foreach ($altsRes['alternatives'] as $alt) {
        if ($alt['vehicle_id'] === (int) $repVehicle['id']) {
            $foundRepVehicle = true;
            assertCondition(!empty($alt['image']) || isset($alt['image']), 'Replacement vehicle has photo field');
            assertCondition(!empty($alt['title']) && !empty($alt['model']), 'Replacement vehicle has model and name');
            assertCondition($alt['vehicle_type'] === 'Scooter', 'Replacement vehicle category is Scooter');
            assertCondition(isset($alt['capacity']) && (int) $alt['capacity'] >= 0, 'Replacement vehicle has passenger capacity');
            assertCondition($alt['price'] == 850.00, 'Replacement vehicle has rental daily price');
            assertCondition($alt['provider_name'] === 'Replacement Rental Partner', 'Replacement vehicle has rental provider name');
            assertCondition($alt['location'] === 'Moalboal', 'Replacement vehicle has pickup location');
            assertCondition($alt['days'] === 3, 'Replacement vehicle covers full 3-day duration');
            assertCondition($alt['total_cost'] == 2550.00, 'Total rental price is calculated accurately for 3 days');
        }
    }
    assertCondition($foundRepVehicle, 'Eligible replacement vehicle (repVehicle) is present in alternatives');

    echo "--- Scenario 5: Unavailable, Already-Booked, Inactive, Unapproved, or Incompatible Vehicles Excluded ---\n";
    // Create an unapproved vehicle
    $unapprovedVId = Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Honda', 'Beat', 'Unapproved Beat', 'unapp-beat-$suffix', 'Scooter', 'Scooter', 700.00, 'Moalboal', 'pending', 1)", [(int) $repRentalProv['id']]);

    // Create an inactive provider vehicle
    $inactiveProvId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Inactive Prov', 'inact_$suffix@test.com', 'hash', 'rental_provider', 0, NOW())");
    $inactiveProvVId = Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Kymco', 'Like 125', 'Inactive Kymco', 'inact-kymco-$suffix', 'Scooter', 'Scooter', 700.00, 'Moalboal', 'approved', 1)", [$inactiveProvId]);

    // Create an already-booked vehicle on overlapping dates
    $bookedVId = Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Suzuki', 'Burgman', 'Booked Burgman', 'booked-burg-$suffix', 'Scooter', 'Scooter', 700.00, 'Moalboal', 'approved', 1)", [(int) $repRentalProv['id']]);
    Database::insert("INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount, status, created_at) VALUES (?, ?, 'Booked Burgman', 'Scooter', 'Moalboal', ?, 3, 2100.00, 'approved', NOW())", [(int) $tourist['id'], $bookedVId, $travelDate2]);

    // Create a vehicle with insufficient passenger capacity (1 seat for 2-passenger package)
    $insufficientCapVId = Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available) VALUES (?, 'Ducati', 'Scrambler', 'Solo Bike', 'solo-bike-$suffix', 'Scooter', 'Scooter', 1, 700.00, 'Moalboal', 'approved', 1)", [(int) $repRentalProv['id']]);

    $altsFilteredTest = PackageBookingService::getAvailableAlternatives($pkgRef2, $vItemId2);
    $excludedIds = [$unapprovedVId, $inactiveProvVId, $bookedVId, $insufficientCapVId, (int) $origVehicle['id']];
    foreach ($altsFilteredTest['alternatives'] as $alt) {
        assertCondition(!in_array($alt['vehicle_id'], $excludedIds, true), "Vehicle {$alt['vehicle_id']} is correctly excluded");
    }
    echo "  [PASS] Unapproved, inactive provider, already-booked, insufficient passenger capacity, and original cancelled vehicles were successfully excluded.\n";

    echo "--- Scenario 6: System Validates Vehicle Availability Again Before Submitting Replacement ---\n";
    // Test backend revalidation of insufficient passenger capacity
    $capacityRevalThrown = false;
    try {
        PackageBookingService::replaceCancelledService($pkgRef2, $vItemId2, (int) $repRentalProv['id'], $insufficientCapVId, [], (int) $guide['id']);
    } catch (\RuntimeException $e) {
        $capacityRevalThrown = true;
        assertCondition(str_contains($e->getMessage(), 'cannot accommodate'), 'Throws insufficient passenger capacity exception on submission');
    }
    assertCondition($capacityRevalThrown, 'Insufficient passenger capacity was blocked by backend revalidation before confirmation');

    // Simulate race condition: reserve repVehicle on overlapping dates right before submission
    $conflictRentalId = Database::insert("INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount, status, created_at) VALUES (?, ?, 'Rep Yamaha Mio', 'Scooter', 'Moalboal', ?, 3, 2550.00, 'approved', NOW())", [(int) $tourist['id'], (int) $repVehicle['id'], $travelDate2]);

    $conflictThrown = false;
    try {
        PackageBookingService::replaceCancelledService($pkgRef2, $vItemId2, (int) $repRentalProv['id'], (int) $repVehicle['id'], [], (int) $guide['id']);
    } catch (\RuntimeException $e) {
        $conflictThrown = true;
        assertCondition(str_contains($e->getMessage(), 'already reserved'), 'Throws availability conflict exception on submission');
    }
    assertCondition($conflictThrown, 'Double-booking was prevented before submission');

    // Clean up temporary conflict reservation
    Database::run("DELETE FROM rental_requests WHERE id = ?", [$conflictRentalId]);

    echo "--- Scenario 7: Replacement Vehicle Price Does Not Exceed Original Cancelled Rental Price ---\n";
    // Create an expensive vehicle (₱4,000/day * 3 days = ₱12,000 > original ₱3,000) with capacity 2
    $expensiveVId = Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available) VALUES (?, 'Ducati', 'Scrambler', 'Expensive Ducati', 'exp-ducati-$suffix', 'Scooter', 'Scooter', 2, 4000.00, 'Moalboal', 'approved', 1)", [(int) $repRentalProv['id']]);

    $priceExceededThrown = false;
    try {
        PackageBookingService::replaceCancelledService($pkgRef2, $vItemId2, (int) $repRentalProv['id'], $expensiveVId, [], (int) $guide['id']);
    } catch (\RuntimeException $e) {
        $priceExceededThrown = true;
        assertCondition(str_contains($e->getMessage(), 'cannot exceed original'), 'Throws price restriction exception');
    }
    assertCondition($priceExceededThrown, 'Exceeding original price was blocked');

    echo "--- Scenario 8: After Replacement Submission, Replacement Provider Receives Booking Request With Pending Status ---\n";
    $repResult = PackageBookingService::replaceCancelledService($pkgRef2, $vItemId2, (int) $repRentalProv['id'], (int) $repVehicle['id'], [], (int) $guide['id']);
    assertCondition($repResult['success'] === true, 'replaceCancelledService succeeded');

    // Verify replacement rental request in rental_requests
    $newRentalReq = Database::first("SELECT * FROM rental_requests WHERE package_booking_ref = ? AND vehicle_id = ? ORDER BY id DESC LIMIT 1", [$pkgRef2, (int) $repVehicle['id']]);
    assertCondition($newRentalReq !== null, 'New replacement rental request created in rental_requests');
    assertCondition($newRentalReq['status'] === 'pending', 'Replacement rental request has strictly PENDING status');
    assertCondition((int) $newRentalReq['vehicle_id'] === (int) $repVehicle['id'], 'Replacement rental request points to replacement vehicle');

    // Verify new replacement item in package_booking_items
    $newPackageItem = Database::first("SELECT * FROM package_booking_items WHERE package_booking_ref = ? AND is_replacement = 1 ORDER BY id DESC LIMIT 1", [$pkgRef2]);
    assertCondition($newPackageItem !== null, 'New replacement item exists in package_booking_items');
    assertCondition($newPackageItem['status'] === 'pending', 'Replacement item has status pending');
    assertCondition((int) $newPackageItem['replaces_item_id'] === $vItemId2, 'Replacement item references original cancelled item');

    echo "--- Scenario 9: App Displays Replacement Vehicle Under Rental Transportation Replacement & Provider Booking Approvals ---\n";
    $summaryWithRep = PackageBookingService::getPackageBookingSummary($pkgRef2);
    assertCondition(!empty($summaryWithRep['replacement_vehicle']), 'Summary contains replacement_vehicle object');
    assertCondition($summaryWithRep['replacement_vehicle']['vehicle_name'] === 'Rep Yamaha Mio', 'Replacement vehicle name is accurate');
    assertCondition($summaryWithRep['replacement_vehicle']['status'] === 'pending', 'Replacement vehicle status is pending');

    // Simulate trips.tsx replacement state resolution
    $selectedPackageDetails = [
        'package_booking_ref' => $pkgRef2,
        'package_overall_status' => $summaryWithRep['overall_status'],
        'replacement_vehicle' => $summaryWithRep['replacement_vehicle'],
        'cancelled_items' => $summaryWithRep['cancelled_items'],
        'all_cancelled_items' => $summaryWithRep['all_cancelled_items'],
        'provider_statuses' => $summaryWithRep['items'],
        'package_details' => $summaryWithRep['package_details'],
    ];

    $vehicleItemApp = null;
    foreach ($selectedPackageDetails['provider_statuses'] as $p) {
        if ($p['service_type'] === 'vehicle' && empty($p['is_replacement'])) $vehicleItemApp = $p;
    }
    $vehicleReplacementObj = $selectedPackageDetails['package_details']['rental_replacement'] ?? $selectedPackageDetails['replacement_vehicle'];
    $originalVehicleCancelled = Boolean(
        ($vehicleItemApp && ($vehicleItemApp['status'] === 'cancelled')) ||
        !empty($selectedPackageDetails['all_cancelled_items'])
    );
    $hasVehicleReplacement = $originalVehicleCancelled && !empty($vehicleReplacementObj);
    assertCondition($hasVehicleReplacement, 'App correctly detects hasVehicleReplacement === true');

    $activeVehicleSt = strtolower((string) ($vehicleReplacementObj['status'] ?? 'pending'));
    assertCondition($activeVehicleSt === 'pending', 'App displays replacement vehicle with Pending status');

    echo "--- Scenario 10: Original Cancelled Rental Request Remains Available in Booking History ---\n";
    $origRentalInHistory = Database::first("SELECT * FROM rental_requests WHERE id = ?", [$rentalReqId2]);
    assertCondition($origRentalInHistory !== null, 'Original rental request is preserved in rental_requests table');
    assertCondition($origRentalInHistory['status'] === 'cancelled', 'Original rental request status remains cancelled');

    $origItemInHistory = Database::first("SELECT * FROM package_booking_items WHERE id = ?", [$vItemId2]);
    assertCondition($origItemInHistory !== null, 'Original cancelled package booking item is preserved in package_booking_items table');
    assertCondition($origItemInHistory['status'] === 'cancelled', 'Original cancelled item status remains cancelled');

    $allCancelled = $summaryWithRep['all_cancelled_items'];
    $foundOrigInSummary = false;
    foreach ($allCancelled as $c) {
        if ((int) $c['id'] === $vItemId2) {
            $foundOrigInSummary = true;
        }
    }
    assertCondition($foundOrigInSummary, 'Original cancelled vehicle item is present in all_cancelled_items in summary');

    echo "\n=================================================================\n";
    echo "SUCCESS: All 10 Provider Service Status Synchronization &\nVehicle Replacement Availability scenarios PASSED!\n";
    echo "=================================================================\n";

} catch (\Throwable $e) {
    echo "\n[ERROR] Exception occurred: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

function Boolean(mixed $val): bool {
    return (bool) $val;
}
