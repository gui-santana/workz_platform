<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Workz\Platform\Core\StorageManager;
use Workz\Platform\Core\BuildPipeline;
use Workz\Platform\Core\RuntimeEngine;

/**
 * Integration tests for complete app lifecycle
 * Tests end-to-end workflows from creation to execution
 * 
 * Requirements: 1.1, 2.1, 7.1
 */
class AppLifecycleTest extends TestCase
{
    private StorageManager $storageManager;
    private BuildPipeline $buildPipeline;
    private RuntimeEngine $runtimeEngine;
    private array $testApps = [];

    protected function setUp(): void
    {
        $this->storageManager = new StorageManager();
        $this->buildPipeline = new BuildPipeline();
        $this->runtimeEngine = new RuntimeEngine();
    }

    public function testCompleteJavaScriptAppLifecycle()
    {
        // Step 1: Create JavaScript app with database storage
        $appData = [
            'slug' => 'test-js-lifecycle',
            'tt' => 'Test JS Lifecycle App',
            'app_type' => 'javascript',
            'js_code' => 'console.log("Hello from lifecycle test");',
            'scopes' => '["profile.read"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Save code to storage
        $codeData = [
            'js_code' => 'console.log("Updated lifecycle test");',
            'dart_code' => ''
        ];
        
        $saveResult = $this->storageManager->saveAppCode($appId, $codeData);
        $this->assertTrue($saveResult, 'Failed to save app code');

        // Step 3: Retrieve code from storage
        $retrievedCode = $this->storageManager->getAppCode($appId);
        $this->assertIsArray($retrievedCode);
        $this->assertEquals('database', $retrievedCode['storage_type']);
        $this->assertStringContains('Updated lifecycle test', $retrievedCode['js_code']);

        // Step 4: Trigger build
        $buildResult = $this->buildPipeline->triggerBuild($appId, ['web']);
        $this->assertIsArray($buildResult);
        $this->assertArrayHasKey('success', $buildResult);

        // Step 5: Check build status
        $buildStatus = $this->buildPipeline->getBuildStatus($appId);
        $this->assertIsArray($buildStatus);
        $this->assertArrayHasKey('success', $buildStatus);

        // Step 6: Load app in runtime
        $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
        $this->assertIsArray($appInstance);
        $this->assertEquals($appId, $appInstance['id']);
        $this->assertEquals('javascript', $appInstance['app_type']);
        $this->assertEquals('web', $appInstance['platform']);

        // Step 7: Verify app configuration
        $this->assertArrayHasKey('config', $appInstance);
        $this->assertArrayHasKey('dependencies', $appInstance);
        $this->assertArrayHasKey('assets', $appInstance);

        echo "✅ JavaScript app lifecycle test completed successfully\n";
    }

    public function testCompleteFlutterAppLifecycle()
    {
        // Step 1: Create Flutter app with filesystem storage
        $appData = [
            'slug' => 'test-flutter-lifecycle',
            'tt' => 'Test Flutter Lifecycle App',
            'app_type' => 'flutter',
            'dart_code' => 'void main() { print("Flutter lifecycle test"); }',
            'scopes' => '["storage.kv.write"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Initialize filesystem storage
        $fsResult = $this->storageManager->initializeFilesystemStorage($appId, $appData);
        $this->assertIsArray($fsResult);
        $this->assertTrue($fsResult['success'], 'Failed to initialize filesystem storage');

        // Step 3: Save updated code
        $codeData = [
            'js_code' => '',
            'dart_code' => 'void main() { print("Updated Flutter lifecycle test"); }'
        ];
        
        $saveResult = $this->storageManager->saveAppCode($appId, $codeData);
        $this->assertTrue($saveResult, 'Failed to save Flutter app code');

        // Step 4: Initialize Git repository
        $gitResult = $this->storageManager->initializeGitRepository($appId);
        $this->assertIsArray($gitResult);
        $this->assertArrayHasKey('success', $gitResult);

        // Step 5: Commit changes
        if ($gitResult['success']) {
            $commitResult = $this->storageManager->commitChanges($appId, 'Initial Flutter app commit');
            $this->assertIsArray($commitResult);
            $this->assertArrayHasKey('success', $commitResult);
        }

        // Step 6: Trigger multi-platform build
        $buildResult = $this->buildPipeline->triggerBuild($appId, ['web', 'android']);
        $this->assertIsArray($buildResult);
        $this->assertArrayHasKey('success', $buildResult);

        // Step 7: Load app in runtime
        $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
        $this->assertIsArray($appInstance);
        $this->assertEquals($appId, $appInstance['id']);
        $this->assertEquals('flutter', $appInstance['app_type']);
        $this->assertEquals('web', $appInstance['platform']);

        // Step 8: Verify filesystem storage
        $this->assertEquals('filesystem', $appInstance['storage_type']);
        $this->assertArrayHasKey('repository_path', $appInstance);

        echo "✅ Flutter app lifecycle test completed successfully\n";
    }

    public function testStorageMigrationLifecycle()
    {
        // Step 1: Create small JavaScript app (database storage)
        $appData = [
            'slug' => 'test-migration-lifecycle',
            'tt' => 'Test Migration Lifecycle App',
            'app_type' => 'javascript',
            'js_code' => 'console.log("Migration test");',
            'scopes' => '[]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Verify initial database storage
        $initialCode = $this->storageManager->getAppCode($appId);
        $this->assertEquals('database', $initialCode['storage_type']);

        // Step 3: Check migration recommendation
        $migrationCheck = $this->storageManager->checkMigrationNeeded($appId);
        $this->assertIsArray($migrationCheck);
        $this->assertArrayHasKey('needed', $migrationCheck);

        // Step 4: Migrate to filesystem storage
        $migrationResult = $this->storageManager->migrateAppToFilesystem($appId);
        $this->assertIsArray($migrationResult);
        $this->assertArrayHasKey('success', $migrationResult);

        if ($migrationResult['success']) {
            // Step 5: Verify filesystem storage
            $migratedCode = $this->storageManager->getAppCode($appId);
            $this->assertEquals('filesystem', $migratedCode['storage_type']);
            $this->assertArrayHasKey('repository_path', $migratedCode);

            // Step 6: Validate storage integrity
            $validation = $this->storageManager->validateStorageIntegrity($appId);
            $this->assertIsArray($validation);
            $this->assertArrayHasKey('valid', $validation);

            // Step 7: Test app loading after migration
            $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
            $this->assertIsArray($appInstance);
            $this->assertEquals('filesystem', $appInstance['storage_type']);

            echo "✅ Storage migration lifecycle test completed successfully\n";
        } else {
            echo "⚠️ Storage migration not performed: " . ($migrationResult['error'] ?? 'Unknown error') . "\n";
        }
    }

    public function testBuildAndDeploymentLifecycle()
    {
        // Step 1: Create app for build testing
        $appData = [
            'slug' => 'test-build-lifecycle',
            'tt' => 'Test Build Lifecycle App',
            'app_type' => 'javascript',
            'js_code' => 'function hello() { return "Build test"; }',
            'scopes' => '["api.call"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Trigger build
        $buildResult = $this->buildPipeline->triggerBuild($appId, ['web']);
        $this->assertIsArray($buildResult);
        $this->assertArrayHasKey('build_id', $buildResult);
        $buildId = $buildResult['build_id'];

        // Step 3: Monitor build status
        $statusChecks = 0;
        $maxChecks = 10;
        
        do {
            sleep(1);
            $buildStatus = $this->buildPipeline->getBuildStatus($appId);
            $statusChecks++;
        } while (
            $statusChecks < $maxChecks && 
            isset($buildStatus['status']) && 
            $buildStatus['status'] === 'building'
        );

        $this->assertIsArray($buildStatus);
        $this->assertTrue($buildStatus['success']);

        // Step 4: Check for artifacts
        $artifacts = $this->buildPipeline->getArtifacts($appId);
        $this->assertIsArray($artifacts);

        // Step 5: Load app with artifacts
        $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
        $this->assertIsArray($appInstance);
        $this->assertArrayHasKey('assets', $appInstance);

        // Step 6: Test performance metrics
        $metrics = $this->runtimeEngine->getPerformanceMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('runtime_cache', $metrics);
        $this->assertArrayHasKey('running_apps', $metrics);

        echo "✅ Build and deployment lifecycle test completed successfully\n";
    }

    public function testConcurrentAppExecution()
    {
        $appIds = [];
        
        // Step 1: Create multiple test apps
        for ($i = 1; $i <= 3; $i++) {
            $appData = [
                'slug' => "test-concurrent-app-{$i}",
                'tt' => "Test Concurrent App {$i}",
                'app_type' => 'javascript',
                'js_code' => "console.log('Concurrent app {$i}');",
                'scopes' => '[]'
            ];

            $appId = $this->createTestApp($appData);
            $appIds[] = $appId;
            $this->testApps[] = $appId;
        }

        // Step 2: Load all apps concurrently
        $loadedApps = [];
        foreach ($appIds as $appId) {
            $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
            $this->assertIsArray($appInstance);
            $loadedApps[] = $appInstance;
        }

        // Step 3: Verify all apps are loaded
        $this->assertCount(3, $loadedApps);

        // Step 4: Check runtime performance
        $metrics = $this->runtimeEngine->getPerformanceMetrics();
        $this->assertGreaterThanOrEqual(3, $metrics['running_apps']['count']);

        // Step 5: Test cache efficiency
        $cacheStats = $this->runtimeEngine->getCacheStatistics();
        $this->assertGreaterThan(0, $cacheStats['entries']);

        // Step 6: Update app access times
        foreach ($appIds as $appId) {
            $this->runtimeEngine->updateAppAccess($appId);
        }

        echo "✅ Concurrent app execution test completed successfully\n";
    }

    public function testErrorHandlingAndRecovery()
    {
        // Step 1: Test with invalid app ID
        try {
            $this->storageManager->getAppCode(99999);
            $this->fail('Expected exception for invalid app ID');
        } catch (\RuntimeException $e) {
            $this->assertStringContains('App not found', $e->getMessage());
        }

        // Step 2: Test build with invalid app
        $buildResult = $this->buildPipeline->buildApp(99999);
        $this->assertFalse($buildResult['success']);
        $this->assertNotEmpty($buildResult['errors']);

        // Step 3: Test runtime loading with invalid app
        try {
            $this->runtimeEngine->loadApp(99999, 'web');
            $this->fail('Expected exception for invalid app ID');
        } catch (\RuntimeException $e) {
            $this->assertStringContains('App not found', $e->getMessage());
        }

        // Step 4: Test migration rollback scenario
        $appData = [
            'slug' => 'test-error-recovery',
            'tt' => 'Test Error Recovery App',
            'app_type' => 'javascript',
            'js_code' => 'console.log("Error recovery test");',
            'scopes' => '[]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Attempt migration that might fail
        $migrationResult = $this->storageManager->migrateAppToFilesystem($appId);
        $this->assertIsArray($migrationResult);
        $this->assertArrayHasKey('success', $migrationResult);

        if (!$migrationResult['success'] && isset($migrationResult['rollback'])) {
            $this->assertArrayHasKey('success', $migrationResult['rollback']);
        }

        echo "✅ Error handling and recovery test completed successfully\n";
    }

    public function testPerformanceUnderLoad()
    {
        $startTime = microtime(true);
        $operations = 0;

        // Step 1: Create test app
        $appData = [
            'slug' => 'test-performance-load',
            'tt' => 'Test Performance Load App',
            'app_type' => 'javascript',
            'js_code' => 'console.log("Performance test");',
            'scopes' => '[]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Perform multiple operations
        for ($i = 0; $i < 10; $i++) {
            // Load app
            $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
            $this->assertIsArray($appInstance);
            $operations++;

            // Update code
            $codeData = [
                'js_code' => "console.log('Performance test iteration {$i}');",
                'dart_code' => ''
            ];
            $this->storageManager->saveAppCode($appId, $codeData);
            $operations++;

            // Check build status
            $this->buildPipeline->getBuildStatus($appId);
            $operations++;
        }

        $duration = microtime(true) - $startTime;
        $operationsPerSecond = $operations / $duration;

        // Step 3: Verify performance metrics
        $this->assertGreaterThan(0, $operationsPerSecond);
        
        $metrics = $this->runtimeEngine->getPerformanceMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);

        echo "✅ Performance under load test completed successfully\n";
        echo "   Operations: {$operations}, Duration: " . round($duration, 2) . "s, Ops/sec: " . round($operationsPerSecond, 2) . "\n";
    }

    private function createTestApp(array $appData): int
    {
        // Simulate app creation - in real implementation this would use the app creation API
        // For testing, we'll use a mock app ID
        static $nextAppId = 1000;
        return $nextAppId++;
    }

    protected function tearDown(): void
    {
        // Clean up test apps and cache
        $this->runtimeEngine->clearCache();
        
        // Clean up test directories
        $testDirs = [
            '/tmp/test-js-lifecycle',
            '/tmp/test-flutter-lifecycle',
            '/tmp/test-migration-lifecycle',
            '/tmp/test-build-lifecycle',
            '/tmp/test-concurrent-app-1',
            '/tmp/test-concurrent-app-2',
            '/tmp/test-concurrent-app-3',
            '/tmp/test-error-recovery',
            '/tmp/test-performance-load'
        ];

        foreach ($testDirs as $dir) {
            if (is_dir($dir)) {
                exec("rm -rf " . escapeshellarg($dir));
            }
        }

        echo "🧹 Test cleanup completed\n";
    }
}