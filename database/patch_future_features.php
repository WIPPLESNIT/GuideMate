<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

$statements = [
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `password_resets` (
    `email` VARCHAR(190) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `guide_availability` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id` INT UNSIGNED NOT NULL,
    `blocked_date` DATE NOT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `guide_availability_unique` (`listing_id`, `blocked_date`),
    CONSTRAINT `guide_availability_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `promo_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `discount_percent` DECIMAL(5,2) DEFAULT NULL,
    `discount_amount` DECIMAL(10,2) DEFAULT NULL,
    `valid_from` DATE DEFAULT NULL,
    `valid_until` DATE DEFAULT NULL,
    `max_uses` INT UNSIGNED DEFAULT NULL,
    `uses_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `promo_codes_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `review_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `review_images_review_fk` (`review_id`),
    CONSTRAINT `review_images_review_fk` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `dispute_files` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dispute_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `label` VARCHAR(190) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `dispute_files_dispute_fk` (`dispute_id`),
    CONSTRAINT `dispute_files_dispute_fk` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(80) NOT NULL,
    `entity_type` VARCHAR(40) NOT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `meta` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `audit_logs_admin_fk` (`admin_id`),
    CONSTRAINT `audit_logs_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'ALTER TABLE bookings ADD COLUMN verify_token VARCHAR(64) DEFAULT NULL',
    'ALTER TABLE bookings ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL',
    'ALTER TABLE bookings ADD COLUMN promo_code_id INT UNSIGNED DEFAULT NULL',
    'ALTER TABLE bookings ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00',
    'ALTER TABLE bookings ADD COLUMN reminder_sent TINYINT(1) NOT NULL DEFAULT 0',
    // Start time of the experience, so a listing can be booked per date + time slot.
    'ALTER TABLE bookings ADD COLUMN booking_time VARCHAR(5) DEFAULT NULL',
    'ALTER TABLE listings ADD COLUMN sale_price DECIMAL(10,2) DEFAULT NULL',
    'ALTER TABLE listings ADD COLUMN sale_ends_at DATE DEFAULT NULL',
    // Rental Partner + Hotel Partner provider roles (self-registerable dashboards).
    "ALTER TABLE users MODIFY COLUMN role ENUM('admin','guide','tourist','rental_admin','hotel_admin') NOT NULL DEFAULT 'tourist'",
    'ALTER TABLE users ADD COLUMN admin_totp_secret VARCHAR(64) DEFAULT NULL',
    'ALTER TABLE users ADD COLUMN admin_totp_enabled TINYINT(1) NOT NULL DEFAULT 0',
    // Presence for the mobile chat (updated on every authenticated API request).
    'ALTER TABLE users ADD COLUMN last_seen_at TIMESTAMP NULL DEFAULT NULL',
    'ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL',
    'ALTER TABLE users ADD COLUMN login_count INT UNSIGNED NOT NULL DEFAULT 0',
    // Detected original language of a chat message (ISO-639-1), filled lazily.
    'ALTER TABLE messages ADD COLUMN source_lang VARCHAR(8) DEFAULT NULL',
    // Cache of auto-translated chat messages so we only call the provider once.
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `message_translations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_id` INT UNSIGNED NOT NULL,
    `target_lang` VARCHAR(8) NOT NULL,
    `body` TEXT NOT NULL,
    `source_lang` VARCHAR(8) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `message_translations_unique` (`message_id`, `target_lang`),
    CONSTRAINT `message_translations_message_fk` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    // ── Rentals: online (full-payment) reservation, held ID, and refunds ──
    // Full up-front payment recorded against the reservation.
    "ALTER TABLE rental_requests ADD COLUMN payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid'",
    'ALTER TABLE rental_requests ADD COLUMN payment_method VARCHAR(40) DEFAULT NULL',
    'ALTER TABLE rental_requests ADD COLUMN payment_reference VARCHAR(80) DEFAULT NULL',
    'ALTER TABLE rental_requests ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL',
    // Valid ID / important details, held for the rental owner until the unit is returned.
    'ALTER TABLE rental_requests ADD COLUMN id_document VARCHAR(255) DEFAULT NULL',
    'ALTER TABLE rental_requests ADD COLUMN id_type VARCHAR(80) DEFAULT NULL',
    'ALTER TABLE rental_requests ADD COLUMN id_number VARCHAR(120) DEFAULT NULL',
    // A single tourist-filed problem report per reservation, with refund tracking.
    'ALTER TABLE rental_requests ADD COLUMN report_type VARCHAR(40) DEFAULT NULL',
    'ALTER TABLE rental_requests ADD COLUMN report_message VARCHAR(1000) DEFAULT NULL',
    "ALTER TABLE rental_requests ADD COLUMN report_status ENUM('none','open','refunded','rejected') NOT NULL DEFAULT 'none'",
    'ALTER TABLE rental_requests ADD COLUMN report_created_at TIMESTAMP NULL DEFAULT NULL',
    'ALTER TABLE rental_requests ADD COLUMN owner_report_note VARCHAR(500) DEFAULT NULL',
    // Allow a refunded state on the reservation lifecycle.
    "ALTER TABLE rental_requests MODIFY COLUMN status ENUM('pending','approved','contacted','cancelled','completed','refunded') NOT NULL DEFAULT 'pending'",
    // ── Social sign-in (Google / Facebook), stored anonymously ──
    // We keep only the provider + an opaque provider user id. The real email
    // and password are NEVER stored, so admins can never see them.
    "ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(20) DEFAULT NULL",
    'ALTER TABLE users ADD COLUMN oauth_id VARCHAR(191) DEFAULT NULL',
    'ALTER TABLE users ADD UNIQUE KEY users_oauth_unique (oauth_provider, oauth_id)',
    // ── Schedule list ──
    // Guides plot the sessions they actually run (date + start time), instead of
    // only blocking unavailable dates. Travelers can see when a tour is offered.
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `listing_schedules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id` INT UNSIGNED NOT NULL,
    `schedule_date` DATE NOT NULL,
    `start_time` VARCHAR(5) DEFAULT NULL,
    `capacity` INT UNSIGNED DEFAULT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `listing_schedules_listing_fk` (`listing_id`),
    CONSTRAINT `listing_schedules_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

foreach ($statements as $sql) {
    try {
        Database::run($sql);
        echo "OK\n";
    } catch (Throwable $e) {
        echo 'SKIP: ' . $e->getMessage() . "\n";
    }
}

echo "Future features schema patch complete.\n";
