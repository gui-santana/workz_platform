<?php
// src/Core/RuntimeEngine.php

namespace Workz\Platform\Core;

use Workz\Platform\Models\General;

/**
 * Enhanced Runtime Engine for unified app execution
 * Supports both JavaScript and Flutter apps with efficient loading and caching
 * 
 * Requirements: 7.3, 7.4, 4.4
 */
class RuntimeEngine
{
    private General $generalModel;
    private StorageManager $storageManager;
    private PerformanceOptimizer $performanceOptimizer;
    private array $cache = [];
    private array $runningApps = [];
    private const CACHE_TTL = 300; // 5 minutes
    private const MAX_CACHE_SIZE = 50; // Maximum cached apps

    public function __construct()
    {
        $this->generalModel = new General();
        $this->storageManager = new StorageManager();
        $this->performanceOptimizer = new PerformanceOptimizer();
        
        // Initialize performance optimizations
        $this->performanceOptimizer->initializeStaticCaching();
    }

    /**
     * Load app for execution with efficient caching and performance optimization
     * 
     * @param int $appId App ID
     * @param string $platform Target platform (web, android, ios, etc.)
     * @param array $options Loading options (lazy, preload, etc.)
     * @return array App instance data
     */
    public function loadApp(int $appId, string $platform = 'web', array $options = []): array
    {
        $startTime = microtime(true);
        $cacheKey = "app_{$appId}_{$platform}";
        
        // Optimize memory usage before loading
        $this->optimizeMemoryForNewApp($appId);
        
        // Check cache first
        if ($this->isCached($cacheKey) && !($options['force_reload'] ?? false)) {
            $this->updateCacheAccess($cacheKey);
            $appInstance = $this->cache[$cacheKey]['data'];
            $appInstance['load_source'] = 'cache';
            $appInstance['load_time'] = microtime(true) - $startTime;
            return $appInstance;
        }

        // Get app metadata
        $app = $this->storageManager->getAppMetadata($appId);
        if (!$app) {
            throw new \RuntimeException("App not found: {$appId}");
        }

        $storageType = $app['storage_type'] ?? 'database';
        
        // Load based on storage type
        if ($storageType === 'filesystem') {
            $appInstance = $this->loadFromFilesystem($app, $platform, $options);
        } else {
            $appInstance = $this->loadFromDatabase($app, $platform, $options);
        }

        // Add performance metrics
        $appInstance['load_time'] = microtime(true) - $startTime;
        $appInstance['load_source'] = $storageType;
        
        // Generate preload directives for faster startup
        $appInstance['preload_directives'] = $this->generatePreloadDirectives($appInstance);
        
        // Cache static assets for CDN delivery
        $appInstance = $this->cacheStaticAssets($appInstance);
        
        // Cache the result
        $this->cacheAppInstance($cacheKey, $appInstance);
        
        // Track running app
        $this->trackRunningApp($appId, $appInstance);
        
        return $appInstance;
    }

    /**
     * Load app from database storage
     * 
     * @param array $app App metadata
     * @param string $platform Target platform
     * @param array $options Loading options
     * @return array App instance
     */
    private function loadFromDatabase(array $app, string $platform, array $options): array
    {
        $codeData = $this->storageManager->getAppCode($app['id']);
        
        $appType = $app['app_type'] ?? 'javascript';
        $sourceCode = $appType === 'flutter' ? $codeData['dart_code'] : $codeData['js_code'];
        
        // For database storage, code is ready to execute
        return [
            'id' => $app['id'],
            'slug' => $app['slug'],
            'name' => $app['tt'],
            'app_type' => $appType,
            'storage_type' => 'database',
            'platform' => $platform,
            'source_code' => $sourceCode,
            'js_code' => $codeData['js_code'] ?? '',
            'dart_code' => $codeData['dart_code'] ?? '',
            'execution_mode' => 'direct',
            'assets' => [],
            'dependencies' => $this->parseDependencies($app),
            'config' => $this->getAppConfig($app),
            'load_time' => microtime(true),
            'cached' => false
        ];
    }

