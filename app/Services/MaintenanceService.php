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
     *   - login/reset rate-limit counters/locks (rate_limits table)
     *   - spent / expired password-reset tokens
     * (Per-shift "already reminded" flags no longer need purging — they live
     * on event_shifts.reminded_at and die with the shift row.)
     * Returns a counts array. Safe to run repeatedly.
     */
    public static function cleanup(): array
    {
        $rl = dbq(
            "DELETE FROM rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
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
            'reset_tokens_removed' => $pr,
            'at'                   => date('Y-m-d H:i:s'),
        ];
    }
}
