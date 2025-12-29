-- database/migrations/2025_11_11_create_workz_payments_tables.sql

CREATE TABLE IF NOT EXISTS `workz_payments_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('one_time','subscription') NOT NULL DEFAULT 'one_time',
  `mp_payment_id` VARCHAR(64) NULL,
  `mp_preference_id` VARCHAR(64) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'created',
  `app_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'BRL',
  `metadata` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_app_user` (`app_id`, `user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_mp_payment` (`mp_payment_id`),
  KEY `idx_mp_pref` (`mp_preference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

