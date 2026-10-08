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
        return Database::first('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC', [$bookingId]);
    }

    public static function totalRevenue(): float
    {
        $row = Database::first(
            'SELECT COALESCE(SUM(p.amount), 0) AS total
             FROM payments p
             JOIN bookings b ON b.id = p.booking_id
             WHERE p.status = "paid" AND b.status IN ("confirmed", "completed")'
        );
        return (float) ($row['total'] ?? 0);
    }

    public static function refund(int $bookingId): void
    {
        Database::run('UPDATE payments SET status = "refunded" WHERE booking_id = ? AND status = "paid"', [$bookingId]);
    }

    public static function isPaid(int $bookingId): bool
    {
        $payment = self::forBooking($bookingId);
        return $payment !== null && $payment['status'] === 'paid';
    }
}
