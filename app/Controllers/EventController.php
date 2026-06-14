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
        render('events/form', [
            'pageTitle'           => 'Νέα Δράση',
            'event'               => null,
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
       