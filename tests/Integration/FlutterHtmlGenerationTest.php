<?php

namespace Tests\Integration;

// Using custom test framework - no PHPUnit needed
use Workz\Platform\Controllers\AppBuilderController;
use Workz\Platform\Core\FlutterCodeAnalyzer;
use Workz\Platform\Core\EnhancedFlutterHtmlGenerator;
use Workz\Platform\Core\FlutterEngineIntegration;

/**
 * Integration tests for Flutter HTML generation
 * Tests end-to-end app creation with custom HTML generation
 * 
 * Requirements: 1.1, 2.1
 */
class FlutterHtmlGenerationTest
{
    private AppBuilderController $controller;
    private FlutterCodeAnalyzer $analyzer;
    private EnhancedFlutterHtmlGenerator $htmlGenerator;
    private FlutterEngineIntegration $engineIntegration;
    private array $testApps;

    protected function setUp(): void
    {
        $this->controller = new AppBuilderController();
        $this->analyzer = new FlutterCodeAnalyzer();
        $this->htmlGenerator = new EnhancedFlutterHtmlGenerator();
        $this->engineIntegration = new FlutterEngineIntegration();
        
        // Set up test app configurations
        $this->testApps = [
            'simple' => [
                'name' => 'Simple Counter App',
                'code' => $this->getSimpleAppCode(),
                'expectedFeatures' => ['counter', 'material_design', 'state_management']
            ],
            'complex' => [
                'name' => 'Complex Business App',
                'code' => $this->getComplexAppCode(),
                'expectedFeatures' => ['navigation', 'animations', 'http_requests', 'charts']
            ],
            'game' => [
                'name' => 'Simple Game App',
                'code' => $this->getGameAppCode(),
                'expectedFeatures' => ['game_engine', 'animations', 'touch_input']
            ]
        ];
    }

    public function testEndToEndAppCreationWithCustomHtml()
    {
        foreach ($this->testApps as $type => $appConfig) {
            // Create app through controller
            $appData = [
                'name' => $appConfig['name'],
                'type' => 'flutter',
                'code' => $appConfig['code']
            ];
            
            $result = $this->controller->createApp($appData);
            
            $this->assertIsArray($result);
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('app_id', $result);
            
            $appId = $result['app_id'];
            
            // Verify HTML was generated
            $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
            $this->assertFileExists($htmlPath);
            
            // Verify HTML content
            $htmlContent = file_get_contents($htmlPath);
            $this->assertNotEmpty($htmlContent);
            
            // Check for Flutter engine integration
            $this->assertStringContains('flutter.js', $htmlContent);
            $this->assertStringContains('main.dart.js', $htmlContent);
            
            // Check for WorkzSDK integration
            $this->assertStringContains('workz-sdk', $htmlContent);
            
            // Check for app-specific customizations
            $this->assertStringContains($appConfig['name'], $htmlContent);
            
            // Verify metadata was extracted and used
            $metadata = $this->analyzer->extractAppMetadata($appConfig['code']);
            $this->assertStringContains($metadata['title'], $htmlContent);
            $this->assertStringContains($metadata['theme']['primaryColor'], $htmlContent);
            
            // Clean up
            $this->cleanupTestApp($appId);
        }
    }

    public function testHtmlGenerationWithDifferentThemes()
    {
        $themes = [
            'light' => [
                'code' => $this->getAppWithTheme('light', '#2196F3', true),
                'expectedBg' => '#FFFFFF',
                'expectedText' => '#000000'
            ],
            'dark' => [
                'code' => $this->getAppWithTheme('dark', '#6200EE', false),
                'expectedBg' => '#121212',
                'expectedText' => '#FFFFFF'
            ],
            'custom' => [
                'code' => $this->getAppWithTheme('light', '#FF5722', true, '#F5F5F5'),
                'expectedBg' => '#F5F5F5',
                'expectedText' => '#000000'
            ]
        ];

        foreach ($themes as $themeName => $themeConfig) {
            $appData = [
                'name' => "Theme Test App - {$themeName}",
                'type' => 'flutter',
                'code' => $themeConfig['code']
            ];
            
            $result = $this->controller->createApp($appData);
            $appId = $result['app_id'];
            
            $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
            $htmlContent = file_get_contents($htmlPath);
            
            // Check theme colors are applied
            $this->assertStringContains($themeConfig['expectedBg'], $htmlContent);
            $this->assertStringContains($themeConfig['expectedText'], $htmlContent);
            
            // Check loading screen matches theme
            $this->assertStringContains('loading-screen', $htmlContent);
            
            $this->cleanupTestApp($appId);
        }
    }

