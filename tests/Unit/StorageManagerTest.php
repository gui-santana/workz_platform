<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workz\Platform\Core\StorageManager;
use Workz\Platform\Models\General;

/**
 * Unit tests for StorageManager class
 * Tests storage management, migration functionality, and Git integration
 * 
 * Requirements: 4.1, 4.4, 1.4
 */
class StorageManagerTest extends TestCase
{
    private StorageManager $storageManager;
    private $mockGeneralModel;
    private array $testApp;

    protected function setUp(): void
    {
        $this->storageManager = new StorageManager();
        
        // Mock the General model
        $this->mockGeneralModel = $this->createMock(General::class);
        
        // Set up test app data
        $this->testApp = [
            'id' => 1,
            'slug' => 'test-app',
            'tt' => 'Test App',
            'app_type' => 'javascript',
            'storage_type' => 'database',
            'js_code' => 'console.log("Hello World");',
            'dart_code' => '',
            'source_code' => 'console.log("Hello World");',
            'code_size_bytes' => 1024,
            'scopes' => '["profile.read"]',
            'repository_path' => null,
            'last_migration_at' => null,
            'git_branch' => 'main',
            'git_commit_hash' => null
        ];
    }

    public function testGetAppCodeFromDatabase()
    {
        // Test retrieving app code from database storage
        $expected = [
            'js_code' => 'console.log("Hello World");',
            'dart_code' => '',
            'source_code' => 'console.log("Hello World");',
            'storage_type' => 'database'
        ];

        $result = $this->storageManager->getAppCode(1);
        
        $this->assertIsArray($result);
        $this->assertEquals('database', $result['storage_type']);
        $this->assertArrayHasKey('js_code', $result);
        $this->assertArrayHasKey('dart_code', $result);
    }

    public function testGetAppCodeFromFilesystem()
    {
        // Test retrieving app code from filesystem storage
        $filesystemApp = array_merge($this->testApp, [
            'storage_type' => 'filesystem',
            'repository_path' => '/tmp/test-app'
        ]);

        // Create temporary test directory and files
        $testDir = '/tmp/test-app';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
            mkdir($testDir . '/src', 0755, true);
        }
        
        file_put_contents($testDir . '/src/main.js', 'console.log("From filesystem");');

        $result = $this->storageManager->getAppCode(1);
        
        $this->assertIsArray($result);
        $this->assertEquals('filesystem', $result['storage_type']);
        $this->assertArrayHasKey('repository_path', $result);

