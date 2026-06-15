<?php
/**
 * SynDrasi - Operational page (Επιχειρησιακή Σελίδα) for the municipality.
 */
class OperationController
{
    /** GET /operations — command-centre event selector */
    public function index()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid = current_municipality_id();

        $live = dbq(
            "SELECT e.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM event_applications ea WHERE ea.event_id=e.id AND ea.status='approved') AS teams_approved,
                    (SELECT COUNT(*) FROM operational_checkins oc WHERE oc.event_id=e.id AND oc.status='present') AS teams_present,
                    (SELECT COUNT(*) FROM shortage_reports sr WHERE sr.event_id=e.id AND sr.status='open') AS open_shortages
             FROM events e LEFT JOIN event_categories c ON c.id=e.category_id
             WHERE e.municipality_id = :mid
               AND (
                 e.status = 'active'
                 OR (e.status IN ('open','confirmed','review')
                     AND NOW() >= e.start_datetime
                     AND NOW() <= e.end_datetime)
               )
             ORDER BY e.start_datetime ASC",
            ['mid' => $mid]
        )->fetchAll();

        $startingSoon = dbq(
            "SELECT e.*, c.name AS category_name,
                    TIMESTAMPDIFF(MINUTE, NOW(), e.start_datetime) AS mins_until,
                    (SELECT COUNT(*) FROM event_applications ea WHERE ea.event_id=e.id AND ea.status='approved') AS teams_approved
             FROM events e LEFT JOIN event_categories c ON c.id=e.category_id
             WHERE e.municipality_id = :mid
               AND e.status IN ('open','confirmed','review')
               AND e.start_datetime > NOW()
               AND e.start_datetime <= DATE_ADD(NOW(), INTERVAL 60 MINUTE)
             ORDER BY e.start_datetime ASC",
            ['mid' => $mid]
        )->fetchAll();

        $confirmed = dbq(
            "SELECT e.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM event_applications ea WHERE ea.event_id=e.id AND ea.status='approved') AS teams_approved
             FROM events e LEFT JOIN event_categories c ON c.id=e.category_id
             WHERE e.municipality_id = :mid
               AND e.status IN ('confirmed','review')
               AND e.start_datetime > DATE_ADD(NOW(), INTERVAL 60 MINUTE)
             ORDER BY e.start_datetime ASC LIMIT 20",
            ['mid' => $mid]
        )->fetchAll();

