<?php
/**
 * SynDrasi - Video request (admin → team) for an event.
 * Mirrors PhotoRequest, plus instructions, a max duration and an optional
 * batch_id that ties together a single broadcast to several teams.
 */
class VideoRequest
{
    /** Create a pending request (reuses an existing pending one if present). */
    public static function create(int $mid, int $eid, int $tid, ?int $byUser, ?string $instructions = null, int $maxSeconds = 40, ?string $batchId = null): int
    {
        $existing = self::pendingForEventTeam($eid, $tid);
        if ($existing) {
            // Refresh the instructions/duration on the live request so the field
            // device shows the latest ask.
            dbq(
                'UPDATE video_requests
                    SET instructions = :ins, max_seconds = :max, batch_id = :batch, requested_by = :by
                  WHERE id = :id',
                ['ins' => $instructions, 'max' => $maxSeconds, 'batch' => $batchId, 'by' => $byUser, 'id' => (int) $existing['id']]
            );
            return (int) $existing['id'];
        }
        dbq(
            'INSERT INTO video_requests (municipality_id, event_id, team_id, requested_by, instructions, max_seconds, batch_id)
             VALUES (:mid, :eid, :tid, :by, :ins, :max, :batch)',
            ['mid' => $mid, 'eid' => $eid, 'tid' => $tid, 'by' => $byUser, 'ins' => $instructions, 'max' => $maxSeconds, 'batch' => $batchId]
        );
        return (int) db()->lastInsertId();
    }

    public static function pendingForEventTeam(int $eid, int $tid): ?array
    {
        $row = dbq(
            "SELECT * FROM video_requests
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
            "SELECT DISTINCT team_id FROM video_requests WHERE event_id = :eid AND status = 'pending'",
            ['eid' => $eid]
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public static function fulfill(int $id): void
    {
        dbq(
            "UPDATE video_requests SET status = 'fulfilled', fulfilled_at = NOW()
             WHERE id = :id AND status = 'pending'",
            ['id' => $id]
        );
    }

    /** Mark every pending request for a team/event as fulfilled (e.g. on any video upload). */
    public static function fulfillForEventTeam(int $eid, int $tid): void
    {
        dbq(
            "UPDATE video_requests SET status = 'fulfilled', fulfilled_at = NOW()
             WHERE event_id = :eid AND team_id = :tid AND status = 'pending'",
            ['eid' => $eid, 'tid' => $tid]
        );
    }
}
