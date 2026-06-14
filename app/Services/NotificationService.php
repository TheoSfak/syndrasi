<?php
/**
 * SynDrasi - Notification service.
 * Creates in-app notifications and sends matching emails via EmailTemplate.
 */
class NotificationService
{
    /**
     * Returns true if email should be sent for this notification type.
     * Default is ON; municipality admin can disable per type in settings.
     */
    public static function shouldSendEmail($municipalityId, $type)
    {
        if (!$municipalityId) { return true; }
        $val = MunicipalitySetting::get($municipalityId, 'notify_email_' . $type, null);
        return $val === null || $val === '1';
    }

    /**
     * Notify all team admins of a team.
     * $emailSubject / $emailBody override the in-app title/message for the email.
     */
    public static function notifyTeam($teamId, $eventId, $title, $message, $type, $municipalityId = null, $emailSubject = null, $emailBody = null)
    {
        $admins = User::teamAdmins($teamId);
        foreach ($admins as $admin) {
            $eSubject = $emailSubject ?? $title;
            $eBody    = $emailBody    ?? $message;
            $sent = self::shouldSendEmail($municipalityId, $type)
                ? MailService::send($admin['email'], $admin['name'], $eSubject, $eBody, $municipalityId)
                : false;
            Notification::create([
                'municipality_id' => $municipalityId,
                'user_id'         => $admin['id'],
                'team_id'         => $teamId,
                'event_id'        => $eventId,
                'title'           => $title,
                'message'         => $message,
                'type'            => $type,
                'email_sent'      => $sent ? 1 : 0,
            ]);
            // Web push
            self::sendPush($admin['id'], $title, $message);
        }
    }

    /**
     * Notify all municipality admins.
     * $emailSubject / $emailBody override the in-app title/message for the email.
     */
    public static function notifyMunicipality($municipalityId, $eventId, $title, $message, $type, $emailSubject = null, $emailBody = null)
    {
        $admins = User::municipalityAdmins($municipalityId);
        foreach ($admins as $admin) {
            $eSubject = $emailSubject ?? $title;
            $eBody    = $emailBody    ?? $message;
            $sent = self::shouldSendEmail($municipalityId, $type)
                ? MailService::send($admin['email'], $admin['name'], $eSubject, $eBody, $municipalityId)
                : false;
            Notification::create([
                'municipality_id' => $municipalityId,
                'user_id'         => $admin['id'],
                'event_id'        => $eventId,
                'title'           => $title,
                'message'         => $message,
                'type'            => $type,
                'email_sent'      => $sent ? 1 : 0,
            ]);
            // Web push
            self::sendPush($admin['id'], $title, $message);
        }
    }

