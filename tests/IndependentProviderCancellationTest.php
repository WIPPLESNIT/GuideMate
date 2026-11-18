<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\PackageBookingService;

echo "=== GuideMate Independent Provider Cancellation & Alternatives Tests ===\n\n";

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

PackageBookingService::ensureTables();

// Setup test users and listing
$tourist = Database::first("SELECT id, email, name FROM users WHERE role = 'tourist' LIMIT 1");
if (!$tourist) {
    $touristId = Database::insert("INSERT INTO users (name, email, role, status, created_at) VALUES ('Test Tourist', 'tourist_test@guidemate.com', 'tourist', 'active', NOW())");
} else {
    $touristId = (int) $tourist['id'];
}

$guide = Database::first("SELECT id, email, name FROM users WHERE role = 'guide' LIMIT 1");
if (!$guide) {
    $guideId = Database::insert("INSERT INTO users (name, email, role, status, created_at) VALUES ('Test Guide', 'guide_test@guidemate.com', 'guide', 'active', NOW())");
} else {
    $guideId = (int) $guide['id'];
}

$hotelPartner = Database::first("SELECT id, email, name FROM users WHERE role = 'hotel_provider' LIMIT 1");
if (!$hotelPartner) {
    $hotelPartnerId = Database::insert("INSERT INTO users (name, email, role, status, created_at) VALUES ('Test Hotel Partner', 'hotel_test@guidemate.com', 'hotel_provider', 'active', NOW())");
} else {
    $hotelPartnerId = (int) $hotelPartner['id'];
}

$rentalPartner = Database::first("SELECT id, email, name FROM users WHERE role = 'rental_provider' LIMIT 1");
if (!$rentalPartner) {
    $rentalPartnerId = Database::insert("INSERT INTO users (name, email, role, status, created_at) VALUES ('Test Rental Partner', 'rental_test@guidemate.com', 'rental_provider', 'active', NOW())");
} else {
    $rentalPartnerId = (int) $rentalPartner['id'];
}

$pkgListing = Database::first("SELECT id, title, price FROM listings WHERE category_id = (SELECT id FROM categories WHERE slug = 'packages' LIMIT 1) LIMIT 1");
if (!$pkgListing) {
    $catId = Database::first("SELECT id FROM categories WHERE slug = 'packages' LIMIT 1")['id'] ?? 1;
    $pkgListingId = Database::insert("
        INSERT INTO listings (user_id, category_id, title, slug, description, price, area, status, created_at, updated_at)
        VALUES (?, ?, 'Test Cebu Package', 'test-cebu-package', 'Test package', 12000.00, 'Moalboal', 'approved', NOW(), NOW())
    ", [$guideId, $catId]);
} else {
    $pkgListingId = (int) $pkgListing['id'];
}

// ---------------------------------------------------------------------
// TEST 1: Single Provider Cancellation does NOT Cancel Other Providers
// ---------------------------------------------------------------------
echo "1. Testing Independent Provider Cancellation (Hotel cancels, Guide & Rental remain active)...\n";

$pkgRef1 = 'GM-PKG-INDEP1-' . time();
$parentBookingId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, status,
        package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-15', '2026-12-15', '2026-12-17', 2, 2, 12000.00, 'confirmed', ?, 'confirmed', '{\"type\":\"package_booking\",\"package_title\":\"Cebu Explorer\"}', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef1]);

$hotelSubBookingId = Database::insert("
    INSERT INTO bookings (
        listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        deposit_amount, balance_amount, status, hotel_status, package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, 1, ?, '2026-12-15', '2026-12-15', '2026-12-17', 2, 2, 5000.00, 5000.00, 0.00, 'confirmed', 'confirmed', ?, 'confirmed', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef1]);

$rentalRequestId = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount,
        customer_name, customer_email, status, package_booking_ref, package_booking_id, created_at, updated_at
    ) VALUES (?, 'Yamaha NMAX', 'Scooter', 'Moalboal', '2026-12-15', 2, 2000.00, 'Test Tourist', 'tourist@guidemate.com', 'approved', ?, ?, NOW(), NOW())
", [$touristId, $pkgRef1, $parentBookingId]);

$guideItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guided Tour Services', ?, 5000.00, 'confirmed', NOW())
", [$pkgRef1, $parentBookingId, $pkgListingId, $guideId, $parentBookingId]);

$hotelItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Hotel Resort Deluxe Room', ?, 5000.00, 'confirmed', NOW())
", [$pkgRef1, $parentBookingId, $pkgListingId, $hotelPartnerId, $hotelSubBookingId]);

$rentalItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Yamaha NMAX Rental', ?, 2000.00, 'confirmed', NOW())
", [$pkgRef1, $parentBookingId, $pkgListingId, $rentalPartnerId, $rentalRequestId]);

// Trigger: Hotel provider cancels reservation
PackageBookingService::onHotelBookingUpdated($hotelSubBookingId, 'cancel', $hotelPartnerId);

$parentCheck = Database::first("SELECT status, package_overall_status FROM bookings WHERE id = ?", [$parentBookingId]);
$hotelCheck = Database::first("SELECT status, hotel_status FROM bookings WHERE id = ?", [$hotelSubBookingId]);
$rentalCheck = Database::first("SELECT status FROM rental_requests WHERE id = ?", [$rentalRequestId]);
$guideItemCheck = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [$guideItemId]);
$hotelItemCheck = Database::first("SELECT status, cancellation_reason FROM package_booking_items WHERE id = ?", [$hotelItemId]);
$rentalItemCheck = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [$rentalItemId]);

