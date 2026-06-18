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

    /** Queue of emails waiting to be sent after the HTTP response is flushed. */
    private static $queue = [];
    /** Whether the shutdown function has already been registered. */
    private static $shutdownRegistered = false;

    /**
     * Queue an email to send AFTER the browser receives the HTTP response.
     * Uses fastcgi_finish_request() so the redirect is instant for the user.
     * Falls back to synchronous send on servers without FPM.
     */
    public static function sendDeferred($toEmail, $toName, $subject, $body, $municipalityId = null)
    {
        self::$queue[] = [
            'toEmail'        => $toEmail,
            'toName'         => $toName,
            'subject'        => $subject,
            'body'           => $body,
            'municipalityId' => $municipalityId,
        ];
        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(['MailService', 'flushQueue']);
        }
    }

    /**
     * Send all queued emails. Called automatically by the shutdown handler.
     * Flushes the HTTP response to the browser first, then sends via SMTP.
     */
    public static function flushQueue()
    {
        if (empty(self::$queue)) { return; }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();   // browser gets the redirect NOW
        }
        @set_time_limit(120);           // give SMTP enough time, no page timeout
        foreach (self::$queue as $m) {
            self::send($m['toEmail'], $m['toName'], $m['subject'], $m['body'], $m['municipalityId']);
        }
        self::$queue = [];
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

        // Convert plain text to safe HTML
        $htmlBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

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
