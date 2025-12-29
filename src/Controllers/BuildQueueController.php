<?php
// src/Controllers/BuildQueueController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Core\Database;
use Workz\Platform\Models\General;

class BuildQueueController
{
    private string $dbName = 'workz_apps';

    private function requireWorkerSecret(): void
    {
        $provided = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
        $expected = $_ENV['WORKER_SECRET'] ?? 'seu-segredo-super-secreto';
        if (!$provided || $provided !== $expected) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit();
        }
    }

    private function ensureQueueTable(): void
    {
        try {
            $pdo = Database::getInstance($this->dbName);
            $sql = "CREATE TABLE IF NOT EXISTS `build_queue` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `app_id` INT UNSIGNED NOT NULL,
                `build_type` VARCHAR(64) NOT NULL DEFAULT 'flutter_web',
                `status` ENUM('pending','building','success','failed') NOT NULL DEFAULT 'pending',
                `platforms` VARCHAR(100) NULL,
                `build_log` MEDIUMTEXT NULL,
                `output_path` VARCHAR(500) NULL,
                `started_at` TIMESTAMP NULL,
                `completed_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_queue_status` (`status`),
                KEY `idx_queue_app` (`app_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);

            // Backwards‑compatible: garantir coluna platforms em instalações existentes.
            $pdo->exec("ALTER TABLE build_queue ADD COLUMN IF NOT EXISTS `platforms` VARCHAR(100) NULL");
        } catch (\Throwable $e) {
            // Silently ignore; subsequent operations will surface errors
        }
    }

    private function ensureFlutterBuildsTable(): void
    {
        try {
            $pdo = Database::getInstance($this->dbName);
            $sql = "CREATE TABLE IF NOT EXISTS `flutter_builds` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `app_id` INT UNSIGNED NOT NULL,
                `platform` ENUM('web','android','ios','windows','macos','linux') NOT NULL DEFAULT 'web',
                `build_version` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
                `file_path` VARCHAR(500) NULL,
                `file_size` BIGINT NULL,
                `download_url` VARCHAR(500) NULL,
                `store_url` VARCHAR(500) NULL,
                `status` ENUM('building','ready','published','failed','success','pending') NOT NULL DEFAULT 'building',
                `build_log` MEDIUMTEXT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_fb_app` (`app_id`),
                KEY `idx_fb_platform` (`platform`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * POST /api/build-queue/claim
     * Reivindica o próximo job pendente e retorna dados do app (slug + código)
     */
    public function claim(object $auth = null): void
    {
        header('Content-Type: application/json');
        $this->requireWorkerSecret();
        $this->ensureQueueTable();

        try {
            $pdo = Database::getInstance($this->dbName);
            $pdo->beginTransaction();

            // Seleciona 1 job pendente
            $stmt = $pdo->prepare("SELECT * FROM build_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 1 FOR UPDATE");
            $stmt->execute();
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$job) {
                $pdo->commit();
                http_response_code(204);
                echo json_encode(['success' => false, 'message' => 'No jobs']);
                return;
            }

            // Marca como building
            $upd = $pdo->prepare("UPDATE build_queue SET status='building', started_at=NOW() WHERE id=:id");
            $upd->execute([':id' => $job['id']]);
            $pdo->commit();

            // Busca dados do app
            $general = new General();
            $app = $general->search(
                $this->dbName,
                'apps',
                ['id','slug','app_type','storage_type','files','dart_code','source_code'],
                ['id' => (int)$job['app_id']],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado para o job', 'job_id' => $job['id']]);
                return;
            }

            // Atualiza status do app para building
            try { $general->update($this->dbName, 'apps', ['build_status' => 'building', 'last_build_at' => date('Y-m-d H:i:s')], ['id' => (int)$job['app_id']]); } catch (\Throwable $e) {}

            $payload = [
                'job_id' => (int)$job['id'],
                'app_id' => (int)$job['app_id'],
                'slug' => $app['slug'] ?? ('app-'.$job['app_id']),
                'build_type' => $job['build_type'] ?? 'flutter_web',
                'platform' => 'web', // usado apenas como fallback
                'platforms' => $job['platforms'] ?? 'web', // string para o worker interpretar
            ];

            // Fornece código
            $isFs = ($app['storage_type'] ?? 'database') === 'filesystem';
            // Sempre preferir dart_code; fallback para source_code; e, se ainda vazio, subir lib/main.dart dos files
            $payload['dart_code'] = $app['dart_code'] ?? '';
            if ((string)$payload['dart_code'] === '' && !empty($app['source_code'])) {
                $payload['dart_code'] = (string)$app['source_code'];
            }
            if ($isFs && !empty($app['files'])) {
                $filesArr = json_decode((string)$app['files'], true);
                if (is_array($filesArr)) {
                    $payload['files'] = $filesArr;
                    if ((string)$payload['dart_code'] === '') {
                        $main = $filesArr['lib/main.dart'] ?? null;
                        if (is_string($main) && trim($main) !== '') {
                            $payload['dart_code'] = $main;
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $payload]);
        } catch (\Throwable $e) {
            if (method_exists($pdo ?? null, 'rollBack')) { $pdo->rollBack(); }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao reivindicar job', 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/build-queue/update/{id}
     * Atualiza o status do job e sincroniza estado/artefatos do app.
     */
    public function updateJob(int $jobId): void
    {
        header('Content-Type: application/json');
        $this->requireWorkerSecret();
        $this->ensureQueueTable();
        $this->ensureFlutterBuildsTable();

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $status = $input['status'] ?? 'building';
        $log = $input['log'] ?? '';
        $appId = (int)($input['app_id'] ?? 0);
        $platform = $input['platform'] ?? 'web';
        $buildVersion = $input['build_version'] ?? '1.0.0';
        $filePath = $input['file_path'] ?? null;

        try {
            $pdo = Database::getInstance($this->dbName);

            // Atualiza job
            $cols = ['status' => $status, 'build_log' => $log, 'updated_at' => date('Y-m-d H:i:s')];
            if (in_array($status, ['success','failed'], true)) {
                $cols['completed_at'] = date('Y-m-d H:i:s');
            }
            if (!empty($filePath)) { $cols['output_path'] = $filePath; }

            // Build UPDATE statement
            $set = [];$params=[];
            foreach ($cols as $k=>$v) { $set[] = "`$k` = :$k"; $params[":$k"] = $v; }
            $params[':id'] = $jobId;
            $stmt = $pdo->prepare("UPDATE build_queue SET ".implode(', ',$set)." WHERE id = :id");
            $stmt->execute($params);

            // Atualiza status geral do app
            $general = new General();
            if ($appId > 0) {
                $general->update($this->dbName, 'apps', ['build_status' => $status, 'last_build_at' => date('Y-m-d H:i:s')], ['id' => $appId]);
            }

            // Upsert em flutter_builds (apenas para Flutter/web)
            if ($appId > 0) {
                $existingBuild = $general->search(
                    $this->dbName,
                    'flutter_builds',
                    ['id'],
                    ['app_id' => $appId, 'platform' => $platform],
                    false
                );

                $buildData = [
                    'app_id' => $appId,
                    'platform' => $platform,
                    'build_version' => $buildVersion,
                    'status' => $status,
                    'file_path' => $filePath,
                    'build_log' => $log,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if ($existingBuild) {
                    $general->update($this->dbName, 'flutter_builds', $buildData, ['id' => $existingBuild['id']]);
                } else {
                    $general->insert($this->dbName, 'flutter_builds', $buildData);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Job atualizado']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar job', 'error' => $e->getMessage()]);
        }
    }
}