assertTest($hotelItemCheck['status'] === 'cancelled', 'Only the cancelling hotel service is marked cancelled');
assertTest($hotelCheck['status'] === 'cancelled' && $hotelCheck['hotel_status'] === 'cancelled', 'Hotel sub-booking marked cancelled');
assertTest($guideItemCheck['status'] === 'confirmed', 'Guide booking item remains CONFIRMED (not cancelled)');
assertTest($rentalItemCheck['status'] === 'confirmed', 'Vehicle rental item remains CONFIRMED (not cancelled)');
assertTest($rentalCheck['status'] === 'approved', 'Vehicle rental request remains APPROVED (not cancelled)');
assertTest($parentCheck['package_overall_status'] === 'action_required', 'Overall package status changed to ACTION_REQUIRED (not cancelled)');
assertTest($parentCheck['status'] !== 'cancelled', 'Parent booking status is not cancelled');

// Verify audit log exists
$audit = Database::first("SELECT * FROM package_booking_audit_logs WHERE package_booking_ref = ? AND action_type = 'provider_cancelled' ORDER BY id DESC LIMIT 1", [$pkgRef1]);
assertTest($audit !== null, 'Audit log correctly recorded provider_cancelled event');

// ---------------------------------------------------------------------
// TEST 2: Summary API Returns Action Required and Alternatives
// ---------------------------------------------------------------------
echo "\n2. Testing getPackageBookingSummary with Independent Statuses & Alternative Options...\n";

$summary = PackageBookingService::getPackageBookingSummary($pkgRef1);
assertTest($summary !== null, 'Summary returned successfully');
assertTest($summary['overall_status'] === 'action_required', 'Summary overall_status is action_required');
assertTest($summary['has_cancellation'] === true, 'Summary flags has_cancellation = true');
assertTest(count($summary['cancelled_items']) === 1, 'Summary isolates exactly 1 cancelled item');
assertTest(isset($summary['alternative_options']['replace']) && isset($summary['alternative_options']['reschedule']), 'Alternative options present Option A (replace) and Option B (reschedule) first');

// ---------------------------------------------------------------------
// TEST 3: Option A – Replace Cancelled Service
// ---------------------------------------------------------------------
echo "\n3. Testing Option A: Replace Cancelled Service...\n";

