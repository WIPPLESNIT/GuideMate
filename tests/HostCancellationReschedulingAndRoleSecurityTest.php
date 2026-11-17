<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\PackageBookingService;

echo "=== GuideMate Host Cancellation, Rescheduling & Role Restrictions Tests ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$message}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failCount++;
    }
}

// Ensure database setup
PackageBookingService::ensureTables();

// Find or create test users
$tourist = Database::first("SELECT id, email, name FROM users WHERE role = 'tourist' LIMIT 1");
$touristId = (int) ($tourist['id'] ?? 4);

$guide = Database::first("SELECT id, email, name FROM users WHERE role = 'guide' LIMIT 1");
$guideId = (int) ($guide['id'] ?? 2);

$hotelPartner = Database::first("SELECT id, email, name FROM users WHERE role IN ('hotel_provider', 'hotel_partner') LIMIT 1");
$hotelPartnerId = (int) ($hotelPartner['id'] ?? 1);

$rentalPartner = Database::first("SELECT id, email, name FROM users WHERE role IN ('rental_provider', 'rental_partner') LIMIT 1");
$rentalPartnerId = (int) ($rentalPartner['id'] ?? 3);

$pkgListing = Database::first("SELECT id, title, price FROM listings WHERE category_id = (SELECT id FROM categories WHERE slug = 'packages' LIMIT 1) LIMIT 1");
if (!$pkgListing) {
    $catId = Database::first("SELECT id FROM categories WHERE slug = 'packages' LIMIT 1")['id'] ?? 1;
    $pkgListingId = Database::insert("
        INSERT INTO listings (user_id, category_id, title, slug, description, price, area, status, created_at, updated_at)
        VALUES (?, ?, 'Test Host Package', 'test-host-package', 'Test host package', 10000.00, 'Moalboal', 'approved', NOW(), NOW())
    ", [$guideId, $catId]);
} else {
    $pkgListingId = (int) $pkgListing['id'];
}

$timestamp = time();

// -------------------------------------------------------------------------
// 1. Host-Initiated Travel Package Cancellation (Section 1)
// -------------------------------------------------------------------------
echo "1. Testing Web Version - Host-Initiated Travel Package Cancellation...\n";
$pkgRef1 = "GM-PKG-HOST-CANCEL-{$timestamp}";

// Create parent package booking
$parentBookingId1 = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-10', '2026-12-10', '2026-12-12', 2, 2, 10000.00, 'confirmed', ?, 'confirmed', '{\"type\":\"package_booking\",\"package_title\":\"Host Test Package\"}', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef1]);

// Record payment
Payment::create($parentBookingId1, 10000.00, 'gcash', 'paid');

// Create associated hotel sub-booking
$hotelBookingId1 = Database::insert("
    INSERT INTO bookings (
        listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, hotel_status, package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, 1, ?, '2026-12-10', '2026-12-10', '2026-12-12', 2, 2, 5000.00, 'confirmed', 'confirmed', ?, 'confirmed', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef1]);

// Create associated vehicle rental request
$rentalRequestId1 = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount,
        customer_name, customer_email, status, package_booking_ref, package_booking_id, created_at, updated_at
    ) VALUES (?, 'Yamaha NMAX', 'Scooter', 'Moalboal', '2026-12-10', 2, 2000.00, 'Test Tourist', 'tourist@guidemate.com', 'approved', ?, ?, NOW(), NOW())
", [$touristId, $pkgRef1, $parentBookingId1]);

// Create package items
Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guided Tour Services', ?, 3000.00, 'confirmed', NOW())
", [$pkgRef1, $parentBookingId1, $pkgListingId, $guideId, $parentBookingId1]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Deluxe Room', ?, 5000.00, 'confirmed', NOW())
", [$pkgRef1, $parentBookingId1, $pkgListingId, $hotelPartnerId, $hotelBookingId1]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Scooter Rental', ?, 2000.00, 'confirmed', NOW())
", [$pkgRef1, $parentBookingId1, $pkgListingId, $rentalPartnerId, $rentalRequestId1]);

// Host cancels the entire package!
$cancelRes = PackageBookingService::cancelEntirePackage($pkgRef1, $guideId, 'Cancelled by host/package organizer', true);

// Verify cancellations
$parentUpdated1 = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentBookingId1]);
$hotelUpdated1 = Database::first("SELECT * FROM bookings WHERE id = ?", [$hotelBookingId1]);
$rentalUpdated1 = Database::first("SELECT * FROM rental_requests WHERE id = ?", [$rentalRequestId1]);
$itemsUpdated1 = Database::all("SELECT * FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef1]);

assertCondition($parentUpdated1['status'] === 'cancelled' && $parentUpdated1['package_overall_status'] === 'cancelled', "Overall travel package status is CANCELLED");
assertCondition($hotelUpdated1['status'] === 'cancelled' && $hotelUpdated1['hotel_status'] === 'cancelled', "Associated Hotel reservation automatically CANCELLED");
assertCondition($rentalUpdated1['status'] === 'cancelled', "Associated Vehicle rental request automatically CANCELLED");

$allItemsCancelled = true;
foreach ($itemsUpdated1 as $it) {
    if ($it['status'] !== 'cancelled') {
        $allItemsCancelled = false;
        break;
    }
}
assertCondition($allItemsCancelled, "All individual package booking items marked CANCELLED");

$auditCancel = Database::first("SELECT * FROM package_booking_audit_logs WHERE package_booking_ref = ? AND action_type = 'package_cancelled'", [$pkgRef1]);
assertCondition(!empty($auditCancel), "Cancellation recorded in booking audit history");

// -------------------------------------------------------------------------
// 2. Travel Package Rescheduling (Section 2)
// -------------------------------------------------------------------------
echo "\n2. Testing Travel Package Rescheduling (Host-initiated)...\n";
$pkgRef2 = "GM-PKG-HOST-RESCHED-{$timestamp}";

$parentBookingId2 = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-10', '2026-12-10', '2026-12-12', 2, 2, 10000.00, 'confirmed', ?, 'confirmed', '{\"type\":\"package_booking\",\"package_title\":\"Resched Test Package\"}', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef2]);

// Record payment
Payment::create($parentBookingId2, 10000.00, 'gcash', 'paid');

$hotelBookingId2 = Database::insert("
    INSERT INTO bookings (
        listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, hotel_status, package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, 1, ?, '2026-12-10', '2026-12-10', '2026-12-12', 2, 2, 5000.00, 'confirmed', 'confirmed', ?, 'confirmed', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef2]);

$rentalRequestId2 = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount,
        customer_name, customer_email, status, package_booking_ref, package_booking_id, created_at, updated_at
    ) VALUES (?, 'Yamaha NMAX', 'Scooter', 'Moalboal', '2026-12-10', 2, 2000.00, 'Test Tourist', 'tourist@guidemate.com', 'approved', ?, ?, NOW(), NOW())
", [$touristId, $pkgRef2, $parentBookingId2]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guided Tour Services', ?, 3000.00, 'confirmed', NOW())
", [$pkgRef2, $parentBookingId2, $pkgListingId, $guideId, $parentBookingId2]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Deluxe Room', ?, 5000.00, 'confirmed', NOW())
", [$pkgRef2, $parentBookingId2, $pkgListingId, $hotelPartnerId, $hotelBookingId2]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Scooter Rental', ?, 2000.00, 'confirmed', NOW())
", [$pkgRef2, $parentBookingId2, $pkgListingId, $rentalPartnerId, $rentalRequestId2]);

$newTripDate = '2026-12-20';
$reschedRes = PackageBookingService::rescheduleEntirePackage($pkgRef2, $newTripDate, '08:00 AM', $guideId, 'Adjusted trip date per host coordination');

$parentUpdated2 = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentBookingId2]);
$hotelUpdated2 = Database::first("SELECT * FROM bookings WHERE id = ?", [$hotelBookingId2]);
$rentalUpdated2 = Database::first("SELECT * FROM rental_requests WHERE id = ?", [$rentalRequestId2]);

