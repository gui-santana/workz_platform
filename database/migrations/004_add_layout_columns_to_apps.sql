-- Migration: Add layout/orientation columns to apps table
-- Supports per-app aspect ratio and orientation flags

ALTER TABLE apps
  ADD COLUMN aspect_ratio VARCHAR(20) DEFAULT '4:3' COMMENT 'Layout aspect ratio (width:height, e.g. 4:3, 16:9)',
  ADD COLUMN supports_portrait TINYINT(1) DEFAULT 1 COMMENT 'Whether the app supports portrait orientation',
  ADD COLUMN supports_landscape TINYINT(1) DEFAULT 1 COMMENT 'Whether the app supports landscape orientation';

