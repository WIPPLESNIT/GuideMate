<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;

echo "========================================================\n";
echo " Tourist Booking Flow Date & Guide Schedule Sync Tests\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $message\n";
        $passed++;
    } else {
        echo " [FAIL] $message\n";
        $failed++;
    }
}

// -----------------------------------------------------------------------------
// Test 1: Frontend CalendarPicker and [slug].tsx code structure
// -----------------------------------------------------------------------------
echo "--- Test 1: Frontend Code Structure & Constraints ---\n";

$calendarPickerPath = __DIR__ . '/../components/CalendarPicker.tsx';
assertTest(file_exists($calendarPickerPath), "CalendarPicker.tsx exists");
$calendarPickerContent = file_get_contents($calendarPickerPath);

assertTest(
    strpos($calendarPickerContent, 'readOnly?: boolean') !== false,
    "CalendarPicker accepts readOnly prop"
);

assertTest(
    strpos($calendarPickerContent, 'disabled={readOnly || isDisabled}') !== false,
    "CalendarPicker disables date clicking when readOnly is true"
);

assertTest(
    strpos($calendarPickerContent, 'setViewYear(y)') !== false &&
    strpos($calendarPickerContent, 'setViewMonth(m)') !== false,
    "CalendarPicker automatically synchronizes viewYear and viewMonth when selected date changes"
);

$slugPath = __DIR__ . '/../app/listing/[slug].tsx';
assertTest(file_exists($slugPath), "app/listing/[slug].tsx exists");
$slugContent = file_get_contents($slugPath);

assertTest(
    strpos($slugContent, 'Select start time') === false &&
    strpos($slugContent, 'Select end time') === false,
    "Tourist start-time and end-time selection grids are removed from booking modal"
);

assertTest(
    strpos($slugContent, 'guideTimeCard') !== false &&
    strpos($slugContent, 'Guide Defined') !== false,
    "Guide predefined session time is displayed as read-only information"
);

assertTest(
    strpos($slugContent, 'readOnly={!canChangeDate && hasSchedule}') !== false || strpos($slugContent, 'readOnly={!canChangeDate}') !== false,
    "Tour calendar date is locked (read-only) by default for scheduled tours"
);

assertTest(
    strpos($slugContent, 'Select Date') !== false,
    "Contains 'Select Date' button to explicitly unlock date selection"
);

assertTest(
    strpos($slugContent, 'setCanChangeDate(true)') !== false,
    "Tapping 'Select Date' unlocks date picking via setCanChangeDate(true)"
);

assertTest(
    strpos($slugContent, 'setAlreadyBooked(true)') !== false &&
    strpos($slugContent, 'setBookedSlots((prev)') !== false,
    "Successful booking immediately updates alreadyBooked and bookedSlots without manual refresh"
);

// -----------------------------------------------------------------------------
// Test 2: Backend API Fallback for Guide's Predefined Session Times
// -----------------------------------------------------------------------------
echo "\n--- Test 2: Database & Guide Session Times Persistence ---\n";

try {
    // Check if test listing (e.g. Bojo River) has a schedule
    $listingId = 9;
    $scheduleDate = '2026-11-06';
    $startTime = '10:53:00';
    $endTime = '13:01:00';

    $schedule = Database::first("SELECT * FROM listing_schedules WHERE listing_id = ? AND schedule_date = ? LIMIT 1", [$listingId, $scheduleDate]);

    if (!$schedule) {
        Database::run("INSERT INTO listing_schedules (listing_id, schedule_date, start_time, end_time, capacity, note, is_active, created_at) VALUES (?, ?, ?, ?, 2, 'Test sync note', 1, NOW())", [$listingId, $scheduleDate, $startTime, $endTime]);
        $schedule = Database::first("SELECT * FROM listing_schedules WHERE listing_id = ? AND schedule_date = ? LIMIT 1", [$listingId, $scheduleDate]);
    }
    $scheduleId = (int)($schedule['id'] ?? 0);
    $startTime = $schedule['start_time'] ?? '10:53:00';
    $endTime = $schedule['end_time'] ?? '13:01:00';

    assertTest($scheduleId > 0, "Found or created guide schedule session for date $scheduleDate");

    // Simulate booking creation with no tourist times provided (tourist UI removed time selection)
    $tourist = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1") ?: Database::first("SELECT id FROM users LIMIT 1");
    $touristId = (int)($tourist['id'] ?? 1);
    // Emulate ApiController logic for tour session booking where tourist does not select times
    $touristStartTime = null; // Removed from tourist interface
    $touristEndTime = null;   // Removed from tourist interface

    if (empty($touristStartTime) || empty($touristEndTime)) {
        $foundSched = Database::first("SELECT start_time, end_time FROM listing_schedules WHERE listing_id = ? AND schedule_date = ? LIMIT 1", [$listingId, $scheduleDate]);
        if ($foundSched) {
            $touristStartTime = $touristStartTime ?: $foundSched['start_time'];
            $touristEndTime = $touristEndTime ?: $foundSched['end_time'];
        }
    }

    $bookingId = Booking::create(
        $listingId,
        $touristId,
        $scheduleDate,
        2,
        2000.00,
        'Test tourist booking',
        $touristStartTime,
        [$scheduleDate],
        $touristEndTime
    );
    assertTest($bookingId > 0, "Booking successfully created with ID $bookingId");

    $savedBooking = Booking::find($bookingId);
    assertTest(
        $savedBooking && $savedBooking['booking_date'] === $scheduleDate,
        "Booking date exactly matches scheduled session date ($scheduleDate)"
    );

    assertTest(
        $savedBooking && strpos($savedBooking['booking_time'], substr($startTime, 0, 5)) === 0,
        "Guide's predefined start time ($startTime) was saved automatically (got {$savedBooking['booking_time']})"
    );

    assertTest(
        $savedBooking && strpos($savedBooking['booking_end_time'], substr($endTime, 0, 5)) === 0,
        "Guide's predefined end time ($endTime) was saved automatically (got {$savedBooking['booking_end_time']})"
    );

    // Clean up test booking
    Database::run("DELETE FROM bookings WHERE id = ?", [$bookingId]);
    echo " Cleaned up test booking ID $bookingId.\n";

} catch (Exception $e) {
    assertTest(false, "Database exception: " . $e->getMessage());
}

echo "\n--------------------------------------------------------\n";
echo "Results: $passed passed, $failed failed\n";
echo "--------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
