<?php
/**
 * SynDrasi - Team admin portal: dashboard, events, applications,
 * operational actions (check-in, location, shortages), reports, statistics.
 */
class TeamPortalController
{
    /* ------------------------------------------------------------ Dashboard */

    public function dashboard()
    {
        requireRole(['team_admin']);
        $tid = current_team_id();
        $mid = current_municipality_id();
        $team = VolunteerTeam::find($tid);

        $availableEvents = dbq(
            "SELECT COUNT(*) FROM events e
             WHERE e.municipality_id = :mid AND e.status = 'open'
               AND NOT EXISTS (SELECT 1 FROM event_applications ea WHERE ea.event_id = e.id AND ea.team_id = :tid)",
            ['mid' => $mid, 'tid' => $tid]
        )->fetchColumn();

        $pendingApplications = (int) dbq(
            "SELECT COUNT(*) FROM event_applications WHERE team_id = :tid AND status = 'pending'",
            ['tid' => $tid]
        )->fetchColumn();

        $upcomingApproved = dbq(
            "SELECT e.*, ea.approved_people
             FROM event_applications ea JOIN events e ON e.id = ea.event_id
             WHERE ea.team_id = :tid AND ea.status = 'approved'
               AND e.status IN ('open','review','confirmed','active') AND e.end_datetime >= NOW()
             ORDER BY e.start_datetime ASC",
            ['tid' => $tid]
        )->fetchAll();

        $todayOperational = dbq(
            "SELECT e.*, ea.approved_people
             FROM event_applications ea JOIN events e ON e.id = ea.event_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'active'
             ORDER BY e.start_datetime ASC",
            ['tid' => $tid]
        )->fetchAll();

        $stats = StatsService::teamStats($tid);

        render('dashboard/team', [
            'pageTitle'           => 'Πίνακας Ελέγχου Ομάδας',
            'team'                => $team,
            'availableEvents'     => (int) $availableEvents,
            'pendingApplications' => $pendingApplications,
            'upcomingApproved'    => $upcomingApproved,
            'todayOperational'    => $todayOperational,
            'stats'               => $stats,
        ]);
    }

    /* --------------------------------------------------------------- Events */

    public function events()
    {
        requireRole(['team_admin']);
        $mid = current_municipality_id();
        $tid = current_team_id();
        $events       = Event::availableForTeam($mid, $tid);
        $closedEvents = Event::closedForTeam($mid, $tid);
        render('team/events', [
            'pageTitle'    => 'Δράσεις',
            'events'       => $events,
            'closedEvents' => $closedEvents,
        ]);
    }

