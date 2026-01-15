# Workz Platform

## Media processing (OCI Object Storage)

Este projeto inclui upload de imagens/videos curtos via `/api/media`, com transcodificacao para MP4 480p (720p opcional), thumbnails e armazenamento no OCI Object Storage via compatibilidade S3.

### Variaveis de ambiente

Configure no `.env`:

- `MEDIA_DB_NAME` (padrao `workz_data`)
- `MEDIA_RAW_DIR` (padrao `/var/app/uploads/raw`)
- `MEDIA_TMP_DIR` (padrao `/var/app/uploads/tmp`)
- `MEDIA_MAX_SIZE_MB` (padrao `128`)
- `MEDIA_MAX_VIDEO_SECONDS` (padrao `60`)
- `MEDIA_WORKER_BATCH` (itens por execucao do worker)
- `MEDIA_URL_TTL_SECONDS` (padrao `3600`)

OCI S3 compatibility:

- `OCI_S3_REGION=sa-vinhedo-1`
- `OCI_S3_NAMESPACE=axgwwavxlzco`
- `OCI_S3_ENDPOINT=https://axgwwavxlzco.compat.objectstorage.sa-vinhedo-1.oci.customer-oci.com`
- `OCI_BUCKET=...`
- `OCI_S3_ACCESS_KEY=...`
- `OCI_S3_SECRET_KEY=...`
- `OCI_PRESIGN_TTL=3600` (segundos)

### Migracao

Execute a migracao `database/migrations/2025_11_13_create_media_table.sql` no schema `workz_data`.

### Worker

O worker processa items em `status=processing/queued` e atualiza para `ready` ou `error`.
Requer `ffmpeg` e `ffprobe` disponiveis no PATH.

Exemplo com cron (a cada minuto):

```bash
* * * * * cd /opt/workz_platform && php bin/media_worker.php >> /var/log/media_worker.log 2>&1
```

Exemplo com Supervisor:

```
[program:media_worker]
command=php /opt/workz_platform/bin/media_worker.php
autostart=true
autorestart=true
stderr_logfile=/var/log/media_worker.err.log
stdout_logfile=/var/log/media_worker.out.log
```
