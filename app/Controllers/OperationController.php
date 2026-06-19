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
        $this->autoCloseExpired($mid);

        $live = dbq(
            "SELECT e.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM event_applications ea WHERE ea.event_id=e.id AND ea.status='approved') AS teams_approved,
                    (SELECT COUNT(*) FROM operational_checkins oc WHERE oc.event_id=e.id AND oc.status IN ('present_full','present_partial')) AS teams_present,
                    (SELECT COUNT(*) FROM shortage_reports sr WHERE sr.event_id=e.id AND sr.status='open') AS open_shortages
             FROM events e LEFT JOIN event_categories c ON c.id=e.category_id
             WHERE e.municipality_id = :mid
               AND (
                 e.status = 'active'
                 OR (e.status IN ('open','confirmed','review')
                     AND NOW() >= e.start_datetime
                     AND NOW() <= DATE_ADD(e.end_datetime, INTERVAL 4 HOUR))
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
            'orgLabel'   => MunicipalitySetting::orgLabelShort($munSettings),
            'orgIcon'    => MunicipalitySetting::orgIcon($munSettings),
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
        $stats = $this->calcTeamStats($teams);
        ['totalApproved' => $totalApproved, 'totalPresent' => $totalPresent,
         'checkedInCount' => $checkedInCount, 'coverage' => $coverage] = $stats;

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

        $pendingPhoto = array_flip(array_map('intval', PhotoRequest::pendingTeamIds($eid)));
        $pendingGps   = array_flip(array_map('intval', GpsRequest::pendingTeamIds($eid)));
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
                'photo_pending'  => isset($pendingPhoto[(int) $t['team_id']]),
                'gps_pending'    => isset($pendingGps[(int) $t['team_id']]),
            ];
        }

        $tids    = array_map(fn($t) => (int)$t['team_id'], $teams);
        $nameMap = [];
        foreach ($teams as $t) { $nameMap[(int)$t['team_id']] = $t['team_name']; }

        $pendingApps = dbq(
            "SELECT ea.id, ea.team_id, t.name AS team_name, ea.offered_people, ea.comment
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             WHERE ea.event_id = :eid AND ea.status = 'pending'
             ORDER BY ea.submitted_at ASC",
            ['eid' => $eid]
        )->fetchAll();

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
            'teams'        => $teamsPayload,
            'shortages'    => $shortages,
            'notes'        => $notes,
            'activity'     => $activity,
            'sos'          => SosAlert::activeForEvent((int) $eid),
            'messages'     => EventMessage::forEvent((int) $eid),
            'room'         => EventRoomMessage::forEvent((int) $eid),
            'geo_orders'   => $this->geoOrdersForEvent((int) $eid, $tids, $nameMap),
            'pending_apps' => $pendingApps,
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

        $approvedRows = dbq(
            "SELECT ea.team_id, t.name AS team_name FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             WHERE ea.event_id = :eid AND ea.status = 'approved'",
            ['eid' => $eid]
        )->fetchAll();
        $approvedIds = array_map(fn($r) => (int)$r['team_id'], $approvedRows);
        $nameMap     = array_column($approvedRows, 'team_name', 'team_id');

        json_out([
            'ok'         => true,
            'pings'      => array_values($latest),
            'photos'     => self::photoMarkers($eid),
            'geo_orders' => $this->geoOrdersForEvent($eid, $approvedIds, $nameMap),
        ]);
    }

    /**
     * Latest move/incident/poi order per approved team.
     * Returns one entry per team — the newest applicable order (targeted or broadcast).
     */
    private function geoOrdersForEvent(int $eid, array $approvedTeamIds, array $teamNameMap = []): array
    {
        if (empty($approvedTeamIds)) return [];
        $in = implode(',', array_map('intval', $approvedTeamIds));
        $orderRows = dbq(
            "SELECT em.team_id, em.point_kind AS pkind,
                    em.latitude AS lat, em.longitude AS lng,
                    em.body, em.created_at
             FROM event_messages em
             WHERE em.event_id = :eid
               AND em.point_kind IN ('move','incident','poi')
               AND em.latitude IS NOT NULL
               AND em.longitude IS NOT NULL
               AND (em.team_id IS NULL OR em.team_id IN ($in))
             ORDER BY em.created_at DESC",
            ['eid' => $eid]
        )->fetchAll();

        $result = [];
        foreach ($approvedTeamIds as $rawTid) {
            $tid = (int) $rawTid;
            foreach ($orderRows as $row) {
                if ($row['team_id'] === null || (int) $row['team_id'] === $tid) {
                    $result[] = [
                        'team_id'   => $tid,
                        'team_name' => $teamNameMap[$tid] ?? '',
                        'pkind'     => $row['pkind'],
                        'lat'       => (float) $row['lat'],
                        'lng'       => (float) $row['lng'],
                        'body'      => $row['body'],
                        'sent_at'   => substr($row['created_at'], 11, 5),
                    ];
                    break;
                }
            }
        }
        return $result;
    }

    /** Map-ready photo list for an event. */
    private static function photoMarkers(int $eid): array
    {
        $out = [];
        foreach (EventPhoto::forEvent($eid) as $p) {
            $out[] = [
                'id'        => (int) $p['id'],
                'team_id'   => (int) $p['team_id'],
                'team_name' => $p['team_name'],
                'lat'       => $p['latitude'] !== null ? (float) $p['latitude'] : null,
                'lng'       => $p['longitude'] !== null ? (float) $p['longitude'] : null,
                'url'       => url('/operations/photos/' . (int) $p['id']),
                'age_min'   => (int) $p['age_min'],
                'caption'   => $p['caption'],
                'at'        => gr_datetime($p['created_at']),
                'time'      => substr($p['created_at'], 11, 5),
            ];
        }
        return $out;
    }

    /** POST /operations/events/{id}/request-photo — ask a team for a photo. */
    public function requestPhoto($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);
        $tid   = post_int('team_id');
        $team  = VolunteerTeam::find($tid);
        if (!$team || (int) $team['municipality_id'] !== (int) $event['municipality_id']) {
            abort(404, 'Η ομάδα δεν βρέθηκε.');
        }
        PhotoRequest::create((int) $event['municipality_id'], (int) $event['id'], $tid, current_user_id());
        NotificationService::photoRequested($event, $team);
        audit('photo_requested', 'event', $event['id'], 'team ' . $tid);

        if (wants_json()) {
            json_out(['ok' => true]);
        }
        flash_set('success', 'Ζητήθηκε φωτογραφία από την ομάδα «' . $team['name'] . '».');
        redirect('/operations/events/' . $event['id']);
    }

    /** POST /operations/events/{id}/request-gps — ask a team for a live GPS fix. */
    public function requestGps($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);
        $tid   = post_int('team_id');
        $team  = VolunteerTeam::find($tid);
        if (!$team || (int) $team['municipality_id'] !== (int) $event['municipality_id']) {
            abort(404, 'Η ομάδα δεν βρέθηκε.');
        }
        GpsRequest::create((int) $event['municipality_id'], (int) $event['id'], $tid, current_user_id());
        try { NotificationService::gpsRequested($event, $team); } catch (Throwable $e) { error_log('[Ops::requestGps] ' . $e->getMessage()); }
        audit('gps_requested', 'event', $event['id'], 'team ' . $tid);

        if (wants_json()) {
            json_out(['ok' => true]);
        }
        flash_set('success', 'Ζητήθηκε στίγμα GPS από την ομάδα «' . $team['name'] . '».');
        redirect('/operations/events/' . $event['id']);
    }

    /** GET /operations/photos/{id} — stream a stored photo (protected). */
    public function servePhoto($id)
    {
        requireLogin();
        $photo = EventPhoto::find((int) $id);
        if (!$photo) {
            abort(404, 'Η φωτογραφία δεν βρέθηκε.');
        }
        $role = current_role();
        if (in_array($role, ['municipality_admin', 'event_operator'], true)) {
            requireMunicipalityAccess($photo['municipality_id']);
        } elseif ($role === 'team_admin') {
            if ((int) $photo['team_id'] !== (int) current_team_id()) {
                abort(403, 'Δεν έχετε πρόσβαση.');
            }
        } else {
            abort(403, 'Δεν έχετε πρόσβαση.');
        }
        $path = EventPhoto::path($photo);
        if ($path === null) {
            abort(404, 'Το αρχείο δεν βρέθηκε.');
        }
        $mime    = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
        if (!in_array($mime, $allowed, true)) {
            abort(415, 'Μη υποστηριζόμενος τύπος αρχείου.');
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
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
        $stats = $this->calcTeamStats($teams);
        ['totalApproved' => $totalApproved, 'totalPresent' => $totalPresent,
         'checkedInCount' => $checkedInCount, 'coverage' => $coverage] = $stats;

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

        $pendingPhoto = array_flip(array_map('intval', PhotoRequest::pendingTeamIds($eid)));
        $pendingGps   = array_flip(array_map('intval', GpsRequest::pendingTeamIds($eid)));
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
                'photo_pending'   => isset($pendingPhoto[(int) $t['team_id']]),
                'gps_pending'     => isset($pendingGps[(int) $t['team_id']]),
            ];
        }

        $openShortages = count(array_filter($shortages, fn($s) => $s['status'] === 'open'));

        // Check for teams that have gone silent beyond the configured threshold.
        $this->checkSilentTeams($eid, $event, $teamsPayload);

        $pendingApps = dbq(
            "SELECT ea.id, ea.team_id, t.name AS team_name, ea.offered_people, ea.comment
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             WHERE ea.event_id = :eid AND ea.status = 'pending'
             ORDER BY ea.submitted_at ASC",
            ['eid' => $eid]
        )->fetchAll();

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
            'teams'        => $teamsPayload,
            'shortages'    => $shortages,
            'notes'        => $notes,
            'activity'     => $activity,
            'pings'        => array_values($latestPings),
            'photos'       => self::photoMarkers($eid),
            'sos'          => SosAlert::activeForEvent($eid),
            'messages'     => EventMessage::forEvent($eid),
            'room'         => EventRoomMessage::forEvent($eid),
            'geo_orders'   => $this->geoOrdersForEvent($eid,
                                  array_map(fn($t) => (int)$t['team_id'], $teams),
                                  array_column($teams, 'team_name', 'team_id')),
            'pending_apps' => $pendingApps,
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

    /* ── Active-event comms (command side) ───────────────────────────────── */

    /** POST /sos/{id}/acknowledge — command acknowledges a team SOS; notifies team. */
    public function sosAck($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $alert = SosAlert::find((int) $id);
        if (!$alert || (int) $alert['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Το SOS δεν βρέθηκε.');
        }
        SosAlert::acknowledge((int) $id, current_user_id());
        $event = Event::find($alert['event_id']);
        if ($event) {
            try { NotificationService::sosAcknowledged($alert, $event); } catch (Throwable $e) { error_log('[Ops::sosAck] ' . $e->getMessage()); }
        }
        audit('sos_acknowledged', 'event', (int) $alert['event_id'], 'sos ' . $id);
        if (wants_json()) { json_out(['ok' => true]); }
        flash_set('success', 'Το SOS επιβεβαιώθηκε· η ομάδα ενημερώθηκε.');
        redirect('/operations/events/' . $alert['event_id']);
    }

    /** POST /sos/{id}/resolve — close an SOS. */
    public function sosResolve($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $alert = SosAlert::find((int) $id);
        if (!$alert || (int) $alert['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Το SOS δεν βρέθηκε.');
        }
        SosAlert::resolve((int) $id, current_user_id());
        audit('sos_resolved', 'event', (int) $alert['event_id'], 'sos ' . $id);
        if (wants_json()) { json_out(['ok' => true]); }
        flash_set('success', 'Το SOS έκλεισε.');
        redirect('/operations/events/' . $alert['event_id']);
    }

    /** POST /operations/events/{id}/message — command → team(s): message or order. */
    public function sendMessage($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event  = Event::findForCurrent($id);
        $body   = trim(post_str('body'));
        $kind   = post_str('kind') === 'order' ? 'order' : 'message';
        $teamId = post_int('team_id') ?: null;   // null = broadcast to all approved teams

        // Optional geo point (move / incident / poi)
        $pkind = post_str('point_kind');
        $lat   = post_float_or_null('latitude');
        $lng   = post_float_or_null('longitude');
        $hasPoint = in_array($pkind, ['move', 'incident', 'poi'], true)
                 && $lat !== null && $lng !== null
                 && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;

        if ($hasPoint) {
            if ($body === '') {
                $auto = ['move' => 'Μετάβαση στο σημείο', 'incident' => 'Περιστατικό στο σημείο', 'poi' => 'Σημείο ενδιαφέροντος'];
                $body = $auto[$pkind];
            }
            $kind = in_array($pkind, ['move', 'incident'], true) ? 'order' : 'message';
        } else {
            $pkind = null; $lat = null; $lng = null;
            if ($body === '') { json_out(['ok' => false, 'error' => 'Κενό μήνυμα.']); return; }
        }

        EventMessage::create([
            'mid' => $event['municipality_id'], 'eid' => $event['id'], 'tid' => $teamId,
            'role' => 'command', 'uid' => current_user_id(), 'kind' => $kind, 'body' => $body,
            'lat' => $lat, 'lng' => $lng, 'pkind' => $pkind,
        ]);

        if ($teamId) {
            $teamIds = [$teamId];
        } else {
            $teamIds = array_map('intval', dbq(
                "SELECT DISTINCT team_id FROM event_applications WHERE event_id = :eid AND status = 'approved'",
                ['eid' => $event['id']]
            )->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        try {
            if ($hasPoint) {
                NotificationService::commandGeoMessage($event, $teamIds, $pkind, $body, $lat, $lng);
            } else {
                NotificationService::commandMessage($event, $teamIds, $kind, $body);
            }
        } catch (Throwable $e) { error_log('[Ops::sendMessage] ' . $e->getMessage()); }
        audit('ops_message_sent', 'event', (int) $event['id'], ($pkind ?: $kind) . ($teamId ? ' team ' . $teamId : ' broadcast'));
        json_out(['ok' => true]);
    }

    /**
     * POST /operations/events/{id}/applications/{appId}/approve
     * Approve a pending team application directly from the operational view.
     * Municipality admin only — event_operator cannot approve/reject.
     */
    public function approveApplication($id, $appId)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($id);
        $app   = EventApplication::find($appId);
        if (!$app
            || (int) $app['event_id']        !== (int) $event['id']
            || (int) $app['municipality_id'] !== (int) $event['municipality_id']
            || $app['status'] !== 'pending') {
            json_out(['ok' => false, 'error' => 'Η δήλωση δεν βρέθηκε ή δεν είναι σε αναμονή.'], 422);
            return;
        }
        $people = post_int('approved_people');
        if ($people < 1) { $people = (int) $app['offered_people']; }
        $comment = post_str('admin_comment') ?: null;
        EventApplication::approve($appId, $people, $comment, current_user_id());
        audit('application_approved', 'event_application', $appId, 'via ops, people: ' . $people);
        $app['approved_people'] = $people;
        try { NotificationService::applicationApproved($event, $app); } catch (Throwable $e) { error_log('[Ops::approveApp] ' . $e->getMessage()); }
        json_out(['ok' => true]);
    }

    /**
     * POST /operations/events/{id}/applications/{appId}/reject
     * Reject a pending team application directly from the operational view.
     */
    public function rejectApplication($id, $appId)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($id);
        $app   = EventApplication::find($appId);
        if (!$app
            || (int) $app['event_id']        !== (int) $event['id']
            || (int) $app['municipality_id'] !== (int) $event['municipality_id']
            || $app['status'] !== 'pending') {
            json_out(['ok' => false, 'error' => 'Η δήλωση δεν βρέθηκε ή δεν είναι σε αναμονή.'], 422);
            return;
        }
        $comment = post_str('admin_comment') ?: null;
        EventApplication::reject($appId, $comment, current_user_id());
        audit('application_rejected', 'event_application', $appId, 'via ops');
        try { NotificationService::applicationRejected($event, $app, (string) $comment); } catch (Throwable $e) { error_log('[Ops::rejectApp] ' . $e->getMessage()); }
        json_out(['ok' => true]);
    }

    /** POST /operations/events/{id}/room — post to the shared operations room. */
    public function sendRoom($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent($id);
        $body  = trim(post_str('body'));
        if ($body === '') { json_out(['ok' => false, 'error' => 'Κενό μήνυμα.']); return; }
        EventRoomMessage::create([
            'mid' => $event['municipality_id'], 'eid' => $event['id'],
            'role' => 'command', 'uid' => current_user_id(), 'tid' => null, 'body' => $body,
        ]);
        json_out(['ok' => true]);
    }

    /** POST /shortages/{id}/acknowledge */
    public function acknowledgeShortage($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $this->updateShortageStatus((int) $id, 'acknowledged');
    }

    /** POST /shortages/{id}/resolve */
    public function resolveShortage($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $this->updateShortageStatus((int) $id, 'resolved');
    }

    /** Shared shortage state change + team notification (Feature 4: ack loop). */
    private function updateShortageStatus(int $id, string $action): void
    {
        $sh = dbq('SELECT * FROM shortage_reports WHERE id = :id LIMIT 1', ['id' => $id])->fetch();
        if (!$sh || (int) $sh['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η έλλειψη δεν βρέθηκε.');
        }
        $uid = current_user_id();
        if ($action === 'acknowledged') {
            dbq("UPDATE shortage_reports SET status='acknowledged', acknowledged_by=:uid, acknowledged_at=NOW()
                 WHERE id=:id AND status='open'", ['uid' => $uid, 'id' => $id]);
        } elseif ($action === 'resolved') {
            dbq("UPDATE shortage_reports SET status='resolved', resolved_by=:uid, resolved_at=NOW()
                 WHERE id=:id AND status IN ('open','acknowledged')", ['uid' => $uid, 'id' => $id]);
        } else {
            abort(422, 'Μη έγκυρη ενέργεια.');
        }
        $event = Event::find($sh['event_id']);
        if ($event) {
            try { NotificationService::shortageHandled($sh, $event, $action); } catch (Throwable $e) { error_log('[Ops::shortage] ' . $e->getMessage()); }
        }
        audit('shortage_' . $action, 'event', (int) $sh['event_id'], 'shortage ' . $id);
        if (wants_json()) { json_out(['ok' => true]); }
        flash_set('success', $action === 'resolved' ? 'Η έλλειψη επιλύθηκε.' : 'Η έλλειψη επιβεβαιώθηκε.');
        redirect('/operations/events/' . $sh['event_id']);
    }

    /* ═══════════════════ WAR ROOM (multi-event) ═══════════════════ */

    /** GET /operations/war-room — all active events on one map. */
    public function warRoom()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid         = current_municipality_id();
        $this->autoCloseExpired($mid);
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
        $this->autoCloseExpired($mid);

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
     * Check approved teams for GPS silence beyond the configured threshold.
     * Fires in-app + push to command staff once per silence window per team.
     * Dedup uses the notifications table: if a 'team_silent' notification was
     * already created for this team+event within the last $minutes minutes, skip.
     */
    private function checkSilentTeams(int $eid, array $event, array $teams): void
    {
        $mid     = (int) $event['municipality_id'];
        $minutes = (int) (MunicipalitySetting::get($mid, 'ops_silent_team_minutes', '') ?: 20);
        if ($minutes <= 0) { return; }

        $candidates = [];
        foreach ($teams as $t) {
            $age = $t['ping_age_min'];
            if ($age !== null && (int) $age >= $minutes) {
                $candidates[(int) $t['team_id']] = $t;
            }
        }
        if (!$candidates) { return; }

        $in      = implode(',', array_keys($candidates));
        $alerted = array_flip(array_map('intval', dbq(
            "SELECT DISTINCT team_id FROM notifications
             WHERE type = 'team_silent' AND event_id = :eid
               AND team_id IN ($in)
               AND created_at > NOW() - INTERVAL :mins MINUTE",
            ['eid' => $eid, 'mins' => $minutes]
        )->fetchAll(PDO::FETCH_COLUMN) ?: []));

        foreach ($candidates as $tid => $t) {
            if (isset($alerted[$tid])) { continue; }
            try {
                NotificationService::silentTeam(
                    ['id' => $eid, 'title' => $event['title'], 'municipality_id' => $mid],
                    ['id' => $tid, 'name'  => $t['team_name']],
                    (int) $t['ping_age_min']
                );
            } catch (Throwable $e) {
                error_log('[SilentTeam] ' . $e->getMessage());
            }
        }
    }

    /**
     * Lazy auto-close: events shown in operations stay open after their end time
     * and must be closed by the municipality admin. If still open 4 hours after
     * their end, they are closed automatically here (no cron needed — runs on
     * every operations / war-room load and stream poll).
     */
    private function autoCloseExpired(int $mid): void
    {
        $key = 'auto_close_checked_' . $mid;
        $now = time();
        if (isset($_SESSION[$key]) && $now - $_SESSION[$key] < 60) {
            return;
        }
        $_SESSION[$key] = $now;

        $n = dbq(
            "UPDATE events
             SET status = 'closed'
             WHERE municipality_id = :mid
               AND status IN ('active', 'open', 'confirmed', 'review')
               AND end_datetime < DATE_SUB(NOW(), INTERVAL 4 HOUR)",
            ['mid' => $mid]
        )->rowCount();
        if ($n > 0) {
            audit('events_auto_closed', 'event', null, $n . ' event(s) auto-closed (4h grace)');
        }
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
                     AND NOW() <= DATE_ADD(e.end_datetime, INTERVAL 4 HOUR))
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
                'photos'        => self::photoMarkers($eid),
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

    private function calcTeamStats(array $teams): array
    {
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
        return [
            'totalApproved'  => $totalApproved,
            'totalPresent'   => $totalPresent,
            'checkedInCount' => $checkedInCount,
            'coverage'       => $totalApproved > 0 ? round($totalPresent / $totalApproved * 100) : 0,
        ];
    }

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
                       t.name AS actor,
                       CASE oc.status
                           WHEN 'present_full'    THEN 'Παρόντες (πλήρης)'
                           WHEN 'present_partial' THEN 'Μερική παρουσία'
                           WHEN 'not_present'     THEN 'Απόντες'
                           WHEN 'departed'        THEN 'Αποχώρησαν'
                           ELSE oc.status
                       END AS title,
                       '' AS severity
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
