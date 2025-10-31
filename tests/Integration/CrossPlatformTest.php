<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Workz\Platform\Core\BuildPipeline;
use Workz\Platform\Core\RuntimeEngine;
use Workz\Platform\Core\StorageManager;

/**
 * Cross-platform integration tests
 * Tests Flutter and JavaScript apps across different platforms
 * 
 * Requirements: 1.1, 2.1, 7.1
 */
class CrossPlatformTest extends TestCase
{
    private BuildPipeline $buildPipeline;
    private RuntimeEngine $runtimeEngine;
    private StorageManager $storageManager;
    private array $testApps = [];

    protected function setUp(): void
    {
        $this->buildPipeline = new BuildPipeline();
        $this->runtimeEngine = new RuntimeEngine();
        $this->storageManager = new StorageManager();
    }

    public function testJavaScriptWebPlatform()
    {
        // Step 1: Create JavaScript app
        $appData = [
            'slug' => 'test-js-web',
            'tt' => 'Test JavaScript Web App',
            'app_type' => 'javascript',
            'js_code' => $this->getJavaScriptTestCode(),
            'scopes' => '["profile.read", "api.call"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Build for web platform
        $buildResult = $this->buildPipeline->buildApp($appId, ['web']);
        $this->assertIsArray($buildResult);
        $this->assertArrayHasKey('success', $buildResult);

        // Step 3: Load app in web runtime
        $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
        $this->assertIsArray($appInstance);
        $this->assertEquals('javascript', $appInstance['app_type']);
        $this->assertEquals('web', $appInstance['platform']);

        // Step 4: Verify web-specific features
        $this->assertArrayHasKey('source_code', $appInstance);
        $this->assertStringContains('WorkzSDK', $appInstance['source_code']);

        // Step 5: Check preload directives for web
        $this->assertArrayHasKey('preload_directives', $appInstance);
        $this->assertIsArray($appInstance['preload_directives']);

        echo "✅ JavaScript web platform test completed\n";
    }

    public function testFlutterWebPlatform()
    {
        // Step 1: Create Flutter app
        $appData = [
            'slug' => 'test-flutter-web',
            'tt' => 'Test Flutter Web App',
            'app_type' => 'flutter',
            'dart_code' => $this->getFlutterTestCode(),
            'scopes' => '["storage.kv.write", "profile.read"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Initialize filesystem storage for Flutter
        $fsResult = $this->storageManager->initializeFilesystemStorage($appId, $appData);
        $this->assertIsArray($fsResult);

        // Step 3: Build for web platform
        $buildResult = $this->buildPipeline->buildApp($appId, ['web']);
        $this->assertIsArray($buildResult);

        // Step 4: Load app in web runtime
        $appInstance = $this->runtimeEngine->loadApp($appId, 'web');
        $this->assertIsArray($appInstance);
        $this->assertEquals('flutter', $appInstance['app_type']);
        $this->assertEquals('web', $appInstance['platform']);

        // Step 5: Verify Flutter web-specific features
        if ($appInstance['storage_type'] === 'filesystem') {
            $this->assertArrayHasKey('repository_path', $appInstance);
        }

        echo "✅ Flutter web platform test completed\n";
    }

    public function testFlutterMobilePlatforms()
    {
        // Step 1: Create Flutter mobile app
        $appData = [
            'slug' => 'test-flutter-mobile',
            'tt' => 'Test Flutter Mobile App',
            'app_type' => 'flutter',
            'dart_code' => $this->getFlutterMobileTestCode(),
            'scopes' => '["storage.kv.write", "device.info"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Test Android platform
        $androidInstance = $this->runtimeEngine->loadApp($appId, 'android');
        $this->assertIsArray($androidInstance);
        $this->assertEquals('flutter', $androidInstance['app_type']);
        $this->assertEquals('android', $androidInstance['platform']);

        // Step 3: Test iOS platform
        $iosInstance = $this->runtimeEngine->loadApp($appId, 'ios');
        $this->assertIsArray($iosInstance);
        $this->assertEquals('flutter', $iosInstance['app_type']);
        $this->assertEquals('ios', $iosInstance['platform']);

        // Step 4: Build for mobile platforms
        $buildResult = $this->buildPipeline->buildApp($appId, ['android', 'ios']);
        $this->assertIsArray($buildResult);

        // Step 5: Verify platform-specific configurations
        $this->assertArrayHasKey('dependencies', $androidInstance);
        $this->assertArrayHasKey('dependencies', $iosInstance);

        echo "✅ Flutter mobile platforms test completed\n";
    }

    public function testFlutterDesktopPlatforms()
    {
        // Step 1: Create Flutter desktop app
        $appData = [
            'slug' => 'test-flutter-desktop',
            'tt' => 'Test Flutter Desktop App',
            'app_type' => 'flutter',
            'dart_code' => $this->getFlutterDesktopTestCode(),
            'scopes' => '["file.system", "window.control"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        $platforms = ['windows', 'macos', 'linux'];

        foreach ($platforms as $platform) {
            // Step 2: Load app for each desktop platform
            $appInstance = $this->runtimeEngine->loadApp($appId, $platform);
            $this->assertIsArray($appInstance);
            $this->assertEquals('flutter', $appInstance['app_type']);
            $this->assertEquals($platform, $appInstance['platform']);

            // Step 3: Verify platform-specific features
            $this->assertArrayHasKey('config', $appInstance);
            $this->assertArrayHasKey('dependencies', $appInstance);
        }

        // Step 4: Build for all desktop platforms
        $buildResult = $this->buildPipeline->buildApp($appId, $platforms);
        $this->assertIsArray($buildResult);

        echo "✅ Flutter desktop platforms test completed\n";
    }

    public function testCrossPlatformSDKConsistency()
    {
        // Step 1: Create apps for SDK testing
        $jsAppData = [
            'slug' => 'test-sdk-js',
            'tt' => 'Test SDK JavaScript App',
            'app_type' => 'javascript',
            'js_code' => $this->getSDKTestJavaScriptCode(),
            'scopes' => '["profile.read", "storage.kv.write"]'
        ];

        $flutterAppData = [
            'slug' => 'test-sdk-flutter',
            'tt' => 'Test SDK Flutter App',
            'app_type' => 'flutter',
            'dart_code' => $this->getSDKTestFlutterCode(),
            'scopes' => '["profile.read", "storage.kv.write"]'
        ];

        $jsAppId = $this->createTestApp($jsAppData);
        $flutterAppId = $this->createTestApp($flutterAppData);
        $this->testApps[] = $jsAppId;
        $this->testApps[] = $flutterAppId;

        // Step 2: Load JavaScript app
        $jsInstance = $this->runtimeEngine->loadApp($jsAppId, 'web');
        $this->assertIsArray($jsInstance);

        // Step 3: Load Flutter web app
        $flutterWebInstance = $this->runtimeEngine->loadApp($flutterAppId, 'web');
        $this->assertIsArray($flutterWebInstance);

        // Step 4: Load Flutter mobile app
        $flutterMobileInstance = $this->runtimeEngine->loadApp($flutterAppId, 'android');
        $this->assertIsArray($flutterMobileInstance);

        // Step 5: Verify SDK consistency across platforms
        $this->verifySDKConsistency($jsInstance, $flutterWebInstance, $flutterMobileInstance);

        echo "✅ Cross-platform SDK consistency test completed\n";
    }

    public function testPerformanceAcrossPlatforms()
    {
        $performanceResults = [];

        // Step 1: Create test app
        $appData = [
            'slug' => 'test-performance-cross',
            'tt' => 'Test Performance Cross Platform',
            'app_type' => 'flutter',
            'dart_code' => $this->getPerformanceTestCode(),
            'scopes' => '[]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        $platforms = ['web', 'android', 'ios'];

        // Step 2: Test performance on each platform
        foreach ($platforms as $platform) {
            $startTime = microtime(true);
            
            $appInstance = $this->runtimeEngine->loadApp($appId, $platform);
            $this->assertIsArray($appInstance);
            
            $loadTime = microtime(true) - $startTime;
            $performanceResults[$platform] = [
                'load_time' => $loadTime,
                'memory_estimate' => $appInstance['memory_estimate'] ?? 0,
                'asset_count' => count($appInstance['assets'] ?? [])
            ];
        }

        // Step 3: Verify performance is reasonable across platforms
        foreach ($performanceResults as $platform => $metrics) {
            $this->assertLessThan(5.0, $metrics['load_time'], "Load time too high for {$platform}");
            $this->assertGreaterThan(0, $metrics['memory_estimate'], "Memory estimate missing for {$platform}");
        }

        // Step 4: Check performance metrics
        $runtimeMetrics = $this->runtimeEngine->getPerformanceMetrics();
        $this->assertIsArray($runtimeMetrics);
        $this->assertArrayHasKey('memory_usage', $runtimeMetrics);

        echo "✅ Cross-platform performance test completed\n";
        foreach ($performanceResults as $platform => $metrics) {
            echo "   {$platform}: " . round($metrics['load_time'] * 1000, 2) . "ms\n";
        }
    }

    public function testBuildArtifactConsistency()
    {
        // Step 1: Create Flutter app for multi-platform build
        $appData = [
            'slug' => 'test-build-consistency',
            'tt' => 'Test Build Consistency App',
            'app_type' => 'flutter',
            'dart_code' => $this->getFlutterTestCode(),
            'scopes' => '["api.call"]'
        ];

        $appId = $this->createTestApp($appData);
        $this->testApps[] = $appId;

        // Step 2: Build for multiple platforms
        $platforms = ['web', 'android'];
        $buildResult = $this->buildPipeline->buildApp($appId, $platforms);
        $this->assertIsArray($buildResult);

        // Step 3: Check artifacts for each platform
        $artifacts = $this->buildPipeline->getArtifacts($appId);
        $this->assertIsArray($artifacts);

        // Step 4: Verify artifact structure consistency
        foreach ($platforms as $platform) {
            if (isset($artifacts[$platform])) {
                $artifact = $artifacts[$platform];
                $this->assertArrayHasKey('path', $artifact);
                $this->assertArrayHasKey('size', $artifact);
                $this->assertArrayHasKey('download_url', $artifact);
            }
        }

        // Step 5: Test artifact paths
        foreach ($platforms as $platform) {
            $artifactPath = $this->buildPipeline->getArtifactPath($appId, $platform);
            if ($artifactPath) {
                $this->assertIsString($artifactPath);
                $this->assertStringContains($platform, $artifactPath);
            }
        }

        echo "✅ Build artifact consistency test completed\n";
    }

    private function verifySDKConsistency($jsInstance, $flutterWebInstance, $flutterMobileInstance)
    {
        // Verify all instances have SDK dependencies
        $instances = [$jsInstance, $flutterWebInstance, $flutterMobileInstance];
        
        foreach ($instances as $instance) {
            $this->assertArrayHasKey('dependencies', $instance);
            $this->assertArrayHasKey('config', $instance);
            
            // Check for WorkzSDK dependency
            $dependencies = $instance['dependencies'];
            $hasWorkzSDK = false;
            
            if (isset($dependencies['workz_sdk'])) {
                $hasWorkzSDK = true;
            } elseif (isset($dependencies['javascript']['workz_sdk'])) {
                $hasWorkzSDK = true;
            } elseif (isset($dependencies['flutter']['workz_sdk'])) {
                $hasWorkzSDK = true;
            }
            
            $this->assertTrue($hasWorkzSDK, 'WorkzSDK dependency not found');
        }
    }

    private function getJavaScriptTestCode(): string
    {
        return <<<JS
// JavaScript test code for cross-platform testing
class TestApp {
    constructor() {
        this.workzSDK = window.WorkzSDK;
    }
    
    async init() {
        await this.workzSDK.init();
        console.log('JavaScript app initialized');
    }
    
    async testFeatures() {
        // Test profile access
        const profile = await this.workzSDK.profile.get();
        console.log('Profile:', profile);
        
        // Test API calls
        const response = await this.workzSDK.api.call('/test');
        console.log('API response:', response);
    }
}

const app = new TestApp();
app.init().then(() => app.testFeatures());
JS;
    }

    private function getFlutterTestCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';

void main() {
  runApp(TestApp());
}

class TestApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Flutter Test App',
      home: TestHomePage(),
    );
  }
}

class TestHomePage extends StatefulWidget {
  @override
  _TestHomePageState createState() => _TestHomePageState();
}

class _TestHomePageState extends State<TestHomePage> {
  @override
  void initState() {
    super.initState();
    _initWorkzSDK();
  }
  
  Future<void> _initWorkzSDK() async {
    await WorkzSDK.init();
    print('Flutter app initialized');
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Flutter Test')),
      body: Center(
        child: Text('Flutter cross-platform test'),
      ),
    );
  }
}
DART;
    }

