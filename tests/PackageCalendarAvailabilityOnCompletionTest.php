<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\GuideAvailability;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\ListingSchedule;
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

echo "=== GuideMate Package Reservation Calendar Availability Test Suite ===\n\n";

try {
    PackageBookingService::ensureTables();

    $suffix = time();

    // 1. Setup Test Users
    $touristA = Database::first("SELECT id, email, name FROM users WHERE email = 'tourist_a_cal@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, created_at) VALUES ('Tourist A', 'tourist_a_cal@test.com', 'hash', 'tourist', NOW())"), 'email' => 'tourist_a_cal@test.com', 'name' => 'Tourist A'];

    $touristB = Database::first("SELECT id, email, name FROM users WHERE email = 'tourist_b_cal@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, created_at) VALUES ('Tourist B', 'tourist_b_cal@test.com', 'hash', 'tourist', NOW())"), 'email' => 'tourist_b_cal@test.com', 'name' => 'Tourist B'];

    $guideUser = Database::first("SELECT id, email, name FROM users WHERE email = 'guide_cal@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Guide Host Cal', 'guide_cal@test.com', 'hash', 'guide', 'approved', NOW())"), 'email' => 'guide_cal@test.com', 'name' => 'Guide Host Cal'];

    $hotelProvider = Database::first("SELECT id, email, name FROM users WHERE email = 'hotel_cal@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Hotel Partner Cal', 'hotel_cal@test.com', 'hash', 'hotel_provider', 'approved', NOW())"), 'email' => 'hotel_cal@test.com', 'name' => 'Hotel Partner Cal'];

    $rentalProvider = Database::first("SELECT id, email, name FROM users WHERE email = 'rental_cal@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Rental Partner Cal', 'rental_cal@test.com', 'hash', 'rental_provider', 'approved', NOW())"), 'email' => 'rental_cal@test.com', 'name' => 'Rental Partner Cal'];

    $catPackages = (int) (Database::first("SELECT id FROM categories WHERE slug IN ('packages', 'travel-packages') LIMIT 1")['id'] ?? 5);
    $catHotels = (int) (Database::first("SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1")['id'] ?? 3);

    // Setup hotel listing & room
    $hotelListing = Database::first("SELECT id, title FROM listings WHERE user_id = ? AND category_id = ? LIMIT 1", [(int) $hotelProvider['id'], $catHotels])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, ?, 'Cal Beach Resort', 'cal-beach-resort-$suffix', 'Resort', 'Moalboal', 2500.00, 'approved', NOW())", [(int) $hotelProvider['id'], $catHotels]), 'title' => 'Cal Beach Resort'];

    $room = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [(int) $hotelListing['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests, total_units) VALUES (?, 'Cal Suite', 2500.00, 4, 10)", [(int) $hotelListing['id']]), 'name' => 'Cal Suite', 'price_per_night' => 2500.00];

    // Setup vehicle
    $vehicle = Database::first("SELECT id, name FROM vehicles WHERE user_id = ? LIMIT 1", [(int) $rentalProvider['id']])
        ?? ['id' => Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, passenger_capacity, status, is_available) VALUES (?, 'Yamaha', 'Aerox', 'Cal Aerox', 'cal-aerox-$suffix', 'Scooter', 'Scooter', 700.00, 'Moalboal', 2, 'approved', 1)", [(int) $rentalProvider['id']]), 'name' => 'Cal Aerox'];

    // Setup package listing (1-day tour for precise single-date testing)
    $packageListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = ? LIMIT 1", [(int) $guideUser['id'], $catPackages])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, capacity, status, created_at) VALUES (?, ?, 'Cal Package 1D', 'cal-pkg-1d-$suffix', '1-Day Package', 'Moalboal', 4999.00, '1 Day', 6, 'approved', NOW())", [(int) $guideUser['id'], $catPackages]), 'title' => 'Cal Package 1D', 'price' => 4999.00];
    $pkgId = (int) $packageListing['id'];

    // Link package services
    Database::run("DELETE FROM package_services WHERE package_id = ?", [$pkgId]);
    Database::insert("INSERT INTO package_services (package_id, service_type, provider_id, listing_id, hotel_room_id, agreed_rate, status) VALUES (?, 'hotel', ?, ?, ?, 2500.00, 'agreed')", [
        $pkgId,
        (int) $hotelProvider['id'],
        (int) $hotelListing['id'],
        (int) $room['id'],
    ]);
    Database::insert("INSERT INTO package_services (package_id, service_type, provider_id, vehicle_id, agreed_rate, status) VALUES (?, 'vehicle', ?, ?, 700.00, 'agreed')", [
        $pkgId,
        (int) $rentalProvider['id'],
        (int) $vehicle['id'],
    ]);

    // Clean any prior test bookings
    Database::run("DELETE FROM bookings WHERE package_booking_ref LIKE 'GM-PKG-CAL%' OR user_id IN (?, ?)", [(int) $touristA['id'], (int) $touristB['id']]);
    Database::run("DELETE FROM package_booking_items WHERE package_booking_ref LIKE 'GM-PKG-CAL%'");
    Database::run("DELETE FROM listing_schedules WHERE listing_id = ?", [$pkgId]);
    Database::run("DELETE FROM guide_availability WHERE listing_id = ?", [$pkgId]);

    // -------------------------------------------------------------------------
    // TEST 1: A Completed Booking Activates the Calendar Date
    // -------------------------------------------------------------------------
    echo "--- Test 1: Completed Package Booking Automatically Activates Date ---\n";
    $date1 = date('Y-m-d', strtotime('+15 days'));

    // Verify Date 1 is initially available
    $initialBooked = Booking::bookedDates($pkgId);
    assertCondition(!in_array($date1, $initialBooked, true), "Date 1 ($date1) is initially active/available");
    assertCondition(!Booking::isDateBooked($pkgId, $date1), "Booking::isDateBooked returns false initially for Date 1");

    // Tourist A books Date 1
    $bookingRes1 = PackageBookingService::createPackageBooking($pkgId, (int) $touristA['id'], [
        'guestsCount' => 2,
        'travelDate' => $date1,
        'contactName' => 'Tourist A',
        'contactPhone' => '09123456789',
        'notes' => 'Test 1 initial booking',
    ]);
    assertCondition($bookingRes1['success'] === true, "Booking 1 created successfully");
    $pkgRef1 = $bookingRes1['package_booking_ref'];
    $bkId1 = (int) $bookingRes1['parent_booking_id'];

    // While pending or confirmed, Date 1 MUST be inactive / booked
    $bookedWhileActive = Booking::bookedDates($pkgId);
    assertCondition(in_array($date1, $bookedWhileActive, true), "Date 1 is now marked booked/inactive during active reservation");
    assertCondition(Booking::isDateBooked($pkgId, $date1), "Booking::isDateBooked returns true during active reservation");

    // All providers confirm
    PackageBookingService::respond($pkgRef1, 'guide', (int) $guideUser['id'], 'confirm');
    PackageBookingService::respond($pkgRef1, 'hotel', (int) $hotelProvider['id'], 'confirm');
    PackageBookingService::respond($pkgRef1, 'vehicle', (int) $rentalProvider['id'], 'confirm');

    $confirmedBooked = Booking::bookedDates($pkgId);
    assertCondition(in_array($date1, $confirmedBooked, true), "Date 1 remains booked while package is confirmed");

    // Now, Trip is Completed successfully
    $completeRes = PackageBookingService::completePackageBooking($pkgRef1, $bkId1);
    assertCondition($completeRes['overall_status'] === 'completed', "Package booking marked completed");

    // Verify Date 1 is automatically activated / available
    $bookedAfterComplete = Booking::bookedDates($pkgId);
    assertCondition(!in_array($date1, $bookedAfterComplete, true), "Completed booking activates Date 1 (date is no longer in bookedDates)");
    assertCondition(!Booking::isDateBooked($pkgId, $date1), "Booking::isDateBooked returns false after booking is completed");

    // -------------------------------------------------------------------------
    // TEST 2: A Date with an Existing Booking Schedule is NOT Changed
    // -------------------------------------------------------------------------
    echo "\n--- Test 2: Date with Existing Booking Schedule Preserves Its Status ---\n";
    $date2 = date('Y-m-d', strtotime('+20 days'));

    // Scenario 2A: Another active booking exists on the same date for the guide
    echo "  [Scenario 2A: Second active booking on same date preserves booked status]\n";
    // Create first booking on Date 2
    $resA = PackageBookingService::createPackageBooking($pkgId, (int) $touristA['id'], [
        'guestsCount' => 2,
        'travelDate' => $date2,
        'contactName' => 'Tourist A',
        'contactPhone' => '09123456789',
    ]);
    $refA = $resA['package_booking_ref'];
    $idA = (int) $resA['parent_booking_id'];

    // Create another booking record on Date 2 (e.g. direct tour booking on same guide)
    $secondBookingId = Database::insert("
        INSERT INTO bookings (listing_id, user_id, booking_date, guests, total_amount, status, created_at, updated_at)
        VALUES (?, ?, ?, 2, 2000.00, 'confirmed', NOW(), NOW())
    ", [$pkgId, (int) $touristB['id'], $date2]);

    assertCondition(in_array($date2, Booking::bookedDates($pkgId), true), "Date 2 is booked with multiple bookings");

    // Complete Booking A
    PackageBookingService::completePackageBooking($refA, $idA);
    $checkA = Database::first("SELECT status FROM bookings WHERE id = ?", [$idA]);
    assertCondition($checkA['status'] === 'completed', "Booking A is completed");

    // Because Second Booking is STILL confirmed on Date 2, Date 2 MUST NOT be reactivated
    $afterCompleteWithSecondBooking = Booking::bookedDates($pkgId);
    assertCondition(in_array($date2, $afterCompleteWithSecondBooking, true), "Date 2 remains booked because another active booking schedule exists on that date");
    assertCondition(Booking::isDateBooked($pkgId, $date2), "isDateBooked remains true for Date 2 with existing booking schedule");

    // Clean up second booking
    Database::run("DELETE FROM bookings WHERE id = ?", [$secondBookingId]);

    // Now with second booking removed, Date 2 becomes active
    assertCondition(!in_array($date2, Booking::bookedDates($pkgId), true), "Date 2 activates once all active schedules on that date are resolved");

    // Scenario 2B: Date has an existing schedule plotted in listing_schedules
    echo "  [Scenario 2B: Plotted schedule in listing_schedules preserves booked status]\n";
    $date3 = date('Y-m-d', strtotime('+25 days'));

    // Create a package booking on Date 3 before schedule is added
    $resC = PackageBookingService::createPackageBooking($pkgId, (int) $touristA['id'], [
        'guestsCount' => 2,
        'travelDate' => $date3,
        'contactName' => 'Tourist A',
        'contactPhone' => '09123456789',
    ]);
    $refC = $resC['package_booking_ref'];
    $idC = (int) $resC['parent_booking_id'];

    // Add guide schedule in listing_schedules for Date 3 (e.g. guide schedules session)
    ListingSchedule::add($pkgId, $date3, '09:00', '17:00', 4, 'Scheduled special session');
    assertCondition(in_array($date3, Booking::bookedDates($pkgId), true), "Date 3 is recognized as unavailable/booked");

    // Complete booking C
    PackageBookingService::completePackageBooking($refC, $idC);

    // Because listing_schedules STILL has an entry for Date 3, Date 3 MUST remain preserved as unavailable
    $afterCompleteWithListingSchedule = Booking::bookedDates($pkgId);
    assertCondition(in_array($date3, $afterCompleteWithListingSchedule, true), "Date 3 preserves booked status because of existing listing_schedules entry");
    assertCondition(Booking::isDateBooked($pkgId, $date3), "isDateBooked returns true preserving existing listing schedule");

    // Booking attempt on Date 3 is blocked by the existing schedule
    $blockedBySchedule = false;
    try {
        PackageBookingService::createPackageBooking($pkgId, (int) $touristB['id'], [
            'guestsCount' => 2,
            'travelDate' => $date3,
            'contactName' => 'Tourist B Attempt on Date 3',
            'contactPhone' => '09123456789',
        ]);
    } catch (\Throwable $e) {
        $blockedBySchedule = true;
    }
    assertCondition($blockedBySchedule === true, "New booking attempt on Date 3 is blocked by existing listing schedule");

    // Scenario 2C: Date has a block in guide_availability
    echo "  [Scenario 2C: Manual block in guide_availability preserves booked status]\n";
    $date4 = date('Y-m-d', strtotime('+30 days'));
    GuideAvailability::block($pkgId, $date4, 'Maintenance block');
    assertCondition(in_array($date4, Booking::bookedDates($pkgId), true), "Date 4 is recognized as unavailable due to guide_availability");

    // -------------------------------------------------------------------------
    // TEST 3: A Failed or Incomplete Booking Does NOT Activate the Date
    // -------------------------------------------------------------------------
    echo "\n--- Test 3: Failed or Incomplete Booking Does NOT Activate the Date ---\n";
    $date5 = date('Y-m-d', strtotime('+35 days'));

    // Create booking on Date 5
    $resIncomp = PackageBookingService::createPackageBooking($pkgId, (int) $touristA['id'], [
        'guestsCount' => 2,
        'travelDate' => $date5,
        'contactName' => 'Tourist A',
        'contactPhone' => '09123456789',
    ]);
    $refIncomp = $resIncomp['package_booking_ref'];
    $idIncomp = (int) $resIncomp['parent_booking_id'];

    // While status is pending, date is NOT active
    assertCondition(in_array($date5, Booking::bookedDates($pkgId), true), "Incomplete (pending) booking does NOT activate Date 5");

    // Provider partially confirms, overall status is partially_confirmed
    PackageBookingService::respond($refIncomp, 'guide', (int) $guideUser['id'], 'confirm');
    $bkIncomp = Database::first("SELECT package_overall_status FROM bookings WHERE id = ?", [$idIncomp]);
    assertCondition($bkIncomp['package_overall_status'] === 'partially_confirmed', "Status is partially_confirmed");
    assertCondition(in_array($date5, Booking::bookedDates($pkgId), true), "Partially confirmed booking does NOT activate Date 5");

    // Failed booking attempt (e.g. invalid pax < minPax) does not modify date status
    $failedCaught = false;
    try {
        PackageBookingService::createPackageBooking($pkgId, (int) $touristB['id'], [
            'guestsCount' => 0, // Invalid pax
            'travelDate' => $date5,
            'contactName' => 'Failing Traveler',
            'contactPhone' => '09123456789',
        ]);
    } catch (\Throwable $e) {
        $failedCaught = true;
    }
    assertCondition($failedCaught === true, "Failed booking attempt threw exception as expected");
    assertCondition(in_array($date5, Booking::bookedDates($pkgId), true), "Failed booking attempt does not activate or alter Date 5");

    // -------------------------------------------------------------------------
    // TEST 4: Repeated Booking Actions Do NOT Create Duplicate Schedules
    // -------------------------------------------------------------------------
    echo "\n--- Test 4: Repeated Booking Actions Do NOT Create Duplicate Schedules ---\n";
    $repeatCaught = false;
    try {
        // Tourist A already has an active incomplete booking ($refIncomp)
        PackageBookingService::createPackageBooking($pkgId, (int) $touristA['id'], [
            'guestsCount' => 2,
            'travelDate' => date('Y-m-d', strtotime('+40 days')),
            'contactName' => 'Tourist A Repeated Attempt',
            'contactPhone' => '09123456789',
        ]);
    } catch (\Throwable $e) {
        $repeatCaught = true;
        echo "  [INFO] Duplicate booking blocked: " . $e->getMessage() . "\n";
    }
    assertCondition($repeatCaught === true, "System prevented duplicate active booking for Tourist A");

    // Verify Tourist A still has exactly 1 active booking
    $activeCount = (int) (Database::first("
        SELECT COUNT(*) AS cnt FROM bookings
        WHERE user_id = ? AND listing_id = ? AND status IN ('pending', 'confirmed')
    ", [(int) $touristA['id'], $pkgId])['cnt'] ?? 0);
    assertCondition($activeCount === 1, "Exactly 1 active booking exists for Tourist A (no duplicate schedules created)");

    // Safe repeated completion calls (idempotence)
    echo "  [Testing repeated completion calls idempotence]\n";
    PackageBookingService::completePackageBooking($refIncomp, $idIncomp);
    $countAfterFirst = (int) (Database::first("SELECT COUNT(*) AS cnt FROM bookings WHERE package_booking_ref = ? AND status = 'completed'", [$refIncomp])['cnt'] ?? 0);

    // Call complete again
    PackageBookingService::completePackageBooking($refIncomp, $idIncomp);
    $countAfterSecond = (int) (Database::first("SELECT COUNT(*) AS cnt FROM bookings WHERE package_booking_ref = ? AND status = 'completed'", [$refIncomp])['cnt'] ?? 0);
    assertCondition($countAfterFirst === $countAfterSecond, "Repeated completion call does not create duplicate booking records or conflicting updates");

    // Safe cancellation handling
    echo "\n--- Test 5: Safe Cancellation & Date Status Handling ---\n";
    $date6 = date('Y-m-d', strtotime('+45 days'));
    $resCancel = PackageBookingService::createPackageBooking($pkgId, (int) $touristA['id'], [
        'guestsCount' => 2,
        'travelDate' => $date6,
        'contactName' => 'Tourist A',
        'contactPhone' => '09123456789',
    ]);
    assertCondition(in_array($date6, Booking::bookedDates($pkgId), true), "Date 6 is booked before cancellation");

    // Cancel booking safely
    PackageBookingService::cancelEntirePackage($resCancel['package_booking_ref'], (int) $touristA['id'], "Testing safe cancellation");
    assertCondition(!in_array($date6, Booking::bookedDates($pkgId), true), "Date 6 safely freed on cancellation when no other schedule exists");

    echo "\n====================================================================\n";
    echo " All package calendar availability & completion tests PASSED (100%)!\n";
    echo "====================================================================\n";

} catch (\Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
} finally {
    // Clean up test data
    if (isset($pkgId)) {
        Database::run("DELETE FROM bookings WHERE package_booking_ref LIKE 'GM-PKG-CAL%'");
        Database::run("DELETE FROM package_booking_items WHERE package_booking_ref LIKE 'GM-PKG-CAL%'");
        Database::run("DELETE FROM listing_schedules WHERE listing_id = ?", [$pkgId]);
        Database::run("DELETE FROM guide_availability WHERE listing_id = ?", [$pkgId]);
    }
}
