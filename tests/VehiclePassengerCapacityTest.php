<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Vehicle;
use App\Controllers\RentalController;
use App\Controllers\ApiController;
use App\Models\RentalRequest;
use App\Services\PackageBookingService;

final class VehiclePassengerCapacityTest
{
    private array $cleanupVehicleIds = [];

    public function run(): void
    {
        echo "====================================================================\n";
        echo " GuideMate – Vehicle Passenger Capacity Field Test Suite\n";
        echo "====================================================================\n\n";

        try {
            $this->testDatabaseStructureAndExistingVehicles();
            $this->testNewVehicleValidationAndCreation();
            $this->testEditVehicleRetrievalAndUpdating();
            $this->testDisruptionAndAlternativeCapacityDisplay();
            $this->testApiPayloadsIncludeCapacity();
            $this->testAppAndWebFrontendFiles();
            echo "\n====================================================================\n";
            echo "All tests passed successfully!\n";
            echo "====================================================================\n";
        } finally {
            $this->cleanup();
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException("FAILED: {$message}");
        }
        echo "  [PASS] {$message}\n";
    }

    private function testDatabaseStructureAndExistingVehicles(): void
    {
        echo "1. Testing database structure and existing live vehicle listings...\n";

        $cols = array_column(Database::all('DESCRIBE vehicles'), 'Field');
        $this->assert(in_array('passenger_capacity', $cols, true), "vehicles table has 'passenger_capacity' column");

        // Verify vehicles in database have non-negative passenger capacity saved
        $existingVehicles = Database::all('SELECT id, passenger_capacity FROM vehicles');
        $this->assert(count($existingVehicles) > 0, "Found existing vehicle records in database");

        $allValid = true;
        foreach ($existingVehicles as $v) {
            if (!isset($v['passenger_capacity']) || (int) $v['passenger_capacity'] < 0) {
                $allValid = false;
                break;
            }
        }
        $this->assert($allValid, "All vehicle listings have valid non-negative passenger_capacity saved in database");

        // Verify Vehicle::find returns passenger_capacity and capacity alias
        $firstId = (int) $existingVehicles[0]['id'];
        $found = Vehicle::find($firstId);
        $this->assert($found !== null, "Existing vehicle can be retrieved via Vehicle::find");
        $this->assert(isset($found['passenger_capacity']) && (int) $found['passenger_capacity'] >= 0, "Existing vehicle passenger_capacity is valid integer");
        $this->assert((int) $found['capacity'] === (int) $found['passenger_capacity'], "Existing vehicle capacity alias matches passenger_capacity");
    }

    private function testNewVehicleValidationAndCreation(): void
    {
        echo "\n2. Testing listing a new vehicle with Passenger Capacity validation...\n";

        $baseInput = [
            'make' => 'Honda',
            'model' => 'Click 125i',
            'year' => (int) date('Y'),
            'type' => 'Scooter',
            'price_per_day' => '500.00',
            'location' => 'Cebu City · IT Park',
            'description' => 'This is a test description for a brand new vehicle with more than twenty characters.',
            'cover_image' => 'uploads/vehicles/test_scooter.jpg',
        ];

        // 1. Missing passenger capacity
        $test1 = $baseInput;
        $res1 = RentalController::validateVehicleInputs($test1);
        $this->assert(isset($res1['errors']['passenger_capacity']), "Rejects missing passenger capacity");

        // 2. Non-numeric passenger capacity
        $test2 = $baseInput;
        $test2['passenger_capacity'] = 'two';
        $res2 = RentalController::validateVehicleInputs($test2);
        $this->assert(isset($res2['errors']['passenger_capacity']), "Rejects non-numeric passenger capacity");

        // 3. Zero passenger capacity
        $test3 = $baseInput;
        $test3['passenger_capacity'] = '0';
        $res3 = RentalController::validateVehicleInputs($test3);
        $this->assert(isset($res3['errors']['passenger_capacity']), "Rejects 0 passenger capacity for new vehicle listing");

        // 4. Negative passenger capacity
        $test4 = $baseInput;
        $test4['passenger_capacity'] = '-2';
        $res4 = RentalController::validateVehicleInputs($test4);
        $this->assert(isset($res4['errors']['passenger_capacity']), "Rejects negative passenger capacity");

        // 5. Valid positive passenger capacity
        $test5 = $baseInput;
        $test5['passenger_capacity'] = '2';
        $res5 = RentalController::validateVehicleInputs($test5);
        $this->assert(!isset($res5['errors']['passenger_capacity']), "Accepts valid positive passenger capacity (2)");
        $this->assert($res5['data']['passenger_capacity'] === 2, "Validated passenger_capacity is 2");

        // 6. Test creation in database
        $provider = Database::first("SELECT id FROM users WHERE role = 'rental_provider' LIMIT 1") ?? ['id' => 1];
        $providerId = (int) $provider['id'];

        $newVehicleData = array_merge($res5['data'], [
            'user_id' => $providerId,
            'status' => 'pending',
            'image' => 'uploads/vehicles/test_scooter.jpg',
        ]);
        $createdId = Vehicle::create($newVehicleData);
        $this->cleanupVehicleIds[] = $createdId;

        $created = Vehicle::find($createdId);
        $this->assert($created !== null, "New vehicle created in database");
        $this->assert((int) $created['passenger_capacity'] === 2, "Persisted passenger_capacity is 2 in database");
        $this->assert((int) $created['capacity'] === 2, "Capacity alias returns 2 in database");
    }

