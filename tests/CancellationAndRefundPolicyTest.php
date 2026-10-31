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
// Summary
// ---------------------------------------------------------
echo "\n========================================================\n";
echo " Test Summary: $passed Passed, $failed Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
