<?php
/**
 * Imports and normalizes the official Fire Service active-incidents page.
 */
class FireServiceIncidentService
{
    public const SOURCE_URL = 'https://www.fireservice.gr/apps/fire2019/symvanta/page.php';

    private const CATEGORIES = [
        'L1' => 'ΔΑΣΙΚΕΣ ΠΥΡΚΑΓΙΕΣ',
        'P1' => 'ΑΣΤΙΚΕΣ ΠΥΡΚΑΓΙΕΣ',
        'Q1' => 'ΠΑΡΟΧΕΣ ΒΟΗΘΕΙΑΣ',
    ];

    private const STATUSES = ['ΣΕ ΕΞΕΛΙΞΗ', 'ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ', 'ΠΛΗΡΗΣ ΕΛΕΓΧΟΣ', 'ΛΗΞΗ'];
    private const TELEGRAM_STATUSES = ['ΣΕ ΕΞΕΛΙΞΗ', 'ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ'];

    public static function sync(): array
    {
        @set_time_limit(90);
        $fetchId = self::startFetch();
        try {
            $html = self::fetchHtml();
            $incidents = self::parse($html);
            if (!$incidents) {
                throw new RuntimeException('Η πηγή φορτώθηκε αλλά δεν αναγνωρίστηκε κανένα συμβάν.');
            }
            self::upsertIncidents($incidents, $fetchId);
            $telegramSent = self::notifyCreteTelegramIncidents();
            self::cleanup();
            dbq(
                "UPDATE fire_service_fetches
                 SET success = 1, http_status = 200, incidents_found = :cnt, raw_hash = :hash
                 WHERE id = :id",
                ['cnt' => count($incidents), 'hash' => hash('sha256', $html), 'id' => $fetchId]
            );
            return ['success' => true, 'fetch_id' => $fetchId, 'incidents' => count($incidents), 'telegram_sent' => $telegramSent, 'error' => null];
        } catch (Throwable $e) {
            dbq(
                "UPDATE fire_service_fetches SET success = 0, error_message = :err WHERE id = :id",
                ['err' => mb_substr($e->getMessage(), 0, 500), 'id' => $fetchId]
            );
            return ['success' => false, 'fetch_id' => $fetchId, 'incidents' => 0, 'telegram_sent' => 0, 'error' => $e->getMessage()];
        }
    }

