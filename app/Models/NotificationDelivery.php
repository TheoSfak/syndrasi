<?php
/**
 * SynDrasi - Unified outgoing notification delivery log.
 */
class NotificationDelivery
{
    public static function record(array $data): ?int
    {
        $defaults = [
            'municipality_id' => null,
            'channel' => 'email',
            'status' => 'queued',
            'recipient_user_id' => null,
            'team_id' => null,
            'event_id' => null,
            'recipient_label' => null,
            'recipient_address' => null,
            'title' => '',
            'message' => null,
            'type' => null,
            'external_ref' => null,
            'attempts' => 0,
            'error_msg' => null,
            'sent_at' => null,
        ];
        $d = array_merge($defaults, $data);
        if ($d['sent_at'] === null && $d['status'] === 'sent') {
            $d['sent_at'] = date('Y-m-d H:i:s');
        }

        try {
            dbq(
                'INSERT INTO notification_deliveries
                 (municipality_id, channel, status, recipient_user_id, team_id, event_id,
                  recipient_label, recipient_address, title, message, type, external_ref,
                  attempts, error_msg, sent_at)
                 VALUES
                 (:municipality_id, :channel, :status, :recipient_user_id, :team_id, :event_id,
                  :recipient_label, :recipient_address, :title, :message, :type, :external_ref,
                  :attempts, :error_msg, :sent_at)',
                $d
            );
            return (int) db()->lastInsertId();
        } catch (Throwable $e) {
            error_log('[NotificationDelivery::record] ' . $e->getMessage());
            return null;
        }
    }

    public static function markExternalRef(string $externalRef, string $status, int $attempts = 0, ?string $error = null): void
    {
        try {
            dbq(
                "UPDATE notification_deliveries
                 SET status = :status,
                     attempts = :attempts,
                     error_msg = :error,
                     sent_at = CASE WHEN :sent_status = 'sent' THEN NOW() ELSE sent_at END
                 WHERE external_ref = :ref",
                [
                    'status' => $status,
                    'attempts' => max(0, $attempts),
                    'error' => $error,
                    'sent_status' => $status,
                    'ref' => $externalRef,
                ]
            );
        } catch (Throwable $e) {
            error_log('[NotificationDelivery::markExternalRef] ' . $e->getMessage());
        }
    }
}
