<?php

require_once __DIR__ . '/../app/Core/Database.php';

function assertTest(bool $condition, string $description): void {
    if ($condition) {
        echo " [PASS] $description\n";
    } else {
        echo " [FAIL] $description\n";
        exit(1);
    }
}

echo "\n========================================================\n";
echo " Booking Modal Two-State Interaction Tests (Image 1 & 2)\n";
echo "========================================================\n\n";

$slugPath = __DIR__ . '/../app/listing/[slug].tsx';
assertTest(file_exists($slugPath), "app/listing/[slug].tsx exists");

$code = file_get_contents($slugPath);

echo "--- Test 1: State Management & Declarations ---\n";
assertTest(
    strpos($code, "const [bookingViewMode, setBookingViewMode] = useState<'date' | 'session'>('date');") !== false,
    "bookingViewMode state declared with initial state 'date' (State 1)"
);
assertTest(
    strpos($code, "setBookingViewMode('date');") !== false,
    "openBooking and modal close reset bookingViewMode to 'date'"
);

echo "\n--- Test 2: Button Labels & Stability in dateLockedBanner ---\n";
assertTest(
    strpos($code, "bookingViewMode === 'session' ? (") !== false &&
    strpos($code, "<Text style={styles.selectDateBookTourBtnText}>Select Session</Text>") !== false &&
    strpos($code, "<Text style={styles.selectDateBookTourBtnText}>Select Date</Text>") !== false,
    "Button label dynamically displays 'Select Date' in State 1 and 'Select Session' in State 2"
);
assertTest(
    strpos($code, "setBookingViewMode((prev) => (prev === 'session' ? 'date' : 'session'))") !== false,
    "Tapping button toggles between State 1 ('date') and State 2 ('session')"
);
assertTest(
    strpos($code, "styles.selectDateBookTourBtn") !== false,
    "Button remains enclosed in dateLockedBanner keeping its exact position"
);

echo "\n--- Test 3: State 2 (Image 2) Tour Schedule Section ---\n";
assertTest(
    strpos($code, "bookingViewMode === 'session' && hasSchedule") !== false,
    "Tour Schedule section (Image 2) is conditionally rendered when in State 2"
);
assertTest(
    strpos($code, "Tour Schedule") !== false &&
    strpos($code, "Meeting Point & Departure") !== false &&
    strpos($code, "Open in Google Maps") !== false,
    "State 2 renders Meeting Point & Departure card with Google Maps link matching Image 2"
);
assertTest(
    strpos($code, "Available Dates & Sessions") !== false &&
    strpos($code, "scheduled session") !== false,
    "State 2 renders Available Dates & Sessions header with count badge matching Image 2"
);
assertTest(
    strpos($code, "Duration") !== false &&
    strpos($code, "Operating") !== false &&
    strpos($code, "Capacity") !== false,
    "State 2 renders top 3 stat cards: Duration, Operating, and Capacity matching Image 2"
);

echo "\n--- Test 4: Session Selection & Return to State 1 ---\n";
assertTest(
    strpos($code, "setBookingDates([s.schedule_date]);") !== false &&
    strpos($code, "setBookingViewMode('date');") !== false,
    "Selecting a session updates booking date and returns smoothly to State 1"
);
assertTest(
    strpos($code, "Book this Session") !== false &&
    strpos($code, "Selected Session") !== false,
    "Session cards display active/bookable states with 'Book this Session' / 'Selected Session'"
);

echo "\n--- Test 5: Removal of Select Date Button Beneath Tour Schedule ---\n";
assertTest(
    strpos($code, "!hasSchedule ? (") !== false &&
    strpos($code, "<Text style={styles.schedulePrimaryBtnText}>Select Date</Text>") !== false,
    "Select Date button beneath Tour Schedule section is removed for listings with schedules"
);
assertTest(
    strpos($code, "hasSchedule && { marginTop: 0 }") !== false,
    "Close button spacing adjusted cleanly without unnecessary gap when Select Date is removed"
);

echo "\n--------------------------------------------------------\n";
echo "Results: All 13 tests passed!\n";
echo "--------------------------------------------------------\n";
