<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class RevenueService
{
    /**
     * Compute the exact eligible completed revenue for a specific provider.
     * Only completed listings or bookings generate revenue.
     * Pending, Confirmed, Contacted, Ongoing, Cancelled, Refunded, or other
     * incomplete statuses generate 0.00.
     */
    public static function getProviderRevenue(int $providerId): float
    {
        if ($providerId <= 0) {
            return 0.0;
        }

        // 1. Standalone completed bookings where listing belongs to provider
        // Excludes package parent and sub bookings to avoid double counting with package_booking_items
        $standaloneListingRevenue = (float) (Database::first("
            SELECT COALESCE(SUM(b.total_amount), 0) AS total
            FROM bookings b
            JOIN listings l ON l.id = b.listing_id
            WHERE l.user_id = ?
              AND (b.status = 'completed' OR b.hotel_status = 'completed')
              AND b.status NOT IN ('cancelled', 'refunded')
              AND (b.hotel_status IS NULL OR b.hotel_status NOT IN ('cancelled', 'refunded', 'no_show'))
              AND b.package_booking_ref IS NULL
        ", [$providerId])['total'] ?? 0.0);

        // 2. Standalone completed vehicle rental requests for vehicles owned by provider
        $standaloneRentalRevenue = (float) (Database::first("
            SELECT COALESCE(SUM(rr.total_amount), 0) AS total
            FROM rental_requests rr
            JOIN vehicles v ON (v.id = rr.vehicle_id OR v.id = CAST(rr.vehicle_id AS UNSIGNED))
            WHERE v.user_id = ?
              AND rr.status = 'completed'
              AND rr.payment_status = 'paid'
              AND rr.status NOT IN ('cancelled', 'refunded')
              AND rr.package_booking_ref IS NULL
        ", [$providerId])['total'] ?? 0.0);

        // 3. Completed travel package items where this provider is the assigned provider.
        // Requires the parent booking to exist in bookings and not be cancelled or refunded.
        $packageItemsRevenue = (float) (Database::first("
            SELECT COALESCE(SUM(pbi.allocated_amount), 0) AS total
            FROM package_booking_items pbi
            INNER JOIN bookings pb ON pb.id = pbi.parent_booking_id
            WHERE pbi.provider_id = ?
              AND pbi.status = 'completed'
              AND (pbi.payment_status IS NULL OR pbi.payment_status != 'refunded')
              AND pbi.status NOT IN ('cancelled', 'removed')
              AND pb.status NOT IN ('cancelled', 'refunded')
              AND (pb.package_overall_status IS NULL OR pb.package_overall_status NOT IN ('cancelled', 'refunded'))
        ", [$providerId])['total'] ?? 0.0);

        // 4. Legacy completed parent packages that don't have package_booking_items rows
        $legacyPackageRevenue = (float) (Database::first("
            SELECT COALESCE(SUM(b.total_amount), 0) AS total
            FROM bookings b
            JOIN listings l ON l.id = b.listing_id
            JOIN categories c ON c.id = l.category_id
            WHERE l.user_id = ?
              AND c.slug = 'packages'
              AND b.status = 'completed'
              AND b.status NOT IN ('cancelled', 'refunded')
              AND b.package_booking_ref IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM package_booking_items pbi
                  WHERE pbi.package_booking_ref = b.package_booking_ref
              )
        ", [$providerId])['total'] ?? 0.0);

        return round($standaloneListingRevenue + $standaloneRentalRevenue + $packageItemsRevenue + $legacyPackageRevenue, 2);
    }

    /**
     * Compute combined eligible revenue across all providers.
     * Equals the sum of all completed provider earnings with zero double-counting.
     */
    public static function getAdminTotalRevenue(): float
    {
        // 1. All standalone completed bookings
        $standaloneListingTotal = (float) (Database::first("
            SELECT COALESCE(SUM(b.total_amount), 0) AS total
            FROM bookings b
            INNER JOIN listings l ON l.id = b.listing_id
            INNER JOIN users u ON u.id = l.user_id
            WHERE (b.status = 'completed' OR b.hotel_status = 'completed')
              AND b.status NOT IN ('cancelled', 'refunded')
              AND (b.hotel_status IS NULL OR b.hotel_status NOT IN ('cancelled', 'refunded', 'no_show'))
              AND b.package_booking_ref IS NULL
        ")['total'] ?? 0.0);

        // 2. All standalone completed vehicle rental requests
        $standaloneRentalTotal = (float) (Database::first("
            SELECT COALESCE(SUM(rr.total_amount), 0) AS total
            FROM rental_requests rr
            INNER JOIN vehicles v ON (v.id = rr.vehicle_id OR v.id = CAST(rr.vehicle_id AS UNSIGNED))
            INNER JOIN users u ON u.id = v.user_id
            WHERE rr.status = 'completed'
              AND rr.payment_status = 'paid'
              AND rr.status NOT IN ('cancelled', 'refunded')
              AND rr.package_booking_ref IS NULL
        ")['total'] ?? 0.0);

        // 3. All completed travel package items across all providers with active parent booking
        $packageItemsTotal = (float) (Database::first("
            SELECT COALESCE(SUM(pbi.allocated_amount), 0) AS total
            FROM package_booking_items pbi
            INNER JOIN users u ON u.id = pbi.provider_id
            INNER JOIN bookings pb ON pb.id = pbi.parent_booking_id
            WHERE pbi.status = 'completed'
              AND (pbi.payment_status IS NULL OR pbi.payment_status != 'refunded')
              AND pbi.status NOT IN ('cancelled', 'removed')
              AND pb.status NOT IN ('cancelled', 'refunded')
              AND (pb.package_overall_status IS NULL OR pb.package_overall_status NOT IN ('cancelled', 'refunded'))
        ")['total'] ?? 0.0);

        // 4. Legacy completed parent packages without package_booking_items rows
        $legacyPackageTotal = (float) (Database::first("
            SELECT COALESCE(SUM(b.total_amount), 0) AS total
            FROM bookings b
            INNER JOIN listings l ON l.id = b.listing_id
            INNER JOIN categories c ON c.id = l.category_id
            INNER JOIN users u ON u.id = l.user_id
            WHERE c.slug = 'packages'
              AND b.status = 'completed'
              AND b.status NOT IN ('cancelled', 'refunded')
              AND b.package_booking_ref IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM package_booking_items pbi
                  WHERE pbi.package_booking_ref = b.package_booking_ref
              )
        ")['total'] ?? 0.0);

        return round($standaloneListingTotal + $standaloneRentalTotal + $packageItemsTotal + $legacyPackageTotal, 2);
    }

    /**
     * Synchronize a single user's `revenue` column in `users` table with their actual database completed records.
     */
    public static function syncUserRevenue(int $providerId): float
    {
        if ($providerId <= 0) {
            return 0.0;
        }

        $revenue = self::getProviderRevenue($providerId);
        Database::run("UPDATE users SET revenue = ? WHERE id = ?", [$revenue, $providerId]);
        return $revenue;
    }

    /**
     * Synchronize all provider accounts' `revenue` column in `users` table.
     */
    public static function syncAllProvidersRevenue(): void
    {
        // Automatically prune any orphaned package items whose parent booking was permanently deleted
        try {
            Database::run("
                DELETE pbi FROM package_booking_items pbi
                LEFT JOIN bookings b ON b.id = pbi.parent_booking_id
                WHERE b.id IS NULL
            ");
        } catch (\Throwable $e) {}

        $users = Database::all("
            SELECT id, role FROM users
        ");
        foreach ($users as $u) {
            $role = (string) ($u['role'] ?? '');
            if ($role === 'admin' || $role === 'tourist') {
                Database::run("UPDATE users SET revenue = 0 WHERE id = ?", [(int) $u['id']]);
            } else {
                self::syncUserRevenue((int) $u['id']);
            }
        }
    }

    /**
     * Called when a booking is deleted. Synchronizes provider and admin revenues.
     *
     * @param array<string, mixed> $bookingData
     */
    public static function recordBookingDeletion(int $bookingId, array $bookingData = []): void
    {
        $affectedProviderIds = [];
        if (!empty($bookingData['listing_owner_id'])) {
            $affectedProviderIds[] = (int) $bookingData['listing_owner_id'];
        }
        if (!empty($bookingData['guide_id'])) {
            $affectedProviderIds[] = (int) $bookingData['guide_id'];
        }
        if (!empty($bookingData['provider_ids']) && is_array($bookingData['provider_ids'])) {
            foreach ($bookingData['provider_ids'] as $pid) {
                $affectedProviderIds[] = (int) $pid;
            }
        }

        $affectedProviderIds = array_unique(array_filter($affectedProviderIds, fn($pid) => $pid > 0));
        foreach ($affectedProviderIds as $pid) {
            self::syncUserRevenue($pid);
        }
        self::syncAllProvidersRevenue();
    }

    /**
     * Called when a rental request is deleted. Synchronizes provider and admin revenues.
     *
     * @param array<string, mixed> $rentalData
     */
    public static function recordRentalDeletion(int $rentalRequestId, array $rentalData = []): void
    {
        $providerId = (int) ($rentalData['vehicle_owner_id'] ?? $rentalData['provider_id'] ?? 0);
        if ($providerId > 0) {
            self::syncUserRevenue($providerId);
        }
        self::syncAllProvidersRevenue();
    }

    /**
     * Called when a booking is completed. Synchronizes provider revenue.
     */
    public static function recordBookingCompletion(int $bookingId): void
    {
        $booking = Database::first("
            SELECT b.*, l.user_id AS listing_owner_id
            FROM bookings b
            LEFT JOIN listings l ON l.id = b.listing_id
            WHERE b.id = ?
        ", [$bookingId]);

        if (!$booking) {
            return;
        }

        if (!empty($booking['listing_owner_id'])) {
            self::syncUserRevenue((int) $booking['listing_owner_id']);
        }

        // If part of a package, also sync other participating providers
        if (!empty($booking['package_booking_ref'])) {
            self::recordPackageCompletion((string) $booking['package_booking_ref']);
        }
    }

    /**
     * Called when a rental request is completed. Synchronizes provider revenue.
     */
    public static function recordRentalCompletion(int $rentalRequestId): void
    {
        $rental = Database::first("
            SELECT rr.*, v.user_id AS vehicle_owner_id
            FROM rental_requests rr
            LEFT JOIN vehicles v ON v.id = rr.vehicle_id
            WHERE rr.id = ?
        ", [$rentalRequestId]);

        if (!$rental) {
            return;
        }

        if (!empty($rental['vehicle_owner_id'])) {
            self::syncUserRevenue((int) $rental['vehicle_owner_id']);
        }

        // If part of a package, also sync other participating providers
        if (!empty($rental['package_booking_ref'])) {
            self::recordPackageCompletion((string) $rental['package_booking_ref']);
        }
    }

    /**
     * Called when a package is completed. Synchronizes all participating providers' revenue.
     */
    public static function recordPackageCompletion(string $pkgRef): void
    {
        $items = Database::all("
            SELECT DISTINCT provider_id FROM package_booking_items
            WHERE package_booking_ref = ? AND provider_id > 0
        ", [$pkgRef]);

        foreach ($items as $it) {
            self::syncUserRevenue((int) $it['provider_id']);
        }

        $parent = Database::first("
            SELECT b.*, l.user_id AS guide_id
            FROM bookings b
            JOIN listings l ON l.id = b.listing_id
            WHERE b.package_booking_ref = ?
            LIMIT 1
        ", [$pkgRef]);

        if ($parent && !empty($parent['guide_id'])) {
            self::syncUserRevenue((int) $parent['guide_id']);
        }
    }

    /**
     * Get transaction history for provider - displays only eligible completed transactions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getCompletedTransactionsForProvider(int $providerId): array
    {
        if ($providerId <= 0) {
            return [];
        }

        $transactions = [];

        // 1. Standalone completed listing bookings
        $bookings = Database::all("
            SELECT b.id, b.booking_date, b.booking_time, b.booking_end_time, b.guests, b.status,
                   b.total_amount, b.created_at, b.updated_at,
                   l.title AS listing_title, l.slug AS listing_slug,
                   u.name AS customer_name,
                   p.amount AS paid_amount, p.method AS payment_method,
                   p.status AS payment_status, p.reference AS payment_reference,
                   COALESCE(p.paid_at, b.updated_at, b.created_at) AS paid_at
            FROM bookings b
            JOIN listings l ON l.id = b.listing_id
            JOIN users u ON u.id = b.user_id
            LEFT JOIN payments p ON p.booking_id = b.id AND p.status = 'paid'
            WHERE l.user_id = ?
              AND (b.status = 'completed' OR b.hotel_status = 'completed')
              AND b.status NOT IN ('cancelled', 'refunded')
              AND (b.hotel_status IS NULL OR b.hotel_status NOT IN ('cancelled', 'refunded', 'no_show'))
              AND b.package_booking_ref IS NULL
            ORDER BY paid_at DESC
        ", [$providerId]);

        foreach ($bookings as $b) {
            $transactions[] = [
                'id' => $b['id'],
                'booking_date' => $b['booking_date'],
                'booking_time' => $b['booking_time'],
                'booking_end_time' => $b['booking_end_time'],
                'guests' => $b['guests'],
                'status' => 'completed',
                'total_amount' => (float) $b['total_amount'],
                'paid_amount' => (float) ($b['paid_amount'] ?? $b['total_amount']),
                'listing_title' => $b['listing_title'],
                'listing_slug' => $b['listing_slug'],
                'customer_name' => $b['customer_name'],
                'payment_method' => $b['payment_method'] ?? 'card',
                'payment_status' => 'paid',
                'payment_reference' => $b['payment_reference'] ?? ('BK-' . $b['id']),
                'paid_at' => $b['paid_at'],
                'created_at' => $b['created_at'],
            ];
        }

        // 2. Standalone completed rental requests
        $rentals = Database::all("
            SELECT rr.id, rr.pickup_date, rr.rental_days, rr.status,
                   rr.total_amount, rr.customer_name, rr.created_at, rr.updated_at,
                   rr.payment_method, rr.payment_reference, rr.paid_at,
                   v.name AS vehicle_name, v.model
            FROM rental_requests rr
            JOIN vehicles v ON v.id = rr.vehicle_id
            WHERE v.user_id = ?
              AND rr.status = 'completed'
              AND rr.payment_status = 'paid'
              AND rr.package_booking_ref IS NULL
            ORDER BY COALESCE(rr.paid_at, rr.updated_at, rr.created_at) DESC
        ", [$providerId]);

        foreach ($rentals as $r) {
            $transactions[] = [
                'id' => $r['id'],
                'booking_date' => $r['pickup_date'],
                'booking_time' => '09:00 AM',
                'booking_end_time' => null,
                'guests' => (int) ($r['rental_days'] ?? 1),
                'status' => 'completed',
                'total_amount' => (float) $r['total_amount'],
                'paid_amount' => (float) $r['total_amount'],
                'listing_title' => $r['vehicle_name'] ?? 'Vehicle Rental',
                'listing_slug' => '',
                'customer_name' => $r['customer_name'] ?? 'Renter',
                'payment_method' => $r['payment_method'] ?? 'card',
                'payment_status' => 'paid',
                'payment_reference' => $r['payment_reference'] ?? ('RENT-' . $r['id']),
                'paid_at' => $r['paid_at'] ?? $r['updated_at'] ?? $r['created_at'],
                'created_at' => $r['created_at'],
            ];
        }

        // 3. Completed package items
        $packageItems = Database::all("
            SELECT pbi.id, pbi.allocated_amount, pbi.service_type, pbi.service_name,
                   pbi.status, pbi.responded_at, pbi.created_at, pbi.package_booking_ref,
                   b.booking_date, b.guests,
                   u.name AS customer_name,
                   l.title AS package_title
            FROM package_booking_items pbi
            JOIN bookings b ON (b.id = pbi.parent_booking_id)
            JOIN users u ON u.id = b.user_id
            LEFT JOIN listings l ON l.id = b.listing_id
            WHERE pbi.provider_id = ?
              AND pbi.status = 'completed'
              AND (pbi.payment_status IS NULL OR pbi.payment_status != 'refunded')
            ORDER BY COALESCE(pbi.responded_at, pbi.created_at) DESC
        ", [$providerId]);

        foreach ($packageItems as $pi) {
            $transactions[] = [
                'id' => $pi['id'],
                'booking_date' => $pi['booking_date'],
                'booking_time' => null,
                'booking_end_time' => null,
                'guests' => (int) ($pi['guests'] ?? 1),
                'status' => 'completed',
                'total_amount' => (float) $pi['allocated_amount'],
                'paid_amount' => (float) $pi['allocated_amount'],
                'listing_title' => $pi['package_title'] ? ($pi['package_title'] . ' (' . ucfirst((string) $pi['service_type']) . ')') : ($pi['service_name'] ?? 'Package Service'),
                'listing_slug' => '',
                'customer_name' => $pi['customer_name'] ?? 'Traveler',
                'payment_method' => 'card',
                'payment_status' => 'paid',
                'payment_reference' => $pi['package_booking_ref'],
                'paid_at' => $pi['responded_at'] ?? $pi['created_at'],
                'created_at' => $pi['created_at'],
            ];
        }

        // Sort all by date descending
        usort($transactions, function ($a, $b) {
            $ta = strtotime((string) ($a['paid_at'] ?? $a['created_at'] ?? 0));
            $tb = strtotime((string) ($b['paid_at'] ?? $b['created_at'] ?? 0));
            return $tb <=> $ta;
        });

        return $transactions;
    }
}
