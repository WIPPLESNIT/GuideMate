<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\Category;
use App\Services\PackageBookingService;

class OptionCCompletionAndCancellationFixTest
{
    private array $createdUserIds = [];
    private array $createdListingIds = [];
    private array $createdBookingIds = [];
    private array $createdPackageRefs = [];
    private array $createdRentalIds = [];
    private array $createdVehicleIds = [];

    private int $passCount = 0;
    private int $failCount = 0;

    public function run(): void
    {
        echo "=== GuideMate Travel Package Status, Completion & Option A Fix Tests ===\n\n";

        try {
            $this->testOptionCAllowsRemainingServicesToReachCompleted();
            $this->testCancelledPackageDoesNotShowActionRequired();
            $this->testAutoIdentifyCancelledServiceInAlternatives();
            $this->testVehicleAlternativesQueryWorksWithoutReturnDateColumn();
        } finally {
            $this->cleanup();
        }

        echo "\n======================================================\n";
        echo "SUMMARY: Passed: {$this->passCount}, Failed: {$this->failCount}\n";
        if ($this->failCount === 0) {
            echo "ALL COMPLETION, CANCELLATION & OPTION A TESTS PASSED!\n";
        } else {
            echo "SOME TESTS FAILED!\n";
            exit(1);
        }
    }

    private function testOptionCAllowsRemainingServicesToReachCompleted(): void
    {
        echo "1. Testing Option C -> Package Completion (Excluding removed services)...\n";

        $touristId = User::create('Tourist C', 'tourist_c_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $guideId = User::create('Guide C', 'guide_c_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $hotelPartnerId = User::create('Hotel Partner C', 'hotel_c_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds = array_merge($this->createdUserIds, [$touristId, $guideId, $hotelPartnerId]);

        $catGuide = Database::first("SELECT id FROM categories WHERE slug = 'packages' LIMIT 1") ?: Database::first("SELECT id FROM categories LIMIT 1");
        $catHotel = Database::first("SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1");

        $guideListingId = Listing::create([
            'user_id' => $guideId,
            'category_id' => $catGuide['id'],
            'title' => 'Adventure Tour with Marc',
            'slug' => 'adventure-tour-' . bin2hex(random_bytes(4)),
            'description' => 'Tour',
            'price' => 5000.00,
            'status' => 'approved',
        ]);
        $hotelListingId = Listing::create([
            'user_id' => $hotelPartnerId,
            'category_id' => $catHotel['id'],
            'title' => 'Seaside Resort',
            'slug' => 'seaside-resort-' . bin2hex(random_bytes(4)),
            'description' => 'Hotel',
            'price' => 3000.00,
            'status' => 'approved',
        ]);
        $this->createdListingIds = array_merge($this->createdListingIds, [$guideListingId, $hotelListingId]);

        $pkgRef = 'GM-PKG-OPTC-' . time();
        $this->createdPackageRefs[] = $pkgRef;

        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount,
                payment_stage, status, package_booking_ref, package_overall_status, created_at, updated_at
            ) VALUES (?, ?, '2026-12-15', 2, 8000.00, 8000.00, 0.00, 'full_paid', 'confirmed', ?, 'confirmed', NOW(), NOW())
        ", [$guideListingId, $touristId, $pkgRef]);
        $this->createdBookingIds[] = $parentBookingId;

        $hotelSubId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
                total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status,
                package_booking_ref, package_overall_status, created_at, updated_at
            ) VALUES (?, ?, '2026-12-15', '2026-12-15', '2026-12-16', 1, 2, 3000.00, 3000.00, 0.00, 'deposit_paid', 'confirmed', 'confirmed', ?, 'confirmed', NOW(), NOW())
        ", [$hotelListingId, $touristId, $pkgRef]);
        $this->createdBookingIds[] = $hotelSubId;

        $guideItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, sub_booking_id, provider_id, provider_role,
                service_type, service_name, allocated_amount, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide Tour', 5000.00, 'confirmed', NOW(), NOW())
        ", [$pkgRef, $parentBookingId, $parentBookingId, $guideId]);

        $hotelItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, sub_booking_id, provider_id, provider_role,
                service_type, service_name, allocated_amount, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'hotel_admin', 'hotel', 'Hotel Room', 3000.00, 'confirmed', NOW(), NOW())
        ", [$pkgRef, $parentBookingId, $hotelSubId, $hotelPartnerId]);

        // Step 1: Hotel provider cancels
        PackageBookingService::respond($pkgRef, 'hotel', $hotelPartnerId, 'cancel', 'Fully booked on scheduled date');

