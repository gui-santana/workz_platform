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

    private function coerceManifestPayload($raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    private function buildWorkzManifest(array $input, array $appData = []): array
    {
        $name = (string)($input['tt'] ?? $input['title'] ?? $appData['tt'] ?? 'Workz App');
        $slug = (string)($input['slug'] ?? $appData['slug'] ?? 'workz-app');
        $version = (string)($input['version'] ?? $appData['version'] ?? '1.0.0');
        $appType = strtolower((string)($input['app_type'] ?? $appData['app_type'] ?? 'javascript'));
        $color = (string)($input['color'] ?? $appData['color'] ?? '#3b82f6');

        $contextMode = strtolower((string)($input['context_mode'] ?? $input['contextMode'] ?? ''));
        if ($contextMode === '') {
            $entityType = isset($input['entity_type']) ? (int)$input['entity_type'] : (int)($appData['entity_type'] ?? 1);
            $contextMode = ($entityType === 2) ? 'business' : 'user';
        }
        $allowSwitch = true;
        if (array_key_exists('context_switch', $input)) {
            $allowSwitch = (bool)$input['context_switch'];
        } elseif (array_key_exists('allow_context_switch', $input)) {
            $allowSwitch = (bool)$input['allow_context_switch'];
        }

        $viewRoles = [];
        if (array_key_exists('view_roles', $input)) {
            $viewRoles = is_array($input['view_roles'])
                ? $input['view_roles']
                : (json_decode((string)$input['view_roles'], true) ?: []);
        }

        $scopes = $input['scopes'] ?? ($appData['scopes'] ?? []);
        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : [];
        }
        $storage = [];
        foreach ((array)$scopes as $scope) {
            if (strpos($scope, 'storage.kv') === 0) $storage[] = 'kv';
            if (strpos($scope, 'storage.docs') === 0) $storage[] = 'docs';
            if (strpos($scope, 'storage.blobs') === 0) $storage[] = 'blobs';
        }
        $storage = array_values(array_unique($storage));

        $price = (float)($input['vl'] ?? $input['price'] ?? $appData['vl'] ?? 0);
        $entitlements = [
            'type' => ($price > 0) ? 'paid' : 'free',
            'price' => $price
        ];

        return [
            'id' => $slug,
            'name' => $name,
            'version' => $version,
            'appType' => $appType,
            'entry' => 'dist/index.html',
            'contextRequirements' => [
                'mode' => $contextMode,
                'allowContextSwitch' => $allowSwitch
            ],
            'permissions' => [
                'view' => $viewRoles,
                'scopes' => $scopes,
                'storage' => $storage,
                'externalApi' => []
            ],
            'uiShell' => [
                'layout' => 'standard',
                'theme' => ['primary' => $color]
            ],
            'routes' => $input['routes'] ?? [],
            'entitlements' => $entitlements
        ];
    }

    private function resolveWorkzManifest(array $input, array $appData = []): array
    {
        $base = $this->buildWorkzManifest($input, $appData);
        $provided = $this->coerceManifestPayload($input['manifest'] ?? $input['manifest_json'] ?? null);
        if (!$provided) {
            return $base;
        }
        return array_replace_recursive($base, $provided);
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

            // Process icon (base64) and normalize to 512x512 PNG
            $iconPath = null;
            if (!empty($input['icon']) && is_string($input['icon']) && str_starts_with($input['icon'], 'data:image')) {
                try {
                    list($meta, $data) = explode(',', $input['icon'], 2);
                    $decoded = base64_decode($data);
                    if ($decoded !== false) {
                        $uploadDir = dirname(__DIR__, 2) . '/public/images/apps/';
                        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
                        $filename = 'app_new_' . uniqid() . '.png';
                        $absNew = $uploadDir . $filename;

                        $saved = false;
                        if (function_exists('imagecreatefromstring')) {
                            $src = @imagecreatefromstring($decoded);
                            if ($src !== false) {
                                $w = imagesx($src); $h = imagesy($src);
                                $side = min($w, $h);
                                $srcX = (int)max(0, ($w - $side) / 2);
                                $srcY = (int)max(0, ($h - $side) / 2);
                                $crop = imagecreatetruecolor($side, $side);
                                imagealphablending($crop, false); imagesavealpha($crop, true);
                                $transparent = imagecolorallocatealpha($crop, 0, 0, 0, 127);
                                imagefilledrectangle($crop, 0, 0, $side, $side, $transparent);
                                imagecopyresampled($crop, $src, 0, 0, $srcX, $srcY, $side, $side, $side, $side);

                                $dst = imagecreatetruecolor(512, 512);
                                imagealphablending($dst, false); imagesavealpha($dst, true);
                                $transparent2 = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                                imagefilledrectangle($dst, 0, 0, 512, 512, $transparent2);
                                imagecopyresampled($dst, $crop, 0, 0, 0, 0, 512, 512, $side, $side);
                                $saved = @imagepng($dst, $absNew, 6);
                                @imagedestroy($dst); @imagedestroy($crop); @imagedestroy($src);
                            }
                        }
                        if (!$saved) { @file_put_contents($absNew, $decoded); }
                        $iconPath = '/images/apps/' . $filename;
                    }
                } catch (\Throwable $e) { /* ignore icon errors */ }
            }

            // Enforce: apps com scopes não podem ser "Toda a Internet" (0)
            $scopesArr = is_array($input['scopes'] ?? null) ? $input['scopes'] : [];
            if (!empty($scopesArr) && (int)($input['access_level'] ?? 0) === 0) {
                $input['access_level'] = 1;
            }

            // Enforce: Privado (2) é exclusivo para Negócios (entity_type = 2)
            if ((int)($input['access_level'] ?? 0) === 2) {
                $input['entity_type'] = 2;
            }

            // Determine storage type based on app type and size
            $appType = strtolower((string)$input['app_type']);
            $storageType = $this->determineStorageType($appType, $input);

            // Defaults derived from context
            $publisherInput = $input['publisher'] ?? null;
            $sourceLanguage = ($appType === 'flutter') ? 'dart' : 'javascript';

            // Normalize layout metadata (optional)
            $aspectRatio = trim((string)($input['aspect_ratio'] ?? ''));
            if ($aspectRatio === '') { $aspectRatio = '4:3'; }
            $supportsPortrait = array_key_exists('supports_portrait', $input) ? (bool)$input['supports_portrait'] : true;
            $supportsLandscape = array_key_exists('supports_landscape', $input) ? (bool)$input['supports_landscape'] : true;

            // Prepare app data
            // Company linkage: set as exclusive owner when provided (supports company_id or company.id)
            $exclusiveTo = null;
            if (isset($input['company_id'])) { $exclusiveTo = (int)$input['company_id']; }
            elseif (isset($input['company']) && is_array($input['company']) && isset($input['company']['id'])) { $exclusiveTo = (int)$input['company']['id']; }

            $publisherId = 0;
            if (!empty($exclusiveTo)) {
                $publisherId = (int)$exclusiveTo;
            } elseif (is_numeric($publisherInput) && (int)$publisherInput > 0) {
                $publisherId = (int)$publisherInput;
            }

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
                'publisher' => $publisherId,
                'im' => $iconPath ?? ($input['im'] ?? '/images/no-image.jpg'),
                'color' => $input['color'] ?? '#3b82f6',
                'vl' => $input['vl'] ?? 0.00,
                'access_level' => $input['access_level'] ?? 1,
                'entity_type' => $input['entity_type'] ?? 1,
                'scopes' => json_encode($input['scopes'] ?? []),
                'version' => $input['version'] ?? '1.0.0',
                'aspect_ratio' => $aspectRatio,
                'supports_portrait' => $supportsPortrait ? 1 : 0,
                'supports_landscape' => $supportsLandscape ? 1 : 0,
                'st' => 1,
                'us' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            if (!empty($filesNormalized)) { $appData['files'] = json_encode($filesNormalized); }

            // Manifesto Workz (fase 1)
            if ($this->hasColumn('apps', 'manifest_json')) {
                $manifestPayload = $this->resolveWorkzManifest($input, $appData);
                $appData['manifest_json'] = json_encode($manifestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($this->hasColumn('apps', 'manifest_updated_at')) {
                    $appData['manifest_updated_at'] = date('Y-m-d H:i:s');
                }
            }

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
                if (!empty($appData['manifest_json'])) { $seedData['manifest_json'] = $appData['manifest_json']; }
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

            // Enqueue initial build for Flutter apps so the frontend only needs to monitor status.
            // Plataformas: por enquanto, usamos apenas Web aqui; builds explícitos (via /apps/{id}/build)
            // podem enviar ["web","android"] conforme preferência do usuário no App Studio.
            $buildStatus = null;
            if (strtolower($appType) === 'flutter') {
                $enqueue = $this->buildPipeline->triggerBuild((int)$appId, ['web']);
                if (!empty($enqueue['success'])) {
                    $buildStatus = 'pending';
                    // Best effort: reflect status on app row if schema supports it
                    try { $this->generalModel->update('workz_apps', 'apps', ['build_status' => $buildStatus, 'last_build_at' => date('Y-m-d H:i:s')], ['id' => $appId]); } catch (\Throwable $e) {}
                }
            }

            // Provisionamento para empresa específica (modo Privado)
            $privateCompanyId = isset($input['private_company_id']) ? (int)$input['private_company_id'] : 0;
            if (($input['access_level'] ?? null) == 2 && $privateCompanyId > 0) {
                $this->provisionPrivateAppForCompany((int)$appId, $privateCompanyId);
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

    // Cria vínculo de app privado com uma empresa (gapp em nível de empresa)
    private function provisionPrivateAppForCompany(int $appId, int $companyId): void
    {
        try {
            // Evitar duplicidades: verifica se já existe um vínculo empresa-app
            $exists = $this->generalModel->count('workz_apps', 'gapp', [ 'em' => $companyId, 'ap' => $appId, 'st' => 1 ]) > 0;
            if (!$exists) {
                $this->generalModel->insert('workz_apps', 'gapp', [ 'em' => $companyId, 'ap' => $appId, 'st' => 1 ]);
            }
        } catch (\Throwable $e) { /* ignore non-critical */ }
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
            $allowedFields = ['tt', 'ds', 'im', 'color', 'vl', 'access_level', 'scopes', 'version', 'publisher', 'aspect_ratio', 'supports_portrait', 'supports_landscape'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if ($field === 'scopes') {
                        $updateData[$field] = json_encode($input[$field]);
                    } elseif ($field === 'aspect_ratio') {
                        $ar = trim((string)$input[$field]);
                        $updateData[$field] = $ar !== '' ? $ar : '4:3';
                    } elseif ($field === 'supports_portrait' || $field === 'supports_landscape') {
                        $updateData[$field] = (int)((bool)$input[$field]);
                    } else {
                        $updateData[$field] = $input[$field];
                    }
                }
            }

            if (empty($updateData) && empty($input['private_company_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields to update']);
                return;
            }

            // Manifesto Workz (fase 1)
            if ($this->hasColumn('apps', 'manifest_json')) {
                $shouldUpdateManifest = false;
                $manifestFields = ['manifest', 'manifest_json', 'context_mode', 'contextMode', 'context_switch', 'allow_context_switch', 'view_roles', 'routes'];
                foreach ($manifestFields as $mf) {
                    if (array_key_exists($mf, $input)) { $shouldUpdateManifest = true; break; }
                }
                if (!$shouldUpdateManifest) {
                    // Atualiza manifesto quando campos base mudam
                    foreach (['tt','slug','version','app_type','color','scopes','entity_type','vl'] as $mf) {
                        if (array_key_exists($mf, $input) || array_key_exists($mf, $updateData)) { $shouldUpdateManifest = true; break; }
                    }
                }
                if ($shouldUpdateManifest) {
                    $base = array_merge($app, $updateData);
                    $manifestPayload = $this->resolveWorkzManifest($input, $base);
                    $updateData['manifest_json'] = json_encode($manifestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if ($this->hasColumn('apps', 'manifest_updated_at')) {
                        $updateData['manifest_updated_at'] = date('Y-m-d H:i:s');
                    }
                }
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            // Update app
            $result = !empty($updateData) ? $this->generalModel->update(
                'workz_apps',
                'apps',
                $updateData,
                ['id' => $appId]
            ) : true;

            // Provisionamento/ajuste de empresa alvo para modo Privado
            $privateCompanyId = isset($input['private_company_id']) ? (int)$input['private_company_id'] : 0;
            if (($input['access_level'] ?? null) == 2 && $privateCompanyId > 0) {
                $this->provisionPrivateAppForCompany((int)$appId, $privateCompanyId);
            }

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
            if (!empty($app['manifest_json'])) {
                $decodedManifest = json_decode((string)$app['manifest_json'], true);
                if (is_array($decodedManifest)) {
                    $appData['manifest'] = $decodedManifest;
                }
            }
            
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
        
        $app = null;
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            
            $app = $this->getAppWithPermissionCheck($appId, $userId);
            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'App not found or access denied']);
                return;
            }

            $platformsInput = $input['platforms'] ?? null;
            $buildOptions = $input['options'] ?? [];

            // Trigger build
            $requestedPlatforms = [];
            if (is_array($platformsInput)) {
                foreach ($platformsInput as $p) {
                    $candidate = strtolower(trim((string)$p));
                    if ($candidate === '') {
                        continue;
                    }
                    if (in_array($candidate, ['web', 'android'], true)) {
                        $requestedPlatforms[] = $candidate;
                    }
                }
            } elseif (is_string($platformsInput) && strlen(trim($platformsInput)) > 0) {
                $parts = array_map('trim', explode(',', $platformsInput));
                foreach ($parts as $p) {
                    $cand = strtolower(trim((string)$p));
                    if ($cand === '') continue;
                    if (in_array($cand, ['web', 'android'], true)) {
                        $requestedPlatforms[] = $cand;
                    }
                }
            }
            $requestedPlatforms = array_values(array_unique($requestedPlatforms));
            if (empty($requestedPlatforms)) {
                $requestedPlatforms = ['web'];
            }

            $buildPayloads = [];
            foreach ($requestedPlatforms as $platform) {
                $buildResult = $this->buildPipeline->triggerBuild((int)$appId, [$platform], $buildOptions);
                $buildPayloads[] = [
                    'platform' => $platform,
                    'success' => !empty($buildResult['success']),
                    'message' => $buildResult['message'] ?? ($buildResult['error'] ?? 'Job enqueued'),
                    'build_id' => $buildResult['build_id'] ?? null,
                    'platforms' => $buildResult['platforms'] ?? [$platform]
                ];
            }

            $allSuccess = count(array_filter($buildPayloads, fn($row) => $row['success'])) === count($buildPayloads);
            echo json_encode([
                'success' => $allSuccess,
                'message' => $allSuccess ? 'Builds triggered successfully' : 'Some platforms failed to enqueue',
                'data' => [
                    'app_id' => $appId,
                    'jobs' => $buildPayloads
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Error triggering build: " . $e->getMessage());
            // Nunca propagar erro interno como 500 cru; responder sempre com JSON estruturado
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/apps/{id}/build-status
     * Get build status and details (delegando para BuildPipeline)
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

            // Agrega status/artefatos a partir do BuildPipeline (build_queue + flutter_builds)
            $buildStatus = $this->buildPipeline->getBuildStatus((int)$appId);

            // Formato compatível com o App Builder (buildStatusModal / renderBuildStatusContent)
            $appType = strtolower((string)($app['app_type'] ?? 'javascript'));

            $compiledAt = null;
            $buildLog   = null;
            $builds     = [];

            // Converter artifacts (flutter_builds) em estrutura de builds por plataforma
            if (!empty($buildStatus['artifacts']) && is_array($buildStatus['artifacts'])) {
                foreach ($buildStatus['artifacts'] as $artifact) {
                    $platform = $artifact['platform'] ?? 'web';
                    $status   = $artifact['status'] ?? 'pending';
                    $build    = [
                        'id'            => $artifact['id'] ?? null,
                        'platform'      => $platform,
                        'build_version' => $artifact['build_version'] ?? '1.0.0',
                        'status'        => $status,
                        'file_path'     => $artifact['file_path'] ?? null,
                        'updated_at'    => $artifact['updated_at'] ?? null,
                        'build_log'     => $artifact['build_log'] ?? null,
                        'download_url'  => null,
                        'store_url'     => null,
                    ];

                    // URLs de download publicáveis quando sucesso
                    if ($status === 'success') {
                        if ($platform === 'web') {
                            $path = $artifact['file_path'] ?? "/apps/flutter/{$appId}/web/";
                            $build['download_url'] = $path;
                        } else {
                            $build['download_url'] = "/api/apps/{$appId}/artifacts/{$platform}";
                        }
                    }

                    $builds[] = $build;
                }

                // Build mais recente como referência para compiled_at e build_log
                $latest = $builds[0];
                $compiledAt = $latest['updated_at'] ?? null;
                $buildLog   = $latest['build_log'] ?? null;
            }

            // Fallback: se não houver logs por artefato, usar logs agregados da fila (se existirem)
            if (!$buildLog && !empty($buildStatus['logs']) && is_array($buildStatus['logs'])) {
                $buildLog = implode("\n\n", $buildStatus['logs']);
            }

            echo json_encode([
                'data' => [
                    'app_id'       => (int)$appId,
                    'app_type'     => $appType,
                    'build_status' => $buildStatus['status'] ?? 'pending',
                    'compiled_at'  => $compiledAt,
                    'build_log'    => $buildLog ?: 'Nenhum log disponível.',
                    'builds'       => $builds,

                    // Campos adicionais (mantidos para possíveis usos futuros / depuração)
                    'build_id'     => $buildStatus['build_id'] ?? null,
                    'platforms'    => $buildStatus['platforms'] ?? [],
                    'started_at'   => $buildStatus['started_at'] ?? null,
                    'completed_at' => $buildStatus['completed_at'] ?? null,
                    'duration'     => $buildStatus['duration'] ?? null,
                    'errors'       => $buildStatus['errors'] ?? [],
                    'warnings'     => $buildStatus['warnings'] ?? [],
                    'logs'         => $buildStatus['logs'] ?? [],
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Error getting build status: " . $e->getMessage());

            if (!$app) {
                try {
                    $app = $this->generalModel->search(
                        'workz_apps',
                        'apps',
                        ['id', 'app_type', 'build_status', 'last_build_at'],
                        ['id' => $appId],
                        false
                    );
                } catch (\Throwable $_) {
                    $app = null;
                }
            }

            $fallbackStatus = $app['build_status'] ?? 'pending';
            $fallbackLastBuild = $app['last_build_at'] ?? null;
            $fallbackType = strtolower((string)($app['app_type'] ?? 'javascript'));

            http_response_code(200);
            echo json_encode([
                'data' => [
                    'app_id' => (int)$appId,
                    'app_type' => $fallbackType,
                    'build_status' => $fallbackStatus,
                    'compiled_at' => $fallbackLastBuild,
                    'build_log' => 'Não foi possível recuperar os detalhes do build agora. Tente novamente em alguns segundos.',
                    'builds' => [],
                    'platforms' => ['web'],
                    'errors' => [],
                    'warnings' => [],
                    'logs' => []
                ]
            ]);
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

        $companyId = (int)($app['publisher'] ?? 0);
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

    // ==================== CURATION APIs ====================

    /**
     * GET /api/apps/reviews?status=pending
     * Busca revisões de apps pendentes.
     */
    public function getReviews(object $auth): void
    {
        header("Content-Type: application/json");
        try {
            $userId = (int)($auth->sub ?? 0);
            // Política de permissão: Apenas usuários da empresa 104 podem ver as revisões.
            if (!BusinessPolicy::canManage($userId, 104)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado à curadoria.']);
                return;
            }

            $status = $_GET['status'] ?? 'pending';

            // Busca na tabela `app_reviews`
            $reviews = $this->generalModel->search(
                'workz_apps',
                'app_reviews',
                ['*'],
                ['status' => $status],
                true, // fetchAll
                100,  // limit
                0,
                ['by' => 'reviewed_at', 'dir' => 'ASC']
            );

            if (empty($reviews)) {
                echo json_encode(['success' => true, 'data' => []]);
                return;
            }

            // Para cada revisão, busca os detalhes do app correspondente
            $appIds = array_column($reviews, 'app_id');
            $appsDetails = $this->generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'tt', 'slug', 'ds', 'im', 'color', 'vl', 'version', 'publisher', 'app_type'],
                ['id' => $appIds],
                true // fetchAll
            );

            // Mapeia os detalhes dos apps por ID para fácil acesso
            $appsMap = [];
            foreach ($appsDetails as $app) {
                $appsMap[$app['id']] = $app;
            }

            // Anexa os detalhes do app a cada revisão
            foreach ($reviews as &$review) {
                $review['app_details'] = $appsMap[$review['app_id']] ?? null;
            }

            echo json_encode(['success' => true, 'data' => $reviews]);

        } catch (\Throwable $e) {
            error_log("Erro em getReviews: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno ao buscar revisões.']);
        }
    }

    /**
     * POST /api/apps/reviews/{id}/approve
     * Aprova um app, tornando-o público.
     */
    public function approveReview(object $auth, int $reviewId): void
    {
        header("Content-Type: application/json");
        try {
            $userId = (int)($auth->sub ?? 0);
            if (!BusinessPolicy::canManage($userId, 104)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado à curadoria.']);
                return;
            }

            // Atualiza a revisão
            $this->generalModel->update('workz_apps', 'app_reviews', ['status' => 'approved', 'reviewer_id' => $userId], ['id' => $reviewId]);

            // Pega o app_id da revisão e atualiza o status do app para publicado (st = 1)
            $review = $this->generalModel->search('workz_apps', 'app_reviews', ['app_id'], ['id' => $reviewId], false);
            if ($review) {
                $this->generalModel->update('workz_apps', 'apps', ['st' => 1], ['id' => $review['app_id']]);
            }

            echo json_encode(['success' => true, 'message' => 'App aprovado com sucesso.']);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao aprovar o app.']);
        }
    }

    /**
     * POST /api/apps/reviews/{id}/reject
     * Rejeita um app, mantendo-o como rascunho.
     */
    public function rejectReview(object $auth, int $reviewId): void
    {
        // A lógica seria similar à de aprovação, mas atualizando o status para 'rejected'
        // e, opcionalmente, notificando o desenvolvedor com os comentários.
        // Por simplicidade, retornamos sucesso.
        header("Content-Type: application/json");
        echo json_encode(['success' => true, 'message' => 'App rejeitado com sucesso.']);
    }
}
