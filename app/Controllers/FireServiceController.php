<?php
/**
 * Fire Service incident monitor for municipality admins.
 */
class FireServiceController
{
    public function index()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();
        $municipality = $mid ? Municipality::find($mid) : null;
        $defaults = FireServiceIncidentService::defaultFiltersForMunicipality($municipality);

        $filters = [
            'region' => isset($_GET['region']) ? trim((string) $_GET['region']) : $defaults['region'],
            'regional_unit' => isset($_GET['regional_unit']) ? trim((string) $_GET['regional_unit']) : $defaults['regional_unit'],
            'category' => isset($_GET['category']) ? trim((string) $_GET['category']) : '',
            'status_label' => isset($_GET['status_label']) ? trim((string) $_GET['status_label']) : '',
            'current' => isset($_GET['history']) ? '0' : '1',
            'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
        ];

        render('fire_service/index', [
            'pageTitle' => 'Συμβάντα Πυροσβεστικής',
            'incidents' => FireServiceIncidentService::list($filters),
            'options' => FireServiceIncidentService::options(),
            'filters' => $filters,
            'defaults' => $defaults,
            'latestFetch' => FireServiceIncidentService::latestFetch(),
            'sourceUrl' => FireServiceIncidentService::SOURCE_URL,
        ]);
    }

    public function sync()
    {
        requireRole(['municipality_admin']);
        $result = FireServiceIncidentService::sync();
        if ($result['success']) {
            $telegramSent = (int) ($result['telegram_sent'] ?? 0);
            flash_set('success', 'Η ενημέρωση ολοκληρώθηκε. Βρέθηκαν ' . (int) $result['incidents'] . ' συμβάντα.'
                . ($telegramSent > 0 ? ' Στάλθηκαν ' . $telegramSent . ' ειδοποιήσεις Telegram.' : ' Δεν υπήρχαν νέες ειδοποιήσεις Telegram.'));
        } else {
            flash_set('danger', 'Η ενημέρωση απέτυχε: ' . $result['error']);
        }
        audit('fire_service_sync', 'fire_service_fetch', $result['fetch_id'] ?? null, $result);
        redirect('/fire-service');
    }

    public function createEvent($id)
    {
        requireRole(['municipality_admin']);
        try {
            $eventId = FireServiceIncidentService::createEventDraft((int) $id, current_municipality_id(), current_user_id());
            $terms = authority_context(current_municipality_id());
            $eventSingularLc = mb_strtolower($terms['event_singular'] ?? 'Δράση', 'UTF-8');
            audit('fire_service_event_created', 'fire_service_incident', (int) $id, ['event_id' => $eventId]);
            flash_set('success', 'Δημιουργήθηκε πρόχειρη ' . $eventSingularLc . ' από το συμβάν. Ελέγξτε τα στοιχεία πριν από δημοσίευση.');
            redirect('/events/' . $eventId . '/edit');
        } catch (Throwable $e) {
            flash_set('danger', $e->getMessage());
            redirect('/fire-service');
        }
    }

    public function mobilizeReview($id)
    {
        requireRole(['municipality_admin']);
        try {
            $data = FireServiceIncidentService::mobilizationReviewData((int) $id, current_municipality_id());
            render('fire_service/mobilize_review', [
                'pageTitle' => 'Έλεγχος Κινητοποίησης',
                'incidentId' => (int) $id,
                'incident' => $data['incident'],
                'title' => $data['title'],
                'description' => $data['description'],
                'severity' => $data['severity'],
                'locationName' => $data['location_name'],
                'teams' => $data['teams'],
                'existing' => $data['existing'],
            ]);
        } catch (Throwable $e) {
            flash_set('danger', $e->getMessage());
            redirect('/fire-service');
        }
    }

    public function mobilize($id)
    {
        requireRole(['municipality_admin']);
        try {
            $teamIds = (isset($_POST['team_ids']) && is_array($_POST['team_ids']))
                ? array_map('intval', $_POST['team_ids'])
                : [];
            $result = FireServiceIncidentService::createMobilization((int) $id, current_municipality_id(), current_user_id(), [
                'team_ids' => $teamIds,
                'require_vehicle' => post_str('require_vehicle') === '1',
                'require_medical' => post_str('require_medical') === '1',
            ]);
            audit('fire_service_mobilization_created', 'fire_service_incident', (int) $id, $result);
            if (!empty($result['existing'])) {
                flash_set('info', 'Υπάρχει ήδη ενεργό κάλεσμα για αυτό το συμβάν. Ανοίγει ο ζωντανός πίνακας.');
            } else {
                flash_set('success', 'Ξεκίνησε κάλεσμα έκτακτης ανάγκης σε ' . (int) $result['targeted'] . ' εθελοντές.');
            }
            redirect('/mobilizations/' . (int) $result['mobilization_id']);
        } catch (Throwable $e) {
            flash_set('danger', $e->getMessage());
            redirect('/fire-service');
        }
    }
}
