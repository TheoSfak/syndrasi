<?php
/**
 * SynDrasi - Mission Commander field access (NO LOGIN, token link /f/{token}).
 *
 * The team leader assigns a Mission Commander (a team_member without an app
 * account). That commander operates in the field through a personal token link
 * and can: send GPS location, status pings, raise SOS, upload photos, and
 * see / acknowledge orders from the municipality.
 *
 * All actions are attributed to the team (team_id) so they appear under the team
 * name on the command map. DB columns that require a users.id use the team's
 * admin (or a municipality admin) as a stand-in owner.
 */
class FieldController
{
    /** Resolve a token → context, or 404. */
    private function resolve($token): array
    {
        $app = EventApplication::findByFieldToken((string) $token);
        if (!$app || $app['status'] !== 'approved') {
            abort(404, 'Άκυρος ή ληγμένος σύνδεσμος πεδίου.');
        }
        $commander = !empty($app['mission_commander_id']) ? TeamMember::find((int) $app['mission_commander_id']) : null;

        $owner = dbq(
            "SELECT id FROM users WHERE team_id = :tid AND role = 'team_admin' AND status = 'active' ORDER BY id LIMIT 1",
            ['tid' => $app['team_id']]
        )->fetchColumn();
        if (!$owner) {
            $owner = dbq(
                "SELECT id FROM users WHERE municipality_id = :mid AND role = 'municipality_admin' AND status = 'active' ORDER BY id LIMIT 1",
                ['mid' => $app['municipality_id']]
            )->fetchColumn();
        }

        return ['app' => $app, 'commander' => $commander, 'owner' => (int) ($owner ?: 0)];
    }

    /** Minimal $event array (for NotificationService calls). */
    private function eventArr(array $app): array
    {
        return [
            'id'              => (int) $app['event_id'],
            'title'           => $app['event_title'],
            'municipality_id' => (int) $app['municipality_id'],
            'location_name'   => $app['location_name'] ?? null,
        ];
    }

    private function requireActive(array $app): void
    {
        $status  = $app['event_status'] ?? '';
        $started = !empty($app['start_datetime']) && strtotime($app['start_datetime']) <= time();
        if ($status !== 'active' && !($started && in_array($status, ['open', 'confirmed', 'review'], true))) {
            json_out(['success' => false, 'message' => 'Η δράση δεν είναι ενεργή αυτή τη στιγμή.'], 422);
        }
    }

    /** GET /f/{token} — the field hub (no login). */
    public function hub($token)
    {
        $ctx = $this->resolve($token);
        $app = $ctx['app'];
        $lastPing = dbq(
            'SELECT latitude, longitude, created_at FROM location_pings
             WHERE event_id = :eid AND team_id = :tid ORDER BY id DESC LIMIT 1',
            ['eid' => $app['event_id'], 'tid' => $app['team_id']]
        )->fetch() ?: null;
        // Pending photo / GPS requests for this team (so the field device shows the prompt)
        $photoRequest = PhotoRequest::pendingForEventTeam((int) $app['event_id'], (int) $app['team_id']);
        $gpsRequest   = GpsRequest::pendingForEventTeam((int) $app['event_id'], (int) $app['team_id']);
        $munSettings  = MunicipalitySetting::all((int) $app['municipality_id']);
        render('field/hub', [
            'pageTitle'    => 'Πεδίο — ' . $app['event_title'],
            'app'          => $app,
            'commander'    => $ctx['commander'],
            'token'        => $app['field_token'],
            'lastPing'     => $lastPing,
            'photoRequest' => $photoRequest,
            'gpsRequest'   => $gpsRequest,
            'orgLabel'     => MunicipalitySetting::orgLabelShort($munSettings),
            'orgIcon'      => MunicipalitySetting::orgIcon($munSettings),
        ], false); // standalone, no app layout / no login chrome
    }

