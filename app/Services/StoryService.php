<?php
/**
 * SynDrasi - Story / Απολογισμός Δράσης.
 * Aggregates the full history of a (closed/completed) event and computes
 * response-time metrics. All data already exists; nothing new is collected.
 *
 * StoryService::build($eventId) returns a single array consumed by the
 * in-app view, the downloadable HTML and the public version.
 */
class StoryService
{
    /** Team colour palette (one per team, by index). */
    private const PALETTE = ['#2563eb','#16a34a','#ea6c0a','#9333ea','#dc2626','#0891b2','#ca8a04','#db2777','#475569','#15803d'];

    public static function build(int $eid): array
    {
        $event = Event::find($eid);
        if (!$event) { return []; }
        $mid = (int) $event['municipality_id'];

        /* ── Teams (approved) + colour ───────────────────────────────── */
        $teamRows = dbq(
            "SELECT ea.team_id, t.name AS team_name, t.phone AS team_phone,
                    ea.approved_people, ea.actual_people,
                    ea.actual_arrival_time, ea.actual_departure_time, ea.mission_commander_id
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             WHERE ea.event_id = :eid AND ea.status = 'approved'
             ORDER BY t.name",
            ['eid' => $eid]
        )->fetchAll();

        $teams = [];
        $i = 0;
        foreach ($teamRows as $r) {
            $tid = (int) $r['team_id'];
            $teams[$tid] = [
                'id'          => $tid,
                'name'        => $r['team_name'],
                'phone'       => $r['team_phone'],
                'color'       => self::PALETTE[$i % count(self::PALETTE)],
                'approved'    => (int) $r['approved_people'],
                'actual'      => $r['actual_people'] !== null ? (int) $r['actual_people'] : null,
                'arrival'     => $r['actual_arrival_time'],
                'departure'   => $r['actual_departure_time'],
                'commander'   => $r['mission_commander_id'] ? TeamMember::find((int) $r['mission_commander_id']) : null,
            ];
            $i++;
        }
        $teamName = fn($tid) => $teams[(int) $tid]['name'] ?? ('Ομάδα #' . (int) $tid);

        /* ── Location pings (routes) ─────────────────────────────────── */
        $pingsByTeam = [];
        foreach (dbq(
            "SELECT team_id, latitude, longitude, created_at FROM location_pings
             WHERE event_id = :eid AND latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY team_id, created_at",
            ['eid' => $eid]
        )->fetchAll() as $p) {
            $pingsByTeam[(int) $p['team_id']][] = [
                'lat' => (float) $p['latitude'], 'lng' => (float) $p['longitude'], 'at' => $p['created_at'],
            ];
        }

        /* ── Messages / orders / geo points ──────────────────────────── */
        $messages = dbq(
            "SELECT team_id, sender_role, kind, status_code, body, latitude, longitude, point_kind,
                    acknowledged_at, created_at
             FROM event_messages WHERE event_id = :eid ORDER BY created_at",
            ['eid' => $eid]
        )->fetchAll();

        /* ── Requests (gps / photo / video) ──────────────────────────── */
        $gpsReq   = self::reqRows('gps_requests', $eid);
        $photoReq = self::reqRows('photo_requests', $eid);
        $videoReq = self::reqRows('video_requests', $eid);

        /* ── Media ───────────────────────────────────────────────────── */
        $photos = dbq(
            "SELECT id, team_id, latitude, longitude, caption, created_at FROM event_photos
             WHERE event_id = :eid ORDER BY created_at",
            ['eid' => $eid]
        )->fetchAll();
        $videos = dbq(
            "SELECT id, team_id, latitude, longitude, caption, duration_sec, created_at FROM event_videos
             WHERE event_id = :eid ORDER BY created_at",
            ['eid' => $eid]
        )->fetchAll();

        /* ── Shortages / SOS / check-ins ─────────────────────────────── */
        $shortages = dbq(
            "SELECT team_id, shortage_type, severity, title, status, created_at, acknowledged_at, resolved_at
             FROM shortage_reports WHERE event_id = :eid ORDER BY created_at",
            ['eid' => $eid]
        )->fetchAll();
        $sos = dbq(
            "SELECT team_id, latitude, longitude, note, status, created_at, acknowledged_at, resolved_at
             FROM sos_alerts WHERE event_id = :eid ORDER BY created_at",
            ['eid' => $eid]
        )->fetchAll();
        $checkins = dbq(
            "SELECT oc.team_id, oc.status, oc.present_people, oc.expected_people, oc.checked_in_at
             FROM operational_checkins oc
             JOIN ( SELECT team_id, MAX(id) AS mid FROM operational_checkins WHERE event_id = :eid1 GROUP BY team_id ) last
               ON last.mid = oc.id
             WHERE oc.event_id = :eid2",
            ['eid1' => $eid, 'eid2' => $eid]
        )->fetchAll();
        $checkinByTeam = [];
        foreach ($checkins as $c) { $checkinByTeam[(int) $c['team_id']] = $c; }

        /* ── Response metrics per team ───────────────────────────────── */
        $metrics = self::metrics($teams, $gpsReq, $photoReq, $videoReq, $messages);

        /* ── Unified timeline ────────────────────────────────────────── */
        $tl = [];
        $add = function ($at, $actor, $tid, $icon, $color, $title, $detail = '') use (&$tl, $teamName) {
            if (!$at) { return; }
            $tl[] = [
                'at'    => $at,
                'time'  => substr((string) $at, 11, 5),
                'date'  => substr((string) $at, 0, 10),
                'actor' => $actor, // 'command' | 'team' | 'system'
                'team'  => $tid ? $teamName($tid) : null,
                'icon'  => $icon,
                'color' => $color,
                'title' => $title,
                'detail'=> $detail,
            ];
        };
        foreach ($checkinByTeam as $tid => $c) {
            $add($c['checked_in_at'], 'team', $tid, 'bi-box-arrow-in-right', '#16a34a', 'Δήλωση παρουσίας', self::checkinLabel($c['status']) . ' · ' . (int) $c['present_people'] . ' άτομα');
        }
        foreach ($messages as $m) {
            $actor = $m['sender_role'] === 'command' ? 'command' : 'team';
            if ($m['kind'] === 'order') {
                $add($m['created_at'], 'command', $m['team_id'], 'bi-megaphone-fill', '#ea6c0a', 'Εντολή', $m['body']);
                if ($m['acknowledged_at']) {
                    $add($m['acknowledged_at'], 'team', $m['team_id'], 'bi-check2-all', '#16a34a', 'Επιβεβαίωση εντολής (ACK)', '');
                }
            } elseif (!empty($m['point_kind'])) {
                $add($m['created_at'], 'command', $m['team_id'], 'bi-geo-alt-fill', '#2563eb', 'Σημείο: ' . self::pointKind($m['point_kind']), $m['body']);
            } elseif ($m['kind'] === 'status') {
                $add($m['created_at'], 'team', $m['team_id'], 'bi-lightning-charge-fill', '#0891b2', 'Ενημέρωση', $m['body']);
            } else {
                $add($m['created_at'], $actor, $m['team_id'], 'bi-chat-dots', '#64748b', 'Μήνυμα', $m['body']);
            }
        }
        foreach ([['gps', $gpsReq, 'Στίγμα', 'bi-geo'], ['photo', $photoReq, 'Φωτογραφία', 'bi-camera'], ['video', $videoReq, 'Βίντεο', 'bi-camera-video']] as [$k, $rows, $lbl, $ic]) {
            foreach ($rows as $rq) {
                $add($rq['created_at'], 'command', $rq['team_id'], $ic, '#9333ea', 'Αίτημα ' . $lbl, '');
                if ($rq['fulfilled_at']) {
                    $sec = strtotime($rq['fulfilled_at']) - strtotime($rq['created_at']);
                    $add($rq['fulfilled_at'], 'team', $rq['team_id'], $ic . '-fill', '#16a34a', $lbl . ' στάλθηκε', 'απόκριση: ' . self::dur($sec));
                }
            }
        }
        foreach ($photos as $p) { $add($p['created_at'], 'team', $p['team_id'], 'bi-image-fill', '#0891b2', 'Φωτογραφία', $p['caption'] ?: ''); }
        foreach ($videos as $v) { $add($v['created_at'], 'team', $v['team_id'], 'bi-camera-video-fill', '#0891b2', 'Βίντεο', $v['caption'] ?: ''); }
        foreach ($shortages as $sh) {
            $add($sh['created_at'], 'team', $sh['team_id'], 'bi-exclamation-triangle-fill', '#dc2626', 'Έλλειψη: ' . $sh['title'], self::severity($sh['severity']));
            if ($sh['acknowledged_at']) { $add($sh['acknowledged_at'], 'command', $sh['team_id'], 'bi-eye-fill', '#ca8a04', 'Έλλειψη ελήφθη', $sh['title']); }
            if ($sh['resolved_at'])     { $add($sh['resolved_at'], 'command', $sh['team_id'], 'bi-check-circle-fill', '#16a34a', 'Έλλειψη επιλύθηκε', $sh['title'] . ' · χρόνος: ' . self::dur(strtotime($sh['resolved_at']) - strtotime($sh['created_at']))); }
        }
        foreach ($sos as $s) {
            $add($s['created_at'], 'team', $s['team_id'], 'bi-exclamation-octagon-fill', '#dc2626', '🆘 SOS', $s['note'] ?: '');
            if ($s['resolved_at']) { $add($s['resolved_at'], 'command', $s['team_id'], 'bi-shield-check', '#16a34a', 'SOS επιλύθηκε', ''); }
        }
        usort($tl, fn($a, $b) => strcmp((string) $a['at'], (string) $b['at']));

        /* ── Map points ──────────────────────────────────────────────── */
        $mapPoints = [];
        foreach ($messages as $m) {
            if (!empty($m['point_kind']) && $m['latitude'] !== null && $m['longitude'] !== null) {
                $mapPoints[] = ['kind' => $m['point_kind'], 'lat' => (float) $m['latitude'], 'lng' => (float) $m['longitude'], 'label' => self::pointKind($m['point_kind']), 'body' => $m['body'], 'team' => $teamName($m['team_id'])];
            }
        }
        foreach ($photos as $p) { if ($p['latitude'] !== null) { $mapPoints[] = ['kind' => 'photo', 'lat' => (float) $p['latitude'], 'lng' => (float) $p['longitude'], 'label' => 'Φωτό', 'body' => $p['caption'], 'team' => $teamName($p['team_id'])]; } }
        foreach ($videos as $v) { if ($v['latitude'] !== null) { $mapPoints[] = ['kind' => 'video', 'lat' => (float) $v['latitude'], 'lng' => (float) $v['longitude'], 'label' => 'Βίντεο', 'body' => $v['caption'], 'team' => $teamName($v['team_id'])]; } }

        /* ── Summary stats ───────────────────────────────────────────── */
        $totHours = 0.0;
        foreach ($teams as $t) {
            $a = $t['arrival'] ?: $event['start_datetime'];
            $d = $t['departure'] ?: $event['end_datetime'];
            $ppl = $t['actual'] ?? $t['approved'];
            if ($a && $d) { $totHours += max(0, (strtotime($d) - strtotime($a)) / 3600) * $ppl; }
        }
        $orderCount = count(array_filter($messages, fn($m) => $m['kind'] === 'order'));
        $pingCount  = array_sum(array_map('count', $pingsByTeam));
        $summary = [
            'teams'       => count($teams),
            'volunteers'  => array_sum(array_map(fn($t) => $t['actual'] ?? $t['approved'], $teams)),
            'hours'       => round($totHours, 1),
            'orders'      => $orderCount,
            'pings'       => $pingCount,
            'photos'      => count($photos),
            'videos'      => count($videos),
            'shortages'   => count($shortages),
            'sos'         => count($sos),
            'duration_h'  => ($event['start_datetime'] && $event['end_datetime'])
                ? round((strtotime($event['end_datetime']) - strtotime($event['start_datetime'])) / 3600, 1) : null,
        ];

        return [
            'event'       => $event,
            'teams'       => array_values($teams),
            'pingsByTeam' => $pingsByTeam,
            'mapPoints'   => $mapPoints,
            'timeline'    => $tl,
            'metrics'     => $metrics,
            'photos'      => $photos,
            'videos'      => $videos,
            'shortages'   => $shortages,
            'checkins'    => $checkinByTeam,
            'summary'     => $summary,
        ];
    }