    public static function list(array $filters = []): array
    {
        $sql = "SELECT * FROM fire_service_incidents
                WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $params = [];
        if (($filters['current'] ?? '1') === '1') {
            $sql .= " AND is_current = 1";
        }
        foreach (['region', 'regional_unit', 'category', 'status_label'] as $key) {
            if (!empty($filters[$key])) {
                $sql .= " AND {$key} = :{$key}";
                $params[$key] = $filters[$key];
            }
        }
        if (!empty($filters['q'])) {
            $sql .= " AND (raw_text LIKE :q OR municipality LIKE :q OR area_text LIKE :q OR location_text LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        $sql .= " ORDER BY is_current DESC, FIELD(status_label,'ΣΕ ΕΞΕΛΙΞΗ','ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ','ΠΛΗΡΗΣ ΕΛΕΓΧΟΣ','ΛΗΞΗ'), last_seen_at DESC, id DESC";
        return dbq($sql, $params)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        return dbq('SELECT * FROM fire_service_incidents WHERE id = :id LIMIT 1', ['id' => $id])->fetch() ?: null;
    }

    public static function latestFetch(): ?array
    {
        return dbq('SELECT * FROM fire_service_fetches ORDER BY fetched_at DESC, id DESC LIMIT 1')->fetch() ?: null;
    }

    public static function options(): array
    {
        $rows = dbq(
            "SELECT DISTINCT region, regional_unit, category, status_label
             FROM fire_service_incidents
             WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchAll();
        $out = ['regions' => [], 'regional_units' => [], 'categories' => [], 'statuses' => self::STATUSES];
        foreach ($rows as $r) {
            foreach ([['regions', 'region'], ['regional_units', 'regional_unit'], ['categories', 'category']] as [$dst, $src]) {
                if (!empty($r[$src])) { $out[$dst][$r[$src]] = $r[$src]; }
            }
        }
        foreach (['regions', 'regional_units', 'categories'] as $k) {
            $out[$k] = array_values($out[$k]);
            sort($out[$k], SORT_NATURAL);
        }
        return $out;
    }

    public static function defaultFiltersForMunicipality(?array $municipality): array
    {
        $unit = self::creteRegionalUnitForText(($municipality['name'] ?? '') . ' ' . ($municipality['city'] ?? ''));
        return [
            'region' => 'ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ',
            'regional_unit' => $unit ?: '',
            'current' => '1',
        ];
    }

    public static function creteAlert(): array
    {
        $rows = dbq(
            "SELECT status_label, COUNT(*) AS cnt
             FROM fire_service_incidents
             WHERE is_current = 1 AND region = 'ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ'
             GROUP BY status_label"
        )->fetchAll();
        $total = 0;
        $byStatus = [];
        foreach ($rows as $r) {
            $byStatus[$r['status_label']] = (int) $r['cnt'];
            $total += (int) $r['cnt'];
        }
        $latest = dbq(
            "SELECT * FROM fire_service_incidents
             WHERE is_current = 1 AND region = 'ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ'
             ORDER BY last_seen_at DESC LIMIT 5"
        )->fetchAll();
        return ['total' => $total, 'by_status' => $byStatus, 'latest' => $latest, 'fetch' => self::latestFetch()];
    }

    public static function createEventDraft(int $incidentId, int $municipalityId, int $userId): int
    {
        $incident = self::find($incidentId);
        if (!$incident) { throw new RuntimeException('Το συμβάν δεν βρέθηκε.'); }
        if (!empty($incident['created_event_id'])) {
            return (int) $incident['created_event_id'];
        }

        $start = date('Y-m-d H:i:s');
        $end = date('Y-m-d H:i:s', strtotime('+4 hours'));
        $title = 'Πυροσβεστικό συμβάν: ' . ($incident['location_text'] ?: $incident['area_text'] ?: $incident['municipality']);
        $description = "Πηγή: Πυροσβεστικό Σώμα\n"
            . "Κατηγορία: {$incident['category']}\n"
            . "Κατάσταση: {$incident['status_label']}\n"
            . "Περιοχή: {$incident['region']} / {$incident['regional_unit']}\n\n"
            . $incident['raw_text'];
        $id = Event::create([
            'municipality_id' => $municipalityId,
            'category_id' => self::fireCategoryId(),
            'title' => mb_substr($title, 0, 255),
            'description' => $description,
            'location_name' => trim(($incident['municipality'] ?: '') . ' - ' . ($incident['area_text'] ?: ''), " -"),
            'address' => $incident['location_text'] ?: null,
            'latitude' => null,
            'longitude' => null,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'requested_people' => 0,
            'requested_vehicle' => 0,
            'requested_medical_equipment' => 0,
            'instructions' => 'Δημιουργήθηκε ως πρόχειρη δράση από συμβάν του Πυροσβεστικού Σώματος. Ελέγξτε τα στοιχεία πριν από δημοσίευση.',
            'status' => 'draft',
            'created_by' => $userId,
        ]);
        dbq('UPDATE fire_service_incidents SET created_event_id = :eid WHERE id = :id', ['eid' => $id, 'id' => $incidentId]);
        return $id;
    }

    public static function parse(string $html): array
    {
        $out = [];
        foreach (self::CATEGORIES as $tabId => $category) {
            if (!preg_match('#<div\s+id="' . preg_quote($tabId, '#') . '"[^>]*>(.*?)(?=<div\s+id="[LPQ]1"|</div>\s*</div>\s*<script)#si', $html, $m)) {
                continue;
            }
            $section = $m[1];
            $currentStatus = '';
            $pattern = '#(ΣΕ\s+ΕΞΕΛΙΞΗ|ΜΕΡΙΚΟΣ\s+ΕΛΕΓΧΟΣ|ΠΛΗΡΗΣ\s+ΕΛΕΓΧΟΣ|ΛΗΞΗ)\s*\(\d+\)|<div class="(?:panel panel-[^"]+|bg-info)"><div class="panel-heading">(.*?)</div></div>#su';
            if (!preg_match_all($pattern, $section, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $currentStatus = preg_replace('/\s+/u', ' ', trim($match[1]));
                    continue;
                }
                if ($currentStatus === '') { continue; }
                $incident = self::parseIncidentBlock($match[2], $category, $currentStatus);
                if ($incident) { $out[] = $incident; }
            }
        }
        return $out;
    }

    private static function parseIncidentBlock(string $html, string $category, string $status): ?array
    {
        $text = preg_replace('#<br\s*/?>#iu', "\n", $html);
        $text = preg_replace('#</t[dh]>#iu', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_values(array_filter(array_map(fn($l) => preg_replace('/\s+/u', ' ', trim($l)), preg_split('/\r\n|\r|\n/', $text))));
        if (!$lines) { return null; }
        $region = $municipalityLine = $location = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, 'ΠΕΡΙΦΕΡΕΙΑ ')) { $region = $line; continue; }
            if (str_starts_with($line, 'Δ. ')) { $municipalityLine = $line; continue; }
            if ($region && $municipalityLine && $location === '' && !preg_match('/^(ΕΝΑΡΞΗ|ΜΕΡΙΚΟΣ|ΠΛΗΡΗΣ|Τελευταία)/u', $line)) {
                $location = $line;
            }
        }
        if ($region === '' || $municipalityLine === '') { return null; }
        [$municipality, $area] = self::splitMunicipalityLine($municipalityLine);
        $regionalUnit = self::creteRegionalUnitForText($municipality) ?: self::fallbackRegionalUnit($region, $municipality);
        $raw = implode(' · ', $lines);
        $fingerprint = hash('sha256', implode('|', [$category, $region, $municipality, $area, $location]));
        return [
            'fingerprint' => $fingerprint,
            'category' => $category,
            'status_label' => $status,
            'region' => $region,
            'regional_unit' => $regionalUnit,
            'municipality' => $municipality,
            'area_text' => $area,
            'location_text' => $location,
            'raw_text' => $raw,
        ];
    }

