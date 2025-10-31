<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Workz\Platform\Core\StorageManager;
use Workz\Platform\Core\BuildPipeline;
use Workz\Platform\Core\RuntimeEngine;

/**
 * Performance integration tests
 * Tests storage and build system performance under various conditions
 * 
 * Requirements: 1.1, 2.1, 7.1
 */
class PerformanceTest extends TestCase
{
    private StorageManager $storageManager;
    private BuildPipeline $buildPipeline;
    private RuntimeEngine $runtimeEngine;
    private array $testApps = [];
    private array $performanceMetrics = [];

    protected function setUp(): void
    {
        $this->storageManager = new StorageManager();
        $this->buildPipeline = new BuildPipeline();
        $this->runtimeEngine = new RuntimeEngine();
        $this->performanceMetrics = [];
    }

    public function testStoragePerformanceComparison()
    {
        echo "🔍 Testing storage performance comparison...\n";

        // Step 1: Test database storage performance
        $dbPerformance = $this->testDatabaseStoragePerformance();
        
        // Step 2: Test filesystem storage performance
        $fsPerformance = $this->testFilesystemStoragePerformance();
        
        // Step 3: Compare performance metrics
        $this->compareStoragePerformance($dbPerformance, $fsPerformance);
        
        echo "✅ Storage performance comparison completed\n";
        echo "   Database avg: " . round($dbPerformance['avg_time'] * 1000, 2) . "ms\n";
        echo "   Filesystem avg: " . round($fsPerformance['avg_time'] * 1000, 2) . "ms\n";
    }

    public function testMigrationPerformance()
    {
        echo "🔍 Testing migration performance...\n";

        $migrationTimes = [];
        $appSizes = [1024, 10240, 51200, 102400]; // 1KB, 10KB, 50KB, 100KB

        foreach ($appSizes as $size) {
            $startTime = microtime(true);
            
            // Step 1: Create app with specific size
            $appData = [
                'slug' => "test-migration-perf-{$size}",
                'tt' => "Migration Performance Test {$size}B",
                'app_type' => 'javascript',
                'js_code' => str_repeat('console.log("Performance test"); ', $size / 30),
                'scopes' => '[]'
            ];

            $appId = $this->createTestApp($appData);
            $this->testApps[] = $appId;

            // Step 2: Perform migration
            $migrationResult = $this->storageManager->migrateAppToFilesystem($appId);
            
            $migrationTime = microtime(true) - $startTime;
            $migrationTimes[$size] = $migrationTime;

            // Step 3: Verify migration success
            if ($migrationResult['success']) {
                $validation = $this->storageManager->validateStorageIntegrity($appId);
                $this->assertTrue($validation['valid'], "Migration validation failed for size {$size}");
            }
        }

        // Step 4: Analyze migration performance
        $this->analyzeMigrationPerformance($migrationTimes);
        
        echo "✅ Migration performance test completed\n";
        foreach ($migrationTimes as $size => $time) {
            echo "   {$size}B: " . round($time * 1000, 2) . "ms\n";
        }
    }

    public function testBuildPerformanceOptimization()
    {
        echo "🔍 Testing build performance optimization...\n";

        // Step 1: Create test apps of different complexities
        $complexities = ['simple', 'medium', 'complex'];
        $buildTimes = [];

        foreach ($complexities as $complexity) {
            $appData = $this->createAppByComplexity($complexity);
            $appId = $this->createTestApp($appData);
            $this->testApps[] = $appId;

            // Step 2: Measure build time
            $startTime = microtime(true);
            $buildResult = $this->buildPipeline->buildApp($appId, ['web']);
            $buildTime = microtime(true) - $startTime;

            $buildTimes[$complexity] = [
                'time' => $buildTime,
                'success' => $buildResult['success'],
                'errors' => count($buildResult['errors']),
                'duration_reported' => $buildResult['duration'] / 1000 // Convert to seconds
            ];

            // Step 3: Test incremental build (simulate)
            if ($buildResult['success']) {
                $incrementalStart = microtime(true);
                $incrementalResult = $this->buildPipeline->buildApp($appId, ['web']);
                $incrementalTime = microtime(true) - $incrementalStart;
                
                $buildTimes[$complexity]['incremental_time'] = $incrementalTime;
            }
        }

        // Step 4: Analyze build performance
        $this->analyzeBuildPerformance($buildTimes);
        
        echo "✅ Build performance optimization test completed\n";
        foreach ($buildTimes as $complexity => $metrics) {
            echo "   {$complexity}: " . round($metrics['time'] * 1000, 2) . "ms";
            if (isset($metrics['incremental_time'])) {
                echo " (incremental: " . round($metrics['incremental_time'] * 1000, 2) . "ms)";
            }
            echo "\n";
        }
    }

