-- SynDrasi Migration 035 - playbook requested items
-- Turns playbook equipment/capability suggestions into editable requested items per mission.

ALTER TABLE event_playbooks
  ADD COLUMN IF NOT EXISTS requested_items_json TEXT NULL AFTER capabilities_json;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS requested_items_json TEXT NULL AFTER requested_medical_equipment;

UPDATE event_playbooks
SET requested_items_json = capabilities_json
WHERE requested_items_json IS NULL
  AND capabilities_json IS NOT NULL
  AND capabilities_json <> '';
