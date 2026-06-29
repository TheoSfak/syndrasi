<?php
/**
 * SynDrasi - Telegram Bot delivery.
 *
 * Uses Telegram Bot API sendMessage. Intended for municipality command groups
 * and volunteer-team groups/channels, not personal DMs in this MVP.
 */
class TelegramService
{
    private static $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    public static function resolveConfig($municipalityId = null): array
    {
        $cfg = self::defaultConfig();

        if ($municipalityId) {
            try {
                $s = MunicipalitySetting::all($municipalityId);
            } catch (Throwable $ex) {
                $s = [];
            }

            if (isset($s['telegram_enabled']) && $s['telegram_enabled'] !== '') {
                $cfg['enabled'] = $s['telegram_enabled'] === '1';
            }
            if (!empty($s['telegram_bot_token'])) {
                $cfg['bot_token'] = $s['telegram_bot_token'];
            }
            if (isset($s['telegram_command_chat_id']) && $s['telegram_command_chat_id'] !== '') {
                $cfg['command_chat_id'] = $s['telegram_command_chat_id'];
            }
        }

        return $cfg;
    }

    private static function defaultConfig(): array
    {
        $path = BASE_PATH . '/config/telegram.php';
        if (is_file($path)) {
            try {
                $cfg = config('telegram');
                if (is_array($cfg)) {
                    return $cfg;
                }
            } catch (Throwable $e) {
                error_log('[Telegram] config fallback: ' . $e->getMessage());
            }
        }

        return [
            'enabled' => env('TELEGRAM_ENABLED', '0') === '1',
            'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
            'command_chat_id' => env('TELEGRAM_COMMAND_CHAT_ID', ''),
        ];
    }

    public static function sendCommand($municipalityId, string $title, string $message, ?string $url = null): bool
    {
        $cfg = self::resolveConfig($municipalityId);
        return self::sendToChat($cfg, $cfg['command_chat_id'] ?? '', $title, $message, $url);
    }

    public static function sendTeam($teamId, string $title, string $message, $municipalityId = null, ?string $url = null): bool
    {
        $team = VolunteerTeam::find($teamId);
        if (!$team || empty($team['telegram_chat_id'])) {
            self::$lastError = 'Η ομάδα δεν έχει Telegram Chat ID.';
            return false;
        }
        $mid = $municipalityId ?: ($team['municipality_id'] ?? null);
        return self::sendToChat(self::resolveConfig($mid), $team['telegram_chat_id'], $title, $message, $url);
    }

    public static function sendToChat(array $cfg, string $chatId, string $title, string $message, ?string $url = null): bool
    {
        self::$lastError = '';
        if (empty($cfg['enabled'])) {
            self::$lastError = 'Το Telegram είναι απενεργοποιημένο.';
            return false;
        }
        if (trim((string) ($cfg['bot_token'] ?? '')) === '') {
            self::$lastError = 'Λείπει Telegram Bot Token.';
            return false;
        }
        if (trim($chatId) === '') {
            self::$lastError = 'Λείπει Telegram Chat ID.';
            return false;
        }

        $text = self::formatMessage($title, $message, $url);
        return self::apiSendMessage((string) $cfg['bot_token'], $chatId, $text);
    }

    private static function formatMessage(string $title, string $message, ?string $url = null): string
    {
        $title = trim($title);
        $message = trim(strip_tags($message));
        $text = '<b>' . self::h($title) . '</b>';
        if ($message !== '') {
            $text .= "\n" . self::h($message);
        }
        if ($url) {
            $text .= "\n\n" . '<a href="' . self::h($url) . '">Άνοιγμα στο SynDrasi</a>';
        }
        return $text;
    }

    private static function apiSendMessage(string $token, string $chatId, string $text): bool
    {
        $endpoint = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            self::$lastError = 'Telegram HTTP error: ' . $err;
            return false;
        }

        $json = json_decode((string) $resp, true);
        if ($code >= 400 || !is_array($json) || empty($json['ok'])) {
            $desc = is_array($json) && isset($json['description']) ? (string) $json['description'] : ('HTTP ' . $code);
            self::$lastError = 'Telegram API: ' . $desc;
            return false;
        }

        return true;
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
