-- SynDrasi Migration 030 — demo team/member email scheme and team passwords
-- Keep only explicitly allowed real addresses.

UPDATE users
SET email = CONCAT('omada', team_id, '@syndrasi.local'),
    password_hash = '$2y$10$e2ElMBvn3UKMUcM2mW082.bVCLOwXe3PAD4oECkLWtStbvFS1HjkW'
WHERE role = 'team_admin'
  AND team_id IS NOT NULL
  AND email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');

UPDATE users u
JOIN team_members tm ON tm.user_id = u.id
SET u.password_hash = '$2y$10$e2ElMBvn3UKMUcM2mW082.bVCLOwXe3PAD4oECkLWtStbvFS1HjkW'
WHERE u.email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');

UPDATE volunteer_teams
SET email = CONCAT('omada', id, '@syndrasi.local')
WHERE email IS NOT NULL
  AND email <> ''
  AND email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');

UPDATE team_members tm
JOIN (
  SELECT tm1.id,
         CONCAT('omada', tm1.team_id, '_', COUNT(tm2.id), '@syndrasi.local') AS demo_email
  FROM team_members tm1
  JOIN team_members tm2
    ON tm2.team_id = tm1.team_id
   AND tm2.id <= tm1.id
   AND tm2.email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com')
  WHERE tm1.email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com')
  GROUP BY tm1.id, tm1.team_id
) numbered ON numbered.id = tm.id
SET tm.email = numbered.demo_email
WHERE tm.email IS NOT NULL
  AND tm.email <> ''
  AND tm.email NOT IN ('theodore.sfakianakis@gmail.com', 'irmaiden@gmail.com');
