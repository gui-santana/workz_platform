-- Adiciona controle de vigência para apps instalados (suporte/serviços)

ALTER TABLE `gapp`
  ADD COLUMN `end_date` DATE NULL AFTER `start_date`,
  ADD INDEX `idx_end_date` (`end_date`);

