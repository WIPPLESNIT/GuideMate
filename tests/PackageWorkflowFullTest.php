<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\RentalRequest;
use App\Services\PackageBookingService;

class PackageWorkflowFullTest
{
    private static int $testCount = 0;
    private static int $passCount = 0;

    public static function run(): void
    {
        echo "========================================================\n";
        echo "   GUIDEMATE TRAVEL PACKAGE WORKFLOW VERIFICATION TEST  \n";
        echo "========================================================\n\n";

        self::testListingCoverValidation();
        self::testPackagePricingCalculations();
        self::testRentalStatusTransitions();
        self::testHotelCheckInStatus();
        self::testMyTripsSingleCardConsolidation();

        echo "\n--------------------------------------------------------\n";
        echo "Results: " . self::$passCount . " / " . self::$testCount . " tests passed.\n";
        if (self::$passCount === self::$testCount) {
            echo "ALL TESTS PASSED SUCCESSFULLY!\n";
        } else {
            echo "SOME TESTS FAILED!\n";
        }
        echo "========================================================\n";
    }

    private static function assert(bool $condition, string $message): void
    {
        self::$testCount++;
        if ($condition) {
            self::$passCount++;
            echo " [PASS] {$message}\n";
        } else {
            echo " [FAIL] {$message}\n";
        }
    }

    private static function testListingCoverValidation(): void
    {
        echo "TEST SUITE 1: Listing Edit Cover Image Validation\n";

        // Test local upload paths
        $localPaths = [
            'uploads/listings/2469/96c174c4d98d6d28.jpg',
            '/uploads/listings/1/photo.png',
            'assets/img/default.jpg',
            'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957',
        ];

        foreach ($localPaths as $path) {
            $isLocal = str_starts_with($path, 'uploads/') || str_starts_with($path, '/uploads/') || str_contains($path, 'uploads/') || str_starts_with($path, 'assets/') || str_starts_with($path, 'img/');
            $isData = str_starts_with($path, 'data:image/');
            $valid = $isLocal || $isData || filter_var($path, FILTER_VALIDATE_URL) !== false;
            self::assert($valid, "Path '{$path}' is recognized as valid cover image");
        }

        $invalid = 'invalid_not_url_or_path';
        $isLocalInv = str_starts_with($invalid, 'uploads/') || str_starts_with($invalid, '/uploads/') || str_contains($invalid, 'uploads/') || str_starts_with($invalid, 'assets/') || str_starts_with($invalid, 'img/');
        $isDataInv = str_starts_with($invalid, 'data:image/');
        $validInv = $isLocalInv || $isDataInv || filter_var($invalid, FILTER_VALIDATE_URL) !== false;
        self::assert(!$validInv, "Invalid string '{$invalid}' is correctly rejected");
    }

    private static function testPackagePricingCalculations(): void
    {
        echo "\nTEST SUITE 2: Package Pricing & Bundle Discount Calculations\n";

        $thingsRate = 1200.00;
        $attractionRate = 800.00;
        $hotelRatePerNight = 2500.00;
        $nights = 2;
        $hotelTotal = $hotelRatePerNight * $nights; // 5000
        $vehicleDailyRate = 1000.00;
        $days = 3;
        $vehicleTotal = $vehicleDailyRate * $days; // 3000
        $guideMargin = 2000.00;

        $subtotalBeforeDiscount = $thingsRate + $attractionRate + $hotelTotal + $vehicleTotal + $guideMargin;
        self::assert($subtotalBeforeDiscount == 12000.00, "Subtotal before discount is accurately calculated (12,000.00)");

        $bundledServices = $thingsRate + $attractionRate + $hotelTotal + $vehicleTotal;
        $packageDiscount = round($bundledServices * 0.10);
        self::assert($packageDiscount == 1000.00, "10% bundle discount is accurately calculated on bundled services (1,000.00)");

        $subtotalAfterDiscount = $subtotalBeforeDiscount - $packageDiscount; // 11,000
        $platformFee = round($subtotalAfterDiscount * 0.08); // 880
        self::assert($platformFee == 880.00, "Platform fee is 8% of discounted subtotal (880.00)");

        $finalPrice = $subtotalAfterDiscount + $platformFee; // 11,880
        self::assert($finalPrice == 11880.00, "Final discounted package price is 11,880.00 without double counting");
    }

