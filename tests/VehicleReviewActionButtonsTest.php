<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Vehicle;

class VehicleReviewActionButtonsTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Vehicle Review Vertical Action Buttons Tests\n";
        echo "========================================================\n\n";

        $this->testTemplateMarkupStructure();
        $this->testCssStyleRulesExist();
        $this->testPendingVehicleShowsAllThreeButtons();
        $this->testApprovedVehicleHidesApproveButton();
        $this->testRejectedVehicleHidesRejectButton();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n\n";

        return $this->failed === 0;
    }

    private function testTemplateMarkupStructure(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/vehicles.php');

        $this->assert(
            strpos($viewContent, 'class="col-actions"') !== false,
            'testTemplateMarkupStructure: table header includes col-actions class'
        );

        $this->assert(
            strpos($viewContent, 'class="vehicle-actions-vertical"') !== false,
            'testTemplateMarkupStructure: contains vehicle-actions-vertical wrapper'
        );

        $this->assert(
            strpos($viewContent, 'class="vehicle-action-form"') !== false,
            'testTemplateMarkupStructure: approve form uses vehicle-action-form class'
        );

        $this->assert(
            strpos($viewContent, 'btn-action-vertical') !== false,
            'testTemplateMarkupStructure: buttons have btn-action-vertical class'
        );
    }

    private function testCssStyleRulesExist(): void
    {
        $cssContent = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(
            strpos($cssContent, '.vehicle-actions-vertical') !== false,
            'testCssStyleRulesExist: .vehicle-actions-vertical selector defined'
        );

        $this->assert(
            strpos($cssContent, 'flex-direction: column') !== false,
            'testCssStyleRulesExist: flex-direction: column is used for vertical stacking'
        );

        $this->assert(
            strpos($cssContent, '.btn-action-vertical') !== false,
            'testCssStyleRulesExist: .btn-action-vertical selector defined'
        );

        $this->assert(
            strpos($cssContent, '.vehicle-actions-cell') !== false,
            'testCssStyleRulesExist: .vehicle-actions-cell selector defined'
        );
    }

    private function testPendingVehicleShowsAllThreeButtons(): void
    {
        $rendered = $this->renderVehiclesView([
            [
                'id' => 9991,
                'name' => '2022 Honda Click 125i V2 Automatic',
                'location' => 'Minglanilla, Cebu',
                'year' => 2022,
                'make' => 'Honda',
                'specs' => 'Automatic Transmission',
                'image' => null,
                'provider_name' => 'Miguel Yongzon',
                'provider_email' => 'migyong@sample.com',
                'type' => 'Scooter',
                'price_per_day' => 1010.00,
                'status' => 'pending',
                'submitted_at' => '2026-10-23 07:52:00',
                'reviewed_at' => null,
                'reviewer_name' => null,
                'rejection_reason' => null,
            ]
        ], 'pending');

        $this->assert(
            strpos($rendered, 'class="vehicle-actions-vertical"') !== false,
            'testPendingVehicleShowsAllThreeButtons: wrapper is rendered'
        );

        $this->assert(
            strpos($rendered, '/admin/vehicles/9991') !== false && strpos($rendered, '>Inspect</a>') !== false,
            'testPendingVehicleShowsAllThreeButtons: Inspect button rendered'
        );

        $this->assert(
            strpos($rendered, '/admin/vehicles/9991/approve') !== false && strpos($rendered, '✓ Approve</button>') !== false,
            'testPendingVehicleShowsAllThreeButtons: Approve button rendered'
        );

        $this->assert(
            strpos($rendered, 'openRejectModal(9991') !== false && strpos($rendered, 'Reject') !== false,
            'testPendingVehicleShowsAllThreeButtons: Reject button rendered'
        );
    }

    private function testApprovedVehicleHidesApproveButton(): void
    {
        $rendered = $this->renderVehiclesView([
            [
                'id' => 9992,
                'name' => '2023 Yamaha NMAX 155',
                'location' => 'Cebu City',
                'year' => 2023,
                'make' => 'Yamaha',
                'specs' => null,
                'image' => null,
                'provider_name' => 'Miguel Yongzon',
                'provider_email' => 'migyong@sample.com',
                'type' => 'Scooter',
                'price_per_day' => 1200.00,
                'status' => 'approved',
                'submitted_at' => '2026-10-20 10:00:00',
                'reviewed_at' => '2026-10-20 12:00:00',
                'reviewer_name' => 'Administrator',
                'rejection_reason' => null,
            ]
        ], 'approved');

        $this->assert(
            strpos($rendered, '/admin/vehicles/9992') !== false && strpos($rendered, '>Inspect</a>') !== false,
            'testApprovedVehicleHidesApproveButton: Inspect button is present'
        );

        $this->assert(
            strpos($rendered, '/admin/vehicles/9992/approve') === false,
            'testApprovedVehicleHidesApproveButton: Approve button is correctly omitted for approved vehicle'
        );

        $this->assert(
            strpos($rendered, 'openRejectModal(9992') !== false,
            'testApprovedVehicleHidesApproveButton: Reject button is present to allow unpublishing/rejecting'
        );
    }

    private function testRejectedVehicleHidesRejectButton(): void
    {
        $rendered = $this->renderVehiclesView([
            [
                'id' => 9993,
                'name' => '2020 Kawasaki Ninja 400',
                'location' => 'Mandaue City',
                'year' => 2020,
                'make' => 'Kawasaki',
                'specs' => null,
                'image' => null,
                'provider_name' => 'Miguel Yongzon',
                'provider_email' => 'migyong@sample.com',
                'type' => 'Sport',
                'price_per_day' => 2500.00,
                'status' => 'rejected',
                'submitted_at' => '2026-10-19 09:00:00',
                'reviewed_at' => '2026-10-20 12:00:00',
                'reviewer_name' => 'Administrator',
                'rejection_reason' => 'Please provide clear registration documents.',
            ]
        ], 'rejected');

        $this->assert(
            strpos($rendered, '/admin/vehicles/9993') !== false && strpos($rendered, '>Inspect</a>') !== false,
            'testRejectedVehicleHidesRejectButton: Inspect button is present'
        );

        $this->assert(
            strpos($rendered, '/admin/vehicles/9993/approve') !== false,
            'testRejectedVehicleHidesRejectButton: Approve button is present for rejected vehicle'
        );

        $this->assert(
            strpos($rendered, 'openRejectModal(9993') === false,
            'testRejectedVehicleHidesRejectButton: Reject button is correctly omitted for already rejected vehicle'
        );
    }

    /**
     * @param array<int,array<string,mixed>> $vehicles
     */
    private function renderVehiclesView(array $vehicles, string $status = 'all'): string
    {
        $pendingCount = 1;
        ob_start();
        include __DIR__ . '/../app/Views/admin/vehicles.php';
        return (string) ob_get_clean();
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo " [FAIL] {$message}\n";
        }
    }
}

$test = new VehicleReviewActionButtonsTest();
$success = $test->run();
exit($success ? 0 : 1);
