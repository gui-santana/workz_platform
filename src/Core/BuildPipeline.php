<?php
// src/Core/BuildPipeline.php

namespace Workz\Platform\Core;

use Workz\Platform\Core\Database;
use Workz\Platform\Models\General;

/**
 * Minimal BuildPipeline implementation to support app creation and basic build flows.
 * - Exposes artifact discovery for Flutter web builds
 * - Enqueues build jobs into build_queue for the worker to pick up
 */
class BuildPipeline
{
    /**
     * Garante que a tabela build_queue exista (compatível com BuildQueueController).
     */
    private function ensureQueueTable(): void
    {
        try {
            $pdo = Database::getInstance('workz_apps');
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
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `build_queue` LIKE 'platforms'");
            $stmt->execute();
            $platformColumn = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$platformColumn) {
                $pdo->exec("ALTER TABLE `build_queue` ADD COLUMN `platforms` VARCHAR(100) NULL AFTER `build_type`");
            }
        } catch (\Throwable $e) {
            // Silently ignore; subsequent operations will surface errors
        }
    }

    /**
     * Garante que a tabela flutter_builds exista antes de ler status detalhados.
     */
    private function ensureFlutterBuildsTable(): void
    {
        try {
            $pdo = Database::getInstance('workz_apps');
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
            // ignore; queries will surface errors if creation fails
        }
    }

    /**
     * Return artifacts info for an app across platforms.
     * Currently focuses on Flutter web and mirrors rows from flutter_builds table.
     */
    public function getArtifacts(int $appId): array
    {
        $general = new General();
        try {
            $rows = $general->search(
                'workz_apps',
                'flutter_builds',
                ['platform','status','file_path','build_version','updated_at','build_log'],
                ['app_id' => $appId],
                true,
                20,
                0,
                ['by' => 'updated_at', 'dir' => 'DESC']
            ) ?: [];
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve a downloadable artifact path for a given platform.
     * For web, returns the index.html under the canonical folder if present.
     */
    public function getArtifactPath(int $appId, string $platform): ?string
    {
        $publicRoot = dirname(__DIR__, 2) . '/public';
        if ($platform === 'web') {
            $dir = $publicRoot . "/apps/flutter/{$appId}/web";
            $index = $dir . '/index.html';
            if (is_file($index)) { return $index; }
        }
        return null;
    }

    /**
     * Trigger a build by enqueueing a job into build_queue.
     */
    public function triggerBuild(int $appId, $platforms = null, array $options = []): array
    {
        // Garante que a tabela esteja disponível antes de inserir
        $this->ensureQueueTable();
        // Normaliza plataformas para uma lista simples de strings ("web", "android", ...)
        $platformList = null;
        if (is_array($platforms)) {
            $normalized = [];
            foreach ($platforms as $p) {
                $p = strtolower(trim((string)$p));
                if ($p === '') {
                    continue;
                }
                $normalized[] = $p;
            }
            if (!empty($normalized)) {
                $platformList = array_values(array_unique($normalized));
            }
        }

        // String compacta para persistir na fila (ex: "web,android").
        // Mantém compatibilidade: se não houver valor, deixamos null e o worker assume web.
        $platformsString = $platformList ? implode(',', $platformList) : null;

        try {
            $general = new General();
            $row = [
                'app_id' => $appId,
                'build_type' => 'flutter_web',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if ($platformsString !== null) {
                $row['platforms'] = $platformsString;
            }

            $general->insert('workz_apps', 'build_queue', $row);

            return [
                'success' => true,
                'message' => 'Build enqueued',
                'platforms' => $platformList ?: ['web'],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Basic build status aggregation from build_queue + flutter_builds.
     */
    public function getBuildStatus(int $appId): array
    {
        $this->ensureQueueTable();
        $this->ensureFlutterBuildsTable();

        $general = new General();

        $status = [
            'status'       => 'pending',
            'platforms'    => ['web'],
            'artifacts'    => [],
            'build_id'     => null,
            'started_at'   => null,
            'completed_at' => null,
            'duration'     => null,
            'errors'       => [],
            'warnings'     => [],
            'logs'         => [],
        ];

        $queueStatus = null;

        // Estado agregado da fila (build_queue)
        try {
            $queueRows = $general->search(
                'workz_apps',
                'build_queue',
                ['id','status','platforms','started_at','completed_at','created_at','updated_at','build_log'],
                ['app_id' => $appId],
                true,
                1,
                0,
                ['by' => 'updated_at', 'dir' => 'DESC']
            );
            if (!empty($queueRows)) {
                $q = $queueRows[0];
                $status['build_id']     = $q['id'] ?? null;
                $queueStatus            = $q['status'] ?? 'pending';
                $status['status']       = $queueStatus;
                $status['started_at']   = $q['started_at'] ?? $q['created_at'] ?? null;
                $status['completed_at'] = $q['completed_at'] ?? null;
                if (!empty($q['platforms'])) {
                    $status['platforms'] = array_values(
                        array_filter(array_map('trim', explode(',', (string)$q['platforms'])))
                    );
                }
                // Duração simples em segundos, se possível
                if (!empty($status['started_at']) && !empty($status['completed_at'])) {
                    try {
                        $start = new \DateTime($status['started_at']);
                        $end   = new \DateTime($status['completed_at']);
                        $status['duration'] = (string)($end->getTimestamp() - $start->getTimestamp());
                    } catch (\Throwable $e) {
                        $status['duration'] = null;
                    }
                }
                if (!empty($q['build_log'])) {
                    $status['logs'][] = $q['build_log'];
                }
            }
        } catch (\Throwable $e) {
            // Mantém defaults silenciosamente; status continuará como pending
        }

        // Artefatos por plataforma (flutter_builds)
        try {
            $builds = $general->search(
                'workz_apps',
                'flutter_builds',
                ['id','platform','file_path','status','build_version','build_log','created_at','updated_at'],
                ['app_id' => $appId],
                true,
                10,
                0,
                ['by' => 'updated_at', 'dir' => 'DESC']
            ) ?: [];

            $status['artifacts'] = $builds;
            if (!empty($builds)) {
                // Status baseado nos artefatos mais recentes
                $latestArtifactStatus = $builds[0]['status'] ?? null;

                // Regra de precedência:
                // - se há build em andamento/na fila, confiar no status da fila (pending/building)
                // - caso contrário, usar o status do artefato mais recente
                if (!in_array($queueStatus, ['pending', 'building'], true) && $latestArtifactStatus) {
                    $status['status'] = $latestArtifactStatus;
                }
            }
        } catch (\Throwable $e) {
            // Ignora erros de leitura dos artefatos para não quebrar o fluxo
        }

        return $status;
    }
}