// Find or create an alternative hotel listing with price <= original (5000 / 2 nights = 2500/night)
$altHotel = Database::first("SELECT id, user_id FROM listings WHERE category_id = (SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1) AND price <= 2500.00 AND id != ? LIMIT 1", [$pkgListingId]);
if (!$altHotel) {
    $altCatId = Database::first("SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1")['id'] ?? 2;
    $altHotelId = Database::insert("INSERT INTO listings (user_id, category_id, title, slug, price, area, status, created_at, updated_at) VALUES (?, ?, 'Replacement Beach Resort', 'replacement-beach-resort-" . time() . "', 2400.00, 'Moalboal', 'approved', NOW(), NOW())", [$hotelPartnerId, $altCatId]);
    $altHotelProviderId = $hotelPartnerId;
    $altRoomId = (int) Database::insert("INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests) VALUES (?, 'Standard Room', 2400.00, 2)", [$altHotelId]);
} else {
    $altHotelId = (int) $altHotel['id'];
    $altHotelProviderId = (int) $altHotel['user_id'];
    $altRoom = Database::first("SELECT id FROM hotel_rooms WHERE listing_id = ? AND price_per_night <= 2500.00 LIMIT 1", [$altHotelId]);
    $altRoomId = $altRoom ? (int) $altRoom['id'] : 0;
}

$replaceRes = PackageBookingService::replaceCancelledService($pkgRef1, $hotelItemId, $altHotelProviderId, $altHotelId, ['room_id' => $altRoomId], $guideId);
assertTest($replaceRes['success'] === true, 'replaceCancelledService executed successfully');

// Replacement item is initially pending provider confirmation
$repHotelItem = Database::first("SELECT id, status, service_name, sub_booking_id, allocated_amount FROM package_booking_items WHERE replaces_item_id = ?", [$hotelItemId]);
assertTest($repHotelItem !== null, 'New replacement hotel item created');
assertTest($repHotelItem['status'] === 'pending', 'Replacement hotel item is initially pending');

// Replacement provider confirms the booking
PackageBookingService::respond($pkgRef1, 'hotel', $altHotelProviderId, 'confirm');

$repHotelItemAfterConfirm = Database::first("SELECT status, service_name, sub_booking_id, allocated_amount FROM package_booking_items WHERE replaces_item_id = ?", [$hotelItemId]);
$parentAfterReplace = Database::first("SELECT status, package_overall_status, total_amount FROM bookings WHERE id = ?", [$parentBookingId]);
$newSubBooking = Database::first("SELECT status, hotel_status FROM bookings WHERE id = ?", [(int) $repHotelItemAfterConfirm['sub_booking_id']]);

assertTest($repHotelItemAfterConfirm['status'] === 'confirmed', 'Replaced hotel service item is now CONFIRMED');
assertTest($newSubBooking !== null && $newSubBooking['status'] === 'confirmed', 'New hotel sub-booking created and confirmed');
assertTest($parentAfterReplace['package_overall_status'] === 'confirmed', 'Package overall status transitioned back to CONFIRMED');

$replaceAudit = Database::first("SELECT * FROM package_booking_audit_logs WHERE package_booking_ref = ? AND action_type = 'service_replaced' LIMIT 1", [$pkgRef1]);
assertTest($replaceAudit !== null, 'service_replaced audit log correctly recorded');

// ---------------------------------------------------------------------
// TEST 4: Option B – Reschedule Cancelled Service
// ---------------------------------------------------------------------
echo "\n4. Testing Option B: Reschedule Cancelled Service...\n";

// Let rental provider cancel on a new package
$pkgRef2 = 'GM-PKG-INDEP2-' . time();
$parent2Id = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, status,
        package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, ?, '2026-12-20', 2, 10000.00, 'confirmed', ?, 'confirmed', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef2]);

$rental2ReqId = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount,
        customer_name, customer_email, status, package_booking_ref, package_booking_id, created_at, updated_at
    ) VALUES (?, 'Honda Beat', 'Scooter', 'Cebu', '2026-12-20', 2, 1500.00, 'Test Tourist', 't@test.com', 'approved', ?, ?, NOW(), NOW())
