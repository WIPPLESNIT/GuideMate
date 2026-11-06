<?php
/**
 * GuidePackageStep4WhiteItineraryAndNextButtonTest.php
 *
 * Verifies:
 * 1. Step 4 - Day-by-Day Itinerary section background is white, clean, responsive, readable.
 * 2. Itinerary days sync with Step 1 duration as source of truth, adding/removing days while preserving data.
 * 3. Navigation buttons display exactly "Next" across all steps (Web & Mobile).
 */

$passes = 0;
$failures = 0;

function it(string $title, bool $condition, int &$passes, int &$failures): void {
    if ($condition) {
        echo " [PASS] $title\n";
        $passes++;
    } else {
        echo " [FAIL] $title\n";
        $failures++;
    }
}

echo "========================================================\n";
echo " GuideMate - Step 4 White Itinerary & Next Button Tests\n";
echo "========================================================\n\n";

$builderPath = __DIR__ . '/../app/Views/guide/package-builder.php';
$appCreatePath = __DIR__ . '/../app/package/create.tsx';

$builderContent = file_exists($builderPath) ? file_get_contents($builderPath) : '';
$appCreateContent = file_exists($appCreatePath) ? file_get_contents($appCreatePath) : '';

// 1. Requirement 1: Step 4 Day-by-Day Itinerary Section Background is White
it("Requirement 1: Step 4 section panel has white background (#ffffff)",
    str_contains($builderContent, 'id="stepSection4"') &&
    str_contains($builderContent, 'background:#ffffff'),
    $passes, $failures);

it("Requirement 1: Step 4 itinerary editor card has white background (#ffffff) and subtle border",
    str_contains($builderContent, '<!-- Itinerary Day Editor Container (White Background Card) -->') &&
    str_contains($builderContent, 'background:#ffffff;border-radius:16px;padding:1.75rem;color:#0f172a;border:1px solid #e2e8f0;'),
    $passes, $failures);

it("Requirement 1: Step 4 editor has no dark #1e293b background",
    !str_contains($builderContent, 'background:#1e293b'),
    $passes, $failures);

it("Requirement 1: Step 4 Day Title and Narrative inputs use white background and light borders",
    str_contains($builderContent, 'id="activeDayTitle"') &&
    str_contains($builderContent, 'background:#ffffff !important;border:1px solid #cbd5e1 !important;color:#0f172a !important;') &&
    str_contains($builderContent, 'id="activeDayDesc"'),
    $passes, $failures);

it("Requirement 1: Step 4 Timed Schedule Timeline box has light background (#f8fafc) and border",
    str_contains($builderContent, 'Timed Schedule Timeline') &&
    str_contains($builderContent, 'background:#f8fafc;border-radius:12px;padding:1.25rem;border:1px solid #e2e8f0;'),
    $passes, $failures);

it("Requirement 1: Step 4 Day Highlights box has light background (#f8fafc) and border",
    str_contains($builderContent, 'Day Highlights (Checkmarks)') &&
    str_contains($builderContent, 'background:#f8fafc;border-radius:12px;padding:1.25rem;border:1px solid #e2e8f0;'),
    $passes, $failures);

it("Requirement 1: JS renderActiveDayContent generates white timeline cards with borders",
    str_contains($builderContent, 'background:#ffffff;padding:0.6rem 0.85rem;border-radius:8px;border:1px solid #e2e8f0;') &&
    str_contains($builderContent, 'updateTimelineItem'),
    $passes, $failures);

it("Requirement 1: JS renderActiveDayContent generates white highlight cards with borders",
    str_contains($builderContent, 'background:#ffffff;padding:0.5rem 0.75rem;border-radius:8px;border:1px solid #e2e8f0;') &&
    str_contains($builderContent, 'updateDayHighlightItem'),
    $passes, $failures);

// 2. Requirement 2: Sync itinerary days with Step 1 duration
it("Requirement 2: Step 1 duration input #pkg_days triggers updateItineraryDays oninput and onchange",
    str_contains($builderContent, 'id="pkg_days"') &&
    str_contains($builderContent, 'oninput="calculateFinancials(); updateItineraryDays();"') &&
    str_contains($builderContent, 'onchange="calculateFinancials(); updateItineraryDays();"'),
    $passes, $failures);

it("Requirement 2: updateItineraryDays parses #pkg_days value as source of truth",
    str_contains($builderContent, "document.getElementById('pkg_days')?.value") &&
    str_contains($builderContent, 'function updateItineraryDays()'),
    $passes, $failures);

it("Requirement 2: updateItineraryDays dynamically adjusts and renders day tab buttons",
    str_contains($builderContent, "class=\"pkg-day-tab-btn") &&
    str_contains($builderContent, "switchItineraryDay"),
    $passes, $failures);

it("Requirement 2: updateItineraryDays maintains itineraryDaysCache to preserve existing data",
    str_contains($builderContent, 'itineraryDaysCache') &&
    str_contains($builderContent, 'saveCurrentDayData()'),
    $passes, $failures);

it("Requirement 2: goToStep saves active day data before navigating",
    str_contains($builderContent, "function goToStep(step) {\n    saveCurrentDayData();"),
    $passes, $failures);

it("Requirement 2: updateItineraryDays runs on DOMContentLoaded to guarantee initial sync",
    str_contains($builderContent, "document.addEventListener('DOMContentLoaded'") &&
    str_contains($builderContent, 'updateItineraryDays();'),
    $passes, $failures);

// 3. Requirement 3: Navigation button label display exactly "Next" across all steps
it("Requirement 3: Step 1 Navigation Next button displays exactly 'Next'",
    preg_match('/<button[^>]*onclick="goToStep\(2\)"[^>]*class="pkg-btn-next"[^>]*>\s*Next\s*<\/button>/i', $builderContent) === 1,
    $passes, $failures);

it("Requirement 3: Step 2 Navigation Next button displays exactly 'Next'",
    preg_match('/<button[^>]*onclick="goToStep\(3\)"[^>]*class="pkg-btn-next"[^>]*>\s*Next\s*<\/button>/i', $builderContent) === 1,
    $passes, $failures);

it("Requirement 3: Step 3 Navigation Next button displays exactly 'Next'",
    preg_match('/<button[^>]*onclick="goToStep\(4\)"[^>]*class="pkg-btn-next"[^>]*>\s*Next\s*<\/button>/i', $builderContent) === 1,
    $passes, $failures);

it("Requirement 3: Step 4 Navigation Next button displays exactly 'Next'",
    preg_match('/<button[^>]*onclick="goToStep\(5\)"[^>]*class="pkg-btn-next"[^>]*>\s*Next\s*<\/button>/i', $builderContent) === 1,
    $passes, $failures);

it("Requirement 3: Old step-specific suffix 'Next: Pricing & Cost Breakdown' is removed",
    !str_contains($builderContent, 'Next: Pricing & Cost Breakdown'),
    $passes, $failures);

it("Requirement 3: Old step-specific suffix 'Next: Package Selection' is removed from Web view",
    !str_contains($builderContent, 'Next: Package Selection'),
    $passes, $failures);

it("Requirement 3: Mobile app Navigation button displays exactly 'Next'",
    str_contains($appCreateContent, '<Text style={styles.nextBtnText}>Next</Text>'),
    $passes, $failures);

echo "\n--------------------------------------------------------\n";
echo "Results: $passes passed, $failures failed\n";
echo "--------------------------------------------------------\n";

exit($failures > 0 ? 1 : 0);
