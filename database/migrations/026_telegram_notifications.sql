-- SynDrasi Migration 026 — Telegram notification support

ALTER TABLE volunteer_teams
  ADD COLUMN IF NOT EXISTS telegram_chat_id VARCHAR(80) NULL
    COMMENT 'Telegram group/channel chat_id for team notifications' AFTER phone,
  ADD INDEX IF NOT EXISTS idx_teams_telegram_chat (telegram_chat_id);
