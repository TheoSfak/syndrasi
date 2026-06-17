<?php
/**
 * SynDrasi - ShiftController.
 * Municipality admin manages shifts; team admin applies per shift.
 */
class ShiftController
{
    // ── Municipality admin: manage shifts on an event ─────────────────────────

    /** POST /events/{id}/shifts/store */
    public function store($eventId)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($eventId);

        $errors = [];
        $name   = post_str('name');
        $start  = post_str('start_datetime');
        $end    = post_str('end_datetime');
        $req    = max(0, (int) post_str('required_people'));

        if ($name  === '') { $errors[] = 'Δώστε όνομα βάρδιας.'; }
        if ($start === '') { $errors[] = 'Δώστε ώρα έναρξης.'; }
        if ($end   === '') { $errors[] = 'Δώστε ώρα λήξης.'; }
        if ($start && $end && $start >= $end) { $errors[] = 'Η λήξη πρέπει να είναι μετά την έναρξη.'; }

        if ($errors) {
            flash_set('danger', implode(' ', $errors));
            redirect('/events/' . $event['id']);
        }

        EventShift::create([
            'event_id'        => $event['id'],
            'municipality_id' => $event['municipality_id'],
            'name'            => $name,
            'start_datetime'  => $start,
            'end_datetime'    => $end,
            'required_people' => $req,
            'notes'           => post_str('notes'),
        ]);
        audit('shift_created', 'event', $event['id']);
        flash_set('success', 'Η βάρδια «' . $name . '» προστέθηκε.');
        redirect('/events/' . $event['id'] . '#tab-shifts');
    }

    /** POST /events/{id}/shifts/{sid}/update */
    public function update($eventId, $shiftId)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($eventId);
        $shift = EventShift::findForCurrent($shiftId);

        $start  = post_str('start_datetime');
        $end    = post_str('end_datetime');
        $name   = post_str('name');

        if ($name === '' || $start === '' || $end === '' || $start >= $end) {
            flash_set('danger', 'Ελέγξτε τα στοιχεία της βάρδιας.');
            redirect('/events/' . $event['id'] . '#tab-shifts');
        }

        EventShift::update($shiftId, [
            'name'            => $name,
            'start_datetime'  => $start,
            'end_datetime'    => $end,
            'required_people' => max(0, (int) post_str('required_people')),
            'notes'           => post_str('notes'),
        ]);
        audit('shift_updated', 'event_shift', $shiftId);
        flash_set('success', 'Η βάρδια ενημερώθηκε.');
        redirect('/events/' . $event['id'] . '#tab-shifts');
    }

    /** POST /events/{id}/shifts/{sid}/delete */
    public function destroy($eventId, $shiftId)
    {
        requireRole(['municipality_admin']);
        $event = Event::findForCurrent($eventId);
        EventShift::findForCurrent($shiftId); // ownership check

        EventShift::delete($shiftId);
        audit('shift_deleted', 'event_shift', $shiftId);
        flash_set('success', 'Η βάρδια διαγράφηκε.');
        redirect('/events/' . $event['id'] . '#tab-shifts');
    }

    // ── Municipality admin: approve / reject shift applications ───────────────

    /** POST /shift-applications/{id}/approve */
    public function approve($id)
    {
        requireRole(['municipality_admin']);
        $app    = EventShift::findApplication($id);
        $people = max(0, (int) post_str('approved_people', $app['offered_people']));

        EventShift::approveApplication($id, $people);
        audit('shift_app_approved', 'shift_application', $id);

        // Notify the team
        $event = Event::find($app['event_id']);
        NotificationService::shiftApplicationApproved($event, $app, $people);

        flash_set('success', 'Η αίτηση εγκρίθηκε.');
        redirect('/events/' . $app['event_id'] . '#tab-shifts');
    }

    /** POST /shift-applications/{id}/reject */
    public function reject($id)
    {
        requireRole(['municipality_admin']);
        $app = EventShift::findApplication($id);

        EventShift::rejectApplication($id);
        audit('shift_app_rejected', 'shift_application', $id);

        $event = Event::find($app['event_id']);
        NotificationService::shiftApplicationRejected($event, $app);

        flash_set('success', 'Η αίτηση απορρίφθηκε.');
        redirect('/events/' . $app['event_id'] . '#tab-shifts');
    }

    // ── Team portal: apply / cancel shift ────────────────────────────────────

    /** POST /team/events/{id}/shifts/{sid}/apply */
    public function teamApply($eventId, $shiftId)
    {
        requireRole(['team_admin']);

        $event = Event::find($eventId);
        if (!$event || !in_array($event['status'], ['open', 'review', 'confirmed', 'active'], true)) {
            abort(403, 'Η δράση δεν είναι διαθέσιμη για δήλωση.');
        }
        $shift  = EventShift::findForCurrent($shiftId);
        $teamId = current_team_id();

        EventShift::applyTeam([
            'shift_id'        => $shift['id'],
            'event_id'        => $event['id'],
            'team_id'         => $teamId,
            'municipality_id' => $event['municipality_id'],
            'offered_people'  => max(0, (int) post_str('offered_people')),
            'notes'           => post_str('notes'),
        ]);
        audit('shift_applied', 'event_shift', $shiftId);

        // Notify municipality
        $team = VolunteerTeam::find($teamId);
        NotificationService::shiftApplicationSubmitted($event, $shift, $team, (int) post_str('offered_people'));

        flash_set('success', 'Η δήλωσή σας για τη βάρδια καταχωρήθηκε.');
        redirect('/team/events/' . $eventId . '#tab-shifts');
    }

    /** POST /team/shift-applications/{id}/cancel */
    public function teamCancel($id)
    {
        requireRole(['team_admin']);
        $app = EventShift::findApplication($id);
        if ((int) $app['team_id'] !== current_team_id()) { abort(403); }

        EventShift::cancelApplication($app['shift_id'], $app['team_id']);
        audit('shift_cancelled', 'shift_application', $id);
        flash_set('success', 'Η δήλωση ακυρώθηκε.');
        redirect('/team/events/' . $app['event_id'] . '#tab-shifts');
    }
}
