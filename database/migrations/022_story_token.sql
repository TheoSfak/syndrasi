-- SynDrasi Migration 022 — Public Story share token
-- A per-event token that lets anyone (no login) view the public version of the
-- Story/Απολογισμός at /public/story/{token}, including its media.
-- Run in phpMyAdmin against the syndrasi database.

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS story_token VARCHAR(64) NULL COMMENT 'no-login public Story link',
  ADD COLUMN IF NOT EXISTS story_published_at DATETIME NULL;

ALTER TABLE events ADD INDEX idx_events_story_token (story_token);
