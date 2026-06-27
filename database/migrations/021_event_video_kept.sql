-- SynDrasi Migration 021 — Keep story media (exclude from auto-purge)
-- Videos that belong to an event whose Story/Απολογισμός has been generated are
-- flagged kept=1 and are NEVER removed by the 7-day auto-purge.
-- Run in phpMyAdmin against the syndrasi database.

ALTER TABLE event_videos
  ADD COLUMN IF NOT EXISTS kept TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = κρατιέται μόνιμα (μπήκε σε Story)';
