<?php
/**
 * GuideMate - Test Suite for Step 2 Unselected-by-Default Behavior
 * Verifies that:
 * 1. No card (Activities, Attractions, Hotels, Vehicles) is selected by default when Step 2 loads.
 * 2. Preselected card states ($idx === 0) are completely removed.
 * 3. Initial JS state for hotels and vehicles defaults to 0 and 'None'.
 * 4. Step 2 counter badge reflects 0 items selected initially.
 * 5. Selection functions (toggleActivityCard, selectHotelRoom, selectVehicle) correctly apply .is-selected and .selected when chosen.
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
echo " GuideMate - Step 2 Unselected-by-Default Tests\n";
echo "========================================================\n\n";

$builderViewPath = __DIR__ . '/../app/Views/guide/package-builder.php';
$builderContent = file_exists($builderViewPath) ? file_get_contents($builderViewPath) : '';

// 1. Hotel selection preselected state removed
it("Step 2 does NOT preselect first hotel card ($idx === 0 removed)", !str_contains($builderContent, '$isSelectedHotel = ($idx === 0);'), $passes, $failures);
it("Step 2 sets isSelectedHotel to false initially", str_contains($builderContent, '$isSelectedHotel = false;'), $passes, $failures);

// 2. Vehicle selection preselected state removed
it("Step 2 does NOT preselect first vehicle card ($idx === 0 removed)", !str_contains($builderContent, '$isSelectedVehicle = ($idx === 0);'), $passes, $failures);
it("Step 2 sets isSelectedVehicle to false initially", str_contains($builderContent, '$isSelectedVehicle = false;'), $passes, $failures);

// 3. Hidden input defaults
it("selected_hotel_name defaults to 'None' in create mode", str_contains($builderContent, 'id="selected_hotel_name" name="selected_hotel_name" value="<?= $isEdit && !empty($selectedHotelName) ? e($selectedHotelName) : \'None\' ?>"'), $passes, $failures);
it("selected_vehicle_name defaults to 'None' in create mode", str_contains($builderContent, 'id="selected_vehicle_name" name="selected_vehicle_name" value="<?= $isEdit && !empty($selectedVehicleName) ? e($selectedVehicleName) : \'None\' ?>"'), $passes, $failures);

// 4. JavaScript initial state
it("selectedHotelRate defaults to 0 in create mode", str_contains($builderContent, 'let selectedHotelRate = <?= $isEdit && !empty($selectedHotelRate) ? (float) $selectedHotelRate : 0 ?>;'), $passes, $failures);
it("selectedHotelName defaults to 'None' in create mode", str_contains($builderContent, 'let selectedHotelName = "<?= $isEdit && !empty($selectedHotelName) ? e(addslashes($selectedHotelName)) : \'None\' ?>";'), $passes, $failures);
it("selectedVehicleDailyRate defaults to 0 in create mode", str_contains($builderContent, 'let selectedVehicleDailyRate = <?= $isEdit && !empty($selectedVehicleDailyRate) ? (float) $selectedVehicleDailyRate : 0 ?>;'), $passes, $failures);
it("selectedVehicleModel defaults to 'None' in create mode", str_contains($builderContent, 'let selectedVehicleModel = "<?= $isEdit && !empty($selectedVehicleModel) ? e(addslashes($selectedVehicleModel)) : \'None\' ?>";'), $passes, $failures);

// 5. Counter badge calculation supports zero items initially
it("updateStep2Counters handles None hotel and None vehicle properly", str_contains($builderContent, "const hasHotel = (selectedHotelName && selectedHotelName !== 'None');") && str_contains($builderContent, "const hasVehicle = (selectedVehicleModel && selectedVehicleModel !== 'None');"), $passes, $failures);

// 6. Interactive selection logic is preserved
it("selectHotelRoom adds .selected only to the chosen card", str_contains($builderContent, "document.querySelectorAll('[id^=\"hotelCard_\"]').forEach(el => el.classList.remove('selected'))") && str_contains($builderContent, "parentCard.classList.add('selected')"), $passes, $failures);
it("selectVehicle adds .selected only to the chosen card", str_contains($builderContent, "document.querySelectorAll('[id^=\"vehicleCard_\"]').forEach(el => el.classList.remove('selected'))") && str_contains($builderContent, "parentCard.classList.add('selected')"), $passes, $failures);
it("toggleActivityCard toggles .is-selected on click", str_contains($builderContent, "cardEl.classList.remove('is-selected')") && str_contains($builderContent, "cardEl.classList.add('is-selected')"), $passes, $failures);

// 7. Step 3 renders empty/optional status when nothing is chosen
it("renderStep3Details shows 'No Hotel Selected' when unselected", str_contains($builderContent, "selectedHotelName === 'None' || !selectedHotelName) ? 'No Hotel Selected'"), $passes, $failures);
it("renderStep3Details shows 'No Rental Vehicle Selected' when unselected", str_contains($builderContent, "selectedVehicleModel === 'None' || !selectedVehicleModel) ? 'No Rental Vehicle Selected'"), $passes, $failures);

echo "\n--------------------------------------------------------\n";
echo "Results: {$passes} passed, {$failures} failed\n";
echo "--------------------------------------------------------\n";

if ($failures > 0) {
    exit(1);
}