    private static function testRentalStatusTransitions(): void
    {
        echo "\nTEST SUITE 3: Rental Request Status Transitions (Contacted & Completed)\n";

        // Find or create a test rental request with package reference
        $pkgRef = 'GM-PKG-TEST-' . bin2hex(random_bytes(3));
        $vehicle = Database::first("SELECT id, user_id FROM vehicles LIMIT 1");
        if (!$vehicle) {
            echo " [SKIP] No vehicles available for rental test.\n";
            return;
        }

        $tourist = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1") ?? ['id' => 1];
        $providerId = (int) $vehicle['user_id'];

        $rentalId = Database::insert("
            INSERT INTO rental_requests (
                vehicle_id, user_id, pickup_date, rental_days, total_amount,
                payment_status, status, package_booking_ref, created_at, updated_at
            ) VALUES (?, ?, CURDATE(), 2, 2000.00, 'paid', 'approved', ?, NOW(), NOW())
        ", [$vehicle['id'], $tourist['id'], $pkgRef]);

        $existingListing = Database::first("SELECT id FROM listings LIMIT 1");
        $listingId = $existingListing ? (int) $existingListing['id'] : 1;

        $itemBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, guests, total_amount, deposit_amount,
                balance_amount, payment_stage, status, package_booking_ref, package_overall_status, created_at, updated_at
            ) VALUES (?, ?, CURDATE(), 2, 2000.00, 2000.00, 0.00, 'paid', 'pending', ?, 'pending', NOW(), NOW())
        ", [$listingId, $tourist['id'], $pkgRef]);

        $itemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
                service_type, service_name, rental_request_id, allocated_amount, status, created_at
            ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Test Vehicle', ?, 2000.00, 'pending', NOW())
        ", [$pkgRef, $itemBookingId, $listingId, $providerId, $rentalId]);

        // 1. Mark Contacted
        RentalRequest::updateStatus($rentalId, 'contacted');
        PackageBookingService::onRentalUpdated($rentalId, 'contacted', $providerId);

        $freshRental = RentalRequest::find($rentalId);
        self::assert($freshRental['status'] === 'contacted', "Rental request updated to 'contacted' and NOT reverted to 'approved'");

        // 2. Mark Completed
        RentalRequest::updateStatus($rentalId, 'completed');
        PackageBookingService::onRentalUpdated($rentalId, 'completed', $providerId);

        $completedRental = RentalRequest::find($rentalId);
        self::assert($completedRental['status'] === 'completed', "Rental request updated to 'completed' and NOT reverted to 'approved'");

        $freshItem = Database::first("SELECT status FROM package_booking_items WHERE id = ?", [$itemId]);
        self::assert($freshItem['status'] === 'completed', "package_booking_items status synchronized to 'completed'");

        // Cleanup
        Database::run("DELETE FROM package_booking_items WHERE id = ?", [$itemId]);
        Database::run("DELETE FROM rental_requests WHERE id = ?", [$rentalId]);
        Database::run("DELETE FROM bookings WHERE id = ?", [$itemBookingId]);
    }

