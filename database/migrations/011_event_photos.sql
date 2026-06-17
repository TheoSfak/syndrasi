-- SynDrasi Migration 011 — Photo requests & geotagged event photos
-- Admin requests a photo from a team; the team uploads it (with GPS when available);
-- it appears as a marker on the operations/war-room map.
-- Run in phpMyAdmin against the syndrasi database.

CREATE TABLE IF NOT EXISTS photo_requests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  team_id         INT NOT NULL,
  requested_by    INT NULL,                 -- users.id (admin)
  status          ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fulfilled_at    DATETIME NULL,
  INDEX idx_pr_event_team (event_id, team_id),
  INDEX idx_pr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_photos (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  team_id         INT NOT NULL,
  user_id         INT NULL,                 -- uploader (users.id)
  request_id      INT NULL,                 -- photo_requests.id, if from a request
  file_name       VARCHAR(255) NOT NULL,    -- stored file in storage/uploads/event_photos
  latitude        DECIMAL(10,7) NULL,       -- NULL when the device gave no GPS
  longitude       DECIMAL(10,7) NULL,
  caption         VARCHAR(255) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ep_event (event_id),
  INDEX idx_ep_event_team (event_id, team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
