<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;

echo "====================================================================\n";
echo " Hotel Provider Revenue Summary Box Test Suite\n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $condition, string $message): void
{
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] {$message}\n";
        $passCount++;
    } else {
        echo " [FAIL] {$message}\n";
        $failCount++;
    }
}

// ---------------------------------------------------------
// Test 1: Method Existence & Signatures
// ---------------------------------------------------------
echo "--- Test 1: Model Revenue Methods ---\n";
assertCondition(method_exists(Booking::class, 'revenueForHotelOwner'), "Booking::revenueForHotelOwner exists");
assertCondition(method_exists(Booking::class, 'revenueForProvider'), "Booking::revenueForProvider exists");
assertCondition(method_exists(Booking::class, 'revenueForGuide'), "Booking::revenueForGuide exists");

// ---------------------------------------------------------
// Test 2: Revenue Calculation Across Hotel Booking Lifecycles
// ---------------------------------------------------------
echo "\n--- Test 2: Revenue Calculation Across Hotel Booking Lifecycles ---\n";

$db = Database::connection();

// Create a dedicated test hotel owner
$testEmail = 'hotel_rev_test_' . time() . '_' . bin2hex(random_bytes(3)) . '@example.com';
$db->prepare("INSERT INTO users (name, email, password, role, guide_status, created_at, updated_at) VALUES ('Hotel Owner Test', ?, 'secret', 'hotel_provider', 'approved', NOW(), NOW())")->execute([$testEmail]);
$ownerId = (int) $db->lastInsertId();

// Verify zero revenue for fresh hotel provider
$initialRev = Booking::revenueForHotelOwner($ownerId);
assertCondition($initialRev === 0.0, "Fresh hotel provider has exactly 0.00 revenue");

// Create test hotel listing
$db->prepare("INSERT INTO listings (user_id, category_id, title, slug, description, price, area, status, created_at, updated_at) VALUES (?, 2, 'Grand Plaza Hotel', 'grand-plaza-hotel', 'Luxury stay', 3000.00, 'Cebu City', 'approved', NOW(), NOW())")->execute([$ownerId]);
$listingId = (int) $db->lastInsertId();

// 1. Completed reservation (Total: 6,000, 2 nights)
$db->prepare("INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, status, hotel_status, payment_stage, created_at, updated_at) VALUES (?, 2, '2026-11-10', '2026-11-10', '2026-11-12', 2, 2, 6000.00, 1200.00, 0.00, 'completed', 'completed', 'settled', NOW(), NOW())")->execute([$listingId]);
$completedBookingId = (int) $db->lastInsertId();
// Add deposit and checkout payment records
Payment::create($completedBookingId, 1200.00, 'gcash', 'paid');
Payment::create($completedBookingId, 4800.00, 'front_desk_checkout', 'paid');

$revAfterCompleted = Booking::revenueForHotelOwner($ownerId);
assertCondition($revAfterCompleted === 6000.0, "Revenue includes completed stay (got 6,000.00)");

// Verify multiple payment rows do not duplicate booking revenue
assertCondition($revAfterCompleted === 6000.0, "Multiple payment records on a stay do not duplicate revenue");

// 2. Checked-in reservation (Total: 3,500, balance paid at front desk check-in)
$db->prepare("INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, status, hotel_status, payment_stage, created_at, updated_at) VALUES (?, 2, '2026-11-15', '2026-11-15', '2026-11-16', 1, 1, 3500.00, 700.00, 0.00, 'confirmed', 'checked_in', 'paid', NOW(), NOW())")->execute([$listingId]);
$checkedInBookingId = (int) $db->lastInsertId();
Payment::create($checkedInBookingId, 700.00, 'card', 'paid');
Payment::create($checkedInBookingId, 2800.00, 'front_desk_checkin', 'paid');

$revAfterCheckedIn = Booking::revenueForHotelOwner($ownerId);
assertCondition($revAfterCheckedIn === 9500.0, "Revenue includes checked-in stay (6,000 + 3,500 = 9,500.00)");

// 3. Cancelled & refunded reservation (Total: 5,000)
$db->prepare("INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, status, hotel_status, payment_stage, cancelled_at, refund_amount, refund_status, created_at, updated_at) VALUES (?, 2, '2026-11-20', '2026-11-20', '2026-11-22', 2, 2, 5000.00, 1000.00, 4000.00, 'refunded', 'cancelled', 'deposit_paid', NOW(), 1000.00, 'refunded', NOW(), NOW())")->execute([$listingId]);
$cancelledBookingId = (int) $db->lastInsertId();
Payment::create($cancelledBookingId, 1000.00, 'gcash', 'refunded');

$revAfterCancelled = Booking::revenueForHotelOwner($ownerId);
assertCondition($revAfterCancelled === 9500.0, "Cancelled/refunded stay is strictly excluded from revenue (remains 9,500.00)");

