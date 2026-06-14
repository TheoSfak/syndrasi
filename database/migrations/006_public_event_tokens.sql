-- SynDrasi Migration 006 — Public event tokens
-- Adds a shareable read-only token to every event.
-- Run in phpMyAdmin against the syndrasi database.

ALTER TABLE events
  ADD COLUMN public_token VARCHAR(64) NULL UNIQUE AFTER status;

-- Backfill all existing events with a unique 32-char token (UUID without dashes)
UPDATE events
SET public_token = LOWER(REPLACE(UUID(), '-', ''))
WHERE public_token IS NULL;
