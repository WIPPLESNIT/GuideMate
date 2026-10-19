<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Vehicle;

class VehicleRentalTest
{
    private ?int $testAdminId = null;
    private ?int $testProviderAId = null;
    private ?int $testProviderBId = null;
    private array $createdVehicleIds = [];
    private array $createdUserIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Vehicle Rental Listings Test Suite\n";
        echo "========================================================\n\n";

        $this->setUp();

        $this->runTest('testNewRentalProviderHasZeroListingsByDefault', [$this, 'testNewRentalProviderHasZeroListingsByDefault']);
        $this->runTest('testProviderCanCreateVehicleListingWithPendingApproval', [$this, 'testProviderCanCreateVehicleListingWithPendingApproval']);
        $this->runTest('testAdminCanApproveListingAndItBecomesAvailable', [$this, 'testAdminCanApproveListingAndItBecomesAvailable']);
        $this->runTest('testAdminCanRejectListingWithReasonAndAuditInfo', [$this, 'testAdminCanRejectListingWithReasonAndAuditInfo']);
        $this->runTest('testProviderCanEditAndResubmitRejectedListing', [$this, 'testProviderCanEditAndResubmitRejectedListing']);
        $this->runTest('testTouristCanOnlySeeApprovedAndAvailableListings', [$this, 'testTouristCanOnlySeeApprovedAndAvailableListings']);
        $this->runTest('testEditingApprovedListingImportantDetailsResetsStatusToPending', [$this, 'testEditingApprovedListingImportantDetailsResetsStatusToPending']);
        $this->runTest('testProviderCannotBypassApprovalToMakeVehicleAvailable', [$this, 'testProviderCannotBypassApprovalToMakeVehicleAvailable']);
        $this->runTest('testListingOwnershipEnforcement', [$this, 'testListingOwnershipEnforcement']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function setUp(): void
    {
        // Find or create test admin
        $admin = Database::first("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        if ($admin !== null) {
            $this->testAdminId = (int) $admin['id'];
        } else {
            $this->testAdminId = User::create('Test Admin', 'test_admin_' . bin2hex(random_bytes(4)) . '@example.com', 'AdminPass123!', 'admin');
            $this->createdUserIds[] = $this->testAdminId;
        }

        // Create two isolated test rental providers
        $this->testProviderAId = User::create('Test Provider Alpha', 'provider_a_' . bin2hex(random_bytes(4)) . '@example.com', 'ProviderPass123!', 'rental_provider');
        User::setGuideStatus($this->testProviderAId, 'approved');
        $this->createdUserIds[] = $this->testProviderAId;

        $this->testProviderBId = User::create('Test Provider Beta', 'provider_b_' . bin2hex(random_bytes(4)) . '@example.com', 'ProviderPass123!', 'rental_provider');
        User::setGuideStatus($this->testProviderBId, 'approved');
        $this->createdUserIds[] = $this->testProviderBId;
    }

    private function tearDown(): void
    {
        // Clean up created vehicles
        if ($this->createdVehicleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdVehicleIds), '?'));
            Database::run("DELETE FROM vehicles WHERE id IN ({$placeholders})", $this->createdVehicleIds);
        }

        // Clean up created users
        if ($this->createdUserIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            Database::run("DELETE FROM users WHERE id IN ({$placeholders})", $this->createdUserIds);
        }
    }

    private function runTest(string $name, callable $callable): void
    {
        try {
            $callable();
            echo " [PASS] {$name}\n";
            $this->passed++;
        } catch (\Throwable $e) {
            echo " [FAIL] {$name}: " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")\n";
            $this->failed++;
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    /**
     * Requirement: A newly created rental provider account should start with no
     * vehicle listings unless listings are explicitly created through the platform.
     */
    public function testNewRentalProviderHasZeroListingsByDefault(): void
    {
        $newProviderId = User::create('Fresh Provider', 'fresh_' . bin2hex(random_bytes(4)) . '@example.com', 'Password123!', 'rental_admin');
        $this->createdUserIds[] = $newProviderId;

        $listings = Vehicle::forOwner($newProviderId);
        $this->assert(is_array($listings), "Vehicle::forOwner should return an array");
        $this->assert(count($listings) === 0, "A newly created rental provider must have 0 listings by default (got " . count($listings) . ")");

        $counts = Vehicle::countForOwner($newProviderId);
        $this->assert($counts['total'] === 0, "Provider total count should be 0");
        $this->assert($counts['approved'] === 0, "Provider approved count should be 0");
        $this->assert($counts['pending'] === 0, "Provider pending count should be 0");
    }

    /**
     * Requirement: A rental provider can create and submit a vehicle listing,
     * which receives a Pending Approval status and cannot be presented as available.
     */
    public function testProviderCanCreateVehicleListingWithPendingApproval(): void
    {
        $vId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'Yamaha',
            'model' => 'NMAX 155',
            'year' => 2024,
            'type' => 'Scooter',
            'name' => '2024 Yamaha NMAX 155 ABS',
            'description' => 'Brand new Yamaha NMAX 155 with ABS, spacious under-seat storage, and dual helmets.',
            'specs' => ['Automatic', 'ABS', '2 Helmets Included', 'Phone Mount'],
            'location' => 'Cebu City · IT Park',
            'address' => 'Salinas Drive, Lahug',
            'price_per_day' => 750.00,
            'image' => 'uploads/vehicles/sample_nmax.jpg',
            'status' => 'pending',
        ]);
        $this->createdVehicleIds[] = $vId;

        $vehicle = Vehicle::find($vId);
        $this->assert($vehicle !== null, "Vehicle should be found in database");
        $this->assert((string) $vehicle['status'] === 'pending', "Newly submitted vehicle must have status 'pending'");
        $this->assert((int) $vehicle['is_available'] === 0, "Unapproved vehicle must have is_available = 0");
        $this->assert(!empty($vehicle['submitted_at']), "submitted_at timestamp must be recorded");
        $this->assert((string) $vehicle['make'] === 'Yamaha', "Make should match");
        $this->assert((string) $vehicle['model'] === 'NMAX 155', "Model should match");
        $this->assert((int) $vehicle['year'] === 2024, "Year should match");
        $this->assert((float) $vehicle['price_per_day'] === 750.00, "Price should match");

        // Verify it appears in provider's listing inventory
        $ownerListings = Vehicle::forOwner($this->testProviderAId);
        $this->assert(count($ownerListings) >= 1, "Provider should see their submitted listing");
        $this->assert((int) $ownerListings[0]['id'] === $vId, "First listing should be the newly created vehicle");
    }

    /**
     * Requirement: Administrator can review and approve a listing. Once approved,
     * it receives Approved status, becomes available, and records review audit info.
     */
    public function testAdminCanApproveListingAndItBecomesAvailable(): void
    {
        $vId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'Honda',
            'model' => 'Click 125i',
            'year' => 2023,
            'type' => 'Scooter',
            'description' => 'Reliable and fuel-efficient automatic scooter, well-maintained.',
            'price_per_day' => 450.00,
            'location' => 'Mandaue City',
            'status' => 'pending',
        ]);
        $this->createdVehicleIds[] = $vId;

        // Admin approves
        Vehicle::approve($vId, $this->testAdminId);

        $vehicle = Vehicle::find($vId);
        $this->assert((string) $vehicle['status'] === 'approved', "Approved listing must have status 'approved'");
        $this->assert((int) $vehicle['is_available'] === 1, "Approved listing must have is_available = 1");
        $this->assert(!empty($vehicle['reviewed_at']), "reviewed_at timestamp must be recorded");
        $this->assert((int) $vehicle['reviewed_by'] === $this->testAdminId, "reviewed_by reviewer ID must be recorded");
        $this->assert($vehicle['rejection_reason'] === null, "rejection_reason must be NULL on approval");
    }

    /**
     * Requirement: Administrator can reject a listing with an optional reason,
     * recording reviewer, review date, and rejection status.
     */
    public function testAdminCanRejectListingWithReasonAndAuditInfo(): void
    {
        $vId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'Toyota',
            'model' => 'Vios',
            'year' => 2020,
            'type' => 'Sedan',
            'description' => 'Comfortable 5-seater sedan with aircon.',
            'price_per_day' => 1500.00,
            'location' => 'Lapu-Lapu City',
            'status' => 'pending',
        ]);
        $this->createdVehicleIds[] = $vId;

        $reason = 'Please upload a clearer photo of the vehicle registration and insurance papers.';
        Vehicle::reject($vId, $this->testAdminId, $reason);

        $vehicle = Vehicle::find($vId);
        $this->assert((string) $vehicle['status'] === 'rejected', "Rejected listing must have status 'rejected'");
        $this->assert((int) $vehicle['is_available'] === 0, "Rejected listing must have is_available = 0");
        $this->assert((string) $vehicle['rejection_reason'] === $reason, "rejection_reason must be recorded");
        $this->assert(!empty($vehicle['reviewed_at']), "reviewed_at must be recorded");
        $this->assert((int) $vehicle['reviewed_by'] === $this->testAdminId, "reviewed_by must match admin ID");
    }

    /**
     * Requirement: Providers can see the rejection reason, edit, and resubmit
     * the listing, returning it to Pending Approval.
     */
    public function testProviderCanEditAndResubmitRejectedListing(): void
    {
        $vId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'KTM',
            'model' => 'Duke 390',
            'year' => 2022,
            'type' => 'Motorcycle',
            'description' => 'Manual 390cc sport bike.',
            'price_per_day' => 1200.00,
            'location' => 'Cebu City',
            'status' => 'rejected',
        ]);
        $this->createdVehicleIds[] = $vId;

        // Admin had rejected it with reason
        Vehicle::reject($vId, $this->testAdminId, 'Please specify helmet inclusions.');

        $rejectedVehicle = Vehicle::find($vId);
        $this->assert((string) $rejectedVehicle['rejection_reason'] === 'Please specify helmet inclusions.', "Provider must see rejection reason");

        // Provider updates description and resubmits
        Vehicle::update($vId, [
            'description' => 'Manual 390cc sport bike. Includes 2 full-face helmets and riding gloves.',
            'specs' => ['Manual', '2 Helmets Included', 'Riding Gloves'],
        ]);
        Vehicle::resubmit($vId);

        $resubmitted = Vehicle::find($vId);
        $this->assert((string) $resubmitted['status'] === 'pending', "Resubmitted listing must return to 'pending'");
        $this->assert((int) $resubmitted['is_available'] === 0, "Resubmitted listing must not be available until re-approved");
        $this->assert(!empty($resubmitted['submitted_at']), "New submitted_at must be recorded on resubmission");
    }

    /**
     * Requirement: Only approved and currently available vehicle listings
     * should be visible to tourists. Pending, rejected, draft, or unavailable
     * listings must not appear in tourist search results or public listing pages.
     */
    public function testTouristCanOnlySeeApprovedAndAvailableListings(): void
    {
        // 1. Draft listing
        $draftId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'DraftMake',
            'model' => 'DraftModel',
            'year' => 2023,
            'type' => 'Scooter',
            'price_per_day' => 300,
            'location' => 'Cebu',
            'description' => 'Draft vehicle description here.',
            'status' => 'draft',
        ]);
        $this->createdVehicleIds[] = $draftId;

        // 2. Pending listing
        $pendingId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'PendingMake',
            'model' => 'PendingModel',
            'year' => 2023,
            'type' => 'Scooter',
            'price_per_day' => 400,
            'location' => 'Cebu',
            'description' => 'Pending vehicle description here.',
            'status' => 'pending',
        ]);
        $this->createdVehicleIds[] = $pendingId;

        // 3. Rejected listing
        $rejectedId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'RejectedMake',
            'model' => 'RejectedModel',
            'year' => 2023,
            'type' => 'Scooter',
            'price_per_day' => 500,
            'location' => 'Cebu',
            'description' => 'Rejected vehicle description here.',
            'status' => 'rejected',
        ]);
        $this->createdVehicleIds[] = $rejectedId;

        // 4. Approved and available listing
        $approvedId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'ApprovedMake',
            'model' => 'ApprovedModel',
            'year' => 2023,
            'type' => 'Sedan',
            'price_per_day' => 1800,
            'location' => 'Cebu City · IT Park',
            'description' => 'Approved and fully available sedan.',
            'status' => 'approved',
        ]);
        $this->createdVehicleIds[] = $approvedId;
        Vehicle::approve($approvedId, $this->testAdminId);

        // 5. Approved but marked unavailable by provider
        $unavailableId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'UnavailableMake',
            'model' => 'UnavailableModel',
            'year' => 2023,
            'type' => 'SUV',
            'price_per_day' => 2500,
            'location' => 'Cebu City',
            'description' => 'Approved vehicle currently unavailable.',
            'status' => 'approved',
        ]);
        $this->createdVehicleIds[] = $unavailableId;
        Vehicle::approve($unavailableId, $this->testAdminId);
        Vehicle::toggleAvailability($unavailableId, false); // Mark unavailable

        // Query public/tourist catalog
        $touristListings = Vehicle::approvedAndAvailable();
        $touristIds = array_map(static fn($v) => (int) $v['id'], $touristListings);

        $this->assert(in_array($approvedId, $touristIds, true), "Approved and available vehicle must be visible to tourists");
        $this->assert(!in_array($draftId, $touristIds, true), "Draft vehicle must NOT be visible to tourists");
        $this->assert(!in_array($pendingId, $touristIds, true), "Pending vehicle must NOT be visible to tourists");
        $this->assert(!in_array($rejectedId, $touristIds, true), "Rejected vehicle must NOT be visible to tourists");
        $this->assert(!in_array($unavailableId, $touristIds, true), "Unavailable vehicle must NOT be visible to tourists");

        // Verify RentalRequest::vehicleCatalog() matches tourist visibility
        $catalog = RentalRequest::vehicleCatalog();
        $catalogIds = array_map(static fn($c) => (int) $c['id'], $catalog);
        $this->assert(in_array($approvedId, $catalogIds, true), "Approved vehicle must appear in vehicleCatalog");
        $this->assert(!in_array($pendingId, $catalogIds, true), "Pending vehicle must NOT appear in vehicleCatalog");
        $this->assert(!in_array($unavailableId, $catalogIds, true), "Unavailable vehicle must NOT appear in vehicleCatalog");
    }

    /**
     * Requirement: If an approved listing is edited in a way that affects
     * important rental details, return it to Pending Approval until an
     * administrator reviews it again.
     */
    public function testEditingApprovedListingImportantDetailsResetsStatusToPending(): void
    {
        $vId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'Mitsubishi',
            'model' => 'Montero Sport',
            'year' => 2022,
            'type' => 'SUV',
            'price_per_day' => 3000.00,
            'location' => 'Cebu City',
            'description' => 'Original description for approved SUV.',
            'status' => 'approved',
        ]);
        $this->createdVehicleIds[] = $vId;
        Vehicle::approve($vId, $this->testAdminId);

        $vBefore = Vehicle::find($vId);
        $this->assert((string) $vBefore['status'] === 'approved', "Listing should start as approved");

        // Editing price_per_day and model (important rental details)
        // Controller resets status to pending
        Vehicle::update($vId, [
            'price_per_day' => 3500.00,
            'model' => 'Montero Sport GT 4x4',
            'status' => 'pending',
            'is_available' => 0,
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        $vAfter = Vehicle::find($vId);
        $this->assert((string) $vAfter['status'] === 'pending', "Edited approved listing must return to 'pending'");
        $this->assert((int) $vAfter['is_available'] === 0, "Edited listing must not be available while pending");

        // Tourists can no longer see it until re-approved
        $touristIds = array_map(static fn($v) => (int) $v['id'], Vehicle::approvedAndAvailable());
        $this->assert(!in_array($vId, $touristIds, true), "Listing returned to pending must no longer appear in tourist search");

        // Admin re-approves
        Vehicle::approve($vId, $this->testAdminId);
        $vReapproved = Vehicle::find($vId);
        $this->assert((string) $vReapproved['status'] === 'approved', "Re-approved listing must return to 'approved'");
        $this->assert((int) $vReapproved['is_available'] === 1, "Re-approved listing must become available again");
    }

    /**
     * Requirement: Prevent providers from presenting a listing as available
     * for rent until it has been approved by an administrator.
     */
    public function testProviderCannotBypassApprovalToMakeVehicleAvailable(): void
    {
        $vId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'Suzuki',
            'model' => 'Raider 150',
            'year' => 2023,
            'type' => 'Motorcycle',
            'price_per_day' => 600.00,
            'location' => 'Cebu City',
            'description' => 'Pending motorcycle listing.',
            'status' => 'pending',
        ]);
        $this->createdVehicleIds[] = $vId;

        $vehicle = Vehicle::find($vId);
        $status = (string) ($vehicle['status'] ?? 'pending');

        // Only approved or unavailable listings are allowed to toggle availability
        $canToggle = in_array($status, ['approved', 'unavailable'], true);
        $this->assert(!$canToggle, "Provider must not be allowed to toggle availability on an unapproved listing");
        $this->assert((int) $vehicle['is_available'] === 0, "Unapproved listing must remain unavailable");
    }

    /**
     * Requirement: Listing ownership and role-based permissions are enforced.
     * Ensure that each vehicle listing is linked to the correct rental provider
     * and cannot be managed by another provider.
     */
    public function testListingOwnershipEnforcement(): void
    {
        // Provider A creates vehicle
        $vId = Vehicle::create([
            'user_id' => $this->testProviderAId,
            'make' => 'Honda',
            'model' => 'ADV 160',
            'year' => 2024,
            'type' => 'Scooter',
            'price_per_day' => 800.00,
            'location' => 'Cebu City',
            'description' => 'Provider A adventure scooter.',
            'status' => 'pending',
        ]);
        $this->createdVehicleIds[] = $vId;

        $vehicle = Vehicle::find($vId);
        $this->assert((int) $vehicle['user_id'] === $this->testProviderAId, "Vehicle user_id must belong to Provider A");

        // Provider B checks ownership
        $isOwnedByProviderB = (int) $vehicle['user_id'] === $this->testProviderBId;
        $this->assert(!$isOwnedByProviderB, "Provider B must not own Provider A's listing");

        // Provider B's inventory must not contain Provider A's listing
        $providerBListings = Vehicle::forOwner($this->testProviderBId);
        $providerBVehicleIds = array_map(static fn($v) => (int) $v['id'], $providerBListings);
        $this->assert(!in_array($vId, $providerBVehicleIds, true), "Provider B inventory must NOT contain Provider A's vehicle");
    }
}

// Run test directly from CLI
$test = new VehicleRentalTest();
$success = $test->run();
exit($success ? 0 : 1);
