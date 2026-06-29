-- SynDrasi Migration 027 — Fire Service Telegram notification dedupe

CREATE TABLE IF NOT EXISTS fire_service_incident_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fire_service_incident_id INT NOT NULL,
  municipality_id INT NOT NULL,
  status_label VARCHAR(80) NOT NULL,
  telegram_notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fire_notify_incident_muni_status (fire_service_incident_id, municipality_id, status_label),
  INDEX idx_fire_notify_muni_at (municipality_id, telegram_notified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
