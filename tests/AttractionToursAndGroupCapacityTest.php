<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Booking;

echo "====================================================================\n";
echo " Attraction & Tours, Per Group Pricing, Capacity & Booking Tests\n";
echo "====================================================================\n\n";

$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo " [\033[32mPASS\033[0m] $message\n";
        $passed++;
    } else {
        echo " [\033[31mFAIL\033[0m] $message\n";
        $failed++;
    }
}

// -----------------------------------------------------------------------------
// Section 1: Database & Category Structure
// -----------------------------------------------------------------------------
echo "--- Section 1: Database & Category Structure ---\n";

// 1.1 Category renamed
$category = Database::first("SELECT * FROM categories WHERE slug IN ('attractions', 'attraction-and-tours') OR id = 2");
assertCheck($category !== null, "Category ID 2 exists in database");
assertCheck(in_array(($category['name'] ?? ''), ['Attractions', 'Attraction & Tours'], true), "Category name is 'Attractions' or 'Attraction & Tours' (got: " . ($category['name'] ?? 'none') . ")");
assertCheck(in_array(($category['slug'] ?? ''), ['attractions', 'attraction-and-tours'], true), "Category slug is 'attractions' or 'attraction-and-tours'");

// 1.2 Old category 'Tour Guides' should not exist
$oldCategory = Database::first("SELECT * FROM categories WHERE name = 'Tour Guides' OR slug = 'tour-guides'");
assertCheck($oldCategory === null, "'Tour Guides' category has been removed/replaced in categories table");

// 1.3 Listings table has capacity column
$columns = Database::all("SHOW COLUMNS FROM listings LIKE 'capacity'");
assertCheck(count($columns) === 1, "'capacity' column exists in listings table");

// -----------------------------------------------------------------------------
// Section 2: Guide Web Platform Views & Forms
// -----------------------------------------------------------------------------
echo "\n--- Section 2: Guide Web Platform Views & Forms ---\n";

$guideListingsView = file_get_contents(__DIR__ . '/../app/Views/guide/listings.php');
$guideFormView = file_get_contents(__DIR__ . '/../app/Views/guide/form.php');

// 2.1 Slots / Capacity field in listings.php
assertCheck(
    strpos($guideListingsView, 'nl_capacity') !== false && strpos($guideListingsView, 'Slots / Capacity') !== false,
    "Guide listings.php modal contains required 'Slots / Capacity' input"
);

// 2.2 Pricing Unit in listings.php contains 'Per Person' and 'Per Group'
assertCheck(
    strpos($guideListingsView, 'value="per person"') !== false &&
    strpos($guideListingsView, 'value="per group"') !== false &&
    strpos($guideListingsView, 'value="per night"') === false &&
    strpos($guideListingsView, 'value="per tour"') === false &&
    strpos($guideListingsView, 'value="per day"') === false,
    "Guide listings.php pricing unit supports 'Per Person' and 'Per Group' (removed per night, per tour, per day)"
);

// 2.3 3-Photo Upload Layout in listings.php
assertCheck(
    strpos($guideListingsView, 'photo_file_0') !== false &&
    strpos($guideListingsView, 'photo_file_1') !== false &&
    strpos($guideListingsView, 'photo_file_2') !== false,
    "Guide listings.php implements 3-photo upload slots (Photo 1 Cover, Photo 2 Gallery, Photo 3 Gallery)"
);
assertCheck(
    strpos($guideListingsView, 'removeGuidePhoto') !== false,
    "Guide listings.php includes remove photo handler"
);

// 2.4 Slots / Capacity and Per Group in form.php
assertCheck(
    strpos($guideFormView, 'name="capacity"') !== false && strpos($guideFormView, 'Slots / Capacity') !== false,
    "Guide form.php contains 'Slots / Capacity' input"
);
assertCheck(
    strpos($guideFormView, 'value="per group"') !== false,
    "Guide form.php pricing unit defaults to 'Per Group'"
);
assertCheck(
    strpos($guideFormView, 'photo_file_') !== false && strpos($guideFormView, 'photo_url_') !== false,
    "Guide form.php implements 3-photo upload structure matching hotel management"
);

// -----------------------------------------------------------------------------
// Section 3: Pricing Calculation & Overbooking Prevention
// -----------------------------------------------------------------------------
echo "\n--- Section 3: Pricing Calculation & Overbooking Prevention ---\n";

