-- Recriacao idempotente dos esquemas e tabelas do Workz Platform
-- Alvo: MySQL 8 (docker-compose: service "mysql")

-- =====================================================
-- 1) Bases de dados (schemas)
-- =====================================================
CREATE DATABASE IF NOT EXISTS `workz_data`      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `workz_companies` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `workz_apps`      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================
-- 2) Schema: workz_data
-- =====================================================
USE `workz_data`;

-- 2.1) Tabela: hus (pessoas/usuarios)
CREATE TABLE IF NOT EXISTS `hus` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tt` VARCHAR(150) NOT NULL,
  `ml` VARCHAR(190) NOT NULL,
  `pw` VARCHAR(255) NULL,
  `dt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `provider` VARCHAR(30) NULL,
  `pd` VARCHAR(190) NULL,
  `st` TINYINT(1) NOT NULL DEFAULT 1,
  `im` VARCHAR(255) NULL,
  `bk` VARCHAR(255) NULL,
  `un` VARCHAR(100) NULL,
  `cf` TEXT NULL,
  `page_privacy` TINYINT(1) NULL DEFAULT 0,
  `feed_privacy` TINYINT(1) NULL DEFAULT 0,
  `gender` VARCHAR(20) NULL,
  `birth` DATE NULL,
  `contacts` TEXT NULL,
  `pending_email` VARCHAR(190) NULL,
  `email_change_token` VARCHAR(64) NULL,
  `email_change_expires_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hus_email` (`ml`),
  UNIQUE KEY `uniq_hus_un` (`un`),
  KEY `idx_hus_st` (`st`),
  KEY `idx_hus_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios (people)';

-- 2.2) Tabela: hpl (posts do editor)
CREATE TABLE IF NOT EXISTS `hpl` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `us` INT UNSIGNED NOT NULL,
  `tt` VARCHAR(255) NULL,
  `tp` VARCHAR(50) NOT NULL,
  `dt` DATETIME NOT NULL,
  `cm` INT UNSIGNED DEFAULT 0,
  `em` INT UNSIGNED DEFAULT 0,
  `st` TINYINT(1) NOT NULL DEFAULT 1,
  `ct` LONGTEXT NOT NULL,
  `im` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`us`),
  KEY `idx_type` (`tp`),
  KEY `idx_date` (`dt`),
  KEY `idx_st` (`st`),
  CONSTRAINT `fk_hpl_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de posts do editor';

-- 2.3) Tabela: hpl_comments (comentarios dos posts)
CREATE TABLE IF NOT EXISTS `hpl_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pl` INT UNSIGNED NOT NULL,
  `us` INT UNSIGNED NOT NULL,
  `ds` TEXT NOT NULL,
  `dt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post` (`pl`),
  KEY `idx_user` (`us`),
  KEY `idx_dt` (`dt`),
  CONSTRAINT `fk_hpl_comments_pl` FOREIGN KEY (`pl`) REFERENCES `workz_data`.`hpl`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hpl_comments_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comentarios de posts do editor';

-- 2.4) Tabela: lke (curtidas de posts)
CREATE TABLE IF NOT EXISTS `lke` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pl` INT UNSIGNED NOT NULL,
  `us` INT UNSIGNED NOT NULL,
  `dt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_like_pl_us` (`pl`, `us`),
  KEY `idx_lke_pl` (`pl`),
  KEY `idx_lke_us` (`us`),
  CONSTRAINT `fk_lke_pl` FOREIGN KEY (`pl`) REFERENCES `workz_data`.`hpl`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lke_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Curtidas de posts';

-- 2.5) Tabela: usg (seguidores de pessoas)
CREATE TABLE IF NOT EXISTS `usg` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `s0` INT UNSIGNED NOT NULL,
  `s1` INT UNSIGNED NOT NULL,
  `dt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_usg_s0_s1` (`s0`, `s1`),
  KEY `idx_usg_s0` (`s0`),
  KEY `idx_usg_s1` (`s1`),
  CONSTRAINT `fk_usg_s0` FOREIGN KEY (`s0`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usg_s1` FOREIGN KEY (`s1`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Seguidores (follow)';

-- 2.6) Tabela: testimonials (depoimentos)
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `author` INT UNSIGNED NOT NULL,
  `recipient` INT UNSIGNED NOT NULL,
  `recipient_type` VARCHAR(20) NOT NULL,
  `content` TEXT NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 0,
  `dt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_test_recipient` (`recipient`, `recipient_type`),
  KEY `idx_test_author` (`author`),
  KEY `idx_test_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Depoimentos';

-- 2.7) Tabela: work_history (historico profissional)
-- OBS: adiada para após criação de workz_companies.companies (para FK existir)

-- =====================================================
-- 3) Schema: workz_companies
-- =====================================================
USE `workz_companies`;

-- 3.1) Tabela: companies
CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tt` VARCHAR(150) NOT NULL,
  `im` VARCHAR(255) NULL,
  `bk` VARCHAR(255) NULL,
  `st` TINYINT(1) NOT NULL DEFAULT 1,
  `us` INT UNSIGNED NULL,
  `un` VARCHAR(100) NULL,
  `cf` TEXT NULL,
  `page_privacy` TINYINT(1) NULL DEFAULT 0,
  `feed_privacy` TINYINT(1) NULL DEFAULT 0,
  `national_id` VARCHAR(32) NULL,
  `zip_code` VARCHAR(16) NULL,
  `country` VARCHAR(80) NULL,
  `state` VARCHAR(80) NULL,
  `city` VARCHAR(120) NULL,
  `district` VARCHAR(120) NULL,
  `address` VARCHAR(255) NULL,
  `complement` VARCHAR(255) NULL,
  `contacts` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_companies_un` (`un`),
  KEY `idx_companies_st` (`st`),
  KEY `idx_companies_us` (`us`),
  CONSTRAINT `fk_companies_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Negocios (companies)';

