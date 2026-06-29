<?php
/**
 * Reads the official Civil Protection daily fire-risk map and sends Telegram
 * alerts for Crete once per map date.
 */
class FireRiskMapService
{
    public const ARCHIVE_URL = 'https://civilprotection.gov.gr/arxeio-imerision-xartwn';

    private const REGION_LABEL = 'Κρήτη';

    private const RISK_LEVELS = [
        1 => ['label' => 'Χαμηλή', 'rgb' => [150, 240, 160]],
        2 => ['label' => 'Μέση', 'rgb' => [170, 202, 240]],
        3 => ['label' => 'Υψηλή', 'rgb' => [255, 242, 0]],
        4 => ['label' => 'Πολύ υψηλή', 'rgb' => [247, 148, 30]],
        5 => ['label' => 'Κατάσταση συναγερμού', 'rgb' => [237, 28, 36]],
    ];

    /**
     * Relative sample points on the official 1384x1453 map. The probe searches
     * around each point for the nearest colored risk pixel to avoid borders/text.
     */
    private const CRETE_SAMPLES = [
        'Χανιά' => [570 / 1384, 1200 / 1453],
        'Ρέθυμνο' => [680 / 1384, 1220 / 1453],
        'Ηράκλειο' => [780 / 1384, 1235 / 1453],
        'Λασίθι' => [870 / 1384, 1250 / 1453],
    ];

