-- SynDrasi Migration 012 — Operational communications (active-event comms)
-- Adds: SOS / man-down alerts, and a two-way event message thread that also
-- carries command orders/broadcasts and one-tap status pings.
-- Run in phpMyAdmin against the syndrasi database (or via Platform Settings → Updates → run pending).

CREATE TABLE IF NOT EXISTS sos_alerts (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  team_id         INT NOT NULL,
  user_id         INT NOT NULL,                 -- who raised it (users.id)
  latitude        DECIMAL(10,7) NULL,
  longitude       DECIMAL(10,7) NULL,
  accuracy        DECIMAL(10,2) NULL,
  note            VARCHAR(255) NULL,
  status          ENUM('active','acknowledged','resolved') NOT NULL DEFAULT 'active',
  acknowledged_by INT NULL,
  acknowledged_at DATETIME NULL,
  resolved_by     INT NULL,
  resolved_at     DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sos_event (event_id),
  INDEX idx_sos_event_team (event_id, team_id),
  INDEX idx_sos_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_messages (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  team_id         INT NULL,                      -- NULL = broadcast to all teams (command only)
  sender_role     ENUM('command','team') NOT NULL,
  sender_user_id  INT NOT NULL,
  kind            ENUM('message','order','status') NOT NULL DEFAULT 'message',
  status_code     VARCHAR(40) NULL,              -- for kind='status': arrived/task_complete/need_backup/returning/incident
  body            TEXT NULL,
  acknowledged_at DATETIME NULL,                 -- team ACK of a command order
  acknowledged_by INT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_em_event (event_id),
  INDEX idx_em_event_team (event_id, team_id),
  INDEX idx_em_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
