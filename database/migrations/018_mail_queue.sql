-- Deferred mail queue: sendDeferred() inserts here; cron job /cron/mail-queue sends them.
-- Eliminates the 10-15 s HTTP delay caused by synchronous SMTP on Apache/mod_php hosts.
CREATE TABLE IF NOT EXISTS mail_queue (
    id              INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    municipality_id INT UNSIGNED     NULL,
    to_email        VARCHAR(255)     NOT NULL,
    to_name         VARCHAR(255)     NOT NULL DEFAULT '',
    subject         VARCHAR(500)     NOT NULL DEFAULT '',
    body            MEDIUMTEXT       NOT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt    DATETIME         NULL,
    sent_at         DATETIME         NULL,
    error_msg       TEXT             NULL,
    INDEX idx_mq_pending (sent_at, attempts, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
