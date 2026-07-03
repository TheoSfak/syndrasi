-- SynDrasi Migration 040 - live-ops change detection
-- events.last_activity_at is bumped by every operational write (messages,
-- SOS, shortages, notes, media, resource requests, check-ins, application
-- decisions) so the 3-second ops poll can short-circuit to a cheap
-- "no change" response instead of rebuilding the full snapshot.
-- Distinct from updated_at, which means "the event record was edited".
-- Location pings intentionally do NOT bump it (highest-frequency write);
-- the poll's change signature tracks MAX(location_pings.id) separately.

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS last_activity_at DATETIME NULL AFTER updated_at;
