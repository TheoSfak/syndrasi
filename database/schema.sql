-- ============================================================
-- SynDrasi - Database schema (MySQL 8 / MariaDB 10.5+)
-- Run:  mysql -u root -p < schema.sql
--
-- ✅ FRESH INSTALL — this file is now a FULL, up-to-date dump of the schema
-- produced by all files in database/migrations/ (regenerated from a fully
-- migrated dev DB on 2026-07-02, current through migration 038). A brand new
-- database created from this file alone already has every table the app
-- needs — you do NOT need to run database/migrations/*.sql by hand.
--
-- On first request, MigrationRunner::ensureInitialised() sees the `users`
-- table already exists and "baselines" every *.sql file currently in
-- database/migrations/ as already-applied (recording them in
-- schema_migrations WITHOUT re-running them) — because their effect is
-- already present in this file. Only migrations added AFTER this file was
-- generated will actually execute.
--
-- ⚠️ Maintainer note: whenever a new database/migrations/NNN_*.sql file is
-- added, regenerate this file from a fully-migrated dev DB as part of the
-- release step (mysqldump --no-data, hand-cleaned to match this file's
-- style) so schema.sql never drifts from what the migrations actually
-- produce. See DEPLOY.md → "Fresh install".
-- ============================================================

CREATE DATABASE IF NOT EXISTS syndrasi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE syndrasi;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS schema_migrations;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS municipality_settings;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS push_subscriptions;
DROP TABLE IF EXISTS mail_queue;
DROP TABLE IF EXISTS notification_deliveries;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS fire_risk_map_notifications;
DROP TABLE IF EXISTS fire_service_incident_notifications;
DROP TABLE IF EXISTS fire_service_incidents;
DROP TABLE IF EXISTS fire_service_fetches;
DROP TABLE IF EXISTS mobilization_responses;
DROP TABLE IF EXISTS mobilizations;
DROP TABLE IF EXISTS operational_notes;
DROP TABLE IF EXISTS event_reports;
DROP TABLE IF EXISTS shortage_reports;
DROP TABLE IF EXISTS event_room_messages;
DROP TABLE IF EXISTS event_messages;
DROP TABLE IF EXISTS event_videos;
DROP TABLE IF EXISTS video_requests;
DROP TABLE IF EXISTS event_photos;
DROP TABLE IF EXISTS photo_requests;
DROP TABLE IF EXISTS gps_requests;
DROP TABLE IF EXISTS sos_alerts;
DROP TABLE IF EXISTS location_pings;
DROP TABLE IF EXISTS team_debriefs;
DROP TABLE IF EXISTS volunteer_participations;
DROP TABLE IF EXISTS operational_checkins;
DROP TABLE IF EXISTS shift_applications;
DROP TABLE IF EXISTS event_application_members;
DROP TABLE IF EXISTS event_applications;
DROP TABLE IF EXISTS event_shifts;
DROP TABLE IF EXISTS event_templates;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS event_playbooks;
DROP TABLE IF EXISTS event_categories;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS volunteer_teams;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS municipalities;

-- ------------------------------------------------------------
CREATE TABLE municipalities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  authority_type ENUM('municipality','civil_protection','fire_service','coast_guard') NOT NULL DEFAULT 'municipality',
  official_name VARCHAR(255) NULL,
  short_name VARCHAR(80) NULL,
  city VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NULL,
  team_id INT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  phone VARCHAR(50) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','municipality_admin','team_admin','event_operator') NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_municipality_id (municipality_id),
  INDEX idx_users_team_id (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE volunteer_teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  type VARCHAR(100) NULL,
  contact_person VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  telegram_chat_id VARCHAR(80) NULL COMMENT 'Telegram group/channel chat_id for team notifications',
  address VARCHAR(255) NULL,
  has_vehicle TINYINT(1) NOT NULL DEFAULT 0,
  has_medical_equipment TINYINT(1) NOT NULL DEFAULT 0,
  default_people_capacity INT NULL,
  readiness_items_json TEXT NULL,
  notes TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_teams_municipality_id (municipality_id),
  INDEX idx_teams_telegram_chat (telegram_chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Named individual volunteers belonging to a team (roster)
CREATE TABLE team_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  municipality_id INT NOT NULL,
  user_id INT NULL,
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  email VARCHAR(255) NULL,
  date_of_birth DATE NULL,
  address VARCHAR(255) NULL,
  civil_protection_registry_no VARCHAR(100) NULL,
  role_in_team VARCHAR(100) NULL,
  notes TEXT NULL,
  blood_type VARCHAR(10) NULL,
  driving_license VARCHAR(50) NULL,
  certifications TEXT NULL,
  id_number VARCHAR(50) NULL,
  amka VARCHAR(20) NULL,
  is_team_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_assistant_admin TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Βοηθός Αρχηγού with team_admin login access',
  assistant_promoted_at DATETIME NULL,
  assistant_promoted_by INT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tm_team (team_id),
  INDEX idx_tm_muni (municipality_id),
  INDEX idx_tm_status (status),
  INDEX idx_tm_user (user_id),
  INDEX idx_tm_assistant_admin (team_id, is_assistant_admin, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE event_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  authority_type ENUM('municipality','civil_protection','fire_service','coast_guard') NOT NULL DEFAULT 'municipality',
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_categories_authority (authority_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE event_playbooks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  authority_type ENUM('municipality','civil_protection','fire_service','coast_guard') NOT NULL DEFAULT 'municipality',
  title VARCHAR(180) NOT NULL,
  default_people INT NOT NULL DEFAULT 0,
  require_vehicle TINYINT(1) NOT NULL DEFAULT 0,
  require_medical TINYINT(1) NOT NULL DEFAULT 0,
  instructions TEXT NULL,
  capabilities_json TEXT NULL,
  requested_items_json TEXT NULL,
  checklist_json TEXT NULL,
  messages_json TEXT NULL,
  debrief_questions_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_playbook_category (category_id),
  INDEX idx_playbooks_authority (authority_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  category_id INT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  location_name VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NOT NULL,
  requested_people INT NOT NULL DEFAULT 0,
  requested_vehicle TINYINT(1) NOT NULL DEFAULT 0,
  requested_medical_equipment TINYINT(1) NOT NULL DEFAULT 0,
  requested_items_json TEXT NULL,
  instructions TEXT NULL,
  status ENUM('draft','open','review','confirmed','active','closed','completed','cancelled') NOT NULL DEFAULT 'draft',
  public_token VARCHAR(64) NULL UNIQUE,
  published_at DATETIME NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  reconciliation_notes TEXT NULL,
  story_token VARCHAR(64) NULL COMMENT 'no-login public Story link',
  story_published_at DATETIME NULL,
  INDEX idx_events_municipality_id (municipality_id),
  INDEX idx_events_status (status),
  INDEX idx_events_start_datetime (start_datetime),
  INDEX idx_events_story_token (story_token),
  INDEX idx_events_muni_status_start (municipality_id, status, start_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reusable event templates (pre-filled draft events)
CREATE TABLE event_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  title VARCHAR(255) NULL,
  category_id INT NULL,
  description TEXT NULL,
  location_name VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  requested_people INT NOT NULL DEFAULT 0,
  requested_vehicle TINYINT(1) NOT NULL DEFAULT 0,
  requested_medical_equipment TINYINT(1) NOT NULL DEFAULT 0,
  instructions TEXT NULL,
  shifts_json TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_event_templates_municipality_id (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Named shifts within an event (e.g. morning/evening coverage)
CREATE TABLE event_shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  municipality_id INT NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT '',
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NOT NULL,
  required_people SMALLINT DEFAULT 0,
  notes TEXT NULL,
  reminded_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event (event_id),
  INDEX fk_es_mun (municipality_id),
  CONSTRAINT fk_es_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
  CONSTRAINT fk_es_mun FOREIGN KEY (municipality_id) REFERENCES municipalities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE event_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  offered_people INT NOT NULL DEFAULT 0,
  offered_vehicle TINYINT(1) NOT NULL DEFAULT 0,
  offered_medical_equipment TINYINT(1) NOT NULL DEFAULT 0,
  comment TEXT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  approved_people INT NULL,
  admin_comment TEXT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  reviewed_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  actual_people INT NULL,
  actual_arrival_time DATETIME NULL,
  actual_departure_time DATETIME NULL,
  mission_commander_id INT NULL,
  members_notified_at DATETIME NULL,
  field_token VARCHAR(64) NULL COMMENT 'no-login link for the mission commander',
  field_pin VARCHAR(8) NULL COMMENT '4-digit gate for the field link (NULL = no gate)',
  UNIQUE KEY unique_event_team (event_id, team_id),
  INDEX idx_applications_municipality_id (municipality_id),
  INDEX idx_applications_event_id (event_id),
  INDEX idx_applications_team_id (team_id),
  INDEX idx_applications_status (status),
  INDEX idx_apps_field_token (field_token),
  INDEX idx_apps_event_status (event_id, status),
  INDEX idx_apps_team_status (team_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Named members a team assigns to a specific event application
CREATE TABLE event_application_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  member_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_app_member (application_id, member_id),
  INDEX idx_eam_app (application_id),
  INDEX idx_eam_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Per-shift team applications (offering people to a named shift)
CREATE TABLE shift_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shift_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  municipality_id INT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  offered_people SMALLINT DEFAULT 0,
  approved_people SMALLINT DEFAULT 0,
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shift_team (shift_id, team_id),
  INDEX idx_team_event (team_id, event_id),
  INDEX fk_sa_event (event_id),
  CONSTRAINT fk_sa_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
  CONSTRAINT fk_sa_shift FOREIGN KEY (shift_id) REFERENCES event_shifts (id) ON DELETE CASCADE,
  CONSTRAINT fk_sa_team FOREIGN KEY (team_id) REFERENCES volunteer_teams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE operational_checkins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  application_id INT NULL,
  status ENUM('present_full','present_partial','not_present','departed') NOT NULL,
  present_people INT NOT NULL DEFAULT 0,
  expected_people INT NULL,
  message TEXT NULL,
  checked_in_by INT NOT NULL,
  checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_checkins_event_id (event_id),
  INDEX idx_checkins_team_id (team_id),
  INDEX idx_checkins_municipality_id (municipality_id),
  INDEX idx_checkins_event_team_id (event_id, team_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Per-member participation/hours logged against an event (post-event debrief detail)
CREATE TABLE volunteer_participations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  application_id INT NOT NULL,
  member_id INT NOT NULL,
  was_present TINYINT(1) NOT NULL DEFAULT 1,
  hours DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  is_mission_commander TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_event_member (event_id, member_id),
  INDEX idx_vp_member_id (member_id),
  INDEX idx_vp_event_id (event_id),
  INDEX idx_vp_team_id (team_id),
  INDEX idx_vp_municipality_id (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Team-submitted post-event debrief (what went well/wrong, ratings)
CREATE TABLE team_debriefs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  municipality_id INT NOT NULL,
  submitted_by INT NOT NULL,
  actual_volunteers INT NOT NULL DEFAULT 0,
  volunteer_hours DECIMAL(6,1) NOT NULL DEFAULT 0.0,
  incidents_count INT NOT NULL DEFAULT 0,
  what_went_well TEXT NULL,
  what_went_wrong TEXT NULL,
  incidents_description TEXT NULL,
  organization_rating TINYINT NOT NULL DEFAULT 3,
  comments TEXT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_event_team (event_id, team_id),
  INDEX idx_debriefs_event_id (event_id),
  INDEX idx_debriefs_team_id (team_id),
  INDEX idx_debriefs_municipality_id (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE location_pings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  user_id INT NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  accuracy DECIMAL(10,2) NULL,
  message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_location_event_team (event_id, team_id),
  INDEX idx_location_created_at (created_at),
  INDEX idx_loc_event_created (event_id, created_at),
  INDEX idx_loc_event_created_id (event_id, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Field-triggered SOS alerts from a team member during an event
CREATE TABLE sos_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  user_id INT NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  accuracy DECIMAL(10,2) NULL,
  note VARCHAR(255) NULL,
  status ENUM('active','acknowledged','resolved') NOT NULL DEFAULT 'active',
  acknowledged_by INT NULL,
  acknowledged_at DATETIME NULL,
  resolved_by INT NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sos_event (event_id),
  INDEX idx_sos_event_team (event_id, team_id),
  INDEX idx_sos_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Command-initiated requests for a team's live GPS
CREATE TABLE gps_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  requested_by INT NULL,
  status ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fulfilled_at DATETIME NULL,
  INDEX idx_gr_event_team (event_id, team_id),
  INDEX idx_gr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Command-initiated requests for a team to submit a photo
CREATE TABLE photo_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  requested_by INT NULL,
  status ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fulfilled_at DATETIME NULL,
  INDEX idx_pr_event_team (event_id, team_id),
  INDEX idx_pr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Photos submitted by field teams during an event
CREATE TABLE event_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  user_id INT NULL,
  request_id INT NULL,
  file_name VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  caption VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ep_event (event_id),
  INDEX idx_ep_event_team (event_id, team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Command-initiated requests for a team to submit a short video
CREATE TABLE video_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  requested_by INT NULL,
  instructions VARCHAR(500) NULL,
  max_seconds SMALLINT NOT NULL DEFAULT 40,
  batch_id CHAR(36) NULL,
  status ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fulfilled_at DATETIME NULL,
  INDEX idx_vr_event_team (event_id, team_id),
  INDEX idx_vr_status (status),
  INDEX idx_vr_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Short videos submitted by field teams during an event
CREATE TABLE event_videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  user_id INT NULL,
  request_id INT NULL,
  file_name VARCHAR(255) NOT NULL,
  mime VARCHAR(60) NOT NULL DEFAULT 'video/mp4',
  duration_sec SMALLINT NULL,
  size_bytes INT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  caption VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  kept TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = κρατιέται μόνιμα (μπήκε σε Story)',
  INDEX idx_ev_event (event_id),
  INDEX idx_ev_event_team (event_id, team_id),
  INDEX idx_ev_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Command <-> team operational messages/orders/status updates for an event
CREATE TABLE event_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NULL,
  sender_role ENUM('command','team') NOT NULL,
  sender_user_id INT NOT NULL,
  kind ENUM('message','order','status') NOT NULL DEFAULT 'message',
  status_code VARCHAR(40) NULL,
  body TEXT NULL,
  acknowledged_at DATETIME NULL,
  acknowledged_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  point_kind ENUM('move','incident','poi') NULL,
  INDEX idx_em_event (event_id),
  INDEX idx_em_event_team (event_id, team_id),
  INDEX idx_em_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Shared "operations room" chat visible to command and all teams on an event
CREATE TABLE event_room_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  sender_role ENUM('command','team') NOT NULL,
  sender_user_id INT NULL,
  sender_team_id INT NULL,
  sender_label VARCHAR(255) NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_erm_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE shortage_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NOT NULL,
  reported_by INT NOT NULL,
  shortage_type ENUM('people','equipment','medical_supplies','vehicle','other') NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
  acknowledged_by INT NULL,
  acknowledged_at DATETIME NULL,
  resolved_by INT NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_shortages_event_id (event_id),
  INDEX idx_shortages_team_id (team_id),
  INDEX idx_shortages_status (status),
  INDEX idx_shortages_severity (severity),
  INDEX idx_shortages_event_status_created (event_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE event_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  team_id INT NULL,
  report_type ENUM('team_report','municipality_report') NOT NULL,
  incidents_count INT NOT NULL DEFAULT 0,
  transfers_count INT NOT NULL DEFAULT 0,
  first_aid_count INT NOT NULL DEFAULT 0,
  summary TEXT NULL,
  notes TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_reports_event_id (event_id),
  INDEX idx_reports_team_id (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Operational notes added by the municipality during an event
CREATE TABLE operational_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  note TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_opnotes_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Ad-hoc emergency mobilizations (can exist without a full event)
CREATE TABLE mobilizations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  created_by INT NULL,
  event_id INT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'high',
  location_name VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  status ENUM('open','active','stood_down') NOT NULL DEFAULT 'open',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mob_muni (municipality_id),
  INDEX idx_mob_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Per-member no-login response link/status for a mobilization
CREATE TABLE mobilization_responses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mobilization_id INT NOT NULL,
  member_id INT NOT NULL,
  team_id INT NOT NULL,
  token CHAR(64) NOT NULL,
  response ENUM('pending','coming','cant','maybe') NOT NULL DEFAULT 'pending',
  eta_minutes INT NULL,
  notified_push TINYINT(1) NOT NULL DEFAULT 0,
  notified_at DATETIME NULL,
  responded_at DATETIME NULL,
  checked_in_at DATETIME NULL,
  departed_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  UNIQUE KEY token (token),
  UNIQUE KEY uq_mob_member (mobilization_id, member_id),
  INDEX idx_mr_mob (mobilization_id),
  INDEX idx_mr_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Log of each fetch from the Fire Service public incidents feed
CREATE TABLE fire_service_fetches (
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

-- ------------------------------------------------------------
-- De-duplicated Fire Service incidents tracked across fetches
CREATE TABLE fire_service_incidents (
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

-- ------------------------------------------------------------
-- Telegram notification de-dup log for Fire Service incident status changes
CREATE TABLE fire_service_incident_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fire_service_incident_id INT NOT NULL,
  municipality_id INT NOT NULL,
  status_label VARCHAR(80) NOT NULL,
  telegram_notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fire_notify_incident_muni_status (fire_service_incident_id, municipality_id, status_label),
  INDEX idx_fire_notify_muni_at (municipality_id, telegram_notified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Telegram notification de-dup log for daily fire-risk map publications
CREATE TABLE fire_risk_map_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  map_date DATE NOT NULL,
  levels_json TEXT NOT NULL,
  image_url VARCHAR(500) NOT NULL,
  telegram_notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fire_risk_notify_muni_date (municipality_id, map_date),
  INDEX idx_fire_risk_notify_at (telegram_notified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NULL,
  user_id INT NULL,
  team_id INT NULL,
  event_id INT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(100) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  email_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user_id (user_id),
  INDEX idx_notifications_team_id (team_id),
  INDEX idx_notifications_event_id (event_id),
  INDEX idx_notif_user_read (user_id, is_read),
  INDEX idx_notif_user_read_created (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE notification_deliveries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT UNSIGNED NULL,
  channel ENUM('email','sms','telegram','push') NOT NULL,
  status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  recipient_user_id INT UNSIGNED NULL,
  team_id INT UNSIGNED NULL,
  event_id INT UNSIGNED NULL,
  recipient_label VARCHAR(255) NULL,
  recipient_address VARCHAR(255) NULL,
  title VARCHAR(255) NOT NULL DEFAULT '',
  message MEDIUMTEXT NULL,
  type VARCHAR(100) NULL,
  external_ref VARCHAR(255) NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  error_msg TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  INDEX idx_nd_municipality_created (municipality_id, created_at),
  INDEX idx_nd_channel_status (channel, status),
  INDEX idx_nd_user_created (recipient_user_id, created_at),
  INDEX idx_nd_team_created (team_id, created_at),
  INDEX idx_nd_external_ref (external_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Outbound email queue (async SMTP delivery with retry)
CREATE TABLE mail_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT UNSIGNED NULL,
  to_email VARCHAR(255) NOT NULL,
  to_name VARCHAR(255) NOT NULL DEFAULT '',
  subject VARCHAR(500) NOT NULL DEFAULT '',
  body MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_attempt DATETIME NULL,
  sent_at DATETIME NULL,
  error_msg TEXT NULL,
  INDEX idx_mq_pending (sent_at, attempts, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Web Push subscriptions (one row per browser/device endpoint)
CREATE TABLE push_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  endpoint VARCHAR(1000) NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth_key VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_ep (user_id, endpoint(200)),
  CONSTRAINT fk_ps_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  token VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME NULL,
  UNIQUE KEY token (token),
  INDEX idx_pr_email (email),
  INDEX idx_pr_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NULL,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(100) NULL,
  entity_id INT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_municipality_id (municipality_id),
  INDEX idx_audit_user_id (user_id),
  INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Per-municipality settings (SMTP κ.λπ.)
CREATE TABLE municipality_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_mun_key (municipality_id, setting_key),
  INDEX idx_msettings_mid (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Simple key/value settings managed by the super admin
CREATE TABLE app_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Simple key/value rate-limit counters (login throttling κ.λπ.)
CREATE TABLE rate_limits (
  rate_key VARCHAR(100) PRIMARY KEY,
  value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rate_limits_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Applied-migration tracking table (also created lazily by MigrationRunner;
-- included here so a fresh install's table set matches a migrated one
-- immediately, before the app has served its first request).
CREATE TABLE schema_migrations (
  filename VARCHAR(255) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Default categories
INSERT INTO event_categories (authority_type, name) VALUES
('municipality', 'Πολιτιστική εκδήλωση'),
('municipality', 'Συναυλία'),
('municipality', 'Αθλητική δράση'),
('municipality', 'Κοινωνική δράση'),
('municipality', 'Εορταστική δράση'),
('municipality', 'Άλλη δράση'),
('municipality', 'Περιβαλλοντική δράση'),
('municipality', 'Υποστήριξη εκδήλωσης'),
('municipality', 'Έκτακτη ανάγκη'),
('fire_service', 'Πυρκαγιά δασική'),
('fire_service', 'Πυρκαγιά αστική'),
('fire_service', 'Απεγκλωβισμός'),
('fire_service', 'Τροχαίο'),
('fire_service', 'Παροχή βοήθειας'),
('fire_service', 'Έρευνα αγνοουμένου'),
('fire_service', 'Πλημμυρικό συμβάν'),
('fire_service', 'Επιφυλακή / κάλυψη περιοχής'),
('civil_protection', 'Σεισμός'),
('civil_protection', 'Πλημμύρα'),
('civil_protection', 'Κακοκαιρία'),
('civil_protection', 'Εκκένωση'),
('civil_protection', 'Διανομή ειδών ανάγκης'),
('civil_protection', 'Υποστήριξη καταυλισμού'),
('civil_protection', 'Έλεγχος υποδομών'),
('civil_protection', 'Συντονισμός εθελοντών'),
('coast_guard', 'Θαλάσσια διάσωση'),
('coast_guard', 'Έρευνα στη θάλασσα'),
('coast_guard', 'Ρύπανση'),
('coast_guard', 'Ναυτικό ατύχημα'),
('coast_guard', 'Απεγκλωβισμός σκάφους'),
('coast_guard', 'Υποστήριξη λιμένα'),
('coast_guard', 'Μεταφορά / συνδρομή');

-- ------------------------------------------------------------
-- Default response playbooks (one per category; ids match the
-- authority_type/category ordering above so a fresh install lines up
-- with a migrated one)
INSERT INTO event_playbooks
(id, category_id, authority_type, title, default_people, require_vehicle, require_medical, instructions, capabilities_json, requested_items_json, checklist_json, messages_json, debrief_questions_json) VALUES
(1,10,'fire_service','Πρωτόκολλο δασικής πυρκαγιάς',8,1,1,'Άμεση προσέλευση στο σημείο συγκέντρωσης. Φέρτε ΜΑΠ, νερό, ασύρματο/φορτισμένο κινητό και ενημερώστε για διαθέσιμο όχημα. Ο υπεύθυνος ομάδας στέλνει στίγμα ανά 15 λεπτά.','["Πυροσβεστικό/υδροφόρο όχημα","4x4","Ασύρματοι","Πρώτες βοήθειες","Drone/παρατήρηση","Φακοί/ΜΑΠ"]','["Πυροσβεστικό/υδροφόρο όχημα","4x4","Ασύρματοι","Πρώτες βοήθειες","Drone/παρατήρηση","Φακοί/ΜΑΠ"]','["Επιβεβαίωση σημείου συγκέντρωσης και ασφαλούς πρόσβασης","Καταγραφή ανέμου, καπνού και ορατότητας","Ορισμός τομέων ομάδων και υπευθύνων","Αίτημα πρώτου GPS από όλες τις ομάδες","Έλεγχος διαθέσιμων οχημάτων/νερού","Προετοιμασία μηνύματος εκκένωσης αν χρειαστεί"]','["Στείλτε άμεσα διαθέσιμο προσωπικό, οχήματα και ETA.","Αποστείλετε GPS στίγμα και κατάσταση πρόσβασης.","Κρατήστε απόσταση ασφαλείας και αναφέρετε αλλαγή ανέμου/καπνού."]','["Υπήρξε ασφαλής πρόσβαση στο συμβάν?","Ποιος εξοπλισμός έλειψε περισσότερο?","Πώς αξιολογείτε τον τομέα/συντονισμό?","Τι πρέπει να προστεθεί στο πρωτόκολλο?"]'),
(2,11,'fire_service','Πρωτόκολλο αστικής πυρκαγιάς',6,1,1,'Προσέλευση με προτεραιότητα στην ασφάλεια πολιτών. Δηλώστε διαθέσιμα οχήματα, πρώτες βοήθειες και δυνατότητα αποκλεισμού δρόμων.','["Όχημα","Πρώτες βοήθειες","Ασύρματοι","Φακοί","Κώνοι/σήμανση","Υποστήριξη εκκένωσης"]','["Όχημα","Πρώτες βοήθειες","Ασύρματοι","Φακοί","Κώνοι/σήμανση","Υποστήριξη εκκένωσης"]','["Επιβεβαίωση ακριβούς διεύθυνσης και πρόσβασης","Έλεγχος ανάγκης εκκένωσης/αποκλεισμού","Ορισμός ζώνης ασφαλείας","Καταγραφή ευάλωτων πολιτών","Συντονισμός με ΕΚΑΒ/ΕΛΑΣ όπου απαιτείται"]','["Προσέλθετε στο σημείο στάθμευσης και αναφέρετε διαθέσιμο προσωπικό.","Μην εισέρχεστε σε κτίριο χωρίς εντολή αρμόδιου επικεφαλής.","Αναφέρετε άμεσα εγκλωβισμένους ή ανάγκη πρώτων βοηθειών."]','["Ήταν σαφείς οι ζώνες ασφαλείας?","Υπήρξε ανάγκη εκκένωσης?","Τι δυσκόλεψε την πρόσβαση?"]'),
(3,12,'fire_service','Πρωτόκολλο απεγκλωβισμού',5,1,1,'Προσέλευση με εξοπλισμό πρώτων βοηθειών, φακούς και δυνατότητα ασφαλούς περίμετρου. Αναφέρετε ειδικό εξοπλισμό που διαθέτετε.','["Πρώτες βοήθειες","Φακοί","Κοπή/διάνοιξη","Σχοινιά","Όχημα","Σήμανση"]','["Πρώτες βοήθειες","Φακοί","Κοπή/διάνοιξη","Σχοινιά","Όχημα","Σήμανση"]','["Επιβεβαίωση αριθμού εμπλεκομένων","Ασφαλής περίμετρος και πρόσβαση διασωστών","Συντονισμός με αρμόδια υπηρεσία","Καταγραφή κινδύνων ηλεκτρικού/καυσίμων/κατάρρευσης","Προετοιμασία σημείου παραλαβής τραυματιών"]','["Αναφέρετε ειδικό εξοπλισμό και ETA.","Διατηρήστε ελεύθερη πρόσβαση για οχήματα διάσωσης.","Στείλτε άμεσα εικόνα/στίγμα αν είναι ασφαλές."]','["Ποιος κίνδυνος ήταν πιο κρίσιμος?","Υπήρξε επαρκής εξοπλισμός?","Πόσο γρήγορα ορίστηκε ασφαλής περίμετρος?"]'),
(4,13,'fire_service','Πρωτόκολλο τροχαίου συμβάντος',4,1,1,'Προσέλευση με προτεραιότητα σε σήμανση, πρώτες βοήθειες και ασφαλή διαχείριση κυκλοφορίας.','["Πρώτες βοήθειες","Σήμανση/κώνοι","Φακοί","Όχημα","Ασύρματοι"]','["Πρώτες βοήθειες","Σήμανση/κώνοι","Φακοί","Όχημα","Ασύρματοι"]','["Επιβεβαίωση σημείου και λωρίδων κυκλοφορίας","Σήμανση προσέγγισης και ζώνη ασφαλείας","Καταγραφή τραυματιών/εγκλωβισμένων","Συντονισμός με ΕΚΑΒ/Τροχαία","Ενημέρωση ομάδων για ασφαλές σημείο στάθμευσης"]','["Προσέγγιση μόνο από ασφαλή κατεύθυνση.","Αναφέρετε τραυματίες, εγκλωβισμένους και ανάγκη σήμανσης.","Κρατήστε ελεύθερη δίοδο για ΕΚΑΒ/Πυροσβεστική."]','["Ήταν επαρκής η σήμανση?","Πόσο γρήγορα ενημερώθηκε η κυκλοφορία?","Υπήρξε έλλειψη εξοπλισμού πρώτων βοηθειών?"]'),
(5,18,'civil_protection','Πρωτόκολλο σεισμού',10,1,1,'Οι ομάδες κατευθύνονται στα προκαθορισμένα σημεία συγκέντρωσης. Προτεραιότητα: ασφάλεια πολιτών, πρώτες βοήθειες, έλεγχος υποδομών και αναφορά ζημιών.','["Πρώτες βοήθειες","Όχημα","Ασύρματοι","Φακοί","Σκηνές/κουβέρτες","Μηχανικός/τεχνικός έλεγχος"]','["Πρώτες βοήθειες","Όχημα","Ασύρματοι","Φακοί","Σκηνές/κουβέρτες","Μηχανικός/τεχνικός έλεγχος"]','["Ενεργοποίηση σημείων συγκέντρωσης","Αίτημα κατάστασης από όλες τις ομάδες","Καταγραφή αναφορών ζημιών/τραυματιών","Έλεγχος κρίσιμων υποδομών","Προετοιμασία χώρου προσωρινής φιλοξενίας","Συντονισμός διανομής ειδών ανάγκης"]','["Μεταβείτε στο σημείο συγκέντρωσης και αναφέρετε κατάσταση περιοχής.","Στείλτε φωτογραφίες μόνο αν είναι ασφαλές.","Καταγράψτε τραυματίες, ζημιές και άμεσες ανάγκες."]','["Ποια περιοχή είχε τη μεγαλύτερη ανάγκη?","Ήταν λειτουργικά τα σημεία συγκέντρωσης?","Τι έλειψε από την πρώτη ώρα απόκρισης?"]'),
(6,19,'civil_protection','Πρωτόκολλο πλημμύρας',8,1,1,'Προτεραιότητα σε αποκλεισμούς επικίνδυνων σημείων, υποστήριξη πολιτών, αντλήσεις/μεταφορές και συνεχή ενημέρωση στάθμης υδάτων.','["Όχημα 4x4","Αντλίες","Γαλότσες/αδιάβροχα","Πρώτες βοήθειες","Σήμανση","Φακοί"]','["Όχημα 4x4","Αντλίες","Γαλότσες/αδιάβροχα","Πρώτες βοήθειες","Σήμανση","Φακοί"]','["Χαρτογράφηση πλημμυρισμένων δρόμων","Αποκλεισμός επικίνδυνων διελεύσεων","Έλεγχος εγκλωβισμένων/ευάλωτων πολιτών","Συντονισμός αντλήσεων και μεταφορών","Συνεχής ενημέρωση στάθμης νερού","Αίτημα φωτογραφιών από κρίσιμα σημεία"]','["Μην επιχειρείτε διέλευση από νερά με ρεύμα.","Στείλτε στίγμα και φωτογραφία επικίνδυνου σημείου.","Αναφέρετε άμεσα εγκλωβισμένους ή ανάγκη μεταφοράς."]','["Ποια σημεία χρειάζονται μόνιμη σήμανση?","Υπήρχαν αρκετές αντλίες/οχήματα?","Πώς λειτούργησε η ενημέρωση πολιτών?"]'),
(7,20,'civil_protection','Πρωτόκολλο κακοκαιρίας',6,1,1,'Οι ομάδες παραμένουν διαθέσιμες για κοπές δρόμων, υποστήριξη ευάλωτων πολιτών, μεταφορές και αναφορές ζημιών.','["Όχημα","Αλυσοπρίονο/εργαλεία","Πρώτες βοήθειες","Κουβέρτες","Φακοί","Ασύρματοι"]','["Όχημα","Αλυσοπρίονο/εργαλεία","Πρώτες βοήθειες","Κουβέρτες","Φακοί","Ασύρματοι"]','["Έλεγχος κρίσιμων δρόμων και υποδομών","Καταγραφή πεσμένων δέντρων/καλωδίων","Υποστήριξη ευάλωτων πολιτών","Προτεραιοποίηση μεταφορών","Συχνή ενημέρωση κατάστασης ομάδων"]','["Αναφέρετε άμεσα κλειστούς δρόμους ή επικίνδυνα σημεία.","Προτεραιότητα σε ευάλωτους πολίτες και ασφαλή μεταφορά.","Μην επιχειρείτε κοντά σε καλώδια/ασταθείς κατασκευές."]','["Ποιοι δρόμοι έκλεισαν πρώτοι?","Ποιος εξοπλισμός χρειάστηκε περισσότερο?","Πώς λειτούργησε η προτεραιοποίηση αναγκών?"]'),
(8,26,'coast_guard','Πρωτόκολλο θαλάσσιας διάσωσης',6,1,1,'Προσέλευση σε σημείο λιμένα/ακτής. Αναφέρετε σκάφος, διασώστες, πρώτες βοήθειες και δυνατότητα επικοινωνίας VHF/κινητού.','["Σκάφος","Ναυαγοσώστες/δύτες","Πρώτες βοήθειες","VHF/ασύρματοι","Ισοθερμικές κουβέρτες","Όχημα"]','["Σκάφος","Ναυαγοσώστες/δύτες","Πρώτες βοήθειες","VHF/ασύρματοι","Ισοθερμικές κουβέρτες","Όχημα"]','["Επιβεβαίωση τελευταίου γνωστού στίγματος","Ορισμός σημείου συγκέντρωσης σε ακτή/λιμένα","Καταγραφή διαθέσιμων σκαφών και πληρωμάτων","Συντονισμός τομέων έρευνας","Προετοιμασία υποδοχής διασωθέντων","Συνεχής ενημέρωση καιρού/θάλασσας"]','["Αναφέρετε διαθέσιμο σκάφος, πλήρωμα και ETA.","Στείλτε στίγμα/τελευταία γνωστή θέση.","Προτεραιότητα σε ασφάλεια πληρώματος και υποθερμία."]','["Ήταν σαφές το τελευταίο γνωστό στίγμα?","Υπήρχε επαρκής επικοινωνία με σκάφη?","Τι χρειάζεται για ταχύτερη υποδοχή διασωθέντων?"]'),
(9,28,'coast_guard','Πρωτόκολλο θαλάσσιας ρύπανσης',5,1,0,'Προσέλευση για επιτήρηση, φωτογραφική τεκμηρίωση και υποστήριξη αποκλεισμού/σήμανσης της περιοχής.','["Όχημα","Φωτογραφική τεκμηρίωση","Μέσα ατομικής προστασίας","Σήμανση","Σκάφος επιτήρησης"]','["Όχημα","Φωτογραφική τεκμηρίωση","Μέσα ατομικής προστασίας","Σήμανση","Σκάφος επιτήρησης"]','["Επιβεβαίωση έκτασης και τύπου ρύπανσης","Φωτογραφίες από ασφαλή σημεία","Σήμανση/αποκλεισμός ακτής όπου χρειάζεται","Καταγραφή ανέμου/ρεύματος","Ενημέρωση αρμόδιων συνεργείων απορρύπανσης"]','["Στείλτε φωτογραφία και στίγμα ρύπανσης.","Μην έρχεστε σε επαφή με άγνωστη ουσία χωρίς ΜΑΠ.","Αναφέρετε κατεύθυνση εξάπλωσης."]','["Πόσο γρήγορα ορίστηκε περίμετρος?","Ήταν επαρκής η τεκμηρίωση?","Ποια σημεία χρειάζονται επιπλέον επιτήρηση?"]'),
(16,14,'fire_service','Πρωτόκολλο παροχής βοήθειας',4,1,1,'Προσέλευση με βασικό εξοπλισμό υποστήριξης, όχημα όπου υπάρχει και δυνατότητα πρώτων βοηθειών. Αναφέρετε άμεσα το είδος βοήθειας που μπορείτε να προσφέρετε.','["Όχημα","Πρώτες βοήθειες","Φακοί","Ασύρματοι","Βασικά εργαλεία"]','["Όχημα","Πρώτες βοήθειες","Φακοί","Ασύρματοι","Βασικά εργαλεία"]','["Επιβεβαίωση ακριβούς ανάγκης και τοποθεσίας","Καταγραφή διαθέσιμων ομάδων και εξοπλισμού","Ορισμός ασφαλούς σημείου συνάντησης","Αίτημα κατάστασης από την πρώτη ομάδα που φτάνει","Ενημέρωση αν απαιτείται επιπλέον υπηρεσία"]','["Αναφέρετε διαθέσιμο προσωπικό, όχημα και ETA.","Στείλτε στίγμα όταν φτάσετε στο σημείο.","Μην επιχειρείτε χωρίς ασφαλή πρόσβαση."]','["Ήταν σαφής η ανάγκη βοήθειας?","Υπήρχε επαρκής εξοπλισμός?","Τι θα επιτάχυνε την ανταπόκριση?"]'),
(17,15,'fire_service','Πρωτόκολλο έρευνας αγνοουμένου',10,1,1,'Προσέλευση στο σημείο συντονισμού. Δηλώστε διαθέσιμα άτομα, οχήματα, φωτισμό, drone ή γνώση περιοχής. Καμία ομάδα δεν κινείται μόνη χωρίς ανάθεση τομέα.','["Ομάδες πεζής έρευνας","4x4","Drone","Φακοί","Ασύρματοι","Πρώτες βοήθειες","Χάρτες/τοπική γνώση"]','["Ομάδες πεζής έρευνας","4x4","Drone","Φακοί","Ασύρματοι","Πρώτες βοήθειες","Χάρτες/τοπική γνώση"]','["Συλλογή τελευταίου γνωστού σημείου και περιγραφής","Ορισμός τομέων έρευνας και υπευθύνων","Καταγραφή ομάδων εισόδου/εξόδου ανά τομέα","Αίτημα περιοδικού GPS από κάθε ομάδα","Σημείο πρώτων βοηθειών και παραλαβής ευρήματος","Συντονισμός με ΕΛΑΣ/Πυροσβεστική όπου απαιτείται"]','["Μην ξεκινήσετε έρευνα χωρίς ανάθεση τομέα.","Στείλτε GPS ανά 15 λεπτά και αναφέρετε κάθε εύρημα.","Κρατήστε ζεύγη ή μικρές ομάδες για λόγους ασφάλειας."]','["Ήταν σωστός ο χωρισμός τομέων?","Πόσο καλά λειτούργησε η αναφορά GPS?","Υπήρξαν κενά σε εξοπλισμό ή επικοινωνίες?"]'),
(18,16,'fire_service','Πρωτόκολλο πλημμυρικού συμβάντος',8,1,1,'Προσέλευση με προτεραιότητα στην ασφάλεια ομάδων, σήμανση, υποστήριξη εγκλωβισμένων και ενημέρωση για στάθμη υδάτων.','["4x4","Αντλίες","Γαλότσες/αδιάβροχα","Σήμανση","Πρώτες βοήθειες","Φακοί"]','["4x4","Αντλίες","Γαλότσες/αδιάβροχα","Σήμανση","Πρώτες βοήθειες","Φακοί"]','["Χαρτογράφηση πλημμυρισμένων σημείων","Αποκλεισμός επικίνδυνων διελεύσεων","Καταγραφή εγκλωβισμένων ή ευάλωτων πολιτών","Συντονισμός αντλήσεων/μεταφορών","Αίτημα φωτογραφιών από κρίσιμα σημεία","Συνεχής ενημέρωση στάθμης νερού"]','["Μην επιχειρείτε διέλευση από νερά με ρεύμα.","Στείλτε φωτογραφία και στίγμα επικίνδυνου σημείου.","Αναφέρετε άμεσα εγκλωβισμένους ή ανάγκη μεταφοράς."]','["Ποια σημεία ήταν πιο επικίνδυνα?","Υπήρχαν αρκετές αντλίες/οχήματα?","Πώς λειτούργησε η ενημέρωση ομάδων?"]'),
(19,17,'fire_service','Πρωτόκολλο επιφυλακής περιοχής',4,1,0,'Οι ομάδες καλύπτουν προκαθορισμένους τομείς, παραμένουν σε ετοιμότητα και στέλνουν περιοδική ενημέρωση κατάστασης.','["Όχημα περιπολίας","Ασύρματοι","Φακοί","Χάρτης περιοχής","Βασικό φαρμακείο"]','["Όχημα περιπολίας","Ασύρματοι","Φακοί","Χάρτης περιοχής","Βασικό φαρμακείο"]','["Ορισμός τομέων κάλυψης","Καταγραφή διαθέσιμων ομάδων και ωραρίων","Πρώτη αναφορά κατάστασης ανά τομέα","Περιοδικό check-in ανά 30 λεπτά","Καταγραφή συμβάντων ή ύποπτων σημείων","Ετοιμότητα για ανακατεύθυνση ομάδας"]','["Αναλάβετε τομέα και στείλτε πρώτη κατάσταση.","Στείλτε check-in ανά 30 λεπτά ή άμεσα αν υπάρξει συμβάν.","Παραμείνετε διαθέσιμοι για ανακατεύθυνση."]','["Ήταν σωστή η κάλυψη τομέων?","Υπήρξαν κενά χρόνου ή περιοχής?","Πόσο χρήσιμα ήταν τα περιοδικά check-ins?"]'),
(20,21,'civil_protection','Πρωτόκολλο εκκένωσης',12,1,1,'Προτεραιότητα στην ασφαλή καθοδήγηση πολιτών, ευάλωτων ομάδων και οχημάτων προς τα σημεία συγκέντρωσης.','["Οχήματα μεταφοράς","Πρώτες βοήθειες","Σήμανση","Ασύρματοι","Λίστες ευάλωτων πολιτών","Φακοί"]','["Οχήματα μεταφοράς","Πρώτες βοήθειες","Σήμανση","Ασύρματοι","Λίστες ευάλωτων πολιτών","Φακοί"]','["Επιβεβαίωση περιοχής εκκένωσης και διαδρομών","Ορισμός σημείων συγκέντρωσης","Ανάθεση ομάδων σε πόρτα-πόρτα ή κυκλοφορία","Καταγραφή ευάλωτων πολιτών και μεταφορών","Συντονισμός με ΕΛΑΣ/Δήμο/ΕΚΑΒ","Ενημέρωση ολοκλήρωσης ανά τομέα"]','["Μεταβείτε στον τομέα σας και αναφέρετε έναρξη εκκένωσης.","Προτεραιότητα σε ηλικιωμένους, ΑμεΑ και παιδιά.","Αναφέρετε μπλοκαρισμένες διαδρομές ή άρνηση αποχώρησης."]','["Ήταν σαφείς οι διαδρομές εκκένωσης?","Ποια ευάλωτη ομάδα χρειάστηκε περισσότερη υποστήριξη?","Τι πρέπει να αλλάξει στο σχέδιο εκκένωσης?"]'),
(21,22,'civil_protection','Πρωτόκολλο διανομής ειδών ανάγκης',6,1,0,'Οργανωμένη παραλαβή, καταγραφή, φόρτωση και διανομή ειδών ανάγκης με προτεραιότητα σε ευάλωτους πολίτες και απομονωμένες περιοχές.','["Όχημα μεταφοράς","Αποθήκη/σημείο διανομής","Λίστες παραληπτών","Κούτες/παλέτες","Εθελοντές καταγραφής"]','["Όχημα μεταφοράς","Αποθήκη/σημείο διανομής","Λίστες παραληπτών","Κούτες/παλέτες","Εθελοντές καταγραφής"]','["Καταγραφή διαθέσιμων ειδών και ποσοτήτων","Ορισμός σημείου παραλαβής/διανομής","Προτεραιοποίηση περιοχών και ευάλωτων πολιτών","Ανάθεση οχημάτων και δρομολογίων","Επιβεβαίωση παραδόσεων","Αναφορά ελλείψεων αποθέματος"]','["Αναφέρετε όχημα, χωρητικότητα και διαθέσιμα άτομα.","Παραλάβετε μόνο με καταγραφή ποσοτήτων.","Επιβεβαιώστε κάθε παράδοση με σύντομη αναφορά."]','["Ήταν σωστή η προτεραιοποίηση περιοχών?","Υπήρχαν ελλείψεις ειδών?","Πώς λειτούργησε η καταγραφή παραδόσεων?"]'),
(22,23,'civil_protection','Πρωτόκολλο υποστήριξης καταυλισμού',8,1,1,'Υποστήριξη προσωρινού χώρου φιλοξενίας με καταγραφή αναγκών, ροές πολιτών, πρώτες βοήθειες και διανομή ειδών.','["Πρώτες βοήθειες","Σκηνές/κουβέρτες","Νερό/τρόφιμα","Καταγραφή πολιτών","Φωτισμός","Όχημα"]','["Πρώτες βοήθειες","Σκηνές/κουβέρτες","Νερό/τρόφιμα","Καταγραφή πολιτών","Φωτισμός","Όχημα"]','["Έλεγχος χωρητικότητας και βασικών υποδομών","Ορισμός σημείων εισόδου/καταγραφής","Διαχωρισμός ευάλωτων πολιτών","Οργάνωση διανομής ειδών","Σημείο πρώτων βοηθειών","Περιοδική αναφορά αναγκών"]','["Μεταβείτε στο σημείο φιλοξενίας και αναφέρετε ανάγκες.","Καταγράψτε ευάλωτους πολίτες και άμεσες ελλείψεις.","Κρατήστε καθαρές ροές εισόδου και διανομής."]','["Ήταν επαρκείς οι υποδομές καταυλισμού?","Ποιες ανάγκες εμφανίστηκαν πρώτες?","Τι βελτιώνει την καταγραφή πολιτών?"]'),
(23,24,'civil_protection','Πρωτόκολλο ελέγχου υποδομών',5,1,0,'Έλεγχος κρίσιμων υποδομών με φωτογραφική τεκμηρίωση, στίγμα και ταξινόμηση κινδύνου. Δεν γίνεται επέμβαση χωρίς αρμόδιο τεχνικό όπου απαιτείται.','["Όχημα","Φωτογραφική τεκμηρίωση","Φακοί","Ασύρματοι","Μηχανικός/τεχνικός","Σήμανση"]','["Όχημα","Φωτογραφική τεκμηρίωση","Φακοί","Ασύρματοι","Μηχανικός/τεχνικός","Σήμανση"]','["Λίστα κρίσιμων υποδομών προς έλεγχο","Ανάθεση σημείων σε ομάδες","Φωτογραφία και στίγμα ανά σημείο","Ταξινόμηση κινδύνου χαμηλό/μέσο/υψηλό","Σήμανση επικίνδυνων περιοχών","Συγκεντρωτική αναφορά ευρημάτων"]','["Ελέγξτε μόνο το σημείο που σας ανατέθηκε και στείλτε φωτογραφία/στίγμα.","Μην εισέρχεστε σε ασταθή υποδομή.","Αναφέρετε άμεσα υψηλό κίνδυνο ή ανάγκη αποκλεισμού."]','["Ήταν πλήρης η λίστα υποδομών?","Πόσο χρήσιμη ήταν η φωτογραφική τεκμηρίωση?","Ποια σημεία χρειάζονται επανέλεγχο?"]'),
(24,25,'civil_protection','Πρωτόκολλο συντονισμού εθελοντών',4,0,0,'Στήσιμο κέντρου συντονισμού εθελοντών με καταγραφή παρουσιών, δεξιοτήτων, αναθέσεων και αλλαγών βάρδιας.','["Καταγραφή εθελοντών","Ασύρματοι/τηλέφωνα","Λίστες βαρδιών","Σημείο ενημέρωσης","Υπεύθυνος τομέων"]','["Καταγραφή εθελοντών","Ασύρματοι/τηλέφωνα","Λίστες βαρδιών","Σημείο ενημέρωσης","Υπεύθυνος τομέων"]','["Άνοιγμα σημείου καταγραφής εθελοντών","Καταγραφή δεξιοτήτων και διαθεσιμότητας","Ανάθεση ρόλων και τομέων","Ορισμός βαρδιών/αναπληρώσεων","Ενημέρωση ομάδων για κανάλια επικοινωνίας","Περιοδική σύνοψη διαθέσιμου δυναμικού"]','["Δηλώστε διαθέσιμα άτομα, δεξιότητες και χρόνο παραμονής.","Περιμένετε ανάθεση ρόλου πριν κινηθείτε.","Ενημερώστε άμεσα για αλλαγή διαθεσιμότητας."]','["Ήταν καθαρή η ανάθεση ρόλων?","Πόσο γρήγορα καταγράφηκαν οι εθελοντές?","Τι χρειάζεται για καλύτερες βάρδιες?"]'),
(25,27,'coast_guard','Πρωτόκολλο έρευνας στη θάλασσα',8,1,1,'Οργάνωση τομέων έρευνας με βάση το τελευταίο γνωστό στίγμα, καιρό, ρεύματα και διαθέσιμα σκάφη/παρατηρητές.','["Σκάφος","VHF/ασύρματοι","Παρατηρητές","Κιάλια","Πρώτες βοήθειες","Ισοθερμικές κουβέρτες","Όχημα ακτής"]','["Σκάφος","VHF/ασύρματοι","Παρατηρητές","Κιάλια","Πρώτες βοήθειες","Ισοθερμικές κουβέρτες","Όχημα ακτής"]','["Συλλογή τελευταίου γνωστού στίγματος","Έλεγχος καιρού, ανέμου και ρευμάτων","Καταγραφή διαθέσιμων σκαφών/πληρωμάτων","Ορισμός τομέων έρευνας","Περιοδική αναφορά θέσης και ευρημάτων","Σημείο υποδοχής σε λιμένα/ακτή"]','["Αναφέρετε σκάφος, πλήρωμα, καύσιμα και ETA.","Κινηθείτε μόνο στον ανατεθειμένο τομέα.","Στείλτε άμεσα στίγμα για κάθε εύρημα."]','["Ήταν σωστοί οι τομείς έρευνας?","Πόσο καλά λειτούργησε η επικοινωνία VHF/κινητού?","Τι επηρέασε περισσότερο την έρευνα?"]'),
(26,29,'coast_guard','Πρωτόκολλο ναυτικού ατυχήματος',8,1,1,'Υποστήριξη ναυτικού ατυχήματος με προτεραιότητα σε ασφάλεια επιβαινόντων, πρώτες βοήθειες, ρύπανση και ασφαλή πρόσβαση.','["Σκάφος","Πρώτες βοήθειες","VHF/ασύρματοι","Ισοθερμικές κουβέρτες","Αντιρρυπαντικά μέσα","Όχημα"]','["Σκάφος","Πρώτες βοήθειες","VHF/ασύρματοι","Ισοθερμικές κουβέρτες","Αντιρρυπαντικά μέσα","Όχημα"]','["Επιβεβαίωση τύπου ατυχήματος και αριθμού επιβαινόντων","Ορισμός ασφαλούς σημείου προσέγγισης","Καταγραφή τραυματιών/αγνοουμένων","Έλεγχος πιθανής ρύπανσης","Προετοιμασία υποδοχής διασωθέντων","Συντονισμός με λιμενικές/υγειονομικές αρχές"]','["Αναφέρετε διαθέσιμο μέσο, προσωπικό και ETA.","Προτεραιότητα σε τραυματίες και υποθερμία.","Αναφέρετε άμεσα καύσιμα, ρύπανση ή αγνοούμενους."]','["Ήταν σαφής ο αριθμός επιβαινόντων?","Υπήρξε επαρκής υποστήριξη πρώτων βοηθειών?","Τι χρειάζεται για ταχύτερη υποδοχή στο λιμάνι?"]'),
(27,30,'coast_guard','Πρωτόκολλο απεγκλωβισμού σκάφους',5,1,1,'Συνδρομή σε σκάφος με προτεραιότητα στην ασφάλεια επιβαινόντων, την αποφυγή ρύπανσης και την ασφαλή ρυμούλκηση/απεγκλωβισμό από αρμόδιους.','["Σκάφος υποστήριξης","VHF/ασύρματοι","Σχοινιά/ρυμούλκηση","Πρώτες βοήθειες","Αντιρρυπαντικά μέσα","Όχημα ακτής"]','["Σκάφος υποστήριξης","VHF/ασύρματοι","Σχοινιά/ρυμούλκηση","Πρώτες βοήθειες","Αντιρρυπαντικά μέσα","Όχημα ακτής"]','["Επιβεβαίωση θέσης και κατάστασης σκάφους","Έλεγχος επιβαινόντων και τραυματισμών","Έλεγχος καιρού/ρεύματος και κινδύνου ρύπανσης","Συντονισμός με κατάλληλο μέσο συνδρομής","Προετοιμασία σημείου παραλαβής αν χρειαστεί","Συνεχής ενημέρωση θέσης"]','["Στείλτε θέση, κατάσταση επιβαινόντων και κίνδυνο ρύπανσης.","Μην επιχειρείτε ρυμούλκηση χωρίς κατάλληλη εντολή/μέσο.","Αναφέρετε άμεσα αλλαγή καιρού ή εισροή υδάτων."]','["Ήταν επαρκές το διαθέσιμο μέσο συνδρομής?","Υπήρξε κίνδυνος ρύπανσης?","Τι δυσκόλεψε την προσέγγιση?"]'),
(28,31,'coast_guard','Πρωτόκολλο υποστήριξης λιμένα',5,1,0,'Υποστήριξη λειτουργίας λιμένα με έλεγχο ροών, ενημέρωση πολιτών, σήμανση και συνδρομή σε έκτακτες ανάγκες.','["Όχημα","Σήμανση","Ασύρματοι","Φακοί","Πρώτες βοήθειες","Ομάδα ενημέρωσης"]','["Όχημα","Σήμανση","Ασύρματοι","Φακοί","Πρώτες βοήθειες","Ομάδα ενημέρωσης"]','["Ορισμός σημείων ελέγχου/υποστήριξης","Καταγραφή ροών πολιτών και οχημάτων","Σήμανση επικίνδυνων ή κλειστών ζωνών","Προετοιμασία σημείου πρώτων βοηθειών","Ενημέρωση ομάδων για κανάλια επικοινωνίας","Περιοδική αναφορά κατάστασης λιμένα"]','["Αναλάβετε σημείο υποστήριξης και αναφέρετε κατάσταση.","Κρατήστε ανοιχτές κρίσιμες διελεύσεις.","Αναφέρετε άμεσα συνωστισμό, τραυματισμό ή αποκλεισμό."]','["Ποιο σημείο είχε τη μεγαλύτερη πίεση?","Ήταν επαρκής η σήμανση?","Τι χρειάζεται για καλύτερη ροή πολιτών/οχημάτων?"]'),
(29,32,'coast_guard','Πρωτόκολλο μεταφοράς και συνδρομής',4,1,1,'Οργάνωση ασφαλούς μεταφοράς ή συνδρομής με καταγραφή ατόμων, προορισμού, μέσου και κατάστασης υγείας.','["Όχημα","Σκάφος όπου απαιτείται","Πρώτες βοήθειες","Ισοθερμικές κουβέρτες","Ασύρματοι","Λίστα μεταφερομένων"]','["Όχημα","Σκάφος όπου απαιτείται","Πρώτες βοήθειες","Ισοθερμικές κουβέρτες","Ασύρματοι","Λίστα μεταφερομένων"]','["Επιβεβαίωση αιτήματος και αριθμού ατόμων","Καταγραφή αφετηρίας, προορισμού και μέσου","Έλεγχος ανάγκης πρώτων βοηθειών","Ανάθεση ομάδας/οχήματος/σκάφους","Επιβεβαίωση παραλαβής και άφιξης","Αναφορά τυχόν καθυστέρησης ή αλλαγής διαδρομής"]','["Αναφέρετε διαθέσιμο μέσο, άτομα και ETA.","Επιβεβαιώστε παραλαβή και άφιξη.","Αναφέρετε άμεσα ιατρική ανάγκη ή αλλαγή διαδρομής."]','["Ήταν σαφές το αίτημα συνδρομής?","Υπήρξε καθυστέρηση στη μεταφορά?","Τι χρειάζεται για ασφαλέστερη διαδικασία?"]');
