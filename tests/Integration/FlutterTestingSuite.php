<?php

namespace Tests\Integration;

use Workz\Platform\Controllers\AppBuilderController;
use Workz\Platform\Core\FlutterCodeAnalyzer;
use Workz\Platform\Core\EnhancedFlutterHtmlGenerator;

/**
 * Comprehensive testing suite for Flutter Index HTML Sync
 * Tests unit tests, integration tests, and browser compatibility
 * 
 * Requirements: 1.1, 1.2, 2.1, 2.2, 2.3, 3.1
 */
class FlutterTestingSuite
{
    private FlutterCodeAnalyzer $analyzer;
    private EnhancedFlutterHtmlGenerator $htmlGenerator;
    private array $testResults = [];

    public function setUp(): void
    {
        $this->analyzer = new FlutterCodeAnalyzer();
        $this->htmlGenerator = new EnhancedFlutterHtmlGenerator();
    }

    public function testFlutterCodeAnalyzerUnitTests()
    {
        // Test 1: Basic code analysis
        $simpleCode = $this->getSimpleFlutterCode();
        $result = $this->analyzer->analyzeCode($simpleCode);
        
        assertIsArray($result);
        assertTrue($result['isValid']);
        assertEquals('Simple Flutter App', $result['title']);
        assertEquals('material', $result['appType']);
        
        // Test 2: Complex code analysis
        $complexCode = $this->getComplexFlutterCode();
        $result = $this->analyzer->analyzeCode($complexCode);
        
        assertIsArray($result);
        assertTrue($result['isValid']);
        assertEquals('Complex Business App', $result['title']);
        assertTrue($result['hasNavigation']);
        assertTrue($result['hasAnimations']);
        
        // Test 3: Invalid code handling
        $invalidCode = $this->getInvalidFlutterCode();
        $result = $this->analyzer->analyzeCode($invalidCode);
        
        assertIsArray($result);
        assertFalse($result['isValid']);
        
        // Test 4: Metadata extraction
        $metadata = $this->analyzer->extractAppMetadata($simpleCode);
        
        assertIsArray($metadata);
        assertArrayHasKey('title', $metadata);
        assertArrayHasKey('theme', $metadata);
        assertArrayHasKey('loadingConfig', $metadata);
        assertArrayHasKey('performance', $metadata);
        
        $this->testResults['unit_tests'] = 'PASSED';
    }

    public function testHtmlGenerationIntegration()
    {
        // Test 1: Basic HTML generation
        $appId = 1001;
        $metadata = $this->analyzer->extractAppMetadata($this->getSimpleFlutterCode());
        $html = $this->htmlGenerator->generateCustomHtml($appId, $metadata, $this->getSimpleFlutterCode());
        
        assertIsString($html);
        assertContains('Simple Flutter App', $html);
        assertContains('flutter.js', $html);
        assertContains('main.dart.js', $html);
        assertContains('workz-sdk', $html);
        
        // Test 2: Theme integration
        $complexMetadata = $this->analyzer->extractAppMetadata($this->getComplexFlutterCode());
        $complexHtml = $this->htmlGenerator->generateCustomHtml($appId, $complexMetadata, $this->getComplexFlutterCode());
        
        assertIsString($complexHtml);
        assertContains('#6200EE', $complexHtml); // Primary color
        assertContains('dark', $complexHtml); // Theme brightness
        
        // Test 3: Error handling
        $invalidMetadata = ['title' => '', 'theme' => []];
        $fallbackHtml = $this->htmlGenerator->generateCustomHtml($appId, $invalidMetadata, '');
        
        assertIsString($fallbackHtml);
        assertContains('Flutter App', $fallbackHtml); // Default title
        assertContains('flutter-error-fallback', $fallbackHtml);
        
        $this->testResults['integration_tests'] = 'PASSED';
    }

