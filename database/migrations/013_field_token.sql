-- SynDrasi Migration 013 — Mission Commander field link (no-login token)
-- The team leader assigns a Mission Commander (a team_member) per event application.
-- That commander operates in the field without an app account, via a personal token
-- link /f/{token}. This adds the per-application field token.
-- Run in phpMyAdmin (or via Platform Settings → Updates → run pending).

ALTER TABLE event_applications
  ADD COLUMN IF NOT EXISTS field_token VARCHAR(64) NULL COMMENT 'no-login link for the mission commander';

-- Helpful for token lookups (ignore error if it already exists)
ALTER TABLE event_applications
  ADD INDEX idx_apps_field_token (field_token);