    private static function upsertIncidents(array $incidents, int $fetchId): void
    {
        dbq('UPDATE fire_service_incidents SET is_current = 0 WHERE is_current = 1');
        foreach ($incidents as $i) {
            dbq(
                "INSERT INTO fire_service_incidents
                 (fingerprint, category, status_label, region, regional_unit, municipality, area_text, location_text, raw_text, first_seen_at, last_seen_at, last_fetch_id, is_current)
                 VALUES (:fingerprint, :category, :status_label, :region, :regional_unit, :municipality, :area_text, :location_text, :raw_text, NOW(), NOW(), :fetch_id, 1)
                 ON DUPLICATE KEY UPDATE
                   category = VALUES(category), status_label = VALUES(status_label), region = VALUES(region),
                   regional_unit = VALUES(regional_unit), municipality = VALUES(municipality), area_text = VALUES(area_text),
                   location_text = VALUES(location_text), raw_text = VALUES(raw_text), last_seen_at = NOW(),
                   last_fetch_id = VALUES(last_fetch_id), is_current = 1",
                array_merge($i, ['fetch_id' => $fetchId])
            );
        }
    }

    private static function cleanup(): void
    {
        dbq('DELETE FROM fire_service_incidents WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        dbq('DELETE FROM fire_service_fetches WHERE fetched_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        dbq('DELETE FROM fire_service_incident_notifications WHERE telegram_notified_at < DATE_SUB(NOW(), INTERVAL 14 DAY)');
    }

    private static function notifyCreteTelegramIncidents(): int
    {
        $statusesSql = "'" . implode("','", self::TELEGRAM_STATUSES) . "'";
        $incidents = dbq(
            "SELECT *
             FROM fire_service_incidents
             WHERE is_current = 1
               AND region = 'ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ'
               AND status_label IN ({$statusesSql})
             ORDER BY FIELD(status_label, 'ΣΕ ΕΞΕΛΙΞΗ', 'ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ'), last_seen_at DESC, id DESC"
        )->fetchAll();
        if (!$incidents) { return 0; }

        $sent = 0;
        foreach (Municipality::all() as $municipality) {
            if (($municipality['status'] ?? 'active') !== 'active') { continue; }
            $mid = (int) $municipality['id'];
            if (!NotificationService::shouldSendTelegram($mid, 'fire_service_crete')) { continue; }

            $cfg = TelegramService::resolveConfig($mid);
            if (empty($cfg['enabled']) || trim((string) ($cfg['bot_token'] ?? '')) === '') { continue; }
            if (trim((string) ($cfg['command_chat_id'] ?? '')) === '' && trim((string) ($cfg['team_chat_id'] ?? '')) === '') { continue; }

            foreach ($incidents as $incident) {
                if (!self::claimTelegramNotification((int) $incident['id'], $mid, (string) $incident['status_label'])) {
                    continue;
                }
                $ok = self::sendIncidentTelegram($cfg, $incident, $mid);
                if ($ok) {
                    $sent++;
                } else {
                    self::releaseTelegramNotification((int) $incident['id'], $mid, (string) $incident['status_label']);
                }
            }
        }
        return $sent;
    }

    private static function claimTelegramNotification(int $incidentId, int $municipalityId, string $status): bool
    {
        return dbq(
            "INSERT IGNORE INTO fire_service_incident_notifications
             (fire_service_incident_id, municipality_id, status_label, telegram_notified_at)
             VALUES (:iid, :mid, :st, NOW())",
            ['iid' => $incidentId, 'mid' => $municipalityId, 'st' => $status]
        )->rowCount() > 0;
    }

    private static function releaseTelegramNotification(int $incidentId, int $municipalityId, string $status): void
    {
        dbq(
            "DELETE FROM fire_service_incident_notifications
             WHERE fire_service_incident_id = :iid AND municipality_id = :mid AND status_label = :st",
            ['iid' => $incidentId, 'mid' => $municipalityId, 'st' => $status]
        );
    }

    private static function sendIncidentTelegram(array $cfg, array $incident, int $municipalityId): bool
    {
        $hadPrevious = (int) dbq(
            "SELECT COUNT(*)
             FROM fire_service_incident_notifications
             WHERE fire_service_incident_id = :iid AND municipality_id = :mid AND status_label <> :st",
            ['iid' => (int) $incident['id'], 'mid' => $municipalityId, 'st' => (string) $incident['status_label']]
        )->fetchColumn() > 0;

        $title = $hadPrevious ? 'Αλλαγή κατάστασης Πυροσβεστικής' : 'Νέο συμβάν Πυροσβεστικής Κρήτης';
        $message = self::formatIncidentTelegramMessage($incident);
        $url = self::absoluteUrl('/fire-service?region=' . rawurlencode('ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ'));
        $ok = false;

        $commandChat = trim((string) ($cfg['command_chat_id'] ?? ''));
        if ($commandChat !== '') {
            $ok = TelegramService::sendToChat($cfg, $commandChat, $title, $message, $url) || $ok;
        }

        $teamChat = trim((string) ($cfg['team_chat_id'] ?? ''));
        if ($teamChat !== '') {
            $ok = TelegramService::sendToChat($cfg, $teamChat, $title, $message, $url) || $ok;
        }

        return $ok;
    }

    private static function formatIncidentTelegramMessage(array $incident): string
    {
        $parts = [
            'Κατηγορία: ' . ($incident['category'] ?: '—'),
            'Κατάσταση: ' . ($incident['status_label'] ?: '—'),
            'Περιοχή: ' . trim(($incident['regional_unit'] ?: 'ΚΡΗΤΗ') . ' / ' . ($incident['municipality'] ?: ''), ' /'),
        ];
        if (!empty($incident['area_text'])) {
            $parts[] = 'Δημοτική ενότητα/περιοχή: ' . $incident['area_text'];
        }
        if (!empty($incident['location_text'])) {
            $parts[] = 'Τοποθεσία: ' . $incident['location_text'];
        }
        if (!empty($incident['raw_text'])) {
            $parts[] = mb_substr((string) $incident['raw_text'], 0, 500);
        }
        return implode("\n", $parts);
    }

    private static function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . url($path);
    }

