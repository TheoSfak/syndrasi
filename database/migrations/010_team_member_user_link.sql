-- SynDrasi Migration 010 — Link roster members to login accounts
-- Lets a team_member be associated with a users row so emergency call-outs can
-- web-push them directly (and, later, let them respond while logged in).
-- Backfills the link by matching email within the same municipality.
-- Run in phpMyAdmin against the syndrasi database.

ALTER TABLE team_members
  ADD COLUMN user_id INT NULL AFTER municipality_id,
  ADD INDEX idx_tm_user (user_id);

UPDATE team_members tm
JOIN users u
  ON u.email = tm.email
 AND u.municipality_id = tm.municipality_id
SET tm.user_id = u.id
WHERE tm.user_id IS NULL
  AND tm.email IS NOT NULL
  AND tm.email <> '';
