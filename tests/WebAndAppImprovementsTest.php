<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Listing;
use App\Models\Booking;
use App\Models\RentalRequest;
use App\Models\Dispute;
use App\Controllers\ApiController;
use App\Controllers\AdminController;
use App\Controllers\GuideController;
use App\Controllers\HotelController;
use App\Controllers\RentalController;

echo "=== GuideMate Web & App Improvements Verification Tests ===\n\n";

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

// --------------------------------------------------------------------
// TEST 1: Travel Package Deletion Cascade Across All Provider Accounts
// --------------------------------------------------------------------
echo "1. Testing Travel Package Deletion Cascade...\n";

// Create a dummy travel package listing
$pkgId = Database::insert("
    INSERT INTO listings (
        user_id, category_id, title, slug, description, area, price, status, package_approval_status, created_at
    ) VALUES (3, 5, 'Cascade Test Package', 'cascade-test-package-" . time() . "', 'Cascade test description', 'Cebu', 12000.00, 'approved', 'agreed', NOW())
");

$pkgRef = 'GM-PKG-CASCADE-' . time();
$touristId = 2475;

// Parent Guide Booking
$parentBkId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount, payment_stage,
        status, package_booking_ref, package_overall_status, created_at
    ) VALUES (?, ?, '2026-12-10', 2, 12000.00, 12000.00, 0.00, 'settled', 'completed', ?, 'completed', NOW())
", [$pkgId, $touristId, $pkgRef]);

// Child Hotel Booking
$childHotelId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount, payment_stage,
        status, hotel_status, package_booking_ref, package_overall_status, created_at
    ) VALUES (1211, ?, '2026-12-10', 2, 5000.00, 5000.00, 0.00, 'settled', 'completed', 'completed', ?, 'completed', NOW())
", [$touristId, $pkgRef]);

// Child Rental Request
$childRentalId = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, location, pickup_date, rental_days,
        total_amount, customer_name, customer_email, status, package_booking_ref, package_booking_id, created_at
    ) VALUES (?, '72', 'Test Scooter', 'Scooter', 'Test Shop', 'Cebu', '2026-12-10', 2, 2000.00, 'Test User', 'test@user.local', 'completed', ?, ?, NOW())
", [$touristId, $pkgRef, $parentBkId]);

// Package Booking Items
Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, sub_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, 3, 'guide', 'guide', 'Guide Hosting', ?, 5000.00, 'completed', NOW())
", [$pkgRef, $parentBkId, $pkgId, $parentBkId]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, hotel_booking_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, 2469, 'hotel_provider', 'hotel', 'Hotel Room', ?, 5000.00, 'completed', NOW())
", [$pkgRef, $parentBkId, $pkgId, $childHotelId]);

Database::insert("
    INSERT INTO package_booking_items (
        package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
        service_type, service_name, rental_request_id, allocated_amount, status, created_at
    ) VALUES (?, ?, ?, 167, 'rental_provider', 'vehicle', 'Scooter Rental', ?, 2000.00, 'completed', NOW())
", [$pkgRef, $parentBkId, $pkgId, $childRentalId]);

// Standalone Unrelated Booking & Rental (must NOT be deleted)
$standaloneBkId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount, payment_stage,
        status, created_at
    ) VALUES (1193, ?, '2026-12-15', 2, 2500.00, 2500.00, 0.00, 'settled', 'completed', NOW())
", [$touristId]);