    /** POST /f/{token}/location (JSON) — send GPS location. */
    public function location($token)
    {
        $ctx = $this->resolve($token); $app = $ctx['app'];
        $this->requireActive($app);
        $in  = json_input();
        $lat = isset($in['latitude'])  ? (float) $in['latitude']  : null;
        $lng = isset($in['longitude']) ? (float) $in['longitude'] : null;
        $acc = isset($in['accuracy'])  ? (float) $in['accuracy']  : null;
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            json_out(['success' => false, 'message' => 'Μη έγκυρες συντεταγμένες.'], 422);
        }
        dbq(
            'INSERT INTO location_pings (municipality_id, event_id, team_id, user_id, latitude, longitude, accuracy, message)
             VALUES (:mid, :eid, :tid, :uid, :lat, :lng, :acc, :msg)',
            [
                'mid' => $app['municipality_id'], 'eid' => $app['event_id'], 'tid' => $app['team_id'],
                'uid' => $ctx['owner'], 'lat' => $lat, 'lng' => $lng, 'acc' => $acc,
                'msg' => $ctx['commander'] ? ('Υπεύθυνος: ' . $ctx['commander']['full_name']) : null,
            ]
        );
        // A live location closes any pending GPS request from the commander.
        GpsRequest::fulfillForEventTeam((int) $app['event_id'], (int) $app['team_id']);
        json_out(['success' => true, 'message' => 'Το στίγμα στάλθηκε.']);
    }

    /** POST /f/{token}/status (JSON) — one-tap status ping. */
    public function status($token)
    {
        $ctx = $this->resolve($token); $app = $ctx['app'];
        $this->requireActive($app);
        $code = (string) (json_input()['code'] ?? '');
        if (!isset(EventMessage::STATUS_LABELS[$code])) {
            json_out(['success' => false, 'message' => 'Άγνωστο status.'], 422);
        }
        $label = EventMessage::statusLabel($code);
        EventMessage::create([
            'mid' => $app['municipality_id'], 'eid' => $app['event_id'], 'tid' => $app['team_id'],
            'role' => 'team', 'uid' => $ctx['owner'], 'kind' => 'status', 'code' => $code, 'body' => $label,
        ]);
        try { NotificationService::teamMessage($this->eventArr($app), ['name' => $app['team_name']], 'Ενημέρωση κατάστασης', $label); } catch (Throwable $e) { error_log('[Field::status] ' . $e->getMessage()); }
        json_out(['success' => true, 'message' => 'Στάλθηκε: ' . $label]);
    }

    /** POST /f/{token}/sos (JSON) — raise SOS / man-down. */
    public function sos($token)
    {
        $ctx = $this->resolve($token); $app = $ctx['app'];
        $in  = json_input();
        $lat = isset($in['latitude'])  ? (float) $in['latitude']  : null;
        $lng = isset($in['longitude']) ? (float) $in['longitude'] : null;
        $acc = isset($in['accuracy'])  ? (float) $in['accuracy']  : null;
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $lat = null; $lng = null; $acc = null;
        }
        $note = $ctx['commander'] ? ('Υπεύθυνος: ' . $ctx['commander']['full_name']) : null;
        $alertId = SosAlert::create([
            'mid' => $app['municipality_id'], 'eid' => $app['event_id'], 'tid' => $app['team_id'],
            'uid' => $ctx['owner'], 'lat' => $lat, 'lng' => $lng, 'acc' => $acc, 'note' => $note,
        ]);
        try {
            NotificationService::sosRaised(SosAlert::find($alertId), $this->eventArr($app), ['name' => $app['team_name']]);
        } catch (Throwable $e) { error_log('[Field SOS] ' . $e->getMessage()); }
        json_out(['success' => true, 'id' => $alertId, 'message' => 'SOS στάλθηκε — ο δήμος ειδοποιήθηκε.']);
    }

    /** POST /f/{token}/ack-order (JSON) — acknowledge a command order. */
    public function ackOrder($token)
    {
        $ctx = $this->resolve($token); $app = $ctx['app'];
        $msgId = (int) (json_input()['message_id'] ?? 0);
        $m = EventMessage::find($msgId);
        if (!$m || (int) $m['event_id'] !== (int) $app['event_id'] || $m['kind'] !== 'order'
            || !($m['team_id'] === null || (int) $m['team_id'] === (int) $app['team_id'])) {
            json_out(['success' => false, 'message' => 'Η εντολή δεν βρέθηκε.'], 404);
        }
        EventMessage::acknowledge($msgId, $ctx['owner']);
        json_out(['success' => true]);
    }

    /** GET /f/{token}/comms (JSON) — polling feed: messages + own SOS state. */
    public function comms($token)
    {
        $ctx = $this->resolve($token); $app = $ctx['app'];
        json_out([
            'success'       => true,
            'messages'      => EventMessage::forTeamEvent((int) $app['event_id'], (int) $app['team_id'], 0),
            'sos'           => SosAlert::latestForTeamEvent((int) $app['event_id'], (int) $app['team_id']),
            'photo_request' => (bool) PhotoRequest::pendingForEventTeam((int) $app['event_id'], (int) $app['team_id']),
            'gps_request'   => (bool) GpsRequest::pendingForEventTeam((int) $app['event_id'], (int) $app['team_id']),
            'room'          => EventRoomMessage::forEvent((int) $app['event_id']),
            'now'           => date('H:i:s'),
        ]);
    }

    /** POST /f/{token}/message (JSON) — send a private message to the command center. */
    public function message($token)
    {
        $ctx  = $this->resolve($token); $app = $ctx['app'];
        $body = trim((string) (json_input()['body'] ?? ''));
        if ($body === '') { json_out(['success' => false, 'message' => 'Κενό μήνυμα.'], 422); }
        EventMessage::create([
            'mid'  => $app['municipality_id'], 'eid' => $app['event_id'], 'tid' => $app['team_id'],
            'role' => 'team', 'uid' => $ctx['owner'], 'kind' => 'message', 'body' => $body,
        ]);
        try { NotificationService::teamMessage($this->eventArr($app), ['name' => $app['team_name']], 'Μήνυμα πεδίου', $body); } catch (Throwable $e) { error_log('[Field::message] ' . $e->getMessage()); }
        json_out(['success' => true, 'message' => 'Το μήνυμα στάλθηκε.']);
    }

    /** POST /f/{token}/shortage (form) — report a resource shortage. */
    public function shortage($token)
    {
        $ctx  = $this->resolve($token); $app = $ctx['app'];
        $back = '/f/' . $app['field_token'];

        $type = post_str('shortage_type');
        if (!in_array($type, ['people', 'equipment', 'medical_supplies', 'vehicle', 'other'], true)) {
            flash_set('danger', 'Μη έγκυρος τύπος έλλειψης.');
            redirect($back);
        }
        $severity = post_str('severity');
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) { $severity = 'medium'; }
        $title = trim(post_str('title'));
        if ($title === '') {
            flash_set('danger', 'Συμπληρώστε τίτλο έλλειψης.');
            redirect($back);
        }
        dbq(
            "INSERT INTO shortage_reports
             (municipality_id, event_id, team_id, reported_by, shortage_type, severity, title, description, status)
             VALUES (:mid, :eid, :tid, :uid, :type, :sev, :title, :descr, 'open')",
            [
                'mid' => $app['municipality_id'], 'eid' => $app['event_id'], 'tid' => $app['team_id'],
                'uid' => $ctx['owner'], 'type' => $type, 'sev' => $severity,
                'title' => $title, 'descr' => post_str('description') ?: null,
            ]
        );
        try {
            NotificationService::notifyMunicipality(
                (int) $app['municipality_id'], (int) $app['event_id'],
                'Νέα έλλειψη: ' . $title,
                ($app['team_name']) . ' ανέφερε έλλειψη (' . $severity . ') — ' . $app['event_title'],
                url('/operations/events/' . $app['event_id'])
            );
        } catch (Throwable $e) { error_log('[Field::shortage] ' . $e->getMessage()); }
        flash_set('success', 'Η αναφορά έλλειψης στάλθηκε στον δήμο.');
        redirect($back);
    }

    /** POST /f/{token}/room — post to the shared operations room (no login). */
    public function room($token)
    {
        $ctx = $this->resolve($token); $app = $ctx['app'];
        $body = trim((string) (json_input()['body'] ?? ''));
        if ($body === '') { json_out(['success' => false, 'message' => 'Κενό μήνυμα.'], 422); }
        EventRoomMessage::create([
            'mid'  => $app['municipality_id'], 'eid' => $app['event_id'],
            'role' => 'team', 'uid' => $ctx['owner'], 'tid' => $app['team_id'],
            'label' => $ctx['commander']['full_name'] ?? null, 'body' => $body,
        ]);
        json_out(['success' => true]);
    }

    /** POST /f/{token}/photo (multipart) — upload a geotagged photo. */
    public function photo($token)
    {
        $ctx = $this->resolve($token); $app = $ctx['app'];
        $back = '/f/' . $app['field_token'];

        if (empty($_FILES['photo']) || (int) ($_FILES['photo']['error'] ?? 1) !== UPLOAD_ERR_OK) {
            flash_set('danger', 'Δεν επιλέχθηκε έγκυρη φωτογραφία.');
            redirect($back);
        }
        $f = $_FILES['photo'];
        if ((int) $f['size'] > 12 * 1024 * 1024) {
            flash_set('danger', 'Η φωτογραφία είναι πολύ μεγάλη (μέγιστο 12MB).');
            redirect($back);
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $info = @getimagesize($f['tmp_name']);
        $mime = $info['mime'] ?? '';
        if (!isset($allowed[$mime])) {
            flash_set('danger', 'Επιτρέπονται μόνο εικόνες JPG / PNG / WebP.');
            redirect($back);
        }
        $dir = BASE_PATH . EventPhoto::DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            flash_set('danger', 'Αδυναμία αποθήκευσης (φάκελος).');
            redirect($back);
        }
        $name = 'ev' . (int) $app['event_id'] . '_t' . (int) $app['team_id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
            flash_set('danger', 'Αποτυχία αποθήκευσης φωτογραφίας.');
            redirect($back);
        }
        $lat = post_float_or_null('latitude');
        $lng = post_float_or_null('longitude');
        if ($lat !== null && ($lat < -90 || $lat > 90))   { $lat = null; }
        if ($lng !== null && ($lng < -180 || $lng > 180)) { $lng = null; }
        if ($lat === null || $lng === null) { $lat = null; $lng = null; }

        EventPhoto::create([
            'mid' => $app['municipality_id'], 'eid' => $app['event_id'], 'tid' => $app['team_id'],
            'uid' => $ctx['owner'] ?: null, 'rid' => null, 'file' => $name,
            'lat' => $lat, 'lng' => $lng, 'caption' => post_str('caption') ?: null,
        ]);
        // Close any pending photo request for this team (mirrors TeamPortalController::uploadPhoto).
        PhotoRequest::fulfillForEventTeam((int) $app['event_id'], (int) $app['team_id']);
        try { NotificationService::photoUploaded($this->eventArr($app), (int) $app['team_id']); } catch (Throwable $e) {}

        flash_set('success', 'Η φωτογραφία στάλθηκε στον δήμο.' . ($lat === null ? ' (χωρίς τοποθεσία)' : ''));
        redirect($back);
    }
}
