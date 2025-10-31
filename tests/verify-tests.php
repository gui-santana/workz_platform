<?php

/**
 * Simple test verification script
 * Verifies that all test files are properly structured
 */

echo "Verifying Workz Platform Test Suite\n";
echo "===================================\n\n";

$testFiles = [
    'Unit/StorageManagerTest.php',
    'Unit/BuildPipelineTest.php',
    'Unit/RuntimeEngineTest.php',
    'Integration/AppLifecycleTest.php',
    'Integration/CrossPlatformTest.php',
    'Integration/PerformanceTest.php'
];

$totalTests = 0;
$issues = [];

foreach ($testFiles as $testFile) {
    $filePath = __DIR__ . '/' . $testFile;
    
    echo "Checking {$testFile}...\n";
    
    if (!file_exists($filePath)) {
        $issues[] = "❌ File not found: {$testFile}";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Check for class definition
    if (!preg_match('/class\s+\w+Test\s+extends\s+TestCase/', $content)) {
        $issues[] = "❌ Invalid test class structure in {$testFile}";
        continue;
    }
    
    // Count test methods
    $testMethods = preg_match_all('/public\s+function\s+test\w+\s*\(/', $content);
    $totalTests += $testMethods;
    
    echo "  ✅ {$testMethods} test methods found\n";
}

echo "\nCore Classes Verification:\n";
echo "-------------------------\n";

$coreClasses = [
    '../src/Core/MetricsCollector.php',
    '../src/Core/BuildMonitor.php',
    '../src/Core/RuntimeMonitor.php'
];

foreach ($coreClasses as $classFile) {
    $filePath = __DIR__ . '/' . $classFile;
    $className = basename($classFile, '.php');
    
    echo "Checking {$className}...\n";
    
    if (!file_exists($filePath)) {
        $issues[] = "❌ Core class not found: {$className}";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Check for class definition
    if (!preg_match('/class\s+' . $className . '/', $content)) {
        $issues[] = "❌ Invalid class structure in {$className}";
        continue;
    }
    
    // Count public methods
    $publicMethods = preg_match_all('/public\s+function\s+\w+\s*\(/', $content);
    
    echo "  ✅ {$publicMethods} public methods found\n";
}

echo "\nTest Configuration Files:\n";
echo "------------------------\n";

$configFiles = [
    'phpunit.xml',
    'bootstrap.php',
    'run-tests.php'
];

foreach ($configFiles as $configFile) {
    $filePath = __DIR__ . '/' . $configFile;
    
    echo "Checking {$configFile}...\n";
    
    if (!file_exists($filePath)) {
        $issues[] = "❌ Config file not found: {$configFile}";
        continue;
    }
    
    echo "  ✅ File exists\n";
}

echo "\nSummary:\n";
echo "========\n";
echo "Total test methods: {$totalTests}\n";
echo "Test files: " . count($testFiles) . "\n";
echo "Core classes: " . count($coreClasses) . "\n";

if (empty($issues)) {
    echo "\n🎉 All tests and classes are properly structured!\n";
    echo "\nTest Coverage:\n";
    echo "- Unit Tests: StorageManager, BuildPipeline, RuntimeEngine\n";
    echo "- Integration Tests: App Lifecycle, Cross-Platform, Performance\n";
    echo "- Monitoring: MetricsCollector, BuildMonitor, RuntimeMonitor\n";
    echo "\nTo run tests:\n";
    echo "1. Install PHPUnit: composer install\n";
    echo "2. Run tests: ./vendor/bin/phpunit tests/\n";
    echo "3. Or use simple runner: php tests/run-tests.php\n";
    
    exit(0);
} else {
    echo "\n💥 Issues found:\n";
    foreach ($issues as $issue) {
        echo "  {$issue}\n";
    }
    exit(1);
}