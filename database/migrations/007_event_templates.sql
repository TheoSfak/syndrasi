-- Migration 007: Event Templates
-- Reusable event blueprints (core fields + shift structure) per municipality.
-- Shifts are stored as JSON with offsets (in minutes) relative to the event start,
-- so they can be re-created with concrete datetimes when a new event is spun up.

CREATE TABLE event_templates (
  id                          INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id             INT NOT NULL,
  name                        VARCHAR(255) NOT NULL,
  title                       VARCHAR(255) NULL,
  category_id                 INT NULL,
  description                 TEXT NULL,
  location_name               VARCHAR(255) NULL,
  address                     VARCHAR(255) NULL,
  latitude                    DECIMAL(10,7) NULL,
  longitude                   DECIMAL(10,7) NULL,
  requested_people            INT NOT NULL DEFAULT 0,
  requested_vehicle           TINYINT(1) NOT NULL DEFAULT 0,
  requested_medical_equipment TINYINT(1) NOT NULL DEFAULT 0,
  instructions                TEXT NULL,
  shifts_json                 TEXT NULL,
  created_by                  INT NOT NULL,
  created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_event_templates_municipality_id (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
