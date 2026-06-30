<?php
/**
 * SynDrasi - Mail service.
 *
 * Global defaults come from config/mail.php. Each municipality can override
 * them from its settings page (Ρυθμίσεις -> Email/SMTP). Drivers:
 *   'log'  -> writes emails to storage/logs/mail.log
 *   'mail' -> PHP mail()
 *   'smtp' -> PHPMailer if installed, otherwise the built-in SmtpMailer
 *
 * All outgoing emails are wrapped in an HTML layout (municipality branding).
 * The plain-text body is also sent as the text/plain alternative part.
 */
class MailService
{
    /** Last error message of the most recent send() call (for the UI). */
    public static $lastError = '';

    /** Legacy PHP-array queue (used only when the mail_queue DB table is unavailable). */
    private static $queue = [];
    /** Whether the shutdown handler has already been registered this request. */
    private static $shutdownRegistered = false;
    /** Whether any emails were successfully enqueued to the DB this request. */
    private static $dbQueued = false;

    /**
     * Queue an email for delivery without blocking the HTTP response.
     *
     * Strategy (in order):
     *  1. INSERT into mail_queue table (~0 ms) — always instant.
     *  2. A single shutdown handler fires at the end of the request, AFTER
     *     all sendDeferred() calls have completed and all rows are in the DB.
     *     The handler tries dispatchAsync() first (loopback HTTP fire-and-forget),
     *     then falls back to synchronous send if that fails.
     *
     * If the mail_queue table does not exist yet (migration pending), falls back
     * to the legacy PHP-array + shutdown-function approach.
     */
    public static function sendDeferred($toEmail, $toName, $subject, $body, $municipalityId = null)
    {
        if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            dbq(
                "INSERT INTO mail_queue (municipality_id, to_email, to_name, subject, body)
                 VALUES (:mid, :email, :name, :subj, :body)",
                [
                    'mid'   => $municipalityId,
                    'email' => $toEmail,
                    'name'  => (string) $toName,
                    'subj'  => (string) $subject,
                    'body'  => (string) $body,
                ]
            );
            $queueId = (int) db()->lastInsertId();
            NotificationDelivery::record([
                'municipality_id' => $municipalityId,
                'channel' => 'email',
                'status' => 'queued',
                'recipient_label' => (string) $toName,
                'recipient_address' => (string) $toEmail,
                'title' => (string) $subject,
                'message' => (string) $body,
                'external_ref' => 'mail_queue:' . $queueId,
            ]);
            self::$dbQueued = true;
        } catch (Throwable $e) {
            // DB table not yet created — fall back to PHP-array + shutdown.
            error_log('[MailService::sendDeferred] DB queue unavailable, sync fallback: ' . $e->getMessage());
            self::$queue[] = [
                'toEmail'        => $toEmail,
                'toName'         => $toName,
                'subject'        => $subject,
                'body'           => $body,
                'municipalityId' => $municipalityId,
            ];
        }
        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(['MailService', 'flushQueue']);
        }
    }

    /**
     * Shutdown handler — runs once, after ALL sendDeferred() calls in this request.
     *
     * DB-queue path (normal):
     *   1. Try fastcgi_finish_request() — browser gets the redirect instantly (PHP-FPM only).
     *   2. Try dispatchAsync() — fire-and-forget loopback HTTP to /cron/mail-queue.
     *      The server processes it in a separate PHP worker while we exit.
     *   3. If both fail: send synchronously from here (blocks, but reliable fallback).
     *
     * Legacy PHP-array path (mail_queue table missing):
     *   Send array contents synchronously (old behaviour, unchanged).
     */
    public static function flushQueue()
    {
        // ── Legacy path: DB table unavailable ──────────────────────────────
        if (!empty(self::$queue)) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            @set_time_limit(120);
            foreach (self::$queue as $m) {
                self::send($m['toEmail'], $m['toName'], $m['subject'], $m['body'], $m['municipalityId']);
            }
            self::$queue = [];
            return;
        }

        // ── DB-queue path ───────────────────────────────────────────────────
        if (!self::$dbQueued) { return; }

        // PHP-FPM: browser gets the redirect before we do anything else.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            @set_time_limit(120);
            self::processPendingDbQueue();
            return;
        }

        // Apache/mod_php: try a fire-and-forget loopback HTTP request.
        // dispatchAsync() opens a TCP socket, writes the GET, and closes
        // WITHOUT reading — so we don't wait and neither does the browser.
        if (self::dispatchAsync()) {
            return; // separate PHP worker will call /cron/mail-queue
        }

        // Both failed — send synchronously as last resort (may block the response).
        @set_time_limit(120);
        self::processPendingDbQueue();
    }

    /**
     * Fire a non-blocking HTTP request to /cron/mail-queue on the current server.
     * Opens a TCP socket, writes the GET request, closes immediately without reading
     * the response — the server handles it in a separate PHP worker.
     *
     * Returns true if the request was dispatched, false if the connection failed.
     */
    private static function dispatchAsync(): bool
    {
        try {
            $secret = dbq(
                "SELECT setting_value FROM app_settings WHERE setting_key = 'cron_secret' LIMIT 1"
            )->fetchColumn();
            if (!$secret) {
                // Auto-generate and persist a secret so the loopback works without manual setup.
                $secret = bin2hex(random_bytes(24));
                dbq(
                    "INSERT INTO app_settings (setting_key, setting_value) VALUES ('cron_secret', :s)
                     ON DUPLICATE KEY UPDATE setting_value = :s2",
                    ['s' => $secret, 's2' => $secret]
                );
            }

            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // Strip port from host for fsockopen; keep it in the Host header.
            $fsHost = preg_replace('/:\d+$/', '', $host);
            $port   = $https ? 443 : (int) ($_SERVER['SERVER_PORT'] ?? 80);
            // Build the path: dirname of SCRIPT_NAME gives the app sub-directory.
            $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/\\');
            $path   = $base . '/cron/mail-queue';

            $fp = @fsockopen(($https ? 'ssl://' : '') . $fsHost, $port, $errno, $errstr, 2);
            if (!$fp) { return false; }

            fwrite($fp,
                "GET {$path} HTTP/1.0\r\n"
                . "Host: {$host}\r\n"
                . "Authorization: Bearer {$secret}\r\n"
                . "Connection: close\r\n\r\n"
            );
            fclose($fp); // close without reading — fire and forget
            return true;
        } catch (Throwable $e) {
            error_log('[MailService::dispatchAsync] ' . $e->getMessage());
            return false;
        }
    }

    /** Send all pending unsent rows from mail_queue (used as synchronous fallback). */
    private static function processPendingDbQueue(int $limit = 30): void
    {
        try {
            $rows = dbq(
                "SELECT * FROM mail_queue WHERE sent_at IS NULL AND attempts < 3
                 ORDER BY created_at ASC LIMIT :lim",
                ['lim' => $limit]
            )->fetchAll();
            foreach ($rows as $m) {
                dbq("UPDATE mail_queue SET attempts = attempts + 1, last_attempt = NOW() WHERE id = :id",
                    ['id' => $m['id']]);
                $ok = self::send($m['to_email'], $m['to_name'], $m['subject'], $m['body'],
                    $m['municipality_id'] ?: null);
                if ($ok) {
                    dbq("UPDATE mail_queue SET sent_at = NOW(), error_msg = NULL WHERE id = :id",
                        ['id' => $m['id']]);
                    NotificationDelivery::markExternalRef('mail_queue:' . (int) $m['id'], 'sent', (int) $m['attempts'] + 1, null);
                } else {
                    dbq("UPDATE mail_queue SET error_msg = :err WHERE id = :id",
                        ['err' => self::$lastError, 'id' => $m['id']]);
                    NotificationDelivery::markExternalRef('mail_queue:' . (int) $m['id'], 'failed', (int) $m['attempts'] + 1, self::$lastError);
                }
            }
        } catch (Throwable $e) {
            error_log('[MailService::processPendingDbQueue] ' . $e->getMessage());
        }
    }

    /**
     * Send an email.
     *
     * @param int|null $municipalityId  use this municipality's SMTP settings if configured
     * @return bool true on success
     */
    public static function send($toEmail, $toName, $subject, $body, $municipalityId = null)
    {
        self::$lastError = '';
        if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Μη έγκυρη διεύθυνση email παραλήπτη.';
            return false;
        }

        $cfg      = self::resolveConfig($municipalityId);
        $htmlBody = self::wrapHtml($subject, $body, $municipalityId);

        try {
            switch ($cfg['driver']) {
                case 'smtp':
                    return self::sendSmtp($cfg, $toEmail, $toName, $subject, $htmlBody, $body);
                case 'mail':
                    return self::sendPhpMail($cfg, $toEmail, $subject, $htmlBody, $body);
                case 'log':
                default:
                    return self::sendLog($toEmail, $subject, $body);
            }
        } catch (Exception $ex) {
            self::$lastError = $ex->getMessage();
            error_log('Mail failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Wrap plain-text body in a clean HTML email layout.
     * Pulls municipality logo + name from settings for branding.
     */
    public static function wrapHtml(string $subject, string $body, $municipalityId = null): string
    {
        $logoUrl   = '';
        $orgName   = 'SynDrasi';
        $accentColor = '#1a6bbf';

        if ($municipalityId) {
            try {
                $s = MunicipalitySetting::all($municipalityId);
                if (!empty($s['branding_logo_url'])) {
                    $logoUrl = htmlspecialchars($s['branding_logo_url'], ENT_QUOTES, 'UTF-8');
                }
                // Try to get municipality name
                $mun = Municipality::find($municipalityId);
                if ($mun && !empty($mun['name'])) {
                    $orgName = htmlspecialchars($mun['name'], ENT_QUOTES, 'UTF-8');
                }
            } catch (Exception $e) { /* ignore */ }
        }

        // If body already contains HTML tags, use as-is; otherwise escape plain text
        $htmlBody = strip_tags($body) !== $body
            ? $body
            : nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

        $logoHtml = $logoUrl
            ? '<img src="' . $logoUrl . '" alt="' . $orgName . '" style="max-height:50px;max-width:160px;object-fit:contain;display:block;margin:0 auto 8px;">'
            : '';

        return '<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;font-size:15px;color:#333;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:32px 8px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

      <!-- Header -->
      <tr>
        <td style="background:' . $accentColor . ';padding:24px 32px;text-align:center;">
          ' . $logoHtml . '
          <span style="color:#fff;font-size:20px;font-weight:700;letter-spacing:0.5px;">' . $orgName . '</span>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:32px 36px;line-height:1.7;color:#333;">
          ' . $htmlBody . '
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#f4f6f9;padding:18px 36px;text-align:center;font-size:12px;color:#888;border-top:1px solid #e8eaed;">
          Αυτό το μήνυμα στάλθηκε αυτόματα από την πλατφόρμα <strong>SynDrasi</strong>.<br>
          Παρακαλούμε μην απαντάτε σε αυτό το email.
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
    }

    /**
     * Effective mail configuration: global config/mail.php values,
     * overridden by the municipality's saved settings (if it set a driver).
     */
    public static function resolveConfig($municipalityId = null)
    {
        $cfg = config('mail');

        if ($municipalityId) {
            try {
                $s = MunicipalitySetting::all($municipalityId);
            } catch (Exception $ex) {
                $s = [];
            }
            if (!empty($s['mail_driver'])) {
                $cfg['driver'] = $s['mail_driver'];
                $map = [
                    'mail_from_email' => 'from_email',
                    'mail_from_name'  => 'from_name',
                    'smtp_host'       => 'smtp_host',
                    'smtp_port'       => 'smtp_port',
                    'smtp_user'       => 'smtp_user',
                    'smtp_pass'       => 'smtp_pass',
                    'smtp_secure'     => 'smtp_secure',
                ];
                foreach ($map as $key => $cfgKey) {
                    if (isset($s[$key]) && $s[$key] !== '') {
                        $cfg[$cfgKey] = $key === 'smtp_port' ? (int) $s[$key] : $s[$key];
                    }
                }
            }
        }

        return $cfg;
    }

    private static function sendLog($toEmail, $subject, $body)
    {
        $line = sprintf(
            "[%s] To: %s | Subject: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'), $toEmail, $subject, $body, str_repeat('-', 60)
        );
        file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND | LOCK_EX);
        return true;
    }

    private static function sendPhpMail($cfg, $toEmail, $subject, $htmlBody, $plainBody)
    {
        $boundary = md5(uniqid());
        $headers  = 'From: ' . $cfg['from_name'] . ' <' . $cfg['from_email'] . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($plainBody)) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($htmlBody)) . "\r\n"
            . "--{$boundary}--";

        $ok = mail($toEmail, $encodedSubject, $message, $headers);
        if (!$ok) {
            self::$lastError = 'Η συνάρτηση mail() απέτυχε. Ελέγξτε τις ρυθμίσεις PHP mail του server.';
        }
        return $ok;
    }

    private static function sendSmtp($cfg, $toEmail, $toName, $subject, $htmlBody, $plainBody)
    {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return self::sendPhpMailerSmtp($cfg, $toEmail, $toName, $subject, $htmlBody, $plainBody);
        }

        $result = SmtpMailer::send($cfg, $toEmail, $toName, $subject, $htmlBody, $plainBody);
        if ($result === true) {
            return true;
        }
        self::$lastError = $result;
        error_log('SMTP failed: ' . $result);
        return false;
    }

    private static function sendPhpMailerSmtp($cfg, $toEmail, $toName, $subject, $htmlBody, $plainBody)
    {
        $mailerClass = 'PHPMailer\\PHPMailer\\PHPMailer';
        $mail = new $mailerClass(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg['smtp_host'];
            $mail->SMTPAuth   = !empty($cfg['smtp_user']);
            $mail->Username   = $cfg['smtp_user'];
            $mail->Password   = $cfg['smtp_pass'];
            $mail->SMTPSecure = $cfg['smtp_secure'];
            $mail->Port       = (int) $cfg['smtp_port'];
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject  = $subject;
            $mail->isHTML(true);
            $mail->Body     = $htmlBody;
            $mail->AltBody  = $plainBody;
            return $mail->send();
        } catch (Exception $ex) {
            self::$lastError = $mail->ErrorInfo ?: $ex->getMessage();
            error_log('PHPMailer failed: ' . self::$lastError);
            return false;
        }
    }
}
