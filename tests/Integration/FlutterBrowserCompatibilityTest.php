<?php

namespace Tests\Integration;

// Using custom test framework - no PHPUnit needed
use Workz\Platform\Controllers\AppBuilderController;
use Workz\Platform\Core\FlutterCodeAnalyzer;
use Workz\Platform\Core\EnhancedFlutterHtmlGenerator;

/**
 * Browser compatibility tests for Flutter apps
 * Tests Flutter app loading across different browsers and devices
 * 
 * Requirements: 2.2, 2.3
 */
class FlutterBrowserCompatibilityTest
{
    private AppBuilderController $controller;
    private FlutterCodeAnalyzer $analyzer;
    private EnhancedFlutterHtmlGenerator $htmlGenerator;
    private array $testApps;
    private array $browserConfigs;

    protected function setUp(): void
    {
        $this->controller = new AppBuilderController();
        $this->analyzer = new FlutterCodeAnalyzer();
        $this->htmlGenerator = new EnhancedFlutterHtmlGenerator();
        
        // Set up test apps for different scenarios
        $this->testApps = [
            'simple' => $this->createTestApp('Simple App', $this->getSimpleAppCode()),
            'complex' => $this->createTestApp('Complex App', $this->getComplexAppCode()),
            'game' => $this->createTestApp('Game App', $this->getGameAppCode()),
            'responsive' => $this->createTestApp('Responsive App', $this->getResponsiveAppCode())
        ];
        
        // Browser configurations for testing
        $this->browserConfigs = [
            'chrome_desktop' => [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'viewport' => ['width' => 1920, 'height' => 1080],
                'features' => ['webgl', 'canvas', 'service_worker', 'wasm'],
                'expectedRenderer' => 'canvaskit'
            ],
            'firefox_desktop' => [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
                'viewport' => ['width' => 1920, 'height' => 1080],
                'features' => ['webgl', 'canvas', 'service_worker', 'wasm'],
                'expectedRenderer' => 'canvaskit'
            ],
            'safari_desktop' => [
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
                'viewport' => ['width' => 1920, 'height' => 1080],
                'features' => ['webgl', 'canvas', 'service_worker'],
                'expectedRenderer' => 'canvaskit'
            ],
            'edge_desktop' => [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                'viewport' => ['width' => 1920, 'height' => 1080],
                'features' => ['webgl', 'canvas', 'service_worker', 'wasm'],
                'expectedRenderer' => 'canvaskit'
            ],
            'chrome_mobile' => [
                'userAgent' => 'Mozilla/5.0 (Linux; Android 10; SM-G975F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                'viewport' => ['width' => 375, 'height' => 812],
                'features' => ['webgl', 'canvas', 'touch'],
                'expectedRenderer' => 'html'
            ],
            'safari_mobile' => [
                'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                'viewport' => ['width' => 375, 'height' => 812],
                'features' => ['webgl', 'canvas', 'touch'],
                'expectedRenderer' => 'html'
            ],
            'firefox_mobile' => [
                'userAgent' => 'Mozilla/5.0 (Mobile; rv:120.0) Gecko/120.0 Firefox/120.0',
                'viewport' => ['width' => 375, 'height' => 812],
                'features' => ['webgl', 'canvas', 'touch'],
                'expectedRenderer' => 'html'
            ],
            'old_browser' => [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
                'viewport' => ['width' => 1366, 'height' => 768],
                'features' => ['canvas'],
                'expectedRenderer' => 'html'
            ]
        ];
    }

    protected function tearDown(): void
    {
        // Clean up test apps
        foreach ($this->testApps as $appId) {
            $this->cleanupTestApp($appId);
        }
    }

