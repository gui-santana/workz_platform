<?php

/**
 * Simple test runner for Workz Platform unit tests
 * Can be run without PHPUnit if needed
 */

require_once __DIR__ . '/bootstrap.php';

class SimpleTestRunner {
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    
    public function runTests(): void {
        echo "Running Workz Platform Unit Tests\n";
        echo "=================================\n\n";
        
        $testClasses = [
            'Tests\\Unit\\StorageManagerTest',
            'Tests\\Unit\\BuildPipelineTest', 
            'Tests\\Unit\\RuntimeEngineTest',
            'Tests\\Unit\\FlutterCodeAnalyzerTest',
            'Tests\\Integration\\FlutterTestingSuite'
        ];
        
        foreach ($testClasses as $testClass) {
            $this->runTestClass($testClass);
        }
        
        $this->printSummary();
    }
    
    private function runTestClass(string $className): void {
        echo "Running {$className}...\n";
        
        if (!class_exists($className)) {
            echo "  ❌ Class not found: {$className}\n";
            $this->failed++;
            return;
        }
        
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstance();
        
        // Special handling for comprehensive test suite
        if ($className === 'Tests\\Integration\\FlutterTestingSuite') {
            try {
                if ($reflection->hasMethod('setUp')) {
                    $instance->setUp();
                }
                $instance->runAllTests();
                $this->passed++;
            } catch (Throwable $e) {
                echo "  ❌ Comprehensive test suite failed: " . $e->getMessage() . "\n";
                $this->failed++;
            }
            echo "\n";
            return;
        }
        
        // Run setUp if exists
        if ($reflection->hasMethod('setUp')) {
            $instance->setUp();
        }
        
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            if (strpos($method->getName(), 'test') === 0) {
                $this->runTestMethod($instance, $method);
            }
        }
        
        // Run tearDown if exists
        if ($reflection->hasMethod('tearDown')) {
            $instance->tearDown();
        }
        
        echo "\n";
    }
    
    private function runTestMethod(object $instance, ReflectionMethod $method): void {
        $methodName = $method->getName();
        
        try {
            $method->invoke($instance);
            echo "  ✅ {$methodName}\n";
            $this->passed++;
        } catch (Throwable $e) {
            echo "  ❌ {$methodName}: " . $e->getMessage() . "\n";
            $this->failed++;
            $this->failures[] = [
                'method' => get_class($instance) . '::' . $methodName,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
    }
    
    private function printSummary(): void {
        echo "\nTest Summary\n";
        echo "============\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";
        
        if (!empty($this->failures)) {
            echo "\nFailures:\n";
            echo "---------\n";
            foreach ($this->failures as $failure) {
                echo "❌ {$failure['method']}\n";
                echo "   Error: {$failure['error']}\n";
                echo "   File: {$failure['file']}:{$failure['line']}\n\n";
            }
        }
        
        if ($this->failed === 0) {
            echo "\n🎉 All tests passed!\n";
            exit(0);
        } else {
            echo "\n💥 Some tests failed.\n";
            exit(1);
        }
    }
}

// Simple assertion functions for tests
function assertTrue($condition, $message = 'Assertion failed') {
    if (!$condition) {
        throw new Exception($message);
    }
}

function assertFalse($condition, $message = 'Assertion failed') {
    if ($condition) {
        throw new Exception($message);
    }
}

function assertEquals($expected, $actual, $message = 'Values are not equal') {
    if ($expected !== $actual) {
        throw new Exception($message . " Expected: " . var_export($expected, true) . ", Actual: " . var_export($actual, true));
    }
}

function assertNotEquals($expected, $actual, $message = 'Values should not be equal') {
    if ($expected === $actual) {
        throw new Exception($message);
    }
}

function assertNull($value, $message = 'Value is not null') {
    if ($value !== null) {
        throw new Exception($message);
    }
}

function assertNotNull($value, $message = 'Value is null') {
    if ($value === null) {
        throw new Exception($message);
    }
}

function assertIsArray($value, $message = 'Value is not an array') {
    if (!is_array($value)) {
        throw new Exception($message);
    }
}

function assertIsString($value, $message = 'Value is not a string') {
    if (!is_string($value)) {
        throw new Exception($message);
    }
}

function assertIsInt($value, $message = 'Value is not an integer') {
    if (!is_int($value)) {
        throw new Exception($message);
    }
}

function assertIsBool($value, $message = 'Value is not a boolean') {
    if (!is_bool($value)) {
        throw new Exception($message);
    }
}

function assertIsNumeric($value, $message = 'Value is not numeric') {
    if (!is_numeric($value)) {
        throw new Exception($message);
    }
}

function assertArrayHasKey($key, $array, $message = 'Array does not have key') {
    if (!is_array($array) || !array_key_exists($key, $array)) {
        throw new Exception($message . ": {$key}");
    }
}

function assertContains($needle, $haystack, $message = 'Value not found') {
    if (is_array($haystack)) {
        if (!in_array($needle, $haystack)) {
            throw new Exception($message);
        }
    } else {
        if (strpos($haystack, $needle) === false) {
            throw new Exception($message);
        }
    }
}

function assertStringContains($needle, $haystack, $message = 'String not found') {
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message . ": '{$needle}' not found in '{$haystack}'");
    }
}

function assertGreaterThan($expected, $actual, $message = 'Value is not greater') {
    if ($actual <= $expected) {
        throw new Exception($message . " Expected > {$expected}, got {$actual}");
    }
}

function assertGreaterThanOrEqual($expected, $actual, $message = 'Value is not greater or equal') {
    if ($actual < $expected) {
        throw new Exception($message . " Expected >= {$expected}, got {$actual}");
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new SimpleTestRunner();
    $runner->runTests();
}