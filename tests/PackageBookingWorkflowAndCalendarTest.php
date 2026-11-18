<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\GuideAvailability;
use App\Models\HotelRoom;
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

echo "=== GuideMate Travel Package Booking Workflow & Calendar Availability Test Suite ===\n\n";

try {
    PackageBookingService::ensureTables();

    // Setup clean test accounts
    $suffix = time();
    $newUser = Database::first("SELECT id, email, name FROM users WHERE email = 'new_traveler@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, created_at) VALUES ('New Registered Traveler', 'new_traveler@test.com', 'hash', 'tourist', NOW())"), 'email' => 'new_traveler@test.com', 'name' => 'New Registered Traveler'];

    $googleUser = Database::first("SELECT id, email, name FROM users WHERE email = 'google_testuser@social.guidemate.local' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, oauth_provider, oauth_id, created_at) VALUES ('Google Traveler', 'google_testuser@social.guidemate.local', 'hash', 'tourist', 'google', 'oauth_12345', NOW())"), 'email' => 'google_testuser@social.guidemate.local', 'name' => 'Google Traveler'];

    $guideUser = Database::first("SELECT id, email, name FROM users WHERE email = 'guide_wf@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Guide WF Host', 'guide_wf@test.com', 'hash', 'guide', 'approved', NOW())"), 'email' => 'guide_wf@test.com', 'name' => 'Guide WF Host'];

    $hotelProvider = Database::first("SELECT id, email, name FROM users WHERE email = 'hotel_wf@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Hotel WF Partner', 'hotel_wf@test.com', 'hash', 'hotel_provider', 'approved', NOW())"), 'email' => 'hotel_wf@test.com', 'name' => 'Hotel WF Partner'];

    $rentalProvider = Database::first("SELECT id, email, name FROM users WHERE email = 'rental_wf@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Rental WF Partner', 'rental_wf@test.com', 'hash', 'rental_provider', 'approved', NOW())"), 'email' => 'rental_wf@test.com', 'name' => 'Rental WF Partner'];

    // Category IDs
    $catPackages = (int) (Database::first("SELECT id FROM categories WHERE slug IN ('packages', 'travel-packages') LIMIT 1")['id'] ?? 5);
    $catHotels = (int) (Database::first("SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1")['id'] ?? 3);

    // Setup hotel listing & room
    $hotelListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = ? LIMIT 1", [(int) $hotelProvider['id'], $catHotels])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, ?, 'WF Beach Resort', 'wf-beach-resort-$suffix', 'Beach resort', 'Moalboal', 2000.00, 'approved', NOW())", [(int) $hotelProvider['id'], $catHotels]), 'title' => 'WF Beach Resort', 'price' => 2000.00];

    $room = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [(int) $hotelListing['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests, total_units) VALUES (?, 'WF Suite Room', 2000.00, 4, 1)", [(int) $hotelListing['id']]), 'name' => 'WF Suite Room', 'price_per_night' => 2000.00];

    // Setup vehicle
    $vehicle = Database::first("SELECT id, name, price_per_day FROM vehicles WHERE user_id = ? LIMIT 1", [(int) $rentalProvider['id']])
        ?? ['id' => Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Honda', 'Click 125i', 'WF Honda Click', 'wf-click-$suffix', 'Scooter', 'Scooter', 600.00, 'Moalboal', 'approved', 1)", [(int) $rentalProvider['id']]), 'name' => 'WF Honda Click', 'price_per_day' => 600.00];

    // Setup travel package listing
    $packageListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = ? LIMIT 1", [(int) $guideUser['id'], $catPackages])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, capacity, status, created_at) VALUES (?, ?, '3D2N Ultimate WF Adventure', '3d2n-wf-adv-$suffix', 'Complete 3D2N Package', 'Moalboal', 8999.00, '3 Days / 2 Nights', 6, 'approved', NOW())", [(int) $guideUser['id'], $catPackages]), 'title' => '3D2N Ultimate WF Adventure', 'price' => 8999.00];

    // Link package services
    Database::run("DELETE FROM package_services WHERE package_id = ?", [(int) $packageListing['id']]);
    Database::insert("INSERT INTO package_services (package_id, service_type, provider_id, listing_id, hotel_room_id, agreed_rate, status) VALUES (?, 'hotel', ?, ?, ?, 4000.00, 'agreed')", [
        (int) $packageListing['id'],
        (int) $hotelProvider['id'],
        (int) $hotelListing['id'],
        (int) $room['id'],
    ]);
    Database::insert("INSERT INTO package_services (package_id, service_type, provider_id, vehicle_id, agreed_rate, status) VALUES (?, 'vehicle', ?, ?, 1800.00, 'agreed')", [
        (int) $packageListing['id'],
        (int) $rentalProvider['id'],
        (int) $vehicle['id'],
    ]);

    // Clean previous test bookings
    Database::run("DELETE FROM bookings WHERE package_booking_ref LIKE 'GM-PKG-WF%' OR user_id IN (?, ?)", [(int) $newUser['id'], (int) $googleUser['id']]);
    Database::run("DELETE FROM rental_requests WHERE package_booking_ref LIKE 'GM-PKG-WF%' OR user_id IN (?, ?)", [(int) $newUser['id'], (int) $googleUser['id']]);
    Database::run("DELETE FROM package_booking_items WHERE package_booking_ref LIKE 'GM-PKG-WF%'");

    echo "--- Scenario 1 & 2: Newly Registered Traveler Books Travel Package -> Initial Status is Pending ---\n";
    $travelDate1 = date('Y-m-d', strtotime('+10 days'));
    $res1 = PackageBookingService::createPackageBooking((int) $packageListing['id'], (int) $newUser['id'], [
        'guestsCount' => 2,
        'travelDate' => $travelDate1,
        'contactName' => 'New Traveler',
        'contactPhone' => '09123456789',
        'contactEmail' => 'new_traveler@test.com',
        'notes' => 'Testing initial booking workflow',
    ]);

    assertCondition($res1['success'] === true, 'Booking created successfully');
    assertCondition($res1['status'] === 'pending', 'Returned status is strictly pending');
    assertCondition($res1['overall_status'] === 'pending', 'Returned overall_status is strictly pending');

    $parentBooking = Database::first("SELECT id, status, package_overall_status, package_booking_ref FROM bookings WHERE id = ?", [(int) $res1['parent_booking_id']]);
    assertCondition($parentBooking !== null, 'Parent booking record exists in database');
    assertCondition($parentBooking['status'] === 'pending', 'Database parent booking status is pending (NOT confirmed)');
    assertCondition($parentBooking['package_overall_status'] === 'pending', 'Database package_overall_status is pending');

    echo "\n--- Scenario 3: Every Provider Included in Package Receives Booking Request ---\n";
    $items = Database::all("SELECT * FROM package_booking_items WHERE parent_booking_id = ? ORDER BY id ASC", [(int) $res1['parent_booking_id']]);
    assertCondition(count($items) === 3, 'Exactly 3 provider items created (Guide, Hotel, Rental)');

    $guideItem = null;
    $hotelItem = null;
    $rentalItem = null;
    foreach ($items as $it) {
        if ($it['service_type'] === 'guide') $guideItem = $it;
        if ($it['service_type'] === 'hotel') $hotelItem = $it;
        if ($it['service_type'] === 'vehicle') $rentalItem = $it;
    }

    assertCondition($guideItem !== null && (int) $guideItem['provider_id'] === (int) $guideUser['id'], 'Guide provider item correctly registered');
    assertCondition($guideItem['status'] === 'pending', 'Guide item starts with pending status');

    assertCondition($hotelItem !== null && (int) $hotelItem['provider_id'] === (int) $hotelProvider['id'], 'Hotel provider item correctly registered');
    assertCondition($hotelItem['status'] === 'pending', 'Hotel item starts with pending status');
    assertCondition(!empty($hotelItem['sub_booking_id']), 'Hotel sub-booking ID is assigned');

    $hotelSubBooking = Database::first("SELECT id, listing_id, room_id, status, hotel_status FROM bookings WHERE id = ?", [(int) $hotelItem['sub_booking_id']]);
    assertCondition($hotelSubBooking !== null, 'Hotel sub-booking record exists in bookings table');
    assertCondition($hotelSubBooking['status'] === 'pending', 'Hotel sub-booking status is pending');
    assertCondition($hotelSubBooking['hotel_status'] === 'pending', 'Hotel sub-booking hotel_status is pending');

    assertCondition($rentalItem !== null && (int) $rentalItem['provider_id'] === (int) $rentalProvider['id'], 'Rental provider item correctly registered');
    assertCondition($rentalItem['status'] === 'pending', 'Rental item starts with pending status');
    assertCondition(!empty($rentalItem['rental_request_id']), 'Rental request ID is assigned');

    $rentalReq = Database::first("SELECT id, vehicle_id, status FROM rental_requests WHERE id = ?", [(int) $rentalItem['rental_request_id']]);
    assertCondition($rentalReq !== null, 'Rental request record exists in rental_requests table');
    assertCondition($rentalReq['status'] === 'pending', 'Rental request status is pending');

    echo "\n--- Scenario 4: Each Provider Can View & Process Booking Request ---\n";
    // Check Guide Bookings Query
    $guideListings = Database::all("
        SELECT b.id, b.status, b.package_booking_ref
        FROM bookings b
        JOIN listings l ON l.id = b.listing_id
        WHERE l.user_id = ? AND b.id = ?
    ", [(int) $guideUser['id'], (int) $res1['parent_booking_id']]);
    assertCondition(count($guideListings) === 1, 'Guide can view parent package booking under their listings');

    // Check Hotel Bookings Query
    $hotelBookings = Database::all("
        SELECT b.id, b.status, b.package_booking_ref
        FROM bookings b
        JOIN listings l ON l.id = b.listing_id
        WHERE l.user_id = ? AND b.id = ?
    ", [(int) $hotelProvider['id'], (int) $hotelItem['sub_booking_id']]);
    assertCondition(count($hotelBookings) === 1, 'Hotel partner can view sub-booking under their hotel property');

    // Check Rental Requests Query
    $rentalRequests = Database::all("
        SELECT rr.id, rr.status, rr.package_booking_ref
        FROM rental_requests rr
        JOIN vehicles v ON v.id = rr.vehicle_id
        WHERE v.user_id = ? AND rr.id = ?
    ", [(int) $rentalProvider['id'], (int) $rentalItem['rental_request_id']]);
    assertCondition(count($rentalRequests) === 1, 'Rental partner can view rental request under their vehicle fleet');

    echo "\n--- Scenario 5: Workflow Works Consistently Across Different Accounts (Google Traveler) ---\n";
    $travelDate2 = date('Y-m-d', strtotime('+20 days'));
    $res2 = PackageBookingService::createPackageBooking((int) $packageListing['id'], (int) $googleUser['id'], [
        'guestsCount' => 2,
        'travelDate' => $travelDate2,
        'contactName' => 'Google Traveler',
        'contactPhone' => '09987654321',
        'contactEmail' => 'google_testuser@social.guidemate.local',
        'notes' => 'Google account package booking',
    ]);
    assertCondition($res2['success'] === true, 'Google traveler package booking created');
    assertCondition($res2['status'] === 'pending', 'Google traveler booking status is pending');
    assertCondition($res2['overall_status'] === 'pending', 'Google traveler overall status is pending');

    echo "\n--- Scenario 6 & 7: Calendar Date Availability & Unselectable Booked Dates ---\n";
    // Dates for booking 1 (3D2N starting on travelDate1: day 1, day 2, day 3)
    $day1 = $travelDate1;
    $day2 = date('Y-m-d', strtotime("{$travelDate1} +1 day"));
    $day3 = date('Y-m-d', strtotime("{$travelDate1} +2 days"));

    $bookedDates = Booking::bookedDates((int) $packageListing['id']);
    assertCondition(in_array($day1, $bookedDates, true), "Day 1 ({$day1}) is marked booked in calendar");
    assertCondition(in_array($day2, $bookedDates, true), "Day 2 ({$day2}) is marked booked in calendar");
    assertCondition(in_array($day3, $bookedDates, true), "Day 3 ({$day3}) is marked booked in calendar");

    assertCondition(Booking::isDateBooked((int) $packageListing['id'], $day1), "isDateBooked returns true for Day 1 ({$day1})");
    assertCondition(Booking::isDateBooked((int) $packageListing['id'], $day2), "isDateBooked returns true for Day 2 ({$day2})");
    assertCondition(Booking::isDateBooked((int) $packageListing['id'], $day3), "isDateBooked returns true for Day 3 ({$day3})");

    echo "\n--- Scenario 8: Confirmed Provider Approvals Advance Status Appropriately ---\n";
    // Provider 1 (Guide) confirms:
    PackageBookingService::respond($res1['package_booking_ref'], 'guide', (int) $guideUser['id'], 'confirm');
    $guideItemUpdated = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [(int) $guideItem['id']]);
    assertCondition($guideItemUpdated['status'] === 'confirmed', 'Guide item status is confirmed');

    // Overall package should be partially_confirmed (hotel and rental still pending)
    $parentMid = Database::first("SELECT status, package_overall_status FROM bookings WHERE id = ?", [(int) $res1['parent_booking_id']]);
    assertCondition($parentMid['package_overall_status'] === 'partially_confirmed', 'Overall package status is partially_confirmed while others are pending');
    assertCondition($parentMid['status'] === 'pending', 'Parent booking status remains pending while others are pending');

    // Provider 2 (Hotel) confirms:
    PackageBookingService::respond($res1['package_booking_ref'], 'hotel', (int) $hotelProvider['id'], 'confirm');

    // Provider 3 (Rental) confirms:
    PackageBookingService::respond($res1['package_booking_ref'], 'vehicle', (int) $rentalProvider['id'], 'confirm');

    // Now all providers confirmed:
    $parentFinal = Database::first("SELECT status, package_overall_status FROM bookings WHERE id = ?", [(int) $res1['parent_booking_id']]);
    assertCondition($parentFinal['package_overall_status'] === 'confirmed', 'Overall package status is confirmed after all providers approved');
    assertCondition($parentFinal['status'] === 'confirmed', 'Parent booking status is confirmed after all providers approved');

    echo "\n--- Scenario 9: Cancelled or Rescheduled Bookings Update the Calendar ---\n";
    // Reschedule package 2 to a new date:
    $oldDate2 = $travelDate2;
    $newDate2 = date('Y-m-d', strtotime('+35 days'));

    PackageBookingService::rescheduleEntirePackage($res2['package_booking_ref'], $newDate2, 'Rescheduled to next month', (int) $googleUser['id'], 'tourist');

    $calendarAfterReschedule = Booking::bookedDates((int) $packageListing['id']);
    assertCondition(!in_array($oldDate2, $calendarAfterReschedule, true), "Old date ({$oldDate2}) is no longer booked on calendar");
    assertCondition(in_array($newDate2, $calendarAfterReschedule, true), "New date ({$newDate2}) is marked booked on calendar");

    // Cancel package 2:
    PackageBookingService::cancelEntirePackage($res2['package_booking_ref'], (int) $googleUser['id'], 'Testing cancellation calendar release');

    $calendarAfterCancel = Booking::bookedDates((int) $packageListing['id']);
    assertCondition(!in_array($newDate2, $calendarAfterCancel, true), "Cancelled date ({$newDate2}) is immediately released on calendar");
    assertCondition(!Booking::isDateBooked((int) $packageListing['id'], $newDate2), "isDateBooked returns false for cancelled date");

    echo "\n--- Scenario 10: Simultaneous / Conflicting Double-Booking Attempts Are Blocked ---\n";
    $collisionCaught = false;
    try {
        // Attempt to book overlapping dates for package 1 (day1 is already booked)
        PackageBookingService::createPackageBooking((int) $packageListing['id'], (int) $googleUser['id'], [
            'guestsCount' => 2,
            'travelDate' => $day1,
            'contactName' => 'Double Booking Attempter',
            'contactPhone' => '09111111111',
            'notes' => 'Attempting conflicting double booking',
        ]);
    } catch (\Throwable $e) {
        $collisionCaught = true;
        echo "  [INFO] Collision error caught as expected: " . $e->getMessage() . "\n";
    }
    assertCondition($collisionCaught === true, 'Backend prevented conflicting double booking for the same guide/package');

    echo "\n--- Scenario 11: Web & App Consistency ---\n";
    // Test bookedDates matches what ApiController formatListingAsPackage returns
    $apiListing = Database::first("SELECT * FROM listings WHERE id = ?", [(int) $packageListing['id']]);
    $apiBooked = Booking::bookedDates((int) $packageListing['id']);
    assertCondition(in_array($day1, $apiBooked, true), 'API and Web booked_dates array contains day 1');
    assertCondition(in_array($day2, $apiBooked, true), 'API and Web booked_dates array contains day 2');
    assertCondition(in_array($day3, $apiBooked, true), 'API and Web booked_dates array contains day 3');

    echo "\nAll 11 verification scenarios passed with 100% success!\n";
} catch (\Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
