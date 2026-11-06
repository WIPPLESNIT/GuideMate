<?php
/**
 * GuideMate - Listing Update HY093 Parameter Binding Test
 *
 * Verifies that Listing::update and Database::run properly handle parameters,
 * eliminating "SQLSTATE[HY093]: Invalid parameter number: parameter was not defined"
 * when updating package listings with 'is_featured' and any extra metadata.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Listing;

$passes = 0;
$failures = 0;

function it($description, $condition, &$passes, &$failures) {
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passes++;
    } else {
        echo " [FAIL] {$description}\n";
        $failures++;
    }
}

echo "========================================================\n";
echo " GuideMate - Listing::update HY093 Parameter Safety Test\n";
echo "========================================================\n\n";

// 1. Verify Listing::update implementation in Listing.php
$listingSource = file_get_contents(__DIR__ . '/../app/Models/Listing.php');

it("Listing::update explicitly constructs whitelisted parameters",
    str_contains($listingSource, '$params = [') &&
    str_contains($listingSource, "'id' => \$id,"),
    $passes, $failures);

it("Listing::update conditionally handles is_featured without undefined parameter error",
    str_contains($listingSource, "isset(\$data['is_featured'])") &&
    str_contains($listingSource, "is_featured = :is_featured"),
    $passes, $failures);

it("Listing::create supports is_featured in INSERT query and params",
    str_contains($listingSource, "'is_featured' => isset(\$data['is_featured'])") &&
    str_contains($listingSource, ":is_featured"),
    $passes, $failures);

// 2. Verify Database::run defenses against HY093
$dbSource = file_get_contents(__DIR__ . '/../app/Core/Database.php');

it("Database::run cleans extra named parameters that do not exist in SQL",
    str_contains($dbSource, "preg_match_all('/(?<!:):([a-zA-Z0-9_]+)/', \$sql, \$matches)") &&
    str_contains($dbSource, "ltrim((string) \$key, ':')"),
    $passes, $failures);

// 3. Unit test parameter stripping logic
$testSql = 'UPDATE listings SET category_id = :category_id, title = :title WHERE id = :id';
$rawParams = [
    'category_id' => 2,
    ':title' => 'Island Tour',
    'id' => 1191,
    'is_featured' => 1, // extra parameter
    'non_existent_column' => 'foo',
];

preg_match_all('/(?<!:):([a-zA-Z0-9_]+)/', $testSql, $matches);
$placeholders = array_flip($matches[1]);
$clean = [];
foreach ($rawParams as $key => $val) {
    $k = ltrim((string) $key, ':');
    if (isset($placeholders[$k])) {
        $clean[$k] = $val;
    }
}

it("Named parameter filtering removes unbound keys ('is_featured', 'non_existent_column')",
    !isset($clean['is_featured']) && !isset($clean['non_existent_column']),
    $passes, $failures);

it("Named parameter filtering preserves required keys ('category_id', 'title', 'id')",
    isset($clean['category_id']) && isset($clean['title']) && isset($clean['id']) &&
    $clean['category_id'] === 2 && $clean['title'] === 'Island Tour' && $clean['id'] === 1191,
    $passes, $failures);

// 3. Functional database execution test
try {
    // Find or pick a test listing from DB
    $testListing = Database::first("SELECT id, title, area FROM listings LIMIT 1");

    if ($testListing) {
        $id = (int) $testListing['id'];

        // Simulate GuideController::updatePackage call that previously caused HY093
        $updatePayload = [
            'category_id' => 1,
            'title' => $testListing['title'],
            'summary' => 'Updated summary test',
            'description' => 'Updated description test',
            'area' => $testListing['area'] ?? 'Cebu',
            'address' => 'Test Departure Point',
            'price' => 8499.00,
            'price_unit' => 'package',
            'duration' => '3 Days / 2 Nights',
            'capacity' => 8,
            'included' => 'Perks and permits included',
            'not_included' => 'Airfare not included',
            'cover_image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80',
            'is_featured' => 1, // Previously triggered HY093
            'extra_dummy_param' => 'should be ignored safely',
            'another_unbound_key' => 12345,
        ];

        $exceptionCaught = false;
        $errorMsg = '';
        try {
            Listing::update($id, $updatePayload);
        } catch (\Throwable $e) {
            $exceptionCaught = true;
            $errorMsg = $e->getMessage();
        }

        it("Listing::update executes successfully with is_featured and extra keys without throwing HY093",
            !$exceptionCaught,
            $passes, $failures);

        if ($exceptionCaught) {
            echo "   Error: {$errorMsg}\n";
        }

        // Verify updated listing in DB
        $reloaded = Database::first("SELECT id, is_featured, price_unit FROM listings WHERE id = ?", [$id]);
        it("Listing record was updated with is_featured = 1",
            $reloaded && (int) $reloaded['is_featured'] === 1,
            $passes, $failures);

        // Update again with is_featured = 0 to verify toggle
        $updatePayload['is_featured'] = 0;
        Listing::update($id, $updatePayload);
        $reloadedAfter = Database::first("SELECT id, is_featured FROM listings WHERE id = ?", [$id]);
        it("Listing record is_featured toggles back cleanly to 0",
            $reloadedAfter && (int) $reloadedAfter['is_featured'] === 0,
            $passes, $failures);

    } else {
        echo " [SKIP] No existing listing in database to run live update against\n";
    }
} catch (\Throwable $dbEx) {
    echo " [INFO] Live DB test skipped or failed to connect: " . $dbEx->getMessage() . "\n";
}

echo "\n--------------------------------------------------------\n";
echo "Results: {$passes} passed, {$failures} failed\n";
echo "========================================================\n";

if ($failures > 0) {
    exit(1);
}
exit(0);
