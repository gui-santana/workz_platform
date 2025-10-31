-- Ensure build_queue has all columns used by BuildQueueController::updateJob
ALTER TABLE build_queue
  ADD COLUMN IF NOT EXISTS build_log MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS output_path VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS started_at TIMESTAMP NULL,
  ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Optional indexes (ignore errors if already exist on older MySQL)
ALTER TABLE build_queue
  ADD INDEX idx_queue_status (status),
  ADD INDEX idx_queue_app (app_id);

