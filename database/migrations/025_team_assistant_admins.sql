-- SynDrasi Migration 025 — Assistant team admins
-- Distinguishes assistant chiefs from the primary team chief while reusing
-- the existing users.role = 'team_admin' access model.

ALTER TABLE team_members
  ADD COLUMN IF NOT EXISTS is_assistant_admin TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = Βοηθός Αρχηγού with team_admin login access' AFTER is_team_admin,
  ADD COLUMN IF NOT EXISTS assistant_promoted_at DATETIME NULL AFTER is_assistant_admin,
  ADD COLUMN IF NOT EXISTS assistant_promoted_by INT NULL AFTER assistant_promoted_at,
  ADD INDEX IF NOT EXISTS idx_tm_assistant_admin (team_id, is_assistant_admin, status);