    public function showEvent($id)
    {
        requireRole(['team_admin']);
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        if ($event['status'] === 'draft') {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        // closed/completed: only visible if team had an approved application
        if (in_array($event['status'], ['closed', 'completed'], true)) {
            $app = EventApplication::findByEventTeam($event['id'], current_team_id());
            if (!$app || $app['status'] !== 'approved') {
                abort(404, 'Η δράση δεν βρέθηκε.');
            }
        }
        $tid = current_team_id();
        $mid = current_municipality_id();
        $team        = VolunteerTeam::find($tid);
        $application = EventApplication::findByEventTeam($event['id'], $tid);
        $myReport    = dbq(
            "SELECT * FROM event_reports WHERE event_id = :eid AND team_id = :tid AND report_type = 'team_report' LIMIT 1",
            ['eid' => $event['id'], 'tid' => $tid]
        )->fetch() ?: null;

        // Active members for selection
        $teamMembers = TeamMember::allByTeam($tid, true);

        // Conflict check: which members are already committed in another approved application
        // that overlaps with this event's time window
        $memberIds           = array_column($teamMembers, 'id');
        $conflictingMemberIds = $application
            ? TeamMember::conflictingMembers($memberIds, $event['start_datetime'], $event['end_datetime'], $application['id'])
            : TeamMember::conflictingMembers($memberIds, $event['start_datetime'], $event['end_datetime']);

        // Members already assigned to this application
        $applicationMembers = $application ? TeamMember::forApplication($application['id']) : [];

        // Can we still edit the member list? (no check-in yet)
        $hasCheckin = $application ? (bool) dbq(
            'SELECT COUNT(*) FROM operational_checkins WHERE application_id = :aid AND status != \'not_present\'',
            ['aid' => $application['id']]
        )->fetchColumn() : false;
        $canEditMembers = $application && !$hasCheckin && in_array($event['status'], ['open','review','confirmed','active'], true);

        // Shifts for this event (with team's application status per shift)
        $shifts = EventShift::forTeamOnEvent($event['id'], $tid);

        render('team/event_show', [
            'pageTitle'            => $event['title'],
            'event'                => $event,
            'team'                 => $team,
            'application'          => $application,
            'myReport'             => $myReport,
            'teamMembers'          => $teamMembers,
            'conflictingMemberIds' => $conflictingMemberIds,
            'applicationMembers'   => $applicationMembers,
            'canEditMembers'       => $canEditMembers,
            'shifts'               => $shifts,
        ]);
    }

    public function apply($id)
    {
        requireRole(['team_admin']);
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        if (!in_array($event['status'], ['open', 'review'], true)) {
            flash_set('warning', 'Η δράση δεν δέχεται πλέον δηλώσεις συμμετοχής.');
            redirect('/team/events/' . $event['id']);
        }
        if (EventApplication::findByEventTeam($event['id'], current_team_id())) {
            flash_set('warning', 'Έχετε ήδη υποβάλει δήλωση για αυτή τη δράση.');
            redirect('/team/events/' . $event['id']);
        }

        // Validate selected members
        $memberIds = isset($_POST['member_ids']) && is_array($_POST['member_ids'])
            ? array_map('intval', $_POST['member_ids']) : [];
        if (empty($memberIds)) {
            flash_set('danger', 'Επιλέξτε τουλάχιστον ένα μέλος για τη δήλωση.');
            redirect('/team/events/' . $event['id']);
        }

        $commanderId = post_int('mission_commander_id');
        if (!$commanderId || !in_array($commanderId, $memberIds, true)) {
            flash_set('danger', 'Ορίστε Mission Υπεύθυνο από τα επιλεγμένα μέλη.');
            redirect('/team/events/' . $event['id']);
        }

        // Verify all member_ids belong to this team
        $tid         = current_team_id();
        $validMembers = TeamMember::allByTeam($tid, true);
        $validIds    = array_column($validMembers, 'id');
        foreach ($memberIds as $mid) {
            if (!in_array($mid, $validIds, false)) {
                flash_set('danger', 'Μη έγκυρο μέλος επιλέχθηκε.');
                redirect('/team/events/' . $event['id']);
            }
        }

        $offeredPeople = count($memberIds);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $appId = EventApplication::create([
                'municipality_id'           => $event['municipality_id'],
                'event_id'                  => $event['id'],
                'team_id'                   => $tid,
                'offered_people'            => $offeredPeople,
                'offered_vehicle'           => post_bool('offered_vehicle'),
                'offered_medical_equipment' => post_bool('offered_medical_equipment'),
                'comment'                   => post_str('comment') ?: null,
            ]);

            // Save member assignments and commander
            TeamMember::setApplicationMembers($appId, $memberIds);
            dbq(
                'UPDATE event_applications SET mission_commander_id = :cid WHERE id = :id',
                ['cid' => $commanderId, 'id' => $appId]
            );
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('apply() transaction failed: ' . $e->getMessage());
            flash_set('danger', 'Σφάλμα κατά την υποβολή δήλωσης. Παρακαλώ δοκιμάστε ξανά.');
            redirect('/team/events/' . $event['id']);
        }

        audit('application_submitted', 'event_application', $appId,
              'event: ' . $event['title'] . ', members: ' . $offeredPeople . ', commander: ' . $commanderId);

        $team = VolunteerTeam::find($tid);
        NotificationService::applicationSubmitted($event, $team, $offeredPeople);

        // Notify selected members by email
        NotificationService::notifyMembersAssigned($event, $memberIds, $commanderId, $appId);
        dbq('UPDATE event_applications SET members_notified_at = NOW() WHERE id = :id', ['id' => $appId]);

        flash_set('success', 'Η δήλωση υποβλήθηκε με ' . $offeredPeople . ' μέλη.');
        redirect('/team/events/' . $event['id']);
    }

