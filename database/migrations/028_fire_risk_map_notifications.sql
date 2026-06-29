-- SynDrasi Migration 028 — Civil Protection fire-risk map Telegram dedupe

CREATE TABLE IF NOT EXISTS fire_risk_map_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  map_date DATE NOT NULL,
  levels_json TEXT NOT NULL,
  image_url VARCHAR(500) NOT NULL,
  telegram_notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fire_risk_notify_muni_date (municipality_id, map_date),
  INDEX idx_fire_risk_notify_at (telegram_notified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
