<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "Running cancellation and refunds migration patch...\n";

function columnExists(string $table, string $column): bool {
    $row = Database::first(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$table, $column]
    );
    return $row !== null;
}

try {
    if (!columnExists('bookings', 'cancelled_at')) {
        Database::run("ALTER TABLE `bookings` ADD COLUMN `cancelled_at` DATETIME NULL AFTER `updated_at`");
        echo "Added bookings.cancelled_at\n";
    }
    if (!columnExists('bookings', 'cancellation_reason')) {
        Database::run("ALTER TABLE `bookings` ADD COLUMN `cancellation_reason` VARCHAR(255) NULL AFTER `cancelled_at`");
        echo "Added bookings.cancellation_reason\n";
    }
    if (!columnExists('bookings', 'refund_amount')) {
        Database::run("ALTER TABLE `bookings` ADD COLUMN `refund_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `cancellation_reason`");
        echo "Added bookings.refund_amount\n";
    }
    if (!columnExists('bookings', 'refund_status')) {
        Database::run("ALTER TABLE `bookings` ADD COLUMN `refund_status` VARCHAR(50) DEFAULT NULL AFTER `refund_amount`");
        echo "Added bookings.refund_status\n";
    }
    if (!columnExists('bookings', 'cancellation_policy')) {
        Database::run("ALTER TABLE `bookings` ADD COLUMN `cancellation_policy` VARCHAR(20) DEFAULT NULL AFTER `refund_status`");
        echo "Added bookings.cancellation_policy\n";
    }

    if (!columnExists('rental_requests', 'cancelled_at')) {
        Database::run("ALTER TABLE `rental_requests` ADD COLUMN `cancelled_at` DATETIME NULL AFTER `updated_at`");
        echo "Added rental_requests.cancelled_at\n";
    }
    if (!columnExists('rental_requests', 'cancellation_reason')) {
        Database::run("ALTER TABLE `rental_requests` ADD COLUMN `cancellation_reason` VARCHAR(255) NULL AFTER `cancelled_at`");
        echo "Added rental_requests.cancellation_reason\n";
    }
    if (!columnExists('rental_requests', 'refund_amount')) {
        Database::run("ALTER TABLE `rental_requests` ADD COLUMN `refund_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `cancellation_reason`");
        echo "Added rental_requests.refund_amount\n";
    }
    if (!columnExists('rental_requests', 'refund_status')) {
        Database::run("ALTER TABLE `rental_requests` ADD COLUMN `refund_status` VARCHAR(50) DEFAULT NULL AFTER `refund_amount`");
        echo "Added rental_requests.refund_status\n";
    }
    if (!columnExists('rental_requests', 'cancellation_policy')) {
        Database::run("ALTER TABLE `rental_requests` ADD COLUMN `cancellation_policy` VARCHAR(20) DEFAULT '24h' AFTER `refund_status`");
        echo "Added rental_requests.cancellation_policy\n";
    }

    echo "Migration completed successfully!\n";
} catch (\Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
