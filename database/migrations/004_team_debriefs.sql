-- Migration 004: Team Debriefs
-- Each approved team submits a post-event debrief when the event is completed.

CREATE TABLE team_debriefs (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  event_id              INT NOT NULL,
  team_id               INT NOT NULL,
  municipality_id       INT NOT NULL,
  submitted_by          INT NOT NULL,

  -- Quantitative
  actual_volunteers     INT NOT NULL DEFAULT 0,
  volunteer_hours       DECIMAL(6,1) NOT NULL DEFAULT 0,
  incidents_count       INT NOT NULL DEFAULT 0,

  -- Qualitative
  what_went_well        TEXT NULL,
  what_went_wrong       TEXT NULL,
  incidents_description TEXT NULL,

  -- Rating
  organization_rating   TINYINT NOT NULL DEFAULT 3 COMMENT '1-5 stars',
  comments              TEXT NULL,

  submitted_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY unique_event_team (event_id, team_id),
  INDEX idx_debriefs_event_id (event_id),
  INDEX idx_debriefs_team_id (team_id),
  INDEX idx_debriefs_municipality_id (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
