<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Services\PackageBookingService;
use App\Controllers\ApiController;

function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
        echo "  [FAIL] $message\n";
        throw new \RuntimeException("Test failed: $message");
    }
    echo "  [PASS] $message\n";
}

echo "=== GuideMate Travel Package Replacement & Provider Approvals Test Suite ===\n\n";

$passed = 0;
$failed = 0;

try {
    PackageBookingService::ensureTables();

    // Setup base users: Guide, Tourist, Hotel 1, Hotel 2 (Replacement), Rental 1, Rental 2 (Replacement)
    $t = time();
    $tourist = Database::first("SELECT id, email, name FROM users WHERE email = 'tourist_rep@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, created_at) VALUES ('Test Tourist', 'tourist_rep@test.com', 'hash', 'user', NOW())"), 'email' => 'tourist_rep@test.com', 'name' => 'Test Tourist'];
    $guide = Database::first("SELECT id, email, name FROM users WHERE email = 'guide_rep@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Test Guide', 'guide_rep@test.com', 'hash', 'guide', 'approved', NOW())"), 'email' => 'guide_rep@test.com', 'name' => 'Test Guide'];
    $hotel1 = Database::first("SELECT id, email, name FROM users WHERE email = 'hotel1@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Original Hotel Host', 'hotel1@test.com', 'hash', 'hotel_provider', 'approved', NOW())"), 'email' => 'hotel1@test.com', 'name' => 'Original Hotel Host'];
    $hotel2 = Database::first("SELECT id, email, name FROM users WHERE email = 'hotel2@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Replacement Hotel Host', 'hotel2@test.com', 'hash', 'hotel_provider', 'approved', NOW())"), 'email' => 'hotel2@test.com', 'name' => 'Replacement Hotel Host'];
    $rental1 = Database::first("SELECT id, email, name FROM users WHERE email = 'rental1@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Original Rental Host', 'rental1@test.com', 'hash', 'rental_provider', 'approved', NOW())"), 'email' => 'rental1@test.com', 'name' => 'Original Rental Host'];
    $rental2 = Database::first("SELECT id, email, name FROM users WHERE email = 'rental2@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Replacement Rental Host', 'rental2@test.com', 'hash', 'rental_provider', 'approved', NOW())"), 'email' => 'rental2@test.com', 'name' => 'Replacement Rental Host'];

    // Clean up any previous test runs
    Database::run("DELETE FROM bookings WHERE package_booking_ref LIKE 'GM-PKG-REP-TEST%'");
    Database::run("DELETE FROM rental_requests WHERE package_booking_ref LIKE 'GM-PKG-REP-TEST%'");
    Database::run("DELETE FROM package_booking_items WHERE package_booking_ref LIKE 'GM-PKG-REP-TEST%'");

    Database::run("UPDATE listings SET price = 1800.00 WHERE user_id = ? AND category_id = 3", [(int) $hotel2['id']]);
    Database::run("UPDATE hotel_rooms SET price_per_night = 1800.00 WHERE listing_id IN (SELECT id FROM listings WHERE user_id = ?)", [(int) $hotel2['id']]);
    Database::run("UPDATE vehicles SET price_per_day = 300.00 WHERE user_id = ?", [(int) $rental2['id']]);

    // Listings
    $slugSuffix = time();
    $hotelListing1 = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? LIMIT 1", [(int) $hotel1['id']])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, 3, 'Original Resort', 'orig-resort-$slugSuffix', 'Desc', 'Moalboal', 1800.00, 'approved', NOW())", [(int) $hotel1['id']]), 'title' => 'Original Resort', 'price' => 1800.00];
    $hotelListing2 = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? LIMIT 1", [(int) $hotel2['id']])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, 3, 'Replacement Luxury Hotel', 'rep-resort-$slugSuffix', 'Desc', 'Moalboal', 1800.00, 'approved', NOW())", [(int) $hotel2['id']]), 'title' => 'Replacement Luxury Hotel', 'price' => 1800.00];

    // Rooms
    $room1 = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [(int) $hotelListing1['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests) VALUES (?, 'Standard Queen Room', 1800.00, 2)", [(int) $hotelListing1['id']]), 'name' => 'Standard Queen Room', 'price_per_night' => 1800.00];
    $room2 = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [(int) $hotelListing2['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests) VALUES (?, 'Deluxe Ocean View', 1800.00, 2)", [(int) $hotelListing2['id']]), 'name' => 'Deluxe Ocean View', 'price_per_night' => 1800.00];

    // Vehicles
    $vehicle1 = Database::first("SELECT id, name, model, type, price_per_day FROM vehicles WHERE user_id = ? LIMIT 1", [(int) $rental1['id']])
        ?? ['id' => Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Honda', 'Click 125', 'Honda Click 125', 'honda-click-$slugSuffix', 'Scooter', 'Scooter', 500.00, 'Moalboal Town', 'approved', 1)", [(int) $rental1['id']]), 'name' => 'Honda Click 125', 'model' => 'Click 125', 'type' => 'Scooter', 'price_per_day' => 500.00];
    $vehicle2 = Database::first("SELECT id, name, model, type, price_per_day FROM vehicles WHERE user_id = ? LIMIT 1", [(int) $rental2['id']])
        ?? ['id' => Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Yamaha', 'NMAX 155', 'Yamaha NMAX 155', 'yamaha-nmax-$slugSuffix', 'Scooter', 'Scooter', 300.00, 'Moalboal Center', 'approved', 1)", [(int) $rental2['id']]), 'name' => 'Yamaha NMAX 155', 'model' => 'NMAX 155', 'type' => 'Scooter', 'price_per_day' => 300.00];

    $pkgListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = 5 LIMIT 1", [(int) $guide['id']])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, 5, '3D2N Ultimate Cebu Tour Package', 'pkg-tour-$slugSuffix', 'Desc', 'Moalboal', 6500.00, 'approved', NOW())", [(int) $guide['id']]), 'title' => '3D2N Ultimate Cebu Tour Package', 'price' => 6500.00];

    // =========================================================================
    // SCENARIO 7: Action Required status in Guide Account displays ONLY Manage Disruption & Cancel
    // =========================================================================
    echo "1. Testing Scenario 7: Guide Account Action Required button filtering...\n";
    $viewContent = file_get_contents(__DIR__ . '/../app/Views/guide/bookings.php');
    assertCondition(str_contains($viewContent, 'action-manage-disruption'), "Manage Service Disruption action button exists");
    assertCondition(str_contains($viewContent, '$isActionRequired'), "Dedicated isActionRequired condition exists in action menu");
    
    // In that branch, check that Confirm, Replace, Reschedule are not present
    $arPos = strpos($viewContent, 'action-manage-disruption');
    $arBranch = substr($viewContent, $arPos, 700);
    assertCondition(str_contains($arBranch, 'action-manage-disruption'), "Displays Manage Service Disruption in Action Required state");
    assertCondition(str_contains($arBranch, 'action-cancel'), "Displays Cancel button in Action Required state");
    assertCondition(!str_contains($arBranch, 'action-accept'), "Hides Confirm action in Action Required state");
    assertCondition(!str_contains($arBranch, 'action-reschedule'), "Hides Reschedule action in Action Required state");
    $passed += 5;

    // =========================================================================
    // SCENARIO 5: No replacement submitted -> Replacement section remains HIDDEN
    // =========================================================================
    echo "\n2. Testing Scenario 5: When no replacement submitted, replacement section remains hidden...\n";
    $testRef = 'GM-PKG-REP-TEST-' . time();
    $parentNotes = json_encode([
        'type' => 'package_booking',
        'package_title' => '3D2N Ultimate Cebu Tour Package',
        'package_details' => [
            'package_title' => '3D2N Ultimate Cebu Tour Package',
            'hotel_included' => true,
            'rental_included' => true,
            'accommodation' => [
                'hotel_id' => (int) $hotelListing1['id'],
                'hotel_name' => 'Original Resort',
                'room_name' => 'Standard Queen Room',
                'room_type' => 'Standard',
                'price_per_night' => 1800.00,
                'nights' => 2,
                'check_in_date' => '2026-12-15',
                'check_out_date' => '2026-12-17',
                'provider_id' => (int) $hotel1['id'],
                'provider_name' => 'Original Hotel Host',
                'status' => 'confirmed'
            ],
            'rental' => [
                'vehicle_id' => (int) $vehicle1['id'],
                'vehicle_name' => 'Honda Click 125',
                'model' => 'Click 125',
                'vehicle_type' => 'Scooter',
                'capacity' => 2,
                'location' => 'Moalboal Town',
                'pickup_date' => '2026-12-15',
                'return_date' => '2026-12-17',
                'rental_days' => 2,
                'daily_rate' => 500.00,
                'provider_id' => (int) $rental1['id'],
                'provider_name' => 'Original Rental Host',
                'status' => 'confirmed'
            ]
        ]
    ]);

    $parentBookingId = Database::insert("
        INSERT INTO bookings (
            listing_id, user_id, booking_date, check_in_date, check_out_date,
            nights, guests, total_amount, status, package_booking_ref, package_overall_status, notes, created_at, updated_at
        ) VALUES (?, ?, '2026-12-15', '2026-12-15', '2026-12-17', 2, 2, 6500.00, 'confirmed', ?, 'confirmed', ?, NOW(), NOW())
    ", [(int) $pkgListing['id'], (int) $tourist['id'], $testRef, $parentNotes]);

    $itemGuideId = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type, service_name, allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guided Tour Cebu', 2500.00, 'confirmed', NOW())
    ", [$testRef, $parentBookingId, (int) $pkgListing['id'], (int) $guide['id']]);

    $subHotelBookingId = Database::insert("
        INSERT INTO bookings (
            listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, status, hotel_status, package_booking_ref, created_at
        ) VALUES (?, ?, ?, '2026-12-15', '2026-12-15', '2026-12-17', 2, 2, 3600.00, 'confirmed', 'confirmed', ?, NOW())
    ", [(int) $hotelListing1['id'], (int) $room1['id'], (int) $tourist['id'], $testRef]);

    $itemHotelId = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type, service_name, sub_booking_id, allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Original Resort - Standard Room', ?, 3600.00, 'confirmed', NOW())
    ", [$testRef, $parentBookingId, (int) $pkgListing['id'], (int) $hotel1['id'], $subHotelBookingId]);

    $rentalReqId = Database::insert("
        INSERT INTO rental_requests (
            user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount, status, package_booking_ref, created_at
        ) VALUES (?, ?, 'Honda Click 125', 'Scooter', 'Moalboal Town', '2026-12-15', 2, 1000.00, 'approved', ?, NOW())
    ", [(int) $tourist['id'], (int) $vehicle1['id'], $testRef]);

    $itemRentalId = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type, service_name, rental_request_id, allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Honda Click 125', ?, 1000.00, 'confirmed', NOW())
    ", [$testRef, $parentBookingId, (int) $pkgListing['id'], (int) $rental1['id'], $rentalReqId]);

    $sumBefore = PackageBookingService::getPackageBookingSummary($testRef);
    assertCondition(empty($sumBefore['replacement_hotel']), "No replacement hotel exists initially");
    assertCondition(empty($sumBefore['replacement_vehicle']), "No replacement vehicle exists initially");
    $passed += 2;

    // =========================================================================
    // SCENARIO 1 & 6: Hotel Cancels -> Replaced -> Replacement Section Visible & Pending, Original Preserved
    // =========================================================================
    echo "\n3. Testing Scenario 1 & 6: Original hotel cancels, replacement submitted with Pending status, original preserved...\n";
    
    // Original hotel provider cancels
    PackageBookingService::respond($testRef, 'hotel', (int) $hotel1['id'], 'cancel', 'Renovation underway');
    $sumAfterCancel = PackageBookingService::getPackageBookingSummary($testRef);
    assertCondition($sumAfterCancel['overall_status'] === 'action_required', "Package status becomes action_required upon cancellation");
    assertCondition($sumAfterCancel['has_cancellation'] === true, "Summary flags active cancellation");

    // Guide replaces hotel with hotel2 / room2
    $replaceRes = PackageBookingService::replaceCancelledService(
        $testRef,
        $itemHotelId,
        (int) $hotel2['id'],
        (int) $hotelListing2['id'],
        ['room_id' => (int) $room2['id']],
        (int) $guide['id']
    );

    assertCondition($replaceRes['success'] === true, "Hotel replacement succeeded");
    
    // Check database records separation (Scenario 6)
    $origHotelItem = Database::first("SELECT * FROM package_booking_items WHERE id = ?", [$itemHotelId]);
    assertCondition($origHotelItem['status'] === 'cancelled', "Original cancelled hotel item preserved with status cancelled");
    assertCondition($origHotelItem['is_replacement'] == 0, "Original hotel item is not marked as replacement");

    $repHotelItem = Database::first("SELECT * FROM package_booking_items WHERE replaces_item_id = ?", [$itemHotelId]);
    assertCondition($repHotelItem !== null, "New replacement hotel item created in database");
    assertCondition($repHotelItem['status'] === 'pending', "Replacement hotel item initially assigned Pending status");
    assertCondition((int) $repHotelItem['is_replacement'] === 1, "Replacement hotel item flagged as is_replacement = 1");
    assertCondition((int) $repHotelItem['provider_id'] === (int) $hotel2['id'], "Replacement hotel item linked to replacement provider");

    // Check replacement hotel sub-booking status in bookings table
    $repHotelBooking = Database::first("SELECT * FROM bookings WHERE id = ?", [(int) $repHotelItem['sub_booking_id']]);
    assertCondition($repHotelBooking['status'] === 'pending', "Replacement hotel sub-booking status is pending");
    assertCondition($repHotelBooking['hotel_status'] === 'pending', "Replacement hotel sub-booking hotel_status is pending");

    // Check summary & overall package status (partially_confirmed / Awaiting Confirmation)
    $sumAfterReplace = PackageBookingService::getPackageBookingSummary($testRef);
    assertCondition($sumAfterReplace['overall_status'] === 'partially_confirmed', "Overall package status transitioned to partially_confirmed waiting for replacement provider");
    assertCondition(!empty($sumAfterReplace['replacement_hotel']), "Summary contains replacement_hotel details");
    assertCondition(($sumAfterReplace['replacement_hotel']['status'] ?? '') === 'pending', "replacement_hotel status is pending in summary");
    $passed += 11;

    // =========================================================================
    // SCENARIO 2 & 6: Vehicle Cancels -> Replaced -> Replacement Section Visible & Pending, Original Preserved
    // =========================================================================
    echo "\n4. Testing Scenario 2 & 6: Original vehicle cancels, replacement vehicle submitted with Pending status, original preserved...\n";

    // Original vehicle provider cancels
    PackageBookingService::respond($testRef, 'vehicle', (int) $rental1['id'], 'cancel', 'Vehicle undergoing maintenance');

    // Guide replaces vehicle with vehicle2
    $repVehRes = PackageBookingService::replaceCancelledService(
        $testRef,
        $itemRentalId,
        (int) $rental2['id'],
        (int) $vehicle2['id'],
        [],
        (int) $guide['id']
    );

    assertCondition($repVehRes['success'] === true, "Vehicle replacement succeeded");

    // Check database records separation (Scenario 6)
    $origRentalItem = Database::first("SELECT * FROM package_booking_items WHERE id = ?", [$itemRentalId]);
    assertCondition($origRentalItem['status'] === 'cancelled', "Original cancelled vehicle item preserved with status cancelled");

    $repRentalItem = Database::first("SELECT * FROM package_booking_items WHERE replaces_item_id = ?", [$itemRentalId]);
    assertCondition($repRentalItem !== null, "New replacement vehicle item created in database");
    assertCondition($repRentalItem['status'] === 'pending', "Replacement vehicle item initially assigned Pending status");
    assertCondition((int) $repRentalItem['is_replacement'] === 1, "Replacement vehicle item flagged as is_replacement = 1");
    assertCondition((int) $repRentalItem['provider_id'] === (int) $rental2['id'], "Replacement vehicle item linked to replacement provider");

    // Check replacement rental request in rental_requests table
    $repRentalReq = Database::first("SELECT * FROM rental_requests WHERE id = ?", [(int) $repRentalItem['rental_request_id']]);
    assertCondition($repRentalReq['status'] === 'pending', "Replacement rental request status is pending in rental_requests table");

    // Check summary
    $sumAfterVehReplace = PackageBookingService::getPackageBookingSummary($testRef);
    assertCondition(!empty($sumAfterVehReplace['replacement_vehicle']), "Summary contains replacement_vehicle details");
    assertCondition(($sumAfterVehReplace['replacement_vehicle']['status'] ?? '') === 'pending', "replacement_vehicle status is pending in summary");
    $passed += 8;

    // =========================================================================
    // SCENARIO 3: Provider Booking Approvals display replacement providers instead of cancelled
    // =========================================================================
    echo "\n5. Testing Scenario 3: Provider Booking Approvals display replacement providers...\n";
    $activeApprovals = $sumAfterVehReplace['active_items'];
    $activeHotelProv = array_values(array_filter($activeApprovals, fn($i) => $i['service_type'] === 'hotel'))[0] ?? null;
    $activeRentalProv = array_values(array_filter($activeApprovals, fn($i) => $i['service_type'] === 'vehicle'))[0] ?? null;

    assertCondition($activeHotelProv !== null, "Active hotel approval is present");
    assertCondition((int) $activeHotelProv['provider_id'] === (int) $hotel2['id'], "Active hotel approval displays replacement hotel provider (#{$hotel2['id']})");
    assertCondition($activeHotelProv['status'] === 'pending', "Active hotel approval status is pending");

    assertCondition($activeRentalProv !== null, "Active rental approval is present");
    assertCondition((int) $activeRentalProv['provider_id'] === (int) $rental2['id'], "Active rental approval displays replacement rental provider (#{$rental2['id']})");
    assertCondition($activeRentalProv['status'] === 'pending', "Active rental approval status is pending");
    $passed += 6;

    // =========================================================================
    // SCENARIO 4: Replacement provider confirms or rejects -> updated status synchronized
    // =========================================================================
    echo "\n6. Testing Scenario 4: Replacement providers confirm -> statuses synchronized to confirmed...\n";

    // Replacement hotel provider confirms
    PackageBookingService::respond($testRef, 'hotel', (int) $hotel2['id'], 'confirm');

    $hotelCheckSum = PackageBookingService::getPackageBookingSummary($testRef);
    $activeHotelAfterConfirm = array_values(array_filter($hotelCheckSum['active_items'], fn($i) => $i['service_type'] === 'hotel'))[0] ?? null;
    assertCondition($activeHotelAfterConfirm['status'] === 'confirmed', "Replacement hotel approval status updated to confirmed");

    $repHotelSubBookingAfter = Database::first("SELECT * FROM bookings WHERE id = ?", [(int) $repHotelItem['sub_booking_id']]);
    assertCondition($repHotelSubBookingAfter['hotel_status'] === 'confirmed', "Replacement hotel sub-booking updated to confirmed");

    // Replacement vehicle provider confirms
    PackageBookingService::respond($testRef, 'vehicle', (int) $rental2['id'], 'confirm');

    $allConfirmedSum = PackageBookingService::getPackageBookingSummary($testRef);
    $activeRentalAfterConfirm = array_values(array_filter($allConfirmedSum['active_items'], fn($i) => $i['service_type'] === 'vehicle'))[0] ?? null;
    assertCondition($activeRentalAfterConfirm['status'] === 'confirmed', "Replacement vehicle approval status updated to confirmed");

    $repRentalReqAfter = Database::first("SELECT * FROM rental_requests WHERE id = ?", [(int) $repRentalItem['rental_request_id']]);
    assertCondition($repRentalReqAfter['status'] === 'approved', "Replacement rental request updated to approved");

    assertCondition($allConfirmedSum['overall_status'] === 'confirmed', "All replacements confirmed -> Overall package status becomes confirmed");
    $passed += 5;

    echo "\n======================================================\n";
    echo "SUMMARY: Passed: $passed, Failed: $failed\n";
    echo "ALL 7 TRAVEL PACKAGE REPLACEMENT & APPROVAL SCENARIOS PASSED!\n";

} catch (\Throwable $e) {
    echo "\n[ERROR] Exception encountered: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
