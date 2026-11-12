<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Listing;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\DisputeFile;
use App\Models\Payment;
use App\Controllers\ApiController;
use App\Controllers\AdminController;

echo "=== GuideMate 3 Issues Verification Tests ===\n\n";

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

$api = new ApiController();
$refMethod = new ReflectionMethod($api, 'bookingPayload');
$refMethod->setAccessible(true);

// --------------------------------------------------------------------
// TEST 1: Regular Listing Payment Status (App & Web)
// --------------------------------------------------------------------
echo "1. Testing Regular Listing Payment Status Workflow...\n";

// 1.1 Verify Booking 433 (Ogtong Cave)
$b433 = Booking::find(433);
assertCondition($b433 !== null, 'Booking 433 exists');

// Simulate customer bookings retrieval
$custBookings = Booking::forCustomer(2475);
$found433 = null;
foreach ($custBookings as $cb) {
    if ((int) $cb['id'] === 433) {
        $found433 = $cb;
        break;
    }
}
assertCondition($found433 !== null, 'Booking 433 returned in customer bookings');

$p433 = $refMethod->invoke($api, $found433, 2475);
assertCondition(($p433['payment_status'] ?? '') === 'paid', 'Booking 433 API payload payment_status is "paid"');
assertCondition(($p433['payment_stage'] ?? '') === 'paid', 'Booking 433 API payload payment_stage is "paid"');
assertCondition((float) ($p433['balance_amount'] ?? -1) === 0.0, 'Booking 433 API payload balance_amount is 0.00');
assertCondition(($p433['balance_payment_status'] ?? '') === 'Paid', 'Booking 433 API payload balance_payment_status is "Paid"');

// 1.2 Verify Unpaid Booking is NOT automatically marked as Paid
$unpaidBkId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount, payment_stage,
        status, created_at
    ) VALUES (1210, 2475, '2026-12-18', 2, 800.00, 0.00, 800.00, 'unpaid', 'pending', NOW())
