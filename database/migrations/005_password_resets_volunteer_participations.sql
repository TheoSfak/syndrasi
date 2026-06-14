-- Migration 005: Password resets + Individual volunteer participation tracking

-- ── 1. Password reset tokens ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(255) NOT NULL,
  token      VARCHAR(64)  NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at    DATETIME NULL,
  INDEX idx_pr_email (email),
  INDEX idx_pr_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Per-member event participation records ─────────────────────────────────
-- Populated during/after reconciliation for each approved application member.
CREATE TABLE IF NOT EXISTS volunteer_participations (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id      INT NOT NULL,
  event_id             INT NOT NULL,
  team_id              INT NOT NULL,
  application_id       INT NOT NULL,
  member_id            INT NOT NULL,
  was_present          TINYINT(1)    NOT NULL DEFAULT 1,
  hours                DECIMAL(6,2)  NOT NULL DEFAULT 0,
  is_mission_commander TINYINT(1)    NOT NULL DEFAULT 0,
  notes                TEXT NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_event_member (event_id, member_id),
  INDEX idx_vp_member_id       (member_id),
  INDEX idx_vp_event_id        (event_id),
  INDEX idx_vp_team_id         (team_id),
  INDEX idx_vp_municipality_id (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
