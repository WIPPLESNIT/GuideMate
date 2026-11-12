<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Controllers\ApiController;

echo "=== GuideMate Hotel Remaining Balance & Highlight Effects Tests ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $condition, string $label): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failCount++;
    }
}

// 1. Hotel Reservation Remaining Balance & Payments Test
echo "1. Testing Hotel Reservation Remaining Balance Calculation...\n";

$api = new ApiController();
$ref = new ReflectionMethod($api, 'bookingPayload');
$ref->setAccessible(true);

$b467 = Database::first("SELECT b.*, l.title as listing_title, l.cover_image, c.slug as category_slug FROM bookings b JOIN listings l ON l.id = b.listing_id JOIN categories c ON c.id = l.category_id WHERE b.id = 467");

assertCondition($b467 !== null, 'Booking 467 exists in database');

$payload467 = $ref->invoke($api, $b467, (int) $b467['user_id']);

assertCondition($payload467['is_hotel'] === true, 'Booking 467 is recognized as hotel');
assertCondition($payload467['total_amount'] === 7500.0, 'Booking 467 total_amount is 7500.00');
assertCondition($payload467['deposit_amount'] === 1500.0, 'Booking 467 deposit_amount is 1500.00');
assertCondition($payload467['balance_amount'] === 6000.0, 'Booking 467 balance_amount is 6000.00 (not 0.00)');
assertCondition($payload467['balance_payment_status'] === 'Pending', 'Booking 467 balance_payment_status is "Pending"');
assertCondition($payload467['payment_status'] === 'deposit_paid', 'Booking 467 payment_status is "deposit_paid" (NOT "paid")');
assertCondition($payload467['payment_stage'] === 'deposit_paid', 'Booking 467 payment_stage is "deposit_paid"');

// Test Simulated Check In Status
$bCheckedIn = $b467;
$bCheckedIn['hotel_status'] = 'checked_in';
$payloadCheckedIn = $ref->invoke($api, $bCheckedIn, (int) $b467['user_id']);

assertCondition($payloadCheckedIn['balance_amount'] === 0.0, 'Checked In reservation balance_amount is 0.00');
assertCondition($payloadCheckedIn['balance_payment_status'] === 'Paid', 'Checked In reservation balance_payment_status is "Paid"');
assertCondition($payloadCheckedIn['payment_status'] === 'paid', 'Checked In reservation payment_status is "paid"');
assertCondition($payloadCheckedIn['payment_stage'] === 'paid', 'Checked In reservation payment_stage is "paid"');

// Test Simulated Fully Paid Before Check In
$bFullyPaid = $b467;
$bFullyPaid['hotel_status'] = 'confirmed';
$bFullyPaid['balance_amount'] = 0.0;
$bFullyPaid['payment_stage'] = 'paid';
$payloadFullyPaid = $ref->invoke($api, $bFullyPaid, (int) $b467['user_id']);

assertCondition($payloadFullyPaid['balance_amount'] === 0.0, 'Fully paid reservation balance_amount is 0.00');
assertCondition($payloadFullyPaid['balance_payment_status'] === 'Paid', 'Fully paid reservation balance_payment_status is "Paid"');
assertCondition($payloadFullyPaid['payment_status'] === 'paid', 'Fully paid reservation payment_status is "paid"');
assertCondition($payloadFullyPaid['payment_stage'] === 'paid', 'Fully paid reservation payment_stage is "paid"');

// Test Simulated Completed / Settled
$bSettled = $b467;
$bSettled['hotel_status'] = 'completed';
$bSettled['payment_stage'] = 'settled';
$payloadSettled = $ref->invoke($api, $bSettled, (int) $b467['user_id']);

assertCondition($payloadSettled['balance_amount'] === 0.0, 'Completed reservation balance_amount is 0.00');
assertCondition($payloadSettled['balance_payment_status'] === 'Settled', 'Completed reservation balance_payment_status is "Settled"');
assertCondition($payloadSettled['payment_status'] === 'paid', 'Completed reservation payment_status is "paid"');
assertCondition($payloadSettled['payment_stage'] === 'settled', 'Completed reservation payment_stage is "settled"');