    /** POST /team/events/{id}/application/members — update member list before first check-in */
    public function updateApplicationMembers($id)
    {
        requireRole(['team_admin']);
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        $tid         = current_team_id();
        $application = EventApplication::findByEventTeam($event['id'], $tid);
        if (!$application) {
            flash_set('danger', 'Δεν βρέθηκε δήλωση συμμετοχής.');
            redirect('/team/events/' . $event['id']);
        }

        // Guard: no check-in yet
        $hasCheckin = (bool) dbq(
            "SELECT COUNT(*) FROM operational_checkins WHERE application_id = :aid AND status != 'not_present'",
            ['aid' => $application['id']]
        )->fetchColumn();
        if ($hasCheckin) {
            flash_set('danger', 'Δεν επιτρέπεται αλλαγή μελών μετά το πρώτο check-in.');
            redirect('/team/events/' . $event['id']);
        }

        $memberIds = isset($_POST['member_ids']) && is_array($_POST['member_ids'])
            ? array_map('intval', $_POST['member_ids']) : [];
        if (empty($memberIds)) {
            flash_set('danger', 'Επιλέξτε τουλάχιστον ένα μέλος.');
            redirect('/team/events/' . $event['id']);
        }

        $commanderId = post_int('mission_commander_id');
        if (!$commanderId || !in_array($commanderId, $memberIds, true)) {
            flash_set('danger', 'Ορίστε Mission Υπεύθυνο από τα επιλεγμένα μέλη.');
            redirect('/team/events/' . $event['id']);
        }

        // Validate ownership
        $validMembers = TeamMember::allByTeam($tid, true);
        $validIds     = array_column($validMembers, 'id');
        foreach ($memberIds as $mid) {
            if (!in_array($mid, $validIds, false)) {
                flash_set('danger', 'Μη έγκυρο μέλος.');
                redirect('/team/events/' . $event['id']);
            }
        }

        $pdo2 = db();
        $pdo2->beginTransaction();
        try {
            TeamMember::setApplicationMembers($application['id'], $memberIds);
            dbq(
                'UPDATE event_applications SET mission_commander_id = :cid, offered_people = :cnt WHERE id = :id',
                ['cid' => $commanderId, 'cnt' => count($memberIds), 'id' => $application['id']]
            );
            $pdo2->commit();
        } catch (Exception $e) {
            $pdo2->rollBack();
            error_log('updateApplicationMembers() transaction failed: ' . $e->getMessage());
            flash_set('danger', 'Σφάλμα κατά την ενημέρωση μελών. Παρακαλώ δοκιμάστε ξανά.');
            redirect('/team/events/' . $event['id']);
        }
        audit('application_members_updated', 'event_application', $application['id'],
              'members: ' . count($memberIds) . ', commander: ' . $commanderId);

        // Re-notify members who weren't previously notified or new additions
        NotificationService::notifyMembersAssigned($event, $memberIds, $commanderId, $application['id']);
        dbq('UPDATE event_applications SET members_notified_at = NOW() WHERE id = :id', ['id' => $application['id']]);

        flash_set('success', 'Ο κατάλογος μελών ενημερώθηκε.');
        redirect('/team/events/' . $event['id']);
    }

    public function cancelApplication($id)
    {
        requireRole(['team_admin']);
        $application = EventApplication::find($id);
        if (!$application) {
            abort(404, 'Η δήλωση δεν βρέθηκε.');
        }
        requireTeamAccess($application['team_id']);
        if ($application['status'] !== 'pending') {
            flash_set('warning', 'Μόνο εκκρεμείς δηλώσεις μπορούν να ακυρωθούν.');
            redirect('/team/applications');
        }
        EventApplication::cancel($application['id']);
        audit('application_cancelled', 'event_application', $application['id']);
        flash_set('success', 'Η δήλωση ακυρώθηκε.');
        redirect('/team/applications');
    }

    public function applications()
    {
        requireRole(['team_admin']);
        $applications = EventApplication::forTeam(current_team_id());
        render('team/applications', ['pageTitle' => 'Οι Δηλώσεις μας', 'applications' => $applications]);
    }

