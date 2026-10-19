<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

try {
    Database::run("
        CREATE TABLE IF NOT EXISTS `vehicles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `make` VARCHAR(80) NOT NULL,
            `model` VARCHAR(80) NOT NULL,
            `year` INT NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `name` VARCHAR(160) NOT NULL,
            `slug` VARCHAR(180) NOT NULL,
            `description` TEXT NOT NULL,
            `specs` TEXT NULL,
            `location` VARCHAR(160) NOT NULL,
            `address` VARCHAR(255) NULL,
            `price_per_day` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `image` VARCHAR(255) NULL,
            `status` ENUM('draft', 'pending', 'approved', 'rejected', 'unavailable') NOT NULL DEFAULT 'pending',
            `is_available` TINYINT(1) NOT NULL DEFAULT 1,
            `rejection_reason` VARCHAR(500) NULL,
            `submitted_at` TIMESTAMP NULL DEFAULT NULL,
            `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
            `reviewed_by` INT UNSIGNED NULL DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `vehicles_slug_unique` (`slug`),
            KEY `idx_vehicles_user_id` (`user_id`),
            KEY `idx_vehicles_status` (`status`),
            KEY `idx_vehicles_is_available` (`is_available`),
            KEY `idx_vehicles_type` (`type`),
            CONSTRAINT `fk_vehicles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_vehicles_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    Database::run("
        CREATE TABLE IF NOT EXISTS `vehicle_images` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `vehicle_id` INT UNSIGNED NOT NULL,
            `image_path` VARCHAR(255) NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_vehicle_images_vehicle_id` (`vehicle_id`),
            CONSTRAINT `fk_vehicle_images_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "Vehicles migration completed successfully.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