    public function testFlutterEngineCompatibilityAcrossBrowsers()
    {
        foreach ($this->testApps as $appType => $appId) {
            foreach ($this->browserConfigs as $browserName => $config) {
                $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
                $htmlContent = file_get_contents($htmlPath);
                
                // Simulate browser environment
                $this->simulateBrowserEnvironment($config);
                
                // Check Flutter engine configuration
                $this->assertStringContains('_flutter.loader.loadEntrypoint', $htmlContent);
                
                // Check renderer selection based on browser capabilities
                if (in_array('wasm', $config['features']) && $config['expectedRenderer'] === 'canvaskit') {
                    $this->assertStringContains('renderer: "canvaskit"', $htmlContent);
                } else {
                    $this->assertStringContains('renderer: "html"', $htmlContent);
                }
                
                // Check feature detection
                $this->assertStringContains('flutter-feature-detection', $htmlContent);
                
                // Check fallback mechanisms
                $this->assertStringContains('flutter-fallback', $htmlContent);
                
                // Browser-specific optimizations
                $this->validateBrowserSpecificOptimizations($htmlContent, $browserName, $config);
            }
        }
    }

    public function testResponsiveBehaviorAcrossDevices()
    {
        $responsiveAppId = $this->testApps['responsive'];
        $htmlPath = "public/apps/flutter/{$responsiveAppId}/web/index.html";
        $htmlContent = file_get_contents($htmlPath);
        
        foreach ($this->browserConfigs as $browserName => $config) {
            // Check viewport meta tag
            $this->assertStringContains('name="viewport"', $htmlContent);
            $this->assertStringContains('width=device-width', $htmlContent);
            $this->assertStringContains('initial-scale=1', $htmlContent);
            
            // Check responsive CSS
            $this->assertStringContains('@media', $htmlContent);
            
            // Device-specific optimizations
            if (strpos($browserName, 'mobile') !== false) {
                // Mobile optimizations
                $this->assertStringContains('touch-action', $htmlContent);
                $this->assertStringContains('user-scalable=no', $htmlContent);
                $this->assertStringContains('mobile-web-app-capable', $htmlContent);
            } else {
                // Desktop optimizations
                $this->assertStringContains('preload', $htmlContent);
                $this->assertStringContains('prefetch', $htmlContent);
            }
            
            // Check loading screen responsiveness
            $this->validateResponsiveLoadingScreen($htmlContent, $config);
        }
    }

    public function testMobileCompatibilityFeatures()
    {
        $mobileConfigs = array_filter($this->browserConfigs, function($key) {
            return strpos($key, 'mobile') !== false;
        }, ARRAY_FILTER_USE_KEY);
        
        foreach ($this->testApps as $appType => $appId) {
            $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
            $htmlContent = file_get_contents($htmlPath);
            
            foreach ($mobileConfigs as $browserName => $config) {
                // Check PWA features
                $this->assertStringContains('manifest.json', $htmlContent);
                $this->assertStringContains('theme-color', $htmlContent);
                
                // Check mobile-specific meta tags
                $this->assertStringContains('mobile-web-app-capable', $htmlContent);
                $this->assertStringContains('apple-mobile-web-app-capable', $htmlContent);
                $this->assertStringContains('apple-mobile-web-app-status-bar-style', $htmlContent);
                
                // Check touch optimizations
                $this->assertStringContains('touch-action: manipulation', $htmlContent);
                
                // Check service worker for offline support
                $this->assertStringContains('serviceWorker', $htmlContent);
                
                // Check mobile performance optimizations
                if ($appType === 'game') {
                    $this->assertStringContains('will-change: transform', $htmlContent);
                }
            }
        }
    }

    public function testOldBrowserFallbacks()
    {
        $oldBrowserConfig = $this->browserConfigs['old_browser'];
        
        foreach ($this->testApps as $appType => $appId) {
            $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
            $htmlContent = file_get_contents($htmlPath);
            
            // Simulate old browser environment
            $this->simulateBrowserEnvironment($oldBrowserConfig);
            
            // Check fallback renderer
            $this->assertStringContains('renderer: "html"', $htmlContent);
            
            // Check polyfills
            $this->assertStringContains('polyfill', $htmlContent);
            
            // Check graceful degradation
            $this->assertStringContains('flutter-unsupported-browser', $htmlContent);
            
            // Check alternative loading mechanism
            $this->assertStringContains('noscript', $htmlContent);
            
            // Check error handling for unsupported features
            $this->assertStringContains('feature-not-supported', $htmlContent);
        }
    }

