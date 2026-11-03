<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

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
echo " GuideMate Admin Search & Filter Unification Tests\n";
echo "========================================================\n\n";

// 1. Guide Listings Review View Tests
$guideViewPath = __DIR__ . '/../app/Views/admin/guide-listings.php';
assertCondition(file_exists($guideViewPath), 'admin/guide-listings.php exists');
$guideContent = file_get_contents($guideViewPath);

assertCondition(str_contains($guideContent, 'id="guideSearchInput"'), 'Guide view includes #guideSearchInput search bar');
assertCondition(str_contains($guideContent, 'id="guideSearchClear"'), 'Guide view includes #guideSearchClear button');
assertCondition(str_contains($guideContent, 'id="guideStatusBtn"'), 'Guide view includes #guideStatusBtn status dropdown button');
assertCondition(str_contains($guideContent, 'id="guideStatusPopover"'), 'Guide view includes #guideStatusPopover status dropdown popover');
assertCondition(str_contains($guideContent, 'id="guideCategoryBtn"'), 'Guide view includes #guideCategoryBtn category dropdown button');
assertCondition(str_contains($guideContent, 'id="guideCategoryPopover"'), 'Guide view includes #guideCategoryPopover category dropdown popover');
assertCondition(str_contains($guideContent, 'All Listings'), 'Guide view includes "All Listings" status option');
assertCondition(str_contains($guideContent, 'Pending Review'), 'Guide view includes "Pending Review" status option');
assertCondition(str_contains($guideContent, 'Approved'), 'Guide view includes "Approved" status option');
assertCondition(str_contains($guideContent, 'Rejected'), 'Guide view includes "Rejected" status option');
assertCondition(str_contains($guideContent, 'All Types'), 'Guide view includes "All Types" category option');
assertCondition(str_contains($guideContent, 'Attractions'), 'Guide view includes "Attractions" category option');
assertCondition(str_contains($guideContent, 'Things to Do'), 'Guide view includes "Things to Do" category option');
assertCondition(str_contains($guideContent, 'id="guideFilterEmptyRow"'), 'Guide view includes #guideFilterEmptyRow empty state');
assertCondition(str_contains($guideContent, 'users-toolbar'), 'Guide view uses unified .users-toolbar styling');
assertCondition(str_contains($guideContent, 'filter-custom-dropdown'), 'Guide view uses .filter-custom-dropdown component');

// 2. Hotel Listings Review View Tests
$hotelViewPath = __DIR__ . '/../app/Views/admin/hotels.php';
assertCondition(file_exists($hotelViewPath), 'admin/hotels.php exists');
$hotelContent = file_get_contents($hotelViewPath);

assertCondition(!str_contains($hotelContent, '<div class="tabs"'), 'Hotel view no longer uses old .tabs bar');
assertCondition(str_contains($hotelContent, 'id="hotelSearchInput"'), 'Hotel view includes #hotelSearchInput search bar');
assertCondition(str_contains($hotelContent, 'id="hotelSearchClear"'), 'Hotel view includes #hotelSearchClear button');
assertCondition(str_contains($hotelContent, 'id="hotelStatusBtn"'), 'Hotel view includes #hotelStatusBtn status dropdown button');
assertCondition(str_contains($hotelContent, 'id="hotelStatusPopover"'), 'Hotel view includes #hotelStatusPopover status dropdown popover');
assertCondition(!str_contains($hotelContent, 'id="hotelLocationBtn"'), 'Hotel view has removed #hotelLocationBtn location filter');
assertCondition(!str_contains($hotelContent, 'id="hotelLocationPopover"'), 'Hotel view has removed #hotelLocationPopover location popover');
assertCondition(str_contains($hotelContent, 'All Listings'), 'Hotel view includes "All Listings" status option');
assertCondition(str_contains($hotelContent, 'Pending Review'), 'Hotel view includes "Pending Review" status option');
assertCondition(str_contains($hotelContent, 'Approved'), 'Hotel view includes "Approved" status option');
assertCondition(str_contains($hotelContent, 'Rejected'), 'Hotel view includes "Rejected" status option');
assertCondition(!str_contains($hotelContent, 'All Locations'), 'Hotel view has removed "All Locations" filter completely');
assertCondition(str_contains($hotelContent, 'id="hotelFilterEmptyRow"'), 'Hotel view includes #hotelFilterEmptyRow empty state');
assertCondition(str_contains($hotelContent, 'id="adminRejectModal"'), 'Hotel view preserves #adminRejectModal');
assertCondition(str_contains($hotelContent, 'id="adminDeleteModal"'), 'Hotel view preserves #adminDeleteModal');

