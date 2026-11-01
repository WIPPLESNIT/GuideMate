<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Booking;
use App\Models\RentalRequest;
use App\Models\Listing;
use App\Controllers\HotelController;
use App\Controllers\RentalController;
use App\Controllers\GuideController;
use App\Controllers\AdminController;
use App\Controllers\ApiController;

echo "====================================================================\n";
echo " GuideMate Admin Warning & Restricted Booking Actions Test Suite\n";
echo "====================================================================\n\n";

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

// -------------------------------------------------------------------------
// Setup: Create or fetch test users for each provider role
// -------------------------------------------------------------------------
$db = Database::connection();

// 1. Hotel Provider
$hotelUser = Database::first("SELECT id, name, email, role FROM users WHERE email = 'test_hotel_warned@example.com' LIMIT 1");
if ($hotelUser === null) {
    $hotelId = (int) Database::insert(
        "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'hotel_provider', 1, NOW())",
        ['Test Hotel Partner', 'test_hotel_warned@example.com', password_hash('password', PASSWORD_DEFAULT)]
    );
} else {
    $hotelId = (int) $hotelUser['id'];
}

// 2. Rental Provider
$rentalUser = Database::first("SELECT id, name, email, role FROM users WHERE email = 'test_rental_warned@example.com' LIMIT 1");
if ($rentalUser === null) {
    $rentalId = (int) Database::insert(
        "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'rental_provider', 1, NOW())",
        ['Test Rental Partner', 'test_rental_warned@example.com', password_hash('password', PASSWORD_DEFAULT)]
    );
} else {
    $rentalId = (int) $rentalUser['id'];
}

// 3. Tour Guide
$guideUser = Database::first("SELECT id, name, email, role FROM users WHERE email = 'test_guide_warned@example.com' LIMIT 1");
if ($guideUser === null) {
    $guideId = (int) Database::insert(
        "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'guide', 1, NOW())",
        ['Test Guide Partner', 'test_guide_warned@example.com', password_hash('password', PASSWORD_DEFAULT)]
    );
} else {
    $guideId = (int) $guideUser['id'];
}

// -------------------------------------------------------------------------
// Test 1: Warning and Clearing Lifecycles Across All Provider Roles
// -------------------------------------------------------------------------
echo "--- Test 1: Warning Lifecycle Across Provider Roles ---\n";

// Clear initially
User::clearGuideWarning($hotelId);
User::clearGuideWarning($rentalId);
User::clearGuideWarning($guideId);

assertCond(!User::isGuideWarned($hotelId), "Hotel partner initially has no active warning");
assertCond(!User::isGuideWarned($rentalId), "Rental partner initially has no active warning");
assertCond(!User::isGuideWarned($guideId), "Guide initially has no active warning");

// Issue warning to hotel partner
User::warnGuide($hotelId, "Dispute report: extra fee requested from guest.");
assertCond(User::isGuideWarned($hotelId) === true, "Hotel partner has active warning after warnGuide");
assertCond(User::isProviderWarned($hotelId) === true, "User::isProviderWarned returns true for hotel partner");
assertCond(strpos(User::getWarningNote($hotelId), 'extra fee') !== false, "Warning note is preserved in database");

// Issue warning to rental partner
User::warnProvider($rentalId, "Vehicle condition did not match listing.");
assertCond(User::isGuideWarned($rentalId) === true, "Rental partner has active warning after warnProvider");
assertCond(User::isProviderWarned($rentalId) === true, "User::isProviderWarned returns true for rental partner");

// Issue warning to guide
User::warnGuide($guideId, "Guide did not show up on scheduled tour time.");
assertCond(User::isGuideWarned($guideId) === true, "Guide has active warning after warnGuide");

// Clear warnings
User::clearProviderWarning($hotelId);
assertCond(!User::isProviderWarned($hotelId), "Hotel partner active warning cleared immediately without re-auth");
User::clearProviderWarning($rentalId);
assertCond(!User::isProviderWarned($rentalId), "Rental partner active warning cleared immediately without re-auth");
User::clearGuideWarning($guideId);
assertCond(!User::isGuideWarned($guideId), "Guide active warning cleared immediately without re-auth");

// -------------------------------------------------------------------------
// Test 2: Hotel Reservation Action Restriction Enforcement
// -------------------------------------------------------------------------
echo "\n--- Test 2: Hotel Reservation Action Restrictions ---\n";

// Re-apply warning to hotel partner
User::warnProvider($hotelId, "Dispute test warning");
assertCond(User::isProviderWarned($hotelId) === true, "Hotel partner is warned for action testing");

// Set up mock session as hotel partner
$_SESSION['user_id'] = $hotelId;
\App\Core\Auth::refreshUser();

// Clean up any stale test listings
Database::run("DELETE FROM listings WHERE slug LIKE 'test-hotel-prop%' OR slug LIKE 'snorkeling-adv-test%'");

