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

echo "=== GuideMate Travel Package History, Review Restrictions & Disruption Management Tests ===\n\n";

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

// Setup & cleanup
PackageBookingService::ensureTables();
Database::run("DELETE FROM bookings WHERE package_booking_ref LIKE 'GM-PKG-HIST-%'");
Database::run("DELETE FROM rental_requests WHERE package_booking_ref LIKE 'GM-PKG-HIST-%'");
Database::run("DELETE FROM package_booking_items WHERE package_booking_ref LIKE 'GM-PKG-HIST-%'");

$tourist = Database::first("SELECT id, email, name FROM users WHERE role = 'tourist' LIMIT 1");
$touristId = (int) ($tourist['id'] ?? 4);

$guide = Database::first("SELECT id, email, name FROM users WHERE role = 'guide' LIMIT 1");
$guideId = (int) ($guide['id'] ?? 2);

$hotelPartner = Database::first("SELECT id, email, name FROM users WHERE role IN ('hotel_provider', 'hotel_partner') LIMIT 1");
$hotelPartnerId = (int) ($hotelPartner['id'] ?? 1);

$rentalPartner = Database::first("SELECT id, email, name FROM users WHERE role IN ('rental_provider', 'rental_partner') LIMIT 1");
$rentalPartnerId = (int) ($rentalPartner['id'] ?? 3);

$pkgListing = Database::first("SELECT id, title, price FROM listings WHERE category_id = (SELECT id FROM categories WHERE slug = 'packages' LIMIT 1) LIMIT 1");
$pkgListingId = (int) ($pkgListing['id'] ?? 1);

// =========================================================================
// 1. CANCELLED TRAVEL PACKAGE IN HISTORY & REVIEW RESTRICTION
// =========================================================================
echo "1. Testing Cancelled Travel Packages in History & Review Restrictions...\n";

$refCancelled = 'GM-PKG-HIST-CANC-' . time();
$parentCancelledId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-15', '2026-12-15', '2026-12-17', 2, 2, 8000.00, 'cancelled', ?, 'cancelled', '{\"type\":\"package_booking\",\"package_title\":\"Cancelled Package\"}', NOW(), NOW())
", [$pkgListingId, $touristId, $refCancelled]);

// Review eligibility check for cancelled package
$canReviewCancelled = Booking::hasCompletedBooking($pkgListingId, $touristId);
assertCondition(!$canReviewCancelled, "Booking::hasCompletedBooking rejects cancelled travel package (cannot review)");

// Completed package for contrast
$refCompleted = 'GM-PKG-HIST-COMP-' . time();
$parentCompletedId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-11-01', '2026-11-01', '2026-11-03', 2, 2, 8000.00, 'completed', ?, 'completed', '{\"type\":\"package_booking\",\"package_title\":\"Completed Package\"}', NOW(), NOW())
", [$pkgListingId, $touristId, $refCompleted]);

$canReviewCompleted = Booking::hasCompletedBooking($pkgListingId, $touristId);
assertCondition($canReviewCompleted, "Booking::hasCompletedBooking allows completed travel package (eligible for review)");

// Clean up completed test booking so it doesn't affect subsequent tests
Database::run("DELETE FROM bookings WHERE id = ?", [$parentCompletedId]);

// =========================================================================
// 2. WEB VERSION - GUIDE BOOKINGS 3-DOT MENU BUTTON CLEANUP
// =========================================================================
echo "\n2. Testing Guide Bookings 3-Dot Dropdown Actions...\n";

// Render guide bookings view template string or check file content
$guideBookingsView = file_get_contents(__DIR__ . '/../app/Views/guide/bookings.php');
assertCondition(!str_contains($guideBookingsView, 'action-replace-service'), "Option A - Replace Cancelled Service removed from table 3-dot dropdown menu");
assertCondition(!str_contains($guideBookingsView, 'action-reschedule-service'), "Option B - Reschedule Cancelled Service removed from table 3-dot dropdown menu");
assertCondition(!str_contains($guideBookingsView, 'action-reschedule-pkg'), "Reschedule Travel Date button removed from table 3-dot dropdown menu");
assertCondition(str_contains($guideBookingsView, 'action-manage-disruption'), "Dedicated 'Manage Service Disruption' action button is present for Action Required bookings");
assertCondition(!str_contains($guideBookingsView, 'id="reschedulePackageModal"'), "Obsolete standalone reschedule modal removed from bookings view");

// =========================================================================
// 3. OPTION A: REPLACE CANCELLED SERVICE
// =========================================================================
echo "\n3. Testing Disruption Management: Option A - Replace Cancelled Service...\n";

$refDisrupt = 'GM-PKG-HIST-DISR-' . time();
$parentDisruptId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, package_booking_ref, package_overall_status, notes, created_at, updated_at
    ) VALUES (?, ?, '2026-12-25', '2026-12-25', '2026-12-27', 2, 2, 12000.00, 'confirmed', ?, 'action_required', '{\"type\":\"package_booking\",\"package_title\":\"Disrupted Package\"}', NOW(), NOW())
", [$pkgListingId, $touristId, $refDisrupt]);
Payment::create($parentDisruptId, 12000.00, 'gcash', 'paid');

$cancelledItemId = Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, allocated_amount, status, cancellation_reason, created_at
    ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Old Beach Resort', 4000.00, 'cancelled', 'Overbooked for holidays', NOW())
", [$refDisrupt, $parentDisruptId, $pkgListingId, $hotelPartnerId]);

$summary = PackageBookingService::getPackageBookingSummary($refDisrupt);
assertCondition($summary['has_cancellation'] === true, "getPackageBookingSummary flags disruption");
assertCondition(count($summary['cancelled_items']) === 1, "Cancelled hotel service accurately identified");

$alternativesRes = PackageBookingService::getAvailableAlternatives($refDisrupt, $cancelledItemId);
assertCondition(count($alternativesRes['alternatives']) > 0, "Populates available replacements in the same category (Hotel and Stay)");
$firstAlt = $alternativesRes['alternatives'][0];
assertCondition($firstAlt['type'] === 'hotel', "Replacement matches category of cancelled service");
assertCondition(!empty($firstAlt['title']) && isset($firstAlt['price']), "Replacement provides title, price, and capacity");

// Confirm replacement
$replaceRes = PackageBookingService::replaceCancelledService(
    $refDisrupt,
    $cancelledItemId,
    (int) $firstAlt['provider_id'],
    (int) $firstAlt['listing_id'],
    ['room_id' => $firstAlt['room_id'] ?? 1],
    $guideId
);
assertCondition($replaceRes['success'] === true, "Option A replacement executes successfully");
$origItemAfter = Database::first("SELECT * FROM package_booking_items WHERE id = ?", [$cancelledItemId]);
assertCondition($origItemAfter['status'] === 'cancelled', "Original cancelled item preserved as cancelled for reference/history");
$replacementItem = Database::first("SELECT * FROM package_booking_items WHERE replaces_item_id = ?", [$cancelledItemId]);
assertCondition($replacementItem !== null && $replacementItem['status'] === 'pending', "Replacement item created as pending awaiting provider confirmation");

// Verify parent booking notes updated
$parentAfter = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentDisruptId]);
assertCondition(str_contains((string) $parentAfter['notes'], 'accommodation'), "Parent booking notes synchronized with replacement details");

