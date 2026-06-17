-- SynDrasi Migration 016 — GPS location requests
-- Admin (operations console) requests a one-off GPS fix from a team;
-- the team's field device sends its location, which fulfils the request
-- and drops/updates the team marker on the operations/war-room map.
-- Run in phpMyAdmin against the syndrasi database.

CREATE TABLE IF NOT EXISTS gps_requests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  team_id         INT NOT NULL,
  requested_by    INT NULL,                 -- users.id (admin)
  status          ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fulfilled_at    DATETIME NULL,
  INDEX idx_gr_event_team (event_id, team_id),
  INDEX idx_gr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
