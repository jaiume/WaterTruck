-- Water Truck On-Demand Platform Schema
-- MySQL / MariaDB

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table: users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_token` VARCHAR(36) NOT NULL,
    `role` ENUM('customer', 'truck', 'operator', 'admin') NOT NULL DEFAULT 'customer',
    `name` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `email` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_device_token` (`device_token`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: operators
-- ----------------------------
DROP TABLE IF EXISTS `operators`;
CREATE TABLE `operators` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `mode` ENUM('delegated', 'dispatcher') NOT NULL DEFAULT 'delegated',
    `service_area` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    CONSTRAINT `fk_operators_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: trucks
-- ----------------------------
DROP TABLE IF EXISTS `trucks`;
CREATE TABLE `trucks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `operator_id` INT UNSIGNED NULL,
    `name` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `capacity_gallons` INT UNSIGNED NULL,
    `price_fixed` DECIMAL(10, 2) NULL,
    `avg_job_minutes` INT UNSIGNED NOT NULL DEFAULT 30,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `last_seen_at` DATETIME NULL,
    `current_lat` DECIMAL(10, 8) NULL,
    `current_lng` DECIMAL(11, 8) NULL,
    `location_updated_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_operator_id` (`operator_id`),
    KEY `idx_is_active` (`is_active`),
    CONSTRAINT `fk_trucks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_trucks_operator` FOREIGN KEY (`operator_id`) REFERENCES `operators` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: invites
-- ----------------------------
DROP TABLE IF EXISTS `invites`;
CREATE TABLE `invites` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `operator_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(36) NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `truck_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token` (`token`),
    KEY `idx_operator_id` (`operator_id`),
    CONSTRAINT `fk_invites_operator` FOREIGN KEY (`operator_id`) REFERENCES `operators` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invites_truck` FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: jobs
-- ----------------------------
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_user_id` INT UNSIGNED NOT NULL,
    `truck_id` INT UNSIGNED NULL,
    `status` ENUM('pending', 'accepted', 'en_route', 'delivered', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
    `price` DECIMAL(10, 2) NULL,
    `location` TEXT NOT NULL,
    `customer_name` VARCHAR(100) NULL,
    `customer_phone` VARCHAR(20) NULL,
    `lat` DECIMAL(10, 8) NULL,
    `lng` DECIMAL(11, 8) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `accepted_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_customer_user_id` (`customer_user_id`),
    KEY `idx_truck_id` (`truck_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_jobs_customer` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_jobs_truck` FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: job_requests
-- ----------------------------
DROP TABLE IF EXISTS `job_requests`;
CREATE TABLE `job_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id` INT UNSIGNED NOT NULL,
    `truck_id` INT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_job_truck` (`job_id`, `truck_id`),
    KEY `idx_truck_id` (`truck_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_job_requests_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_job_requests_truck` FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: push_subscriptions (unified - by user, not truck)
-- ----------------------------
DROP TABLE IF EXISTS `push_subscriptions`;
CREATE TABLE `push_subscriptions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `endpoint` VARCHAR(500) NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    CONSTRAINT `fk_push_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: notification_queue (unified - by user, not truck)
-- ----------------------------
DROP TABLE IF EXISTS `notification_queue`;
CREATE TABLE `notification_queue` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `customer_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_customer_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_notified_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    CONSTRAINT `fk_notification_queue_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: notification_delivery_queue (retry queue for truck push notifications)
-- ----------------------------
DROP TABLE IF EXISTS `notification_delivery_queue`;
CREATE TABLE `notification_delivery_queue` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dedupe_key` VARCHAR(100) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `truck_id` INT UNSIGNED NOT NULL,
    `job_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `body` VARCHAR(255) NOT NULL,
    `payload_json` JSON NOT NULL,
    `attempt_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
    `status` ENUM('pending', 'processing', 'failed', 'sent', 'dead_letter') NOT NULL DEFAULT 'pending',
    `next_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_error` VARCHAR(255) NULL,
    `locked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_notification_delivery_dedupe` (`dedupe_key`),
    KEY `idx_notification_delivery_due` (`status`, `next_attempt_at`),
    CONSTRAINT `fk_notification_delivery_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notification_delivery_truck` FOREIGN KEY (`truck_id`) REFERENCES `trucks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notification_delivery_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: nearby_notify_signals (dedupe and rate-limit markers for nearby demand pings)
-- ----------------------------
DROP TABLE IF EXISTS `nearby_notify_signals`;
CREATE TABLE `nearby_notify_signals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dedupe_key` VARCHAR(150) NOT NULL,
    `identity_hash` VARCHAR(64) NOT NULL,
    `lat_bucket` DECIMAL(8,3) NOT NULL,
    `lng_bucket` DECIMAL(8,3) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_nearby_notify_dedupe` (`dedupe_key`),
    KEY `idx_nearby_notify_identity_created` (`identity_hash`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