    private static function reqRows(string $table, int $eid): array
    {
        return dbq("SELECT team_id, created_at, fulfilled_at FROM {$table} WHERE event_id = :eid", ['eid' => $eid])->fetchAll();
    }

    /** Per-team response-time metrics. */
    private static function metrics(array $teams, array $gps, array $photo, array $video, array $messages): array
    {
        $out = [];
        foreach ($teams as $tid => $t) {
            $out[$tid] = [
                'team'  => $t['name'],
                'color' => $t['color'],
                'gps'   => self::reqStat($gps, $tid),
                'photo' => self::reqStat($photo, $tid),
                'video' => self::reqStat($video, $tid),
                'ack'   => self::ackStat($messages, $tid),
            ];
        }
        return $out;
    }

    /** {sent, answered, avg, median, fastest, slowest} for a request set & team. */
    private static function reqStat(array $rows, int $tid): array
    {
        $durs = [];
        $sent = 0;
        foreach ($rows as $r) {
            if ((int) $r['team_id'] !== $tid) { continue; }
            $sent++;
            if ($r['fulfilled_at']) { $durs[] = strtotime($r['fulfilled_at']) - strtotime($r['created_at']); }
        }
        return self::statBlock($sent, $durs);
    }

    private static function ackStat(array $messages, int $tid): array
    {
        $durs = [];
        $sent = 0;
        foreach ($messages as $m) {
            if ($m['kind'] !== 'order') { continue; }
            if ($m['team_id'] !== null && (int) $m['team_id'] !== $tid) { continue; }
            $sent++;
            if ($m['acknowledged_at']) { $durs[] = strtotime($m['acknowledged_at']) - strtotime($m['created_at']); }
        }
        return self::statBlock($sent, $durs);
    }