    public function testFlutterEngineLoadingInDifferentScenarios()
    {
        $scenarios = [
            'standard' => [
                'code' => $this->getSimpleAppCode(),
                'expectedEngine' => 'canvaskit',
                'expectedFeatures' => ['service_worker', 'pwa_manifest']
            ],
            'game' => [
                'code' => $this->getGameAppCode(),
                'expectedEngine' => 'canvaskit',
                'expectedFeatures' => ['high_performance', 'webgl']
            ],
            'business' => [
                'code' => $this->getComplexAppCode(),
                'expectedEngine' => 'html',
                'expectedFeatures' => ['accessibility', 'seo_friendly']
            ]
        ];

        foreach ($scenarios as $scenarioName => $scenario) {
            $appData = [
                'name' => "Engine Test - {$scenarioName}",
                'type' => 'flutter',
                'code' => $scenario['code']
            ];
            
            $result = $this->controller->createApp($appData);
            $appId = $result['app_id'];
            
            $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
            $htmlContent = file_get_contents($htmlPath);
            
            // Check Flutter engine configuration
            $this->assertStringContains('_flutter.loader.loadEntrypoint', $htmlContent);
            
            // Check for scenario-specific optimizations
            foreach ($scenario['expectedFeatures'] as $feature) {
                switch ($feature) {
                    case 'service_worker':
                        $this->assertStringContains('serviceWorker', $htmlContent);
                        break;
                    case 'pwa_manifest':
                        $this->assertStringContains('manifest.json', $htmlContent);
                        break;
                    case 'high_performance':
                        $this->assertStringContains('renderer: "canvaskit"', $htmlContent);
                        break;
                    case 'accessibility':
                        $this->assertStringContains('aria-', $htmlContent);
                        break;
                }
            }
            
            $this->cleanupTestApp($appId);
        }
    }

    public function testAppUpdateRegeneratesHtml()
    {
        // Create initial app
        $initialCode = $this->getSimpleAppCode();
        $appData = [
            'name' => 'Update Test App',
            'type' => 'flutter',
            'code' => $initialCode
        ];
        
        $result = $this->controller->createApp($appData);
        $appId = $result['app_id'];
        
        $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
        $initialHtml = file_get_contents($htmlPath);
        
        // Update app with new code
        $updatedCode = $this->getUpdatedAppCode();
        $updateResult = $this->controller->updateApp($appId, [
            'code' => $updatedCode
        ]);
        
        $this->assertTrue($updateResult['success']);
        
        // Verify HTML was regenerated
        $updatedHtml = file_get_contents($htmlPath);
        $this->assertNotEquals($initialHtml, $updatedHtml);
        
        // Check new features are reflected
        $this->assertStringContains('Updated Flutter App', $updatedHtml);
        $this->assertStringContains('#4CAF50', $updatedHtml); // New theme color
        
        $this->cleanupTestApp($appId);
    }

    public function testErrorHandlingDuringHtmlGeneration()
    {
        // Test with invalid Dart code
        $invalidCode = $this->getInvalidDartCode();
        $appData = [
            'name' => 'Error Test App',
            'type' => 'flutter',
            'code' => $invalidCode
        ];
        
        $result = $this->controller->createApp($appData);
        
        // Should still create app with fallback HTML
        $this->assertTrue($result['success']);
        $appId = $result['app_id'];
        
        $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
        $this->assertFileExists($htmlPath);
        
        $htmlContent = file_get_contents($htmlPath);
        
        // Should contain error handling and fallback content
        $this->assertStringContains('flutter-error-fallback', $htmlContent);
        $this->assertStringContains('Flutter App', $htmlContent); // Default title
        
        $this->cleanupTestApp($appId);
    }

    public function testWorkzSdkIntegrationInGeneratedHtml()
    {
        $appData = [
            'name' => 'SDK Integration Test',
            'type' => 'flutter',
            'code' => $this->getAppWithSdkUsage()
        ];
        
        $result = $this->controller->createApp($appData);
        $appId = $result['app_id'];
        
        $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
        $htmlContent = file_get_contents($htmlPath);
        
        // Check WorkzSDK integration
        $this->assertStringContains('workz-sdk-v2.js', $htmlContent);
        $this->assertStringContains('window.WorkzSDK', $htmlContent);
        
        // Check SDK initialization
        $this->assertStringContains('WorkzSDK.init', $htmlContent);
        
        // Check app-specific SDK configuration
        $this->assertStringContains("appId: {$appId}", $htmlContent);
        
        $this->cleanupTestApp($appId);
    }

