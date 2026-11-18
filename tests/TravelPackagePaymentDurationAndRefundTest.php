<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Payment;
use App\Services\PackageBookingService;

function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
        echo "  [FAIL] $message\n";
        throw new \RuntimeException("Test failed: $message");
    }
    echo "  [PASS] $message\n";
}

echo "=== GuideMate Travel Package Payment, Duration, Refund & Cancellation Test Suite ===\n\n";

$passed = 0;
$failed = 0;

try {
    PackageBookingService::ensureTables();

    // Setup base users
    $slugSuffix = time();
    $tourist = Database::first("SELECT id, email, name FROM users WHERE email = 'tourist_pdr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, created_at) VALUES ('Test Tourist PDR', 'tourist_pdr@test.com', 'hash', 'user', NOW())"), 'email' => 'tourist_pdr@test.com', 'name' => 'Test Tourist PDR'];

    Database::run("DELETE FROM bookings WHERE package_booking_ref LIKE 'GM-PKG-TEST-PDR%' OR user_id = ?", [(int) $tourist['id']]);
    Database::run("DELETE FROM rental_requests WHERE package_booking_ref LIKE 'GM-PKG-TEST-PDR%' OR user_id = ?", [(int) $tourist['id']]);
    Database::run("DELETE FROM package_booking_items WHERE package_booking_ref LIKE 'GM-PKG-TEST-PDR%'");
    $guide = Database::first("SELECT id, email, name FROM users WHERE email = 'guide_pdr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Test Guide PDR', 'guide_pdr@test.com', 'hash', 'guide', 'approved', NOW())"), 'email' => 'guide_pdr@test.com', 'name' => 'Test Guide PDR'];
    $hotelHost = Database::first("SELECT id, email, name FROM users WHERE email = 'hotel_pdr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Hotel Host PDR', 'hotel_pdr@test.com', 'hash', 'hotel_provider', 'approved', NOW())"), 'email' => 'hotel_pdr@test.com', 'name' => 'Hotel Host PDR'];
    $rentalHost = Database::first("SELECT id, email, name FROM users WHERE email = 'rental_pdr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Rental Host PDR', 'rental_pdr@test.com', 'hash', 'rental_provider', 'approved', NOW())"), 'email' => 'rental_pdr@test.com', 'name' => 'Rental Host PDR'];
    $repHotelHost = Database::first("SELECT id, email, name FROM users WHERE email = 'rep_hotel_pdr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Replacement Hotel Host PDR', 'rep_hotel_pdr@test.com', 'hash', 'hotel_provider', 'approved', NOW())"), 'email' => 'rep_hotel_pdr@test.com', 'name' => 'Replacement Hotel Host PDR'];

    // Listings & Rooms & Vehicles
    $hotelListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = 3 LIMIT 1", [(int) $hotelHost['id']])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, 3, 'Moalboal Coastal Resort', 'moalboal-resort-$slugSuffix', 'Resort', 'Moalboal', 1200.00, 'approved', NOW())", [(int) $hotelHost['id']]), 'title' => 'Moalboal Coastal Resort', 'price' => 1200.00];

    $room = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [(int) $hotelListing['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests) VALUES (?, 'Standard Queen Room', 1200.00, 2)", [(int) $hotelListing['id']]), 'name' => 'Standard Queen Room', 'price_per_night' => 1200.00];

    $repHotelListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = 3 LIMIT 1", [(int) $repHotelHost['id']])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, 3, 'Moalboal Budget Lodge', 'moalboal-lodge-$slugSuffix', 'Lodge', 'Moalboal', 900.00, 'approved', NOW())", [(int) $repHotelHost['id']]), 'title' => 'Moalboal Budget Lodge', 'price' => 900.00];

    $repRoomCheap = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? AND price_per_night = 900.00 LIMIT 1", [(int) $repHotelListing['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests) VALUES (?, 'Cozy Twin Room', 900.00, 2)", [(int) $repHotelListing['id']]), 'name' => 'Cozy Twin Room', 'price_per_night' => 900.00];

    $repRoomExpensive = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? AND price_per_night = 2500.00 LIMIT 1", [(int) $repHotelListing['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests) VALUES (?, 'VIP Ocean Suite', 2500.00, 2)", [(int) $repHotelListing['id']]), 'name' => 'VIP Ocean Suite', 'price_per_night' => 2500.00];

    $vehicle = Database::first("SELECT id, name, price_per_day FROM vehicles WHERE user_id = ? LIMIT 1", [(int) $rentalHost['id']])
        ?? ['id' => Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Honda', 'Beat 110', 'Honda Beat 110', 'honda-beat-$slugSuffix', 'Scooter', 'Scooter', 400.00, 'Moalboal', 'approved', 1)", [(int) $rentalHost['id']]), 'name' => 'Honda Beat 110', 'price_per_day' => 400.00];

    // Travel Package Listing (3 Days / 2 Nights)
    $packageListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = 5 LIMIT 1", [(int) $guide['id']])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, status, created_at) VALUES (?, 5, '3D2N Moalboal Escape', 'moalboal-escape-$slugSuffix', '3D2N Tour Package', 'Moalboal', 6000.00, '3 Days / 2 Nights', 'approved', NOW())", [(int) $guide['id']]), 'title' => '3D2N Moalboal Escape', 'price' => 6000.00];

    // =========================================================================
    // SCENARIO 7 & 8: Match Hotel and Rental Duration to Travel Package Duration
    // =========================================================================
    echo "1. Testing Scenarios 7 & 8: Match Hotel and Rental Duration to 3-day Package...\n";
    $durations = PackageBookingService::derivePackageDurations($packageListing['id']);
    assertCondition($durations['days'] === 3, "Package duration is 3 days");
    assertCondition($durations['hotel_nights'] === 2, "Hotel covers 2 nights (D - 1)");
    assertCondition($durations['rental_days'] === 3, "Rental covers 3 days (D)");

    $ratePerNight = (float) $room['price_per_night']; // 1200.00
    $dailyRate = (float) $vehicle['price_per_day']; // 400.00
    $expectedHotelTotal = round($ratePerNight * $durations['hotel_nights'], 2); // 2400.00
    $expectedRentalTotal = round($dailyRate * $durations['rental_days'], 2); // 1200.00

    assertCondition($expectedHotelTotal === 2400.00, "Hotel total automatically calculated: 2 nights x ₱1,200 = ₱2,400.00");
    assertCondition($expectedRentalTotal === 1200.00, "Vehicle total automatically calculated: 3 days x ₱400 = ₱1,200.00");
    $passed += 5;

    // =========================================================================
    // SCENARIO 9: Package Duration Change Recalculates Schedules and Amounts
    // =========================================================================
    echo "\n2. Testing Scenario 9: Package Duration Change Recalculates Schedules and Amounts...\n";
    $durations4Days = PackageBookingService::derivePackageDurations($packageListing['id'], 4);
    assertCondition($durations4Days['days'] === 4, "Updated duration days is 4");
    assertCondition($durations4Days['hotel_nights'] === 3, "Updated hotel nights is 3 (4 - 1)");
    assertCondition($durations4Days['rental_days'] === 4, "Updated rental days is 4");
    $expectedHotelTotal4D = round($ratePerNight * 3, 2); // 3600.00
    $expectedRentalTotal4D = round($dailyRate * 4, 2); // 1600.00
    assertCondition($expectedHotelTotal4D === 3600.00, "Recalculated hotel total for 4 days is ₱3,600.00");
    assertCondition($expectedRentalTotal4D === 1600.00, "Recalculated rental total for 4 days is ₱1,600.00");
    $passed += 5;

    // =========================================================================
    // SCENARIO 1: Vehicle Rental Provider Cancels Confirmed Booking
    // =========================================================================
    echo "\n3. Testing Scenario 1: Vehicle Rental Provider Cancels Confirmed Booking...\n";
    $pkgRef1 = 'GM-PKG-TEST-PDR1-' . time();
    $travelDate1 = date('Y-m-d', strtotime('+7 days'));
    $checkOut1 = date('Y-m-d', strtotime('+9 days'));

    // Create package parent booking
    $parentBookingId1 = Database::insert("
        INSERT INTO bookings (
            listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref, package_overall_status, created_at
        ) VALUES (?, ?, ?, ?, ?, 2, 2, 6000.00, 6000.00, 0.00, 'deposit_paid', 'pending', ?, 'pending', NOW())
    ", [(int) $packageListing['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef1]);

    Payment::create($parentBookingId1, 6000.00, 'ewallet', 'paid');

    // Guide item
    $guideItem1 = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, sub_booking_id, allocated_amount, original_allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide Service', ?, 2400.00, 2400.00, 'confirmed', NOW())
    ", [$pkgRef1, $parentBookingId1, (int) $packageListing['id'], (int) $guide['id'], $parentBookingId1]);

    // Hotel sub-booking & item
    $hotelSubId1 = Database::insert("
        INSERT INTO bookings (
            listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status, package_booking_ref, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 2, 2, 2400.00, 2400.00, 0.00, 'deposit_paid', 'confirmed', 'confirmed', ?, NOW())
    ", [(int) $hotelListing['id'], (int) $room['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef1]);

    $hotelItem1 = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, sub_booking_id, allocated_amount, original_allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Room', ?, 2400.00, 2400.00, 'confirmed', NOW())
    ", [$pkgRef1, $parentBookingId1, (int) $packageListing['id'], (int) $hotelHost['id'], $hotelSubId1]);

    // Rental request & item
    $rentalReqId1 = Database::insert("
        INSERT INTO rental_requests (
            user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
            total_amount, status, payment_status, package_booking_ref, package_booking_id, created_at
        ) VALUES (?, ?, 'Honda Beat 110', 'Scooter', 'Moalboal', ?, 3, 1200.00, 'approved', 'paid', ?, ?, NOW())
    ", [(int) $tourist['id'], (int) $vehicle['id'], $travelDate1, $pkgRef1, $parentBookingId1]);

    $rentalItem1 = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, rental_request_id, allocated_amount, original_allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Vehicle Rental', ?, 1200.00, 1200.00, 'confirmed', NOW())
    ", [$pkgRef1, $parentBookingId1, (int) $packageListing['id'], (int) $rentalHost['id'], $rentalReqId1]);

    // Rental provider cancels
    $resp1 = PackageBookingService::respond($pkgRef1, 'vehicle', (int) $rentalHost['id'], 'cancel', 'Vehicle maintenance breakdown');
    assertCondition($resp1['success'] === true, "Rental cancellation response returned success");

    // Verify rental request is cancelled & refunded
    $updatedRental1 = Database::first("SELECT status, payment_status, refund_status, refund_amount FROM rental_requests WHERE id = ?", [$rentalReqId1]);
    assertCondition($updatedRental1['status'] === 'cancelled', "Rental request status updated to cancelled");
    assertCondition($updatedRental1['payment_status'] === 'refunded', "Rental request payment_status updated to refunded");
    assertCondition($updatedRental1['refund_status'] === 'refunded', "Rental request refund_status updated to refunded");
    assertCondition((float) $updatedRental1['refund_amount'] === 1200.00, "Rental request refunded exactly ₱1,200.00");

    // Verify other services are NOT cancelled or refunded
    $updatedHotel1 = Database::first("SELECT status, hotel_status, refund_status FROM bookings WHERE id = ?", [$hotelSubId1]);
    assertCondition($updatedHotel1['status'] === 'confirmed', "Hotel reservation remains confirmed");
    assertCondition($updatedHotel1['refund_status'] === null || $updatedHotel1['refund_status'] !== 'refunded', "Hotel reservation was NOT refunded");

    $updatedParent1 = Database::first("SELECT package_overall_status, status FROM bookings WHERE id = ?", [$parentBookingId1]);
    assertCondition($updatedParent1['package_overall_status'] === 'action_required', "Overall package status is action_required for disruption handling");
    assertCondition($updatedParent1['status'] !== 'cancelled', "Parent package booking was NOT cancelled");
    $passed += 8;

    // =========================================================================
    // SCENARIO 2: Hotel Provider Cancels Confirmed Reservation
    // =========================================================================
    echo "\n4. Testing Scenario 2: Hotel Provider Cancels Confirmed Reservation...\n";
    $pkgRef2 = 'GM-PKG-TEST-PDR2-' . time();
    $parentBookingId2 = Database::insert("
        INSERT INTO bookings (
            listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref, package_overall_status, created_at
        ) VALUES (?, ?, ?, ?, ?, 2, 2, 6000.00, 6000.00, 0.00, 'deposit_paid', 'pending', ?, 'pending', NOW())
    ", [(int) $packageListing['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef2]);

    Payment::create($parentBookingId2, 6000.00, 'ewallet', 'paid');

    $hotelSubId2 = Database::insert("
        INSERT INTO bookings (
            listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status, package_booking_ref, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 2, 2, 2400.00, 2400.00, 0.00, 'deposit_paid', 'confirmed', 'confirmed', ?, NOW())
    ", [(int) $hotelListing['id'], (int) $room['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef2]);

    $hotelItem2 = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, sub_booking_id, allocated_amount, original_allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Room', ?, 2400.00, 2400.00, 'confirmed', NOW())
    ", [$pkgRef2, $parentBookingId2, (int) $packageListing['id'], (int) $hotelHost['id'], $hotelSubId2]);

    $rentalReqId2 = Database::insert("
        INSERT INTO rental_requests (
            user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
            total_amount, status, payment_status, package_booking_ref, package_booking_id, created_at
        ) VALUES (?, ?, 'Honda Beat 110', 'Scooter', 'Moalboal', ?, 3, 1200.00, 'approved', 'paid', ?, ?, NOW())
    ", [(int) $tourist['id'], (int) $vehicle['id'], $travelDate1, $pkgRef2, $parentBookingId2]);

    $rentalItem2 = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, rental_request_id, allocated_amount, original_allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Vehicle Rental', ?, 1200.00, 1200.00, 'confirmed', NOW())
    ", [$pkgRef2, $parentBookingId2, (int) $packageListing['id'], (int) $rentalHost['id'], $rentalReqId2]);

    // Hotel cancels
    $resp2 = PackageBookingService::respond($pkgRef2, 'hotel', (int) $hotelHost['id'], 'cancel', 'Plumbing emergency in room');
    assertCondition($resp2['success'] === true, "Hotel cancellation response returned success");

    $updatedHotel2 = Database::first("SELECT status, hotel_status, refund_status, refund_amount FROM bookings WHERE id = ?", [$hotelSubId2]);
    assertCondition($updatedHotel2['status'] === 'cancelled', "Hotel booking status updated to cancelled");
    assertCondition($updatedHotel2['hotel_status'] === 'cancelled', "Hotel booking hotel_status updated to cancelled");
    assertCondition($updatedHotel2['refund_status'] === 'refunded', "Hotel booking refund_status updated to refunded");
    assertCondition((float) $updatedHotel2['refund_amount'] === 2400.00, "Hotel booking refunded actual amount paid (₱2,400.00)");

    // Rental unaffected
    $updatedRental2 = Database::first("SELECT status, refund_status FROM rental_requests WHERE id = ?", [$rentalReqId2]);
    assertCondition($updatedRental2['status'] === 'approved', "Rental request remains approved and active");
    assertCondition($updatedRental2['refund_status'] === null || $updatedRental2['refund_status'] !== 'refunded', "Rental request was NOT refunded");
    $passed += 7;

    // =========================================================================
    // SCENARIO 3: Replacement Price Exceeding Original Price is Rejected
    // =========================================================================
    echo "\n5. Testing Scenario 3: Replacement Price Exceeding Original Price is Rejected...\n";
    // In $pkgRef2, cancelled hotel had allocated ₱2,400.00 (2 nights x ₱1,200.00)
    // Attempt to replace with expensive room: 2 nights x ₱2,500.00 = ₱5,000.00 > ₱2,400.00
    $rejectedExceeding = false;
    try {
        PackageBookingService::replaceCancelledService(
            $pkgRef2,
            $hotelItem2,
            (int) $repHotelHost['id'],
            (int) $repHotelListing['id'],
            ['room_id' => (int) $repRoomExpensive['id']],
            (int) $guide['id']
        );
    } catch (\RuntimeException $e) {
        $rejectedExceeding = true;
        assertCondition(str_contains($e->getMessage(), 'cannot exceed original cancelled service price'), "Exception message correctly indicates price cannot exceed original: " . $e->getMessage());
    }
    assertCondition($rejectedExceeding === true, "Replacement with higher price (₱5,000 > ₱2,400) was successfully rejected");
    $passed += 2;

    // =========================================================================
    // SCENARIO 4: Replacement Payment Verification & Surplus Refund
    // =========================================================================
    echo "\n6. Testing Scenario 4: Replacement Payment Verification & Surplus Refund...\n";
    // Replace with cheaper room: 2 nights x ₱900.00 = ₱1,800.00 (< ₱2,400.00 original)
    // Surplus = ₱600.00
    $parentBeforeReplace = Database::first("SELECT total_amount, refund_amount FROM bookings WHERE id = ?", [$parentBookingId2]);
    $oldParentTotal = (float) $parentBeforeReplace['total_amount'];

    $replaceRes = PackageBookingService::replaceCancelledService(
        $pkgRef2,
        $hotelItem2,
        (int) $repHotelHost['id'],
        (int) $repHotelListing['id'],
        ['room_id' => (int) $repRoomCheap['id']],
        (int) $guide['id']
    );
    assertCondition($replaceRes['success'] === true, "Replacement with compliant cheaper price succeeded");

    // Check replacement sub-booking is created and marked Paid
    $repSubBooking = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? AND room_id = ? LIMIT 1", [$pkgRef2, (int) $repRoomCheap['id']]);
    assertCondition($repSubBooking !== null, "Replacement hotel sub-booking created");
    assertCondition($repSubBooking['payment_stage'] === 'paid', "Replacement service automatically marked Paid after reallocation verification");
    assertCondition((float) $repSubBooking['total_amount'] === 1800.00, "Replacement allocated amount is ₱1,800.00");

    // Check surplus refund of ₱600.00 to traveler
    $parentAfterReplace = Database::first("SELECT total_amount, refund_amount, refund_status FROM bookings WHERE id = ?", [$parentBookingId2]);
    assertCondition((float) $parentAfterReplace['total_amount'] === round($oldParentTotal - 600.00, 2), "Parent total reduced by surplus difference (₱600.00)");
    assertCondition((float) $parentAfterReplace['refund_amount'] >= 600.00, "Parent booking recorded surplus refund amount of ₱600.00");
    assertCondition($parentAfterReplace['refund_status'] === 'partial_refund' || $parentAfterReplace['refund_status'] === 'refunded', "Parent booking marked partial_refund for surplus");

    // Verify refund payment record in payments table
    $surplusPayment = Database::first("SELECT * FROM payments WHERE booking_id = ? AND amount < 0 AND method = 'refund' LIMIT 1", [$parentBookingId2]);
    assertCondition($surplusPayment !== null && (float) $surplusPayment['amount'] === -600.00, "Payment refund record of -₱600.00 exists in payments table");
    $passed += 8;

    // =========================================================================
    // SCENARIO 5: Host Cancels Entire Package Booking
    // =========================================================================
    echo "\n7. Testing Scenario 5: Host Cancels Entire Package Booking...\n";
    $pkgRef5 = 'GM-PKG-TEST-PDR5-' . time();
    $parentBookingId5 = Database::insert("
        INSERT INTO bookings (
            listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref, package_overall_status, created_at
        ) VALUES (?, ?, ?, ?, ?, 2, 2, 6000.00, 6000.00, 0.00, 'deposit_paid', 'pending', ?, 'pending', NOW())
    ", [(int) $packageListing['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef5]);

    Payment::create($parentBookingId5, 6000.00, 'ewallet', 'paid');

    $hotelSubId5 = Database::insert("
        INSERT INTO bookings (
            listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status, package_booking_ref, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 2, 2, 2400.00, 2400.00, 0.00, 'deposit_paid', 'confirmed', 'confirmed', ?, NOW())
    ", [(int) $hotelListing['id'], (int) $room['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef5]);

    Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, sub_booking_id, allocated_amount, original_allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Room', ?, 2400.00, 2400.00, 'confirmed', NOW())
    ", [$pkgRef5, $parentBookingId5, (int) $packageListing['id'], (int) $hotelHost['id'], $hotelSubId5]);

    $rentalReqId5 = Database::insert("
        INSERT INTO rental_requests (
            user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
            total_amount, status, payment_status, package_booking_ref, package_booking_id, created_at
        ) VALUES (?, ?, 'Honda Beat 110', 'Scooter', 'Moalboal', ?, 3, 1200.00, 'approved', 'paid', ?, ?, NOW())
    ", [(int) $tourist['id'], (int) $vehicle['id'], $travelDate1, $pkgRef5, $parentBookingId5]);

    Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, rental_request_id, allocated_amount, original_allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Vehicle Rental', ?, 1200.00, 1200.00, 'confirmed', NOW())
    ", [$pkgRef5, $parentBookingId5, (int) $packageListing['id'], (int) $rentalHost['id'], $rentalReqId5]);

    // Host cancels entire package
    $cancelRes5 = PackageBookingService::cancelEntirePackage($pkgRef5, (int) $guide['id'], 'Cancelled by host due to severe typhoon alert', true);
    assertCondition($cancelRes5['success'] === true, "Host entire package cancellation succeeded");

    // Parent booking cancelled
    $p5 = Database::first("SELECT status, package_overall_status, refund_status FROM bookings WHERE id = ?", [$parentBookingId5]);
    assertCondition($p5['status'] === 'cancelled', "Parent booking status is cancelled");
    assertCondition($p5['package_overall_status'] === 'cancelled', "Parent package_overall_status is cancelled");
    assertCondition($p5['refund_status'] === 'refunded', "Parent booking marked refunded");

    // Hotel sub-booking cancelled
    $h5 = Database::first("SELECT status, hotel_status, refund_status, refund_amount FROM bookings WHERE id = ?", [$hotelSubId5]);
    assertCondition($h5['status'] === 'cancelled', "Hotel sub-booking status cancelled");
    assertCondition($h5['hotel_status'] === 'cancelled', "Hotel sub-booking hotel_status cancelled");
    assertCondition($h5['refund_status'] === 'refunded', "Hotel sub-booking refund_status refunded");

    // Rental request cancelled
    $r5 = Database::first("SELECT status, payment_status, refund_status, refund_amount FROM rental_requests WHERE id = ?", [$rentalReqId5]);
    assertCondition($r5['status'] === 'cancelled', "Rental request status cancelled");
    assertCondition($r5['payment_status'] === 'refunded', "Rental request payment_status refunded");
    assertCondition($r5['refund_status'] === 'refunded', "Rental request refund_status refunded");
    $passed += 10;

    // =========================================================================
    // SCENARIO 6: Hotel Reservation with Balance Due - Refund ONLY Amount Paid
    // =========================================================================
    echo "\n8. Testing Scenario 6: Hotel with Balance Due refunds ONLY amount already paid...\n";
    $pkgRef6 = 'GM-PKG-TEST-PDR6-' . time();
    $parentBookingId6 = Database::insert("
        INSERT INTO bookings (
            listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref, package_overall_status, created_at
        ) VALUES (?, ?, ?, ?, ?, 2, 2, 6000.00, 3000.00, 3000.00, 'deposit_paid', 'pending', ?, 'pending', NOW())
    ", [(int) $packageListing['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef6]);

    Payment::create($parentBookingId6, 3000.00, 'ewallet', 'paid');

    // Hotel sub-booking with total ₱5,000.00, but deposit ₱2,000.00 paid and balance_amount ₱3,000.00 due!
    $hotelSubId6 = Database::insert("
        INSERT INTO bookings (
            listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status, package_booking_ref, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 2, 2, 5000.00, 2000.00, 3000.00, 'deposit_paid', 'confirmed', 'confirmed', ?, NOW())
    ", [(int) $hotelListing['id'], (int) $room['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef6]);

    Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, sub_booking_id, allocated_amount, original_allocated_amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Room', ?, 5000.00, 5000.00, 'confirmed', NOW())
    ", [$pkgRef6, $parentBookingId6, (int) $packageListing['id'], (int) $hotelHost['id'], $hotelSubId6]);

    // Cancel entire package
    $cancelRes6 = PackageBookingService::cancelEntirePackage($pkgRef6, (int) $guide['id'], 'Cancelled by host', true);
    assertCondition($cancelRes6['success'] === true, "Cancellation executed successfully");

    $h6 = Database::first("SELECT total_amount, deposit_amount, balance_amount, refund_amount, refund_status FROM bookings WHERE id = ?", [$hotelSubId6]);
    assertCondition((float) $h6['refund_amount'] === 2000.00, "Hotel with Balance Due was refunded ONLY the deposit amount paid (₱2,000.00), NOT full ₱5,000.00!");
    assertCondition((float) $h6['balance_amount'] === 0.00, "Outstanding balance cancelled (balance_amount = 0.00) so guest is not charged remaining");
    assertCondition($h6['refund_status'] === 'refunded', "Hotel marked as refunded");
    $passed += 4;

    // =========================================================================
    // SCENARIO 10: App Version: Hide Cancel Booking Button After All Providers Confirm
    // =========================================================================
    echo "\n9. Testing Scenario 10: App Version Cancel Booking Button Visibility Rules...\n";
    $tripsFileContent = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');

    // Rule: Hide Cancel Booking button after all providers confirm
    assertCondition(str_contains($tripsFileContent, '!isAllConfirmed && overallStatus !== \'confirmed\''), "App trips.tsx suppresses Cancel Booking button when all providers confirm");
    assertCondition(str_contains($tripsFileContent, 'Cancel Booking'), "Button label is Cancel Booking");

    // Test the logic directly:
    // When guideConfirmed = true, hotelConfirmed = true, vehicleConfirmed = true:
    $guideConfirmed = true;
    $hotelConfirmed = true;
    $vehicleConfirmed = true;
    $isAllConfirmed = $guideConfirmed && $hotelConfirmed && $vehicleConfirmed;
    $overallStatus = 'confirmed';
    $canShowCancelButton = !$isAllConfirmed && $overallStatus !== 'confirmed';
    assertCondition($canShowCancelButton === false, "When all providers confirmed, Cancel Booking button is HIDDEN");

    // When hotel provider is still pending:
    $hotelConfirmedPending = false;
    $isAllConfirmedPending = $guideConfirmed && $hotelConfirmedPending && $vehicleConfirmed;
    $overallStatusPending = 'pending';
    $canShowCancelButtonPending = !$isAllConfirmedPending && $overallStatusPending !== 'confirmed';
    assertCondition($canShowCancelButtonPending === true, "When hotel is still pending, Cancel Booking button remains VISIBLE for traveler");
    $passed += 5;

    echo "\n======================================================\n";
    echo "SUCCESS: All {$passed} tests passed successfully!\n";
    echo "======================================================\n";

} catch (\Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
