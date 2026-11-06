<?php
/**
 * GuidePackageBuilderStep1Test.php
 * Automated verification suite for Guide Account - Step 1: Package Overview & Basic Information
 * Updated per 4 reference images requirements:
 * 1. Remove "Package Category Pill"
 * 2. Remove "Live Tourist View"
 * 3. Remove "Assurance" from "Key Trip Attributes"
 * 4. Fix "Package Showcase Photo" layout, spacing, styling & behavior
 * 5. Clear all editable text fields by default with descriptive placeholders
 */

echo "========================================================\n";
echo " GuideMate - Guide Package Builder Step 1 Test Suite\n";
echo "========================================================\n\n";

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

// 1. Check package-builder.php file existence and contents
$builderViewPath = __DIR__ . '/../app/Views/guide/package-builder.php';
it("Web package builder view exists", file_exists($builderViewPath), $passes, $failures);

$builderContent = file_exists($builderViewPath) ? file_get_contents($builderViewPath) : '';

// 2. Stepper workflow tabs matching the 5-step workflow
it("Stepper includes Step 1: Overview tab", str_contains($builderContent, '<strong>Overview</strong>') && str_contains($builderContent, 'stepTab1'), $passes, $failures);
it("Stepper includes Step 2: Package Selection tab", str_contains($builderContent, '<strong>Package Selection</strong>') && str_contains($builderContent, 'stepTab2'), $passes, $failures);
it("Stepper includes Step 3: Selected Package Details tab", str_contains($builderContent, '<strong>Selected Package Details</strong>') && str_contains($builderContent, 'stepTab3'), $passes, $failures);
it("Stepper includes Step 4: Itinerary tab", str_contains($builderContent, '<strong>Itinerary</strong>') && str_contains($builderContent, 'stepTab4'), $passes, $failures);
it("Stepper includes Step 5: Pricing tab", str_contains($builderContent, '<strong>Pricing</strong>') && str_contains($builderContent, 'stepTab5'), $passes, $failures);

// 3. Step 1 Header & Structure
it("Step 1 section heading is 'Package Overview & Basic Information'", str_contains($builderContent, 'Step 1: Package Overview & Basic Information'), $passes, $failures);

// 4. Requirement 1: Package Category Pill removed
it("Requirement 1: 'Package Category Pill' label is removed from Web view", !str_contains($builderContent, 'Package Category Pill'), $passes, $failures);
it("Requirement 1: Category pill group is removed from Web view", !str_contains($builderContent, 'categoryPillGroup'), $passes, $failures);
it("Requirement 1: category_label input is removed from Web Step 1", !str_contains($builderContent, 'name="category_label"'), $passes, $failures);
it("Special Highlight Badge manual selector removed and automated with name='badge'", !str_contains($builderContent, 'id="badgePillGroup"') && str_contains($builderContent, 'name="badge"'), $passes, $failures);

// 5. Requirement 2: Live Tourist View removed
it("Requirement 2: 'Live Tourist View' section is removed from Web view", !str_contains($builderContent, 'Live Tourist View'), $passes, $failures);
it("Requirement 2: livePreviewHeroImg element is removed from Web view", !str_contains($builderContent, 'livePreviewHeroImg'), $passes, $failures);
it("Requirement 2: 2-column split .pkg-step1-grid is removed in favor of clean full-width layout", !str_contains($builderContent, 'pkg-step1-grid'), $passes, $failures);

// 6. Requirement 3: Assurance item removed from Key Trip Attributes
it("Requirement 3: 'Assurance' item is removed from Key Trip Attributes", !str_contains($builderContent, 'name="assurance"'), $passes, $failures);
it("Requirement 3: Key Trip Attributes is 2-column (Duration & Group Size only)", str_contains($builderContent, 'Key Trip Attributes') && !str_contains($builderContent, '3-Column Matrix'), $passes, $failures);
it("Key Trip Attributes includes Duration Days and Nights counters", str_contains($builderContent, 'name="duration_days"') && str_contains($builderContent, 'name="duration_nights"'), $passes, $failures);
it("Key Trip Attributes includes Group Size Min and Max counters", str_contains($builderContent, 'name="min_pax"') && str_contains($builderContent, 'name="max_pax"'), $passes, $failures);

// 7. Requirement 4: Package Showcase Photo component matching Image 4
it("Requirement 4: Showcase photos header displays 'Package Showcase Photos (Cover & Gallery)'", str_contains($builderContent, 'Package Showcase Photos (Cover & Gallery)'), $passes, $failures);
it("Requirement 4: Showcase photos header has '3 Photo Slots' badge", str_contains($builderContent, '3 Photo Slots'), $passes, $failures);
it("Requirement 4: Showcase photos component uses .pkg-showcase-grid with 3 columns", str_contains($builderContent, 'pkg-showcase-grid'), $passes, $failures);
it("Requirement 4: Showcase cards have Photo 1 (Cover) Hero badge and 3 slots", str_contains($builderContent, '(Cover) *') && str_contains($builderContent, 'Hero'), $passes, $failures);
it("Requirement 4: Showcase cards have file input, URL input, and delete button", str_contains($builderContent, 'photo_file_') && str_contains($builderContent, 'photo_url_') && str_contains($builderContent, 'pkg_remove_btn_'), $passes, $failures);
it("Requirement 4: Duplicate photo upload section in Step 5 is removed", !str_contains($builderContent, 'Package Photos (Upload up to 3 photos)'), $passes, $failures);