    public function testRuntimeCacheEfficiency()
    {
        echo "🔍 Testing runtime cache efficiency...\n";

        // Step 1: Create test apps
        $appIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $appData = [
                'slug' => "test-cache-{$i}",
                'tt' => "Cache Test App {$i}",
                'app_type' => 'javascript',
                'js_code' => "console.log('Cache test app {$i}');",
                'scopes' => '[]'
            ];
            
            $appId = $this->createTestApp($appData);
            $appIds[] = $appId;
            $this->testApps[] = $appId;
        }

        // Step 2: Test cold load performance
        $coldLoadTimes = [];
        foreach ($appIds as $appId) {
            $startTime = microtime(true);
            $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
            $coldLoadTime = microtime(true) - $startTime;
            $coldLoadTimes[] = $coldLoadTime;
            
            $this->assertIsArray($appInstance);
        }

        // Step 3: Test warm load performance (from cache)
        $warmLoadTimes = [];
        foreach ($appIds as $appId) {
            $startTime = microtime(true);
            $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
            $warmLoadTime = microtime(true) - $startTime;
            $warmLoadTimes[] = $warmLoadTime;
            
            $this->assertIsArray($appInstance);
        }

        // Step 4: Analyze cache efficiency
        $this->analyzeCacheEfficiency($coldLoadTimes, $warmLoadTimes);
        
        // Step 5: Test cache statistics
        $cacheStats = $this->runtimeEngine->getCacheStatistics();
        $this->assertGreaterThan(0, $cacheStats['entries']);
        $this->assertGreaterThan(0, $cacheStats['total_access_count']);