// Create test booking for hotel
$hotelSlug = 'test-hotel-prop-' . uniqid();
$testListingId = (int) Database::insert(
    "INSERT INTO listings (user_id, category_id, title, slug, price, area, status, created_at) VALUES (?, 1, 'Test Hotel Property', ?, 2500, 'Cebu', 'approved', NOW())",
    [$hotelId, $hotelSlug]
);
$testBookingId = (int) Database::insert(
    "INSERT INTO bookings (listing_id, user_id, total_amount, deposit_amount, balance_amount, status, hotel_status, booking_date, created_at) VALUES (?, 1, 5000, 1500, 3500, 'pending', 'pending', CURDATE(), NOW())",
    [$testListingId]
);

// Verify HotelController rejects action when warned
$hotelController = new HotelController();
// Test with JSON accept header
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_POST['_token'] = csrf_token();
$_POST['csrf_token'] = csrf_token();
$_POST['action'] = 'confirm';

ob_start();
try {
    $hotelController->updateReservationStatus((string) $testBookingId);
} catch (\Throwable $e) {
    // exit / headers
}
$output = ob_get_clean();

// Check reservation status was NOT changed
$freshBooking = Database::first("SELECT hotel_status FROM bookings WHERE id = ?", [$testBookingId]);
assertCond($freshBooking['hotel_status'] === 'pending', "Hotel reservation remains 'pending' because warned partner cannot confirm");

// Clear warning and verify action is immediately allowed
User::clearProviderWarning($hotelId);
\App\Core\Auth::refreshUser();

// Update reservation without warning
Database::run("UPDATE bookings SET hotel_status = 'confirmed' WHERE id = ?", [$testBookingId]);
$updatedBooking = Database::first("SELECT hotel_status FROM bookings WHERE id = ?", [$testBookingId]);
assertCond($updatedBooking['hotel_status'] === 'confirmed', "Reservation can be updated immediately once warning is cleared");

// -------------------------------------------------------------------------
// Test 3: Rental Request Action Restriction Enforcement
// -------------------------------------------------------------------------
echo "\n--- Test 3: Rental Request Action Restrictions ---\n";

// Warn rental partner
User::warnProvider($rentalId, "Rental dispute warning");
assertCond(User::isProviderWarned($rentalId) === true, "Rental partner is warned for action testing");

$_SESSION['user_id'] = $rentalId;
\App\Core\Auth::refreshUser();

// Create test vehicle and rental request
$vSlug = 'test-vehicle-' . uniqid();
$testVehicleId = (int) Database::insert(
    "INSERT INTO vehicles (user_id, make, model, year, type, name, slug, price_per_day, status, is_available, created_at) VALUES (?, 'Toyota', 'Wigo', 2022, 'Car', 'Toyota Wigo Test', ?, 1500, 'approved', 1, NOW())",
    [$rentalId, $vSlug]
);
$testRentalId = (int) Database::insert(
    "INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, location, pickup_date, rental_days, total_amount, status, payment_status, created_at) VALUES (1, ?, 'Toyota Wigo Test', 'Car', 'Test Shop', 'Cebu City', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 2, 3000, 'pending', 'paid', NOW())",
    [$testVehicleId]
);

$rentalController = new RentalController();
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_POST['_token'] = csrf_token();
$_POST['csrf_token'] = csrf_token();
$_POST['status'] = 'approved';

ob_start();
try {
    $rentalController->updateStatus((string) $testRentalId);
} catch (\Throwable $e) {
    // exit
}
$rentalOutput = ob_get_clean();

$freshRental = Database::first("SELECT status FROM rental_requests WHERE id = ?", [$testRentalId]);
assertCond($freshRental['status'] === 'pending', "Rental request remains 'pending' because warned partner cannot approve/confirm");

// Test refund rejection
ob_start();
try {
    $rentalController->refund((string) $testRentalId);
} catch (\Throwable $e) {
    // exit
}
$refundOutput = ob_get_clean();
$freshRental2 = Database::first("SELECT payment_status FROM rental_requests WHERE id = ?", [$testRentalId]);
assertCond($freshRental2['payment_status'] === 'paid', "Rental payment status remains 'paid' because warned partner cannot issue refunds");

// Clear warning and verify action restores immediately
User::clearProviderWarning($rentalId);
\App\Core\Auth::refreshUser();
assertCond(!User::isProviderWarned($rentalId), "Rental partner warning is cleared");

// -------------------------------------------------------------------------
// Test 4: Guide Booking Actions Restriction Enforcement
// -------------------------------------------------------------------------
echo "\n--- Test 4: Guide Booking Actions Restrictions ---\n";

User::warnGuide($guideId, "Tour guide conduct warning");
assertCond(User::isGuideWarned($guideId) === true, "Tour guide is warned");

$_SESSION['user_id'] = $guideId;
\App\Core\Auth::refreshUser();

