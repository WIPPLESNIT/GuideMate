<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "========================================================\n";
echo " GuideMate Active vs History Separation Test Suite\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
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
// Test 1: Date-Based and Status-Based Completion Logic
// ---------------------------------------------------------
echo "--- Test 1: Date-Based & Status-Based Completion Definition ---\n";

$today = date('Y-m-d');
$pastDate = date('Y-m-d', strtotime('-5 days'));
$futureDate = date('Y-m-d', strtotime('+5 days'));

// 1. Hotel checkout date passed
$hotelPastCheckout = [
    'id' => 101,
    'kind' => 'hotel',
    'hotel_status' => 'confirmed',
    'status' => 'confirmed',
    'check_in_date' => date('Y-m-d', strtotime('-7 days')),
    'check_out_date' => $pastDate,
];

// 2. Hotel upcoming checkout
$hotelUpcomingCheckout = [
    'id' => 102,
    'kind' => 'hotel',
    'hotel_status' => 'checked_in',
    'status' => 'confirmed',
    'check_in_date' => date('Y-m-d', strtotime('-1 days')),
    'check_out_date' => $futureDate,
];

// 3. Attraction tour date passed
$attractionPastVisit = [
    'id' => 201,
    'kind' => 'attraction',
    'status' => 'confirmed',
    'booking_date' => $pastDate,
];

// 4. Attraction tour upcoming
$attractionUpcomingVisit = [
    'id' => 202,
    'kind' => 'attraction',
    'status' => 'confirmed',
    'booking_date' => $futureDate,
];

// 5. Rental return date passed
$rentalPastReturn = [
    'id' => 301,
    'kind' => 'rental',
    'status' => 'active',
    'pickup_date' => date('Y-m-d', strtotime('-8 days')),
    'rental_days' => 3, // return date = -5 days
];

// 6. Rental active / in duration
$rentalUpcomingReturn = [
    'id' => 302,
    'kind' => 'rental',
    'status' => 'active',
    'pickup_date' => date('Y-m-d', strtotime('-1 days')),
    'rental_days' => 5, // return date = +4 days
];

function isItemCompleted(array $item, string $today): bool {
    $hotelStatus = (string) ($item['hotel_status'] ?? '');
    $status = (string) ($item['status'] ?? '');

    if ($hotelStatus === 'completed' || $status === 'completed' || $status === 'cancelled' || $status === 'refunded') {
        return true;
    }

    $kind = $item['kind'] ?? 'hotel';
    if ($kind === 'hotel') {
        $checkout = (string) ($item['check_out_date'] ?? $item['booking_date'] ?? '');
        return ($checkout !== '' && $checkout < $today);
    } elseif ($kind === 'attraction') {
        $visit = (string) ($item['booking_date'] ?? '');
        return ($visit !== '' && $visit < $today);
    } elseif ($kind === 'rental') {
        $pickup = (string) ($item['pickup_date'] ?? '');
        $days = (int) ($item['rental_days'] ?? 1);
        $returnDate = date('Y-m-d', strtotime($pickup . " +{$days} days"));
        return ($returnDate < $today);
    }
    return false;
}

assertCondition(isItemCompleted($hotelPastCheckout, $today) === true, 'Hotel with past checkout date is completed');
assertCondition(isItemCompleted($hotelUpcomingCheckout, $today) === false, 'Hotel with future checkout date is active');

assertCondition(isItemCompleted($attractionPastVisit, $today) === true, 'Attraction with past visit date is completed');
assertCondition(isItemCompleted($attractionUpcomingVisit, $today) === false, 'Attraction with future visit date is active');

assertCondition(isItemCompleted($rentalPastReturn, $today) === true, 'Rental with past return date is completed');
assertCondition(isItemCompleted($rentalUpcomingReturn, $today) === false, 'Rental with future return date is active');


// ---------------------------------------------------------
// Test 2: Verify app/(tabs)/trips.tsx implementation
// ---------------------------------------------------------
echo "\n--- Test 2: Mobile App Trips Screen (app/(tabs)/trips.tsx) ---\n";
$tripsContent = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');