    /**
     * Load app from filesystem storage
     * 
     * @param array $app App metadata
     * @param string $platform Target platform
     * @param array $options Loading options
     * @return array App instance
     */
    private function loadFromFilesystem(array $app, string $platform, array $options): array
    {
        $repositoryPath = $app['repository_path'];
        
        if (!$repositoryPath || !is_dir($repositoryPath)) {
            throw new \RuntimeException("Repository path not found: {$repositoryPath}");
        }

        // Check if we should load from build artifacts or source
        $useArtifacts = $this->shouldUseArtifacts($app, $platform);
        
        if ($useArtifacts) {
            return $this->loadFromArtifacts($app, $platform, $options);
        } else {
            return $this->loadFromSource($app, $platform, $options);
        }
    }

    /**
     * Load app from compiled artifacts
     * 
     * @param array $app App metadata
     * @param string $platform Target platform
     * @param array $options Loading options
     * @return array App instance
     */
    private function loadFromArtifacts(array $app, string $platform, array $options): array
    {
        $repositoryPath = $app['repository_path'];
        $artifactPath = $this->getArtifactPath($repositoryPath, $platform);
        
        if (!is_dir($artifactPath)) {
            // Fallback to source if artifacts not available
            return $this->loadFromSource($app, $platform, $options);
        }

        $appType = $app['app_type'] ?? 'javascript';
        $assets = $this->loadArtifactAssets($artifactPath, $platform, $options);
        
        return [
            'id' => $app['id'],
            'slug' => $app['slug'],
            'name' => $app['tt'],
            'app_type' => $appType,
            'storage_type' => 'filesystem',
            'platform' => $platform,
            'execution_mode' => 'artifact',
            'artifact_path' => $artifactPath,
            'assets' => $assets,
            'dependencies' => $this->loadDependenciesFromArtifacts($artifactPath),
            'config' => $this->loadConfigFromRepository($repositoryPath),
            'load_time' => microtime(true),
            'cached' => false
        ];
    }

    /**
     * Load app from source code
     * 
     * @param array $app App metadata
     * @param string $platform Target platform
     * @param array $options Loading options
     * @return array App instance
     */
    private function loadFromSource(array $app, string $platform, array $options): array
    {
        $codeData = $this->storageManager->getAppCode($app['id']);
        $repositoryPath = $app['repository_path'];
        
        $appType = $app['app_type'] ?? 'javascript';
        $sourceCode = $appType === 'flutter' ? $codeData['dart_code'] : $codeData['js_code'];
        
        return [
            'id' => $app['id'],
            'slug' => $app['slug'],
            'name' => $app['tt'],
            'app_type' => $appType,
            'storage_type' => 'filesystem',
            'platform' => $platform,
            'source_code' => $sourceCode,
            'js_code' => $codeData['js_code'] ?? '',
            'dart_code' => $codeData['dart_code'] ?? '',
            'execution_mode' => 'source',
            'repository_path' => $repositoryPath,
            'assets' => $this->loadSourceAssets($repositoryPath, $options),
            'dependencies' => $this->loadDependenciesFromSource($repositoryPath),
            'config' => $this->loadConfigFromRepository($repositoryPath),
            'load_time' => microtime(true),
            'cached' => false
        ];
    }

    /**
     * Determine if artifacts should be used for loading
     * 
     * @param array $app App metadata
     * @param string $platform Target platform
     * @return bool Whether to use artifacts
     */
    private function shouldUseArtifacts(array $app, string $platform): bool
    {
        $repositoryPath = $app['repository_path'];
        $artifactPath = $this->getArtifactPath($repositoryPath, $platform);
        
        // Use artifacts if they exist and are newer than source
        if (!is_dir($artifactPath)) {
            return false;
        }

        // Check if artifacts are up to date
        $artifactTime = filemtime($artifactPath);
        $sourceTime = $this->getSourceModificationTime($repositoryPath);
        
        return $artifactTime >= $sourceTime;
    }

