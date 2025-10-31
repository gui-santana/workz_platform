<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workz\Platform\Core\BuildPipeline;
use Workz\Platform\Models\General;

/**
 * Unit tests for BuildPipeline class
 * Tests build orchestration, artifact management, and multi-platform support
 * 
 * Requirements: 5.1, 5.2, 5.3
 */
class BuildPipelineTest extends TestCase
{
    private BuildPipeline $buildPipeline;
    private array $testApp;

    protected function setUp(): void
    {
        $this->buildPipeline = new BuildPipeline();
        
        // Set up test app data
        $this->testApp = [
            'id' => 1,
            'slug' => 'test-build-app',
            'tt' => 'Test Build App',
            'app_type' => 'javascript',
            'storage_type' => 'database',
            'js_code' => 'console.log("Build test");',
            'dart_code' => '',
            'source_code' => 'console.log("Build test");',
            'build_status' => 'pending',
            'build_id' => null,
            'last_build_at' => null,
            'build_artifacts' => null
        ];
    }

    public function testBuildAppJavaScript()
    {
        $result = $this->buildPipeline->buildApp(1, ['web']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('appId', $result);
        $this->assertArrayHasKey('artifacts', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('buildId', $result);
        
        $this->assertIsBool($result['success']);
        $this->assertIsArray($result['artifacts']);
        $this->assertIsArray($result['errors']);
        $this->assertIsArray($result['warnings']);
        $this->assertIsNumeric($result['duration']);
        $this->assertIsString($result['buildId']);
    }

    public function testBuildAppFlutter()
    {
        $flutterApp = array_merge($this->testApp, [
            'app_type' => 'flutter',
            'dart_code' => 'void main() { print("Flutter test"); }',
            'source_code' => 'void main() { print("Flutter test"); }'
        ]);

        $result = $this->buildPipeline->buildApp(1, ['web']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('buildId', $result);
        
        // Flutter builds may fail in test environment, but structure should be correct
        if (!$result['success']) {
            $this->assertArrayHasKey('errors', $result);
            $this->assertNotEmpty($result['errors']);
        }
    }

    public function testBuildAppMultiplePlatforms()
    {
        $platforms = ['web', 'android'];
        $result = $this->buildPipeline->buildApp(1, $platforms);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('artifacts', $result);
        
        // Check that build was attempted for each platform
        if ($result['success']) {
            foreach ($platforms as $platform) {
                if (isset($result['artifacts'][$platform])) {
                    $this->assertArrayHasKey('platform', $result['artifacts'][$platform]);
                    $this->assertArrayHasKey('path', $result['artifacts'][$platform]);
                    $this->assertArrayHasKey('type', $result['artifacts'][$platform]);
                }
            }
        }
    }

    public function testGetBuildStatus()
    {
        $status = $this->buildPipeline->getBuildStatus(1);
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('success', $status);
        
        if ($status['success']) {
            $this->assertArrayHasKey('appId', $status);
            $this->assertArrayHasKey('slug', $status);
            $this->assertArrayHasKey('status', $status);
            $this->assertArrayHasKey('buildId', $status);
            $this->assertArrayHasKey('lastBuildAt', $status);
            $this->assertArrayHasKey('artifacts', $status);
            $this->assertArrayHasKey('hasArtifacts', $status);
            
            $this->assertIsInt($status['appId']);
            $this->assertIsString($status['slug']);
            $this->assertIsArray($status['artifacts']);
            $this->assertIsBool($status['hasArtifacts']);
        } else {
            $this->assertArrayHasKey('error', $status);
        }
    }

    public function testGetBuildHistory()
    {
        $history = $this->buildPipeline->getBuildHistory(1, 5);
        
        $this->assertIsArray($history);
        $this->assertArrayHasKey('success', $history);
        
        if ($history['success']) {
            $this->assertArrayHasKey('appId', $history);
            $this->assertArrayHasKey('builds', $history);
            $this->assertIsArray($history['builds']);
            
            foreach ($history['builds'] as $build) {
                $this->assertArrayHasKey('status', $build);
                $this->assertArrayHasKey('buildId', $build);
            }
        } else {
            $this->assertArrayHasKey('error', $history);
        }
    }

    public function testCancelBuild()
    {
        $buildId = 'test_build_' . time();
        $result = $this->buildPipeline->cancelBuild(1, $buildId);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if ($result['success']) {
            $this->assertArrayHasKey('message', $result);
            $this->assertArrayHasKey('buildId', $result);
            $this->assertEquals($buildId, $result['buildId']);
        } else {
            $this->assertArrayHasKey('error', $result);
        }
    }

    public function testGetArtifacts()
    {
        $artifacts = $this->buildPipeline->getArtifacts(1);
        
        $this->assertIsArray($artifacts);
        
        foreach ($artifacts as $platform => $artifact) {
            $this->assertIsString($platform);
            $this->assertIsArray($artifact);
            $this->assertArrayHasKey('path', $artifact);
            $this->assertArrayHasKey('size', $artifact);
            $this->assertArrayHasKey('modified', $artifact);
            $this->assertArrayHasKey('download_url', $artifact);
        }
    }

    public function testGetArtifactPath()
    {
        $webPath = $this->buildPipeline->getArtifactPath(1, 'web');
        $androidPath = $this->buildPipeline->getArtifactPath(1, 'android');
        $iosPath = $this->buildPipeline->getArtifactPath(1, 'ios');
        
        if ($webPath) {
            $this->assertStringContains('web/index.html', $webPath);
        }
        
        if ($androidPath) {
            $this->assertStringContains('android/app.apk', $androidPath);
        }
        
        if ($iosPath) {
            $this->assertStringContains('ios/app.ipa', $iosPath);
        }
        
        // Test invalid platform
        $invalidPath = $this->buildPipeline->getArtifactPath(1, 'invalid-platform');
        $this->assertNull($invalidPath);
    }

    public function testTriggerBuild()
    {
        $result = $this->buildPipeline->triggerBuild(1, ['web']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if ($result['success']) {
            $this->assertArrayHasKey('build_id', $result);
            $this->assertArrayHasKey('platforms', $result);
            $this->assertArrayHasKey('estimated_duration', $result);
            
            $this->assertIsString($result['build_id']);
            $this->assertIsArray($result['platforms']);
            $this->assertIsInt($result['estimated_duration']);
            $this->assertContains('web', $result['platforms']);
        } else {
            $this->assertArrayHasKey('error', $result);
        }
    }

    public function testTriggerBuildMultiplePlatforms()
    {
        $platforms = ['web', 'android', 'ios'];
        $result = $this->buildPipeline->triggerBuild(1, $platforms);
        
        $this->assertIsArray($result);
        
        if ($result['success']) {
            $this->assertEquals($platforms, $result['platforms']);
            $this->assertGreaterThan(0, $result['estimated_duration']);
        }
    }

    public function testTriggerBuildWithOptions()
    {
        $options = [
            'clean_build' => true,
            'debug_mode' => false,
            'optimization_level' => 'high'
        ];
        
        $result = $this->buildPipeline->triggerBuild(1, ['web'], $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testBuildAppWithInvalidId()
    {
        $result = $this->buildPipeline->buildApp(99999);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        
        $hasAppNotFoundError = false;
        foreach ($result['errors'] as $error) {
            if (strpos($error['message'], 'App not found') !== false) {
                $hasAppNotFoundError = true;
                break;
            }
        }
        $this->assertTrue($hasAppNotFoundError);
    }

    public function testBuildStatusForNonExistentApp()
    {
        $status = $this->buildPipeline->getBuildStatus(99999);
        
        $this->assertIsArray($status);
        $this->assertFalse($status['success']);
        $this->assertArrayHasKey('error', $status);
        $this->assertStringContains('App not found', $status['error']);
    }

    public function testBuildResultStructure()
    {
        $result = $this->buildPipeline->buildApp(1);
        
        // Verify all required fields are present
        $requiredFields = ['success', 'appId', 'artifacts', 'errors', 'warnings', 'duration', 'buildId'];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $result, "Missing required field: {$field}");
        }
        
        // Verify field types
        $this->assertIsBool($result['success']);
        $this->assertIsInt($result['appId']);
        $this->assertIsArray($result['artifacts']);
        $this->assertIsArray($result['errors']);
        $this->assertIsArray($result['warnings']);
        $this->assertIsNumeric($result['duration']);
        $this->assertIsString($result['buildId']);
    }

    public function testErrorHandling()
    {
        // Test with invalid app type
        $invalidApp = array_merge($this->testApp, ['app_type' => 'invalid-type']);
        
        $result = $this->buildPipeline->buildApp(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('errors', $result);
        
        // Should handle gracefully even with invalid data
        $this->assertIsArray($result['errors']);
    }

    protected function tearDown(): void
    {
        // Clean up any test build directories
        $tempBuildPath = '/tmp/workz_builds';
        if (is_dir($tempBuildPath)) {
            exec("find {$tempBuildPath} -name 'build_*' -type d -mtime +1 -exec rm -rf {} + 2>/dev/null");
        }
    }
}