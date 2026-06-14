<?php
/**
 * SynDrasi - EventShift model.
 * Handles time-slot (shift) scheduling within events.
 */
class EventShift
{
    /** All shifts for an event, ordered chronologically. */
    public static function forEvent($eventId): array
    {
        return dbq(
            'SELECT * FROM event_shifts WHERE event_id = :eid ORDER BY start_datetime',
            ['eid' => $eventId]
        )->fetchAll();
    }

    /** Find one shift (with municipality check via event). */
    public static function findForCurrent($id): array
    {
        $shift = dbq('SELECT * FROM event_shifts WHERE id = :id LIMIT 1', ['id' => $id])->fetch();
        if (!$shift) { abort(404, 'Η βάρδια δεν βρέθηκε.'); }
        requireMunicipalityAccess($shift['municipality_id']);
        return $shift;
    }

    /** Create a new shift. Returns new ID. */
    public static function create(array $data): int
    {
        dbq(
            'INSERT INTO event_shifts (event_id, municipality_id, name, start_datetime, end_datetime, required_people, notes)
             VALUES (:eid, :mid, :name, :start, :end, :req, :notes)',
            [
                'eid'   => $data['event_id'],
                'mid'   => $data['municipality_id'],
                'name'  => $data['name'],
                'start' => $data['start_datetime'],
                'end'   => $data['end_datetime'],
                'req'   => $data['required_people'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]
        );
        return (int) db()->lastInsertId();
    }

    /** Update a shift. */
    public static function update($id, array $data): void
    {
        dbq(
            'UPDATE event_shifts SET name=:name, start_datetime=:start, end_datetime=:end,
             required_people=:req, notes=:notes WHERE id=:id',
            [
                'name'  => $data['name'],
                'start' => $data['start_datetime'],
                'end'   => $data['end_datetime'],
                'req'   => $data['required_people'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'id'    => $id,
            ]
        );
    }

    /** Delete a shift. */
    public static function delete($id): void
    {
        dbq('DELETE FROM event_shifts WHERE id = :id', ['id' => $id]);
    }

    // ── Shift Applications ────────────────────────────────────────────────────

    /** All shift applications for an event, grouped with shift + team info. */
    public static function applicationsForEvent($eventId): array
    {
        return dbq(
            'SELECT sa.*, es.name AS shift_name, es.start_datetime AS shift_start, es.end_datetime AS shift_end,
                    t.name AS team_name
             FROM shift_applications sa
             JOIN event_shifts es ON es.id = sa.shift_id
             JOIN volunteer_teams t ON t.id = sa.team_id
             WHERE sa.event_id = :eid
             ORDER BY es.start_datetime, t.name',
            ['eid' => $eventId]
        )->fetchAll();
    }

    /** All shift applications for a team on an event. */
    public static function applicationsForTeam($eventId, $teamId): array
    {
        return dbq(
            'SELECT sa.*, es.name AS shift_name, es.start_datetime AS shift_start, es.end_datetime AS shift_end
             FROM shift_applications sa
             JOIN event_shifts es ON es.id = sa.shift_id
             WHERE sa.event_id = :eid AND sa.team_id = :tid
             ORDER BY es.start_datetime',
            ['eid' => $eventId, 'tid' => $teamId]
        )->fetchAll();
    }

    /** Apply a team to a shift. Returns new application ID. */
    public static function applyTeam(array $data): int
    {
        dbq(
            'INSERT INTO shift_applications (shift_id, event_id, team_id, municipality_id, offered_people, notes)
             VALUES (:sid, :eid, :tid, :mid, :offered, :notes)
             ON DUPLICATE KEY UPDATE offered_people=:offered, notes=:notes, status=\'pending\', updated_at=NOW()',
            [
                'sid'     => $data['shift_id'],
                'eid'     => $data['event_id'],
                'tid'     => $data['team_id'],
                'mid'     => $data['municipality_id'],
                'offered' => $data['offered_people'] ?? 0,
                'notes'   => $data['notes'] ?? null,
            ]
        );
        return (int) db()->lastInsertId();
    }

    /** Cancel a team's application for a shift. */
    public static function cancelApplication($shiftId, $teamId): void
    {
        dbq(
            'DELETE FROM shift_applications WHERE shift_id=:sid AND team_id=:tid',
            ['sid' => $shiftId, 'tid' => $teamId]
        );
    }

    /** Approve a shift application. */
    public static function approveApplication($id, $approvedPeople): void
    {
        dbq(
            'UPDATE shift_applications SET status=\'approved\', approved_people=:ap, updated_at=NOW() WHERE id=:id',
            ['ap' => $approvedPeople, 'id' => $id]
        );
    }

    /** Reject a shift application. */
    public static function rejectApplication($id): void
    {
        dbq(
            'UPDATE shift_applications SET status=\'rejected\', approved_people=0, updated_at=NOW() WHERE id=:id',
            ['id' => $id]
        );
    }

    /** Find a single shift application by ID with municipality guard. */
    public static function findApplication($id): array
    {
        $app = dbq(
            'SELECT sa.*, es.name AS shift_name, es.start_datetime AS shift_start,
                    es.end_datetime AS shift_end, t.name AS team_name
             FROM shift_applications sa
             JOIN event_shifts es ON es.id = sa.shift_id
             JOIN volunteer_teams t ON t.id = sa.team_id
             WHERE sa.id = :id LIMIT 1',
            ['id' => $id]
        )->fetch();
        if (!$app) { abort(404, 'Η αίτηση βάρδιας δεν βρέθηκε.'); }
        requireMunicipalityAccess($app['municipality_id']);
        return $app;
    }

    /**
     * Return shifts with per-team application summary for the operational page.
     * Each shift row includes: applied_teams (count), approved_people (sum).
     */
    public static function withStatsForEvent($eventId): array
    {
        return dbq(
            'SELECT es.*,
                    COUNT(sa.id) AS applied_teams,
                    COALESCE(SUM(CASE WHEN sa.status=\'approved\' THEN sa.approved_people ELSE 0 END), 0) AS confirmed_people
             FROM event_shifts es
             LEFT JOIN shift_applications sa ON sa.shift_id = es.id
             WHERE es.event_id = :eid
             GROUP BY es.id
             ORDER BY es.start_datetime',
            ['eid' => $eventId]
        )->fetchAll();
    }

    /**
     * Return shifts the given team has applied to for this event,
     * merged with full shift info.
     */
    public static function forTeamOnEvent($eventId, $teamId): array
    {
        return dbq(
            'SELECT es.*, sa.status AS app_status, sa.offered_people, sa.approved_people, sa.id AS app_id
             FROM event_shifts es
             LEFT JOIN shift_applications sa ON sa.shift_id = es.id AND sa.team_id = :tid
             WHERE es.event_id = :eid
             ORDER BY es.start_datetime',
            ['eid' => $eventId, 'tid' => $teamId]
        )->fetchAll();
    }
}
