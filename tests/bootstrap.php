<?php

// Bootstrap file for PHPUnit tests
// Sets up autoloading and test environment

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Define test environment
define('TESTING', true);
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Include Composer autoloader if available
$autoloadPaths = [
    PROJECT_ROOT . '/vendor/autoload.php',
    PROJECT_ROOT . '/../../autoload.php'
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

// Simple autoloader for project classes if Composer not available
spl_autoload_register(function ($className) {
    // Convert namespace to file path
    $className = ltrim($className, '\\');
    $fileName = '';
    $namespace = '';
    
    if ($lastNsPos = strrpos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
    
    // Try different base paths
    $basePaths = [
        PROJECT_ROOT . '/src/',
        PROJECT_ROOT . '/',
        TEST_ROOT . '/'
    ];
    
    foreach ($basePaths as $basePath) {
        $fullPath = $basePath . $fileName;
        if (file_exists($fullPath)) {
            require_once $fullPath;
            return;
        }
    }
});

// Mock database connection for tests
class MockDatabase {
    public function query($sql) {
        return [];
    }
    
    public function prepare($sql) {
        return new MockStatement();
    }
}

class MockStatement {
    public function execute($params = []) {
        return true;
    }
    
    public function fetch() {
        return false;
    }
    
    public function fetchAll() {
        return [];
    }
}

// Set up test database if needed
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'workz_test');
    define('DB_USER', 'test');
    define('DB_PASS', 'test');
}

// Create temporary directories for tests
$tempDirs = [
    '/tmp/workz_test',
    '/tmp/workz_builds',
    '/tmp/workz_backups'
];

foreach ($tempDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Clean up function for tests
function cleanupTestEnvironment() {
    $tempDirs = [
        '/tmp/workz_test',
        '/tmp/test-app',
        '/tmp/test-git-app',
        '/tmp/test-runtime-fs-app',
        '/tmp/test-runtime-assets'
    ];
    
    foreach ($tempDirs as $dir) {
        if (is_dir($dir)) {
            exec("rm -rf " . escapeshellarg($dir));
        }
    }
}

// Register cleanup function
register_shutdown_function('cleanupTestEnvironment');

echo "Test environment initialized\n";