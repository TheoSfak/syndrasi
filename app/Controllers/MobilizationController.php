<?php
/**
 * SynDrasi - Emergency Mobilization (Κάλεσμα Έκτακτης Ανάγκης).
 *
 * Command side (municipality_admin / event_operator): create a call-out, watch a
 * live board, stand it down. Volunteer side: a token link (no login) to reply
 * Έρχομαι / Δεν μπορώ + ETA and check in on arrival.
 */
class MobilizationController
{
    private const COMMAND_ROLES = Role::COMMAND;

    /* ── Command side ──────────────────────────────────────────────────────── */

    public function index()
    {
        requireRole(self::COMMAND_ROLES);
        render('mobilizations/index', [
            'pageTitle'      => 'Κάλεσμα Έκτακτης Ανάγκης',
            'mobilizations'  => Mobilization::forMunicipality(current_municipality_id()),
        ]);
    }

    public function create()
    {
        requireRole(self::COMMAND_ROLES);
        $mid = current_municipality_id();
        render('mobilizations/form', [
            'pageTitle' => 'Νέο Κάλεσμα',
            'teams'     => VolunteerTeam::forMunicipality($mid),
            'events'    => Event::forMunicipality($mid, ['status' => 'active']),
        ]);
    }

    public function store()
    {
        requireRole(self::COMMAND_ROLES);
        $mid = current_municipality_id();

        $title    = post_str('title');
        $severity = post_str('severity', 'high');
        if ($title === '' || !in_array($severity, Mobilization::VALID_SEVERITIES, true)) {
            flash_set('danger', t('controllers/MobilizationController.001', 'Συμπληρώστε τίτλο και επιλέξτε σοβαρότητα.'));
            remember_old();
            redirect('/mobilizations/new');
        }

        // Resolve targets: whole municipality, or only the chosen teams.
        $teamIds = (isset($_POST['team_ids']) && is_array($_POST['team_ids']))
            ? array_map('intval', $_POST['team_ids']) : [];
        $members = TeamMember::allByMunicipality($mid);
        if ($teamIds) {
            $members = array_values(array_filter($members, fn($m) => in_array((int) $m['team_id'], $teamIds, true)));
        }
        if (!$members) {
            flash_set('warning', t('controllers/MobilizationController.002', 'Δεν βρέθηκαν ενεργά μέλη για κλήση με αυτά τα κριτήρια.'));
            redirect('/mobilizations/new');
        }

        $mobId = Mobilization::create([
            'municipality_id' => $mid,
            'created_by'      => current_user_id(),
            'event_id'        => post_int('event_id') ?: null,
            'title'           => $title,
            'description'     => post_str('description') ?: null,
            'severity'        => $severity,
            'location_name'   => post_str('location_name') ?: null,
            'latitude'        => post_float_or_null('latitude'),
            'longitude'       => post_float_or_null('longitude'),
            'status'          => 'active',
        ]);

        $targets = MobilizationResponse::seedTargets($mobId, $members);

        $mob = Mobilization::find($mobId);
        NotificationService::mobilize($mob, $targets);

        audit('mobilization_created', 'mobilization', $mobId,
            ['severity' => $severity, 'targeted' => count($targets)]);

        flash_set('success', sprintf(t('controllers/MobilizationController.007', 'Το κάλεσμα στάλθηκε σε %s εθελοντές.'), count($targets)));
        redirect('/mobilizations/' . $mobId);
    }

    public function show($id)
    {
        requireRole(self::COMMAND_ROLES);
        $mob = Mobilization::findForCurrent($id);
        render('mobilizations/show', [
            'pageTitle' => 'Κάλεσμα: ' . $mob['title'],
            'mob'       => $mob,
            'snapshot'  => Mobilization::snapshot((int) $mob['id']),
        ]);
    }

    /** JSON snapshot polled by the live board. */
    public function stream($id)
    {
        requireRole(self::COMMAND_ROLES);
        $mob = Mobilization::findForCurrent($id);
        json_out(Mobilization::snapshot((int) $mob['id']));
    }

    public function standDown($id)
    {
        requireRole(self::COMMAND_ROLES);
        $mob = Mobilization::findForCurrent($id);
        Mobilization::standDown((int) $mob['id']);
        audit('mobilization_stood_down', 'mobilization', (int) $mob['id']);
        flash_set('info', t('controllers/MobilizationController.003', 'Το κάλεσμα έκλεισε.'));
        redirect('/mobilizations/' . $mob['id']);
    }

    /** Staff marks a volunteer as on-site (or departed) from the board. */
    public function checkin($id)
    {
        requireRole(self::COMMAND_ROLES);
        $mob = Mobilization::findForCurrent($id);

        $responseId = post_int('response_id');
        $row = MobilizationResponse::find($responseId);
        if (!$row || (int) $row['mobilization_id'] !== (int) $mob['id']) {
            abort(404, 'Η εγγραφή δεν βρέθηκε.');
        }

        if (post_str('action') === 'depart') {
            MobilizationResponse::depart($responseId);
        } else {
            MobilizationResponse::checkIn($responseId);
        }

        if (wants_json()) {
            json_out(Mobilization::snapshot((int) $mob['id']));
        }
        redirect('/mobilizations/' . $mob['id']);
    }

    /* ── Volunteer side (token link, no login) ─────────────────────────────── */

    public function respondForm($token)
    {
        $row = MobilizationResponse::findByToken((string) $token);
        if (!$row) {
            abort(404, 'Ο σύνδεσμος δεν είναι έγκυρος.');
        }
        render('mobilizations/respond', [
            'pageTitle' => 'Κάλεσμα Έκτακτης Ανάγκης',
            'r'         => $row,
        ], false); // standalone — no app layout / no login chrome
    }

    public function respond($token)
    {
        $row = MobilizationResponse::findByToken((string) $token);
        if (!$row) {
            abort(404, 'Ο σύνδεσμος δεν είναι έγκυρος.');
        }
        $rid    = (int) $row['id'];
        $action = post_str('action');

        if ($action === 'arrived') {
            MobilizationResponse::checkIn($rid);
            flash_set('success', t('controllers/MobilizationController.004', 'Καταγράφηκε η άφιξή σας. Ευχαριστούμε!'));
        } elseif ($action === 'departed') {
            MobilizationResponse::depart($rid);
            flash_set('info', t('controllers/MobilizationController.005', 'Καταγράφηκε η αποχώρησή σας.'));
        } elseif (in_array($action, ['coming', 'cant', 'maybe'], true)) {
            $eta = ($action === 'coming') ? (post_int('eta_minutes') ?: null) : null;
            MobilizationResponse::setResponse($rid, $action, $eta);
            flash_set('success', $action === 'cant'
                ? 'Καταγράφηκε ότι δεν μπορείτε. Ευχαριστούμε για την ενημέρωση.'
                : 'Η απάντησή σας καταγράφηκε. Ευχαριστούμε!');
        } else {
            flash_set('danger', t('controllers/MobilizationController.006', 'Μη έγκυρη επιλογή.'));
        }

        redirect('/m/' . $row['token']);
    }
}
