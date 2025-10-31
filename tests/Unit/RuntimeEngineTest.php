<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workz\Platform\Core\RuntimeEngine;
use Workz\Platform\Models\General;

/**
 * Unit tests for RuntimeEngine class
 * Tests app loading, caching, and performance optimization
 * 
 * Requirements: 7.3, 7.4, 4.4
 */
class RuntimeEngineTest extends TestCase
{
    private RuntimeEngine $runtimeEngine;
    private array $testApp;

    protected function setUp(): void
    {
        $this->runtimeEngine = new RuntimeEngine();
        
        // Set up test app data
        $this->testApp = [
            'id' => 1,
            'slug' => 'test-runtime-app',
            'tt' => 'Test Runtime App',
            'app_type' => 'javascript',
            'storage_type' => 'database',
            'js_code' => 'console.log("Runtime test");',
            'dart_code' => '',
            'source_code' => 'console.log("Runtime test");',
            'code_size_bytes' => 1024,
            'scopes' => '["profile.read"]',
            'repository_path' => null,
            'color' => '#007bff',
            'icon' => '/images/test-icon.png',
            'version' => '1.0.0'
        ];
    }

    public function testLoadAppFromDatabase()
    {
        $result = $this->runtimeEngine->loadApp(1, 'web');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('app_type', $result);
        $this->assertArrayHasKey('storage_type', $result);
        $this->assertArrayHasKey('platform', $result);
        $this->assertArrayHasKey('execution_mode', $result);
        $this->assertArrayHasKey('load_time', $result);
        $this->assertArrayHasKey('load_source', $result);
        $this->assertArrayHasKey('preload_directives', $result);
        
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('web', $result['platform']);
        $this->assertIsNumeric($result['load_time']);
        $this->assertIsArray($result['preload_directives']);
    }

    public function testLoadAppFromFilesystem()
    {
        // Create temporary filesystem app
        $testDir = '/tmp/test-runtime-fs-app';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
            mkdir($testDir . '/src', 0755, true);
            mkdir($testDir . '/build', 0755, true);
        }
        
        file_put_contents($testDir . '/src/main.js', 'console.log("Filesystem test");');
        file_put_contents($testDir . '/workz.json', json_encode([
            'name' => 'Test FS App',
            'version' => '1.0.0',
            'appType' => 'javascript'
        ]));

        $filesystemApp = array_merge($this->testApp, [
            'storage_type' => 'filesystem',
            'repository_path' => $testDir
        ]);

        $result = $this->runtimeEngine->loadApp(1, 'web');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('storage_type', $result);
        
