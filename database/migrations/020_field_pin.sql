-- SynDrasi Migration 020 — Optional field PIN gate
-- A short 4-digit PIN on the mission-commander field link. When set, the first
-- visit on a device asks for the PIN; after a correct entry the device is
-- remembered (signed cookie). Backwards compatible: NULL pin = no gate.
-- Run in phpMyAdmin against the syndrasi database.

ALTER TABLE event_applications
  ADD COLUMN IF NOT EXISTS field_pin VARCHAR(8) NULL COMMENT '4-digit gate for the field link (NULL = no gate)';
