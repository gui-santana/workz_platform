<?php
// src/Core/PerformanceOptimizer.php

namespace Workz\Platform\Core;

use Workz\Platform\Models\General;

/**
 * Performance Optimizer for static asset caching, CDN integration, and resource preloading
 * Implements memory management optimizations for concurrent app execution
 * 
 * Requirements: 7.1, 7.2, 7.3
 */
class PerformanceOptimizer
{
    private General $generalModel;
    private array $config;
    private array $memoryStats = [];
    private array $cacheHeaders = [];
    private const MAX_CONCURRENT_APPS = 10;
    private const MEMORY_THRESHOLD = 0.8; // 80% of available memory

    public function __construct(array $config = [])
    {
        $this->generalModel = new General();
        $this->config = array_merge([
            'cdn_enabled' => true,
            'cdn_base_url' => 'https://cdn.workz.com',
            'cache_ttl' => 3600, // 1 hour
            'preload_enabled' => true,
            'memory_limit' => '512M',
            'static_cache_dir' => '/var/cache/workz/static',
            'enable_compression' => true,
            'enable_etag' => true
        ], $config);
    }

    /**
     * Initialize static asset caching system
     * 
     * @return bool Success status
     */
    public function initializeStaticCaching(): bool
    {
        try {
            // Create cache directory if it doesn't exist
            $cacheDir = $this->config['static_cache_dir'];
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            // Create subdirectories for different asset types
            $subDirs = ['js', 'css', 'images', 'fonts', 'artifacts'];
            foreach ($subDirs as $subDir) {
                $path = $cacheDir . '/' . $subDir;
                if (!is_dir($path)) {
                    mkdir($path, 0755, true);
                }
            }

            // Set up cache headers configuration
            $this->setupCacheHeaders();

            return true;
        } catch (\Throwable $e) {
            error_log("Failed to initialize static caching: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cache static asset with compression and optimization
     * 
     * @param string $assetPath Original asset path
     * @param string $content Asset content
     * @param string $type Asset type (js, css, image, etc.)
     * @return array Cache result with path and metadata
     */
    public function cacheStaticAsset(string $assetPath, string $content, string $type): array
    {
        try {
            $cacheDir = $this->config['static_cache_dir'];
            $hash = md5($content);
            $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
            $cachedFileName = $hash . '.' . $extension;
            $cachedPath = $cacheDir . '/' . $type . '/' . $cachedFileName;
            
            // Check if already cached
            if (file_exists($cachedPath)) {
                return [
                    'cached_path' => $cachedPath,
                    'cdn_url' => $this->getCdnUrl($type . '/' . $cachedFileName),
                    'size' => filesize($cachedPath),
                    'hash' => $hash,
                    'cache_hit' => true
                ];
            }
            
            // Compress content based on type
            $optimizedContent = $this->optimizeContent($content, $type);
            
            // Write to cache
            file_put_contents($cachedPath, $optimizedContent);
            
            // Generate ETag
            $etag = $this->generateETag($cachedPath);
            
            // Store metadata
            $metadataPath = $cachedPath . '.meta';
            $metadata = [
                'original_path' => $assetPath,
                'type' => $type,
                'hash' => $hash,
                'etag' => $etag,
                'created_at' => time(),
                'original_size' => strlen($content),
                'optimized_size' => strlen($optimizedContent),
                'compression_ratio' => round((1 - strlen($optimizedContent) / strlen($content)) * 100, 2)
            ];
            file_put_contents($metadataPath, json_encode($metadata));
            
            return [
                'cached_path' => $cachedPath,
                'cdn_url' => $this->getCdnUrl($type . '/' . $cachedFileName),
                'size' => strlen($optimizedContent),
                'hash' => $hash,
                'etag' => $etag,
                'cache_hit' => false,
                'metadata' => $metadata
            ];
            
        } catch (\Throwable $e) {
            error_log("Failed to cache asset {$assetPath}: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'cached_path' => null,
                'cdn_url' => null
            ];
        }
    }

    /**
     * Optimize content based on type
     * 
     * @param string $content Original content
     * @param string $type Content type
     * @return string Optimized content
     */
    private function optimizeContent(string $content, string $type): string
    {
        if (!$this->config['enable_compression']) {
            return $content;
        }
        
        switch ($type) {
            case 'js':
                return $this->minifyJavaScript($content);
            case 'css':
                return $this->minifyCSS($content);
            case 'json':
                return $this->minifyJSON($content);
            default:
                // For other types, use gzip compression if beneficial
                $compressed = gzcompress($content, 9);
                return strlen($compressed) < strlen($content) ? $compressed : $content;
        }
    }

    /**
     * Minify JavaScript content
     * 
     * @param string $content JavaScript content
     * @return string Minified content
     */
    private function minifyJavaScript(string $content): string
    {
        // Basic JavaScript minification
        // Remove comments
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        $content = preg_replace('/\/\/.*$/m', '', $content);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = str_replace(['; ', ' {', '} ', ' (', ') ', ', '], [';', '{', '}', '(', ')', ','], $content);
        
        return trim($content);
    }

    /**
     * Minify CSS content
     * 
     * @param string $content CSS content
     * @return string Minified content
     */
    private function minifyCSS(string $content): string
    {
        // Remove comments
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = str_replace(['; ', ' {', '} ', ': ', ', '], [';', '{', '}', ':', ','], $content);
        
        return trim($content);
    }

    /**
     * Minify JSON content
     * 
     * @param string $content JSON content
     * @return string Minified content
     */
    private function minifyJSON(string $content): string
    {
        $decoded = json_decode($content, true);
        return $decoded ? json_encode($decoded, JSON_UNESCAPED_SLASHES) : $content;
    }

    /**
     * Get CDN URL for cached asset
     * 
     * @param string $relativePath Relative path from cache directory
     * @return string CDN URL
     */
    private function getCdnUrl(string $relativePath): string
    {
        if (!$this->config['cdn_enabled']) {
            return '/cache/static/' . $relativePath;
        }
        
        return rtrim($this->config['cdn_base_url'], '/') . '/static/' . $relativePath;
    }

    /**
     * Generate ETag for cached file
     * 
     * @param string $filePath File path
     * @return string ETag value
     */
    private function generateETag(string $filePath): string
    {
        if (!$this->config['enable_etag']) {
            return '';
        }
        
        $stat = stat($filePath);
        return '"' . md5($stat['mtime'] . $stat['size']) . '"';
    }

    /**
     * Setup cache headers for static assets
     */
    private function setupCacheHeaders(): void
    {
        // This would typically be handled by web server configuration
        // But we can provide the headers for PHP-served assets
        $this->cacheHeaders = [
            'Cache-Control' => 'public, max-age=' . $this->config['cache_ttl'],
            'Expires' => gmdate('D, d M Y H:i:s', time() + $this->config['cache_ttl']) . ' GMT',
            'Vary' => 'Accept-Encoding'
        ];
    }

    /**
     * Implement resource preloading for faster app startup
     * 
     * @param array $resources List of resources to preload
     * @return array Preload directives
     */
    public function generatePreloadDirectives(array $resources): array
    {
        if (!$this->config['preload_enabled']) {
            return [];
        }
        
        $preloadDirectives = [];
        
        foreach ($resources as $resource) {
            $type = $this->getPreloadType($resource['type']);
            $crossorigin = $this->needsCrossorigin($resource['type']) ? 'crossorigin' : '';
            
            $preloadDirectives[] = [
                'rel' => 'preload',
                'href' => $resource['url'],
                'as' => $type,
                'crossorigin' => $crossorigin,
                'priority' => $resource['priority'] ?? 'medium'
            ];
        }
        
        // Sort by priority
        usort($preloadDirectives, function($a, $b) {
            $priorities = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($priorities[$b['priority']] ?? 2) - ($priorities[$a['priority']] ?? 2);
        });
        
        return $preloadDirectives;
    }

    /**
     * Get preload type for resource
     * 
     * @param string $resourceType Resource type
     * @return string Preload type
     */
    private function getPreloadType(string $resourceType): string
    {
        $typeMap = [
            'js' => 'script',
            'css' => 'style',
            'font' => 'font',
            'image' => 'image',
            'json' => 'fetch'
        ];
        
        return $typeMap[$resourceType] ?? 'fetch';
    }

    /**
     * Check if resource needs crossorigin attribute
     * 
     * @param string $resourceType Resource type
     * @return bool Whether crossorigin is needed
     */
    private function needsCrossorigin(string $resourceType): bool
    {
        return in_array($resourceType, ['font', 'fetch']);
    }

    /**
     * Create memory management optimizations for concurrent apps
     * 
     * @param array $apps Currently running apps
     * @return array Memory optimization results
     */
    public function optimizeMemoryUsage(array $apps): array
    {
        $memoryInfo = $this->getMemoryInfo();
        $optimizations = [];
        
        // Check if we're approaching memory limits
        if ($memoryInfo['usage_ratio'] > self::MEMORY_THRESHOLD) {
            $optimizations = $this->performMemoryOptimizations($apps, $memoryInfo);
        }
        
        // Limit concurrent apps if necessary
        if (count($apps) > self::MAX_CONCURRENT_APPS) {
            $optimizations['concurrent_limit'] = $this->limitConcurrentApps($apps);
        }
        
        // Update memory statistics
        $this->updateMemoryStats($memoryInfo);
        
        return [
            'memory_info' => $memoryInfo,
            'optimizations_applied' => $optimizations,
            'concurrent_apps' => count($apps),
            'max_concurrent' => self::MAX_CONCURRENT_APPS
        ];
    }

    /**
     * Get current memory information
     * 
     * @return array Memory information
     */
    private function getMemoryInfo(): array
    {
        $memoryLimit = $this->parseMemoryLimit($this->config['memory_limit']);
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        return [
            'limit' => $memoryLimit,
            'usage' => $memoryUsage,
            'peak' => $memoryPeak,
            'available' => $memoryLimit - $memoryUsage,
            'usage_ratio' => $memoryUsage / $memoryLimit,
            'peak_ratio' => $memoryPeak / $memoryLimit
        ];
    }

    /**
     * Parse memory limit string to bytes
     * 
     * @param string $memoryLimit Memory limit (e.g., "512M")
     * @return int Memory limit in bytes
     */
    private function parseMemoryLimit(string $memoryLimit): int
    {
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (int) $memoryLimit;
        }
    }

    /**
     * Perform memory optimizations
     * 
     * @param array $apps Running apps
     * @param array $memoryInfo Memory information
     * @return array Applied optimizations
     */
    private function performMemoryOptimizations(array $apps, array $memoryInfo): array
    {
        $optimizations = [];
        
        // Clear unused caches
        $this->clearUnusedCaches();
        $optimizations['cache_cleared'] = true;
        
        // Garbage collection
        $collected = gc_collect_cycles();
        if ($collected > 0) {
            $optimizations['gc_collected'] = $collected;
        }
        
        // Unload least recently used apps if memory is still high
        if ($memoryInfo['usage_ratio'] > 0.9) {
            $unloaded = $this->unloadLeastRecentlyUsedApps($apps);
            if ($unloaded > 0) {
                $optimizations['apps_unloaded'] = $unloaded;
            }
        }
        
        return $optimizations;
    }

    /**
     * Clear unused caches
     */
    private function clearUnusedCaches(): void
    {
        $cacheDir = $this->config['static_cache_dir'];
        $maxAge = time() - ($this->config['cache_ttl'] * 2); // Clear caches older than 2x TTL
        
        if (!is_dir($cacheDir)) {
            return;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $maxAge) {
                unlink($file->getPathname());
            }
        }
    }

    /**
     * Unload least recently used apps
     * 
     * @param array $apps Running apps
     * @return int Number of apps unloaded
     */
    private function unloadLeastRecentlyUsedApps(array $apps): int
    {
        // Sort apps by last access time
        usort($apps, function($a, $b) {
            return ($a['last_access'] ?? 0) - ($b['last_access'] ?? 0);
        });
        
        $unloaded = 0;
        $targetCount = (int) (count($apps) * 0.7); // Keep 70% of apps
        
        for ($i = 0; $i < count($apps) - $targetCount; $i++) {
            // Signal app to unload (this would be handled by the runtime)
            $this->signalAppUnload($apps[$i]['id']);
            $unloaded++;
        }
        
        return $unloaded;
    }

    /**
     * Signal app to unload (placeholder for runtime integration)
     * 
     * @param int $appId App ID to unload
     */
    private function signalAppUnload(int $appId): void
    {
        // This would integrate with the runtime engine to unload the app
        // For now, just log the action
        error_log("Signaling unload for app {$appId} due to memory pressure");
    }

    /**
     * Limit concurrent apps
     * 
     * @param array $apps Running apps
     * @return array Limitation results
     */
    private function limitConcurrentApps(array $apps): array
    {
        $excess = count($apps) - self::MAX_CONCURRENT_APPS;
        $rejected = [];
        
        // Sort by priority/last access and reject excess apps
        usort($apps, function($a, $b) {
            $priorityA = $a['priority'] ?? 5;
            $priorityB = $b['priority'] ?? 5;
            
            if ($priorityA === $priorityB) {
                return ($a['last_access'] ?? 0) - ($b['last_access'] ?? 0);
            }
            
            return $priorityB - $priorityA; // Higher priority first
        });
        
        for ($i = self::MAX_CONCURRENT_APPS; $i < count($apps); $i++) {
            $rejected[] = $apps[$i]['id'];
        }
        
        return [
            'excess_count' => $excess,
            'rejected_apps' => $rejected,
            'allowed_apps' => self::MAX_CONCURRENT_APPS
        ];
    }

    /**
     * Update memory statistics
     * 
     * @param array $memoryInfo Current memory info
     */
    private function updateMemoryStats(array $memoryInfo): void
    {
        $this->memoryStats[] = [
            'timestamp' => time(),
            'usage' => $memoryInfo['usage'],
            'peak' => $memoryInfo['peak'],
            'usage_ratio' => $memoryInfo['usage_ratio']
        ];
        
        // Keep only last 100 entries
        if (count($this->memoryStats) > 100) {
            $this->memoryStats = array_slice($this->memoryStats, -100);
        }
    }

    /**
     * Get performance metrics
     * 
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $cacheDir = $this->config['static_cache_dir'];
        $cacheSize = $this->calculateDirectorySize($cacheDir);
        $memoryInfo = $this->getMemoryInfo();
        
        return [
            'cache' => [
                'directory' => $cacheDir,
                'size_bytes' => $cacheSize,
                'size_mb' => round($cacheSize / 1024 / 1024, 2),
                'ttl_seconds' => $this->config['cache_ttl']
            ],
            'memory' => $memoryInfo,
            'memory_history' => array_slice($this->memoryStats, -10), // Last 10 entries
            'cdn' => [
                'enabled' => $this->config['cdn_enabled'],
                'base_url' => $this->config['cdn_base_url']
            ],
            'preload' => [
                'enabled' => $this->config['preload_enabled']
            ],
            'compression' => [
                'enabled' => $this->config['enable_compression']
            ],
            'concurrent_limits' => [
                'max_apps' => self::MAX_CONCURRENT_APPS,
                'memory_threshold' => self::MEMORY_THRESHOLD
            ]
        ];
    }

    /**
     * Calculate directory size recursively
     * 
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    private function calculateDirectorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }
        
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }

    /**
     * Clear all performance caches
     * 
     * @return bool Success status
     */
    public function clearAllCaches(): bool
    {
        try {
            $cacheDir = $this->config['static_cache_dir'];
            
            if (is_dir($cacheDir)) {
                $this->removeDirectory($cacheDir);
                $this->initializeStaticCaching();
            }
            
            // Clear memory stats
            $this->memoryStats = [];
            
            return true;
        } catch (\Throwable $e) {
            error_log("Failed to clear caches: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove directory recursively
     * 
     * @param string $directory Directory to remove
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        
        rmdir($directory);
    }
}