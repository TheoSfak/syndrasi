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
        NotificationService::applicationApproved($event, $application);

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
        NotificationService::applicationRejected($event, $application, (string) $comment);

        flash_set('success', 'Η δήλωση της ομάδας "' . $application['team_name'] . '" απορρίφθηκε.');
        redirect('/events/' . $application['event_id'] . '/applications');
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
