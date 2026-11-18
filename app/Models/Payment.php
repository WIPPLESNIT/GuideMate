<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Payment
{
    /**
     * Record a (simulated) payment for a booking and mark it paid.
     */
    public static function create(int $bookingId, float $amount, string $method, string $status = 'paid'): int
    {
        $reference = 'GM-' . strtoupper(bin2hex(random_bytes(5)));
        $paidAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;
        return Database::insert(
            'INSERT INTO payments (booking_id, amount, method, status, reference, paid_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$bookingId, $amount, $method, $status, $reference, $paidAt]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forBooking(int $bookingId): ?array
    {
        return Database::first('SELECT * FROM payments WHERE booking_id = ? ORDER BY (CASE WHEN status = "paid" THEN 0 ELSE 1 END), id DESC LIMIT 1', [$bookingId]);
    }

    public static function totalRevenue(): float
    {
        return \App\Services\RevenueService::getAdminTotalRevenue();
    }

    public static function refund(int $bookingId): void
    {
        Database::run('UPDATE payments SET status = "refunded" WHERE booking_id = ? AND status = "paid"', [$bookingId]);
    }

    public static function isPaid(int $bookingId): bool
    {
        $row = Database::first('SELECT id FROM payments WHERE booking_id = ? AND status = "paid" LIMIT 1', [$bookingId]);
        return $row !== null;
    }
}
