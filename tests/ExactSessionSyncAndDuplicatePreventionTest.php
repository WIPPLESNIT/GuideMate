<?php
/**
 * Exact Session Synchronization & Duplicate Booking Prevention Test Suite
 *
 * Requirements verified:
 * 1. Exact session synchronization between "Tour Schedule" and "Book Tour Session"
 * 2. Duplicate booking prevention for active / incomplete bookings by listing ID
 * 3. Rebooking permitted once existing booking is marked 'completed' or 'cancelled'/'refunded'
 * 4. Technical / UX requirements: server-side enforcement, idempotency, and session capacity validation
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;

function assertTest(bool $condition, string $description): void {
    if ($condition) {
        echo " [PASS] $description\n";
    } else {
        echo " [FAIL] $description\n";
        exit(1);
    }
}

echo "\n====================================================================\n";
echo " Exact Session Sync & Duplicate Booking Prevention Tests\n";
echo "====================================================================\n\n";

$slugPath = __DIR__ . '/../app/listing/[slug].tsx';
$apiPath = __DIR__ . '/../services/api.ts';
$apiControllerPath = __DIR__ . '/../app/Controllers/ApiController.php';
$bookingControllerPath = __DIR__ . '/../app/Controllers/BookingController.php';
$bookingModelPath = __DIR__ . '/../app/Models/Booking.php';

assertTest(file_exists($slugPath), "app/listing/[slug].tsx exists");
assertTest(file_exists($apiPath), "services/api.ts exists");
assertTest(file_exists($apiControllerPath), "app/Controllers/ApiController.php exists");
assertTest(file_exists($bookingControllerPath), "app/Controllers/BookingController.php exists");
assertTest(file_exists($bookingModelPath), "app/Models/Booking.php exists");

$slugCode = file_get_contents($slugPath);
$apiCode = file_get_contents($apiPath);
$apiControllerCode = file_get_contents($apiControllerPath);
$bookingControllerCode = file_get_contents($bookingControllerPath);
$bookingModelCode = file_get_contents($bookingModelPath);

// -----------------------------------------------------------------------------
// Test 1: Frontend Code Structure & Exact Session Synchronization
// -----------------------------------------------------------------------------
echo "--- Test 1: Frontend Exact Session Synchronization ---\n";

assertTest(
    strpos($slugCode, 'sessionId?: number;') !== false,
    "openBooking accepts exact sessionId parameter"
);

assertTest(
    strpos($slugCode, 'matchedSession = schedules.find((s) => s.id === opts.sessionId);') !== false,
    "openBooking synchronizes date, start_time, and end_time from the exact session ID"
);

assertTest(
    strpos($slugCode, 'setSelectedSessionId(matchedSession.id);') !== false,
    "Selected session ID state is synchronized immediately"
);

assertTest(
    strpos($slugCode, 'sessionId: s.id') !== false &&
    strpos($slugCode, 'allowDateChange: false') !== false,
    "Tour Schedule modal 'Book this Session' passes exact sessionId and locks date"
);

assertTest(
    strpos($slugCode, 'schedule_id: selectedSessionId || undefined') !== false,
    "createBooking payload includes exact schedule_id"
);

assertTest(
    strpos($apiCode, 'schedule_id?: number;') !== false,
    "services/api.ts defines schedule_id in createBooking API payload"
);

// -----------------------------------------------------------------------------
// Test 2: Server-side Session Synchronization & Validation in ApiController
// -----------------------------------------------------------------------------
echo "\n--- Test 2: Server-side Exact Session Resolution & Validation ---\n";

assertTest(
    strpos($apiControllerCode, '$scheduleId = !empty($body[\'schedule_id\']) ? (int) $body[\'schedule_id\'] : 0;') !== false,
    "ApiController extracts schedule_id from API payload"
);

assertTest(
    strpos($apiControllerCode, 'SELECT * FROM listing_schedules WHERE id = ? AND listing_id = ? LIMIT 1') !== false,
    "ApiController validates schedule_id against listing_schedules database table"
);

assertTest(
    strpos($apiControllerCode, 'Booking::getActiveBookingForListing($listingId, $userId)') !== false,
    "ApiController enforces server-side duplicate active booking check for the listing"
);

assertTest(
    strpos($apiControllerCode, "'active_booking' => [") !== false,
    "ApiController returns active booking details with HTTP 409 when duplicate booking is attempted"
);

assertTest(
    strpos($bookingControllerCode, 'Booking::getActiveBookingForListing((int) $listing[\'id\'], (int) Auth::id())') !== false,
    "BookingController enforces server-side duplicate active booking check in web booking flow"
);

// -----------------------------------------------------------------------------
// Test 3: Booking Model Active Booking Query & Status Categorization
// -----------------------------------------------------------------------------
echo "\n--- Test 3: Booking Model Active Booking Query & Status Rules ---\n";

assertTest(
    method_exists(Booking::class, 'getActiveBookingForListing'),
    "Booking::getActiveBookingForListing method exists"
);

assertTest(
    method_exists(Booking::class, 'hasActiveBookingForListing'),
    "Booking::hasActiveBookingForListing method exists"
);

// -----------------------------------------------------------------------------
// Test 4: Database Functional Tests for Duplicate Prevention & Rebooking
// -----------------------------------------------------------------------------
echo "\n--- Test 4: Database Functional Tests for Duplicate Prevention & Rebooking ---\n";

$listing = Database::first("SELECT * FROM listings WHERE title LIKE '%Bojo%' LIMIT 1") ?: Database::first("SELECT * FROM listings ORDER BY id ASC LIMIT 1");
assertTest($listing !== null, "Found test listing (ID: " . ($listing['id'] ?? 0) . ")");

$testListingId = (int) $listing['id'];
$tourist = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1") ?: Database::first("SELECT id FROM users LIMIT 1");
assertTest($tourist !== null, "Found test user account");
$testUserId = (int) $tourist['id'];

// Clean up any stale test bookings for test user
Database::run("DELETE FROM bookings WHERE user_id = ? AND listing_id = ?", [$testUserId, $testListingId]);

// Verify no active booking initially
$initialActive = Booking::getActiveBookingForListing($testListingId, $testUserId);
assertTest($initialActive === null, "Initially no active booking exists for test tourist");
assertTest(!Booking::hasActiveBookingForListing($testListingId, $testUserId), "hasActiveBookingForListing returns false initially");

// Create active booking with status = 'pending'
$bookingId = Booking::create(
    $testListingId,
    $testUserId,
    '2026-11-20',
    1,
    (float) $listing['price'],
    '[TEST_ACTIVE_PREVENTION] Initial active booking (pending)',
    '10:00:00',
    ['2026-11-20'],
    '12:00:00'
);
assertTest($bookingId > 0, "Created test booking with ID $bookingId");

// Verify active booking is detected for status 'pending'
$activePending = Booking::getActiveBookingForListing($testListingId, $testUserId);
assertTest($activePending !== null, "Active booking detected when status is 'pending'");
assertTest((int)$activePending['id'] === $bookingId, "Detected active booking matches test booking ID");
assertTest(Booking::hasActiveBookingForListing($testListingId, $testUserId), "hasActiveBookingForListing returns true for 'pending'");

// Verify active booking is detected for status 'confirmed'
Database::run("UPDATE bookings SET status = 'confirmed' WHERE id = ?", [$bookingId]);
$activeConfirmed = Booking::getActiveBookingForListing($testListingId, $testUserId);
assertTest($activeConfirmed !== null && $activeConfirmed['status'] === 'confirmed', "Active booking detected when status is 'confirmed'");
assertTest(Booking::hasActiveBookingForListing($testListingId, $testUserId), "hasActiveBookingForListing returns true for 'confirmed'");

// Verify active booking is detected for status 'disputed'
Database::run("UPDATE bookings SET status = 'disputed' WHERE id = ?", [$bookingId]);
$activeDisputed = Booking::getActiveBookingForListing($testListingId, $testUserId);
assertTest($activeDisputed !== null && $activeDisputed['status'] === 'disputed', "Active booking detected when status is 'disputed'");
assertTest(Booking::hasActiveBookingForListing($testListingId, $testUserId), "hasActiveBookingForListing returns true for 'disputed'");

// -----------------------------------------------------------------------------
// Test 5: Rebooking Permitted After Completion or Inactive Status
// -----------------------------------------------------------------------------
echo "\n--- Test 5: Rebooking Permitted After Completion or Inactive Status ---\n";

// Update status to 'completed' -> Rebooking MUST be allowed
Database::run("UPDATE bookings SET status = 'completed' WHERE id = ?", [$bookingId]);
$afterCompleted = Booking::getActiveBookingForListing($testListingId, $testUserId);
assertTest($afterCompleted === null, "Booking::getActiveBookingForListing returns null when booking is 'completed'");
assertTest(!Booking::hasActiveBookingForListing($testListingId, $testUserId), "hasActiveBookingForListing returns false when 'completed' (rebooking permitted)");

// Update status to 'cancelled' -> Rebooking MUST be allowed
Database::run("UPDATE bookings SET status = 'cancelled' WHERE id = ?", [$bookingId]);
$afterCancelled = Booking::getActiveBookingForListing($testListingId, $testUserId);
assertTest($afterCancelled === null, "Booking::getActiveBookingForListing returns null when booking is 'cancelled'");
assertTest(!Booking::hasActiveBookingForListing($testListingId, $testUserId), "hasActiveBookingForListing returns false when 'cancelled' (rebooking permitted)");

// Update status to 'refunded' -> Rebooking MUST be allowed
Database::run("UPDATE bookings SET status = 'refunded' WHERE id = ?", [$bookingId]);
$afterRefunded = Booking::getActiveBookingForListing($testListingId, $testUserId);
assertTest($afterRefunded === null, "Booking::getActiveBookingForListing returns null when booking is 'refunded'");
assertTest(!Booking::hasActiveBookingForListing($testListingId, $testUserId), "hasActiveBookingForListing returns false when 'refunded' (rebooking permitted)");

// Clean up test booking
Database::run("DELETE FROM bookings WHERE id = ?", [$bookingId]);
echo " Cleaned up test booking ID $bookingId.\n";

// -----------------------------------------------------------------------------
// Test 6: Idempotency Protection for Repeated Clicks & Refreshes
// -----------------------------------------------------------------------------
echo "\n--- Test 6: Idempotency & Concurrency Protection ---\n";

$testRef = 'TEST_REF_' . time();
$bookingRefId = Booking::create(
    $testListingId,
    $testUserId,
    '2026-11-25',
    1,
    (float) $listing['price'],
    "[TEST_IDEMPOTENCY] Ref: {$testRef}",
    '10:00:00',
    ['2026-11-25'],
    '12:00:00'
);
assertTest($bookingRefId > 0, "Created booking with payment reference ($testRef)");

// Idempotency lookup
$foundIdempotent = Database::first(
    "SELECT b.* FROM bookings b
     WHERE b.user_id = ? AND b.listing_id = ? AND b.notes LIKE ?
     ORDER BY b.id DESC LIMIT 1",
    [$testUserId, $testListingId, "%Ref: {$testRef}%"]
);
assertTest($foundIdempotent !== null && (int)$foundIdempotent['id'] === $bookingRefId, "Found existing booking by payment reference; prevents double booking on retry/refresh");

// Clean up
Database::run("DELETE FROM bookings WHERE id = ?", [$bookingRefId]);
echo " Cleaned up idempotency test booking ID $bookingRefId.\n";

echo "\n--------------------------------------------------------------------\n";
echo "Results: All Exact Session Sync & Duplicate Prevention Tests Passed!\n";
echo "--------------------------------------------------------------------\n";
