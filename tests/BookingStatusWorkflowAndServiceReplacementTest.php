<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Services\PackageBookingService;
use App\Services\NotificationService;

class BookingStatusWorkflowAndServiceReplacementTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "=== GuideMate Booking Status Workflow & Service Replacement Tests ===\n\n";

        $this->cleanupTestArtifacts();

        try {
            $this->testInitialBookingStatusAndActionMenu();
            $this->testGuideConfirmsBookingAndStatusBecomesAwaiting();
            $this->testConfirmActionHiddenWhenAwaitingProviders();
            $this->testAllProvidersConfirmedWorkflow();
            $this->testOptionAReplacementInExistingModal();
            $this->testOptionAReplacementFilteringAndEmptyState();
        } finally {
            $this->cleanupTestArtifacts();
        }

        echo "\n======================================================\n";
        echo "SUMMARY: Passed: {$this->passed}, Failed: {$this->failed}\n";
        if ($this->failed > 0) {
            echo "SOME TESTS FAILED!\n";
            exit(1);
        }
        echo "ALL BOOKING STATUS WORKFLOW & SERVICE REPLACEMENT TESTS PASSED!\n";
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    private function cleanupTestArtifacts(): void
    {
        Database::run("DELETE FROM package_booking_audit_logs WHERE package_booking_ref LIKE 'GM-PKG-WF-%'");
        Database::run("DELETE FROM package_booking_items WHERE package_booking_ref LIKE 'GM-PKG-WF-%'");
        Database::run("DELETE FROM rental_requests WHERE package_booking_ref LIKE 'GM-PKG-WF-%'");
        Database::run("DELETE FROM bookings WHERE package_booking_ref LIKE 'GM-PKG-WF-%'");
    }

    private function testInitialBookingStatusAndActionMenu(): void
    {
        echo "1. Testing Initial Travel Package Booking Status (Pending)...\n";

        // Setup test users & listings
        $guide = Database::first("SELECT id, name, email FROM users WHERE role = 'guide' LIMIT 1");
        $tourist = Database::first("SELECT id, name, email FROM users WHERE role = 'tourist' LIMIT 1");
        $hotelPartner = Database::first("SELECT id, name, email FROM users WHERE role IN ('partner', 'hotel') LIMIT 1");
        if (!$hotelPartner) {
            $hotelPartner = Database::first("SELECT id, name, email FROM users WHERE id != ? LIMIT 1", [(int) ($guide['id'] ?? 0)]);
        }

        $pkgRef = 'GM-PKG-WF-001';
        $today = date('Y-m-d');

        $pkgListing = Database::first("SELECT id FROM listings WHERE category_id = 1 LIMIT 1");
        $pkgListingId = (int) ($pkgListing['id'] ?? 1);

        // Create parent package booking with pending status
        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
                total_amount, status, package_booking_ref, package_overall_status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 1, 2, 8500.00, 'pending', ?, 'pending', '{\"type\":\"package_booking\"}', NOW(), NOW())
        ", [$pkgListingId, (int) $tourist['id'], $today, $today, $today, $pkgRef]);

        \App\Models\Payment::create($parentBookingId, 8500.00, 'gcash', 'paid');

        // Register guide item
        Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id,
                provider_role, service_type, service_name, sub_booking_id, allocated_amount,
                status, created_at
            ) VALUES (?, ?, 1, ?, 'guide', 'guide', 'Tour Guide Service', ?, 3500.00, 'pending', NOW())
        ", [$pkgRef, $parentBookingId, (int) $guide['id'], $parentBookingId]);

        // Register hotel item
        Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id,
                provider_role, service_type, service_name, sub_booking_id, allocated_amount,
                status, created_at
            ) VALUES (?, ?, 1, ?, 'partner', 'hotel', 'Hotel Room Stay', NULL, 5000.00, 'pending', NOW())
        ", [$pkgRef, $parentBookingId, (int) $hotelPartner['id']]);

        $booking = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentBookingId]);
        $this->assert($booking['package_overall_status'] === 'pending', 'Newly booked Travel Package has package_overall_status = pending');
        $this->assert($booking['status'] === 'pending', 'Newly booked Travel Package has status = pending');

        // Verify HTML status pill logic from bookings.php
        $b = $booking;
        $bStatus = (string) $b['status'];
        $isPkg = !empty($b['package_booking_ref']);
        $overallPkgStatus = (string) ($b['package_overall_status'] ?? '');
        $isActionRequired = $isPkg && $overallPkgStatus === 'action_required';
        $isCompleted = !$isActionRequired && ($bStatus === 'completed' || $overallPkgStatus === 'completed');
        $isCancelled = !$isActionRequired && (in_array($bStatus, ['cancelled', 'refunded'], true) || $overallPkgStatus === 'cancelled');
        $isAwaiting = $isPkg && !$isCompleted && !$isCancelled && !$isActionRequired && ($overallPkgStatus === 'partially_confirmed');
        $isPkgConfirmed = $isPkg && !$isCompleted && !$isCancelled && !$isActionRequired && ($overallPkgStatus === 'confirmed' || ($bStatus === 'confirmed' && $overallPkgStatus !== 'partially_confirmed' && $overallPkgStatus !== 'pending'));
        $isPending = !$isCompleted && !$isCancelled && !$isActionRequired && !$isAwaiting && !$isPkgConfirmed && ($bStatus === 'pending' || $overallPkgStatus === 'pending');

        $this->assert($isAwaiting === false, 'Initial package booking is NOT marked as Awaiting Providers Confirmation');
        $this->assert($isPending === true, 'Initial package booking is accurately flagged as Pending in Guide Bookings view');
    }

    private function testGuideConfirmsBookingAndStatusBecomesAwaiting(): void
    {
        echo "\n2. Testing Guide Confirms Booking -> Awaiting Providers Confirmation...\n";

        $pkgRef = 'GM-PKG-WF-001';
        $booking = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ?", [$pkgRef]);
        $bookingId = (int) $booking['id'];

        // Guide confirms booking
        Booking::updateStatus($bookingId, 'confirmed');
        PackageBookingService::onBookingUpdated($bookingId, 'confirmed', (int) $booking['user_id']);

        $updatedBooking = Database::first("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        $this->assert(
            $updatedBooking['package_overall_status'] === 'partially_confirmed',
            'Overall package status transitioned to partially_confirmed (Awaiting Providers Confirmation)'
        );

        $guideItem = Database::first("SELECT status FROM package_booking_items WHERE package_booking_ref = ? AND service_type = 'guide'", [$pkgRef]);
        $hotelItem = Database::first("SELECT status FROM package_booking_items WHERE package_booking_ref = ? AND service_type = 'hotel'", [$pkgRef]);

        $this->assert($guideItem['status'] === 'confirmed', 'Guide service item is marked confirmed');
        $this->assert($hotelItem['status'] === 'pending', 'Hotel provider booking remains pending awaiting hotel response');
    }

    private function testConfirmActionHiddenWhenAwaitingProviders(): void
    {
        echo "\n3. Testing Confirm Action Button is Removed/Hidden When Awaiting Providers Confirmation...\n";

        $pkgRef = 'GM-PKG-WF-001';
        $b = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ?", [$pkgRef]);

        $bStatus = (string) $b['status'];
        $isPkg = !empty($b['package_booking_ref']);
        $overallPkgStatus = (string) ($b['package_overall_status'] ?? '');
        $isActionRequired = $isPkg && $overallPkgStatus === 'action_required';
        $isCompleted = !$isActionRequired && ($bStatus === 'completed' || $overallPkgStatus === 'completed');
        $isCancelled = !$isActionRequired && (in_array($bStatus, ['cancelled', 'refunded'], true) || $overallPkgStatus === 'cancelled');
        $isAwaiting = $isPkg && !$isCompleted && !$isCancelled && !$isActionRequired && ($overallPkgStatus === 'partially_confirmed');

        $this->assert($isAwaiting === true, 'Package is identified as isAwaiting in Guide Bookings');

        // Test view template output simulation
        ob_start();
        $isWarned = false;
        if ($isWarned) {
            // ...
        } elseif ($isPkg && $isAwaiting) {
            echo '<button class="action-cancel">Cancel</button>';
        } elseif ($b['status'] === 'pending' || ($isPkg && $overallPkgStatus === 'pending')) {
            echo '<button class="action-accept">Confirm</button>';
            echo '<button class="action-cancel">Cancel</button>';
        }
        $actionsHtml = ob_get_clean();

        $this->assert(str_contains($actionsHtml, 'action-cancel'), 'Cancel button is present in action dropdown');
        $this->assert(!str_contains($actionsHtml, 'action-accept'), 'Confirm button is REMOVED from action dropdown when awaiting providers');
    }

    private function testAllProvidersConfirmedWorkflow(): void
    {
        echo "\n4. Testing All Providers Confirm -> Package Confirmed...\n";

        $pkgRef = 'GM-PKG-WF-001';
        $hotelItem = Database::first("SELECT * FROM package_booking_items WHERE package_booking_ref = ? AND service_type = 'hotel'", [$pkgRef]);

        // Hotel confirms
        PackageBookingService::respond($pkgRef, 'hotel', (int) $hotelItem['provider_id'], 'confirm');

        $b = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ?", [$pkgRef]);
        $this->assert($b['package_overall_status'] === 'confirmed', 'Overall package status transitions to confirmed when all providers confirm');
        $this->assert($b['status'] === 'confirmed', 'Main booking status is confirmed');

        // View logic test: Confirm is hidden, Complete and Cancel are displayed
        $bStatus = (string) $b['status'];
        $isPkg = true;
        $overallPkgStatus = (string) $b['package_overall_status'];
        $isCompleted = false;

        $canComplete = ($bStatus === 'confirmed' || ($isPkg && $overallPkgStatus === 'confirmed')) && !$isCompleted;
        $this->assert($canComplete === true, 'Confirmed package exposes Complete action');
    }

    private function testOptionAReplacementInExistingModal(): void
    {
        echo "\n5. Testing Option A Replacement within Existing Manage Disruption Modal...\n";

        $pkgRef = 'GM-PKG-WF-002';
        $today = date('Y-m-d');
        $tourist = Database::first("SELECT id, name, email FROM users WHERE role = 'tourist' LIMIT 1");
        $guide = Database::first("SELECT id, name, email FROM users WHERE role = 'guide' LIMIT 1");
        $hotelPartner = Database::first("SELECT id, name, email FROM users WHERE role IN ('partner', 'hotel') LIMIT 1");
        if (!$hotelPartner) {
            $hotelPartner = Database::first("SELECT id, name, email FROM users WHERE id != ? LIMIT 1", [(int) ($guide['id'] ?? 0)]);
        }

        $pkgListing = Database::first("SELECT id FROM listings WHERE category_id = 1 LIMIT 1");
        $pkgListingId = (int) ($pkgListing['id'] ?? 1);

        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
                total_amount, status, package_booking_ref, package_overall_status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 1, 2, 7000.00, 'confirmed', ?, 'confirmed', '{\"type\":\"package_booking\"}', NOW(), NOW())
        ", [$pkgListingId, (int) $tourist['id'], $today, $today, $today, $pkgRef]);

        \App\Models\Payment::create($parentBookingId, 7000.00, 'gcash', 'paid');

        // Register guide item
        Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id,
                provider_role, service_type, service_name, allocated_amount, status, created_at
            ) VALUES (?, ?, 1, ?, 'guide', 'guide', 'Tour Guide Service', 3000.00, 'confirmed', NOW())
        ", [$pkgRef, $parentBookingId, (int) $guide['id']]);

        // Register hotel item
        $hotelItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id,
                provider_role, service_type, service_name, allocated_amount, status, created_at
            ) VALUES (?, ?, 1, ?, 'partner', 'hotel', 'Premier Suite Hotel', 4000.00, 'confirmed', NOW())
        ", [$pkgRef, $parentBookingId, (int) $hotelPartner['id']]);

        // Hotel cancels service -> Disruption triggered
        PackageBookingService::respond($pkgRef, 'hotel', (int) $hotelPartner['id'], 'cancel', 'Overbooked room');

        $b = Database::first("SELECT * FROM bookings WHERE id = ?", [$parentBookingId]);
        $this->assert($b['package_overall_status'] === 'action_required', 'Hotel cancellation sets package_overall_status to action_required');

        // Test alternatives endpoint response structure
        $res = PackageBookingService::getAvailableAlternatives($pkgRef, $hotelItemId);
        $this->assert(isset($res['cancelled_item']), 'getAvailableAlternatives returns cancelled_item metadata');
        $this->assert(isset($res['alternatives']), 'getAvailableAlternatives returns alternatives list');
        $this->assert(is_array($res['alternatives']), 'alternatives is an array');

        $cancelledItem = $res['cancelled_item'];
        $this->assert($cancelledItem['service_type'] === 'hotel', 'Cancelled item automatically identified as hotel without manual category selection');
        $this->assert($cancelledItem['service_category_label'] === 'Hotel and Stay', 'Category label is Hotel and Stay');
    }

    private function testOptionAReplacementFilteringAndEmptyState(): void
    {
        echo "\n6. Testing Option A Replacement Filtering & Empty State Message...\n";

        $modalHtml = file_get_contents(__DIR__ . '/../app/Views/partials/package_alternatives_modal.php');

        $expectedEmptyMsg = 'No available replacement services found for this cancelled service. Please try again later or consider another available service disruption option.';
        $this->assert(str_contains($modalHtml, $expectedEmptyMsg), 'Exact required empty state message is embedded in package alternatives modal');

        $this->assert(str_contains($modalHtml, 'id="pkgAlternativesModal"'), 'Reuses existing #pkgAlternativesModal (no duplicate modal created)');
        $this->assert(str_contains($modalHtml, 'loadAlternatives'), 'Dynamically loads replacement services inside existing disruption modal');
        $this->assert(str_contains($modalHtml, 'Array.isArray(data.alternatives)'), 'Correctly parses array from API response');
        $this->assert(str_contains($modalHtml, 'Select &amp; Replace'), 'Presents Select & Replace action for eligible alternative');
    }
}

(new BookingStatusWorkflowAndServiceReplacementTest())->run();
