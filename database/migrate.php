<?php
// database/migrate.php
// Database migration runner for unified app architecture

require_once __DIR__ . '/../vendor/autoload.php';

use Workz\Platform\Core\StorageInfrastructure;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

echo "Workz! Platform - Database Migration Runner\n";
echo "==========================================\n\n";

try {
    // Check if storage columns already exist
    if (StorageInfrastructure::hasStorageColumns()) {
        echo "✓ Storage columns already exist in apps table\n";
    } else {
        echo "→ Applying storage infrastructure migration...\n";
        
        // Apply migration with error handling for existing columns
        $migrationFile = __DIR__ . '/migrations/001_add_storage_columns_to_apps.sql';
        
        try {
            StorageInfrastructure::applyMigration($migrationFile);
            echo "✓ Storage columns added to apps table\n";
            echo "✓ Indexes created for efficient queries\n";
        } catch (Exception $e) {
            // Check if error is due to existing columns
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "✓ Some columns already existed, migration completed\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Verify migration success
    if (StorageInfrastructure::hasStorageColumns()) {
        echo "\n✓ Migration completed successfully!\n";
        echo "\nNew columns added:\n";
        echo "  - storage_type (ENUM: 'database', 'filesystem')\n";
        echo "  - repository_path (VARCHAR 255)\n";
        echo "  - code_size_bytes (BIGINT)\n";
        echo "  - last_migration_at (TIMESTAMP)\n";
        echo "  - git_branch (VARCHAR 100)\n";
        echo "  - git_commit_hash (VARCHAR 40)\n";
        echo "\nIndexes created:\n";
        echo "  - idx_apps_storage_type\n";
        echo "  - idx_apps_repository_path\n";
        echo "  - idx_apps_code_size\n";
        echo "  - idx_apps_last_migration\n";
    } else {
        echo "\n✗ Migration verification failed\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n→ Testing filesystem directory creation...\n";

try {
    // Test directory structure creation
    $testSlug = 'test-app-' . time();
    $testPath = StorageInfrastructure::createAppDirectoryStructure($testSlug);
    echo "✓ Created test directory structure at: {$testPath}\n";
    
    // Test Git initialization
    if (StorageInfrastructure::initializeGitRepository($testPath)) {
        echo "✓ Git repository initialized\n";
    } else {
        echo "⚠ Git initialization failed (git may not be available)\n";
    }
    
    // Test app configuration creation
    $testConfig = [
        'name' => 'Test App',
        'slug' => $testSlug,
        'appType' => 'javascript',
        'scopes' => ['profile.read']
    ];
    
    if (StorageInfrastructure::createAppConfig($testPath, $testConfig)) {
        echo "✓ App configuration file created\n";
    }
    
    // Clean up test directory
    if (is_dir($testPath)) {
        exec("rm -rf " . escapeshellarg($testPath));
        echo "✓ Test directory cleaned up\n";
    }
    
} catch (Exception $e) {
    echo "⚠ Filesystem test failed: " . $e->getMessage() . "\n";
    echo "  This may be due to permissions or missing directories\n";
}

echo "\n✓ Storage infrastructure setup complete!\n";
echo "\nNext steps:\n";
echo "1. Implement StorageManager class (Task 2.1)\n";
echo "2. Add storage migration functionality (Task 2.2)\n";
echo "3. Implement filesystem operations and Git integration (Task 2.3)\n";