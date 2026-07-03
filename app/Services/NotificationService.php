<?php
/**
 * SynDrasi - Notification service.
 * Creates in-app notifications and sends matching emails via EmailTemplate.
 */
class NotificationService
{
    private static function operationalChannelTypes(): array
    {
        return [
            'photo_request',
            'video_request',
            'gps_request',
            'photo_uploaded',
            'video_uploaded',
            'gps_arrived',
            'ops_message',
            'ops_geo',
            'team_silent',
            'shortage_update',
            'sos_ack',
        ];
    }

    private static function eventTerms($municipalityId): array
    {
        $terms = authority_context((int) $municipalityId);
        $singular = $terms['event_singular'] ?? 'Δράση';
        $singularLc = mb_strtolower($singular, 'UTF-8');

        return [
            'singular' => $singular,
            'singular_lc' => $singularLc,
            'plural' => $terms['event_plural'] ?? 'Δράσεις',
            'plural_lc' => $terms['event_plural_lc'] ?? 'δράσεις',
            'org_short' => $terms['short_name'] ?? $terms['short'] ?? 'Φορέας',
        ];
    }

    private static function defaultChannelFor(string $type): string
    {
        return in_array($type, self::operationalChannelTypes(), true) ? 'off' : 'email';
    }

    /**
     * Delivery channel for a notification type: 'off' | 'email' | 'sms' | 'both'.
     * Reads notify_channel_<type>; falls back to the legacy notify_email_<type>
     * toggle. Operational alerts default to off for Email/SMS; core workflow
     * notifications default to email.
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
        if ($legacy !== null) {
            return $legacy === '0' ? 'off' : 'email';
        }
        return self::defaultChannelFor((string) $type);
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

    /** Returns true if Telegram should be sent for this notification type. */
    public static function shouldSendTelegram($municipalityId, $type)
    {
        if (!$municipalityId) { return false; }
        return MunicipalitySetting::get($municipalityId, 'notify_telegram_' . $type, '0') === '1';
    }

