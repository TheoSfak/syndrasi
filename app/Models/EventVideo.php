<?php
/**
 * SynDrasi - Geotagged short video uploaded by a team for an event.
 * Mirrors EventPhoto. Stored files live in storage/uploads/event_videos and
 * are auto-purged after 7 days (see MaintenanceService::cleanup()).
 */
class EventVideo
{
    public const DIR = '/storage/uploads/event_videos';

    /** How long uploaded clips are kept before auto-purge. */
    public const RETENTION_DAYS = 7;

    public static function create(array $d): int
    {
        dbq(
            'INSERT INTO event_videos
               (municipality_id, event_id, team_id, user_id, request_id, file_name, mime, duration_sec, size_bytes, latitude, longitude, caption)
             VALUES
               (:mid, :eid, :tid, :uid, :rid, :file, :mime, :dur, :size, :lat, :lng, :caption)',
            $d
        );
        return (int) db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $row = dbq('SELECT * FROM event_videos WHERE id = :id LIMIT 1', ['id' => $id])->fetch();
        return $row ?: null;
    }

    /** All videos for an event, newest first, with team name + days left before purge. */
    public static function forEvent(int $eid): array
    {
        return dbq(
            "SELECT v.id, v.team_id, v.mime, v.duration_sec, v.size_bytes, v.latitude, v.longitude,
                    v.caption, v.created_at,
                    t.name AS team_name,
                    TIMESTAMPDIFF(MINUTE, v.created_at, NOW()) AS age_min,
                    GREATEST(0, " . self::RETENTION_DAYS . " - TIMESTAMPDIFF(DAY, v.created_at, NOW())) AS days_left
             FROM event_videos v
             JOIN volunteer_teams t ON t.id = v.team_id
             WHERE v.event_id = :eid
             ORDER BY v.created_at DESC",
            ['eid' => $eid]
        )->fetchAll();
    }

    /** A team's own videos for an event (for the team page). */
    public static function forTeamEvent(int $eid, int $tid): array
    {
        return dbq(
            "SELECT id, file_name, mime, duration_sec, latitude, longitude, created_at
             FROM event_videos
             WHERE event_id = :eid AND team_id = :tid
             ORDER BY created_at DESC LIMIT 20",
            ['eid' => $eid, 'tid' => $tid]
        )->fetchAll();
    }

    /** Flag every video of an event as kept (excluded from auto-purge). */
    public static function markKeptForEvent(int $eid): void
    {
        // Defensive: if migration 021 (kept column) hasn't run yet, don't break the page.
        try {
            dbq('UPDATE event_videos SET kept = 1 WHERE event_id = :eid', ['eid' => $eid]);
        } catch (Throwable $e) {
            error_log('[markKeptForEvent] ' . $e->getMessage());
        }
    }

    /** Delete a video: remove the stored file and the DB row. */
    public static function delete(int $id): bool
    {
        $video = self::find($id);
        if (!$video) { return false; }
        $path = self::path($video);
        if ($path !== null) { @unlink($path); }
        dbq('DELETE FROM event_videos WHERE id = :id', ['id' => $id]);
        return true;
    }

    /** Absolute path to the stored file, validated, or null. */
    public static function path(array $video): ?string
    {
        $name = basename((string) $video['file_name']);
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            return null;
        }
        $full = BASE_PATH . self::DIR . '/' . $name;
        return is_file($full) ? $full : null;
    }
}