    private static function startFetch(): int
    {
        dbq('INSERT INTO fire_service_fetches (source_url, success) VALUES (:url, 0)', ['url' => self::SOURCE_URL]);
        return (int) db()->lastInsertId();
    }

    private static function fetchHtml(): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 25,
                'header' => "User-Agent: SynDrasi Fire Incident Monitor\r\nAccept: text/html,application/xhtml+xml\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $html = @file_get_contents(self::SOURCE_URL, false, $context);
        if ($html === false || trim($html) === '') {
            throw new RuntimeException('Αποτυχία λήψης από το Πυροσβεστικό Σώμα.');
        }
        return $html;
    }

    private static function splitMunicipalityLine(string $line): array
    {
        $line = preg_replace('/^Δ\.\s*/u', '', trim($line));
        $parts = array_map('trim', explode(' - ', $line));
        $municipality = $parts[0] ?? $line;
        $area = implode(' - ', array_slice($parts, 1));
        return [$municipality, $area];
    }

    private static function fallbackRegionalUnit(string $region, string $municipality): string
    {
        return $region === 'ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ' ? (self::creteRegionalUnitForText($municipality) ?: 'ΚΡΗΤΗ') : '';
    }

    private static function creteRegionalUnitForText(string $text): string
    {
        $n = self::norm($text);
        $map = [
            'Π.Ε. ΗΡΑΚΛΕΙΟΥ' => ['ΗΡΑΚΛΕΙΟΥ','ΧΕΡΣΟΝΗΣΟΥ','ΜΑΛΕΒΙΖΙΟΥ','ΜΙΝΩΑ ΠΕΔΙΑΔΑΣ','ΑΡΧΑΝΩΝ','ΑΣΤΕΡΟΥΣΙΩΝ','ΓΟΡΤΥΝΑΣ','ΦΑΙΣΤΟΥ','ΒΙΑΝΝΟΥ'],
            'Π.Ε. ΛΑΣΙΘΙΟΥ' => ['ΑΓΙΟΥ ΝΙΚΟΛΑΟΥ','ΙΕΡΑΠΕΤΡΡΑΣ','ΙΕΡΑΠΕΤΡΑΣ','ΣΗΤΕΙΑΣ','ΟΡΟΠΕΔΙΟΥ ΛΑΣΙΘΙΟΥ'],
            'Π.Ε. ΡΕΘΥΜΝΟΥ' => ['ΡΕΘΥΜΝΗΣ','ΑΜΑΡΙΟΥ','ΑΓΙΟΥ ΒΑΣΙΛΕΙΟΥ','ΜΥΛΟΠΟΤΑΜΟΥ','ΑΝΩΓΕΙΩΝ'],
            'Π.Ε. ΧΑΝΙΩΝ' => ['ΧΑΝΙΩΝ','ΚΙΣΣΑΜΟΥ','ΠΛΑΤΑΝΙΑ','ΑΠΟΚΟΡΩΝΟΥ','ΣΦΑΚΙΩΝ','ΚΑΝΤΑΝΟΥ','ΣΕΛΙΝΟΥ','ΓΑΥΔΟΥ'],
        ];
        foreach ($map as $unit => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($n, self::norm($needle))) { return $unit; }
            }
        }
        return '';
    }

    private static function norm(string $text): string
    {
        $text = mb_strtoupper($text, 'UTF-8');
        return strtr($text, ['Ά'=>'Α','Έ'=>'Ε','Ή'=>'Η','Ί'=>'Ι','Ό'=>'Ο','Ύ'=>'Υ','Ώ'=>'Ω','Ϊ'=>'Ι','Ϋ'=>'Υ']);
    }

    private static function fireCategoryId(): ?int
    {
        $id = dbq("SELECT id FROM event_categories WHERE name LIKE '%Πυρ%' OR name LIKE '%Έκτακ%' ORDER BY id LIMIT 1")->fetchColumn();
        return $id ? (int) $id : null;
    }
}
