<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PackageBookingService;

class VehicleReplacementCapacityValidationTest
{
    private array $cleanupVehicleIds = [];
    private array $cleanupUserIds = [];
    private array $cleanupRentalRequestIds = [];
    private array $cleanupBookingIds = [];

    public function run(): void
    {
        echo "====================================================================\n";
        echo " GuideMate – Vehicle Replacement Capacity Validation Test Suite\n";
        echo "====================================================================\n\n";

        try {
            $this->testCapacityFilteringForPackageBookings();
            $this->testFiveGuestScenarioMatrix();
            $this->testBackendRevalidationOnConfirmation();
            $this->testVehicleSelectionDisplayFields();
            $this->testWebModalMarkupAndValidation();

            echo "\n====================================================================\n";
            echo "All Vehicle Replacement Capacity Validation tests passed successfully!\n";
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

    private function testCapacityFilteringForPackageBookings(): void
    {
        echo "1. Testing eligible vehicle filtering based on package passenger capacity...\n";

        $suffix = bin2hex(random_bytes(4));
        $touristId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Tourist Cap', 'tourist_cap_$suffix@test.com', 'hash', 'tourist', 1, NOW())");
        $this->cleanupUserIds[] = $touristId;

        $guideId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Guide Cap', 'guide_cap_$suffix@test.com', 'hash', 'guide', 1, NOW())");
        $this->cleanupUserIds[] = $guideId;

        $rentalProvId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Rental Prov Cap', 'prov_cap_$suffix@test.com', 'hash', 'rental_provider', 1, NOW())");
        $this->cleanupUserIds[] = $rentalProvId;

        $catPackages = (int) (Database::first("SELECT id FROM categories WHERE slug = 'travel-packages' LIMIT 1")['id'] ?? 4);

        // Create package listing (6 pax max)
        $pkgListingId = Database::insert("
            INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, capacity, status, created_at)
            VALUES (?, ?, '4-Pax Island Safari', '4-pax-island-safari-$suffix', 'Safari Tour', 'Moalboal', 12000.00, '3 Days / 2 Nights', 6, 'approved', NOW())
        ", [$guideId, $catPackages]);

        $travelDate = date('Y-m-d', strtotime('+7 days'));
        $pkgRef = 'GM-PKG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Create booked package for 4 GUESTS / PASSENGERS
        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
                total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref,
                package_overall_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 2, 4, 12000.00, 12000.00, 0.00, 'deposit_paid', 'action_required', ?, 'action_required', NOW(), NOW())
        ", [$pkgListingId, $touristId, $travelDate, $travelDate, date('Y-m-d', strtotime("{$travelDate} + 2 days")), $pkgRef]);
        $this->cleanupBookingIds[] = $parentBookingId;

        // Cancelled original vehicle item (allocated 3,000 for 3 days = 1,000/day)
        $cancelledOrigVId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Toyota', 'Avanza', 'Orig Avanza', 'orig-avanza-$suffix', 'Car', 'Sedan', 5, 1000.00, 'Moalboal', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $cancelledOrigVId;

        $cancelledRentalReqId = Database::insert("
            INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount, status, created_at)
            VALUES (?, ?, 'Orig Avanza', 'Sedan', 'Moalboal', ?, 3, 3000.00, 'cancelled', NOW())
        ", [$touristId, $cancelledOrigVId, $travelDate]);
        $this->cleanupRentalRequestIds[] = $cancelledRentalReqId;

        $cancelledItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type,
                service_name, rental_request_id, allocated_amount, original_allocated_amount, status, created_at
            ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Orig Avanza', ?, 3000.00, 3000.00, 'cancelled', NOW())
        ", [$pkgRef, $parentBookingId, $pkgListingId, $rentalProvId, $cancelledRentalReqId]);

        // 1. Vehicle with 2 seats (Scooter): INSUFFICIENT for 4-passenger package -> MUST BE EXCLUDED
        $scooterVId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Honda', 'Click 125i', 'Scooter 2-Seater', 'scooter-2s-$suffix', 'Scooter', 'Sedan', 2, 700.00, 'Moalboal', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $scooterVId;

        // 2. Vehicle with 0 seats (Legacy/unset): INSUFFICIENT for 4-passenger package -> MUST BE EXCLUDED
        $zeroVId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Nissan', 'Almera', 'Zero Cap Sedan', 'zero-cap-$suffix', 'Car', 'Sedan', 0, 800.00, 'Moalboal', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $zeroVId;

        // 3. Vehicle with 5 seats (Sedan): SUFFICIENT for 4-passenger package -> MUST BE INCLUDED
        $sedan5VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Toyota', 'Vios', 'Eligible 5-Seater', 'vios-5s-$suffix', 'Car', 'Sedan', 5, 850.00, 'Moalboal', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $sedan5VId;

        // 4. Vehicle with 7 seats (SUV): SUFFICIENT for 4-passenger package -> MUST BE INCLUDED
        $suv7VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Mitsubishi', 'Xpander', 'Eligible 7-Seater', 'xpander-7s-$suffix', 'MPV', 'Sedan', 7, 950.00, 'Moalboal', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $suv7VId;

        // 5. Vehicle with 5 seats but UNAPPROVED -> MUST BE EXCLUDED
        $unapprovedVId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Hyundai', 'Accent', 'Unapproved Accent', 'unapp-accent-$suffix', 'Car', 'Sedan', 5, 800.00, 'Moalboal', 'pending', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $unapprovedVId;

        // 6. Vehicle with 5 seats but UNAVAILABLE (is_available = 0) -> MUST BE EXCLUDED
        $unavailVId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Kia', 'Soluto', 'Unavailable Soluto', 'unavail-soluto-$suffix', 'Car', 'Sedan', 5, 800.00, 'Moalboal', 'approved', 0)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $unavailVId;

        // 7. Vehicle with 5 seats but ALREADY RESERVED during travel schedule -> MUST BE EXCLUDED
        $bookedVId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Honda', 'City', 'Booked City', 'booked-city-$suffix', 'Car', 'Sedan', 5, 800.00, 'Moalboal', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $bookedVId;

        $conflictReqId = Database::insert("
            INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount, status, created_at)
            VALUES (?, ?, 'Booked City', 'Sedan', 'Moalboal', ?, 3, 2400.00, 'approved', NOW())
        ", [$touristId, $bookedVId, $travelDate]);
        $this->cleanupRentalRequestIds[] = $conflictReqId;

        // Query available alternatives for Option A
        $res = PackageBookingService::getAvailableAlternatives($pkgRef, $cancelledItemId);
        $alts = $res['alternatives'] ?? [];
        $altVehicleIds = array_column($alts, 'vehicle_id');

        $this->assert(in_array($sedan5VId, $altVehicleIds, true), "5-passenger vehicle is displayed for 4-passenger package");
        $this->assert(in_array($suv7VId, $altVehicleIds, true), "7-passenger vehicle is displayed for 4-passenger package");
        $this->assert(!in_array($scooterVId, $altVehicleIds, true), "2-passenger scooter is EXCLUDED for 4-passenger package");
        $this->assert(!in_array($zeroVId, $altVehicleIds, true), "0-passenger legacy vehicle is EXCLUDED for 4-passenger package");
        $this->assert(!in_array($unapprovedVId, $altVehicleIds, true), "Unapproved vehicle is EXCLUDED");
        $this->assert(!in_array($unavailVId, $altVehicleIds, true), "Unavailable vehicle is EXCLUDED");
        $this->assert(!in_array($bookedVId, $altVehicleIds, true), "Already reserved vehicle during schedule is EXCLUDED");
        $this->assert(!in_array($cancelledOrigVId, $altVehicleIds, true), "Original cancelled vehicle is EXCLUDED");
    }

    private function testFiveGuestScenarioMatrix(): void
    {
        echo "\n2. Testing 5-guest travel package replacement scenario matrix (4, 5, 6, 7, 10 pax)...\n";

        $suffix = bin2hex(random_bytes(4));
        $touristId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Tourist 5Pax', 'tourist_5pax_$suffix@test.com', 'hash', 'tourist', 1, NOW())");
        $this->cleanupUserIds[] = $touristId;

        $guideId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Guide 5Pax', 'guide_5pax_$suffix@test.com', 'hash', 'guide', 1, NOW())");
        $this->cleanupUserIds[] = $guideId;

        $rentalProvId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Rental 5Pax', 'rental_5pax_$suffix@test.com', 'hash', 'rental_provider', 1, NOW())");
        $this->cleanupUserIds[] = $rentalProvId;

        $catPackages = (int) (Database::first("SELECT id FROM categories WHERE slug = 'travel-packages' LIMIT 1")['id'] ?? 4);

        $pkgListingId = Database::insert("
            INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, capacity, status, created_at)
            VALUES (?, ?, '5-Guest Package', '5-guest-package-$suffix', '5-Guest Package', 'Cebu', 15000.00, '3 Days / 2 Nights', 8, 'approved', NOW())
        ", [$guideId, $catPackages]);

        $travelDate = date('Y-m-d', strtotime('+12 days'));
        $pkgRef = 'GM-PKG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Create booked package for 5 GUESTS
        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
                total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref,
                package_overall_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 2, 5, 15000.00, 15000.00, 0.00, 'deposit_paid', 'action_required', ?, 'action_required', NOW(), NOW())
        ", [$pkgListingId, $touristId, $travelDate, $travelDate, date('Y-m-d', strtotime("{$travelDate} + 2 days")), $pkgRef]);
        $this->cleanupBookingIds[] = $parentBookingId;

        // Cancelled vehicle (Honda Brio, 5 seats, 1600/day = 4800 total)
        $cancelledVId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Honda', 'Brio RS', 'Honda Brio RS', 'brio-rs-$suffix', 'Car', 'Sedan', 5, 1600.00, 'Cebu City', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $cancelledVId;

        $cancelledReqId = Database::insert("
            INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount, status, created_at)
            VALUES (?, ?, 'Honda Brio RS', 'Sedan', 'Cebu City', ?, 3, 4800.00, 'cancelled', NOW())
        ", [$touristId, $cancelledVId, $travelDate]);
        $this->cleanupRentalRequestIds[] = $cancelledReqId;

        $cancelledItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type,
                service_name, rental_request_id, allocated_amount, original_allocated_amount, status, created_at
            ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Honda Brio RS', ?, 4800.00, 4800.00, 'cancelled', NOW())
        ", [$pkgRef, $parentBookingId, $pkgListingId, $rentalProvId, $cancelledReqId]);

        // 1. Vehicle with 4 seats (Hatchback/Sedan): INSUFFICIENT for 5 guests -> EXCLUDED
        $cap4VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Suzuki', 'Celerio', 'Suzuki Celerio (4-seater)', 'celerio-4s-$suffix', 'Small Car', 'Sedan', 4, 1100.00, 'Cebu City', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $cap4VId;

        // 2. Vehicle with 5 seats (Sedan): SUFFICIENT for 5 guests -> INCLUDED
        $cap5VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Toyota', 'Vios', 'Toyota Vios (5-seater)', 'vios-5pax-$suffix', 'Sedan Car', 'Sedan', 5, 1800.00, 'Cebu City', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $cap5VId;

        // 3. Vehicle with 6 seats (Mini MPV): SUFFICIENT for 5 guests -> INCLUDED
        $cap6VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Honda', 'BR-V', 'Honda BR-V (6-seater)', 'brv-6s-$suffix', 'Compact MPV', 'SUV', 6, 2200.00, 'Cebu City', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $cap6VId;

        // 4. Vehicle with 7 seats (MPV/SUV): SUFFICIENT for 5 guests -> INCLUDED
        $cap7VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Mitsubishi', 'Xpander', 'Mitsubishi Xpander (7-seater)', 'xpander-7pax-$suffix', 'Family MPV', 'MPV', 7, 2500.00, 'Cebu City', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $cap7VId;

        // 5. Vehicle with 10 seats (Van): SUFFICIENT for 5 guests -> INCLUDED
        $cap10VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Nissan', 'Urvan', 'Nissan Urvan (10-seater)', 'urvan-10s-$suffix', 'Passenger Van', 'Van', 10, 3500.00, 'Cebu City', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $cap10VId;

        // 6. Vehicle with 5 seats but UNAVAILABLE (is_available = 0) -> EXCLUDED
        $unavail5VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Kia', 'Soluto', 'Kia Soluto (5-seater unavailable)', 'soluto-unavail-$suffix', 'Sedan Car', 'Sedan', 5, 1700.00, 'Cebu City', 'approved', 0)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $unavail5VId;

        // 7. Vehicle with 5 seats but ALREADY BOOKED for overlapping schedule -> EXCLUDED
        $booked5VId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Hyundai', 'Reina', 'Hyundai Reina (5-seater booked)', 'reina-booked-$suffix', 'Sedan Car', 'Sedan', 5, 1750.00, 'Cebu City', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $booked5VId;

        $overlapReqId = Database::insert("
            INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days, total_amount, status, created_at)
            VALUES (?, ?, 'Hyundai Reina', 'Sedan', 'Cebu City', ?, 3, 5250.00, 'approved', NOW())
        ", [$touristId, $booked5VId, $travelDate]);
        $this->cleanupRentalRequestIds[] = $overlapReqId;

        // Run getAvailableAlternatives
        $res = PackageBookingService::getAvailableAlternatives($pkgRef, $cancelledItemId);
        $alts = $res['alternatives'] ?? [];
        $altVehicleIds = array_column($alts, 'vehicle_id');

        $this->assert(!in_array($cap4VId, $altVehicleIds, true), "5-guest package: vehicle with passenger capacity 4 is EXCLUDED");
        $this->assert(in_array($cap5VId, $altVehicleIds, true), "5-guest package: vehicle with passenger capacity 5 is INCLUDED");
        $this->assert(in_array($cap6VId, $altVehicleIds, true), "5-guest package: vehicle with passenger capacity 6 is INCLUDED");
        $this->assert(in_array($cap7VId, $altVehicleIds, true), "5-guest package: vehicle with passenger capacity 7 is INCLUDED");
        $this->assert(in_array($cap10VId, $altVehicleIds, true), "5-guest package: vehicle with passenger capacity 10 is INCLUDED");
        $this->assert(!in_array($unavail5VId, $altVehicleIds, true), "5-guest package: 5-capacity vehicle marked unavailable is EXCLUDED");
        $this->assert(!in_array($booked5VId, $altVehicleIds, true), "5-guest package: 5-capacity vehicle booked for schedule is EXCLUDED");
    }