// 3. Vehicle Listings Review View Tests
$vehicleViewPath = __DIR__ . '/../app/Views/admin/vehicles.php';
assertCondition(file_exists($vehicleViewPath), 'admin/vehicles.php exists');
$vehicleContent = file_get_contents($vehicleViewPath);

assertCondition(!str_contains($vehicleContent, '<div class="tabs"'), 'Vehicle view no longer uses old .tabs bar');
assertCondition(str_contains($vehicleContent, 'id="vehicleSearchInput"'), 'Vehicle view includes #vehicleSearchInput search bar');
assertCondition(str_contains($vehicleContent, 'id="vehicleSearchClear"'), 'Vehicle view includes #vehicleSearchClear button');
assertCondition(str_contains($vehicleContent, 'id="vehicleStatusBtn"'), 'Vehicle view includes #vehicleStatusBtn status dropdown button');
assertCondition(str_contains($vehicleContent, 'id="vehicleStatusPopover"'), 'Vehicle view includes #vehicleStatusPopover status dropdown popover');
assertCondition(str_contains($vehicleContent, 'id="vehicleTypeBtn"'), 'Vehicle view includes #vehicleTypeBtn type dropdown button');
assertCondition(str_contains($vehicleContent, 'id="vehicleTypePopover"'), 'Vehicle view includes #vehicleTypePopover type dropdown popover');
assertCondition(str_contains($vehicleContent, 'All Listings'), 'Vehicle view includes "All Listings" status option');
assertCondition(str_contains($vehicleContent, 'Pending Review'), 'Vehicle view includes "Pending Review" status option');
assertCondition(str_contains($vehicleContent, 'Approved'), 'Vehicle view includes "Approved" status option');
assertCondition(str_contains($vehicleContent, 'Rejected'), 'Vehicle view includes "Rejected" status option');
assertCondition(str_contains($vehicleContent, 'All Types'), 'Vehicle view includes "All Types" vehicle type option');
assertCondition(str_contains($vehicleContent, 'Scooter'), 'Vehicle view includes "Scooter" vehicle type option');
assertCondition(str_contains($vehicleContent, 'Motorcycle'), 'Vehicle view includes "Motorcycle" vehicle type option');
assertCondition(str_contains($vehicleContent, 'Sedan'), 'Vehicle view includes "Sedan" vehicle type option');
assertCondition(str_contains($vehicleContent, 'SUV'), 'Vehicle view includes "SUV" vehicle type option');
assertCondition(str_contains($vehicleContent, 'Van'), 'Vehicle view includes "Van" vehicle type option');
assertCondition(str_contains($vehicleContent, 'id="vehicleFilterEmptyRow"'), 'Vehicle view includes #vehicleFilterEmptyRow empty state');
assertCondition(str_contains($vehicleContent, 'id="adminRejectModal"'), 'Vehicle view preserves #adminRejectModal');
assertCondition(str_contains($vehicleContent, 'id="adminDeleteModal"'), 'Vehicle view preserves #adminDeleteModal');

// 4. Controller Integration Tests
$adminControllerPath = __DIR__ . '/../app/Controllers/AdminController.php';
$adminContent = file_get_contents($adminControllerPath);
assertCondition(str_contains($adminContent, 'Listing::allGuideListingsForAdmin(\'all\', \'all\')'), 'AdminController guideListings fetches all listings for client-side search & filtering');
assertCondition(str_contains($adminContent, 'Listing::allHotelsForAdmin(\'all\')'), 'AdminController hotels fetches all hotel listings for client-side search & filtering');
assertCondition(str_contains($adminContent, 'Vehicle::allForAdmin(\'all\')'), 'AdminController vehicles fetches all vehicle listings for client-side search & filtering');

echo "\n--------------------------------------------------------\n";
echo "Results: $passed passed, $failed failed\n";
echo "--------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
