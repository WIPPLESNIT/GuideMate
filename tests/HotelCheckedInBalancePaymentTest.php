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
echo " Hotel Checked-In Reservation Balance Payment Test Suite\n";
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

// ── Setup: Find or create a test hotel partner and guest ──
$db = Database::connection();

// Ensure test partner exists
$partner = Database::first("SELECT id, name, email FROM users WHERE email = 'partner_hotel_test@example.com' LIMIT 1")
    ?: Database::first("SELECT id, name, email FROM users WHERE role = 'partner' LIMIT 1");
if ($partner === null) {
    $partnerId = Database::insert(
        "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'partner', 1, NOW())",
        ['Hotel Partner Test', 'partner_hotel_test@example.com', password_hash('password', PASSWORD_DEFAULT)]
    );
} else {
    $partnerId = (int) $partner['id'];
}

// Ensure test guest exists
$guest = Database::first("SELECT id, name, email FROM users WHERE email = 'guest_hotel_test@example.com' LIMIT 1")
    ?: Database::first("SELECT id, name, email FROM users WHERE role = 'user' LIMIT 1");
if ($guest === null) {
    $guestId = Database::insert(
        "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'user', 1, NOW())",
        ['Guest Test', 'guest_hotel_test@example.com', password_hash('password', PASSWORD_DEFAULT)]
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
         VALUES (?, ?, 'Seaside Grand Resort', 'seaside-grand-resort-test', 'Luxury test resort', 'Mactan Island', 'Lapu-Lapu City', 5000.00, 'night', 'approved', NOW())",
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
        'name' => 'Deluxe Ocean View',
        'price_per_night' => 4000.00,
        'deposit_percentage' => 20,
        'status' => 'available',
    ]);
} else {
    $roomId = (int) $room['id'];
}

// ── Test 1: Create a hotel reservation with deposit & unpaid balance ──
echo "--- Test 1: Create Hotel Reservation with Deposit & Outstanding Balance ---\n";
$totalStay = 8000.00;
$depositAmount = 1600.00; // 20%
$balanceAmount = 6400.00; // 80%

$bookingId = Booking::createHotelReservation(
    $hotelId,
    $roomId,
    $guestId,
    '2027-04-10',
    '2027-04-12',
    2,
    2,
    $totalStay,
    $depositAmount,
    $balanceAmount,
    'Test reservation for checked-in balance workflow'
);
assertCondition($bookingId > 0, "Created test hotel reservation #{$bookingId}");

$booking = Booking::find($bookingId);
assertCondition((float) $booking['balance_amount'] === 6400.00, "Stored initial balance amount: ₱6,400.00");
assertCondition($booking['payment_stage'] === 'deposit_paid', "Initial payment stage is 'deposit_paid'");
assertCondition($booking['hotel_status'] === 'pending', "Initial hotel status is 'pending'");

// ── Test 2: Hotel Staff Confirms Reservation and Checks In Guest ──
echo "\n--- Test 2: Front Desk Changes Status to 'Checked In' ---\n";

// HotelController check_in logic simulation:
// Front desk sets status to checked_in, transitions payment_stage to balance_due
$balance = (float) ($booking['balance_amount'] ?? 0);
if ($balance <= 0 && (float) ($booking['total_amount'] ?? 0) > (float) ($booking['deposit_amount'] ?? 0)) {
    $balance = max(0.0, round((float) $booking['total_amount'] - (float) ($booking['deposit_amount'] ?? 0), 2));
}

$db->prepare("UPDATE bookings SET hotel_status = 'checked_in', payment_stage = 'balance_due', balance_amount = ? WHERE id = ?")->execute([$balance, $bookingId]);

$checkedInBooking = Booking::find($bookingId);
assertCondition($checkedInBooking['hotel_status'] === 'checked_in', "Reservation status updated to 'checked_in'");
assertCondition($checkedInBooking['payment_stage'] === 'balance_due', "Reservation payment stage set to 'balance_due'");
assertCondition((float) $checkedInBooking['balance_amount'] === 6400.00, "Remaining balance ₱6,400.00 is active and payable");

// ── Test 3: API Balance Payment Endpoint Validation ──
echo "\n--- Test 3: API Endpoint Balance Payment Validation ---\n";

function invokeApiPayBalance(int $bookingId, array $body = []): array {
    $_POST = $body;
    $controller = new ApiController();
    ob_start();
    $controller->payBookingBalance($bookingId);
    $output = ob_get_clean();
    $code = http_response_code();
    $json = json_decode((string) $output, true) ?? [];
    return ['status' => $code, 'data' => $json, 'raw' => $output];
}

// 3a. Unauthorized user (no token)
unset($_SERVER['HTTP_AUTHORIZATION']);
$res = invokeApiPayBalance($bookingId);
assertCondition($res['status'] === 401, "Endpoint rejects unauthenticated user with 401");

