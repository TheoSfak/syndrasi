-- ============================================================
-- SynDrasi - Migration 002: Team Members Roster
-- Run once against the syndrasi database.
-- ============================================================

USE syndrasi;

-- ------------------------------------------------------------
-- team_members: roster of volunteers per team
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS team_members (
  id                        INT AUTO_INCREMENT PRIMARY KEY,
  team_id                   INT NOT NULL,
  municipality_id           INT NOT NULL,

  -- Fixed required fields
  full_name                 VARCHAR(255) NOT NULL,
  phone                     VARCHAR(50) NOT NULL,

  -- Fixed optional fields (always in schema, visibility controlled by municipality)
  email                     VARCHAR(255) NULL,
  date_of_birth             DATE NULL,
  address                   VARCHAR(255) NULL,
  civil_protection_registry_no VARCHAR(100) NULL COMMENT 'ΑΜ Πολιτικής Προστασίας',
  role_in_team              VARCHAR(100) NULL COMMENT 'Ειδικότητα / Ρόλος',
  notes                     TEXT NULL,

  -- Configurable optional fields (shown/hidden per municipality_settings[member_fields_config])
  blood_type                VARCHAR(10) NULL,
  driving_license           VARCHAR(50) NULL COMMENT 'Κατηγορία διπλώματος',
  certifications            TEXT NULL,
  id_number                 VARCHAR(50) NULL COMMENT 'Αριθμός ταυτότητας',
  amka                      VARCHAR(20) NULL COMMENT 'ΑΜΚΑ',

  -- Meta
  is_team_admin             TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = εμφανίζεται ως Διαχειριστής Ομάδας',
  status                    ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_team_members_team_id (team_id),
  INDEX idx_team_members_municipality_id (municipality_id),
  INDEX idx_team_members_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- event_application_members: which members attend each application
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_application_members (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  member_id      INT NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_app_member (application_id, member_id),
  INDEX idx_eam_application_id (application_id),
  INDEX idx_eam_member_id (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- event_applications: add mission commander + member notification flag
-- ------------------------------------------------------------
ALTER TABLE event_applications
  ADD COLUMN IF NOT EXISTS mission_commander_id INT NULL COMMENT 'FK team_members.id',
  ADD COLUMN IF NOT EXISTS members_notified_at  DATETIME NULL,
  ADD INDEX IF NOT EXISTS idx_apps_mission_commander (mission_commander_id);
