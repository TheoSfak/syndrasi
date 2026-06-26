-- SynDrasi Migration 019 — Video requests & geotagged event videos
-- Admin (operations console) requests a short video (30-40s) from one team
-- or broadcasts the request to several teams (shared batch_id). The team's
-- field device records (or picks from gallery) and uploads the clip with GPS
-- when available; it appears in the war-room and is auto-purged after 7 days.
-- Run in phpMyAdmin against the syndrasi database.

CREATE TABLE IF NOT EXISTS video_requests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  team_id         INT NOT NULL,
  requested_by    INT NULL,                 -- users.id (admin)
  instructions    VARCHAR(500) NULL,        -- admin's shooting instructions
  max_seconds     SMALLINT NOT NULL DEFAULT 40,
  batch_id        CHAR(36) NULL,            -- shared id when broadcast to many teams
  status          ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fulfilled_at    DATETIME NULL,
  INDEX idx_vr_event_team (event_id, team_id),
  INDEX idx_vr_status (status),
  INDEX idx_vr_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_videos (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  team_id         INT NOT NULL,
  user_id         INT NULL,                 -- uploader (users.id)
  request_id      INT NULL,                 -- video_requests.id, if from a request
  file_name       VARCHAR(255) NOT NULL,    -- stored file in storage/uploads/event_videos
  mime            VARCHAR(60)  NOT NULL DEFAULT 'video/mp4',
  duration_sec    SMALLINT NULL,
  size_bytes      INT NULL,
  latitude        DECIMAL(10,7) NULL,       -- NULL when the device gave no GPS
  longitude       DECIMAL(10,7) NULL,
  caption         VARCHAR(255) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ev_event (event_id),
  INDEX idx_ev_event_team (event_id, team_id),
  INDEX idx_ev_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
