<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;

echo "========================================================\n";
echo " Hotel Room Inventory, Availability & Deposit Test Suite\n";
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

// ── Test 1: Hotel Listing and Room Types Retrieval ──
echo "--- Test 1: Hotel Listing & Seeded Room Types ---\n";
$hotel = Database::first("SELECT id, title, slug, category_id FROM listings WHERE category_id = 3 LIMIT 1");
assertCondition($hotel !== null, "Hotel listing exists in category_id = 3");

$listingId = (int) $hotel['id'];
$rooms = HotelRoom::forListing($listingId);
assertCondition(count($rooms) >= 1, "Hotel has configured room types (found " . count($rooms) . " rooms)");

$firstRoom = $rooms[0];
assertCondition(!empty($firstRoom['name']), "Room has name: '{$firstRoom['name']}'");
assertCondition((float) $firstRoom['price_per_night'] > 0, "Room has price per night: ₱" . number_format((float) $firstRoom['price_per_night'], 2));
assertCondition((int) ($firstRoom['deposit_percentage'] ?? 0) >= 5, "Room has deposit percentage: " . (int) $firstRoom['deposit_percentage'] . "%");

// ── Test 2: Listing::isBookable ──
echo "\n--- Test 2: Bookable Listing Policy ---\n";
$hotelListing = ['category_slug' => 'hotels'];
$guideListing = ['category_slug' => 'tour-guides'];
$thingsListing = ['category_slug' => 'things-to-do'];

assertCondition(Listing::isBookable($hotelListing) === true, "Hotels are bookable via online deposit");
assertCondition(Listing::isBookable($guideListing) === true, "Tour guides remain bookable");
assertCondition(Listing::isBookable($thingsListing) === false, "Things-to-do remain non-bookable / discovery");

// ── Test 3: Manual Room Availability Controls ──
echo "\n--- Test 3: Hotel Partner Manual Availability Controls ---\n";
$testRoomId = (int) $firstRoom['id'];
$origStatus = (string) $firstRoom['status'];

// Toggle to blocked
HotelRoom::updateStatus($testRoomId, HotelRoom::STATUS_BLOCKED);
$check = HotelRoom::find($testRoomId);
assertCondition($check['status'] === HotelRoom::STATUS_BLOCKED, "Room manually marked as Blocked");
assertCondition(HotelRoom::isAvailableForDates($testRoomId, '2027-01-10', '2027-01-12') === false, "Blocked room rejected by availability checker");

// Toggle to fully_booked
HotelRoom::updateStatus($testRoomId, HotelRoom::STATUS_FULLY_BOOKED);
$check = HotelRoom::find($testRoomId);
assertCondition($check['status'] === HotelRoom::STATUS_FULLY_BOOKED, "Room manually marked as Fully Booked");
assertCondition(HotelRoom::isAvailableForDates($testRoomId, '2027-01-10', '2027-01-12') === false, "Fully booked room rejected by availability checker");

// Toggle to unavailable
HotelRoom::updateStatus($testRoomId, HotelRoom::STATUS_UNAVAILABLE);
$check = HotelRoom::find($testRoomId);
assertCondition($check['status'] === HotelRoom::STATUS_UNAVAILABLE, "Room manually marked as Unavailable");
assertCondition(HotelRoom::isAvailableForDates($testRoomId, '2027-01-10', '2027-01-12') === false, "Unavailable room rejected by availability checker");

// Restore to available
HotelRoom::updateStatus($testRoomId, HotelRoom::STATUS_AVAILABLE);
$check = HotelRoom::find($testRoomId);
assertCondition($check['status'] === HotelRoom::STATUS_AVAILABLE, "Room restored to Available");
assertCondition(HotelRoom::isAvailableForDates($testRoomId, '2027-01-10', '2027-01-12') === true, "Available room accepted by availability checker");

// ── Test 4: Partial Deposit & Balance Calculation ──
echo "\n--- Test 4: Partial Deposit & Financial Breakdown ---\n";
$rate = 3500.00;
$nights = 3;
$depositPct = 20;
$breakdown = HotelRoom::calculateBreakdown($rate, $nights, $depositPct);

$expectedTotal = 10500.00;
$expectedDeposit = 2100.00;
$expectedBalance = 8400.00;

assertCondition($breakdown['total_amount'] === $expectedTotal, "Total stay accurately calculated (3 × ₱3,500 = ₱10,500)");
assertCondition($breakdown['deposit_amount'] === $expectedDeposit, "Online deposit accurately calculated (20% = ₱2,100)");
assertCondition($breakdown['balance_amount'] === $expectedBalance, "Front desk balance accurately calculated (80% = ₱8,400)");

