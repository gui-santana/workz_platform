-- Add team context to gapp (workz_apps) and audit log table (workz_data)

-- =============================
-- gapp: enable per-team installs
-- =============================
ALTER TABLE `gapp`
    ADD COLUMN IF NOT EXISTS `cm` INT UNSIGNED NULL AFTER `em`;

-- Generated column to normalize NULL for unique constraint
ALTER TABLE `gapp`
    ADD COLUMN IF NOT EXISTS `cm0` INT GENERATED ALWAYS AS (IFNULL(`cm`, 0)) STORED AFTER `cm`;

-- Unique constraint ensuring one row per business/app/team (team nullable)
ALTER TABLE `gapp`
    ADD UNIQUE INDEX `uniq_gapp_em_ap_cm0` (`em`, `ap`, `cm0`);

-- Helpful lookup indexes
ALTER TABLE `gapp`
    ADD INDEX `idx_gapp_cm` (`cm`),
    ADD INDEX `idx_gapp_us_em_ap` (`us`, `em`, `ap`);

-- =============================
-- audit_logs (workz_data)
-- =============================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_us` INT UNSIGNED NOT NULL,
  `action` VARCHAR(120) NOT NULL,
  `em` INT UNSIGNED NULL,
  `cm` INT UNSIGNED NULL,
  `ap` INT UNSIGNED NULL,
  `target_type` VARCHAR(64) NULL,
  `target_id` INT UNSIGNED NULL,
  `before_json` JSON NULL,
  `after_json` JSON NULL,
  `ip` VARCHAR(64) NULL,
  `ua` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_actor_created` (`actor_us`, `created_at`),
  INDEX `idx_scope` (`em`, `cm`, `ap`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