    private function getFlutterMobileTestCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';

void main() {
  runApp(MobileTestApp());
}

class MobileTestApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Flutter Mobile Test',
      home: MobileHomePage(),
    );
  }
}

class MobileHomePage extends StatefulWidget {
  @override
  _MobileHomePageState createState() => _MobileHomePageState();
}

class _MobileHomePageState extends State<MobileHomePage> {
  @override
  void initState() {
    super.initState();
    _testMobileFeatures();
  }
  
  Future<void> _testMobileFeatures() async {
    await WorkzSDK.init();
    
    // Test device-specific features
    final deviceInfo = await WorkzSDK.device.getInfo();
    print('Device info: \$deviceInfo');
    
    // Test storage
    await WorkzSDK.storage.set('test_key', 'mobile_value');
    final value = await WorkzSDK.storage.get('test_key');
    print('Storage test: \$value');
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Mobile Test')),
      body: Center(
        child: Column(
          children: [
            Text('Flutter Mobile Test'),
            ElevatedButton(
              onPressed: _testMobileFeatures,
              child: Text('Test Mobile Features'),
            ),
          ],
        ),
      ),
    );
  }
}
DART;
    }

    private function getFlutterDesktopTestCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';

void main() {
  runApp(DesktopTestApp());
}

class DesktopTestApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Flutter Desktop Test',
      home: DesktopHomePage(),
    );
  }
}

