<?php
// src/Core/BuildPipeline.php

namespace Workz\Platform\Core;

use Workz\Platform\Models\General;

/**
 * Minimal BuildPipeline implementation to support app creation and basic build flows.
 * - Exposes artifact discovery for Flutter web builds
 * - Enqueues build jobs into build_queue for the worker to pick up
 */
class BuildPipeline
{
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
        try {
            $general = new General();
            $general->insert('workz_apps', 'build_queue', [
                'app_id' => $appId,
                'build_type' => 'flutter_web',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return ['success' => true, 'message' => 'Build enqueued'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Basic build status aggregation from build_queue + flutter_builds.
     */
    public function getBuildStatus(int $appId): array
    {
        $general = new General();
        $status = [
            'status' => 'pending',
            'platforms' => ['web'],
            'artifacts' => [],
        ];
        try {
            $q = $general->search(
                'workz_apps',
                'build_queue',
                ['status','updated_at','created_at'],
                ['app_id' => $appId],
                true,
                1,
                0,
                ['by' => 'updated_at', 'dir' => 'DESC']
            );
            if (!empty($q)) { $status['status'] = $q[0]['status'] ?? 'pending'; }
        } catch (\Throwable $e) {}

        try {
            $builds = $general->search(
                'workz_apps',
                'flutter_builds',
                ['platform','file_path','status','updated_at'],
                ['app_id' => $appId],
                true,
                10,
                0,
                ['by' => 'updated_at', 'dir' => 'DESC']
            ) ?: [];
            $status['artifacts'] = $builds;
            if (!empty($builds)) { $status['status'] = $builds[0]['status'] ?? $status['status']; }
        } catch (\Throwable $e) {}

        return $status;
    }
}
