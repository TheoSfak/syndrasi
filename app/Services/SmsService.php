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
        try {
            switch ($cfg['driver']) {
                case 'http':
                    return self::sendHttp($cfg, $phone, $message);
                case 'none':
                    return false;
                case 'log':
                default:
                    return self::sendLog($phone, $message);
            }
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('SMS failed: ' . $e->getMessage());
            return false;
        }
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
}