class DesktopHomePage extends StatefulWidget {
  @override
  _DesktopHomePageState createState() => _DesktopHomePageState();
}

class _DesktopHomePageState extends State<DesktopHomePage> {
  @override
  void initState() {
    super.initState();
    _testDesktopFeatures();
  }
  
  Future<void> _testDesktopFeatures() async {
    await WorkzSDK.init();
    
    // Test desktop-specific features
    print('Desktop app initialized');
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Desktop Test')),
      body: Center(
        child: Text('Flutter Desktop Test'),
      ),
    );
  }
}
DART;
    }

    private function getSDKTestJavaScriptCode(): string
    {
        return <<<JS
// SDK consistency test for JavaScript
async function testSDKFeatures() {
    // Initialize SDK
    await WorkzSDK.init();
    
    // Test profile API
    const profile = await WorkzSDK.profile.get();
    
    // Test storage API
    await WorkzSDK.storage.set('test_key', 'js_value');
    const value = await WorkzSDK.storage.get('test_key');
    
    console.log('JavaScript SDK test completed');
}

testSDKFeatures();
JS;
    }

    private function getSDKTestFlutterCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';

void main() {
  runApp(SDKTestApp());
}

class SDKTestApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: SDKTestPage(),
    );
  }
}

class SDKTestPage extends StatefulWidget {
  @override
  _SDKTestPageState createState() => _SDKTestPageState();
}

