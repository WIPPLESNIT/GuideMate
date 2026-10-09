-- GuideMate database schema (MySQL / MariaDB)
-- A TripAdvisor-style travel platform scoped to Cebu, Philippines.
--
-- You can import this file directly in phpMyAdmin, or run:
--   php database/migrate.php
-- which creates the database, applies this schema, and seeds Cebu sample data.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users: admins, tour guides (providers), and tourists (customers)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120) NOT NULL,
    `email`       VARCHAR(190) NOT NULL,
    `password`    VARCHAR(255) NOT NULL,
    `role`        ENUM('admin','guide','tourist','rental_admin','hotel_admin') NOT NULL DEFAULT 'tourist',
    `phone`       VARCHAR(40)  DEFAULT NULL,
    `avatar`      VARCHAR(255) DEFAULT NULL,
    `bio`         TEXT         DEFAULT NULL,
    `location`    VARCHAR(120) DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `suspension_reason` VARCHAR(500) DEFAULT NULL,
    `suspended_at`      TIMESTAMP    NULL DEFAULT NULL,
    -- Provider verification workflow: tourists/admins stay 'none'; guides,
    -- rental admins and hotel admins start 'pending' until an admin reviews
    -- their submitted documents.
    `guide_status`      ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
    `guide_review_note` VARCHAR(500) DEFAULT NULL,
    `guide_reviewed_at` TIMESTAMP    NULL DEFAULT NULL,
    `guide_warned`      TINYINT(1)   NOT NULL DEFAULT 0,
    `guide_warning_note` VARCHAR(500) DEFAULT NULL,
    `admin_totp_secret` VARCHAR(64) DEFAULT NULL,
    `admin_totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_seen_at` TIMESTAMP   NULL DEFAULT NULL,
    `last_login_at` TIMESTAMP  NULL DEFAULT NULL,
    `login_count` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- guide_documents: verification files a guide submits (ID, license,
-- accreditation, certificates, employment proof, association membership, etc.)
-- An admin reviews these to approve or reject the guide's application.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `guide_documents`;
CREATE TABLE `guide_documents` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `doc_type`   VARCHAR(60)  NOT NULL,
    `label`      VARCHAR(190) DEFAULT NULL,
    `file_path`  VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `guide_documents_user_fk` (`user_id`),
    CONSTRAINT `guide_documents_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- categories: main sections (Things to Do, Tour Guides, Hotels, Restaurants)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(80)  NOT NULL,
    `slug`        VARCHAR(80)  NOT NULL,
    `icon`        VARCHAR(40)  DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- listings: tours/activities, guides, hotels, restaurants in Cebu
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `listings`;
CREATE TABLE `listings` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `category_id`  INT UNSIGNED NOT NULL,
    `title`        VARCHAR(160) NOT NULL,
    `slug`         VARCHAR(180) NOT NULL,
    `summary`      VARCHAR(255) DEFAULT NULL,
    `description`  TEXT         NOT NULL,
    `area`         VARCHAR(120) NOT NULL,
    `address`      VARCHAR(255) DEFAULT NULL,
    `latitude`     DECIMAL(10,7) DEFAULT NULL,
    `longitude`    DECIMAL(10,7) DEFAULT NULL,
    `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `price_unit`   VARCHAR(40)  NOT NULL DEFAULT 'per person',
    `duration`     VARCHAR(80)  DEFAULT NULL,
    `included`     TEXT         DEFAULT NULL,
    `not_included` TEXT         DEFAULT NULL,
    `cover_image`  VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `is_featured`  TINYINT(1)   NOT NULL DEFAULT 0,
    `sale_price`   DECIMAL(10,2) DEFAULT NULL,
    `sale_ends_at` DATE DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `listings_slug_unique` (`slug`),
    KEY `listings_user_fk` (`user_id`),
    KEY `listings_category_fk` (`category_id`),
    KEY `listings_status_idx` (`status`),
    CONSTRAINT `listings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `listings_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- listing_images: gallery photos for a listing
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `listing_images`;
CREATE TABLE `listing_images` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `listing_images_listing_fk` (`listing_id`),
    CONSTRAINT `listing_images_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- bookings: a tourist reserving a tour/guide/stay/table
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id`   INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NOT NULL,
    `booking_date` DATE NOT NULL,
    `booking_time` TIME DEFAULT NULL,
    `booking_end_time` TIME DEFAULT NULL,
    `booking_dates` TEXT DEFAULT NULL,
    `guests`       INT NOT NULL DEFAULT 1,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status`       ENUM('pending','confirmed','completed','cancelled','disputed','refunded') NOT NULL DEFAULT 'pending',
    `notes`        VARCHAR(500) DEFAULT NULL,
    `verify_token` VARCHAR(64) DEFAULT NULL,
    `verified_at`  TIMESTAMP NULL DEFAULT NULL,
    `promo_code_id` INT UNSIGNED DEFAULT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `bookings_listing_fk` (`listing_id`),
    KEY `bookings_user_fk` (`user_id`),
    CONSTRAINT `bookings_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- payments: a (simulated) transaction tied to a booking
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT UNSIGNED NOT NULL,
    `amount`     DECIMAL(10,2) NOT NULL,
    `method`     VARCHAR(40) NOT NULL DEFAULT 'card',
    `status`     ENUM('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
    `reference`  VARCHAR(64) NOT NULL,
    `paid_at`    TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payments_reference_unique` (`reference`),
    KEY `payments_booking_fk` (`booking_id`),
    CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- reviews: ratings + comments left on listings
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id` INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `rating`     TINYINT UNSIGNED NOT NULL,
    `title`      VARCHAR(160) DEFAULT NULL,
    `comment`    TEXT NOT NULL,
    `visited_on` DATE DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `reviews_listing_fk` (`listing_id`),
    KEY `reviews_user_fk` (`user_id`),
    CONSTRAINT `reviews_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- messages: direct messages between tourists and guides
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_id`   INT UNSIGNED NOT NULL,
    `receiver_id` INT UNSIGNED NOT NULL,
    `listing_id`  INT UNSIGNED DEFAULT NULL,
    `body`        TEXT NOT NULL,
    `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `messages_sender_fk` (`sender_id`),
    KEY `messages_receiver_fk` (`receiver_id`),
    KEY `messages_listing_fk` (`listing_id`),
    CONSTRAINT `messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `messages_receiver_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `messages_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- conversation_settings: per-user pin/archive preferences for a chat partner
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `conversation_settings`;
CREATE TABLE `conversation_settings` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `partner_id`  INT UNSIGNED NOT NULL,
    `is_pinned`   TINYINT(1) NOT NULL DEFAULT 0,
    `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `conversation_settings_unique` (`user_id`, `partner_id`),
    KEY `conversation_settings_partner_fk` (`partner_id`),
    CONSTRAINT `conversation_settings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `conversation_settings_partner_fk` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- disputes: tourist reports about a paid booking (extra payment, no-show, etc.)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `disputes`;
CREATE TABLE `disputes` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id`       INT UNSIGNED NOT NULL,
    `user_id`          INT UNSIGNED NOT NULL,
    `guide_id`         INT UNSIGNED NOT NULL,
    `problem_type`     ENUM('extra_payment','no_show','service_mismatch','unsafe','other') NOT NULL,
    `amount_requested` DECIMAL(10,2) DEFAULT NULL,
    `description`      TEXT NOT NULL,
    `status`           ENUM('open','reviewing','resolved_refund','resolved_warning','rejected') NOT NULL DEFAULT 'open',
    `admin_note`       VARCHAR(500) DEFAULT NULL,
    `resolved_at`      TIMESTAMP NULL DEFAULT NULL,
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `disputes_booking_unique` (`booking_id`),
    KEY `disputes_user_fk` (`user_id`),
    KEY `disputes_guide_fk` (`guide_id`),
    CONSTRAINT `disputes_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `disputes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `disputes_guide_fk` FOREIGN KEY (`guide_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- password_resets: one-time tokens for account recovery
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `email` VARCHAR(190) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- guide_availability: dates a guide blocks on a listing calendar
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `guide_availability`;
CREATE TABLE `guide_availability` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id` INT UNSIGNED NOT NULL,
    `blocked_date` DATE NOT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `guide_availability_unique` (`listing_id`, `blocked_date`),
    CONSTRAINT `guide_availability_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- promo_codes: checkout discounts (simulated payment still applies discount)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `promo_codes`;
CREATE TABLE `promo_codes` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- review_images: optional photos attached to reviews
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `review_images`;
CREATE TABLE `review_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `review_images_review_fk` (`review_id`),
    CONSTRAINT `review_images_review_fk` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- dispute_files: evidence uploads for tourist dispute reports
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `dispute_files`;
CREATE TABLE `dispute_files` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dispute_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `label` VARCHAR(190) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `dispute_files_dispute_fk` (`dispute_id`),
    CONSTRAINT `dispute_files_dispute_fk` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- audit_logs: admin action history
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- favorites: tourists saving listings (the "heart"/save feature)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `listing_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `favorites_unique` (`user_id`, `listing_id`),
    KEY `favorites_listing_fk` (`listing_id`),
    CONSTRAINT `favorites_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `favorites_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- account_appeals: suspension appeals submitted by suspended users
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `account_appeals`;
CREATE TABLE `account_appeals` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED DEFAULT NULL,
    `name`              VARCHAR(120) NOT NULL DEFAULT '',
    `email`             VARCHAR(190) NOT NULL DEFAULT '',
    `suspension_reason` VARCHAR(255) NOT NULL DEFAULT '',
    `appeal_message`    TEXT NOT NULL,
    `status`            VARCHAR(20) NOT NULL DEFAULT 'pending',
    `admin_notes`       TEXT DEFAULT NULL,
    `reviewed_at`       TIMESTAMP NULL DEFAULT NULL,
    `reviewed_by`       INT UNSIGNED DEFAULT NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_appeals_user` (`user_id`),
    KEY `idx_appeals_status` (`status`),
    KEY `idx_appeals_created` (`created_at`),
    CONSTRAINT `fk_appeals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