    public function testCrossOriginResourceSharing()
    {
        foreach ($this->testApps as $appType => $appId) {
            $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
            $htmlContent = file_get_contents($htmlPath);
            
            // Check CORS headers
            $this->assertStringContains('crossorigin="anonymous"', $htmlContent);
            
            // Check resource integrity
            $this->assertStringContains('integrity=', $htmlContent);
            
            // Check CSP headers
            $this->assertStringContains('Content-Security-Policy', $htmlContent);
            
            // Check referrer policy
            $this->assertStringContains('referrerpolicy', $htmlContent);
        }
    }

    public function testAccessibilityFeatures()
    {
        foreach ($this->testApps as $appType => $appId) {
            $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
            $htmlContent = file_get_contents($htmlPath);
            
            // Check ARIA attributes
            $this->assertStringContains('aria-label', $htmlContent);
            $this->assertStringContains('role=', $htmlContent);
            
            // Check semantic HTML
            $this->assertStringContains('<main', $htmlContent);
            $this->assertStringContains('lang=', $htmlContent);
            
            // Check screen reader support
            $this->assertStringContains('sr-only', $htmlContent);
            
            // Check keyboard navigation
            $this->assertStringContains('tabindex', $htmlContent);
            
            // Check high contrast support
            $this->assertStringContains('prefers-contrast', $htmlContent);
            
            // Check reduced motion support
            $this->assertStringContains('prefers-reduced-motion', $htmlContent);
        }
    }

    public function testPerformanceOptimizationsPerBrowser()
    {
        foreach ($this->browserConfigs as $browserName => $config) {
            foreach ($this->testApps as $appType => $appId) {
                $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
                $htmlContent = file_get_contents($htmlPath);
                
                // Check resource hints
                $this->assertStringContains('dns-prefetch', $htmlContent);
                $this->assertStringContains('preconnect', $htmlContent);
                
                // Browser-specific optimizations
                if (strpos($browserName, 'chrome') !== false) {
                    // Chrome-specific optimizations
                    $this->assertStringContains('loading="lazy"', $htmlContent);
                } elseif (strpos($browserName, 'safari') !== false) {
                    // Safari-specific optimizations
                    $this->assertStringContains('webkit-appearance', $htmlContent);
                } elseif (strpos($browserName, 'firefox') !== false) {
                    // Firefox-specific optimizations
                    $this->assertStringContains('moz-appearance', $htmlContent);
                }
                
                // Performance monitoring
                $this->assertStringContains('performance.mark', $htmlContent);
                $this->assertStringContains('performance.measure', $htmlContent);
            }
        }
    }

    public function testErrorHandlingAcrossBrowsers()
    {
        foreach ($this->browserConfigs as $browserName => $config) {
            foreach ($this->testApps as $appType => $appId) {
                $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
                $htmlContent = file_get_contents($htmlPath);
                
                // Check error boundaries
                $this->assertStringContains('flutter-error-boundary', $htmlContent);
                
                // Check error reporting
                $this->assertStringContains('window.onerror', $htmlContent);
                $this->assertStringContains('unhandledrejection', $htmlContent);
                
                // Check browser-specific error handling
                if (strpos($browserName, 'old') !== false) {
                    $this->assertStringContains('unsupported-browser-message', $htmlContent);
                }
                
                // Check retry mechanisms
                $this->assertStringContains('retry-button', $htmlContent);
                
                // Check fallback content
                $this->assertStringContains('flutter-fallback-content', $htmlContent);
            }
        }
    }

    public function testSecurityFeaturesAcrossBrowsers()
    {
        foreach ($this->testApps as $appType => $appId) {
            $htmlPath = "public/apps/flutter/{$appId}/web/index.html";
            $htmlContent = file_get_contents($htmlPath);
            
            // Check Content Security Policy
            $this->assertStringContains("script-src 'self'", $htmlContent);
            $this->assertStringContains("style-src 'self'", $htmlContent);
            
            // Check HTTPS enforcement
            $this->assertStringContains('Strict-Transport-Security', $htmlContent);
            
            // Check XSS protection
            $this->assertStringContains('X-Content-Type-Options', $htmlContent);
            $this->assertStringContains('X-Frame-Options', $htmlContent);
            
            // Check referrer policy
            $this->assertStringContains('Referrer-Policy', $htmlContent);
            
            // Check feature policy
            $this->assertStringContains('Permissions-Policy', $htmlContent);
        }
    }