class _SDKTestPageState extends State<SDKTestPage> {
  @override
  void initState() {
    super.initState();
    _testSDKFeatures();
  }
  
  Future<void> _testSDKFeatures() async {
    // Initialize SDK
    await WorkzSDK.init();
    
    // Test profile API
    final profile = await WorkzSDK.profile.get();
    
    // Test storage API
    await WorkzSDK.storage.set('test_key', 'flutter_value');
    final value = await WorkzSDK.storage.get('test_key');
    
    print('Flutter SDK test completed');
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(child: Text('SDK Test')),
    );
  }
}
DART;
    }

    private function getPerformanceTestCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(PerformanceTestApp());
}

class PerformanceTestApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: PerformanceTestPage(),
    );
  }
}

class PerformanceTestPage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Performance Test')),
      body: ListView.builder(
        itemCount: 100,
        itemBuilder: (context, index) {
          return ListTile(
            title: Text('Item \$index'),
            subtitle: Text('Performance test item'),
          );
        },
      ),
    );
  }
}
DART;
    }

    private function createTestApp(array $appData): int
    {
        // Simulate app creation - in real implementation this would use the app creation API
        static $nextAppId = 2000;
        return $nextAppId++;
    }

    protected function tearDown(): void
    {
        // Clean up test apps and cache
        $this->runtimeEngine->clearCache();
        
        // Clean up test directories
        $testSlugs = [
            'test-js-web', 'test-flutter-web', 'test-flutter-mobile',
            'test-flutter-desktop', 'test-sdk-js', 'test-sdk-flutter',
            'test-performance-cross', 'test-build-consistency'
        ];

        foreach ($testSlugs as $slug) {
            $testDir = "/tmp/{$slug}";
            if (is_dir($testDir)) {
                exec("rm -rf " . escapeshellarg($testDir));
            }
        }

        echo "🧹 Cross-platform test cleanup completed\n";
    }
}