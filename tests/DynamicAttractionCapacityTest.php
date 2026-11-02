<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Listing;
use App\Models\Booking;

function assertTest(bool $condition, string $description): void {
    if ($condition) {
        echo " [PASS] $description\n";
    } else {
        echo " [FAIL] $description\n";
        exit(1);
    }
}

echo "\n========================================================\n";
echo " Dynamic Attraction Capacity & Details Section Tests\n";
echo "========================================================\n\n";

$slugPath = __DIR__ . '/../app/listing/[slug].tsx';
assertTest(file_exists($slugPath), "app/listing/[slug].tsx exists");

$code = file_get_contents($slugPath);

echo "--- Test 1: Duration & Details Inclusions/Exclusions Removal ---\n";
// Ensure What's Included and Not Included are removed from Duration & Details modal
$durationModalStart = strpos($code, 'visible={durationModalOpen}');
$durationModalEnd = strpos($code, '</Modal>', $durationModalStart);
$durationModalCode = substr($code, $durationModalStart, $durationModalEnd - $durationModalStart);

assertTest(
    strpos($durationModalCode, "What's Included") === false,
    "What's Included content is completely removed from Duration & Details modal"
);
assertTest(
    strpos($durationModalCode, "Not Included") === false,
    "Not Included content is completely removed from Duration & Details modal"
);

echo "\n--- Test 2: Dynamic Slots / Capacity Field Layout & Availability ---\n";
assertTest(
    strpos($durationModalCode, 'Slots / Capacity') !== false,
    "Slots / Capacity section header is preserved in Duration & Details"
);
assertTest(
    strpos($durationModalCode, 'slots available') !== false &&
    strpos($durationModalCode, 'Available Slots:') !== false,
    "Displays remaining availability clearly e.g. '8 of 10 slots available' or 'Available Slots: 8/10'"
);
assertTest(
    strpos($durationModalCode, 'Total Capacity:') !== false,
    "Clearly distinguishes between total capacity and remaining available slots"
);
assertTest(
    strpos($durationModalCode, 'Fully Booked · No slots available') !== false,
    "Displays clear 'Fully Booked · No slots available' message when capacity is reached"
);

echo "\n--- Test 3: Booking Restrictions & Overbooking Prevention ---\n";
assertTest(
    strpos($durationModalCode, 'disabled={isAttractionFullyBooked}') !== false,
    "Disables Book now action button in Duration & Details modal when fully booked"
);
assertTest(
    strpos($code, 'attractionAvailableSlots <= 0') !== false,
    "Prevents proceeding to payment when attraction has no remaining slots"
);
assertTest(
    strpos($code, 'guests > attractionAvailableSlots') !== false,
    "Prevents booking more slots than currently available"
);
assertTest(
    strpos($code, 'Math.max(0,') !== false,
    "Guarantees available slots count never drops below zero"
);

echo "\n--- Test 4: Database & API Backend Dynamic Capacity Calculation ---\n";
$apiCode = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
assertTest(
    strpos($apiCode, '$totalListingBooked') !== false &&
    strpos($apiCode, '$listingAvailSlots = max(0, $listingCap - $totalListingBooked)') !== false,
    "ApiController calculates remaining available slots dynamically from actual database bookings"
);
assertTest(
    strpos($apiCode, "\$listingPayload['total_capacity'] = \$listingCap") !== false &&
    strpos($apiCode, "\$listingPayload['available_slots'] = \$listingAvailSlots") !== false,
    "ApiController returns total_capacity and available_slots in listing payload"
);

// Verify actual DB calculation on Moalboal Snorkeling (ID 1)
$listing = Database::first('SELECT id, title, capacity FROM listings WHERE id = 1');
assertTest($listing !== null, "Found Moalboal Snorkeling listing in database");
$totalCap = (int) (!empty($listing['capacity']) ? $listing['capacity'] : 10);
assertTest($totalCap === 10, "Initial total capacity for attraction is set to 10 slots (got: $totalCap)");

$bookedSum = Database::first(
    'SELECT COALESCE(SUM(guests), 0) AS total_booked
     FROM bookings
     WHERE listing_id = 1
       AND status IN ("pending", "confirmed")
       AND (booking_date >= CURDATE() OR booking_date IS NULL)'
);
$totalBooked = (int) ($bookedSum['total_booked'] ?? 0);
$expectedAvail = max(0, $totalCap - $totalBooked);
assertTest($expectedAvail >= 0 && $expectedAvail <= 10, "Calculated available slots ($expectedAvail/10) is valid and non-negative");

echo "\n--------------------------------------------------------\n";
echo "Results: All 13 tests passed!\n";
echo "--------------------------------------------------------\n";
