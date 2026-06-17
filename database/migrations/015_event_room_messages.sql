-- SynDrasi Migration 015 — Shared operations room chat
-- A single event-wide channel where command + all teams + field commanders talk
-- together (separate from the private per-team threads in event_messages).
-- Run in phpMyAdmin (or Platform Settings → Updates → run pending).

CREATE TABLE IF NOT EXISTS event_room_messages (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  sender_role     ENUM('command','team') NOT NULL,
  sender_user_id  INT NULL,
  sender_team_id  INT NULL,                  -- team attribution (NULL for command)
  sender_label    VARCHAR(255) NULL,         -- display name when no user (e.g. field commander)
  body            TEXT NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_erm_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
