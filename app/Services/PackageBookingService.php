<?php

declare(strict_types=1);

namespace App\Services;

use App\Controllers\AdminController;
use App\Core\Database;
use App\Models\Booking;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\PackageService;
use App\Models\Payment;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Vehicle;
use RuntimeException;

final class PackageBookingService
{
    private static bool $tablesEnsured = false;

    /**
     * Ensure database schema tables and columns exist for package bookings.
     */
    public static function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }

        try {
            // 1. Table for tracking individual provider items inside a package booking
            Database::run("
                CREATE TABLE IF NOT EXISTS `package_booking_items` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `package_booking_ref` VARCHAR(64) NOT NULL,
                    `parent_booking_id` INT UNSIGNED NOT NULL,
                    `package_id` INT UNSIGNED NOT NULL,
                    `provider_id` INT UNSIGNED NOT NULL,
                    `provider_role` ENUM('guide', 'hotel_provider', 'rental_provider') NOT NULL,
                    `service_type` ENUM('guide', 'hotel', 'vehicle') NOT NULL,
                    `service_name` VARCHAR(255) NOT NULL,
                    `sub_booking_id` INT UNSIGNED NULL,
                    `rental_request_id` INT UNSIGNED NULL,
                    `allocated_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `status` ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
                    `cancellation_reason` VARCHAR(500) NULL,
                    `responded_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_pkg_bk_items_ref` (`package_booking_ref`),
                    KEY `idx_pkg_bk_items_parent` (`parent_booking_id`),
                    KEY `idx_pkg_bk_items_provider` (`provider_id`, `status`),
                    KEY `idx_pkg_bk_items_sub_bk` (`sub_booking_id`),
                    KEY `idx_pkg_bk_items_rental` (`rental_request_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Ensure columns for existing package_booking_items table
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `package_booking_ref` VARCHAR(64) NULL AFTER `id`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `provider_role` VARCHAR(64) NULL AFTER `provider_id`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` MODIFY COLUMN `service_type` ENUM('guide', 'hotel', 'vehicle') NOT NULL");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `service_name` VARCHAR(255) NULL AFTER `service_type`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `sub_booking_id` INT UNSIGNED NULL AFTER `service_name`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `allocated_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `rental_request_id`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `cancellation_reason` VARCHAR(500) NULL AFTER `status`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `responded_at` TIMESTAMP NULL DEFAULT NULL AFTER `cancellation_reason`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            } catch (\Throwable) {}

            // 2. Ensure package reference and overall status columns on bookings table
            try {
                Database::run("
                    ALTER TABLE `bookings`
                    ADD COLUMN `package_booking_ref` VARCHAR(64) NULL AFTER `notes`,
                    ADD COLUMN `package_overall_status` ENUM('pending', 'partially_confirmed', 'confirmed', 'cancellation_requested', 'cancelled', 'completed') NULL AFTER `package_booking_ref`,
                    ADD INDEX `idx_bookings_pkg_ref` (`package_booking_ref`)
                ");
            } catch (\Throwable) {}

            // 3. Ensure package reference, package booking ID, and guide ID doc on rental_requests table
            try {
                Database::run("
                    ALTER TABLE `rental_requests`
                    ADD COLUMN `package_booking_ref` VARCHAR(64) NULL AFTER `notes`,
                    ADD COLUMN `package_booking_id` INT UNSIGNED NULL AFTER `package_booking_ref`,
                    ADD COLUMN `guide_id_doc` VARCHAR(255) NULL AFTER `package_booking_id`,
                    ADD INDEX `idx_rentals_pkg_ref` (`package_booking_ref`)
                ");
            } catch (\Throwable) {}

            try {
                Database::run("
                    ALTER TABLE `rental_requests`
                    ADD COLUMN `guide_id_doc` VARCHAR(255) NULL AFTER `package_booking_id`
                ");
            } catch (\Throwable) {}

            self::$tablesEnsured = true;
        } catch (\Throwable) {
            // Gracefully ignore if offline or sandbox restricted
        }
    }

    /**
     * Create a complete travel package reservation across all involved providers.
     *
     * @param int $packageId
     * @param int $touristId
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function createPackageBooking(int $packageId, int $touristId, array $payload): array
    {
        self::ensureTables();

        $package = Listing::find($packageId);
        if ($package === null) {
            throw new RuntimeException('Travel Package listing not found.');
        }

        $guideId = (int) ($package['user_id'] ?? 1);
        $guideUser = User::find($guideId) ?? [
            'id' => $guideId,
            'name' => 'Tour Guide',
            'email' => 'guide@guidemate.com',
            'phone' => '0917 123 4567',
            'avatar' => '',
        ];

        // Parse package metadata using AdminController inspection helper
        $inspected = AdminController::buildPackageInspectionData($package, $guideUser);
        $days = max(1, (int) ($inspected['duration_days'] ?? 3));
        $nights = max(0, (int) ($inspected['duration_nights'] ?? max(0, $days - 1)));
        $minPax = 2;
        $maxPax = max(2, (int) ($package['capacity'] ?? 8));

        $guestsCount = max(1, (int) ($payload['guestsCount'] ?? 1));
        if ($guestsCount < $minPax) {
            throw new RuntimeException("This package requires a minimum of {$minPax} participants.");
        }
        if ($guestsCount > $maxPax) {
            throw new RuntimeException("This package allows a maximum of {$maxPax} participants.");
        }

        $travelDate = (string) ($payload['travelDate'] ?? date('Y-m-d', strtotime('+3 days')));
        $contactName = (string) ($payload['contactName'] ?? 'Tourist');
        $contactPhone = (string) ($payload['contactPhone'] ?? '0917 123 4567');
        $contactEmail = (string) ($payload['contactEmail'] ?? '');
        if ($contactEmail === '') {
            $touristUser = User::find($touristId);
            $contactEmail = (string) ($touristUser['email'] ?? 'tourist@guidemate.com');
        }
        $specialNotes = (string) ($payload['notes'] ?? '');
        $paymentMethod = (string) ($payload['paymentMethod'] ?? 'ewallet');

        // Price calculation: Constant package base price
        $packageBasePrice = (float) ($package['price'] ?? 8499.00);
        $taxes = round($packageBasePrice * 0.05, 2);
        $environmentalFees = 250.00;
        $voucherDiscount = (float) ($payload['voucherDiscount'] ?? 0.00);
        $finalTotal = max(0.00, round($packageBasePrice + $taxes + $environmentalFees - $voucherDiscount, 2));

        // Generate centralized package booking reference
        $pkgRef = 'GM-PKG-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $txnRef = 'TXN-PKG-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        // Detect bundled provider services:
        $hotelIncluded = !empty($inspected['hotel_included']) && !empty($inspected['hotel']);
        $rentalIncluded = !empty($inspected['rental_included']) && !empty($inspected['rental']);

        // Calculate individual shares:
        $hotelShare = 0.00;
        $hotelDetails = null;
        if ($hotelIncluded) {
            $hotelDetails = $inspected['hotel'];
            $ratePerNight = (float) ($hotelDetails['price_per_night'] ?? 0);
            $stayNights = max(1, $nights);
            $hotelShare = round($ratePerNight * $stayNights, 2);
        }

        $vehicleShare = 0.00;
        $vehicleDetails = null;
        if ($rentalIncluded) {
            $vehicleDetails = $inspected['rental'];
            $dailyRate = (float) ($vehicleDetails['daily_rate'] ?? $vehicleDetails['price_per_day'] ?? 0);
            $vehicleShare = round($dailyRate * $days, 2);
        }

        // Guide share is remainder of the base package price after external services
        $guideShare = max(0.00, round($packageBasePrice - $hotelShare - $vehicleShare, 2));

        // Build flat inclusions array matching the customer app reference screen
        $inclusions = [];
        if (!empty($inspected['inclusions']['attractions'])) {
            foreach ($inspected['inclusions']['attractions'] as $attr) {
                $inclusions[] = [
                    'type' => 'attraction',
                    'title' => (string) $attr['title'],
                    'subtitle' => (string) (!empty($attr['summary']) ? $attr['summary'] : (!empty($attr['area']) ? $attr['area'] : 'Attraction access')),
                    'icon' => 'water-outline',
                    'badge' => 'ATTRACTION',
                ];
            }
        }
        if (!empty($inspected['inclusions']['things_to_do'])) {
            foreach ($inspected['inclusions']['things_to_do'] as $act) {
                $inclusions[] = [
                    'type' => 'activity',
                    'title' => (string) $act['title'],
                    'subtitle' => (string) (!empty($act['summary']) ? $act['summary'] : (!empty($act['area']) ? $act['area'] : 'Activity tour')),
                    'icon' => 'compass-outline',
                    'badge' => 'THINGS TO DO',
                ];
            }
        }
        if ($hotelIncluded && $hotelDetails) {
            $inclusions[] = [
                'type' => 'hotel',
                'title' => (string) ($hotelDetails['hotel_name'] ?? 'Hotel Stay'),
                'subtitle' => (string) ($hotelDetails['room_name'] ?? 'Room') . " · {$nights} Night(s)",
                'icon' => 'bed-outline',
                'badge' => 'HOTEL STAY',
            ];
        }
        if ($rentalIncluded && $vehicleDetails) {
            $inclusions[] = [
                'type' => 'rental',
                'title' => (string) ($vehicleDetails['vehicle_name'] ?? $vehicleDetails['name'] ?? 'Vehicle Rental'),
                'subtitle' => "{$days}-Day rental · " . ($vehicleDetails['vehicle_type'] ?? 'Vehicle'),
                'icon' => 'car-sport-outline',
                'badge' => 'VEHICLE RENTAL',
            ];
        }
        if (!empty($inspected['custom_perks'])) {
            foreach ($inspected['custom_perks'] as $perk) {
                $inclusions[] = [
                    'type' => 'perk',
                    'title' => (string) $perk,
                    'subtitle' => 'Included in package bundle',
                    'icon' => 'shield-checkmark-outline',
                    'badge' => 'INCLUDED PERK',
                ];
            }
        }

        // Itinerary schedule dates
        $checkInDate = $travelDate;
        $checkOutDate = date('Y-m-d', strtotime("{$travelDate} +{$nights} days"));

        // Package Notes JSON
        $notesData = [
            'type' => 'package_booking',
            'package_id' => $packageId,
            'package_title' => $package['title'],
            'package_booking_ref' => $pkgRef,
            'travel_date' => $travelDate,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'duration_days' => $days,
            'duration_nights' => $nights,
            'duration' => $inspected['duration_code'] ?? "{$days}D{$nights}N",
            'destination' => $inspected['destination'] ?? ($package['area'] ?? 'Cebu'),
            'meeting_point' => $inspected['meeting_point'] ?? ($package['address'] ?? 'Meeting point coordinated with guide'),
            'guests_count' => $guestsCount,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
            'contact_email' => $contactEmail,
            'special_notes' => $specialNotes,
            'payment_method' => $paymentMethod,
            'payment_reference' => $txnRef,
            'package_base_price' => $packageBasePrice,
            'taxes' => $taxes,
            'environmental_fees' => $environmentalFees,
            'voucher_discount' => $voucherDiscount,
            'final_total' => $finalTotal,
            'allocations' => [
                'guide_share' => $guideShare,
                'hotel_share' => $hotelShare,
                'vehicle_share' => $vehicleShare,
                'taxes' => $taxes,
                'fees' => $environmentalFees,
            ],
            'inclusions' => $inclusions,
            'exclusions' => $inspected['exclusions'] ?? [],
            'itinerary' => $inspected['itinerary'] ?? [],
            'accommodation' => $hotelIncluded ? $hotelDetails : null,
            'rental' => $rentalIncluded ? $vehicleDetails : null,
            'hotel_room_id' => $hotelIncluded ? (int) ($hotelDetails['room_id'] ?? 0) : null,
            'vehicle_id' => $rentalIncluded ? (int) ($vehicleDetails['id'] ?? 0) : null,
            'guide' => [
                'id' => $guideId,
                'name' => $guideUser['name'] ?? 'Marc Repuesto',
                'avatar' => $guideUser['avatar'] ?? '',
                'phone' => $guideUser['phone'] ?? '0917 123 4567',
            ],
        ];

        $notesJson = json_encode($notesData, JSON_UNESCAPED_SLASHES);

        // 1. Create Main Package Booking (assigned to Guide, initial status PENDING)
        $parentBookingId = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, guests,
                total_amount, deposit_amount, balance_amount,
                payment_stage, status, notes, package_booking_ref,
                package_overall_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 0.00, 'deposit_paid', 'pending', ?, ?, 'pending', NOW(), NOW())
        ", [
            $packageId,
            $touristId,
            $travelDate,
            $guestsCount,
            $finalTotal,
            $finalTotal,
            $notesJson,
            $pkgRef,
        ]);

        // 2. Insert Main Payment Record (held in escrow pending provider consensus)
        Database::insert("
            INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
            VALUES (?, ?, ?, 'paid', ?, NOW(), NOW())
        ", [
            $parentBookingId,
            $finalTotal,
            $paymentMethod,
            $txnRef,
        ]);

        // 3. Register Guide Item in package_booking_items
        Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id,
                provider_id, provider_role, service_type, service_name,
                sub_booking_id, allocated_amount, status, created_at
            ) VALUES (?, ?, ?, ?, 'guide', 'guide', ?, ?, ?, 'pending', NOW())
        ", [
            $pkgRef,
            $parentBookingId,
            $packageId,
            $guideId,
            $package['title'] . ' (Guide & Activities)',
            $parentBookingId,
            $guideShare,
        ]);

        // 4. Register Hotel Provider Reservation IF AND ONLY IF included
        $hotelBookingId = null;
        if ($hotelIncluded && $hotelDetails !== null) {
            $hotelListingId = (int) ($hotelDetails['hotel_listing_id'] ?? $hotelDetails['listing_id'] ?? 0);
            $hotelProviderId = (int) ($hotelDetails['partner_id'] ?? $hotelDetails['user_id'] ?? 0);
            if ($hotelProviderId <= 0 && $hotelListingId > 0) {
                $hUser = Database::first("SELECT user_id FROM listings WHERE id = ?", [$hotelListingId]);
                $hotelProviderId = (int) ($hUser['user_id'] ?? 0);
            }
            $roomName = (string) ($hotelDetails['room_name'] ?? $hotelDetails['name'] ?? 'Hotel Room');
            $hotelName = (string) ($hotelDetails['hotel_name'] ?? 'Hotel Property');
            $hotelRoomId = (int) ($hotelDetails['room_id'] ?? 0);

            $hotelNotes = json_encode([
                'type' => 'package_sub_booking',
                'service_type' => 'hotel',
                'package_booking_ref' => $pkgRef,
                'parent_booking_id' => $parentBookingId,
                'package_title' => $package['title'],
                'room_name' => $roomName,
                'hotel_name' => $hotelName,
                'allocated_share' => $hotelShare,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'guests_count' => $guestsCount,
            ], JSON_UNESCAPED_SLASHES);

            $hotelBookingId = Database::insert("
                INSERT INTO bookings (
                    listing_id, room_id, user_id, booking_date, check_in_date, check_out_date,
                    nights, guests, total_amount, deposit_amount, balance_amount,
                    payment_stage, hotel_status, status, notes, package_booking_ref,
                    package_overall_status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 'deposit_paid', 'pending', 'pending', ?, ?, 'pending', NOW(), NOW())
            ", [
                $hotelListingId,
                $hotelRoomId,
                $touristId,
                $travelDate,
                $checkInDate,
                $checkOutDate,
                $nights,
                $guestsCount,
                $hotelShare,
                $hotelShare,
                $hotelNotes,
                $pkgRef,
            ]);

            Database::insert("
                INSERT INTO package_booking_items (
                    package_booking_ref, parent_booking_id, package_id,
                    provider_id, provider_role, service_type, service_name,
                    sub_booking_id, allocated_amount, status, created_at
                ) VALUES (?, ?, ?, ?, 'hotel_provider', 'hotel', ?, ?, ?, 'pending', NOW())
            ", [
                $pkgRef,
                $parentBookingId,
                $packageId,
                $hotelProviderId,
                "{$hotelName} — {$roomName}",
                $hotelBookingId,
                $hotelShare,
            ]);
        }

        // 5. Register Rental Provider Request IF AND ONLY IF included
        $rentalRequestId = null;
        if ($rentalIncluded && $vehicleDetails !== null) {
            $vehicleId = (int) ($vehicleDetails['id'] ?? 0);
            $rentalProviderId = (int) ($vehicleDetails['user_id'] ?? $vehicleDetails['partner_id'] ?? 0);
            if ($rentalProviderId <= 0 && $vehicleId > 0) {
                $vUser = Database::first("SELECT user_id FROM vehicles WHERE id = ?", [$vehicleId]);
                $rentalProviderId = (int) ($vUser['user_id'] ?? 0);
            }
            $vehicleName = (string) ($vehicleDetails['vehicle_name'] ?? $vehicleDetails['name'] ?? 'Rental Vehicle');
            $vehicleType = (string) ($vehicleDetails['vehicle_type'] ?? $vehicleDetails['type'] ?? 'Scooter');
            $shopName = (string) ($vehicleDetails['partner_name'] ?? $vehicleDetails['location'] ?? 'Rental Partner');

            $rentalNotes = "Package Booking Ref: {$pkgRef} | Package: {$package['title']} | Special notes: {$specialNotes}";

            $guideDoc = Database::first("
                SELECT guide_id_doc FROM package_services WHERE package_id = ? AND service_type = 'vehicle' LIMIT 1
            ", [$packageId])['guide_id_doc'] ?? null;
            if (empty($guideDoc)) {
                $guideDoc = Database::first("
                    SELECT guide_vehicle_id_doc FROM listings WHERE id = ? LIMIT 1
                ", [$packageId])['guide_vehicle_id_doc'] ?? null;
            }

            $rentalRequestId = Database::insert("
                INSERT INTO rental_requests (
                    user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, location,
                    pickup_date, rental_days, total_amount, customer_name, customer_email,
                    customer_phone, notes, status, payment_status, payment_method,
                    payment_reference, paid_at, package_booking_ref, package_booking_id, guide_id_doc, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'paid', ?, ?, NOW(), ?, ?, ?, NOW())
            ", [
                $touristId,
                (string) $vehicleId,
                $vehicleName,
                $vehicleType,
                $shopName,
                $vehicleDetails['location'] ?? 'Cebu',
                $travelDate,
                $days,
                $vehicleShare,
                $contactName,
                $contactEmail,
                $contactPhone,
                $rentalNotes,
                $paymentMethod,
                $txnRef,
                $pkgRef,
                $parentBookingId,
                $guideDoc,
            ]);

            Database::insert("
                INSERT INTO package_booking_items (
                    package_booking_ref, parent_booking_id, package_id,
                    provider_id, provider_role, service_type, service_name,
                    rental_request_id, allocated_amount, status, created_at
                ) VALUES (?, ?, ?, ?, 'rental_provider', 'vehicle', ?, ?, ?, 'pending', NOW())
            ", [
                $pkgRef,
                $parentBookingId,
                $packageId,
                $rentalProviderId,
                $vehicleName,
                $rentalRequestId,
                $vehicleShare,
            ]);
        }

        // 6. Notify participating providers
        self::dispatchProviderBookingNotifications($pkgRef, $parentBookingId, $package, $payload, [
            'guideId' => $guideId,
            'guideShare' => $guideShare,
            'hotelDetails' => $hotelIncluded ? $hotelDetails : null,
            'hotelShare' => $hotelShare,
            'vehicleDetails' => $rentalIncluded ? $vehicleDetails : null,
            'vehicleShare' => $vehicleShare,
            'travelDate' => $travelDate,
            'days' => $days,
            'nights' => $nights,
            'contactName' => $contactName,
        ]);

        return [
            'success' => true,
            'package_booking_ref' => $pkgRef,
            'parent_booking_id' => $parentBookingId,
            'hotel_booking_id' => $hotelBookingId,
            'rental_request_id' => $rentalRequestId,
            'total_amount' => $finalTotal,
            'status' => 'pending',
            'overall_status' => 'pending',
            'allocations' => [
                'guide' => $guideShare,
                'hotel' => $hotelShare,
                'vehicle' => $vehicleShare,
            ],
            'message' => "Your travel package booking has been submitted! Reference #{$pkgRef}. All participating providers have received reservation requests.",
        ];
    }

    /**
     * Dispatch notification alerts to all providers participating in the package booking.
     *
     * @param string $pkgRef
     * @param int $parentBookingId
     * @param array<string, mixed> $package
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $meta
     */
    private static function dispatchProviderBookingNotifications(
        string $pkgRef,
        int $parentBookingId,
        array $package,
        array $payload,
        array $meta
    ): void {
        $packageTitle = (string) $package['title'];
        $travelDate = (string) ($meta['travelDate'] ?? '');
        $touristName = (string) ($meta['contactName'] ?? 'Tourist');

        // Guide Notification
        $guideId = (int) $meta['guideId'];
        $guideUser = User::find($guideId);
        if ($guideUser && !empty($guideUser['email'])) {
            $msg = "A tourist has booked your Travel Package '{$packageTitle}'. Travel Date: {$travelDate}. Package Reference: {$pkgRef}. Allocated Share: ₱" . number_format((float) $meta['guideShare'], 2) . ". Please review and confirm this booking in your Bookings dashboard.";
            NotificationService::send($guideUser['email'], "New Package Booking: {$packageTitle} (#{$pkgRef})", $msg);
            self::createAutomatedMessage($guideId, "New Package Booking (#{$pkgRef})", $msg);
        }

        // Hotel Provider Notification
        if (!empty($meta['hotelDetails'])) {
            $hotel = $meta['hotelDetails'];
            $hotelEmail = (string) ($hotel['provider_email'] ?? '');
            $hotelProviderId = (int) ($hotel['partner_id'] ?? $hotel['user_id'] ?? 0);
            $roomName = (string) ($hotel['room_name'] ?? $hotel['name'] ?? 'Room');
            $nights = (int) ($meta['nights'] ?? 1);
            $hotelShare = (float) ($meta['hotelShare'] ?? 0);

            if ($hotelEmail !== '') {
                $msg = "A travel package containing your accommodation ('{$roomName}') has been booked by {$touristName} for {$nights} night(s) starting {$travelDate}. Package Ref: {$pkgRef}. Payout Share: ₱" . number_format($hotelShare, 2) . ". Please inspect and confirm the reservation in your Hotel Reservations section.";
                NotificationService::send($hotelEmail, "Package Reservation: {$roomName} (#{$pkgRef})", $msg);
            }
            if ($hotelProviderId > 0) {
                self::createAutomatedMessage($hotelProviderId, "Package Reservation Request (#{$pkgRef})", "Your room '{$roomName}' is included in package booking #{$pkgRef}. Please confirm or decline this reservation in Hotel Reservations.");
            }
        }

        // Vehicle Rental Provider Notification
        if (!empty($meta['vehicleDetails'])) {
            $vehicle = $meta['vehicleDetails'];
            $rentalEmail = (string) ($vehicle['provider_email'] ?? '');
            $rentalProviderId = (int) ($vehicle['user_id'] ?? 0);
            $vehicleName = (string) ($vehicle['vehicle_name'] ?? $vehicle['name'] ?? 'Vehicle');
            $days = (int) ($meta['days'] ?? 1);
            $vehicleShare = (float) ($meta['vehicleShare'] ?? 0);

            if ($rentalEmail !== '') {
                $msg = "A travel package containing your rental vehicle ('{$vehicleName}') has been booked by {$touristName} for {$days} day(s) starting {$travelDate}. Package Ref: {$pkgRef}. Payout Share: ₱" . number_format($vehicleShare, 2) . ". Please review and confirm the rental request in your Rental Requests section.";
                NotificationService::send($rentalEmail, "Package Rental Request: {$vehicleName} (#{$pkgRef})", $msg);
            }
            if ($rentalProviderId > 0) {
                self::createAutomatedMessage($rentalProviderId, "Package Rental Request (#{$pkgRef})", "Your rental unit '{$vehicleName}' is included in travel package booking #{$pkgRef}. Please review in Rental Requests.");
            }
        }
    }

    /**
     * Mark a complete travel package as completed (all services fulfilled).
     *
     * @param string $pkgRef
     * @param int $bookingId
     * @param int $userId
     * @return array<string, mixed>
     */
    public static function completePackageBooking(string $pkgRef, int $bookingId = 0, int $userId = 0): array
    {
        self::ensureTables();

        // 1. Mark parent booking and all bookings linked to this package reference as completed
        Database::run("
            UPDATE bookings
            SET status = 'completed', package_overall_status = 'completed', updated_at = NOW()
            WHERE package_booking_ref = ?
        ", [$pkgRef]);

        if ($bookingId > 0) {
            Database::run("
                UPDATE bookings
                SET status = 'completed', package_overall_status = 'completed', updated_at = NOW()
                WHERE id = ?
            ", [$bookingId]);
        }

        // 2. Mark all items in package_booking_items as completed
        Database::run("
            UPDATE package_booking_items
            SET status = 'completed', updated_at = NOW()
            WHERE package_booking_ref = ?
        ", [$pkgRef]);

        // 3. Mark all sub-bookings (hotel reservations) and rental requests as completed
        $items = Database::all("SELECT * FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef]);
        foreach ($items as $it) {
            if (!empty($it['sub_booking_id'])) {
                Database::run("
                    UPDATE bookings
                    SET status = 'completed', hotel_status = 'completed', updated_at = NOW()
                    WHERE id = ?
                ", [(int) $it['sub_booking_id']]);
            }
            if (!empty($it['rental_request_id'])) {
                Database::run("
                    UPDATE rental_requests
                    SET status = 'completed', updated_at = NOW()
                    WHERE id = ?
                ", [(int) $it['rental_request_id']]);
            }
        }

        // 4. Notify tourist
        $parent = Database::first("
            SELECT b.*, u.email AS tourist_email, u.name AS tourist_name, l.title AS package_title
            FROM bookings b
            JOIN users u ON u.id = b.user_id
            JOIN listings l ON l.id = b.listing_id
            WHERE (b.package_booking_ref = ? OR b.id = ?)
            ORDER BY b.id ASC
            LIMIT 1
        ", [$pkgRef, $bookingId]);

        if ($parent && !empty($parent['tourist_email'])) {
            $msg = "Your Travel Package '{$parent['package_title']}' has been marked as Completed by your tour guide host. Thank you for traveling with GuideMate! You may now leave a review to share your travel experience.";
            NotificationService::send(
                $parent['tourist_email'],
                "Trip Completed: {$parent['package_title']} (#{$pkgRef})",
                $msg
            );
        }

        return [
            'success' => true,
            'package_booking_ref' => $pkgRef,
            'overall_status' => 'completed',
            'items' => $items,
        ];
    }

    /**
     * Called when a Guide updates a booking in GuideController::updateBookingStatus.
     */
    public static function onBookingUpdated(int $bookingId, string $status, int $userId): void
    {
        self::ensureTables();

        // If completed, finalize the entire package booking as completed
        if ($status === 'completed') {
            $booking = Database::first("SELECT id, package_booking_ref FROM bookings WHERE id = ?", [$bookingId]);
            $pkgRef = !empty($booking['package_booking_ref']) ? (string) $booking['package_booking_ref'] : null;

            if (!$pkgRef) {
                $item = Database::first("SELECT package_booking_ref FROM package_booking_items WHERE sub_booking_id = ? OR parent_booking_id = ? LIMIT 1", [$bookingId, $bookingId]);
                if ($item) {
                    $pkgRef = (string) $item['package_booking_ref'];
                }
            }

            if ($pkgRef) {
                self::completePackageBooking($pkgRef, $bookingId, $userId);
            }
            return;
        }

        $item = Database::first("
            SELECT * FROM package_booking_items
            WHERE sub_booking_id = ? AND provider_id = ?
        ", [$bookingId, $userId]);

        if (!$item) {
            // Check if this booking is the parent booking
            $parent = Database::first("SELECT id, package_booking_ref FROM bookings WHERE id = ? AND package_booking_ref IS NOT NULL", [$bookingId]);
            if ($parent && !empty($parent['package_booking_ref'])) {
                $pkgRef = (string) $parent['package_booking_ref'];
                $action = ($status === 'confirmed') ? 'confirm' : ($status === 'cancelled' ? 'cancel' : 'pending');
                self::respond($pkgRef, 'guide', $userId, $action);
            }
            return;
        }

        $pkgRef = (string) $item['package_booking_ref'];
        $serviceType = (string) $item['service_type'];
        $action = ($status === 'confirmed') ? 'confirm' : ($status === 'cancelled' ? 'cancel' : 'pending');

        self::respond($pkgRef, $serviceType, $userId, $action);
    }

    /**
     * Called when a Hotel Provider updates a reservation in HotelController::updateReservationStatus.
     */
    public static function onHotelBookingUpdated(int $bookingId, string $action, int $userId): void
    {
        self::ensureTables();

        $item = Database::first("
            SELECT * FROM package_booking_items
            WHERE sub_booking_id = ? AND service_type = 'hotel'
        ", [$bookingId]);

        if (!$item) {
            return;
        }

        $pkgRef = (string) $item['package_booking_ref'];
        $mappedAction = match ($action) {
            'confirm', 'confirm_deposit', 'accept', 'check_in', 'settle_balance' => 'confirm',
            'cancel', 'no_show' => 'cancel',
            default => 'pending',
        };

        self::respond($pkgRef, 'hotel', (int) $item['provider_id'], $mappedAction);
    }

    /**
     * Called when a Rental Provider updates a rental request in RentalController::updateStatus.
     */
    public static function onRentalUpdated(int $rentalId, string $status, int $userId): void
    {
        self::ensureTables();

        $item = Database::first("
            SELECT * FROM package_booking_items
            WHERE rental_request_id = ? AND service_type = 'vehicle'
        ", [$rentalId]);

        if (!$item) {
            $rr = Database::first("SELECT * FROM rental_requests WHERE id = ?", [$rentalId]);
            if ($rr && !empty($rr['package_booking_id'])) {
                $item = Database::first("
                    SELECT * FROM package_booking_items
                    WHERE parent_booking_id = ? AND service_type = 'vehicle'
                ", [(int) $rr['package_booking_id']]);
                if ($item) {
                    Database::run("UPDATE package_booking_items SET rental_request_id = ? WHERE id = ?", [$rentalId, $item['id']]);
                }
            }
        }

        if (!$item) {
            return;
        }

        if ((int) ($item['provider_id'] ?? 0) <= 0 && $userId > 0) {
            Database::run("UPDATE package_booking_items SET provider_id = ? WHERE id = ?", [$userId, $item['id']]);
            $item['provider_id'] = $userId;
        }

        $pkgRef = (string) $item['package_booking_ref'];
        $action = in_array($status, ['approved', 'contacted', 'completed'], true)
            ? 'confirm'
            : ($status === 'cancelled' ? 'cancel' : 'pending');

        self::respond($pkgRef, 'vehicle', (int) $item['provider_id'], $action);
    }

    /**
     * Respond to a package booking item (confirm or cancel) and coordinate the overall package status.
     *
     * @param string $pkgRef
     * @param string $serviceType 'guide' | 'hotel' | 'vehicle'
     * @param int $providerId
     * @param string $action 'confirm' | 'cancel'
     * @param string|null $reason
     * @return array<string, mixed>
     */
    public static function respond(
        string $pkgRef,
        string $serviceType,
        int $providerId,
        string $action,
        ?string $reason = null
    ): array {
        self::ensureTables();

        $item = Database::first("
            SELECT * FROM package_booking_items
            WHERE package_booking_ref = ? AND service_type = ? AND provider_id = ?
        ", [$pkgRef, $serviceType, $providerId]);

        if (!$item) {
            // Fallback by service type and package ref
            $item = Database::first("
                SELECT * FROM package_booking_items
                WHERE package_booking_ref = ? AND service_type = ?
            ", [$pkgRef, $serviceType]);
        }

        if (!$item) {
            throw new RuntimeException("Package booking service item not found for reference {$pkgRef}.");
        }

        $newStatus = ($action === 'confirm') ? 'confirmed' : 'cancelled';

        Database::run("
            UPDATE package_booking_items
            SET status = ?, cancellation_reason = COALESCE(?, cancellation_reason), responded_at = NOW()
            WHERE id = ?
        ", [$newStatus, $reason, $item['id']]);

        // Keep sub-booking records in sync
        if ($item['sub_booking_id'] !== null) {
            $subId = (int) $item['sub_booking_id'];
            if ($newStatus === 'confirmed') {
                Database::run("UPDATE bookings SET status = 'confirmed', hotel_status = 'confirmed' WHERE id = ?", [$subId]);
            } else {
                Database::run("UPDATE bookings SET status = 'cancelled', hotel_status = 'cancelled', cancellation_reason = ? WHERE id = ?", [$reason ?? 'Cancelled by provider', $subId]);
            }
        }

        if ($item['rental_request_id'] !== null) {
            $rId = (int) $item['rental_request_id'];
            if ($newStatus === 'confirmed') {
                Database::run("UPDATE rental_requests SET status = 'approved' WHERE id = ?", [$rId]);
            } else {
                Database::run("UPDATE rental_requests SET status = 'cancelled' WHERE id = ?", [$rId]);
            }
        }

        // Re-evaluate overall consensus status
        return self::synchronizeOverallPackageStatus($pkgRef, $serviceType, $action, $reason);
    }

    /**
     * Synchronize and evaluate overall package booking status across all providers.
     *
     * @param string $pkgRef
     * @param string $lastService
     * @param string $lastAction
     * @param string|null $reason
     * @return array<string, mixed>
     */
    public static function synchronizeOverallPackageStatus(
        string $pkgRef,
        string $lastService = '',
        string $lastAction = '',
        ?string $reason = null
    ): array {
        self::ensureTables();

        $items = Database::all("SELECT * FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef]);
        if ($items === []) {
            return ['status' => 'pending'];
        }

        $parentBookingId = (int) $items[0]['parent_booking_id'];

        // If package or parent booking is completed, preserve completed consensus
        $parent = Database::first("SELECT id, status, package_overall_status FROM bookings WHERE id = ? OR package_booking_ref = ?", [$parentBookingId, $pkgRef]);
        if ($parent && ($parent['status'] === 'completed' || ($parent['package_overall_status'] ?? '') === 'completed')) {
            return [
                'success' => true,
                'package_booking_ref' => $pkgRef,
                'overall_status' => 'completed',
                'items' => $items,
            ];
        }

        $hasRejection = false;
        $hasPending = false;
        $allConfirmed = true;
        $rejectingItem = null;

        foreach ($items as $it) {
            if ($it['status'] === 'cancelled') {
                $hasRejection = true;
                $allConfirmed = false;
                $rejectingItem = $it;
            } elseif ($it['status'] === 'pending') {
                $hasPending = true;
                $allConfirmed = false;
            }
        }

        // Consensus logic
        if ($hasRejection) {
            // Rule: If ANY provider cancels, overall package is cancelled and payment is refunded
            $overallStatus = 'cancelled';
            self::cascadeCancellation($pkgRef, $parentBookingId, $rejectingItem, $reason);
        } elseif ($allConfirmed) {
            // Rule: All required providers have confirmed -> package is fully Confirmed
            $overallStatus = 'confirmed';
            self::finalizeConfirmation($pkgRef, $parentBookingId, $items);
        } else {
            // Rule: Some confirmed, some pending -> partially confirmed
            $hasAnyConfirmed = false;
            foreach ($items as $it) {
                if ($it['status'] === 'confirmed') {
                    $hasAnyConfirmed = true;
                    break;
                }
            }
            $overallStatus = $hasAnyConfirmed ? 'partially_confirmed' : 'pending';
            Database::run("
                UPDATE bookings
                SET package_overall_status = ?, status = 'pending'
                WHERE package_booking_ref = ?
            ", [$overallStatus, $pkgRef]);
        }

        return [
            'success' => true,
            'package_booking_ref' => $pkgRef,
            'overall_status' => $overallStatus,
            'items' => $items,
        ];
    }

    /**
     * Finalize confirmation: Mark package confirmed, release settlement to provider accounts.
     *
     * @param string $pkgRef
     * @param int $parentBookingId
     * @param array<int, array<string, mixed>> $items
     */
    private static function finalizeConfirmation(string $pkgRef, int $parentBookingId, array $items): void
    {
        $parent = Database::first("SELECT status, package_overall_status FROM bookings WHERE id = ?", [$parentBookingId]);
        if ($parent && ($parent['status'] === 'completed' || ($parent['package_overall_status'] ?? '') === 'completed')) {
            return;
        }

        Database::run("
            UPDATE bookings
            SET package_overall_status = 'confirmed', status = 'confirmed'
            WHERE package_booking_ref = ? AND status NOT IN ('completed', 'cancelled', 'refunded')
        ", [$pkgRef]);

        // Release payment allocations into provider balances
        foreach ($items as $it) {
            $providerId = (int) $it['provider_id'];
            $amount = (float) $it['allocated_amount'];
            if ($providerId > 0 && $amount > 0) {
                User::addRevenue($providerId, $amount);
                User::incrementRequests($providerId);
            }
        }

        // Notify tourist that all providers confirmed
        $parent = Database::first("
            SELECT b.*, u.email AS tourist_email, u.name AS tourist_name, l.title AS package_title
            FROM bookings b
            JOIN users u ON u.id = b.user_id
            JOIN listings l ON l.id = b.listing_id
            WHERE b.id = ?
        ", [$parentBookingId]);

        if ($parent && !empty($parent['tourist_email'])) {
            $msg = "Great news! All participating providers for your Travel Package '{$parent['package_title']}' have confirmed your reservation. Package Booking Reference: {$pkgRef}. Your trip is fully confirmed!";
            NotificationService::send($parent['tourist_email'], "Package Confirmed: {$parent['package_title']} (#{$pkgRef})", $msg);
        }
    }

    /**
     * Cascade cancellation: Cancel all sub-bookings, process tourist refund, and notify everyone.
     *
     * @param string $pkgRef
     * @param int $parentBookingId
     * @param array<string, mixed>|null $rejectingItem
     * @param string|null $reason
     */
    private static function cascadeCancellation(
        string $pkgRef,
        int $parentBookingId,
        ?array $rejectingItem,
        ?string $reason
    ): void {
        $cancelReason = $reason ?? ($rejectingItem['cancellation_reason'] ?? 'A participating provider was unable to accommodate the package reservation.');

        // 1. Cancel all bookings and rental requests tied to this package
        Database::run("
            UPDATE bookings
            SET package_overall_status = 'cancelled', status = 'cancelled',
                cancelled_at = NOW(), cancellation_reason = ?
            WHERE package_booking_ref = ?
        ", [$cancelReason, $pkgRef]);

        Database::run("
            UPDATE rental_requests
            SET status = 'cancelled', admin_note = ?
            WHERE package_booking_ref = ?
        ", [$cancelReason, $pkgRef]);

        // 2. Refund the tourist's payment
        Payment::refund($parentBookingId);

        // 3. Notify tourist and participating providers
        $parent = Database::first("
            SELECT b.*, u.email AS tourist_email, u.name AS tourist_name, l.title AS package_title
            FROM bookings b
            JOIN users u ON u.id = b.user_id
            JOIN listings l ON l.id = b.listing_id
            WHERE b.id = ?
        ", [$parentBookingId]);

        if ($parent && !empty($parent['tourist_email'])) {
            $refundAmount = (float) $parent['total_amount'];
            $msg = "Notice: Your travel package reservation '{$parent['package_title']}' (#{$pkgRef}) has been cancelled because: {$cancelReason}. A full refund of ₱" . number_format($refundAmount, 2) . " has been automatically reversed to your original payment method.";
            NotificationService::send($parent['tourist_email'], "Package Reservation Cancelled (#{$pkgRef})", $msg);
        }

        // Notify guide
        if ($parent && !empty($parent['guide_id'])) {
            $guide = User::find((int) $parent['guide_id']);
            if ($guide && !empty($guide['email'])) {
                $msg = "Your travel package booking #{$pkgRef} ('{$parent['package_title']}') was cancelled. Reason: {$cancelReason}.";
                NotificationService::send($guide['email'], "Package Booking Cancelled (#{$pkgRef})", $msg);
            }
        }
    }

    /**
     * Process a tourist-initiated cancellation for an entire package booking.
     *
     * @param int $parentBookingId
     * @param int $touristId
     * @param string $reason
     * @return array<string, mixed>
     */
    public static function cancelByTourist(int $parentBookingId, int $touristId, string $reason = 'Cancelled by tourist'): array
    {
        self::ensureTables();

        $booking = Database::first("SELECT * FROM bookings WHERE id = ? AND user_id = ?", [$parentBookingId, $touristId]);
        if (!$booking) {
            throw new RuntimeException('Package reservation not found.');
        }

        $pkgRef = (string) ($booking['package_booking_ref'] ?? '');
        if ($pkgRef === '') {
            $pkgRef = 'GM-PKG-' . $parentBookingId;
        }

        // Update all package booking items to cancelled
        Database::run("
            UPDATE package_booking_items
            SET status = 'cancelled', cancellation_reason = ?
            WHERE package_booking_ref = ?
        ", [$reason, $pkgRef]);

        self::cascadeCancellation($pkgRef, $parentBookingId, null, $reason);

        return [
            'success' => true,
            'package_booking_ref' => $pkgRef,
            'status' => 'cancelled',
            'refund_amount' => (float) $booking['total_amount'],
            'message' => 'Travel package booking cancelled. A full refund has been initiated.',
        ];
    }

    /**
     * Get summary metrics and provider approval details for a package booking.
     *
     * @param string|int $refOrId
     * @return array<string, mixed>|null
     */
    public static function getPackageBookingSummary(string|int $refOrId): ?array
    {
        self::ensureTables();

        $isRef = is_string($refOrId) && str_starts_with($refOrId, 'GM-PKG-');
        $item = $isRef
            ? Database::first("SELECT * FROM package_booking_items WHERE package_booking_ref = ? LIMIT 1", [$refOrId])
            : Database::first("SELECT * FROM package_booking_items WHERE parent_booking_id = ? LIMIT 1", [(int) $refOrId]);

        if (!$item) {
            return null;
        }

        $pkgRef = (string) $item['package_booking_ref'];
        $items = Database::all("
            SELECT pbi.*,
                   COALESCE(u.name, v_owner.name, h_owner.name, 'Service Partner') AS provider_name,
                   COALESCE(u.email, v_owner.email, h_owner.email, '') AS provider_email,
                   COALESCE(u.phone, v_owner.phone, h_owner.phone, '') AS provider_phone,
                   COALESCE(NULLIF(pbi.provider_id, 0), v_owner.id, h_owner.id, 0) AS resolved_provider_id,
                   rr.status AS rental_request_status
            FROM package_booking_items pbi
            LEFT JOIN users u ON u.id = pbi.provider_id
            LEFT JOIN rental_requests rr ON rr.id = pbi.rental_request_id
            LEFT JOIN vehicles v ON (v.id = rr.vehicle_id OR v.id = CAST(rr.vehicle_id AS UNSIGNED))
            LEFT JOIN users v_owner ON v_owner.id = v.user_id
            LEFT JOIN bookings sub_b ON sub_b.id = pbi.sub_booking_id
            LEFT JOIN listings sub_l ON sub_l.id = sub_b.listing_id
            LEFT JOIN users h_owner ON h_owner.id = sub_l.user_id
            WHERE pbi.package_booking_ref = ?
            ORDER BY pbi.id ASC
        ", [$pkgRef]);

        // Auto-heal provider_id and synchronize provider status if needed
        foreach ($items as &$it) {
            $realProvId = (int) ($it['resolved_provider_id'] ?? 0);
            if ((int) ($it['provider_id'] ?? 0) <= 0 && $realProvId > 0) {
                Database::run("UPDATE package_booking_items SET provider_id = ? WHERE id = ?", [$realProvId, $it['id']]);
                $it['provider_id'] = $realProvId;
            }
            if ($it['service_type'] === 'vehicle' && ($it['rental_request_status'] ?? '') === 'approved' && $it['status'] === 'pending') {
                Database::run("UPDATE package_booking_items SET status = 'confirmed' WHERE id = ?", [$it['id']]);
                $it['status'] = 'confirmed';
            }
        }
        unset($it);

        $parent = Database::first("SELECT * FROM bookings WHERE id = ?", [(int) $item['parent_booking_id']]);
        $overallStatus = (string) ($parent['package_overall_status'] ?? $parent['status'] ?? 'pending');
        if (($parent['status'] ?? '') === 'completed' || ($parent['package_overall_status'] ?? '') === 'completed') {
            $overallStatus = 'completed';
        }

        return [
            'package_booking_ref' => $pkgRef,
            'parent_booking_id' => (int) $item['parent_booking_id'],
            'overall_status' => $overallStatus,
            'items' => $items,
            'total_amount' => (float) ($parent['total_amount'] ?? 0),
        ];
    }

    /**
     * Send an automated system message between users in the messages table.
     */
    private static function createAutomatedMessage(int $recipientId, string $subject, string $body): void
    {
        try {
            $sender = Database::first("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $senderId = $sender ? (int) $sender['id'] : 1;
            Database::insert("
                INSERT INTO messages (sender_id, receiver_id, subject, body, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ", [$senderId, $recipientId, $subject, $body]);
        } catch (\Throwable) {}
    }
}
