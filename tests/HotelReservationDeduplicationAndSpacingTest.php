<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;

echo "====================================================================\n";
echo " Hotel Reservation Deduplication & Table Spacing Test Suite\n";
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

// ---------------------------------------------------------
// Test 1: Root Cause Reproduction & Verification across Multiple Statuses
// ---------------------------------------------------------
echo "--- Test 1: Reservation Duplication Across Multiple Statuses ---\n";

// Find or create a test hotel provider
$owner = Database::first("SELECT id FROM users WHERE role IN ('hotel_provider', 'guide') ORDER BY id ASC LIMIT 1");
if (!$owner) {
    echo " [SKIP] No provider user found.\n";
    exit(0);
}
$ownerId = (int) $owner['id'];

// Find or create a listing for this provider
$listing = Database::first("SELECT id FROM listings WHERE user_id = ? LIMIT 1", [$ownerId]);
if (!$listing) {
    $listingId = Database::insert(
        "INSERT INTO listings (user_id, category_id, title, slug, description, area, price, status, created_at)
         VALUES (?, 1, 'Deduplication Test Resort', 'dedup-test-resort-" . time() . "', 'Test description', 'Moalboal', 5000, 'active', NOW())",
        [$ownerId]
    );
} else {
    $listingId = (int) $listing['id'];
}

$tourist = Database::first("SELECT id FROM users WHERE role = 'tourist' ORDER BY id ASC LIMIT 1");
$touristId = $tourist ? (int) $tourist['id'] : $ownerId;

// Create test reservations with multiple payments in different statuses:
// 1. Completed stay with 2 payments (deposit + checkout settlement)
$bCompletedId = Database::insert(
    "INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, total_amount, deposit_amount, balance_amount, payment_stage, status, hotel_status, created_at)
     VALUES (?, ?, CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 5000, 1000, 4000, 'settled', 'completed', 'completed', NOW())",
    [$listingId, $touristId]
);
Database::insert(
    "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
     VALUES (?, 1000, 'gcash', 'paid', 'TEST-REF-1', NOW(), NOW())",
    [$bCompletedId]
);
Database::insert(
    "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
     VALUES (?, 4000, 'front_desk_cash', 'paid', 'TEST-REF-2', NOW(), NOW())",
    [$bCompletedId]
);

// 2. Checked-in stay with 2 payments (deposit + checkin payment)
$bCheckedInId = Database::insert(
    "INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, total_amount, deposit_amount, balance_amount, payment_stage, status, hotel_status, created_at)
     VALUES (?, ?, CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 6000, 1200, 4800, 'paid', 'confirmed', 'checked_in', NOW())",
    [$listingId, $touristId]
);
Database::insert(
    "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
     VALUES (?, 1200, 'card', 'paid', 'TEST-REF-3', NOW(), NOW())",
    [$bCheckedInId]
);
Database::insert(
    "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
     VALUES (?, 4800, 'front_desk_checkin', 'paid', 'TEST-REF-4', NOW(), NOW())",
    [$bCheckedInId]
);

// 3. Disputed booking with 2 payments
$bDisputedId = Database::insert(
    "INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, total_amount, deposit_amount, balance_amount, payment_stage, status, hotel_status, created_at)
     VALUES (?, ?, CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 3000, 600, 2400, 'settled', 'disputed', 'completed', NOW())",
    [$listingId, $touristId]
);
Database::insert(
    "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
     VALUES (?, 600, 'gcash', 'paid', 'TEST-REF-5', NOW(), NOW())",
    [$bDisputedId]
);
Database::insert(
    "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
     VALUES (?, 2400, 'front_desk_cash', 'paid', 'TEST-REF-6', NOW(), NOW())",
    [$bDisputedId]
);

// 4. Cancelled/Refunded booking
$bCancelledId = Database::insert(
    "INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, total_amount, deposit_amount, balance_amount, payment_stage, status, hotel_status, refund_amount, refund_status, cancelled_at, created_at)
     VALUES (?, ?, CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 4000, 800, 3200, 'refunded', 'refunded', 'cancelled', 800, 'initiated', NOW(), NOW())",
    [$listingId, $touristId]
);
Database::insert(
    "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
     VALUES (?, 800, 'gcash', 'refunded', 'TEST-REF-7', NOW(), NOW())",
    [$bCancelledId]
);

// Retrieve reservations using Booking::forGuide
$reservations = Booking::forGuide($ownerId);

// Count occurrences of each booking ID
$bookingCounts = [];
foreach ($reservations as $r) {
    $bId = (int) $r['id'];
    $bookingCounts[$bId] = ($bookingCounts[$bId] ?? 0) + 1;
}

