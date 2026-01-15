-- Migration: Create media table for uploads/transcoding

CREATE TABLE IF NOT EXISTS `media` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('queued','processing','ready','error') NOT NULL DEFAULT 'processing',
    `type` ENUM('image','video') NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `size_bytes` BIGINT UNSIGNED DEFAULT 0,
    `duration_seconds` DECIMAL(8,3) DEFAULT NULL,
    `width` INT DEFAULT NULL,
    `height` INT DEFAULT NULL,
    `object_keys` JSON DEFAULT NULL,
    `thumb_keys` JSON DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_media_status` (`status`),
    INDEX `idx_media_user` (`user_id`),
    INDEX `idx_media_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
