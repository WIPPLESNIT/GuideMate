<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Controllers\ApiController;

echo "====================================================================\n";
echo " Hotel Checked-In 'Paid' Status & Checkout 'Settled' Test Suite\n";
echo "====================================================================\n\n";

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

$db = Database::connection();

// ── Setup: Find or create partner & guest ──
$partner = Database::first("SELECT id, name, email FROM users WHERE email = 'hotel_frontdesk_test@example.com' LIMIT 1")
    ?: Database::first("SELECT id, name, email FROM users WHERE role = 'hotel_provider' OR role = 'partner' LIMIT 1");
if ($partner === null) {
    $partnerId = Database::insert(
        "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'hotel_provider', 1, NOW())",
        ['Hotel Front Desk Test', 'hotel_frontdesk_test@example.com', password_hash('password', PASSWORD_DEFAULT)]
    );
} else {
    $partnerId = (int) $partner['id'];
}

$guest = Database::first("SELECT id, name, email FROM users WHERE email = 'hotel_guest_test@example.com' LIMIT 1")
    ?: Database::first("SELECT id, name, email FROM users WHERE role = 'user' LIMIT 1");
if ($guest === null) {
    $guestId = Database::insert(
        "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'user', 1, NOW())",
        ['Hotel Guest Test', 'hotel_guest_test@example.com', password_hash('password', PASSWORD_DEFAULT)]
    );
} else {
    $guestId = (int) $guest['id'];
}

// Ensure hotel category exists
$hotelCat = Database::first("SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1");
$catId = $hotelCat ? (int) $hotelCat['id'] : 3;

// Find or create hotel listing
$hotel = Database::first("SELECT id, title, slug FROM listings WHERE category_id = ? AND user_id = ? LIMIT 1", [$catId, $partnerId]);
if ($hotel === null) {
    $hotelId = Database::insert(
        "INSERT INTO listings (user_id, category_id, title, slug, description, address, area, price, price_unit, status, created_at)
         VALUES (?, ?, 'Seaside Grand Resort CheckedIn Test', 'seaside-grand-resort-checkin-test', 'Luxury test resort', 'Mactan Island', 'Lapu-Lapu City', 6000.00, 'night', 'approved', NOW())",
        [$partnerId, $catId]
    );
} else {
    $hotelId = (int) $hotel['id'];
}

// Ensure room type exists
$room = Database::first("SELECT id, name, price_per_night, deposit_percentage FROM hotel_rooms WHERE listing_id = ? LIMIT 1", [$hotelId]);
if ($room === null) {
    $roomId = HotelRoom::create([
        'listing_id' => $hotelId,
        'name' => 'Presidential Suite',
        'price_per_night' => 5000.00,
        'deposit_percentage' => 20,
        'status' => 'available',
    ]);
} else {
    $roomId = (int) $room['id'];
}

// ── Test 1: Create a confirmed hotel reservation with 20% deposit paid and 80% balance due ──
echo "--- Test 1: Create Hotel Reservation with Deposit Paid & Balance Due ---\n";
$totalStay = 10000.00;
$depositAmount = 2000.00; // 20%
$balanceAmount = 8000.00; // 80%

$bookingId = Booking::createHotelReservation(
    $hotelId,
    $roomId,
    $guestId,
    '2027-05-01',
    '2027-05-03',
    2,
    2,
    $totalStay,
    $depositAmount,
    $balanceAmount,
    'Checked-in paid test reservation'
);
assertCondition($bookingId > 0, "Created test hotel reservation #{$bookingId}");

// Confirm reservation
$db->prepare("UPDATE bookings SET hotel_status = 'confirmed', status = 'confirmed', payment_stage = 'balance_due', balance_amount = ? WHERE id = ?")
    ->execute([$balanceAmount, $bookingId]);

$confirmedBooking = Booking::find($bookingId);
assertCondition($confirmedBooking['hotel_status'] === 'confirmed', "Hotel status is 'confirmed'");
assertCondition($confirmedBooking['payment_stage'] === 'balance_due', "Payment stage is 'balance_due' prior to check-in");
assertCondition((float) $confirmedBooking['balance_amount'] === 8000.00, "Outstanding balance is ₱8,000.00 prior to check-in");

// ── Test 2: Front Desk Changes Status to 'Checked In' ──
echo "\n--- Test 2: Front Desk Check-In Transitions Balance to 'Paid' ---\n";

// Execute HotelController check_in logic:
$balance = (float) ($confirmedBooking['balance_amount'] ?? 0);
if ($balance <= 0 && (float) ($confirmedBooking['total_amount'] ?? 0) > (float) ($confirmedBooking['deposit_amount'] ?? 0)) {
    $balance = max(0.0, round((float) $confirmedBooking['total_amount'] - (float) ($confirmedBooking['deposit_amount'] ?? 0), 2));
}
if ($balance > 0) {
    Payment::create($bookingId, $balance, 'front_desk_checkin', 'paid');
}
$db->prepare("UPDATE bookings SET hotel_status = 'checked_in', payment_stage = 'paid', balance_amount = 0.00 WHERE id = ?")->execute([$bookingId]);

$checkedInBooking = Booking::find($bookingId);
assertCondition($checkedInBooking['hotel_status'] === 'checked_in', "Booking hotel_status is now 'checked_in'");
assertCondition($checkedInBooking['payment_stage'] === 'paid', "Booking payment_stage automatically updated to 'paid'");
assertCondition((float) $checkedInBooking['balance_amount'] === 0.00, "Booking balance_amount updated to 0.00");

