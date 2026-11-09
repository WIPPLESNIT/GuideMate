<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\NotificationService;

final class PackageService
{
    private static bool $tableEnsured = false;

    /**
     * Ensure package_services table and listings.package_approval_status column exist.
     */
    public static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        try {
            Database::run("
                CREATE TABLE IF NOT EXISTS `package_services` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `package_id` INT UNSIGNED NOT NULL,
                    `provider_id` INT UNSIGNED NOT NULL,
                    `service_type` ENUM('hotel', 'vehicle', 'activity', 'attraction') NOT NULL,
                    `hotel_room_id` INT UNSIGNED NULL,
                    `vehicle_id` INT UNSIGNED NULL,
                    `listing_id` INT UNSIGNED NULL,
                    `status` ENUM('pending', 'agreed', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                    `rejection_reason` VARCHAR(500) NULL,
                    `agreed_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `notes` TEXT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_pkg_services_pkg` (`package_id`),
                    KEY `idx_pkg_services_provider` (`provider_id`, `status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Attempt to ensure enum includes 'agreed' in existing tables
            try {
                Database::run("
                    ALTER TABLE `package_services`
                    MODIFY COLUMN `status` ENUM('pending', 'agreed', 'approved', 'rejected') NOT NULL DEFAULT 'pending'
                ");
            } catch (\Throwable $e) {}

            // Attempt to ensure listings table has package_approval_status column
            try {
                Database::run("
                    ALTER TABLE `listings`
                    ADD COLUMN `package_approval_status` ENUM('pending', 'agreed', 'rejected') NOT NULL DEFAULT 'pending'
                ");
            } catch (\Throwable $e) {}

            // Attempt to ensure guide_id_doc exists on package_services
            try {
                Database::run("
                    ALTER TABLE `package_services`
                    ADD COLUMN `guide_id_doc` VARCHAR(255) NULL AFTER `notes`
                ");
            } catch (\Throwable $e) {}

            // Attempt to ensure guide_vehicle_id_doc exists on listings
            try {
                Database::run("
                    ALTER TABLE `listings`
                    ADD COLUMN `guide_vehicle_id_doc` VARCHAR(255) NULL
                ");
            } catch (\Throwable $e) {}

            self::$tableEnsured = true;
        } catch (\Throwable $e) {
            // Gracefully ignore if database connection is unavailable in restricted sandbox
        }
    }

    /**
     * Synchronize included services (hotel room and vehicle) when a package is created or updated.
     * Automatically dispatches notifications to the respective providers.
     *
     * @param int $packageId
     * @param int $hotelRoomId
     * @param int $vehicleId
     * @param array<int> $activityIds
     * @param int $guideId
     * @param string $packageTitle
     * @param array<mixed> $itineraryDays
     * @param string $durationText
     * @return array<string, mixed>
     */
    public static function syncPackageServices(
        int $packageId,
        int $hotelRoomId,
        int $vehicleId,
        array $activityIds,
        int $guideId,
        string $packageTitle,
        array $itineraryDays = [],
        string $durationText = '',
        ?string $guideVehicleIdDoc = null
    ): array {
        self::ensureTable();

        if (!empty($guideVehicleIdDoc)) {
            try {
                Database::run("UPDATE listings SET guide_vehicle_id_doc = ? WHERE id = ?", [$guideVehicleIdDoc, $packageId]);
            } catch (\Throwable) {}
        }

        $guide = User::find($guideId) ?? [
            'id' => $guideId,
            'name' => 'Tour Guide',
            'email' => 'guide@guidemate.com',
            'phone' => 'N/A',
        ];

        $retainedServiceIds = [];

        // 1. Hotel Room Service Provider
        if ($hotelRoomId > 0) {
            try {
                $room = Database::first("
                    SELECT hr.id AS room_id, hr.name AS room_name, hr.price_per_night, hr.bed_type, hr.max_guests,
                           l.id AS hotel_listing_id, l.title AS hotel_name, l.area AS hotel_area, l.user_id AS provider_id,
                           u.name AS provider_name, u.email AS provider_email, u.phone AS provider_phone
                    FROM hotel_rooms hr
                    JOIN listings l ON l.id = hr.listing_id
                    JOIN users u ON u.id = l.user_id
                    WHERE hr.id = ?
                ", [$hotelRoomId]);

                if ($room) {
                    $providerId = (int) $room['provider_id'];
                    $existingService = Database::first("
                        SELECT id, status, rejection_reason
                        FROM package_services
                        WHERE package_id = ? AND service_type = 'hotel' AND hotel_room_id = ?
                    ", [$packageId, $hotelRoomId]);

                    if ($existingService) {
                        $retainedServiceIds[] = (int) $existingService['id'];
                    } else {
                        // New inclusion: insert as pending and notify hotel provider
                        $serviceId = (int) Database::insert("
                            INSERT INTO package_services
                            (package_id, provider_id, service_type, hotel_room_id, agreed_rate, status, notes)
                            VALUES (?, ?, 'hotel', ?, ?, 'pending', ?)
                        ", [
                            $packageId,
                            $providerId,
                            $hotelRoomId,
                            (float) $room['price_per_night'],
                            "Included Room: {$room['room_name']} at {$room['hotel_name']}",
                        ]);
                        $retainedServiceIds[] = $serviceId;

                        // Send email & in-app notification to the hotel provider
                        if (!empty($room['provider_email'])) {
                            NotificationService::packageInclusionRequest(
                                (string) $room['provider_email'],
                                (string) $room['provider_name'],
                                $packageTitle,
                                (string) ($guide['name'] ?? 'Guide Host'),
                                (string) ($guide['email'] ?? ''),
                                (string) ($guide['phone'] ?? ''),
                                'Hotel Accommodation',
                                "{$room['room_name']} ({$room['hotel_name']})",
                                money((float) $room['price_per_night']) . ' / night',
                                $durationText,
                                $itineraryDays,
                                $packageId,
                                $serviceId
                            );
                        }

                        // Send automated internal message from Guide to Hotel Provider
                        self::sendAutomatedInclusionMessage(
                            $guideId,
                            $providerId,
                            $packageId,
                            $packageTitle,
                            "Hotel Stay ({$room['room_name']} at {$room['hotel_name']})"
                        );
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. Vehicle Rental Service Provider
        if ($vehicleId > 0) {
            try {
                $veh = Database::first("
                    SELECT v.id AS vehicle_id, v.make, v.model, v.type AS vehicle_type, v.price_per_day,
                           v.user_id AS provider_id,
                           u.name AS provider_name, u.email AS provider_email, u.phone AS provider_phone
                    FROM vehicles v
                    JOIN users u ON u.id = v.user_id
                    WHERE v.id = ?
                ", [$vehicleId]);

                if ($veh) {
                    $providerId = (int) $veh['provider_id'];
                    $vehTitle = trim(($veh['make'] ?? '') . ' ' . ($veh['model'] ?? ''));
                    if ($vehTitle === '') $vehTitle = 'Partner Rental Vehicle';

                    $existingService = Database::first("
                        SELECT id, status, rejection_reason
                        FROM package_services
                        WHERE package_id = ? AND service_type = 'vehicle' AND vehicle_id = ?
                    ", [$packageId, $vehicleId]);

                    if ($existingService) {
                        $retainedServiceIds[] = (int) $existingService['id'];
                        if (!empty($guideVehicleIdDoc)) {
                            try {
                                Database::run("UPDATE package_services SET guide_id_doc = ? WHERE id = ?", [$guideVehicleIdDoc, (int) $existingService['id']]);
                            } catch (\Throwable) {}
                        }
                    } else {
                        // New inclusion: insert as pending and notify vehicle rental provider
                        $serviceId = (int) Database::insert("
                            INSERT INTO package_services
                            (package_id, provider_id, service_type, vehicle_id, agreed_rate, status, notes, guide_id_doc)
                            VALUES (?, ?, 'vehicle', ?, ?, 'pending', ?, ?)
                        ", [
                            $packageId,
                            $providerId,
                            $vehicleId,
                            (float) $veh['price_per_day'],
                            "Included Vehicle: {$vehTitle} ({$veh['vehicle_type']})",
                            $guideVehicleIdDoc,
                        ]);
                        $retainedServiceIds[] = $serviceId;

                        // Send email & in-app notification to the vehicle rental provider
                        if (!empty($veh['provider_email'])) {
                            NotificationService::packageInclusionRequest(
                                (string) $veh['provider_email'],
                                (string) $veh['provider_name'],
                                $packageTitle,
                                (string) ($guide['name'] ?? 'Guide Host'),
                                (string) ($guide['email'] ?? ''),
                                (string) ($guide['phone'] ?? ''),
                                'Vehicle Rental',
                                "{$vehTitle} (" . ucfirst((string) $veh['vehicle_type']) . ")",
                                money((float) $veh['price_per_day']) . ' / day',
                                $durationText,
                                $itineraryDays,
                                $packageId,
                                $serviceId
                            );
                        }

                        // Send automated internal message from Guide to Vehicle Rental Provider
                        self::sendAutomatedInclusionMessage(
                            $guideId,
                            $providerId,
                            $packageId,
                            $packageTitle,
                            "Vehicle Rental ({$vehTitle})"
                        );
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 3. Remove obsolete external services if unselected
        try {
            if (!empty($retainedServiceIds)) {
                $placeholders = implode(',', array_fill(0, count($retainedServiceIds), '?'));
                Database::run("
                    DELETE FROM package_services
                    WHERE package_id = ? AND service_type IN ('hotel', 'vehicle') AND id NOT IN ($placeholders)
                ", array_merge([$packageId], $retainedServiceIds));
            } else {
                Database::run("
                    DELETE FROM package_services
                    WHERE package_id = ? AND service_type IN ('hotel', 'vehicle')
                ", [$packageId]);
            }
        } catch (\Throwable $e) {}

        // 4. Recalculate and update the overall package status
        $overallStatus = self::refreshPackageApprovalStatus($packageId);

        return [
            'package_id' => $packageId,
            'approval_status' => $overallStatus,
            'services_count' => count($retainedServiceIds),
        ];
    }

    /**
     * Send an in-app message from guide to provider alerting them of the package inclusion.
     */
    private static function sendAutomatedInclusionMessage(
        int $senderId,
        int $receiverId,
        int $packageId,
        string $packageTitle,
        string $serviceName
    ): void {
        try {
            $reqUrl = url('/dashboard/package-requests');
            $msg = "Hello! I am preparing the travel package \"{$packageTitle}\" and have requested to include your {$serviceName}.\n\n"
                 . "Please review how your service will be utilized and confirm your agreement on your dashboard:\n{$reqUrl}";

            Database::insert("
                INSERT INTO messages (sender_id, receiver_id, listing_id, message, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ", [$senderId, $receiverId, $packageId, $msg]);
        } catch (\Throwable $e) {}
    }

    /**
     * Get all package service records for a specific package.
     *
     * @param int $packageId
     * @return array<int, array<string, mixed>>
     */
    public static function getForPackage(int $packageId): array
    {
        self::ensureTable();
        try {
            return Database::all("
                SELECT ps.*,
                       u.id AS provider_user_id, u.name AS provider_name, u.email AS provider_email, u.phone AS provider_phone,
                       hr.name AS room_name, hr.price_per_night, hr.bed_type, hr.max_guests, hr.amenities AS room_amenities,
                       hl.id AS hotel_listing_id, hl.title AS hotel_name, hl.area AS hotel_area,
                       v.id AS veh_id, v.make AS vehicle_make, v.model AS vehicle_model, v.type AS vehicle_type,
                       v.price_per_day AS vehicle_daily_rate, v.specs AS vehicle_specs, v.location AS vehicle_location
                FROM package_services ps
                LEFT JOIN users u ON u.id = ps.provider_id
                LEFT JOIN hotel_rooms hr ON hr.id = ps.hotel_room_id
                LEFT JOIN listings hl ON hl.id = hr.listing_id
                LEFT JOIN vehicles v ON v.id = ps.vehicle_id
                WHERE ps.package_id = ?
                ORDER BY ps.id ASC
            ", [$packageId]);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get all package inclusion requests for a specific provider.
     *
     * @param int $providerId
     * @param string $statusFilter 'all' | 'pending' | 'agreed' | 'rejected'
     * @return array<int, array<string, mixed>>
     */
    public static function getForProvider(int $providerId, string $statusFilter = 'all'): array
    {
        self::ensureTable();
        try {
            $sql = "
                SELECT ps.*,
                       l.id AS pkg_id, l.title AS package_title, l.slug AS package_slug, l.area AS package_area,
                       l.price AS package_price, l.duration AS package_duration, l.cover_image AS package_cover,
                       l.description AS package_description, l.included AS package_included, l.not_included AS package_not_included,
                       l.package_approval_status AS pkg_approval_status,
                       g.id AS guide_id, g.name AS guide_name, g.email AS guide_email, g.phone AS guide_phone,
                       g.avatar AS guide_avatar, g.guide_status, g.created_at AS guide_joined,
                       hr.name AS room_name, hr.price_per_night, hr.bed_type, hr.max_guests,
                       hl.id AS hotel_listing_id, hl.title AS hotel_name, hl.area AS hotel_area,
                       v.id AS veh_id, v.make AS vehicle_make, v.model AS vehicle_model, v.type AS vehicle_type,
                       v.price_per_day AS vehicle_daily_rate, v.specs AS vehicle_specs, v.location AS vehicle_location
                FROM package_services ps
                JOIN listings l ON l.id = ps.package_id
                JOIN users g ON g.id = l.user_id
                LEFT JOIN hotel_rooms hr ON hr.id = ps.hotel_room_id
                LEFT JOIN listings hl ON hl.id = hr.listing_id
                LEFT JOIN vehicles v ON v.id = ps.vehicle_id
                WHERE ps.provider_id = ?
            ";
            $params = [$providerId];

            if ($statusFilter !== 'all') {
                if ($statusFilter === 'agreed') {
                    $sql .= " AND ps.status IN ('agreed', 'approved')";
                } else {
                    $sql .= " AND ps.status = ?";
                    $params[] = $statusFilter;
                }
            }

            $sql .= " ORDER BY ps.id DESC";
            return Database::all($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Find a single package service entry by ID.
     *
     * @param int $serviceId
     * @return array<string, mixed>|null
     */
    public static function find(int $serviceId): ?array
    {
        self::ensureTable();
        try {
            return Database::first("
                SELECT ps.*,
                       l.id AS pkg_id, l.title AS package_title, l.slug AS package_slug, l.area AS package_area,
                       l.price AS package_price, l.duration AS package_duration, l.cover_image AS package_cover,
                       l.description AS package_description, l.included AS package_included, l.not_included AS package_not_included,
                       l.user_id AS guide_id,
                       g.name AS guide_name, g.email AS guide_email, g.phone AS guide_phone, g.avatar AS guide_avatar
                FROM package_services ps
                JOIN listings l ON l.id = ps.package_id
                JOIN users g ON g.id = l.user_id
                WHERE ps.id = ?
                LIMIT 1
            ", [$serviceId]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Provider responds to an inclusion request (Agree or Reject).
     *
     * @param int $serviceId
     * @param int $providerId
     * @param string $action 'agree' | 'reject'
     * @param string|null $rejectionReason
     * @return array<string, mixed>
     */
    public static function respond(int $serviceId, int $providerId, string $action, ?string $rejectionReason = null): array
    {
        self::ensureTable();

        $action = strtolower(trim($action));
        if (!in_array($action, ['agree', 'reject'], true)) {
            return ['success' => false, 'error' => 'Invalid decision action. Please choose Agree or Reject.'];
        }

        $service = self::find($serviceId);
        if (!$service || (int) $service['provider_id'] !== $providerId) {
            return ['success' => false, 'error' => 'Package inclusion request not found or access unauthorized.'];
        }

        $currentStatus = strtolower((string) ($service['status'] ?? ''));
        if ($action === 'reject' && in_array($currentStatus, ['agreed', 'approved'], true)) {
            return ['success' => false, 'error' => 'You have already agreed to the inclusion of your service in this package. Once agreed, this request cannot be declined.'];
        }

        $newStatus = ($action === 'agree') ? 'agreed' : 'rejected';
        $reasonClean = ($action === 'reject') ? trim((string) $rejectionReason) : null;

        try {
            Database::run("
                UPDATE package_services
                SET status = ?, rejection_reason = ?, updated_at = NOW()
                WHERE id = ? AND provider_id = ?
            ", [$newStatus, $reasonClean, $serviceId, $providerId]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Database error while saving decision: ' . $e->getMessage()];
        }

        $packageId = (int) $service['package_id'];
        $packageTitle = (string) $service['package_title'];
        $guideId = (int) $service['guide_id'];
        $guideEmail = (string) $service['guide_email'];
        $guideName = (string) $service['guide_name'];

        $provider = User::find($providerId);
        $providerName = (string) ($provider['name'] ?? 'Service Partner');

        // Refresh overall package status
        $newOverallStatus = self::refreshPackageApprovalStatus($packageId);

        // Notify guide about provider's decision
        if (!empty($guideEmail)) {
            NotificationService::packageProviderDecision(
                $guideEmail,
                $guideName,
                $providerName,
                ucfirst((string) $service['service_type']),
                $packageTitle,
                $newStatus,
                $reasonClean,
                $newOverallStatus
            );
        }

        // Also send an automated in-app message to guide
        try {
            $decisionMsg = ($newStatus === 'agreed')
                ? "Hello {$guideName}! I have reviewed and AGREED to the inclusion of our service in your travel package \"{$packageTitle}\"."
                : "Hello {$guideName}. Regretfully, we have DECLINED the inclusion of our service in \"{$packageTitle}\"." . ($reasonClean ? "\nReason: {$reasonClean}" : '');

            Database::insert("
                INSERT INTO messages (sender_id, receiver_id, listing_id, message, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ", [$providerId, $guideId, $packageId, $decisionMsg]);
        } catch (\Throwable $e) {}

        return [
            'success' => true,
            'service_id' => $serviceId,
            'decision' => $newStatus,
            'package_status' => $newOverallStatus,
        ];
    }

    /**
     * Recalculates and updates the overall package approval status for a package.
     *
     * Rules:
     * - If no external partner services required (guide only): 'agreed'
     * - If ANY required partner service is 'rejected': 'rejected'
     * - Else if ANY required partner service is 'pending': 'pending'
     * - Else if ALL required partner services are 'agreed' (or 'approved'): 'agreed'
     *
     * @param int $packageId
     * @return string 'agreed' | 'pending' | 'rejected'
     */
    public static function refreshPackageApprovalStatus(int $packageId): string
    {
        self::ensureTable();

        $services = self::getForPackage($packageId);

        // Only external partner services (hotel and vehicle) require third-party agreement
        $externalServices = array_filter($services, function ($s) {
            return in_array($s['service_type'] ?? '', ['hotel', 'vehicle'], true);
        });

        if (empty($externalServices)) {
            $overall = 'agreed';
        } else {
            $hasRejected = false;
            $hasPending = false;
            $allAgreed = true;

            foreach ($externalServices as $s) {
                $st = strtolower((string) ($s['status'] ?? 'pending'));
                if ($st === 'rejected') {
                    $hasRejected = true;
                } elseif ($st === 'pending') {
                    $hasPending = true;
                }
                if ($st !== 'agreed' && $st !== 'approved') {
                    $allAgreed = false;
                }
            }

            if ($hasRejected) {
                $overall = 'rejected';
            } elseif ($hasPending || !$allAgreed) {
                $overall = 'pending';
            } else {
                $overall = 'agreed';
            }
        }

        try {
            Database::run("
                UPDATE listings
                SET package_approval_status = ?
                WHERE id = ?
            ", [$overall, $packageId]);
        } catch (\Throwable $e) {}

        return $overall;
    }

    /**
     * Get the overall approval status for a package.
     *
     * @param int $packageId
     * @param string|null $cachedStatus
     * @return string 'agreed' | 'pending' | 'rejected'
     */
    public static function getOverallStatus(int $packageId, ?string $cachedStatus = null): string
    {
        if (!empty($cachedStatus) && in_array(strtolower($cachedStatus), ['agreed', 'pending', 'rejected'], true)) {
            return strtolower($cachedStatus);
        }

        self::ensureTable();
        try {
            $row = Database::first("SELECT package_approval_status FROM listings WHERE id = ?", [$packageId]);
            if ($row && !empty($row['package_approval_status'])) {
                return strtolower((string) $row['package_approval_status']);
            }
        } catch (\Throwable $e) {}

        return self::refreshPackageApprovalStatus($packageId);
    }

    /**
     * Get a detailed provider approval summary for admin inspection and dashboards.
     *
     * @param int $packageId
     * @return array<string, mixed>
     */
    public static function getProviderApprovalSummary(int $packageId): array
    {
        $services = self::getForPackage($packageId);
        $externalServices = array_values(array_filter($services, function ($s) {
            return in_array($s['service_type'] ?? '', ['hotel', 'vehicle'], true);
        }));

        $total = count($externalServices);
        $agreed = 0;
        $pending = 0;
        $rejected = 0;

        foreach ($externalServices as $s) {
            $st = strtolower((string) ($s['status'] ?? 'pending'));
            if ($st === 'agreed' || $st === 'approved') {
                $agreed++;
            } elseif ($st === 'rejected') {
                $rejected++;
            } else {
                $pending++;
            }
        }

        if ($total === 0) {
            $overall = 'agreed';
        } elseif ($rejected > 0) {
            $overall = 'rejected';
        } elseif ($pending > 0 || $agreed < $total) {
            $overall = 'pending';
        } else {
            $overall = 'agreed';
        }

        return [
            'overall_status' => $overall,
            'total_required' => $total,
            'agreed_count' => $agreed,
            'pending_count' => $pending,
            'rejected_count' => $rejected,
            'is_fully_agreed' => ($overall === 'agreed'),
            'services' => $externalServices,
        ];
    }
}