    public function testBrowserCompatibilityFeatures()
    {
        $appId = 1002;
        $metadata = $this->analyzer->extractAppMetadata($this->getResponsiveFlutterCode());
        $html = $this->htmlGenerator->generateCustomHtml($appId, $metadata, $this->getResponsiveFlutterCode());
        
        // Test 1: Responsive design features
        assertContains('viewport', $html);
        assertContains('width=device-width', $html);
        assertContains('initial-scale=1', $html);
        
        // Test 2: PWA features
        assertContains('manifest.json', $html);
        assertContains('theme-color', $html);
        assertContains('serviceWorker', $html);
        
        // Test 3: Accessibility features
        assertContains('aria-label', $html);
        assertContains('role=', $html);
        assertContains('lang=', $html);
        
        // Test 4: Performance optimizations
        assertContains('preload', $html);
        assertContains('dns-prefetch', $html);
        assertContains('preconnect', $html);
        
        // Test 5: Security features
        assertContains('Content-Security-Policy', $html);
        assertContains('crossorigin', $html);
        assertContains('integrity=', $html);
        
        // Test 6: Flutter engine configuration
        assertContains('_flutter.loader.loadEntrypoint', $html);
        assertContains('flutter-feature-detection', $html);
        
        $this->testResults['browser_compatibility'] = 'PASSED';
    }

    public function testMobileCompatibility()
    {
        $appId = 1003;
        $metadata = $this->analyzer->extractAppMetadata($this->getGameFlutterCode());
        $html = $this->htmlGenerator->generateCustomHtml($appId, $metadata, $this->getGameFlutterCode());
        
        // Test mobile-specific features
        assertContains('mobile-web-app-capable', $html);
        assertContains('apple-mobile-web-app-capable', $html);
        assertContains('touch-action', $html);
        assertContains('user-scalable=no', $html);
        
        // Test game-specific optimizations
        assertContains('will-change: transform', $html);
        assertContains('renderer: "canvaskit"', $html);
        
        $this->testResults['mobile_compatibility'] = 'PASSED';
    }

    public function testErrorHandlingAndFallbacks()
    {
        // Test 1: Invalid Dart code
        $invalidCode = $this->getInvalidFlutterCode();
        $result = $this->analyzer->analyzeCode($invalidCode);
        
        assertFalse($result['isValid']);
        assertIsArray($result); // Should still return structured data
        
        // Test 2: Fallback HTML generation
        $appId = 1004;
        $emptyMetadata = [];
        $fallbackHtml = $this->htmlGenerator->generateCustomHtml($appId, $emptyMetadata, $invalidCode);
        
        assertIsString($fallbackHtml);
        assertContains('flutter-error-fallback', $fallbackHtml);
        assertContains('Flutter App', $fallbackHtml); // Default title
        
        // Test 3: Syntax validation
        assertTrue($this->analyzer->validateDartSyntax($this->getSimpleFlutterCode()));
        assertFalse($this->analyzer->validateDartSyntax($invalidCode));
        
        $this->testResults['error_handling'] = 'PASSED';
    }

    public function testPerformanceOptimizations()
    {
        $appId = 1005;
        $metadata = $this->analyzer->extractAppMetadata($this->getComplexFlutterCode());
        $html = $this->htmlGenerator->generateCustomHtml($appId, $metadata, $this->getComplexFlutterCode());
        
        // Test performance features
        assertContains('defer', $html);
        assertContains('async', $html);
        assertContains('loading="lazy"', $html);
        
        // Test caching strategies
        assertContains('cache-control', $html);
        assertContains('sw.js', $html); // Service worker
        
        // Test resource optimization
        assertContains('preload', $html);
        assertContains('prefetch', $html);
        
        // Test performance monitoring
        assertContains('performance.mark', $html);
        assertContains('performance.measure', $html);
        
        $this->testResults['performance_optimizations'] = 'PASSED';
    }