    private function testEditVehicleRetrievalAndUpdating(): void
    {
        echo "\n3. Testing editing an existing vehicle and updating passenger capacity...\n";

        $provider = Database::first("SELECT id FROM users WHERE role = 'rental_provider' LIMIT 1") ?? ['id' => 1];
        $providerId = (int) $provider['id'];

        // Create an existing vehicle that starts with capacity 0 (as legacy listings do)
        $vehicleId = Vehicle::create([
            'user_id' => $providerId,
            'make' => 'Toyota',
            'model' => 'Vios',
            'year' => (int) date('Y'),
            'type' => 'Sedan',
            'passenger_capacity' => 0,
            'name' => '2026 Toyota Vios Test',
            'description' => 'Test vehicle for checking edit form capacity updates with plenty of characters.',
            'location' => 'Mandaue City',
            'price_per_day' => 1800.00,
            'status' => 'approved',
        ]);
        $this->cleanupVehicleIds[] = $vehicleId;

        // Retrieve existing
        $retrieved = Vehicle::find($vehicleId);
        $this->assert((int) $retrieved['passenger_capacity'] === 0, "Existing vehicle initially has passenger_capacity 0");

        // Update passenger capacity to 5
        Vehicle::update($vehicleId, [
            'passenger_capacity' => 5,
        ]);

        $updated = Vehicle::find($vehicleId);
        $this->assert((int) $updated['passenger_capacity'] === 5, "Updated vehicle now has passenger_capacity = 5");
        $this->assert((int) $updated['capacity'] === 5, "Updated vehicle capacity alias is 5");
    }

    private function testDisruptionAndAlternativeCapacityDisplay(): void
    {
        echo "\n4. Testing Manage Service Disruption replacement capacity retrieval...\n";

        // Query available vehicles for alternatives
        $vehicles = Database::all("SELECT id, name, type, passenger_capacity FROM vehicles WHERE passenger_capacity = 0 LIMIT 1");
        if (!empty($vehicles)) {
            $v = $vehicles[0];
            $cap = (int) ($v['passenger_capacity'] ?? 0);
            $this->assert($cap === 0, "Unedited existing vehicle returns 0 passenger capacity for replacement display");
        }

        // Test that PackageBookingService::getAvailableAlternatives maps passenger_capacity
        $altFile = file_get_contents(__DIR__ . '/../app/Services/PackageBookingService.php');
        $this->assert(str_contains($altFile, "\$vCapacity = (int) (\$v['passenger_capacity'] ?? \$v['capacity'] ?? 0);"), "PackageBookingService derives capacity directly from database without hardcoded match fallback");
        $this->assert(str_contains($altFile, "'passenger_capacity' => \$vCapacity,"), "PackageBookingService includes passenger_capacity in replacement alternatives");
    }

