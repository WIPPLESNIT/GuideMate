<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class RentalRequest
{
    /** Problem types a tourist can report against a rental reservation. */
    public const REPORT_TYPES = [
        'vehicle_defect'  => 'Vehicle problem / breakdown',
        'not_delivered'   => 'Unit was not delivered',
        'not_as_described' => 'Not as described / wrong unit',
        'overcharged'     => 'Overcharged / extra fees',
        'safety'          => 'Unsafe or unfit to drive',
        'other'           => 'Other problem',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::all(
            'SELECT r.*, u.name AS user_name, u.email AS user_email
             FROM rental_requests r
             JOIN users u ON u.id = r.user_id
             ORDER BY FIELD(r.status, "pending", "approved", "contacted", "completed", "cancelled", "refunded"), r.created_at DESC'
        );
    }

    /**
     * Rental requests for vehicles owned by a specific rental provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forProvider(int $providerId): array
    {
        return Database::all(
            'SELECT r.*, u.name AS user_name, u.email AS user_email
             FROM rental_requests r
             JOIN users u ON u.id = r.user_id
             JOIN vehicles v ON CAST(v.id AS CHAR) = CAST(r.vehicle_id AS CHAR)
             WHERE v.user_id = ?
             ORDER BY FIELD(r.status, "pending", "approved", "contacted", "completed", "cancelled", "refunded"), r.created_at DESC',
            [$providerId]
        );
    }

    /**
     * Reservations that belong to a specific tourist (for the mobile app).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forCustomer(int $userId): array
    {
        return Database::all(
            'SELECT * FROM rental_requests WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM rental_requests WHERE id = ?', [$id]);
    }

    /**
     * Create a fully-paid reservation. The tourist pays the whole amount up
     * front and submits a valid ID that is held for the rental owner.
     *
     * @param array<string, mixed> $data
     */
    public static function create(int $userId, array $data): int
    {
        $days = max(1, (int) ($data['rental_days'] ?? 1));
        $pricePerDay = (float) ($data['price_per_day'] ?? 0);
        $total = round($pricePerDay * $days, 2);

        $paid = !empty($data['payment_reference']) || ($data['payment_status'] ?? '') === 'paid';

        $id = Database::insert(
            'INSERT INTO rental_requests (
                user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, location,
                pickup_date, rental_days, total_amount, customer_name, customer_email,
                customer_phone, notes, status,
                payment_status, payment_method, payment_reference, paid_at,
                id_document, id_type, id_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                (string) ($data['vehicle_id'] ?? ''),
                (string) ($data['vehicle_name'] ?? ''),
                (string) ($data['vehicle_type'] ?? ''),
                (string) ($data['shop_name'] ?? ''),
                (string) ($data['location'] ?? ''),
                (string) ($data['pickup_date'] ?? ''),
                $days,
                $total,
                (string) ($data['customer_name'] ?? ''),
                (string) ($data['customer_email'] ?? ''),
                (string) ($data['customer_phone'] ?? ''),
                (string) ($data['notes'] ?? ''),
                'pending',
                $paid ? 'paid' : 'unpaid',
                $paid ? (string) ($data['payment_method'] ?? 'card') : null,
                $paid ? (string) ($data['payment_reference'] ?? '') : null,
                $paid ? date('Y-m-d H:i:s') : null,
                ($data['id_document'] ?? null) !== null ? (string) $data['id_document'] : null,
                (string) ($data['id_type'] ?? '') !== '' ? (string) $data['id_type'] : null,
                (string) ($data['id_number'] ?? '') !== '' ? (string) $data['id_number'] : null,
            ]
        );

        $vehicleId = $data['vehicle_id'] ?? null;
        if ($vehicleId !== null && is_numeric($vehicleId)) {
            $vehicle = Vehicle::find((int) $vehicleId);
            if ($vehicle !== null && !empty($vehicle['user_id'])) {
                $providerId = (int) $vehicle['user_id'];
                User::incrementRequests($providerId);
                if ($paid) {
                    User::addRevenue($providerId, $total);
                }
            }
        }

        return $id;
    }

    /**
     * File a single tourist problem report against a reservation.
     */
    public static function report(int $id, string $type, string $message): void
    {
        Database::run(
            "UPDATE rental_requests
             SET report_type = ?, report_message = ?, report_status = 'open',
                 report_created_at = NOW()
             WHERE id = ?",
            [$type, $message, $id]
        );
    }

    /**
     * Refund a reservation (owner-initiated) and resolve its report.
     */
    public static function refund(int $id, ?string $ownerNote = null): void
    {
        $rental = self::find($id);

        Database::run(
            "UPDATE rental_requests
             SET status = 'refunded', payment_status = 'refunded',
                 report_status = CASE WHEN report_status = 'open' THEN 'refunded' ELSE report_status END,
                 owner_report_note = COALESCE(?, owner_report_note)
             WHERE id = ?",
            [$ownerNote, $id]
        );

        if ($rental !== null && ($rental['payment_status'] ?? '') === 'paid') {
            $vehicleId = $rental['vehicle_id'] ?? null;
            if ($vehicleId !== null && is_numeric($vehicleId)) {
                $vehicle = Vehicle::find((int) $vehicleId);
                if ($vehicle !== null && !empty($vehicle['user_id'])) {
                    User::deductRevenue((int) $vehicle['user_id'], (float) ($rental['total_amount'] ?? 0));
                }
            }
        }
    }

    /**
     * Reject a tourist's report without a refund (owner-initiated).
     */
    public static function rejectReport(int $id, ?string $ownerNote = null): void
    {
        Database::run(
            "UPDATE rental_requests
             SET report_status = 'rejected', owner_report_note = COALESCE(?, owner_report_note)
             WHERE id = ? AND report_status = 'open'",
            [$ownerNote, $id]
        );
    }

    public static function reportTypeLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }
        return self::REPORT_TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function updateStatus(int $id, string $status, ?string $adminNote = null): void
    {
        $contacted = in_array($status, ['contacted', 'approved', 'completed'], true)
            ? date('Y-m-d H:i:s')
            : null;

        Database::run(
            'UPDATE rental_requests
             SET status = ?, admin_note = COALESCE(?, admin_note), contacted_at = COALESCE(?, contacted_at)
             WHERE id = ?',
            [$status, $adminNote, $contacted, $id]
        );
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Awaiting confirmation',
            'approved' => 'Approved',
            'contacted' => 'Contacted',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            'refunded' => 'Refunded',
            default => ucfirst($status),
        };
    }

    /**
     * Catalog of rentable vehicles shown in the mobile app and tourist portal.
     * Backed by approved and available vehicle listings created by rental providers.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function vehicleCatalog(): array
    {
        $vehicles = Vehicle::approvedAndAvailable();
        if ($vehicles !== []) {
            return array_map(static function (array $v): array {
                return [
                    'id' => (string) $v['id'],
                    'slug' => (string) $v['slug'],
                    'name' => (string) $v['name'],
                    'make' => (string) $v['make'],
                    'model' => (string) $v['model'],
                    'year' => (int) $v['year'],
                    'type' => (string) $v['type'],
                    'price_per_day' => (float) $v['price_per_day'],
                    'specs' => Vehicle::parseSpecs($v['specs'] ?? null),
                    'shop' => (string) ($v['provider_name'] ?? 'Rental Partner'),
                    'location' => (string) ($v['location'] ?? 'Cebu'),
                    'rating' => 4.8,
                    'image' => api_img_src((string) ($v['image'] ?? ''), 'vehicle' . $v['id']),
                ];
            }, $vehicles);
        }

        return [];
    }
}
