-- SynDrasi Migration 037 - dedicated rate_limits table
-- Login/reset rate-limit counters were being stored as rows in app_settings
-- (login_fail_*, login_lock_*, reset_req_*), an unbounded key/value dumping
-- ground shared with real config. Give them their own table with an index
-- for the periodic cleanup sweep.

CREATE TABLE IF NOT EXISTS rate_limits (
  rate_key   VARCHAR(100) NOT NULL PRIMARY KEY,
  value      TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rate_limits_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
