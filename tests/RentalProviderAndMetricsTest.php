<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Router;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Vehicle;

final class RentalProviderAndMetricsTest
{
    private array $createdUserIds = [];
    private array $createdVehicleIds = [];
    private array $createdRequestIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Rental Provider & Metrics Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testRoleReplacementAndMigration', [$this, 'testRoleReplacementAndMigration']);
        $this->runTest('testNewAccountsStartWithZeroRevenueAndZeroRequests', [$this, 'testNewAccountsStartWithZeroRevenueAndZeroRequests']);
        $this->runTest('testMetricsNotCalculatedFromUnrelatedData', [$this, 'testMetricsNotCalculatedFromUnrelatedData']);
        $this->runTest('testRentalRequestUpdatesProviderMetrics', [$this, 'testRentalRequestUpdatesProviderMetrics']);
        $this->runTest('testRentalRefundDeductsProviderRevenue', [$this, 'testRentalRefundDeductsProviderRevenue']);
        $this->runTest('testRentalProviderAuthorizationAndRouteGuards', [$this, 'testRentalProviderAuthorizationAndRouteGuards']);
        $this->runTest('testLegacyRentalAdminSessionCompatibility', [$this, 'testLegacyRentalAdminSessionCompatibility']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testRoleReplacementAndMigration(): void
    {
        // 1. Existing user migrated to rental_provider in DB
        $migratedUsers = Database::all("SELECT id, role FROM users WHERE role = 'rental_admin'");
        $this->assert(
            count($migratedUsers) === 0,
            'All existing users with role rental_admin must be migrated to rental_provider'
        );

        // 2. Direct creation with rental_provider
        $emailP = 'provider_direct_' . bin2hex(random_bytes(4)) . '@example.com';
        $pId = User::create('Provider Direct', $emailP, 'Secret123!', 'rental_provider');
        $this->createdUserIds[] = $pId;

        $userP = User::find($pId);
        $this->assert($userP !== null, 'Created provider must be found');
        $this->assert($userP['role'] === 'rental_provider', 'User role must be rental_provider');
        $this->assert(User::isProviderRole($userP['role']), 'rental_provider must be recognised as provider role');

        // 3. Normalization of legacy rental_admin input
        $emailLegacy = 'provider_legacy_' . bin2hex(random_bytes(4)) . '@example.com';
        $legacyId = User::create('Provider Legacy Input', $emailLegacy, 'Secret123!', 'rental_admin');
        $this->createdUserIds[] = $legacyId;

        $userLegacy = User::find($legacyId);
        $this->assert($userLegacy !== null, 'Legacy input user must be found');
        $this->assert(
            $userLegacy['role'] === 'rental_provider',
            'Passing rental_admin to User::create must automatically normalize to rental_provider'
        );
    }

    public function testNewAccountsStartWithZeroRevenueAndZeroRequests(): void
    {
        // Test newly registered tourist
        $touristEmail = 'tourist_metric_' . bin2hex(random_bytes(4)) . '@example.com';
        $touristId = User::create('Metric Tourist', $touristEmail, 'Secret123!', 'tourist');
        $this->createdUserIds[] = $touristId;
        $tourist = User::find($touristId);

        $this->assert(isset($tourist['revenue']), 'User record must contain revenue column');
        $this->assert(isset($tourist['total_requests']), 'User record must contain total_requests column');
        $this->assert((float) $tourist['revenue'] === 0.0, 'New tourist must have revenue = 0');
        $this->assert((int) $tourist['total_requests'] === 0, 'New tourist must have total_requests = 0');

        // Test newly registered rental_provider
        $providerEmail = 'provider_metric_' . bin2hex(random_bytes(4)) . '@example.com';
        $providerId = User::create('Metric Provider', $providerEmail, 'Secret123!', 'rental_provider');
        $this->createdUserIds[] = $providerId;
        $provider = User::find($providerId);

        $this->assert((float) $provider['revenue'] === 0.0, 'New rental_provider must have revenue = 0');
        $this->assert((int) $provider['total_requests'] === 0, 'New rental_provider must have total_requests = 0');

        // Test newly registered guide
        $guideEmail = 'guide_metric_' . bin2hex(random_bytes(4)) . '@example.com';
        $guideId = User::create('Metric Guide', $guideEmail, 'Secret123!', 'guide');
        $this->createdUserIds[] = $guideId;
        $guide = User::find($guideId);

        $this->assert((float) $guide['revenue'] === 0.0, 'New guide must have revenue = 0');
        $this->assert((int) $guide['total_requests'] === 0, 'New guide must have total_requests = 0');

        // Test newly registered OAuth user
        $oauthId = 'oauth_metric_' . bin2hex(random_bytes(4));
        $oauthUserId = User::createOAuthUser('google', $oauthId, 'OAuth User');
        $this->createdUserIds[] = $oauthUserId;
        $oauthUser = User::find($oauthUserId);

        $this->assert((float) $oauthUser['revenue'] === 0.0, 'New OAuth user must have revenue = 0');
        $this->assert((int) $oauthUser['total_requests'] === 0, 'New OAuth user must have total_requests = 0');
    }

    public function testMetricsNotCalculatedFromUnrelatedData(): void
    {
        // Provider 1: established partner with existing listings and requests
        $p1Id = User::create('Partner One', 'p1_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        $this->createdUserIds[] = $p1Id;
        User::setMetrics($p1Id, 5500.00, 10);

        // Provider 2: brand-new partner registering today
        $p2Id = User::create('Partner Two', 'p2_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        $this->createdUserIds[] = $p2Id;

        $p2 = User::find($p2Id);
        $this->assert(
            (float) $p2['revenue'] === 0.0,
            'Brand-new partner must have revenue = 0 regardless of partner 1 revenue'
        );
        $this->assert(
            (int) $p2['total_requests'] === 0,
            'Brand-new partner must have total_requests = 0 regardless of partner 1 requests'
        );

        // Partner 2 vehicle requests must be strictly empty
        $p2Requests = RentalRequest::forProvider($p2Id);
        $this->assert(
            $p2Requests === [],
            'Brand-new partner must have 0 scoped rental requests'
        );
    }

    public function testRentalRequestUpdatesProviderMetrics(): void
    {
        // Setup provider
        $providerId = User::create('Provider Metrics Update', 'pmu_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        User::setGuideStatus($providerId, 'approved');
        $this->createdUserIds[] = $providerId;

        // Setup tourist
        $touristId = User::create('Tourist Customer', 'tc_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $this->createdUserIds[] = $touristId;

        // Create vehicle listing owned by provider
        $slug = 'v-metric-' . bin2hex(random_bytes(4));
        $vehicleId = Vehicle::create([
            'user_id' => $providerId,
            'make' => 'Yamaha',
            'model' => 'NMAX 155',
            'year' => 2024,
            'type' => 'Scooter',
            'name' => 'Yamaha NMAX 155',
            'slug' => $slug,
            'description' => 'Modern scooter for testing metrics.',
            'specs' => ['Transmission' => 'Automatic'],
            'location' => 'Cebu City',
            'price_per_day' => 800.00,
            'status' => 'approved',
            'is_available' => 1,
        ]);
        $this->createdVehicleIds[] = $vehicleId;

        // Verify initial provider metrics
        $before = User::find($providerId);
        $this->assert((float) $before['revenue'] === 0.0, 'Initial provider revenue must be 0');
        $this->assert((int) $before['total_requests'] === 0, 'Initial provider total_requests must be 0');

        // Customer reserves vehicle for 3 days @ 800/day = 2400.00
        $reqId = RentalRequest::create($touristId, [
            'vehicle_id' => (string) $vehicleId,
            'vehicle_name' => 'Yamaha NMAX 155',
            'vehicle_type' => 'Scooter',
            'shop_name' => 'Provider Metrics Update',
            'location' => 'Cebu City',
            'pickup_date' => date('Y-m-d', strtotime('+2 days')),
            'rental_days' => 3,
            'price_per_day' => 800.00,
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
            'payment_reference' => 'GCASH-123456',
        ]);
        $this->createdRequestIds[] = $reqId;

        // Verify provider metrics updated
        $after = User::find($providerId);
        $this->assert(
            (int) $after['total_requests'] === 1,
            'Provider total_requests must increment to 1 after new booking'
        );
        $this->assert(
            (float) $after['revenue'] === 2400.00,
            'Provider revenue must increase by 2400.00 after paid booking'
        );

        // Verify scoped request lookup returns this request
        $requests = RentalRequest::forProvider($providerId);
        $this->assert(count($requests) === 1, 'Provider must have 1 request in forProvider()');
        $this->assert((int) $requests[0]['id'] === $reqId, 'The scoped request ID must match');
    }

    public function testRentalRefundDeductsProviderRevenue(): void
    {
        // Provider
        $providerId = User::create('Provider Refund', 'pref_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        User::setGuideStatus($providerId, 'approved');
        $this->createdUserIds[] = $providerId;

        // Tourist
        $touristId = User::create('Tourist Refund', 'tref_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $this->createdUserIds[] = $touristId;

        // Vehicle
        $vehicleId = Vehicle::create([
            'user_id' => $providerId,
            'make' => 'Honda',
            'model' => 'Click 125i',
            'year' => 2023,
            'type' => 'Scooter',
            'name' => 'Honda Click 125i Refund Test',
            'slug' => 'v-ref-' . bin2hex(random_bytes(4)),
            'description' => 'Scooter refund test.',
            'specs' => [],
            'location' => 'Mandaue City',
            'price_per_day' => 500.00,
            'status' => 'approved',
            'is_available' => 1,
        ]);
        $this->createdVehicleIds[] = $vehicleId;

        // Book for 2 days @ 500 = 1000.00
        $reqId = RentalRequest::create($touristId, [
            'vehicle_id' => (string) $vehicleId,
            'vehicle_name' => 'Honda Click 125i',
            'vehicle_type' => 'Scooter',
            'shop_name' => 'Provider Refund',
            'location' => 'Mandaue City',
            'pickup_date' => date('Y-m-d', strtotime('+3 days')),
            'rental_days' => 2,
            'price_per_day' => 500.00,
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'payment_reference' => 'CARD-1111',
        ]);
        $this->createdRequestIds[] = $reqId;

        $booked = User::find($providerId);
        $this->assert((float) $booked['revenue'] === 1000.00, 'Provider revenue must be 1000.00 before refund');

        // Refund reservation
        RentalRequest::refund($reqId, 'Cancelled upon request');

        $refunded = User::find($providerId);
        $this->assert(
            (float) $refunded['revenue'] === 0.0,
            'Provider revenue must be deducted by 1000.00 back to 0.00 after refund'
        );
        $this->assert(
            (int) $refunded['total_requests'] === 1,
            'Total requests count must remain intact to preserve lifetime booking count history'
        );
    }

    public function testRentalProviderAuthorizationAndRouteGuards(): void
    {
        $providerId = User::create('Provider Auth', 'pauth_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        $this->createdUserIds[] = $providerId;

        // Simulate session auth
        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $this->assert(Auth::check(), 'Auth::check() must return true for logged-in provider');
        $this->assert(Auth::hasRole('rental_provider'), 'Auth::hasRole(rental_provider) must be true');
        $this->assert(Auth::hasRole('rental_admin'), 'Auth::hasRole(rental_admin) backward-compatibility must be true');
        $this->assert(!Auth::hasRole('tourist'), 'Auth::hasRole(tourist) must be false');
        $this->assert(!Auth::hasRole('guide'), 'Auth::hasRole(guide) must be false');

        unset($_SESSION['user_id']);
        Auth::refreshUser();
    }

    public function testLegacyRentalAdminSessionCompatibility(): void
    {
        // 1. Verify a tourist does not satisfy rental_provider
        $touristId = User::create('Tourist Compat', 'tcompat_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $this->createdUserIds[] = $touristId;
        $_SESSION['user_id'] = $touristId;
        Auth::refreshUser();

        $this->assert(!Auth::hasRole('rental_provider'), 'Tourist must not satisfy rental_provider role');

        // 2. Verify an existing rental_provider user satisfies both rental_provider and legacy rental_admin checks
        $providerId = User::create('Legacy Admin Compat', 'lcompat_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        $this->createdUserIds[] = $providerId;

        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $this->assert(Auth::hasRole('rental_provider'), 'rental_provider user must satisfy rental_provider role');
        $this->assert(Auth::hasRole('rental_admin'), 'rental_provider user must satisfy legacy rental_admin role check');

        unset($_SESSION['user_id']);
        Auth::refreshUser();
    }

    private function tearDown(): void
    {
        if ($this->createdRequestIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdRequestIds), '?'));
            Database::run("DELETE FROM rental_requests WHERE id IN ({$placeholders})", $this->createdRequestIds);
        }

        if ($this->createdVehicleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdVehicleIds), '?'));
            Database::run("DELETE FROM vehicles WHERE id IN ({$placeholders})", $this->createdVehicleIds);
        }

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
}

$suite = new RentalProviderAndMetricsTest();
$success = $suite->run();
exit($success ? 0 : 1);