", [$touristId, $pkgRef2, $parent2Id]);

$guide2ItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide', ?, 8500.00, 'confirmed', NOW())
", [$pkgRef2, $parent2Id, $pkgListingId, $guideId, $parent2Id]);

$rental2ItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Honda Beat', ?, 1500.00, 'confirmed', NOW())
", [$pkgRef2, $parent2Id, $pkgListingId, $rentalPartnerId, $rental2ReqId]);

// Rental provider cancels
PackageBookingService::onRentalUpdated($rental2ReqId, 'cancelled', $rentalPartnerId);

$rental2ItemCancelled = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [$rental2ItemId]);
$guide2ItemUntouched = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [$guide2ItemId]);
$parent2Status = Database::first("SELECT package_overall_status FROM bookings WHERE id = ?", [$parent2Id]);

assertTest($rental2ItemCancelled['status'] === 'cancelled', 'Rental item is cancelled');
assertTest($guide2ItemUntouched['status'] === 'confirmed', 'Guide item remains confirmed');
assertTest($parent2Status['package_overall_status'] === 'action_required', 'Package status is action_required');

// Now execute Option B: Reschedule service
$rescheduleRes = PackageBookingService::rescheduleCancelledService($pkgRef2, $rental2ItemId, '2026-12-22', '09:00 AM', 'Rescheduled with provider agreement', $touristId);
assertTest($rescheduleRes['success'] === true, 'rescheduleCancelledService executed successfully');

$rental2ItemAfterResched = Database::first("SELECT status, rescheduled_date, rescheduled_time FROM package_booking_items WHERE id = ?", [$rental2ItemId]);
$rental2ReqAfterResched = Database::first("SELECT pickup_date, status FROM rental_requests WHERE id = ?", [$rental2ReqId]);
$parent2AfterResched = Database::first("SELECT package_overall_status FROM bookings WHERE id = ?", [$parent2Id]);

assertTest($rental2ItemAfterResched['status'] === 'rescheduled', 'Item status updated to rescheduled');
assertTest($rental2ItemAfterResched['rescheduled_date'] === '2026-12-22', 'Rescheduled date updated');
assertTest($rental2ReqAfterResched['pickup_date'] === '2026-12-22', 'Rental request pickup date updated');
assertTest($parent2AfterResched['package_overall_status'] === 'confirmed', 'Overall package status transitioned back to confirmed');

// ---------------------------------------------------------------------
// TEST 5: Option C – Continue Without Cancelled Service
// ---------------------------------------------------------------------
echo "\n5. Testing Option C: Continue Travel Package Without Cancelled Service...\n";

$pkgRef3 = 'GM-PKG-INDEP3-' . time();
$parent3Id = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, status,
        package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, ?, '2026-12-25', 2, 10000.00, 'confirmed', ?, 'confirmed', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef3]);

$guide3ItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide', ?, 8000.00, 'confirmed', NOW())
", [$pkgRef3, $parent3Id, $pkgListingId, $guideId, $parent3Id]);

$rental3ReqId = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount,
        customer_name, customer_email, status, package_booking_ref, package_booking_id, created_at, updated_at
    ) VALUES (?, 'Scooter', 'Scooter', 'Cebu', '2026-12-25', 2, 2000.00, 'Test Tourist', 't@test.com', 'approved', ?, ?, NOW(), NOW())
", [$touristId, $pkgRef3, $parent3Id]);

$rental3ItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Scooter', ?, 2000.00, 'confirmed', NOW())
", [$pkgRef3, $parent3Id, $pkgListingId, $rentalPartnerId, $rental3ReqId]);

// Rental provider cancels
PackageBookingService::onRentalUpdated($rental3ReqId, 'cancelled', $rentalPartnerId);