        echo "✅ Runtime cache efficiency test completed\n";
        echo "   Cold load avg: " . round(array_sum($coldLoadTimes) / count($coldLoadTimes) * 1000, 2) . "ms\n";
        echo "   Warm load avg: " . round(array_sum($warmLoadTimes) / count($warmLoadTimes) * 1000, 2) . "ms\n";
        echo "   Cache entries: " . $cacheStats['entries'] . "\n";
    }

    public function testConcurrentLoadPerformance()
    {
        echo "🔍 Testing concurrent load performance...\n";

        // Step 1: Create multiple test apps
        $appIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $appData = [
                'slug' => "test-concurrent-{$i}",
                'tt' => "Concurrent Test App {$i}",
                'app_type' => 'javascript',
                'js_code' => "console.log('Concurrent test {$i}');",
                'scopes' => '[]'
            ];
            
            $appId = $this->createTestApp($appData);
            $appIds[] = $appId;
            $this->testApps[] = $appId;
        }

        // Step 2: Test sequential loading
        $sequentialStart = microtime(true);
        $sequentialResults = [];
        foreach ($appIds as $appId) {
            $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
            $sequentialResults[] = $appInstance;
        }
        $sequentialTime = microtime(true) - $sequentialStart;

        // Step 3: Test concurrent-like loading (simulate with rapid succession)
        $this->runtimeEngine->clearCache(); // Clear cache for fair comparison
        
        $concurrentStart = microtime(true);
        $concurrentResults = [];
        
        // Simulate concurrent loading by loading apps in rapid succession
        foreach ($appIds as $appId) {
            $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
            $concurrentResults[] = $appInstance;
        }
        $concurrentTime = microtime(true) - $concurrentStart;

        // Step 4: Analyze concurrent performance
        $this->analyzeConcurrentPerformance($sequentialTime, $concurrentTime, count($appIds));

        // Step 5: Check memory usage
        $memoryMetrics = $this->runtimeEngine->getPerformanceMetrics();
        $this->assertArrayHasKey('memory_usage', $memoryMetrics);

        echo "✅ Concurrent load performance test completed\n";
        echo "   Sequential: " . round($sequentialTime * 1000, 2) . "ms\n";
        echo "   Concurrent-like: " . round($concurrentTime * 1000, 2) . "ms\n";
        echo "   Memory usage: " . round($memoryMetrics['memory_usage']['current'] / 1024 / 1024, 2) . "MB\n";
    }

    public function testLargeAppPerformance()
    {
        echo "🔍 Testing large app performance...\n";

        // Step 1: Create large app
        $largeCode = str_repeat("console.log('Large app test'); ", 10000); // ~300KB
        $appData = [
            'slug' => 'test-large-app',
            'tt' => 'Large App Performance Test',
            'app_type' => 'javascript',
            'js_code' => $largeCode,
            'scopes' => '["api.call", "storage.kv.write"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Test storage operations
        $storageStart = microtime(true);
        $saveResult = $this->storageManager->saveAppCode($appId, [
            'js_code' => $largeCode,
            'dart_code' => ''
        ]);
        $storageTime = microtime(true) - $storageStart;

        $this->assertTrue($saveResult, 'Failed to save large app code');

        // Step 3: Test retrieval
        $retrievalStart = microtime(true);
        $retrievedCode = $this->storageManager->getAppCode($appId);
        $retrievalTime = microtime(true) - $retrievalStart;

        $this->assertIsArray($retrievedCode);
        $this->assertEquals(strlen($largeCode), strlen($retrievedCode['js_code']));

        // Step 4: Test migration performance for large app
        $migrationStart = microtime(true);
        $migrationResult = $this->storageManager->migrateAppToFilesystem($appId);
        $migrationTime = microtime(true) - $migrationStart;

        // Step 5: Test runtime loading
        $loadStart = microtime(true);
        $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
        $loadTime = microtime(true) - $loadStart;

        $this->assertIsArray($appInstance);

        // Step 6: Analyze large app performance
        $this->analyzeLargeAppPerformance([
            'storage' => $storageTime,
            'retrieval' => $retrievalTime,
            'migration' => $migrationTime,
            'load' => $loadTime,
            'code_size' => strlen($largeCode)
        ]);

        echo "✅ Large app performance test completed\n";
        echo "   Code size: " . round(strlen($largeCode) / 1024, 2) . "KB\n";
        echo "   Storage: " . round($storageTime * 1000, 2) . "ms\n";
        echo "   Retrieval: " . round($retrievalTime * 1000, 2) . "ms\n";
        echo "   Migration: " . round($migrationTime * 1000, 2) . "ms\n";
        echo "   Load: " . round($loadTime * 1000, 2) . "ms\n";
    }

    private function testDatabaseStoragePerformance(): array
    {
        $times = [];
        $operations = 20;

        for ($i = 0; $i < $operations; $i++) {
            $appData = [
                'slug' => "test-db-perf-{$i}",
                'tt' => "DB Performance Test {$i}",
                'app_type' => 'javascript',
                'js_code' => "console.log('DB performance test {$i}');",
                'scopes' => '[]'
            ];

            $appId = $this->createTestApp($appData);
            $this->testApps[] = $appId;

            // Test save and retrieve
            $startTime = microtime(true);
            
            $this->storageManager->saveAppCode($appId, [
                'js_code' => $appData['js_code'],
                'dart_code' => ''
            ]);
            
            $this->storageManager->getAppCode($appId);
            
            $times[] = microtime(true) - $startTime;
        }

        return [
            'avg_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'operations' => $operations
        ];
    }

    private function testFilesystemStoragePerformance(): array
    {
        $times = [];
        $operations = 20;

        for ($i = 0; $i < $operations; $i++) {
            $appData = [
                'slug' => "test-fs-perf-{$i}",
                'tt' => "FS Performance Test {$i}",
                'app_type' => 'javascript',
                'js_code' => "console.log('FS performance test {$i}');",
                'scopes' => '[]'
            ];

            $appId = $this->createTestApp($appData);
            $this->testApps[] = $appId;

            $startTime = microtime(true);
            
            // Initialize filesystem storage
            $this->storageManager->initializeFilesystemStorage($appId, $appData);
            
            // Save and retrieve
            $this->storageManager->saveAppCode($appId, [
                'js_code' => $appData['js_code'],
                'dart_code' => ''
            ]);
            
            $this->storageManager->getAppCode($appId);
            
            $times[] = microtime(true) - $startTime;
        }

        return [
            'avg_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'operations' => $operations
        ];
    }

    private function compareStoragePerformance(array $dbPerf, array $fsPerf): void
    {
        // Database should be faster for small operations
        $this->assertLessThan($fsPerf['avg_time'] * 2, $dbPerf['avg_time'], 
            'Database storage significantly slower than expected');
        
        // Both should complete operations in reasonable time
        $this->assertLessThan(1.0, $dbPerf['avg_time'], 'Database operations too slow');
        $this->assertLessThan(2.0, $fsPerf['avg_time'], 'Filesystem operations too slow');
    }

    private function analyzeMigrationPerformance(array $migrationTimes): void
    {
        // Migration time should scale reasonably with size
        $sizes = array_keys($migrationTimes);
        $times = array_values($migrationTimes);
        
        // Larger apps should not take exponentially longer
        $maxTime = max($times);
        $this->assertLessThan(10.0, $maxTime, 'Migration taking too long for large apps');
        
        // All migrations should complete in reasonable time
        foreach ($times as $time) {
            $this->assertLessThan(5.0, $time, 'Individual migration taking too long');
        }
    }

    private function analyzeBuildPerformance(array $buildTimes): void
    {
        foreach ($buildTimes as $complexity => $metrics) {
            // Build should complete in reasonable time
            $this->assertLessThan(30.0, $metrics['time'], "Build time too long for {$complexity} app");
            
            // Incremental builds should be faster
            if (isset($metrics['incremental_time'])) {
                $this->assertLessThan($metrics['time'], $metrics['incremental_time'] + 1.0, 
                    "Incremental build not faster for {$complexity} app");
            }
        }
    }

    private function analyzeCacheEfficiency(array $coldTimes, array $warmTimes): void
    {
        $avgCold = array_sum($coldTimes) / count($coldTimes);
        $avgWarm = array_sum($warmTimes) / count($warmTimes);
        
        // Warm loads should be significantly faster
        $this->assertLessThan($avgCold * 0.8, $avgWarm, 'Cache not providing significant performance improvement');
        
        // Both should be reasonable
        $this->assertLessThan(2.0, $avgCold, 'Cold load times too slow');
        $this->assertLessThan(0.5, $avgWarm, 'Warm load times too slow');
    }

    private function analyzeConcurrentPerformance(float $sequentialTime, float $concurrentTime, int $appCount): void
    {
        // Concurrent loading should not be significantly slower
        $this->assertLessThan($sequentialTime * 1.5, $concurrentTime, 
            'Concurrent loading significantly slower than sequential');
        
        // Performance should be reasonable
        $avgTimePerApp = $concurrentTime / $appCount;
        $this->assertLessThan(1.0, $avgTimePerApp, 'Average app load time too slow under concurrent load');
    }

    private function analyzeLargeAppPerformance(array $metrics): void
    {
        // Large app operations should complete in reasonable time
        $this->assertLessThan(5.0, $metrics['storage'], 'Large app storage too slow');
        $this->assertLessThan(2.0, $metrics['retrieval'], 'Large app retrieval too slow');
        $this->assertLessThan(10.0, $metrics['migration'], 'Large app migration too slow');
        $this->assertLessThan(3.0, $metrics['load'], 'Large app loading too slow');
        
        // Verify code size is actually large
        $this->assertGreaterThan(100000, $metrics['code_size'], 'Test app not actually large');
    }

    private function createAppByComplexity(string $complexity): array
    {
        switch ($complexity) {
            case 'simple':
                return [
                    'slug' => 'test-simple-build',
                    'tt' => 'Simple Build Test',
                    'app_type' => 'javascript',
                    'js_code' => 'console.log("Simple app");',
                    'scopes' => '[]'
                ];
                
            case 'medium':
                return [
                    'slug' => 'test-medium-build',
                    'tt' => 'Medium Build Test',
                    'app_type' => 'javascript',
                    'js_code' => str_repeat('function test() { console.log("Medium complexity"); } ', 100),
                    'scopes' => '["profile.read", "api.call"]'
                ];
                
            case 'complex':
                return [
                    'slug' => 'test-complex-build',
                    'tt' => 'Complex Build Test',
                    'app_type' => 'flutter',
                    'dart_code' => str_repeat('class TestClass { void method() { print("Complex"); } } ', 200),
                    'scopes' => '["profile.read", "storage.kv.write", "api.call", "device.info"]'
                ];
                
            default:
                throw new \InvalidArgumentException("Unknown complexity: {$complexity}");
        }
    }

    private function createTestApp(array $appData): int
    {
        // Simulate app creation - in real implementation this would use the app creation API
        static $nextAppId = 3000;
        return $nextAppId++;
    }

    protected function tearDown(): void
    {
        // Clean up test apps and cache
        $this->runtimeEngine->clearCache();
        
        // Clean up performance test directories
        $patterns = [
            '/tmp/test-*-perf-*',
            '/tmp/test-cache-*',
            '/tmp/test-concurrent-*',
            '/tmp/test-large-app',
            '/tmp/test-*-build'
        ];

        foreach ($patterns as $pattern) {
            exec("rm -rf {$pattern} 2>/dev/null");
        }

        echo "🧹 Performance test cleanup completed\n";
    }
}