        render('operations/index', [
            'pageTitle'    => 'Κέντρο Επιχειρήσεων',
            'live'         => $live,
            'startingSoon' => $startingSoon,
            'confirmed'    => $confirmed,
        ]);
    }

    /** GET /operations/events/{id} */
    public function show($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);
        $mid   = $event['municipality_id'];
        $munSettings = MunicipalitySetting::all($mid);

        render('operations/event', [
            'pageTitle'  => 'Επιχειρησιακή: ' . $event['title'],
            'event'      => $event,
            'mapDefLat'  => $munSettings['map_lat']  ?? '',
            'mapDefLng'  => $munSettings['map_lng']  ?? '',
            'mapDefZoom' => $munSettings['map_zoom'] ?? '13',
            'config'     => ['map_refresh_seconds' => 20],
        ]);
    }

    /** GET /operations/events/{id}/gate-qr — full-screen Gate QR for team check-in. */
    public function gateQr($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);

        render('operations/gate-qr', [
            'pageTitle' => 'QR Πύλης · ' . $event['title'],
            'event'     => $event,
        ], false);  // standalone full-screen
    }

    /**
     * GET /operations/events/{id}/status  — full JSON snapshot for live polling.
     */
    public function status($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);
        $eid   = $event['id'];

        $teams = $this->teamStatusList($eid);

        $totalApproved  = 0;
        $totalPresent   = 0;
        $checkedInCount = 0;
        foreach ($teams as $t) {
            $totalApproved += (int) $t['approved_people'];
            if ($t['present_people'] !== null) {
                $totalPresent += (int) $t['present_people'];
            }
            if ($t['checkin_status'] && $t['checkin_status'] !== 'pending') {
                $checkedInCount++;
            }
        }
        $coverage = $totalApproved > 0 ? round($totalPresent / $totalApproved * 100) : 0;

        $shortages = dbq(
            "SELECT sr.*, t.name AS team_name, ua.name AS ack_name, ur.name AS res_name
             FROM shortage_reports sr
             JOIN volunteer_teams t ON t.id = sr.team_id
             LEFT JOIN users ua ON ua.id = sr.acknowledged_by
             LEFT JOIN users ur ON ur.id = sr.resolved_by
             WHERE sr.event_id = :eid
             ORDER BY FIELD(sr.status,'open','acknowledged','resolved'),
                      FIELD(sr.severity,'critical','high','medium','low'), sr.created_at DESC",
            ['eid' => $eid]
        )->fetchAll();
        $openShortages = count(array_filter($shortages, fn($s) => $s['status'] === 'open'));

        $notes = dbq(
            "SELECT n.*, u.name AS author_name FROM operational_notes n
             JOIN users u ON u.id = n.user_id
             WHERE n.event_id = :eid ORDER BY n.created_at DESC LIMIT 30",
            ['eid' => $eid]
        )->fetchAll();

        $activity = $this->buildActivityFeed($eid);

        $teamsPayload = [];
        foreach ($teams as $t) {
            $pingAge    = null;
            $pingAgeMin = null;
            if ($t['last_ping_at']) {
                $pingAgeMin = round((time() - strtotime($t['last_ping_at'])) / 60);
                $pingAge    = $pingAgeMin < 60
                    ? $pingAgeMin . ' λεπτά πριν'
                    : round($pingAgeMin / 60, 1) . ' ώρες πριν';
            }
            $teamsPayload[] = [
                'team_id'        => (int) $t['team_id'],
                'team_name'      => $t['team_name'],
                'team_phone'     => $t['team_phone'],
                'checkin_status' => $t['checkin_status'],
                'checkin_msg'    => $t['checkin_msg'],
                'present_people' => (int) $t['present_people'],
                'approved_people'=> (int) $t['approved_people'],
                'last_ping_at'   => $t['last_ping_at'],
                'ping_age'       => $pingAge,
                'ping_age_min'   => $pingAgeMin,
                'ping_lat'       => $t['last_lat'] ? (float)$t['last_lat'] : null,
                'ping_lng'       => $t['last_lng'] ? (float)$t['last_lng'] : null,
            ];
        }

        json_out([
            'ok'           => true,
            'ts'           => date('H:i:s'),
            'event_status' => $event['status'],
            'end_ts'       => strtotime($event['end_datetime']) * 1000,
            'start_ts'     => strtotime($event['start_datetime']) * 1000,
            'stats' => [
                'total_approved' => $totalApproved,
                'total_present'  => $totalPresent,
                'checked_in'     => $checkedInCount,
                'coverage'       => $coverage,
                'open_shortages' => $openShortages,
            ],
            'teams'     => $teamsPayload,
            'shortages' => $shortages,
            'notes'     => $notes,
            'activity'  => $activity,
        ]);
    }

    /** GET /operations/events/{id}/locations */
    public function locations($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);
        $eid   = $event['id'];

        $pings = dbq(
            "SELECT lp.team_id, t.name AS team_name, lp.latitude, lp.longitude,
                    lp.accuracy, lp.message, lp.created_at,
                    TIMESTAMPDIFF(MINUTE, lp.created_at, NOW()) AS age_min
             FROM location_pings lp
             JOIN volunteer_teams t ON t.id = lp.team_id
             WHERE lp.event_id = :eid
               AND lp.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
             ORDER BY lp.created_at DESC",
            ['eid' => $eid]
        )->fetchAll();

        $latest = [];
        foreach ($pings as $p) {
            if (!isset($latest[$p['team_id']])) {
                $latest[$p['team_id']] = $p;
            }
        }

        json_out(['ok' => true, 'pings' => array_values($latest)]);
    }

    /**
     * GET /operations/events/{id}/stream — SSE snapshot endpoint.
     * Sends one complete JSON snapshot per connection, then closes.
     * The browser reconnects every 3 000 ms (set via retry:).
     * This "close-and-retry" pattern works reliably on Apache/XAMPP
     * without long-running PHP or output-buffering issues.
     */
    public function stream($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);
        $eid   = (int) $event['id'];

        // Release session file lock so other tabs don't block
        session_write_close();

        // Kill all PHP output buffers before SSE headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');   // Nginx: skip proxy buffer
        header('Content-Encoding: none');  // Disable mod_deflate / gzip
        header('Connection: close');       // One snapshot per connection

        // Browser reconnects 3 s after this connection closes
        echo "retry: 3000\n";

        $snapshot = $this->buildStreamSnapshot($eid, $event);
        echo "event: update\n";
        echo "data: " . json_encode($snapshot, JSON_UNESCAPED_UNICODE) . "\n\n";

        flush();
        exit;
    }

    /**
     * Unified data snapshot — teams, stats, shortages, notes, activity, map pings.
     * Used by stream() and available as fallback via status() polling.
     */
    private function buildStreamSnapshot(int $eid, array $event): array
    {
        $teams = $this->teamStatusList($eid);

        $totalApproved = $totalPresent = $checkedInCount = 0;
        foreach ($teams as $t) {
            $totalApproved += (int) $t['approved_people'];
            if ($t['present_people'] !== null) {
                $totalPresent += (int) $t['present_people'];
            }
            if ($t['checkin_status'] && $t['checkin_status'] !== 'pending') {
                $checkedInCount++;
            }
        }
        $coverage = $totalApproved > 0 ? round($totalPresent / $totalApproved * 100) : 0;

        $shortages = dbq(
            "SELECT sr.*, t.name AS team_name
             FROM shortage_reports sr
             JOIN volunteer_teams t ON t.id = sr.team_id
             WHERE sr.event_id = :eid
             ORDER BY FIELD(sr.status,'open','acknowledged','resolved'),
                      FIELD(sr.severity,'critical','high','medium','low'), sr.created_at DESC",
            ['eid' => $eid]
        )->fetchAll();

        $notes = dbq(
            "SELECT n.*, u.name AS author_name FROM operational_notes n
             JOIN users u ON u.id = n.user_id
             WHERE n.event_id = :eid ORDER BY n.created_at DESC LIMIT 30",
            ['eid' => $eid]
        )->fetchAll();

        $activity = $this->buildActivityFeed($eid);

        $pingRows = dbq(
            "SELECT lp.team_id, t.name AS team_name, lp.latitude, lp.longitude,
                    lp.accuracy, lp.message, lp.created_at,
                    TIMESTAMPDIFF(MINUTE, lp.created_at, NOW()) AS age_min
             FROM location_pings lp
             JOIN volunteer_teams t ON t.id = lp.team_id
             WHERE lp.event_id = :eid
               AND lp.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
             ORDER BY lp.created_at DESC",
            ['eid' => $eid]
        )->fetchAll();
        $latestPings = [];
        foreach ($pingRows as $p) {
            if (!isset($latestPings[$p['team_id']])) {
                $latestPings[$p['team_id']] = $p;
            }
        }

        $teamsPayload = [];
        foreach ($teams as $t) {
            $pingAge = $pingAgeMin = null;
            if ($t['last_ping_at']) {
                $pingAgeMin = round((time() - strtotime($t['last_ping_at'])) / 60);
                $pingAge    = $pingAgeMin < 60
                    ? $pingAgeMin . ' λεπτά πριν'
                    : round($pingAgeMin / 60, 1) . ' ώρες πριν';
            }
            $teamsPayload[] = [
                'team_id'         => (int) $t['team_id'],
                'team_name'       => $t['team_name'],
                'team_phone'      => $t['team_phone'],
                'checkin_status'  => $t['checkin_status'],
                'checkin_msg'     => $t['checkin_msg'],
                'present_people'  => (int) $t['present_people'],
                'approved_people' => (int) $t['approved_people'],
                'last_ping_at'    => $t['last_ping_at'],
                'ping_age'        => $pingAge,
                'ping_age_min'    => $pingAgeMin,
                'ping_lat'        => $t['last_lat'] ? (float) $t['last_lat'] : null,
                'ping_lng'        => $t['last_lng'] ? (float) $t['last_lng'] : null,
            ];
        }

        $openShortages = count(array_filter($shortages, fn($s) => $s['status'] === 'open'));

        return [
            'ok'           => true,
            'ts'           => date('H:i:s'),
            'event_status' => $event['status'],
            'stats'        => [
                'total_approved' => $totalApproved,
                'total_present'  => $totalPresent,
                'checked_in'     => $checkedInCount,
                'coverage'       => $coverage,
                'open_shortages' => $openShortages,
            ],
            'teams'     => $teamsPayload,
            'shortages' => $shortages,
            'notes'     => $notes,
            'activity'  => $activity,
            'pings'     => array_values($latestPings),
        ];
    }

    /** POST /operations/events/{id}/note */
    public function addNote($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);
        $note  = trim(post_str('note'));
        if (!$note) {
            json_out(['ok' => false, 'error' => 'Empty note.']);
            return;
        }
        dbq(
            "INSERT INTO operational_notes (municipality_id, event_id, user_id, note)
             VALUES (:mid, :eid, :uid, :note)",
            ['mid' => $event['municipality_id'], 'eid' => $event['id'],
             'uid' => $_SESSION['user_id'], 'note' => $note]
        );
        audit('ops_note_added', 'event', $event['id']);
        json_out(['ok' => true, 'ts' => date('H:i')]);
    }

    /* ═══════════════════ WAR ROOM (multi-event) ═══════════════════ */

    /** GET /operations/war-room — all active events on one map. */
    public function warRoom()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid         = current_municipality_id();
        $munSettings = MunicipalitySetting::all($mid);

        // Initial snapshot rendered server-side so the page is useful before SSE.
        $snapshot = $this->buildWarRoomSnapshot($mid);

        render('operations/war-room', [
            'pageTitle'  => 'Κέντρο Συντονισμού — Όλες οι Δράσεις',
            'snapshot'   => $snapshot,
            'mapDefLat'  => $munSettings['map_lat']  ?? '38.0',
            'mapDefLng'  => $munSettings['map_lng']  ?? '23.7',
            'mapDefZoom' => $munSettings['map_zoom'] ?? '11',
        ]);
    }

    /**
     * GET /operations/war-room/stream — SSE snapshot of all active events.
     * Close-and-retry pattern, identical to stream().
     */
    public function warRoomStream()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid = current_municipality_id();

        session_write_close();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        header('Content-Encoding: none');
        header('Connection: close');

        echo "retry: 3000\n";
        $snapshot = $this->buildWarRoomSnapshot($mid);
        echo "event: update\n";
        echo "data: " . json_encode($snapshot, JSON_UNESCAPED_UNICODE) . "\n\n";

        flush();
        exit;
    }

    /**
     * Aggregate snapshot of every active/live event for a municipality.
     * Per event: status, location, coverage stats, open shortages, latest team pings.
     * Coverage is computed via teamStatusList() so figures match the single-event
     * command centre exactly. Active events are few, so per-event queries are cheap.
     */
    private function buildWarRoomSnapshot(int $mid): array
    {
        $events = dbq(
            "SELECT e.*, c.name AS category_name
             FROM events e LEFT JOIN event_categories c ON c.id = e.category_id
             WHERE e.municipality_id = :mid
               AND (
                 e.status = 'active'
                 OR (e.status IN ('open','confirmed','review')
                     AND NOW() >= e.start_datetime
                     AND NOW() <= e.end_datetime)
               )
             ORDER BY e.start_datetime ASC",
            ['mid' => $mid]
        )->fetchAll();

        $out          = [];
        $totShortages = 0;
        $totPresent   = 0;
        $totApproved  = 0;

        foreach ($events as $event) {
            $eid   = (int) $event['id'];
            $teams = $this->teamStatusList($eid);

            $approved = $present = $checkedIn = 0;
            foreach ($teams as $t) {
                $approved += (int) $t['approved_people'];
                if ($t['present_people'] !== null) {
                    $present += (int) $t['present_people'];
                }
                if ($t['checkin_status'] && $t['checkin_status'] !== 'pending') {
                    $checkedIn++;
                }
            }
            $coverage = $approved > 0 ? (int) round($present / $approved * 100) : 0;

            $openShort = (int) dbq(
                "SELECT COUNT(*) FROM shortage_reports
                 WHERE event_id = :eid AND status = 'open'",
                ['eid' => $eid]
            )->fetchColumn();

            $pingRows = dbq(
                "SELECT lp.team_id, t.name AS team_name, lp.latitude, lp.longitude,
                        TIMESTAMPDIFF(MINUTE, lp.created_at, NOW()) AS age_min
                 FROM location_pings lp
                 JOIN volunteer_teams t ON t.id = lp.team_id
                 WHERE lp.event_id = :eid
                   AND lp.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                 ORDER BY lp.created_at DESC",
                ['eid' => $eid]
            )->fetchAll();
            $latest = [];
            foreach ($pingRows as $p) {
                if (!isset($latest[$p['team_id']])) {
                    $latest[$p['team_id']] = [
                        'team_id'   => (int) $p['team_id'],
                        'team_name' => $p['team_name'],
                        'latitude'  => (float) $p['latitude'],
                        'longitude' => (float) $p['longitude'],
                        'age_min'   => (int) $p['age_min'],
                    ];
                }
            }

            $totShortages += $openShort;
            $totPresent   += $present;
            $totApproved  += $approved;

            $out[] = [
                'id'            => $eid,
                'title'         => $event['title'],
                'status'        => $event['status'],
                'category'      => $event['category_name'],
                'location_name' => $event['location_name'],
                'lat'           => $event['latitude']  ? (float) $event['latitude']  : null,
                'lng'           => $event['longitude'] ? (float) $event['longitude'] : null,
                'start_ts'      => strtotime($event['start_datetime']) * 1000,
                'end_ts'        => strtotime($event['end_datetime']) * 1000,
                'stats'         => [
                    'teams_total'     => count($teams),
                    'teams_present'   => $checkedIn,
                    'people_approved' => $approved,
                    'people_present'  => $present,
                    'coverage'        => $coverage,
                    'open_shortages'  => $openShort,
                ],
                'pings'         => array_values($latest),
            ];
        }

        $globalCoverage = $totApproved > 0 ? (int) round($totPresent / $totApproved * 100) : 0;

        return [
            'ok'     => true,
            'ts'     => date('H:i:s'),
            'totals' => [
                'events'         => count($out),
                'people_present' => $totPresent,
                'people_approved'=> $totApproved,
                'coverage'       => $globalCoverage,
                'open_shortages' => $totShortages,
            ],
            'events' => $out,
        ];
    }

    // ----------------------------------------------------------- Private helpers

    private function teamStatusList(int $eid): array
    {
        return dbq(
            "SELECT ea.team_id, t.name AS team_name, t.phone AS team_phone,
                    ea.approved_people,
                    oc.status AS checkin_status,
                    oc.present_people, oc.message AS checkin_msg,
                    lp.latitude  AS last_lat,
                    lp.longitude AS last_lng,
                    lp.created_at AS last_ping_at
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             LEFT JOIN operational_checkins oc
               ON oc.event_id = ea.event_id AND oc.team_id = ea.team_id
             LEFT JOIN (
                 SELECT lp1.team_id, lp1.latitude, lp1.longitude, lp1.created_at
                 FROM location_pings lp1
                 JOIN (
                     SELECT team_id, MAX(id) AS max_id
                     FROM location_pings
                     WHERE event_id = :eid2
                     GROUP BY team_id
                 ) latest ON latest.team_id = lp1.team_id AND latest.max_id = lp1.id
             ) lp ON lp.team_id = ea.team_id
             WHERE ea.event_id = :eid AND ea.status = 'approved'
             ORDER BY t.name ASC",
            ['eid' => $eid, 'eid2' => $eid]
        )->fetchAll();
    }

    private function buildActivityFeed(int $eid): array
    {
        return dbq(
            "SELECT type, ts, actor, title, severity FROM (
                SELECT 'checkin' AS type, oc.checked_in_at AS ts,
                       t.name AS actor, oc.status AS title, '' AS severity
                FROM operational_checkins oc
                JOIN volunteer_teams t ON t.id = oc.team_id
                WHERE oc.event_id = :eid
                UNION ALL
                SELECT 'shortage', sr.created_at, t.name, sr.title, sr.severity
                FROM shortage_reports sr
                JOIN volunteer_teams t ON t.id = sr.team_id
                WHERE sr.event_id = :eid2
                UNION ALL
                SELECT 'note', n.created_at, u.name, n.note, ''
                FROM operational_notes n
                JOIN users u ON u.id = n.user_id
                WHERE n.event_id = :eid3
             ) feed
             ORDER BY ts DESC
             LIMIT 25",
            ['eid' => $eid, 'eid2' => $eid, 'eid3' => $eid]
        )->fetchAll();
    }
}
