-- SynDrasi Migration 029 — sanitize demo volunteer emails
-- Replace volunteer/team contact emails with non-deliverable demo addresses.
-- Keep only explicitly allowed real addresses.

UPDATE users
SET email = CONCAT('demo-user-', id, '@syndrasi.local')
WHERE role = 'team_admin'
  AND email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');

UPDATE team_members
SET email = CONCAT('demo-member-', id, '@syndrasi.local')
WHERE email IS NOT NULL
  AND email <> ''
  AND email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');

UPDATE volunteer_teams
SET email = CONCAT('demo-team-', id, '@syndrasi.local')
WHERE email IS NOT NULL
  AND email <> ''
  AND email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');

UPDATE mail_queue
SET to_email = CONCAT('demo-mail-', id, '@syndrasi.local')
WHERE to_email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');

UPDATE password_resets
SET email = CONCAT('demo-reset-', id, '@syndrasi.local')
WHERE email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');