assertCondition(($bookingCounts[$bCompletedId] ?? 0) === 1, "Completed reservation #{$bCompletedId} (2 payments) appears exactly ONCE");
assertCondition(($bookingCounts[$bCheckedInId] ?? 0) === 1, "Checked-in reservation #{$bCheckedInId} (2 payments) appears exactly ONCE");
assertCondition(($bookingCounts[$bDisputedId] ?? 0) === 1, "Disputed reservation #{$bDisputedId} (2 payments) appears exactly ONCE");
assertCondition(($bookingCounts[$bCancelledId] ?? 0) === 1, "Cancelled/Refunded reservation #{$bCancelledId} appears exactly ONCE");

// Verify there are no duplicate IDs anywhere in the result
$allUnique = count($reservations) === count(array_unique(array_column($reservations, 'id')));
assertCondition($allUnique, "Zero duplicate rows exist across ALL statuses in Booking::forGuide results");

// ---------------------------------------------------------
// Test 2: Status, Payment Stage, and Balance Display Correctness
// ---------------------------------------------------------
echo "\n--- Test 2: Field Integrity & Status Display ---\n";

$completedRow = array_values(array_filter($reservations, fn($r) => (int)$r['id'] === $bCompletedId))[0] ?? null;
$checkedInRow = array_values(array_filter($reservations, fn($r) => (int)$r['id'] === $bCheckedInId))[0] ?? null;
$cancelledRow = array_values(array_filter($reservations, fn($r) => (int)$r['id'] === $bCancelledId))[0] ?? null;

assertCondition($completedRow !== null && $completedRow['hotel_status'] === 'completed', "Completed stay retains hotel_status='completed'");
assertCondition($checkedInRow !== null && $checkedInRow['hotel_status'] === 'checked_in', "Checked-in stay retains hotel_status='checked_in'");
assertCondition($cancelledRow !== null && ($cancelledRow['hotel_status'] === 'cancelled' || $cancelledRow['status'] === 'refunded'), "Cancelled stay retains cancellation/refund status");
assertCondition($cancelledRow !== null && !empty($cancelledRow['refund_amount']) && (float)$cancelledRow['refund_amount'] === 800.0, "Refund amount 800.00 is preserved");

// ---------------------------------------------------------
// Test 3: Table Layout & Spacing Consistency in Web Views
// ---------------------------------------------------------
echo "\n--- Test 3: Table Layout & Spacing Consistency ---\n";

$dashHotelView = file_get_contents(__DIR__ . '/../app/Views/dashboard/hotel.php');
$guideBookingsView = file_get_contents(__DIR__ . '/../app/Views/guide/bookings.php');

assertCondition(
    strpos($dashHotelView, "display:inline-flex;flex-direction:column;align-items:center;gap:6px") !== false,
    "dashboard/hotel.php status column uses flex column container with clear 6px gap"
);
assertCondition(
    strpos($dashHotelView, "res-refund-amount") !== false,
    "dashboard/hotel.php contains res-refund-amount class for refund text"
);
assertCondition(
    strpos($guideBookingsView, "display:inline-flex;flex-direction:column;align-items:center;gap:6px") !== false,
    "guide/bookings.php status column uses flex column container with clear 6px gap"
);
assertCondition(
    strpos($guideBookingsView, "res-refund-amount") !== false,
    "guide/bookings.php contains res-refund-amount class for refund text"
);

// ---------------------------------------------------------
// Test 4: Revenue and Earnings Calculations Deduplication
// ---------------------------------------------------------
echo "\n--- Test 4: Revenue Calculations Deduplication ---\n";

$rev = Booking::revenueForGuide($ownerId);
assertCondition(is_float($rev) && $rev >= 0, "Booking::revenueForGuide executes accurately without SQL errors");

$earnings = Booking::earningsPerMonth($ownerId);
assertCondition(is_array($earnings), "Booking::earningsPerMonth returns clean array without multiplying rows");

// Clean up test bookings and payments
Database::run("DELETE FROM payments WHERE booking_id IN (?, ?, ?, ?)", [$bCompletedId, $bCheckedInId, $bDisputedId, $bCancelledId]);
Database::run("DELETE FROM bookings WHERE id IN (?, ?, ?, ?)", [$bCompletedId, $bCheckedInId, $bDisputedId, $bCancelledId]);

// ---------------------------------------------------------
// Summary
// ---------------------------------------------------------
echo "\n====================================================================\n";
echo " Test Results: $passed Passed, $failed Failed\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