    /**
     * Get artifact path for platform
     * 
     * @param string $repositoryPath Repository path
     * @param string $platform Target platform
     * @return string Artifact path
     */
    private function getArtifactPath(string $repositoryPath, string $platform): string
    {
        return $repositoryPath . '/build/' . $platform;
    }

    /**
     * Get source modification time
     * 
     * @param string $repositoryPath Repository path
     * @return int Modification timestamp
     */
    private function getSourceModificationTime(string $repositoryPath): int
    {
        $srcPath = $repositoryPath . '/src';
        if (!is_dir($srcPath)) {
            return 0;
        }

        $latestTime = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $latestTime = max($latestTime, $file->getMTime());
            }
        }

        return $latestTime;
    }

    /**
     * Load artifact assets with lazy loading support
     * 
     * @param string $artifactPath Artifact directory path
     * @param string $platform Target platform
     * @param array $options Loading options
     * @return array Asset information
     */
    private function loadArtifactAssets(string $artifactPath, string $platform, array $options): array
    {
        $assets = [];
        $lazyLoad = $options['lazy_load'] ?? true;
        
        if ($platform === 'web') {
            // Web assets
            $indexFile = $artifactPath . '/index.html';
            $mainJsFile = $artifactPath . '/main.js';
            $assetsDir = $artifactPath . '/assets';
            
            if (file_exists($indexFile)) {
                $assets['index'] = [
                    'type' => 'html',
                    'path' => $indexFile,
                    'size' => filesize($indexFile),
                    'loaded' => !$lazyLoad
                ];
                
                if (!$lazyLoad) {
                    $assets['index']['content'] = file_get_contents($indexFile);
                }
            }
            
            if (file_exists($mainJsFile)) {
                $assets['main_js'] = [
                    'type' => 'javascript',
                    'path' => $mainJsFile,
                    'size' => filesize($mainJsFile),
                    'loaded' => !$lazyLoad
                ];
                
                if (!$lazyLoad) {
                    $assets['main_js']['content'] = file_get_contents($mainJsFile);
                }
            }
            
            if (is_dir($assetsDir)) {
                $assets['static_assets'] = $this->scanAssetDirectory($assetsDir, $lazyLoad);
            }
        }
        
        return $assets;
    }

    /**
     * Load source assets
     * 
     * @param string $repositoryPath Repository path
     * @param array $options Loading options
     * @return array Asset information
     */
    private function loadSourceAssets(string $repositoryPath, array $options): array
    {
        $assets = [];
        $assetsDir = $repositoryPath . '/src/assets';
        
        if (is_dir($assetsDir)) {
            $lazyLoad = $options['lazy_load'] ?? true;
            $assets['source_assets'] = $this->scanAssetDirectory($assetsDir, $lazyLoad);
        }
        
        return $assets;
    }

    /**
     * Scan asset directory
     * 
     * @param string $directory Directory to scan
     * @param bool $lazyLoad Whether to lazy load assets
     * @return array Asset list
     */
    private function scanAssetDirectory(string $directory, bool $lazyLoad = true): array
    {
        $assets = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($directory . '/', '', $file->getPathname());
                $assets[$relativePath] = [
                    'type' => $this->getAssetType($file->getExtension()),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'loaded' => false
                ];
                
                if (!$lazyLoad && $file->getSize() < 1024 * 100) { // Load small files (< 100KB)
                    $assets[$relativePath]['content'] = file_get_contents($file->getPathname());
                    $assets[$relativePath]['loaded'] = true;
                }
            }
        }
        
        return $assets;
    }

    /**
     * Get asset type from extension
     * 
     * @param string $extension File extension
     * @return string Asset type
     */
    private function getAssetType(string $extension): string
    {
        $types = [
            'js' => 'javascript',
            'css' => 'stylesheet',
            'html' => 'html',
            'json' => 'json',
            'png' => 'image',
            'jpg' => 'image',
            'jpeg' => 'image',
            'gif' => 'image',
            'svg' => 'image',
            'woff' => 'font',
            'woff2' => 'font',
            'ttf' => 'font'
        ];
        
        return $types[strtolower($extension)] ?? 'binary';
    }

    /**
     * Load dependencies from artifacts
     * 
     * @param string $artifactPath Artifact path
     * @return array Dependencies
     */
    private function loadDependenciesFromArtifacts(string $artifactPath): array
    {
        $dependencies = [];
        
        // Try to load from package.json or pubspec.yaml in build directory
        $packageFile = $artifactPath . '/../package.json';
        $pubspecFile = $artifactPath . '/../pubspec.yaml';
        
        if (file_exists($packageFile)) {
            $packageData = json_decode(file_get_contents($packageFile), true);
            $dependencies['javascript'] = $packageData['dependencies'] ?? [];
        }
        
        if (file_exists($pubspecFile)) {
            // Parse YAML dependencies (basic parsing)
            $pubspecContent = file_get_contents($pubspecFile);
            $dependencies['flutter'] = $this->parseYamlDependencies($pubspecContent);
        }
        
        return $dependencies;
    }

    /**
     * Load dependencies from source
     * 
     * @param string $repositoryPath Repository path
     * @return array Dependencies
     */
    private function loadDependenciesFromSource(string $repositoryPath): array
    {
        $dependencies = [];
        
        $packageFile = $repositoryPath . '/package.json';
        $pubspecFile = $repositoryPath . '/pubspec.yaml';
        
        if (file_exists($packageFile)) {
            $packageData = json_decode(file_get_contents($packageFile), true);
            $dependencies['javascript'] = $packageData['dependencies'] ?? [];
        }
        
        if (file_exists($pubspecFile)) {
            $pubspecContent = file_get_contents($pubspecFile);
            $dependencies['flutter'] = $this->parseYamlDependencies($pubspecContent);
        }
        
        return $dependencies;
    }

    /**
     * Parse YAML dependencies (basic implementation)
     * 
     * @param string $yamlContent YAML content
     * @return array Parsed dependencies
     */
    private function parseYamlDependencies(string $yamlContent): array
    {
        $dependencies = [];
        $lines = explode("\n", $yamlContent);
        $inDependencies = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === 'dependencies:') {
                $inDependencies = true;
                continue;
            }
            
            if ($inDependencies) {
                if (strpos($line, ':') !== false && !str_starts_with($line, '#')) {
                    [$name, $version] = explode(':', $line, 2);
                    $dependencies[trim($name)] = trim($version);
                } elseif (!str_starts_with($line, ' ') && $line !== '') {
                    // End of dependencies section
                    break;
                }
            }
        }
        
        return $dependencies;
    }

    /**
     * Load app configuration from repository
     * 
     * @param string $repositoryPath Repository path
     * @return array App configuration
     */
    private function loadConfigFromRepository(string $repositoryPath): array
    {
        $configFile = $repositoryPath . '/workz.json';
        
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true) ?? [];
        }
        
        return [];
    }

    /**
     * Parse dependencies from app metadata
     * 
     * @param array $app App metadata
     * @return array Dependencies
     */
    private function parseDependencies(array $app): array
    {
        $scopes = json_decode($app['scopes'] ?? '[]', true);
        
        return [
            'workz_sdk' => ['version' => '2.0.0'],
            'scopes' => $scopes
        ];
    }

    /**
     * Get app configuration from metadata
     * 
     * @param array $app App metadata
     * @return array App configuration
     */
    private function getAppConfig(array $app): array
    {
        return [
            'name' => $app['tt'],
            'slug' => $app['slug'],
            'version' => $app['version'] ?? '1.0.0',
            'app_type' => $app['app_type'] ?? 'javascript',
            'scopes' => json_decode($app['scopes'] ?? '[]', true),
            'theme' => [
                'primary_color' => $app['color'] ?? '#007bff',
                'icon' => $app['icon'] ?? '/images/default-app-icon.png'
            ]
        ];
    }

    /**
     * Check if app is cached and not expired
     * 
     * @param string $cacheKey Cache key
     * @return bool Whether cached and valid
     */
    private function isCached(string $cacheKey): bool
    {
        if (!isset($this->cache[$cacheKey])) {
            return false;
        }
        
        $cacheEntry = $this->cache[$cacheKey];
        return (time() - $cacheEntry['timestamp']) < self::CACHE_TTL;
    }

    /**
     * Cache app instance
     * 
     * @param string $cacheKey Cache key
     * @param array $appInstance App instance data
     */
    private function cacheAppInstance(string $cacheKey, array $appInstance): void
    {
        // Implement LRU cache eviction if needed
        if (count($this->cache) >= self::MAX_CACHE_SIZE) {
            $this->evictOldestCacheEntry();
        }
        
        $this->cache[$cacheKey] = [
            'data' => $appInstance,
            'timestamp' => time(),
            'access_count' => 1,
            'last_access' => time()
        ];
        
        // Mark as cached
        $this->cache[$cacheKey]['data']['cached'] = true;
    }

    /**
     * Update cache access statistics
     * 
     * @param string $cacheKey Cache key
     */
    private function updateCacheAccess(string $cacheKey): void
    {
        if (isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey]['access_count']++;
            $this->cache[$cacheKey]['last_access'] = time();
        }
    }

    /**
     * Evict oldest cache entry (LRU)
     */
    private function evictOldestCacheEntry(): void
    {
        if (empty($this->cache)) {
            return;
        }
        
        $oldestKey = null;
        $oldestTime = PHP_INT_MAX;
        
        foreach ($this->cache as $key => $entry) {
            if ($entry['last_access'] < $oldestTime) {
                $oldestTime = $entry['last_access'];
                $oldestKey = $key;
            }
        }
        
        if ($oldestKey) {
            unset($this->cache[$oldestKey]);
        }
    }

    /**
     * Preload app for faster execution
     * 
     * @param int $appId App ID
     * @param string $platform Target platform
     * @return bool Success status
     */
    public function preloadApp(int $appId, string $platform = 'web'): bool
    {
        try {
            $this->loadApp($appId, $platform, ['lazy_load' => false]);
            return true;
        } catch (\Throwable $e) {
            error_log("Failed to preload app {$appId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load asset content on demand
     * 
     * @param int $appId App ID
     * @param string $assetPath Asset path
     * @return array Asset content
     */
    public function loadAsset(int $appId, string $assetPath): array
    {
        $app = $this->storageManager->getAppMetadata($appId);
        if (!$app) {
            throw new \RuntimeException("App not found: {$appId}");
        }

        $storageType = $app['storage_type'] ?? 'database';
        
        if ($storageType === 'filesystem') {
            $fullPath = $app['repository_path'] . '/' . ltrim($assetPath, '/');
            
            if (!file_exists($fullPath) || !is_file($fullPath)) {
                throw new \RuntimeException("Asset not found: {$assetPath}");
            }
            
            return [
                'path' => $assetPath,
                'type' => $this->getAssetType(pathinfo($fullPath, PATHINFO_EXTENSION)),
                'size' => filesize($fullPath),
                'content' => file_get_contents($fullPath),
                'mime_type' => mime_content_type($fullPath)
            ];
        } else {
            throw new \RuntimeException("Asset loading not supported for database storage");
        }
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function getCacheStatistics(): array
    {
        $totalSize = 0;
        $totalAccess = 0;
        
        foreach ($this->cache as $entry) {
            $totalAccess += $entry['access_count'];
            // Estimate size (rough calculation)
            $totalSize += strlen(serialize($entry['data']));
        }
        
        return [
            'entries' => count($this->cache),
            'max_entries' => self::MAX_CACHE_SIZE,
            'total_access_count' => $totalAccess,
            'estimated_size_bytes' => $totalSize,
            'ttl_seconds' => self::CACHE_TTL
        ];
    }

    /**
     * Clear cache
     * 
     * @param int|null $appId Optional app ID to clear specific app cache
     */
    public function clearCache(?int $appId = null): void
    {
        if ($appId) {
            // Clear cache for specific app
            $keysToRemove = [];
            foreach (array_keys($this->cache) as $key) {
                if (str_starts_with($key, "app_{$appId}_")) {
                    $keysToRemove[] = $key;
                }
            }
            
            foreach ($keysToRemove as $key) {
                unset($this->cache[$key]);
            }
            
            // Remove from running apps
            unset($this->runningApps[$appId]);
        } else {
            // Clear all cache
            $this->cache = [];
            $this->runningApps = [];
        }
    }

    /**
     * Optimize memory usage before loading new app
     * 
     * @param int $appId App ID being loaded
     */
    private function optimizeMemoryForNewApp(int $appId): void
    {
        // Get current running apps for memory optimization
        $runningAppsData = array_values($this->runningApps);
        
        // Perform memory optimization
        $optimization = $this->performanceOptimizer->optimizeMemoryUsage($runningAppsData);
        
        // Handle concurrent app limits
        if (isset($optimization['optimizations_applied']['concurrent_limit'])) {
            $rejectedApps = $optimization['optimizations_applied']['concurrent_limit']['rejected_apps'];
            foreach ($rejectedApps as $rejectedAppId) {
                $this->unloadApp($rejectedAppId);
            }
        }
    }

    /**
     * Generate preload directives for app assets
     * 
     * @param array $appInstance App instance data
     * @return array Preload directives
     */
    private function generatePreloadDirectives(array $appInstance): array
    {
        $resources = [];
        
        // Add WorkzSDK as high priority
        $resources[] = [
            'url' => '/js/core/workz-sdk-v2.js',
            'type' => 'js',
            'priority' => 'high'
        ];
        
        // Add app runner
        $resources[] = [
            'url' => '/js/core/app-runner.js',
            'type' => 'js',
            'priority' => 'high'
        ];
        
        // Add app-specific assets
        if (isset($appInstance['assets'])) {
            foreach ($appInstance['assets'] as $assetGroup) {
                if (is_array($assetGroup)) {
                    foreach ($assetGroup as $asset) {
                        if (isset($asset['path']) && isset($asset['type'])) {
                            $priority = $this->getAssetPriority($asset['type'], $asset['size'] ?? 0);
                            $resources[] = [
                                'url' => $this->getAssetUrl($asset['path']),
                                'type' => $asset['type'],
                                'priority' => $priority
                            ];
                        }
                    }
                }
            }
        }
        
        return $this->performanceOptimizer->generatePreloadDirectives($resources);
    }

    /**
     * Get asset priority based on type and size
     * 
     * @param string $type Asset type
     * @param int $size Asset size in bytes
     * @return string Priority level
     */
    private function getAssetPriority(string $type, int $size): string
    {
        // Critical assets get high priority
        if (in_array($type, ['javascript', 'html'])) {
            return 'high';
        }
        
        // Small assets get medium priority
        if ($size < 50 * 1024) { // < 50KB
            return 'medium';
        }
        
        // Large assets get low priority
        return 'low';
    }

    /**
     * Get public URL for asset
     * 
     * @param string $assetPath Asset path
     * @return string Public URL
     */
    private function getAssetUrl(string $assetPath): string
    {
        // Convert filesystem path to public URL
        if (str_starts_with($assetPath, '/')) {
            return $assetPath;
        }
        
        return '/assets/' . ltrim($assetPath, '/');
    }

    /**
     * Cache static assets for CDN delivery
     * 
     * @param array $appInstance App instance data
     * @return array Updated app instance with cached asset URLs
     */
    private function cacheStaticAssets(array $appInstance): array
    {
        if (!isset($appInstance['assets'])) {
            return $appInstance;
        }
        
        foreach ($appInstance['assets'] as $groupName => &$assetGroup) {
            if (is_array($assetGroup)) {
                foreach ($assetGroup as $assetName => &$asset) {
                    if (isset($asset['content']) && isset($asset['type'])) {
                        // Cache the asset
                        $cacheResult = $this->performanceOptimizer->cacheStaticAsset(
                            $asset['path'] ?? $assetName,
                            $asset['content'],
                            $asset['type']
                        );
                        
                        if (isset($cacheResult['cdn_url'])) {
                            $asset['cdn_url'] = $cacheResult['cdn_url'];
                            $asset['cached'] = true;
                            $asset['cache_hash'] = $cacheResult['hash'] ?? null;
                            $asset['etag'] = $cacheResult['etag'] ?? null;
                        }
                    }
                }
            }
        }
        
        return $appInstance;
    }

    /**
     * Track running app for memory management
     * 
     * @param int $appId App ID
     * @param array $appInstance App instance data
     */
    private function trackRunningApp(int $appId, array $appInstance): void
    {
        $this->runningApps[$appId] = [
            'id' => $appId,
            'slug' => $appInstance['slug'],
            'app_type' => $appInstance['app_type'],
            'platform' => $appInstance['platform'],
            'started_at' => time(),
            'last_access' => time(),
            'priority' => $this->getAppPriority($appInstance),
            'memory_estimate' => $this->estimateAppMemoryUsage($appInstance)
        ];
    }

    /**
     * Get app priority for memory management
     * 
     * @param array $appInstance App instance data
     * @return int Priority (1-10, higher is more important)
     */
    private function getAppPriority(array $appInstance): int
    {
        // Flutter apps get higher priority due to complexity
        if ($appInstance['app_type'] === 'flutter') {
            return 7;
        }
        
        // Filesystem apps get medium priority
        if ($appInstance['storage_type'] === 'filesystem') {
            return 6;
        }
        
        // Database apps get lower priority
        return 5;
    }

    /**
     * Estimate app memory usage
     * 
     * @param array $appInstance App instance data
     * @return int Estimated memory usage in bytes
     */
    private function estimateAppMemoryUsage(array $appInstance): int
    {
        $baseMemory = 1024 * 1024; // 1MB base
        
        // Add memory for source code
        if (isset($appInstance['source_code'])) {
            $baseMemory += strlen($appInstance['source_code']) * 2; // 2x for processing
        }
        
        // Add memory for assets
        if (isset($appInstance['assets'])) {
            foreach ($appInstance['assets'] as $assetGroup) {
                if (is_array($assetGroup)) {
                    foreach ($assetGroup as $asset) {
                        if (isset($asset['size'])) {
                            $baseMemory += $asset['size'];
                        }
                    }
                }
            }
        }
        
        // Flutter apps use more memory
        if ($appInstance['app_type'] === 'flutter') {
            $baseMemory *= 3;
        }
        
        return $baseMemory;
    }

    /**
     * Unload app to free memory
     * 
     * @param int $appId App ID to unload
     * @return bool Success status
     */
    public function unloadApp(int $appId): bool
    {
        try {
            // Clear from cache
            $this->clearCache($appId);
            
            // Remove from running apps
            unset($this->runningApps[$appId]);
            
            // Log the unload
            error_log("Unloaded app {$appId} to free memory");
            
            return true;
        } catch (\Throwable $e) {
            error_log("Failed to unload app {$appId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get performance metrics for runtime engine
     * 
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $cacheStats = $this->getCacheStatistics();
        $optimizerMetrics = $this->performanceOptimizer->getPerformanceMetrics();
        
        return [
            'runtime_cache' => $cacheStats,
            'running_apps' => [
                'count' => count($this->runningApps),
                'apps' => array_values($this->runningApps)
            ],
            'performance_optimizer' => $optimizerMetrics,
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ]
        ];
    }

    /**
     * Update app access time for LRU tracking
     * 
     * @param int $appId App ID
     */
    public function updateAppAccess(int $appId): void
    {
        if (isset($this->runningApps[$appId])) {
            $this->runningApps[$appId]['last_access'] = time();
        }
    }
}