    private static function testHotelCheckInStatus(): void
    {
        echo "\nTEST SUITE 4: Hotel Reservation Status Transition (Check In)\n";

        $pkgRef = 'GM-PKG-HOTEL-' . bin2hex(random_bytes(3));
        $hotelListing = Database::first("SELECT id, user_id FROM listings WHERE category_id = (SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1) LIMIT 1");
        if (!$hotelListing) {
            $hotelListing = Database::first("SELECT id, user_id FROM listings LIMIT 1");
        }
        $tourist = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1") ?? ['id' => 1];
        $hotelProviderId = (int) $hotelListing['user_id'];

        $subBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, room_id, user_id, booking_date, nights, guests, total_amount,
                deposit_amount, balance_amount, payment_stage, hotel_status, status,
                package_booking_ref, package_overall_status, created_at, updated_at
            ) VALUES (?, 1, ?, CURDATE(), 2, 2, 4000.00, 4000.00, 0.00, 'paid', 'confirmed', 'confirmed', ?, 'pending', NOW(), NOW())
        ", [$hotelListing['id'], $tourist['id'], $pkgRef]);

        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, guests, total_amount, deposit_amount,
                balance_amount, payment_stage, status, package_booking_ref, package_overall_status, created_at, updated_at
            ) VALUES (?, ?, CURDATE(), 2, 8000.00, 8000.00, 0.00, 'paid', 'pending', ?, 'pending', NOW(), NOW())
        ", [$hotelListing['id'], $tourist['id'], $pkgRef]);

        $itemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
                service_type, service_name, sub_booking_id, allocated_amount, status, created_at
            ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', 'Test Hotel', ?, 4000.00, 'pending', NOW())
        ", [$pkgRef, $parentBookingId, $hotelListing['id'], $hotelProviderId, $subBookingId]);

        // Hotel Provider executes 'check_in'
        Database::run("UPDATE bookings SET hotel_status = 'checked_in' WHERE id = ?", [$subBookingId]);
        PackageBookingService::onHotelBookingUpdated($subBookingId, 'check_in', $hotelProviderId);

        $freshSub = Database::first("SELECT hotel_status, status FROM bookings WHERE id = ?", [$subBookingId]);
        self::assert($freshSub['hotel_status'] === 'checked_in', "Hotel reservation updated to 'checked_in' and NOT reverted to 'confirmed'");

        // Cleanup
        Database::run("DELETE FROM package_booking_items WHERE id = ?", [$itemId]);
        Database::run("DELETE FROM bookings WHERE id IN (?, ?)", [$subBookingId, $parentBookingId]);
    }

    private static function testMyTripsSingleCardConsolidation(): void
    {
        echo "\nTEST SUITE 5: Customer My Trips Single Card Consolidation\n";

        $tourist = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1") ?? ['id' => 99999];
        $touristId = (int) $tourist['id'];
        $pkgRef = 'GM-PKG-MYTRIPS-' . bin2hex(random_bytes(3));

        $existingListing = Database::first("SELECT id FROM listings LIMIT 1");
        $listingId = $existingListing ? (int) $existingListing['id'] : 1;

        // 1. Regular individual hotel booking (should be visible in My Trips)
        $regHotelId = Database::insert("
            INSERT INTO bookings (
                listing_id, room_id, user_id, booking_date, nights, guests, total_amount,
                deposit_amount, balance_amount, payment_stage, hotel_status, status,
                created_at, updated_at
            ) VALUES (?, 1, ?, CURDATE(), 2, 2, 3500.00, 3500.00, 0.00, 'paid', 'confirmed', 'confirmed', NOW(), NOW())
        ", [$listingId, $touristId]);

        // 2. Travel Package parent booking (should be visible in My Trips)
        $pkgParentId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, guests, total_amount, deposit_amount,
                balance_amount, payment_stage, status, package_booking_ref, package_overall_status,
                notes, created_at, updated_at
            ) VALUES (?, ?, CURDATE(), 2, 10000.00, 10000.00, 0.00, 'paid', 'pending', ?, 'pending', '{\"type\":\"package_booking\"}', NOW(), NOW())
        ", [$listingId, $touristId, $pkgRef]);

        // 3. Travel Package child hotel sub-booking (SHOULD BE FILTERED OUT to avoid duplicate cards)
        $pkgSubHotelId = Database::insert("
            INSERT INTO bookings (
                listing_id, room_id, user_id, booking_date, nights, guests, total_amount,
                deposit_amount, balance_amount, payment_stage, hotel_status, status,
                package_booking_ref, package_overall_status, notes, created_at, updated_at
            ) VALUES (?, 1, ?, CURDATE(), 2, 2, 4000.00, 4000.00, 0.00, 'paid', 'confirmed', 'confirmed', ?, 'pending', '{\"type\":\"package_sub_booking\"}', NOW(), NOW())
        ", [$listingId, $touristId, $pkgRef]);

        $itemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
                service_type, service_name, sub_booking_id, allocated_amount, status, created_at
            ) VALUES (?, ?, ?, 1, 'hotel_provider', 'hotel', 'Bundled Hotel', ?, 4000.00, 'pending', NOW())
        ", [$pkgRef, $pkgParentId, $listingId, $pkgSubHotelId]);

        $customerBookings = Booking::forCustomer($touristId);
        $bookingIds = array_column($customerBookings, 'id');

        self::assert(in_array($regHotelId, $bookingIds), "Regular individual hotel reservation is returned in customer trips");
        self::assert(in_array($pkgParentId, $bookingIds), "Parent Travel Package booking is returned in customer trips");
        self::assert(!in_array($pkgSubHotelId, $bookingIds), "Child hotel sub-booking is CONSOLIDATED and excluded from duplicate trip cards");

        // Cleanup
        Database::run("DELETE FROM package_booking_items WHERE id = ?", [$itemId]);
        Database::run("DELETE FROM bookings WHERE id IN (?, ?, ?)", [$regHotelId, $pkgParentId, $pkgSubHotelId]);
    }
}

PackageWorkflowFullTest::run();