    public function testPerformanceOptimizationsInGeneratedHtml()
    {
        $appData = [
            'name' => 'Performance Test App',
            'type' => 'flutter',
            'code' => $this->getComplexAppCode()
        ];
        
        $result = $this->controller->createApp($appData);
        $appId = $result['app_id'];
        
        $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
        $htmlContent = file_get_contents($htmlPath);
        
        // Check performance optimizations
        $this->assertStringContains('preload', $htmlContent);
        $this->assertStringContains('defer', $htmlContent);
        
        // Check resource hints
        $this->assertStringContains('dns-prefetch', $htmlContent);
        $this->assertStringContains('preconnect', $htmlContent);
        
        // Check compression and caching headers
        $this->assertStringContains('cache-control', $htmlContent);
        
        // Check service worker for caching
        $this->assertStringContains('sw.js', $htmlContent);
        
        $this->cleanupTestApp($appId);
    }

    public function testResponsiveDesignInGeneratedHtml()
    {
        $appData = [
            'name' => 'Responsive Test App',
            'type' => 'flutter',
            'code' => $this->getResponsiveAppCode()
        ];
        
        $result = $this->controller->createApp($appData);
        $appId = $result['app_id'];
        
        $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
        $htmlContent = file_get_contents($htmlPath);
        
        // Check responsive meta tags
        $this->assertStringContains('viewport', $htmlContent);
        $this->assertStringContains('width=device-width', $htmlContent);
        $this->assertStringContains('initial-scale=1', $htmlContent);
        
        // Check responsive CSS
        $this->assertStringContains('@media', $htmlContent);
        
        // Check mobile-specific optimizations
        $this->assertStringContains('touch-action', $htmlContent);
        
        $this->cleanupTestApp($appId);
    }

    private function getSimpleAppCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Simple Counter App',
      theme: ThemeData(
        primarySwatch: Colors.blue,
        brightness: Brightness.light,
        useMaterial3: true,
      ),
      home: CounterPage(),
    );
  }
}

class CounterPage extends StatefulWidget {
  @override
  _CounterPageState createState() => _CounterPageState();
}

class _CounterPageState extends State<CounterPage> {
  int _counter = 0;

  void _incrementCounter() {
    setState(() {
      _counter++;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Counter')),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('Count: \$_counter'),
            ElevatedButton(
              onPressed: _incrementCounter,
              child: Text('Increment'),
            ),
          ],
        ),
      ),
    );
  }
}
DART;
    }

    private function getComplexAppCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:charts_flutter/flutter.dart' as charts;

void main() {
  runApp(BusinessApp());
}

class BusinessApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Complex Business App',
      theme: ThemeData(
        primaryColor: Color(0xFF6200EE),
        brightness: Brightness.dark,
        useMaterial3: false,
      ),
      routes: {
        '/': (context) => DashboardPage(),
        '/analytics': (context) => AnalyticsPage(),
        '/settings': (context) => SettingsPage(),
      },
      initialRoute: '/',
    );
  }
}

class DashboardPage extends StatefulWidget {
  @override
  _DashboardPageState createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> with TickerProviderStateMixin {
  AnimationController _controller;
  List<charts.Series> _chartData = [];

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(duration: Duration(seconds: 2), vsync: this);
    _loadData();
  }

  Future<void> _loadData() async {
    final response = await http.get(Uri.parse('https://api.example.com/data'));
    // Process response and update chart data
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Business Dashboard')),
      body: Column(
        children: [
          Expanded(
            child: charts.BarChart(_chartData),
          ),
          AnimatedContainer(
            duration: Duration(milliseconds: 500),
            height: 100,
            child: ListView.builder(
              itemCount: 10,
              itemBuilder: (context, index) => ListTile(
                title: Text('Item \$index'),
                onTap: () => Navigator.pushNamed(context, '/analytics'),
              ),
            ),
          ),
        ],
      ),
      drawer: Drawer(
        child: ListView(
          children: [
            DrawerHeader(child: Text('Menu')),
            ListTile(title: Text('Dashboard'), onTap: () => Navigator.pop(context)),
            ListTile(title: Text('Analytics'), onTap: () => Navigator.pushNamed(context, '/analytics')),
            ListTile(title: Text('Settings'), onTap: () => Navigator.pushNamed(context, '/settings')),
          ],
        ),
      ),
    );
  }
}

class AnalyticsPage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Analytics')),
      body: Center(child: Text('Analytics Page')),
    );
  }
}

class SettingsPage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Settings')),
      body: Center(child: Text('Settings Page')),
    );
  }
}
DART;
    }

    private function getGameAppCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:flame/game.dart';
import 'package:flame/components.dart';

void main() {
  runApp(GameApp());
}

class GameApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Simple Game App',
      theme: ThemeData(
        primarySwatch: Colors.red,
        brightness: Brightness.dark,
      ),
      home: GameWidget<SimpleGame>(),
    );
  }
}

