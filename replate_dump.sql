-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: replate
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.5

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_details` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_type` enum('info','warning','security','critical') COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user_log` (`user_id`),
  KEY `idx_type_created` (`log_type`,`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'LOGIN_FAILED','Failed login attempt for email admin@replate.com','warning','127.0.0.1','2026-11-16 18:52:13'),(2,4,'USER_REGISTERED','User account registered with role \'recipient\'','info','127.0.0.1','2026-11-16 18:53:38'),(3,4,'USER_LOGIN','User successfully logged in','info','127.0.0.1','2026-11-16 18:53:50'),(4,4,'REQUEST_CREATED','Submitted claim request #3 for donation #3','info','127.0.0.1','2026-11-16 18:54:03'),(5,5,'USER_REGISTERED','User account registered with role \'recipient\'','info','127.0.0.1','2026-11-16 19:05:56'),(6,5,'USER_LOGIN','User successfully logged in','info','127.0.0.1','2026-11-16 19:06:05'),(7,5,'USER_LOGIN','User successfully logged in','info','127.0.0.1','2026-11-16 20:26:38');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_tokens`
--

DROP TABLE IF EXISTS `auth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_tokens` (
  `token_id` int NOT NULL AUTO_INCREMENT,
  `selector` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashed_validator` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `idx_user_tokens` (`user_id`),
  CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_tokens`
--

LOCK TABLES `auth_tokens` WRITE;
/*!40000 ALTER TABLE `auth_tokens` DISABLE KEYS */;
INSERT INTO `auth_tokens` VALUES (1,'784be84282b10943d2297f5c61dfb2a4','5488c8cbc21ff1b992ccce09f9bbb3b65fd9ba97c6a6941ce0d146e84a71b97f',4,'2026-12-16 18:53:50','2026-11-16 18:53:50'),(2,'e48ba79309153c687be14bb71ed24e6f','82ca8b207ebb3e7f195f9436fde1328b38765f29d23ee61ff4affadfdaea12c1',5,'2026-12-16 19:06:05','2026-11-16 19:06:05'),(3,'1c2211b517f2787d407ad45e2db6b6c1','aae802d419627b7421b506db031e1bf8968bd9f67361b82a92a0673a57d56191',5,'2026-12-16 20:26:38','2026-11-16 20:26:38');
/*!40000 ALTER TABLE `auth_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Prepared Meals','Freshly cooked meals and catering surplus','2026-11-16 13:29:35'),(2,'Produce & Fruits','Fresh vegetables, fruits, and organic produce','2026-11-16 13:29:35'),(3,'Bakery & Grains','Bread, pastries, flour, and grain products','2026-11-16 13:29:35'),(4,'Dairy & Groceries','Milk, cheese, eggs, and packaged goods','2026-11-16 13:29:35');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donation_requests`
--

DROP TABLE IF EXISTS `donation_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donation_requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `donation_id` int NOT NULL,
  `recipient_id` int NOT NULL,
  `quantity_requested` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `collection_date` datetime DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Completed') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uq_donation_recipient` (`donation_id`,`recipient_id`),
  KEY `idx_recipient_status` (`recipient_id`,`status`),
  CONSTRAINT `donation_requests_ibfk_1` FOREIGN KEY (`donation_id`) REFERENCES `food_donations` (`donation_id`) ON DELETE CASCADE,
  CONSTRAINT `donation_requests_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donation_requests`
--

