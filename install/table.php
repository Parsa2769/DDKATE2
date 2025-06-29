-- =========================================================================================
-- File: db_schema.sql
-- Path: /db_schema.sql (در ریشه پروژه)
-- Description: 🏗️ این فایل، نقشه کامل و بهینه شده دیتابیس ربات ddkate است.
-- اسکریپت نصب به صورت خودکار این فایل را برای ساخت تمام جداول اجرا می‌کند.
-- این روش بسیار امن‌تر، سریع‌تر و قابل اعتمادتر از فایل table.php قدیمی است.
-- =========================================================================================

SET NAMES utf8mb4;
SET time_zone = '+03:30';
SET foreign_key_checks = 0;

--
-- Table structure for table `users`
--
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `user_state` varchar(100) COLLATE utf8mb4_bin DEFAULT 'start',
  `state_data` text COLLATE utf8mb4_bin DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `ban_description` text COLLATE utf8mb4_bin DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `rules_accepted` tinyint(1) NOT NULL DEFAULT 0,
  `last_message_time` int(11) DEFAULT 0,
  `message_count` int(11) DEFAULT 0,
  `referrer_id` bigint(20) DEFAULT NULL,
  `referral_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_id` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

--
-- Table structure for table `panels`
--
DROP TABLE IF EXISTS `panels`;
CREATE TABLE `panels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `type` enum('sanaei','marzban','alireza') COLLATE utf8mb4_bin NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `password` text COLLATE utf8mb4_bin NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_bin NOT NULL DEFAULT 'active',
  `protocols` json DEFAULT NULL COMMENT 'Stores enabled protocols like {"vless": true, "vmess": false}',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


--
-- Table structure for table `products`
--
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `panel_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `days` int(11) NOT NULL COMMENT 'Duration in days',
  `data_limit_gb` int(11) NOT NULL COMMENT 'Data limit in GB, 0 for unlimited',
  `inbound_id` int(11) DEFAULT NULL COMMENT 'For 3x-ui panels',
  `is_test_account` tinyint(1) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `panel_id` (`panel_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`panel_id`) REFERENCES `panels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


--
-- Table structure for table `invoices`
--
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `panel_user_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Username or ID on the panel',
  `price` decimal(15,2) NOT NULL,
  `status` enum('pending','paid','cancelled','error', 'expired', 'limited', 'deactivated') COLLATE utf8mb4_bin NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


--
-- Table structure for table `settings`
--
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `key` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `value` text COLLATE utf8mb4_bin,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

--
-- Default settings
--
INSERT INTO `settings` (`key`, `value`) VALUES
('bot_status', 'on'),
('channel_lock_status', 'off'),
('join_channel_id', ''),
('join_channel_username', ''),
('rules_status', 'off'),
('text_start', '👋 سلام {name}، به ربات فروش ddkate خوش آمدید!'),
-- ... ( تمام متن‌ها و تنظیمات دیگر در این جدول ذخیره خواهند شد )
('limit_usertest_all', '1'),
('time_usertest', '1'),
('val_usertest', '100');

--
-- Table structure for table `payments`
--
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_id` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` varchar(50) COLLATE utf8mb4_bin NOT NULL,
  `status` enum('pending','paid','failed','rejected') COLLATE utf8mb4_bin NOT NULL DEFAULT 'pending',
  `receipt_photo_id` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


SET foreign_key_checks = 1;
