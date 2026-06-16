<?php
/**
 * SynDrasi - Notification service.
 * Creates in-app notifications and sends matching emails via EmailTemplate.
 */
class NotificationService
{
    /**
     * Delivery channel for a notification type: 'off' | 'email' | 'sms' | 'both'.
     * Reads notify_channel_<type>; falls back to the legacy notify_email_<type>
     * toggle (1/unset → email, 0 → off). Default when nothing is set: 'email'.
     */
    public static function channelFor($municipalityId, $type)
    {
        if (!$municipalityId) { return 'email'; }
        $ch = MunicipalitySetting::get($municipalityId, 'notify_channel_' . $type, null);
        if ($ch !== null && in_array($ch, ['off', 'email', 'sms', 'both'], true)) {
            return $ch;
        }
        // Legacy fallback
        $legacy = MunicipalitySetting::get($municipalityId, 'notify_email_' . $type, null);
        return $legacy === '0' ? 'off' : 'email';
    }

    /**
     * Returns true if email should be sent for this notification type.
     * Default is ON; municipality admin can disable/switch per type in settings.
     */
    public static function shouldSendEmail($municipalityId, $type)
    {
        return in_array(self::channelFor($municipalityId, $type), ['email', 'both'], true);
    }

    /** Returns true if SMS should be sent for this notification type. */
    public static function shouldSendSms($municipalityId, $type)
    {
        return in_array(self::channelFor($municipalityId, $type), ['sms', 'both'], true);
    }