    private function testBackendRevalidationOnConfirmation(): void
    {
        echo "\n2. Testing backend availability and capacity revalidation before confirming replacement...\n";

        $suffix = bin2hex(random_bytes(4));
        $touristId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Tourist Reval', 'tourist_reval_$suffix@test.com', 'hash', 'tourist', 1, NOW())");
        $this->cleanupUserIds[] = $touristId;

        $guideId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Guide Reval', 'guide_reval_$suffix@test.com', 'hash', 'guide', 1, NOW())");
        $this->cleanupUserIds[] = $guideId;

        $rentalProvId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Rental Prov Reval', 'prov_reval_$suffix@test.com', 'hash', 'rental_provider', 1, NOW())");
        $this->cleanupUserIds[] = $rentalProvId;

        $catPackages = (int) (Database::first("SELECT id FROM categories WHERE slug = 'travel-packages' LIMIT 1")['id'] ?? 4);

        $pkgListingId = Database::insert("
            INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, capacity, status, created_at)
            VALUES (?, ?, '5-Pax South Cebu', '5-pax-south-cebu-$suffix', 'South Cebu Tour', 'Moalboal', 15000.00, '3 Days / 2 Nights', 6, 'approved', NOW())
        ", [$guideId, $catPackages]);

        $travelDate = date('Y-m-d', strtotime('+10 days'));
        $pkgRef = 'GM-PKG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Package booking for 5 PASSENGERS
        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
                total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref,
                package_overall_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 2, 5, 15000.00, 15000.00, 0.00, 'deposit_paid', 'action_required', ?, 'action_required', NOW(), NOW())
        ", [$pkgListingId, $touristId, $travelDate, $travelDate, date('Y-m-d', strtotime("{$travelDate} + 2 days")), $pkgRef]);
        $this->cleanupBookingIds[] = $parentBookingId;

