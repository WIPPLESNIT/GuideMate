<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "=== Running Travel Package Builder Schema Migration ===\n";

try {
    // 1. Ensure Travel Packages category
    $existing = Database::first("SELECT id FROM categories WHERE slug = 'packages'");
    if (!$existing) {
        Database::run(
            'INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (?, ?, ?, ?, ?)',
            ['Travel Packages', 'packages', 'cube', 'All-inclusive multi-service trips curated and hosted by local guides', 5]
        );
        echo "✓ Category 'Travel Packages' created.\n";
    }

    // 2. package_services table
    Database::run("
        CREATE TABLE IF NOT EXISTS `package_services` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `package_id` INT UNSIGNED NOT NULL,
            `provider_id` INT UNSIGNED NOT NULL,
            `service_type` ENUM('hotel', 'vehicle', 'activity', 'attraction') NOT NULL,
            `hotel_room_id` INT UNSIGNED NULL,
            `vehicle_id` INT UNSIGNED NULL,
            `listing_id` INT UNSIGNED NULL,
            `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            `rejection_reason` VARCHAR(500) NULL,
            `agreed_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pkg_services_pkg` (`package_id`),
            KEY `idx_pkg_services_provider` (`provider_id`, `status`),
            CONSTRAINT `fk_pkg_serv_package` FOREIGN KEY (`package_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pkg_serv_provider` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'package_services' verified / created.\n";

    // 3. package_itineraries table
    Database::run("
        CREATE TABLE IF NOT EXISTS `package_itineraries` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `package_id` INT UNSIGNED NOT NULL,
            `day_number` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `highlights` TEXT NULL,
            `schedule` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_pkg_itinerary_day` (`package_id`, `day_number`),
            CONSTRAINT `fk_pkg_itin_package` FOREIGN KEY (`package_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'package_itineraries' verified / created.\n";

    // 4. package_booking_items table
    Database::run("
        CREATE TABLE IF NOT EXISTS `package_booking_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `parent_booking_id` INT UNSIGNED NOT NULL,
            `package_id` INT UNSIGNED NOT NULL,
            `provider_id` INT UNSIGNED NOT NULL,
            `service_type` ENUM('hotel', 'vehicle') NOT NULL,
            `hotel_booking_id` INT UNSIGNED NULL,
            `rental_request_id` INT UNSIGNED NULL,
            `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pkg_bk_parent` (`parent_booking_id`),
            KEY `idx_pkg_bk_provider` (`provider_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'package_booking_items' verified / created.\n";

    echo "=== Migration Finished Successfully ===\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