    /** Send a short SMS to a recipient with a phone, best-effort. */
    private static function maybeSms($municipalityId, $type, $phone, $title, $message)
    {
        if (empty($phone) || !self::shouldSendSms($municipalityId, $type)) { return; }
        try {
            $text = trim($title . ' — ' . strip_tags($message));
            SmsService::send($phone, $text, $municipalityId);
        } catch (Throwable $e) {
            error_log('[Notify] sms failed: ' . $e->getMessage());
        }
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
            // SMS (if channel includes sms and the admin has a phone)
            self::maybeSms($municipalityId, $type, $admin['phone'] ?? '', $title, $message);
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
            // SMS (if channel includes sms and the admin has a phone)
            self::maybeSms($municipalityId, $type, $admin['phone'] ?? '', $title, $message);
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

    /* ── Emergency mobilization ──────────────────────────────────────────── */

    /**
     * Fan out an emergency call-out.
     *  - Email each targeted member their personal response link (best-effort).
     *  - Web-push to members who also hold a user account (matched by email).
     *  - In-app + push awareness ping to municipality command staff (no email).
     * Every channel is wrapped so one failure never blocks the rest.
     * $targets = rows from MobilizationResponse::seedTargets (id, token, email, phone, full_name).
     */
    public static function mobilize(array $mob, array $targets)
    {
        $mid   = (int) $mob['municipality_id'];
        $sev   = severity_label($mob['severity']);
        $title = 'Κάλεσμα έκτακτης ανάγκης: ' . $mob['title'];

        // Map active user accounts of this municipality by email → id (for push).
        $usersByEmail = [];
        foreach (dbq(
            'SELECT id, email FROM users WHERE municipality_id = :mid AND status = :st',
            ['mid' => $mid, 'st' => 'active']
        )->fetchAll() as $u) {
            if (!empty($u['email'])) {
                $usersByEmail[mb_strtolower($u['email'])] = (int) $u['id'];
            }
        }

        foreach ($targets as $t) {
            $link   = self::absoluteUrl('/m/' . $t['token']);
            $pushed = false;
            $email  = !empty($t['email']) ? mb_strtolower($t['email']) : '';

            // Prefer the directly linked account (migration 010); fall back to
            // matching the member's email to a municipality user.
            $pushUserId = !empty($t['user_id']) ? (int) $t['user_id']
                        : (($email !== '' && isset($usersByEmail[$email])) ? $usersByEmail[$email] : null);
            if ($pushUserId) {
                try {
                    WebPushService::sendToUser($pushUserId, [
                        'title' => $title,
                        'body'  => $sev . ' — πατήστε για να απαντήσετε.',
                        'url'   => '/m/' . $t['token'],
                    ]);
                    $pushed = true;
                } catch (Throwable $e) {
                    error_log('[Mobilize] push failed: ' . $e->getMessage());
                }
            }

            if (!empty($t['email'])) {
                try {
                    $body = '<p>Ενεργοποιήθηκε <strong>κάλεσμα έκτακτης ανάγκης</strong> (' . e($sev) . ').</p>'
                          . '<p><strong>' . e($mob['title']) . '</strong></p>'
                          . (!empty($mob['location_name']) ? '<p>Τοποθεσία: ' . e($mob['location_name']) . '</p>' : '')
                          . '<p>Παρακαλούμε δηλώστε αν μπορείτε να ανταποκριθείτε:</p>'
                          . '<p><a href="' . $link . '">' . $link . '</a></p>';
                    MailService::send($t['email'], $t['full_name'] ?? '', $title, $body, $mid);
                } catch (Throwable $e) {
                    error_log('[Mobilize] email failed: ' . $e->getMessage());
                }
            }

            // SMS — most reliable channel for volunteers without an account.
            // Uses the configured driver (default 'log' → storage/logs/sms.log).
            if (!empty($t['phone'])) {
                try {
                    $sms = $mob['title'] . ' — Κάλεσμα έκτακτης ανάγκης (' . $sev . '). '
                         . 'Απαντήστε εδώ: ' . $link;
                    SmsService::send($t['phone'], $sms, $mid);
                } catch (Throwable $e) {
                    error_log('[Mobilize] sms failed: ' . $e->getMessage());
                }
            }

            MobilizationResponse::markNotified((int) $t['id'], $pushed);
        }

        // Awareness ping to command staff — in-app + push only (no email noise).
        try {
            $msg = 'Στάλθηκε κάλεσμα σε ' . count($targets) . ' εθελοντές (' . $sev . ').';
            foreach (User::municipalityAdmins($mid) as $admin) {
                Notification::create([
                    'municipality_id' => $mid,
                    'user_id'         => $admin['id'],
                    'title'           => $title,
                    'message'         => $msg,
                    'type'            => 'mobilization',
                    'email_sent'      => 0,
                ]);
                self::sendPush($admin['id'], $title, $msg);
            }
        } catch (Throwable $e) {
            error_log('[Mobilize] command notify failed: ' . $e->getMessage());
        }
    }

    /* ── Photo requests ──────────────────────────────────────────────────── */

    /** Admin asked a team for a photo — notify the team (in-app + push). */
    public static function photoRequested(array $event, array $team)
    {
        $title = 'Αίτημα φωτογραφίας';
        $msg   = 'Ο δήμος ζητά φωτογραφία για τη δράση «' . $event['title'] . '».';
        foreach (User::teamAdmins($team['id']) as $admin) {
            Notification::create([
                'municipality_id' => $event['municipality_id'],
                'user_id'         => $admin['id'],
                'team_id'         => $team['id'],
                'event_id'        => $event['id'],
                'title'           => $title,
                'message'         => $msg,
                'type'            => 'photo_request',
                'email_sent'      => 0,
            ]);
            self::sendPush($admin['id'], $title, $msg);
        }
    }

    /** Team uploaded a photo — notify municipality admins (in-app + push). */
    public static function photoUploaded(array $event, int $teamId)
    {
        $team  = VolunteerTeam::find($teamId);
        $tname = $team['name'] ?? ('#' . $teamId);
        $title = 'Νέα φωτογραφία ομάδας';
        $msg   = 'Η ομάδα «' . $tname . '» έστειλε φωτογραφία για τη δράση «' . $event['title'] . '».';
        foreach (User::municipalityAdmins($event['municipality_id']) as $admin) {
            Notification::create([
                'municipality_id' => $event['municipality_id'],
                'user_id'         => $admin['id'],
                'event_id'        => $event['id'],
                'title'           => $title,
                'message'         => $msg,
                'type'            => 'photo_uploaded',
                'email_sent'      => 0,
            ]);
            self::sendPush($admin['id'], $title, $msg);
        }
    }

    /* ── Active-event communications ─────────────────────────────────────── */

    /**
     * SOS / man-down raised by a team — FORCED push + SMS to all command staff
     * (municipality admins + operators), regardless of notification-channel settings.
     */
    public static function sosRaised(array $alert, array $event, array $team)
    {
        $mid     = (int) $event['municipality_id'];
        $tname   = $team['name'] ?? 'Ομάδα';
        $title   = '🆘 SOS — ' . $tname;
        $hasGeo  = !empty($alert['latitude']) && !empty($alert['longitude']);
        $note    = !empty($alert['note']) ? ' Σημείωση: ' . $alert['note'] : '';
        $msg     = 'Η ομάδα «' . $tname . '» εξέπεμψε SOS στη δράση «' . $event['title'] . '».' . $note;
        $link    = self::absoluteUrl('/operations/events/' . $event['id']);
        $geoTxt  = $hasGeo ? (' Θέση: ' . $alert['latitude'] . ',' . $alert['longitude'] . '.') : '';
        $sms     = 'SOS! ' . $tname . ' — ' . $event['title'] . '.' . $geoTxt . ' ' . $link;

        foreach (User::commandStaff($mid) as $u) {
            try {
                Notification::create([
                    'municipality_id' => $mid,
                    'user_id'         => $u['id'],
                    'event_id'        => $event['id'],
                    'title'           => $title,
                    'message'         => $msg,
                    'type'            => 'sos',
                    'email_sent'      => 0,
                ]);
                self::sendPush($u['id'], $title, $msg);       // forced — always
                if (!empty($u['phone'])) {
                    SmsService::send($u['phone'], $sms, $mid); // forced — always
                }
            } catch (Throwable $e) {
                error_log('[SOS] notify failed: ' . $e->getMessage());
            }
        }
    }

    /** Command acknowledged a team's SOS — close the loop back to the team. */
    public static function sosAcknowledged(array $alert, array $event)
    {
        $title = 'Το SOS σας ελήφθη';
        $msg   = 'Ο δήμος έλαβε το SOS σας για τη δράση «' . $event['title'] . '» και ανταποκρίνεται.';
        foreach (User::teamAdmins((int) $alert['team_id']) as $u) {
            try {
                Notification::create([
                    'municipality_id' => (int) $event['municipality_id'],
                    'user_id'         => $u['id'],
                    'team_id'         => (int) $alert['team_id'],
                    'event_id'        => (int) $event['id'],
                    'title'           => $title,
                    'message'         => $msg,
                    'type'            => 'sos_ack',
                    'email_sent'      => 0,
                ]);
                self::sendPush($u['id'], $title, $msg);
            } catch (Throwable $e) {
                error_log('[SOS] ack notify failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Command → team(s) message or order. $teamIds = list of team ids to deliver to
     * (a broadcast resolves to all approved teams before calling this).
     */
    public static function commandMessage(array $event, array $teamIds, string $kind, string $body)
    {
        $mid   = (int) $event['municipality_id'];
        $title = $kind === 'order' ? 'Εντολή δήμου' : 'Μήνυμα δήμου';
        $msg   = mb_substr(trim($body), 0, 200);
        foreach (array_unique(array_map('intval', $teamIds)) as $tid) {
            foreach (User::teamAdmins($tid) as $u) {
                try {
                    Notification::create([
                        'municipality_id' => $mid,
                        'user_id'         => $u['id'],
                        'team_id'         => $tid,
                        'event_id'        => (int) $event['id'],
                        'title'           => $title,
                        'message'         => $msg,
                        'type'            => 'ops_message',
                        'email_sent'      => 0,
                    ]);
                    self::sendPush($u['id'], $title, $msg);
                } catch (Throwable $e) {
                    error_log('[Comms] command message failed: ' . $e->getMessage());
                }
            }
        }
    }

    /** Team → command message / status ping — in-app + push to command staff. */
    public static function teamMessage(array $event, array $team, string $title, string $body)
    {
        $mid = (int) $event['municipality_id'];
        $msg = ($team['name'] ?? 'Ομάδα') . ': ' . mb_substr(trim($body), 0, 200);
        foreach (User::commandStaff($mid) as $u) {
            try {
                Notification::create([
                    'municipality_id' => $mid,
                    'user_id'         => $u['id'],
                    'event_id'        => (int) $event['id'],
                    'title'           => $title,
                    'message'         => $msg,
                    'type'            => 'ops_message',
                    'email_sent'      => 0,
                ]);
                self::sendPush($u['id'], $title, $msg);
            } catch (Throwable $e) {
                error_log('[Comms] team message failed: ' . $e->getMessage());
            }
        }
    }

    /** Shortage acknowledged/resolved by command — notify the reporting team. */
    public static function shortageHandled(array $shortage, array $event, string $action)
    {
        $verb  = $action === 'resolved' ? 'επιλύθηκε' : 'ελήφθη';
        $title = 'Η έλλειψη ' . $verb;
        $msg   = 'Η αναφορά έλλειψης «' . $shortage['title'] . '» ' . $verb
               . ' από τον δήμο (δράση «' . $event['title'] . '»).';
        foreach (User::teamAdmins((int) $shortage['team_id']) as $u) {
            try {
                Notification::create([
                    'municipality_id' => (int) $event['municipality_id'],
                    'user_id'         => $u['id'],
                    'team_id'         => (int) $shortage['team_id'],
                    'event_id'        => (int) $event['id'],
                    'title'           => $title,
                    'message'         => $msg,
                    'type'            => 'shortage_update',
                    'email_sent'      => 0,
                ]);
                self::sendPush($u['id'], $title, $msg);
            } catch (Throwable $e) {
                error_log('[Comms] shortage update failed: ' . $e->getMessage());
            }
        }
    }

    /** Build an absolute URL (scheme+host) for links sent off-site, e.g. email. */
    private static function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . url($path);
    }
}
