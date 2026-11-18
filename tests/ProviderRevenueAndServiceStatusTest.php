<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Payment;
use App\Services\PackageBookingService;
use App\Services\RevenueService;
use App\Controllers\ApiController;

function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
        echo "  [FAIL] $message\n";
        throw new \RuntimeException("Test failed: $message");
    }
    echo "  [PASS] $message\n";
}

echo "=== GuideMate Provider Revenue & Service Status Test Suite ===\n\n";

$passed = 0;
$failed = 0;

try {
    PackageBookingService::ensureTables();

    // 0. Setup test providers and tourist
    $ts = time();
    Database::run("INSERT INTO users (name, email, role, password, revenue, created_at) VALUES (?, ?, 'guide', 'hash', 0, NOW())", ["Guide Test $ts", "guide_$ts@test.com"]);
    $guideId = (int) Database::pdo()->lastInsertId();

    Database::run("INSERT INTO users (name, email, role, password, revenue, created_at) VALUES (?, ?, 'hotel_provider', 'hash', 0, NOW())", ["Hotel Test $ts", "hotel_$ts@test.com"]);
    $hotelId = (int) Database::pdo()->lastInsertId();

    Database::run("INSERT INTO users (name, email, role, password, revenue, created_at) VALUES (?, ?, 'rental_provider', 'hash', 0, NOW())", ["Rental Test $ts", "rental_$ts@test.com"]);
    $rentalId = (int) Database::pdo()->lastInsertId();

    Database::run("INSERT INTO users (name, email, role, password, revenue, created_at) VALUES (?, ?, 'tourist', 'hash', 0, NOW())", ["Tourist Test $ts", "tourist_$ts@test.com"]);
    $touristId = (int) Database::pdo()->lastInsertId();

    // Listings
    Database::run("INSERT INTO listings (user_id, category_id, title, slug, price, status, created_at) VALUES (?, 1, ?, ?, 2500.00, 'approved', NOW())", [$guideId, "Guide Tour $ts", "guide-tour-$ts"]);
    $guideListingId = (int) Database::pdo()->lastInsertId();

    Database::run("INSERT INTO listings (user_id, category_id, title, slug, price, status, created_at) VALUES (?, 3, ?, ?, 3000.00, 'approved', NOW())", [$hotelId, "Hotel Room $ts", "hotel-room-$ts"]);
    $hotelListingId = (int) Database::pdo()->lastInsertId();

    Database::run("INSERT INTO vehicles (user_id, name, slug, type, price_per_day, status, is_available, created_at) VALUES (?, ?, ?, 'SUV', 1500.00, 'approved', 1, NOW())", [$rentalId, "Rental Car $ts", "rental-car-$ts"]);
    $vehicleId = (int) Database::pdo()->lastInsertId();

    echo "--- Scenario 1: Provider booking with Pending status does not generate revenue ---\n";
    // Create pending standalone tour booking
    $pendingTourBookingId = Booking::create(
        $guideListingId,
        $touristId,
        date('Y-m-d', strtotime('+3 days')),
        2,
        5000.00,
        'Test tour note',
        '09:00:00'
    );
    
    // Create pending standalone rental request
    $pendingRentalId = RentalRequest::create($touristId, [
        'vehicle_id' => $vehicleId,
        'vehicle_name' => "Rental Car $ts",
        'vehicle_type' => 'SUV',
        'pickup_date' => date('Y-m-d', strtotime('+3 days')),
        'rental_days' => 2,
        'price_per_day' => 1500.00,
        'payment_status' => 'unpaid',
    ]);

    $guideRev = RevenueService::getProviderRevenue($guideId);
    $rentalRev = RevenueService::getProviderRevenue($rentalId);
    assertCondition($guideRev === 0.0, "Guide revenue is 0.00 for pending booking (got $guideRev)");
    assertCondition($rentalRev === 0.0, "Rental provider revenue is 0.00 for pending rental request (got $rentalRev)");
    $passed += 2;

    echo "\n--- Scenario 2: Cancelled or refunded booking does not generate provider revenue ---\n";
    // Update tour booking to confirmed, then cancelled
    Booking::updateStatus($pendingTourBookingId, 'cancelled');
    $guideRevAfterCancel = RevenueService::getProviderRevenue($guideId);
    assertCondition($guideRevAfterCancel === 0.0, "Guide revenue is 0.00 after booking is cancelled (got $guideRevAfterCancel)");

    // Update rental request to cancelled
    RentalRequest::updateStatus($pendingRentalId, 'cancelled');
    $rentalRevAfterCancel = RevenueService::getProviderRevenue($rentalId);
    assertCondition($rentalRevAfterCancel === 0.0, "Rental revenue is 0.00 after rental request is cancelled (got $rentalRevAfterCancel)");
    $passed += 2;

    echo "\n--- Scenario 3: Successfully completed booking generates revenue exactly once ---\n";
    // Create completed booking for guide
    $completedTourBookingId = Booking::create(
        $guideListingId,
        $touristId,
        date('Y-m-d', strtotime('-2 days')),
        2,
        5000.00,
        'Test tour note',
        '09:00:00'
    );
    Payment::create($completedTourBookingId, 5000.00, 'card', 'paid');
    Booking::updateStatus($completedTourBookingId, 'completed');
    
    $guideRevCompleted = RevenueService::syncUserRevenue($guideId);
    assertCondition($guideRevCompleted === 5000.00, "Guide completed booking recorded revenue of 5000.00 (got $guideRevCompleted)");

    // Process completion again to test idempotency / duplicate prevention
    RevenueService::recordBookingCompletion($completedTourBookingId);
    $guideRevDupCheck = RevenueService::syncUserRevenue($guideId);
    assertCondition($guideRevDupCheck === 5000.00, "Duplicate completion check: Guide revenue remains exactly 5000.00 without double counting (got $guideRevDupCheck)");

    // Complete rental request
    $completedRentalId = RentalRequest::create($touristId, [
        'vehicle_id' => $vehicleId,
        'vehicle_name' => "Rental Car $ts",
        'vehicle_type' => 'SUV',
        'pickup_date' => date('Y-m-d', strtotime('-3 days')),
        'rental_days' => 2,
        'price_per_day' => 1500.00,
        'payment_status' => 'paid',
        'payment_reference' => "PAY-RENT-$ts",
    ]);
    RentalRequest::updateStatus($completedRentalId, 'completed');
    $rentalRevCompleted = RevenueService::syncUserRevenue($rentalId);
    assertCondition($rentalRevCompleted === 3000.00, "Rental provider completed rental recorded revenue of 3000.00 (got $rentalRevCompleted)");
    
    // Process rental completion again to test idempotency
    RentalRequest::updateStatus($completedRentalId, 'completed');
    $rentalRevDupCheck = RevenueService::syncUserRevenue($rentalId);
    assertCondition($rentalRevDupCheck === 3000.00, "Duplicate completion check: Rental revenue remains exactly 3000.00 (got $rentalRevDupCheck)");
    $passed += 4;

    echo "\n--- Scenario 4: Each provider account displays only its own eligible revenue ---\n";
    // Check that Guide has only 5000.00, Rental has only 3000.00, and Hotel has 0.00
    $guideFinalRev = RevenueService::getProviderRevenue($guideId);
    $rentalFinalRev = RevenueService::getProviderRevenue($rentalId);
    $hotelFinalRev = RevenueService::getProviderRevenue($hotelId);
    assertCondition($guideFinalRev === 5000.00, "Guide account sees only its own revenue of 5000.00 (got $guideFinalRev)");
    assertCondition($rentalFinalRev === 3000.00, "Rental account sees only its own revenue of 3000.00 (got $rentalFinalRev)");
    assertCondition($hotelFinalRev === 0.0, "Hotel account has 0.00 completed revenue (got $hotelFinalRev)");
    $passed += 3;

    echo "\n--- Scenario 5: Admin Account displays the combined eligible revenue across all providers ---\n";
    RevenueService::syncAllProvidersRevenue();
    $adminTotal = RevenueService::getAdminTotalRevenue();
    $allProvidersSum = (float) (Database::first("SELECT COALESCE(SUM(revenue), 0) AS total FROM users WHERE role IN ('guide', 'hotel_provider', 'rental_provider', 'hotel_admin')")['total'] ?? 0);
    assertCondition($adminTotal >= 8000.00, "Admin total revenue includes test completed bookings (got $adminTotal)");
    assertCondition(abs($adminTotal - $allProvidersSum) < 0.01, "Admin total revenue equals sum of individual provider revenues ($adminTotal == $allProvidersSum)");
    $passed += 2;

    echo "\n--- Scenario 6: Admin can delete multiple packages consecutively without delete modal getting stuck ---\n";
    // Verify guide-listings view template has proper reset, re-enable button, and showAdminAlert
    $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/guide-listings.php');
    assertCondition(str_contains($viewContent, 'btn.disabled = false;'), "Delete button disabled state is cleared in open/close modal");
    assertCondition(str_contains($viewContent, 'btn.textContent = \'Permanently Delete\';'), "Delete button text is reset");
    assertCondition(str_contains($viewContent, 'pendingDeleteId = null;'), "Pending delete ID is reset in close modal");
    assertCondition(str_contains($viewContent, 'function showAdminAlert('), "showAdminAlert function exists for dynamic notification");
    assertCondition(str_contains($viewContent, 'isDeleting = false;'), "isDeleting flag properly guards and resets delete operations");
    $passed += 5;

    echo "\n--- Scenario 7 & 8: Rental Provider Contacted & Updated Status Synchronization ---\n";
    // Create package with guide, hotel, and vehicle
    $pkgListingId = Booking::create(
        $guideListingId,
        $touristId,
        date('Y-m-d', strtotime('+7 days')),
        2,
        12000.00,
        'Package note',
        '08:00:00'
    );
    $pkgRef = "GM-PKG-$ts";
    Database::run("UPDATE bookings SET package_booking_ref = ?, package_overall_status = 'confirmed' WHERE id = ?", [$pkgRef, $pkgListingId]);

    // Sub-booking for hotel
    $hotelSubBookingId = Booking::create(
        $hotelListingId,
        $touristId,
        date('Y-m-d', strtotime('+7 days')),
        2,
        4000.00,
        'Hotel note',
        '14:00:00'
    );
    Database::run("UPDATE bookings SET package_booking_ref = ?, hotel_status = 'confirmed' WHERE id = ?", [$pkgRef, $hotelSubBookingId]);

    // Rental request for package
    $rentalPkgReqId = RentalRequest::create($touristId, [
        'vehicle_id' => $vehicleId,
        'vehicle_name' => "Rental Car $ts",
        'vehicle_type' => 'SUV',
        'pickup_date' => date('Y-m-d', strtotime('+7 days')),
        'rental_days' => 2,
        'price_per_day' => 1500.00,
        'payment_status' => 'paid',
        'payment_reference' => "PAY-PKG-RENT-$ts",
    ]);
    Database::run("UPDATE rental_requests SET package_booking_ref = ? WHERE id = ?", [$pkgRef, $rentalPkgReqId]);

    // Package items
    Database::run("INSERT INTO package_booking_items (package_booking_ref, parent_booking_id, service_type, provider_id, provider_role, service_name, sub_booking_id, allocated_amount, status, payment_status, created_at) VALUES (?, ?, 'guide', ?, 'guide', 'Guided Tour', ?, 5000.00, 'confirmed', 'paid', NOW())", [$pkgRef, $pkgListingId, $guideId, $pkgListingId]);
    Database::run("INSERT INTO package_booking_items (package_booking_ref, parent_booking_id, service_type, provider_id, provider_role, service_name, sub_booking_id, allocated_amount, status, payment_status, created_at) VALUES (?, ?, 'hotel', ?, 'hotel_provider', 'Hotel Stay', ?, 4000.00, 'confirmed', 'paid', NOW())", [$pkgRef, $pkgListingId, $hotelId, $hotelSubBookingId]);
    Database::run("INSERT INTO package_booking_items (package_booking_ref, parent_booking_id, service_type, provider_id, provider_role, service_name, rental_request_id, allocated_amount, status, payment_status, created_at) VALUES (?, ?, 'vehicle', ?, 'rental_provider', 'Car Rental', ?, 3000.00, 'confirmed', 'paid', NOW())", [$pkgRef, $pkgListingId, $rentalId, $rentalPkgReqId]);

    // 7. Update rental status to 'contacted'
    RentalRequest::updateStatus($rentalPkgReqId, 'contacted');
    $summaryAfterContact = PackageBookingService::getPackageBookingSummary($pkgRef);
    $vehicleItem = null;
    foreach ($summaryAfterContact['active_items'] as $it) {
        if ($it['service_type'] === 'vehicle') {
            $vehicleItem = $it;
        }
    }
    assertCondition($vehicleItem !== null, "Vehicle package item found");
    assertCondition(($vehicleItem['rental_request_status'] ?? '') === 'contacted', "Vehicle rental_request_status is 'contacted' (got " . ($vehicleItem['rental_request_status'] ?? '') . ")");

    // Test API response
    $apiCtrl = new ApiController();
    $refMethod = new \ReflectionMethod(ApiController::class, 'bookingPayload');
    $refMethod->setAccessible(true);
    $bookingData = Database::first("SELECT * FROM bookings WHERE id = ?", [$pkgListingId]);
    $formatted = $refMethod->invoke($apiCtrl, $bookingData, $touristId);
    $apiVehicle = null;
    foreach ($formatted['provider_statuses'] as $p) {
        if ($p['service_type'] === 'vehicle') {
            $apiVehicle = $p;
        }
    }
    assertCondition(($apiVehicle['rental_request_status'] ?? '') === 'contacted', "API provider_statuses vehicle rental_request_status is 'contacted'");

    // 8. When rental provider updates status again (e.g. to completed)
    RentalRequest::updateStatus($rentalPkgReqId, 'completed');
    $summaryAfterComplete = PackageBookingService::getPackageBookingSummary($pkgRef);
    $vehicleItemCompleted = null;
    foreach ($summaryAfterComplete['active_items'] as $it) {
        if ($it['service_type'] === 'vehicle') {
            $vehicleItemCompleted = $it;
        }
    }
    assertCondition(($vehicleItemCompleted['rental_request_status'] ?? '') === 'completed', "Vehicle rental_request_status updated to 'completed'");
    $formattedAfterComplete = $refMethod->invoke($apiCtrl, $bookingData, $touristId);
    $apiVehicleCompleted = null;
    foreach ($formattedAfterComplete['provider_statuses'] as $p) {
        if ($p['service_type'] === 'vehicle') {
            $apiVehicleCompleted = $p;
        }
    }
    assertCondition(($apiVehicleCompleted['rental_request_status'] ?? '') === 'completed', "API provider_statuses vehicle status does not stay contacted once completed");
    $passed += 5;

    echo "\n--- Scenario 9: When hotel provider changes reservation status to Check in, App displays Check in until Completed ---\n";
    // Change hotel status to 'checked_in'
    Database::run("UPDATE bookings SET hotel_status = 'checked_in' WHERE id = ?", [$hotelSubBookingId]);
    $summaryAfterCheckIn = PackageBookingService::getPackageBookingSummary($pkgRef);
    $hotelItem = null;
    foreach ($summaryAfterCheckIn['active_items'] as $it) {
        if ($it['service_type'] === 'hotel') {
            $hotelItem = $it;
        }
    }
    assertCondition($hotelItem !== null, "Hotel package item found");
    assertCondition(($hotelItem['hotel_reservation_status'] ?? '') === 'checked_in', "Hotel reservation status is 'checked_in'");

    $formattedHotel = $refMethod->invoke($apiCtrl, $bookingData, $touristId);
    $apiHotel = null;
    foreach ($formattedHotel['provider_statuses'] as $p) {
        if ($p['service_type'] === 'hotel') {
            $apiHotel = $p;
        }
    }
    assertCondition(($apiHotel['hotel_reservation_status'] ?? '') === 'checked_in', "API provider_statuses hotel_reservation_status is 'checked_in'");

    // Now update hotel reservation status to 'completed'
    Database::run("UPDATE bookings SET hotel_status = 'completed', status = 'completed' WHERE id = ?", [$hotelSubBookingId]);
    $summaryHotelComplete = PackageBookingService::getPackageBookingSummary($pkgRef);
    $hotelItemCompleted = null;
    foreach ($summaryHotelComplete['active_items'] as $it) {
        if ($it['service_type'] === 'hotel') {
            $hotelItemCompleted = $it;
        }
    }
    assertCondition(($hotelItemCompleted['hotel_reservation_status'] ?? '') === 'completed', "Hotel reservation status is updated to 'completed'");
    $formattedHotelComplete = $refMethod->invoke($apiCtrl, $bookingData, $touristId);
    $apiHotelComplete = null;
    foreach ($formattedHotelComplete['provider_statuses'] as $p) {
        if ($p['service_type'] === 'hotel') {
            $apiHotelComplete = $p;
        }
    }
    assertCondition(($apiHotelComplete['hotel_reservation_status'] ?? '') === 'completed', "API provider_statuses hotel status does not stay 'checked_in' once completed");
    $passed += 5;

    echo "\n--- Scenario 10: Deletion of Completed & Incomplete Bookings & Revenue Adjustments ---\n";
    // Verify provider with completed booking has revenue
    $guideRevBeforeDelete = RevenueService::getProviderRevenue($guideId);
    $adminRevBeforeDelete = RevenueService::getAdminTotalRevenue();
    assertCondition($guideRevBeforeDelete === 5000.00, "Guide has 5000.00 revenue before deletion");

    // 10a: Delete pending booking ($pendingTourBookingId was cancelled). Deleting it must NOT reduce revenue.
    $deletePendingResult = Booking::delete($pendingTourBookingId);
    assertCondition($deletePendingResult === true, "Pending/cancelled booking was deleted");
    $guideRevAfterNonRevenueDelete = RevenueService::getProviderRevenue($guideId);
    $adminRevAfterNonRevenueDelete = RevenueService::getAdminTotalRevenue();
    assertCondition($guideRevAfterNonRevenueDelete === 5000.00, "Deleting booking that generated 0.00 revenue does NOT reduce provider revenue (got $guideRevAfterNonRevenueDelete)");
    assertCondition($adminRevAfterNonRevenueDelete === $adminRevBeforeDelete, "Deleting non-revenue booking does NOT reduce admin revenue");

    // 10b: Repeated deletion request for the same booking ID returns false and does not affect revenue
    $secondDeleteAttempt = Booking::delete($pendingTourBookingId);
    assertCondition($secondDeleteAttempt === false, "Repeated delete call returns false safely");
    assertCondition(RevenueService::getProviderRevenue($guideId) === 5000.00, "Repeated delete call leaves revenue untouched");

    // 10c: Delete completed booking ($completedTourBookingId which contributed 5000.00)
    $deleteCompletedResult = Booking::delete($completedTourBookingId);
    assertCondition($deleteCompletedResult === true, "Completed booking was deleted");
    $guideRevAfterCompletedDelete = RevenueService::getProviderRevenue($guideId);
    assertCondition($guideRevAfterCompletedDelete === 0.0, "Provider with no completed bookings now displays 0.00 revenue (got $guideRevAfterCompletedDelete)");
    
    $adminRevAfterCompletedDelete = RevenueService::getAdminTotalRevenue();
    assertCondition($adminRevAfterCompletedDelete === ($adminRevBeforeDelete - 5000.00), "Admin combined revenue decreased by exactly the deleted 5000.00");

    // 10d: Delete completed standalone rental ($completedRentalId which contributed 3000.00)
    $rentalRevBeforeStandaloneDelete = RevenueService::getProviderRevenue($rentalId);
    assertCondition($rentalRevBeforeStandaloneDelete === 6000.00, "Rental provider has 6000.00 before deletion (3000 standalone + 3000 package, got $rentalRevBeforeStandaloneDelete)");
    $deleteRentalResult = RentalRequest::delete($completedRentalId);
    assertCondition($deleteRentalResult === true, "Completed standalone rental was deleted");
    $rentalRevAfterStandaloneDelete = RevenueService::getProviderRevenue($rentalId);
    assertCondition($rentalRevAfterStandaloneDelete === 3000.00, "Rental provider revenue decreased by exactly the deleted 3000.00 (got $rentalRevAfterStandaloneDelete)");

    // 10e: Repeated deletion on already deleted rental returns false and leaves revenue unchanged
    $secondRentalDelete = RentalRequest::delete($completedRentalId);
    assertCondition($secondRentalDelete === false, "Repeated rental delete returns false");
    assertCondition(RevenueService::getProviderRevenue($rentalId) === 3000.00, "Rental revenue remains 3000.00 without negative drift");

    // 10f: Now delete the package booking ($pkgListingId) which holds completed items for hotel (4000) and rental (3000)
    $deletePkgResult = Booking::delete($pkgListingId);
    assertCondition($deletePkgResult === true, "Completed package booking was deleted");
    $hotelRevAfterPkgDelete = RevenueService::getProviderRevenue($hotelId);
    $rentalRevAfterPkgDelete = RevenueService::getProviderRevenue($rentalId);
    $guideRevAfterPkgDelete = RevenueService::getProviderRevenue($guideId);
    assertCondition($hotelRevAfterPkgDelete === 0.0, "Hotel provider with all completed bookings deleted displays exactly 0.00 (got $hotelRevAfterPkgDelete)");
    assertCondition($rentalRevAfterPkgDelete === 0.0, "Rental provider with all completed bookings deleted displays exactly 0.00 (got $rentalRevAfterPkgDelete)");
    assertCondition($guideRevAfterPkgDelete === 0.0, "Guide provider with all completed bookings deleted displays exactly 0.00 (got $guideRevAfterPkgDelete)");

    $adminRevAfterAllDeleted = RevenueService::getAdminTotalRevenue();
    assertCondition($adminRevAfterAllDeleted === 0.0, "Admin Overview total revenue displays exactly 0.00 when all completed bookings are deleted (got $adminRevAfterAllDeleted)");
    $passed += 8;

    echo "\n--- Scenario 11: All revenue and service status information remains consistent with database records ---\n";
    RevenueService::syncAllProvidersRevenue();
    $finalAdminRev = RevenueService::getAdminTotalRevenue();
    $usersSum = (float) (Database::first("SELECT COALESCE(SUM(revenue), 0) AS total FROM users WHERE role IN ('guide', 'hotel_provider', 'rental_provider', 'hotel_admin')")['total'] ?? 0);
    assertCondition(abs($finalAdminRev - $usersSum) < 0.01, "Database records check: Admin total ($finalAdminRev) strictly matches provider revenue sum ($usersSum)");
    $passed += 1;

    // Clean up test data
    Database::run("DELETE FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef]);
    Database::run("DELETE FROM payments WHERE booking_id IN (?, ?, ?)", [$completedTourBookingId, $pendingTourBookingId, $pkgListingId]);
    Database::run("DELETE FROM bookings WHERE id IN (?, ?, ?, ?)", [$completedTourBookingId, $pendingTourBookingId, $pkgListingId, $hotelSubBookingId]);
    Database::run("DELETE FROM rental_requests WHERE id IN (?, ?, ?)", [$pendingRentalId, $completedRentalId, $rentalPkgReqId]);
    Database::run("DELETE FROM vehicles WHERE id = ?", [$vehicleId]);
    Database::run("DELETE FROM listings WHERE id IN (?, ?)", [$guideListingId, $hotelListingId]);
    Database::run("DELETE FROM users WHERE id IN (?, ?, ?, ?)", [$guideId, $hotelId, $rentalId, $touristId]);

    echo "\nProvider Revenue & Service Status: ALL $passed ASSERTIONS PASSED!\n";
} catch (\Throwable $e) {
    echo "\nException caught: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
}

exit($failed > 0 ? 1 : 0);
