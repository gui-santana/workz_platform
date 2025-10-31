# Database Schema and Storage Infrastructure

This directory contains database migrations and utilities for the Workz! Platform unified app architecture.

## Files

### Migrations
- `migrations/001_add_storage_columns_to_apps.sql` - Adds storage-related columns to the apps table
- `migrate.php` - Migration runner script

### Utilities
- `test_storage_infrastructure.php` - Test script for verifying storage infrastructure

## Database Schema Changes

The following columns have been added to the `apps` table:

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `storage_type` | ENUM('database', 'filesystem') | 'database' | Storage method for app code |
| `repository_path` | VARCHAR(255) | NULL | Filesystem path for repository-based apps |
| `code_size_bytes` | BIGINT | 0 | Size of app code in bytes |
| `last_migration_at` | TIMESTAMP | NULL | Timestamp of last storage migration |
| `git_branch` | VARCHAR(100) | 'main' | Current Git branch for filesystem apps |
| `git_commit_hash` | VARCHAR(40) | NULL | Latest Git commit hash |

### Indexes

The following indexes have been created for efficient queries:

- `idx_apps_storage_type` - Index on storage_type column
- `idx_apps_repository_path` - Index on repository_path column  
- `idx_apps_code_size` - Index on code_size_bytes column
- `idx_apps_last_migration` - Index on last_migration_at column

## Storage Type Determination

The system automatically determines the appropriate storage type based on:

1. **App Type**: Flutter apps always use filesystem storage
2. **Code Size**: Apps exceeding 50KB use filesystem storage
3. **Features**: Apps requiring Git, collaboration, or versioning use filesystem storage
4. **Default**: Small JavaScript apps use database storage

## Directory Structure

Filesystem-based apps use the following directory structure:

```
/apps/[slug]/
├── .git/                    # Git repository
├── src/                     # Source code
│   ├── main.js             # JavaScript entry
│   ├── main.dart           # Flutter entry
│   ├── lib/                # Flutter libraries
│   └── assets/             # Static assets
├── build/                   # Compiled artifacts
│   ├── web/                # Web build
│   ├── android/            # Android APK
│   ├── ios/                # iOS IPA
│   ├── windows/            # Windows executable
│   ├── macos/              # macOS app
│   └── linux/              # Linux executable
├── workz.json              # App configuration
├── .gitignore              # Git ignore rules
└── README.md               # Documentation
```

## Usage

### Running Migrations

```bash
# Run through Docker
docker-compose exec php-fpm php /var/www/html/database/migrate.php

# Or directly if PHP is available
php database/migrate.php
```

### Testing Infrastructure

```bash
# Run tests through Docker
docker-compose exec php-fpm php /var/www/html/database/test_storage_infrastructure.php
```

## Requirements Fulfilled

This implementation fulfills the following requirements:

- **4.2**: Apps table updated with storage-related columns
- **4.5**: Database indexes created for efficient storage type queries  
- **8.3**: Storage type tracking in apps table for routing decisions

## Next Steps

1. Implement StorageManager class (Task 2.1)
2. Add storage migration functionality (Task 2.2)
3. Implement filesystem operations and Git integration (Task 2.3)