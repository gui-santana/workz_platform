-- Subscriptions schema (workz_apps)

CREATE TABLE IF NOT EXISTS `workz_payments_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(120) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'BRL',
  `frequency` INT NOT NULL DEFAULT 1,
  `frequency_type` ENUM('days','months') NOT NULL DEFAULT 'months',
  `mp_plan_id` VARCHAR(64) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_app_plan` (`app_id`,`amount`,`frequency`,`frequency_type`),
  KEY `idx_plan_mp` (`mp_plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workz_payments_subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NULL,
  `app_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NULL,
  `mp_preapproval_id` VARCHAR(64) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `next_charge_date` DATE NULL,
  `metadata` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_user_app` (`user_id`,`app_id`),
  KEY `idx_sub_mp` (`mp_preapproval_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

