<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\Listing;
use App\Models\User;

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo " [PASS] $msg\n";
    } else {
        $failed++;
        echo " [FAIL] $msg\n";
    }
}

echo "========================================================\n";
echo " Guide Listing Photo Edit & Retrieval Tests\n";
echo "========================================================\n\n";

// 1. Layouts define window.APP_BASE_URL
$mainLayout = file_get_contents(__DIR__ . '/../app/Views/layouts/main.php');
assertCondition(str_contains($mainLayout, 'window.APP_BASE_URL'), 'main.php layout defines window.APP_BASE_URL in head');

$adminLayout = file_get_contents(__DIR__ . '/../app/Views/layouts/admin.php');
assertCondition(str_contains($adminLayout, 'window.APP_BASE_URL'), 'admin.php layout defines window.APP_BASE_URL in head');

// 2. guide/listings.php view checks
$listingsView = file_get_contents(__DIR__ . '/../app/Views/guide/listings.php');
assertCondition(!str_contains($listingsView, 'var base = (window.APP_BASE_URL || \'\').replace(/\/+$/, \'\');'), 'guide/listings.php no longer uses fallback empty string for missing APP_BASE_URL');
assertCondition(str_contains($listingsView, 'function resolveImageUrl(path)'), 'guide/listings.php defines robust resolveImageUrl(path)');
assertCondition(str_contains($listingsView, 'populatePhotos(listing.photos, listing.cover_image'), 'guide/listings.php passes listing.photos to populatePhotos');
assertCondition(str_contains($listingsView, 'fetch(baseListingsUrl + \'/\' + listing.id'), 'guide/listings.php fetches fresh photos asynchronously if needed');

// 3. guide/form.php view checks
$formView = file_get_contents(__DIR__ . '/../app/Views/guide/form.php');
assertCondition(str_contains($formView, '$existingPhotos = $editing ? \App\Models\Listing::getPhotos($listing) : [];'), 'guide/form.php initializes $existingPhotos using Listing::getPhotos');
assertCondition(str_contains($formView, 'function resolveFormImageUrl(path)'), 'guide/form.php defines robust resolveFormImageUrl(path)');

// 4. Listing Model tests
$testGuide = Database::first('SELECT * FROM users WHERE role = "guide" LIMIT 1');
if ($testGuide) {
    $guideId = (int) $testGuide['id'];
    $listings = Listing::forGuideOwner($guideId);
    assertCondition(is_array($listings), 'Listing::forGuideOwner returns array');
    if ($listings !== []) {
        $first = $listings[0];
        assertCondition(array_key_exists('photos', $first), 'Listing in forGuideOwner includes "photos" key');
        assertCondition(is_array($first['photos']), 'Listing "photos" is an array');
    }
}

// 5. Test specific listing 1161 if present (from user screenshot)
$l1161 = Listing::find(1161);
if ($l1161 !== null) {
    assertCondition(array_key_exists('photos', $l1161), 'Listing 1161 includes photos key from Listing::find');
    assertCondition(count($l1161['photos']) === 3, 'Listing 1161 retrieves all 3 photos (Photo 1 Cover, Photo 2 Gallery, Photo 3 Gallery)');
    assertCondition($l1161['photos'][0] === $l1161['cover_image'], 'Listing 1161 Photo 1 matches cover_image');
    assertCondition(str_contains($l1161['photos'][0], 'uploads/listings/'), 'Photo 1 is a stored upload path');
    assertCondition(str_contains($l1161['photos'][1], 'uploads/listings/'), 'Photo 2 is a stored upload path');
    assertCondition(str_contains($l1161['photos'][2], 'uploads/listings/'), 'Photo 3 is a stored upload path');
}

// 6. Test resolveImageUrl logic simulating client-side behavior
function testSimulatedResolveImageUrl(string $path, string $appBase): string {
    $path = trim($path);
    if ($path === '') return '';
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
        return $path;
    }
    $base = rtrim($appBase, '/');
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        return str_starts_with($path, '/') ? $path : '/' . $path;
    }
    $clean = ltrim($path, '/');
    return ($base !== '' ? $base . '/' : '/') . $clean;
}

$sampleUpload = 'uploads/listings/3/dc639b9788e65eb2.jpg';
$resolvedSubfolder = testSimulatedResolveImageUrl($sampleUpload, '/GuideMate/public');
assertCondition($resolvedSubfolder === '/GuideMate/public/uploads/listings/3/dc639b9788e65eb2.jpg', 'Resolves subfolder upload path correctly with app base');

$resolvedAlreadyPrefixed = testSimulatedResolveImageUrl('/GuideMate/public/uploads/listings/3/dc639b9788e65eb2.jpg', '/GuideMate/public');
assertCondition($resolvedAlreadyPrefixed === '/GuideMate/public/uploads/listings/3/dc639b9788e65eb2.jpg', 'Does not duplicate subfolder base path if already present');

$resolvedDomainRoot = testSimulatedResolveImageUrl($sampleUpload, '');
assertCondition($resolvedDomainRoot === '/uploads/listings/3/dc639b9788e65eb2.jpg', 'Resolves root domain path correctly');

$sampleExternal = 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957';
$resolvedExternal = testSimulatedResolveImageUrl($sampleExternal, '/GuideMate/public');
assertCondition($resolvedExternal === $sampleExternal, 'Passes external URL through untouched');

// 7. Route registration
$routesContent = file_get_contents(__DIR__ . '/../routes/web.php');
assertCondition(str_contains($routesContent, "'/dashboard/listings/{id}'"), 'routes/web.php registers GET /dashboard/listings/{id}');

echo "\n--------------------------------------------------------\n";
echo "Results: $passed passed, $failed failed\n";
echo "--------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
