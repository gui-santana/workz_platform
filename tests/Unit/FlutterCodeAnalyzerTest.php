<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workz\Platform\Core\FlutterCodeAnalyzer;

/**
 * Unit tests for FlutterCodeAnalyzer class
 * Tests Dart code analysis and metadata extraction
 * 
 * Requirements: 1.2, 3.1
 */
class FlutterCodeAnalyzerTest extends TestCase
{
    private FlutterCodeAnalyzer $analyzer;
    private string $validDartCode;
    private string $complexDartCode;
    private string $invalidDartCode;

    protected function setUp(): void
    {
        $this->analyzer = new FlutterCodeAnalyzer();
        
        // Set up test Dart code samples
        $this->validDartCode = <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Test Flutter App',
      theme: ThemeData(
        primarySwatch: Colors.blue,
        brightness: Brightness.light,
        useMaterial3: true,
      ),
      home: MyHomePage(),
    );
  }
}

class MyHomePage extends StatefulWidget {
  @override
  _MyHomePageState createState() => _MyHomePageState();
}

class _MyHomePageState extends State<MyHomePage> {
  int _counter = 0;

  void _incrementCounter() {
    setState(() {
      _counter++;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Test App'),
      ),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Text('You have pushed the button this many times:'),
            Text(
              '\$_counter',
              style: Theme.of(context).textTheme.headline4,
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _incrementCounter,
        tooltip: 'Increment',
        child: Icon(Icons.add),
      ),
    );
  }
}
DART;

        $this->complexDartCode = <<<DART
import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:provider/provider.dart';
import 'package:http/http.dart' as http;

void main() {
  runApp(ComplexApp());
}

class ComplexApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Complex Flutter App',
      theme: ThemeData(
        primaryColor: Color(0xFF6200EE),
        scaffoldBackgroundColor: Color(0xFFF5F5F5),
        brightness: Brightness.dark,
        useMaterial3: false,
        fontFamily: 'Roboto',
      ),
      routes: {
        '/': (context) => HomePage(),
        '/details': (context) => DetailsPage(),
      },
      initialRoute: '/',
      home: HomePage(),
    );
  }
}