// 4. Pending / unconfirmed reservation (Total: 4,000)
$db->prepare("INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, status, hotel_status, payment_stage, created_at, updated_at) VALUES (?, 2, '2026-11-25', '2026-11-25', '2026-11-26', 1, 1, 4000.00, 800.00, 3200.00, 'pending', 'pending', 'deposit_paid', NOW(), NOW())")->execute([$listingId]);
$pendingBookingId = (int) $db->lastInsertId();

$revAfterPending = Booking::revenueForHotelOwner($ownerId);
assertCondition($revAfterPending === 9500.0, "Pending/unconfirmed stay is excluded from recognized revenue (remains 9,500.00)");

// 5. Confirmed reservation with verified deposit
$db->prepare("INSERT INTO bookings (listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, status, hotel_status, payment_stage, created_at, updated_at) VALUES (?, 2, '2026-11-28', '2026-11-28', '2026-11-30', 2, 2, 2000.00, 400.00, 1600.00, 'confirmed', 'confirmed', 'balance_due', NOW(), NOW())")->execute([$listingId]);
$confirmedBookingId = (int) $db->lastInsertId();
Payment::create($confirmedBookingId, 400.00, 'instapay', 'paid');

$revAfterConfirmed = Booking::revenueForHotelOwner($ownerId);
assertCondition($revAfterConfirmed === 11500.0, "Confirmed stay with verified deposit is recognized (9,500 + 2,000 = 11,500.00)");

// ---------------------------------------------------------
// Test 3: Dashboard Controller & View Data Binding
// ---------------------------------------------------------
echo "\n--- Test 3: Dashboard Controller & View Data Binding ---\n";

$dashCtrlFile = file_get_contents(__DIR__ . '/../app/Controllers/DashboardController.php');
assertCondition(
    strpos($dashCtrlFile, "Booking::revenueForHotelOwner") !== false,
    "DashboardController::hotelAdmin calculates Booking::revenueForHotelOwner"
);
assertCondition(
    strpos($dashCtrlFile, "'revenue' => \$revenue") !== false,
    "DashboardController::hotelAdmin passes 'revenue' to hotel dashboard view"
);

$hotelViewFile = file_get_contents(__DIR__ . '/../app/Views/dashboard/hotel.php');
assertCondition(
    strpos($hotelViewFile, "admin_icon('revenue', 18)") !== false,
    "dashboard/hotel.php renders admin_icon('revenue', 18)"
);
assertCondition(
    strpos($hotelViewFile, "money(\$revenue)") !== false,
    "dashboard/hotel.php formats revenue using money(\$revenue)"
);
assertCondition(
    strpos($hotelViewFile, "__('d_stat_revenue', 'Revenue')") !== false || strpos($hotelViewFile, "<div class=\"label\">Revenue</div>") !== false,
    "dashboard/hotel.php renders Revenue label with localization support"
);
assertCondition(
    strpos($hotelViewFile, "completedStaysCount") !== false,
    "dashboard/hotel.php computes and displays completed stays in stat-action note"
);
assertCondition(
    strpos($hotelViewFile, "stat stat-ico-card") !== false,
    "dashboard/hotel.php uses standard stat stat-ico-card styling"
);

// ---------------------------------------------------------
// Test 4: Currency & Number Formatting Helpers
// ---------------------------------------------------------
echo "\n--- Test 4: Currency & Number Formatting ---\n";

$formattedZero = money(0.0);
assertCondition(strpos($formattedZero, '0.00') !== false, "money(0.0) displays zero balance gracefully: {$formattedZero}");

$formattedAmount = money(11500.0);
assertCondition(strpos($formattedAmount, '11,500.00') !== false, "money(11500.0) formats thousands separator: {$formattedAmount}");

// ---------------------------------------------------------
// Test 5: Reusability and Guide Backward Compatibility
// ---------------------------------------------------------
echo "\n--- Test 5: Guide Provider Compatibility ---\n";

$guideRev = Booking::revenueForGuide($ownerId);
assertCondition($guideRev === 11500.0, "Booking::revenueForGuide returns identical calculation via revenueForProvider");

// Clean up test records
$db->prepare("DELETE FROM payments WHERE booking_id IN (?, ?, ?, ?, ?)")->execute([
    $completedBookingId, $checkedInBookingId, $cancelledBookingId, $pendingBookingId, $confirmedBookingId
]);
$db->prepare("DELETE FROM bookings WHERE id IN (?, ?, ?, ?, ?)")->execute([
    $completedBookingId, $checkedInBookingId, $cancelledBookingId, $pendingBookingId, $confirmedBookingId
]);
$db->prepare("DELETE FROM listings WHERE id = ?")->execute([$listingId]);
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$ownerId]);

echo "\nCleaned up all test records.\n";

echo "====================================================================\n";
echo " Test Results: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
