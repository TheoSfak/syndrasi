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
DROP TABLE IF EXISTS event_playbooks;
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
  address VARCHAR(255) NULL,
  has_vehicle TINYINT(1) NOT NULL DEFAULT 0,
  has_medical_equipment TINYINT(1) NOT NULL DEFAULT 0,
  default_people_capacity INT NULL,
  readiness_items_json TEXT NULL,
  notes TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_teams_municipality_id (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
CREATE TABLE event_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  authority_type ENUM('municipality','civil_protection','fire_service','coast_guard') NOT NULL DEFAULT 'municipality',
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_event_category_authority_name (authority_type, name),
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
INSERT INTO event_categories (authority_type, name) VALUES
('municipality', 'Πολιτιστική εκδήλωση'),
('municipality', 'Συναυλία'),
('municipality', 'Αθλητική δράση'),
('municipality', 'Κοινωνική δράση'),
('municipality', 'Εορταστική δράση'),
('municipality', 'Περιβαλλοντική δράση'),
('municipality', 'Υποστήριξη εκδήλωσης'),
('municipality', 'Έκτακτη ανάγκη'),
('municipality', 'Άλλη δράση'),
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

INSERT INTO event_playbooks
(category_id, authority_type, title, default_people, require_vehicle, require_medical, instructions, capabilities_json, checklist_json, messages_json, debrief_questions_json)
SELECT c.id, p.authority_type, p.title, p.default_people, p.require_vehicle, p.require_medical,
       p.instructions, p.capabilities_json, p.checklist_json, p.messages_json, p.debrief_questions_json
FROM (
  SELECT 'fire_service' authority_type, 'Πυρκαγιά δασική' category_name, 'Πρωτόκολλο δασικής πυρκαγιάς' title, 8 default_people, 1 require_vehicle, 1 require_medical,
         'Άμεση προσέλευση στο σημείο συγκέντρωσης. Φέρτε ΜΑΠ, νερό, ασύρματο/φορτισμένο κινητό και ενημερώστε για διαθέσιμο όχημα. Ο υπεύθυνος ομάδας στέλνει στίγμα ανά 15 λεπτά.' instructions,
         '["Πυροσβεστικό/υδροφόρο όχημα","4x4","Ασύρματοι","Πρώτες βοήθειες","Drone/παρατήρηση","Φακοί/ΜΑΠ"]' capabilities_json,
         '["Επιβεβαίωση σημείου συγκέντρωσης και ασφαλούς πρόσβασης","Καταγραφή ανέμου, καπνού και ορατότητας","Ορισμός τομέων ομάδων και υπευθύνων","Αίτημα πρώτου GPS από όλες τις ομάδες","Έλεγχος διαθέσιμων οχημάτων/νερού","Προετοιμασία μηνύματος εκκένωσης αν χρειαστεί"]' checklist_json,
         '["Στείλτε άμεσα διαθέσιμο προσωπικό, οχήματα και ETA.","Αποστείλετε GPS στίγμα και κατάσταση πρόσβασης.","Κρατήστε απόσταση ασφαλείας και αναφέρετε αλλαγή ανέμου/καπνού."]' messages_json,
         '["Υπήρξε ασφαλής πρόσβαση στο συμβάν?","Ποιος εξοπλισμός έλειψε περισσότερο?","Πώς αξιολογείτε τον τομέα/συντονισμό?","Τι πρέπει να προστεθεί στο πρωτόκολλο?"]' debrief_questions_json
  UNION ALL SELECT 'fire_service','Πυρκαγιά αστική','Πρωτόκολλο αστικής πυρκαγιάς',6,1,1,'Προσέλευση με προτεραιότητα στην ασφάλεια πολιτών. Δηλώστε διαθέσιμα οχήματα, πρώτες βοήθειες και δυνατότητα αποκλεισμού δρόμων.','["Όχημα","Πρώτες βοήθειες","Ασύρματοι","Φακοί","Κώνοι/σήμανση","Υποστήριξη εκκένωσης"]','["Επιβεβαίωση ακριβούς διεύθυνσης και πρόσβασης","Έλεγχος ανάγκης εκκένωσης/αποκλεισμού","Ορισμός ζώνης ασφαλείας","Καταγραφή ευάλωτων πολιτών","Συντονισμός με ΕΚΑΒ/ΕΛΑΣ όπου απαιτείται"]','["Προσέλθετε στο σημείο στάθμευσης και αναφέρετε διαθέσιμο προσωπικό.","Μην εισέρχεστε σε κτίριο χωρίς εντολή αρμόδιου επικεφαλής.","Αναφέρετε άμεσα εγκλωβισμένους ή ανάγκη πρώτων βοηθειών."]','["Ήταν σαφείς οι ζώνες ασφαλείας?","Υπήρξε ανάγκη εκκένωσης?","Τι δυσκόλεψε την πρόσβαση?"]'
  UNION ALL SELECT 'fire_service','Απεγκλωβισμός','Πρωτόκολλο απεγκλωβισμού',5,1,1,'Προσέλευση με εξοπλισμό πρώτων βοηθειών, φακούς και δυνατότητα ασφαλούς περίμετρου. Αναφέρετε ειδικό εξοπλισμό που διαθέτετε.','["Πρώτες βοήθειες","Φακοί","Κοπή/διάνοιξη","Σχοινιά","Όχημα","Σήμανση"]','["Επιβεβαίωση αριθμού εμπλεκομένων","Ασφαλής περίμετρος και πρόσβαση διασωστών","Συντονισμός με αρμόδια υπηρεσία","Καταγραφή κινδύνων ηλεκτρικού/καυσίμων/κατάρρευσης","Προετοιμασία σημείου παραλαβής τραυματιών"]','["Αναφέρετε ειδικό εξοπλισμό και ETA.","Διατηρήστε ελεύθερη πρόσβαση για οχήματα διάσωσης.","Στείλτε άμεσα εικόνα/στίγμα αν είναι ασφαλές."]','["Ποιος κίνδυνος ήταν πιο κρίσιμος?","Υπήρξε επαρκής εξοπλισμός?","Πόσο γρήγορα ορίστηκε ασφαλής περίμετρος?"]'
  UNION ALL SELECT 'fire_service','Τροχαίο','Πρωτόκολλο τροχαίου συμβάντος',4,1,1,'Προσέλευση με προτεραιότητα σε σήμανση, πρώτες βοήθειες και ασφαλή διαχείριση κυκλοφορίας.','["Πρώτες βοήθειες","Σήμανση/κώνοι","Φακοί","Όχημα","Ασύρματοι"]','["Επιβεβαίωση σημείου και λωρίδων κυκλοφορίας","Σήμανση προσέγγισης και ζώνη ασφαλείας","Καταγραφή τραυματιών/εγκλωβισμένων","Συντονισμός με ΕΚΑΒ/Τροχαία","Ενημέρωση ομάδων για ασφαλές σημείο στάθμευσης"]','["Προσέγγιση μόνο από ασφαλή κατεύθυνση.","Αναφέρετε τραυματίες, εγκλωβισμένους και ανάγκη σήμανσης.","Κρατήστε ελεύθερη δίοδο για ΕΚΑΒ/Πυροσβεστική."]','["Ήταν επαρκής η σήμανση?","Πόσο γρήγορα ενημερώθηκε η κυκλοφορία?","Υπήρξε έλλειψη εξοπλισμού πρώτων βοηθειών?"]'
  UNION ALL SELECT 'civil_protection','Σεισμός','Πρωτόκολλο σεισμού',10,1,1,'Οι ομάδες κατευθύνονται στα προκαθορισμένα σημεία συγκέντρωσης. Προτεραιότητα: ασφάλεια πολιτών, πρώτες βοήθειες, έλεγχος υποδομών και αναφορά ζημιών.','["Πρώτες βοήθειες","Όχημα","Ασύρματοι","Φακοί","Σκηνές/κουβέρτες","Μηχανικός/τεχνικός έλεγχος"]','["Ενεργοποίηση σημείων συγκέντρωσης","Αίτημα κατάστασης από όλες τις ομάδες","Καταγραφή αναφορών ζημιών/τραυματιών","Έλεγχος κρίσιμων υποδομών","Προετοιμασία χώρου προσωρινής φιλοξενίας","Συντονισμός διανομής ειδών ανάγκης"]','["Μεταβείτε στο σημείο συγκέντρωσης και αναφέρετε κατάσταση περιοχής.","Στείλτε φωτογραφίες μόνο αν είναι ασφαλές.","Καταγράψτε τραυματίες, ζημιές και άμεσες ανάγκες."]','["Ποια περιοχή είχε τη μεγαλύτερη ανάγκη?","Ήταν λειτουργικά τα σημεία συγκέντρωσης?","Τι έλειψε από την πρώτη ώρα απόκρισης?"]'
  UNION ALL SELECT 'civil_protection','Πλημμύρα','Πρωτόκολλο πλημμύρας',8,1,1,'Προτεραιότητα σε αποκλεισμούς επικίνδυνων σημείων, υποστήριξη πολιτών, αντλήσεις/μεταφορές και συνεχή ενημέρωση στάθμης υδάτων.','["Όχημα 4x4","Αντλίες","Γαλότσες/αδιάβροχα","Πρώτες βοήθειες","Σήμανση","Φακοί"]','["Χαρτογράφηση πλημμυρισμένων δρόμων","Αποκλεισμός επικίνδυνων διελεύσεων","Έλεγχος εγκλωβισμένων/ευάλωτων πολιτών","Συντονισμός αντλήσεων και μεταφορών","Συνεχής ενημέρωση στάθμης νερού","Αίτημα φωτογραφιών από κρίσιμα σημεία"]','["Μην επιχειρείτε διέλευση από νερά με ρεύμα.","Στείλτε στίγμα και φωτογραφία επικίνδυνου σημείου.","Αναφέρετε άμεσα εγκλωβισμένους ή ανάγκη μεταφοράς."]','["Ποια σημεία χρειάζονται μόνιμη σήμανση?","Υπήρχαν αρκετές αντλίες/οχήματα?","Πώς λειτούργησε η ενημέρωση πολιτών?"]'
  UNION ALL SELECT 'civil_protection','Κακοκαιρία','Πρωτόκολλο κακοκαιρίας',6,1,1,'Οι ομάδες παραμένουν διαθέσιμες για κοπές δρόμων, υποστήριξη ευάλωτων πολιτών, μεταφορές και αναφορές ζημιών.','["Όχημα","Αλυσοπρίονο/εργαλεία","Πρώτες βοήθειες","Κουβέρτες","Φακοί","Ασύρματοι"]','["Έλεγχος κρίσιμων δρόμων και υποδομών","Καταγραφή πεσμένων δέντρων/καλωδίων","Υποστήριξη ευάλωτων πολιτών","Προτεραιοποίηση μεταφορών","Συχνή ενημέρωση κατάστασης ομάδων"]','["Αναφέρετε άμεσα κλειστούς δρόμους ή επικίνδυνα σημεία.","Προτεραιότητα σε ευάλωτους πολίτες και ασφαλή μεταφορά.","Μην επιχειρείτε κοντά σε καλώδια/ασταθείς κατασκευές."]','["Ποιοι δρόμοι έκλεισαν πρώτοι?","Ποιος εξοπλισμός χρειάστηκε περισσότερο?","Πώς λειτούργησε η προτεραιοποίηση αναγκών?"]'
  UNION ALL SELECT 'coast_guard','Θαλάσσια διάσωση','Πρωτόκολλο θαλάσσιας διάσωσης',6,1,1,'Προσέλευση σε σημείο λιμένα/ακτής. Αναφέρετε σκάφος, διασώστες, πρώτες βοήθειες και δυνατότητα επικοινωνίας VHF/κινητού.','["Σκάφος","Ναυαγοσώστες/δύτες","Πρώτες βοήθειες","VHF/ασύρματοι","Ισοθερμικές κουβέρτες","Όχημα"]','["Επιβεβαίωση τελευταίου γνωστού στίγματος","Ορισμός σημείου συγκέντρωσης σε ακτή/λιμένα","Καταγραφή διαθέσιμων σκαφών και πληρωμάτων","Συντονισμός τομέων έρευνας","Προετοιμασία υποδοχής διασωθέντων","Συνεχής ενημέρωση καιρού/θάλασσας"]','["Αναφέρετε διαθέσιμο σκάφος, πλήρωμα και ETA.","Στείλτε στίγμα/τελευταία γνωστή θέση.","Προτεραιότητα σε ασφάλεια πληρώματος και υποθερμία."]','["Ήταν σαφές το τελευταίο γνωστό στίγμα?","Υπήρχε επαρκής επικοινωνία με σκάφη?","Τι χρειάζεται για ταχύτερη υποδοχή διασωθέντων?"]'
  UNION ALL SELECT 'coast_guard','Ρύπανση','Πρωτόκολλο θαλάσσιας ρύπανσης',5,1,0,'Προσέλευση για επιτήρηση, φωτογραφική τεκμηρίωση και υποστήριξη αποκλεισμού/σήμανσης της περιοχής.','["Όχημα","Φωτογραφική τεκμηρίωση","Μέσα ατομικής προστασίας","Σήμανση","Σκάφος επιτήρησης"]','["Επιβεβαίωση έκτασης και τύπου ρύπανσης","Φωτογραφίες από ασφαλή σημεία","Σήμανση/αποκλεισμός ακτής όπου χρειάζεται","Καταγραφή ανέμου/ρεύματος","Ενημέρωση αρμόδιων συνεργείων απορρύπανσης"]','["Στείλτε φωτογραφία και στίγμα ρύπανσης.","Μην έρχεστε σε επαφή με άγνωστη ουσία χωρίς ΜΑΠ.","Αναφέρετε κατεύθυνση εξάπλωσης."]','["Πόσο γρήγορα ορίστηκε περίμετρος?","Ήταν επαρκής η τεκμηρίωση?","Ποια σημεία χρειάζονται επιπλέον επιτήρηση?"]'
) p
JOIN event_categories c ON c.authority_type = p.authority_type AND c.name = p.category_name;