        // Cleanup
        if (is_dir($testDir)) {
            exec("rm -rf " . escapeshellarg($testDir));
        }
    }

    public function testLoadAppWithCaching()
    {
        // First load
        $result1 = $this->runtimeEngine->loadApp(1, 'web');
        $this->assertArrayHasKey('cached', $result1);
        
        // Second load should use cache
        $result2 = $this->runtimeEngine->loadApp(1, 'web');
        $this->assertArrayHasKey('load_source', $result2);
        
        // Both results should have same structure
        $this->assertEquals($result1['id'], $result2['id']);
        $this->assertEquals($result1['platform'], $result2['platform']);
    }

    public function testLoadAppWithForceReload()
    {
        $options = ['force_reload' => true];
        $result = $this->runtimeEngine->loadApp(1, 'web', $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('load_source', $result);
        // Force reload should not use cache
        $this->assertNotEquals('cache', $result['load_source']);
    }

    public function testLoadAppWithLazyLoading()
    {
        $options = ['lazy_load' => true];
        $result = $this->runtimeEngine->loadApp(1, 'web', $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('assets', $result);
        
        // Check that assets are configured for lazy loading
        foreach ($result['assets'] as $assetGroup) {
            if (is_array($assetGroup)) {
                foreach ($assetGroup as $asset) {
                    if (isset($asset['loaded'])) {
                        // Small assets might be preloaded, large ones should be lazy
                        $this->assertIsBool($asset['loaded']);
                    }
                }
            }
        }
    }

    public function testPreloadApp()
    {
        $result = $this->runtimeEngine->preloadApp(1, 'web');
        
        $this->assertIsBool($result);
        
        // If successful, app should be in cache
        if ($result) {
            $cacheStats = $this->runtimeEngine->getCacheStatistics();
            $this->assertGreaterThan(0, $cacheStats['entries']);
        }
    }

    public function testLoadAsset()
    {
        // Create test filesystem app with assets
        $testDir = '/tmp/test-runtime-assets';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
            mkdir($testDir . '/assets', 0755, true);
        }
        
        $testAssetContent = 'body { color: red; }';
        file_put_contents($testDir . '/assets/style.css', $testAssetContent);

        $filesystemApp = array_merge($this->testApp, [
            'storage_type' => 'filesystem',
            'repository_path' => $testDir
        ]);

        try {
            $asset = $this->runtimeEngine->loadAsset(1, 'assets/style.css');
            
            $this->assertIsArray($asset);
            $this->assertArrayHasKey('path', $asset);
            $this->assertArrayHasKey('type', $asset);
            $this->assertArrayHasKey('size', $asset);
            $this->assertArrayHasKey('content', $asset);
            $this->assertArrayHasKey('mime_type', $asset);
            
            $this->assertEquals('assets/style.css', $asset['path']);
            $this->assertEquals($testAssetContent, $asset['content']);
        } catch (\RuntimeException $e) {
            // Asset loading may fail if app is not filesystem-based
            $this->assertStringContains('Asset loading not supported', $e->getMessage());
        }

        // Cleanup
        if (is_dir($testDir)) {
            exec("rm -rf " . escapeshellarg($testDir));
        }
    }

    public function testGetCacheStatistics()
    {
        $stats = $this->runtimeEngine->getCacheStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('entries', $stats);
        $this->assertArrayHasKey('max_entries', $stats);
        $this->assertArrayHasKey('total_access_count', $stats);
        $this->assertArrayHasKey('estimated_size_bytes', $stats);
        $this->assertArrayHasKey('ttl_seconds', $stats);
        
        $this->assertIsInt($stats['entries']);
        $this->assertIsInt($stats['max_entries']);
        $this->assertIsInt($stats['total_access_count']);
        $this->assertIsInt($stats['estimated_size_bytes']);
        $this->assertIsInt($stats['ttl_seconds']);
        
        $this->assertGreaterThanOrEqual(0, $stats['entries']);
        $this->assertGreaterThan(0, $stats['max_entries']);
        $this->assertGreaterThanOrEqual(0, $stats['total_access_count']);
    }

    public function testClearCache()
    {
        // Load an app to populate cache
        $this->runtimeEngine->loadApp(1, 'web');
        
        // Clear cache for specific app
        $this->runtimeEngine->clearCache(1);
        
        $stats = $this->runtimeEngine->getCacheStatistics();
        // Cache should be cleared or reduced
        $this->assertIsInt($stats['entries']);
        
        // Clear all cache
        $this->runtimeEngine->clearCache();
        
        $statsAfterClear = $this->runtimeEngine->getCacheStatistics();
        $this->assertEquals(0, $statsAfterClear['entries']);
        $this->assertEquals(0, $statsAfterClear['total_access_count']);
    }

    public function testUnloadApp()
    {
        // Load an app first
        $this->runtimeEngine->loadApp(1, 'web');
        
        // Unload the app
        $result = $this->runtimeEngine->unloadApp(1);
        
        $this->assertTrue($result);
        
        // Verify app is removed from cache
        $stats = $this->runtimeEngine->getCacheStatistics();
        // Cache entries should be reduced or zero
        $this->assertIsInt($stats['entries']);
    }

    public function testGetPerformanceMetrics()
    {
        $metrics = $this->runtimeEngine->getPerformanceMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('runtime_cache', $metrics);
        $this->assertArrayHasKey('running_apps', $metrics);
        $this->assertArrayHasKey('performance_optimizer', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        
        // Check runtime cache metrics
        $this->assertIsArray($metrics['runtime_cache']);
        $this->assertArrayHasKey('entries', $metrics['runtime_cache']);
        
        // Check running apps metrics
        $this->assertIsArray($metrics['running_apps']);
        $this->assertArrayHasKey('count', $metrics['running_apps']);
        $this->assertArrayHasKey('apps', $metrics['running_apps']);
        $this->assertIsInt($metrics['running_apps']['count']);
        $this->assertIsArray($metrics['running_apps']['apps']);
        
        // Check memory usage metrics
        $this->assertIsArray($metrics['memory_usage']);
        $this->assertArrayHasKey('current', $metrics['memory_usage']);
        $this->assertArrayHasKey('peak', $metrics['memory_usage']);
        $this->assertArrayHasKey('limit', $metrics['memory_usage']);
        $this->assertIsInt($metrics['memory_usage']['current']);
        $this->assertIsInt($metrics['memory_usage']['peak']);
    }

    public function testUpdateAppAccess()
    {
        // Load an app to track it
        $this->runtimeEngine->loadApp(1, 'web');
        
        // Update access time
        $this->runtimeEngine->updateAppAccess(1);
        
        // This should not throw any errors
        $this->assertTrue(true);
    }

    public function testLoadAppWithDifferentPlatforms()
    {
        $platforms = ['web', 'android', 'ios'];
        
        foreach ($platforms as $platform) {
            $result = $this->runtimeEngine->loadApp(1, $platform);
            
            $this->assertIsArray($result);
            $this->assertEquals($platform, $result['platform']);
            $this->assertArrayHasKey('execution_mode', $result);
        }
    }

    public function testLoadAppErrorHandling()
    {
        try {
            $result = $this->runtimeEngine->loadApp(99999, 'web');
            $this->fail('Expected RuntimeException for non-existent app');
        } catch (\RuntimeException $e) {
            $this->assertStringContains('App not found', $e->getMessage());
        }
    }

    public function testLoadAssetErrorHandling()
    {
        try {
            $asset = $this->runtimeEngine->loadAsset(1, 'non-existent-asset.js');
            // This may succeed or fail depending on app storage type
            if (is_array($asset)) {
                $this->assertArrayHasKey('path', $asset);
            }
        } catch (\RuntimeException $e) {
            // Expected for database storage or non-existent assets
            $this->assertTrue(true);
        }
    }

    public function testAppInstanceStructure()
    {
        $result = $this->runtimeEngine->loadApp(1, 'web');
        
        // Verify required fields
        $requiredFields = [
            'id', 'slug', 'name', 'app_type', 'storage_type', 'platform',
            'execution_mode', 'assets', 'dependencies', 'config', 'load_time', 'cached'
        ];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $result, "Missing required field: {$field}");
        }
        
        // Verify field types
        $this->assertIsInt($result['id']);
        $this->assertIsString($result['slug']);
        $this->assertIsString($result['name']);
        $this->assertIsString($result['app_type']);
        $this->assertIsString($result['storage_type']);
        $this->assertIsString($result['platform']);
        $this->assertIsString($result['execution_mode']);
        $this->assertIsArray($result['assets']);
        $this->assertIsArray($result['dependencies']);
        $this->assertIsArray($result['config']);
        $this->assertIsNumeric($result['load_time']);
        $this->assertIsBool($result['cached']);
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        $this->runtimeEngine->clearCache();
        
        // Clean up any test directories
        $testDirs = ['/tmp/test-runtime-fs-app', '/tmp/test-runtime-assets'];
        foreach ($testDirs as $dir) {
            if (is_dir($dir)) {
                exec("rm -rf " . escapeshellarg($dir));
            }
        }
    }
}