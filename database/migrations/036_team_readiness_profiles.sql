-- SynDrasi Migration 036 - team readiness profiles
-- Stores operational capabilities/equipment per team for mission matching.

ALTER TABLE volunteer_teams
  ADD COLUMN IF NOT EXISTS readiness_items_json TEXT NULL AFTER default_people_capacity;
