<?php
/**
 * SynDrasi - Shared accept/decline handling for resource dispatch requests.
 * Both response surfaces (team portal, no-login field link) funnel here after
 * doing their own authentication and ownership checks; this class owns the
 * input validation, the pending→accepted/declined transition, notifications
 * to command staff, auditing, and the JSON response.
 */
class ResourceRequestResponder
{
    private const ETA_MIN = 1;
    private const ETA_MAX = 1440;

    /**
     * @param array  $rr          The resource_requests row (already ownership-checked by the caller)
     * @param array  $input       Decoded JSON body: action ('accept'|'decline'), note?, eta_minutes?
     * @param array  $event       Event-like array (id, title, municipality_id at minimum)
     * @param array  $team        Team-like array (name at minimum)
     * @param string $auditSuffix Extra audit context, e.g. ' (field)'
     */
    public static function respond(array $rr, array $input, array $event, array $team, string $auditSuffix = ''): never
    {
        $action = (string) ($input['action'] ?? '');
        if (!in_array($action, ['accept', 'decline'], true)) {
            json_out(['success' => false, 'message' => 'Μη έγκυρη ενέργεια.'], 422);
        }
        $note = trim((string) ($input['note'] ?? '')) ?: null;
        $eta  = null;
        if ($action === 'accept' && isset($input['eta_minutes'])) {
            $eta = (int) $input['eta_minutes'];
            if ($eta < self::ETA_MIN || $eta > self::ETA_MAX) { $eta = null; }
        }

        $status = $action === 'accept' ? 'accepted' : 'declined';
        if (!ResourceRequest::respond((int) $rr['id'], $status, $note, $eta)) {
            json_out(['success' => false, 'message' => 'Το αίτημα δεν είναι πλέον σε εκκρεμότητα.'], 409);
        }
        Event::touchActivity((int) $rr['event_id']);

        try {
            $status === 'accepted'
                ? NotificationService::resourceAccepted($event, $team, (string) $rr['item_label'], $eta)
                : NotificationService::resourceDeclined($event, $team, (string) $rr['item_label'], $note);
        } catch (Throwable $e) { error_log('[resourceRespond] ' . $e->getMessage()); }

        audit('resource_' . $status, 'event', (int) $rr['event_id'], 'request ' . (int) $rr['id'] . $auditSuffix);
        json_out(['success' => true, 'message' => $status === 'accepted' ? 'Καταγράφηκε η αποδοχή.' : 'Καταγράφηκε η αδυναμία.']);
    }
}
