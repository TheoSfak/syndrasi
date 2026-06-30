<?php
/**
 * SynDrasi - SMS delivery (pluggable).
 *
 * Mirrors MailService: the default 'log' driver writes to storage/logs/sms.log
 * so the mobilization flow is fully testable with zero setup. Set SMS_DRIVER=http
 * and fill SMS_ENDPOINT / SMS_API_KEY to use a real Greek gateway (Yuboto,
 * AppText, Vonage, …) — adapt sendHttp() to that provider's payload.
 */
class SmsService
{
    private static $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    /**
     * Resolve the effective SMS config for a municipality.
     * Per-municipality settings (saved from the admin UI) win over the
     * platform defaults in config/sms.php (which read env vars).
     */
    public static function resolveConfig($municipalityId = null): array
    {
        $cfg = config('sms');

        if ($municipalityId) {
            try {
                $s = MunicipalitySetting::all($municipalityId);
            } catch (Throwable $ex) {
                $s = [];
            }
            $map = [
                'sms_driver'   => 'driver',
                'sms_sender'   => 'sender',
                'sms_endpoint' => 'endpoint',
                'sms_api_key'  => 'api_key',
                'sms_username' => 'username',
            ];
            foreach ($map as $key => $cfgKey) {
                if (isset($s[$key]) && $s[$key] !== '') {
                    $cfg[$cfgKey] = $s[$key];
                }
            }
        }

        return $cfg;
    }

    public static function send($toPhone, string $message, $municipalityId = null): bool
    {
        self::$lastError = '';
        $phone = self::normalize($toPhone);
        if ($phone === '') {
            self::$lastError = 'Μη έγκυρος αριθμός τηλεφώνου.';
            return false;
        }

        $cfg = self::resolveConfig($municipalityId);
        $ok = false;
        try {
            switch ($cfg['driver']) {
                case 'smsbox':
                    $ok = self::sendSmsbox($cfg, $phone, $message);
                    break;
                case 'http':
                    $ok = self::sendHttp($cfg, $phone, $message);
                    break;
                case 'none':
                    self::$lastError = 'Το SMS κανάλι είναι απενεργοποιημένο.';
                    $ok = false;
                    break;
                case 'log':
                default:
                    $ok = self::sendLog($phone, $message);
                    break;
            }
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('SMS failed: ' . $e->getMessage());
            $ok = false;
        }

        NotificationDelivery::record([
            'municipality_id' => $municipalityId,
            'channel' => 'sms',
            'status' => $ok ? 'sent' : 'failed',
            'recipient_address' => $phone,
            'title' => mb_substr(strip_tags($message), 0, 120),
            'message' => $message,
            'attempts' => 1,
            'error_msg' => $ok ? null : self::$lastError,
        ]);
        return $ok;
    }

    /** Keep digits and a leading +; assume Greek (+30) for bare 10-digit numbers. */
    private static function normalize($raw): string
    {
        $p = preg_replace('/[^\d+]/', '', (string) $raw);
        if ($p === '') {
            return '';
        }
        if ($p[0] !== '+' && strlen($p) === 10) {
            $p = '+30' . $p; // Greek mobile/landline
        }
        return $p;
    }

    private static function sendLog(string $phone, string $message): bool
    {
        $line = sprintf(
            "[%s] To: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'), $phone, $message, str_repeat('-', 60)
        );
        file_put_contents(BASE_PATH . '/storage/logs/sms.log', $line, FILE_APPEND | LOCK_EX);
        return true;
    }

    /**
     * Generic HTTP gateway. Adapt the payload to your provider's API.
     * Left intentionally simple — most Greek gateways accept a POST like this.
     */
    private static function sendHttp(array $cfg, string $phone, string $message): bool
    {
        if (empty($cfg['endpoint']) || empty($cfg['api_key'])) {
            self::$lastError = 'SMS gateway δεν έχει ρυθμιστεί (SMS_ENDPOINT / SMS_API_KEY).';
            return false;
        }
        $payload = http_build_query([
            'key'     => $cfg['api_key'],
            'sender'  => $cfg['sender'],
            'to'      => $phone,
            'message' => $message,
        ]);
        $ch = curl_init($cfg['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $code >= 400) {
            self::$lastError = 'Gateway error: ' . ($err !== '' ? $err : ('HTTP ' . $code));
            return false;
        }
        return true;
    }

    /**
     * smsbox.gr driver (httpapi). Two auth modes:
     *   - username + password (api_key field = password): we call auth.php to get a
     *     fresh sesskey (valid 2h), then sendsms.php. Recommended for production.
     *   - sesskey only (api_key field = the sesskey, no username): use it directly
     *     on sendsms.php. Handy for a quick test (the sesskey expires in 2 hours).
     * Success when the response starts with "OK".
     */
    private static function sendSmsbox(array $cfg, string $phone, string $message): bool
    {
        $base = 'https://www.smsbox.gr/httpapi';
        $username = trim((string) ($cfg['username'] ?? ''));
        $secret   = trim((string) ($cfg['api_key'] ?? ''));   // password OR sesskey
        $from     = ($cfg['sender'] ?? '') !== '' ? $cfg['sender'] : 'SynDrasi';
        $to       = ltrim($phone, '+');                        // smsbox wants digits (e.g. 30694...)

        $sesskey = '';
        if ($username !== '') {
            // username + password → fetch a fresh sesskey
            $auth = self::httpGet($base . '/auth.php?username=' . rawurlencode($username) . '&password=' . rawurlencode($secret));
            $t = trim((string) $auth);
            if (stripos($t, 'ok') === 0) {
                $sesskey = trim(substr($t, 2));
            } else {
                self::$lastError = 'smsbox auth: ' . ($t !== '' ? $t : 'no response');
                return false;
            }
        } else {
            // api_key holds the sesskey directly (tolerate a pasted "OK xxxx")
            $sesskey = (stripos($secret, 'ok ') === 0) ? trim(substr($secret, 3)) : $secret;
        }
        if ($sesskey === '') {
            self::$lastError = 'smsbox: λείπει sesskey ή username/password.';
            return false;
        }

        $url = $base . '/sendsms.php?sesskey=' . rawurlencode($sesskey)
             . '&from=' . rawurlencode($from)
             . '&to='   . rawurlencode($to)
             . '&text=' . rawurlencode($message);
        $resp = self::httpGet($url);
        $t = trim((string) $resp);
        if (stripos($t, 'ok') === 0) {
            return true;
        }
        self::$lastError = 'smsbox: ' . ($t !== '' ? $t : 'no response');
        return false;
    }

    /** Simple HTTP GET helper; returns body string or null on transport error. */
    private static function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            self::$lastError = 'HTTP error: ' . $err;
            return null;
        }
        return (string) $resp;
    }
}
