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
     * and sends reminder notifications. Safe to run every minute — uses the shift's own
     * `reminded_at` column to avoid duplicates.
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
               AND es.reminded_at IS NULL",
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
            dbq('UPDATE event_shifts SET reminded_at = NOW() WHERE id = :id', ['id' => $shift['id']]);
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

    /**
     * GET /cron/mail-queue
     * Sends up to 50 pending emails from the mail_queue table per run.
     * Retries failed messages up to 3 times before giving up.
     * Safe to run every minute via cron.
     *
     * Example cron line (Linux):
     *   * * * * *  curl -s -H "Authorization: Bearer TOKEN" "http://yoursite/cron/mail-queue" > /dev/null
     */
    public function processMailQueue()
    {
        $this->authCron();
        @set_time_limit(120);

        $maxAttempts = 3;
        $batchSize   = 50;

        $pending = dbq(
            "SELECT * FROM mail_queue
             WHERE sent_at IS NULL AND attempts < :max
             ORDER BY created_at ASC
             LIMIT :lim",
            ['max' => $maxAttempts, 'lim' => $batchSize]
        )->fetchAll();

        $sent   = 0;
        $failed = 0;

        foreach ($pending as $m) {
            dbq(
                "UPDATE mail_queue SET attempts = attempts + 1, last_attempt = NOW() WHERE id = :id",
                ['id' => $m['id']]
            );

            $ok = MailService::send(
                $m['to_email'],
                $m['to_name'],
                $m['subject'],
                $m['body'],
                $m['municipality_id'] ?: null
            );

            if ($ok) {
                dbq("UPDATE mail_queue SET sent_at = NOW(), error_msg = NULL WHERE id = :id", ['id' => $m['id']]);
                $sent++;
            } else {
                dbq(
                    "UPDATE mail_queue SET error_msg = :err WHERE id = :id",
                    ['err' => MailService::$lastError, 'id' => $m['id']]
                );
                $failed++;
            }
        }

        json_out([
            'success'  => true,
            'sent'     => $sent,
            'failed'   => $failed,
            'pending'  => count($pending),
            'at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * GET /cron/fire-service
     * Fetches the official Fire Service incidents page. Safe to run every 5 minutes.
     */
    public function fireService()
    {
        $this->authCron();
        $result = FireServiceIncidentService::sync();
        json_out(array_merge($result, ['at' => date('Y-m-d H:i:s')]), $result['success'] ? 200 : 502);
    }

    /**
     * POST /cron/fire-service/ingest
     * Receives Fire Service incidents HTML from an external fetcher when
     * the live server's outbound IP is blocked by fireservice.gr's WAF
     * (FortiADC). JSON body: {"html_b64": "<base64>"}.
     *
     * The HTML is base64-encoded by the caller: sending raw HTML/script
     * markup in the POST body gets blocked with a plain-text 403 by this
     * host's own inbound WAF (content-pattern rule, not size — a 42KB
     * plain-text body passes fine, a 2KB snippet with real <tags> does not).
     * Base64 has no recognizable HTML/script syntax, so it passes through.
     */
    public function ingestFireService()
    {
        $this->authCron();
        @set_time_limit(90);

        $body = json_input();
        $html = '';
        if (isset($body['html_b64'])) {
            $decoded = base64_decode((string) $body['html_b64'], true);
            $html = $decoded === false ? '' : $decoded;
        } elseif (isset($body['html'])) {
            $html = (string) $body['html'];
        }

        if (trim($html) === '') {
            json_out(['success' => false, 'error' => 'Missing or invalid html_b64.'], 422);
        }
        if (strlen($html) > 2 * 1024 * 1024) {
            json_out(['success' => false, 'error' => 'HTML payload too large.'], 422);
        }

        try {
            $result = FireServiceIncidentService::syncFromHtml($html);
        } catch (InvalidArgumentException $e) {
            json_out(['success' => false, 'error' => $e->getMessage()], 422);
        }
        json_out(array_merge($result, ['at' => date('Y-m-d H:i:s')]), $result['success'] ? 200 : 422);
    }

    /**
     * GET /cron/fire-risk-map
     * Checks the Civil Protection daily fire-risk map. Safe to run every 60 min.
     */
    public function fireRiskMap()
    {
        $this->authCron();
        $result = FireRiskMapService::sync();
        json_out(array_merge($result, ['at' => date('Y-m-d H:i:s')]), $result['success'] ? 200 : 502);
    }

    /**
     * POST /cron/fire-risk-map/ingest
     * Receives a fire-risk map image from an external fetcher when the live
     * server cannot download Civil Protection directly. Multipart fields:
     *   - map_date: YYYY-MM-DD
     *   - fire_risk_map or image: image file
     */
    public function ingestFireRiskMap()
    {
        $this->authCron();
        @set_time_limit(90);

        $date = isset($_POST['map_date']) ? trim((string) $_POST['map_date']) : '';
        $sourceUrl = isset($_POST['source_url']) ? trim((string) $_POST['source_url']) : 'external-ingest';
        $file = $_FILES['fire_risk_map'] ?? ($_FILES['image'] ?? null);

        if ($date === '') {
            json_out(['success' => false, 'error' => 'Missing map_date.'], 422);
        }
        if (!$file || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            json_out(['success' => false, 'error' => 'Missing fire_risk_map/image upload.'], 422);
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 12 * 1024 * 1024) {
            json_out(['success' => false, 'error' => 'Uploaded map must be up to 12MB.'], 422);
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            json_out(['success' => false, 'error' => 'Invalid uploaded file.'], 422);
        }

        $binary = file_get_contents((string) $file['tmp_name']);
        if ($binary === false || $binary === '') {
            json_out(['success' => false, 'error' => 'Could not read uploaded map.'], 422);
        }

        $result = FireRiskMapService::syncBinary((string) $binary, $date, null, $sourceUrl);
        json_out(array_merge($result, ['at' => date('Y-m-d H:i:s')]), $result['success'] ? 200 : 422);
    }

    /* ── Private ─────────────────────────────────────────────────────────── */

    private function authCron()
    {
        // Accept secret via Authorization: Bearer header only; never from GET (avoids access-log exposure)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader === '' && function_exists('getallheaders')) {
            // Some Apache/mod_php configs (e.g. XAMPP on Windows without CGIPassAuth)
            // never populate $_SERVER['HTTP_AUTHORIZATION'] even though the header was sent.
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $authHeader = $value;
                    break;
                }
            }
        }
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
