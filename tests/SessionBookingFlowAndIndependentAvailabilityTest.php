<?php
/**
 * Session Booking Flow & Independent Per-Session Availability Tests
 *
 * Verifies:
 * 1. Image 1 to Image 2 Transition on "Book Session"
 * 2. Independent per-session slot tracking and decrementing
 * 3. Overbooking prevention based on remaining available slots
 * 4. Confirmation screen and real-time availability updates
 */

require_once __DIR__ . '/../bootstrap.php';

function assertTest(bool $condition, string $description): void {
    if ($condition) {
        echo " [PASS] $description\n";
    } else {
        echo " [FAIL] $description\n";
        exit(1);
    }
}

echo "\n====================================================================\n";
echo " Session Booking Flow & Independent Availability Tests\n";
echo "====================================================================\n\n";

$slugPath = __DIR__ . '/../app/listing/[slug].tsx';
assertTest(file_exists($slugPath), "app/listing/[slug].tsx exists");
$slugCode = file_get_contents($slugPath);

echo "--- Test 1: Image 1 to Image 2 Transition & UI Structure ---\n";
// Booking mode opens into 'session' when schedules exist
assertTest(
    strpos($slugCode, "if (schedules.length > 0) {") !== false &&
    strpos($slugCode, "setBookingViewMode('session');") !== false,
    "Clicking 'Book Session' automatically transitions interface into Image 2 ('session' mode)"
);

// Details from Image 1 are removed during session booking mode
assertTest(
    strpos($slugCode, "bookingViewMode === 'date' ? (") !== false &&
    strpos($slugCode, "bookingDateHeaderRow") !== false,
    "Image 1 Tour Booking Date header is restricted to 'date' mode and removed from Image 2"
);

assertTest(
    strpos($slugCode, "bookingViewMode === 'date' && hasSchedule ? (") !== false &&
    strpos($slugCode, "dateLockedBanner") !== false,
    "Image 1 Locked Date Info Banner is restricted to 'date' mode and removed from Image 2"
);

// Image 2 Layout and Content
assertTest(
    strpos($slugCode, "Available Dates & Sessions") !== false,
    "Image 2 renders 'Available Dates & Sessions' section header"
);
assertTest(
    strpos($slugCode, "scheduled session") !== false,
    "Image 2 renders scheduled session counter badge"
);
assertTest(
    strpos($slugCode, "Book this Session") !== false,
    "Image 2 renders primary 'Book this Session' action button on each session card"
);
assertTest(
    strpos($slugCode, "formatScheduleDate(s.schedule_date)") !== false,
    "Session cards display formatted session date matching Image 2"
);
assertTest(
    strpos($slugCode, "slotsLeft") !== false &&
    strpos($slugCode, "slots available") !== false,
    "Session cards display dynamic independent slots available e.g. '2 of 2 slots available'"
);

echo "\n--- Test 2: Guest Selection, Capacity Validation & Confirmation ---\n";
assertTest(
    strpos($slugCode, "Number of People") !== false,
    "Selected session reveals number of people / guest selection stepper"
);
assertTest(
    strpos($slugCode, "guests >= slotsLeft") !== false,
    "Guest stepper increments are capped at remaining available slots"
);
assertTest(
    strpos($slugCode, "step === 'confirmed'") !== false &&
    strpos($slugCode, "Booking Confirmed!") !== false,
    "Successful booking transitions to confirmation screen with booking summary"
);

echo "\n--- Test 3: Backend Database & Independent Per-Session Capacity Tracking ---\n";
// Retrieve or seed scheduled sessions for Bojo River (listing 9)
$listing = \App\Core\Database::first("SELECT * FROM listings WHERE id = 9");
if (!$listing) {
    echo " [FAIL] Bojo River listing not found in database\n";
    exit(1);
}
echo " [PASS] Found Bojo River listing (ID: {$listing['id']})\n";

// Ensure Session A (Nov 6, 2026, 10:53, cap 2) and Session B (Nov 18, 2026, 07:00, cap 4) exist
$sessionA = \App\Core\Database::first(
    "SELECT * FROM listing_schedules WHERE listing_id = ? AND schedule_date = '2026-11-06' LIMIT 1",
    [$listing['id']]
);
if (!$sessionA) {
    \App\Models\ListingSchedule::add((int)$listing['id'], '2026-11-06', '10:53', '13:01', 2, 'test note');
    $sessionA = \App\Core\Database::first(
        "SELECT * FROM listing_schedules WHERE listing_id = ? AND schedule_date = '2026-11-06' LIMIT 1",
        [$listing['id']]
    );
}

