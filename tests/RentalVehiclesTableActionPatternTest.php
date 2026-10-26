<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Controllers\RentalController;
use App\Models\Vehicle;

final class RentalVehiclesTableActionPatternTest
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<int, int> */
    private array $createdVehicleIds = [];
    /** @var array<int, int> */
    private array $createdUserIds = [];

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Rental Vehicles Action Pattern & Table Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testStatBoxesAndHeaderButtonRemoved', [$this, 'testStatBoxesAndHeaderButtonRemoved']);
        $this->runTest('testInventoryTableMarkupAndNoScrollbar', [$this, 'testInventoryTableMarkupAndNoScrollbar']);
        $this->runTest('testRenderedTableHasThreeDotMenuAndStaticAvailability', [$this, 'testRenderedTableHasThreeDotMenuAndStaticAvailability']);
        $this->runTest('testCssStylesEliminateScrollbarAndStyleDropdown', [$this, 'testCssStylesEliminateScrollbarAndStyleDropdown']);
        $this->runTest('testToggleAvailabilityActionExecution', [$this, 'testToggleAvailabilityActionExecution']);

        $this->cleanup();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function runTest(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo " [PASS] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            echo " [FAIL] {$name}: {$e->getMessage()}\n";
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public function testStatBoxesAndHeaderButtonRemoved(): void
    {
        $viewContent = file_get_contents(BASE_DIR . '/app/Views/rental/vehicles.php');
        $this->assert($viewContent !== false, 'Unable to read rental/vehicles.php');

        // 1. Stat grid must be completely removed
        $this->assert(!str_contains($viewContent, 'stat-grid'), 'Summary stat-grid must be removed from rental/vehicles.php');
        $this->assert(!str_contains($viewContent, 'Total Vehicles'), 'Total Vehicles summary box must be removed');
        $this->assert(!str_contains($viewContent, 'Approved &amp; Available') && !str_contains($viewContent, 'Approved & Available'), 'Approved & Available summary box must be removed');
        $this->assert(!str_contains($viewContent, 'Pending Approval') || !str_contains($viewContent, 'stat-ico-card'), 'Pending Approval stat box must be removed');
        $this->assert(!str_contains($viewContent, 'Action Needed') || !str_contains($viewContent, 'stat-ico-card'), 'Action Needed stat box must be removed');

        // 2. Heading must not have "+ List a Vehicle" button beside it
        $sectionHead = '';
        if (preg_match('/<div class="section-head"[^>]*>(.*?)<\/div>\s*(?:<\?php|\n|<div)/s', $viewContent, $matches)) {
            $sectionHead = $matches[1];
        }
        $this->assert(!str_contains($sectionHead, '+ List a Vehicle'), 'Heading section-head must not contain "+ List a Vehicle" button');
        $this->assert(!str_contains($sectionHead, 'btn-primary'), 'Heading section-head must not contain btn-primary');

        // 3. Panel header MUST still contain "+ List Vehicle"
        $this->assert(str_contains($viewContent, '+ List Vehicle'), 'Panel header must retain "+ List Vehicle" action button');
        $this->assert(str_contains($viewContent, 'data-open-new-listing'), 'Panel header button must retain data-open-new-listing attribute');
    }

    public function testInventoryTableMarkupAndNoScrollbar(): void
    {
        $viewContent = file_get_contents(BASE_DIR . '/app/Views/rental/vehicles.php');

        // 1. No inline overflow-x:auto
        $this->assert(!str_contains($viewContent, 'overflow-x:auto'), 'panel-body must not have inline overflow-x:auto');

        // 2. Wrap class and table class
        $this->assert(str_contains($viewContent, 'rental-inventory-wrap'), 'panel-body must have rental-inventory-wrap class');
        $this->assert(str_contains($viewContent, 'rental-inventory-table'), 'table must have rental-inventory-table class');

        // 3. Proper column headers
        $this->assert(str_contains($viewContent, '<th class="col-vehicle">Vehicle</th>'), 'Table must have col-vehicle column');
        $this->assert(str_contains($viewContent, '<th class="col-type">Type</th>'), 'Table must have col-type column');
        $this->assert(str_contains($viewContent, '<th class="col-rate">Daily Rate</th>'), 'Table must have col-rate column');
        $this->assert(str_contains($viewContent, '<th class="col-status">Status</th>'), 'Table must have col-status column');
        $this->assert(str_contains($viewContent, '<th class="col-availability">Availability</th>'), 'Table must have col-availability column');
        $this->assert(str_contains($viewContent, '<th class="col-actions">Actions</th>'), 'Table must have col-actions column');
    }

    public function testRenderedTableHasThreeDotMenuAndStaticAvailability(): void
    {
        // Render view in isolation with mock data
        $vehicles = [
            [
                'id' => 101,
                'name' => '2022 Honda Click 125i V2 Automatic',
                'type' => 'Scooter',
                'location' => 'Minglanilla, Cebu',
                'year' => 2022,
                'make' => 'Honda',
                'price_per_day' => 1010.00,
                'status' => 'approved',
                'is_available' => 1,
                'image' => null,
                'rejection_reason' => null,
                'slug' => '2022-honda-click-125i',
            ],
            [
                'id' => 102,
                'name' => '2023 Yamaha NMAX 155 ABS',
                'type' => 'Scooter',
                'location' => 'Cebu City',
                'year' => 2023,
                'make' => 'Yamaha',
                'price_per_day' => 1500.00,
                'status' => 'approved',
                'is_available' => 0,
                'image' => null,
                'rejection_reason' => null,
                'slug' => '2023-yamaha-nmax-155',
            ],
            [
                'id' => 103,
                'name' => '2021 Toyota Vios 1.3 XLE',
                'type' => 'Sedan',
                'location' => 'Mandaue City',
                'year' => 2021,
                'make' => 'Toyota',
                'price_per_day' => 2200.00,
                'status' => 'rejected',
                'is_available' => 0,
                'image' => null,
                'rejection_reason' => 'Please provide clearer registration photos.',
                'slug' => '2021-toyota-vios',
            ],
        ];
        $stats = ['total' => 3, 'approved' => 2, 'pending' => 0, 'rejected' => 1];
        $isVerified = true;
        $user = ['id' => 88, 'role' => 'rental_provider', 'guide_status' => 'approved'];

        ob_start();
        include BASE_DIR . '/app/Views/rental/vehicles.php';
        $rendered = (string) ob_get_clean();

        // Check Three-Dot Trigger button
        $this->assert(str_contains($rendered, 'class="btn-res-action-trigger"'), 'Rendered table must have btn-res-action-trigger');
        $this->assert(str_contains($rendered, 'id="vehicleActionBtn_101"'), 'Trigger button must have unique id for vehicle 101');
        $this->assert(str_contains($rendered, 'aria-controls="vehicleActionMenu_101"'), 'Trigger button must control vehicleActionMenu_101');
        $this->assert(str_contains($rendered, 'id="vehicleActionMenu_101"'), 'Dropdown menu must exist for vehicle 101');

        // Check Actions inside Three-Dot Dropdown
        $this->assert(str_contains($rendered, '/dashboard/vehicles/101/edit'), 'Edit action must be inside vehicle 101 menu');
        $this->assert(str_contains($rendered, '/dashboard/vehicles/101/toggle-availability'), 'Availability toggle action must be inside vehicle 101 menu');
        $this->assert(str_contains($rendered, 'Mark as Unavailable'), 'Vehicle 101 (available) must offer Mark as Unavailable inside menu');
        $this->assert(str_contains($rendered, 'Mark as Available'), 'Vehicle 102 (unavailable) must offer Mark as Available inside menu');
        $this->assert(str_contains($rendered, '/dashboard/vehicles/103/resubmit'), 'Vehicle 103 (rejected) must offer Submit for Review inside menu');
        $this->assert(str_contains($rendered, '/dashboard/vehicles/101/delete'), 'Delete action must be inside menu');

        // Check Availability Column is STATIC (not a clickable form or button)
        $this->assert(preg_match('/<td class="col-availability">[\s\S]*?class="pill pill-available"[\s\S]*?Available[\s\S]*?<\/td>/u', $rendered) === 1, 'Availability cell for vehicle 101 must be a static pill without form/button');
        $this->assert(preg_match('/<td class="col-availability">[\s\S]*?class="pill pill-unavailable"[\s\S]*?Unavailable[\s\S]*?<\/td>/u', $rendered) === 1, 'Availability cell for vehicle 102 must be a static pill without form/button');
        $this->assert(preg_match('/<td class="col-availability">[\s\S]*?class="hint"[\s\S]*?Requires revision[\s\S]*?<\/td>/u', $rendered) === 1, 'Availability cell for vehicle 103 must show revision hint');

        // Verify that col-availability has NO form elements
        if (preg_match_all('/<td class="col-availability">(.*?)<\/td>/s', $rendered, $availMatches)) {
            foreach ($availMatches[1] as $availCell) {
                $this->assert(!str_contains($availCell, '<form'), 'col-availability must not contain any <form>');
                $this->assert(!str_contains($availCell, '<button'), 'col-availability must not contain any <button>');
            }
        }
    }

    public function testCssStylesEliminateScrollbarAndStyleDropdown(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');
        $this->assert($css !== false, 'Unable to read style.css');

        $this->assert(str_contains($css, '.rental-inventory-wrap'), 'style.css must define .rental-inventory-wrap');
        $this->assert(str_contains($css, '.rental-inventory-table'), 'style.css must define .rental-inventory-table');
        $this->assert(str_contains($css, 'overflow-x: hidden !important;'), 'style.css must ensure overflow-x: hidden on rental-inventory-wrap');
        $this->assert(str_contains($css, '.rental-inventory-table th.col-actions'), 'style.css must style col-actions on rental table');
    }

    public function testToggleAvailabilityActionExecution(): void
    {
        // Set up test rental provider and vehicle in database
        $email = 'rental_toggle_' . bin2hex(random_bytes(4)) . '@example.com';
        $userId = Database::insert(
            "INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES (?, ?, ?, 'rental_provider', 'approved', NOW())",
            ['Rental Provider Toggle', $email, password_hash('Secret123!', PASSWORD_BCRYPT)]
        );
        $this->createdUserIds[] = $userId;

        // Insert vehicle
        $slug = 'test-scooter-' . bin2hex(random_bytes(4));
        $vehicleId = Database::insert(
            "INSERT INTO vehicles (user_id, name, type, location, year, make, model, price_per_day, is_available, status, slug, created_at)
             VALUES (?, 'Test Toggle Scooter', 'Scooter', 'Cebu City', 2022, 'Honda', 'Click', 600.00, 1, 'approved', ?, NOW())",
            [$userId, $slug]
        );
        $this->createdVehicleIds[] = $vehicleId;

        // Verify initial is_available = 1
        $v = Database::first('SELECT * FROM vehicles WHERE id = ?', [$vehicleId]);
        $this->assert($v !== null && (int) $v['is_available'] === 1, 'Initial vehicle is_available must be 1');

        // Simulate toggle availability
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = 'test_token';
        $_POST['csrf_token'] = 'test_token';

        Vehicle::toggleAvailability($vehicleId, false);
        $vAfter1 = Database::first('SELECT * FROM vehicles WHERE id = ?', [$vehicleId]);
        $this->assert($vAfter1 !== null && (int) $vAfter1['is_available'] === 0, 'After first toggle, is_available must be 0');

        Vehicle::toggleAvailability($vehicleId, true);
        $vAfter2 = Database::first('SELECT * FROM vehicles WHERE id = ?', [$vehicleId]);
        $this->assert($vAfter2 !== null && (int) $vAfter2['is_available'] === 1, 'After second toggle, is_available must be 1');
    }

    private function cleanup(): void
    {
        if ($this->createdVehicleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdVehicleIds), '?'));
            Database::run("DELETE FROM vehicles WHERE id IN ({$placeholders})", $this->createdVehicleIds);
        }
        if ($this->createdUserIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            Database::run("DELETE FROM users WHERE id IN ({$placeholders})", $this->createdUserIds);
        }
    }
}

$suite = new RentalVehiclesTableActionPatternTest();
exit($suite->run() ? 0 : 1);