    public function runAllTests()
    {
        echo "Running Flutter Index HTML Sync Comprehensive Test Suite\n";
        echo "========================================================\n\n";
        
        try {
            $this->testFlutterCodeAnalyzerUnitTests();
            echo "✅ Unit Tests: FlutterCodeAnalyzer\n";
        } catch (\Exception $e) {
            echo "❌ Unit Tests: FlutterCodeAnalyzer - " . $e->getMessage() . "\n";
            $this->testResults['unit_tests'] = 'FAILED: ' . $e->getMessage();
        }
        
        try {
            $this->testHtmlGenerationIntegration();
            echo "✅ Integration Tests: HTML Generation\n";
        } catch (\Exception $e) {
            echo "❌ Integration Tests: HTML Generation - " . $e->getMessage() . "\n";
            $this->testResults['integration_tests'] = 'FAILED: ' . $e->getMessage();
        }
        
        try {
            $this->testBrowserCompatibilityFeatures();
            echo "✅ Browser Compatibility Tests\n";
        } catch (\Exception $e) {
            echo "❌ Browser Compatibility Tests - " . $e->getMessage() . "\n";
            $this->testResults['browser_compatibility'] = 'FAILED: ' . $e->getMessage();
        }
        
        try {
            $this->testMobileCompatibility();
            echo "✅ Mobile Compatibility Tests\n";
        } catch (\Exception $e) {
            echo "❌ Mobile Compatibility Tests - " . $e->getMessage() . "\n";
            $this->testResults['mobile_compatibility'] = 'FAILED: ' . $e->getMessage();
        }
        
        try {
            $this->testErrorHandlingAndFallbacks();
            echo "✅ Error Handling and Fallbacks\n";
        } catch (\Exception $e) {
            echo "❌ Error Handling and Fallbacks - " . $e->getMessage() . "\n";
            $this->testResults['error_handling'] = 'FAILED: ' . $e->getMessage();
        }
        
        try {
            $this->testPerformanceOptimizations();
            echo "✅ Performance Optimizations\n";
        } catch (\Exception $e) {
            echo "❌ Performance Optimizations - " . $e->getMessage() . "\n";
            $this->testResults['performance_optimizations'] = 'FAILED: ' . $e->getMessage();
        }
        
        $this->printTestSummary();
    }

    private function printTestSummary()
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->testResults as $testName => $result) {
            $status = strpos($result, 'PASSED') !== false ? '✅' : '❌';
            echo "{$status} " . ucwords(str_replace('_', ' ', $testName)) . ": {$result}\n";
            
            if (strpos($result, 'PASSED') !== false) {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        echo "\nTotal: " . ($passed + $failed) . " test suites\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        
        if ($failed === 0) {
            echo "\n🎉 All test suites passed! Flutter Index HTML Sync is ready.\n";
        } else {
            echo "\n💥 Some test suites failed. Please review the implementation.\n";
        }
    }

    private function getSimpleFlutterCode(): string
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
      title: 'Simple Flutter App',
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

    private function getComplexFlutterCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

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
  List<String> _data = [];

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(duration: Duration(seconds: 2), vsync: this);
    _loadData();
  }

  Future<void> _loadData() async {
    final response = await http.get(Uri.parse('https://api.example.com/data'));
    // Process response
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Business Dashboard')),
      body: AnimatedContainer(
        duration: Duration(milliseconds: 500),
        child: ListView.builder(
          itemCount: _data.length,
          itemBuilder: (context, index) => ListTile(
            title: Text(_data[index]),
            onTap: () => Navigator.pushNamed(context, '/analytics'),
          ),
        ),
      ),
      drawer: Drawer(
        child: ListView(
          children: [
            DrawerHeader(child: Text('Menu')),
            ListTile(title: Text('Dashboard')),
            ListTile(title: Text('Analytics')),
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
DART;
    }

    private function getGameFlutterCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';
import 'package:flame/game.dart';

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

class SimpleGame extends FlameGame {
  @override
  Future<void> onLoad() async {
    // Game initialization
  }
}
DART;
    }

    private function getResponsiveFlutterCode(): string
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
      child: GridView.builder(
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: MediaQuery.of(context).size.width > 600 ? 3 : 2,
        ),
        itemCount: 20,
        itemBuilder: (context, index) => Card(
          child: Center(child: Text('Item \$index')),
        ),
      ),
    );
  }
}
DART;
    }

    private function getInvalidFlutterCode(): string
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
}