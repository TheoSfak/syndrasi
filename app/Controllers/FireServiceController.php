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
            flash_set('success', 'Η ενημέρωση ολοκληρώθηκε. Βρέθηκαν ' . (int) $result['incidents'] . ' συμβάντα.');
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
            audit('fire_service_event_created', 'fire_service_incident', (int) $id, ['event_id' => $eventId]);
            flash_set('success', 'Δημιουργήθηκε πρόχειρη δράση από το συμβάν. Ελέγξτε τα στοιχεία πριν από δημοσίευση.');
            redirect('/events/' . $eventId . '/edit');
        } catch (Throwable $e) {
            flash_set('danger', $e->getMessage());
            redirect('/fire-service');
        }
    }
}
