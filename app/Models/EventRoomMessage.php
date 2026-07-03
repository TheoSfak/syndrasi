<?php
/**
 * SynDrasi - Shared operations room chat (event-wide channel).
 *
 * Separate from EventMessage (private per-team threads). Everyone on the active
 * event — command staff, all approved teams, and field commanders — posts here
 * and everyone sees it. Methods are defensive: if migration 015 hasn't run yet
 * they no-op (return 0 / []) so nothing breaks.
 */
class EventRoomMessage
{
    public static function create(array $d): int
    {
        try {
            dbq(
                'INSERT INTO event_room_messages
                   (municipality_id, event_id, sender_role, sender_user_id, sender_team_id, sender_label, body)
                 VALUES (:mid, :eid, :role, :uid, :tid, :label, :body)',
                [
                    'mid'   => $d['mid'], 'eid' => $d['eid'], 'role' => $d['role'],
                    'uid'   => $d['uid'] ?? null, 'tid' => $d['tid'] ?? null,
                    'label' => $d['label'] ?? null, 'body' => $d['body'],
                ]
            );
            $id = (int) db()->lastInsertId();
            Event::touchActivity((int) $d['eid']);
            return $id;
        } catch (Throwable $e) {
            error_log('[Room] create failed (migration 015?): ' . $e->getMessage());
            return 0;
        }
    }

    /** All room messages for an event, oldest first. $since for polling. */
    public static function forEvent(int $eid, int $sinceId = 0): array
    {
        try {
            return dbq(
                "SELECT r.id, r.sender_role, r.sender_team_id, r.sender_label, r.body, r.created_at,
                        t.name AS team_name, u.name AS sender_name
                 FROM event_room_messages r
                 LEFT JOIN volunteer_teams t ON t.id = r.sender_team_id
                 LEFT JOIN users u ON u.id = r.sender_user_id
                 WHERE r.event_id = :eid AND r.id > :since
                 ORDER BY r.id ASC",
                ['eid' => $eid, 'since' => $sinceId]
            )->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}
