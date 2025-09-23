-- Migration: create workz_data.work_history
-- Purpose: store user professional experiences independent of membership

CREATE TABLE IF NOT EXISTS `work_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `us` INT UNSIGNED NOT NULL,               -- user id (workz_data.hus.id)
  `em` INT UNSIGNED NULL DEFAULT NULL,      -- company id (workz_companies.companies.id) - nullable for freelancers
  `tt` VARCHAR(150) NOT NULL,               -- title/role
  `cf` TEXT NULL,                           -- description/details
  `type` VARCHAR(30) NULL,                  -- type: clt, contrato, freelancer, estagio, etc.
  `location` VARCHAR(150) NULL,             -- location (optional)
  `start_date` DATE NULL,                   -- start date
  `end_date` DATE NULL,                     -- end date (NULL means current)
  `visibility` TINYINT(1) NOT NULL DEFAULT 1, -- 1 visible, 0 hidden
  `verified` TINYINT(1) NOT NULL DEFAULT 0,  -- 1 verified by company, 0 not
  `verified_by` INT UNSIGNED NULL DEFAULT NULL, -- verifier user id (optional)
  `verified_at` DATETIME NULL DEFAULT NULL,
  `st` TINYINT(1) NOT NULL DEFAULT 1,       -- status: 1 active (current) or general enabled flag
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wh_user` (`us`),
  KEY `idx_wh_company` (`em`),
  KEY `idx_wh_status` (`st`),
  KEY `idx_wh_verified` (`verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

