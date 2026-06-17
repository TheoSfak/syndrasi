-- SynDrasi Migration 014 — Geo points on event messages
-- Lets the municipality send a map point to teams during an event:
--   move     = "go to this point" (relocation order, needs ACK)
--   incident = "incident at this point" (urgent, forced push+SMS, needs ACK)
--   poi      = point of interest (informational)
-- Run in phpMyAdmin (or Platform Settings → Updates → run pending).

ALTER TABLE event_messages
  ADD COLUMN latitude   DECIMAL(10,7) NULL,
  ADD COLUMN longitude  DECIMAL(10,7) NULL,
  ADD COLUMN point_kind ENUM('move','incident','poi') NULL;