    private function createTestApp(string $name, string $code): int
    {
        $appData = [
            'name' => $name,
            'type' => 'flutter',
            'code' => $code
        ];
        
        $result = $this->controller->createApp($appData);
        return $result['app_id'];
    }

    private function simulateBrowserEnvironment(array $config): void
    {
        // Simulate browser environment for testing
        $_SERVER['HTTP_USER_AGENT'] = $config['userAgent'];
        
        // Set viewport dimensions
        $_SERVER['HTTP_VIEWPORT_WIDTH'] = $config['viewport']['width'];
        $_SERVER['HTTP_VIEWPORT_HEIGHT'] = $config['viewport']['height'];
        
        // Set supported features
        $_SERVER['HTTP_SUPPORTED_FEATURES'] = implode(',', $config['features']);
    }

    private function validateBrowserSpecificOptimizations(string $htmlContent, string $browserName, array $config): void
    {
        if (strpos($browserName, 'mobile') !== false) {
            // Mobile-specific validations
            $this->assertStringContains('viewport-fit=cover', $htmlContent);
            $this->assertStringContains('minimal-ui', $htmlContent);
        }
        
        if (strpos($browserName, 'safari') !== false) {
            // Safari-specific validations
            $this->assertStringContains('apple-touch-icon', $htmlContent);
            $this->assertStringContains('apple-mobile-web-app-title', $htmlContent);
        }
        
        if (strpos($browserName, 'chrome') !== false) {
            // Chrome-specific validations
            $this->assertStringContains('theme-color', $htmlContent);
        }
        
        if ($config['expectedRenderer'] === 'canvaskit') {
            $this->assertStringContains('canvaskit.wasm', $htmlContent);
        }
    }

    private function validateResponsiveLoadingScreen(string $htmlContent, array $config): void
    {
        // Check loading screen adapts to viewport
        $this->assertStringContains('loading-screen-responsive', $htmlContent);
        
        if ($config['viewport']['width'] < 768) {
            // Mobile loading screen
            $this->assertStringContains('loading-mobile', $htmlContent);
        } else {
            // Desktop loading screen
            $this->assertStringContains('loading-desktop', $htmlContent);
        }
    }

    private function getSimpleAppCode(): string
    {
        return <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(SimpleApp());
}

class SimpleApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Simple App',
      theme: ThemeData(primarySwatch: Colors.blue),
      home: Scaffold(
        appBar: AppBar(title: Text('Simple App')),
        body: Center(child: Text('Hello World')),
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

void main() {
  runApp(ComplexApp());
}

class ComplexApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Complex App',
      theme: ThemeData(
        primaryColor: Color(0xFF6200EE),
        brightness: Brightness.dark,
      ),
      routes: {
        '/': (context) => HomePage(),
        '/details': (context) => DetailsPage(),
      },
    );
  }
}

class HomePage extends StatefulWidget {
  @override
  _HomePageState createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> with TickerProviderStateMixin {
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
      appBar: AppBar(title: Text('Complex App')),
      body: ListView.builder(
        itemCount: _data.length,
        itemBuilder: (context, index) => ListTile(
          title: Text(_data[index]),
          onTap: () => Navigator.pushNamed(context, '/details'),
        ),
      ),
      drawer: Drawer(
        child: ListView(
          children: [
            DrawerHeader(child: Text('Menu')),
            ListTile(title: Text('Home')),
            ListTile(title: Text('Settings')),
          ],
        ),
      ),
    );
  }
}

class DetailsPage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Details')),
      body: Center(child: Text('Details Page')),
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

void main() {
  runApp(GameApp());
}

class GameApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Game App',
      theme: ThemeData(primarySwatch: Colors.red),
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

    private function cleanupTestApp(int $appId): void
    {
        $appPath = "public/apps/flutter/{$appId}";
        if (is_dir($appPath)) {
            $this->deleteDirectory($appPath);
        }
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