    /* ---------------------------------------------------------- Operational */

    /** Full-screen Mobile Action Hub — /team/live/{id} */
    public function live($id)
    {
        requireRole(['team_admin']);
        $context = $this->approvedContext($id);
        $event       = $context['event'];
        $application = $context['application'];
        $tid = current_team_id();

        $lastCheckin = dbq(
            'SELECT * FROM operational_checkins WHERE event_id = :eid AND team_id = :tid ORDER BY id DESC LIMIT 1',
            ['eid' => $event['id'], 'tid' => $tid]
        )->fetch() ?: null;

        $lastPing = dbq(
            'SELECT * FROM location_pings WHERE event_id = :eid AND team_id = :tid ORDER BY id DESC LIMIT 1',
            ['eid' => $event['id'], 'tid' => $tid]
        )->fetch() ?: null;

        $shortages = dbq(
            'SELECT * FROM shortage_reports WHERE event_id = :eid AND team_id = :tid ORDER BY created_at DESC',
            ['eid' => $event['id'], 'tid' => $tid]
        )->fetchAll();

        render('team/live', [
            'pageTitle'   => 'Live · ' . $event['title'],
            'event'       => $event,
            'application' => $application,
            'lastCheckin' => $lastCheckin,
            'lastPing'    => $lastPing,
            'shortages'   => $shortages,
        ], false);  // standalone — no navbar/sidebar layout
    }

    /**
     * GET /team/qr-checkin/{id} — streamlined one-tap check-in reached by
     * scanning the operator's Gate QR. Optional alternative to the normal flow;
     * reuses the same checkin() endpoint and operational_checkins table.
     */
    public function qrCheckin($id)
    {
        requireRole(['team_admin']);
        $context     = $this->approvedContext($id);
        $event       = $context['event'];
        $application = $context['application'];

        $lastCheckin = dbq(
            'SELECT * FROM operational_checkins WHERE event_id = :eid AND team_id = :tid ORDER BY id DESC LIMIT 1',
            ['eid' => $event['id'], 'tid' => current_team_id()]
        )->fetch() ?: null;

        render('team/qr-checkin', [
            'pageTitle'   => 'QR Παρουσία · ' . $event['title'],
            'event'       => $event,
            'application' => $application,
            'lastCheckin' => $lastCheckin,
        ], false);  // standalone — no navbar/sidebar layout
    }

    /** Mobile operational page for the team during an active event. */
    public function operations($id)
    {
        requireRole(['team_admin']);
        $context = $this->approvedContext($id);
        $event = $context['event'];
        $application = $context['application'];

        $lastCheckin = dbq(
            'SELECT * FROM operational_checkins WHERE event_id = :eid AND team_id = :tid ORDER BY id DESC LIMIT 1',
            ['eid' => $event['id'], 'tid' => current_team_id()]
        )->fetch() ?: null;

        $lastPing = dbq(
            'SELECT * FROM location_pings WHERE event_id = :eid AND team_id = :tid ORDER BY id DESC LIMIT 1',
            ['eid' => $event['id'], 'tid' => current_team_id()]
        )->fetch() ?: null;

        $shortages = dbq(
            'SELECT * FROM shortage_reports WHERE event_id = :eid AND team_id = :tid ORDER BY created_at DESC',
            ['eid' => $event['id'], 'tid' => current_team_id()]
        )->fetchAll();

        render('team/operations', [
            'pageTitle'   => 'Επιχειρησιακές Ενέργειες',
            'event'       => $event,
            'application' => $application,
            'lastCheckin' => $lastCheckin,
            'lastPing'    => $lastPing,
            'shortages'   => $shortages,
        ]);
    }

