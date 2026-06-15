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
     *   - login rate-limit counters/locks (login_fail_* / login_lock_*)
     *   - per-shift "already reminded" flags for shifts that ran long ago
     *   - spent / expired password-reset tokens
     * Returns a counts array. Safe to run repeatedly.
     */
    public static function cleanup(): array
    {
        $rl = dbq(
            "DELETE FROM app_settings
             WHERE (setting_key LIKE 'login_fail_%' OR setting_key LIKE 'login_lock_%')
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

        return [
            'rate_limit_removed'   => $rl,
            'shift_flags_removed'  => $sr,
            'reset_tokens_removed' => $pr,
            'at'                   => date('Y-m-d H:i:s'),
        ];
    }
}