class HomePage extends StatefulWidget {
  @override
  _HomePageState createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> with TickerProviderStateMixin {
  AnimationController _controller;
  Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: Duration(seconds: 2),
      vsync: this,
    );
    _animation = Tween<double>(begin: 0, end: 1).animate(_controller);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Complex App'),
      ),
      body: AnimatedContainer(
        duration: Duration(milliseconds: 500),
        child: ListView(
          children: [
            Card(
              child: ListTile(
                title: Text('Item 1'),
                onTap: () => Navigator.pushNamed(context, '/details'),
              ),
            ),
            Image.asset('assets/images/logo.png'),
            FadeTransition(
              opacity: _animation,
              child: Container(
                height: 200,
                color: Colors.blue,
              ),
            ),
          ],
        ),
      ),
      drawer: Drawer(
        child: ListView(
          children: [
            DrawerHeader(child: Text('Menu')),
            ListTile(title: Text('Home')),
          ],
        ),
      ),
      bottomNavigationBar: BottomNavigationBar(
        items: [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(Icons.settings), label: 'Settings'),
        ],
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

        $this->invalidDartCode = <<<DART
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

    public function testAnalyzeCodeBasicStructure()
    {
        $result = $this->analyzer->analyzeCode($this->validDartCode);
        
        $this->assertIsArray($result);
        
        // Check required keys
        $requiredKeys = [
            'title', 'theme', 'mainWidget', 'dependencies', 'hasNavigation',
            'appType', 'customAssets', 'widgets', 'hasStateManagement',
            'hasAnimations', 'isValid', 'complexity', 'version'
        ];
        
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    public function testExtractAppTitle()
    {
        $result = $this->analyzer->analyzeCode($this->validDartCode);
        
        $this->assertEquals('Test Flutter App', $result['title']);
    }

    public function testExtractAppTitleFromComplexCode()
    {
        $result = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertEquals('Complex Flutter App', $result['title']);
    }

    public function testExtractThemeConfiguration()
    {
        $result = $this->analyzer->analyzeCode($this->validDartCode);
        
        $this->assertIsArray($result['theme']);
        $this->assertArrayHasKey('primaryColor', $result['theme']);
        $this->assertArrayHasKey('brightness', $result['theme']);
        $this->assertArrayHasKey('useMaterial3', $result['theme']);
        
        $this->assertEquals('#2196F3', $result['theme']['primaryColor']); // Colors.blue
        $this->assertEquals('light', $result['theme']['brightness']);
        $this->assertTrue($result['theme']['useMaterial3']);
    }

    public function testExtractComplexThemeConfiguration()
    {
        $result = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertIsArray($result['theme']);
        $this->assertEquals('#6200EE', $result['theme']['primaryColor']);
        $this->assertEquals('#F5F5F5', $result['theme']['backgroundColor']);
        $this->assertEquals('dark', $result['theme']['brightness']);
        $this->assertFalse($result['theme']['useMaterial3']);
        $this->assertEquals('Roboto', $result['theme']['fontFamily']);
    }

    public function testExtractMainWidget()
    {
        $result = $this->analyzer->analyzeCode($this->validDartCode);
        
        $this->assertEquals('MyApp', $result['mainWidget']);
    }

    public function testExtractDependencies()
    {
        $result = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertIsArray($result['dependencies']);
        $this->assertContains('provider', $result['dependencies']);
        $this->assertContains('http', $result['dependencies']);
    }

    public function testDetectNavigation()
    {
        $simpleResult = $this->analyzer->analyzeCode($this->validDartCode);
        $complexResult = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertFalse($simpleResult['hasNavigation']);
        $this->assertTrue($complexResult['hasNavigation']);
    }

    public function testDetermineAppType()
    {
        $result = $this->analyzer->analyzeCode($this->validDartCode);
        
        $this->assertEquals('material', $result['appType']);
    }

    public function testExtractCustomAssets()
    {
        $result = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertIsArray($result['customAssets']);
        $this->assertContains('assets/images/logo.png', $result['customAssets']);
    }

    public function testExtractUsedWidgets()
    {
        $result = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertIsArray($result['widgets']);
        $this->assertContains('MaterialApp', $result['widgets']);
        $this->assertContains('Scaffold', $result['widgets']);
        $this->assertContains('AppBar', $result['widgets']);
        $this->assertContains('FloatingActionButton', $result['widgets']);
        $this->assertContains('BottomNavigationBar', $result['widgets']);
        $this->assertContains('Drawer', $result['widgets']);
        $this->assertContains('ListView', $result['widgets']);
        $this->assertContains('Card', $result['widgets']);
        $this->assertContains('Container', $result['widgets']);
        $this->assertContains('Column', $result['widgets']);
        $this->assertContains('Text', $result['widgets']);
        $this->assertContains('Image', $result['widgets']);
    }

    public function testDetectStateManagement()
    {
        $simpleResult = $this->analyzer->analyzeCode($this->validDartCode);
        $complexResult = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertTrue($simpleResult['hasStateManagement']); // Has StatefulWidget and setState
        $this->assertTrue($complexResult['hasStateManagement']); // Has StatefulWidget and Provider
    }

    public function testDetectAnimations()
    {
        $simpleResult = $this->analyzer->analyzeCode($this->validDartCode);
        $complexResult = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertFalse($simpleResult['hasAnimations']);
        $this->assertTrue($complexResult['hasAnimations']); // Has AnimationController and FadeTransition
    }

    public function testValidateDartSyntax()
    {
        $validResult = $this->analyzer->validateDartSyntax($this->validDartCode);
        $invalidResult = $this->analyzer->validateDartSyntax($this->invalidDartCode);
        
        $this->assertTrue($validResult);
        $this->assertFalse($invalidResult);
    }

    public function testCalculateComplexity()
    {
        $simpleResult = $this->analyzer->analyzeCode($this->validDartCode);
        $complexResult = $this->analyzer->analyzeCode($this->complexDartCode);
        
        $this->assertContains($simpleResult['complexity'], ['low', 'medium', 'high']);
        $this->assertContains($complexResult['complexity'], ['low', 'medium', 'high']);
        
        // Complex code should have higher or equal complexity
        $complexityOrder = ['low' => 1, 'medium' => 2, 'high' => 3];
        $this->assertGreaterThanOrEqual(
            $complexityOrder[$simpleResult['complexity']],
            $complexityOrder[$complexResult['complexity']]
        );
    }

    public function testExtractAppMetadata()
    {
        $result = $this->analyzer->extractAppMetadata($this->validDartCode);
        
        $this->assertIsArray($result);
        
        // Check required keys for HTML generation
        $requiredKeys = ['title', 'theme', 'mainWidget', 'appType', 'hasNavigation', 'loadingConfig', 'performance'];
        
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
        
        // Check theme structure
        $this->assertIsArray($result['theme']);
        $this->assertArrayHasKey('primaryColor', $result['theme']);
        $this->assertArrayHasKey('brightness', $result['theme']);
        $this->assertArrayHasKey('useMaterial3', $result['theme']);
        $this->assertArrayHasKey('backgroundColor', $result['theme']);
        $this->assertArrayHasKey('fontFamily', $result['theme']);
        
        // Check loading config structure
        $this->assertIsArray($result['loadingConfig']);
        $this->assertArrayHasKey('showSpinner', $result['loadingConfig']);
        $this->assertArrayHasKey('backgroundColor', $result['loadingConfig']);
        $this->assertArrayHasKey('textColor', $result['loadingConfig']);
        $this->assertArrayHasKey('message', $result['loadingConfig']);
        
        // Check performance structure
        $this->assertIsArray($result['performance']);
        $this->assertArrayHasKey('complexity', $result['performance']);
        $this->assertArrayHasKey('hasAnimations', $result['performance']);
        $this->assertArrayHasKey('estimatedLoadTime', $result['performance']);
    }

    public function testExtractAppMetadataWithDefaults()
    {
        $minimalCode = <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(MaterialApp(home: Text('Hello')));
}
DART;

        $result = $this->analyzer->extractAppMetadata($minimalCode);
        
        // Should provide defaults for missing values
        $this->assertEquals('Flutter App', $result['title']);
        $this->assertEquals('#2196F3', $result['theme']['primaryColor']);
        $this->assertEquals('light', $result['theme']['brightness']);
        $this->assertTrue($result['theme']['useMaterial3']);
        $this->assertEquals('#FFFFFF', $result['theme']['backgroundColor']);
        $this->assertEquals('Roboto', $result['theme']['fontFamily']);
    }

    public function testLoadingConfigGeneration()
    {
        $result = $this->analyzer->extractAppMetadata($this->validDartCode);
        
        $loadingConfig = $result['loadingConfig'];
        
        $this->assertTrue($loadingConfig['showSpinner']);
        $this->assertEquals('#2196F3', $loadingConfig['backgroundColor']);
        $this->assertEquals('#000000', $loadingConfig['textColor']); // Light theme = black text
        $this->assertStringContains('Test Flutter App', $loadingConfig['message']);
    }

    public function testLoadingConfigForDarkTheme()
    {
        $result = $this->analyzer->extractAppMetadata($this->complexDartCode);
        
        $loadingConfig = $result['loadingConfig'];
        
        $this->assertEquals('#FFFFFF', $loadingConfig['textColor']); // Dark theme = white text
    }

    public function testPerformanceEstimation()
    {
        $simpleResult = $this->analyzer->extractAppMetadata($this->validDartCode);
        $complexResult = $this->analyzer->extractAppMetadata($this->complexDartCode);
        
        $this->assertIsInt($simpleResult['performance']['estimatedLoadTime']);
        $this->assertIsInt($complexResult['performance']['estimatedLoadTime']);
        
        // Complex app should have longer estimated load time
        $this->assertGreaterThanOrEqual(
            $simpleResult['performance']['estimatedLoadTime'],
            $complexResult['performance']['estimatedLoadTime']
        );
        
        // Load times should be reasonable (between 1-10 seconds)
        $this->assertGreaterThan(1000, $simpleResult['performance']['estimatedLoadTime']);
        $this->assertLessThan(10000, $simpleResult['performance']['estimatedLoadTime']);
    }

    public function testColorConversion()
    {
        $codeWithColors = <<<DART
import 'package:flutter/material.dart';

void main() {
  runApp(MaterialApp(
    theme: ThemeData(
      primarySwatch: Colors.red,
      primaryColor: Color(0xFF6200EE),
    ),
    home: Text('Hello'),
  ));
}
DART;

        $result = $this->analyzer->analyzeCode($codeWithColors);
        
        // Should convert Colors.red to hex
        $this->assertEquals('#F44336', $result['theme']['primaryColor']);
    }

    public function testEdgeCases()
    {
        // Empty code
        $emptyResult = $this->analyzer->analyzeCode('');
        $this->assertFalse($emptyResult['isValid']);
        
        // Code without main function
        $noMainCode = <<<DART
import 'package:flutter/material.dart';

class MyApp extends StatelessWidget {
  Widget build(BuildContext context) {
    return MaterialApp(home: Text('Hello'));
  }
}
DART;
        
        $noMainResult = $this->analyzer->analyzeCode($noMainCode);
        $this->assertFalse($noMainResult['isValid']);
        
        // Code without Flutter imports
        $noFlutterCode = <<<DART
void main() {
  print('Hello World');
}
DART;
        
        $noFlutterResult = $this->analyzer->analyzeCode($noFlutterCode);
        $this->assertFalse($noFlutterResult['isValid']);
    }

    public function testCupertinoAppDetection()
    {
        $cupertinoCode = <<<DART
import 'package:flutter/cupertino.dart';

void main() {
  runApp(CupertinoApp(
    title: 'Cupertino App',
    home: CupertinoPageScaffold(
      child: Center(child: Text('Hello iOS')),
    ),
  ));
}
DART;

        $result = $this->analyzer->analyzeCode($cupertinoCode);
        
        $this->assertEquals('cupertino', $result['appType']);
        $this->assertEquals('Cupertino App', $result['title']);
    }

    public function testGameAppDetection()
    {
        $gameCode = <<<DART
import 'package:flutter/material.dart';
import 'package:flame/game.dart';

void main() {
  runApp(MaterialApp(
    home: GameWidget<MyGame>(),
  ));
}

class MyGame extends FlameGame {
  // Game logic here
}
DART;

        $result = $this->analyzer->analyzeCode($gameCode);
        
        $this->assertEquals('game', $result['appType']);
    }

    public function testBusinessAppDetection()
    {
        $businessCode = <<<DART
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:charts_flutter/flutter.dart' as charts;

void main() {
  runApp(MaterialApp(
    home: BusinessDashboard(),
  ));
}

class BusinessDashboard extends StatelessWidget {
  Widget build(BuildContext context) {
    return Scaffold(
      body: charts.BarChart([]),
    );
  }
}
DART;

        $result = $this->analyzer->analyzeCode($businessCode);
        
        $this->assertEquals('business', $result['appType']);
    }
}