<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

try {
    Database::run("
        CREATE TABLE IF NOT EXISTS `rental_reviews` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `rental_id` INT UNSIGNED NOT NULL,
            `reviewer_id` INT UNSIGNED NOT NULL,
            `provider_id` INT UNSIGNED NOT NULL,
            `vehicle_id` VARCHAR(50) NULL,
            `rating` TINYINT UNSIGNED NOT NULL,
            `feedback` TEXT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_rental_review` (`rental_id`),
            KEY `idx_provider_id` (`provider_id`),
            KEY `idx_reviewer_id` (`reviewer_id`),
            KEY `idx_vehicle_id` (`vehicle_id`),
            CONSTRAINT `fk_rental_reviews_rental` FOREIGN KEY (`rental_id`) REFERENCES `rental_requests` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rental_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rental_reviews_provider` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "Table rental_reviews created successfully.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