-- 3.2) Tabela: employees (vinculo usuario-empresa)
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `us` INT UNSIGNED NOT NULL,
  `em` INT UNSIGNED NOT NULL,
  `nv` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `st` TINYINT(1) NOT NULL DEFAULT 0,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_employees_em_us` (`em`, `us`),
  KEY `idx_employees_us` (`us`),
  KEY `idx_employees_st` (`st`),
  CONSTRAINT `fk_employees_em` FOREIGN KEY (`em`) REFERENCES `workz_companies`.`companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employees_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.3) Tabela: teams
CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tt` VARCHAR(150) NOT NULL,
  `im` VARCHAR(255) NULL,
  `bk` VARCHAR(255) NULL,
  `st` TINYINT(1) NOT NULL DEFAULT 1,
  `un` VARCHAR(100) NULL,
  `us` INT UNSIGNED NOT NULL,
  `usmn` TEXT NULL,
  `em` INT UNSIGNED NOT NULL,
  `cf` TEXT NULL,
  `feed_privacy` TINYINT(1) NULL DEFAULT 0,
  `contacts` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teams_em` (`em`),
  KEY `idx_teams_us` (`us`),
  KEY `idx_teams_st` (`st`),
  UNIQUE KEY `uniq_teams_un` (`un`),
  CONSTRAINT `fk_teams_em` FOREIGN KEY (`em`) REFERENCES `workz_companies`.`companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teams_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.4) Tabela: teams_users (vinculo usuario-equipe)
CREATE TABLE IF NOT EXISTS `teams_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `us` INT UNSIGNED NOT NULL,
  `cm` INT UNSIGNED NOT NULL,
  `nv` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `st` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_teams_users_cm_us` (`cm`, `us`),
  KEY `idx_teams_users_us` (`us`),
  KEY `idx_teams_users_st` (`st`),
  CONSTRAINT `fk_teams_users_cm` FOREIGN KEY (`cm`) REFERENCES `workz_companies`.`teams`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teams_users_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4) Schema: workz_apps
-- =====================================================
USE `workz_apps`;

-- 4.1) Tabela: apps (catalogo)
CREATE TABLE IF NOT EXISTS `apps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tt` VARCHAR(150) NOT NULL,
  `im` VARCHAR(255) NULL,
  `vl` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `st` TINYINT(1) NOT NULL DEFAULT 1,
  `dt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apps_st` (`st`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catalogo de aplicativos';

-- Extensões de metadados para apps (idempotentes)
ALTER TABLE `apps`
  ADD COLUMN `slug` VARCHAR(60) NULL,
  ADD COLUMN `src` VARCHAR(255) NULL,
  ADD COLUMN `embed_url` VARCHAR(255) NULL,
  ADD COLUMN `color` VARCHAR(16) NULL,
  ADD COLUMN `ds` TEXT NULL,
  ADD COLUMN `publisher` VARCHAR(120) NULL,
  ADD COLUMN `version` VARCHAR(20) NULL,
  ADD COLUMN `scopes` JSON NULL;

-- Índice único para slug
CREATE UNIQUE INDEX `uniq_apps_slug` ON `apps` (`slug`);

-- 4.2) Tabela: gapp (instalacoes/assinaturas)
CREATE TABLE IF NOT EXISTS `gapp` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `us` INT UNSIGNED NULL,
  `em` INT UNSIGNED NULL,
  `ap` INT UNSIGNED NOT NULL,
  `st` TINYINT(1) NOT NULL DEFAULT 1,
  `subscription` TINYINT(1) NOT NULL DEFAULT 0,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gapp_us` (`us`),
  KEY `idx_gapp_em` (`em`),
  KEY `idx_gapp_ap` (`ap`),
  KEY `idx_gapp_subscription` (`subscription`),
  KEY `idx_gapp_st` (`st`),
  CONSTRAINT `fk_gapp_ap` FOREIGN KEY (`ap`) REFERENCES `workz_apps`.`apps`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gapp_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gapp_em` FOREIGN KEY (`em`) REFERENCES `workz_companies`.`companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Instalacoes/assinaturas de apps';

-- 4.3) Tabela: quickapps (acesso rápido/favoritos de apps por usuário)
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

-- 2.7) Tabela: work_history (historico profissional) – criada após companies
USE `workz_data`;
CREATE TABLE IF NOT EXISTS `work_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `us` INT UNSIGNED NOT NULL,
  `em` INT UNSIGNED NULL DEFAULT NULL,
  `tt` VARCHAR(150) NOT NULL,
  `cf` TEXT NULL,
  `type` VARCHAR(30) NULL,
  `location` VARCHAR(150) NULL,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `visibility` TINYINT(1) NOT NULL DEFAULT 1,
  `verified` TINYINT(1) NOT NULL DEFAULT 0,
  `verified_by` INT UNSIGNED NULL DEFAULT NULL,
  `verified_at` DATETIME NULL DEFAULT NULL,
  `st` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wh_user` (`us`),
  KEY `idx_wh_company` (`em`),
  KEY `idx_wh_status` (`st`),
  KEY `idx_wh_verified` (`verified`),
  CONSTRAINT `fk_work_history_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_work_history_em` FOREIGN KEY (`em`) REFERENCES `workz_companies`.`companies`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
