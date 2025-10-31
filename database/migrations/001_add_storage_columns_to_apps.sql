-- Migration: Add storage-related columns to apps table
-- Requirements: 4.2, 4.5, 8.3

-- Add storage_type column
ALTER TABLE apps ADD COLUMN storage_type ENUM('database', 'filesystem') DEFAULT 'database' COMMENT 'Storage method for app code';

-- Add repository_path column  
ALTER TABLE apps ADD COLUMN repository_path VARCHAR(255) NULL COMMENT 'Filesystem path for repository-based apps';

-- Add code_size_bytes column
ALTER TABLE apps ADD COLUMN code_size_bytes BIGINT DEFAULT 0 COMMENT 'Size of app code in bytes';

-- Add last_migration_at column
ALTER TABLE apps ADD COLUMN last_migration_at TIMESTAMP NULL COMMENT 'Timestamp of last storage migration';

-- Add git_branch column
ALTER TABLE apps ADD COLUMN git_branch VARCHAR(100) DEFAULT 'main' COMMENT 'Current Git branch for filesystem apps';

-- Add git_commit_hash column
ALTER TABLE apps ADD COLUMN git_commit_hash VARCHAR(40) NULL COMMENT 'Latest Git commit hash';

-- Create indexes for efficient storage type queries
CREATE INDEX idx_apps_storage_type ON apps(storage_type);
CREATE INDEX idx_apps_repository_path ON apps(repository_path);
CREATE INDEX idx_apps_code_size ON apps(code_size_bytes);
CREATE INDEX idx_apps_last_migration ON apps(last_migration_at);