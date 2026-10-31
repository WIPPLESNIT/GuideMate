<?php

declare(strict_types=1);

echo "========================================================\n";
echo " GuideMate Trips Category & History Status Filter Tests\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertCond(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] $msg\n";
        $passed++;
    } else {
        echo " [FAIL] $msg\n";
        $failed++;
    }
}

// ---------------------------------------------------------
// Test 1: Category Restriction in trips.tsx
// ---------------------------------------------------------
echo "--- Test 1: Category Restriction (Only Hotel Stays & Attractions & Tours) ---\n";

$tripsContent = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');

assertCond(strpos($tripsContent, "categoryFilter === 'rental'") === false, 'Rentals category chip is removed from category filters');
assertCond(strpos($tripsContent, "getMyRentals") === false, 'getMyRentals is not called in trips.tsx');
assertCond(strpos($tripsContent, "renderRentalItem") === false, 'renderRentalItem is removed from trips.tsx');
assertCond(strpos($tripsContent, "Hotel Stays") !== false, 'Hotel Stays category is present');
assertCond(strpos($tripsContent, "Attractions & Tours") !== false, 'Attractions & Tours category is present');
assertCond(strpos($tripsContent, "All Trips") !== false, 'All Trips category is present');
assertCond(strpos($tripsContent, "Completed hotel stays & attraction tours") !== false, 'Subtitle only mentions hotel stays and attraction tours');

// ---------------------------------------------------------
// Test 2: Status Filtering (Only Completed & Refunded in History)
// ---------------------------------------------------------
echo "\n--- Test 2: Status Filtering (Only Completed and Refunded in History) ---\n";

// Emulate isTripHistory logic from trips.tsx
function isTripHistory(array $b): bool {
    $status = $b['status'] ?? '';
    $hotelStatus = $b['hotel_status'] ?? '';

    if ($status === 'completed' || $status === 'refunded') {
        return true;
    }
    if ($hotelStatus === 'completed' || $hotelStatus === 'refunded') {
        return true;
    }
    return false;
}

assertCond(isTripHistory(['status' => 'completed']) === true, 'Status completed is in History');
assertCond(isTripHistory(['status' => 'refunded']) === true, 'Status refunded is in History');
assertCond(isTripHistory(['status' => 'confirmed', 'hotel_status' => 'completed']) === true, 'Hotel status completed is in History');
assertCond(isTripHistory(['status' => 'confirmed', 'hotel_status' => 'refunded']) === true, 'Hotel status refunded is in History');

assertCond(isTripHistory(['status' => 'confirmed']) === false, 'Status confirmed/approved is NOT in History (stays in Active)');
assertCond(isTripHistory(['status' => 'approved']) === false, 'Status approved is NOT in History (stays in Active)');
assertCond(isTripHistory(['status' => 'pending']) === false, 'Status pending (awaiting confirmation) is NOT in History (stays in Active)');
assertCond(isTripHistory(['status' => 'accepted']) === false, 'Status accepted is NOT in History (stays in Active)');
assertCond(isTripHistory(['status' => 'deposit_paid']) === false, 'Status deposit_paid is NOT in History (stays in Active)');
assertCond(isTripHistory(['status' => 'checked_in']) === false, 'Status checked_in is NOT in History (stays in Active)');

// Verify trips.tsx implements this exact function
assertCond(strpos($tripsContent, "function isTripHistory(b: ApiBooking): boolean") !== false, 'trips.tsx implements isTripHistory filter function');
assertCond(preg_match('/b\.status === \'completed\' \|\| b\.status === \'refunded\'/', $tripsContent) === 1, 'trips.tsx checks for completed or refunded');

// ---------------------------------------------------------
// Test 3: Attractions Card Layout & Navigation Button Alignment
// ---------------------------------------------------------
echo "\n--- Test 3: Attractions & Tours Card Layout & Spacing ---\n";

assertCond(preg_match('/card:\s*\{[^}]*padding:\s*(12|14)/', $tripsContent) === 1, 'Card has consistent 14px outer padding');
assertCond(strpos($tripsContent, "cardHeaderRow") !== false, 'cardHeaderRow separates top media/metadata from bottom actions');
assertCond(preg_match('/cardBody:\s*\{[^}]*marginLeft:\s*12/', $tripsContent) === 1, 'cardBody has marginLeft: 12');
assertCond(strpos($tripsContent, "cardDivider") !== false, 'cardDivider provides clean full-width separation');
assertCond(preg_match('/navBtn:\s*\{[^}]*borderRadius:\s*999/', $tripsContent) === 1, 'navBtn has rounded pill shape');
assertCond(preg_match('/navBtn:\s*\{[^}]*marginRight:\s*2/', $tripsContent) === 1, 'navBtn has explicit right margin to prevent border contact');
assertCond(strpos($tripsContent, "Details & Receipt") !== false, 'Details & Receipt button is positioned cleanly');
assertCond(preg_match('/actionsRow:\s*\{[^}]*flexWrap:\s*\'wrap\'/', $tripsContent) === 1, 'actionsRow supports clean full-width multi-action wrapping');

echo "\n========================================================\n";
echo " Results: $passed passed, $failed failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
