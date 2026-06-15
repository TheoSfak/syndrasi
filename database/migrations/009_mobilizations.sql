-- SynDrasi Migration 009 — Emergency Mobilization (Κάλεσμα Έκτακτης Ανάγκης)
-- Adds the instant call-out feature: a mobilization plus one response row per
-- targeted volunteer (token-addressable so account-less members can reply).
-- Run in phpMyAdmin against the syndrasi database.

-- 1. The call-out itself
CREATE TABLE IF NOT EXISTS mobilizations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  created_by      INT NULL,                       -- users.id
  event_id        INT NULL,                       -- optional link to an existing event
  title           VARCHAR(255) NOT NULL,
  description     TEXT NULL,
  severity        ENUM('low','medium','high','critical') NOT NULL DEFAULT 'high',
  location_name   VARCHAR(255) NULL,
  latitude        DECIMAL(10,7) NULL,
  longitude       DECIMAL(10,7) NULL,
  status          ENUM('open','active','stood_down') NOT NULL DEFAULT 'open',
  started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at        DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mob_muni (municipality_id),
  INDEX idx_mob_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. One row per targeted member = who was called + how they responded
CREATE TABLE IF NOT EXISTS mobilization_responses (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  mobilization_id INT NOT NULL,
  member_id       INT NOT NULL,                   -- team_members.id
  team_id         INT NOT NULL,
  token           CHAR(64) NOT NULL UNIQUE,       -- no-login response link
  response        ENUM('pending','coming','cant','maybe') NOT NULL DEFAULT 'pending',
  eta_minutes     INT NULL,
  notified_push   TINYINT(1) NOT NULL DEFAULT 0,
  notified_at     DATETIME NULL,
  responded_at    DATETIME NULL,
  checked_in_at   DATETIME NULL,                  -- on-site (QR or manual)
  departed_at     DATETIME NULL,
  notes           VARCHAR(255) NULL,
  UNIQUE KEY uq_mob_member (mobilization_id, member_id),
  INDEX idx_mr_mob (mobilization_id),
  INDEX idx_mr_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
