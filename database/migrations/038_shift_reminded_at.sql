-- SynDrasi Migration 038 - shift reminder dedup flag
-- CronController::shiftReminders() used one app_settings row per shift
-- (shift_reminded_<id>), forever, checked via a NOT EXISTS subquery on every
-- run. The flag belongs on the shift it describes.

ALTER TABLE event_shifts
  ADD COLUMN IF NOT EXISTS reminded_at DATETIME NULL AFTER notes;
