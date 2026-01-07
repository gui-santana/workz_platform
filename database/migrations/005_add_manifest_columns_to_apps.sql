-- Migration: Add Workz app manifest storage to apps table
-- Requirements: Manifest-driven app execution (phase 1)

ALTER TABLE apps ADD COLUMN manifest_json LONGTEXT NULL COMMENT 'Workz app manifest (workz.app.json)';
ALTER TABLE apps ADD COLUMN manifest_updated_at TIMESTAMP NULL COMMENT 'Last manifest update timestamp';