// 3b. Authenticated as wrong user (different user)
$otherUser = Database::first("SELECT id FROM users WHERE id != ? AND is_active = 1 LIMIT 1", [$guestId]);
$otherToken = \App\Core\ApiAuth::issue((int) $otherUser['id']);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $otherToken;
$res = invokeApiPayBalance($bookingId);
assertCondition($res['status'] === 404, "Endpoint rejects unauthorized booking owner with 404");

// 3c. Valid user but booking not checked in (temporarily set to pending)
$guestToken = \App\Core\ApiAuth::issue($guestId);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $guestToken;
$db->prepare("UPDATE bookings SET hotel_status = 'confirmed' WHERE id = ?")->execute([$bookingId]);
$res = invokeApiPayBalance($bookingId);
assertCondition($res['status'] === 422, "Endpoint rejects payment if guest is not yet checked in (status: confirmed)");
assertCondition(str_contains($res['data']['error'] ?? '', 'checked in'), "Error explains balance payment is available once checked in");

// Reset back to checked_in
$db->prepare("UPDATE bookings SET hotel_status = 'checked_in' WHERE id = ?")->execute([$bookingId]);

// 3d. Missing or short reference for GCash payment
$res = invokeApiPayBalance($bookingId, [
    'payment_method' => 'gcash',
    'payment_reference' => '12', // too short (< 4)
]);
assertCondition($res['status'] === 422, "Rejects GCash payment with reference < 4 characters");

// ── Test 4: Successful Balance Payment via GCash ──
echo "\n--- Test 4: Successful Balance Payment via Mobile App ---\n";
$gcashRef = 'GCASH-REF-' . strtoupper(bin2hex(random_bytes(4)));
$res = invokeApiPayBalance($bookingId, [
    'payment_method' => 'gcash',
    'payment_reference' => $gcashRef,
]);

assertCondition($res['status'] === 200, "Balance payment succeeded with HTTP 200");
assertCondition(($res['data']['success'] ?? false) === true, "Response reports success = true");
assertCondition((float) ($res['data']['amount_paid'] ?? 0) === 6400.00, "Amount paid matches remaining balance: ₱6,400.00");

// Verify booking state in database
$settledBooking = Booking::find($bookingId);
assertCondition($settledBooking['payment_stage'] === 'settled', "Booking payment_stage is now 'settled'");
assertCondition((float) $settledBooking['balance_amount'] === 0.00, "Booking balance_amount is now 0.00");
assertCondition($settledBooking['hotel_status'] === 'checked_in', "Booking remains 'checked_in' while guest stay is ongoing");

// Verify payments table record
$paymentRecord = Database::first("SELECT * FROM payments WHERE booking_id = ? AND method = 'gcash' ORDER BY id DESC LIMIT 1", [$bookingId]);
assertCondition($paymentRecord !== null, "Payment record created in payments table");
assertCondition((float) $paymentRecord['amount'] === 6400.00, "Payment record amount is ₱6,400.00");
assertCondition($paymentRecord['status'] === 'paid', "Payment record status is 'paid'");
assertCondition($paymentRecord['reference'] === $gcashRef, "Payment record reference matches user reference: {$gcashRef}");

// ── Test 5: Re-paying Settled Balance is Prevented ──
echo "\n--- Test 5: Prevention of Double Balance Payment ---\n";
$res = invokeApiPayBalance($bookingId, [
    'payment_method' => 'card',
]);
assertCondition($res['status'] === 422, "Prevents duplicate balance payment with 422");
assertCondition(str_contains($res['data']['error'] ?? '', 'no outstanding balance'), "Error correctly indicates no balance due");

// ── Test 6: Front Desk Settle & Check Out ──
echo "\n--- Test 6: Front Desk Checkout of Settled Reservation ---\n";
// When front desk performs settle_balance / checkout
$balance = (float) ($settledBooking['balance_amount'] ?? 0);
$initialPaymentCount = (int) Database::first("SELECT COUNT(*) as c FROM payments WHERE booking_id = ?", [$bookingId])['c'];

if ($balance > 0 && ($settledBooking['payment_stage'] ?? '') !== 'settled') {
    Payment::create($bookingId, $balance, 'front_desk_cash', 'paid');
}
$db->prepare("UPDATE bookings SET hotel_status = 'completed', payment_stage = 'settled', balance_amount = 0.00, status = 'completed', unit_completed_at = NOW() WHERE id = ?")->execute([$bookingId]);

$completedBooking = Booking::find($bookingId);
$newPaymentCount = (int) Database::first("SELECT COUNT(*) as c FROM payments WHERE booking_id = ?", [$bookingId])['c'];

assertCondition($completedBooking['hotel_status'] === 'completed', "Front desk successfully completed check-out");
assertCondition($completedBooking['payment_stage'] === 'settled', "Booking remains settled");
assertCondition($newPaymentCount === $initialPaymentCount, "No duplicate cash payment created because balance was already settled via mobile app");

// Cleanup test booking
Database::run("DELETE FROM payments WHERE booking_id = ?", [$bookingId]);
Database::run("DELETE FROM bookings WHERE id = ?", [$bookingId]);

echo "\n====================================================================\n";
echo " Test Results: $passed Passed, $failed Failed\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
