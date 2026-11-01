<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Controllers\ApiController;
use App\Services\NotificationService;

echo "========================================================\n";
echo " GuideMate Cancellation & Refund Policy Verification\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertCond(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] $msg\n";
        $passed++;
    } else {
        echo " [FAIL] $msg\n";
        $failed++;
    }
}

// ---------------------------------------------------------
// Test 1: Database Migration Schema
// ---------------------------------------------------------
echo "--- Test 1: Database Schema Migration ---\n";
$db = Database::connection();

$checkBookingCols = $db->query("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'bookings' 
      AND COLUMN_NAME IN ('cancelled_at', 'cancellation_reason', 'refund_amount', 'refund_status', 'cancellation_policy')
")->fetchAll(PDO::FETCH_COLUMN);

assertCond(in_array('cancelled_at', $checkBookingCols), "Column 'cancelled_at' exists in bookings table");
assertCond(in_array('cancellation_reason', $checkBookingCols), "Column 'cancellation_reason' exists in bookings table");
assertCond(in_array('refund_amount', $checkBookingCols), "Column 'refund_amount' exists in bookings table");
assertCond(in_array('refund_status', $checkBookingCols), "Column 'refund_status' exists in bookings table");
assertCond(in_array('cancellation_policy', $checkBookingCols), "Column 'cancellation_policy' exists in bookings table");

$checkRentalCols = $db->query("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'rental_requests' 
      AND COLUMN_NAME IN ('cancelled_at', 'cancellation_reason', 'refund_amount', 'refund_status', 'cancellation_policy')
")->fetchAll(PDO::FETCH_COLUMN);

assertCond(in_array('cancelled_at', $checkRentalCols), "Column 'cancelled_at' exists in rental_requests table");
assertCond(in_array('cancellation_reason', $checkRentalCols), "Column 'cancellation_reason' exists in rental_requests table");
assertCond(in_array('refund_amount', $checkRentalCols), "Column 'refund_amount' exists in rental_requests table");
assertCond(in_array('refund_status', $checkRentalCols), "Column 'refund_status' exists in rental_requests table");
assertCond(in_array('cancellation_policy', $checkRentalCols), "Column 'cancellation_policy' exists in rental_requests table");

// ---------------------------------------------------------
// Test 2: API Routes Registration
// ---------------------------------------------------------
echo "\n--- Test 2: API Routes Registration ---\n";
$routesContent = file_get_contents(__DIR__ . '/../routes/api.php');
assertCond(strpos($routesContent, "'/api/bookings/{id}/cancel'") !== false, "POST /api/bookings/{id}/cancel route registered");
assertCond(strpos($routesContent, "'/api/rentals/{id}/cancel'") !== false, "POST /api/rentals/{id}/cancel route registered");

// ---------------------------------------------------------
// Test 3: Backend Business Logic - Policy Hours & Deadlines
// ---------------------------------------------------------
echo "\n--- Test 3: Cancellation Policy Hours & Deadlines ---\n";
require_once __DIR__ . '/../app/Controllers/ApiController.php';

$apiCtrl = new ApiController();
$reflection = new ReflectionClass($apiCtrl);
$computeMethod = $reflection->getMethod('computeCancellationPolicy');
$computeMethod->setAccessible(true);

// Hotel Stay: 72 hours before check-in date 14:00
$futureDate = date('Y-m-d', strtotime('+5 days'));
$hotelBooking = [
    'status' => 'confirmed',
    'hotel_status' => 'confirmed',
    'check_in_date' => $futureDate,
    'total_amount' => 5000,
    'deposit_amount' => 1500,
    'paid_amount' => 1500,
];
$hotelPolicy = $computeMethod->invoke($apiCtrl, $hotelBooking, 'hotel');
assertCond($hotelPolicy['policy'] === '72h', "Default hotel cancellation policy is 72h");
assertCond($hotelPolicy['can_cancel'] === true, "Hotel booking 5 days in advance can be cancelled");
assertCond($hotelPolicy['refund_amount'] === 1500.0, "Refund amount matches deposit paid (1,500)");
$expectedDeadline = strtotime($futureDate . ' 14:00:00') - (72 * 3600);
assertCond($hotelPolicy['deadline_timestamp'] === $expectedDeadline, "Hotel cancellation deadline is 72 hours before 14:00 check-in");

// Attraction Tour: 24 hours before tour date/time
$tourBooking = [
    'status' => 'confirmed',
    'booking_date' => $futureDate,
    'booking_time' => '09:00:00',
    'total_amount' => 2400,
];
$tourPolicy = $computeMethod->invoke($apiCtrl, $tourBooking, 'attraction');
assertCond($tourPolicy['policy'] === '24h', "Default tour cancellation policy is 24h");
assertCond($tourPolicy['can_cancel'] === true, "Tour booking 5 days in advance can be cancelled");
$expectedTourDeadline = strtotime($futureDate . ' 09:00:00') - (24 * 3600);
assertCond($tourPolicy['deadline_timestamp'] === $expectedTourDeadline, "Tour cancellation deadline is 24 hours before tour start time");

// Vehicle Rental: 24 hours before pickup date
$rentalBooking = [
    'status' => 'approved',
    'start_date' => $futureDate,
    'total_price' => 3000,
];
$rentalPolicy = $computeMethod->invoke($apiCtrl, $rentalBooking, 'rental');
assertCond($rentalPolicy['policy'] === '24h', "Default rental cancellation policy is 24h");
assertCond($rentalPolicy['can_cancel'] === true, "Rental booking 5 days in advance can be cancelled");

// Custom Policy Override: If hotel has 24h custom policy
$hotelCustomBooking = [
    'status' => 'confirmed',
    'check_in_date' => $futureDate,
    'cancellation_policy' => '24h',
    'total_amount' => 4000,
];
$customPolicy = $computeMethod->invoke($apiCtrl, $hotelCustomBooking, 'hotel');
assertCond($customPolicy['policy'] === '24h', "Custom cancellation policy (24h) overrides default hotel policy");

// ---------------------------------------------------------
// Test 4: Prevention of Cancellation for Completed or Past-Deadline Bookings
// ---------------------------------------------------------
echo "\n--- Test 4: Completed and Past-Deadline Guard Rules ---\n";

// Completed booking
$completedBooking = [
    'status' => 'completed',
    'check_in_date' => $futureDate,
];
$completedPolicy = $computeMethod->invoke($apiCtrl, $completedBooking, 'hotel');
assertCond($completedPolicy['can_cancel'] === false, "Completed booking cannot be cancelled");
assertCond(strpos($completedPolicy['ineligible_reason'], 'completed') !== false, "Reason states booking is completed");

// Past deadline booking (e.g. check-in in 12 hours under 72h policy)
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$urgentBooking = [
    'status' => 'confirmed',
    'check_in_date' => $tomorrow,
];
$urgentPolicy = $computeMethod->invoke($apiCtrl, $urgentBooking, 'hotel');
assertCond($urgentPolicy['can_cancel'] === false, "Booking past 72h deadline cannot be cancelled");
assertCond(strpos($urgentPolicy['ineligible_reason'], 'cancellation deadline for this 72h policy passed') !== false, "Reason states cancellation deadline has passed");

// Already refunded booking
$refundedBooking = [
    'status' => 'refunded',
    'check_in_date' => $futureDate,
];
$refundedPolicy = $computeMethod->invoke($apiCtrl, $refundedBooking, 'hotel');
assertCond($refundedPolicy['can_cancel'] === false, "Refunded booking cannot be cancelled");

// ---------------------------------------------------------
// Test 5: Notification Service Methods
// ---------------------------------------------------------
echo "\n--- Test 5: Notification Service Methods ---\n";
assertCond(method_exists(NotificationService::class, 'bookingCancelled'), "NotificationService::bookingCancelled exists");
assertCond(method_exists(NotificationService::class, 'rentalCancelled'), "NotificationService::rentalCancelled exists");

// ---------------------------------------------------------
// Test 6: Frontend API & Screen Bindings
// ---------------------------------------------------------
echo "\n--- Test 6: Frontend API and Screen Bindings ---\n";
$apiTsContent = file_get_contents(__DIR__ . '/../services/api.ts');
assertCond(strpos($apiTsContent, 'CancellationPolicyMeta') !== false, "CancellationPolicyMeta type exported in api.ts");
assertCond(strpos($apiTsContent, 'cancelBooking') !== false, "cancelBooking exported in api.ts");
assertCond(strpos($apiTsContent, 'cancelRental') !== false, "cancelRental exported in api.ts");

$tripsContent = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');
assertCond(strpos($tripsContent, 'Cancellation & Refund Policy') !== false, "Cancellation policy card displayed in trips.tsx");
assertCond(strpos($tripsContent, 'handleCancelBooking') !== false, "handleCancelBooking handler present in trips.tsx");
assertCond(strpos($tripsContent, 'Cancel Booking') !== false, "Cancel Booking button present in trips.tsx");
assertCond(strpos($tripsContent, 'selectedBookingDetails.cancellation?.can_cancel') !== false, "Cancel button conditionally enabled/styled based on eligibility");

$rentalsContent = file_get_contents(__DIR__ . '/../app/my-rentals.tsx');
assertCond(strpos($rentalsContent, 'Cancellation & Refund Policy') !== false, "Cancellation policy card displayed in my-rentals.tsx");
assertCond(strpos($rentalsContent, 'handleCancelRental') !== false, "handleCancelRental handler present in my-rentals.tsx");
assertCond(strpos($rentalsContent, 'Cancel Reservation') !== false, "Cancel Reservation button present in my-rentals.tsx");
assertCond(strpos($rentalsContent, 'selectedDetailRental.cancellation?.can_cancel') !== false, "Rental cancel button conditionally enabled/styled based on eligibility");

// ---------------------------------------------------------
// Test 7: Deposit vs Full Payment, Checked-In, and Started Bookings
// ---------------------------------------------------------
echo "\n--- Test 7: Deposit Rules, Started, and Checked-In Stays ---\n";
use App\Services\CancellationPolicyService;

// Deposit payment refund rule
$depositStay = [
    'status' => 'confirmed',
    'hotel_status' => 'deposit_paid',
    'payment_stage' => 'deposit_paid',
    'check_in_date' => date('Y-m-d', strtotime('+4 days')),
    'total_amount' => 6000.0,
    'deposit_amount' => 1200.0,
];
$depositPolicy = CancellationPolicyService::compute($depositStay, 'hotel');
assertCond($depositPolicy['can_cancel'] === true, "Deposit-paid hotel booking is eligible for cancellation");
assertCond($depositPolicy['refund_amount'] === 1200.0, "Deposit-paid refund amount is exactly deposit paid (₱1,200)");
assertCond($depositPolicy['refund_type'] === 'deposit_full', "Refund type is 'deposit_full'");
assertCond(strpos($depositPolicy['deposit_rule'], 'remaining balance') !== false, "Deposit rule clearly explains remaining balance is cancelled");

// Full payment refund rule
$fullPaidStay = [
    'status' => 'confirmed',
    'hotel_status' => 'confirmed',
    'payment_stage' => 'settled',
    'payment_status' => 'paid',
    'check_in_date' => date('Y-m-d', strtotime('+4 days')),
    'total_amount' => 4500.0,
    'deposit_amount' => 900.0,
    'paid_amount' => 4500.0,
];
$fullPaidPolicy = CancellationPolicyService::compute($fullPaidStay, 'hotel');
assertCond($fullPaidPolicy['can_cancel'] === true, "Full-paid hotel booking is eligible for cancellation");
assertCond($fullPaidPolicy['refund_amount'] === 4500.0, "Full-paid refund amount is total amount paid (₱4,500)");
assertCond($fullPaidPolicy['refund_type'] === 'full', "Refund type is 'full'");

// Already checked in booking
$checkedInStay = [
    'status' => 'confirmed',
    'hotel_status' => 'checked_in',
    'check_in_date' => date('Y-m-d', strtotime('-1 day')),
    'total_amount' => 3000.0,
];
$checkedInPolicy = CancellationPolicyService::compute($checkedInStay, 'hotel');
assertCond($checkedInPolicy['can_cancel'] === false, "Checked-in guest cannot cancel booking");
assertCond(strpos($checkedInPolicy['ineligible_reason'], 'already checked in') !== false, "Ineligible reason mentions already checked in");

// Already started tour
$startedTour = [
    'status' => 'confirmed',
    'booking_date' => date('Y-m-d'),
    'booking_time' => '06:00:00', // 6 AM today
    'total_amount' => 2000.0,
];
$startedPolicy = CancellationPolicyService::compute($startedTour, 'attraction');
assertCond($startedPolicy['can_cancel'] === false, "Tour that has already started cannot be cancelled");

// Timezone test (Asia/Manila)
assertCond(CancellationPolicyService::TIMEZONE === 'Asia/Manila', "Cancellation service uses Asia/Manila timezone");
$tourTzCheck = [
    'booking_date' => '2026-12-01',
    'booking_time' => '09:00:00',
];
$tzPolicy = CancellationPolicyService::compute($tourTzCheck, 'attraction');
$tzDeadlineObj = new DateTimeImmutable('2026-12-01 09:00:00', new DateTimeZone('Asia/Manila'));
$expectedTzDeadline = $tzDeadlineObj->getTimestamp() - (24 * 3600);
assertCond($tzPolicy['deadline_timestamp'] === $expectedTzDeadline, "Deadline timestamp accurately respects Asia/Manila 24h offset");

// Web bookings view verification
$bookingsViewContent = file_get_contents(__DIR__ . '/../app/Views/bookings/index.php');
assertCond(strpos($bookingsViewContent, 'CancellationPolicyService::compute') !== false, "bookings/index.php computes cancellation policy");
assertCond(strpos($bookingsViewContent, '$cancellation[\'can_cancel\']') !== false, "bookings/index.php shows active cancel button when eligible");
assertCond(strpos($bookingsViewContent, 'POLICY') !== false, "bookings/index.php displays policy badge and deadline");

// ---------------------------------------------------------
// Test 8: 24-Hour Booking Grace Period (Cooling-Off Window)
// ---------------------------------------------------------
echo "\n--- Test 8: Booking Grace Period / Cooling-Off Window ---\n";

// Booking #145 scenario: Hotel check-in is 71.7 hours away (past standard 72h mark), but created 15 minutes ago
$threeDaysFromNow = date('Y-m-d', strtotime('+3 days'));
$recentlyCreatedHotelBooking = [
    'id' => 999,
    'status' => 'confirmed',
    'hotel_status' => 'confirmed',
    'check_in_date' => $threeDaysFromNow,
    'booking_time' => '14:00:00',
    'deposit_amount' => 1120.0,
    'total_amount' => 5600.0,
    'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
];
$recentPolicy = CancellationPolicyService::compute($recentlyCreatedHotelBooking, 'hotel');
assertCond($recentPolicy['can_cancel'] === true, "Recently created booking (<24h old) within 72h window is eligible under grace period");
assertCond(strpos($recentPolicy['policy_label'], 'grace period active') !== false, "Policy label denotes active grace period");
assertCond(strpos($recentPolicy['remaining_text'], 'Grace period') !== false, "Remaining text shows remaining grace period");
assertCond($recentPolicy['refund_amount'] === 1120.0, "Refund amount matches deposit paid during grace period");

// Old booking scenario: check-in is 2 days away, created 3 days ago (grace period expired)
$expiredGraceBooking = [
    'id' => 998,
    'status' => 'confirmed',
    'hotel_status' => 'confirmed',
    'check_in_date' => date('Y-m-d', strtotime('+2 days')),
    'booking_time' => '14:00:00',
    'deposit_amount' => 1000.0,
    'total_amount' => 4000.0,
    'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
];
$expiredGracePolicy = CancellationPolicyService::compute($expiredGraceBooking, 'hotel');
assertCond($expiredGracePolicy['can_cancel'] === false, "Booking created >24h ago past standard deadline cannot be cancelled");
assertCond($expiredGracePolicy['remaining_text'] === 'Deadline passed', "Remaining text shows deadline passed for expired grace booking");

// ---------------------------------------------------------
// Test 9: Web & App Cancelled Hotel Experience Consistency
// ---------------------------------------------------------
echo "\n--- Test 9: Web & App Cancelled Hotel Reservation Consistency ---\n";

// 9.1 Web Hotel Reservation Table in dashboard/hotel.php
$hotelDashContent = file_get_contents(__DIR__ . '/../app/Views/dashboard/hotel.php');
assertCond(strpos($hotelDashContent, "'cancelled', 'refunded' => 'Cancelled'") !== false, "dashboard/hotel.php sets label to 'Cancelled' for cancelled and refunded bookings");
assertCond(strpos($hotelDashContent, "<?php if (\$isCancelled): ?>\n                                                <span class=\"hint\" style=\"font-weight:600;color:var(--ink-200,#6B7280);\">-</span>") !== false, "dashboard/hotel.php displays '-' for balance due when cancelled");

// 9.2 Web Tourist My Bookings in bookings/index.php
$bookingsViewContent = file_get_contents(__DIR__ . '/../app/Views/bookings/index.php');
assertCond(strpos($bookingsViewContent, "Due: -") !== false, "bookings/index.php displays 'Due: -' when cancelled");
assertCond(strpos($bookingsViewContent, "'cancelled', 'refunded' => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'border' => '#FCA5A5', 'label' => 'Cancelled']") !== false, "bookings/index.php maps refunded/cancelled status to Cancelled badge");

// 9.3 App Hotel Card in trips.tsx
$tripsContent = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');
assertCond(strpos($tripsContent, "isCancelled ? 'Refunded'") !== false, "trips.tsx hotel card sets deposit paid to 'Refunded' when cancelled");
assertCond(strpos($tripsContent, "isCancelled ? '-'") !== false, "trips.tsx hotel card sets remaining due to '-' when cancelled");
assertCond(strpos($tripsContent, "Booking was canceled.") !== false, "trips.tsx replaces pay remaining balance with 'Booking was canceled.'");

// 9.4 App Details & Receipt Modal in trips.tsx
assertCond(strpos($tripsContent, "isDetailsCancelled ? 'Refunded'") !== false, "trips.tsx itemized receipt badge displays 'Refunded' when cancelled");
assertCond(strpos($tripsContent, "{isDetailsCancelled ? '-' : `✓ \${formatPrice(selectedBookingDetails.deposit_amount)}`}") !== false, "trips.tsx itemized receipt sets deposit paid to '-' when cancelled");
assertCond(strpos($tripsContent, "isDetailsCancelled\n                            ? '-'") !== false || strpos($tripsContent, "isDetailsCancelled ? '-'") !== false, "trips.tsx itemized receipt sets remaining balance to '-' when cancelled");
assertCond(strpos($tripsContent, "Refund Status") !== false && strpos($tripsContent, "flexShrink: 0") !== false, "trips.tsx prevents refund status label collision with flexShrink");

// ---------------------------------------------------------
// Summary
// ---------------------------------------------------------
echo "\n========================================================\n";
echo " Test Summary: $passed Passed, $failed Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
