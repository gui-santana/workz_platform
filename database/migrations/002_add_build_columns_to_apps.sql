-- Add build-related columns to apps table
-- Requirements: 5.1, 5.2, 5.3

ALTER TABLE apps ADD COLUMN build_status ENUM('pending', 'building', 'success', 'failed', 'cancelled') DEFAULT 'pending';
ALTER TABLE apps ADD COLUMN build_id VARCHAR(100) NULL;
ALTER TABLE apps ADD COLUMN last_build_at TIMESTAMP NULL;
ALTER TABLE apps ADD COLUMN build_artifacts JSON NULL;
ALTER TABLE apps ADD COLUMN build_errors JSON NULL;
ALTER TABLE apps ADD COLUMN build_duration INT DEFAULT 0;

-- Create indexes for efficient build queries
CREATE INDEX idx_apps_build_status ON apps(build_status);
CREATE INDEX idx_apps_build_id ON apps(build_id);
CREATE INDEX idx_apps_last_build_at ON apps(last_build_at);