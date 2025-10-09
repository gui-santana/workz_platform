-- Corrige instância existente adicionando colunas esperadas pelo frontend
USE `workz_data`;
ALTER TABLE `hpl`
  ADD COLUMN IF NOT EXISTS `tt` VARCHAR(255) NULL AFTER `us`,
  ADD COLUMN IF NOT EXISTS `im` VARCHAR(255) NULL AFTER `ct`;

