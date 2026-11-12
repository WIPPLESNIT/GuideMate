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
                    l.title AS listing_title, l.slug AS listing_slug, l.category_id,
                    c.slug AS category_slug, c.name AS category_name,
                    u.name AS tourist_name, u.email AS tourist_email,
                    g.name AS guide_name, g.email AS guide_email, g.role AS provider_role,
                    g.guide_warned, g.guide_warning_note,
                    p.status AS payment_status, p.reference AS payment_reference
             FROM disputes d
             JOIN bookings b ON b.id = d.booking_id
             JOIN listings l ON l.id = b.listing_id
             LEFT JOIN categories c ON c.id = l.category_id
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
                    l.title AS listing_title, l.slug AS listing_slug, l.category_id,
                    c.slug AS category_slug, c.name AS category_name,
                    u.name AS tourist_name, u.email AS tourist_email,
                    g.name AS guide_name, g.email AS guide_email, g.role AS provider_role,
                    g.guide_warned, g.guide_warning_note
             FROM disputes d
             JOIN bookings b ON b.id = d.booking_id
             JOIN listings l ON l.id = b.listing_id
             LEFT JOIN categories c ON c.id = l.category_id
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
                       l.title AS listing_title, l.slug AS listing_slug, l.category_id,
                       c.slug AS category_slug, c.name AS category_name,
                       u.name AS tourist_name, u.email AS tourist_email,
                       g.name AS guide_name, g.email AS guide_email, g.role AS provider_role,
                       g.guide_warned, g.guide_warning_note
                FROM disputes d
                JOIN bookings b ON b.id = d.booking_id
                JOIN listings l ON l.id = b.listing_id
                LEFT JOIN categories c ON c.id = l.category_id
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
     * Resolve provider account details and contextual action labels.
     *
     * @param array<string, mixed> $dispute
     * @return array{type:string,account_type_label:string,provider_title:string,warn_btn_label:string,badge_class:string}
     */
    public static function providerAccountInfo(array $dispute): array
    {
        $role = (string) ($dispute['provider_role'] ?? '');
        $catSlug = (string) ($dispute['category_slug'] ?? '');

        if (in_array($role, ['hotel_provider', 'hotel_admin'], true) || $catSlug === 'hotels') {
            return [
                'type' => 'hotel_provider',
                'account_type_label' => 'Hotel Provider Account',
                'provider_title' => 'Hotel Provider',
                'warn_btn_label' => 'Warn Hotel Provider',
                'badge_class' => 'pill-hotel',
            ];
        }

        if (in_array($role, ['rental_provider', 'rental_admin'], true) || in_array($catSlug, ['vehicles', 'vehicle-rentals', 'rentals'], true)) {
            return [
                'type' => 'rental_provider',
                'account_type_label' => 'Vehicle Rental Provider Account',
                'provider_title' => 'Vehicle Rental Provider',
                'warn_btn_label' => 'Warn Vehicle Rental Provider',
                'badge_class' => 'pill-rental',
            ];
        }

        return [
            'type' => 'guide',
            'account_type_label' => 'Guide Account',
            'provider_title' => 'Tour Guide',
            'warn_btn_label' => 'Warn Guide',
            'badge_class' => 'pill-guide',
        ];
    }

    /**
     * Determine whether a complaint has already been reviewed by an administrator.
     */
    public static function isReviewed(string|array $disputeOrStatus): bool
    {
        $status = is_array($disputeOrStatus) ? (string) ($disputeOrStatus['status'] ?? '') : $disputeOrStatus;
        return in_array($status, ['resolved_refund', 'resolved_warning', 'rejected'], true);
    }

    /**
     * Delete a complaint record and its attached physical evidence files.
     */
    public static function delete(int $id): bool
    {
        $files = DisputeFile::forDispute($id);
        foreach ($files as $f) {
            if (!empty($f['file_path'])) {
                $abs = dirname(__DIR__, 2) . '/public/' . ltrim((string) $f['file_path'], '/');
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
        }
        return Database::run("DELETE FROM disputes WHERE id = ?", [$id]) > 0;
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