    private function testApiPayloadsIncludeCapacity(): void
    {
        echo "\n5. Testing API controllers and models include passenger_capacity...\n";

        $catalog = RentalRequest::vehicleCatalog();
        $this->assert(count($catalog) > 0, "Vehicle catalog returned vehicles");
        $first = $catalog[0];
        $this->assert(array_key_exists('passenger_capacity', $first), "RentalRequest::vehicleCatalog includes 'passenger_capacity'");
        $this->assert(array_key_exists('capacity', $first), "RentalRequest::vehicleCatalog includes 'capacity'");

        $apiFile = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
        $this->assert(str_contains($apiFile, "'passenger_capacity' => (int) (\$vehicle['passenger_capacity'] ?? (\$vehicle['capacity'] ?? 0)),"), "rentalDetail API includes passenger_capacity");
        $this->assert(str_contains($apiFile, "'passenger_capacity' => (int) (\$v['passenger_capacity'] ?? (\$v['capacity'] ?? 0)),"), "rentalUnits API includes passenger_capacity");
    }

    private function testAppAndWebFrontendFiles(): void
    {
        echo "\n6. Testing Web views and App screens for passenger capacity field & display...\n";

        // Web: Modal in vehicles.php
        $vehiclesView = file_get_contents(__DIR__ . '/../app/Views/rental/vehicles.php');
        $this->assert(str_contains($vehiclesView, 'id="vf_passenger_capacity"'), "vehicles.php contains vf_passenger_capacity input");
        $this->assert(str_contains($vehiclesView, 'Passenger Capacity <span class="req"'), "vehicles.php marks Passenger Capacity as required (*)");
        $this->assert(str_contains($vehiclesView, "errors.passenger_capacity = 'Passenger capacity is required and must be a valid number.';"), "vehicles.php validates passenger capacity on submit");
        $this->assert(str_contains($vehiclesView, "passengerCapacityIn.value = (vehicle.passenger_capacity !== undefined && vehicle.passenger_capacity !== null) ? vehicle.passenger_capacity :"), "vehicles.php openEditVehicleModal populates existing capacity");

        // Web: Standalone form
        $formView = file_get_contents(__DIR__ . '/../app/Views/rental/vehicle-form.php');
        $this->assert(str_contains($formView, 'id="v_passenger_capacity"'), "vehicle-form.php contains v_passenger_capacity input");

        // Web: Public detail show.php
        $showView = file_get_contents(__DIR__ . '/../app/Views/rental/show.php');
        $this->assert(str_contains($showView, '<strong>Passenger Capacity:</strong>'), "rental/show.php displays Passenger Capacity");

        // Web: Manage Service Disruption modal (Image 2)
        $altModal = file_get_contents(__DIR__ . '/../app/Views/partials/package_alternatives_modal.php');
        $this->assert(str_contains($altModal, '<strong>Passenger Capacity:</strong> ${(alt.passenger_capacity !== undefined && alt.passenger_capacity !== null) ? alt.passenger_capacity : ((alt.capacity !== undefined && alt.capacity !== null) ? alt.capacity : 0)} seats &bull;'), "package_alternatives_modal displays actual database capacity without hardcoded 4");

        // App: Vehicle Rental Details (Vehicle Specifications section)
        $appRentalDetails = file_get_contents(__DIR__ . '/../app/rental/[id].tsx');
        $this->assert(str_contains($appRentalDetails, '<Text style={[styles.specLabel, { color: colors.textSub }]}>Passenger Capacity</Text>'), "rental/[id].tsx has Passenger Capacity label in Vehicle Specifications");
        $this->assert(str_contains($appRentalDetails, 'vehicle.passenger_capacity !== undefined && vehicle.passenger_capacity !== null'), "rental/[id].tsx displays actual passenger capacity or 0");

        // App: Travel Package Details (Image 3)
        $appTrips = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');
        $this->assert(str_contains($appTrips, '\u2022 Capacity: ${repCap} Passengers'), "trips.tsx displays actual database capacity without hardcoded 4");

        // TypeScript API definitions
        $apiTs = file_get_contents(__DIR__ . '/../services/api.ts');
        $this->assert(str_contains($apiTs, 'passenger_capacity?: number;'), "services/api.ts includes passenger_capacity on ApiRentalVehicle");
    }

    private function cleanup(): void
    {
        foreach ($this->cleanupVehicleIds as $id) {
            Database::run('DELETE FROM vehicles WHERE id = ?', [$id]);
        }
    }
}

$test = new VehiclePassengerCapacityTest();
$test->run();
