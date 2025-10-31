<?php
// src/Controllers/AppManagementController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Workz\Platform\Core\StorageManager;
use Workz\Platform\Core\BuildPipeline;
use Workz\Platform\Policies\BusinessPolicy;
class AppManagementController
{
    private General $generalModel;
    private StorageManager $storageManager;
    private BuildPipeline $buildPipeline;

    private function getTableColumns(string $table): array
    {
        try {
            $pdo = \Workz\Platform\Core\Database::getInstance('workz_apps');
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $meta = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $meta; // each row: Field, Type, Null, Key, Default, Extra
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $pdo = \Workz\Platform\Core\Database::getInstance('workz_apps');
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :c");
            $stmt->execute([':c' => $column]);
            return (bool)$stmt->fetch();
        } catch (\Throwable $e) { return false; }
    }

    private function filterColumns(string $table, array $data): array
    {
        $meta = $this->getTableColumns($table);
        if (empty($meta)) return $data;
        $allowed = array_flip(array_map(fn($c) => $c['Field'] ?? '', $meta));
        return array_intersect_key($data, $allowed);
    }

    private function fillRequiredColumns(string $table, array $data): array
    {
        $meta = $this->getTableColumns($table);
        if (empty($meta)) return $data;

        foreach ($meta as $col) {
            $name = $col['Field'] ?? '';
            if ($name === '' || array_key_exists($name, $data)) continue;

            $isNullable = strtoupper((string)$col['Null']) === 'YES';
            $hasDefault = $col['Default'] !== null;
            if ($isNullable || $hasDefault) continue;

            $type = strtolower((string)$col['Type']);

            if ($name === 'is_default') { $data[$name] = 0; continue; }
            if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
                $data[$name] = 0; continue;
            }
            if (preg_match("/enum\((.*?)\)/i", $type, $m)) {
                $opts = array_map(fn($s) => trim($s, "' \""), explode(',', $m[1]));
                $data[$name] = $opts[0] ?? '';
                // Prefer sensible defaults for known fields
                if ($name === 'build_status' || $name === 'status') { $data[$name] = in_array('pending', $opts, true) ? 'pending' : ($opts[0] ?? ''); }
                continue;
            }
            if (str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
                $data[$name] = date('Y-m-d H:i:s'); continue;
            }
            // Fallback for varchar/text
            $data[$name] = '';
        }
        return $data;
    }

    public function __construct()
    {
        $this->generalModel = new General();
        $this->storageManager = new StorageManager();
        $this->buildPipeline = new BuildPipeline();
    }

    // ==================== STORAGE MANAGEMENT APIs ====================

    /**
     * GET /api/apps/{id}/storage
     * Get storage information for an app
     */
    public function getStorageInfo(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            $storageInfo = [
                'storage_type' => $app['storage_type'] ?? 'database',
                'repository_path' => $app['repository_path'],
                'code_size_bytes' => (int)($app['code_size_bytes'] ?? 0),
                'last_migration_at' => $app['last_migration_at'],
                'git_branch' => $app['git_branch'] ?? 'main',
                'git_commit_hash' => $app['git_commit_hash'],
                'can_migrate_to_filesystem' => $this->canMigrateToFilesystem($app),
                'can_migrate_to_database' => $this->canMigrateToDatabase($app)
            ];

            echo json_encode(['data' => $storageInfo]);

        } catch (\Throwable $e) {
            error_log("Error getting storage info: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * POST /api/apps/{id}/storage/migrate
     * Migrate app between storage types
     */
    public function migrateStorage(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $targetType = $input['target_type'] ?? '';
            
            if (!in_array($targetType, ['database', 'filesystem'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid target_type. Must be "database" or "filesystem"']);
                return;
            }

            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            $currentType = $app['storage_type'] ?? 'database';
            if ($currentType === $targetType) {
                http_response_code(400);
                echo json_encode(['error' => 'App is already using ' . $targetType . ' storage']);
                return;
            }

            // Perform migration
            $result = $this->storageManager->migrateApp($appId, $targetType);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Migration completed successfully',
                    'data' => [
                        'previous_type' => $currentType,
                        'new_type' => $targetType,
                        'migration_id' => $result['migration_id'] ?? null
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Migration failed',
                    'message' => $result['error'] ?? 'Unknown error'
                ]);
            }

        } catch (\Throwable $e) {
            error_log("Error migrating storage: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/apps/{id}/code
     * Get app code (storage-agnostic)
     */
    public function getCode(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            $code = $this->storageManager->getAppCode($appId);
            
            echo json_encode([
                'data' => [
                    'app_id' => $appId,
                    'app_type' => $app['app_type'] ?? 'javascript',
                    'storage_type' => $app['storage_type'] ?? 'database',
                    'js_code' => $code['js_code'] ?? null,
                    'dart_code' => $code['dart_code'] ?? null,
                    'files' => $code['files'] ?? [],
                    'last_modified' => $code['last_modified'] ?? null
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Error getting app code: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * PUT /api/apps/{id}/code
     * Update app code (storage-agnostic)
     */
    public function updateCode(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            
            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            $codeData = [
                'js_code' => $input['js_code'] ?? null,
                'dart_code' => $input['dart_code'] ?? null,
                'files' => $input['files'] ?? [],
                'commit_message' => $input['commit_message'] ?? 'Update app code'
            ];

            $result = $this->storageManager->saveAppCode($appId, $codeData);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Code updated successfully',
                    'data' => [
                        'app_id' => $appId,
                        'storage_type' => $app['storage_type'] ?? 'database',
                        'code_size_bytes' => $result['code_size_bytes'] ?? 0,
                        'git_commit_hash' => $result['git_commit_hash'] ?? null
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to update code',
                    'message' => $result['error'] ?? 'Unknown error'
                ]);
            }

        } catch (\Throwable $e) {
            error_log("Error updating app code: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/apps/{id}/artifacts
     * Get build artifacts
     */
    public function getArtifacts(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            $artifacts = $this->buildPipeline->getArtifacts($appId);
            
            echo json_encode([
                'data' => [
                    'app_id' => $appId,
                    'app_type' => $app['app_type'] ?? 'javascript',
                    'artifacts' => $artifacts,
                    'last_build_at' => $app['last_build_at'] ?? null,
                    'build_status' => $app['build_status'] ?? 'pending'
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Error getting artifacts: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/apps/{id}/artifacts/{platform}
     * Download specific platform artifact
     */
    public function downloadArtifact(object $auth, string $appId, string $platform): void
    {
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            $artifactPath = $this->buildPipeline->getArtifactPath($appId, $platform);
            
            if (!$artifactPath || !file_exists($artifactPath)) {
                http_response_code(404);
                echo json_encode(['error' => 'Artifact not found for platform: ' . $platform]);
                return;
            }

            // Set appropriate headers for file download
            $filename = basename($artifactPath);
            $mimeType = $this->getMimeTypeForPlatform($platform);
            
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($artifactPath));
            
            readfile($artifactPath);

        } catch (\Throwable $e) {
            error_log("Error downloading artifact: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    } 
   // ==================== APP MANAGEMENT APIs ====================

    /**
     * POST /api/apps/create
     * Create new app with storage model support
     */
    public function createApp(object $auth): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            // Normalize field names from frontend
            $input['tt'] = $input['tt'] ?? $input['title'] ?? null;
            $input['ds'] = $input['ds'] ?? $input['description'] ?? '';
            $input['dart_code'] = $input['dart_code'] ?? $input['app_code'] ?? '';
            $input['js_code'] = $input['js_code'] ?? '';
            // Robustly resolve app_type (favor Flutter if signals are present)
            $rawType = strtolower(trim((string)($input['app_type'] ?? $input['appType'] ?? '')));
            $hasFlutterSignals = (
                !empty($input['files']) && is_array($input['files'])
            ) || (
                isset($input['dart_code']) && is_string($input['dart_code']) && trim($input['dart_code']) !== ''
            );
            // Heuristic: textarea enviado em js_code mas com Dart/Flutter
            $jsLooksLikeDart = false;
            if ($rawType !== 'flutter' && empty($input['dart_code']) && !empty($input['js_code'])) {
                $js = (string)$input['js_code'];
                $needle1 = strpos($js, "package:flutter/") !== false;
                $needle2 = preg_match('/void\s+main\s*\(/i', $js) === 1;
                $needle3 = strpos($js, 'MaterialApp') !== false;
                $jsLooksLikeDart = ($needle1 || ($needle2 && $needle3));
                if ($jsLooksLikeDart) {
                    $input['dart_code'] = $js;
                    $input['js_code'] = '';
                    $hasFlutterSignals = true;
                }
            }
            if ($rawType !== 'flutter' && $hasFlutterSignals) {
                $input['app_type'] = 'flutter';
            } else {
                $input['app_type'] = $rawType ?: 'javascript';
            }
            if (empty($input['slug']) && !empty($input['tt'])) {
                $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $input['tt']), '-'));
                $input['slug'] = $slug ?: null;
            }

            // Validate required fields (after normalization)
            $requiredFields = ['tt', 'slug', 'app_type'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Field '$field' is required"]);
                    return;
                }
            }

            // Check if slug already exists
            $existingApp = $this->generalModel->search(
                'workz_apps',
                'apps',
                ['id'],
                ['slug' => $input['slug']],
                false
            );

            if ($existingApp) {
                http_response_code(409);
                echo json_encode(['error' => 'App with this slug already exists']);
                return;
            }

            // Determine storage type based on app type and size
            $appType = strtolower((string)$input['app_type']);
            $storageType = $this->determineStorageType($appType, $input);

            // Defaults derived from context
            $publisherDefault = $input['publisher'] ?? ($auth->name ?? 'Workz Platform');
            $sourceLanguage = ($appType === 'flutter') ? 'dart' : 'javascript';

            // Prepare app data
            // Company linkage: set as exclusive owner when provided (supports company_id or company.id)
            $exclusiveTo = null;
            if (isset($input['company_id'])) { $exclusiveTo = (int)$input['company_id']; }
            elseif (isset($input['company']) && is_array($input['company']) && isset($input['company']['id'])) { $exclusiveTo = (int)$input['company']['id']; }

            // Normalize files: textarea (dart_code) must be the source of truth for Flutter
            $filesNormalized = [];
            if (!empty($input['files']) && is_array($input['files'])) {
                $filesNormalized = $input['files'];
            }
            if ($appType === 'flutter') {
                $dc = (string)($input['dart_code'] ?? '');
                if (trim($dc) !== '') {
                    // Force textarea code into lib/main.dart regardless of incoming files
                    $filesNormalized['lib/main.dart'] = $dc;
                }
            }

            $appData = [
                'tt' => $input['tt'],
                'slug' => $input['slug'],
                'ds' => $input['ds'] ?? '',
                'app_type' => $appType,
                'storage_type' => $storageType,
                'js_code' => $input['js_code'] ?? '',
                'dart_code' => $input['dart_code'] ?? '',
                'exclusive_to_entity_id' => $exclusiveTo,
                'im' => $input['im'] ?? '/images/no-image.jpg',
                'color' => $input['color'] ?? '#3b82f6',
                'vl' => $input['vl'] ?? 0.00,
                'access_level' => $input['access_level'] ?? 1,
                'entity_type' => $input['entity_type'] ?? 0,
                'scopes' => json_encode($input['scopes'] ?? []),
                'version' => $input['version'] ?? '1.0.0',
                'publisher' => $publisherDefault,
                'st' => 1,
                'us' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            if (!empty($filesNormalized)) { $appData['files'] = json_encode($filesNormalized); }

            // If schema supports source_language/source_code, fill with safe values
            if ($this->hasColumn('apps', 'source_language')) { $appData['source_language'] = $sourceLanguage; }
            if ($this->hasColumn('apps', 'source_code')) {
                $appData['source_code'] = $appType === 'flutter' ? ($appData['dart_code'] ?? '') : ($appData['js_code'] ?? '');
            }

            // Calculate initial code size
            $codeSize = strlen($appData['js_code']) + strlen($appData['dart_code']);
            $appData['code_size_bytes'] = $codeSize;

            // Provide safe defaults required by some schemas
            if ($this->hasColumn('apps', 'is_default') && !isset($appData['is_default'])) {
                $appData['is_default'] = 0; // default to non-default app
            }

            // Create app record (filter + ensure required columns have safe values)
            $appDataFiltered = $this->filterColumns('apps', $appData);
            $appDataFiltered = $this->fillRequiredColumns('apps', $appDataFiltered);
            $appId = $this->generalModel->insert('workz_apps', 'apps', $appDataFiltered);

            if (!$appId) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create app']);
                return;
            }

            // Initialize storage based on type
            if ($storageType === 'filesystem') {
                // Seed repo with normalized files metadata for consistency
                $seedData = $input;
                if (!empty($filesNormalized)) { $seedData['files'] = $filesNormalized; }
                $initResult = $this->storageManager->initializeFilesystemStorage($appId, $seedData);
                if (!$initResult['success']) {
                    // Rollback app creation
                    $this->generalModel->delete('workz_apps', 'apps', ['id' => $appId]);
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to initialize filesystem storage']);
                    return;
                }
                
                // Update repository path
                $this->generalModel->update(
                    'workz_apps',
                    'apps',
                    ['repository_path' => $initResult['repository_path']],
                    ['id' => $appId]
                );
            }

            // Create user-app relationship (tolerant to minimal schema)
            try {
                $this->generalModel->insert('workz_apps', 'gapp', [
                    'us' => $userId,
                    'ap' => $appId,
                    'st' => 1,
                ]);
            } catch (\Throwable $e) { /* ignore non-critical */ }

            // Enqueue initial build for Flutter apps so the frontend only needs to monitor status
            $buildStatus = null;
            if (strtolower($appType) === 'flutter') {
                $enqueue = $this->buildPipeline->triggerBuild((int)$appId);
                if (!empty($enqueue['success'])) {
                    $buildStatus = 'pending';
                    // Best effort: reflect status on app row if schema supports it
                    try { $this->generalModel->update('workz_apps', 'apps', ['build_status' => $buildStatus, 'last_build_at' => date('Y-m-d H:i:s')], ['id' => $appId]); } catch (\Throwable $e) {}
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'App created successfully',
                'data' => [
                    'id' => $appId,
                    'slug' => $input['slug'],
                    'storage_type' => $storageType,
                    'app_type' => $appType,
                    'build_status' => $buildStatus
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Error creating app: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * PUT /api/apps/{id}
     * Update app with storage model support
     */
    public function updateApp(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            
            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            // Prepare update data (only allow certain fields to be updated)
            $allowedFields = ['tt', 'ds', 'im', 'color', 'vl', 'access_level', 'scopes', 'version', 'publisher'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if ($field === 'scopes') {
                        $updateData[$field] = json_encode($input[$field]);
                    } else {
                        $updateData[$field] = $input[$field];
                    }
                }
            }

            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields to update']);
                return;
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            // Update app
            $result = $this->generalModel->update(
                'workz_apps',
                'apps',
                $updateData,
                ['id' => $appId]
            );

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'App updated successfully',
                    'data' => [
                        'id' => $appId,
                        'updated_fields' => array_keys($updateData)
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update app']);
            }

        } catch (\Throwable $e) {
            error_log("Error updating app: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/apps/{id}
     * Get app details with storage information
     */
    public function getApp(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            // Include storage information
            $appData = $app;
            $appData['scopes'] = json_decode($app['scopes'] ?? '[]', true);
            
            // Add storage-specific information
            $appData['storage_info'] = [
                'storage_type' => $app['storage_type'] ?? 'database',
                'repository_path' => $app['repository_path'],
                'code_size_bytes' => (int)($app['code_size_bytes'] ?? 0),
                'git_branch' => $app['git_branch'] ?? 'main',
                'git_commit_hash' => $app['git_commit_hash']
            ];

            // Add build information if available
            $appData['build_info'] = [
                'last_build_at' => $app['last_build_at'],
                'build_status' => $app['build_status'] ?? 'pending',
                'build_version' => $app['build_version']
            ];

            echo json_encode(['data' => $appData]);

        } catch (\Throwable $e) {
            error_log("Error getting app: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // ==================== BUILD MANAGEMENT APIs ====================

    /**
     * POST /api/apps/{id}/build
     * Trigger app build
     */
    public function triggerBuild(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            
            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            $platforms = $input['platforms'] ?? null;
            $buildOptions = $input['options'] ?? [];

            // Trigger build
            $buildResult = $this->buildPipeline->triggerBuild($appId, $platforms, $buildOptions);
            
            if ($buildResult['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Build triggered successfully',
                    'data' => [
                        'build_id' => $buildResult['build_id'],
                        'app_id' => $appId,
                        'platforms' => $buildResult['platforms'],
                        'status' => 'building',
                        'estimated_duration' => $buildResult['estimated_duration'] ?? null
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to trigger build',
                    'message' => $buildResult['error'] ?? 'Unknown error'
                ]);
            }

        } catch (\Throwable $e) {
            error_log("Error triggering build: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/apps/{id}/build-status
     * Get build status and details
     */
    public function getBuildStatus(object $auth, string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App not found or access denied']);
                return;
            }

            $buildStatus = $this->buildPipeline->getBuildStatus($appId);
            
            echo json_encode([
                'data' => [
                    'app_id' => $appId,
                    'build_status' => $buildStatus['status'] ?? 'pending',
                    'build_id' => $buildStatus['build_id'] ?? null,
                    'platforms' => $buildStatus['platforms'] ?? [],
                    'started_at' => $buildStatus['started_at'] ?? null,
                    'completed_at' => $buildStatus['completed_at'] ?? null,
                    'duration' => $buildStatus['duration'] ?? null,
                    'artifacts' => $buildStatus['artifacts'] ?? [],
                    'errors' => $buildStatus['errors'] ?? [],
                    'warnings' => $buildStatus['warnings'] ?? [],
                    'logs' => $buildStatus['logs'] ?? []
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Error getting build status: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * POST /api/apps/{id}/build/webhook
     * Webhook for build completion notifications
     */
    public function buildWebhook(string $appId): void
    {
        header("Content-Type: application/json");
        
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            
            // Validate webhook signature if configured
            $webhookSecret = $_ENV['BUILD_WEBHOOK_SECRET'] ?? '';
            if ($webhookSecret) {
                $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
                $expectedSignature = 'sha256=' . hash_hmac('sha256', file_get_contents('php://input'), $webhookSecret);
                
                if (!hash_equals($expectedSignature, $signature)) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid webhook signature']);
                    return;
                }
            }

            // Process webhook data
            $buildId = $input['build_id'] ?? '';
            $status = $input['status'] ?? '';
            $platforms = $input['platforms'] ?? [];
            $artifacts = $input['artifacts'] ?? [];
            $errors = $input['errors'] ?? [];

            // Update build status in database
            $updateData = [
                'build_status' => $status,
                'build_completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if (!empty($artifacts)) {
                $updateData['build_artifacts'] = json_encode($artifacts);
            }

            if (!empty($errors)) {
                $updateData['build_errors'] = json_encode($errors);
            }

            $this->generalModel->update(
                'workz_apps',
                'apps',
                $updateData,
                ['id' => $appId]
            );

            // Send notifications if configured
            $this->sendBuildNotifications($appId, $status, $buildId);

            echo json_encode([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Throwable $e) {
            error_log("Error processing build webhook: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // ==================== HELPER METHODS ====================

    private function getAppWithPermissionCheck(string $appId, int $userId): ?array
    {
        // Get app details
        $app = $this->generalModel->search(
            'workz_apps',
            'apps',
            ['*'],
            ['id' => $appId],
            false
        );

        if (!$app) {
            return null;
        }

        // Check if user has access to this app
        $hasAccess = $this->generalModel->count(
            'workz_apps',
            'gapp',
            ['us' => $userId, 'ap' => $appId, 'st' => 1]
        ) > 0;

        if ($hasAccess) { return $app; }

        $companyId = (int)($app['exclusive_to_entity_id'] ?? 0);
        if ($companyId > 0) {
            try {
                if (BusinessPolicy::canManage($userId, $companyId)) {
                    return $app;
                }
            } catch (\Throwable $e) { }
        }

        return null;
    }

    private function canMigrateToFilesystem(array $app): bool
    {
        $currentType = $app['storage_type'] ?? 'database';
        return $currentType === 'database';
    }

    private function canMigrateToDatabase(array $app): bool
    {
        $currentType = $app['storage_type'] ?? 'database';
        $codeSize = (int)($app['code_size_bytes'] ?? 0);
        $threshold = 50 * 1024; // 50KB
        
        return $currentType === 'filesystem' && $codeSize <= $threshold;
    }

    private function determineStorageType(string $appType, array $input): string
    {
        // Flutter apps always use filesystem
        if ($appType === 'flutter') {
            return 'filesystem';
        }

        // Check code size for JavaScript apps
        $jsCode = $input['js_code'] ?? '';
        $dartCode = $input['dart_code'] ?? '';
        $totalSize = strlen($jsCode) + strlen($dartCode);
        
        $threshold = 50 * 1024; // 50KB
        
        return $totalSize > $threshold ? 'filesystem' : 'database';
    }

    private function getMimeTypeForPlatform(string $platform): string
    {
        $mimeTypes = [
            'web' => 'application/zip',
            'android' => 'application/vnd.android.package-archive',
            'ios' => 'application/octet-stream',
            'windows' => 'application/zip',
            'macos' => 'application/zip',
            'linux' => 'application/zip'
        ];

        return $mimeTypes[$platform] ?? 'application/octet-stream';
    }

    private function sendBuildNotifications(string $appId, string $status, string $buildId): void
    {
        // Implementation for sending notifications (email, webhooks, etc.)
        // This could be expanded based on requirements
        error_log("Build notification: App $appId, Status: $status, Build: $buildId");
    }

    /**
     * DELETE /api/apps/{id} (or POST /api/apps/{id}/delete)
     * Removes app from DB and deletes public directories.
     */
    public function deleteApp(object $auth, string $appId): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); return; }

            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'App not found or access denied']); return; }

            // Remove public artifacts and repo
            $publicRoot = dirname(__DIR__, 2) . '/public';
            $flutterArtifacts = $publicRoot . '/apps/flutter/' . $appId;
            $repoPath = !empty($app['repository_path']) ? ($publicRoot . $app['repository_path']) : null;
            $legacySlugPath = !empty($app['slug']) ? ($publicRoot . '/apps/' . $app['slug']) : null;
            $this->safeRmDir($flutterArtifacts);
            if ($repoPath) { $this->safeRmDir($repoPath); }
            if ($legacySlugPath) { $this->safeRmDir($legacySlugPath); }

            // Dependent rows
            try { $this->generalModel->delete('workz_apps','build_queue', ['app_id' => (int)$appId]); } catch (\Throwable $e) {}
            try { $this->generalModel->delete('workz_apps','flutter_builds', ['app_id' => (int)$appId]); } catch (\Throwable $e) {}
            try { $this->generalModel->delete('workz_apps','gapp', ['ap' => (int)$appId]); } catch (\Throwable $e) {}

            // App row
            $ok = $this->generalModel->delete('workz_apps', 'apps', ['id' => (int)$appId]);
            if (!$ok) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to delete app row']); return; }

            echo json_encode(['success'=>true,'message'=>'App deleted']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'Internal error: '.$e->getMessage()]);
        }
    }

    private function safeRmDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            try { $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath()); } catch (\Throwable $e) {}
        }
        @rmdir($dir);
    }
}






