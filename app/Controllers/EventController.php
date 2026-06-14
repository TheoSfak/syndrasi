<?php
/**
 * SynDrasi - Events CRUD (municipality admin).
 *
 * Status pages:
 *   /events/drafts    → draft
 *   /events           → Ενεργές (open/review/confirmed/active)
 *   /events/closed    → Κλειστές (closed — reconciliation)
 *   /events/completed → Ολοκληρωμένες (completed)
 */
class EventController
{
    // ---------------------------------------------------------------- Listings

    /** Ενεργές δράσεις: open / review / confirmed / active */
    public function index()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid    = current_municipality_id();
        $events = Event::forMunicipalityByStatuses($mid, ['open', 'review', 'confirmed', 'active']);
        render('events/active', [
            'pageTitle' => 'Ενεργές Δράσεις',
            'events'    => $events,
            'templates' => EventTemplate::forMunicipality($mid),
        ]);
    }

    /** Calendar view — all events for a given month */
    public function calendar()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid = current_municipality_id();

        $yearMonth = isset($_GET['m']) ? preg_replace('/[^0-9\-]/', '', trim($_GET['m'])) : date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $yearMonth = date('Y-m');
        }
        [$year, $month] = array_map('intval', explode('-', $yearMonth));
        $year  = max(2020, min(2040, $year));
        $month = max(1,    min(12,   $month));

        $from = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $to   = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));

        $events = dbq(
            "SELECT e.id, e.title, e.status, e.start_datetime, e.end_datetime,
                    c.name AS category_name
             FROM events e
             LEFT JOIN event_categories c ON c.id = e.category_id
             WHERE e.municipality_id = :mid
               AND e.status != 'cancelled'
               AND e.start_datetime <= :to
               AND e.end_datetime   >= :from
             ORDER BY e.start_datetime ASC",
            ['mid' => $mid, 'from' => $from, 'to' => $to]
        )->fetchAll();

        $prevTs    = mktime(0, 0, 0, $month - 1, 1, $year);
        $nextTs    = mktime(0, 0, 0, $month + 1, 1, $year);
        $prevMonth = date('Y-m', $prevTs);
        $nextMonth = date('Y-m', $nextTs);

        render('events/calendar', [
            'pageTitle'  => 'Ημερολόγιο Δράσεων',
            'events'     => $events,
            'year'       => $year,
            'month'      => $month,
            'prevMonth'  => $prevMonth,
            'nextMonth'  => $nextMonth,
        ]);
    }

    /** Πρόχειρα */
    public function drafts()
    {
        requireRole(['municipality_admin']);
        $mid    = current_municipality_id();
        $events = Event::forMunicipalityByStatuses($mid, ['draft']);
        render('events/drafts', [
            'pageTitle' => 'Πρόχειρες Δράσεις',
            'events'    => $events,
        ]);
    }

    /** Κλειστές δράσεις — αναμονή αρχειοθέτησης */
    public function closed()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid    = current_municipality_id();
        $events = Event::forMunicipalityByStatuses($mid, ['closed']);
        render('events/closed', [
            'pageTitle' => 'Κλειστές Δράσεις',
            'events'    => $events,
        ]);
    }

    /** Ολοκληρωμένες δράσεις — αρχείο */
    public function completed()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid    = current_municipality_id();
        $q      = isset($_GET['q']) ? trim($_GET['q']) : '';
        $from   = isset($_GET['from']) ? trim($_GET['from']) : '';
        $to     = isset($_GET['to']) ? trim($_GET['to']) : '';
        $events = Event::forMunicipalityByStatuses($mid, ['completed'], ['q' => $q, 'from' => $from, 'to' => $to]);
        render('events/completed', [
            'pageTitle' => 'Ολοκληρωμένες Δράσεις',
            'events'    => $events,
            'q'         => $q,
            'from'      => $from,
            'to'        => $to,
        ]);
    }

    // --------------------------------------------------------------- CRUD

    public function create()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        // Optional prefill from a saved template (?template=ID)
        $prefill = null;
        $templateId = null;
        if (isset($_GET['template'])) {
            $tpl = EventTemplate::findForMunicipality((int) $_GET['template'], $mid);
            if ($tpl) {
                $templateId = (int) $tpl['id'];
                $prefill = [
                    'title'                       => $tpl['title'],
                    'category_id'                 => $tpl['category_id'],
                    'description'                 => $tpl['description'],
                    'location_name'               => $tpl['location_name'],
                    'address'                     => $tpl['address'],
                    'latitude'                    => $tpl['latitude'],
                    'longitude'                   => $tpl['longitude'],
                    'requested_people'            => $tpl['requested_people'],
                    'requested_vehicle'           => $tpl['requested_vehicle'],
                    'requested_medical_equipment' => $tpl['requested_medical_equipment'],
                    'instructions'                => $tpl['instructions'],
                ];
            }
        }

        render('events/form', [
            'pageTitle'           => 'Νέα Δράση',
            'event'               => $prefill,
            'templateId'          => $templateId,
            'categories'          => Event::categories(),
            'defaultInstructions' => MunicipalitySetting::get($mid, 'event_default_instructions', ''),
        ]);
    }

    public function store()
    {
        requireRole(['municipality_admin']);
        $data = $this->validated();
        if ($data === null) {
            redirect('/events/create');
        }
        $data['municipality_id'] = current_municipality_id();
        $data['created_by']      = $_SESSION['user_id'];
        $data['status']          = 'draft';

        $id = Event::create($data);
        audit('event_created', 'event', $id, $data['title']);

        // Re-create shifts from the source template, if started from one
        $templateId = post_int('template_id');
        if ($templateId) {
            $tpl = EventTemplate::findForMunicipality($templateId, $data['municipality_id']);
            if ($tpl && !empty($tpl['shifts_json'])) {
                $tplShifts = json_decode($tpl['shifts_json'], true) ?: [];
                $baseStart = strtotime($data['start_datetime']);
                foreach ($tplShifts as $sh) {
                    EventShift::create([
                        'event_id'        => $id,
                        'municipality_id' => $data['municipality_id'],
                        'name'            => $sh['name'] ?? 'Βάρδια',
                        'start_datetime'  => date('Y-m-d H:i:s', $baseStart + ((int) ($sh['start_offset_min'] ?? 0)) * 60),
                        'end_datetime'    => date('Y-m-d H:i:s', $baseStart + ((int) ($sh['end_offset_min'] ?? 0)) * 60),
                        'required_people' => (int) ($sh['required_people'] ?? 0),
                        'notes'           => $sh['notes'] ?? null,
                    ]);
                }
            }
        }

        if (post_str('action') === 'publish') {
            Event::markPublished($id);
            $event = Event::find($id);
            NotificationService::eventPublished($event);
            audit('event_published', 'event', $id);
            flash_set('success', 'Η δράση δημιουργήθηκε και δημοσιεύθηκε στις ομάδες.');
        } else {
            flash_set('success', 'Η δράση αποθηκεύτηκε ως πρόχειρη.');
        }
        redirect('/events/' . $id);
    }

    public function show($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event        = Event::findForCurrent($id);
        $applications = EventApplication::forEvent($event['id']);
        $reports      = dbq(
            'SELECT er.*, t.name AS team_name, u.name AS author_name
             FROM event_reports er
             LEFT JOIN volunteer_teams t ON t.id = er.team_id
             JOIN users u ON u.id = er.created_by
             WHERE er.event_id = :eid ORDER BY er.created_at',
            ['eid' => $event['id']]
        )->fetchAll();
        $shifts    = EventShift::forEvent($event['id']);
        $shiftApps = EventShift::applicationsForEvent($event['id']);
        render('events/show', [
            'pageTitle'    => $event['title'],
            'event'        => $event,
            'applications' => $applications,
            'reports'      => $reports,
            'shifts'       => $shifts,
            'shiftApps'    => $shiftApps,
        ]);
    }

    public function edit($id)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($id);
        render('events/form', [
            'pageTitle'  => 'Επεξεργασία Δράσης',
            'event'      => $event,
            'categories' => Event::categories(),
        ]);
    }

    public function update($id)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($id);
        $data  = $this->validated();
        if ($data === null) {
            redirect('/events/' . $event['id'] . '/edit');
        }
        Event::update($event['id'], $data);
        audit('event_updated', 'event', $event['id'], $data['title']);
        flash_set('success', 'Η δράση ενημερώθηκε.');
        redirect('/events/' . $event['id']);
    }

    // ------------------------------------------------------------- Transitions

    public function publish($id)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent((int)$id);
        if ($event['status'] !== 'draft') {
            flash_set('error', 'Μόνο πρόχειρες δράσεις μπορούν να δημοσιευθούν.');
            redirect('/events/' . $event['id']);
        }
        Event::markPublished($event['id']);
        $event = Event::find($event['id']);
        NotificationService::eventPublished($event);
        audit('event_published', 'event', $event['id'], $event['title']);
        flash_set('success', 'Η δράση δημοσιεύθηκε και οι ομάδες ειδοποιήθηκαν.');
        redirect('/events/' . $event['id']);
    }

    /** Manual early-start */
    public function activate($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent((int)$id);
        if (!Event::canTransition($event['status'], 'active')) {
            flash_set('error', 'Η δράση δεν μπορεί να ενεργοποιηθεί από αυτή την κατάσταση.');
            redirect('/events/' . $event['id']);
        }
        Event::setStatus($event['id'], 'active');
        audit('event_activated', 'event', $event['id'], $event['title']);
        flash_set('success', 'Η δράση ενεργοποιήθηκε.');
        redirect('/operations/events/' . $event['id']);
    }

    /** Manual early-end OR scheduled close */
    public function complete($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event = Event::findForCurrent((int)$id);
        if (!Event::canTransition($event['status'], 'completed')) {
            flash_set('error', 'Η δράση δεν μπορεί να ολοκληρωθεί από αυτή την κατάσταση.');
            redirect('/events/' . $event['id']);
        }
        Event::setStatus($event['id'], 'completed');
        audit('event_completed', 'event', $event['id'], $event['title']);
        flash_set('success', 'Η δράση ολοκληρώθηκε.');
        redirect('/operations');
    }

    /** Move completed → archived (cancelled = archived in this system) */
    public function archive($id)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent((int)$id);
        if (!Event::canTransition($event['status'], 'cancelled')) {
            flash_set('error', 'Η δράση δεν μπορεί να αρχειοθετηθεί από αυτή την κατάσταση.');
            redirect('/events/' . $event['id']);
        }
        Event::setStatus($event['id'], 'cancelled');
        audit('event_archived', 'event', $event['id'], $event['title']);
        flash_set('success', 'Η δράση αρχειοθετήθηκε.');
        redirect('/events/completed');
    }

    /** GET — reconciliation form for a closed event */
    public function reconcile($id)
    {
        requireRole(['municipality_admin']);
        $event        = Event::findForCurrent($id);
        $applications = EventApplication::forEvent($event['id']);
        render('events/reconcile', [
            'pageTitle'    => 'Αρχειοθέτηση: ' . $event['title'],
            'event'        => $event,
            'applications' => $applications,
        ]);
    }

    /** POST — save reconciliation data */
    public function saveReconciliation($id)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($id);
        if ($event['status'] !== 'closed') {
            flash_set('warning', 'Μόνο κλειστές δράσεις επιτρέπουν αρχειοθέτηση.');
            redirect('/events/' . $event['id']);
        }

        // Save per-application actuals + event notes atomically
        $appIds = isset($_POST['app_id']) && is_array($_POST['app_id']) ? $_POST['app_id'] : [];
        $notes  = post_str('reconciliation_notes');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($appIds as $appId) {
                $appId  = (int) $appId;
                $actual = isset($_POST['actual_people'][$appId]) ? (int) $_POST['actual_people'][$appId] : null;
                $arriv  = isset($_POST['actual_arrival_time'][$appId]) ? trim($_POST['actual_arrival_time'][$appId]) : null;
                $depart = isset($_POST['actual_departure_time'][$appId]) ? trim($_POST['actual_departure_time'][$appId]) : null;

                $arrivTs  = ($arriv  !== '' && $arriv)  ? date('Y-m-d H:i:s', strtotime($arriv))  : null;
                $departTs = ($depart !== '' && $depart) ? date('Y-m-d H:i:s', strtotime($depart)) : null;

                dbq(
                    'UPDATE event_applications SET
                       actual_people         = :actual,
                       actual_arrival_time   = :arriv,
                       actual_departure_time = :depart
                     WHERE id = :id AND event_id = :eid',
                    [
                        'actual' => ($actual !== null && $actual >= 0) ? $actual : null,
                        'arriv'  => $arrivTs,
                        'depart' => $departTs,
                        'id'     => $appId,
                        'eid'    => $event['id'],
                    ]
                );

                // --- Volunteer participation records ---
                $presencePost = isset($_POST['member_present'][$appId]) && is_array($_POST['member_present'][$appId])
                    ? $_POST['member_present'][$appId] : [];

                if (!empty($presencePost)) {
                    // Get all assigned members so we can record absence too
                    $assignedMembers = TeamMember::forApplication($appId);

                    // Calculate hours from actual arrival → departure (or event times)
                    $fromTs = $arrivTs  ? strtotime($arrivTs)  : strtotime($event['start_datetime']);
                    $toTs   = $departTs ? strtotime($departTs) : strtotime($event['end_datetime']);
                    $hours  = max(0, round(($toTs - $fromTs) / 3600, 2));

                    // Fetch application for team_id + mission_commander_id
                    $appRow = dbq(
                        'SELECT team_id, mission_commander_id FROM event_applications WHERE id = :id',
                        ['id' => $appId]
                    )->fetch();
                    $teamId     = $appRow ? (int) $appRow['team_id'] : 0;
                    $commanderId = $appRow && $appRow['mission_commander_id']
                        ? (int) $appRow['mission_commander_id'] : null;

                    // Build presences array: include all assigned members, marking absent those not checked
                    $presences = [];
                    foreach ($assignedMembers as $m) {
                        $mid = (int) $m['id'];
                        $presences[$mid] = [
                            'present' => isset($presencePost[$mid]) ? 1 : 0,
                            'notes'   => null,
                        ];
                    }

                    VolunteerParticipation::saveForApplication(
                        (int) $event['id'],
                        $teamId,
                        $appId,
                        (int) $event['municipality_id'],
                        $presences,
                        $hours,
                        $commanderId
                    );
                }
            }

            dbq(
                'UPDATE events SET reconciliation_notes = :notes WHERE id = :id',
                ['notes' => $notes ?: null, 'id' => $event['id']]
            );
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('saveReconciliation() transaction failed: ' . $e->getMessage());
            flash_set('danger', 'Σφάλμα κατά την αποθήκευση. Παρακαλώ δοκιμάστε ξανά.');
            redirect('/events/' . $event['id'] . '/reconcile');
        }

        audit('event_reconciled', 'event', $event['id']);
        flash_set('success', 'Τα δεδομένα αρχειοθέτησης αποθηκεύτηκαν.');
        redirect('/events/' . $event['id'] . '/reconcile');
    }

    // --------------------------------------------------------------- Clone

    /** POST /events/{id}/clone — copy to new draft, redirect to edit */
    public function clone($id)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent((int) $id);

        $data = [
            'municipality_id'             => $event['municipality_id'],
            'category_id'                 => $event['category_id'],
            'title'                       => 'Αντίγραφο: ' . $event['title'],
            'description'                 => $event['description'],
            'location_name'               => $event['location_name'],
            'address'                     => $event['address'],
            'latitude'                    => $event['latitude'],
            'longitude'                   => $event['longitude'],
            'start_datetime'              => $event['start_datetime'],
            'end_datetime'                => $event['end_datetime'],
            'requested_people'            => $event['requested_people'],
            'requested_vehicle'           => $event['requested_vehicle'],
            'requested_medical_equipment' => $event['requested_medical_equipment'],
            'instructions'                => $event['instructions'],
            'status'                      => 'draft',
            'created_by'                  => current_user_id(),
        ];

        $newId = Event::create($data);
        audit('event_cloned', 'event', $newId, 'cloned from #' . $event['id'] . ': ' . $event['title']);
        flash_set('success', 'Η δράση αντιγράφηκε ως πρόχειρο. Ενημερώστε τις ημερομηνίες και δημοσιεύστε.');
        redirect('/events/' . $newId . '/edit');
    }

    // -------------------------------------------------------------- Debriefs

    /** GET /events/{id}/debriefs — admin overview of all team debriefs for an event */
    public function debriefs($id)
    {
        requireRole(['municipality_admin', 'event_operator']);
        $event    = Event::findForCurrent($id);
        $debriefs = TeamDebrief::forEvent($event['id']);
        $stats    = TeamDebrief::statsForEvent($event['id']);

        // How many approved teams haven't submitted yet
        $approvedCount = (int) dbq(
            "SELECT COUNT(*) FROM event_applications WHERE event_id = :eid AND status = 'approved'",
            ['eid' => $event['id']]
        )->fetchColumn();

        render('events/debriefs', [
            'pageTitle'     => 'Debriefs: ' . $event['title'],
            'event'         => $event,
            'debriefs'      => $debriefs,
            'stats'         => $stats,
            'approvedCount' => $approvedCount,
        ]);
    }

    // ------------------------------------------------------------ Helpers

    /** Validate event form. Returns data array or null. */
    /** POST /events/{id}/save-template — snapshot this event (core fields + shifts) as a reusable template. */
    public function saveTemplate($id)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($id);

        $name = trim(post_str('template_name'));
        if ($name === '') {
            $name = $event['title'];
        }

        $baseStart = strtotime($event['start_datetime']);
        $shifts = [];
        foreach (EventShift::forEvent($event['id']) as $sh) {
            $shifts[] = [
                'name'             => $sh['name'],
                'start_offset_min' => (int) round((strtotime($sh['start_datetime']) - $baseStart) / 60),
                'end_offset_min'   => (int) round((strtotime($sh['end_datetime']) - $baseStart) / 60),
                'required_people'  => (int) $sh['required_people'],
                'notes'            => $sh['notes'],
            ];
        }

        EventTemplate::create([
            'municipality_id'             => $event['municipality_id'],
            'name'                        => $name,
            'title'                       => $event['title'],
            'category_id'                 => $event['category_id'],
            'description'                 => $event['description'],
            'location_name'               => $event['location_name'],
            'address'                     => $event['address'],
            'latitude'                    => $event['latitude'],
            'longitude'                   => $event['longitude'],
            'requested_people'            => (int) $event['requested_people'],
            'requested_vehicle'           => (int) $event['requested_vehicle'],
            'requested_medical_equipment' => (int) $event['requested_medical_equipment'],
            'instructions'                => $event['instructions'],
            'shifts_json'                 => json_encode($shifts, JSON_UNESCAPED_UNICODE),
            'created_by'                  => $_SESSION['user_id'],
        ]);
        audit('event_template_created', 'event', $event['id'], $name);
        flash_set('success', 'Το πρότυπο «' . $name . '» αποθηκεύτηκε.');
        redirect('/events/' . $event['id']);
    }

    /** POST /event-templates/{id}/delete */
    public function deleteTemplate($id)
    {
        requireRole(['municipality_admin']);
        EventTemplate::delete((int) $id, current_municipality_id());
        audit('event_template_deleted', 'event_template', (int) $id);
        flash_set('success', 'Το πρότυπο διαγράφηκε.');
        redirect('/events');
    }

    private function validated()
    {
        $title = post_str('title');
        $start = post_str('start_datetime');
        $end   = post_str('end_datetime');

        $errors = [];
        if ($title === '') {
            $errors[] = 'Ο τίτλος είναι υποχρεωτικός.';
        }
        if ($start === '' || strtotime($start) === false) {
            $errors[] = 'Η ημερομηνία έναρξης δεν είναι έγκυρη.';
        }
        if ($end === '' || strtotime($end) === false) {
            $errors[] = 'Η ημερομηνία λήξης δεν είναι έγκυρη.';
        }
        if (empty($errors) && strtotime($end) <= strtotime($start)) {
            $errors[] = 'Η λήξη πρέπει να είναι μετά την έναρξη.';
        }

        if ($errors) {
            remember_old();
            foreach ($errors as $err) {
                flash_set('danger', $err);
            }
            return null;
        }

        return [
            'category_id'                 => post_int('category_id') ?: null,
            'title'                       => $title,
            'description'                 => post_str('description') ?: null,
            'location_name'               => post_str('location_name') ?: null,
            'address'                     => post_str('address') ?: null,
            'latitude'                    => post_float_or_null('latitude'),
            'longitude'                   => post_float_or_null('longitude'),
            'start_datetime'              => date('Y-m-d H:i:s', strtotime($start)),
            'end_datetime'                => date('Y-m-d H:i:s', strtotime($end)),
            'requested_people'            => max(0, post_int('requested_people')),
            'requested_vehicle'           => post_bool('requested_vehicle'),
            'requested_medical_equipment' => post_bool('requested_medical_equipment'),
            'instructions'                => post_str('instructions') ?: null,
        ];
    }
}
