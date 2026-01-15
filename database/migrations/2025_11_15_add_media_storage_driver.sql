-- Migration: Add storage driver to media records (post v2)

ALTER TABLE `media`
    ADD COLUMN IF NOT EXISTS `storage_driver` VARCHAR(16) NOT NULL DEFAULT 'local';