assertCondition($parentUpdated2['booking_date'] === $newTripDate, "Parent booking date updated to new date ({$newTripDate})");
assertCondition($parentUpdated2['status'] === 'pending' && $parentUpdated2['package_overall_status'] === 'pending', "Package status updated to PENDING");
assertCondition(Payment::isPaid($parentBookingId2), "Original payment status RETAINED as PAID");

assertCondition($hotelUpdated2['check_in_date'] === $newTripDate, "Hotel check_in_date synchronized to {$newTripDate}");
assertCondition($hotelUpdated2['hotel_status'] === 'pending', "Hotel status updated to PENDING awaiting re-confirmation");

assertCondition($rentalUpdated2['pickup_date'] === $newTripDate, "Rental pickup_date synchronized to {$newTripDate}");
assertCondition($rentalUpdated2['status'] === 'pending', "Rental request updated to PENDING awaiting re-confirmation");

$auditResched = Database::first("SELECT * FROM package_booking_audit_logs WHERE package_booking_ref = ? AND action_type = 'package_rescheduled'", [$pkgRef2]);
assertCondition(!empty($auditResched), "Audit log recorded package_rescheduled event");

// -------------------------------------------------------------------------
// 3. Backend Role-Based Access Security (Section 8)
// -------------------------------------------------------------------------
echo "\n3. Testing Backend Role-Based Permissions & Restriction Enforcement...\n";