    /**
     * Send a web push notification to all subscriptions of a user.
     * Silent failure — push is best-effort, never breaks the main flow.
     */
    private static function sendPush(int $userId, string $title, string $body): void
    {
        try {
            $subs = dbq('SELECT * FROM push_subscriptions WHERE user_id = :uid', ['uid' => $userId])->fetchAll();
            if (!$subs) { return; }
            $payload = ['title' => $title, 'body' => $body, 'url' => '/notifications'];
            foreach ($subs as $sub) {
                WebPushService::send($sub, $payload);
            }
        } catch (Throwable $e) {
            error_log('[WebPush] sendPush error for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    /* ── Domain events ───────────────────────────────────────────────────── */

    /** Event published: notify all active teams of the municipality. */
    public static function eventPublished(array $event)
    {
        $teams = VolunteerTeam::forMunicipality($event['municipality_id'], true);
        $mid   = $event['municipality_id'];

        // In-app notification (short)
        $inAppTitle   = 'Νέα δράση: ' . $event['title'];
        $inAppMessage = 'Δημοσιεύθηκε νέα δράση. Συνδεθείτε για να δηλώσετε συμμετοχή.';

        // Email body via template
        $tpl = EmailTemplate::resolve($mid, 'event_published', [
            'event_title'    => $event['title'],
            'event_category' => $event['category_name'] ?? '—',
            'event_date'     => gr_datetime($event['start_datetime']),
            'event_location' => $event['location_name'] ?: '—',
        ]);

        foreach ($teams as $team) {
            self::notifyTeam(
                $team['id'], $event['id'],
                $inAppTitle, $inAppMessage,
                'event_published', $mid,
                $tpl['subject'], $tpl['body']
            );
        }
    }

    /** Application submitted: notify the municipality. */
    public static function applicationSubmitted(array $event, array $team, $offeredPeople)
    {
        $mid = $event['municipality_id'];

        $inAppTitle   = 'Νέα δήλωση συμμετοχής: ' . $event['title'];
        $inAppMessage = 'Η ομάδα "' . $team['name'] . '" δήλωσε συμμετοχή με ' . (int) $offeredPeople . ' άτομα.';

        $tpl = EmailTemplate::resolve($mid, 'application_submitted', [
            'event_title'    => $event['title'],
            'team_name'      => $team['name'],
            'offered_people' => (int) $offeredPeople,
        ]);

        self::notifyMunicipality(
            $mid, $event['id'],
            $inAppTitle, $inAppMessage,
            'application_submitted',
            $tpl['subject'], $tpl['body']
        );
    }

    /** Application approved: notify the team. */
    public static function applicationApproved(array $event, array $application)
    {
        $mid = $event['municipality_id'];

        $inAppTitle   = 'Εγκρίθηκε η συμμετοχή σας στη δράση';
        $inAppMessage = 'Η συμμετοχή της ομάδας εγκρίθηκε για τη δράση "' . $event['title'] . '" με ' . (int) $application['approved_people'] . ' άτομα.';

        $tpl = EmailTemplate::resolve($mid, 'application_approved', [
            'event_title'     => $event['title'],
            'event_date'      => gr_date($event['start_datetime']),
            'event_time'      => gr_time($event['start_datetime']),
            'event_location'  => $event['location_name'] ?: '—',
            'approved_people' => (int) $application['approved_people'],
        ]);

        self::notifyTeam(
            $application['team_id'], $event['id'],
            $inAppTitle, $inAppMessage,
            'application_approved', $mid,
            $tpl['subject'], $tpl['body']
        );
    }

    /** Application rejected: notify the team. */
    public static function applicationRejected(array $event, array $application, $reason = '')
    {
        $mid = $event['municipality_id'];

        $inAppTitle   = 'Απάντηση στη δήλωση συμμετοχής σας';
        $inAppMessage = 'Η δήλωση συμμετοχής για τη δράση "' . $event['title'] . '" δεν εγκρίθηκε.';

        $reasonLine = $reason !== '' ? "\nΣχόλιο δήμου: " . $reason . "\n" : '';
        $tpl = EmailTemplate::resolve($mid, 'application_rejected', [
            'event_title'      => $event['title'],
            'rejection_reason' => $reasonLine,
        ]);

        self::notifyTeam(
            $application['team_id'], $event['id'],
            $inAppTitle, $inAppMessage,
            'application_rejected', $mid,
            $tpl['subject'], $tpl['body']
        );
    }

    /** Shortage reported: notify the municipality. */
    public static function shortageReported(array $event, array $team, $typeLabel, $severityLabel, $shortageTitle)
    {
        $mid = $event['municipality_id'];

        $inAppTitle   = 'Αναφορά έλλειψης: ' . $event['title'];
        $inAppMessage = 'Η ομάδα "' . $team['name'] . '" ανέφερε έλλειψη (' . $typeLabel . ', ' . $severityLabel . ').';

        $tpl = EmailTemplate::resolve($mid, 'shortage_reported', [
            'event_title'       => $event['title'],
            'team_name'         => $team['name'],
            'shortage_type'     => $typeLabel,
            'shortage_severity' => $severityLabel,
            'shortage_title'    => $shortageTitle,
        ]);

        self::notifyMunicipality(
            $mid, $event['id'],
            $inAppTitle, $inAppMessage,
            'shortage_reported',
            $tpl['subject'], $tpl['body']
        );
    }

    /** Event reminder: notify approved teams before the event. */
    public static function eventReminder(array $event)
    {
        $applications = EventApplication::approvedForEvent($event['id']);
        $mid          = $event['municipality_id'];

        $inAppTitle = 'Υπενθύμιση δράσης: ' . $event['title'];

        foreach ($applications as $app) {
            $inAppMessage = 'Υπενθύμιση για τη δράση "' . $event['title'] . '" στις ' . gr_datetime($event['start_datetime']) . '.';

            $tpl = EmailTemplate::resolve($mid, 'event_reminder', [
                'event_title'     => $event['title'],
                'event_date'      => gr_date($event['start_datetime']),
                'event_time'      => gr_time($event['start_datetime']),
                'event_location'  => $event['location_name'] ?: '—',
                'approved_people' => (int) $app['approved_people'],
            ]);

            self::notifyTeam(
                $app['team_id'], $event['id'],
                $inAppTitle, $inAppMessage,
                'event_reminder', $mid,
                $tpl['subject'], $tpl['body']
            );
        }

        return count($applications);
    }

    /** Event completed/closed: notify approved teams to submit reports. */
    public static function eventCompleted(array $event)
    {
        $applications = EventApplication::approvedForEvent($event['id']);
        $mid          = $event['municipality_id'];

        $inAppTitle   = 'Ολοκληρώθηκε η δράση: ' . $event['title'];
        $inAppMessage = 'Παρακαλούμε υποβάλετε τη σύντομη αναφορά της ομάδας σας.';

        $tpl = EmailTemplate::resolve($mid, 'event_completed', [
            'event_title' => $event['title'],
        ]);

        foreach ($applications as $app) {
            self::notifyTeam(
                $app['team_id'], $event['id'],
                $inAppTitle, $inAppMessage,
                'event_completed', $mid,
                $tpl['subject'], $tpl['body']
            );
        }
    }

    // ── Shift Scheduling notifications ───────────────────────────────────────

    /** Shift application submitted: notify the municipality. */
    public static function shiftApplicationSubmitted(array $event, array $shift, array $team, $offeredPeople)
    {
        $mid = $event['municipality_id'];
        $inAppTitle   = 'Δήλωση βάρδιας: ' . $event['title'];
        $inAppMessage = 'Η ομάδα "' . $team['name'] . '" δήλωσε βάρδια «' . $shift['name'] . '» με ' . (int) $offeredPeople . ' άτομα.';
        self::notifyMunicipality($mid, $event['id'], $inAppTitle, $inAppMessage, 'application_submitted');
    }

    /** Shift application approved: notify the team. */
    public static function shiftApplicationApproved(array $event, array $app, $approvedPeople)
    {
        $mid  = $event['municipality_id'];
        $inAppTitle   = 'Εγκρίθηκε βάρδια: ' . $app['shift_name'];
        $inAppMessage = 'Η βάρδια «' . $app['shift_name'] . '» εγκρίθηκε για ' . (int) $approvedPeople . ' άτομα. Δράση: ' . $event['title'] . '.';
        self::notifyTeam($app['team_id'], $event['id'], $inAppTitle, $inAppMessage, 'application_approved', $mid);
    }

    /** Shift application rejected: notify the team. */
    public static function shiftApplicationRejected(array $event, array $app)
    {
        $mid = $event['municipality_id'];
        $inAppTitle   = 'Απορρίφθηκε βάρδια: ' . $app['shift_name'];
        $inAppMessage = 'Η δήλωση βάρδιας «' . $app['shift_name'] . '» δεν εγκρίθηκε. Δράση: ' . $event['title'] . '.';
        self::notifyTeam($app['team_id'], $event['id'], $inAppTitle, $inAppMessage, 'application_rejected', $mid);
    }

    /** Shift reminder: sent 1 hour before a shift starts to all approved teams. */
    public static function shiftReminder(array $event, array $shift)
    {
        $mid  = $event['municipality_id'];
        $apps = dbq(
            'SELECT * FROM shift_applications WHERE shift_id = :sid AND status = \'approved\'',
            ['sid' => $shift['id']]
        )->fetchAll();

        $inAppTitle = 'Υπενθύμιση βάρδιας: ' . $shift['name'];
        foreach ($apps as $app) {
            $msg = 'Υπενθύμιση: η βάρδια «' . $shift['name'] . '» ξεκινά στις ' . gr_time($shift['start_datetime']) . '. Δράση: ' . $event['title'] . '.';
            self::notifyTeam($app['team_id'], $event['id'], $inAppTitle, $msg, 'event_reminder', $mid);
        }
        return count($apps);
    }

    /**
     * Send an email to each team member assigned to an application.
     * Skips members with no email address.
     * Respects the notify_email_application_approved gating per municipality.
     */
    public static function notifyMembersAssigned(array $event, array $memberIds, $commanderId, $applicationId)
    {
        $mid = $event['municipality_id'];

        if (!self::shouldSendEmail($mid, 'application_approved')) {
            return;
        }

        foreach ($memberIds as $memberId) {
            $member = TeamMember::find($memberId);
            if (!$member || empty($member['email'])) {
                continue;
            }

            $isCommander   = ((int) $memberId === (int) $commanderId);
            $role          = $isCommander ? 'Mission Υπεύθυνος' : 'Μέλος ομάδας';
            $commanderNote = $isCommander
                ? "Ως Mission Υπεύθυνος, εσύ θα είσαι υπεύθυνος για την αποστολή στίγματος και ενημερώσεων κατά τη διάρκεια της δράσης.\n\n"
                : '';

            $tpl = EmailTemplate::resolve($mid, 'member_assigned', [
                'member_name'    => $member['full_name'],
                'event_title'    => $event['title'],
                'event_start'    => gr_datetime($event['start_datetime']),
                'event_end'      => gr_datetime($event['end_datetime']),
                'event_location' => $event['location_name'] ?: ($event['address'] ?: '—'),
                'member_role'    => $role,
                'commander_note' => $commanderNote,
            ]);

            MailService::send($member['email'], $member['full_name'], $tpl['subject'], $tpl['body'], $mid);
        }
    }
}