// =========================================================================
// 4. OPTION B: RESCHEDULE TRAVEL DATE WITH AVAILABILITY VALIDATION
// =========================================================================
echo "\n4. Testing Disruption Management: Option B - Reschedule Travel Date...\n";

// Reschedule modal file check
$modalPartial = file_get_contents(__DIR__ . '/../app/Views/partials/package_alternatives_modal.php');
assertCondition(str_contains($modalPartial, 'Option B: Reschedule Travel Date'), "Option B title updated to 'Option B: Reschedule Travel Date'");
assertCondition(str_contains($modalPartial, 'rescheduleCurrentTravelDate'), "Displays current travel date for reference");
assertCondition(str_contains($modalPartial, 'New Travel Date *'), "Includes New Travel Date field");
assertCondition(str_contains($modalPartial, 'New Start Time (Optional)'), "Includes New Start Time optional field");

// Test Provider Availability Validation:
// Create a conflicting booking on room 1 for 2026-12-28
$hotelConflictId = Database::insert("
    INSERT INTO bookings (
        listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
        status, hotel_status, created_at, updated_at
    ) VALUES (?, 1, ?, '2026-12-28', '2026-12-28', '2026-12-30', 2, 2, 3000.00, 'confirmed', 'confirmed', NOW(), NOW())
", [$pkgListingId, $touristId]);

// Attach hotel room 1 to our disrupted package
Database::run("UPDATE bookings SET room_id = 1 WHERE id = ?", [$parentDisruptId]);

$conflictThrown = false;
try {
    // Attempt rescheduling package to conflicting date 2026-12-28
    PackageBookingService::rescheduleEntirePackage($refDisrupt, '2026-12-28', '09:00 AM', $guideId, 'Move to conflicting date');
} catch (\RuntimeException $e) {
    $conflictThrown = true;
    assertCondition(str_contains($e->getMessage(), 'not available') || str_contains($e->getMessage(), 'conflicts'), "Availability validation blocked rescheduling due to hotel room conflict");
}
assertCondition($conflictThrown, "Conflicting date was prevented from rescheduling");

// Remove conflicting hotel reservation
Database::run("DELETE FROM bookings WHERE id = ?", [$hotelConflictId]);

// Now reschedule to a valid non-conflicting future date
$validNewDate = '2026-12-29';
$reschedSuccess = PackageBookingService::rescheduleEntirePackage($refDisrupt, $validNewDate, '09:00 AM', $guideId, 'Coordinated with guest');

assertCondition($reschedSuccess['success'] === true, "Option B successfully reschedules package to valid date");
$parentRescheduled = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentDisruptId]);
assertCondition($parentRescheduled['booking_date'] === $validNewDate, "Main travel package travel date synchronized to {$validNewDate}");
assertCondition($parentRescheduled['status'] === 'pending' && $parentRescheduled['package_overall_status'] === 'pending', "Package status set to Pending awaiting provider reconfirmation");
assertCondition(Payment::isPaid($parentDisruptId), "Existing payment preserved as Paid");

// Verify audit log
$audit = Database::first("SELECT * FROM package_booking_audit_logs WHERE package_booking_ref = ? AND action_type = 'package_rescheduled'", [$refDisrupt]);
assertCondition($audit !== null, "Rescheduling recorded in booking audit history");

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passCount}, Failed: {$failCount}\n";
if ($failCount === 0) {
    echo "ALL TRAVEL PACKAGE HISTORY, REVIEW & DISRUPTION TESTS PASSED!\n";
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
