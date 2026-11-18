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
                    `original_allocated_amount` DECIMAL(10,2) NULL,
                    `status` ENUM('pending', 'confirmed', 'cancelled', 'rescheduled', 'completed', 'removed') NOT NULL DEFAULT 'pending',
                    `is_replacement` TINYINT(1) NOT NULL DEFAULT 0,
                    `replaces_item_id` INT UNSIGNED NULL,
                    `cancellation_reason` VARCHAR(500) NULL,
                    `rescheduled_date` DATE NULL,
                    `rescheduled_time` VARCHAR(64) NULL,
                    `rescheduled_notes` VARCHAR(500) NULL,
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
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `original_allocated_amount` DECIMAL(10,2) NULL AFTER `allocated_amount`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` MODIFY COLUMN `status` ENUM('pending', 'confirmed', 'cancelled', 'rescheduled', 'completed', 'removed') NOT NULL DEFAULT 'pending'");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `is_replacement` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `replaces_item_id` INT UNSIGNED NULL AFTER `is_replacement`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `cancellation_reason` VARCHAR(500) NULL AFTER `replaces_item_id`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `rescheduled_date` DATE NULL AFTER `responded_at`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `rescheduled_time` VARCHAR(64) NULL AFTER `rescheduled_date`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `rescheduled_notes` VARCHAR(500) NULL AFTER `rescheduled_time`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `responded_at` TIMESTAMP NULL DEFAULT NULL AFTER `cancellation_reason`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `refund_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `original_allocated_amount`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `refund_status` VARCHAR(50) NULL AFTER `refund_amount`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `payment_status` VARCHAR(50) NULL AFTER `refund_status`");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `package_booking_items` ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            } catch (\Throwable) {}

            // 2. Ensure package reference and overall status columns on bookings table
            try {
                Database::run("
                    ALTER TABLE `bookings`
                    ADD COLUMN `package_booking_ref` VARCHAR(64) NULL AFTER `notes`,
                    ADD COLUMN `package_overall_status` ENUM('pending', 'partially_confirmed', 'confirmed', 'action_required', 'rescheduled', 'cancellation_requested', 'cancelled', 'completed') NULL AFTER `package_booking_ref`,
                    ADD INDEX `idx_bookings_pkg_ref` (`package_booking_ref`)
                ");
            } catch (\Throwable) {}
            try {
                Database::run("ALTER TABLE `bookings` MODIFY COLUMN `package_overall_status` ENUM('pending', 'partially_confirmed', 'confirmed', 'action_required', 'rescheduled', 'cancellation_requested', 'cancelled', 'completed') NULL");
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

            // 4. Create package booking audit logs table
            try {
                Database::run("
                    CREATE TABLE IF NOT EXISTS `package_booking_audit_logs` (
                        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `package_booking_ref` VARCHAR(64) NOT NULL,
                        `parent_booking_id` INT UNSIGNED NOT NULL,
                        `item_id` INT UNSIGNED NULL,
                        `action_type` VARCHAR(64) NOT NULL,
                        `actor_id` INT UNSIGNED NULL,
                        `actor_role` VARCHAR(64) NULL,
                        `summary` VARCHAR(500) NOT NULL,
                        `details` TEXT NULL,
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `idx_pkg_audit_ref` (`package_booking_ref`),
                        KEY `idx_pkg_audit_parent` (`parent_booking_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (\Throwable) {}

            self::$tablesEnsured = true;
        } catch (\Throwable) {
            // Gracefully ignore if offline or sandbox restricted
        }
    }

    /**
     * Automatically derive package durations and dates.
     * Hotel nights: D - 1 (or max(1, D - 1)). Rental days: D.
     *
     * @param int|string|array<string, mixed>|null $packageOrDuration
     * @param int|null $explicitDays
     * @return array{days: int, duration_days: int, hotel_nights: int, nights: int, rental_days: int, duration_code: string}
     */
    public static function derivePackageDurations(int|string|array|null $packageOrDuration, ?int $explicitDays = null): array
    {
        $days = 3;
        $durationStr = '';

        if (is_numeric($explicitDays) && (int) $explicitDays > 0) {
            $days = (int) $explicitDays;
        } elseif (is_array($packageOrDuration)) {
            if (!empty($packageOrDuration['duration_days'])) {
                $days = max(1, (int) $packageOrDuration['duration_days']);
            } elseif (!empty($packageOrDuration['days_count'])) {
                $days = max(1, (int) $packageOrDuration['days_count']);
            } elseif (!empty($packageOrDuration['duration'])) {
                $durationStr = (string) $packageOrDuration['duration'];
            }
        } elseif (is_string($packageOrDuration)) {
            $durationStr = $packageOrDuration;
        } elseif (is_int($packageOrDuration) && $packageOrDuration > 0) {
            $pkg = Listing::find($packageOrDuration);
            if ($pkg && !empty($pkg['duration'])) {
                $durationStr = (string) $pkg['duration'];
            }
        }

        if ($durationStr !== '' && (!is_numeric($explicitDays) || (int) $explicitDays <= 0)) {
            if (preg_match('/(\d+)\s*(?:d|day)/i', $durationStr, $m)) {
                $days = max(1, (int) $m[1]);
            } elseif (preg_match('/half\s*day/i', $durationStr)) {
                $days = 1;
            }
        }

        $hotelNights = max(1, $days - 1);
        $rentalDays = max(1, $days);

        return [
            'days' => $days,
            'duration_days' => $days,
            'hotel_nights' => $hotelNights,
            'nights' => $hotelNights,
            'rental_days' => $rentalDays,
            'duration_code' => "{$days}D{$hotelNights}N",
        ];
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

        // Parse package metadata using AdminController inspection helper & derived durations
        $inspected = AdminController::buildPackageInspectionData($package, $guideUser);
        $durations = self::derivePackageDurations($package, !empty($payload['duration_days']) ? (int) $payload['duration_days'] : (!empty($inspected['duration_days']) ? (int) $inspected['duration_days'] : null));
        $days = $durations['days'];
        $nights = $durations['hotel_nights'];
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
            $hotelDetails['nights'] = $stayNights;
            $hotelDetails['duration_nights'] = $stayNights;
            $hotelDetails['check_in_date'] = $travelDate;
            $hotelDetails['check_out_date'] = date('Y-m-d', strtotime("{$travelDate} +{$stayNights} days"));
        }

        $vehicleShare = 0.00;
        $vehicleDetails = null;
        if ($rentalIncluded) {
            $vehicleDetails = $inspected['rental'];
            $dailyRate = (float) ($vehicleDetails['daily_rate'] ?? $vehicleDetails['price_per_day'] ?? 0);
            $vehicleShare = round($dailyRate * $days, 2);
            $vehicleDetails['rental_days'] = $days;
            $vehicleDetails['duration_days'] = $days;
            $vehicleDetails['pickup_date'] = $travelDate;
            $vehicleDetails['return_date'] = date('Y-m-d', strtotime("{$travelDate} +{$days} days"));
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
        $rentalReturnDate = date('Y-m-d', strtotime("{$travelDate} +{$days} days"));

        // Validate provider availability for the matched duration
        if ($hotelIncluded && !empty($hotelDetails['room_id'])) {
            $roomId = (int) $hotelDetails['room_id'];
            $roomConflict = Database::first("
                SELECT id FROM bookings
                WHERE room_id = ?
                  AND status NOT IN ('cancelled', 'refunded')
                  AND hotel_status NOT IN ('cancelled', 'refunded')
                  AND check_in_date < ?
                  AND check_out_date > ?
                LIMIT 1
            ", [$roomId, $checkOutDate, $checkInDate]);
            if ($roomConflict) {
                $rm = Database::first("SELECT name FROM hotel_rooms WHERE id = ?", [$roomId]);
                $rmName = $rm['name'] ?? 'Hotel room';
                throw new RuntimeException("Selected room '{$rmName}' is not available for the package dates ({$checkInDate} to {$checkOutDate}).");
            }
        }

        if ($rentalIncluded && !empty($vehicleDetails['id'])) {
            $vehicleId = (int) $vehicleDetails['id'];
            $vConflict = Database::first("
                SELECT id FROM rental_requests
                WHERE vehicle_id = ?
                  AND status IN ('approved', 'confirmed', 'contacted', 'pending')
                  AND pickup_date < ?
                  AND DATE_ADD(pickup_date, INTERVAL COALESCE(rental_days, 1) DAY) > ?
                LIMIT 1
            ", [$vehicleId, $rentalReturnDate, $travelDate]);
            if ($vConflict) {
                $v = Database::first("SELECT name, make, model FROM vehicles WHERE id = ?", [$vehicleId]);
                $vName = $v['name'] ?? ($v ? trim($v['make'] . ' ' . $v['model']) : 'Vehicle');
                throw new RuntimeException("Selected vehicle '{$vName}' is already reserved for the package duration ({$travelDate} to {$rentalReturnDate}).");
            }
        }

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
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests,
                total_amount, deposit_amount, balance_amount,
                payment_stage, status, notes, package_booking_ref,
                package_overall_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 'deposit_paid', 'pending', ?, ?, 'pending', NOW(), NOW())
        ", [
            $packageId,
            $touristId,
            $travelDate,
            $checkInDate,
            $checkOutDate,
            $nights,
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

        // 2. Mark non-removed items in package_booking_items as completed
        Database::run("
            UPDATE package_booking_items
            SET status = 'completed', updated_at = NOW()
            WHERE package_booking_ref = ? AND status NOT IN ('removed', 'cancelled')
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

        // If completed, update the guide's package component status and re-evaluate overall consensus
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
                Database::run("
                    UPDATE package_booking_items
                    SET status = 'completed', responded_at = NOW()
                    WHERE package_booking_ref = ? AND service_type = 'guide'
                ", [$pkgRef]);
                // Re-evaluate consensus: overall package only becomes completed if all providers have completed!
                self::synchronizeOverallPackageStatus($pkgRef, 'guide', 'completed');
            }
            return;
        }

        // If cancelled or refunded, trigger immediate cancellation cascade
        if (in_array($status, ['cancelled', 'refunded'], true)) {
            $booking = Database::first("SELECT id, package_booking_ref FROM bookings WHERE id = ?", [$bookingId]);
            $pkgRef = !empty($booking['package_booking_ref']) ? (string) $booking['package_booking_ref'] : null;

            if (!$pkgRef) {
                $item = Database::first("SELECT package_booking_ref FROM package_booking_items WHERE sub_booking_id = ? OR parent_booking_id = ? LIMIT 1", [$bookingId, $bookingId]);
                if ($item) {
                    $pkgRef = (string) $item['package_booking_ref'];
                }
            }

            if ($pkgRef) {
                self::respond($pkgRef, 'guide', $userId, 'cancel');
                return;
            }
        }

        $item = Database::first("
            SELECT * FROM package_booking_items
            WHERE sub_booking_id = ?
        ", [$bookingId]);

        if (!$item) {
            // Check if this booking is the parent booking
            $parent = Database::first("SELECT id, package_booking_ref FROM bookings WHERE id = ? AND package_booking_ref IS NOT NULL", [$bookingId]);
            if ($parent && !empty($parent['package_booking_ref'])) {
                $pkgRef = (string) $parent['package_booking_ref'];
                $action = ($status === 'confirmed') ? 'confirm' : (in_array($status, ['cancelled', 'refunded'], true) ? 'cancel' : 'pending');
                self::respond($pkgRef, 'guide', $userId, $action);
            }
            return;
        }

        $pkgRef = (string) $item['package_booking_ref'];
        $serviceType = (string) $item['service_type'];
        $action = ($status === 'confirmed') ? 'confirm' : (in_array($status, ['cancelled', 'refunded'], true) ? 'cancel' : 'pending');

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
            'settle_balance', 'completed' => 'completed',
            'confirm', 'confirm_deposit', 'accept', 'check_in' => 'confirm',
            'cancel', 'no_show', 'cancelled', 'refunded' => 'cancel',
            default => 'pending',
        };

        if ($action === 'check_in') {
            Database::run("UPDATE bookings SET hotel_status = 'checked_in' WHERE id = ?", [$bookingId]);
        }

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
        $action = in_array($status, ['approved', 'contacted'], true)
            ? 'confirm'
            : ($status === 'completed' ? 'completed' : (in_array($status, ['cancelled', 'refunded', 'declined', 'rejected'], true) ? 'cancel' : 'pending'));

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
            ORDER BY is_replacement DESC, id DESC LIMIT 1
        ", [$pkgRef, $serviceType, $providerId]);

        if (!$item) {
            // Fallback by service type and package ref, preferring active replacement/pending
            $item = Database::first("
                SELECT * FROM package_booking_items
                WHERE package_booking_ref = ? AND service_type = ? AND status != 'cancelled'
                ORDER BY is_replacement DESC, id DESC LIMIT 1
            ", [$pkgRef, $serviceType]);
        }

        if (!$item) {
            $item = Database::first("
                SELECT * FROM package_booking_items
                WHERE package_booking_ref = ? AND service_type = ?
                ORDER BY is_replacement DESC, id DESC LIMIT 1
            ", [$pkgRef, $serviceType]);
        }

        if (!$item) {
            // Check if parent booking exists
            $parent = Database::first("SELECT id FROM bookings WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
            if ($parent && $action === 'cancel') {
                self::cascadeCancellation($pkgRef, (int) $parent['id'], null, $reason);
                return [
                    'success' => true,
                    'package_booking_ref' => $pkgRef,
                    'overall_status' => 'cancelled',
                ];
            }
            return [
                'success' => false,
                'package_booking_ref' => $pkgRef,
                'error' => "Package booking service item not found for reference {$pkgRef}.",
            ];
        }

        $newStatus = ($action === 'confirm' || $action === 'completed') ? (($action === 'completed') ? 'completed' : 'confirmed') : 'cancelled';

        Database::run("
            UPDATE package_booking_items
            SET status = ?, cancellation_reason = COALESCE(?, cancellation_reason), responded_at = NOW()
            WHERE id = ?
        ", [$newStatus, $reason, $item['id']]);

        // Keep sub-booking records in sync without demoting progressed statuses
        if ($item['sub_booking_id'] !== null) {
            $subId = (int) $item['sub_booking_id'];
            if ($newStatus === 'confirmed' || $newStatus === 'completed') {
                Database::run("
                    UPDATE bookings
                    SET status = CASE
                            WHEN status = 'completed' OR ? = 'completed' THEN 'completed'
                            ELSE 'confirmed'
                        END,
                        hotel_status = CASE
                            WHEN hotel_status IN ('checked_in', 'completed') THEN hotel_status
                            ELSE 'confirmed'
                        END
                    WHERE id = ?
                ", [$newStatus, $subId]);
            } else {
                $subB = Database::first("SELECT * FROM bookings WHERE id = ?", [$subId]);
                $paid = 0.00;
                if ($subB) {
                    $hasBalance = ((float) ($subB['balance_amount'] ?? 0)) > 0;
                    if ($hasBalance) {
                        $paid = ((float) ($subB['deposit_amount'] ?? 0) > 0)
                            ? (float) $subB['deposit_amount']
                            : max(0.00, (float) $subB['total_amount'] - (float) $subB['balance_amount']);
                    } else {
                        $paid = (float) $subB['total_amount'] > 0 ? (float) $subB['total_amount'] : (float) $item['allocated_amount'];
                    }
                }
                Database::run("
                    UPDATE bookings
                    SET status = 'cancelled',
                        hotel_status = 'cancelled',
                        cancelled_at = COALESCE(cancelled_at, NOW()),
                        cancellation_reason = ?,
                        refund_amount = ?,
                        refund_status = 'refunded',
                        balance_amount = 0.00,
                        updated_at = NOW()
                    WHERE id = ?
                ", [$reason ?? 'Cancelled by provider', $paid, $subId]);

                Database::run("
                    UPDATE package_booking_items
                    SET refund_amount = ?,
                        refund_status = 'refunded',
                        payment_status = 'refunded'
                    WHERE id = ?
                ", [$paid, (int) $item['id']]);

                Database::run("UPDATE payments SET status = 'refunded' WHERE booking_id = ? AND status = 'paid'", [$subId]);
                if ($paid > 0) {
                    Database::insert("
                        INSERT INTO payments (booking_id, amount, method, status, reference, created_at)
                        VALUES (?, ?, 'refund', 'refunded', ?, NOW())
                    ", [$subId, -$paid, 'REF-HOTEL-' . strtoupper(bin2hex(random_bytes(4)))]);
                }
            }
        }

        if ($item['rental_request_id'] !== null) {
            $rId = (int) $item['rental_request_id'];
            if ($newStatus === 'confirmed' || $newStatus === 'completed') {
                Database::run("
                    UPDATE rental_requests
                    SET status = CASE
                        WHEN status IN ('contacted', 'completed') THEN status
                        ELSE 'approved'
                    END
                    WHERE id = ?
                ", [$rId]);
            } else {
                $rr = Database::first("SELECT * FROM rental_requests WHERE id = ?", [$rId]);
                $paid = $rr ? ((float) $rr['total_amount'] > 0 ? (float) $rr['total_amount'] : (float) $item['allocated_amount']) : (float) $item['allocated_amount'];

                Database::run("
                    UPDATE rental_requests
                    SET status = 'cancelled',
                        cancelled_at = COALESCE(cancelled_at, NOW()),
                        cancellation_reason = ?,
                        payment_status = 'refunded',
                        refund_status = 'refunded',
                        refund_amount = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ", [$reason ?? 'Cancelled by provider', $paid, $rId]);

                Database::run("
                    UPDATE package_booking_items
                    SET refund_amount = ?,
                        refund_status = 'refunded',
                        payment_status = 'refunded'
                    WHERE id = ?
                ", [$paid, (int) $item['id']]);

                if ($paid > 0) {
                    Database::insert("
                        INSERT INTO payments (booking_id, amount, method, status, reference, created_at)
                        VALUES (?, ?, 'refund', 'refunded', ?, NOW())
                    ", [(int) $item['parent_booking_id'], -$paid, 'REF-RENTAL-' . strtoupper(bin2hex(random_bytes(4)))]);
                }
            }
        }

        // Synchronize replacement details in parent booking notes if present
        $parentForNotes = Database::first("SELECT id, notes FROM bookings WHERE package_booking_ref = ? OR id = ?", [$pkgRef, (int) ($item['parent_booking_id'] ?? 0)]);
        if ($parentForNotes && !empty($parentForNotes['notes'])) {
            $pNotes = json_decode((string) $parentForNotes['notes'], true);
            if (is_array($pNotes) && isset($pNotes['package_details'])) {
                $notesChanged = false;
                if ($serviceType === 'hotel' && isset($pNotes['package_details']['accommodation_replacement'])) {
                    $pNotes['package_details']['accommodation_replacement']['status'] = $newStatus;
                    $notesChanged = true;
                }
                if ($serviceType === 'vehicle' && isset($pNotes['package_details']['rental_replacement'])) {
                    $pNotes['package_details']['rental_replacement']['status'] = $newStatus;
                    $notesChanged = true;
                }
                if ($notesChanged) {
                    Database::run("UPDATE bookings SET notes = ? WHERE id = ?", [json_encode($pNotes), (int) $parentForNotes['id']]);
                }
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

        $supersededItemIds = [];
        foreach ($items as $it) {
            if (!empty($it['replaces_item_id'])) {
                $supersededItemIds[] = (int) $it['replaces_item_id'];
            }
        }

        $hasRejection = false;
        $hasPending = false;
        $activeItems = [];
        $rejectingItem = null;

        foreach ($items as $it) {
            $st = (string) ($it['status'] ?? 'pending');
            $itId = (int) $it['id'];
            if ($st === 'removed') {
                continue; // Dropped items do not affect ongoing consensus
            }
            if (in_array($itId, $supersededItemIds, true)) {
                continue; // Superseded original items do not participate in active consensus
            }
            $activeItems[] = $it;

            if ($st === 'cancelled') {
                $hasRejection = true;
                $rejectingItem = $it;
            } elseif ($st === 'pending') {
                $hasPending = true;
            }
        }

        $allCompleted = count($activeItems) > 0;
        $allConfirmedOrCompleted = count($activeItems) > 0;

        foreach ($activeItems as $it) {
            $st = (string) ($it['status'] ?? 'pending');
            if ($st !== 'completed') {
                $allCompleted = false;
            }
            if (!in_array($st, ['confirmed', 'completed', 'rescheduled'], true)) {
                $allConfirmedOrCompleted = false;
            }
        }

        // Consensus logic: Independent provider cancellation
        if ($hasRejection) {
            $allCancelled = true;
            foreach ($activeItems as $it) {
                if ($it['status'] !== 'cancelled') {
                    $allCancelled = false;
                    break;
                }
            }

            if ($allCancelled) {
                $overallStatus = 'cancelled';
                self::cascadeCancellation($pkgRef, $parentBookingId, $rejectingItem, $reason);
            } else {
                // Rule: If one provider cancels, do NOT automatically cancel the entire package or other providers!
                // Mark overall package status as action_required to allow traveler/guide to select alternatives
                $overallStatus = 'action_required';
                Database::run("
                    UPDATE bookings
                    SET package_overall_status = 'action_required'
                    WHERE package_booking_ref = ? OR id = ?
                ", [$pkgRef, $parentBookingId]);

                if ($rejectingItem !== null) {
                    self::notifyProviderCancellation($pkgRef, $rejectingItem, $reason);
                    self::logAudit(
                        $pkgRef,
                        $parentBookingId,
                        (int) $rejectingItem['id'],
                        'provider_cancelled',
                        (int) ($rejectingItem['provider_id'] ?? 0),
                        (string) ($rejectingItem['provider_role'] ?? 'provider'),
                        "Service provider cancelled " . ucfirst((string) $rejectingItem['service_type']) . " service ({$rejectingItem['service_name']}). Package overall status set to action_required.",
                        $reason
                    );
                }
            }
        } elseif ($allCompleted) {
            // Rule: Overall package status should ONLY become Completed when ALL required providers have completed their respective services
            $overallStatus = 'completed';
            self::completePackageBooking($pkgRef, $parentBookingId, 0);
        } elseif ($allConfirmedOrCompleted) {
            // Rule: All active providers have confirmed (or rescheduled/completed) -> Package is Confirmed
            $overallStatus = 'confirmed';
            self::finalizeConfirmation($pkgRef, $parentBookingId, $items);
            Database::run("
                UPDATE bookings
                SET package_overall_status = 'confirmed', status = 'confirmed'
                WHERE package_booking_ref = ? AND status NOT IN ('completed', 'cancelled', 'refunded')
            ", [$pkgRef]);
        } else {
            // Rule: Some confirmed/completed, some pending -> partially confirmed
            $hasAnyConfirmedOrCompleted = false;
            foreach ($activeItems as $it) {
                if (in_array($it['status'], ['confirmed', 'completed', 'rescheduled'], true)) {
                    $hasAnyConfirmedOrCompleted = true;
                    break;
                }
            }
            $overallStatus = $hasAnyConfirmedOrCompleted ? 'partially_confirmed' : 'pending';
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
     * Cascade cancellation: Atomically cancel all bookings, reservations, and rental requests
     * associated with the same Travel Package, refund payments, and notify customer and all providers.
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
        self::ensureTables();

        $parent = Database::first("
            SELECT b.*, u.email AS tourist_email, u.name AS tourist_name, l.title AS package_title
            FROM bookings b
            LEFT JOIN users u ON u.id = b.user_id
            LEFT JOIN listings l ON l.id = b.listing_id
            WHERE b.id = ? OR b.package_booking_ref = ?
            LIMIT 1
        ", [$parentBookingId, $pkgRef]);

        if (!$parent) {
            return;
        }

        // Rule: If package is already completed, prevent cancellation
        if (($parent['status'] ?? '') === 'completed' || ($parent['package_overall_status'] ?? '') === 'completed') {
            return;
        }

        // Rule: Prevent duplicate cancellation processing and repeated notifications
        $items = Database::all("SELECT * FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef]);
        if ($items === []) {
            $items = Database::all("SELECT * FROM package_booking_items WHERE parent_booking_id = ?", [$parentBookingId]);
        }
        $allCancelled = count($items) > 0;
        foreach ($items as $it) {
            if (($it['status'] ?? '') !== 'cancelled') {
                $allCancelled = false;
                break;
            }
        }
        if ($allCancelled && ($parent['status'] ?? '') === 'cancelled' && ($parent['package_overall_status'] ?? '') === 'cancelled') {
            return; // Already fully cancelled
        }

        // Determine initiating provider context
        $initiatingRole = (string) ($rejectingItem['service_type'] ?? 'provider');
        $initiatorId = (int) ($rejectingItem['provider_id'] ?? 0);
        $initiatorUser = $initiatorId > 0 ? User::find($initiatorId) : null;
        $initiatorName = $initiatorUser['name'] ?? ($rejectingItem['service_name'] ?? ucfirst($initiatingRole) . ' Provider');

        $baseReason = $reason ?: ($rejectingItem['cancellation_reason'] ?? 'A participating service provider was unable to accommodate the reservation.');
        $cancelReason = "Cancelled by {$initiatorName} (" . ucfirst($initiatingRole) . "): {$baseReason}";

        // Wrap updates in transaction to guarantee consistency and prevent partially updated records
        $pdo = Database::pdo();
        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }

        try {
            // 1. Cancel all package_booking_items
            Database::run("
                UPDATE package_booking_items
                SET status = 'cancelled',
                    cancellation_reason = COALESCE(?, cancellation_reason),
                    refund_amount = allocated_amount,
                    refund_status = 'refunded',
                    payment_status = 'refunded',
                    responded_at = COALESCE(responded_at, NOW())
                WHERE package_booking_ref = ? OR parent_booking_id = ?
            ", [$cancelReason, $pkgRef, $parentBookingId]);

            // 2. Process all hotel sub-bookings and balance due refunds
            $subBookings = Database::all("SELECT * FROM bookings WHERE package_booking_ref = ? AND id != ?", [$pkgRef, $parentBookingId]);
            foreach ($items as $it) {
                if (!empty($it['sub_booking_id']) && (int) $it['sub_booking_id'] !== $parentBookingId) {
                    $found = false;
                    foreach ($subBookings as $sb) {
                        if ((int) $sb['id'] === (int) $it['sub_booking_id']) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $extraSub = Database::first("SELECT * FROM bookings WHERE id = ?", [(int) $it['sub_booking_id']]);
                        if ($extraSub) {
                            $subBookings[] = $extraSub;
                        }
                    }
                }
            }

            foreach ($subBookings as $subB) {
                $hasBalanceDue = ((float) ($subB['balance_amount'] ?? 0)) > 0;
                $amountPaid = 0.00;
                if ($hasBalanceDue) {
                    // Outstanding balance due: refund ONLY the amount already paid toward the reservation
                    if ((float) ($subB['deposit_amount'] ?? 0) > 0) {
                        $amountPaid = (float) $subB['deposit_amount'];
                    } else {
                        $amountPaid = max(0.00, (float) $subB['total_amount'] - (float) $subB['balance_amount']);
                    }
                } else {
                    $amountPaid = (float) $subB['total_amount'];
                }

                Database::run("
                    UPDATE bookings
                    SET status = 'cancelled',
                        hotel_status = 'cancelled',
                        package_overall_status = 'cancelled',
                        cancelled_at = COALESCE(cancelled_at, NOW()),
                        cancellation_reason = COALESCE(cancellation_reason, ?),
                        refund_amount = ?,
                        refund_status = 'refunded',
                        balance_amount = 0.00,
                        payment_stage = 'settled',
                        updated_at = NOW()
                    WHERE id = ?
                ", [$cancelReason, $amountPaid, (int) $subB['id']]);

                Database::run("UPDATE payments SET status = 'refunded' WHERE booking_id = ? AND status = 'paid'", [(int) $subB['id']]);
                if ($amountPaid > 0) {
                    Database::insert("
                        INSERT INTO payments (booking_id, amount, method, status, reference, created_at)
                        VALUES (?, ?, 'refund', 'refunded', ?, NOW())
                    ", [(int) $subB['id'], -$amountPaid, 'REF-HOTEL-' . strtoupper(bin2hex(random_bytes(4)))]);
                }
            }

            // 3. Cancel all rental_requests associated with this package
            $rentals = Database::all("SELECT * FROM rental_requests WHERE package_booking_ref = ? OR package_booking_id = ?", [$pkgRef, $parentBookingId]);
            foreach ($rentals as $r) {
                $rPaid = (float) ($r['total_amount'] ?? 0);
                Database::run("
                    UPDATE rental_requests
                    SET status = 'cancelled',
                        cancelled_at = COALESCE(cancelled_at, NOW()),
                        cancellation_reason = COALESCE(cancellation_reason, ?),
                        payment_status = 'refunded',
                        refund_status = 'refunded',
                        refund_amount = ?,
                        admin_note = COALESCE(admin_note, ?),
                        updated_at = NOW()
                    WHERE id = ?
                ", [$cancelReason, $rPaid, $cancelReason, (int) $r['id']]);
            }

            // 4. Cancel parent booking and refund amount paid
            $parentBalanceDue = ((float) ($parent['balance_amount'] ?? 0)) > 0;
            $parentPaid = 0.00;
            if ($parentBalanceDue) {
                if ((float) ($parent['deposit_amount'] ?? 0) > 0) {
                    $parentPaid = (float) $parent['deposit_amount'];
                } else {
                    $parentPaid = max(0.00, (float) $parent['total_amount'] - (float) $parent['balance_amount']);
                }
            } else {
                $parentPaid = (float) $parent['total_amount'];
            }

            Database::run("
                UPDATE bookings
                SET status = 'cancelled',
                    hotel_status = 'cancelled',
                    package_overall_status = 'cancelled',
                    cancelled_at = COALESCE(cancelled_at, NOW()),
                    cancellation_reason = COALESCE(cancellation_reason, ?),
                    refund_amount = ?,
                    refund_status = 'refunded',
                    balance_amount = 0.00,
                    payment_stage = 'settled',
                    updated_at = NOW()
                WHERE id = ?
            ", [$cancelReason, $parentPaid, $parentBookingId]);

            Payment::refund($parentBookingId);
            if ($parentPaid > 0) {
                Database::insert("
                    INSERT INTO payments (booking_id, amount, method, status, reference, created_at)
                    VALUES (?, ?, 'refund', 'refunded', ?, NOW())
                ", [$parentBookingId, -$parentPaid, 'REF-PKG-' . strtoupper(bin2hex(random_bytes(4)))]);
            }

            if (!$inTx) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$inTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // 5. Notify customer and all affected providers
        $pkgTitle = (string) ($parent['package_title'] ?? 'Travel Package');
        $touristEmail = (string) ($parent['tourist_email'] ?? '');
        $touristId = (int) ($parent['user_id'] ?? 0);

        // A. Notify Tourist
        if ($touristEmail !== '') {
            $refundAmount = (float) $parent['total_amount'];
            $msg = "Notice: Your travel package reservation '{$pkgTitle}' (#{$pkgRef}) has been cancelled because: {$cancelReason}. A full refund of ₱" . number_format($refundAmount, 2) . " has been reversed to your original payment method.";
            NotificationService::send($touristEmail, "Package Reservation Cancelled (#{$pkgRef})", $msg);
        }
        if ($touristId > 0) {
            self::createAutomatedMessage(
                $touristId,
                "Package Booking Cancelled (#{$pkgRef})",
                "Your travel package '{$pkgTitle}' has been cancelled. Reason: {$cancelReason}."
            );
        }

        // B. Notify Guide
        $guideId = (int) ($parent['guide_id'] ?? 0);
        if ($guideId > 0 && $guideId !== $initiatorId) {
            $guide = User::find($guideId);
            if ($guide && !empty($guide['email'])) {
                $msg = "Notice: Travel Package #{$pkgRef} ('{$pkgTitle}') was cancelled because another participating provider was unable to accommodate the package reservation. Reason: {$cancelReason}.";
                NotificationService::send($guide['email'], "Package Booking Cancelled (#{$pkgRef})", $msg);
            }
            self::createAutomatedMessage(
                $guideId,
                "Package Reservation Cancelled (#{$pkgRef})",
                "Travel Package reservation #{$pkgRef} ('{$pkgTitle}') was cancelled. Reason: {$cancelReason}."
            );
        }

        // C. Notify Hotel Provider and Vehicle Rental Provider
        foreach ($items as $it) {
            $provId = (int) ($it['provider_id'] ?? 0);
            if ($provId > 0 && $provId !== $initiatorId && $provId !== $guideId) {
                $prov = User::find($provId);
                if ($prov && !empty($prov['email'])) {
                    $svcType = ucfirst((string) $it['service_type']);
                    $msg = "Notice: Travel Package #{$pkgRef} ('{$pkgTitle}') containing your {$svcType} reservation was cancelled. Reason: {$cancelReason}.";
                    NotificationService::send($prov['email'], "Package Reservation Cancelled (#{$pkgRef})", $msg);
                }
                self::createAutomatedMessage(
                    $provId,
                    "Package Reservation Cancelled (#{$pkgRef})",
                    "Travel Package reservation #{$pkgRef} ('{$pkgTitle}') was cancelled. Reason: {$cancelReason}."
                );
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
                   rr.status AS rental_request_status,
                   rr.refund_amount AS rental_refund_amount,
                   rr.refund_status AS rental_refund_status,
                   rr.payment_status AS rental_payment_status,
                   rr.rental_days,
                   rr.pickup_date AS rental_pickup_date,
                   DATE_ADD(rr.pickup_date, INTERVAL COALESCE(rr.rental_days, 1) DAY) AS rental_return_date,
                   COALESCE(rr.vehicle_name, v.name, 'Rental Vehicle') AS vehicle_name,
                   COALESCE(rr.vehicle_type, v.type, 'Vehicle') AS vehicle_type,
                   sub_b.hotel_status AS hotel_reservation_status,
                   sub_b.status AS sub_booking_status,
                   sub_b.refund_amount AS hotel_refund_amount,
                   sub_b.refund_status AS hotel_refund_status,
                   sub_b.balance_amount AS hotel_balance_amount,
                   sub_b.deposit_amount AS hotel_deposit_amount,
                   sub_b.payment_stage AS hotel_payment_stage,
                   sub_b.check_in_date AS hotel_check_in_date,
                   sub_b.check_out_date AS hotel_check_out_date,
                   sub_b.nights AS hotel_nights
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
            if ($it['service_type'] === 'vehicle') {
                $rStatus = (string) ($it['rental_request_status'] ?? '');
                if (in_array($rStatus, ['approved', 'contacted'], true) && $it['status'] === 'pending') {
                    Database::run("UPDATE package_booking_items SET status = 'confirmed' WHERE id = ?", [$it['id']]);
                    $it['status'] = 'confirmed';
                } elseif ($rStatus === 'completed' && $it['status'] !== 'completed') {
                    Database::run("UPDATE package_booking_items SET status = 'completed' WHERE id = ?", [$it['id']]);
                    $it['status'] = 'completed';
                }
            } elseif ($it['service_type'] === 'hotel') {
                $hStatus = (string) ($it['hotel_reservation_status'] ?? '');
                if (in_array($hStatus, ['confirmed', 'checked_in'], true) && $it['status'] === 'pending') {
                    Database::run("UPDATE package_booking_items SET status = 'confirmed' WHERE id = ?", [$it['id']]);
                    $it['status'] = 'confirmed';
                } elseif ($hStatus === 'completed' && $it['status'] !== 'completed') {
                    Database::run("UPDATE package_booking_items SET status = 'completed' WHERE id = ?", [$it['id']]);
                    $it['status'] = 'completed';
                }
            } elseif ($it['service_type'] === 'guide') {
                $subStatus = (string) ($it['sub_booking_status'] ?? '');
                if ($subStatus === 'completed' && $it['status'] !== 'completed') {
                    Database::run("UPDATE package_booking_items SET status = 'completed' WHERE id = ?", [$it['id']]);
                    $it['status'] = 'completed';
                }
            }
        }
        unset($it);

        $hasRejection = false;
        $hasPending = false;
        $allConfirmedOrCompleted = count($items) > 0;
        $allCompleted = count($items) > 0;

        foreach ($items as $it) {
            $st = (string) ($it['status'] ?? 'pending');
            if ($st === 'cancelled') {
                $hasRejection = true;
                $allConfirmedOrCompleted = false;
                $allCompleted = false;
            } elseif ($st === 'pending') {
                $hasPending = true;
                $allConfirmedOrCompleted = false;
                $allCompleted = false;
            } elseif ($st === 'confirmed') {
                $allCompleted = false;
            } elseif ($st === 'completed') {
                // Completed item
            }
        }

        $parent = Database::first("SELECT * FROM bookings WHERE id = ?", [(int) $item['parent_booking_id']]);
        $parentOverall = (string) ($parent['package_overall_status'] ?? '');
        $parentStatus = (string) ($parent['status'] ?? '');
        $parentCancelled = $parent && (in_array($parentStatus, ['cancelled', 'refunded'], true) || $parentOverall === 'cancelled');
        $parentCompleted = ($parentStatus === 'completed' || $parentOverall === 'completed');

        $supersededItemIds = [];
        foreach ($items as $it) {
            if (!empty($it['replaces_item_id'])) {
                $supersededItemIds[] = (int) $it['replaces_item_id'];
            }
        }

        $hasRejection = false;
        $hasPending = false;
        $hasRescheduled = false;
        $activeItems = [];
        $cancelledItems = [];
        $allCancelledItems = [];
        $replacementHotel = null;
        $replacementVehicle = null;
        $originalHotel = null;
        $originalVehicle = null;

        foreach ($items as $it) {
            $st = (string) ($it['status'] ?? 'pending');
            $itId = (int) $it['id'];
            $isSuperseded = in_array($itId, $supersededItemIds, true);

            if ($it['service_type'] === 'hotel') {
                if (!empty($it['is_replacement']) || !empty($it['replaces_item_id'])) {
                    $replacementHotel = $it;
                } else {
                    $originalHotel = $it;
                }
            } elseif ($it['service_type'] === 'vehicle') {
                if (!empty($it['is_replacement']) || !empty($it['replaces_item_id'])) {
                    $replacementVehicle = $it;
                } else {
                    $originalVehicle = $it;
                }
            }

            if ($st === 'cancelled') {
                $allCancelledItems[] = $it;
                if (!$isSuperseded) {
                    $hasRejection = true;
                    $cancelledItems[] = $it;
                }
            } elseif ($st === 'removed') {
                continue;
            } elseif (!$isSuperseded) {
                $activeItems[] = $it;
                if ($st === 'pending') {
                    $hasPending = true;
                } elseif ($st === 'rescheduled') {
                    $hasRescheduled = true;
                }
            }
        }

        $parentNotes = !empty($parent['notes']) ? json_decode((string) $parent['notes'], true) : [];
        $pkgDetailsNotes = is_array($parentNotes) ? ($parentNotes['package_details'] ?? []) : [];
        if (!empty($pkgDetailsNotes['accommodation_replacement'])) {
            $replacementHotel = array_merge($replacementHotel ?? [], $pkgDetailsNotes['accommodation_replacement']);
        }
        if (!empty($pkgDetailsNotes['rental_replacement'])) {
            $replacementVehicle = array_merge($replacementVehicle ?? [], $pkgDetailsNotes['rental_replacement']);
        }
        if (!empty($pkgDetailsNotes['accommodation']) && $originalHotel) {
            $originalHotel = array_merge($originalHotel, $pkgDetailsNotes['accommodation']);
        }
        if (!empty($pkgDetailsNotes['rental']) && $originalVehicle) {
            $originalVehicle = array_merge($originalVehicle, $pkgDetailsNotes['rental']);
        }

        $allActiveCompleted = count($activeItems) > 0;
        $allActiveConfirmed = count($activeItems) > 0;
        foreach ($activeItems as $it) {
            if ($it['status'] !== 'completed') {
                $allActiveCompleted = false;
            }
            if (!in_array($it['status'], ['confirmed', 'completed', 'rescheduled'], true)) {
                $allActiveConfirmed = false;
            }
        }

        if ($parentCancelled) {
            $overallStatus = 'cancelled';
        } elseif ($parentCompleted || $allActiveCompleted) {
            $overallStatus = 'completed';
            if ($parent && ($parentOverall !== 'completed' || $parentStatus !== 'completed')) {
                Database::run("UPDATE bookings SET status = 'completed', package_overall_status = 'completed', updated_at = NOW() WHERE package_booking_ref = ? OR id = ?", [$pkgRef, (int) $parent['id']]);
            }
        } elseif ($hasRejection || $parentOverall === 'action_required') {
            $overallStatus = 'action_required';
        } elseif ($allActiveConfirmed) {
            $overallStatus = 'confirmed';
        } else {
            $hasAnyConfirmedOrCompleted = false;
            foreach ($activeItems as $it) {
                if (in_array($it['status'], ['confirmed', 'completed', 'rescheduled'], true)) {
                    $hasAnyConfirmedOrCompleted = true;
                    break;
                }
            }
            $overallStatus = $hasAnyConfirmedOrCompleted ? 'partially_confirmed' : 'pending';
        }

        // Fetch audit logs
        $auditLogs = Database::all("
            SELECT * FROM package_booking_audit_logs
            WHERE package_booking_ref = ?
            ORDER BY id DESC
        ", [$pkgRef]);

        return [
            'package_booking_ref' => $pkgRef,
            'parent_booking_id' => (int) $item['parent_booking_id'],
            'overall_status' => $overallStatus,
            'items' => $items,
            'active_items' => $activeItems,
            'cancelled_items' => $cancelledItems,
            'all_cancelled_items' => $allCancelledItems,
            'has_cancellation' => $hasRejection,
            'replacement_hotel' => $replacementHotel,
            'replacement_vehicle' => $replacementVehicle,
            'original_hotel' => $originalHotel,
            'original_vehicle' => $originalVehicle,
            'package_details' => $pkgDetailsNotes,
            'alternative_options' => [
                'replace' => [
                    'option' => 'A',
                    'title' => 'Replace the Cancelled Service',
                    'description' => 'Find and select an alternative provider in the same category.',
                ],
                'reschedule' => [
                    'option' => 'B',
                    'title' => 'Reschedule the Cancelled Service',
                    'description' => 'Propose an alternative date and time for the affected service.',
                ],
                'continue_without' => [
                    'option' => 'C',
                    'title' => 'Continue Without the Cancelled Service',
                    'description' => 'Drop this service, recalculate cost, and proceed with confirmed services.',
                ],
                'cancel_package' => [
                    'option' => 'D',
                    'title' => 'Cancel the Entire Travel Package',
                    'description' => 'Cancel all included services and receive a full refund.',
                ],
            ],
            'audit_logs' => $auditLogs,
            'total_amount' => (float) ($parent['total_amount'] ?? 0),
            'refund_amount' => (float) ($parent['refund_amount'] ?? 0),
            'refund_status' => (string) ($parent['refund_status'] ?? 'none'),
        ];
    }

    /**
     * Record an audit log for package booking state transitions.
     */
    public static function logAudit(
        string $pkgRef,
        int $parentBookingId,
        ?int $itemId,
        string $actionType,
        ?int $actorId,
        ?string $actorRole,
        string $summary,
        ?string $details = null
    ): void {
        try {
            self::ensureTables();
            Database::insert("
                INSERT INTO package_booking_audit_logs (
                    package_booking_ref, parent_booking_id, item_id, action_type,
                    actor_id, actor_role, summary, details, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $pkgRef,
                $parentBookingId,
                $itemId,
                $actionType,
                $actorId,
                $actorRole,
                $summary,
                $details
            ]);
        } catch (\Throwable) {}
    }

    /**
     * Dispatch cancellation notifications immediately to traveler and guide.
     */
    public static function notifyProviderCancellation(string $pkgRef, array $cancelledItem, ?string $reason = null): void
    {
        $parent = Database::first("
            SELECT b.*, u.email AS tourist_email, u.name AS tourist_name, u.phone AS tourist_phone,
                   l.title AS package_title, l.user_id AS guide_owner_id
            FROM bookings b
            LEFT JOIN users u ON u.id = b.user_id
            LEFT JOIN listings l ON l.id = b.listing_id
            WHERE b.package_booking_ref = ? OR b.id = ?
            LIMIT 1
        ", [$pkgRef, (int) ($cancelledItem['parent_booking_id'] ?? 0)]);

        if (!$parent) {
            return;
        }

        $packageTitle = (string) ($parent['package_title'] ?? 'Travel Package');
        $touristEmail = (string) ($parent['tourist_email'] ?? '');
        $touristId = (int) ($parent['user_id'] ?? 0);

        $providerId = (int) ($cancelledItem['provider_id'] ?? 0);
        $providerUser = $providerId > 0 ? User::find($providerId) : null;
        $providerName = $providerUser['name'] ?? ($cancelledItem['provider_name'] ?? ($cancelledItem['service_name'] ?? 'Service Provider'));
        $serviceType = ucfirst((string) ($cancelledItem['service_type'] ?? 'service'));
        $serviceName = (string) ($cancelledItem['service_name'] ?? $serviceType);
        $cancelReason = $reason ?: ($cancelledItem['cancellation_reason'] ?? 'Service provider is unavailable for this date.');

        // Compute affected schedule
        $schedule = 'Scheduled travel period';
        if (!empty($cancelledItem['sub_booking_id'])) {
            $subB = Database::first("SELECT check_in_date, check_out_date, nights FROM bookings WHERE id = ?", [(int) $cancelledItem['sub_booking_id']]);
            if ($subB && !empty($subB['check_in_date'])) {
                $checkIn = date('M j, Y', strtotime($subB['check_in_date']));
                $checkOut = !empty($subB['check_out_date']) ? date('M j, Y', strtotime($subB['check_out_date'])) : $checkIn;
                $nights = (int) ($subB['nights'] ?? 1);
                $schedule = "Check-in: {$checkIn} to Check-out: {$checkOut} ({$nights} night(s))";
            }
        } elseif (!empty($cancelledItem['rental_request_id'])) {
            $rr = Database::first("SELECT pickup_date, rental_days FROM rental_requests WHERE id = ?", [(int) $cancelledItem['rental_request_id']]);
            if ($rr && !empty($rr['pickup_date'])) {
                $pickup = date('M j, Y', strtotime($rr['pickup_date']));
                $days = (int) ($rr['rental_days'] ?? 1);
                $schedule = "Pickup Date: {$pickup} ({$days} day(s))";
            }
        } else {
            $bDate = !empty($parent['check_in_date']) ? $parent['check_in_date'] : ($parent['booking_date'] ?? '');
            if ($bDate !== '') {
                $schedule = "Travel Date: " . date('M j, Y', strtotime($bDate));
            }
        }

        $subject = "Service Provider Cancellation Notice: {$packageTitle} (#{$pkgRef})";
        $body = "Notice: Provider {$providerName} has cancelled their {$serviceType} service ('{$serviceName}') for Travel Package '{$packageTitle}' (#{$pkgRef}).\n"
              . "Affected Schedule: {$schedule}\n"
              . "Cancellation Reason: {$cancelReason}\n\n"
              . "Important: Your other confirmed bookings in this travel package remain active and unaffected.\n"
              . "Available Alternative Options:\n"
              . "1. Option A: Replace the cancelled service with an available alternative provider\n"
              . "2. Option B: Reschedule the cancelled service to an alternative date\n"
              . "3. Option C: Continue the travel package without this service (amount will be refunded)\n"
              . "4. Option D: Cancel the entire travel package with a full refund\n\n"
              . "Please visit your bookings page or open the GuideMate app to review and select your preferred option.";

        if ($touristEmail !== '') {
            NotificationService::send($touristEmail, $subject, $body);
        }
        if ($touristId > 0) {
            self::createAutomatedMessage($touristId, $subject, $body);
        }

        // Notify Guide/Organizer
        $guideId = (int) ($parent['guide_owner_id'] ?? 0);
        if ($guideId <= 0) {
            $guideItem = Database::first("SELECT provider_id FROM package_booking_items WHERE package_booking_ref = ? AND service_type = 'guide' LIMIT 1", [$pkgRef]);
            $guideId = (int) ($guideItem['provider_id'] ?? 0);
        }

        if ($guideId > 0 && $guideId !== $providerId) {
            $guideUser = User::find($guideId);
            if ($guideUser && !empty($guideUser['email'])) {
                NotificationService::send($guideUser['email'], $subject, $body);
            }
            self::createAutomatedMessage($guideId, $subject, $body);
        }
    }

    /**
     * Get available replacement services for a cancelled package item (Option A).
     * Automatically identifies cancelled service category and applies strict filtering:
     * - Hotel: Located near original hotel, accommodates guest capacity, available for travel dates, active.
     * - Vehicle: Same rental category, passenger capacity meets requirements, available for dates, active.
     * - Things to Do / Attractions: Active and approved in same category, capacity meets guests, available on dates.
     */
    public static function getAvailableAlternatives(string $pkgRef, int $itemId = 0): array
    {
        self::ensureTables();

        $item = null;
        if ($itemId > 0) {
            $item = Database::first("SELECT * FROM package_booking_items WHERE id = ? AND package_booking_ref = ?", [$itemId, $pkgRef]);
        }
        if (!$item) {
            $item = Database::first("SELECT * FROM package_booking_items WHERE package_booking_ref = ? AND status = 'cancelled' ORDER BY id ASC LIMIT 1", [$pkgRef]);
        }
        if (!$item) {
            $item = Database::first("SELECT * FROM package_booking_items WHERE package_booking_ref = ? ORDER BY id ASC LIMIT 1", [$pkgRef]);
        }
        if (!$item) {
            return [];
        }

        $parent = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
        $guestsCount = max(1, (int) ($parent['guests'] ?? 1));
        $travelDate = (string) ($parent['booking_date'] ?? date('Y-m-d'));
        $serviceType = (string) $item['service_type'];
        $alternatives = [];

        $categoryLabel = match ($serviceType) {
            'hotel' => 'Hotel and Stay',
            'vehicle' => 'Vehicle Rental',
            'things_to_do', 'activity' => 'Things to Do',
            'attraction', 'attractions' => 'Attractions',
            default => 'Tour Guide',
        };

        $provUser = Database::first("SELECT name FROM users WHERE id = ?", [(int) $item['provider_id']]);
        $cancelledItemDetails = [
            'id' => (int) $item['id'],
            'service_type' => $serviceType,
            'service_category_label' => $categoryLabel,
            'service_name' => (string) $item['service_name'],
            'cancellation_reason' => (string) ($item['cancellation_reason'] ?? 'Provider unavailable for scheduled date'),
            'allocated_amount' => (float) $item['allocated_amount'],
            'provider_id' => (int) $item['provider_id'],
            'provider_name' => (string) ($provUser['name'] ?? 'Provider'),
        ];

        if ($serviceType === 'hotel') {
            $cancelledHotelArea = '';
            $checkIn = $travelDate;
            $checkOut = date('Y-m-d', strtotime($travelDate . ' + 1 day'));
            if (!empty($item['sub_booking_id'])) {
                $sub = Database::first("SELECT * FROM bookings WHERE id = ?", [(int) $item['sub_booking_id']]);
                if ($sub) {
                    $checkIn = !empty($sub['check_in_date']) ? $sub['check_in_date'] : $travelDate;
                    $checkOut = !empty($sub['check_out_date']) ? $sub['check_out_date'] : date('Y-m-d', strtotime($checkIn . ' + 1 day'));
                    if (!empty($sub['listing_id'])) {
                        $origHotel = Database::first("SELECT area FROM listings WHERE id = ?", [(int) $sub['listing_id']]);
                        $cancelledHotelArea = (string) ($origHotel['area'] ?? '');
                    }
                }
            }
            if ($cancelledHotelArea === '' && !empty($parent['listing_id'])) {
                $parentListing = Database::first("SELECT area FROM listings WHERE id = ?", [(int) $parent['listing_id']]);
                $cancelledHotelArea = (string) ($parentListing['area'] ?? '');
            }

            // Exclude fully booked, unavailable, inactive, or insufficient-capacity hotels
            $hotels = Database::all("
                SELECT l.id, l.title, l.area, l.price, l.user_id AS provider_id, u.name AS provider_name,
                       COALESCE(l.cover_image, (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY sort_order ASC, id ASC LIMIT 1)) AS image
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                JOIN users u ON u.id = l.user_id
                WHERE c.slug = 'hotels'
                  AND l.status = 'approved'
                  AND (u.is_active = 1 OR u.is_active IS NULL)
                  AND l.user_id != ?
                ORDER BY (CASE WHEN l.area = ? AND ? != '' THEN 0 ELSE 1 END), l.id DESC
                LIMIT 20
            ", [(int) $item['provider_id'], $cancelledHotelArea, $cancelledHotelArea]);

            foreach ($hotels as $h) {
                $rooms = Database::all("
                    SELECT hr.*
                    FROM hotel_rooms hr
                    WHERE hr.listing_id = ?
                      AND hr.status = 'available'
                      AND (hr.max_guests IS NULL OR hr.max_guests >= ?)
                ", [(int) $h['id'], $guestsCount]);

                if ($rooms === [] && $guestsCount <= 2) {
                    $rooms = Database::all("
                        SELECT hr.*
                        FROM hotel_rooms hr
                        WHERE hr.listing_id = ?
                          AND hr.status = 'available'
                    ", [(int) $h['id']]);
                }

                foreach ($rooms as $rm) {
                    $roomId = (int) $rm['id'];
                    $roomCap = (int) ($rm['max_guests'] ?? 2);
                    if ($roomCap < $guestsCount) {
                        continue;
                    }
                    if (isset($rm['available_units']) && $rm['available_units'] !== null && (int) $rm['available_units'] <= 0) {
                        continue;
                    }
                    $isBooked = Database::first("
                        SELECT id FROM bookings
                        WHERE room_id = ?
                          AND status NOT IN ('cancelled', 'refunded')
                          AND hotel_status NOT IN ('cancelled', 'refunded')
                          AND check_in_date < ?
                          AND check_out_date > ?
                        LIMIT 1
                    ", [$roomId, $checkOut, $checkIn]);

                    if ($isBooked) {
                        continue;
                    }

                    $pricePerNight = (float) ($rm['price_per_night'] ?? $h['price']);
                    $dur = self::derivePackageDurations($parent['listing_id'] ?? null, (int) ($parent['days_count'] ?? 0));
                    $hotelNights = max(1, (int) ($parent['nights'] ?? $dur['hotel_nights']));
                    $totalCost = round($pricePerNight * $hotelNights, 2);

                    // Price restriction: Replacement service price must not exceed original cancelled service price
                    if ($totalCost > (float) $item['allocated_amount']) {
                        continue;
                    }

                    $roomImg = !empty($rm['image']) ? $rm['image'] : (string) ($h['image'] ?? '');
                    $alternatives[] = [
                        'type' => 'hotel',
                        'service_category_label' => 'Hotel and Stay',
                        'provider_id' => (int) $h['provider_id'],
                        'provider_name' => (string) $h['provider_name'],
                        'listing_id' => (int) $h['id'],
                        'room_id' => $roomId,
                        'title' => (string) $h['title'],
                        'room_name' => (string) $rm['name'],
                        'capacity' => $roomCap,
                        'available_units' => (int) ($rm['available_units'] ?? 1),
                        'area' => (string) $h['area'],
                        'price' => $pricePerNight,
                        'pricing' => $totalCost,
                        'total_cost' => $totalCost,
                        'nights' => $hotelNights,
                        'surplus' => max(0.00, round((float) $item['allocated_amount'] - $totalCost, 2)),
                        'image' => $roomImg,
                        'availability_status' => 'Available',
                    ];
                }
            }
        } elseif ($serviceType === 'vehicle') {
            $targetType = '';
            $pickupDate = $travelDate;
            $rentalDays = 1;
            $returnDate = date('Y-m-d', strtotime($travelDate . ' + 1 day'));
            if (!empty($item['rental_request_id'])) {
                $req = Database::first("SELECT * FROM rental_requests WHERE id = ?", [(int) $item['rental_request_id']]);
                if ($req) {
                    $pickupDate = !empty($req['pickup_date']) ? $req['pickup_date'] : $travelDate;
                    $rentalDays = max(1, (int) ($req['rental_days'] ?? 1));
                    $returnDate = date('Y-m-d', strtotime($pickupDate . " + {$rentalDays} day"));
                    if (!empty($req['vehicle_id'])) {
                        $origV = Database::first("SELECT COALESCE(type, 'Vehicle') as vehicle_type FROM vehicles WHERE id = ?", [(int) $req['vehicle_id']]);
                        $targetType = (string) ($origV['vehicle_type'] ?? '');
                    }
                }
            }

            $vehicles = Database::all("
                SELECT v.*,
                       COALESCE(v.name, CONCAT(COALESCE(v.make, ''), ' ', COALESCE(v.model, ''))) AS make_model,
                       COALESCE(v.type, 'Vehicle') AS vehicle_type,
                       COALESCE(v.price_per_day, 0.0) AS daily_rate,
                       u.name AS provider_name
                FROM vehicles v
                JOIN users u ON u.id = v.user_id
                WHERE (v.status = 'available' OR v.status = 'approved')
                  AND (v.is_available = 1 OR v.is_available IS NULL)
                  AND (u.is_active = 1 OR u.is_active IS NULL)
                  AND v.user_id != ?
                ORDER BY (CASE WHEN v.type = ? AND ? != '' THEN 0 ELSE 1 END), v.id DESC
                LIMIT 20
            ", [(int) $item['provider_id'], $targetType, $targetType]);

            foreach ($vehicles as $v) {
                $vId = (int) $v['id'];
                $vType = (string) ($v['vehicle_type'] ?? $v['type'] ?? 'Vehicle');
                $vCapacity = match (strtolower(trim($vType))) {
                    'scooter', 'motorcycle', 'moped', 'bike' => 2,
                    'sedan', 'hatchback', 'coupe' => 4,
                    'suv', 'crossover' => 5,
                    'van', 'minivan', 'minibus' => 10,
                    default => 4,
                };
                if (!empty($v['capacity'])) {
                    $vCapacity = (int) $v['capacity'];
                }
                if ($vCapacity < $guestsCount) {
                    continue;
                }

                $isReserved = Database::first("
                    SELECT id FROM rental_requests
                    WHERE vehicle_id = ?
                      AND status IN ('approved', 'confirmed', 'contacted', 'pending')
                      AND pickup_date < ?
                      AND DATE_ADD(pickup_date, INTERVAL rental_days DAY) > ?
                    LIMIT 1
                ", [$vId, $returnDate, $pickupDate]);

                if ($isReserved) {
                    continue;
                }

                $title = !empty($v['make_model']) ? trim((string) $v['make_model']) : (!empty($v['name']) ? (string) $v['name'] : 'Vehicle Rental');
                $dailyRate = (float) ($v['daily_rate'] ?? $v['price_per_day'] ?? 0);
                $dur = self::derivePackageDurations($parent['listing_id'] ?? null, (int) ($parent['days_count'] ?? 0));
                $vDays = max(1, (int) ($parent['days_count'] ?? $dur['rental_days']));
                $totalCost = round($dailyRate * $vDays, 2);

                // Price restriction: Replacement service price must not exceed original cancelled service price
                if ($totalCost > (float) $item['allocated_amount']) {
                    continue;
                }

                $alternatives[] = [
                    'type' => 'vehicle',
                    'service_category_label' => 'Vehicle Rental',
                    'provider_id' => (int) $v['user_id'],
                    'provider_name' => (string) $v['provider_name'],
                    'vehicle_id' => $vId,
                    'title' => $title,
                    'vehicle_type' => $vType,
                    'capacity' => $vCapacity,
                    'location' => (string) ($v['location'] ?? 'Cebu'),
                    'price' => $dailyRate,
                    'pricing' => $totalCost,
                    'total_cost' => $totalCost,
                    'days' => $vDays,
                    'surplus' => max(0.00, round((float) $item['allocated_amount'] - $totalCost, 2)),
                    'image' => (string) ($v['image'] ?? ''),
                    'availability_status' => 'Available',
                ];
            }
        } elseif (in_array($serviceType, ['things_to_do', 'activity', 'attraction', 'attractions'], true)) {
            $catSlug = str_contains($serviceType, 'attraction') ? 'attractions' : 'things-to-do';
            $catLabel = str_contains($serviceType, 'attraction') ? 'Attractions' : 'Things to Do';

            $listings = Database::all("
                SELECT l.id, l.title, l.area, l.price, l.capacity, l.user_id AS provider_id, u.name AS provider_name,
                       COALESCE(l.cover_image, (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY sort_order ASC, id ASC LIMIT 1)) AS image
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                JOIN users u ON u.id = l.user_id
                WHERE (c.slug = ? OR c.slug = 'things-to-do' OR c.slug = 'attractions')
                  AND l.status = 'approved'
                  AND (u.is_active = 1 OR u.is_active IS NULL)
                  AND (l.capacity IS NULL OR l.capacity >= ?)
                  AND l.user_id != ?
                ORDER BY l.id DESC
                LIMIT 20
            ", [$catSlug, $guestsCount, (int) $item['provider_id']]);

            foreach ($listings as $l) {
                $lId = (int) $l['id'];
                $lCap = (int) ($l['capacity'] ?? 10);
                if ($lCap < $guestsCount) {
                    continue;
                }
                if (Booking::isDateBooked($lId, $travelDate)) {
                    continue;
                }

                $alternatives[] = [
                    'type' => $serviceType,
                    'service_category_label' => $catLabel,
                    'provider_id' => (int) $l['provider_id'],
                    'provider_name' => (string) $l['provider_name'],
                    'listing_id' => $lId,
                    'title' => (string) $l['title'],
                    'capacity' => $lCap,
                    'area' => (string) $l['area'],
                    'price' => (float) $l['price'],
                    'image' => (string) ($l['image'] ?? ''),
                    'availability_status' => 'Available',
                ];
            }
        } elseif ($serviceType === 'guide') {
            $guides = Database::all("
                SELECT u.id, u.name, u.email, u.phone, u.avatar, u.location
                FROM users u
                WHERE u.role = 'guide' AND (u.is_active = 1 OR u.is_active IS NULL) AND u.id != ?
                ORDER BY u.id DESC
                LIMIT 10
            ", [(int) $item['provider_id']]);

            foreach ($guides as $g) {
                $alternatives[] = [
                    'type' => 'guide',
                    'service_category_label' => 'Tour Guide',
                    'provider_id' => (int) $g['id'],
                    'provider_name' => (string) $g['name'],
                    'title' => 'Licensed Guide ' . (string) $g['name'],
                    'area' => (string) ($g['location'] ?? 'Cebu'),
                    'price' => (float) ($item['allocated_amount'] ?? 2500.00),
                    'avatar' => (string) ($g['avatar'] ?? ''),
                ];
            }
        }

        return [
            'cancelled_item' => $cancelledItemDetails,
            'alternatives' => $alternatives,
        ];
    }

    /**
     * Option A: Replace a cancelled service with an alternative provider.
     */
    public static function replaceCancelledService(
        string $pkgRef,
        int $itemId,
        int $newProviderId,
        int $newServiceId,
        array $options = [],
        int $actorId = 0
    ): array {
        self::ensureTables();

        $item = Database::first("SELECT * FROM package_booking_items WHERE id = ? AND package_booking_ref = ?", [$itemId, $pkgRef]);
        if (!$item) {
            throw new RuntimeException("Service item not found.");
        }

        if ($item['status'] !== 'cancelled') {
            throw new RuntimeException("Only cancelled service items can be replaced.");
        }

        $parent = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
        if (!$parent) {
            throw new RuntimeException("Package booking not found.");
        }

        $parentBookingId = (int) $parent['id'];
        $oldAllocated = (float) $item['allocated_amount'];
        $serviceType = (string) $item['service_type'];

        $newAllocated = $oldAllocated;
        $newServiceName = $item['service_name'];
        $newSubBookingId = $item['sub_booking_id'];
        $newRentalId = $item['rental_request_id'];

        $dur = self::derivePackageDurations($parent['listing_id'] ?? null, (int) ($parent['days_count'] ?? 0));
        $packageDays = $dur['days'];
        $packageNights = $dur['hotel_nights'];
        $parentIsPaid = Payment::isPaid($parentBookingId) || in_array($parent['payment_stage'] ?? '', ['paid', 'deposit_paid', 'full'], true);
        $replacementPaymentStatus = $parentIsPaid ? 'paid' : 'unpaid';

        if ($serviceType === 'hotel') {
            $hotelListing = Database::first("SELECT * FROM listings WHERE id = ?", [$newServiceId]);
            if (!$hotelListing) {
                throw new RuntimeException("Selected replacement hotel listing not found.");
            }
            $roomId = (int) ($options['room_id'] ?? 0);
            $room = $roomId > 0 ? Database::first("SELECT * FROM hotel_rooms WHERE id = ?", [$roomId]) : null;
            $roomName = $room ? (string) $room['name'] : 'Standard Room';
            $hotelName = (string) $hotelListing['title'];
            $newServiceName = "{$hotelName} - {$roomName}";

            $newProviderUser = Database::first("SELECT * FROM users WHERE id = ?", [$newProviderId]);
            $pricePerNight = (float) ($room['price_per_night'] ?? $hotelListing['price']);
            $nights = max(1, (int) ($parent['nights'] ?? $packageNights));
            $newAllocated = round($pricePerNight * $nights, 2);

            // Price restriction: Replacement service price must not exceed original cancelled service price
            if ($newAllocated > $oldAllocated) {
                throw new RuntimeException("Replacement service price (₱" . number_format($newAllocated, 2) . ") cannot exceed original cancelled service price (₱" . number_format($oldAllocated, 2) . ").");
            }

            $checkInDate = $parent['check_in_date'] ?? $parent['booking_date'];
            $checkOutDate = date('Y-m-d', strtotime("{$checkInDate} +{$nights} days"));

            if ($roomId > 0) {
                $isBooked = Database::first("
                    SELECT id FROM bookings
                    WHERE room_id = ?
                      AND status NOT IN ('cancelled', 'refunded')
                      AND hotel_status NOT IN ('cancelled', 'refunded')
                      AND check_in_date < ?
                      AND check_out_date > ?
                    LIMIT 1
                ", [$roomId, $checkOutDate, $checkInDate]);
                if ($isBooked) {
                    throw new RuntimeException("Selected room '{$roomName}' is not available for the package dates ({$checkInDate} to {$checkOutDate}).");
                }
            }

            $newSubBookingId = Database::insert("
                INSERT INTO bookings (
                    listing_id, room_id, user_id, booking_date, check_in_date, check_out_date,
                    nights, guests, total_amount, deposit_amount, balance_amount,
                    payment_stage, hotel_status, status, notes, package_booking_ref,
                    package_overall_status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 'pending', 'pending', ?, ?, 'partially_confirmed', NOW(), NOW())
            ", [
                (int) $hotelListing['id'],
                $roomId,
                (int) $parent['user_id'],
                $parent['booking_date'],
                $checkInDate,
                $checkOutDate,
                $nights,
                (int) $parent['guests'],
                $newAllocated,
                $newAllocated,
                $parentIsPaid ? 'paid' : 'deposit_paid',
                json_encode([
                    'type' => 'package_sub_booking',
                    'service_type' => 'hotel',
                    'package_booking_ref' => $pkgRef,
                    'parent_booking_id' => $parentBookingId,
                    'hotel_name' => $hotelName,
                    'room_name' => $roomName,
                    'is_replacement' => 1,
                    'replaces_item_id' => $itemId,
                ]),
                $pkgRef
            ]);
        } elseif ($serviceType === 'vehicle') {
            $vehicle = Database::first("SELECT * FROM vehicles WHERE id = ?", [$newServiceId]);
            if (!$vehicle) {
                throw new RuntimeException("Selected replacement vehicle not found.");
            }
            $newProviderUser = Database::first("SELECT * FROM users WHERE id = ?", [$newProviderId]);
            $vehicleName = !empty($vehicle['name']) ? (string) $vehicle['name'] : (!empty($vehicle['make']) ? trim($vehicle['make'] . ' ' . ($vehicle['model'] ?? '')) : 'Vehicle Rental');
            $newServiceName = $vehicleName;
            $dailyRate = (float) ($vehicle['daily_rate'] ?? $vehicle['price_per_day'] ?? 0);
            $vehicleType = (string) ($vehicle['type'] ?? $vehicle['vehicle_type'] ?? 'Vehicle');
            $days = max(1, (int) ($parent['days_count'] ?? $packageDays));
            $newAllocated = round($dailyRate * $days, 2);

            // Price restriction: Replacement service price must not exceed original cancelled service price
            if ($newAllocated > $oldAllocated) {
                throw new RuntimeException("Replacement service price (₱" . number_format($newAllocated, 2) . ") cannot exceed original cancelled service price (₱" . number_format($oldAllocated, 2) . ").");
            }

            $pickupDate = (string) $parent['booking_date'];
            $returnDate = date('Y-m-d', strtotime("{$pickupDate} +{$days} days"));

            $vehicleConflict = Database::first("
                SELECT id FROM rental_requests
                WHERE vehicle_id = ?
                  AND status IN ('approved', 'confirmed', 'contacted', 'pending')
                  AND pickup_date < ?
                  AND DATE_ADD(pickup_date, INTERVAL COALESCE(rental_days, 1) DAY) > ?
                LIMIT 1
            ", [(int) $vehicle['id'], $returnDate, $pickupDate]);
            if ($vehicleConflict) {
                throw new RuntimeException("Selected vehicle '{$vehicleName}' is already reserved for the package rental dates ({$pickupDate} to {$returnDate}).");
            }

            $newRentalId = Database::insert("
                INSERT INTO rental_requests (
                    user_id, vehicle_id, vehicle_name, vehicle_type, location, pickup_date, rental_days,
                    total_amount, customer_name, customer_email, status, payment_status, package_booking_ref,
                    package_booking_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW(), NOW())
            ", [
                (int) $parent['user_id'],
                (int) $vehicle['id'],
                $vehicleName,
                $vehicleType,
                (string) ($vehicle['location'] ?? 'Designated Location'),
                $pickupDate,
                $days,
                $newAllocated,
                'Tourist',
                'tourist@guidemate.com',
                $replacementPaymentStatus,
                $pkgRef,
                $parentBookingId
            ]);
        } elseif ($serviceType === 'guide') {
            $guideUser = Database::first("SELECT * FROM users WHERE id = ? AND role = 'guide'", [$newProviderId]);
            if (!$guideUser) {
                throw new RuntimeException("Selected replacement guide not found.");
            }
            $newServiceName = 'Guided Tour by ' . (string) $guideUser['name'];
        } elseif (in_array($serviceType, ['things_to_do', 'activity', 'attraction', 'attractions'], true)) {
            $listing = Database::first("SELECT * FROM listings WHERE id = ?", [$newServiceId]);
            if (!$listing) {
                throw new RuntimeException("Selected replacement activity/attraction listing not found.");
            }
            $newServiceName = (string) $listing['title'];
            $price = (float) $listing['price'];
            $guests = max(1, (int) ($parent['guests'] ?? 1));
            $newAllocated = round($price * $guests, 2);

            if ($newAllocated > $oldAllocated) {
                throw new RuntimeException("Replacement service price (₱" . number_format($newAllocated, 2) . ") cannot exceed original cancelled service price (₱" . number_format($oldAllocated, 2) . ").");
            }
        }

        $priceDiff = round($newAllocated - $oldAllocated, 2);

        // Insert new replacement item into package_booking_items (keeping original cancelled item separate for history)
        $newProviderRole = match ($serviceType) {
            'hotel' => 'hotel_provider',
            'vehicle' => 'rental_provider',
            'guide' => 'guide',
            default => 'guide',
        };

        $newPackageItemId = Database::insert("
            INSERT INTO package_booking_items (
                package_booking_ref, parent_booking_id, package_id, provider_id, provider_role,
                service_type, service_name, sub_booking_id, rental_request_id,
                allocated_amount, original_allocated_amount, status, payment_status,
                is_replacement, replaces_item_id, cancellation_reason, responded_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 1, ?, NULL, NULL, NOW(), NOW())
        ", [
            $pkgRef,
            $parentBookingId,
            (int) ($item['package_id'] ?? 0),
            $newProviderId,
            $newProviderRole,
            $serviceType,
            $newServiceName,
            $newSubBookingId,
            $newRentalId,
            $newAllocated,
            $oldAllocated,
            $replacementPaymentStatus,
            $itemId
        ]);

        // Payment reallocation & Surplus Refund
        if ($parentIsPaid) {
            Payment::create($newSubBookingId ?: $parentBookingId, $newAllocated, 'package_reallocation', 'paid');

            // If replacement costs less than original cancelled service, refund surplus difference
            if ($newAllocated < $oldAllocated) {
                $surplus = round($oldAllocated - $newAllocated, 2);
                Database::run("
                    UPDATE bookings
                    SET total_amount = total_amount - ?,
                        refund_amount = COALESCE(refund_amount, 0.00) + ?,
                        refund_status = CASE WHEN refund_status = 'refunded' THEN 'refunded' ELSE 'partial_refund' END,
                        updated_at = NOW()
                    WHERE id = ?
                ", [$surplus, $surplus, $parentBookingId]);

                Database::insert("
                    INSERT INTO payments (booking_id, amount, method, status, reference, created_at)
                    VALUES (?, ?, 'refund', 'refunded', ?, NOW())
                ", [$parentBookingId, -$surplus, 'REF-SURPLUS-' . strtoupper(bin2hex(random_bytes(4)))]);

                $touristUser = User::find((int) $parent['user_id']);
                if ($touristUser && !empty($touristUser['email'])) {
                    $surplusMsg = "Your replacement service cost ₱" . number_format($newAllocated, 2) . " which is ₱" . number_format($surplus, 2) . " less than the original service. The surplus difference of ₱" . number_format($surplus, 2) . " has been automatically refunded to you.";
                    NotificationService::send($touristUser['email'], "Surplus Refund for Travel Package #{$pkgRef}", $surplusMsg);
                    self::createAutomatedMessage((int) $parent['user_id'], "Surplus Refund (#{$pkgRef})", $surplusMsg);
                }
            }
        }

        // Update parent booking notes JSON if present
        $rawNotes = (string) ($parent['notes'] ?? '');
        $notesArr = json_decode($rawNotes, true);
        if (!is_array($notesArr)) {
            $notesArr = [];
        }
        if (!isset($notesArr['package_details']) || !is_array($notesArr['package_details'])) {
            $notesArr['package_details'] = [];
        }

        if ($serviceType === 'hotel') {
            $notesArr['package_details']['accommodation_replacement'] = [
                'booking_id' => $newSubBookingId,
                'package_item_id' => $newPackageItemId,
                'hotel_id' => (int) $hotelListing['id'],
                'hotel_name' => $hotelName,
                'room_id' => $roomId,
                'room_name' => $roomName,
                'room_type' => (string) ($room['room_type'] ?? 'Standard'),
                'price_per_night' => $pricePerNight,
                'pricing' => $newAllocated,
                'allocated_amount' => $newAllocated,
                'nights' => $nights,
                'check_in_date' => $parent['check_in_date'] ?? $parent['booking_date'],
                'check_out_date' => $parent['check_out_date'] ?? $parent['booking_date'],
                'provider_id' => $newProviderId,
                'provider_name' => (string) ($newProviderUser['name'] ?? 'Hotel Provider'),
                'provider_email' => (string) ($newProviderUser['email'] ?? ''),
                'provider_phone' => (string) ($newProviderUser['phone'] ?? ''),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } elseif ($serviceType === 'vehicle') {
            $notesArr['package_details']['rental_replacement'] = [
                'request_id' => $newRentalId,
                'package_item_id' => $newPackageItemId,
                'vehicle_id' => (int) $vehicle['id'],
                'vehicle_name' => $vehicleName,
                'model' => (string) ($vehicle['model'] ?? $vehicleName),
                'vehicle_type' => $vehicleType,
                'capacity' => (int) ($vehicle['capacity'] ?? $vehicle['seats'] ?? 4),
                'location' => (string) ($vehicle['location'] ?? 'Designated Location'),
                'pickup_date' => (string) $parent['booking_date'],
                'pickup_time' => (string) (!empty($parent['booking_time']) ? $parent['booking_time'] : '09:00 AM'),
                'return_date' => $returnDate,
                'return_time' => (string) (!empty($parent['booking_end_time']) ? $parent['booking_end_time'] : '05:00 PM'),
                'rental_days' => $days,
                'daily_rate' => $dailyRate,
                'pricing' => $newAllocated,
                'allocated_amount' => $newAllocated,
                'provider_id' => $newProviderId,
                'provider_name' => (string) ($newProviderUser['name'] ?? 'Rental Partner'),
                'provider_email' => (string) ($newProviderUser['email'] ?? ''),
                'provider_phone' => (string) ($newProviderUser['phone'] ?? ''),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        Database::run("UPDATE bookings SET notes = ? WHERE id = ?", [json_encode($notesArr), $parentBookingId]);

        // Re-evaluate overall package status
        $syncRes = self::synchronizeOverallPackageStatus($pkgRef, $serviceType, 'confirm');

        // Audit log
        self::logAudit(
            $pkgRef,
            $parentBookingId,
            $itemId,
            'service_replaced',
            $actorId,
            'guide',
            "Host replaced {$serviceType} service with '{$newServiceName}' (Provider #{$newProviderId}). Amount adjusted: ₱" . number_format($priceDiff, 2) . ".",
            json_encode(['old_allocated' => $oldAllocated, 'new_allocated' => $newAllocated, 'price_diff' => $priceDiff])
        );

        // Notify traveler
        $touristUser = User::find((int) $parent['user_id']);
        if ($touristUser && !empty($touristUser['email'])) {
            $msg = "Your travel package (#{$pkgRef}) replacement for {$serviceType} service has been confirmed: '{$newServiceName}'. Updated package total: ₱" . number_format((float) ($parent['total_amount'] + $priceDiff), 2);
            NotificationService::send($touristUser['email'], "Service Replaced: Travel Package #{$pkgRef}", $msg);
            self::createAutomatedMessage((int) $parent['user_id'], "Service Replaced (#{$pkgRef})", $msg);
        }

        // Notify new service provider
        if ($newProviderId > 0 && $newProviderId !== (int) $parent['user_id']) {
            $newProvUser = User::find($newProviderId);
            if ($newProvUser && !empty($newProvUser['email'])) {
                $msg = "You have been selected as the replacement {$serviceType} provider for Travel Package #{$pkgRef}. Service: '{$newServiceName}'. Please check your dashboard.";
                NotificationService::send($newProvUser['email'], "New Package Booking Assignment: #{$pkgRef}", $msg);
                self::createAutomatedMessage($newProviderId, "Package Assignment (#{$pkgRef})", $msg);
            }
        }

        return [
            'success' => true,
            'package_booking_ref' => $pkgRef,
            'item_id' => $itemId,
            'service_name' => $newServiceName,
            'new_allocated_amount' => $newAllocated,
            'price_difference' => $priceDiff,
            'overall_status' => $syncRes['overall_status'] ?? 'confirmed',
            'message' => "Cancelled {$serviceType} service has been successfully replaced with {$newServiceName}.",
        ];
    }

    /**
     * Option B: Reschedule a cancelled service to an alternative date/time.
     */
    public static function rescheduleCancelledService(
        string $pkgRef,
        int $itemId,
        string $newDate,
        ?string $newTime = null,
        ?string $notes = null,
        int $actorId = 0
    ): array {
        self::ensureTables();

        $item = Database::first("SELECT * FROM package_booking_items WHERE id = ? AND package_booking_ref = ?", [$itemId, $pkgRef]);
        if (!$item) {
            throw new RuntimeException("Service item not found.");
        }

        if ($item['status'] !== 'cancelled') {
            throw new RuntimeException("Only cancelled service items can be rescheduled.");
        }

        $parent = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
        if (!$parent) {
            throw new RuntimeException("Package booking not found.");
        }

        if (empty($newDate) || strtotime($newDate) < strtotime('today')) {
            throw new RuntimeException("Please select a valid future date.");
        }

        $parentBookingId = (int) $parent['id'];
        $serviceType = (string) $item['service_type'];

        // Validate selected schedule against affected provider's availability
        if ($serviceType === 'hotel' && !empty($item['sub_booking_id'])) {
            $sub = Database::first("SELECT * FROM bookings WHERE id = ?", [(int) $item['sub_booking_id']]);
            if ($sub && !empty($sub['room_id'])) {
                $nights = max(1, (int) round((strtotime($sub['check_out_date'] ?? $newDate) - strtotime($sub['check_in_date'] ?? $newDate)) / 86400));
                $newCheckOut = date('Y-m-d', strtotime($newDate . " + {$nights} days"));

                $overlap = Database::first("
                    SELECT id FROM bookings
                    WHERE room_id = ?
                      AND id != ?
                      AND status NOT IN ('cancelled', 'refunded')
                      AND hotel_status NOT IN ('cancelled', 'refunded')
                      AND check_in_date < ?
                      AND check_out_date > ?
                    LIMIT 1
                ", [(int) $sub['room_id'], (int) $sub['id'], $newCheckOut, $newDate]);

                if ($overlap) {
                    throw new RuntimeException("The hotel provider is not available on {$newDate}. Please choose another date.");
                }
            }
        } elseif ($serviceType === 'vehicle' && !empty($item['rental_request_id'])) {
            $req = Database::first("SELECT * FROM rental_requests WHERE id = ?", [(int) $item['rental_request_id']]);
            if ($req && !empty($req['vehicle_id'])) {
                $days = max(1, (int) round((strtotime($req['return_date'] ?? $newDate) - strtotime($req['pickup_date'] ?? $newDate)) / 86400));
                $newReturn = date('Y-m-d', strtotime($newDate . " + {$days} days"));

                $overlap = Database::first("
                    SELECT id FROM rental_requests
                    WHERE vehicle_id = ?
                      AND id != ?
                      AND status IN ('approved', 'confirmed', 'contacted', 'pending')
                      AND pickup_date < ?
                      AND return_date > ?
                    LIMIT 1
                ", [(int) $req['vehicle_id'], (int) $req['id'], $newReturn, $newDate]);

                if ($overlap) {
                    throw new RuntimeException("The rental vehicle is unavailable on {$newDate}. Please select another date.");
                }
            }
        }

        Database::run("
            UPDATE package_booking_items
            SET status = 'rescheduled',
                rescheduled_date = ?,
                rescheduled_time = ?,
                rescheduled_notes = ?,
                cancellation_reason = NULL,
                responded_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ", [$newDate, $newTime, $notes, $itemId]);

        if (!empty($item['sub_booking_id'])) {
            $nights = max(1, (int) ($parent['nights'] ?? 1));
            $newCheckOut = date('Y-m-d', strtotime($newDate . " + {$nights} days"));
            Database::run("
                UPDATE bookings
                SET check_in_date = ?, check_out_date = ?, status = 'confirmed', hotel_status = 'confirmed', updated_at = NOW()
                WHERE id = ?
            ", [$newDate, $newCheckOut, (int) $item['sub_booking_id']]);
        }

        if (!empty($item['rental_request_id'])) {
            Database::run("
                UPDATE rental_requests
                SET pickup_date = ?, status = 'approved', updated_at = NOW()
                WHERE id = ?
            ", [$newDate, (int) $item['rental_request_id']]);
        }

        $syncRes = self::synchronizeOverallPackageStatus($pkgRef, $serviceType, 'reschedule');

        self::logAudit(
            $pkgRef,
            $parentBookingId,
            $itemId,
            'service_rescheduled',
            $actorId,
            'guide',
            "Rescheduled {$serviceType} service to {$newDate}" . ($newTime ? " at {$newTime}" : "") . ".",
            $notes
        );

        $providerId = (int) $item['provider_id'];
        if ($providerId > 0) {
            $provUser = User::find($providerId);
            if ($provUser && !empty($provUser['email'])) {
                $msg = "A rescheduling request has been confirmed for your {$serviceType} service in package #{$pkgRef}. New Date: {$newDate}.";
                NotificationService::send($provUser['email'], "Service Rescheduled: Package #{$pkgRef}", $msg);
            }
            self::createAutomatedMessage($providerId, "Service Rescheduled (#{$pkgRef})", "Service rescheduled to {$newDate}.");
        }

        // Notify traveler
        $touristUser = User::find((int) $parent['user_id']);
        if ($touristUser && !empty($touristUser['email'])) {
            $msg = "Your host has rescheduled the {$serviceType} service ('{$item['service_name']}') in package #{$pkgRef} to " . date('M j, Y', strtotime($newDate)) . ". Other confirmed bookings remain active.";
            NotificationService::send($touristUser['email'], "Service Rescheduled: Package #{$pkgRef}", $msg);
            self::createAutomatedMessage((int) $parent['user_id'], "Service Rescheduled (#{$pkgRef})", $msg);
        }

        return [
            'success' => true,
            'package_booking_ref' => $pkgRef,
            'item_id' => $itemId,
            'rescheduled_date' => $newDate,
            'rescheduled_time' => $newTime,
            'overall_status' => $syncRes['overall_status'] ?? 'confirmed',
            'message' => "Successfully rescheduled {$serviceType} service to " . date('M j, Y', strtotime($newDate)) . ".",
        ];
    }

    /**
     * Reschedule an entire travel package by the host (Section 2).
     * Updates travel date across parent booking, hotel sub-bookings, and rental requests.
     * Sets Package Status: Pending.
     * Payment Status: Paid (if already paid - never reset or invalidated).
     */
    public static function rescheduleEntirePackage(
        string $pkgRef,
        string $newDate,
        ?string $newTime = null,
        int $actorId = 0,
        string $notes = 'Rescheduled by host',
        ?int $newDurationDays = null
    ): array {
        self::ensureTables();

        $parent = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
        if (!$parent) {
            throw new RuntimeException("Package booking not found.");
        }

        if (strtotime($newDate) === false || strtotime($newDate) < strtotime('today')) {
            throw new RuntimeException("Please select a valid future date.");
        }

        $parentBookingId = (int) $parent['id'];
        $oldDate = (string) $parent['booking_date'];

        $dur = self::derivePackageDurations($parent['listing_id'] ?? null, $newDurationDays);
        $nights = $dur['hotel_nights'];
        $rentalDays = $dur['rental_days'];

        $hotelSub = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? AND room_id IS NOT NULL LIMIT 1", [$pkgRef]);
        $newCheckOut = date('Y-m-d', strtotime($newDate . " + {$nights} days"));

        $rentalSub = Database::first("SELECT * FROM rental_requests WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
        $newReturnDate = date('Y-m-d', strtotime($newDate . " + {$rentalDays} days"));

        // Recalculate amounts if new duration specified
        if ($newDurationDays !== null && (int) $newDurationDays > 0) {
            if ($hotelSub && !empty($hotelSub['room_id'])) {
                $rm = Database::first("SELECT price_per_night FROM hotel_rooms WHERE id = ?", [(int) $hotelSub['room_id']]);
                $roomRate = (float) ($rm['price_per_night'] ?? 0);
                if ($roomRate > 0) {
                    $newHotelTotal = round($roomRate * $nights, 2);
                    Database::run("UPDATE bookings SET total_amount = ?, deposit_amount = ? WHERE id = ?", [$newHotelTotal, $newHotelTotal, (int) $hotelSub['id']]);
                    Database::run("UPDATE package_booking_items SET allocated_amount = ? WHERE sub_booking_id = ?", [$newHotelTotal, (int) $hotelSub['id']]);
                }
            }

            if ($rentalSub && !empty($rentalSub['vehicle_id'])) {
                $vh = Database::first("SELECT COALESCE(daily_rate, price_per_day, 0) AS daily_rate FROM vehicles WHERE id = ?", [(int) $rentalSub['vehicle_id']]);
                $vDaily = (float) ($vh['daily_rate'] ?? 0);
                if ($vDaily > 0) {
                    $newRentalTotal = round($vDaily * $rentalDays, 2);
                    Database::run("UPDATE rental_requests SET total_amount = ?, rental_days = ? WHERE id = ?", [$newRentalTotal, $rentalDays, (int) $rentalSub['id']]);
                    Database::run("UPDATE package_booking_items SET allocated_amount = ? WHERE rental_request_id = ?", [$newRentalTotal, (int) $rentalSub['id']]);
                }
            }
        }

        // Validate provider availability for the new dates
        // 1. Hotel Room availability
        if ($hotelSub && !empty($hotelSub['room_id'])) {
            $roomId = (int) $hotelSub['room_id'];
            $hotelConflict = Database::first("
                SELECT id FROM bookings
                WHERE room_id = ?
                  AND id != ?
                  AND (package_booking_ref IS NULL OR package_booking_ref != ?)
                  AND status NOT IN ('cancelled', 'refunded')
                  AND check_in_date < ?
                  AND check_out_date > ?
            ", [$roomId, (int) $hotelSub['id'], $pkgRef, $newCheckOut, $newDate]);
            if ($hotelConflict) {
                $r = Database::first("SELECT name FROM hotel_rooms WHERE id = ?", [$roomId]);
                $rName = (string) ($r['name'] ?? 'Hotel room');
                throw new RuntimeException("Selected room '{$rName}' is not available for the rescheduled dates ({$newDate} to {$newCheckOut}). Please choose a different date.");
            }
        }

        // 2. Vehicle Rental availability
        if ($rentalSub && !empty($rentalSub['vehicle_id'])) {
            $vehicleId = (int) $rentalSub['vehicle_id'];
            $vehicleConflict = Database::first("
                SELECT id FROM rental_requests
                WHERE vehicle_id = ?
                  AND id != ?
                  AND (package_booking_ref IS NULL OR package_booking_ref != ?)
                  AND status IN ('approved', 'confirmed', 'contacted', 'pending')
                  AND pickup_date < ?
                  AND DATE_ADD(pickup_date, INTERVAL COALESCE(rental_days, 1) DAY) > ?
            ", [$vehicleId, (int) $rentalSub['id'], $pkgRef, $newReturnDate, $newDate]);
            if ($vehicleConflict) {
                $v = Database::first("SELECT name, make, model FROM vehicles WHERE id = ?", [$vehicleId]);
                $vName = $v['name'] ?? ($v ? trim($v['make'] . ' ' . $v['model']) : 'Selected vehicle');
                throw new RuntimeException("Vehicle '{$vName}' is already reserved for the selected date. Please choose an alternative date.");
            }
        }

        // 3. Tour Guide availability
        $guideListingId = (int) ($parent['listing_id'] ?? 0);
        $guideUserId = (int) ($parent['guide_id'] ?? 0);
        if ($guideUserId <= 0 && $guideListingId > 0) {
            $gl = Database::first("SELECT user_id FROM listings WHERE id = ?", [$guideListingId]);
            $guideUserId = (int) ($gl['user_id'] ?? 0);
        }
        if ($guideUserId > 0) {
            $guideConflict = Database::first("
                SELECT b.id FROM bookings b
                JOIN listings l ON l.id = b.listing_id
                WHERE (l.user_id = ? OR b.listing_id = ?)
                  AND b.booking_date = ?
                  AND (b.package_booking_ref IS NULL OR b.package_booking_ref != ?)
                  AND b.id != ?
                  AND b.status IN ('pending', 'confirmed')
            ", [$guideUserId, $guideListingId, $newDate, $pkgRef, $parentBookingId]);
            if ($guideConflict) {
                throw new RuntimeException("The tour guide is unavailable on " . date('F j, Y', strtotime($newDate)) . ". Please select an alternative date.");
            }
        }

        // 1. Update parent booking: Status Pending, Payment remains Paid (never Refunded)
        $parentWasPaid = Payment::isPaid($parentBookingId) || in_array($parent['payment_stage'] ?? '', ['paid', 'deposit_paid', 'full', 'settled'], true);
        $paymentStage = $parentWasPaid ? 'paid' : ($parent['payment_stage'] ?? 'unpaid');

        $timeClause = $newTime !== null ? ", booking_time = '{$newTime}'" : "";
        Database::run("
            UPDATE bookings
            SET booking_date = ?,
                check_in_date = ?,
                check_out_date = ?,
                nights = ?,
                status = 'pending',
                package_overall_status = 'pending',
                refund_status = NULL,
                refund_amount = 0.00,
                cancellation_reason = NULL,
                cancelled_at = NULL,
                payment_stage = ?,
                updated_at = NOW()
                {$timeClause}
            WHERE id = ?
        ", [$newDate, $newDate, $newCheckOut, $nights, $paymentStage, $parentBookingId]);

        if ($parentWasPaid) {
            Database::run("
                UPDATE payments
                SET status = 'paid'
                WHERE booking_id = ? AND method != 'refund'
            ", [$parentBookingId]);
        }

        // Update parent booking notes JSON if present
        $rawNotes = (string) ($parent['notes'] ?? '');
        $notesArr = json_decode($rawNotes, true);
        if (is_array($notesArr)) {
            if (isset($notesArr['package_details'])) {
                $notesArr['package_details']['travel_date'] = $newDate;
                $notesArr['package_details']['booking_date'] = $newDate;
                $notesArr['package_details']['duration_days'] = $rentalDays;
                $notesArr['package_details']['duration_nights'] = $nights;
                if (isset($notesArr['package_details']['accommodation'])) {
                    $notesArr['package_details']['accommodation']['check_in_date'] = $newDate;
                    $notesArr['package_details']['accommodation']['check_out_date'] = $newCheckOut;
                    $notesArr['package_details']['accommodation']['nights'] = $nights;
                }
                if (isset($notesArr['package_details']['rental'])) {
                    $notesArr['package_details']['rental']['pickup_date'] = $newDate;
                    $notesArr['package_details']['rental']['rental_days'] = $rentalDays;
                    $notesArr['package_details']['rental']['duration_days'] = $rentalDays;
                    $notesArr['package_details']['rental']['return_date'] = $newReturnDate;
                }
            }
            Database::run("UPDATE bookings SET notes = ? WHERE id = ?", [json_encode($notesArr), $parentBookingId]);
        }

        // 2. Update hotel sub-bookings:
        Database::run("
            UPDATE bookings
            SET booking_date = ?,
                check_in_date = ?,
                check_out_date = ?,
                nights = ?,
                status = 'pending',
                hotel_status = 'pending',
                refund_status = NULL,
                refund_amount = 0.00,
                cancellation_reason = NULL,
                cancelled_at = NULL,
                payment_stage = ?,
                updated_at = NOW()
            WHERE package_booking_ref = ? AND id != ?
        ", [$newDate, $newDate, $newCheckOut, $nights, $paymentStage, $pkgRef, $parentBookingId]);

        if ($hotelSub && $parentWasPaid) {
            Database::run("
                UPDATE payments
                SET status = 'paid'
                WHERE booking_id = ? AND method != 'refund'
            ", [(int) $hotelSub['id']]);
        }

        // 3. Update rental requests:
        Database::run("
            UPDATE rental_requests
            SET pickup_date = ?,
                rental_days = ?,
                status = 'pending',
                payment_status = ?,
                refund_status = NULL,
                refund_amount = 0.00,
                cancellation_reason = NULL,
                cancelled_at = NULL,
                updated_at = NOW()
            WHERE package_booking_ref = ? OR package_booking_id = ?
        ", [$newDate, $rentalDays, $parentWasPaid ? 'paid' : 'unpaid', $pkgRef, $parentBookingId]);

        // 4. Update package_booking_items:
        Database::run("
            UPDATE package_booking_items
            SET status = 'pending',
                payment_status = ?,
                refund_status = NULL,
                refund_amount = 0.00,
                cancellation_reason = NULL,
                rescheduled_date = ?,
                rescheduled_time = ?,
                rescheduled_notes = ?,
                updated_at = NOW()
            WHERE package_booking_ref = ?
        ", [$parentWasPaid ? 'paid' : 'unpaid', $newDate, $newTime, $notes, $pkgRef]);

        // 5. Audit log
        self::logAudit(
            $pkgRef,
            $parentBookingId,
            null,
            'package_rescheduled',
            $actorId,
            'guide',
            "Host rescheduled travel package travel date from {$oldDate} to {$newDate}. Overall package status set to Pending. Original payment preserved.",
            $notes
        );

        // 6. Notify traveler and all providers
        $packageTitle = (string) ($parent['listing_title'] ?? '');
        if ($packageTitle === '' && !empty($parent['listing_id'])) {
            $l = Listing::find((int) $parent['listing_id']);
            $packageTitle = (string) ($l['title'] ?? 'Travel Package');
        }
        if ($packageTitle === '') {
            $packageTitle = 'Travel Package';
        }

        $hostGuideId = (int) ($parent['guide_id'] ?? 0);
        if ($hostGuideId <= 0 && !empty($parent['listing_id'])) {
            $l = Listing::find((int) $parent['listing_id']);
            $hostGuideId = (int) ($l['user_id'] ?? 0);
        }

        // Notify traveler
        $touristUser = User::find((int) $parent['user_id']);
        if ($touristUser && !empty($touristUser['email'])) {
            $msg = "Your host has rescheduled the travel date for '{$packageTitle}' (#{$pkgRef}) to " . date('F j, Y', strtotime($newDate)) . ". Status is now Pending while providers re-confirm. Your existing payment remains active and fully honored.";
            NotificationService::send($touristUser['email'], "Travel Package Rescheduled: {$packageTitle} (#{$pkgRef})", $msg);
            self::createAutomatedMessage((int) $parent['user_id'], "Travel Package Rescheduled", $msg);
        }

        // Notify assigned guide
        if ($hostGuideId > 0) {
            $guideUser = User::find($hostGuideId);
            if ($guideUser && !empty($guideUser['email'])) {
                $msg = "Travel Package #{$pkgRef} ('{$packageTitle}') schedule has been updated to " . date('F j, Y', strtotime($newDate)) . ". Package status is Pending awaiting provider reconfirmation.";
                NotificationService::send($guideUser['email'], "Schedule Update: Package #{$pkgRef}", $msg);
                self::createAutomatedMessage($hostGuideId, "Schedule Update (#{$pkgRef})", $msg);
            }
        }

        // Notify hotel partner and vehicle provider with tailored reconfirmation request
        $items = Database::all("SELECT * FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef]);
        foreach ($items as $it) {
            $provId = (int) $it['provider_id'];
            if ($provId > 0 && $provId !== $hostGuideId) {
                $pUser = User::find($provId);
                if ($pUser && !empty($pUser['email'])) {
                    if ($it['service_type'] === 'hotel') {
                        $msg = "Travel Package #{$pkgRef} ('{$packageTitle}') has been rescheduled to new check-in date: " . date('F j, Y', strtotime($newDate)) . " (Check-out: " . date('F j, Y', strtotime($newCheckOut)) . "). Please review and reconfirm your room reservation.";
                    } elseif ($it['service_type'] === 'vehicle') {
                        $msg = "Travel Package #{$pkgRef} ('{$packageTitle}') has been rescheduled to new vehicle pickup date: " . date('F j, Y', strtotime($newDate)) . ". Please review and reconfirm your rental reservation.";
                    } else {
                        $msg = "Travel Package #{$pkgRef} ('{$packageTitle}') has been rescheduled to " . date('F j, Y', strtotime($newDate)) . " by the host. Please review and confirm your service for the updated date.";
                    }
                    NotificationService::send($pUser['email'], "Schedule Update: Package #{$pkgRef}", $msg);
                    self::createAutomatedMessage($provId, "Schedule Update (#{$pkgRef})", $msg);
                }
            }
        }

        return [
            'success' => true,
            'message' => "Travel Package rescheduled to " . date('F j, Y', strtotime($newDate)) . ". Status set to Pending. Original payment preserved.",
            'new_date' => $newDate,
            'package_status' => 'pending',
        ];
    }

    /**
     * Option C: Continue travel package without the cancelled service, recalculating cost and issuing refund.
     */
    public static function continueWithoutService(
        string $pkgRef,
        int $itemId,
        int $actorId = 0,
        ?string $reason = null
    ): array {
        self::ensureTables();

        $item = Database::first("SELECT * FROM package_booking_items WHERE id = ? AND package_booking_ref = ?", [$itemId, $pkgRef]);
        if (!$item) {
            throw new RuntimeException("Service item not found.");
        }

        if ($item['status'] !== 'cancelled') {
            throw new RuntimeException("Only cancelled service items can be dropped.");
        }

        $serviceType = (string) $item['service_type'];
        if ($serviceType === 'guide') {
            throw new RuntimeException("The tour guide host service is essential to completing this travel package itinerary. Please select Option D to cancel the entire package or coordinate with your guide.");
        }

        $parent = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
        if (!$parent) {
            throw new RuntimeException("Package booking not found.");
        }

        $parentBookingId = (int) $parent['id'];
        $serviceType = (string) $item['service_type'];
        $serviceName = (string) $item['service_name'];
        $allocatedAmount = (float) $item['allocated_amount'];
        $currentTotal = (float) $parent['total_amount'];
        $newTotal = max(0.00, round($currentTotal - $allocatedAmount, 2));

        Database::run("
            UPDATE package_booking_items
            SET status = 'removed',
                cancellation_reason = 'Continued package without this service',
                updated_at = NOW()
            WHERE id = ?
        ", [$itemId]);

        Database::run("
            UPDATE bookings
            SET total_amount = ?,
                refund_amount = COALESCE(refund_amount, 0) + ?,
                refund_status = 'partially_refunded',
                updated_at = NOW()
            WHERE id = ?
        ", [$newTotal, $allocatedAmount, $parentBookingId]);

        $syncRes = self::synchronizeOverallPackageStatus($pkgRef, $serviceType, 'remove');

        self::logAudit(
            $pkgRef,
            $parentBookingId,
            $itemId,
            'service_removed',
            $actorId,
            'traveler',
            "Continued package without {$serviceType} ('{$serviceName}'). Refund of ₱" . number_format($allocatedAmount, 2) . " credited. New package total: ₱" . number_format($newTotal, 2) . ".",
            $reason
        );

        $packageTitle = (string) ($parent['listing_title'] ?? '');
        if ($packageTitle === '' && !empty($parent['listing_id'])) {
            $l = Listing::find((int) $parent['listing_id']);
            $packageTitle = (string) ($l['title'] ?? 'Travel Package');
        }
        if ($packageTitle === '') {
            $packageTitle = 'Travel Package';
        }

        $touristUser = User::find((int) $parent['user_id']);
        if ($touristUser && !empty($touristUser['email'])) {
            $msg = "You have chosen to continue Travel Package '{$packageTitle}' (#{$pkgRef}) without {$serviceType} ('{$serviceName}'). A refund of ₱" . number_format($allocatedAmount, 2) . " has been issued to your payment method. Your updated package total is ₱" . number_format($newTotal, 2) . ".";
            NotificationService::send($touristUser['email'], "Package Updated: {$serviceName} Removed (#{$pkgRef})", $msg);
            self::createAutomatedMessage((int) $parent['user_id'], "Package Updated (#{$pkgRef})", $msg);
        }

        // Notify host
        $hostGuideId = (int) ($parent['guide_id'] ?? 0);
        if ($hostGuideId <= 0 && !empty($parent['listing_id'])) {
            $l = Listing::find((int) $parent['listing_id']);
            $hostGuideId = (int) ($l['user_id'] ?? 0);
        }

        if ($hostGuideId > 0) {
            $guideUser = User::find($hostGuideId);
            if ($guideUser && !empty($guideUser['email'])) {
                $msg = "Traveler has opted to continue Travel Package #{$pkgRef} ('{$packageTitle}') without {$serviceType} ('{$serviceName}'). The remaining itinerary and confirmed bookings will proceed as planned.";
                NotificationService::send($guideUser['email'], "Package Itinerary Update: #{$pkgRef}", $msg);
                self::createAutomatedMessage($hostGuideId, "Package Itinerary Update", $msg);
            }
        }

        // Notify affected provider
        $provId = (int) $item['provider_id'];
        if ($provId > 0 && $provId !== $hostGuideId) {
            $pUser = User::find($provId);
            if ($pUser && !empty($pUser['email'])) {
                NotificationService::send($pUser['email'], "Service Removed from Package #{$pkgRef}", "Your service '{$serviceName}' in Travel Package #{$pkgRef} has been removed per traveler decision.");
            }
        }

        return [
            'success' => true,
            'package_booking_ref' => $pkgRef,
            'removed_item_id' => $itemId,
            'removed_service_name' => $serviceName,
            'refund_amount' => $allocatedAmount,
            'new_total_amount' => $newTotal,
            'overall_status' => $syncRes['overall_status'] ?? 'confirmed',
            'message' => "Removed {$serviceName} from package. ₱" . number_format($allocatedAmount, 2) . " has been refunded, and your package will continue with the remaining confirmed services.",
        ];
    }

    /**
     * Option D: Calculate consequences, remaining bookings, and full refund for entire package cancellation.
     */
    public static function calculateEntirePackageCancellationSummary(string $pkgRef): array
    {
        self::ensureTables();

        $items = Database::all("
            SELECT pbi.*, u.name AS provider_name
            FROM package_booking_items pbi
            LEFT JOIN users u ON u.id = pbi.provider_id
            WHERE pbi.package_booking_ref = ?
        ", [$pkgRef]);

        $parent = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
        if (!$parent) {
            throw new RuntimeException("Package booking not found.");
        }

        $activeServices = [];
        $cancelledServices = [];
        foreach ($items as $it) {
            if ($it['status'] === 'cancelled') {
                $cancelledServices[] = [
                    'id' => (int) $it['id'],
                    'service_type' => $it['service_type'],
                    'service_name' => $it['service_name'],
                    'provider_name' => $it['provider_name'] ?? 'Provider',
                    'status' => 'cancelled',
                    'cancellation_reason' => $it['cancellation_reason'] ?? 'Provider unavailable',
                    'allocated_amount' => (float) $it['allocated_amount'],
                ];
            } elseif ($it['status'] !== 'removed') {
                $activeServices[] = [
                    'id' => (int) $it['id'],
                    'service_type' => $it['service_type'],
                    'service_name' => $it['service_name'],
                    'provider_name' => $it['provider_name'] ?? 'Provider',
                    'status' => $it['status'],
                    'allocated_amount' => (float) $it['allocated_amount'],
                ];
            }
        }

        $totalAmount = (float) $parent['total_amount'];
        $alreadyRefunded = (float) ($parent['refund_amount'] ?? 0);
        $refundPayable = max(0.00, round($totalAmount, 2));

        return [
            'package_booking_ref' => $pkgRef,
            'package_title' => (string) ($parent['listing_title'] ?? 'Travel Package'),
            'total_package_amount' => $totalAmount,
            'refund_amount' => $refundPayable,
            'already_refunded' => $alreadyRefunded,
            'active_services' => $activeServices,
            'cancelled_services' => $cancelledServices,
            'active_count' => count($activeServices),
            'consequences' => [
                'All remaining active provider bookings (hotels, vehicles, guide) will be terminated immediately.',
                'A full refund of ₱' . number_format($refundPayable, 2) . ' will be processed and returned to your original payment method.',
                'All participating service providers will be notified of the cancellation.',
                'This action cannot be undone once confirmed.',
            ],
        ];
    }

    /**
     * Option D: Cancel the entire travel package with full refund and provider notifications.
     */
    public static function cancelEntirePackage(
        string $pkgRef,
        int $actorId = 0,
        string $reason = 'Cancelled by traveler following service disruption',
        bool $isConfirmed = false
    ): array {
        self::ensureTables();

        $parent = Database::first("SELECT * FROM bookings WHERE package_booking_ref = ? LIMIT 1", [$pkgRef]);
        if (!$parent) {
            throw new RuntimeException("Package booking not found.");
        }

        $parentBookingId = (int) $parent['id'];
        $items = Database::all("SELECT * FROM package_booking_items WHERE package_booking_ref = ?", [$pkgRef]);

        // 1. Cancel all items
        Database::run("
            UPDATE package_booking_items
            SET status = 'cancelled',
                cancellation_reason = COALESCE(cancellation_reason, ?),
                refund_amount = allocated_amount,
                refund_status = 'refunded',
                payment_status = 'refunded',
                responded_at = COALESCE(responded_at, NOW()),
                updated_at = NOW()
            WHERE package_booking_ref = ?
        ", [$reason, $pkgRef]);

        // 2. Process all hotel sub-bookings and balance due refunds
        $subBookings = Database::all("SELECT * FROM bookings WHERE package_booking_ref = ? AND id != ?", [$pkgRef, $parentBookingId]);
        foreach ($items as $it) {
            if (!empty($it['sub_booking_id']) && (int) $it['sub_booking_id'] !== $parentBookingId) {
                $found = false;
                foreach ($subBookings as $sb) {
                    if ((int) $sb['id'] === (int) $it['sub_booking_id']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $extraSub = Database::first("SELECT * FROM bookings WHERE id = ?", [(int) $it['sub_booking_id']]);
                    if ($extraSub) {
                        $subBookings[] = $extraSub;
                    }
                }
            }
        }

        foreach ($subBookings as $subB) {
            $hasBalanceDue = ((float) ($subB['balance_amount'] ?? 0)) > 0;
            $amountPaid = 0.00;
            if ($hasBalanceDue) {
                // Outstanding balance due: refund ONLY the amount already paid toward the reservation
                if ((float) ($subB['deposit_amount'] ?? 0) > 0) {
                    $amountPaid = (float) $subB['deposit_amount'];
                } else {
                    $amountPaid = max(0.00, (float) $subB['total_amount'] - (float) $subB['balance_amount']);
                }
            } else {
                $amountPaid = (float) $subB['total_amount'];
            }

            Database::run("
                UPDATE bookings
                SET status = 'cancelled',
                    hotel_status = 'cancelled',
                    package_overall_status = 'cancelled',
                    cancelled_at = COALESCE(cancelled_at, NOW()),
                    cancellation_reason = COALESCE(cancellation_reason, ?),
                    refund_amount = ?,
                    refund_status = 'refunded',
                    balance_amount = 0.00,
                    payment_stage = 'settled',
                    updated_at = NOW()
                WHERE id = ?
            ", [$reason, $amountPaid, (int) $subB['id']]);

            Database::run("UPDATE payments SET status = 'refunded' WHERE booking_id = ? AND status = 'paid'", [(int) $subB['id']]);
            if ($amountPaid > 0) {
                Database::insert("
                    INSERT INTO payments (booking_id, amount, method, status, reference, created_at)
                    VALUES (?, ?, 'refund', 'refunded', ?, NOW())
                ", [(int) $subB['id'], -$amountPaid, 'REF-HOTEL-' . strtoupper(bin2hex(random_bytes(4)))]);
            }
        }

        // 3. Cancel all rental requests
        $rentals = Database::all("SELECT * FROM rental_requests WHERE package_booking_ref = ? OR package_booking_id = ?", [$pkgRef, $parentBookingId]);
        foreach ($rentals as $r) {
            $rPaid = (float) ($r['total_amount'] ?? 0);
            Database::run("
                UPDATE rental_requests
                SET status = 'cancelled',
                    cancelled_at = COALESCE(cancelled_at, NOW()),
                    cancellation_reason = COALESCE(cancellation_reason, ?),
                    payment_status = 'refunded',
                    refund_status = 'refunded',
                    refund_amount = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$reason, $rPaid, (int) $r['id']]);
        }

        // 4. Cancel parent booking and refund amount paid
        $parentBalanceDue = ((float) ($parent['balance_amount'] ?? 0)) > 0;
        $parentPaid = 0.00;
        if ($parentBalanceDue) {
            if ((float) ($parent['deposit_amount'] ?? 0) > 0) {
                $parentPaid = (float) $parent['deposit_amount'];
            } else {
                $parentPaid = max(0.00, (float) $parent['total_amount'] - (float) $parent['balance_amount']);
            }
        } else {
            $parentPaid = (float) $parent['total_amount'];
        }

        Database::run("
            UPDATE bookings
            SET status = 'cancelled',
                hotel_status = 'cancelled',
                package_overall_status = 'cancelled',
                cancelled_at = COALESCE(cancelled_at, NOW()),
                cancellation_reason = COALESCE(cancellation_reason, ?),
                refund_amount = ?,
                refund_status = 'refunded',
                balance_amount = 0.00,
                payment_stage = 'settled',
                updated_at = NOW()
            WHERE id = ?
        ", [$reason, $parentPaid, $parentBookingId]);

        // Trigger payment refund if paid
        Payment::refund($parentBookingId);
        if ($parentPaid > 0) {
            Database::insert("
                INSERT INTO payments (booking_id, amount, method, status, reference, created_at)
                VALUES (?, ?, 'refund', 'refunded', ?, NOW())
            ", [$parentBookingId, -$parentPaid, 'REF-PKG-' . strtoupper(bin2hex(random_bytes(4)))]);
        }

        // Audit log
        self::logAudit(
            $pkgRef,
            $parentBookingId,
            null,
            'package_cancelled',
            $actorId,
            'traveler',
            "Entire travel package cancelled. Full refund of ₱" . number_format((float) $parent['total_amount'], 2) . " processed. Reason: {$reason}.",
            $reason
        );

        // Notify traveler and all providers
        $packageTitle = (string) ($parent['listing_title'] ?? '');
        if ($packageTitle === '' && !empty($parent['listing_id'])) {
            $l = Listing::find((int) $parent['listing_id']);
            $packageTitle = (string) ($l['title'] ?? 'Travel Package');
        }
        if ($packageTitle === '') {
            $packageTitle = 'Travel Package';
        }

        $touristUser = User::find((int) $parent['user_id']);
        if ($touristUser && !empty($touristUser['email'])) {
            $msg = "Your travel package '{$packageTitle}' (#{$pkgRef}) has been fully cancelled. A full refund of ₱" . number_format((float) $parent['total_amount'], 2) . " has been reversed to your account. Reason: {$reason}.";
            NotificationService::send($touristUser['email'], "Package Cancelled (#{$pkgRef})", $msg);
            self::createAutomatedMessage((int) $parent['user_id'], "Package Cancelled (#{$pkgRef})", $msg);
        }

        foreach ($items as $it) {
            $provId = (int) $it['provider_id'];
            if ($provId > 0 && $provId !== (int) $parent['user_id']) {
                $pUser = User::find($provId);
                if ($pUser && !empty($pUser['email'])) {
                    $msg = "Travel Package #{$pkgRef} ('{$packageTitle}') has been cancelled. Any remaining bookings for your service have been released.";
                    NotificationService::send($pUser['email'], "Package Cancelled (#{$pkgRef})", $msg);
                }
                self::createAutomatedMessage($provId, "Package Cancelled (#{$pkgRef})", "Package #{$pkgRef} cancelled.");
            }
        }

        return [
            'success' => true,
            'package_booking_ref' => $pkgRef,
            'overall_status' => 'cancelled',
            'refund_amount' => (float) $parent['total_amount'],
            'message' => "Entire travel package cancelled. Full refund of ₱" . number_format((float) $parent['total_amount'], 2) . " has been processed.",
        ];
    }

    /**
     * Send an automated system message between users in the messages table.
     */
    public static function createAutomatedMessage(int $recipientId, string $subject, string $body): void
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