    public static function sync(?int $onlyMunicipalityId = null): array
    {
        @set_time_limit(90);
        try {
            $map = self::latestMap();
            $image = self::fetchBinary($map['image_url']);
            $analysis = self::analyseImage($image);
            $sent = self::notifyMunicipalities($map, $analysis, $onlyMunicipalityId);
            self::cleanup();
            return [
                'success' => true,
                'map_date' => $map['map_date'],
                'image_url' => $map['image_url'],
                'levels' => $analysis['levels'],
                'telegram_sent' => $sent,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'map_date' => null,
                'image_url' => null,
                'levels' => [],
                'telegram_sent' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function latestMap(): array
    {
        $html = self::fetchText(self::ARCHIVE_URL);
        if (!preg_match_all('#https?://[^"\']+/sites/default/files/\d{4}-\d{2}/(\d{6})\.jpe?g|/sites/default/files/\d{4}-\d{2}/(\d{6})\.jpe?g#i', $html, $matches, PREG_SET_ORDER)) {
            throw new RuntimeException('Δεν βρέθηκε εικόνα ημερήσιου χάρτη στην Πολιτική Προστασία.');
        }

        $bestDate = null;
        $bestUrl = null;
        foreach ($matches as $m) {
            $code = $m[1] ?: $m[2];
            $date = self::dateFromCode($code);
            if ($date === null) { continue; }
            $url = $m[0];
            if (!str_starts_with($url, 'http')) {
                $url = 'https://civilprotection.gov.gr' . $url;
            }
            if ($bestDate === null || strcmp($date, $bestDate) > 0) {
                $bestDate = $date;
                $bestUrl = $url;
            }
        }

        if (!$bestDate || !$bestUrl) {
            throw new RuntimeException('Δεν αναγνωρίστηκε ημερομηνία χάρτη κινδύνου πυρκαγιάς.');
        }

        return ['map_date' => $bestDate, 'image_url' => $bestUrl];
    }

    public static function analyseImage(string $binary): array
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Το PHP GD extension δεν είναι διαθέσιμο.');
        }
        $img = @imagecreatefromstring($binary);
        if (!$img) {
            throw new RuntimeException('Η εικόνα του χάρτη δεν μπορεί να διαβαστεί.');
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $levels = [];
        foreach (self::CRETE_SAMPLES as $unit => [$rx, $ry]) {
            $x = (int) round($rx * $w);
            $y = (int) round($ry * $h);
            $rgb = self::nearestRiskPixel($img, $x, $y, (int) max(22, round($w * 0.025)));
            if ($rgb === null) {
                throw new RuntimeException('Δεν αναγνωρίστηκε χρώμα για ' . $unit . '.');
            }
            $level = self::classifyRisk($rgb);
            $levels[$unit] = [
                'level' => $level,
                'label' => self::RISK_LEVELS[$level]['label'],
                'rgb' => $rgb,
            ];
        }
        imagedestroy($img);

        return [
            'levels' => $levels,
            'max_level' => max(array_map(static fn($r) => (int) $r['level'], $levels)),
        ];
    }

    private static function notifyMunicipalities(array $map, array $analysis, ?int $onlyMunicipalityId = null): int
    {
        $sent = 0;
        foreach (Municipality::all() as $municipality) {
            if (($municipality['status'] ?? 'active') !== 'active') { continue; }
            $mid = (int) $municipality['id'];
            if ($onlyMunicipalityId !== null && $mid !== $onlyMunicipalityId) { continue; }
            if (!NotificationService::shouldSendTelegram($mid, 'fire_risk_crete')) { continue; }

            $cfg = TelegramService::resolveConfig($mid);
            if (empty($cfg['enabled']) || trim((string) ($cfg['bot_token'] ?? '')) === '') { continue; }
            if (trim((string) ($cfg['command_chat_id'] ?? '')) === '' && trim((string) ($cfg['team_chat_id'] ?? '')) === '') { continue; }
            if (!self::claimNotification($mid, $map['map_date'], $analysis['levels'], $map['image_url'])) { continue; }

            $ok = self::sendTelegram($cfg, $map, $analysis);
            if ($ok) {
                $sent++;
            } else {
                self::releaseNotification($mid, $map['map_date']);
            }
        }
        return $sent;
    }

    private static function sendTelegram(array $cfg, array $map, array $analysis): bool
    {
        $max = (int) $analysis['max_level'];
        $dateLabel = gr_date($map['map_date']);
        $title = 'Χάρτης κινδύνου πυρκαγιάς Κρήτης';
        $message = 'Για ' . $dateLabel . ', η ' . self::REGION_LABEL . ' είναι έως επίπεδο '
            . $max . ' - ' . self::RISK_LEVELS[$max]['label'] . ".\n"
            . self::formatLevels($analysis['levels'])
            . "\n\nΧάρτης: " . $map['image_url'];

        $ok = false;
        $commandChat = trim((string) ($cfg['command_chat_id'] ?? ''));
        if ($commandChat !== '') {
            $ok = TelegramService::sendToChat($cfg, $commandChat, $title, $message) || $ok;
        }
        $teamChat = trim((string) ($cfg['team_chat_id'] ?? ''));
        if ($teamChat !== '') {
            $ok = TelegramService::sendToChat($cfg, $teamChat, $title, $message) || $ok;
        }
        return $ok;
    }

    private static function formatLevels(array $levels): string
    {
        $order = ['Χανιά', 'Ρέθυμνο', 'Ηράκλειο', 'Λασίθι'];
        $lines = [];
        foreach ($order as $unit) {
            if (!isset($levels[$unit])) { continue; }
            $lines[] = $unit . ': επίπεδο ' . (int) $levels[$unit]['level'] . ' - ' . $levels[$unit]['label'];
        }
        return implode("\n", $lines);
    }

    private static function claimNotification(int $municipalityId, string $mapDate, array $levels, string $imageUrl): bool
    {
        return dbq(
            "INSERT IGNORE INTO fire_risk_map_notifications
             (municipality_id, map_date, levels_json, image_url, telegram_notified_at)
             VALUES (:mid, :map_date, :levels, :url, NOW())",
            [
                'mid' => $municipalityId,
                'map_date' => $mapDate,
                'levels' => json_encode($levels, JSON_UNESCAPED_UNICODE),
                'url' => $imageUrl,
            ]
        )->rowCount() > 0;
    }

    private static function releaseNotification(int $municipalityId, string $mapDate): void
    {
        dbq(
            'DELETE FROM fire_risk_map_notifications WHERE municipality_id = :mid AND map_date = :map_date',
            ['mid' => $municipalityId, 'map_date' => $mapDate]
        );
    }

    private static function cleanup(): void
    {
        dbq('DELETE FROM fire_risk_map_notifications WHERE telegram_notified_at < DATE_SUB(NOW(), INTERVAL 60 DAY)');
    }

    private static function nearestRiskPixel($img, int $x, int $y, int $radius): ?array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $best = null;
        $bestDistance = PHP_INT_MAX;
        for ($dy = -$radius; $dy <= $radius; $dy++) {
            for ($dx = -$radius; $dx <= $radius; $dx++) {
                $px = $x + $dx;
                $py = $y + $dy;
                if ($px < 0 || $py < 0 || $px >= $w || $py >= $h) { continue; }
                $rgb = self::rgbAt($img, $px, $py);
                if (!self::looksLikeRiskColor($rgb)) { continue; }
                $dist = ($dx * $dx) + ($dy * $dy);
                if ($dist < $bestDistance) {
                    $bestDistance = $dist;
                    $best = $rgb;
                }
            }
        }
        return $best;
    }

    private static function rgbAt($img, int $x, int $y): array
    {
        $raw = imagecolorat($img, $x, $y);
        return [($raw >> 16) & 255, ($raw >> 8) & 255, $raw & 255];
    }

    private static function looksLikeRiskColor(array $rgb): bool
    {
        [$r, $g, $b] = $rgb;
        if ($r > 245 && $g > 245 && $b > 245) { return false; }
        if ($r < 35 && $g < 35 && $b < 35) { return false; }
        return self::classifyDistance($rgb) < 9000;
    }

    private static function classifyRisk(array $rgb): int
    {
        $bestLevel = 2;
        $bestDistance = PHP_INT_MAX;
        foreach (self::RISK_LEVELS as $level => $def) {
            $dist = self::rgbDistance($rgb, $def['rgb']);
            if ($dist < $bestDistance) {
                $bestDistance = $dist;
                $bestLevel = (int) $level;
            }
        }
        return $bestLevel;
    }

    private static function classifyDistance(array $rgb): int
    {
        $best = PHP_INT_MAX;
        foreach (self::RISK_LEVELS as $def) {
            $best = min($best, self::rgbDistance($rgb, $def['rgb']));
        }
        return $best;
    }

    private static function rgbDistance(array $a, array $b): int
    {
        return (($a[0] - $b[0]) ** 2) + (($a[1] - $b[1]) ** 2) + (($a[2] - $b[2]) ** 2);
    }

    private static function dateFromCode(string $code): ?string
    {
        if (!preg_match('/^\d{6}$/', $code)) { return null; }
        $year = 2000 + (int) substr($code, 0, 2);
        $month = (int) substr($code, 2, 2);
        $day = (int) substr($code, 4, 2);
        if (!checkdate($month, $day, $year)) { return null; }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function fetchText(string $url): string
    {
        $body = self::httpGet($url);
        if (trim($body) === '') {
            throw new RuntimeException('Η σελίδα της Πολιτικής Προστασίας επέστρεψε κενό περιεχόμενο.');
        }
        return $body;
    }

    private static function fetchBinary(string $url): string
    {
        $body = self::httpGet($url);
        if ($body === '') {
            throw new RuntimeException('Η εικόνα του χάρτη είναι κενή.');
        }
        return $body;
    }

    private static function httpGet(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'header' => "User-Agent: SynDrasi Fire Risk Monitor\r\nAccept: */*\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Αποτυχία λήψης από την Πολιτική Προστασία.');
        }
        return (string) $body;
    }
}
