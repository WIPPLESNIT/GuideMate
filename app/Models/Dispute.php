<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Dispute
{
    public const TYPES = [
        'extra_payment' => 'Guide asked for extra payment',
        'no_show' => 'Guide did not show up',
        'service_mismatch' => 'Service was very different from listing',
        'unsafe' => 'Unsafe or unprofessional behavior',
        'other' => 'Other problem',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'reviewing' => 'Under review',
        'resolved_refund' => 'Refunded',
        'resolved_warning' => 'Guide warned',
        'rejected' => 'Rejected',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO disputes (booking_id, user_id, guide_id, problem_type, amount_requested, description)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['booking_id'],
                $data['user_id'],
                $data['guide_id'],
                $data['problem_type'],
                $data['amount_requested'],
                $data['description'],
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT d.*, b.booking_date, b.total_amount, b.status AS booking_status,
                    l.title AS listing_title, l.slug AS listing_slug,
                    u.name AS tourist_name, u.email AS tourist_email,
                    g.name AS guide_name, g.email AS guide_email,
                    p.status AS payment_status, p.reference AS payment_reference
             FROM disputes d
             JOIN bookings b ON b.id = d.booking_id
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = d.user_id
             JOIN users g ON g.id = d.guide_id
             LEFT JOIN payments p ON p.booking_id = b.id
             WHERE d.id = ?',
            [$id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forBooking(int $bookingId): ?array
    {
        return Database::first('SELECT * FROM disputes WHERE booking_id = ?', [$bookingId]);
    }

    /**
     * Disputes awaiting admin action (open or under review).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pending(): array
    {
        return Database::all(
            'SELECT d.*, b.booking_date, b.total_amount,
                    l.title AS listing_title,
                    u.name AS tourist_name, g.name AS guide_name
             FROM disputes d
             JOIN bookings b ON b.id = d.booking_id
             JOIN listings l ON l.id = b.listing_id
             JOIN users u ON u.id = d.user_id
             JOIN users g ON g.id = d.guide_id
             WHERE d.status IN ("open", "reviewing")
             ORDER BY d.created_at DESC'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(?string $status = null): array
    {
        $sql = 'SELECT d.*, b.booking_date, b.total_amount,
                       l.title AS listing_title,
                       u.name AS tourist_name, g.name AS guide_name
                FROM disputes d
                JOIN bookings b ON b.id = d.booking_id
                JOIN listings l ON l.id = b.listing_id
                JOIN users u ON u.id = d.user_id
                JOIN users g ON g.id = d.guide_id';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE d.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY FIELD(d.status, "open", "reviewing", "resolved_refund", "resolved_warning", "rejected"), d.created_at DESC';
        return Database::all($sql, $params);
    }

    public static function countOpen(): int
    {
        $row = Database::first('SELECT COUNT(*) AS c FROM disputes WHERE status IN ("open","reviewing")');
        return (int) ($row['c'] ?? 0);
    }

    public static function resolve(int $id, string $status, ?string $adminNote = null): void
    {
        $resolvedAt = in_array($status, ['resolved_refund', 'resolved_warning', 'rejected'], true)
            ? date('Y-m-d H:i:s')
            : null;
        Database::run(
            'UPDATE disputes SET status = ?, admin_note = ?, resolved_at = ? WHERE id = ?',
            [$status, $adminNote, $resolvedAt, $id]
        );
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * @return array{bookings:int,disputes:int,rate:float}
     */
    public static function rateStats(): array
    {
        $bookings = (int) (Database::first(
            'SELECT COUNT(*) AS c FROM bookings WHERE status IN ("confirmed","completed","disputed","refunded")'
        )['c'] ?? 0);
        $disputes = (int) (Database::first('SELECT COUNT(*) AS c FROM disputes')['c'] ?? 0);
        return [
            'bookings' => $bookings,
            'disputes' => $disputes,
            'rate' => $bookings > 0 ? round($disputes / $bookings * 100, 1) : 0.0,
        ];
    }
}