    private static function statBlock(int $sent, array $durs): array
    {
        sort($durs);
        $n = count($durs);
        $avg = $n ? (int) round(array_sum($durs) / $n) : null;
        $med = $n ? (int) $durs[intdiv($n, 2)] : null;
        return [
            'sent'        => $sent,
            'answered'    => $n,
            'rate'        => $sent ? round($n / $sent * 100) : null,
            'avg'         => $avg,
            'median'      => $med,
            'fastest'     => $n ? (int) $durs[0] : null,
            'slowest'     => $n ? (int) $durs[$n - 1] : null,
            'avg_label'   => $avg !== null ? self::dur($avg) : '—',
            'median_label'=> $med !== null ? self::dur($med) : '—',
            'avg_min'     => $avg !== null ? round($avg / 60, 1) : null,
        ];
    }

    /** Humanise a duration in seconds. */
    public static function dur(?int $sec): string
    {
        if ($sec === null || $sec < 0) { return '—'; }
        if ($sec < 60) { return $sec . '″'; }
        if ($sec < 3600) { return intdiv($sec, 60) . '′ ' . ($sec % 60) . '″'; }
        return intdiv($sec, 3600) . 'ω ' . intdiv($sec % 3600, 60) . '′';
    }

    private static function pointKind(string $k): string
    {
        return ['move' => 'Μετάβαση', 'incident' => 'Περιστατικό', 'poi' => 'Σημείο ενδιαφέροντος'][$k] ?? $k;
    }
    private static function checkinLabel(string $s): string
    {
        return ['present_full' => 'Πλήρης παρουσία', 'present_partial' => 'Μερική παρουσία', 'departed' => 'Αποχώρηση'][$s] ?? $s;
    }
    private static function severity(string $s): string
    {
        return ['low' => 'Χαμηλή', 'medium' => 'Μεσαία', 'high' => 'Υψηλή', 'critical' => 'Κρίσιμη'][$s] ?? $s;
    }
}
