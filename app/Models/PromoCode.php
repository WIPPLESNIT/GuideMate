<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use Throwable;

final class PromoCode
{
    /** @var array<string, array<string, mixed>> */
    private const SALE_CATALOG = [
        'CEBU6' => [
            'label' => '6% OFF',
            'description' => 'Sitewide on Cebu tours',
            'min_spend' => 8000,
            'discount_percent' => 6,
            'discount_amount' => null,
            'valid_until' => '2026-12-31',
        ],
        'ISLAND300' => [
            'label' => '₱300 OFF',
            'description' => 'Island hopping packages',
            'min_spend' => 3000,
            'discount_percent' => null,
            'discount_amount' => 300,
            'valid_until' => '2026-09-30',
        ],
        'STAY10' => [
            'label' => '10% OFF',
            'description' => 'Hotels & resorts',
            'min_spend' => 5000,
            'discount_percent' => 10,
            'discount_amount' => null,
            'valid_until' => '2026-11-15',
        ],
    ];

    private static bool $ready = false;

    public static function ensureTables(): void
    {
        if (self::$ready) {
            return;
        }

        Database::run(
            'CREATE TABLE IF NOT EXISTS promo_codes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(40) NOT NULL,
                discount_percent DECIMAL(5,2) DEFAULT NULL,
                discount_amount DECIMAL(10,2) DEFAULT NULL,
                min_spend DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                valid_from DATE DEFAULT NULL,
                valid_until DATE DEFAULT NULL,
                max_uses INT UNSIGNED DEFAULT NULL,
                uses_count INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY promo_codes_code_unique (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        Database::run(
            'CREATE TABLE IF NOT EXISTS voucher_redemptions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                booking_id INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY voucher_redemptions_user_code (user_id, code),
                KEY voucher_redemptions_booking (booking_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        try {
            Database::run('ALTER TABLE promo_codes ADD COLUMN min_spend DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        } catch (Throwable) {
            // column already exists
        }

        foreach (self::SALE_CATALOG as $code => $item) {
            Database::run(
                'INSERT IGNORE INTO promo_codes (code, discount_percent, discount_amount, min_spend, valid_until, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)',
                [
                    $code,
                    $item['discount_percent'],
                    $item['discount_amount'],
                    $item['min_spend'],
                    $item['valid_until'],
                ]
            );
        }

        self::$ready = true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function catalogForUser(?int $userId): array
    {
        self::ensureTables();
        $redeemed = [];
        $used = [];
        if ($userId !== null) {
            $rows = Database::all('SELECT code, booking_id FROM voucher_redemptions WHERE user_id = ?', [$userId]);
            foreach ($rows as $r) {
                $c = strtoupper((string) $r['code']);
                $redeemed[] = $c;
                if ($r['booking_id'] !== null) {
                    $used[] = $c;
                }
            }
        }
        $out = [];
        foreach (self::SALE_CATALOG as $code => $item) {
            $c = strtoupper($code);
            $until = (string) $item['valid_until'];
            $isRedeemed = in_array($c, $redeemed, true);
            $isUsed = in_array($c, $used, true);
            $out[] = [
                'code' => $code,
                'label' => (string) $item['label'],
                'description' => (string) $item['description'],
                'min_spend' => (float) $item['min_spend'],
                'discount_percent' => $item['discount_percent'],
                'discount_amount' => $item['discount_amount'],
                'expires' => 'Valid until ' . date('M j', strtotime($until)),
                'redeemed' => $isRedeemed,
                'used' => $isUsed,
            ];
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    public static function codesRedeemedBy(int $userId): array
    {
        self::ensureTables();
        $rows = Database::all(
            'SELECT code FROM voucher_redemptions WHERE user_id = ?',
            [$userId]
        );
        return array_map(static fn (array $row): string => strtoupper((string) $row['code']), $rows);
    }

    public static function userHasRedeemed(int $userId, string $code): bool
    {
        self::ensureTables();
        $row = Database::first(
            'SELECT id FROM voucher_redemptions WHERE user_id = ? AND code = ? LIMIT 1',
            [$userId, strtoupper(trim($code))]
        );
        return $row !== null;
    }

    public static function userHasUsed(int $userId, string $code): bool
    {
        self::ensureTables();
        $row = Database::first(
            'SELECT id FROM voucher_redemptions WHERE user_id = ? AND code = ? AND booking_id IS NOT NULL LIMIT 1',
            [$userId, strtoupper(trim($code))]
        );
        return $row !== null;
    }

    /**
     * Redeem a voucher to a user account.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public static function redeemByUser(int $userId, string $code): array
    {
        self::ensureTables();
        $normalized = strtoupper(trim($code));
        $promo = self::findByCode($normalized);
        if ($promo === null) {
            return ['success' => false, 'error' => 'Invalid voucher code.'];
        }

        $existing = Database::first(
            'SELECT id, booking_id FROM voucher_redemptions WHERE user_id = ? AND code = ? LIMIT 1',
            [$userId, $normalized]
        );
        if ($existing !== null) {
            return ['success' => false, 'error' => 'You have already redeemed this voucher.'];
        }

        try {
            Database::insert(
                'INSERT INTO voucher_redemptions (user_id, code, booking_id) VALUES (?, ?, NULL)',
                [$userId, $normalized]
            );
            return ['success' => true, 'message' => 'Voucher successfully redeemed to your account!'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Failed to redeem voucher.'];
        }
    }

    /**
     * Mark a redeemed voucher as used in a booking.
     */
    public static function markRedemptionUsed(int $userId, string $code, int $bookingId): void
    {
        self::ensureTables();
        $normalized = strtoupper(trim($code));
        $existing = Database::first(
            'SELECT id FROM voucher_redemptions WHERE user_id = ? AND code = ? LIMIT 1',
            [$userId, $normalized]
        );
        if ($existing !== null) {
            Database::run(
                'UPDATE voucher_redemptions SET booking_id = ? WHERE id = ?',
                [$bookingId, $existing['id']]
            );
        } else {
            try {
                Database::insert(
                    'INSERT INTO voucher_redemptions (user_id, code, booking_id) VALUES (?, ?, ?)',
                    [$userId, $normalized, $bookingId]
                );
            } catch (Throwable) {}
        }
    }

    public static function recordRedemption(int $userId, string $code, int $bookingId): void
    {
        self::markRedemptionUsed($userId, $code, $bookingId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByCode(string $code): ?array
    {
        self::ensureTables();
        return Database::first(
            'SELECT * FROM promo_codes WHERE code = ? AND is_active = 1',
            [strtoupper(trim($code))]
        );
    }

    /**
     * @return array{valid: bool, discount: float, id: ?int, message: string}
     */
    public static function validate(string $code, float $subtotal, ?int $userId = null): array
    {
        $normalized = strtoupper(trim($code));
        $row = self::findByCode($normalized);
        if ($row === null) {
            return ['valid' => false, 'discount' => 0.0, 'id' => null, 'message' => 'Invalid promo code.'];
        }

        if ($userId !== null) {
            // Check if user has redeemed from Deals & Offers
            if (!self::userHasRedeemed($userId, $normalized)) {
                return [
                    'valid' => false,
                    'discount' => 0.0,
                    'id' => null,
                    'message' => 'You must redeem this voucher from Deals & Offers before using it.',
                ];
            }
            // Check if already used in a booking
            if (self::userHasUsed($userId, $normalized)) {
                return [
                    'valid' => false,
                    'discount' => 0.0,
                    'id' => null,
                    'message' => 'You have already used this voucher on a previous booking.',
                ];
            }
        }

        $today = date('Y-m-d');
        if (!empty($row['valid_from']) && $today < $row['valid_from']) {
            return ['valid' => false, 'discount' => 0.0, 'id' => null, 'message' => 'This promo code is not active yet.'];
        }
        if (!empty($row['valid_until']) && $today > $row['valid_until']) {
            return ['valid' => false, 'discount' => 0.0, 'id' => null, 'message' => 'This promo code has expired.'];
        }
        if ($row['max_uses'] !== null && (int) $row['uses_count'] >= (int) $row['max_uses']) {
            return ['valid' => false, 'discount' => 0.0, 'id' => null, 'message' => 'This promo code has reached its usage limit.'];
        }

        $minSpend = (float) ($row['min_spend'] ?? 0);
        if ($minSpend > 0 && $subtotal < $minSpend) {
            return [
                'valid' => false,
                'discount' => 0.0,
                'id' => (int) $row['id'],
                'message' => 'This voucher needs a minimum spend of ₱' . number_format($minSpend, 0) . '.',
            ];
        }

        $discount = 0.0;
        if ($row['discount_percent'] !== null) {
            $discount = round($subtotal * ((float) $row['discount_percent'] / 100), 2);
        } elseif ($row['discount_amount'] !== null) {
            $discount = min($subtotal, (float) $row['discount_amount']);
        }

        return ['valid' => true, 'discount' => $discount, 'id' => (int) $row['id'], 'message' => 'Promo applied.'];
    }

    public static function incrementUse(int $id): void
    {
        Database::run('UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = ?', [$id]);
    }
}