    /** POST /team/operations/events/{id}/checkin */
    public function checkin($id)
    {
        requireRole(['team_admin']);
        $context = $this->approvedContext($id, true);
        $event = $context['event'];
        $application = $context['application'];

        $status = post_str('status');
        $allowed = ['present_full', 'present_partial', 'not_present', 'departed'];
        if (!in_array($status, $allowed, true)) {
            flash_set('danger', 'Μη έγκυρη κατάσταση παρουσίας.');
            redirect('/team/operations/events/' . $event['id']);
        }

        $expected = (int) $application['approved_people'];
        if ($status === 'present_full') {
            $present = $expected;
        } elseif ($status === 'present_partial') {
            $present = post_int('present_people');
            if ($present < 1 || $present >= $expected) {
                flash_set('danger', 'Για μερική παρουσία δηλώστε αριθμό ατόμων μικρότερο από τα εγκεκριμένα (' . $expected . ').');
                redirect('/team/operations/events/' . $event['id']);
            }
        } elseif ($status === 'departed') {
            $present = 0;
        } else {
            $present = 0;
        }

        dbq(
            'INSERT INTO operational_checkins
             (municipality_id, event_id, team_id, application_id, status, present_people, expected_people, message, checked_in_by)
             VALUES (:mid, :eid, :tid, :aid, :status, :present, :expected, :message, :uid)',
            [
                'mid' => $event['municipality_id'], 'eid' => $event['id'], 'tid' => current_team_id(),
                'aid' => $application['id'], 'status' => $status, 'present' => $present,
                'expected' => $expected, 'message' => post_str('message') ?: null, 'uid' => $_SESSION['user_id'],
            ]
        );
        audit('team_checked_in', 'event', $event['id'], 'status: ' . $status . ', present: ' . $present);

        flash_set('success', 'Η δήλωση παρουσίας καταχωρήθηκε: ' . greek_status($status) . '.');
        $from = post_str('_from');
        if ($from === 'qr') {
            redirect('/team/qr-checkin/' . $event['id']);
        }
        redirect($from === 'live' ? '/team/live/' . $event['id'] : '/team/operations/events/' . $event['id']);
    }

    /** POST /team/operations/events/{id}/send-location  (JSON) */
    public function sendLocation($id)
    {
        requireRole(['team_admin']);
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            json_out(['success' => false, 'message' => 'Η δράση δεν βρέθηκε.'], 404);
        }
        $application = EventApplication::findByEventTeam($event['id'], current_team_id());
        if (!$application || $application['status'] !== 'approved') {
            json_out(['success' => false, 'message' => 'Η ομάδα σας δεν είναι εγκεκριμένη για αυτή τη δράση.'], 403);
        }
        if (!in_array($event['status'], ['active', 'confirmed'], true)) {
            json_out(['success' => false, 'message' => 'Η δράση δεν είναι ενεργή.'], 422);
        }

