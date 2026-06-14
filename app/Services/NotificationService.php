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
            'event_tit