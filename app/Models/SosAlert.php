<?php
/**
 * SynDrasi - SOS / man-down alert raised by a team during an active event.
 */
class SosAlert
{
    public static function create(array $d): int
    {
        dbq(
            'INSERT INTO sos_alerts
               (municipality_id, event_id, team_id, user_id, latitude, longitude, accuracy, note)
             VALUES
               (:mid, :eid, :tid, :uid, :lat, :lng, :acc, :note)',
            $d
        );
        return (int) db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $row = dbq('SELECT * FROM sos_alerts WHERE id = :id LIMIT 1', ['id' => $id])->fetch();
        return $row ?: null;
    }

    /** Active (unresolved) alerts for an event, newest first, with team + ack names. */
    public static function activeForEvent(int $eid): array
    {
        return dbq(
            "SELECT s.*, t.name AS team_name, u.name AS raiser_name, ua.name AS ack_name,
                    TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) AS age_min
             FROM sos_alerts s
             JOIN volunteer_teams t ON t.id = s.team_id
             LEFT JOIN users u  ON u.id  = s.user_id
             LEFT JOIN users ua ON ua.id = s.acknowledged_by
             WHERE s.event_id = :eid AND s.status <> 'resolved'
             ORDER BY FIELD(s.status,'active','acknowledged'), s.created_at DESC",
            ['eid' => $eid]
        )->fetchAll();
    }

    /** The team's own latest non-resolved alert for an event (for the team hub). */
    public static function latestForTeamEvent(int $eid, int $tid): ?array
    {
        $row = dbq(
            "SELECT s.*, ua.name AS ack_name
             FROM sos_alerts s
             LEFT JOIN users ua ON ua.id = s.acknowledged_by
             WHERE s.event_id = :eid AND s.team_id = :tid AND s.status <> 'resolved'
             ORDER BY s.created_at DESC LIMIT 1",
            ['eid' => $eid, 'tid' => $tid]
        )->fetch();
        return $row ?: null;
    }

    public static function acknowledge(int $id, int $userId): void
    {
        dbq(
            "UPDATE sos_alerts
             SET status = 'acknowledged', acknowledged_by = :uid, acknowledged_at = NOW()
             WHERE id = :id AND status = 'active'",
            ['uid' => $userId, 'id' => $id]
        );
    }

    public static function resolve(int $id, int $userId): void
    {
        dbq(
            "UPDATE sos_alerts
             SET status = 'resolved', resolved_by = :uid, resolved_at = NOW()
             WHERE id = :id AND status <> 'resolved'",
            ['uid' => $userId, 'id' => $id]
        );
    }
}
