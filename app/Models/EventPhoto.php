<?php
/**
 * SynDrasi - Geotagged photo uploaded by a team for an event.
 */
class EventPhoto
{
    public const DIR = '/storage/uploads/event_photos';

    public static function create(array $d): int
    {
        dbq(
            'INSERT INTO event_photos
               (municipality_id, event_id, team_id, user_id, request_id, file_name, latitude, longitude, caption)
             VALUES
               (:mid, :eid, :tid, :uid, :rid, :file, :lat, :lng, :caption)',
            $d
        );
        $id = (int) db()->lastInsertId();
        Event::touchActivity((int) $d['eid']);
        return $id;
    }

    public static function find(int $id): ?array
    {
        $row = dbq('SELECT * FROM event_photos WHERE id = :id LIMIT 1', ['id' => $id])->fetch();
        return $row ?: null;
    }

    /** All photos for an event, newest first, with team name. */
    public static function forEvent(int $eid): array
    {
        return dbq(
            "SELECT p.id, p.team_id, p.latitude, p.longitude, p.caption, p.created_at,
                    t.name AS team_name,
                    TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) AS age_min
             FROM event_photos p
             JOIN volunteer_teams t ON t.id = p.team_id
             WHERE p.event_id = :eid
             ORDER BY p.created_at DESC",
            ['eid' => $eid]
        )->fetchAll();
    }

    /** A team's own photos for an event (for the team page). */
    public static function forTeamEvent(int $eid, int $tid): array
    {
        return dbq(
            "SELECT id, file_name, latitude, longitude, created_at
             FROM event_photos
             WHERE event_id = :eid AND team_id = :tid
             ORDER BY created_at DESC LIMIT 20",
            ['eid' => $eid, 'tid' => $tid]
        )->fetchAll();
    }

    /** Absolute path to the stored file, validated, or null. */
    public static function path(array $photo): ?string
    {
        $name = basename((string) $photo['file_name']);
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return null;
        }
        $full = BASE_PATH . self::DIR . '/' . $name;
        return is_file($full) ? $full : null;
    }
}