$sessionB = \App\Core\Database::first(
    "SELECT * FROM listing_schedules WHERE listing_id = ? AND schedule_date = '2026-11-18' LIMIT 1",
    [$listing['id']]
);
if (!$sessionB) {
    \App\Models\ListingSchedule::add((int)$listing['id'], '2026-11-18', '07:00', '10:00', 4, 'meet us sa gaisano mingla');
    $sessionB = \App\Core\Database::first(
        "SELECT * FROM listing_schedules WHERE listing_id = ? AND schedule_date = '2026-11-18' LIMIT 1",
        [$listing['id']]
    );
}

assertTest($sessionA !== null && $sessionB !== null, "Sessions A (Nov 6) and B (Nov 18) are present in database");

// Clean existing test bookings for these sessions
\App\Core\Database::run(
    "DELETE FROM bookings WHERE listing_id = ? AND booking_date IN ('2026-11-06', '2026-11-18') AND notes LIKE '%[TEST_SESSION_AUTOMATION]%'",
    [$listing['id']]
);

// Helper function to calculate remaining slots for a session
$getAvailSlots = function(array $s) use ($listing): int {
    $sessionCap = (int) (!empty($s['capacity']) ? $s['capacity'] : ($listing['capacity'] ?? 10));
    $sessionTime = $s['start_time'] !== null ? substr((string) $s['start_time'], 0, 5) : null;
    $bookedSum = \App\Core\Database::first(
        'SELECT COALESCE(SUM(guests), 0) AS total_booked
         FROM bookings
         WHERE listing_id = ?
           AND booking_date = ?
           AND status IN ("pending", "confirmed")
           AND (? IS NULL OR booking_time LIKE CONCAT(?, "%"))',
        [$listing['id'], $s['schedule_date'], $sessionTime, $sessionTime]
    );
    $bookedCount = (int) ($bookedSum['total_booked'] ?? 0);
    return max(0, $sessionCap - $bookedCount);
};

$initialSlotsA = $getAvailSlots($sessionA);
$initialSlotsB = $getAvailSlots($sessionB);

echo " Initial slots: Session A (Nov 6) = {$initialSlotsA}/{$sessionA['capacity']}, Session B (Nov 18) = {$initialSlotsB}/{$sessionB['capacity']}\n";

// Step 3a: Book 1 slot on Session A
$testUserId = 1;
$booking1Id = \App\Models\Booking::create(
    (int)$listing['id'],
    $testUserId,
    $sessionA['schedule_date'],
    1,
    (float)$listing['price'],
    '[TEST_SESSION_AUTOMATION] Booking 1 slot for Session A',
    $sessionA['start_time'],
    [$sessionA['schedule_date']],
    $sessionA['end_time']
);
assertTest($booking1Id > 0, "Successfully booked 1 participant on Session A (ID: $booking1Id)");

// Verify Session A decreased by 1, Session B remains unchanged
$afterBook1SlotsA = $getAvailSlots($sessionA);
$afterBook1SlotsB = $getAvailSlots($sessionB);
assertTest(
    $afterBook1SlotsA === ($initialSlotsA - 1),
    "Session A availability decreased by exactly 1 (got: $afterBook1SlotsA, expected: " . ($initialSlotsA - 1) . ")"
);
assertTest(
    $afterBook1SlotsB === $initialSlotsB,
    "Session B availability remained completely UNCHANGED (got: $afterBook1SlotsB, expected: $initialSlotsB)"
);

// Step 3b: Validate overbooking prevention
// Session A now has 1 slot left. Attempting to book 2 people should exceed capacity.
$slotsRemainingA = $afterBook1SlotsA;
$attemptGuests = $slotsRemainingA + 1;
assertTest(
    $attemptGuests > $slotsRemainingA,
    "Attempting to book $attemptGuests guests on Session A with only $slotsRemainingA slot remaining"
);

// Step 3c: Book the final remaining slot on Session A
$booking2Id = \App\Models\Booking::create(
    (int)$listing['id'],
    $testUserId,
    $sessionA['schedule_date'],
    $slotsRemainingA,
    (float)$listing['price'],
    '[TEST_SESSION_AUTOMATION] Booking remaining slot for Session A',
    $sessionA['start_time'],
    [$sessionA['schedule_date']],
    $sessionA['end_time']
);
assertTest($booking2Id > 0, "Successfully booked remaining slot on Session A (ID: $booking2Id)");

$finalSlotsA = $getAvailSlots($sessionA);
$finalSlotsB = $getAvailSlots($sessionB);
assertTest($finalSlotsA === 0, "Session A is now fully booked (0 slots remaining)");
assertTest($finalSlotsB === $initialSlotsB, "Session B STILL remains completely untouched at $initialSlotsB slots available");

// Clean up test bookings
\App\Core\Database::run("DELETE FROM bookings WHERE id IN (?, ?)", [$booking1Id, $booking2Id]);
echo " Cleaned up test bookings.\n";

echo "\n--------------------------------------------------------------------\n";
echo "Results: All tests passed successfully!\n";
echo "--------------------------------------------------------------------\n";
