<?php
// src/Core/StorageManager.php

namespace Workz\Platform\Core;

use Workz\Platform\Models\General;

/**
 * Storage Manager for handling hybrid storage system
 * Provides transparent access to both database and filesystem storage
 * 
 * Requirements: 4.1, 4.4, 1.4
 */
class StorageManager
{
    private General $generalModel;
    private const STORAGE_THRESHOLD = 50 * 1024; // 50KB

    public function __construct()
    {
        $this->generalModel = new General();
    }

    /**
     * Get app code from appropriate storage location
     * 
     * @param int $appId App ID
     * @return array App code data
     * @throws \RuntimeException if app not found or storage error
     */
    public function getAppCode(int $appId): array
    {
        // Get app metadata to determine storage type
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            throw new \RuntimeException("App not found: {$appId}");
        }

        $storageType = $app['storage_type'] ?? 'database';

        if ($storageType === 'filesystem') {
            return $this->getCodeFromFilesystem($app);
        } else {
            return $this->getCodeFromDatabase($app);
        }
    }

    /**
     * Save app code to appropriate storage location
     * 
     * @param int $appId App ID
     * @param array $codeData Code data to save
     * @return bool Success status
     */
    public function saveAppCode(int $appId, array $codeData): bool
    {
        // Get current app data
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            throw new \RuntimeException("App not found: {$appId}");
        }

        // Calculate code size
        $jsCode = $codeData['js_code'] ?? '';
        $dartCode = $codeData['dart_code'] ?? '';
        $codeSize = StorageInfrastructure::calculateCodeSize($jsCode, $dartCode);

        // Determine appropriate storage type
        $appType = $app['app_type'] ?? 'javascript';
        $features = json_decode($app['scopes'] ?? '[]', true);
        $newStorageType = StorageInfrastructure::determineStorageType($appType, $codeSize, $features);

        // Update code size in database
        $updateData = [
            'code_size_bytes' => $codeSize,
            'js_code' => $jsCode,
            'dart_code' => $dartCode,
            'source_code' => $appType === 'flutter' ? $dartCode : $jsCode
        ];

        // If storage type needs to change, handle migration
        $currentStorageType = $app['storage_type'] ?? 'database';
        if ($newStorageType !== $currentStorageType) {
            return $this->migrateAndSave($appId, $app, $codeData, $newStorageType);
        }

        // Save to current storage type
        if ($currentStorageType === 'filesystem') {
            return $this->saveCodeToFilesystem($app, $codeData) && 
                   $this->generalModel->update('workz_apps', 'apps', $updateData, ['id' => $appId]);
        } else {
            return $this->generalModel->update('workz_apps', 'apps', $updateData, ['id' => $appId]);
        }
    }

    /**
     * Check if app should be migrated based on current criteria
     * 
     * @param int $appId App ID
     * @return array Migration recommendation
     */
    public function checkMigrationNeeded(int $appId): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['needed' => false, 'reason' => 'App not found'];
        }

        $currentStorageType = $app['storage_type'] ?? 'database';
        $codeSize = (int)($app['code_size_bytes'] ?? 0);
        $appType = $app['app_type'] ?? 'javascript';
        $features = json_decode($app['scopes'] ?? '[]', true);

        $recommendedType = StorageInfrastructure::determineStorageType($appType, $codeSize, $features);

        if ($currentStorageType === $recommendedType) {
            return ['needed' => false, 'reason' => 'Already using optimal storage'];
        }

        $reason = '';
        if ($recommendedType === 'filesystem') {
            if ($appType === 'flutter') {
                $reason = 'Flutter apps require filesystem storage';
            } elseif ($codeSize > self::STORAGE_THRESHOLD) {
                $reason = 'Code size exceeds threshold (' . number_format($codeSize) . ' bytes)';
            } else {
                $reason = 'Advanced features require filesystem storage';
            }
        } else {
            $reason = 'App is small enough for database storage';
        }

        return [
            'needed' => true,
            'from' => $currentStorageType,
            'to' => $recommendedType,
            'reason' => $reason,
            'codeSize' => $codeSize
        ];
    }

    /**
     * Get storage statistics for monitoring and optimization
     * 
     * @return array Storage usage statistics
     */
    public function getStorageStatistics(): array
    {
        $stats = [
            'database' => ['count' => 0, 'total_size' => 0],
            'filesystem' => ['count' => 0, 'total_size' => 0],
            'migration_candidates' => []
        ];

        // Get all apps with storage information
        $apps = $this->generalModel->search('workz_apps', 'apps', 
            ['id', 'slug', 'storage_type', 'code_size_bytes', 'app_type', 'scopes'], 
            [], true);

        if (!$apps) {
            return $stats;
        }

        foreach ($apps as $app) {
            $storageType = $app['storage_type'] ?? 'database';
            $codeSize = (int)($app['code_size_bytes'] ?? 0);

            $stats[$storageType]['count']++;
            $stats[$storageType]['total_size'] += $codeSize;

            // Check if migration is recommended
            $migrationCheck = $this->checkMigrationNeeded($app['id']);
            if ($migrationCheck['needed']) {
                $stats['migration_candidates'][] = [
                    'id' => $app['id'],
                    'slug' => $app['slug'],
                    'from' => $migrationCheck['from'],
                    'to' => $migrationCheck['to'],
                    'reason' => $migrationCheck['reason'],
                    'size' => $codeSize
                ];
            }
        }

        return $stats;
    }

    /**
     * Validate storage integrity for an app
     * 
     * @param int $appId App ID
     * @return array Validation results
     */
    public function validateStorageIntegrity(int $appId): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['valid' => false, 'errors' => ['App not found']];
        }

        $errors = [];
        $storageType = $app['storage_type'] ?? 'database';

        if ($storageType === 'filesystem') {
            $repositoryPath = $app['repository_path'];
            
            // Check if repository path exists
            if (!$repositoryPath || !is_dir($repositoryPath)) {
                $errors[] = 'Repository path does not exist: ' . $repositoryPath;
            } else {
                // Check required directories
                $requiredDirs = ['src', 'build'];
                foreach ($requiredDirs as $dir) {
                    if (!is_dir($repositoryPath . '/' . $dir)) {
                        $errors[] = "Missing required directory: {$dir}";
                    }
                }

                // Check if Git repository is initialized
                if (!is_dir($repositoryPath . '/.git')) {
                    $errors[] = 'Git repository not initialized';
                }

                // Check workz.json configuration
                if (!file_exists($repositoryPath . '/workz.json')) {
                    $errors[] = 'Missing workz.json configuration file';
                }
            }
        } else {
            // Database storage validation
            if (empty($app['js_code']) && empty($app['dart_code'])) {
                $errors[] = 'No code found in database storage';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'storage_type' => $storageType
        ];
    }

    /**
     * Get code from database storage
     */
    private function getCodeFromDatabase(array $app): array
    {
        return [
            'js_code' => $app['js_code'] ?? '',
            'dart_code' => $app['dart_code'] ?? '',
            'source_code' => $app['source_code'] ?? '',
            'storage_type' => 'database'
        ];
    }

    /**
     * Get code from filesystem storage
     */
    private function getCodeFromFilesystem(array $app): array
    {
        $repositoryPath = $app['repository_path'];
        
        if (!$repositoryPath || !is_dir($repositoryPath)) {
            throw new \RuntimeException("Repository path not found: {$repositoryPath}");
        }

        $jsCode = '';
        $dartCode = '';

        // Try to read JavaScript main file
        $jsFile = $repositoryPath . '/src/main.js';
        if (file_exists($jsFile)) {
            $jsCode = file_get_contents($jsFile);
        }

        // Try to read Dart main file
        $dartFile = $repositoryPath . '/src/main.dart';
        if (file_exists($dartFile)) {
            $dartCode = file_get_contents($dartFile);
        }

        $appType = $app['app_type'] ?? 'javascript';
        $sourceCode = $appType === 'flutter' ? $dartCode : $jsCode;

        return [
            'js_code' => $jsCode,
            'dart_code' => $dartCode,
            'source_code' => $sourceCode,
            'storage_type' => 'filesystem',
            'repository_path' => $repositoryPath
        ];
    }

    /**
     * Save code to filesystem storage
     */
    private function saveCodeToFilesystem(array $app, array $codeData): bool
    {
        $repositoryPath = $app['repository_path'];
        
        if (!$repositoryPath || !is_dir($repositoryPath)) {
            return false;
        }

        $success = true;

        // Save JavaScript code if provided
        if (isset($codeData['js_code'])) {
            $jsFile = $repositoryPath . '/src/main.js';
            $success = $success && file_put_contents($jsFile, $codeData['js_code']) !== false;
        }

        // Save Dart code if provided
        if (isset($codeData['dart_code'])) {
            $dartFile = $repositoryPath . '/src/main.dart';
            $success = $success && file_put_contents($dartFile, $codeData['dart_code']) !== false;
        }

        return $success;
    }

    /**
     * Get app metadata with storage information
     * 
     * @param int $appId App ID
     * @return array|null App metadata or null if not found
     */
    public function getAppMetadata(int $appId): ?array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return null;
        }

        // Add computed fields for API transparency
        $app['storage_info'] = [
            'type' => $app['storage_type'] ?? 'database',
            'size_bytes' => (int)($app['code_size_bytes'] ?? 0),
            'size_formatted' => $this->formatBytes((int)($app['code_size_bytes'] ?? 0)),
            'last_migration' => $app['last_migration_at'],
            'repository_path' => $app['repository_path']
        ];

        return $app;
    }

    /**
     * List apps by storage type for management purposes
     * 
     * @param string|null $storageType Filter by storage type ('database', 'filesystem', or null for all)
     * @param int|null $limit Limit number of results
     * @param int|null $offset Offset for pagination
     * @return array List of apps with storage information
     */
    public function listAppsByStorageType(?string $storageType = null, ?int $limit = null, ?int $offset = null): array
    {
        $conditions = [];
        if ($storageType) {
            $conditions['storage_type'] = $storageType;
        }

        $apps = $this->generalModel->search('workz_apps', 'apps', 
            ['id', 'slug', 'tt', 'storage_type', 'code_size_bytes', 'app_type', 'last_migration_at', 'repository_path'], 
            $conditions, true, $limit, $offset, ['by' => 'id', 'dir' => 'DESC']);

        if (!$apps) {
            return [];
        }

        // Enhance with storage information
        foreach ($apps as &$app) {
            $app['storage_info'] = [
                'type' => $app['storage_type'] ?? 'database',
                'size_bytes' => (int)($app['code_size_bytes'] ?? 0),
                'size_formatted' => $this->formatBytes((int)($app['code_size_bytes'] ?? 0)),
                'last_migration' => $app['last_migration_at']
            ];
        }

        return $apps;
    }

    /**
     * Format bytes into human readable format
     * 
     * @param int $bytes Number of bytes
     * @return string Formatted size string
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf('%.1f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Migrate app storage and save new code
     */
    private function migrateAndSave(int $appId, array $app, array $codeData, string $newStorageType): bool
    {
        try {
            if ($newStorageType === 'filesystem') {
                return $this->migrateToFilesystem($appId, $app, $codeData);
            } else {
                return $this->migrateToDatabase($appId, $app, $codeData);
            }
        } catch (\Throwable $e) {
            error_log("Migration failed for app {$appId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Migrate app to filesystem storage
     */
    private function migrateToFilesystem(int $appId, array $app, array $codeData): bool
    {
        $slug = $app['slug'];
        
        // Create directory structure
        $appPath = StorageInfrastructure::createAppDirectoryStructure($slug);
        
        // Initialize Git repository
        StorageInfrastructure::initializeGitRepository($appPath);
        
        // Create app configuration
        $appConfig = [
            'name' => $app['tt'],
            'slug' => $slug,
            'version' => $app['version'] ?? '1.0.0',
            'appType' => $app['app_type'] ?? 'javascript',
            'scopes' => json_decode($app['scopes'] ?? '[]', true)
        ];
        StorageInfrastructure::createAppConfig($appPath, $appConfig);
        
        // Save code to filesystem
        $this->saveCodeToFilesystem(['repository_path' => $appPath], $codeData);
        
        // Update database
        $updateData = [
            'storage_type' => 'filesystem',
            'repository_path' => $appPath,
            'last_migration_at' => date('Y-m-d H:i:s'),
            'code_size_bytes' => StorageInfrastructure::calculateCodeSize(
                $codeData['js_code'] ?? '', 
                $codeData['dart_code'] ?? ''
            )
        ];
        
        return $this->generalModel->update('workz_apps', 'apps', $updateData, ['id' => $appId]);
    }

    /**
     * Migrate app to database storage
     */
    private function migrateToDatabase(int $appId, array $app, array $codeData): bool
    {
        // Update database with code and storage type
        $updateData = [
            'storage_type' => 'database',
            'js_code' => $codeData['js_code'] ?? '',
            'dart_code' => $codeData['dart_code'] ?? '',
            'source_code' => ($app['app_type'] === 'flutter') ? 
                ($codeData['dart_code'] ?? '') : ($codeData['js_code'] ?? ''),
            'last_migration_at' => date('Y-m-d H:i:s'),
            'repository_path' => null,
            'code_size_bytes' => StorageInfrastructure::calculateCodeSize(
                $codeData['js_code'] ?? '', 
                $codeData['dart_code'] ?? ''
            )
        ];
        
        $success = $this->generalModel->update('workz_apps', 'apps', $updateData, ['id' => $appId]);
        
        // Clean up filesystem directory if migration successful
        if ($success && !empty($app['repository_path']) && is_dir($app['repository_path'])) {
            exec("rm -rf " . escapeshellarg($app['repository_path']));
        }
        
        return $success;
    }

    /**
     * Migrate app to filesystem storage with backup and rollback support
     * 
     * @param int $appId App ID
     * @return array Migration result with success status and details
     */
    public function migrateAppToFilesystem(int $appId): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['success' => false, 'error' => 'App not found'];
        }

        if (($app['storage_type'] ?? 'database') === 'filesystem') {
            return ['success' => false, 'error' => 'App already uses filesystem storage'];
        }

        $backupId = null;
        
        try {
            // Step 1: Create backup
            $backupId = $this->createBackup($appId, $app);
            
            // Step 2: Get current code
            $codeData = $this->getCodeFromDatabase($app);
            
            // Step 3: Perform migration
            $success = $this->migrateToFilesystem($appId, $app, $codeData);
            
            if (!$success) {
                throw new \RuntimeException('Migration to filesystem failed');
            }

            // Step 4: Validate migration
            $validation = $this->validateStorageIntegrity($appId);
            if (!$validation['valid']) {
                throw new \RuntimeException('Migration validation failed: ' . implode(', ', $validation['errors']));
            }

            // Step 5: Cleanup backup after successful migration
            $this->cleanupBackup($backupId);
            
            return [
                'success' => true,
                'message' => 'Successfully migrated to filesystem storage',
                'repository_path' => $app['repository_path'] ?? null
            ];
            
        } catch (\Throwable $e) {
            // Rollback on failure
            if ($backupId) {
                $rollbackResult = $this->rollbackMigration($appId, $backupId);
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'rollback' => $rollbackResult
                ];
            }
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Migrate app to database storage with backup and rollback support
     * 
     * @param int $appId App ID
     * @return array Migration result with success status and details
     */
    public function migrateAppToDatabase(int $appId): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['success' => false, 'error' => 'App not found'];
        }

        if (($app['storage_type'] ?? 'database') === 'database') {
            return ['success' => false, 'error' => 'App already uses database storage'];
        }

        $backupId = null;
        
        try {
            // Step 1: Create backup
            $backupId = $this->createBackup($appId, $app);
            
            // Step 2: Get current code from filesystem
            $codeData = $this->getCodeFromFilesystem($app);
            
            // Step 3: Perform migration
            $success = $this->migrateToDatabase($appId, $app, $codeData);
            
            if (!$success) {
                throw new \RuntimeException('Migration to database failed');
            }

            // Step 4: Validate migration
            $validation = $this->validateStorageIntegrity($appId);
            if (!$validation['valid']) {
                throw new \RuntimeException('Migration validation failed: ' . implode(', ', $validation['errors']));
            }

            // Step 5: Cleanup backup after successful migration
            $this->cleanupBackup($backupId);
            
            return [
                'success' => true,
                'message' => 'Successfully migrated to database storage'
            ];
            
        } catch (\Throwable $e) {
            // Rollback on failure
            if ($backupId) {
                $rollbackResult = $this->rollbackMigration($appId, $backupId);
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'rollback' => $rollbackResult
                ];
            }
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create backup of app data before migration
     * 
     * @param int $appId App ID
     * @param array $app App data
     * @return string Backup ID
     */
    private function createBackup(int $appId, array $app): string
    {
        $backupId = 'backup_' . $appId . '_' . time() . '_' . uniqid();
        $backupPath = '/tmp/workz_backups/' . $backupId;
        
        // Create backup directory
        if (!is_dir('/tmp/workz_backups')) {
            mkdir('/tmp/workz_backups', 0755, true);
        }
        
        mkdir($backupPath, 0755, true);
        
        // Save app metadata
        file_put_contents($backupPath . '/app_metadata.json', json_encode($app, JSON_PRETTY_PRINT));
        
        $storageType = $app['storage_type'] ?? 'database';
        
        if ($storageType === 'filesystem' && !empty($app['repository_path']) && is_dir($app['repository_path'])) {
            // Backup filesystem data
            exec("cp -r " . escapeshellarg($app['repository_path']) . " " . escapeshellarg($backupPath . '/repository'));
        } else {
            // Backup database data
            $codeData = [
                'js_code' => $app['js_code'] ?? '',
                'dart_code' => $app['dart_code'] ?? '',
                'source_code' => $app['source_code'] ?? ''
            ];
            file_put_contents($backupPath . '/code_data.json', json_encode($codeData, JSON_PRETTY_PRINT));
        }
        
        return $backupId;
    }

    /**
     * Rollback migration using backup
     * 
     * @param int $appId App ID
     * @param string $backupId Backup ID
     * @return array Rollback result
     */
    private function rollbackMigration(int $appId, string $backupId): array
    {
        $backupPath = '/tmp/workz_backups/' . $backupId;
        
        if (!is_dir($backupPath)) {
            return ['success' => false, 'error' => 'Backup not found'];
        }
        
        try {
            // Restore app metadata
            $metadataFile = $backupPath . '/app_metadata.json';
            if (!file_exists($metadataFile)) {
                throw new \RuntimeException('Backup metadata not found');
            }
            
            $originalApp = json_decode(file_get_contents($metadataFile), true);
            $originalStorageType = $originalApp['storage_type'] ?? 'database';
            
            if ($originalStorageType === 'filesystem') {
                // Restore filesystem data
                $repositoryBackup = $backupPath . '/repository';
                if (is_dir($repositoryBackup)) {
                    $targetPath = $originalApp['repository_path'];
                    if ($targetPath && is_dir($targetPath)) {
                        exec("rm -rf " . escapeshellarg($targetPath));
                    }
                    exec("cp -r " . escapeshellarg($repositoryBackup) . " " . escapeshellarg($targetPath));
                }
            } else {
                // Restore database data
                $codeFile = $backupPath . '/code_data.json';
                if (file_exists($codeFile)) {
                    $codeData = json_decode(file_get_contents($codeFile), true);
                    $originalApp = array_merge($originalApp, $codeData);
                }
            }
            
            // Restore database record
            unset($originalApp['id']); // Don't update the ID
            $success = $this->generalModel->update('workz_apps', 'apps', $originalApp, ['id' => $appId]);
            
            if (!$success) {
                throw new \RuntimeException('Failed to restore database record');
            }
            
            return ['success' => true, 'message' => 'Migration rolled back successfully'];
            
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Rollback failed: ' . $e->getMessage()];
        }
    }

    /**
     * Cleanup backup files after successful migration
     * 
     * @param string $backupId Backup ID
     * @return bool Success status
     */
    private function cleanupBackup(string $backupId): bool
    {
        $backupPath = '/tmp/workz_backups/' . $backupId;
        
        if (is_dir($backupPath)) {
            exec("rm -rf " . escapeshellarg($backupPath));
            return !is_dir($backupPath);
        }
        
        return true;
    }

    /**
     * List available backups for an app
     * 
     * @param int $appId App ID
     * @return array List of available backups
     */
    public function listBackups(int $appId): array
    {
        $backupsDir = '/tmp/workz_backups';
        $backups = [];
        
        if (!is_dir($backupsDir)) {
            return $backups;
        }
        
        $pattern = $backupsDir . '/backup_' . $appId . '_*';
        $backupDirs = glob($pattern);
        
        foreach ($backupDirs as $backupDir) {
            $backupId = basename($backupDir);
            $metadataFile = $backupDir . '/app_metadata.json';
            
            if (file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true);
                $backups[] = [
                    'backup_id' => $backupId,
                    'created_at' => date('Y-m-d H:i:s', filemtime($backupDir)),
                    'storage_type' => $metadata['storage_type'] ?? 'unknown',
                    'size' => $this->getDirectorySize($backupDir)
                ];
            }
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $backups;
    }

    /**
     * Get directory size in bytes
     * 
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }    /**

     * Initialize Git repository for filesystem app
     * 
     * @param int $appId App ID
     * @return array Result with success status and details
     */
    public function initializeGitRepository(int $appId): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['success' => false, 'error' => 'App not found'];
        }

        $storageType = $app['storage_type'] ?? 'database';
        if ($storageType !== 'filesystem') {
            return ['success' => false, 'error' => 'Git is only available for filesystem storage'];
        }

        $repositoryPath = $app['repository_path'];
        if (!$repositoryPath || !is_dir($repositoryPath)) {
            return ['success' => false, 'error' => 'Repository path not found'];
        }

        try {
            // Initialize Git if not already initialized
            if (!is_dir($repositoryPath . '/.git')) {
                $success = StorageInfrastructure::initializeGitRepository($repositoryPath);
                if (!$success) {
                    throw new \RuntimeException('Failed to initialize Git repository');
                }
            }

            // Set up initial commit if repository is empty
            $this->createInitialCommit($repositoryPath, $app);

            // Update database with Git information
            $this->generalModel->update('workz_apps', 'apps', [
                'git_branch' => 'main',
                'git_commit_hash' => $this->getLatestCommitHash($repositoryPath)
            ], ['id' => $appId]);

            return [
                'success' => true,
                'message' => 'Git repository initialized successfully',
                'branch' => 'main'
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Commit changes to Git repository
     * 
     * @param int $appId App ID
     * @param string $message Commit message
     * @param string|null $author Author name and email (optional)
     * @return array Result with success status and commit details
     */
    public function commitChanges(int $appId, string $message, ?string $author = null): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['success' => false, 'error' => 'App not found'];
        }

        $repositoryPath = $app['repository_path'];
        if (!$repositoryPath || !is_dir($repositoryPath . '/.git')) {
            return ['success' => false, 'error' => 'Git repository not found'];
        }

        try {
            $commands = [];
            
            // Set author if provided
            if ($author) {
                $commands[] = "cd " . escapeshellarg($repositoryPath) . " && git config user.name " . escapeshellarg($author);
                $commands[] = "cd " . escapeshellarg($repositoryPath) . " && git config user.email " . escapeshellarg($author);
            }

            // Add all changes
            $commands[] = "cd " . escapeshellarg($repositoryPath) . " && git add .";
            
            // Commit changes
            $commands[] = "cd " . escapeshellarg($repositoryPath) . " && git commit -m " . escapeshellarg($message);

            foreach ($commands as $command) {
                exec($command, $output, $returnCode);
                if ($returnCode !== 0) {
                    throw new \RuntimeException("Git command failed: {$command}");
                }
            }

            // Get commit hash and update database
            $commitHash = $this->getLatestCommitHash($repositoryPath);
            $this->generalModel->update('workz_apps', 'apps', [
                'git_commit_hash' => $commitHash
            ], ['id' => $appId]);

            return [
                'success' => true,
                'message' => 'Changes committed successfully',
                'commit_hash' => $commitHash,
                'commit_message' => $message
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create or switch to a Git branch
     * 
     * @param int $appId App ID
     * @param string $branchName Branch name
     * @param bool $create Whether to create the branch if it doesn't exist
     * @return array Result with success status and branch details
     */
    public function switchBranch(int $appId, string $branchName, bool $create = false): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['success' => false, 'error' => 'App not found'];
        }

        $repositoryPath = $app['repository_path'];
        if (!$repositoryPath || !is_dir($repositoryPath . '/.git')) {
            return ['success' => false, 'error' => 'Git repository not found'];
        }

        // Validate branch name
        if (!preg_match('/^[a-zA-Z0-9_\-\/]+$/', $branchName)) {
            return ['success' => false, 'error' => 'Invalid branch name'];
        }

        try {
            $command = "cd " . escapeshellarg($repositoryPath);
            
            if ($create) {
                $command .= " && git checkout -b " . escapeshellarg($branchName);
            } else {
                $command .= " && git checkout " . escapeshellarg($branchName);
            }

            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \RuntimeException("Failed to switch to branch: {$branchName}");
            }

            // Update database with new branch
            $this->generalModel->update('workz_apps', 'apps', [
                'git_branch' => $branchName,
                'git_commit_hash' => $this->getLatestCommitHash($repositoryPath)
            ], ['id' => $appId]);

            return [
                'success' => true,
                'message' => $create ? "Branch '{$branchName}' created and switched to" : "Switched to branch '{$branchName}'",
                'branch' => $branchName
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get Git status for an app
     * 
     * @param int $appId App ID
     * @return array Git status information
     */
    public function getGitStatus(int $appId): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['success' => false, 'error' => 'App not found'];
        }

        $repositoryPath = $app['repository_path'];
        if (!$repositoryPath || !is_dir($repositoryPath . '/.git')) {
            return ['success' => false, 'error' => 'Git repository not found'];
        }

        try {
            // Get current branch
            exec("cd " . escapeshellarg($repositoryPath) . " && git branch --show-current", $branchOutput);
            $currentBranch = trim($branchOutput[0] ?? 'main');

            // Get status
            exec("cd " . escapeshellarg($repositoryPath) . " && git status --porcelain", $statusOutput);
            
            // Get commit history (last 10 commits)
            exec("cd " . escapeshellarg($repositoryPath) . " && git log --oneline -10", $logOutput);

            // Parse status
            $changes = [
                'modified' => [],
                'added' => [],
                'deleted' => [],
                'untracked' => []
            ];

            foreach ($statusOutput as $line) {
                if (strlen($line) >= 3) {
                    $status = substr($line, 0, 2);
                    $file = trim(substr($line, 3));
                    
                    if ($status[0] === 'M' || $status[1] === 'M') {
                        $changes['modified'][] = $file;
                    } elseif ($status[0] === 'A' || $status[1] === 'A') {
                        $changes['added'][] = $file;
                    } elseif ($status[0] === 'D' || $status[1] === 'D') {
                        $changes['deleted'][] = $file;
                    } elseif ($status === '??') {
                        $changes['untracked'][] = $file;
                    }
                }
            }

            return [
                'success' => true,
                'branch' => $currentBranch,
                'commit_hash' => $this->getLatestCommitHash($repositoryPath),
                'changes' => $changes,
                'has_changes' => !empty($statusOutput),
                'recent_commits' => $logOutput
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get list of branches for an app
     * 
     * @param int $appId App ID
     * @return array List of branches
     */
    public function listBranches(int $appId): array
    {
        $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        
        if (!$app) {
            return ['success' => false, 'error' => 'App not found'];
        }

        $repositoryPath = $app['repository_path'];
        if (!$repositoryPath || !is_dir($repositoryPath . '/.git')) {
            return ['success' => false, 'error' => 'Git repository not found'];
        }

        try {
            exec("cd " . escapeshellarg($repositoryPath) . " && git branch", $output);
            
            $branches = [];
            $currentBranch = null;
            
            foreach ($output as $line) {
                $line = trim($line);
                if (strpos($line, '*') === 0) {
                    $currentBranch = trim(substr($line, 1));
                    $branches[] = ['name' => $currentBranch, 'current' => true];
                } else {
                    $branches[] = ['name' => $line, 'current' => false];
                }
            }

            return [
                'success' => true,
                'branches' => $branches,
                'current_branch' => $currentBranch
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create initial commit for a new repository
     * 
     * @param string $repositoryPath Repository path
     * @param array $app App data
     * @return bool Success status
     */
    private function createInitialCommit(string $repositoryPath, array $app): bool
    {
        try {
            // Check if there are any commits
            exec("cd " . escapeshellarg($repositoryPath) . " && git log --oneline 2>/dev/null", $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                // Repository already has commits
                return true;
            }

            // Set up Git user for initial commit
            $commands = [
                "cd " . escapeshellarg($repositoryPath) . " && git config user.name 'Workz Platform'",
                "cd " . escapeshellarg($repositoryPath) . " && git config user.email 'platform@workz.com'",
                "cd " . escapeshellarg($repositoryPath) . " && git add .",
                "cd " . escapeshellarg($repositoryPath) . " && git commit -m 'Initial commit: " . ($app['tt'] ?? 'Workz App') . "'"
            ];

            foreach ($commands as $command) {
                exec($command, $cmdOutput, $cmdReturn);
                if ($cmdReturn !== 0) {
                    error_log("Git command failed: {$command}");
                }
            }

            return true;

        } catch (\Throwable $e) {
            error_log("Failed to create initial commit: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get latest commit hash from repository
     * 
     * @param string $repositoryPath Repository path
     * @return string|null Commit hash or null if not found
     */
    private function getLatestCommitHash(string $repositoryPath): ?string
    {
        try {
            exec("cd " . escapeshellarg($repositoryPath) . " && git rev-parse HEAD 2>/dev/null", $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output[0])) {
                return trim($output[0]);
            }
            
            return null;

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Migrate app between storage types
     * 
     * @param int $appId App ID
     * @param string $targetType Target storage type ('database' or 'filesystem')
     * @return array Migration result
     */
    public function migrateApp(int $appId, string $targetType): array
    {
        if ($targetType === 'filesystem') {
            return $this->migrateAppToFilesystem($appId);
        } elseif ($targetType === 'database') {
            return $this->migrateAppToDatabase($appId);
        } else {
            return ['success' => false, 'error' => 'Invalid target type'];
        }
    }

    /**
     * Initialize filesystem storage for a new app
     * 
     * @param int $appId App ID
     * @param array $appData App data
     * @return array Initialization result
     */
    public function initializeFilesystemStorage(int $appId, array $appData): array
    {
        try {
            $slug = $appData['slug'] ?? "app-{$appId}";
            $repositoryPath = "/apps/{$slug}";
            $fullPath = dirname(__DIR__, 2) . '/public' . $repositoryPath;

            // Create directory structure
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                mkdir($fullPath . '/src', 0755, true);
                mkdir($fullPath . '/build', 0755, true);
            }

            // Initialize Git repository
            if (!is_dir($fullPath . '/.git')) {
                exec("cd " . escapeshellarg($fullPath) . " && git init");
                exec("cd " . escapeshellarg($fullPath) . " && git config user.name 'Workz Platform'");
                exec("cd " . escapeshellarg($fullPath) . " && git config user.email 'noreply@workz.com'");
            }

            // Create initial files
            $this->createInitialFiles($fullPath, $appData);

            return [
                'success' => true,
                'repository_path' => $repositoryPath
            ];

        } catch (\Throwable $e) {
            error_log("Error initializing filesystem storage: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create initial files for filesystem storage
     */
    private function createInitialFiles(string $path, array $appData): void
    {
        $appType = $appData['app_type'] ?? 'javascript';

        // Create workz.json configuration
        $config = [
            'name' => $appData['tt'] ?? 'Untitled App',
            'slug' => $appData['slug'] ?? 'untitled-app',
            'version' => $appData['version'] ?? '1.0.0',
            'appType' => $appType,
            'storageType' => 'filesystem',
            'workzSDK' => [
                'version' => '^2.0.0',
                'scopes' => json_decode($appData['scopes'] ?? '[]', true)
            ]
        ];

        file_put_contents($path . '/workz.json', json_encode($config, JSON_PRETTY_PRINT));

        // If files are provided, materialize them first
        $providedFilesRaw = $appData['files'] ?? null;
        if (!empty($providedFilesRaw)) {
            $filesArr = is_array($providedFilesRaw) ? $providedFilesRaw : json_decode((string)$providedFilesRaw, true);
            if (is_array($filesArr)) {
                foreach ($filesArr as $rel => $content) {
                    $full = rtrim($path, '/');
                    if (strpos($rel, '/') !== 0) { $full .= '/'; }
                    $full .= $rel;
                    $dir = dirname($full);
                    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
                    file_put_contents($full, (string)$content);
                }
            }
        }

        // Create initial source files when nothing was provided
        if ($appType === 'javascript') {
            $jsCode = $appData['js_code'] ?? '// Your JavaScript code here\nconsole.log("Hello from Workz!");';
            if (!file_exists($path . '/src/main.js')) {
                if (!is_dir($path . '/src')) { mkdir($path . '/src', 0755, true); }
                file_put_contents($path . '/src/main.js', $jsCode);
            }
        } elseif ($appType === 'flutter') {
            $dartCode = $appData['dart_code'] ?? '// Your Dart code here\nvoid main() {\n  print("Hello from Flutter!\n");\n}';
            // Padroniza para lib/main.dart (estrutura Flutter)
            if (!file_exists($path . '/lib/main.dart')) {
                if (!is_dir($path . '/lib')) { mkdir($path . '/lib', 0755, true); }
                file_put_contents($path . '/lib/main.dart', $dartCode);
            }
            
            // Create pubspec.yaml (string template to avoid requiring yaml extension)
            $pkgName = str_replace('-', '_', $appData['slug'] ?? 'untitled_app');
            $desc = $appData['ds'] ?? 'A Flutter app for Workz Platform';
            $ver = $appData['version'] ?? '1.0.0';
            $pubspecYaml = "name: {$pkgName}\n" .
                            "description: {$desc}\n" .
                            "version: {$ver}\n" .
                            "environment:\n  sdk: '>=3.0.0 <4.0.0'\n" .
                            "dependencies:\n  flutter:\n    sdk: flutter\n  workz_sdk: ^2.0.0\n";
            if (!file_exists($path . '/pubspec.yaml')) {
                file_put_contents($path . '/pubspec.yaml', $pubspecYaml);
            }
        }

        // Create README
        $readme = "# " . ($appData['tt'] ?? 'Untitled App') . "\n\n" . 
                  ($appData['ds'] ?? 'A Workz Platform application') . "\n\n" .
                  "## Development\n\nThis app uses " . ucfirst($appType) . " and is stored in filesystem mode.";
        
        file_put_contents($path . '/README.md', $readme);

        // Initial commit
        exec("cd " . escapeshellarg($path) . " && git add .");
        exec("cd " . escapeshellarg($path) . " && git commit -m 'Initial commit'");
    }
}
