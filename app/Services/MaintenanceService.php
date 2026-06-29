<?php
/**
 * SynDrasi - Maintenance tasks.
 * Shared by the token-protected cron endpoint and the admin "Cron Jobs" tab,
 * so a human can trigger the same housekeeping with one button.
 */
class MaintenanceService
{
    /**
     * Purge transient rows that accumulate and never expire:
     *   - login/reset rate-limit counters/locks
     *   - per-shift "already reminded" flags for shifts that ran long ago
     *   - spent / expired password-reset tokens
     * Returns a counts array. Safe to run repeatedly.
     */
    public static function cleanup(): array
    {
        $rl = dbq(
            "DELETE FROM app_settings
             WHERE (setting_key LIKE 'login_fail_%'
                    OR setting_key LIKE 'login_lock_%'
                    OR setting_key LIKE 'reset_req_%')
               AND updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->rowCount();

        $sr = dbq(
            "DELETE FROM app_settings
             WHERE setting_key LIKE 'shift_reminded_%'
               AND updated_at < DATE_SUB(NOW(), INTERVAL 2 DAY)"
        )->rowCount();

        $pr = dbq(
            "DELETE FROM password_resets
             WHERE used_at IS NOT NULL
                OR created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->rowCount();

        // Auto-purge field videos older than EventVideo::RETENTION_DAYS (file + row).
        $videoDir = BASE_PATH . EventVideo::DIR;
        $vc = 0;
        try {
            $oldVideos = dbq(
                "SELECT id, file_name FROM event_videos
                 WHERE kept = 0 AND created_at < DATE_SUB(NOW(), INTERVAL " . EventVideo::RETENTION_DAYS . " DAY)"
            )->fetchAll();
        } catch (Throwable $e) {
            $oldVideos = dbq(
                "SELECT id, file_name FROM event_videos
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL " . EventVideo::RETENTION_DAYS . " DAY)"
            )->fetchAll();
        }
        foreach ($oldVideos as $v) {
            $name = basename((string) $v['file_name']);
            if ($name !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
                @unlink($videoDir . '/' . $name);
            }
            dbq("DELETE FROM event_videos WHERE id = :id", ['id' => (int) $v['id']]);
            $vc++;
        }

        return [
            'rate_limit_removed'   => $rl,
            'videos_purged'        => $vc,
            'shift_flags_removed'  => $sr,
            'reset_tokens_removed' => $pr,
            'at'                   => date('Y-m-d H:i:s'),
        ];
    }
}
