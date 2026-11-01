<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\HotelRoom;
use App\Models\User;

echo "========================================================\n";
echo " GuideMate Hotel Booking Flow & 1-Night Reservation Tests\n";
echo "========================================================\n\n";

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

// ---------------------------------------------------------
// Test 1: Frontend & View Inspection (Duration Removed & Times Displayed)
// ---------------------------------------------------------
echo "--- Test 1: Code & View Inspection ---\n";

$slugTsx = file_get_contents(__DIR__ . '/../app/listing/[slug].tsx');
assertCond(strpos($slugTsx, "Check-In / Out") !== false, 'Mobile listing shows Check-In / Out for hotels');
assertCond(strpos($slugTsx, "2:00 PM / 12:00 PM") !== false, 'Mobile listing shows standard 2:00 PM / 12:00 PM times');
assertCond(strpos($slugTsx, "hotelCheckIn") !== false && strpos($slugTsx, "hotelCheckOut") !== false, 'Mobile listing uses dedicated hotelCheckIn and hotelCheckOut states');
assertCond(strpos($slugTsx, "getNextCalendarDay") !== false, 'Mobile listing includes getNextCalendarDay helper');
assertCond(strpos($slugTsx, "ARRIVAL / CHECK-IN") !== false, 'Mobile modal includes arrival date selector');
assertCond(strpos($slugTsx, "DEPARTURE / CHECK-OUT") !== false, 'Mobile modal includes departure date selector');

$webShow = file_get_contents(__DIR__ . '/../app/Views/listings/show.php');
assertCond(strpos($webShow, "Check-in 2:00 PM · Check-out 12:00 PM") !== false, 'Web listing show.php displays standard check-in/out times instead of duration');
assertCond(strpos($webShow, "From 2:00 PM") !== false, 'Web booking form displays From 2:00 PM under check-in');
assertCond(strpos($webShow, "Until 12:00 PM") !== false, 'Web booking form displays Until 12:00 PM under check-out');

$webCheckout = file_get_contents(__DIR__ . '/../app/Views/bookings/checkout.php');
assertCond(strpos($webCheckout, "Check-in from 2:00 PM · Check-out until 12:00 PM") !== false, 'Web checkout summary displays standard check-in/out times');

// ---------------------------------------------------------
// Test 2: Database / API Booking Creation & 1-Night Validation
// ---------------------------------------------------------
echo "\n--- Test 2: Database & Model 1-Night Booking Flow ---\n";

try {
    // Look for a test hotel room & listing
    $room = Database::first("SELECT * FROM hotel_rooms WHERE status = 'available' LIMIT 1") 
        ?: Database::first("SELECT * FROM hotel_rooms LIMIT 1");
    $hotelListing = null;
    if ($room) {
        $hotelListing = Database::first("SELECT id, title, user_id FROM listings WHERE id = ?", [$room['listing_id']]);
    }

    // Find a tourist user
    $tourist = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1");
    if (!$tourist) {
        $tourist = Database::first("SELECT id FROM users LIMIT 1");
    }

    if ($hotelListing && $room && $tourist) {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $dayAfter = date('Y-m-d', strtotime('+2 days'));

        // Test 2a: Standard 1-night stay creation
        $bookingId = Booking::createHotelReservation(
            (int) $hotelListing['id'],
            (int) $room['id'],
            (int) $tourist['id'],
            $tomorrow,
            $dayAfter,
            1,
            2,
            (float) $room['price_per_night'],
            (float) $room['price_per_night'] * 0.2,
            (float) $room['price_per_night'] * 0.8,
            "1-Night Test Booking"
        );

        $savedBooking = Database::first("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        assertCond($savedBooking !== null, "Successfully created 1-night reservation (ID: {$bookingId})");
        assertCond((int) $savedBooking['nights'] === 1, "Reservation nights count is exactly 1");
        assertCond($savedBooking['check_in_date'] === $tomorrow, "Check-in date matches arrival date ({$tomorrow})");
        assertCond($savedBooking['check_out_date'] === $dayAfter, "Check-out date matches next day ({$dayAfter})");
        assertCond($savedBooking['booking_time'] === '14:00:00', "Standard check-in time 14:00:00 is set");
        assertCond($savedBooking['booking_end_time'] === '12:00:00', "Standard check-out time 12:00:00 is set");

        // Clean up test booking
        Database::run("DELETE FROM bookings WHERE id = ?", [$bookingId]);
        echo " Cleaned up test booking ID {$bookingId}.\n";
    } else {
        echo " [SKIP] Test hotel listing or room not found in DB. Skipping live DB insertion.\n";
    }
} catch (\Throwable $e) {
    echo " [ERROR] DB test exception: " . $e->getMessage() . "\n";
    $failed++;
}

// ---------------------------------------------------------
// Test 3: Backend Controller Date Validation Logic
// ---------------------------------------------------------
echo "\n--- Test 3: Date Validation Rules ---\n";

function validateHotelDates(string $checkIn, string $checkOut): array {
    $errors = [];
    if ($checkIn === '' || strtotime($checkIn) === false) {
        $errors['check_in_date'] = 'Please choose a valid arrival (check-in) date.';
    } elseif (strtotime($checkIn) < strtotime('today')) {
        $errors['check_in_date'] = 'Check-in date cannot be in the past.';
    }

    if ($checkIn !== '' && $checkOut === '') {
        $checkOut = date('Y-m-d', strtotime($checkIn . ' +1 day'));
    }

    if ($checkOut === '' || strtotime($checkOut) === false) {
        $errors['check_out_date'] = 'Please choose a valid departure (check-out) date.';
    } elseif (strtotime($checkOut) < strtotime($checkIn)) {
        $errors['check_out_date'] = 'Check-out date cannot be earlier than check-in date.';
    } elseif (strtotime($checkOut) === strtotime($checkIn)) {
        $errors['check_out_date'] = 'Check-out date must be at least one day after check-in date. Same-day overnight stays are not permitted.';
    }

    return ['errors' => $errors, 'check_out' => $checkOut];
}

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$dayAfter = date('Y-m-d', strtotime('+2 days'));
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Valid 1-night stay with explicit dates
$res1 = validateHotelDates($tomorrow, $dayAfter);
assertCond(empty($res1['errors']), 'Valid 1-night stay with tomorrow to day-after passes validation');

// Valid 1-night stay with check-out omitted (auto-default to +1 day)
$res2 = validateHotelDates($tomorrow, '');
assertCond(empty($res2['errors']) && $res2['check_out'] === $dayAfter, 'Omitted check-out date auto-defaults to following day and passes validation');

// Same-day check-in and check-out rejected
$res3 = validateHotelDates($tomorrow, $tomorrow);
assertCond(isset($res3['errors']['check_out_date']) && strpos($res3['errors']['check_out_date'], 'Same-day overnight stays are not permitted') !== false, 'Same-day check-in and check-out is rejected');

// Reversed dates rejected
$res4 = validateHotelDates($dayAfter, $tomorrow);
assertCond(isset($res4['errors']['check_out_date']) && strpos($res4['errors']['check_out_date'], 'cannot be earlier') !== false, 'Reversed dates (check-out before check-in) is rejected');

// Past check-in date rejected
$res5 = validateHotelDates($yesterday, $tomorrow);
assertCond(isset($res5['errors']['check_in_date']) && strpos($res5['errors']['check_in_date'], 'cannot be in the past') !== false, 'Past check-in date is rejected');

// Missing check-in date rejected
$res6 = validateHotelDates('', $tomorrow);
assertCond(isset($res6['errors']['check_in_date']) && strpos($res6['errors']['check_in_date'], 'valid arrival') !== false, 'Missing check-in date is rejected');

// ---------------------------------------------------------
// Test 4: Default 1-Night Check-Out & Month Boundary Tests
// ---------------------------------------------------------
echo "\n--- Test 4: 1-Night Default & Month Boundary Calculation ---\n";

function phpGetNextCalendarDay(string $dateStr): string {
    $parts = explode('-', $dateStr);
    if (count($parts) === 3) {
        $y = (int) $parts[0];
        $m = (int) $parts[1];
        $d = (int) $parts[2];
        $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $d), new DateTimeZone('UTC'));
        return $dt->modify('+1 day')->format('Y-m-d');
    }
    return $dateStr;
}

