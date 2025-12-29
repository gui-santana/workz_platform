-- database/migrations/2025_11_11_create_billing_tables.sql

CREATE TABLE IF NOT EXISTS `billing_payment_methods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('user','business') NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(32) NOT NULL DEFAULT 'mercadopago',
  `pm_type` ENUM('card','pix','other') NOT NULL DEFAULT 'card',
  `status` TINYINT NOT NULL DEFAULT 1,
  `label` VARCHAR(100) NULL,
  `brand` VARCHAR(32) NULL,
  `last4` VARCHAR(4) NULL,
  `exp_month` TINYINT NULL,
  `exp_year` SMALLINT NULL,
  `mp_card_id` VARCHAR(64) NULL,
  `mp_customer_id` VARCHAR(64) NULL,
  `token_ref` VARCHAR(128) NULL,
  `is_default` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_status` (`status`),
  KEY `idx_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `billing_bank_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` INT UNSIGNED NOT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `is_default` TINYINT NOT NULL DEFAULT 0,
  `holder_name` VARCHAR(100) NOT NULL,
  `document` VARCHAR(20) NOT NULL,
  `bank_code` VARCHAR(10) NULL,
  `bank_name` VARCHAR(100) NULL,
  `branch` VARCHAR(20) NULL,
  `account_number` VARCHAR(32) NULL,
  `account_type` ENUM('checking','savings','payment') NOT NULL DEFAULT 'checking',
  `pix_key_type` ENUM('cpf','cnpj','email','phone','random','evp') NULL,
  `pix_key` VARCHAR(140) NULL,
  `metadata` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_business` (`business_id`),
  KEY `idx_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

