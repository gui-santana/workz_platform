# Workz Platform — Database Quick Restore

Este guia resume como criar/restaurar os bancos do projeto usando Docker + MySQL 8.

## Visão Geral
- Serviço: `workz_platform_mysql` (MySQL 8), porta host `3400` → container `3306`.
- Volume: `db_data` montado em `/var/lib/mysql` (persistente).
- Schemas usados pelo app: `workz_data` (principal), `workz_companies`, `workz_apps`.
- Padrões atuais:
  - `docker-compose.yml` → `MYSQL_DATABASE: workz_data`.
  - `.env` → `DB_NAME=workz_data`, `DB_DATABASE=workz_data`, `DB_HOST=mysql`, `DB_USERNAME=workz`, `DB_PASSWORD=workzpass`.

## Restauração Rápida (schemas + seed opcional)
1) Suba o MySQL e aguarde ficar saudável:
```
docker compose up -d mysql
docker compose ps mysql
```
2) Recrie os schemas e tabelas (idempotente):
```
docker cp database/recreate_workz_platform_db.sql workz_platform_mysql:/tmp/recreate.sql
docker exec -i workz_platform_mysql sh -lc "mysql -uroot -proot_password < /tmp/recreate.sql"
```
3) Opcional — importar dados de exemplo (seed):
```
docker cp database/seed_workz_platform.sql workz_platform_mysql:/tmp/seed.sql
docker exec -i workz_platform_mysql sh -lc "mysql -uroot -proot_password < /tmp/seed.sql"
```
4) Validar:
- phpMyAdmin: http://localhost:8081 (user `root`, senha `root_password`).
- Endpoint: http://localhost:9090/app/test_db.php (deve apontar para `workz_data`).

## Reanexar volume antigo (se necessário)
1) Liste volumes e procure algum que contenha “workz”:
```
docker volume ls
```
2) Se quiser usar um volume antigo (ex.: `workz_platform_db_data_old`), edite `docker-compose.yml`:
```
volumes:
  db_data:
    external: true
    name: workz_platform_db_data_old
```
3) Recrie os serviços:
```
docker compose down
docker compose up -d
```

## Backup e Restore manuais
- Dump das três bases:
```
docker exec -i workz_platform_mysql sh -lc 'mysqldump -uroot -proot_password --databases workz_data workz_companies workz_apps' > database/backup.sql
```
- Restore a partir de `backup.sql`:
```
docker exec -i workz_platform_mysql sh -lc 'mysql -uroot -proot_password' < database/backup.sql
```

## Dicas de Solução de Problemas
- Container não “healthy”:
  - `docker logs -f workz_platform_mysql`
  - `docker exec -it workz_platform_mysql sh -lc "mysqladmin -uroot -proot_password ping"`
- Usuário `workz` sem permissão:
```
docker exec -i workz_platform_mysql sh -lc "mysql -uroot -proot_password -e \"GRANT ALL PRIVILEGES ON workz_data.* TO 'workz'@'%'; GRANT ALL PRIVILEGES ON workz_companies.* TO 'workz'@'%'; GRANT ALL PRIVILEGES ON workz_apps.* TO 'workz'@'%'; FLUSH PRIVILEGES;\""
```
- Alinhamento de ambiente:
  - `.env` deve conter `DB_NAME=workz_data` e/ou `DB_DATABASE=workz_data`.
  - O backend acessa explicitamente os três schemas; mantenha-os existentes no MySQL.

---
Para verificações rápidas em desenvolvimento: `http://localhost:9090/app/test_db.php` e `http://localhost:8081`.