LOCK TABLES `donation_requests` WRITE;
/*!40000 ALTER TABLE `donation_requests` DISABLE KEYS */;
INSERT INTO `donation_requests` VALUES (1,1,3,10.00,NULL,NULL,'Pending','2026-11-16 13:29:35','2026-11-16 13:29:35'),(2,2,3,5.00,NULL,NULL,'Approved','2026-11-16 13:29:35','2026-11-16 13:29:35'),(3,3,4,30.00,NULL,NULL,'Pending','2026-11-16 18:54:03','2026-11-16 18:54:03');
/*!40000 ALTER TABLE `donation_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `food_categories`
--

DROP TABLE IF EXISTS `food_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `food_categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `food_categories`
--

LOCK TABLES `food_categories` WRITE;
/*!40000 ALTER TABLE `food_categories` DISABLE KEYS */;
INSERT INTO `food_categories` VALUES (1,'Prepared Meals','Freshly cooked meals and catering surplus','2026-11-16 13:29:35'),(2,'Produce & Fruits','Fresh vegetables, fruits, and organic produce','2026-11-16 13:29:35'),(3,'Bakery & Grains','Bread, pastries, flour, and grain products','2026-11-16 13:29:35'),(4,'Dairy & Groceries','Milk, cheese, eggs, and packaged goods','2026-11-16 13:29:35');
/*!40000 ALTER TABLE `food_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `food_donations`
--

DROP TABLE IF EXISTS `food_donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `food_donations` (
  `donation_id` int NOT NULL AUTO_INCREMENT,
  `donor_id` int NOT NULL,
  `category_id` int DEFAULT '1',
  `food_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'kg',
  `expiry_date` datetime NOT NULL,
  `pickup_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Available','Reserved','Collected','Cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'Available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`donation_id`),
  KEY `idx_status_expiry` (`status`,`expiry_date`),
  KEY `idx_donor` (`donor_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `food_donations_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `food_donations_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `food_categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `food_donations`
--

LOCK TABLES `food_donations` WRITE;
/*!40000 ALTER TABLE `food_donations` DISABLE KEYS */;
INSERT INTO `food_donations` VALUES (1,2,2,'Fresh Organic Apples',NULL,25.00,'kg','2026-11-19 16:29:35','123 Market Street, Downtown','Available','2026-11-16 13:29:35','2026-11-16 13:29:35'),(2,2,3,'Assorted Artisan Breads',NULL,15.00,'loaves','2026-11-17 16:29:35','45 Bakery Lane','Available','2026-11-16 13:29:35','2026-11-16 13:29:35'),(3,2,1,'Prepared Vegetable Casseroles',NULL,30.00,'servings','2026-11-18 16:29:35','789 Commercial Ave','Available','2026-11-16 13:29:35','2026-11-16 13:29:35');
/*!40000 ALTER TABLE `food_donations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_unread` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,4,'Welcome to RePlate!','Your account has been created. Explore available donations or submit food requests near you.','welcome',0,'2026-11-16 18:53:38'),(2,4,'New Login Detected','Successful login recorded from 127.0.0.1 at 18:53, Nov 16, 2026.','security',0,'2026-11-16 18:53:50'),(3,2,'New Food Request','A recipient requested your donation \'Prepared Vegetable Casseroles\'.','request',0,'2026-11-16 18:54:03'),(4,5,'Welcome to RePlate!','Your account has been created. Explore available donations or submit food requests near you.','welcome',0,'2026-11-16 19:05:56'),(5,5,'New Login Detected','Successful login recorded from 127.0.0.1 at 19:06, Nov 16, 2026.','security',0,'2026-11-16 19:06:05'),(6,5,'New Login Detected','Successful login recorded from 127.0.0.1 at 20:26, Nov 16, 2026.','security',0,'2026-11-16 20:26:38');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_user_token` (`user_id`,`token`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,5,'752145','2026-11-17 07:23:01','2026-11-17 07:08:02');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `generated_by` int NOT NULL,
  `report_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `discretion_mode` tinyint(1) DEFAULT '0',
  `phone_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_rescue_radius_km` int DEFAULT '10',
  `vehicle_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'car',
  `pickup_instructions` text COLLATE utf8mb4_unicode_ci,
  `dietary_tags` json DEFAULT NULL,
  `storage_equipment` json DEFAULT NULL,
  `temp_logging_required` tinyint(1) DEFAULT '0',
  `byoc_required` tinyint(1) DEFAULT '0',
  `auto_accept_trusted` tinyint(1) DEFAULT '0',
  `emergency_broadcasts` tinyint(1) DEFAULT '0',
  `expiry_alert_hours` int DEFAULT '3',
  `notify_inapp` tinyint(1) DEFAULT '1',
  `notify_email` tinyint(1) DEFAULT '1',
  `notify_sms` tinyint(1) DEFAULT '0',
  `notify_whatsapp` tinyint(1) DEFAULT '0',
  `tax_receipts_enabled` tinyint(1) DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_settings`
--

LOCK TABLES `user_settings` WRITE;
/*!40000 ALTER TABLE `user_settings` DISABLE KEYS */;
INSERT INTO `user_settings` VALUES (1,4,0,NULL,10,'car',NULL,NULL,NULL,0,0,0,0,3,1,1,0,0,0,'2026-11-16 18:53:38'),(2,5,0,NULL,10,'car',NULL,NULL,NULL,0,0,0,0,3,1,1,0,0,0,'2026-11-16 19:05:56');
/*!40000 ALTER TABLE `user_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `org_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('recipient','donor','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recipient',
  `status` enum('Active','Suspended','Pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `verification_status` enum('Unverified','Pending','Verified','Rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unverified',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `normalized_email` (`normalized_email`),
  KEY `idx_role_status` (`role`,`status`),
  KEY `idx_verification` (`verification_status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Admin',NULL,'admin@replate.com','admin@replate.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin','Active','Verified','2026-11-16 13:29:35','2026-11-16 13:29:35'),(2,'Green Valley Supermarket',NULL,'donor@replate.com','donor@replate.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','donor','Active','Verified','2026-11-16 13:29:35','2026-11-16 13:29:35'),(3,'Hope Community Kitchen',NULL,'recipient@replate.com','recipient@replate.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','recipient','Active','Verified','2026-11-16 13:29:35','2026-11-16 13:29:35'),(4,'Moringa Daily Dev',NULL,'moringadailydev@gmail.com','moringadailydev@gmail.com','$2y$12$T0fCfhv2VS3zrh8/8bDXmOUnzmlJc9lafi7Jf7Mlp5wfzm/JmANEG','recipient','Active','Pending','2026-11-16 18:53:38','2026-11-16 18:53:38'),(5,'Brian Recipient',NULL,'briankimani432@gmail.com','briankimani432@gmail.com','$2y$12$6jrX6yTnWICd/agFSRYS2uRqwwHDk8QqwfHPEECzPPKyiosbk.en6','recipient','Active','Pending','2026-11-16 19:05:56','2026-11-16 19:05:56');
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

-- Dump completed on 2026-11-17 17:23:55