class SimpleGame extends FlameGame with HasTappables {
  @override
  Future<void> onLoad() async {
    add(Player());
  }

  @override
  bool onTapDown(TapDownInfo info) {
    // Handle tap input
    return true;
  }
}

class Player extends SpriteComponent with Tappable {
  @override
  Future<void> onLoad() async {
    sprite = await Sprite.load('player.png');
    size = Vector2(64, 64);
    position = Vector2(100, 100);
  }

  @override
  bool onTapDown(TapDownInfo info) {
    // Handle player tap
    return true;
  }
}
DART;
    }

    private function getAppWithTheme(string $brightness, string $primaryColor, bool $useMaterial3, string $backgroundColor = null): string
    {
        $bgColor = $backgroundColor !== null ? $backgroundColor : ($brightness === 'dark' ? '#121212' : '#FFFFFF');
        
        return <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(ThemedApp());
}

class ThemedApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Themed App',
      theme: ThemeData(
        primaryColor: Color(0xFF" . ltrim($primaryColor, '#') . "),
        scaffoldBackgroundColor: Color(0xFF" . ltrim($bgColor, '#') . "),
        brightness: Brightness." . $brightness . ",
        useMaterial3: " . ($useMaterial3 ? 'true' : 'false') . ",
      ),
      home: Scaffold(
        appBar: AppBar(title: Text('Themed App')),
        body: Center(child: Text('Hello World')),
      ),
    );
  }
}
DART;
    }

    private function getUpdatedAppCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(UpdatedApp());
}

class UpdatedApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Updated Flutter App',
      theme: ThemeData(
        primarySwatch: Colors.green,
        brightness: Brightness.light,
        useMaterial3: true,
      ),
      home: Scaffold(
        appBar: AppBar(title: Text('Updated App')),
        body: Center(
          child: Column(
            children: [
              Text('This app has been updated!'),
              ElevatedButton(
                onPressed: () {},
                child: Text('New Button'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
DART;
    }

    private function getInvalidDartCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(MyApp());

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Invalid App',
      home: Text('Hello'),
    );
  }
// Missing closing brace
DART;
    }

    private function getAppWithSdkUsage(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:workz_sdk/workz_sdk.dart';

void main() {
  runApp(SdkApp());
}

class SdkApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SDK Integration App',
      home: SdkHomePage(),
    );
  }
}

class SdkHomePage extends StatefulWidget {
  @override
  _SdkHomePageState createState() => _SdkHomePageState();
}

class _SdkHomePageState extends State<SdkHomePage> {
  String _data = '';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    final data = await WorkzSdk.getData('user_preferences');
    setState(() {
      _data = data ?? 'No data';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('SDK App')),
      body: Center(
        child: Column(
          children: [
            Text('Data from SDK: \$_data'),
            ElevatedButton(
              onPressed: () => WorkzSdk.saveData('user_preferences', 'new_value'),
              child: Text('Save Data'),
            ),
          ],
        ),
      ),
    );
  }
}
DART;
    }

    private function getResponsiveAppCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(ResponsiveApp());
}

class ResponsiveApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Responsive App',
      home: ResponsiveHomePage(),
    );
  }
}

class ResponsiveHomePage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Responsive App')),
      body: LayoutBuilder(
        builder: (context, constraints) {
          if (constraints.maxWidth > 600) {
            return Row(
              children: [
                Expanded(flex: 1, child: NavigationPanel()),
                Expanded(flex: 2, child: ContentPanel()),
              ],
            );
          } else {
            return Column(
              children: [
                Expanded(child: ContentPanel()),
                NavigationPanel(),
              ],
            );
          }
        },
      ),
    );
  }
}

class NavigationPanel extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.blue[100],
      child: ListView(
        children: [
          ListTile(title: Text('Home')),
          ListTile(title: Text('About')),
          ListTile(title: Text('Contact')),
        ],
      ),
    );
  }
}

class ContentPanel extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16),
      child: Column(
        children: [
          Text('Main Content'),
          Expanded(
            child: GridView.builder(
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: MediaQuery.of(context).size.width > 600 ? 3 : 2,
              ),
              itemCount: 20,
              itemBuilder: (context, index) => Card(
                child: Center(child: Text('Item \$index')),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
DART;
    }

    private function cleanupTestApp(int $appId): void
    {
        // Clean up test app files
        $appPath = "public/apps/flutter/{$appId}";
        if (is_dir($appPath)) {
            $this->deleteDirectory($appPath);
        }
        
        // Clean up database entries if needed
        // This would depend on your database implementation
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}