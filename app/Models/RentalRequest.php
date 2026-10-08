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

        return Database::insert(
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
        Database::run(
            "UPDATE rental_requests
             SET status = 'refunded', payment_status = 'refunded',
                 report_status = CASE WHEN report_status = 'open' THEN 'refunded' ELSE report_status END,
                 owner_report_note = COALESCE(?, owner_report_note)
             WHERE id = ?",
            [$ownerNote, $id]
        );
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
     * Catalog of rentable vehicles shown in the mobile app.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function vehicleCatalog(): array
    {
        return [
            [
                'id' => 'scooter',
                'name' => 'Honda Click 125i',
                'type' => 'Scooter',
                'price_per_day' => 500,
                'specs' => ['Automatic', '2 seats', 'Helmet included'],
                'shop' => 'Cebu Moto Rentals',
                'location' => 'Cebu City · Fuente Osmeña',
                'rating' => 4.8,
                'image' => 'scooter.jpg',
            ],
            [
                'id' => 'motorcycle',
                'name' => 'KTM 390 Adventure',
                'type' => 'Motorcycle',
                'price_per_day' => 1200,
                'specs' => ['Manual', '2 seats', 'Off-road ready'],
                'shop' => 'ADV Riders Cebu',
                'location' => 'Mandaue City · A.S. Fortuna',
                'rating' => 4.7,
                'image' => 'motorcycle.png',
            ],
            [
                'id' => 'ebike',
                'name' => 'City E-Bike',
                'type' => 'E-Bike',
                'price_per_day' => 350,
                'specs' => ['Electric', 'Pedal-assist', '60km range'],
                'shop' => 'GreenRide Mactan',
                'location' => 'Lapu-Lapu · Mactan Island',
                'rating' => 4.9,
                'image' => 'ebike.jpg',
            ],
            [
                'id' => 'sedan',
                'name' => 'Toyota Vios',
                'type' => 'Sedan',
                'price_per_day' => 1800,
                'specs' => ['5 seats', 'Aircon', 'Self-drive'],
                'shop' => 'Cebu Car Hub',
                'location' => 'Cebu City · IT Park',
                'rating' => 4.6,
                'image' => 'sedan.jpg',
            ],
            [
                'id' => 'suv',
                'name' => 'Kia Sportage',
                'type' => 'SUV',
                'price_per_day' => 2800,
                'specs' => ['5 seats', '4x4', 'Self-drive'],
                'shop' => 'Island SUV Rentals',
                'location' => 'Cebu City · SRP',
                'rating' => 4.7,
                'image' => 'suv.jpg',
            ],
            [
                'id' => 'van',
                'name' => 'Toyota HiAce Grandia',
                'type' => 'Van',
                'price_per_day' => 4500,
                'specs' => ['12 seats', 'With driver', 'Tours'],
                'shop' => 'Cebu Van Tours',
                'location' => 'Lapu-Lapu · near Airport',
                'rating' => 4.9,
                'image' => 'van.jpg',
            ],
        ];
    }
}