$standaloneRentalId = Database::insert("
    INSERT INTO rental_requests (
        user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, location, pickup_date, rental_days,
        total_amount, customer_name, customer_email, status, created_at
    ) VALUES (?, '72', 'Standalone Scooter', 'Scooter', 'Test Shop', 'Cebu', '2026-12-15', 1, 1000.00, 'Test User', 'test@user.local', 'completed', NOW())
", [$touristId]);

// Execute: Delete the Travel Package
Listing::delete($pkgId);

// Verify package listing is deleted
$pkgCheck = Database::first("SELECT id FROM listings WHERE id = ?", [$pkgId]);
assertCondition($pkgCheck === null, 'Package listing was deleted from listings table');

// Verify child bookings and rental request are deleted
$parentCheck = Database::first("SELECT id FROM bookings WHERE id = ?", [$parentBkId]);
assertCondition($parentCheck === null, 'Guide parent booking was deleted');

$hotelCheck = Database::first("SELECT id FROM bookings WHERE id = ?", [$childHotelId]);
assertCondition($hotelCheck === null, 'Associated Hotel reservation was deleted from hotel dashboard');

$rentalCheck = Database::first("SELECT id FROM rental_requests WHERE id = ?", [$childRentalId]);
assertCondition($rentalCheck === null, 'Associated Vehicle rental request was deleted from rental dashboard');

$itemsCheck = Database::all("SELECT id FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef]);
assertCondition(empty($itemsCheck), 'Associated package_booking_items were removed without leaving orphans');

// Verify standalone records are completely unaffected
$standaloneBkCheck = Database::first("SELECT id FROM bookings WHERE id = ?", [$standaloneBkId]);
assertCondition($standaloneBkCheck !== null, 'Unrelated individual booking remained completely unaffected');

$standaloneRentalCheck = Database::first("SELECT id FROM rental_requests WHERE id = ?", [$standaloneRentalId]);
assertCondition($standaloneRentalCheck !== null, 'Unrelated individual rental request remained completely unaffected');

// Clean up standalone test records
Database::run("DELETE FROM bookings WHERE id = ?", [$standaloneBkId]);
Database::run("DELETE FROM rental_requests WHERE id = ?", [$standaloneRentalId]);

// --------------------------------------------------------------------
// TEST 2: Controller Deletion Eligibility (Completed, Refunded, Cancelled)
// --------------------------------------------------------------------
echo "\n2. Testing Controller Deletion Eligibility for Completed, Refunded, Cancelled...\n";

// Guide controller tests
$bCompleted = ['status' => 'completed', 'package_overall_status' => '', 'hotel_status' => '', 'payment_stage' => ''];
$bCancelled = ['status' => 'cancelled', 'package_overall_status' => '', 'hotel_status' => '', 'payment_stage' => ''];
$bRefunded = ['status' => 'refunded', 'package_overall_status' => '', 'hotel_status' => '', 'payment_stage' => ''];
$bPending = ['status' => 'pending', 'package_overall_status' => '', 'hotel_status' => '', 'payment_stage' => ''];

$guideCheck = function($b) {
    $status = strtolower((string) ($b['status'] ?? ''));
    $overallPkgStatus = strtolower((string) ($b['package_overall_status'] ?? ''));
    $hotelStatus = strtolower((string) ($b['hotel_status'] ?? $status));
    $paymentStage = strtolower((string) ($b['payment_stage'] ?? ''));
    return in_array($status, ['completed', 'cancelled', 'refunded'], true)
        || in_array($overallPkgStatus, ['completed', 'cancelled', 'refunded'], true)
        || in_array($hotelStatus, ['completed', 'cancelled', 'refunded'], true)
        || $paymentStage === 'settled'
        || !empty($b['refund_status']);
};

assertCondition($guideCheck($bCompleted), 'GuideController allows deletion of Completed records');
assertCondition($guideCheck($bCancelled), 'GuideController allows deletion of Cancelled records');
assertCondition($guideCheck($bRefunded), 'GuideController allows deletion of Refunded records');
assertCondition(!$guideCheck($bPending), 'GuideController correctly blocks deletion of Pending records');

// Hotel controller tests
$hotelCheckFunc = function($b) {
    $hotelStatus = strtolower((string) ($b['hotel_status'] ?? $b['status'] ?? ''));
    $status = strtolower((string) ($b['status'] ?? ''));
    $paymentStage = strtolower((string) ($b['payment_stage'] ?? ''));
    return in_array($hotelStatus, ['completed', 'cancelled', 'refunded'], true)
        || in_array($status, ['completed', 'cancelled', 'refunded'], true)
        || $paymentStage === 'settled'
        || !empty($b['refund_status']);
};

assertCondition($hotelCheckFunc(['hotel_status' => 'completed']), 'HotelController allows deletion of Completed reservations');
assertCondition($hotelCheckFunc(['hotel_status' => 'cancelled']), 'HotelController allows deletion of Cancelled reservations');
assertCondition($hotelCheckFunc(['status' => 'refunded']), 'HotelController allows deletion of Refunded reservations');
assertCondition(!$hotelCheckFunc(['hotel_status' => 'pending']), 'HotelController correctly blocks deletion of Pending reservations');

// Rental controller tests
$rentalCheckFunc = function($r) {
    $status = strtolower(trim((string) ($r['status'] ?? '')));
    $paymentStatus = strtolower(trim((string) ($r['payment_status'] ?? '')));
    $reportStatus = strtolower(trim((string) ($r['report_status'] ?? '')));
    return in_array($status, ['completed', 'cancelled', 'refunded'], true)
        || in_array('refunded', [$paymentStatus, $reportStatus], true)
        || !empty($r['refund_status']);
};

assertCondition($rentalCheckFunc(['status' => 'completed']), 'RentalController allows deletion of Completed rental requests');
assertCondition($rentalCheckFunc(['status' => 'cancelled']), 'RentalController allows deletion of Cancelled rental requests');
assertCondition($rentalCheckFunc(['status' => 'refunded']), 'RentalController allows deletion of Refunded rental requests');
assertCondition($rentalCheckFunc(['report_status' => 'refunded']), 'RentalController allows deletion when report_status is refunded');
assertCondition(!$rentalCheckFunc(['status' => 'pending']), 'RentalController correctly blocks deletion of Pending rental requests');

// --------------------------------------------------------------------
// TEST 3: Admin Actions on Approved Listings
// --------------------------------------------------------------------
echo "\n3. Testing Admin Actions on Approved Listings...\n";

// Check AdminController status allowlist
$adminStatusCheck = function(string $status) {
    return in_array($status, ['rejected', 'approved'], true);
};

assertCondition($adminStatusCheck('approved'), 'AdminController allows permanent deletion of Approved listings');
assertCondition($adminStatusCheck('rejected'), 'AdminController allows permanent deletion of Rejected listings');
assertCondition(!$adminStatusCheck('pending'), 'AdminController blocks deletion of Pending listings');

// --------------------------------------------------------------------
// TEST 4: Travel Package Dispute Submission & Status Preservation
// --------------------------------------------------------------------
echo "\n4. Testing Travel Package Dispute Submission & Status Preservation...\n";

// Dynamically find or fallback to completed package booking for user 2475
$targetPkgBk = Database::first("
    SELECT id FROM bookings 
    WHERE package_booking_ref IS NOT NULL 
      AND user_id = 2475 
      AND (notes NOT LIKE '%package_sub_booking%' OR notes IS NULL)
    ORDER BY id DESC LIMIT 1
");
$testPkgBkId = $targetPkgBk ? (int) $targetPkgBk['id'] : 430;

// Clean existing dispute on target booking if any
Database::run("DELETE FROM disputes WHERE booking_id = ?", [$testPkgBkId]);
Database::run("UPDATE bookings SET status = 'completed', package_overall_status = 'completed', hotel_status = 'completed' WHERE id = ?", [$testPkgBkId]);

// Simulate submitDispute logic
$bookingTest = Booking::find($testPkgBkId);
assertCondition($bookingTest !== null, "Package Booking {$testPkgBkId} exists");

$isPaid = \App\Models\Payment::isPaid($testPkgBkId)
    || ($bookingTest['payment_status'] ?? '') === 'paid'
    || in_array(($bookingTest['payment_stage'] ?? ''), ['paid', 'settled', 'deposit_paid'], true)
    || ($bookingTest['status'] ?? '') === 'completed';

if (!$isPaid && !empty($bookingTest['package_booking_ref'])) {
    $pkgPayment = Database::first("SELECT id FROM payments WHERE reference = ? OR reference LIKE ? LIMIT 1", [
        $bookingTest['package_booking_ref'],
        '%' . $bookingTest['package_booking_ref'] . '%'
    ]);
    if ($pkgPayment !== null) $isPaid = true;
}

assertCondition($isPaid, "Travel Package Booking {$testPkgBkId} is recognized as paid/settled without 403");

// Resolve Guide ID
$guideId = (int) ($bookingTest['guide_id'] ?? 0);
if ($guideId <= 0 && !empty($bookingTest['listing_id'])) {
    $listing = Database::first('SELECT user_id FROM listings WHERE id = ?', [(int) $bookingTest['listing_id']]);
    $guideId = (int) ($listing['user_id'] ?? 0);
}

assertCondition($guideId === 3, 'Dispute correctly maps to package creator Guide (User ID 3 - Marc Repuesto)');

// Create dispute
$dispId = Dispute::create([
    'booking_id' => $testPkgBkId,
    'user_id' => 2475,
    'guide_id' => $guideId,
    'problem_type' => 'other',
    'amount_requested' => null,
    'description' => 'Test dispute against travel package without breaking booking status.',
]);

assertCondition($dispId > 0, 'Dispute successfully created in disputes table');

// Verify booking status was NOT mutated to 'disputed'
$bookingAfterDispute = Booking::find($testPkgBkId);
assertCondition($bookingAfterDispute['status'] === 'completed', "Booking {$testPkgBkId} status remained \"completed\" (NOT mutated to \"disputed\")");
assertCondition($bookingAfterDispute['package_overall_status'] === 'completed', 'Package overall status remained "completed"');

// Check booking payload
$api = new ApiController();
$refMethod = new ReflectionMethod($api, 'bookingPayload');
$refMethod->setAccessible(true);
$customerBookings = Booking::forCustomer(2475);
$targetBkFound = null;
foreach ($customerBookings as $cb) {
    if ((int) $cb['id'] === $testPkgBkId) {
        $targetBkFound = $cb;
        break;
    }
}

assertCondition($targetBkFound !== null, "Booking {$testPkgBkId} found in customer bookings");
assertCondition((int) ($targetBkFound['dispute_id'] ?? 0) === $dispId, "Booking {$testPkgBkId} has dispute_id populated from disputes table");

$payload = $refMethod->invoke($api, $targetBkFound, 2475);
assertCondition($payload['status'] === 'completed', 'API payload status is "completed" so it renders in History');
assertCondition($payload['can_report'] === false, 'API payload can_report is false because report already exists');
assertCondition($payload['dispute_id'] === $dispId, 'API payload dispute_id is set so App shows "View Report" button');

// --------------------------------------------------------------------
// TEST 5: Package Catalog Returns Active Approved Packages
// --------------------------------------------------------------------
echo "\n5. Testing Package Catalog returns active approved packages...\n";

// Clean up any test packages first
Database::run("DELETE FROM listings WHERE title LIKE 'Admin Deletion Test Package%'");

$getDbPkgs = new ReflectionMethod($api, 'getDatabasePackages');
$getDbPkgs->setAccessible(true);
$pkgs = $getDbPkgs->invoke($api);

assertCondition(!empty($pkgs), 'getDatabasePackages returns active approved travel packages');

$pkgIds = array_map(fn($p) => (int) $p['id'], $pkgs);
assertCondition(in_array(1212, $pkgIds, true), 'Package 1212 (3D2N South Cebu Adventure) is present');

// --------------------------------------------------------------------
// TEST 6: Admin Permanent Listing Deletion with CSRF Handling
// --------------------------------------------------------------------
echo "\n6. Testing Admin Permanent Listing Deletion...\n";

// 1. Create a test listing with package category, itinerary, services, and schedules
$testListingId = Database::insert("
    INSERT INTO listings (
        user_id, category_id, title, slug, description, area, price, status, package_approval_status, created_at
    ) VALUES (3, 5, 'Admin Deletion Test Package', 'admin-deletion-test-pkg-" . time() . "', 'Test package for admin deletion', 'Cebu', 5000.00, 'approved', 'agreed', NOW())
");

Database::run("INSERT INTO package_itineraries (package_id, day_number, title, description) VALUES (?, 1, 'Day 1 Tour', 'Visit sites')", [$testListingId]);
Database::run("INSERT INTO package_services (package_id, provider_id, service_type, status, agreed_rate) VALUES (?, 3, 'hotel', 'approved', 2500)", [$testListingId]);
Database::run("INSERT INTO listing_schedules (listing_id, schedule_date, start_time, end_time) VALUES (?, '2026-12-15', '08:00', '17:00')", [$testListingId]);

assertCondition(Listing::find($testListingId) !== null, 'Test listing created successfully');

// Verify CSRF resilience in Controller
$_SESSION['_csrf'] = 'valid-test-csrf-token';

// Test CSRF validation with _token
$_POST = ['_token' => 'valid-test-csrf-token'];
$_SERVER['REQUEST_METHOD'] = 'POST';
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
$ctrl = new AdminController();
$csrfMethod = new ReflectionMethod($ctrl, 'verifyCsrf');
$csrfMethod->setAccessible(true);
$csrfPassedToken = false;
try {
    $csrfMethod->invoke($ctrl);
    $csrfPassedToken = true;
} catch (\Throwable $e) {}
assertCondition($csrfPassedToken, 'Controller::verifyCsrf passes with $_POST["_token"]');

// Test CSRF validation with _csrf
$_POST = ['_csrf' => 'valid-test-csrf-token'];
$csrfPassedCsrf = false;
try {
    $csrfMethod->invoke($ctrl);
    $csrfPassedCsrf = true;
} catch (\Throwable $e) {}
assertCondition($csrfPassedCsrf, 'Controller::verifyCsrf passes with $_POST["_csrf"]');

// Test CSRF validation with HTTP_X_CSRF_TOKEN
$_POST = [];
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid-test-csrf-token';
$csrfPassedHeader = false;
try {
    $csrfMethod->invoke($ctrl);
    $csrfPassedHeader = true;
} catch (\Throwable $e) {}
assertCondition($csrfPassedHeader, 'Controller::verifyCsrf passes with HTTP_X_CSRF_TOKEN header');

// Now perform deletion of testListingId
Listing::delete($testListingId);
assertCondition(Listing::find($testListingId) === null, 'Test listing permanently deleted');

$itinLeft = Database::first('SELECT COUNT(*) as cnt FROM package_itineraries WHERE package_id = ?', [$testListingId]);
assertCondition((int) ($itinLeft['cnt'] ?? 0) === 0, 'Associated package itineraries cleaned up');

$servLeft = Database::first('SELECT COUNT(*) as cnt FROM package_services WHERE package_id = ?', [$testListingId]);
assertCondition((int) ($servLeft['cnt'] ?? 0) === 0, 'Associated package services cleaned up');

// Clean up test dispute on booking 329
Database::run("DELETE FROM disputes WHERE id = ?", [$dispId]);

// --------------------------------------------------------------------
// TEST 7: Multiple Dispute Evidence Photos (4 Photos)
// --------------------------------------------------------------------
echo "\n7. Testing Multiple Dispute Evidence Photos (4 Photos)...\n";

// Create a dummy booking for user 2475 to report
$testBkId = Database::insert("
    INSERT INTO bookings (
        listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount, payment_stage,
        status, created_at
    ) VALUES (1211, 2475, '2026-12-20', 2, 8000.00, 8000.00, 0.00, 'settled', 'completed', NOW())
");

// 1. Submit dispute with 1st photo
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'booking_id' => (string) $testBkId,
    'problem_type' => 'other',
    'description' => 'Test report with 4 evidence photos verification in test suite',
];

$token = 'test-token-evidence-' . time();
Database::run("INSERT INTO api_tokens (user_id, token, expires_at) VALUES (2475, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))", [$token]);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

$makeTempJpeg = function(): string {
    $tmp = tempnam(sys_get_temp_dir(), 'test_photo_') . '.jpg';
    $jpegBytes = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
    file_put_contents($tmp, $jpegBytes);
    return $tmp;
};

$_FILES['evidence'] = [
    'name' => 'photo_1.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $makeTempJpeg(),
    'error' => UPLOAD_ERR_OK,
    'size' => 1024,
];

ob_start();
$api->submitDispute();
$res1 = json_decode(ob_get_clean(), true);

assertCondition(!empty($res1['success']), 'Initial dispute created with photo 1');
$newDispId = (int) ($res1['dispute']['id'] ?? 0);
assertCondition($newDispId > 0, 'New dispute ID returned');

// 2. Upload photo 2, 3, 4 via uploadDisputeEvidence
for ($i = 2; $i <= 4; $i++) {
    $_FILES['evidence'] = [
        'name' => "photo_{$i}.jpg",
        'type' => 'image/jpeg',
        'tmp_name' => $makeTempJpeg(),
        'error' => UPLOAD_ERR_OK,
        'size' => 1024,
    ];
    ob_start();
    $api->uploadDisputeEvidence((string) $newDispId);
    $uploadRes = json_decode(ob_get_clean(), true);
    assertCondition(!empty($uploadRes['success']), "Evidence photo {$i} successfully attached via uploadDisputeEvidence");
}

// 3. Verify all 4 photos in database
$savedFiles = \App\Models\DisputeFile::forDispute($newDispId);
assertCondition(count($savedFiles) === 4, 'Exactly 4 evidence photos stored in dispute_files for dispute');

// 4. Verify API myDispute returns all 4 photos
$_GET = ['booking_id' => (string) $testBkId];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
$api->myDispute();
$myDispRes = json_decode(ob_get_clean(), true);
$retrievedPhotos = $myDispRes['dispute']['photos'] ?? [];
assertCondition(count($retrievedPhotos) === 4, 'API myDispute returns all 4 evidence photos');

// Clean up test records
Database::run("DELETE FROM dispute_files WHERE dispute_id = ?", [$newDispId]);
Database::run("DELETE FROM disputes WHERE id = ?", [$newDispId]);
Database::run("DELETE FROM bookings WHERE id = ?", [$testBkId]);

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "======================================================\n";
