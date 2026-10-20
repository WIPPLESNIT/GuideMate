<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\RentalRequest;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\Message;

final class VehicleDetailsAndMessagingTest
{
    private array $results = [];

    public function run(): void
    {
        echo "========================================================\n";
        echo " GuideMate Vehicle Details & Messaging Test Suite\n";
        echo "========================================================\n\n";

        $this->testVehicleCatalogIncludesRichAttributes();
        $this->testVehicleDetailEndpointReturnsCompleteInformation();
        $this->testVehicleDetailEndpointReturns404ForNonexistent();
        $this->testProviderMessagingIntegration();

        $passed = count(array_filter($this->results));
        $total = count($this->results);

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$passed} passed, " . ($total - $passed) . " failed\n";
        echo "--------------------------------------------------------\n";

        if ($passed !== $total) {
            exit(1);
        }
    }

    private function testVehicleCatalogIncludesRichAttributes(): void
    {
        $catalog = RentalRequest::vehicleCatalog();
        $hasVehicles = $catalog !== [];
        $first = $catalog[0] ?? null;

        $hasRequiredFields = $first !== null
            && isset($first['id'])
            && isset($first['name'])
            && isset($first['make'])
            && isset($first['model'])
            && isset($first['year'])
            && isset($first['type'])
            && isset($first['price_per_day'])
            && isset($first['provider_id'])
            && isset($first['provider_name'])
            && isset($first['location'])
            && isset($first['description'])
            && isset($first['images'])
            && is_array($first['images'])
            && count($first['images']) >= 1;

        $ok = $hasVehicles && $hasRequiredFields;
        $this->record('testVehicleCatalogIncludesRichAttributes', $ok);
    }

    private function testVehicleDetailEndpointReturnsCompleteInformation(): void
    {
        $vehicle = Vehicle::approvedAndAvailable()[0] ?? null;
        if ($vehicle === null) {
            $this->record('testVehicleDetailEndpointReturnsCompleteInformation', false);
            return;
        }

        $prevDisplay = ini_get('display_errors');
        @ini_set('display_errors', '0');

        $controller = new \App\Controllers\ApiController();

        ob_start();
        $controller->rentalDetail((string) $vehicle['id']);
        $raw = ob_get_clean();

        @ini_set('display_errors', $prevDisplay);

        $data = json_decode($raw ?: '', true);

        $ok = is_array($data)
            && isset($data['vehicle'])
            && (string) $data['vehicle']['id'] === (string) $vehicle['id']
            && isset($data['vehicle']['provider'])
            && (int) $data['vehicle']['provider']['id'] === (int) $vehicle['user_id']
            && isset($data['vehicle']['requirements'])
            && count($data['vehicle']['requirements']) >= 3
            && isset($data['vehicle']['images'])
            && is_array($data['vehicle']['images']);

        $this->record('testVehicleDetailEndpointReturnsCompleteInformation', $ok);
    }

    private function testVehicleDetailEndpointReturns404ForNonexistent(): void
    {
        $prevDisplay = ini_get('display_errors');
        @ini_set('display_errors', '0');

        $controller = new \App\Controllers\ApiController();

        ob_start();
        $controller->rentalDetail('999999');
        $raw = ob_get_clean();

        @ini_set('display_errors', $prevDisplay);

        $data = json_decode($raw ?: '', true);

        $ok = is_array($data) && isset($data['error']);
        $this->record('testVehicleDetailEndpointReturns404ForNonexistent', $ok);
    }

    private function testProviderMessagingIntegration(): void
    {
        $vehicle = Vehicle::approvedAndAvailable()[0] ?? null;
        if ($vehicle === null) {
            $this->record('testProviderMessagingIntegration', false);
            return;
        }

        $providerId = (int) $vehicle['user_id'];
        $provider = User::find($providerId);

        $ok = $provider !== null
            && !empty($provider['name'])
            && ($provider['role'] === 'rental_provider');

        $this->record('testProviderMessagingIntegration', $ok);
    }

    private function record(string $name, bool $passed): void
    {
        $this->results[$name] = $passed;
        $status = $passed ? '[PASS]' : '[FAIL]';
        echo " {$status} {$name}\n";
    }
}

(new VehicleDetailsAndMessagingTest())->run();
