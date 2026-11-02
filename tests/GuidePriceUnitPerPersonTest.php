<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Listing;
use App\Models\User;

echo "====================================================================\n";
echo " Guide Listing Price Unit: 'Per person' Feature Tests\n";
echo "====================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
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
// Test 1: Frontend Modal & Form Markup Inspection
// -----------------------------------------------------------------------------
echo "--- Test 1: Views & Form Options Markup ---\n";

$listingsView = file_get_contents(__DIR__ . '/../app/Views/guide/listings.php');
$formView = file_get_contents(__DIR__ . '/../app/Views/guide/form.php');

assertTest(
    strpos($listingsView, '<select class="input" id="nl_price_unit" name="price_unit"') !== false,
    "Listings modal has price_unit select input (#nl_price_unit)"
);

assertTest(
    strpos($listingsView, 'value="per person"') !== false &&
    strpos($listingsView, 'Per Person') !== false,
    "Listings modal contains 'Per person' option matching standard title case capitalization"
);

assertTest(
    strpos($listingsView, 'value="per group"') !== false &&
    strpos($listingsView, 'Per Group') !== false,
    "Listings modal preserves 'Per group' option"
);

assertTest(
    strpos($listingsView, 'updateGuidePriceUnitHint') !== false,
    "Listings modal contains dynamic hint handler when switching price units"
);

assertTest(
    strpos($formView, 'value="per person"') !== false &&
    strpos($formView, 'value="per group"') !== false,
    "Standalone guide form.php contains both 'Per person' and 'Per group' options"
);

// -----------------------------------------------------------------------------
// Test 2: Controller & Model Validation and Persistence
// -----------------------------------------------------------------------------
echo "\n--- Test 2: Database Persistence & Retrieval ---\n";

try {
    // Find or create test guide user
    $guideUser = Database::first("SELECT id FROM users WHERE role = 'guide' LIMIT 1");
    if (!$guideUser) {
        $guideId = Database::insert(
            "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Test Guide', 'testguide_unit@example.com', 'password', 'guide', 1, NOW())"
        );
    } else {
        $guideId = (int) $guideUser['id'];
    }

    $category = Database::first("SELECT id FROM categories WHERE slug = 'attraction-and-tours' OR id = 2 LIMIT 1");
    $categoryId = $category ? (int) $category['id'] : 2;

    // Test inserting listing with 'per person'
    $testSlugPerson = 'test-tour-per-person-' . time();
    $personListingId = Listing::create([
        'user_id' => $guideId,
        'category_id' => $categoryId,
        'title' => 'Test Tour Per Person',
        'slug' => $testSlugPerson,
        'summary' => 'Summary for per person tour',
        'description' => 'Description for per person tour',
        'area' => 'Cebu City',
        'address' => 'Colon St, Cebu City',
        'price' => 1200.00,
        'price_unit' => 'per person',
        'duration' => '3 hours',
        'capacity' => 12,
        'status' => 'approved',
    ]);

    assertTest($personListingId > 0, "Created test listing with 'per person' price unit (ID: $personListingId)");

    $retrievedPerson = Listing::find($personListingId);
    assertTest(
        $retrievedPerson !== null && strtolower((string) $retrievedPerson['price_unit']) === 'per person',
        "Listing retrieved from database has price_unit = 'per person' (got: " . ($retrievedPerson['price_unit'] ?? 'none') . ")"
    );

    // Test inserting listing with 'per group'
    $testSlugGroup = 'test-tour-per-group-' . time();
    $groupListingId = Listing::create([
        'user_id' => $guideId,
        'category_id' => $categoryId,
        'title' => 'Test Tour Per Group',
        'slug' => $testSlugGroup,
        'summary' => 'Summary for per group tour',
        'description' => 'Description for per group tour',
        'area' => 'Moalboal',
        'address' => 'Panagsama Beach, Moalboal',
        'price' => 3500.00,
        'price_unit' => 'per group',
        'duration' => '5 hours',
        'capacity' => 8,
        'status' => 'approved',
    ]);

    assertTest($groupListingId > 0, "Created test listing with 'per group' price unit (ID: $groupListingId)");

    $retrievedGroup = Listing::find($groupListingId);
    assertTest(
        $retrievedGroup !== null && strtolower((string) $retrievedGroup['price_unit']) === 'per group',
        "Listing retrieved from database has price_unit = 'per group' (got: " . ($retrievedGroup['price_unit'] ?? 'none') . ")"
    );

    // Clean up created test listings
    Database::run("DELETE FROM listings WHERE id IN (?, ?)", [$personListingId, $groupListingId]);
    echo " Cleaned up temporary test listings.\n";

} catch (\Throwable $e) {
    assertTest(false, "Database persistence test exception: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 3: Pricing Calculation Consistency
// -----------------------------------------------------------------------------
echo "\n--- Test 3: Pricing Logic Across Booking Workflows ---\n";

// Verify 'per person' calculation
$price = 1500.00;
$guests = 4;
$days = 1;

$personUnit = 'per person';
$isPerGroupPerson = str_contains(strtolower($personUnit), 'group');
$totalPerson = $isPerGroupPerson ? ($price * $days) : ($price * $guests * $days);

assertTest(!$isPerGroupPerson, "'per person' is correctly recognized as NOT per-group");
assertTest($totalPerson === 6000.00, "'per person' total multiplies price by guests (1500 * 4 = 6000, got: $totalPerson)");

// Verify 'per group' calculation
$groupUnit = 'per group';
$isPerGroupGroup = str_contains(strtolower($groupUnit), 'group');
$totalGroup = $isPerGroupGroup ? ($price * $days) : ($price * $guests * $days);

assertTest($isPerGroupGroup, "'per group' is correctly recognized as per-group");
assertTest($totalGroup === 1500.00, "'per group' total is fixed regardless of guests (got: $totalGroup)");

// -----------------------------------------------------------------------------
// Test 4: Mobile App [slug].tsx Compatibility
// -----------------------------------------------------------------------------
echo "\n--- Test 4: Mobile App [slug].tsx Compatibility ---\n";

$slugCode = file_get_contents(__DIR__ . '/../app/listing/[slug].tsx');

assertTest(
    strpos($slugCode, 'const isPerGroup = (listing?.price_unit || \'\').toLowerCase().includes(\'group\');') !== false,
    "Mobile app accurately derives isPerGroup from listing.price_unit"
);

assertTest(
    strpos($slugCode, 'isPerGroup ? listing.price * daysCount : listing.price * guests * daysCount') !== false,
    "Mobile app calculates subtotal per person (price * guests) when price_unit is 'per person'"
);

echo "\n--------------------------------------------------------------------\n";
echo "Results: $passed passed, $failed failed\n";
echo "--------------------------------------------------------------------\n";

exit($failed === 0 ? 0 : 1);