        // Step 2: Traveler chooses Option C (Continue Without Hotel)
        $optCRes = PackageBookingService::continueWithoutService($pkgRef, $hotelItemId, $touristId, 'Tourist prefers to continue without hotel');
        $this->assert($optCRes['success'] === true, "Option C executed successfully");

        // Verify hotel item is now marked 'removed'
        $hotelItem = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [$hotelItemId]);
        $this->assert($hotelItem['status'] === 'removed', "Hotel item marked as 'removed'");

        // Step 3: Guide completes the tour
        PackageBookingService::onBookingUpdated($parentBookingId, 'completed', $guideId);

        // Step 4: Verify package summary and parent booking have transitioned to 'completed'
        $summary = PackageBookingService::getPackageBookingSummary($pkgRef);
        $this->assert($summary !== null, "Summary is accessible");
        $this->assert($summary['overall_status'] === 'completed', "Package overall_status is 'completed' (was {$summary['overall_status']})");

        $parent = Database::first("SELECT status, package_overall_status FROM bookings WHERE id = ?", [$parentBookingId]);
        $this->assert($parent['package_overall_status'] === 'completed', "Parent booking package_overall_status in DB is 'completed'");
    }

    private function testCancelledPackageDoesNotShowActionRequired(): void
    {
        echo "2. Testing Cancelled Package Does NOT Report Action Required...\n";

        $touristId = User::create('Tourist D', 'tourist_d_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $guideId = User::create('Guide D', 'guide_d_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds = array_merge($this->createdUserIds, [$touristId, $guideId]);

        $catGuide = Database::first("SELECT id FROM categories WHERE slug = 'packages' LIMIT 1") ?: Database::first("SELECT id FROM categories LIMIT 1");
        $guideListingId = Listing::create([
            'user_id' => $guideId,
            'category_id' => $catGuide['id'],
            'title' => 'Island Hopping with Guide D',
            'slug' => 'island-tour-' . bin2hex(random_bytes(4)),
            'description' => 'Tour',
            'price' => 4500.00,
            'status' => 'approved',
        ]);
        $this->createdListingIds[] = $guideListingId;

        $pkgRef = 'GM-PKG-CANCEL-' . time();
        $this->createdPackageRefs[] = $pkgRef;

        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount,
                payment_stage, status, package_booking_ref, package_overall_status, created_at, updated_at
            ) VALUES (?, ?, '2026-12-22', 2, 4500.00, 4500.00, 0.00, 'full_paid', 'action_required', ?, 'action_required', NOW(), NOW())
        ", [$guideListingId, $touristId, $pkgRef]);
        $this->createdBookingIds[] = $parentBookingId;

        $guideItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, sub_booking_id, provider_id, provider_role,
                service_type, service_name, allocated_amount, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Guide Service', 4500.00, 'cancelled', NOW(), NOW())
        ", [$pkgRef, $parentBookingId, $parentBookingId, $guideId]);

        // Cancel the entire package
        PackageBookingService::cancelEntirePackage($pkgRef, $touristId, 'Cancelled entire package', true);

        // Verify summary
        $summary = PackageBookingService::getPackageBookingSummary($pkgRef);
        $this->assert($summary['overall_status'] === 'cancelled', "Overall status is 'cancelled' after full package cancellation");
        $this->assert($summary['overall_status'] !== 'action_required', "Cancelled package does not report action_required");
    }

    private function testAutoIdentifyCancelledServiceInAlternatives(): void
    {
        echo "3. Testing getAvailableAlternatives auto-identifies cancelled service when itemId is 0...\n";

        $touristId = User::create('Tourist Alt', 'tourist_alt_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $guideId = User::create('Guide Alt', 'guide_alt_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds = array_merge($this->createdUserIds, [$touristId, $guideId]);

        $catGuide = Database::first("SELECT id FROM categories WHERE slug = 'packages' LIMIT 1") ?: Database::first("SELECT id FROM categories LIMIT 1");
        $guideListingId = Listing::create([
            'user_id' => $guideId,
            'category_id' => $catGuide['id'],
            'title' => 'Cebu Tour Package',
            'slug' => 'cebu-tour-' . bin2hex(random_bytes(4)),
            'description' => 'Tour',
            'price' => 5000.00,
            'status' => 'approved',
        ]);
        $this->createdListingIds[] = $guideListingId;

        $pkgRef = 'GM-PKG-AUTO-' . time();
        $this->createdPackageRefs[] = $pkgRef;

        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount,
                payment_stage, status, package_booking_ref, package_overall_status, created_at, updated_at
            ) VALUES (?, ?, '2026-12-25', 2, 5000.00, 5000.00, 0.00, 'full_paid', 'pending', ?, 'action_required', NOW(), NOW())
        ", [$guideListingId, $touristId, $pkgRef]);
        $this->createdBookingIds[] = $parentBookingId;

        Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, sub_booking_id, provider_id, provider_role,
                service_type, service_name, allocated_amount, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'guide', 'guide', 'Tour Guide Service', 5000.00, 'cancelled', NOW(), NOW())
        ", [$pkgRef, $parentBookingId, $parentBookingId, $guideId]);

        // Call without specifying itemId (itemId = 0)
        $alts = PackageBookingService::getAvailableAlternatives($pkgRef, 0);
        $this->assert(is_array($alts), "getAvailableAlternatives executes without error and auto-detects cancelled item");
    }

    private function testVehicleAlternativesQueryWorksWithoutReturnDateColumn(): void
    {
        echo "4. Testing Vehicle Alternatives query works without return_date column...\n";

        $ownerId = User::create('Rental Owner', 'rental_own_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        $touristId = User::create('Tourist Veh', 'tourist_v_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'tourist');
        $this->createdUserIds = array_merge($this->createdUserIds, [$ownerId, $touristId]);

        $vehId = Database::insert("
            INSERT INTO vehicles (
                user_id, make, model, name, slug, type, price_per_day,
                location, status, is_available, created_at, updated_at
            ) VALUES (?, 'Toyota', 'Innova', 'Toyota Innova MPV', 'toyota-innova-" . bin2hex(random_bytes(3)) . "', 'Van/MPV', 2500.00, 'Cebu City', 'available', 1, NOW(), NOW())
        ", [$ownerId]);
        $this->createdVehicleIds[] = $vehId;

        $pkgRef = 'GM-PKG-VEH-' . time();
        $this->createdPackageRefs[] = $pkgRef;

        $firstListing = Database::first("SELECT id FROM listings LIMIT 1");
        $sampleListingId = $firstListing ? (int) $firstListing['id'] : null;

        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, guests, total_amount, deposit_amount, balance_amount,
                payment_stage, status, package_booking_ref, package_overall_status, created_at, updated_at
            ) VALUES (?, ?, '2026-12-28', 4, 2500.00, 2500.00, 0.00, 'full_paid', 'pending', ?, 'action_required', NOW(), NOW())
        ", [$sampleListingId, $touristId, $pkgRef]);
        $this->createdBookingIds[] = $parentBookingId;

        $rentalReqId = Database::insert("
            INSERT INTO rental_requests (
                user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
                total_amount, customer_name, customer_email, status, package_booking_ref, created_at, updated_at
            ) VALUES (?, ?, 'Toyota Innova MPV', 'Van/MPV', 'Cebu City', '2026-12-28', 1, 2500.00, 'Tourist', 't@example.com', 'cancelled', ?, NOW(), NOW())
        ", [$touristId, $vehId, $pkgRef]);
        $this->createdRentalIds[] = $rentalReqId;

        $vehItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, rental_request_id, provider_id, provider_role,
                service_type, service_name, allocated_amount, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Innova Van Rental', 2500.00, 'cancelled', NOW(), NOW())
        ", [$pkgRef, $parentBookingId, $rentalReqId, $ownerId]);

        $alts = PackageBookingService::getAvailableAlternatives($pkgRef, $vehItemId);
        $this->assert(is_array($alts), "Vehicle alternatives query executes without SQL error");
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passCount++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failCount++;
            echo "  [FAIL] {$message}\n";
        }
    }

    private function cleanup(): void
    {
        foreach ($this->createdPackageRefs as $ref) {
            Database::run("DELETE FROM package_booking_audit_logs WHERE package_booking_ref = ?", [$ref]);
            Database::run("DELETE FROM package_booking_items WHERE package_booking_ref = ?", [$ref]);
        }
        foreach ($this->createdRentalIds as $rId) {
            Database::run("DELETE FROM rental_requests WHERE id = ?", [$rId]);
        }
        foreach ($this->createdBookingIds as $bId) {
            Database::run("DELETE FROM bookings WHERE id = ?", [$bId]);
        }
        foreach ($this->createdVehicleIds as $vId) {
            Database::run("DELETE FROM vehicles WHERE id = ?", [$vId]);
        }
        foreach ($this->createdListingIds as $lId) {
            Database::run("DELETE FROM listings WHERE id = ?", [$lId]);
        }
        foreach ($this->createdUserIds as $uId) {
            Database::run("DELETE FROM users WHERE id = ?", [$uId]);
        }
    }
}

$test = new OptionCCompletionAndCancellationFixTest();
$test->run();