// Execute Option C: Continue without rental service
$dropRes = PackageBookingService::continueWithoutService($pkgRef3, $rental3ItemId, $touristId, 'Tourist will take public taxi instead');
assertTest($dropRes['success'] === true, 'continueWithoutService executed successfully');
assertTest((float) $dropRes['refund_amount'] === 2000.00, 'Refund of ₱2000 allocated amount calculated');
assertTest((float) $dropRes['new_total_amount'] === 8000.00, 'New package total recalculated to ₱8000');

$parent3AfterDrop = Database::first("SELECT total_amount, refund_amount, refund_status, package_overall_status FROM bookings WHERE id = ?", [$parent3Id]);
$rental3ItemAfterDrop = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [$rental3ItemId]);

assertTest($rental3ItemAfterDrop['status'] === 'removed', 'Dropped service marked as removed');
assertTest((float) $parent3AfterDrop['total_amount'] === 8000.00, 'Parent booking total_amount updated in DB');
assertTest((float) $parent3AfterDrop['refund_amount'] === 2000.00, 'Parent booking refund_amount updated in DB');
assertTest($parent3AfterDrop['package_overall_status'] === 'confirmed', 'Package overall status is confirmed for remaining active services');

// ---------------------------------------------------------------------
// TEST 6: Option D – Cancel Entire Package
// ---------------------------------------------------------------------
echo "\n6. Testing Option D: Cancel Entire Package...\n";

$pkgRef4 = 'GM-PKG-INDEP4-' . time();
$parent4Id = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, status,
        package_booking_ref, package_overall_status, created_at, updated_at
    ) VALUES (?, ?, '2026-12-28', 2, 9000.00, 'confirmed', ?, 'confirmed', NOW(), NOW())
", [$pkgListingId, $touristId, $pkgRef4]);

$guide4ItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide', ?, 9000.00, 'confirmed', NOW())
", [$pkgRef4, $parent4Id, $pkgListingId, $guideId, $parent4Id]);

$cancelSummary = PackageBookingService::calculateEntirePackageCancellationSummary($pkgRef4);
assertTest((float) $cancelSummary['refund_amount'] === 9000.00, 'calculateEntirePackageCancellationSummary calculates full refund');
assertTest(count($cancelSummary['consequences']) > 0, 'Consequences list populated');

$fullCancelRes = PackageBookingService::cancelEntirePackage($pkgRef4, $touristId, 'Tourist chose to cancel entire trip', true);
assertTest($fullCancelRes['success'] === true, 'cancelEntirePackage executed successfully');

$parent4AfterCancel = Database::first("SELECT status, package_overall_status, refund_status FROM bookings WHERE id = ?", [$parent4Id]);
$guide4ItemAfterCancel = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [$guide4ItemId]);

assertTest($parent4AfterCancel['status'] === 'cancelled' && $parent4AfterCancel['package_overall_status'] === 'cancelled', 'Parent package marked cancelled');
assertTest($guide4ItemAfterCancel['status'] === 'cancelled', 'All included items marked cancelled');
assertTest($parent4AfterCancel['refund_status'] === 'refunded', 'Refund status set to refunded');

// Clean up test records
Database::run("DELETE FROM package_booking_audit_logs WHERE package_booking_ref IN (?, ?, ?, ?)", [$pkgRef1, $pkgRef2, $pkgRef3, $pkgRef4]);
Database::run("DELETE FROM package_booking_items WHERE package_booking_ref IN (?, ?, ?, ?)", [$pkgRef1, $pkgRef2, $pkgRef3, $pkgRef4]);
Database::run("DELETE FROM bookings WHERE id IN (?, ?, ?, ?, ?)", [$parentBookingId, $hotelSubBookingId, $parent2Id, $parent3Id, $parent4Id]);
Database::run("DELETE FROM rental_requests WHERE id IN (?, ?, ?)", [$rentalRequestId, $rental2ReqId, $rental3ReqId]);

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
if ($failed === 0) {
    echo "ALL INDEPENDENT CANCELLATION TESTS PASSED!\n";
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