// ── Test 5: Hotel Reservation Lifecycle & Overlap Prevention ──
echo "\n--- Test 5: Hotel Reservation Creation & Overlap Gating ---\n";
// Create a test user for booking
$tourist = Database::first("SELECT id, name, email FROM users WHERE role = 'tourist' LIMIT 1");
if ($tourist === null) {
    $touristId = Database::insert(
        "INSERT INTO users (name, email, password, role) VALUES ('Test Guest', 'guest@test.local', 'secret', 'tourist')"
    );
} else {
    $touristId = (int) $tourist['id'];
}

$checkIn = '2027-05-10';
$checkOut = '2027-05-13'; // 3 nights
$nights = 3;

$bookingId = Booking::createHotelReservation(
    $listingId,
    $testRoomId,
    $touristId,
    $checkIn,
    $checkOut,
    $nights,
    2,
    $expectedTotal,
    $expectedDeposit,
    $expectedBalance,
    "Test hotel reservation"
);
assertCondition($bookingId > 0, "Hotel reservation created in database (#$bookingId)");

// Retrieve booking
$booking = Booking::find($bookingId);
assertCondition((int) $booking['room_id'] === $testRoomId, "Reservation stored correct room_id");
assertCondition($booking['room_name'] === $firstRoom['name'], "Reservation joined room_name: '{$booking['room_name']}'");
assertCondition((float) $booking['deposit_amount'] === $expectedDeposit, "Reservation stored deposit amount: ₱{$expectedDeposit}");
assertCondition((float) $booking['balance_amount'] === $expectedBalance, "Reservation stored balance amount: ₱{$expectedBalance}");
assertCondition($booking['check_in_date'] === $checkIn, "Reservation stored check-in: $checkIn");
assertCondition($booking['check_out_date'] === $checkOut, "Reservation stored check-out: $checkOut");

// Verify overlap prevention
assertCondition(
    HotelRoom::isAvailableForDates($testRoomId, '2027-05-11', '2027-05-12') === false,
    "Collision detection blocks overlapping dates inside existing reservation"
);
assertCondition(
    HotelRoom::isAvailableForDates($testRoomId, '2027-05-08', '2027-05-11') === false,
    "Collision detection blocks overlapping check-in spanning start date"
);
assertCondition(
    HotelRoom::isAvailableForDates($testRoomId, '2027-05-13', '2027-05-15') === true,
    "Dates immediately after check-out are available for the next guest"
);

// ── Test 6: Payment of Deposit and Front-Desk State Transitions ──
echo "\n--- Test 6: Deposit Payment & Front-Desk States ---\n";
// 1. Pay deposit online
Payment::create($bookingId, $expectedDeposit, 'card', 'paid');
Database::run("UPDATE bookings SET hotel_status = 'deposit_paid', payment_stage = 'deposit_paid' WHERE id = ?", [$bookingId]);

$b = Booking::find($bookingId);
assertCondition($b['hotel_status'] === 'deposit_paid', "State transitioned to 'deposit_paid'");
assertCondition($b['payment_stage'] === 'deposit_paid', "Payment stage is 'deposit_paid'");

// 2. Hotel partner confirms reservation
Database::run("UPDATE bookings SET hotel_status = 'confirmed', status = 'confirmed' WHERE id = ?", [$bookingId]);
$b = Booking::find($bookingId);
assertCondition($b['hotel_status'] === 'confirmed', "Partner confirmed: State is 'confirmed' (room held)");

// 3. Guest arrives and checks in -> front desk balance due
Database::run("UPDATE bookings SET hotel_status = 'checked_in', payment_stage = 'balance_due' WHERE id = ?", [$bookingId]);
$b = Booking::find($bookingId);
assertCondition($b['hotel_status'] === 'checked_in', "Guest checked in at hotel");
assertCondition($b['payment_stage'] === 'balance_due', "Payment stage transitioned to 'balance_due'");

// 4. Guest settles balance at front desk upon checkout
Payment::create($bookingId, $expectedBalance, 'front_desk_cash', 'paid');
Database::run("UPDATE bookings SET hotel_status = 'completed', payment_stage = 'settled', status = 'completed' WHERE id = ?", [$bookingId]);
$b = Booking::find($bookingId);
assertCondition($b['hotel_status'] === 'completed', "Front desk balance settled: Reservation 'completed'");
assertCondition($b['payment_stage'] === 'settled', "Payment stage is 'settled'");

// Clean up test reservation
Database::run("DELETE FROM payments WHERE booking_id = ?", [$bookingId]);
Database::run("DELETE FROM bookings WHERE id = ?", [$bookingId]);
echo "✓ Cleaned up test reservation #$bookingId\n";

echo "\n--------------------------------------------------------\n";
echo "Results: $passed passed, $failed failed\n";
echo "--------------------------------------------------------\n";