        // Cleanup
        if (is_dir($testDir)) {
            exec("rm -rf " . escapeshellarg($testDir));
        }
    }

    public function testSaveAppCodeToDatabase()
    {
        $codeData = [
            'js_code' => 'console.log("Updated code");',
            'dart_code' => '',
        ];

        $result = $this->storageManager->saveAppCode(1, $codeData);
        
        $this->assertTrue($result);
    }

    public function testCheckMigrationNeeded()
    {
        // Test migration recommendation for small JavaScript app
        $result = $this->storageManager->checkMigrationNeeded(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('needed', $result);
        $this->assertArrayHasKey('reason', $result);
        
        if ($result['needed']) {
            $this->assertArrayHasKey('from', $result);
            $this->assertArrayHasKey('to', $result);
        }
    }

    public function testGetStorageStatistics()
    {
        $stats = $this->storageManager->getStorageStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('database', $stats);
        $this->assertArrayHasKey('filesystem', $stats);
        $this->assertArrayHasKey('migration_candidates', $stats);
        
        $this->assertArrayHasKey('count', $stats['database']);
        $this->assertArrayHasKey('total_size', $stats['database']);
        $this->assertArrayHasKey('count', $stats['filesystem']);
        $this->assertArrayHasKey('total_size', $stats['filesystem']);
    }

    public function testValidateStorageIntegrity()
    {
        $result = $this->storageManager->validateStorageIntegrity(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('storage_type', $result);
        $this->assertIsBool($result['valid']);
        $this->assertIsArray($result['errors']);
    }

    public function testGetAppMetadata()
    {
        $metadata = $this->storageManager->getAppMetadata(1);
        
        if ($metadata) {
            $this->assertIsArray($metadata);
            $this->assertArrayHasKey('storage_info', $metadata);
            $this->assertArrayHasKey('type', $metadata['storage_info']);
            $this->assertArrayHasKey('size_bytes', $metadata['storage_info']);
            $this->assertArrayHasKey('size_formatted', $metadata['storage_info']);
        } else {
            $this->assertNull($metadata);
        }
    }

    public function testListAppsByStorageType()
    {
        // Test listing all apps
        $allApps = $this->storageManager->listAppsByStorageType();
        $this->assertIsArray($allApps);

        // Test listing database apps only
        $dbApps = $this->storageManager->listAppsByStorageType('database');
        $this->assertIsArray($dbApps);

        // Test listing filesystem apps only
        $fsApps = $this->storageManager->listAppsByStorageType('filesystem');
        $this->assertIsArray($fsApps);

        // Verify storage_info is added to each app
        foreach ($allApps as $app) {
            $this->assertArrayHasKey('storage_info', $app);
            $this->assertArrayHasKey('type', $app['storage_info']);
        }
    }

    public function testMigrateAppToFilesystem()
    {
        $result = $this->storageManager->migrateAppToFilesystem(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
        } else {
            $this->assertArrayHasKey('message', $result);
        }
    }

    public function testMigrateAppToDatabase()
    {
        $result = $this->storageManager->migrateAppToDatabase(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
        } else {
            $this->assertArrayHasKey('message', $result);
        }
    }

    public function testInitializeGitRepository()
    {
        // Create a test filesystem app
        $testDir = '/tmp/test-git-app';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $result = $this->storageManager->initializeGitRepository(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);

        // Cleanup
        if (is_dir($testDir)) {
            exec("rm -rf " . escapeshellarg($testDir));
        }
    }

    public function testCommitChanges()
    {
        $result = $this->storageManager->commitChanges(1, 'Test commit message');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
        }
    }

    public function testSwitchBranch()
    {
        $result = $this->storageManager->switchBranch(1, 'feature-branch', true);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
        }
    }

    public function testGetGitStatus()
    {
        $result = $this->storageManager->getGitStatus(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if ($result['success']) {
            $this->assertArrayHasKey('branch', $result);
            $this->assertArrayHasKey('changes', $result);
            $this->assertArrayHasKey('has_changes', $result);
        }
    }

    public function testListBranches()
    {
        $result = $this->storageManager->listBranches(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if ($result['success']) {
            $this->assertArrayHasKey('branches', $result);
            $this->assertArrayHasKey('current_branch', $result);
            $this->assertIsArray($result['branches']);
        }
    }

    public function testListBackups()
    {
        $backups = $this->storageManager->listBackups(1);
        
        $this->assertIsArray($backups);
        
        foreach ($backups as $backup) {
            $this->assertArrayHasKey('backup_id', $backup);
            $this->assertArrayHasKey('created_at', $backup);
            $this->assertArrayHasKey('storage_type', $backup);
            $this->assertArrayHasKey('size', $backup);
        }
    }

    public function testInitializeFilesystemStorage()
    {
        $appData = [
            'slug' => 'test-init-app',
            'tt' => 'Test Init App',
            'app_type' => 'javascript',
            'scopes' => '[]'
        ];

        $result = $this->storageManager->initializeFilesystemStorage(999, $appData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
        
        if ($result['success']) {
            $this->assertArrayHasKey('repository_path', $result);
        }
    }

    protected function tearDown(): void
    {
        // Clean up any test directories
        $testDirs = ['/tmp/test-app', '/tmp/test-git-app'];
        foreach ($testDirs as $dir) {
            if (is_dir($dir)) {
                exec("rm -rf " . escapeshellarg($dir));
            }
        }
    }
}