// 8. Requirement 5: Empty default values & descriptive placeholders across the entire builder
it("Requirement 5: Package title is empty by default with placeholder", str_contains($builderContent, 'placeholder="e.g. 3D2N South Cebu Expedition') && str_contains($builderContent, '$defaultTitle = $isEdit ?'), $passes, $failures);
it("Requirement 5: Destination area is empty by default with placeholder", str_contains($builderContent, 'placeholder="e.g. Moalboal & Badian, South Cebu"'), $passes, $failures);
it("Requirement 5: Departure meeting point is empty by default with placeholder", str_contains($builderContent, 'placeholder="e.g. Panagsama Beach, Moalboal or Mactan Airport"'), $passes, $failures);
it("Requirement 5: Tagline summary is empty by default with placeholder", str_contains($builderContent, 'placeholder="e.g. All-inclusive 3-day South Cebu escape'), $passes, $failures);
it("Requirement 5: Duration Days has descriptive placeholder", str_contains($builderContent, 'id="pkg_days"') && str_contains($builderContent, 'placeholder="e.g. 3"'), $passes, $failures);
it("Requirement 5: Duration Nights has descriptive placeholder", str_contains($builderContent, 'id="pkg_nights"') && str_contains($builderContent, 'placeholder="e.g. 2"'), $passes, $failures);
it("Requirement 5: Group Size Min has descriptive placeholder", str_contains($builderContent, 'id="pkg_min_pax"') && str_contains($builderContent, 'placeholder="e.g. 2"'), $passes, $failures);
it("Requirement 5: Group Size Max has descriptive placeholder", str_contains($builderContent, 'id="pkg_max_pax"') && str_contains($builderContent, 'placeholder="e.g. 8"'), $passes, $failures);
it("Requirement 5: About This Package textarea is empty by default with descriptive placeholder", str_contains($builderContent, 'placeholder="Describe the overall package experience') && str_contains($builderContent, '$defaultDesc = $isEdit'), $passes, $failures);
it("Requirement 5: Experience Tags textarea is empty by default with descriptive placeholder", str_contains($builderContent, 'placeholder="e.g. Adventure & Nature') && str_contains($builderContent, '$defaultTags = $isEdit'), $passes, $failures);
it("Requirement 5: Guide curation margin is empty by default in create mode with placeholder", str_contains($builderContent, 'id="guide_margin"') && str_contains($builderContent, 'placeholder="e.g. 2000"'), $passes, $failures);
it("Requirement 5: Itinerary highlights textarea has descriptive placeholder", str_contains($builderContent, 'id="pkg_itinerary"') && str_contains($builderContent, 'placeholder="e.g. Day 1:'), $passes, $failures);
it("Requirement 5: Policies textarea has descriptive placeholder", str_contains($builderContent, 'id="pkg_policies"') && str_contains($builderContent, 'placeholder="e.g. Free cancellation'), $passes, $failures);

// 9. Backend Controller & API metadata support
$guideControllerPath = __DIR__ . '/../app/Controllers/GuideController.php';
$guideControllerContent = file_exists($guideControllerPath) ? file_get_contents($guideControllerPath) : '';
it("GuideController createPackage passes empty default values for new package", str_contains($guideControllerContent, '\'defaultCategory\' => \'\''), $passes, $failures);
it("GuideController storePackage handles optional metadata cleanly", str_contains($guideControllerContent, 'PACKAGE_OVERVIEW'), $passes, $failures);
it("GuideController updatePackage preserves PACKAGE_OVERVIEW metadata", str_contains($guideControllerContent, 'PACKAGE_OVERVIEW'), $passes, $failures);

// 10. Mobile App Version (React Native / Expo)
$appCreatePath = __DIR__ . '/../app/package/create.tsx';
it("Mobile app Step 1 screen exists (app/package/create.tsx)", file_exists($appCreatePath), $passes, $failures);

$appCreateContent = file_exists($appCreatePath) ? file_get_contents($appCreatePath) : '';
it("Mobile app screen contains Step 1 header and step indicator", str_contains($appCreateContent, 'Step 1: Overview & Basic Info'), $passes, $failures);
it("Mobile app has Live Tourist Preview removed per Requirement 2", !str_contains($appCreateContent, 'LIVE TOURIST PREVIEW'), $passes, $failures);
it("Mobile app has Category pills removed per Requirement 1", !str_contains($appCreateContent, 'CATEGORIES.map') && !str_contains($appCreateContent, 'Experience Category'), $passes, $failures);
it("Mobile app has Assurance selector removed per Requirement 3", !str_contains($appCreateContent, 'ASSURANCE_OPTIONS') && !str_contains($appCreateContent, 'Assurance Guarantee'), $passes, $failures);
it("Mobile app has manual badge picker removed and defaults to New Expedition", !str_contains($appCreateContent, 'BADGES.map') && str_contains($appCreateContent, "useState('New Expedition')"), $passes, $failures);
it("Mobile app inputs default to empty strings per Requirement 5", str_contains($appCreateContent, 'setTitle(\'\')') || str_contains($appCreateContent, 'useState(\'\')'), $passes, $failures);
it("Mobile app inputs provide descriptive placeholders per Requirement 5", str_contains($appCreateContent, 'placeholder="e.g. 3D2N South Cebu Expedition'), $passes, $failures);

$appLayoutPath = __DIR__ . '/../app/_layout.tsx';
$appLayoutContent = file_exists($appLayoutPath) ? file_get_contents($appLayoutPath) : '';
it("Mobile app _layout.tsx registers package/create route", str_contains($appLayoutContent, 'name="package/create"'), $passes, $failures);

echo "\n--------------------------------------------------------\n";
echo "Results: {$passes} passed, {$failures} failed\n";
echo "--------------------------------------------------------\n";

if ($failures > 0) {
    exit(1);
}

