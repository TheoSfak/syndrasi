<?php
/**
 * SynDrasi - GPS location request (admin → team) for an event.
 * Mirrors PhotoRequest: the commander asks a team for a live GPS fix;
 * the next location ping from that team fulfils the request.
 */
class GpsRequest
{
    /** Create a pending request (reuses an existing pending one if present). */
    public static function create(int $mid, int $eid, int $tid, ?int $byUser): int
    {
        $existing = self::pendingForEventTeam($eid, $tid);
        if ($existing) {
            return (int) $existing['id'];
        }
        dbq(
            'INSERT INTO gps_requests (municipality_id, event_id, team_id, requested_by)
             VALUES (:mid, :eid, :tid, :by)',
            ['mid' => $mid, 'eid' => $eid, 'tid' => $tid, 'by' => $byUser]
        );
        return (int) db()->lastInsertId();
    }

    public static function pendingForEventTeam(int $eid, int $tid): ?array
    {
        $row = dbq(
            "SELECT * FROM gps_requests
             WHERE event_id = :eid AND team_id = :tid AND status = 'pending'
             ORDER BY id DESC LIMIT 1",
            ['eid' => $eid, 'tid' => $tid]
        )->fetch();
        return $row ?: null;
    }

    /** Team IDs with a pending request for an event (for the admin UI). */
    public static function pendingTeamIds(int $eid): array
    {
        return dbq(
            "SELECT DISTINCT team_id FROM gps_requests WHERE event_id = :eid AND status = 'pending'",
            ['eid' => $eid]
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** Mark every pending request for a team/event as fulfilled. */
    public static function fulfillForEventTeam(int $eid, int $tid): void
    {
        dbq(
            "UPDATE gps_requests SET status = 'fulfilled', fulfilled_at = NOW()
             WHERE event_id = :eid AND team_id = :tid AND status = 'pending'",
            ['eid' => $eid, 'tid' => $tid]
        );
    }

    public static function fulfill(int $id): void
    {
        dbq(
            "UPDATE gps_requests SET status = 'fulfilled', fulfilled_at = NOW()
             WHERE id = :id AND status = 'pending'",
            ['id' => $id]
        );
    }
}
