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
                $len = strlen($html);
                $hasTabs = (str_contains($html, 'id="L1"') || str_contains($html, "id='L1'")) ? 'tabs-ok' : 'no-tabs';
                $snippet = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
                $snippet = mb_substr($snippet, 0, 220, 'UTF-8');
                throw new RuntimeException(
                    'Η πηγή φορτώθηκε αλλά δεν αναγνωρίστηκε κανένα συμβάν. '
                    . "(μήκος={$len}, {$hasTabs}, απόσπασμα: {$snippet})"
                );
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
        $terms = authority_context($municipalityId);
        $eventSingularLc = mb_strtolower($terms['event_singular'] ?? 'Δράση', 'UTF-8');
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
            'instructions' => 'Δημιουργήθηκε ως πρόχειρη ' . $eventSingularLc . ' από συμβάν του Πυροσβεστικού Σώματος. Ελέγξτε τα στοιχεία πριν από δημοσίευση.',
            'status' => 'draft',
            'created_by' => $userId,
        ]);
        dbq('UPDATE fire_service_incidents SET created_event_id = :eid WHERE id = :id', ['eid' => $id, 'id' => $incidentId]);
        return $id;
    }

    public static function mobilizationReviewData(int $incidentId, int $municipalityId): array
    {
        $incident = self::find($incidentId);
        if (!$incident) { throw new RuntimeException('Το συμβάν δεν βρέθηκε.'); }
        if ((int) ($incident['is_current'] ?? 0) !== 1) {
            throw new RuntimeException('Το συμβάν δεν είναι πλέον στο τρέχον snapshot.');
        }

        $title = mb_substr(self::mobilizationTitle($incident), 0, 255);
        $description = self::mobilizationDescription($incident);
        return [
            'incident' => $incident,
            'title' => $title,
            'description' => $description,
            'severity' => self::mobilizationSeverity($incident),
            'location_name' => self::incidentLocationName($incident),
            'teams' => self::mobilizationTeams($municipalityId),
            'existing' => self::existingActiveMobilization($municipalityId, $title, $description),
        ];
    }

    public static function createMobilization(int $incidentId, int $municipalityId, int $userId, array $options = []): array
    {
        $incident = self::find($incidentId);
        if (!$incident) { throw new RuntimeException('Το συμβάν δεν βρέθηκε.'); }
        if ((int) ($incident['is_current'] ?? 0) !== 1) {
            throw new RuntimeException('Το συμβάν δεν είναι πλέον στο τρέχον snapshot.');
        }

        $teamIds = array_key_exists('team_ids', $options)
            ? array_values(array_unique(array_map('intval', (array) $options['team_ids'])))
            : null;
        $requireVehicle = !empty($options['require_vehicle']);
        $requireMedical = !empty($options['require_medical']);
        $members = self::mobilizationTargetMembers($municipalityId, $teamIds, $requireVehicle, $requireMedical);
        if (!$members) {
            throw new RuntimeException('Δεν βρέθηκαν ενεργά μέλη εθελοντικών ομάδων με τα επιλεγμένα κριτήρια.');
        }

        $title = mb_substr(self::mobilizationTitle($incident), 0, 255);
        $description = self::mobilizationDescription($incident);
        $existing = self::existingActiveMobilization($municipalityId, $title, $description);
        if ($existing) {
            return [
                'mobilization_id' => (int) $existing['id'],
                'targeted' => (int) $existing['targeted'],
                'existing' => true,
            ];
        }

        $mobId = Mobilization::create([
            'municipality_id' => $municipalityId,
            'created_by' => $userId,
            'event_id' => null,
            'title' => $title,
            'description' => $description,
            'severity' => self::mobilizationSeverity($incident),
            'location_name' => self::incidentLocationName($incident),
            'latitude' => null,
            'longitude' => null,
            'status' => 'active',
        ]);

        $targets = MobilizationResponse::seedTargets($mobId, $members);
        $mob = Mobilization::find($mobId);
        NotificationService::mobilize($mob, $targets);

        return [
            'mobilization_id' => $mobId,
            'targeted' => count($targets),
            'existing' => false,
        ];
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

    private static function mobilizationTeams(int $municipalityId): array
    {
        return dbq(
            "SELECT vt.id, vt.name, vt.type, vt.contact_person, vt.has_vehicle,
                    vt.has_medical_equipment, vt.default_people_capacity,
                    COUNT(tm.id) AS active_members
             FROM volunteer_teams vt
             LEFT JOIN team_members tm ON tm.team_id = vt.id AND tm.status = 'active'
             WHERE vt.municipality_id = :mid AND vt.status = 'active'
             GROUP BY vt.id, vt.name, vt.type, vt.contact_person, vt.has_vehicle,
                      vt.has_medical_equipment, vt.default_people_capacity
             ORDER BY vt.name",
            ['mid' => $municipalityId]
        )->fetchAll();
    }

    private static function mobilizationTargetMembers(int $municipalityId, ?array $teamIds, bool $requireVehicle, bool $requireMedical): array
    {
        if (is_array($teamIds) && count($teamIds) === 0) {
            return [];
        }

        $sql = "SELECT tm.*, vt.name AS team_name
                FROM team_members tm
                JOIN volunteer_teams vt ON vt.id = tm.team_id
                WHERE tm.municipality_id = :mid
                  AND tm.status = 'active'
                  AND vt.status = 'active'";
        $params = ['mid' => $municipalityId];

        if (is_array($teamIds)) {
            $placeholders = [];
            foreach ($teamIds as $idx => $teamId) {
                $key = 'team' . $idx;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $teamId;
            }
            $sql .= ' AND tm.team_id IN (' . implode(',', $placeholders) . ')';
        }
        if ($requireVehicle) {
            $sql .= ' AND vt.has_vehicle = 1';
        }
        if ($requireMedical) {
            $sql .= ' AND vt.has_medical_equipment = 1';
        }
        $sql .= ' ORDER BY vt.name, tm.full_name';

        return dbq($sql, $params)->fetchAll();
    }

    private static function existingActiveMobilization(int $municipalityId, string $title, string $description): ?array
    {
        return dbq(
            "SELECT mob.id,
                    (SELECT COUNT(*) FROM mobilization_responses r WHERE r.mobilization_id = mob.id) AS targeted
             FROM mobilizations mob
             WHERE mob.municipality_id = :mid
               AND mob.status IN ('open','active')
               AND mob.title = :title
               AND mob.description = :description
             ORDER BY mob.id DESC
             LIMIT 1",
            ['mid' => $municipalityId, 'title' => $title, 'description' => $description]
        )->fetch() ?: null;
    }

    private static function mobilizationTitle(array $incident): string
    {
        $place = $incident['location_text'] ?: $incident['area_text'] ?: $incident['municipality'] ?: 'Πυροσβεστικό συμβάν';
        return 'Κινητοποίηση: ' . $place;
    }

    private static function mobilizationDescription(array $incident): string
    {
        $lines = [
            'Πηγή: Πυροσβεστικό Σώμα',
            'Κατηγορία: ' . ($incident['category'] ?: '—'),
            'Κατάσταση: ' . ($incident['status_label'] ?: '—'),
            'Περιοχή: ' . trim(($incident['region'] ?: '') . ' / ' . ($incident['regional_unit'] ?: ''), ' /'),
        ];
        $location = self::incidentLocationName($incident);
        if ($location !== '') {
            $lines[] = 'Τοποθεσία: ' . $location;
        }
        if (!empty($incident['raw_text'])) {
            $lines[] = '';
            $lines[] = (string) $incident['raw_text'];
        }
        $lines[] = '';
        $lines[] = 'Δημιουργήθηκε άμεσα από τα Συμβάντα Πυροσβεστικής για επιβεβαίωση διαθεσιμότητας εθελοντών.';
        return implode("\n", $lines);
    }

    private static function mobilizationSeverity(array $incident): string
    {
        $status = (string) ($incident['status_label'] ?? '');
        $category = (string) ($incident['category'] ?? '');
        if ($status === 'ΣΕ ΕΞΕΛΙΞΗ' && str_contains($category, 'ΔΑΣΙΚΕΣ')) {
            return 'critical';
        }
        if ($status === 'ΣΕ ΕΞΕΛΙΞΗ') {
            return 'high';
        }
        if ($status === 'ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ') {
            return 'medium';
        }
        return 'low';
    }

    private static function incidentLocationName(array $incident): string
    {
        return trim(implode(' - ', array_filter([
            $incident['municipality'] ?? '',
            $incident['area_text'] ?? '',
            $incident['location_text'] ?? '',
        ], fn($v) => trim((string) $v) !== '')), " -");
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
        $headers = [
            'User-Agent: SynDrasi Fire Incident Monitor',
            'Accept: text/html,application/xhtml+xml',
        ];

        $detail = 'άγνωστο σφάλμα';

        if (function_exists('curl_init')) {
            $ch = curl_init(self::SOURCE_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $html = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($html !== false && $code < 400 && trim((string) $html) !== '') {
                return (string) $html;
            }

            $detail = $html === false ? ($err ?: 'cURL error') : ('HTTP ' . $code);
            error_log('[FireService] cURL fetch failed: ' . $detail);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 25,
                'header' => implode("\r\n", $headers) . "\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $html = @file_get_contents(self::SOURCE_URL, false, $context);
        if ($html === false || trim($html) === '') {
            if (!empty($http_response_header) && is_array($http_response_header)) {
                $detail = mb_substr((string) $http_response_header[0], 0, 80);
            }
            throw new RuntimeException('Αποτυχία λήψης από το Πυροσβεστικό Σώμα (' . $detail . ').');
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
