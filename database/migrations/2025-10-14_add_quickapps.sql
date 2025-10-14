-- Migration: create quickapps table for app favorites (access quick bar)
CREATE DATABASE IF NOT EXISTS `workz_apps` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `workz_apps`;

CREATE TABLE IF NOT EXISTS `quickapps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `us` INT UNSIGNED NOT NULL,
  `ap` INT UNSIGNED NOT NULL,
  `sort` INT UNSIGNED NULL DEFAULT 0,
  `dt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_quick_us_ap` (`us`, `ap`),
  KEY `idx_quick_us` (`us`),
  KEY `idx_quick_ap` (`ap`),
  KEY `idx_quick_sort` (`sort`),
  CONSTRAINT `fk_quickapps_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quickapps_ap` FOREIGN KEY (`ap`) REFERENCES `workz_apps`.`apps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Favoritos (acesso rápido) de apps por usuário';

