<?php
/**
 * GuideMate - Comprehensive Test Suite for Guide Package Builder Workflow Enhancement
 * Validates the 5-Step Workflow, Host Info Auto-Retrieval, 4-Way Package Selection,
 * Day-by-Day Itinerary, Pricing Breakdown, and App Version Package Output.
 */

declare(strict_types=1);

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
echo " GuideMate - Package Builder Workflow Enhancement Tests\n";
echo "========================================================\n\n";

$builderViewPath = __DIR__ . '/../app/Views/guide/package-builder.php';
$builderContent = file_exists($builderViewPath) ? file_get_contents($builderViewPath) : '';

// 1. Workflow Sequence
it("Step 1 tab is 'Overview'", str_contains($builderContent, 'stepTab1') && str_contains($builderContent, '<strong>Overview</strong>'), $passes, $failures);
it("Step 2 tab is 'Package Selection'", str_contains($builderContent, 'stepTab2') && str_contains($builderContent, '<strong>Package Selection</strong>'), $passes, $failures);
it("Step 3 tab is 'Selected Package Details'", str_contains($builderContent, 'stepTab3') && str_contains($builderContent, '<strong>Selected Package Details</strong>'), $passes, $failures);
it("Step 4 tab is 'Itinerary'", str_contains($builderContent, 'stepTab4') && str_contains($builderContent, '<strong>Itinerary</strong>'), $passes, $failures);
it("Step 5 tab is 'Pricing'", str_contains($builderContent, 'stepTab5') && str_contains($builderContent, '<strong>Pricing</strong>'), $passes, $failures);

// 2. Step 1 Overview (Preserved Exactly)
it("Step 1 section heading is preserved", str_contains($builderContent, 'Step 1: Package Overview & Basic Information'), $passes, $failures);
it("Step 1 has automatic Special Highlight Badge", str_contains($builderContent, '$autoBadge') && !str_contains($builderContent, 'id="badgePillGroup"'), $passes, $failures);
it("Step 1 has 2-column trip attributes (Duration & Group Size only)", str_contains($builderContent, 'Key Trip Attributes') && str_contains($builderContent, 'Duration Breakdown') && str_contains($builderContent, 'Group Size Scope'), $passes, $failures);
it("Step 1 has 3-slot showcase photo grid", str_contains($builderContent, 'pkg-showcase-grid') && str_contains($builderContent, '3 Photo Slots'), $passes, $failures);

// 3. Step 2 Package Selection (4 Categories)
it("Step 2 includes Category filter buttons", str_contains($builderContent, 'pkg-category-filter-btn') && str_contains($builderContent, 'filterSelectionCategory'), $passes, $failures);
it("Step 2 includes Things to Do category", str_contains($builderContent, 'Things to Do (Guided Adventures & Activities)'), $passes, $failures);
it("Step 2 includes Attractions category", str_contains($builderContent, 'Attractions (Scenic Parks & Ecological Reserves)'), $passes, $failures);
it("Step 2 includes Hotels & Stays category", str_contains($builderContent, 'Hotels & Stays (Partner Accommodations)'), $passes, $failures);
it("Step 2 includes Vehicle Rentals category", str_contains($builderContent, 'Vehicle Rentals (Transportation Partners)'), $passes, $failures);
it("Step 2 includes No Hotel option", str_contains($builderContent, 'id="hotelCard_none"'), $passes, $failures);
it("Step 2 includes No Vehicle option", str_contains($builderContent, 'id="vehicleCard_none"'), $passes, $failures);

// 4. Step 3 Selected Package Details (Image 2 & Image 5)
it("Step 3 displays auto-retrieved Host Information Card matching Image 2", str_contains($builderContent, 'Package Host Information (Auto-Retrieved)') && str_contains($builderContent, 'Licensed Regional Tour Guide'), $passes, $failures);
it("Host card displays DOT accreditation and tour rating", str_contains($builderContent, 'DOT Accredited') && str_contains($builderContent, '$hostRating') && str_contains($builderContent, '$hostReviewCount'), $passes, $failures);
it("Host card displays guide bio quote", str_contains($builderContent, '$hostBio'), $passes, $failures);
it("Host card displays Message Guide Host action", str_contains($builderContent, 'Message Guide Host'), $passes, $failures);
it("Step 3 displays Included Hotel Details card matching Image 5", str_contains($builderContent, 'Included Hotel Details') && str_contains($builderContent, 'step3HotelName'), $passes, $failures);
it("Hotel card in Step 3 includes amenities pills", str_contains($builderContent, 'Free High-Speed Wi-Fi') && str_contains($builderContent, 'Swimming Pool Access') && str_contains($builderContent, 'Complimentary Daily Breakfast'), $passes, $failures);
it("Step 3 displays Included Rental Vehicle card matching Image 5", str_contains($builderContent, 'Included Rental Vehicle') && str_contains($builderContent, 'step3VehicleModel'), $passes, $failures);
it("Vehicle card in Step 3 includes feature pills", str_contains($builderContent, '2 DOT-Certified Safety Helmets') && str_contains($builderContent, 'Comprehensive 24/7 Roadside Assistance'), $passes, $failures);
it("Step 3 includes Perks & Inclusions matching Image 3", str_contains($builderContent, 'Included Perks & What\'s Not Included'), $passes, $failures);
it("Step 3 includes Policies & Requirements matching Image 5", str_contains($builderContent, 'id="pkg_policies"'), $passes, $failures);

