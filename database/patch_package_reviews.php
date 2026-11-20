<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

try {
    // 1. Ensure booking_id exists with UNIQUE KEY on reviews table
    $columns = Database::all("SHOW COLUMNS FROM `reviews` LIKE 'booking_id'");
    if (empty($columns)) {
        Database::run("ALTER TABLE `reviews` ADD COLUMN `booking_id` INT UNSIGNED DEFAULT NULL AFTER `id`");
        Database::run("ALTER TABLE `reviews` ADD UNIQUE KEY `unique_booking_review` (`booking_id`)");
        echo "Added booking_id column and unique constraint to reviews table.\n";
    }

    // 2. Backfill existing reviews where booking_id IS NULL to matching completed bookings
    $unlinkedReviews = Database::all("SELECT id, listing_id, user_id FROM reviews WHERE booking_id IS NULL ORDER BY id ASC");
    $linkedCount = 0;
    foreach ($unlinkedReviews as $rev) {
        $booking = Database::first("
            SELECT b.id FROM bookings b
            WHERE b.listing_id = ?
              AND b.user_id = ?
              AND (b.status = 'completed' OR b.package_overall_status = 'completed')
              AND NOT EXISTS (SELECT 1 FROM reviews r2 WHERE r2.booking_id = b.id)
            ORDER BY b.id ASC
            LIMIT 1
        ", [$rev['listing_id'], $rev['user_id']]);

        if ($booking !== null) {
            Database::run("UPDATE reviews SET booking_id = ? WHERE id = ?", [(int) $booking['id'], (int) $rev['id']]);
            $linkedCount++;
            echo "Linked review #{$rev['id']} to booking #{$booking['id']}.\n";
        }
    }

    echo "patch_package_reviews completed successfully. Linked {$linkedCount} reviews.\n";
} catch (Throwable $e) {
    echo "patch_package_reviews error: " . $e->getMessage() . "\n";
    exit(1);
}
