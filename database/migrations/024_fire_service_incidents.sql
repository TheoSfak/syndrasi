CREATE TABLE IF NOT EXISTS fire_service_fetches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source_url VARCHAR(255) NOT NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) NOT NULL DEFAULT 0,
  http_status INT NULL,
  incidents_found INT NOT NULL DEFAULT 0,
  error_message VARCHAR(500) NULL,
  raw_hash CHAR(64) NULL,
  INDEX idx_fire_fetches_at (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fire_service_incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fingerprint CHAR(64) NOT NULL,
  category VARCHAR(80) NOT NULL,
  status_label VARCHAR(80) NOT NULL,
  region VARCHAR(120) NULL,
  regional_unit VARCHAR(120) NULL,
  municipality VARCHAR(160) NULL,
  area_text VARCHAR(255) NULL,
  location_text VARCHAR(255) NULL,
  raw_text TEXT NOT NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  last_fetch_id INT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  created_event_id INT NULL,
  UNIQUE KEY uniq_fire_fingerprint (fingerprint),
  INDEX idx_fire_current_region_unit (is_current, region, regional_unit),
  INDEX idx_fire_last_seen (last_seen_at),
  INDEX idx_fire_created_event (created_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
