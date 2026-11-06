<?php
/**
 * GuideMate - Test Suite for Step 3 White Background Cards, Dynamic Data Sourcing & Section Icons
 *
 * Requirements covered:
 * 1. Background color set to white (#ffffff) for:
 *    - Package Host Information
 *    - Included Accommodation Details
 *    - Included Rental Vehicle
 * 2. Section icons removed from headers:
 *    - Package Host Information
 *    - Bundled Activities & Attractions
 *    - Included Accommodation Details
 *    - Included Rental Vehicle
 *    - Included Perks & What's Not Included
 * 3. Dynamically sourced info:
 *    - Package Host Information from currently logged-in account ($user) & guideRatingStats
 *    - Included Accommodation Details exclusively from selected hotel data
 *    - Included Rental Vehicle exclusively from selected rental vehicle data
 *    - No hard-coded, outdated, or unrelated placeholder values
 * 4. Correct booking dates:
 *    - Hotel check-in and check-out dates sourced from selected hotel
 *    - Vehicle handover and return dates sourced from selected vehicle
 *    - Proper time and day formatting preserved
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$passes = 0;
$failures = 0;

function it(string $description, bool $condition, &$passes, &$failures): void {
    if ($condition) {
        echo " [PASS] " . $description . "\n";
        $passes++;
    } else {
        echo " [FAIL] " . $description . "\n";
        $failures++;
    }
}

echo "========================================================\n";
echo " GuideMate - Step 3 White Cards & Dynamic Data Tests\n";
echo "========================================================\n\n";

$builderViewPath = __DIR__ . '/../app/Views/guide/package-builder.php';
$builderContent = file_exists($builderViewPath) ? file_get_contents($builderViewPath) : '';

$controllerPath = __DIR__ . '/../app/Controllers/GuideController.php';
$controllerContent = file_exists($controllerPath) ? file_get_contents($controllerPath) : '';

// --------------------------------------------------------------------------
// 1. SECTION BACKGROUNDS (Requirement 1: White #ffffff)
// --------------------------------------------------------------------------
echo "--- 1. Section Backgrounds (White) ---\n";

it("Package Host Information card has white background (#ffffff)", 
    (bool) preg_match('/<!-- Host Card with White Background -->\s*<div style="background:#ffffff;border-radius:16px;padding:1.5rem 1.75rem;color:#0f172a;/s', $builderContent), 
    $passes, $failures);

it("Included Accommodation Details card has white background (#ffffff)", 
    (bool) preg_match('/Included Accommodation Details\s*<\/label>\s*<div style="background:#ffffff;border-radius:16px;/s', $builderContent), 
    $passes, $failures);

it("Included Rental Vehicle card has white background (#ffffff)", 
    (bool) preg_match('/Included Rental Vehicle\s*<\/label>\s*<div style="background:#ffffff;border-radius:16px;/s', $builderContent), 
    $passes, $failures);

preg_match('/id="stepSection3"(.*?)id="stepSection4"/s', $builderContent, $step3Matches);
$step3Html = $step3Matches[1] ?? '';

it("Dark card backgrounds (#111827 / #1e293b) are removed from Step 3 preview cards", 
    !str_contains($step3Html, 'background:#111827') &&
    !str_contains($step3Html, 'background:#1e293b'), 
    $passes, $failures);

it("Pill badges (.pkg-amenity-badge, .pkg-feature-badge) styled with light background for white cards", 
    str_contains($builderContent, '.pkg-amenity-badge {') && 
    str_contains($builderContent, 'background: #fef3c7;') &&
    str_contains($builderContent, '.pkg-feature-badge {') && 
    str_contains($builderContent, 'background: #eff6ff;'), 
    $passes, $failures);

// --------------------------------------------------------------------------
// 2. RESTORATION OF SECTION ICONS IN STEP 3
// --------------------------------------------------------------------------
echo "\n--- 2. Restoration of Section Icons in Step 3 ---\n";

it("Package Host Information header has restored icon (🧑‍💼)", 
    str_contains($builderContent, '<span>🧑‍💼</span> Package Host Information (Auto-Retrieved)'), 
    $passes, $failures);

it("Bundled Activities & Attractions header has restored icon (🎯)", 
    str_contains($builderContent, '<span>🎯</span> Bundled Activities & Attractions'), 
    $passes, $failures);

it("Included Accommodation Details section header has restored icon (🏨)", 
    str_contains($builderContent, '<span>🏨</span> Included Accommodation Details'), 
    $passes, $failures);

it("Included Hotel Details inner header has restored icon (🛏️)", 
    str_contains($builderContent, '🛏️</span>') &&
    str_contains($builderContent, 'Included Hotel Details'), 
    $passes, $failures);

it("Included Rental Vehicle section header has restored icon (🛵)", 
    str_contains($builderContent, '<span>🛵</span> Included Rental Vehicle'), 
    $passes, $failures);

it("Included Rental Vehicle inner header has restored icon (🚗)", 
    str_contains($builderContent, '🚗</span>') &&
    str_contains($builderContent, 'Included Rental Vehicle'), 
    $passes, $failures);

it("Included Perks & What's Not Included header has restored icon (🎁)", 
    str_contains($builderContent, '<span>🎁</span> Included Perks & What\'s Not Included'), 
    $passes, $failures);

// --------------------------------------------------------------------------
// 3. DYNAMIC DATA SOURCING (Requirement 2)
// --------------------------------------------------------------------------
echo "\n--- 3. Dynamic Data Sourcing ---\n";

it("GuideController passes dynamic guideStats to view in newPackage", 
    str_contains($controllerContent, "'guideStats' => \$guideStats"), 
    $passes, $failures);

it("GuideController passes dynamic guideStats to view in editPackage", 
    str_contains($controllerContent, "Booking::guideRatingStats((int) (Auth::id() ?? 0))"), 
    $passes, $failures);

it("Package Host Information sources name dynamically from \$user['name']", 
    str_contains($builderContent, '$hostName = (string) ($user[\'name\'] ??'), 
    $passes, $failures);

it("Package Host Information sources rating & review count dynamically from guide stats", 
    str_contains($builderContent, '$hostRating = (float) ($hostStats[\'avg_rating\'] ?? 0);') &&
    str_contains($builderContent, '$hostReviewCount = (int) ($hostStats[\'review_count\'] ?? 0);') &&
    str_contains($builderContent, '$hostToursCount = (int) ($hostStats[\'completed\'] ?? 0);'), 
    $passes, $failures);

it("Hard-coded host rating (4.9), review count (48), tours count (112) are removed", 
    !str_contains($builderContent, '$hostRating = 4.9;') &&
    !str_contains($builderContent, '$hostReviewCount = 48;') &&
    !str_contains($builderContent, '$hostToursCount = 112;'), 
    $passes, $failures);

it("Hard-coded host bio fallback is removed", 
    !str_contains($builderContent, 'Native Moalboal local with 7+ years of professional outdoor guiding experience'), 
    $passes, $failures);

it("Step 2 hotel radios carry dynamic data-amenities, data-hotel-area, data-bed-type", 
    str_contains($builderContent, 'data-amenities="<?= e($hAmenitiesJson) ?>"') &&
    str_contains($builderContent, 'data-hotel-area="<?= e($hArea) ?>"') &&
    str_contains($builderContent, 'data-bed-type="<?= e($hBed) ?>"'), 
    $passes, $failures);

it("Step 2 vehicle radios carry dynamic data-specs, data-location, data-handover-time, data-return-time", 
    str_contains($builderContent, 'data-specs="<?= e($vSpecsJson) ?>"') &&
    str_contains($builderContent, 'data-location="<?= e($vLoc) ?>"') &&
    str_contains($builderContent, 'data-handover-time="<?= e($vHandover) ?>"') &&
    str_contains($builderContent, 'data-return-time="<?= e($vReturn) ?>"'), 
    $passes, $failures);

it("Hard-coded Sea Breeze Resort removed from default Step 3 HTML", 
    !str_contains($builderContent, '<h3 id="step3HotelName" style="margin:0 0 0.25rem 0;font-size:1.25rem;font-weight:800;color:#ffffff;">' . "\n" . '                                Sea Breeze Oceanfront Resort'), 
    $passes, $failures);

it("Hard-coded Yamaha NMAX removed from default Step 3 HTML", 
    !str_contains($builderContent, '<h3 id="step3VehicleModel" style="margin:0 0 0.25rem 0;font-size:1.25rem;font-weight:800;color:#ffffff;">' . "\n" . '                                Yamaha NMAX 155cc ABS'), 
    $passes, $failures);

it("renderStep3Details updates hotel amenities pills dynamically from selectedHotelAmenities", 
    str_contains($builderContent, 'selectedHotelAmenities.map(am =>') &&
    str_contains($builderContent, 'pkg-amenity-badge'), 
    $passes, $failures);

it("renderStep3Details updates vehicle features pills dynamically from selectedVehicleSpecs", 
    str_contains($builderContent, 'selectedVehicleSpecs.map(sp =>') &&
    str_contains($builderContent, 'pkg-feature-badge'), 
    $passes, $failures);

// --------------------------------------------------------------------------
// 4. CORRECT BOOKING DATES (Requirement 3)
// --------------------------------------------------------------------------
echo "\n--- 4. Correct Booking Dates ---\n";

it("selectHotelRoom captures checkin and checkout times from selected radio data attributes", 
    str_contains($builderContent, 'selectedHotelCheckin = radio.dataset.checkin ||') &&
    str_contains($builderContent, 'selectedHotelCheckout = radio.dataset.checkout ||'), 
    $passes, $failures);

it("selectVehicle captures handover and return times from selected radio data attributes", 
    str_contains($builderContent, 'selectedVehicleHandover = radio.dataset.handoverTime ||') &&
    str_contains($builderContent, 'selectedVehicleReturn = radio.dataset.returnTime ||'), 
    $passes, $failures);

it("formatTime12h helper ensures consistent, readable 12-hour AM/PM date-time formatting", 
    str_contains($builderContent, 'function formatTime12h(timeStr, defaultTime)'), 
    $passes, $failures);

it("renderStep3Details renders hotel Check-in and Check-out using dynamic selection times", 
    str_contains($builderContent, 'hCheckInEl.innerHTML = `<strong>Check-in:</strong> Day 1 · ${escapeHtml(checkinFormatted)} Onwards`') &&
    str_contains($builderContent, 'hCheckOutEl.innerHTML = `<strong>Check-out:</strong> Day ${checkoutDay} · ${escapeHtml(checkoutFormatted)} Midday`'), 
    $passes, $failures);

it("renderStep3Details renders vehicle Handover and Return using dynamic selection times", 
    str_contains($builderContent, 'vHandoverEl.innerHTML = `<strong>Handover:</strong> Day 1 · ${escapeHtml(handoverFormatted)}`') &&
    str_contains($builderContent, 'vReturnEl.innerHTML = `<strong>Return:</strong> Day ${days} · ${escapeHtml(returnFormatted)}`'), 
    $passes, $failures);

it("DOMContentLoaded invokes renderStep3Details to immediately synchronize initial Step 3 state", 
    str_contains($builderContent, "document.addEventListener('DOMContentLoaded', () => {\n    updateDescCount();\n    updateStep2Counters();\n    renderStep3Details();"), 
    $passes, $failures);

echo "\n--------------------------------------------------------\n";
echo "Results: {$passes} passed, {$failures} failed\n";
echo "--------------------------------------------------------\n";

if ($failures > 0) {
    exit(1);
}
