<?php
/**
 * SynDrasi - CronController.
 * Lightweight endpoints callable by cron jobs / Windows Task Scheduler.
 * All endpoints are protected by a secret token in municipality_settings
 * (key: cron_secret) or the global app_setting 'cron_secret'.
 *
 * Example cron line (Linux):
 *   * * * * *  curl -s -H "Authorization: Bearer TOKEN" "http://yoursite/cron/shift-reminders" > /dev/null
 *
 * Example Windows Task Scheduler:
 *   curl -H "Authorization: Bearer TOKEN" "http://localhost/syndrasi/public/cron/shift-reminders"
 */
class CronController
{
    /**
     * GET /cron/shift-reminders
     * Scans all approved shift applications whose shift starts in the next 55–65 minutes
     * and sends reminder notifications. Safe to run every minute — uses a `reminded_at`
     * flag to avoid duplicates (stored in shift_applications.notes as a prefix, or we
     * use a simple flag column check via app_settings).
     */
    public function shiftReminders()
    {
        $this->authCron();

        // Find shifts starting in ~60 minutes (55–65 min window)
        $upcoming = dbq(
            "SELECT es.*, e.title AS event_title, e.municipality_id, e.location_name
             FROM event_shifts es
             JOIN events e ON e.id = es.event_id
             WHERE es.start_datetime BETWEEN DATE_ADD(NOW(), INTERVAL 55 MINUTE)
                                         AND DATE_ADD(NOW(), INTERVAL 65 MINUTE)
               AND e.status IN ('open','review','confirmed','active')
               AND NOT EXISTS (
                   SELECT 1 FROM app_settings
                   WHERE setting_key = CONCAT('shift_reminded_', es.id)
               )",
        )->fetchAll();

        $count = 0;
        foreach ($upcoming as $shift) {
            $event = [
                'id'              => $shift['event_id'],
                'title'           => $shift['event_title'],
                'municipality_id' => $shift['municipality_id'],
                'location_name'   => $shift['location_name'],
            ];

            $sent = NotificationService::shiftReminder($event, $shift);
            $count += $sent;

            // Mark as reminded so we don't send again
            dbq(
                "INSERT INTO app_settings (setting_key, setting_value)
                 VALUES (:k, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = NOW()",
                ['k' => 'shift_reminded_' . $shift['id']]
            );
        }

        json_out([
            'success'          => true,
            'shifts_processed' => count($upcoming),
            'notifications'    => $count,
            'at'               => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * GET /cron/cleanup
     * Purges transient rows that accumulate in app_settings and never expire:
     *   - login rate-limit counters/locks (login_fail_* / login_lock_*)
     *   - per-shift "already reminded" flags for shifts that ended long ago
     *   - expired/used password-reset tokens
     * Safe to run daily.
     */
    public function cleanup()
    {
        $this->authCron();
        json_out(array_merge(['success' => true], MaintenanceService::cleanup()));
    }

    /* ── Private ─────────────────────────────────────────────────────────── */

    private function authCron()
    {
        // Accept secret via Authorization: Bearer header only; never from GET (avoids access-log exposure)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $secret = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        if ($secret === '') {
            http_response_code(401);
            exit(json_encode(['error' => 'Missing secret. Use: Authorization: Bearer <secret>']));
        }
        $stored = dbq(
            "SELECT setting_value FROM app_settings WHERE setting_key = 'cron_secret' LIMIT 1"
        )->fetchColumn();

        if (!$stored || !hash_equals((string) $stored, $secret)) {
            http_response_code(403);
            exit(json_encode(['error' => 'Invalid secret.']));
          }
    }
}
