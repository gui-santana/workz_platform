<?php
// database/migrate_build_columns.php
// Migration for build-related columns

require_once __DIR__ . '/../vendor/autoload.php';

use Workz\Platform\Core\StorageInfrastructure;

echo "Applying build columns migration...\n";

try {
    StorageInfrastructure::applyMigration(__DIR__ . '/migrations/002_add_build_columns_to_apps.sql');
    echo "✓ Build columns migration applied successfully!\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "✓ Build columns already exist!\n";
    } else {
        echo "✗ Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}