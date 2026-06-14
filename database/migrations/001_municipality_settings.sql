-- Migration 001: per-municipality settings (SMTP κ.λπ.)
-- Run: mysql -u root -p syndrasi < database/migrations/001_municipality_settings.sql

USE syndrasi;

CREATE TABLE IF NOT EXISTS municipality_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_mun_key (municipality_id, setting_key),
  INDEX idx_msettings_mid (municipality_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
