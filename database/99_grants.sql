-- Concede privilégios ao usuário de aplicação para todos os schemas usados
-- Executado automaticamente no primeiro boot (docker-entrypoint-initdb.d)

GRANT ALL PRIVILEGES ON `workz_data`.*      TO 'workz'@'%';
GRANT ALL PRIVILEGES ON `workz_companies`.* TO 'workz'@'%';
GRANT ALL PRIVILEGES ON `workz_apps`.*      TO 'workz'@'%';
FLUSH PRIVILEGES;