assertCondition(strpos($tripsContent, "viewMode === 'active'") !== false, 'Implements viewMode switching between active and history');
assertCondition(strpos($tripsContent, "historyIconBtn") !== false, 'Implements dedicated history button icon in header');
assertCondition(strpos($tripsContent, "historyBadge") !== false, 'Implements badge counter on history icon');
assertCondition(strpos($tripsContent, "modeSegmentWrap") !== false, 'Implements Active vs History segmented option controls');
assertCondition(strpos($tripsContent, "Hotel Stays") !== false, 'Distinct category tab for Hotel Stays');
assertCondition(strpos($tripsContent, "Attractions & Tours") !== false, 'Distinct category tab for Attractions & Tours');
assertCondition(strpos($tripsContent, "selectedBookingDetails") !== false, 'Supports opening completed item for details');
assertCondition(strpos($tripsContent, "Booking Details & Receipt Modal") !== false, 'Features full Booking Details & Receipt Modal');
assertCondition(strpos($tripsContent, "Itemized Receipt") !== false, 'Displays itemized receipt with total and settled amounts');


// ---------------------------------------------------------
// Test 3: Verify app/my-rentals.tsx implementation
// ---------------------------------------------------------
echo "\n--- Test 3: Mobile App Rentals Screen (app/my-rentals.tsx) ---\n";
$rentalsContent = file_get_contents(__DIR__ . '/../app/my-rentals.tsx');

assertCondition(strpos($rentalsContent, "viewMode === 'active'") !== false, 'Implements viewMode switching between active and history rentals');
assertCondition(strpos($rentalsContent, "isRentalCompleted") !== false, 'Uses robust isRentalCompleted with return date check');
assertCondition(strpos($rentalsContent, "historyIconBtn") !== false, 'Implements dedicated history button icon with badge in header');
assertCondition(strpos($rentalsContent, "segmentWrap") !== false, 'Implements Active vs History segmented option tabs');
assertCondition(strpos($rentalsContent, "selectedDetailRental") !== false, 'Supports opening completed rental for details');
assertCondition(strpos($rentalsContent, "Rental Details & Receipt Modal") !== false, 'Features full Rental Details & Receipt Modal');
assertCondition(strpos($rentalsContent, "Payment &amp; Receipt") !== false || strpos($rentalsContent, "Payment & Receipt") !== false, 'Displays payment and receipt breakdown for vehicle rentals');


// ---------------------------------------------------------
// Test 4: Verify services/api.ts completion definitions
// ---------------------------------------------------------
echo "\n--- Test 4: Shared API Completion Definition (services/api.ts) ---\n";
$apiTsContent = file_get_contents(__DIR__ . '/../services/api.ts');

assertCondition(strpos($apiTsContent, "export function isBookingCompleted") !== false, 'Exports central isBookingCompleted function');
assertCondition(strpos($apiTsContent, "export function isRentalCompleted") !== false, 'Exports central isRentalCompleted function');
assertCondition(strpos($apiTsContent, "export function getRentalReturnDate") !== false, 'Exports getRentalReturnDate function');


// ---------------------------------------------------------
// Test 5: Verify app/Views/bookings/index.php web implementation
// ---------------------------------------------------------
echo "\n--- Test 5: Web Bookings Portal (app/Views/bookings/index.php) ---\n";
$webBookingsContent = file_get_contents(__DIR__ . '/../app/Views/bookings/index.php');

assertCondition(strpos($webBookingsContent, '$activeBookings') !== false, 'Partitions bookings into activeBookings array');
assertCondition(strpos($webBookingsContent, '$historyBookings') !== false, 'Partitions bookings into historyBookings array');
assertCondition(strpos($webBookingsContent, '$checkoutDate < $today') !== false, 'Applies hotel checkout date passed check');
assertCondition(strpos($webBookingsContent, '$bookingDate < $today') !== false, 'Applies attraction visit date passed check');
assertCondition(strpos($webBookingsContent, '?tab=history') !== false, 'Provides History tab link/option');
assertCondition(strpos($webBookingsContent, '?tab=active') !== false, 'Provides Active tab link/option');
assertCondition(strpos($webBookingsContent, '&cat=hotels') !== false, 'Provides Hotels category filter on web');
assertCondition(strpos($webBookingsContent, '&cat=attractions') !== false, 'Provides Attractions category filter on web');


echo "\n========================================================\n";
echo " Results: $passed passed, $failed failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
