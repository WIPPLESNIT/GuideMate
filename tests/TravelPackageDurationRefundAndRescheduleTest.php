<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PackageBookingService;
use App\Controllers\ApiController;

function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
        echo "  [FAIL] $message\n";
        throw new \RuntimeException("Test failed: $message");
    }
    echo "  [PASS] $message\n";
}

echo "=== GuideMate Travel Package Duration, Refund Status & Rescheduling Test Suite ===\n\n";

$passed = 0;
$failed = 0;

try {
    PackageBookingService::ensureTables();

    // 1. Setup base accounts
    $slugSuffix = time();
    $tourist = Database::first("SELECT id, email, name FROM users WHERE email = 'tourist_drr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, created_at) VALUES ('Test Tourist DRR', 'tourist_drr@test.com', 'hash', 'user', NOW())"), 'email' => 'tourist_drr@test.com', 'name' => 'Test Tourist DRR'];

    $guide = Database::first("SELECT id, email, name FROM users WHERE email = 'guide_drr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Test Guide DRR', 'guide_drr@test.com', 'hash', 'guide', 'approved', NOW())"), 'email' => 'guide_drr@test.com', 'name' => 'Test Guide DRR'];

    $hotelHost = Database::first("SELECT id, email, name FROM users WHERE email = 'hotel_drr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Hotel Host DRR', 'hotel_drr@test.com', 'hash', 'hotel_provider', 'approved', NOW())"), 'email' => 'hotel_drr@test.com', 'name' => 'Hotel Host DRR'];

    $rentalHost = Database::first("SELECT id, email, name FROM users WHERE email = 'rental_drr@test.com' LIMIT 1")
        ?? ['id' => Database::insert("INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Rental Host DRR', 'rental_drr@test.com', 'hash', 'rental_provider', 'approved', NOW())"), 'email' => 'rental_drr@test.com', 'name' => 'Rental Host DRR'];

    Database::run("DELETE FROM bookings WHERE package_booking_ref LIKE 'GM-PKG-DRR%' OR user_id = ?", [(int) $tourist['id']]);
    Database::run("DELETE FROM rental_requests WHERE package_booking_ref LIKE 'GM-PKG-DRR%' OR user_id = ?", [(int) $tourist['id']]);
    Database::run("DELETE FROM package_booking_items WHERE package_booking_ref LIKE 'GM-PKG-DRR%'");

    // Listings
    $hotelListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = 3 LIMIT 1", [(int) $hotelHost['id']])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at) VALUES (?, 3, 'Moalboal Seaside Inn', 'moalboal-inn-$slugSuffix', 'Seaside Inn', 'Moalboal', 1500.00, 'approved', NOW())", [(int) $hotelHost['id']]), 'title' => 'Moalboal Seaside Inn', 'price' => 1500.00];

    $room = Database::first("SELECT id, name, price_per_night FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [(int) $hotelListing['id']])
        ?? ['id' => Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests) VALUES (?, 'Deluxe Ocean View Room', 1500.00, 2)", [(int) $hotelListing['id']]), 'name' => 'Deluxe Ocean View Room', 'price_per_night' => 1500.00];

    $vehicle = Database::first("SELECT id, name, price_per_day FROM vehicles WHERE user_id = ? LIMIT 1", [(int) $rentalHost['id']])
        ?? ['id' => Database::insert("INSERT INTO vehicles (user_id, make, model, name, slug, description, type, price_per_day, location, status, is_available) VALUES (?, 'Yamaha', 'NMAX 155', 'Yamaha NMAX 155', 'yamaha-nmax-$slugSuffix', 'Scooter', 'Scooter', 500.00, 'Moalboal', 'approved', 1)", [(int) $rentalHost['id']]), 'name' => 'Yamaha NMAX 155', 'price_per_day' => 500.00];

    $packageListing = Database::first("SELECT id, title, price FROM listings WHERE user_id = ? AND category_id = 5 LIMIT 1", [(int) $guide['id']])
        ?? ['id' => Database::insert("INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, status, created_at) VALUES (?, 5, '3D2N Southern Cebu Adventure', '3d2n-south-cebu-$slugSuffix', 'Complete 3-Day Package', 'Moalboal', 7500.00, '3 Days / 2 Nights', 'approved', NOW())", [(int) $guide['id']]), 'title' => '3D2N Southern Cebu Adventure', 'price' => 7500.00];

    // =========================================================================
    // SCENARIO 1 & 2: 3-Day Package Duration, Rental Days, Dates & Payment Match
    // =========================================================================
    echo "1. Testing Scenario 1 & 2: 3-day Travel Package Rental Duration & Dates Match...\n";
    $travelDate1 = date('Y-m-d', strtotime('+7 days'));
    $checkOut1 = date('Y-m-d', strtotime('+9 days'));
    $pkgRef1 = 'GM-PKG-DRR1-' . time();

    // Package duration derivation test
    $durations = PackageBookingService::derivePackageDurations($packageListing['id']);
    assertCondition($durations['days'] === 3, "Package duration is 3 days");
    assertCondition($durations['hotel_nights'] === 2, "Hotel covers 2 nights (D - 1)");
    assertCondition($durations['rental_days'] === 3, "Rental covers 3 days (D)");

    // Create package parent booking (3 Days / 2 Nights)
    $parentBookingId1 = Database::insert("
        INSERT INTO bookings (
            listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref, package_overall_status, created_at
        ) VALUES (?, ?, ?, ?, ?, 2, 2, 7500.00, 7500.00, 0.00, 'paid', 'pending', ?, 'pending', NOW())
    ", [(int) $packageListing['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef1]);

    Payment::create($parentBookingId1, 7500.00, 'card', 'paid');

    // Guide item
    $guideItemId1 = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, sub_booking_id, allocated_amount, original_allocated_amount, status, payment_status, created_at
        ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide Service', ?, 3000.00, 3000.00, 'confirmed', 'paid', NOW())
    ", [$pkgRef1, $parentBookingId1, (int) $packageListing['id'], (int) $guide['id'], $parentBookingId1]);

    // Hotel sub-booking & item
    $hotelSubId1 = Database::insert("
        INSERT INTO bookings (
            listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
            total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status, package_booking_ref, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 2, 2, 3000.00, 3000.00, 0.00, 'paid', 'confirmed', 'confirmed', ?, NOW())
    ", [(int) $hotelListing['id'], (int) $room['id'], (int) $tourist['id'], $travelDate1, $travelDate1, $checkOut1, $pkgRef1]);

    $hotelItemId1 = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, sub_booking_id, allocated_amount, original_allocated_amount, status, payment_status, created_at
        ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Room', ?, 3000.00, 3000.00, 'confirmed', 'paid', NOW())
    ", [$pkgRef1, $parentBookingId1, (int) $packageListing['id'], (int) $hotelHost['id'], $hotelSubId1]);

    // Rental request & item
    $rentalReqId1 = Database::insert("
        INSERT INTO rental_requests (
            user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
            total_amount, status, payment_status, package_booking_ref, package_booking_id, created_at
        ) VALUES (?, ?, 'Yamaha NMAX 155', 'Scooter', 'Moalboal', ?, 3, 1500.00, 'approved', 'paid', ?, ?, NOW())
    ", [(int) $tourist['id'], (int) $vehicle['id'], $travelDate1, $pkgRef1, $parentBookingId1]);

    $rentalItemId1 = Database::insert("
        INSERT INTO package_booking_items (
            package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
            service_type, service_name, rental_request_id, allocated_amount, original_allocated_amount, status, payment_status, created_at
        ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Vehicle Rental', ?, 1500.00, 1500.00, 'confirmed', 'paid', NOW())
    ", [$pkgRef1, $parentBookingId1, (int) $packageListing['id'], (int) $rentalHost['id'], $rentalReqId1]);

    $rentalRequest1 = Database::first("SELECT * FROM rental_requests WHERE id = ?", [$rentalReqId1]);
    assertCondition((int) $rentalRequest1['rental_days'] === 3, "Rental request rental_days matches 3-day package duration (3 days)");
    assertCondition($rentalRequest1['pickup_date'] === $travelDate1, "Rental pickup date matches package travel date ($travelDate1)");

    $expectedRentalAmount = round(3 * 500.00, 2);
    assertCondition((float) $rentalRequest1['total_amount'] === $expectedRentalAmount, "Rental payment calculation matches: 3 days x ₱500.00 = ₱1,500.00");

    // Check ApiController::bookingPayload output for mobile app
    $parentBooking1 = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentBookingId1]);
    $apiCtrl = new ApiController();
    $method = new ReflectionMethod($apiCtrl, 'bookingPayload');
    $method->setAccessible(true);
    $formattedBooking1 = $method->invoke($apiCtrl, $parentBooking1, (int) $tourist['id']);

    assertCondition(isset($formattedBooking1['package_details']), "Formatted booking contains package_details");
    $pkgDetailsApi1 = $formattedBooking1['package_details'];
    assertCondition((int) ($pkgDetailsApi1['rental']['rental_days'] ?? 0) === 3, "ApiController formats rental_days as 3 full days (not 1 day)");
    assertCondition((int) ($pkgDetailsApi1['accommodation']['nights'] ?? 0) === 2, "ApiController formats accommodation nights as 2 nights");
    echo "\n";

    // =========================================================================
    // SCENARIO 4 & 5: Independent Provider Cancellation & Statuses (Paid / Refunded)
    // =========================================================================
    echo "2. Testing Scenarios 4 & 5: Independent Provider Cancellation & Statuses...\n";

    // Check parent payment status via Booking::forCustomer initially
    $custBookingsInitial = Booking::forCustomer((int) $tourist['id']);
    $myBookingInitial = array_values(array_filter($custBookingsInitial, fn($b) => (int) $b['id'] === $parentBookingId1))[0] ?? null;
    assertCondition($myBookingInitial !== null && $myBookingInitial['payment_status'] === 'paid', "Initial package payment status is 'paid'");

    // Hotel cancels confirmed reservation
    $respCancel = PackageBookingService::respond($pkgRef1, 'hotel', (int) $hotelHost['id'], 'cancel', 'Emergency facility maintenance');
    assertCondition($respCancel['success'] === true, "Hotel provider cancellation handled successfully");

    // Fetch updated statuses of each provider
    $hotelSubAfterCancel = Database::first("SELECT * FROM bookings WHERE id = ?", [$hotelSubId1]);
    $rentalAfterCancel = Database::first("SELECT * FROM rental_requests WHERE id = ?", [$rentalReqId1]);
    $hotelItemAfterCancel = Database::first("SELECT * FROM package_booking_items WHERE id = ?", [$hotelItemId1]);
    $rentalItemAfterCancel = Database::first("SELECT * FROM package_booking_items WHERE id = ?", [$rentalItemId1]);
    $guideItemAfterCancel = Database::first("SELECT * FROM package_booking_items WHERE id = ?", [$guideItemId1]);

    // Check affected hotel is refunded
    assertCondition($hotelSubAfterCancel['status'] === 'cancelled', "Hotel sub-booking status is 'cancelled'");
    assertCondition($hotelSubAfterCancel['refund_status'] === 'refunded', "Hotel sub-booking refund_status is 'refunded'");
    assertCondition((float) $hotelSubAfterCancel['refund_amount'] === 3000.00, "Hotel sub-booking refunded exact paid amount (₱3,000.00)");
    assertCondition($hotelItemAfterCancel['refund_status'] === 'refunded', "Hotel item refund_status is 'refunded'");

    // Check unaffected rental and guide are STILL PAID and NOT refunded
    assertCondition($rentalAfterCancel['status'] === 'approved', "Rental request is NOT cancelled (status: approved)");
    assertCondition(($rentalAfterCancel['refund_status'] ?? null) !== 'refunded', "Rental request refund_status is NOT refunded");
    assertCondition($rentalAfterCancel['payment_status'] === 'paid', "Vehicle rental request payment_status is 'paid'");
    assertCondition($rentalItemAfterCancel['payment_status'] === 'paid', "Vehicle rental item payment_status remains 'paid'");
    assertCondition($guideItemAfterCancel['payment_status'] === 'paid', "Tour Guide item payment_status remains 'paid'");

    // Check parent package booking payment status remains paid
    $custBookingsAfterCancel = Booking::forCustomer((int) $tourist['id']);
    $myBookingAfterCancel = array_values(array_filter($custBookingsAfterCancel, fn($b) => (int) $b['id'] === $parentBookingId1))[0] ?? null;
    assertCondition($myBookingAfterCancel !== null && $myBookingAfterCancel['payment_status'] === 'paid', "Overall package payment status remains 'paid' (not marked refunded overall)");

    // Verify summary returns independent statuses
    $summaryAfterCancel = PackageBookingService::getPackageBookingSummary($pkgRef1);
    assertCondition($summaryAfterCancel['has_cancellation'] === true, "Summary indicates active cancellation for disrupted service");
    echo "\n";

    // =========================================================================
    // SCENARIO 6 & 7: Reschedule Paid Travel Package & Verify Payment Remains Paid
    // =========================================================================
    echo "3. Testing Scenarios 6 & 7: Reschedule Paid Package & Verify Payment Remains Paid...\n";
    $newTravelDate = date('Y-m-d', strtotime('+14 days'));
    $rescheduleRes = PackageBookingService::rescheduleEntirePackage(
        $pkgRef1,
        $newTravelDate,
        '09:00 AM',
        (int) $guide['id'],
        'Rescheduled to next week'
    );
    assertCondition($rescheduleRes['success'] === true, "rescheduleEntirePackage succeeded");

    // Fetch refreshed records
    $parentRescheduled = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentBookingId1]);
    $hotelRescheduled = Database::first("SELECT * FROM bookings WHERE id = ?", [$hotelSubId1]);
    $rentalRescheduled = Database::first("SELECT * FROM rental_requests WHERE id = ?", [$rentalReqId1]);

    // Check parent payment status remains 'paid'
    assertCondition($parentRescheduled['payment_stage'] === 'paid', "Parent booking payment_stage remains 'paid' after reschedule");
    $custBookingsRescheduled = Booking::forCustomer((int) $tourist['id']);
    $myBookingRescheduled = array_values(array_filter($custBookingsRescheduled, fn($b) => (int) $b['id'] === $parentBookingId1))[0] ?? null;
    assertCondition($myBookingRescheduled !== null && $myBookingRescheduled['payment_status'] === 'paid', "Parent package payment_status in Booking::forCustomer remains 'paid' (not marked refunded)");

    // Check dates updated on all services
    assertCondition($parentRescheduled['booking_date'] === $newTravelDate, "Parent package travel date updated to $newTravelDate");
    assertCondition($hotelRescheduled['check_in_date'] === $newTravelDate, "Hotel check-in date updated to $newTravelDate");
    $expectedHotelCheckOut = date('Y-m-d', strtotime("{$newTravelDate} +2 days"));
    assertCondition($hotelRescheduled['check_out_date'] === $expectedHotelCheckOut, "Hotel check-out date updated to $expectedHotelCheckOut");
    assertCondition($rentalRescheduled['pickup_date'] === $newTravelDate, "Vehicle rental pickup date updated to $newTravelDate");
    assertCondition((int) $rentalRescheduled['rental_days'] === 3, "Vehicle rental duration remains 3 full days");
    $summaryRescheduled = PackageBookingService::getPackageBookingSummary($pkgRef1);
    $vehicleItemRescheduled = array_values(array_filter($summaryRescheduled['items'] ?? [], fn($i) => ($i['service_type'] ?? '') === 'vehicle'))[0] ?? null;
    $expectedRentalReturn = date('Y-m-d', strtotime("{$newTravelDate} +3 days"));
    assertCondition($vehicleItemRescheduled !== null && $vehicleItemRescheduled['rental_return_date'] === $expectedRentalReturn, "Vehicle rental return date in summary correctly covers full package duration ($expectedRentalReturn)");
    echo "\n";

    // =========================================================================
    // SCENARIO 8: Provider Reconfirmation Does Not Trigger Unnecessary Refund
    // =========================================================================
    echo "4. Testing Scenario 8: Provider Reconfirmation Does Not Trigger Unnecessary Refund...\n";
    // Check payment records count before reconfirmation
    $refundPaymentsBefore = Database::all("SELECT * FROM payments WHERE booking_id = ? AND method = 'refund'", [$parentBookingId1]);
    $refundCountBefore = count($refundPaymentsBefore);

    // Hotel reconfirms rescheduled booking
    $reconfirmHotelRes = PackageBookingService::respond($pkgRef1, 'hotel', (int) $hotelHost['id'], 'confirm');
    assertCondition($reconfirmHotelRes['success'] === true, "Hotel confirmed rescheduled booking");

    // Rental reconfirms rescheduled booking
    $reconfirmRentalRes = PackageBookingService::respond($pkgRef1, 'vehicle', (int) $rentalHost['id'], 'confirm');
    assertCondition($reconfirmRentalRes['success'] === true, "Vehicle provider confirmed rescheduled booking");

    // Check payment records after reconfirmation
    $refundPaymentsAfter = Database::all("SELECT * FROM payments WHERE booking_id = ? AND method = 'refund'", [$parentBookingId1]);
    $refundCountAfter = count($refundPaymentsAfter);
    assertCondition($refundCountAfter === $refundCountBefore, "Provider reconfirmation did NOT insert any unnecessary refunds (count before: $refundCountBefore, after: $refundCountAfter)");

    // Check payment status remains paid
    $parentFinal = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentBookingId1]);
    assertCondition($parentFinal['payment_stage'] === 'paid', "Parent booking payment_stage is still 'paid'");
    $custBookingsFinal = Booking::forCustomer((int) $tourist['id']);
    $myBookingFinal = array_values(array_filter($custBookingsFinal, fn($b) => (int) $b['id'] === $parentBookingId1))[0] ?? null;
    assertCondition($myBookingFinal !== null && $myBookingFinal['payment_status'] === 'paid', "Final booking payment_status is 'paid'");

    // Verify refund amount on parent is 0
    assertCondition((float) ($parentFinal['refund_amount'] ?? 0) === 0.0, "Parent booking refund_amount remains 0.00");
    assertCondition(empty($parentFinal['refund_status']), "Parent booking refund_status remains NULL");
    echo "\n";

    // =========================================================================
    // SCENARIO 3 APP UI CHECK: Refunded Message Removed From Hotel Stay Accommodation
    // =========================================================================
    echo "5. Testing Scenario 3 App UI: Refunded Message Removed From Hotel Stay Accommodation...\n";
    $tripsTsxContent = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');

    // In hotel section, the "Cancelled by original hotel provider" notice should exist
    assertCondition(strpos($tripsTsxContent, '✕ Cancelled by original hotel provider') !== false, "Hotel section retains '✕ Cancelled by original hotel provider' notice");

    // Inside original hotel section, the "✓ Refunded:" badge must NOT exist
    $hotelSectionStart = strpos($tripsTsxContent, 'Hotel Stay Accommodation');
    $hotelSectionEnd = strpos($tripsTsxContent, 'Hotel Stay Accommodation Replacement');
    $hotelSectionText = substr($tripsTsxContent, $hotelSectionStart, $hotelSectionEnd - $hotelSectionStart);

    assertCondition(strpos($hotelSectionText, '✓ Refunded:') === false, "Refunded badge successfully removed from Hotel Stay Accommodation section in trips.tsx");
    assertCondition(strpos($hotelSectionText, 'formatDate(hotelCheckIn)') !== false, "Hotel Stay Accommodation preserves check-in date");
    assertCondition(strpos($hotelSectionText, 'formatDate(hotelCheckOut)') !== false, "Hotel Stay Accommodation preserves check-out date");
    assertCondition(strpos($hotelSectionText, 'derivedNights') !== false, "Hotel Stay Accommodation preserves number of nights");
    echo "\n";

    echo "======================================================\n";
    echo "SUCCESS: All 8 Travel Package Duration, Refund Status & Rescheduling scenarios passed!\n";
    echo "======================================================\n";

} catch (\Throwable $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