$guideSlug = 'snorkeling-adv-test-' . uniqid();
$guideBookingListing = (int) Database::insert(
    "INSERT INTO listings (user_id, category_id, title, slug, price, area, status, created_at) VALUES (?, 1, 'Snorkeling Adventure', ?, 1800, 'Moalboal', 'approved', NOW())",
    [$guideId, $guideSlug]
);
$guideBookingId = (int) Database::insert(
    "INSERT INTO bookings (listing_id, user_id, total_amount, status, booking_date, created_at) VALUES (?, 1, 1800, 'pending', CURDATE(), NOW())",
    [$guideBookingListing]
);

$guideController = new GuideController();
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_POST['_token'] = csrf_token();
$_POST['csrf_token'] = csrf_token();
$_POST['status'] = 'confirmed';

ob_start();
try {
    $guideController->updateBookingStatus((string) $guideBookingId);
} catch (\Throwable $e) {
    // exit
}
$guideOutput = ob_get_clean();

$freshGuideBooking = Database::first("SELECT status FROM bookings WHERE id = ?", [$guideBookingId]);
assertCond($freshGuideBooking['status'] === 'pending', "Guide booking remains 'pending' because warned guide cannot confirm");

// Verify verifyBooking is also blocked
$_POST['verify_token'] = 'DUMMY_TOKEN_123';
ob_start();
try {
    $guideController->verifyBooking();
} catch (\Throwable $e) {
    // exit
}
$verifyOutput = ob_get_clean();

User::clearGuideWarning($guideId);
\App\Core\Auth::refreshUser();
assertCond(!User::isGuideWarned($guideId), "Guide warning is cleared and access is restored");

// -------------------------------------------------------------------------
// Test 5: UI Template Status Indicators and Disabled Buttons
// -------------------------------------------------------------------------
echo "\n--- Test 5: UI Templates and Indicators ---\n";

$hotelView = file_get_contents(__DIR__ . '/../app/Views/dashboard/hotel.php');
assertCond(strpos($hotelView, '$isWarned') !== false, "dashboard/hotel.php checks isWarned");
assertCond(strpos($hotelView, 'res-action-restricted-notice') !== false, "dashboard/hotel.php renders restricted notice in dropdown");
assertCond(strpos($hotelView, 'is-disabled action-accept') !== false, "dashboard/hotel.php disables Accept button when warned");
assertCond(strpos($hotelView, 'is-disabled action-confirm') !== false, "dashboard/hotel.php disables Confirm button when warned");
assertCond(strpos($hotelView, 'is-disabled action-cancel') !== false, "dashboard/hotel.php disables Cancel button when warned");
assertCond(strpos($hotelView, 'is-disabled action-settle') !== false, "dashboard/hotel.php disables Settle/Complete button when warned");

$bookingsView = file_get_contents(__DIR__ . '/../app/Views/guide/bookings.php');
assertCond(strpos($bookingsView, 'res-action-restricted-notice') !== false, "guide/bookings.php renders restricted notice in dropdown");
assertCond(strpos($bookingsView, 'is-disabled action-accept') !== false, "guide/bookings.php disables Confirm/Complete when warned");
assertCond(strpos($bookingsView, 'is-disabled action-cancel') !== false, "guide/bookings.php disables Cancel when warned");

$rentalView = file_get_contents(__DIR__ . '/../app/Views/rental/requests.php');
assertCond(strpos($rentalView, '$isWarned') !== false, "rental/requests.php checks isWarned");
assertCond(strpos($rentalView, 'res-action-restricted-notice') !== false, "rental/requests.php renders restricted notice in dropdown");
assertCond(strpos($rentalView, 'is-disabled action-accept') !== false, "rental/requests.php disables Confirm/Complete when warned");
assertCond(strpos($rentalView, 'is-disabled action-cancel') !== false, "rental/requests.php disables Decline/Cancel when warned");
assertCond(strpos($rentalView, 'Refund Restricted') !== false, "rental/requests.php disables Refund button in problem modal when warned");

$verifyView = file_get_contents(__DIR__ . '/../app/Views/guide/verify.php');
assertCond(strpos($verifyView, '$isWarned') !== false, "guide/verify.php checks isWarned");
assertCond(strpos($verifyView, '$isWarned ? \' disabled') !== false, "guide/verify.php disables verify button when warned");

$adminUsersView = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');
assertCond(strpos($adminUsersView, 'btn-warn-user') !== false, "admin/users.php has Issue Warning action for providers");
assertCond(strpos($adminUsersView, 'warnUserModal') !== false, "admin/users.php has warnUserModal for issuing warnings");
assertCond(strpos($adminUsersView, 'clear-warning') !== false, "admin/users.php has Remove Restriction form");

// Cleanup test records
Database::run("DELETE FROM bookings WHERE id IN (?, ?)", [$testBookingId, $guideBookingId]);
Database::run("DELETE FROM listings WHERE id IN (?, ?)", [$testListingId, $guideBookingListing]);
Database::run("DELETE FROM rental_requests WHERE id = ?", [$testRentalId]);
Database::run("DELETE FROM vehicles WHERE id = ?", [$testVehicleId]);
Database::run("DELETE FROM users WHERE id IN (?, ?, ?)", [$hotelId, $rentalId, $guideId]);

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " Test Summary: $passed Passed, $failed Failed\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
