-- ALTERs para adicionar chaves estrangeiras em um banco já existente
-- Execute uma vez após criar as tabelas sem FKs

-- =====================
-- workz_data
-- =====================
USE `workz_data`;
ALTER TABLE `hpl` ADD CONSTRAINT `fk_hpl_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;
ALTER TABLE `hpl_comments` ADD CONSTRAINT `fk_hpl_comments_pl` FOREIGN KEY (`pl`) REFERENCES `workz_data`.`hpl`(`id`) ON DELETE CASCADE;
ALTER TABLE `hpl_comments` ADD CONSTRAINT `fk_hpl_comments_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;
ALTER TABLE `lke` ADD CONSTRAINT `fk_lke_pl` FOREIGN KEY (`pl`) REFERENCES `workz_data`.`hpl`(`id`) ON DELETE CASCADE;
ALTER TABLE `lke` ADD CONSTRAINT `fk_lke_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;
ALTER TABLE `usg` ADD CONSTRAINT `fk_usg_s0` FOREIGN KEY (`s0`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;
ALTER TABLE `usg` ADD CONSTRAINT `fk_usg_s1` FOREIGN KEY (`s1`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;
ALTER TABLE `work_history` ADD CONSTRAINT `fk_work_history_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;
ALTER TABLE `work_history` ADD CONSTRAINT `fk_work_history_em` FOREIGN KEY (`em`) REFERENCES `workz_companies`.`companies`(`id`) ON DELETE SET NULL;

-- =====================
-- workz_companies
-- =====================
USE `workz_companies`;
ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_em` FOREIGN KEY (`em`) REFERENCES `workz_companies`.`companies`(`id`) ON DELETE CASCADE;
ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;
ALTER TABLE `teams` ADD CONSTRAINT `fk_teams_em` FOREIGN KEY (`em`) REFERENCES `workz_companies`.`companies`(`id`) ON DELETE CASCADE;
ALTER TABLE `teams` ADD CONSTRAINT `fk_teams_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE RESTRICT;
ALTER TABLE `teams_users` ADD CONSTRAINT `fk_teams_users_cm` FOREIGN KEY (`cm`) REFERENCES `workz_companies`.`teams`(`id`) ON DELETE CASCADE;
ALTER TABLE `teams_users` ADD CONSTRAINT `fk_teams_users_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;

-- =====================
-- workz_apps
-- =====================
USE `workz_apps`;
ALTER TABLE `gapp` ADD CONSTRAINT `fk_gapp_ap` FOREIGN KEY (`ap`) REFERENCES `workz_apps`.`apps`(`id`) ON DELETE CASCADE;
ALTER TABLE `gapp` ADD CONSTRAINT `fk_gapp_us` FOREIGN KEY (`us`) REFERENCES `workz_data`.`hus`(`id`) ON DELETE CASCADE;
ALTER TABLE `gapp` ADD CONSTRAINT `fk_gapp_em` FOREIGN KEY (`em`) REFERENCES `workz_companies`.`companies`(`id`) ON DELETE CASCADE;