// 5. Step 4 Day-by-Day Itinerary (Image 4)
it("Step 4 dynamically generates Day 1..N tabs", str_contains($builderContent, 'id="itineraryDayTabs"') && str_contains($builderContent, 'updateItineraryDays'), $passes, $failures);
it("Step 4 includes Day Title and Day Narrative inputs", str_contains($builderContent, 'id="activeDayTitle"') && str_contains($builderContent, 'id="activeDayDesc"'), $passes, $failures);
it("Step 4 includes Timed Schedule Timeline with add/remove slots", str_contains($builderContent, 'id="activeDayTimeline"') && str_contains($builderContent, 'addTimelineSlot'), $passes, $failures);
it("Step 4 includes Day Highlights checkmarks matching Image 4", str_contains($builderContent, 'id="activeDayHighlights"') && str_contains($builderContent, 'addDayHighlight'), $passes, $failures);

// 6. Step 5 Pricing Breakdown
it("Step 5 displays Things to Do subtotal in simulator", str_contains($builderContent, 'id="sim_activity_fee"'), $passes, $failures);
it("Step 5 displays Attractions subtotal in simulator", str_contains($builderContent, 'id="sim_attraction_fee"'), $passes, $failures);
it("Step 5 displays Hotel Stay subtotal in simulator", str_contains($builderContent, 'id="sim_hotel_fee"'), $passes, $failures);
it("Step 5 displays Transport subtotal in simulator", str_contains($builderContent, 'id="sim_rental_fee"'), $passes, $failures);
it("Step 5 displays Guide Margin in simulator", str_contains($builderContent, 'id="sim_guide_margin"'), $passes, $failures);
it("Step 5 displays Calculated Total Package Price", str_contains($builderContent, 'id="sim_final_total"'), $passes, $failures);
it("Step 5 has interactive Guide Margin controller", str_contains($builderContent, 'id="guide_margin"') && str_contains($builderContent, 'calculateFinancials'), $passes, $failures);

// 7. Backend & API Synchronization
$apiControllerPath = __DIR__ . '/../app/Controllers/ApiController.php';
$apiControllerContent = file_exists($apiControllerPath) ? file_get_contents($apiControllerPath) : '';
it("ApiController formatListingAsPackage formats host details matching Image 2", str_contains($apiControllerContent, '\'role\' => \'Licensed Regional Tour Guide\'') && str_contains($apiControllerContent, '\'badge\' => \'DOT Accredited\''), $passes, $failures);
it("ApiController formatListingAsPackage includes Image 3 inclusions", str_contains($apiControllerContent, 'Kawasan Falls Natural Park') && str_contains($apiControllerContent, 'Full Kawasan Canyoneering & Cliff Jumps') && str_contains($apiControllerContent, 'All Environmental & Local Tourism Fees'), $passes, $failures);
it("ApiController formatListingAsPackage includes Day-by-Day itinerary matching Image 4", str_contains($apiControllerContent, 'Day 1: Coastal Arrival & Moalboal Sunset Marine Sanctuary') && str_contains($apiControllerContent, '01:00 PM') && str_contains($apiControllerContent, 'Resort check-in & scooter handover briefing'), $passes, $failures);
it("ApiController formatListingAsPackage includes hotel specs matching Image 5", str_contains($apiControllerContent, 'Sea Breeze Oceanfront Resort') && str_contains($apiControllerContent, 'Free High-Speed Wi-Fi'), $passes, $failures);
it("ApiController formatListingAsPackage includes vehicle specs matching Image 5", str_contains($apiControllerContent, 'Yamaha NMAX 155cc ABS') && str_contains($apiControllerContent, '2 DOT-Certified Safety Helmets'), $passes, $failures);

// 8. Mobile App Output Screen (app/package/[id].tsx)
$appPackageViewPath = __DIR__ . '/../app/package/[id].tsx';
$appPackageContent = file_exists($appPackageViewPath) ? file_get_contents($appPackageViewPath) : '';
it("Mobile app displays Host Information card matching Image 2", str_contains($appPackageContent, 'styles.guideCard') && str_contains($appPackageContent, 'Message Guide Host'), $passes, $failures);
it("Mobile app displays All-In-One Inclusions matching Image 3", str_contains($appPackageContent, 'All-In-One Inclusions') && str_contains($appPackageContent, 'What\'s Not Included:'), $passes, $failures);
it("Mobile app displays Day-by-Day Itinerary matching Image 4", str_contains($appPackageContent, 'Day-by-Day Itinerary') && str_contains($appPackageContent, 'Day Highlights:'), $passes, $failures);
it("Mobile app displays Included Hotel Details matching Image 5", str_contains($appPackageContent, 'Included Hotel Details') && str_contains($appPackageContent, 'packageData.accommodationDetail'), $passes, $failures);
it("Mobile app displays Included Rental Vehicle matching Image 5", str_contains($appPackageContent, 'Included Rental Vehicle') && str_contains($appPackageContent, 'packageData.rentalDetail'), $passes, $failures);

echo "\n--------------------------------------------------------\n";
echo "Results: $passes passed, $failures failed\n";
echo "--------------------------------------------------------\n";

if ($failures > 0) {
    exit(1);
}