// 2. Testing App Version trips.tsx for Removal of Highlight Effects
echo "\n2. Testing App Version Trips UI for Highlight Removal...\n";

$tripsFile = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');

// Check renderAttractionItem
$hasAttractionCategoryPillInCard = str_contains($tripsFile, '<View style={styles.attractionCategoryPill}>\n              <Ionicons name="ticket-outline"')
    || (str_contains($tripsFile, 'renderAttractionItem') && str_contains($tripsFile, 'styles.attractionCategoryPill'));
assertCondition(!$hasAttractionCategoryPillInCard, 'renderAttractionItem does NOT contain attractionCategoryPill highlight effect');

// Check renderActivityItem
$hasActivityCategoryPillInCard = str_contains($tripsFile, '<View style={styles.activityCategoryPill}>\n              <Ionicons name="compass-outline"')
    || (str_contains($tripsFile, 'renderActivityItem') && str_contains($tripsFile, 'styles.activityCategoryPill'));
assertCondition(!$hasActivityCategoryPillInCard, 'renderActivityItem does NOT contain activityCategoryPill highlight effect');

// Check Modal Service Overview
$hasCategoryPillInModal = str_contains($tripsFile, 'isDetailsActivity ? (\n                        <View style={[styles.activityCategoryPill');
assertCondition(!$hasCategoryPillInModal, 'Modal Service Overview does NOT contain unwanted category pill highlights');

// Verify Category Filters (Tabs) are still present and intact
assertCondition(str_contains($tripsFile, "categoryFilter === 'hotel'"), 'Hotel category filter tab is preserved');
assertCondition(str_contains($tripsFile, "categoryFilter === 'activity'"), 'Activity category filter tab is preserved');
assertCondition(str_contains($tripsFile, "categoryFilter === 'attraction'"), 'Attraction category filter tab is preserved');
assertCondition(str_contains($tripsFile, "categoryFilter === 'package'"), 'Package category filter tab is preserved');

// Verify Hotel Remaining Due UI logic in trips.tsx
assertCondition(str_contains($tripsFile, "isCheckedIn || isFullyPaid ? 'Paid' : formatPrice(unpaidBalance)"), 'Remaining Due displays Paid when checked in or fully paid, and formatPrice(unpaidBalance) when awaiting payment');
assertCondition(str_contains($tripsFile, "Remaining balance paid at check-in. No further payment required."), 'Front desk notice displays "Remaining balance paid at check-in" when checked in');
assertCondition(str_contains($tripsFile, "Pay remaining balance at front desk"), 'Front desk notice displays "Pay remaining balance at front desk" when awaiting payment');
assertCondition(str_contains($tripsFile, "{formatPrice(0)} (Paid at Check-In)"), 'Modal displays "₱0 (Paid at Check-In)" when checked in');

// 3. Testing Web Version Consistency
echo "\n3. Testing Web Version Consistency...\n";

$webBookingsFile = file_get_contents(__DIR__ . '/../app/Views/bookings/index.php');
assertCondition(str_contains($webBookingsFile, '$hotelStatus === \'checked_in\' || ($b[\'payment_stage\'] ?? \'\') === \'paid\''), 'Web bookings index checks for checked_in or paid hotel reservation');
assertCondition(str_contains($webBookingsFile, 'Due: Paid (at check-in)'), 'Web bookings index displays "Due: Paid (at check-in)" when checked in');

$webHotelFile = file_get_contents(__DIR__ . '/../app/Views/dashboard/hotel.php');
assertCondition(str_contains($webHotelFile, '$hotelStatus === \'checked_in\' || ($b[\'payment_stage\'] ?? \'\') === \'paid\''), 'Web hotel dashboard checks for checked_in or paid');
assertCondition(str_contains($webHotelFile, '<span class="pill pill-paid" style="font-size:0.74rem;padding:2px 7px;background:#DCFCE7;color:#15803D;border:1px solid #86EFAC;">Paid</span>'), 'Web hotel dashboard displays Paid pill when checked in');

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passCount}, Failed: {$failCount}\n";
echo "======================================================\n";

if ($failCount > 0) {
    exit(1);
}