    /** Send an email if this notification type's channel includes email. */
    private static function maybeEmail($municipalityId, $type, $email, $name, $subject, $body): bool
    {
        if (empty($email) || !self::shouldSendEmail($municipalityId, $type)) { return false; }
        try {
            MailService::sendDeferred($email, $name ?: '', $subject, $body, $municipalityId);
            return true;
        } catch (Throwable $e) {
            error_log('[Notify] email failed: ' . $e->getMessage());
            return false;
        }
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

    private static function maybeTelegramTeam($municipalityId, $type, $teamId, $title, $message, ?string $url = null, bool $forced = false): void
    {
        if (!$forced && !self::shouldSendTelegram($municipalityId, $type)) { return; }
        try {
            TelegramService::sendTeam((int) $teamId, $title, $message, $municipalityId, $url);
        } catch (Throwable $e) {
            error_log('[Notify] telegram team failed: ' . $e->getMessage());
        }
    }

    private static function maybeTelegramCommand($municipalityId, $type, $title, $message, ?string $url = null, bool $forced = false): void
    {
        if (!$forced && !self::shouldSendTelegram($municipalityId, $type)) { return; }
        try {
            TelegramService::sendCommand($municipalityId, $title, $message, $url);
        } catch (Throwable $e) {
            error_log('[Notify] telegram command failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify all team admins of a team.
     * $emailSubject / $emailBody override the in-app title/message for the email.
     */
    public static function notifyTeam($teamId, $eventId, $title, $message, $type, $municipalityId = null, $emailSubject = null, $emailBody = null, ?string $telegramUrl = null)
    {
        $admins = User::teamAdmins($teamId);
        foreach ($admins as $admin) {
            $eSubject = $emailSubject ?? $title;
            $eBody    = $emailBody    ?? $message;
            $shouldEmail = self::shouldSendEmail($municipalityId, $type);
            if ($shouldEmail) {
                MailService::sendDeferred($admin['email'], $admin['name'], $eSubject, $eBody, $municipalityId);
            }
            $sent = $shouldEmail;
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
        self::maybeTelegramTeam($municipalityId, $type, $teamId, $title, $message, $telegramUrl ?: self::absoluteUrl($eventId ? '/team/events/' . $eventId : '/team/dashboard'));
    }

    /**
     * Notify all municipality admins.
     * $emailSubject / $emailBody override the in-app title/message for the email.
     */
    public static function notifyMunicipality($municipalityId, $eventId, $title, $message, $type, $emailSubject = null, $emailBody = null, ?string $telegramUrl = null)
    {
        $admins = User::municipalityAdmins($municipalityId);
        foreach ($admins as $admin) {
            $eSubject = $emailSubject ?? $title;
            $eBody    = $emailBody    ?? $message;
            $shouldEmail = self::shouldSendEmail($municipalityId, $type);
            if ($shouldEmail) {
                MailService::sendDeferred($admin['email'], $admin['name'], $eSubject, $eBody, $municipalityId);
            }
            $sent = $shouldEmail;
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
        self::maybeTelegramCommand($municipalityId, $type, $title, $message, $telegramUrl ?: self::absoluteUrl($eventId ? '/events/' . $eventId : '/dashboard'));
    }

    private static function notifyCommandStaff($municipalityId, $eventId, $title, $message, $type, ?string $telegramUrl = null, string $urgency = 'normal'): void
    {
        foreach (User::commandStaff($municipalityId) as $u) {
            try {
                $emailSent = self::maybeEmail($municipalityId, $type, $u['email'] ?? '', $u['name'] ?? '', $title, $message);
                Notification::create([
                    'municipality_id' => $municipalityId,
                    'user_id'         => $u['id'],
                    'event_id'        => $eventId,
                    'title'           => $title,
                    'message'         => $message,
                    'type'            => $type,
                    'email_sent'      => $emailSent ? 1 : 0,
                ]);
                self::maybeSms($municipalityId, $type, $u['phone'] ?? '', $title, $message);
                self::sendPush($u['id'], $title, $message, $urgency);
            } catch (Throwable $e) {
                error_log('[Notify] command staff failed: ' . $e->getMessage());
            }
        }
        self::maybeTelegramCommand($municipalityId, $type, $title, $message, $telegramUrl ?: self::absoluteUrl($eventId ? '/operations/events/' . $eventId : '/dashboard'));
    }

    /**
     * Send a web push notification to all subscriptions of a user.
     * Silent failure — push is best-effort, never breaks the main flow.
     */
    /**
     * $urgency: 'very-high' for SOS/incident, 'high' for ops alerts, 'normal' for the rest.
     */
    private static function sendPush(int $userId, string $title, string $body, string $urgency = 'normal'): void
    {
        $user = null;
        try {
            $user = dbq(
                'SELECT id, municipality_id, team_id, name, email FROM users WHERE id = :uid LIMIT 1',
                ['uid' => $userId]
            )->fetch() ?: null;
            $subs = dbq('SELECT * FROM push_subscriptions WHERE user_id = :uid', ['uid' => $userId])->fetchAll();
            if (!$subs) {
                NotificationDelivery::record([
                    'municipality_id' => $user['municipality_id'] ?? null,
                    'channel' => 'push',
                    'status' => 'skipped',
                    'recipient_user_id' => $userId,
                    'team_id' => $user['team_id'] ?? null,
                    'recipient_label' => $user['name'] ?? null,
                    'recipient_address' => $user['email'] ?? null,
                    'title' => $title,
                    'message' => $body,
                    'error_msg' => 'Δεν υπάρχει ενεργή push συνδρομή για τον χρήστη.',
                ]);
                return;
            }
            $payload = ['title' => $title, 'body' => $body, 'url' => '/notifications'];
            $ok = 0;
            foreach ($subs as $sub) {
                if (WebPushService::send($sub, $payload, 86400, $urgency)) {
                    $ok++;
                }
            }
            NotificationDelivery::record([
                'municipality_id' => $user['municipality_id'] ?? null,
                'channel' => 'push',
                'status' => $ok > 0 ? 'sent' : 'failed',
                'recipient_user_id' => $userId,
                'team_id' => $user['team_id'] ?? null,
                'recipient_label' => $user['name'] ?? null,
                'recipient_address' => $user['email'] ?? null,
                'title' => $title,
                'message' => $body,
                'attempts' => count($subs),
                'error_msg' => $ok > 0 ? null : 'Απέτυχε η αποστολή σε όλες τις push συνδρομές.',
            ]);
        } catch (Throwable $e) {
            NotificationDelivery::record([
                'municipality_id' => $user['municipality_id'] ?? null,
                'channel' => 'push',
                'status' => 'failed',
                'recipient_user_id' => $userId,
                'team_id' => $user['team_id'] ?? null,
                'recipient_label' => $user['name'] ?? null,
                'recipient_address' => $user['email'] ?? null,
                'title' => $title,
                'message' => $body,
                'attempts' => 1,
                'error_msg' => $e->getMessage(),
            ]);
            error_log('[WebPush] sendPush error for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    /* ── Domain events ───────────────────────────────────────────────────── */

    /** Event published: notify all active teams of the municipality. */
    public static function eventPublished(array $event)
    {
        $teams = VolunteerTeam::forMunicipality($event['municipality_id'], true);
        $mid   = $event['municipality_id'];
        $et    = self::eventTerms($mid);

        // In-app notification (short)
        $inAppTitle   = 'Νέα ' . $et['singular_lc'] . ': ' . $event['title'];
        $inAppMessage = 'Δημοσιεύθηκε νέα ' . $et['singular_lc'] . '. Συνδεθείτε για να δηλώσετε συμμετοχή.';

        // Email body via template
        $tpl = EmailTemplate::resolve($mid, 'event_published', [
            'event_title'    => $event['title'],
            'event_category' => $event['category_name'] ?? '—',
            'event_date'     => gr_datetime($event['start_datetime']),
            'event_location' => $event['location_name'] ?: '—',
            'event_label'    => $et['singular'],
            'event_label_lc' => $et['singular_lc'],
            'org_short'      => $et['org_short'],
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
        $et  = self::eventTerms($mid);

        $inAppTitle   = 'Νέα δήλωση συμμετοχής: ' . $event['title'];
        $inAppMessage = 'Η ομάδα "' . $team['name'] . '" δήλωσε συμμετοχή με ' . (int) $offeredPeople . ' άτομα.';

        $tpl = EmailTemplate::resolve($mid, 'application_submitted', [
            'event_title'    => $event['title'],
            'event_label'    => $et['singular'],
            'event_label_lc' => $et['singular_lc'],
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
        $et  = self::eventTerms($mid);

        $inAppTitle   = 'Εγκρίθηκε η συμμετοχή σας στη ' . $et['singular_lc'];
        $inAppMessage = 'Η συμμετοχή της ομάδας εγκρίθηκε για τη ' . $et['singular_lc'] . ' "' . $event['title'] . '" με ' . (int) $application['approved_people'] . ' άτομα.';

        $tpl = EmailTemplate::resolve($mid, 'application_approved', [
            'event_title'     => $event['title'],
            'event_label'     => $et['singular'],
            'event_label_lc'  => $et['singular_lc'],
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
        $et  = self::eventTerms($mid);

        $inAppTitle   = 'Απάντηση στη δήλωση συμμετοχής σας';
        $inAppMessage = 'Η δήλωση συμμετοχής για τη ' . $et['singular_lc'] . ' "' . $event['title'] . '" δεν εγκρίθηκε.';

        $reasonLine = $reason !== '' ? "\nΣχόλιο " . $et['org_short'] . ": " . $reason . "\n" : '';
        $tpl = EmailTemplate::resolve($mid, 'application_rejected', [
            'event_title'      => $event['title'],
            'event_label'      => $et['singular'],
            'event_label_lc'   => $et['singular_lc'],
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
        $et  = self::eventTerms($mid);

        $inAppTitle   = 'Αναφορά έλλειψης: ' . $event['title'];
        $inAppMessage = 'Η ομάδα "' . $team['name'] . '" ανέφερε έλλειψη (' . $typeLabel . ', ' . $severityLabel . ').';

        $tpl = EmailTemplate::resolve($mid, 'shortage_reported', [
            'event_title'       => $event['title'],
            'event_label'       => $et['singular'],
            'event_label_lc'    => $et['singular_lc'],
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
        $et           = self::eventTerms($mid);

        $inAppTitle = 'Υπενθύμιση ' . $et['singular_lc'] . ': ' . $event['title'];

        foreach ($applications as $app) {
            $inAppMessage = 'Υπενθύμιση για τη ' . $et['singular_lc'] . ' "' . $event['title'] . '" στις ' . gr_datetime($event['start_datetime']) . '.';

            $tpl = EmailTemplate::resolve($mid, 'event_reminder', [
                'event_title'     => $event['title'],
                'event_label'     => $et['singular'],
                'event_label_lc'  => $et['singular_lc'],
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

    /** Event closed by admin: notify approved teams to fill the post-event debrief. */
    public static function eventClosed(array $event): void
    {
        $applications = EventApplication::approvedForEvent($event['id']);
        $mid          = $event['municipality_id'];
        $et           = self::eventTerms($mid);

        $inAppTitle   = 'Debrief ' . $et['singular_lc'] . ': ' . $event['title'];
        $inAppMessage = 'Η ' . $et['singular_lc'] . ' ολοκληρώθηκε. Συμπληρώστε το Post-Event Debrief της ομάδας σας.';

        $tpl = EmailTemplate::resolve($mid, 'event_closed', [
            'event_title' => $event['title'],
            'event_label' => $et['singular'],
            'event_label_lc' => $et['singular_lc'],
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

    /** Event completed/closed: notify approved teams to submit reports. */
    public static function eventCompleted(array $event)
    {
        $applications = EventApplication::approvedForEvent($event['id']);
        $mid          = $event['municipality_id'];
        $et           = self::eventTerms($mid);

        $inAppTitle   = 'Ολοκληρώθηκε η ' . $et['singular_lc'] . ': ' . $event['title'];
        $inAppMessage = 'Παρακαλούμε υποβάλετε τη σύντομη αναφορά της ομάδας σας.';

        $tpl = EmailTemplate::resolve($mid, 'event_completed', [
            'event_title' => $event['title'],
            'event_label' => $et['singular'],
            'event_label_lc' => $et['singular_lc'],
        ]);

        foreach ($applications as $app) {
            self::notifyTeam(
                $app['team_id'], $event['id'],
                $inAppTitle, $inAppMessage,
                'event_completed', $mid,
                $tpl['subject'], $tpl['body']
            );
        }

        self::maybeTelegramCommand(
            $mid,
            'event_completed',
            'Ολοκληρώθηκε ' . $et['singular_lc'],
            'Η ' . $et['singular_lc'] . ' "' . $event['title'] . '" μετακινήθηκε στις Ολοκληρωμένες.',
            self::absoluteUrl('/events/' . (int) $event['id'])
        );
    }

    // ── Shift Scheduling notifications ───────────────────────────────────────

    /** Shift application submitted: notify the municipality. */
    public static function shiftApplicationSubmitted(array $event, array $shift, array $team, $offeredPeople)
    {
        $mid = $event['municipality_id'];
        $et  = self::eventTerms($mid);
        $inAppTitle   = 'Δήλωση βάρδιας: ' . $event['title'];
        $inAppMessage = 'Η ομάδα "' . $team['name'] . '" δήλωσε βάρδια «' . $shift['name'] . '» με ' . (int) $offeredPeople . ' άτομα.';
        self::notifyMunicipality($mid, $event['id'], $inAppTitle, $inAppMessage, 'application_submitted');
    }

    /** Shift application approved: notify the team. */
    public static function shiftApplicationApproved(array $event, array $app, $approvedPeople)
    {
        $mid  = $event['municipality_id'];
        $et   = self::eventTerms($mid);
        $inAppTitle   = 'Εγκρίθηκε βάρδια: ' . $app['shift_name'];
        $inAppMessage = 'Η βάρδια «' . $app['shift_name'] . '» εγκρίθηκε για ' . (int) $approvedPeople . ' άτομα. ' . $et['singular'] . ': ' . $event['title'] . '.';
        self::notifyTeam($app['team_id'], $event['id'], $inAppTitle, $inAppMessage, 'application_approved', $mid);
    }

    /** Shift application rejected: notify the team. */
    public static function shiftApplicationRejected(array $event, array $app)
    {
        $mid = $event['municipality_id'];
        $et  = self::eventTerms($mid);
        $inAppTitle   = 'Απορρίφθηκε βάρδια: ' . $app['shift_name'];
        $inAppMessage = 'Η δήλωση βάρδιας «' . $app['shift_name'] . '» δεν εγκρίθηκε. ' . $et['singular'] . ': ' . $event['title'] . '.';
        self::notifyTeam($app['team_id'], $event['id'], $inAppTitle, $inAppMessage, 'application_rejected', $mid);
    }

    /** Shift reminder: sent 1 hour before a shift starts to all approved teams. */
    public static function shiftReminder(array $event, array $shift)
    {
        $mid  = $event['municipality_id'];
        $et   = self::eventTerms($mid);
        $apps = dbq(
            'SELECT * FROM shift_applications WHERE shift_id = :sid AND status = \'approved\'',
            ['sid' => $shift['id']]
        )->fetchAll();

        $inAppTitle = 'Υπενθύμιση βάρδιας: ' . $shift['name'];
        foreach ($apps as $app) {
            $msg = 'Υπενθύμιση: η βάρδια «' . $shift['name'] . '» ξεκινά στις ' . gr_time($shift['start_datetime']) . '. ' . $et['singular'] . ': ' . $event['title'] . '.';
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
        $et  = self::eventTerms($mid);

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
                ? "Ως Mission Υπεύθυνος, εσύ θα είσαι υπεύθυνος για την αποστολή στίγματος και ενημερώσεων κατά τη διάρκεια της " . $et['singular_lc'] . ".\n\n"
                : '';

            $tpl = EmailTemplate::resolve($mid, 'member_assigned', [
                'member_name'    => $member['full_name'],
                'event_title'    => $event['title'],
                'event_label'    => $et['singular'],
                'event_label_lc' => $et['singular_lc'],
                'event_start'    => gr_datetime($event['start_datetime']),
                'event_end'      => gr_datetime($event['end_datetime']),
                'event_location' => $event['location_name'] ?: ($event['address'] ?: '—'),
                'member_role'    => $role,
                'commander_note' => $commanderNote,
            ]);

            MailService::sendDeferred($member['email'], $member['full_name'], $tpl['subject'], $tpl['body'], $mid);
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
                    $pushOk = WebPushService::sendToUser($pushUserId, [
                        'title' => $title,
                        'body'  => $sev . ' — πατήστε για να απαντήσετε.',
                        'url'   => '/m/' . $t['token'],
                    ]);
                    $pushed = $pushOk > 0;
                    $pushUser = dbq(
                        'SELECT id, municipality_id, team_id, name, email FROM users WHERE id = :uid LIMIT 1',
                        ['uid' => $pushUserId]
                    )->fetch() ?: null;
                    NotificationDelivery::record([
                        'municipality_id' => $mid,
                        'channel' => 'push',
                        'status' => $pushOk > 0 ? 'sent' : 'skipped',
                        'recipient_user_id' => $pushUserId,
                        'team_id' => $pushUser['team_id'] ?? null,
                        'recipient_label' => $pushUser['name'] ?? ($t['full_name'] ?? null),
                        'recipient_address' => $pushUser['email'] ?? ($t['email'] ?? null),
                        'title' => $title,
                        'message' => $sev . ' — πατήστε για να απαντήσετε.',
                        'attempts' => 1,
                        'error_msg' => $pushOk > 0 ? null : 'Δεν υπάρχει ενεργή push συνδρομή ή απέτυχε η αποστολή.',
                    ]);
                } catch (Throwable $e) {
                    NotificationDelivery::record([
                        'municipality_id' => $mid,
                        'channel' => 'push',
                        'status' => 'failed',
                        'recipient_user_id' => $pushUserId,
                        'recipient_label' => $t['full_name'] ?? null,
                        'recipient_address' => $t['email'] ?? null,
                        'title' => $title,
                        'message' => $sev . ' — πατήστε για να απαντήσετε.',
                        'attempts' => 1,
                        'error_msg' => $e->getMessage(),
                    ]);
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
                    MailService::sendDeferred($t['email'], $t['full_name'] ?? '', $title, $body, $mid);
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
        $et = self::eventTerms((int) $event['municipality_id']);
        $title = 'Αίτημα φωτογραφίας';
        $msg   = $et['org_short'] . ' ζητά φωτογραφία για τη ' . $et['singular_lc'] . ' «' . $event['title'] . '».';
        self::notifyTeam((int) $team['id'], (int) $event['id'], $title, $msg, 'photo_request', (int) $event['municipality_id'], null, null, self::absoluteUrl('/team/operations/events/' . (int) $event['id']));
    }

    /** Commander asks a team for a live GPS fix — notify the team (in-app + push). */
    public static function gpsRequested(array $event, array $team)
    {
        $et = self::eventTerms((int) $event['municipality_id']);
        $title = 'Αίτημα στίγματος GPS';
        $msg   = $et['org_short'] . ' ζητά το στίγμα GPS σας για τη ' . $et['singular_lc'] . ' «' . $event['title'] . '».';
        self::notifyTeam((int) $team['id'], (int) $event['id'], $title, $msg, 'gps_request', (int) $event['municipality_id'], null, null, self::absoluteUrl('/team/operations/events/' . (int) $event['id']));
    }

    /** Team uploaded a photo — notify municipality admins (in-app + push). */
    public static function photoUploaded(array $event, int $teamId)
    {
        $et    = self::eventTerms((int) $event['municipality_id']);
        $team  = VolunteerTeam::find($teamId);
        $tname = $team['name'] ?? ('#' . $teamId);
        $title = 'Νέα φωτογραφία ομάδας';
        $msg   = 'Η ομάδα «' . $tname . '» έστειλε φωτογραφία για τη ' . $et['singular_lc'] . ' «' . $event['title'] . '».';
        self::notifyMunicipality((int) $event['municipality_id'], (int) $event['id'], $title, $msg, 'photo_uploaded', null, null, self::absoluteUrl('/operations/events/' . (int) $event['id']));
    }


    /** Commander asks a team for a short video — notify the team (in-app + push). */
    public static function videoRequested(array $event, array $team, ?string $instructions = null)
    {
        $et = self::eventTerms((int) $event['municipality_id']);
        $title = 'Αίτημα βίντεο';
        $msg   = $et['org_short'] . ' ζητά σύντομο βίντεο για τη ' . $et['singular_lc'] . ' «' . $event['title'] . '».'
               . ($instructions ? ' Οδηγίες: ' . $instructions : '');
        self::notifyTeam((int) $team['id'], (int) $event['id'], $title, $msg, 'video_request', (int) $event['municipality_id'], null, null, self::absoluteUrl('/team/operations/events/' . (int) $event['id']));
    }

    /** Command ζητά πόρο από ομάδα (Smart Resource Dispatch) — in-app + push. */
    public static function resourceRequested(array $event, array $team, string $item)
    {
        $et    = self::eventTerms((int) $event['municipality_id']);
        $title = 'Αίτημα πόρου';
        $msg   = $et['org_short'] . ' ζητά «' . $item . '» για τη ' . $et['singular_lc'] . ' «' . $event['title'] . '».';
        self::notifyTeam((int) $team['id'], (int) $event['id'], $title, $msg, 'resource_request', (int) $event['municipality_id'], null, null, self::absoluteUrl('/team/operations/events/' . (int) $event['id']));
    }

    /** Ομάδα αποδέχθηκε αίτημα πόρου (Φάση 2) — ενημέρωση προσωπικού διοίκησης. */
    public static function resourceAccepted(array $event, array $team, string $item, ?int $etaMinutes = null)
    {
        $et    = self::eventTerms((int) $event['municipality_id']);
        $title = 'Αποδοχή αιτήματος πόρου';
        $msg   = 'Η ομάδα «' . ($team['name'] ?? '') . '» αποδέχθηκε το αίτημα «' . $item . '» για τη '
               . $et['singular_lc'] . ' «' . $event['title'] . '».'
               . ($etaMinutes ? ' ETA: ' . (int) $etaMinutes . '′.' : '');
        self::notifyCommandStaff((int) $event['municipality_id'], (int) $event['id'], $title, $msg, 'resource_accepted', self::absoluteUrl('/operations/events/' . (int) $event['id']));
    }

    /** Ομάδα δήλωσε αδυναμία σε αίτημα πόρου (Φάση 2) — ενημέρωση προσωπικού διοίκησης. */
    public static function resourceDeclined(array $event, array $team, string $item, ?string $note = null)
    {
        $et    = self::eventTerms((int) $event['municipality_id']);
        $title = 'Αδυναμία διάθεσης πόρου';
        $msg   = 'Η ομάδα «' . ($team['name'] ?? '') . '» δεν μπορεί να διαθέσει «' . $item . '» για τη '
               . $et['singular_lc'] . ' «' . $event['title'] . '».'
               . ($note ? ' Σχόλιο: ' . $note : '');
        self::notifyCommandStaff((int) $event['municipality_id'], (int) $event['id'], $title, $msg, 'resource_declined', self::absoluteUrl('/operations/events/' . (int) $event['id']));
    }

    /** Team uploaded a video — notify municipality admins (in-app + push). */
    public static function videoUploaded(array $event, int $teamId)
    {
        $et    = self::eventTerms((int) $event['municipality_id']);
        $team  = VolunteerTeam::find($teamId);
        $tname = $team['name'] ?? ('#' . $teamId);
        $title = 'Νέο βίντεο ομάδας';
        $msg   = 'Η ομάδα «' . $tname . '» έστειλε βίντεο για τη ' . $et['singular_lc'] . ' «' . $event['title'] . '».';
        self::notifyMunicipality((int) $event['municipality_id'], (int) $event['id'], $title, $msg, 'video_uploaded', null, null, self::absoluteUrl('/operations/events/' . (int) $event['id']));
    }

    /* ── Active-event communications ─────────────────────────────────────── */

    /**
     * SOS / man-down raised by a team — FORCED push + SMS to all command staff
     * (municipality admins + operators), regardless of notification-channel settings.
     */
    public static function sosRaised(array $alert, array $event, array $team)
    {
        $mid     = (int) $event['municipality_id'];
        $et      = self::eventTerms($mid);
        $tname   = $team['name'] ?? 'Ομάδα';
        $title   = '🆘 SOS — ' . $tname;
        $hasGeo  = !empty($alert['latitude']) && !empty($alert['longitude']);
        $note    = !empty($alert['note']) ? ' Σημείωση: ' . $alert['note'] : '';
        $msg     = 'Η ομάδα «' . $tname . '» εξέπεμψε SOS στη ' . $et['singular_lc'] . ' «' . $event['title'] . '».' . $note;
        $link    = self::absoluteUrl('/operations/events/' . $event['id']);
        $geoTxt  = $hasGeo ? (' Θέση: ' . $alert['latitude'] . ',' . $alert['longitude'] . '.') : '';
        $sms     = 'SOS! ' . $tname . ' — ' . $event['title'] . '.' . $geoTxt . ' ' . $link;

        self::maybeTelegramCommand($mid, 'sos', $title, $msg . $geoTxt, $link, true);

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
                self::sendPush($u['id'], $title, $msg, 'very-high'); // forced — always, max urgency
                if (!empty($u['phone'])) {
                    SmsService::send($u['phone'], $sms, $mid);        // forced — always
                }
            } catch (Throwable $e) {
                error_log('[SOS] notify failed: ' . $e->getMessage());
            }
        }
    }

    /** Command acknowledged a team's SOS — close the loop back to the team. */
    public static function sosAcknowledged(array $alert, array $event)
    {
        $et = self::eventTerms((int) $event['municipality_id']);
        $title = 'Το SOS σας ελήφθη';
        $msg   = $et['org_short'] . ' έλαβε το SOS σας για τη ' . $et['singular_lc'] . ' «' . $event['title'] . '» και ανταποκρίνεται.';
        self::notifyTeam((int) $alert['team_id'], (int) $event['id'], $title, $msg, 'sos_ack', (int) $event['municipality_id'], null, null, self::absoluteUrl('/team/operations/events/' . (int) $event['id']));
    }

    /**
     * Command → team(s) message or order. $teamIds = list of team ids to deliver to
     * (a broadcast resolves to all approved teams before calling this).
     */
    public static function commandMessage(array $event, array $teamIds, string $kind, string $body)
    {
        $mid   = (int) $event['municipality_id'];
        $et    = self::eventTerms($mid);
        $title = $kind === 'order' ? 'Εντολή ' . $et['org_short'] : 'Μήνυμα ' . $et['org_short'];
        $msg   = mb_substr(trim($body), 0, 200);
        $forced = $kind === 'order';
        $link = self::absoluteUrl('/team/operations/events/' . (int) $event['id']);
        foreach (array_unique(array_map('intval', $teamIds)) as $tid) {
            self::maybeTelegramTeam($mid, 'ops_message', $tid, $title, $msg, $link, $forced);
            foreach (User::teamAdmins($tid) as $u) {
                try {
                    $emailSent = self::maybeEmail($mid, 'ops_message', $u['email'] ?? '', $u['name'] ?? '', $title, $msg);
                    Notification::create([
                        'municipality_id' => $mid,
                        'user_id'         => $u['id'],
                        'team_id'         => $tid,
                        'event_id'        => (int) $event['id'],
                        'title'           => $title,
                        'message'         => $msg,
                        'type'            => 'ops_message',
                        'email_sent'      => $emailSent ? 1 : 0,
                    ]);
                    self::maybeSms($mid, 'ops_message', $u['phone'] ?? '', $title, $msg);
                    self::sendPush($u['id'], $title, $msg);
                } catch (Throwable $e) {
                    error_log('[Comms] command message failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Command → team(s) GEO point (move / incident / poi). For 'incident' the
     * delivery is FORCED push + SMS (to team admins with a phone and the team's
     * mission commander), including a Google Maps link.
     */
    public static function commandGeoMessage(array $event, array $teamIds, string $pointKind, string $body, $lat, $lng)
    {
        $mid    = (int) $event['municipality_id'];
        $et     = self::eventTerms($mid);
        $titles = ['move' => 'Εντολή μετάβασης', 'incident' => '⚠️ Περιστατικό', 'poi' => 'Σημείο ' . $et['org_short']];
        $title  = $titles[$pointKind] ?? ('Σημείο ' . $et['org_short']);
        $msg    = mb_substr(trim($body), 0, 200);
        $maps   = ($lat !== null && $lng !== null) ? ('https://www.google.com/maps?q=' . $lat . ',' . $lng) : '';
        $forced = in_array($pointKind, ['incident', 'move'], true);
        $sms    = $title . ': ' . $msg . ($maps ? ' ' . $maps : '');
        $link   = self::absoluteUrl('/team/operations/events/' . (int) $event['id']);

        foreach (array_unique(array_map('intval', $teamIds)) as $tid) {
            self::maybeTelegramTeam($mid, $pointKind === 'incident' ? 'ops_incident' : 'ops_geo', $tid, $title, $msg . ($maps ? ' ' . $maps : ''), $link, $forced);
            foreach (User::teamAdmins($tid) as $u) {
                try {
                    $emailSent = $pointKind === 'incident' ? false : self::maybeEmail($mid, 'ops_geo', $u['email'] ?? '', $u['name'] ?? '', $title, $msg . ($maps ? ' ' . $maps : ''));
                    Notification::create([
                        'municipality_id' => $mid,
                        'user_id'         => $u['id'],
                        'team_id'         => $tid,
                        'event_id'        => (int) $event['id'],
                        'title'           => $title,
                        'message'         => $msg,
                        'type'            => $pointKind === 'incident' ? 'ops_incident' : 'ops_geo',
                        'email_sent'      => $emailSent ? 1 : 0,
                    ]);
                    self::sendPush($u['id'], $title, $msg, $forced ? 'very-high' : 'normal');
                    if (!$forced) {
                        self::maybeSms($mid, 'ops_geo', $u['phone'] ?? '', $title, $msg . ($maps ? ' ' . $maps : ''));
                    }
                    if ($forced && !empty($u['phone'])) {
                        SmsService::send($u['phone'], $sms, $mid);
                    }
                } catch (Throwable $e) {
                    error_log('[Geo] team admin notify failed: ' . $e->getMessage());
                }
            }
            // Forced: also SMS the mission commander operating in the field
            if ($forced) {
                try {
                    $cmd = dbq(
                        "SELECT tm.phone FROM event_applications ea
                         JOIN team_members tm ON tm.id = ea.mission_commander_id
                         WHERE ea.event_id = :eid AND ea.team_id = :tid AND ea.status = 'approved' LIMIT 1",
                        ['eid' => $event['id'], 'tid' => $tid]
                    )->fetch();
                    if ($cmd && !empty($cmd['phone'])) {
                        SmsService::send($cmd['phone'], $sms, $mid);
                    }
                } catch (Throwable $e) {
                    error_log('[Geo] commander SMS failed: ' . $e->getMessage());
                }
            }
        }
    }

    /** Team → command message / status ping — in-app + push to command staff. */
    public static function teamMessage(array $event, array $team, string $title, string $body)
    {
        $mid = (int) $event['municipality_id'];
        $msg = ($team['name'] ?? 'Ομάδα') . ': ' . mb_substr(trim($body), 0, 200);
        self::notifyCommandStaff($mid, (int) $event['id'], $title, $msg, 'ops_message', self::absoluteUrl('/operations/events/' . (int) $event['id']));
    }

    /** Shortage acknowledged/resolved by command — notify the reporting team. */
    public static function shortageHandled(array $shortage, array $event, string $action)
    {
        $et    = self::eventTerms((int) $event['municipality_id']);
        $verb  = $action === 'resolved' ? 'επιλύθηκε' : 'ελήφθη';
        $title = 'Η έλλειψη ' . $verb;
        $msg   = 'Η αναφορά έλλειψης «' . $shortage['title'] . '» ' . $verb
               . ' από ' . $et['org_short'] . ' (' . $et['singular_lc'] . ' «' . $event['title'] . '»).';
        self::notifyTeam((int) $shortage['team_id'], (int) $event['id'], $title, $msg, 'shortage_update', (int) $event['municipality_id'], null, null, self::absoluteUrl('/team/operations/events/' . (int) $event['id']));
    }

    /**
     * GPS fix arrived: a team fulfilled a GPS request — notify command staff (in-app + push).
     * Only called when there was a pending GPS request at the time the fix was received.
     */
    public static function gpsArrived(array $event, array $team, float $lat, float $lng): void
    {
        $mid   = (int) $event['municipality_id'];
        $et    = self::eventTerms($mid);
        $tname = $team['name'] ?? 'Ομάδα';
        $maps  = 'https://www.google.com/maps?q=' . round($lat, 6) . ',' . round($lng, 6);
        $title = '📍 Στίγμα ελήφθη: ' . $tname;
        $msg   = 'Η ομάδα «' . $tname . '» έστειλε GPS για τη ' . $et['singular_lc'] . ' «' . $event['title'] . '». ' . $maps;
        self::notifyCommandStaff($mid, (int) $event['id'], $title, $msg, 'gps_arrived', self::absoluteUrl('/operations/events/' . (int) $event['id']), 'high');
    }

    /**
     * Silent team: a team hasn't sent a GPS ping for $minutesSilent+ minutes — warn command.
     * Deduplication is handled by the caller (OperationController::checkSilentTeams).
     */
    public static function silentTeam(array $event, array $team, int $minutesSilent): void
    {
        $mid   = (int) $event['municipality_id'];
        $et    = self::eventTerms($mid);
        $tname = $team['name'] ?? 'Ομάδα';
        $title = '⚠ Ομάδα σε σίγη: ' . $tname;
        $msg   = 'Η ομάδα «' . $tname . '» δεν έχει στείλει στίγμα για ' . $minutesSilent
               . ' λεπτά (' . $et['singular_lc'] . ' «' . $event['title'] . '»).';
        foreach (User::commandStaff($mid) as $u) {
            try {
                $emailSent = self::maybeEmail($mid, 'team_silent', $u['email'] ?? '', $u['name'] ?? '', $title, $msg);
                Notification::create([
                    'municipality_id' => $mid,
                    'user_id'         => $u['id'],
                    'event_id'        => (int) $event['id'],
                    'team_id'         => (int) $team['id'],
                    'title'           => $title,
                    'message'         => $msg,
                    'type'            => 'team_silent',
                    'email_sent'      => $emailSent ? 1 : 0,
                ]);
                self::maybeSms($mid, 'team_silent', $u['phone'] ?? '', $title, $msg);
                self::sendPush($u['id'], $title, $msg, 'high');
            } catch (Throwable $e) {
                error_log('[Silent] notify failed: ' . $e->getMessage());
            }
        }
        self::maybeTelegramCommand($mid, 'team_silent', $title, $msg, self::absoluteUrl('/operations/events/' . (int) $event['id']));
    }

    /** Build an absolute URL (scheme+host) for links sent off-site, e.g. email. */
    private static function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . url($path);
    }
}
