-- Migration: Create media cleanup queue for failed storage deletions

CREATE TABLE IF NOT EXISTS `media_cleanup_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `media_id` BIGINT UNSIGNED NOT NULL,
    `object_key` VARCHAR(512) NOT NULL,
    `storage_driver` VARCHAR(16) NOT NULL DEFAULT 'local',
    `attempts` INT NOT NULL DEFAULT 0,
    `last_error` TEXT NULL,
    `status` ENUM('pending','done','error') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_media_cleanup_status` (`status`),
    INDEX `idx_media_cleanup_media` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