        $cancelledItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type,
                service_name, allocated_amount, original_allocated_amount, status, created_at
            ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Cancelled Innova', 4500.00, 4500.00, 'cancelled', NOW())
        ", [$pkgRef, $parentBookingId, $pkgListingId, $rentalProvId]);

        // Vehicle A: Insufficient capacity (4 seats for 5 passengers)
        $vehicle4Cap = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Honda', 'Brio', 'Small Hatchback', 'brio-4cap-$suffix', 'Car', 'Sedan', 4, 1200.00, 'Moalboal', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $vehicle4Cap;

        // Vehicle B: Sufficient capacity (7 seats)
        $vehicle7Cap = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, status, is_available)
            VALUES (?, 'Toyota', 'Fortuner', '7-Seater Fortuner', 'fortuner-7cap-$suffix', 'SUV', 'Sedan', 7, 1300.00, 'Moalboal', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $vehicle7Cap;

        // 1. Attempt replacement with insufficient capacity vehicle (4 seats < 5 passengers)
        $capacityBlocked = false;
        try {
            PackageBookingService::replaceCancelledService($pkgRef, $cancelledItemId, $rentalProvId, $vehicle4Cap, [], $guideId);
        } catch (\RuntimeException $e) {
            $capacityBlocked = true;
            $this->assert(str_contains($e->getMessage(), 'cannot accommodate the 5 passenger(s)'), "Throws specific capacity rejection error: " . $e->getMessage());
        }
        $this->assert($capacityBlocked, "Backend strictly blocks replacement vehicle with insufficient passenger capacity");

        // 2. Attempt replacement with sufficient capacity vehicle (7 seats >= 5 passengers)
        $replaceResult = PackageBookingService::replaceCancelledService($pkgRef, $cancelledItemId, $rentalProvId, $vehicle7Cap, [], $guideId);
        $this->assert($replaceResult['success'] === true, "Backend allows replacement vehicle meeting passenger capacity");

        // Check new rental request was created with strictly PENDING status
        $newRental = Database::first("SELECT * FROM rental_requests WHERE package_booking_ref = ? AND vehicle_id = ? ORDER BY id DESC LIMIT 1", [$pkgRef, $vehicle7Cap]);
        $this->assert($newRental !== null, "New replacement rental request created");
        $this->assert($newRental['status'] === 'pending', "Replacement rental request has strictly 'pending' status");
        $this->cleanupRentalRequestIds[] = (int) $newRental['id'];

        // 3. Attempt race condition: vehicle7Cap is now booked, try to replace again on another item -> should block with availability conflict
        $anotherItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type,
                service_name, allocated_amount, original_allocated_amount, status, created_at
            ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Another Cancelled Vehicle', 4500.00, 4500.00, 'cancelled', NOW())
        ", [$pkgRef, $parentBookingId, $pkgListingId, $rentalProvId]);

        $conflictBlocked = false;
        try {
            PackageBookingService::replaceCancelledService($pkgRef, $anotherItemId, $rentalProvId, $vehicle7Cap, [], $guideId);
        } catch (\RuntimeException $e) {
            $conflictBlocked = true;
            $this->assert(str_contains($e->getMessage(), 'already reserved'), "Throws availability conflict: " . $e->getMessage());
        }
        $this->assert($conflictBlocked, "Backend prevents double-booking an already assigned vehicle for overlapping dates");
    }

    private function testVehicleSelectionDisplayFields(): void
    {
        echo "\n3. Testing vehicle selection display information under Option A...\n";

        $suffix = bin2hex(random_bytes(4));
        $touristId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Tourist Disp', 'tourist_disp_$suffix@test.com', 'hash', 'tourist', 1, NOW())");
        $this->cleanupUserIds[] = $touristId;

        $guideId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Guide Disp', 'guide_disp_$suffix@test.com', 'hash', 'guide', 1, NOW())");
        $this->cleanupUserIds[] = $guideId;

        $rentalProvId = Database::insert("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Rental Prov Disp', 'prov_disp_$suffix@test.com', 'hash', 'rental_provider', 1, NOW())");
        $this->cleanupUserIds[] = $rentalProvId;

        $catPackages = (int) (Database::first("SELECT id FROM categories WHERE slug = 'travel-packages' LIMIT 1")['id'] ?? 4);

        $pkgListingId = Database::insert("
            INSERT INTO listings (user_id, category_id, title, slug, description, area, price, duration, capacity, status, created_at)
            VALUES (?, ?, 'Disp Safari Tour', 'disp-safari-$suffix', 'Safari Tour', 'Moalboal', 8000.00, '3 Days / 2 Nights', 6, 'approved', NOW())
        ", [$guideId, $catPackages]);

        $travelDate = date('Y-m-d', strtotime('+5 days'));
        $pkgRef = 'GM-PKG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
                total_amount, deposit_amount, balance_amount, payment_stage, status, package_booking_ref,
                package_overall_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 2, 2, 8000.00, 8000.00, 0.00, 'deposit_paid', 'action_required', ?, 'action_required', NOW(), NOW())
        ", [$pkgListingId, $touristId, $travelDate, $travelDate, date('Y-m-d', strtotime("{$travelDate} + 2 days")), $pkgRef]);
        $this->cleanupBookingIds[] = $parentBookingId;

        $cancelledItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type,
                service_name, allocated_amount, original_allocated_amount, status, created_at
            ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', 'Cancelled Scooter', 2500.00, 2500.00, 'cancelled', NOW())
        ", [$pkgRef, $parentBookingId, $pkgListingId, $rentalProvId]);

        $dispVehicleId = Database::insert("
            INSERT INTO vehicles (user_id, make, model, name, slug, description, type, passenger_capacity, price_per_day, location, image, status, is_available)
            VALUES (?, 'Yamaha', 'Aerox 155', '2025 Yamaha Aerox 155', 'aerox-disp-$suffix', 'Modern scooter', 'Scooter', 2, 750.00, 'Moalboal · Panagsama Beach', 'uploads/vehicles/aerox_test.jpg', 'approved', 1)
        ", [$rentalProvId]);
        $this->cleanupVehicleIds[] = $dispVehicleId;

        $res = PackageBookingService::getAvailableAlternatives($pkgRef, $cancelledItemId);
        $alts = $res['alternatives'] ?? [];

        $found = null;
        foreach ($alts as $alt) {
            if ($alt['vehicle_id'] === $dispVehicleId) {
                $found = $alt;
                break;
            }
        }

        $this->assert($found !== null, "Alternative vehicle was retrieved successfully");
        $this->assert(!empty($found['image']), "Displays vehicle photo (image: {$found['image']})");
        $this->assert($found['title'] === '2025 Yamaha Aerox 155' || $found['make'] === 'Yamaha', "Displays vehicle name/model");
        $this->assert($found['vehicle_type'] === 'Scooter', "Displays vehicle type (type: {$found['vehicle_type']})");
        $this->assert((int) $found['passenger_capacity'] === 2, "Displays actual passenger capacity (capacity: {$found['passenger_capacity']})");
        $this->assert((float) $found['price'] === 750.00, "Displays daily rental price (price: ₱750.00)");
        $this->assert((float) $found['total_cost'] === 750.00 * (int) $found['days'], "Displays total rental price based on schedule duration");
        $this->assert($found['provider_name'] === 'Rental Prov Disp', "Displays rental provider name");
        $this->assert($found['location'] === 'Moalboal · Panagsama Beach', "Displays pickup location");
        $this->assert(!empty($found['pickup_date']) && !empty($found['return_date']), "Displays availability for package schedule ({$found['pickup_date']} to {$found['return_date']})");
    }

    private function testWebModalMarkupAndValidation(): void
    {
        echo "\n4. Testing Web modal markup and Option A client logic...\n";

        $modalFile = file_get_contents(__DIR__ . '/../app/Views/partials/package_alternatives_modal.php');
        $this->assert($modalFile !== false, "package_alternatives_modal.php exists");

        $this->assert(str_contains($modalFile, 'Option A: Replace the Cancelled Service'), "Option A heading present in modal");
        $this->assert(str_contains($modalFile, 'Passenger Capacity:'), "Modal template includes 'Passenger Capacity:' label");
        $this->assert(str_contains($modalFile, 'alt.passenger_capacity'), "Modal dynamically reads alt.passenger_capacity from vehicle database");
        $this->assert(str_contains($modalFile, 'Dates Available:'), "Modal displays Dates Available schedule");
        $this->assert(str_contains($modalFile, 'Total Rental Price'), "Modal displays Total Rental Price");
        $this->assert(str_contains($modalFile, 'Rental Provider:'), "Modal displays Rental Provider");
        $this->assert(str_contains($modalFile, 'Pickup Location:'), "Modal displays Pickup Location");
        $this->assert(str_contains($modalFile, 'Type / Category:'), "Modal displays Type / Category");
    }

    private function cleanup(): void
    {
        if ($this->cleanupVehicleIds !== []) {
            $in = implode(',', array_map('intval', $this->cleanupVehicleIds));
            Database::run("DELETE FROM vehicles WHERE id IN ($in)");
        }
        if ($this->cleanupRentalRequestIds !== []) {
            $in = implode(',', array_map('intval', $this->cleanupRentalRequestIds));
            Database::run("DELETE FROM rental_requests WHERE id IN ($in)");
        }
        if ($this->cleanupBookingIds !== []) {
            $in = implode(',', array_map('intval', $this->cleanupBookingIds));
            Database::run("DELETE FROM bookings WHERE id IN ($in)");
            Database::run("DELETE FROM package_booking_items WHERE parent_booking_id IN ($in)");
        }
        if ($this->cleanupUserIds !== []) {
            $in = implode(',', array_map('intval', $this->cleanupUserIds));
            Database::run("DELETE FROM users WHERE id IN ($in)");
        }
    }
}

(new VehicleReplacementCapacityValidationTest())->run();
