<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Category;
use App\Models\Listing;

echo "====================================================================\n";
echo " Attractions Category & Mobile Browse by Category Feature Tests\n";
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
// Test 1: Database Category Separation
// -----------------------------------------------------------------------------
echo "--- Test 1: Database Category Separation ---\n";

$thingsCategory = Category::findBySlug('things-to-do');
assertTest($thingsCategory !== null, "Category 'Things to Do' (slug: things-to-do) exists");
assertTest((int)$thingsCategory['id'] === 1, "'Things to Do' has category ID 1");

$attractionsCategory = Category::findBySlug('attractions');
assertTest($attractionsCategory !== null, "Category 'Attractions' (slug: attractions) exists");
assertTest((int)$attractionsCategory['id'] === 2, "'Attractions' has category ID 2");
assertTest($attractionsCategory['name'] === 'Attractions', "'Attractions' category name is 'Attractions'");

// -----------------------------------------------------------------------------
// Test 2: Listing Categorization & Separation
// -----------------------------------------------------------------------------
echo "\n--- Test 2: Listing Categorization & Separation ---\n";

// Search listings by category=attractions
$attractionListings = Listing::search(['category' => 'attractions']);
assertTest(count($attractionListings) >= 1, "At least 1 listing retrieved under 'attractions' category");

$kawasanFound = false;
foreach ($attractionListings as $l) {
    if (str_contains($l['title'], 'Kawasan Canyoneering Tour')) {
        $kawasanFound = true;
    }
    assertTest(
        (int)$l['category_id'] === 2 || in_array($l['category_slug'], ['attractions', 'attraction-and-tours'], true),
        "Listing '{$l['title']}' belongs to Attractions category"
    );
}
assertTest($kawasanFound, "Previously existing Kawasan Canyoneering Tour listing appears under 'Attractions'");

// Search listings by category=attraction-and-tours (alias compatibility)
$aliasListings = Listing::search(['category' => 'attraction-and-tours']);
assertTest(count($aliasListings) === count($attractionListings), "Search with alias 'attraction-and-tours' yields identical results to 'attractions'");

// Search listings by category=things-to-do
$thingsListings = Listing::search(['category' => 'things-to-do']);
assertTest(count($thingsListings) >= 1, "Listings retrieved under 'things-to-do' category");

foreach ($thingsListings as $l) {
    assertTest(
        (int)$l['category_id'] === 1 && $l['category_slug'] === 'things-to-do',
        "Listing '{$l['title']}' remains strictly under Things to Do (category_id = 1)"
    );
    assertTest(
        !str_contains($l['title'], 'Kawasan Canyoneering Tour'),
        "Attractions listing does NOT bleed into Things to Do"
    );
}

// -----------------------------------------------------------------------------
// Test 3: Mobile App index.tsx Category Grid & Style
// -----------------------------------------------------------------------------
echo "\n--- Test 3: Mobile App index.tsx Category Grid ---\n";

$indexCode = file_get_contents(__DIR__ . '/../app/(tabs)/index.tsx');

assertTest(
    strpos($indexCode, 'attractions: { icon: \'ticket\', bg: \'#FCE7F3\', color: \'#EC4899\' }') !== false,
    "Mobile app maps 'attractions' to ticket icon, soft rose background (#FCE7F3), and accent color (#EC4899)"
);

assertTest(
    strpos($indexCode, 'category.slug !== \'attraction-and-tours\'') === false &&
    strpos($indexCode, 'category.name !== \'Attraction & Tours\'') === false,
    "Mobile Browse by Category grid does NOT exclude attractions category"
);

assertTest(
    strpos($indexCode, 'router.push({ pathname: \'/things-to-do\', params: { category: \'attractions\' } })') !== false,
    "openAttractions helper navigates to /things-to-do with category='attractions'"
);

// -----------------------------------------------------------------------------
// Test 4: Mobile App things-to-do.tsx Normalization & Filtering
// -----------------------------------------------------------------------------
echo "\n--- Test 4: Mobile App things-to-do.tsx Normalization ---\n";

$thingsCode = file_get_contents(__DIR__ . '/../app/things-to-do.tsx');

assertTest(
    strpos($thingsCode, 'normalizedCat = (rawCat === \'attraction-and-tours\' || rawCat === \'attraction\') ? \'attractions\' : rawCat') !== false,
    "things-to-do.tsx normalizes raw category params to 'attractions'"
);

assertTest(
    strpos($thingsCode, 'categoryParam === \'attractions\'') !== false &&
    strpos($thingsCode, '? \'Attractions\'') !== false,
    "things-to-do.tsx sets title to 'Attractions' when categoryParam is 'attractions'"
);

// -----------------------------------------------------------------------------
// Test 5: Web Platform Navigation & Layout
// -----------------------------------------------------------------------------
echo "\n--- Test 5: Web Platform Navigation & Routes ---\n";

$mainLayout = file_get_contents(__DIR__ . '/../app/Views/layouts/main.php');

assertTest(
    strpos($mainLayout, 'url(\'/attractions\')') !== false &&
    strpos($mainLayout, 'Attractions</a>') !== false,
    "Web layout top navigation bar includes 'Attractions' link"
);

assertTest(
    strpos($mainLayout, 'url(\'/things-to-do\')') !== false,
    "Web layout preserves 'Things to Do' link"
);

$webRoutes = file_get_contents(__DIR__ . '/../routes/web.php');

assertTest(
    strpos($webRoutes, '\'/attractions\', [ListingController::class, \'attractions\']') !== false,
    "routes/web.php defines /attractions route"
);

assertTest(
    strpos($webRoutes, '\'/attraction-and-tours\'') !== false,
    "routes/web.php handles /attraction-and-tours redirect"
);

echo "\n--------------------------------------------------------------------\n";
echo "Results: $passed passed, $failed failed\n";
echo "--------------------------------------------------------------------\n";

exit($failed === 0 ? 0 : 1);
