-- Concede privilégios ao usuário de aplicação para os schemas do Workz Platform
-- Execute com um usuário com privilégios (ex.: root)

CREATE USER IF NOT EXISTS 'workz'@'%' IDENTIFIED BY 'workzpass';
GRANT ALL PRIVILEGES ON `workz_data`.*      TO 'workz'@'%';
GRANT ALL PRIVILEGES ON `workz_companies`.* TO 'workz'@'%';
GRANT ALL PRIVILEGES ON `workz_apps`.*      TO 'workz'@'%';
FLUSH PRIVILEGES;

