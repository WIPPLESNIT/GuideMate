-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: guidemate
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `guidemate`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `guidemate` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `guidemate`;

--
-- Table structure for table `api_tokens`
--

DROP TABLE IF EXISTS `api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_tokens_token` (`token`),
  KEY `idx_api_tokens_user` (`user_id`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_tokens`
--

LOCK TABLES `api_tokens` WRITE;
/*!40000 ALTER TABLE `api_tokens` DISABLE KEYS */;
INSERT INTO `api_tokens` VALUES (1,2,'8f2dae86bab6437055c6c97a03e140cfbf966227e8b56b010768a2919d4ece96','2026-07-15 20:15:47','2026-06-15 12:15:47'),(2,2,'096b1d06632ffb5e91642fa02f0239870cce64e24dfb68e67a20fcc4e03398de','2026-07-15 20:16:00','2026-06-15 12:16:00');
/*!40000 ALTER TABLE `api_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_logs_admin_fk` (`admin_id`),
  CONSTRAINT `audit_logs_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `booking_date` date NOT NULL,
  `guests` int(11) NOT NULL DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','confirmed','completed','cancelled','disputed','refunded') NOT NULL DEFAULT 'pending',
  `notes` varchar(500) DEFAULT NULL,
  `verify_token` varchar(64) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `promo_code_id` int(10) unsigned DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bookings_listing_fk` (`listing_id`),
  KEY `bookings_user_fk` (`user_id`),
  CONSTRAINT `bookings_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `icon` varchar(40) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Things to Do','things-to-do','compass','Tours, attractions & activities across Cebu',1),(2,'Tour Guides','tour-guides','user-check','Hire trusted local Cebuano guides',2),(3,'Hotels & Stays','hotels','bed','Resorts, hotels and stays',3),(4,'Restaurants','restaurants','utensils','Where to eat in Cebu',4);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_settings`
--

DROP TABLE IF EXISTS `conversation_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `partner_id` int(10) unsigned NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_settings_unique` (`user_id`,`partner_id`),
  KEY `conversation_settings_partner_fk` (`partner_id`),
  CONSTRAINT `conversation_settings_partner_fk` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_settings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_settings`
--

LOCK TABLES `conversation_settings` WRITE;
/*!40000 ALTER TABLE `conversation_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dispute_files`
--

DROP TABLE IF EXISTS `dispute_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dispute_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dispute_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dispute_files_dispute_fk` (`dispute_id`),
  CONSTRAINT `dispute_files_dispute_fk` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispute_files`
--

LOCK TABLES `dispute_files` WRITE;
/*!40000 ALTER TABLE `dispute_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `dispute_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disputes`
--

DROP TABLE IF EXISTS `disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disputes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `guide_id` int(10) unsigned NOT NULL,
  `problem_type` enum('extra_payment','no_show','service_mismatch','unsafe','other') NOT NULL,
  `amount_requested` decimal(10,2) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('open','reviewing','resolved_refund','resolved_warning','rejected') NOT NULL DEFAULT 'open',
  `admin_note` varchar(500) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `disputes_booking_unique` (`booking_id`),
  KEY `disputes_user_fk` (`user_id`),
  KEY `disputes_guide_fk` (`guide_id`),
  CONSTRAINT `disputes_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disputes_guide_fk` FOREIGN KEY (`guide_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disputes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disputes`
--

LOCK TABLES `disputes` WRITE;
/*!40000 ALTER TABLE `disputes` DISABLE KEYS */;
/*!40000 ALTER TABLE `disputes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `favorites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `favorites_unique` (`user_id`,`listing_id`),
  KEY `favorites_listing_fk` (`listing_id`),
  CONSTRAINT `favorites_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favorites_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorites`
--

LOCK TABLES `favorites` WRITE;
/*!40000 ALTER TABLE `favorites` DISABLE KEYS */;
/*!40000 ALTER TABLE `favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guide_availability`
--

DROP TABLE IF EXISTS `guide_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guide_availability` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `blocked_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `guide_availability_unique` (`listing_id`,`blocked_date`),
  CONSTRAINT `guide_availability_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guide_availability`
--

LOCK TABLES `guide_availability` WRITE;
/*!40000 ALTER TABLE `guide_availability` DISABLE KEYS */;
/*!40000 ALTER TABLE `guide_availability` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guide_documents`
--

DROP TABLE IF EXISTS `guide_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guide_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `doc_type` varchar(60) NOT NULL,
  `label` varchar(190) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `guide_documents_user_fk` (`user_id`),
  CONSTRAINT `guide_documents_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guide_documents`
--

LOCK TABLES `guide_documents` WRITE;
/*!40000 ALTER TABLE `guide_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `guide_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `listing_images`
--

DROP TABLE IF EXISTS `listing_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `listing_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `listing_images_listing_fk` (`listing_id`),
  CONSTRAINT `listing_images_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `listing_images`
--

LOCK TABLES `listing_images` WRITE;
/*!40000 ALTER TABLE `listing_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `listing_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `listings`
--

DROP TABLE IF EXISTS `listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `listings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `title` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `area` varchar(120) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_unit` varchar(40) NOT NULL DEFAULT 'per person',
  `duration` varchar(80) DEFAULT NULL,
  `included` text DEFAULT NULL,
  `not_included` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sale_ends_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `listings_slug_unique` (`slug`),
  KEY `listings_user_fk` (`user_id`),
  KEY `listings_category_fk` (`category_id`),
  KEY `listings_status_idx` (`status`),
  CONSTRAINT `listings_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `listings`
--

LOCK TABLES `listings` WRITE;
/*!40000 ALTER TABLE `listings` DISABLE KEYS */;
INSERT INTO `listings` VALUES (1,3,1,'Moalboal Snorkeling','moalboal-snorkeling','Snorkel the vibrant coral reefs and turtle spots of Moalboal.','Explore the crystal-clear waters of Moalboal, Cebu. Snorkel over thriving coral gardens, swim alongside sea turtles, and witness the famous marine life of Panagsama Beach. Equipment and a local guide are included.','Moalboal, Cebu','Panagsama Beach, Moalboal, Cebu',NULL,NULL,800.00,'per person','2-3 hours','Snorkeling gear, local guide, boat transfer','Food and drinks, hotel pickup','https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=1200&q=70','approved',1,NULL,NULL,'2026-06-15 12:27:23','2026-06-15 12:27:23'),(2,3,4,'Kyle\'s Resto Bar','kyles-resto-bar','Beachfront resto bar with local dishes and drinks in Moalboal.','Relax at Kyle\'s Resto Bar in Moalboal, Cebu. Enjoy fresh seafood, Filipino favorites, ice-cold drinks, and a laid-back beachfront atmosphere — perfect after a day of diving and snorkeling.','Moalboal, Cebu','Panagsama Beach, Moalboal, Cebu',NULL,NULL,1000.00,'per table',NULL,'Table reservation','Food and drinks (pay on site)','https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=70','approved',1,NULL,NULL,'2026-06-15 12:27:23','2026-06-15 12:27:23');
/*!40000 ALTER TABLE `listings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` int(10) unsigned NOT NULL,
  `receiver_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `messages_sender_fk` (`sender_id`),
  KEY `messages_receiver_fk` (`receiver_id`),
  KEY `messages_listing_fk` (`listing_id`),
  CONSTRAINT `messages_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_receiver_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(190) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(40) NOT NULL DEFAULT 'card',
  `status` enum('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
  `reference` varchar(64) NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_reference_unique` (`reference`),
  KEY `payments_booking_fk` (`booking_id`),
  CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_codes`
--

DROP TABLE IF EXISTS `promo_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `max_uses` int(10) unsigned DEFAULT NULL,
  `uses_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `promo_codes_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_codes`
--

LOCK TABLES `promo_codes` WRITE;
/*!40000 ALTER TABLE `promo_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `promo_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_images`
--

DROP TABLE IF EXISTS `review_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `review_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `review_images_review_fk` (`review_id`),
  CONSTRAINT `review_images_review_fk` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_images`
--

LOCK TABLES `review_images` WRITE;
/*!40000 ALTER TABLE `review_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `title` varchar(160) DEFAULT NULL,
  `comment` text NOT NULL,
  `visited_on` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reviews_listing_fk` (`listing_id`),
  KEY `reviews_user_fk` (`user_id`),
  CONSTRAINT `reviews_listing_fk` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guide','tourist') NOT NULL DEFAULT 'tourist',
  `phone` varchar(40) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `guide_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `guide_review_note` varchar(500) DEFAULT NULL,
  `guide_reviewed_at` timestamp NULL DEFAULT NULL,
  `guide_warned` tinyint(1) NOT NULL DEFAULT 0,
  `guide_warning_note` varchar(500) DEFAULT NULL,
  `admin_totp_secret` varchar(64) DEFAULT NULL,
  `admin_totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrator','fbaquerosa@gmail.com','$2y$10$fWMGmvoZB7Ll9C.emzfPjeiUxcePTSYQiSqOxViUCWEIPJtqGEZJa','admin',NULL,NULL,'Platform administrator.','Cebu City',1,'none',NULL,NULL,0,NULL,NULL,0,'2026-06-15 11:51:31','2026-06-15 11:51:31'),(2,'Jalel Mauc','jalelmauc@gmail.com','$2y$10$VWE9XjTCU1cch9Q8daRguuNBGBn3prrwsJXNfpMk83GYF9Im1ZV0G','tourist',NULL,NULL,NULL,NULL,1,'none',NULL,NULL,0,NULL,NULL,0,'2026-06-15 12:15:47','2026-06-15 12:15:47'),(3,'Moalboal Local Guide','guide.moalboal@guidemate.local','$2y$10$8kopCDTNm6m3GZ5u/ahiR.GcEvuYEWhdAJgN0J5z00xveI5FyQxQ6','guide',NULL,NULL,'Local guide for snorkeling tours and dining around Moalboal.','Moalboal, Cebu',1,'approved',NULL,NULL,0,NULL,NULL,0,'2026-06-15 12:27:23','2026-06-15 12:27:23');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-15 20:30:29