        $input = json_input();
        $lat = isset($input['latitude']) ? (float) $input['latitude'] : null;
        $lng = isset($input['longitude']) ? (float) $input['longitude'] : null;
        $accuracy = isset($input['accuracy']) ? (float) $input['accuracy'] : null;

        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            json_out(['success' => false, 'message' => 'Μη έγκυρες συντεταγμένες.'], 422);
        }

        dbq(
            'INSERT INTO location_pings (municipality_id, event_id, team_id, user_id, latitude, longitude, accuracy)
             VALUES (:mid, :eid, :tid, :uid, :lat, :lng, :acc)',
            [
                'mid' => $event['municipality_id'], 'eid' => $event['id'], 'tid' => current_team_id(),
                'uid' => $_SESSION['user_id'], 'lat' => $lat, 'lng' => $lng, 'acc' => $accuracy,
            ]
        );

        json_out(['success' => true, 'message' => 'Η τοποθεσία στάλθηκε.']);
    }

    /** POST /team/operations/events/{id}/shortage — report a resource shortage. */
    public function reportShortage($id)
    {
        requireRole(['team_admin']);
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        $tid         = current_team_id();
        $application = EventApplication::findByEventTeam($event['id'], $tid);
        if (!$application || $application['status'] !== 'approved') {
            flash_set('danger', 'Η ομάδα σας δεν είναι εγκεκριμένη για αυτή τη δράση.');
            redirect('/team/operations/events/' . $event['id']);
        }

        $type = post_str('shortage_type');
        if (!in_array($type, ['people', 'equipment', 'medical_supplies', 'vehicle', 'other'], true)) {
            flash_set('danger', 'Μη έγκυρος τύπος έλλειψης.');
            redirect('/team/operations/events/' . $event['id']);
        }
        $severity = post_str('severity');
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $severity = 'medium';
        }
        $title = trim(post_str('title'));
        if ($title === '') {
            flash_set('danger', 'Συμπληρώστε τίτλο έλλειψης.');
            redirect('/team/operations/events/' . $event['id']);
        }

        dbq(
            "INSERT INTO shortage_reports
             (municipality_id, event_id, team_id, reported_by, shortage_type, severity, title, description, status)
             VALUES (:mid, :eid, :tid, :uid, :type, :sev, :title, :descr, 'open')",
            [
                'mid' => $event['municipality_id'], 'eid' => $event['id'], 'tid' => $tid,
                'uid' => $_SESSION['user_id'], 'type' => $type, 'sev' => $severity,
                'title' => $title, 'descr' => post_str('description') ?: null,
            ]
        );
        audit('shortage_reported', 'event', $event['id'], 'type: ' . $type . ', severity: ' . $severity);

        $team = VolunteerTeam::find($tid);
        NotificationService::notifyMunicipality(
            $event['municipality_id'],
            $event['id'],
            'Νέα έλλειψη: ' . $title,
            ($team['name'] ?? 'Ομάδα') . ' ανέφερε έλλειψη (' . $severity . ') στη δράση ' . $event['title'] . '.',
            'shortage'
        );

        flash_set('success', 'Η έλλειψη αναφέρθηκε στο κέντρο επιχειρήσεων.');
        $from = post_str('_from');
        redirect($from === 'live' ? '/team/live/' . $event['id'] : '/team/operations/events/' . $event['id']);
    }

    /** POST /team/events/{id}/report — team post-event report (upsert). */
    public function submitReport($id)
    {
        requireRole(['team_admin']);
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        $tid         = current_team_id();
        $application = EventApplication::findByEventTeam($event['id'], $tid);
        if (!$application || $application['status'] !== 'approved') {
            flash_set('danger', 'Η ομάδα σας δεν συμμετείχε σε αυτή τη δράση.');
            redirect('/team/events/' . $event['id']);
        }

        $incidents = max(0, post_int('incidents_count'));
        $transfers = max(0, post_int('transfers_count'));
        $firstAid  = max(0, post_int('first_aid_count'));
        $summary   = post_str('summary') ?: null;
        $notes     = post_str('notes') ?: null;

        $existing = dbq(
            "SELECT id FROM event_reports WHERE event_id = :eid AND team_id = :tid AND report_type = 'team_report' LIMIT 1",
            ['eid' => $event['id'], 'tid' => $tid]
        )->fetch();

        if ($existing) {
            dbq(
                'UPDATE event_reports
                 SET incidents_count = :inc, transfers_count = :tr, first_aid_count = :fa,
                     summary = :sum, notes = :notes
                 WHERE id = :id',
                ['inc' => $incidents, 'tr' => $transfers, 'fa' => $firstAid,
                 'sum' => $summary, 'notes' => $notes, 'id' => $existing['id']]
            );
        } else {
            dbq(
                "INSERT INTO event_reports
                 (municipality_id, event_id, team_id, report_type, incidents_count, transfers_count, first_aid_count, summary, notes, created_by)
                 VALUES (:mid, :eid, :tid, 'team_report', :inc, :tr, :fa, :sum, :notes, :uid)",
                ['mid' => $event['municipality_id'], 'eid' => $event['id'], 'tid' => $tid,
                 'inc' => $incidents, 'tr' => $transfers, 'fa' => $firstAid,
                 'sum' => $summary, 'notes' => $notes, 'uid' => $_SESSION['user_id']]
            );
        }
        audit('team_report_submitted', 'event', $event['id']);
        flash_set('success', 'Η αναφορά καταχωρήθηκε.');
        redirect('/team/events/' . $event['id']);
    }

    /** GET /team/statistics */
    public function statistics()
    {
        requireRole(['team_admin']);
        $tid  = current_team_id();
        $team = VolunteerTeam::find($tid);
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

        $stats = StatsService::teamStats($tid, $year);

        $history = dbq(
            "SELECT e.title, e.start_datetime, e.end_datetime, c.name AS category_name,
                    ea.approved_people, ci.present_people, ci.status AS checkin_status
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN event_categories c ON c.id = e.category_id
             LEFT JOIN (
                SELECT oc1.* FROM operational_checkins oc1
                JOIN (SELECT event_id, team_id, MAX(id) AS last_id FROM operational_checkins GROUP BY event_id, team_id) x
                  ON x.last_id = oc1.id
             ) ci ON ci.event_id = ea.event_id AND ci.team_id = ea.team_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'
               AND YEAR(e.start_datetime) = :year
             ORDER BY e.start_datetime DESC",
            ['tid' => $tid, 'year' => $year]
        )->fetchAll();

        render('team/statistics', [
            'pageTitle' => 'Στατιστικά Ομάδας',
            'team'      => $team,
            'stats'     => $stats,
            'history'   => $history,
            'year'      => $year,
        ]);
    }

    /** GET /team/events/{id}/debrief — post-event debrief form. */
    public function debrief($id)
    {
        requireRole(['team_admin']);
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        $tid         = current_team_id();
        $application = EventApplication::findByEventTeam($event['id'], $tid);
        if (!$application || $application['status'] !== 'approved') {
            flash_set('danger', 'Η ομάδα σας δεν συμμετείχε σε αυτή τη δράση.');
            redirect('/team/events/' . $event['id']);
        }

        $debrief = TeamDebrief::findByEventTeam((int) $event['id'], (int) $tid);

        render('team/debrief', [
            'pageTitle'   => 'Απολογισμός · ' . $event['title'],
            'event'       => $event,
            'application' => $application,
            'debrief'     => $debrief,
        ]);
    }

    /** POST /team/events/{id}/debrief — save the post-event debrief. */
    public function saveDebrief($id)
    {
        requireRole(['team_admin']);
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        $tid         = current_team_id();
        $application = EventApplication::findByEventTeam($event['id'], $tid);
        if (!$application || $application['status'] !== 'approved') {
            flash_set('danger', 'Η ομάδα σας δεν συμμετείχε σε αυτή τη δράση.');
            redirect('/team/events/' . $event['id']);
        }

        $rating = post_int('organization_rating');
        if ($rating < 1 || $rating > 5) {
            $rating = 3;
        }

        TeamDebrief::upsert([
            'event_id'              => (int) $event['id'],
            'team_id'               => (int) $tid,
            'municipality_id'       => (int) $event['municipality_id'],
            'submitted_by'          => (int) $_SESSION['user_id'],
            'actual_volunteers'     => max(0, post_int('actual_volunteers')),
            'volunteer_hours'       => (float) str_replace(',', '.', (string) post_str('volunteer_hours')),
            'incidents_count'       => max(0, post_int('incidents_count')),
            'what_went_well'        => post_str('what_went_well') ?: null,
            'what_went_wrong'       => post_str('what_went_wrong') ?: null,
            'incidents_description' => post_str('incidents_description') ?: null,
            'organization_rating'   => $rating,
            'comments'              => post_str('comments') ?: null,
        ]);
        audit('team_debrief_submitted', 'event', $event['id']);

        flash_set('success', 'Ο απολογισμός υποβλήθηκε. Ευχαριστούμε!');
        redirect('/team/events/' . $event['id']);
    }

    // ---------------------------------------------------------- Private helpers

    /**
     * Resolve the event and the current team's approved application, or abort.
     * @param bool $requireActive when true, the event must be currently active.
     * @return array{event: array, application: array}
     */
    private function approvedContext($id, bool $requireActive = false): array
    {
        $event = Event::find($id);
        if (!$event || (int) $event['municipality_id'] !== (int) current_municipality_id()) {
            abort(404, 'Η δράση δεν βρέθηκε.');
        }
        $application = EventApplication::findByEventTeam($event['id'], current_team_id());
        if (!$application || $application['status'] !== 'approved') {
            abort(403, 'Η ομάδα σας δεν είναι εγκεκριμένη για αυτή τη δράση.');
        }
        if ($requireActive && $event['status'] !== 'active') {
            flash_set('warning', 'Η δράση δεν είναι ενεργή αυτή τη στιγμή.');
            redirect('/team/operations/events/' . $event['id']);
        }
        return ['event' => $event, 'application' => $application];
    }
}
