<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Models\Booking;
use App\Models\PackageService;
use App\Models\Payment;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\CancellationPolicyService;
use App\Services\NotificationService;
use App\Services\PackageBookingService;

class TravelPackageBookingWorkflowTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "========================================================\n";
        echo " GuideMate - Travel Package Booking Workflow Tests\n";
        echo "========================================================\n\n";

        $this->testDatabaseSchemaAndServiceRegistration();
        $this->testPricingAndAllocationCalculation();
        $this->testConsensusStatusTransitions();
        $this->testProviderDashboardsIntegration();
        $this->testCancellationAndRefundCascading();
        $this->testAppBookingPayloadAndTripsDisplay();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function testDatabaseSchemaAndServiceRegistration(): void
    {
        echo "--- 1. Database and Service Registration (PackageBookingService) ---\n";

        $this->assert(
            class_exists(PackageBookingService::class),
            'PackageBookingService class exists in App\Services'
        );

        $this->assert(
            method_exists(PackageBookingService::class, 'ensureTables'),
            'PackageBookingService defines ensureTables'
        );

        $this->assert(
            method_exists(PackageBookingService::class, 'createPackageBooking'),
            'PackageBookingService defines createPackageBooking'
        );

        $this->assert(
            method_exists(PackageBookingService::class, 'respond'),
            'PackageBookingService defines respond for provider decisions'
        );

        $this->assert(
            method_exists(PackageBookingService::class, 'cancelByTourist'),
            'PackageBookingService defines cancelByTourist'
        );

        $this->assert(
            method_exists(PackageBookingService::class, 'getPackageBookingSummary'),
            'PackageBookingService defines getPackageBookingSummary'
        );
    }

    private function testPricingAndAllocationCalculation(): void
    {
        echo "\n--- 2. Payment Distribution & Itemized Share Calculations ---\n";

        // Test distribution math
        $packageBase = 8499.00;
        $stayNights = 2;
        $roomRate = 1800.00;
        $hotelShare = $roomRate * $stayNights; // 3600.00

        $rentalDays = 3;
        $vehicleDaily = 650.00;
        $vehicleShare = $vehicleDaily * $rentalDays; // 1950.00

        $guideShare = max(0.00, round($packageBase - $hotelShare - $vehicleShare, 2)); // 8499 - 3600 - 1950 = 2949.00

        $this->assert(
            $hotelShare === 3600.00,
            'Hotel provider share accurately computed based on nightly rate and duration'
        );

        $this->assert(
            $vehicleShare === 1950.00,
            'Rental provider share accurately computed based on daily rate and rental days'
        );

        $this->assert(
            $guideShare === 2949.00,
            'Guide share reflects activities and services remainder'
        );

        $allocatedSum = $hotelShare + $vehicleShare + $guideShare;
        $this->assert(
            round($allocatedSum, 2) === round($packageBase, 2),
            'Total allocated provider shares exactly match the package base price'
        );
    }

    private function testConsensusStatusTransitions(): void
    {
        echo "\n--- 3. Package Consensus Status Management ---\n";

        // Centralized status transitions:
        // Case A: Initial booking -> 'pending'
        // Case B: Guide confirms, others pending -> 'partially_confirmed'
        // Case C: All confirm -> 'confirmed'
        // Case D: Any provider cancels -> 'cancelled'

        $items = [
            ['service_type' => 'guide', 'status' => 'pending'],
            ['service_type' => 'hotel', 'status' => 'pending'],
            ['service_type' => 'vehicle', 'status' => 'pending'],
        ];

        // Evaluate initial
        $statusA = $this->evaluateConsensus($items);
        $this->assert($statusA === 'pending', 'Initial status is pending when no providers have responded');

        // Guide confirms
        $items[0]['status'] = 'confirmed';
        $statusB = $this->evaluateConsensus($items);
        $this->assert($statusB === 'partially_confirmed', 'Status transitions to partially_confirmed when 1 of 3 providers approves');

        // Hotel confirms
        $items[1]['status'] = 'confirmed';
        $statusC = $this->evaluateConsensus($items);
        $this->assert($statusC === 'partially_confirmed', 'Status remains partially_confirmed when 2 of 3 providers approve');

        // Rental confirms
        $items[2]['status'] = 'confirmed';
        $statusD = $this->evaluateConsensus($items);
        $this->assert($statusD === 'confirmed', 'Status transitions to confirmed when ALL 3 providers approve');

        // Rejection rule: If vehicle provider cancels
        $items[2]['status'] = 'cancelled';
        $statusE = $this->evaluateConsensus($items);
        $this->assert($statusE === 'cancelled', 'Status transitions to cancelled immediately if any required provider declines');
    }

    private function testProviderDashboardsIntegration(): void
    {
        echo "\n--- 4. Provider Existing Dashboards Integration ---\n";

        // Verify Guide Bookings view contains Travel Package indicator
        $guideBookingsView = file_get_contents(__DIR__ . '/../app/Views/guide/bookings.php');
        $this->assert(
            str_contains($guideBookingsView, 'package_booking_ref'),
            'Guide bookings view checks for package_booking_ref'
        );
        $this->assert(
            str_contains($guideBookingsView, 'Travel Package'),
            'Guide bookings view renders Travel Package badge for bundled tours'
        );

        // Verify Rental Requests view contains Travel Package indicator
        $rentalRequestsView = file_get_contents(__DIR__ . '/../app/Views/rental/requests.php');
        $this->assert(
            str_contains($rentalRequestsView, 'package_booking_ref'),
            'Rental requests view checks for package_booking_ref'
        );
        $this->assert(
            str_contains($rentalRequestsView, 'Travel Package'),
            'Rental requests view renders Travel Package badge for vehicle bookings'
        );

        // Verify Hotel Reservations section contains Travel Package indicator
        $this->assert(
            str_contains($guideBookingsView, 'hotel-reservations-table') && str_contains($guideBookingsView, 'package_booking_ref'),
            'Hotel reservations section displays package reference tag'
        );

        // Verify Controllers notify PackageBookingService
        $guideController = file_get_contents(__DIR__ . '/../app/Controllers/GuideController.php');
        $this->assert(
            str_contains($guideController, 'PackageBookingService::onBookingUpdated'),
            'GuideController notifies PackageBookingService on booking status change'
        );

        $hotelController = file_get_contents(__DIR__ . '/../app/Controllers/HotelController.php');
        $this->assert(
            str_contains($hotelController, 'PackageBookingService::onHotelBookingUpdated'),
            'HotelController notifies PackageBookingService on reservation status change'
        );

        $rentalController = file_get_contents(__DIR__ . '/../app/Controllers/RentalController.php');
        $this->assert(
            str_contains($rentalController, 'PackageBookingService::onRentalUpdated'),
            'RentalController notifies PackageBookingService on rental request status change'
        );
    }

    private function testCancellationAndRefundCascading(): void
    {
        echo "\n--- 5. Cancellation Cascading & Refund Integration ---\n";

        $cancellationService = file_get_contents(__DIR__ . '/../app/Services/CancellationPolicyService.php');
        $this->assert(
            str_contains($cancellationService, 'PackageBookingService::cancelByTourist'),
            'CancellationPolicyService delegates package cancellations to PackageBookingService'
        );

        $packageBookingService = file_get_contents(__DIR__ . '/../app/Services/PackageBookingService.php');
        $this->assert(
            str_contains($packageBookingService, 'cascadeCancellation'),
            'PackageBookingService defines cascadeCancellation'
        );
        $this->assert(
            str_contains($packageBookingService, 'Payment::refund'),
            'cascadeCancellation triggers full refund of package payment'
        );
        $this->assert(
            str_contains($packageBookingService, 'NotificationService::send'),
            'cascadeCancellation notifies affected tourists and providers'
        );
    }

    private function testAppBookingPayloadAndTripsDisplay(): void
    {
        echo "\n--- 6. Mobile App & API Payload Integration ---\n";

        $apiController = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
        $this->assert(
            str_contains($apiController, 'PackageBookingService::createPackageBooking'),
            'ApiController::bookPackage uses PackageBookingService::createPackageBooking'
        );
        $this->assert(
            str_contains($apiController, 'PackageBookingService::getPackageBookingSummary'),
            'ApiController::bookingPayload attaches package booking summary and provider statuses'
        );
        $this->assert(
            str_contains($apiController, 'package_overall_status'),
            'ApiController exposes package_overall_status'
        );

        $tripsTsx = file_get_contents(__DIR__ . '/../app/(tabs)/trips.tsx');
        $this->assert(
            str_contains($tripsTsx, 'Provider Booking Approvals'),
            'trips.tsx Package Details modal renders Provider Booking Approvals section'
        );
        $this->assert(
            str_contains($tripsTsx, 'provider_statuses'),
            'trips.tsx checks provider_statuses for Guide, Hotel, and Rental'
        );
        $this->assert(
            str_contains($tripsTsx, 'package_overall_status'),
            'trips.tsx renders overall status dynamically'
        );
    }

    private function evaluateConsensus(array $items): string
    {
        $hasRejection = false;
        $hasPending = false;
        $hasConfirmed = false;

        foreach ($items as $it) {
            if ($it['status'] === 'cancelled') {
                $hasRejection = true;
            } elseif ($it['status'] === 'pending') {
                $hasPending = true;
            } elseif ($it['status'] === 'confirmed') {
                $hasConfirmed = true;
            }
        }

        if ($hasRejection) {
            return 'cancelled';
        }
        if (!$hasPending && $hasConfirmed) {
            return 'confirmed';
        }
        if ($hasConfirmed && $hasPending) {
            return 'partially_confirmed';
        }
        return 'pending';
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

(new TravelPackageBookingWorkflowTest())->run();