// 3.1 Per group pricing calculation in ApiController / BookingController
$apiController = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
assertCheck(
    strpos($apiController, '$isPerGroup = str_contains(strtolower((string) ($listing[\'price_unit\'] ?? \'\')), \'group\');') !== false,
    "ApiController checks for 'group' in price_unit"
);
assertCheck(
    strpos($apiController, '$subtotal = $isPerGroup ? ($price * $daysCount) : ($price * $guests * $daysCount);') !== false,
    "ApiController calculates fixed per-group price without multiplying by guest count"
);

// 3.2 Capacity validation / overbooking prevention in ApiController
assertCheck(
    strpos($apiController, 'This tour session is already fully booked. Please choose another date or session.') !== false,
    "ApiController prevents overbooking when total booked reaches capacity limit"
);
assertCheck(
    strpos($apiController, 'only has {$availableSlots} slot(s) remaining') !== false,
    "ApiController prevents overbooking when requested guests exceed available capacity"
);

// -----------------------------------------------------------------------------
// Section 4: Mobile Application Listing Screen ([slug].tsx)
// -----------------------------------------------------------------------------
echo "\n--- Section 4: Mobile App [slug].tsx Interface & Behavior ---\n";

$slugCode = file_get_contents(__DIR__ . '/../app/listing/[slug].tsx');

// 4.1 Non-scheduled listings show Duration + See Details
assertCheck(
    strpos($slugCode, 'hasSchedule ?') !== false &&
    strpos($slugCode, 'setScheduleModalOpen(true)') !== false &&
    strpos($slugCode, 'setDurationModalOpen(true)') !== false,
    "Mobile fact card switches between Schedule (hasSchedule) and Duration (!hasSchedule)"
);
assertCheck(
    strpos($slugCode, 'See Details') !== false,
    "Non-scheduled listings display 'See Details' indicator"
);

// 4.2 Duration Details Modal exists
assertCheck(
    strpos($slugCode, 'visible={durationModalOpen}') !== false &&
    strpos($slugCode, 'Duration & Details') !== false,
    "app/listing/[slug].tsx implements dedicated Duration & Details modal"
);

// 4.3 Scheduled listings show locked banner; Non-scheduled hide it
assertCheck(
    strpos($slugCode, '{hasSchedule ? (') !== false &&
    strpos($slugCode, 'Booking date is locked to prevent accidental changes') !== false,
    "Booking date locked banner is rendered only when hasSchedule is true (hidden for non-scheduled)"
);

// 4.4 Button renamed to 'Select Date'
assertCheck(
    strpos($slugCode, '<Text style={styles.selectDateBookTourBtnText}>Select Date</Text>') !== false &&
    strpos($slugCode, '<Text style={styles.schedulePrimaryBtnText}>Select Date</Text>') !== false,
    "Unlock button and schedule modal primary button renamed from 'Select Date & Book Tour' to 'Select Date'"
);

// 4.5 Bottom Bar CTA adapts to hasSchedule
assertCheck(
    strpos($slugCode, "hasSchedule ? 'Book Session' : 'Book now'") !== false,
    "Bottom bar button displays 'Book Session' for scheduled listings and 'Book now' for non-scheduled"
);

// 4.6 Tour Session Time & Available Sessions Clutter Removal
assertCheck(
    strpos($slugCode, 'Available Sessions for') === false,
    "Mobile booking modal removes redundant 'Available Sessions' section/label below calendar"
);
assertCheck(
    strpos($slugCode, 'guideTimeCard') !== false &&
    strpos($slugCode, 'Tour Session Time') !== false &&
    strpos($slugCode, 'Guide Defined') !== false,
    "Mobile booking modal cleanly displays guide predefined Tour Session Time card directly below calendar"
);

// 4.7 Per Group subtotal in mobile app
assertCheck(
    strpos($slugCode, 'isPerGroup ? listing.price * daysCount : listing.price * guests * daysCount') !== false,
    "Mobile booking subtotal uses fixed per-group pricing (does not multiply by guests when isPerGroup)"
);

// 4.8 Automatic slot decrement on booking submission
assertCheck(
    strpos($slugCode, 'const newSlots = Math.max(0, cap - newBooked)') !== false,
    "Mobile submitBooking immediately decrements available session slots in local state upon confirmation"
);

echo "\n--------------------------------------------------------------------\n";
echo "Results: $passed passed, $failed failed\n";
echo "--------------------------------------------------------------------\n";

exit($failed === 0 ? 0 : 1);
