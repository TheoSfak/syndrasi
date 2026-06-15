-- 008_app_settings_timestamp.sql
-- app_settings.updated_at previously had only `ON UPDATE CURRENT_TIMESTAMP`
-- (no DEFAULT), so freshly INSERTed rows were left NULL. That made it
-- impossible to age-out transient rows (login throttling counters,
-- shift-reminder flags) in /cron/cleanup. Give it a DEFAULT and backfill.

ALTER TABLE app_settings
  MODIFY updated_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

UPDATE app_settings SET updated_at = NOW() WHERE updated_at IS NULL;
