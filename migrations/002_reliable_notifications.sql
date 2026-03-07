-- Migration: Reliable notifications queue and nearby signal dedupe/rate helpers

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `notification_delivery_queue` (
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

CREATE TABLE IF NOT EXISTS `nearby_notify_signals` (
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
