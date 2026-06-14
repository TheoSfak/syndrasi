-- ============================================================
-- SynDrasi - Migration 003: closed status + reconciliation
-- Run once against the syndrasi database.
-- ============================================================

USE syndrasi;

-- Add 'closed' status to events (between 'active' and 'completed')
ALTER TABLE events
  MODIFY COLUMN status ENUM('draft','open','review','confirmed','active','closed','completed','cancelled')
    NOT NULL DEFAULT 'draft';

-- Reconciliation notes on the event level
ALTER TABLE events
  ADD COLUMN IF NOT EXISTS reconciliation_notes TEXT NULL
    COMMENT 'Σημειώσεις δήμου κατά την αρχειοθέτηση';

-- Per-application reconciliation: actual presence data
ALTER TABLE event_applications
  ADD COLUMN IF NOT EXISTS actual_people       INT NULL
    COMMENT 'Πραγματικά άτομα που παρέστησαν',
  ADD COLUMN IF NOT EXISTS actual_arrival_time   DATETIME NULL
    COMMENT 'Πραγματική ώρα άφιξης',
  ADD COLUMN IF NOT EXISTS actual_departure_time DATETIME NULL
    COMMENT 'Πραγματική ώρα αναχώρησης';
