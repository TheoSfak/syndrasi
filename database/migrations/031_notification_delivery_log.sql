-- SynDrasi Migration 031 - notification delivery log
-- Unified operational history for email/SMS/Telegram/push deliveries.

CREATE TABLE IF NOT EXISTS notification_deliveries (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipality_id   INT UNSIGNED NULL,
    channel           ENUM('email','sms','telegram','push') NOT NULL,
    status            ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
    recipient_user_id INT UNSIGNED NULL,
    team_id           INT UNSIGNED NULL,
    event_id          INT UNSIGNED NULL,
    recipient_label   VARCHAR(255) NULL,
    recipient_address VARCHAR(255) NULL,
    title             VARCHAR(255) NOT NULL DEFAULT '',
    message           MEDIUMTEXT NULL,
    type              VARCHAR(100) NULL,
    external_ref      VARCHAR(255) NULL,
    attempts          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    error_msg         TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at           DATETIME NULL,
    INDEX idx_nd_municipality_created (municipality_id, created_at),
    INDEX idx_nd_channel_status (channel, status),
    INDEX idx_nd_user_created (recipient_user_id, created_at),
    INDEX idx_nd_team_created (team_id, created_at),
    INDEX idx_nd_external_ref (external_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
