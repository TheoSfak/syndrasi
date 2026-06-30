<?php
/**
 * SynDrasi - Application review (municipality admin).
 */
class ApplicationController
{
    /** All pending applications across events. */
    public function pending()
    {
        requireRole(['municipality_admin']);
        $applications = EventApplication::pendingForMunicipality(current_municipality_id());
        render('applications/pending', [
            'pageTitle'    => 'Δηλώσεις Συμμετοχής',
            'applications' => $applications,
        ]);
    }

    /** Applications of one event with review actions. */
    public function index($eventId)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($eventId);
        $applications = EventApplication::forEvent($event['id']);

        // attach short team history for the review screen
        foreach ($applications as $k => $app) {
            $applications[$k]['history'] = EventApplication::teamHistorySummary($app['team_id']);
            $applications[$k]['match'] = TeamMissionMatcher::scoreTeam($event, [
                'has_vehicle'             => $app['team_has_vehicle'] ?? 0,
                'has_medical_equipment'   => $app['team_has_medical'] ?? 0,
                'default_people_capacity' => $app['team_default_people_capacity'] ?? 0,
                'readiness_items_json'    => $app['team_readiness_items_json'] ?? null,
            ], $app);
        }

        render('applications/index', [
            'pageTitle'    => 'Δηλώσεις: ' . $event['title'],
            'event'        => $event,
            'applications' => $applications,
        ]);
    }

    public function approve($id)
    {
        requireRole(['municipality_admin']);
        $application = $this->findOwn($id);

        $approvedPeople = post_int('approved_people');
        if ($approvedPeople < 1) {
            flash_set('danger', 'Συμπληρώστε τον εγκεκριμένο αριθμό ατόμων (τουλάχιστον 1).');
            redirect('/events/' . $application['event_id'] . '/applications');
        }

        $comment = post_str('admin_comment') ?: null;
        EventApplication::approve($application['id'], $approvedPeople, $comment, $_SESSION['user_id']);
        audit('application_approved', 'event_application', $application['id'], 'approved_people: ' . $approvedPeople);

        $event = Event::find($application['event_id']);
        $application['approved_people'] = $approvedPeople;
        try { NotificationService::applicationApproved($event, $application); } catch (Throwable $e) { error_log($e); }

        flash_set('success', 'Η συμμετοχή της ομάδας "' . $application['team_name'] . '" εγκρίθηκε.');
        redirect('/events/' . $application['event_id'] . '/applications');
    }

    public function reject($id)
    {
        requireRole(['municipality_admin']);
        $application = $this->findOwn($id);

        $comment = post_str('admin_comment') ?: null;
        EventApplication::reject($application['id'], $comment, $_SESSION['user_id']);
        audit('application_rejected', 'event_application', $application['id']);

        $event = Event::find($application['event_id']);
        try { NotificationService::applicationRejected($event, $application, (string) $comment); } catch (Throwable $e) { error_log($e); }

        flash_set('success', 'Η δήλωση της ομάδας "' . $application['team_name'] . '" απορρίφθηκε.');
        redirect('/events/' . $application['event_id'] . '/applications');
    }

    /** POST /events/{id}/applications/bulk — batch approve or reject multiple applications */
    public function bulkApprove($eventId)
    {
        requireRole(['municipality_admin']);
        $event  = Event::findForCurrent((int) $eventId);
        $action = post_str('bulk_action'); // 'approve' | 'reject'
        $appIds = isset($_POST['app_ids']) && is_array($_POST['app_ids'])
            ? array_map('intval', $_POST['app_ids']) : [];

        if (empty($appIds)) {
            flash_set('warning', 'Δεν επιλέξατε καμία δήλωση.');
            redirect('/events/' . $event['id'] . '/applications');
        }
        if (!in_array($action, ['approve', 'reject'], true)) {
            flash_set('warning', 'Μη έγκυρη ενέργεια.');
            redirect('/events/' . $event['id'] . '/applications');
        }

        // Fetch all selected applications in one query to avoid N+1
        $in = implode(',', array_fill(0, count($appIds), '?'));
        $appsById = [];
        foreach (dbq("SELECT * FROM event_applications WHERE id IN ($in)", $appIds)->fetchAll() as $a) {
            $appsById[(int) $a['id']] = $a;
        }

        $count = 0;
        foreach ($appIds as $appId) {
            $app = $appsById[$appId] ?? null;
            if (!$app
                || (int) $app['event_id']        !== (int) $event['id']
                || (int) $app['municipality_id']  !== (int) current_municipality_id()
                || $app['status'] !== 'pending') {
                continue;
            }

            if ($action === 'approve') {
                $people = isset($_POST['approved_people'][$appId])
                    ? (int) $_POST['approved_people'][$appId]
                    : (int) $app['offered_people'];
                if ($people < 1) $people = (int) $app['offered_people'];
                EventApplication::approve($appId, $people, null, current_user_id());
                $app['approved_people'] = $people;
                try { NotificationService::applicationApproved($event, $app); } catch (Throwable $e) { error_log($e); }
                audit('application_approved', 'event_application', $appId, 'bulk, people: ' . $people);
            } else {
                EventApplication::reject($appId, null, current_user_id());
                try { NotificationService::applicationRejected($event, $app, ''); } catch (Throwable $e) { error_log($e); }
                audit('application_rejected', 'event_application', $appId, 'bulk');
            }
            $count++;
        }

        $label = $action === 'approve' ? 'εγκρίθηκαν' : 'απορρίφθηκαν';
        flash_set('success', $count . ' δηλώσεις ' . $label . '.');
        redirect('/events/' . $event['id'] . '/applications');
    }

    private function findOwn($id)
    {
        $application = EventApplication::find($id);
        if (!$application) {
            abort(404, 'Η δήλωση δεν βρέθηκε.');
        }
        requireMunicipalityAccess($application['municipality_id']);
        return $application;
    }
}
