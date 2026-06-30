-- ============================================================
-- SynDrasi - Database schema (MySQL 8 / MariaDB 10.5+)
-- Run:  mysql -u root -p < schema.sql
--
-- ⚠️ FRESH INSTALL — IMPORTANT:
-- This file is the BASE schema. Newer features live in database/migrations/.
-- After importing this file you MUST run ALL migration files in order, e.g.:
--     for f in database/migrations/0*.sql; do mysql -u root syndrasi < "$f"; done
-- (The in-app self-updater auto-runs pending migrations on EXISTING installs;
--  a brand-new DB created from this file still needs the migrations applied once.)
-- Tables added by migrations include: team_members, event_application_members,
-- team_debriefs, event_templates, mobilizations, photo_requests, event_photos,
-- sos_alerts, event_messages, event_room_messages (013–015 add field_token /
-- geo columns). See DEPLOY.md → "Fresh install".
-- ============================================================

CREATE DATABASE IF NOT EXISTS syndrasi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE syndrasi;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS event_reports;
DROP TABLE IF EXISTS shortage_reports;
DROP TABLE IF EXISTS location_pings;
DROP TABLE IF EXISTS operational_checkins;
DROP TABLE IF EXISTS operational_notes;
DROP TABLE IF EXISTS event_applications;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS event_categories;
DROP TABLE IF EXISTS volunteer_teams;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS municipalities;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS municipality_settings;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
CREATE TABLE municipalities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
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
  address VARCHAR(255) NULL,
  has_vehicle TINYINT(1) NOT NULL DEFAULT 0,
  has_medical_equipment TINYINT(1) NOT NULL DEFAULT 0,
  default_people_capacity INT NULL,
  notes TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_teams_municipality_id (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE event_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
  instructions TEXT NULL,
  status ENUM('draft','open','review','confirmed','active','closed','completed','cancelled') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_events_municipality_id (municipality_id),
  INDEX idx_events_status (status),
  INDEX idx_events_start_datetime (start_datetime),
  INDEX idx_events_muni_status_start (municipality_id, status, start_datetime)
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
  UNIQUE KEY unique_event_team (event_id, team_id),
  INDEX idx_applications_municipality_id (municipality_id),
  INDEX idx_applications_event_id (event_id),
  INDEX idx_applications_team_id (team_id),
  INDEX idx_applications_status (status),
  INDEX idx_apps_event_status (event_id, status),
  INDEX idx_apps_team_status (team_id, status)
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
-- Default categories
INSERT INTO event_categories (name) VALUES
('Πολιτιστική εκδήλωση'),
('Συναυλία'),
('Αθλητική δράση'),
('Κοινωνική δράση'),
('Εορταστική δράση'),
('Άλλη δράση');