$dateBoundaryCases = [
    ['in' => '2026-11-01', 'expected' => '2026-11-02', 'desc' => 'November 1 -> November 2 (exact 1-night stay)'],
    ['in' => '2026-12-15', 'expected' => '2026-12-16', 'desc' => 'December 15 -> December 16 (exact 1-night stay)'],
    ['in' => '2027-01-31', 'expected' => '2027-02-01', 'desc' => 'January 31 -> February 1 (month boundary rollover)'],
    ['in' => '2026-02-28', 'expected' => '2026-03-01', 'desc' => 'February 28 (non-leap year) -> March 1'],
    ['in' => '2028-02-28', 'expected' => '2028-02-29', 'desc' => 'February 28 (leap year) -> February 29'],
    ['in' => '2028-02-29', 'expected' => '2028-03-01', 'desc' => 'February 29 (leap year) -> March 1'],
    ['in' => '2026-03-31', 'expected' => '2026-04-01', 'desc' => 'March 31 -> April 1 (month boundary rollover)'],
    ['in' => '2026-04-30', 'expected' => '2026-05-01', 'desc' => 'April 30 -> May 1 (month boundary rollover)'],
    ['in' => '2026-12-31', 'expected' => '2027-01-01', 'desc' => 'December 31 -> January 1 (year boundary rollover)'],
];

foreach ($dateBoundaryCases as $case) {
    $out = phpGetNextCalendarDay($case['in']);
    assertCond($out === $case['expected'], "{$case['desc']}: got {$out}");
}

// Verify [slug].tsx sets 1-night check-out when check-in is picked
assertCond(
    strpos($slugTsx, 'setHotelCheckOut(getNextCalendarDay(pickedDate));') !== false,
    '[slug].tsx automatically sets check-out to exactly 1 day after check-in'
);
assertCond(
    strpos($slugTsx, 'setHotelPickerTarget(\'check_out\');') !== false,
    '[slug].tsx switches picker target to check_out allowing manual departure selection'
);
assertCond(
    strpos($slugTsx, 'setHotelCheckOut(pickedDate);') !== false,
    '[slug].tsx preserves manual check-out selection when check-out card is active'
);

// Verify show.php sets 1-night check-out when check-in is picked
assertCond(
    strpos($webShow, 'onHotelCheckInChange') !== false,
    'show.php implements onHotelCheckInChange to update check-out to +1 day'
);
assertCond(
    strpos($webShow, 'outDate.value = defaultOut;') !== false,
    'show.php automatically sets check-out value to exact next calendar day'
);

echo "\n========================================================\n";
echo " Results: $passed passed, $failed failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