$apiCtrl = new \App\Controllers\ApiController();

$pkgRef3 = "GM-PKG-ROLE-SEC-{$timestamp}";
$parentBookingId3 = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-15', '2026-12-15', '2026-12-17', 2, 2, 10000.00, 'action_required', ?, 'action_required', '{\"type\":\"package_booking\",\"package_title\":\"Role Test Package\"}', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef3]);

$cancelledItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, allocated_amount, status, cancellation_reason, created_at
    ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Grand Cebu Hotel', 5000.00, 'cancelled', 'Renovations', NOW())
", [$pkgRef3, $parentBookingId3, $pkgListingId, $hotelPartnerId]);

$guideItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Tour Guide Service', 5000.00, 'confirmed', NOW())
", [$pkgRef3, $parentBookingId3, $pkgListingId, $guideId]);

// Traveler attempts Option A (Replace) -> MUST FAIL (Host only)
$_SESSION['user_id'] = $touristId;
\App\Core\Auth::refreshUser();

ob_start();
$_POST = ['item_id' => $cancelledItemId, 'provider_id' => $hotelPartnerId, 'service_id' => 1];
$apiCtrl->packageBookingReplace($pkgRef3);
$outA = ob_get_clean();
$jsonA = json_decode($outA, true);
assertCondition(isset($jsonA['error']) && str_contains($jsonA['error'], 'Only the host/organizer can replace'), "Traveler calling Option A (Replace) is BLOCKED (403)");

// Traveler attempts Option B (Reschedule) -> MUST FAIL (Host only)
ob_start();
$_POST = ['item_id' => $cancelledItemId, 'new_date' => '2026-12-18'];
$apiCtrl->packageBookingReschedule($pkgRef3);
$outB = ob_get_clean();
$jsonB = json_decode($outB, true);
assertCondition(isset($jsonB['error']) && str_contains($jsonB['error'], 'Only the host/organizer can reschedule'), "Traveler calling Option B (Reschedule) is BLOCKED (403)");

// Host attempts Option C (Continue Without) -> MUST FAIL (Traveler only)
$_SESSION['user_id'] = $guideId;
\App\Core\Auth::refreshUser();

ob_start();
$_POST = ['item_id' => $cancelledItemId];
$apiCtrl->packageBookingContinueWithout($pkgRef3);
$outC = ob_get_clean();
$jsonC = json_decode($outC, true);
assertCondition(isset($jsonC['error']) && str_contains($jsonC['error'], 'Only the traveler can choose to continue without'), "Host calling Option C (Continue Without) is BLOCKED (403)");

// Host attempts Option D (Cancel Full Package via traveler endpoint) -> MUST FAIL (Traveler only)
ob_start();
$_POST = ['reason' => 'Host trying Option D'];
$apiCtrl->packageBookingCancelFull($pkgRef3);
$outD = ob_get_clean();
$jsonD = json_decode($outD, true);
assertCondition(isset($jsonD['error']) && str_contains($jsonD['error'], 'Only the traveler can cancel the entire package under Option D'), "Host calling Option D is BLOCKED (403)");

// Traveler attempts Option C on essential Guide service -> MUST FAIL (Essential protection)
$_SESSION['user_id'] = $touristId;
\App\Core\Auth::refreshUser();

// Mark guide service as cancelled to test essential check
Database::run("UPDATE package_booking_items SET status = 'cancelled' WHERE id = ?", [$guideItemId]);
ob_start();
$_POST = ['item_id' => $guideItemId];
$apiCtrl->packageBookingContinueWithout($pkgRef3);
$outEssential = ob_get_clean();
$jsonEssential = json_decode($outEssential, true);
assertCondition(isset($jsonEssential['error']) && str_contains($jsonEssential['error'], 'essential'), "Option C prevents removing essential guide service from itinerary");

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passCount}, Failed: {$failCount}\n";
if ($failCount === 0) {
    echo "ALL HOST CANCELLATION, RESCHEDULING & SECURITY TESTS PASSED!\n";
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
