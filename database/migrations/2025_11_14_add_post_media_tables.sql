-- Migration: Add post media support (media extensions + hpl_media)

-- Extend media table to support post uploads
ALTER TABLE `media`
    ADD COLUMN `us` BIGINT NOT NULL DEFAULT 0,
    ADD COLUMN `cm` BIGINT NOT NULL DEFAULT 0,
    ADD COLUMN `em` BIGINT NOT NULL DEFAULT 0,
    ADD COLUMN `mime` VARCHAR(120) NULL,
    ADD COLUMN `size` BIGINT NULL,
    ADD COLUMN `object_key` VARCHAR(512) NULL,
    ADD COLUMN `url` VARCHAR(1024) NULL;

ALTER TABLE `media`
    MODIFY COLUMN `status` ENUM('queued','processing','ready','error','init','uploaded','failed') NOT NULL DEFAULT 'init';

UPDATE `media` SET `us` = `user_id` WHERE `us` = 0;

CREATE INDEX `idx_media_us` ON `media` (`us`);
CREATE INDEX `idx_media_cm` ON `media` (`cm`);
CREATE INDEX `idx_media_em` ON `media` (`em`);

-- Pivot table for post <-> media ordering
CREATE TABLE IF NOT EXISTS `hpl_media` (
    `post_id` BIGINT NOT NULL,
    `media_id` BIGINT NOT NULL,
    `ord` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`post_id`, `media_id`),
    INDEX `idx_hpl_media_post_ord` (`post_id`, `ord`),
    INDEX `idx_hpl_media_media` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