");
$unpaidRow = Database::first("
    SELECT b.*, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.area,
           l.duration AS listing_duration, l.address AS listing_address,
           c.slug AS category_slug,
           (SELECT status FROM payments WHERE booking_id = b.id ORDER BY id DESC LIMIT 1) AS payment_status
    FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    LEFT JOIN categories c ON c.id = l.category_id
    WHERE b.id = ?
", [$unpaidBkId]);

$pUnpaid = $refMethod->invoke($api, $unpaidRow, 2475);
assertCondition(($pUnpaid['payment_status'] ?? '') === 'unpaid', 'Unpaid booking payload payment_status remains "unpaid"');
assertCondition((float) ($pUnpaid['balance_amount'] ?? 0) === 800.0, 'Unpaid booking payload balance_amount remains full amount (800.00)');
assertCondition(($pUnpaid['balance_payment_status'] ?? '') === 'Pending', 'Unpaid booking payload balance_payment_status is "Pending"');

// 1.3 Complete payment and verify status transitions to Paid
Payment::create($unpaidBkId, 800.00, 'gcash', 'paid');
Booking::updateStatus($unpaidBkId, 'confirmed');

$paidRow = Database::first("
    SELECT b.*, l.title AS listing_title, l.slug AS listing_slug, l.cover_image, l.area,
           l.duration AS listing_duration, l.address AS listing_address,
           c.slug AS category_slug,
           (SELECT status FROM payments WHERE booking_id = b.id ORDER BY id DESC LIMIT 1) AS payment_status
    FROM bookings b
    LEFT JOIN listings l ON l.id = b.listing_id
    LEFT JOIN categories c ON c.id = l.category_id
    WHERE b.id = ?
", [$unpaidBkId]);

$pPaid = $refMethod->invoke($api, $paidRow, 2475);
assertCondition(($pPaid['payment_status'] ?? '') === 'paid', 'Confirmed & paid booking payload payment_status is "paid"');
assertCondition((float) ($pPaid['balance_amount'] ?? -1) === 0.0, 'Confirmed & paid booking payload balance_amount is 0.00');
assertCondition(($pPaid['balance_payment_status'] ?? '') === 'Paid', 'Confirmed & paid booking payload balance_payment_status is "Paid"');

// Clean up test booking
Database::run("DELETE FROM payments WHERE booking_id = ?", [$unpaidBkId]);
Database::run("DELETE FROM bookings WHERE id = ?", [$unpaidBkId]);


// --------------------------------------------------------------------
// TEST 2: Admin Overview Complaints Action Button & Deletion
// --------------------------------------------------------------------
echo "\n2. Testing Admin Overview Complaints Action Button & Deletion...\n";

// 2.1 Verify isReviewed logic
assertCondition(Dispute::isReviewed('rejected'), 'Status "rejected" is recognized as reviewed (Action: Delete)');
assertCondition(Dispute::isReviewed('resolved_refund'), 'Status "resolved_refund" is recognized as reviewed (Action: Delete)');
assertCondition(Dispute::isReviewed('resolved_warning'), 'Status "resolved_warning" is recognized as reviewed (Action: Delete)');
assertCondition(!Dispute::isReviewed('open'), 'Status "open" is recognized as NOT reviewed (Action: Review)');
assertCondition(!Dispute::isReviewed('reviewing'), 'Status "reviewing" is recognized as NOT reviewed (Action: Review)');

// 2.2 Create a dummy complaint to test deletion
$testDisputeBkId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount, payment_stage,
        status, created_at
    ) VALUES (1210, 2475, '2026-12-25', 1, 400.00, 400.00, 0.00, 'paid', 'completed', NOW())
");

$testDisputeId = Dispute::create([
    'booking_id' => $testDisputeBkId,
    'user_id' => 2475,
    'guide_id' => 3,
    'problem_type' => 'other',
    'amount_requested' => null,
    'description' => 'Test complaint deletion workflow',
]);

// Mark dispute as reviewed (rejected)
Dispute::resolve($testDisputeId, 'rejected', 'Testing complaint deletion');
$dispCheck = Dispute::find($testDisputeId);
assertCondition(($dispCheck['status'] ?? '') === 'rejected', 'Test complaint status is "rejected"');
assertCondition(Dispute::isReviewed($dispCheck), 'Test complaint isReviewed returns true');

// 2.3 Attempt deletion via AdminController
$admin = new AdminController();
$_SESSION['_csrf'] = 'valid-test-csrf-token';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_POST = ['_token' => 'valid-test-csrf-token'];

ob_start();
$admin->deleteDispute((string) $testDisputeId);
$deleteRes = json_decode(ob_get_clean(), true);

assertCondition(!empty($deleteRes['success']), 'AdminController::deleteDispute successfully deleted reviewed complaint');
assertCondition(Dispute::find($testDisputeId) === null, 'Dispute record is completely removed from database');

// 2.4 Verify unreviewed dispute CANNOT be deleted
$unreviewedDispId = Dispute::create([
    'booking_id' => $testDisputeBkId,
    'user_id' => 2475,
    'guide_id' => 3,
    'problem_type' => 'other',
    'amount_requested' => null,
    'description' => 'Unreviewed dispute test',
]);

ob_start();
$admin->deleteDispute((string) $unreviewedDispId);
$blockedRes = json_decode(ob_get_clean(), true);

assertCondition(empty($blockedRes['success']), 'AdminController::deleteDispute blocks deletion of unreviewed complaint');
assertCondition(Dispute::find($unreviewedDispId) !== null, 'Unreviewed dispute remains safely in database');

// Clean up
Database::run("DELETE FROM disputes WHERE id = ?", [$unreviewedDispId]);
Database::run("DELETE FROM bookings WHERE id = ?", [$testDisputeBkId]);


// --------------------------------------------------------------------
// TEST 3: Nearest Hotel Distance & Location Matching Logic
// --------------------------------------------------------------------
echo "\n3. Testing Nearest Hotel Distance & Location Matching Logic...\n";

// 3.1 Test Ogtong Cave (Santa Fe, Bantayan Island - lat: 11.2185609, lng: 123.7285283)
$ogtongListing = Listing::find(1210);
assertCondition($ogtongListing !== null, 'Ogtong Cave listing exists');

$ogtongNearest = Listing::nearestHotel((float) $ogtongListing['latitude'], (float) $ogtongListing['longitude'], 1210, 35.0);
assertCondition($ogtongNearest === null, 'Listing::nearestHotel returns NULL for Ogtong Cave (no hotel within 35km)');

$nearestPayloadMethod = new ReflectionMethod($api, 'nearestHotelPayload');
$nearestPayloadMethod->setAccessible(true);
$ogtongPayload = $nearestPayloadMethod->invoke($api, $ogtongListing);

assertCondition(is_array($ogtongPayload), 'nearestHotelPayload returns structured response for Ogtong Cave');
assertCondition(($ogtongPayload['available'] ?? true) === false, 'nearestHotelPayload indicates available = false for distant experiences');

// 3.2 Test Listing close to Lapu-Lapu City (e.g. 10,000 Roses Cafe / Mactan - lat: 10.2521909, lng: 123.9494748)
$mactanListing = Listing::find(1208); // 10,000 Roses Cafe
assertCondition($mactanListing !== null, '10,000 Roses Cafe listing exists');

$mactanNearest = Listing::nearestHotel((float) $mactanListing['latitude'], (float) $mactanListing['longitude'], 1208, 35.0);
assertCondition($mactanNearest !== null, 'Listing::nearestHotel finds a nearby hotel for 10,000 Roses Cafe');
assertCondition((int) ($mactanNearest['id'] ?? 0) === 1211, 'Nearest hotel is Bluewater Maribago Beach Resort (ID 1211)');
assertCondition((float) ($mactanNearest['distance_km'] ?? 999) < 20.0, 'Calculated distance is genuinely close (< 20 km)');

$mactanPayload = $nearestPayloadMethod->invoke($api, $mactanListing);
assertCondition(($mactanPayload['available'] ?? false) === true, 'nearestHotelPayload indicates available = true for close experiences');
assertCondition(($mactanPayload['title'] ?? '') === 'Bluewater Maribago Beach Resort', 'Payload contains genuine hotel title');

// 3.3 Test nearestHotels sorting
$nearbyHotels = Listing::nearestHotels((float) $mactanListing['latitude'], (float) $mactanListing['longitude'], 1208, 35.0);
assertCondition(!empty($nearbyHotels), 'Listing::nearestHotels returns array of nearby hotels');
if (count($nearbyHotels) > 1) {
    assertCondition($nearbyHotels[0]['distance_km'] <= $nearbyHotels[1]['distance_km'], 'Hotels are properly ordered from nearest to farthest');
}

// 3.4 Test coordinate validation excludes invalid coordinates
assertCondition(Listing::nearestHotel(0.0, 0.0, 0, 35.0) === null, 'Invalid coordinate (0, 0) is rejected');
assertCondition(Listing::nearestHotel(-999.0, 999.0, 0, 35.0) === null, 'Out of bounds coordinates are rejected');

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "======================================================\n";