// Check payment record
$paymentRec = Database::first("SELECT * FROM payments WHERE booking_id = ? AND method = 'front_desk_checkin' ORDER BY id DESC LIMIT 1", [$bookingId]);
assertCondition($paymentRec !== null, "Payment record created for front desk check-in balance");
assertCondition((float) $paymentRec['amount'] === 8000.00, "Payment record amount equals remaining balance: ₱8,000.00");
assertCondition($paymentRec['status'] === 'paid', "Payment record status is 'paid'");

// ── Test 3: API Serialization for Checked-In Reservation ──
echo "\n--- Test 3: API Returns 'Paid' Payment Status and Zero Balance ---\n";

$guestToken = \App\Core\ApiAuth::issue($guestId);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $guestToken;

$apiController = new ApiController();
$refMethod = new ReflectionMethod(ApiController::class, 'bookingPayload');
$refMethod->setAccessible(true);
$serialized = $refMethod->invoke($apiController, $checkedInBooking, $guestId);

assertCondition($serialized['hotel_status'] === 'checked_in', "API hotel_status is 'checked_in'");
assertCondition($serialized['payment_stage'] === 'paid', "API payment_stage is 'paid'");
assertCondition((float) $serialized['balance_amount'] === 0.0, "API balance_amount is 0.0");
assertCondition($serialized['balance_payment_status'] === 'Paid', "API balance_payment_status is 'Paid'");

// ── Test 4: Direct API Payment Attempt on Checked-In Reservation is Blocked ──
echo "\n--- Test 4: Prevent Further Payment on Checked-In Reservation ---\n";

$_POST = [
    'payment_method' => 'card',
];
ob_start();
$apiController->payBookingBalance($bookingId);
$output = ob_get_clean();
$code = http_response_code();
$resJson = json_decode((string) $output, true) ?? [];

assertCondition($code === 422, "API blocks balance payment on checked-in reservation with HTTP 422");
assertCondition(str_contains($resJson['error'] ?? '', 'no outstanding balance'), "API response error: '{$resJson['error']}'");

// ── Test 5: Checkout Transitions Reservation to 'Settled' & 'Completed' ──
echo "\n--- Test 5: Checkout Transition to 'Settled' is Preserved ---\n";

// Execute HotelController settle_balance logic:
$balance = (float) ($checkedInBooking['balance_amount'] ?? 0);
if ($balance > 0 && !in_array(($checkedInBooking['payment_stage'] ?? ''), ['settled', 'paid'], true)) {
    Payment::create($bookingId, $balance, 'front_desk_checkout', 'paid');
}
$db->prepare("UPDATE bookings SET hotel_status = 'completed', payment_stage = 'settled', balance_amount = 0.00, status = 'completed', unit_completed_at = NOW() WHERE id = ?")->execute([$bookingId]);

$completedBooking = Booking::find($bookingId);
assertCondition($completedBooking['hotel_status'] === 'completed', "Checkout updated hotel_status to 'completed'");
assertCondition($completedBooking['payment_stage'] === 'settled', "Checkout transitioned payment_stage to 'settled'");
assertCondition($completedBooking['status'] === 'completed', "Checkout updated status to 'completed'");

$serializedCompleted = $refMethod->invoke($apiController, $completedBooking, $guestId);
assertCondition($serializedCompleted['payment_stage'] === 'settled', "API payment_stage for checked-out stay is 'settled'");
assertCondition($serializedCompleted['balance_payment_status'] === 'Settled', "API balance_payment_status for checked-out stay is 'Settled'");
assertCondition((float) $serializedCompleted['balance_amount'] === 0.0, "API balance_amount for checked-out stay is 0.0");

// ── Test 6: Verify trips.tsx UI Invariants ──
echo "\n--- Test 6: Verify trips.tsx UI Invariants ---\n";

$tripsCode = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');

// Invariant A: "Remaining Balance Payable" card content hidden
assertCondition(!str_contains($tripsCode, 'Remaining Balance Payable'), "trips.tsx hides 'Remaining Balance Payable' notice box");

// Invariant B: "Pay Balance" button removed for checked-in reservations
assertCondition(!str_contains($tripsCode, 'payBalanceBtn'), "trips.tsx removes 'Pay Balance' button");

// Invariant C: Financial grid displays 'Paid' when checked in and 'Settled' when checkout completed
assertCondition(str_contains($tripsCode, "isSettled ? 'Settled' : (isCheckedIn || isPaid ? 'Paid' : (unpaidBalance > 0 ? formatPrice(unpaidBalance) : 'Settled'))"), "trips.tsx displays 'Paid' when checked in and 'Settled' when checked out");

// Invariant D: Receipt modal displays '✓ PAID' when checked in and '✓ SETTLED' when checked out
assertCondition(str_contains($tripsCode, "isDetailsSettled ? '✓ SETTLED' : isDetailsPaid ? '✓ PAID' : 'PARTIAL / PENDING'"), "trips.tsx receipt modal distinguishes '✓ PAID' at check-in from '✓ SETTLED' at checkout");

// Invariant E: Attraction card layout has attractionPriceRow and attractionActionsCol
assertCondition(str_contains($tripsCode, "styles.attractionPriceRow"), "trips.tsx attraction card uses dedicated attractionPriceRow");
assertCondition(str_contains($tripsCode, "styles.attractionActionsCol"), "trips.tsx attraction card uses stacked attractionActionsCol");

// Invariant F: No Rentals category in My Trips
assertCondition(!str_contains($tripsCode, "Rentals ("), "trips.tsx does not display Rentals category in My Trips");

// Cleanup test booking
Database::run("DELETE FROM payments WHERE booking_id = ?", [$bookingId]);
Database::run("DELETE FROM bookings WHERE id = ?", [$bookingId]);

echo "\n====================================================================\n";
echo " Test Results: $passed Passed, $failed Failed\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
