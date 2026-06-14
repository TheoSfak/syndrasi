<?php
/**
 * SynDrasi - Event application model.
 */
class EventApplication
{
    public static function find($id)
    {
        return dbq(
            'SELECT ea.*, t.name AS team_name, e.title AS event_title, e.status AS event_status,
                    e.start_datetime, e.end_datetime, e.location_name
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             JOIN events e ON e.id = ea.event_id
             WHERE ea.id = :id LIMIT 1',
            ['id' => $id]
        )->fetch() ?: null;
    }

    public static function forEvent($eventId)
    {
        return dbq(
            'SELECT ea.*, t.name AS team_name, t.type AS team_type, t.phone AS team_phone,
                    t.has_vehicle AS team_has_vehicle, t.has_medical_equipment AS team_has_medical
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             WHERE ea.event_id = :eid
             ORDER BY FIELD(ea.status, \'pending\', \'approved\', \'rejected\', \'cancelled\'), ea.submitted_at',
            ['eid' => $eventId]
        )->fetchAll();
    }

    public static function approvedForEvent($eventId)
    {
        return dbq(
            "SELECT ea.*, t.name AS team_name, t.phone AS team_phone
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             WHERE ea.event_id = :eid AND ea.status = 'approved'
             ORDER BY t.name",
            ['eid' => $eventId]
        )->fetchAll();
    }

    public static function findByEventTeam($eventId, $teamId)
    {
        return dbq(
            'SELECT * FROM event_applications WHERE event_id = :eid AND team_id = :tid LIMIT 1',
            ['eid' => $eventId, 'tid' => $teamId]
        )->fetch() ?: null;
    }

    public static function pendingForMunicipality($municipalityId)
    {
        return dbq(
            "SELECT ea.*, t.name AS team_name, e.title AS event_title, e.start_datetime
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             JOIN events e ON e.id = ea.event_id
             WHERE ea.municipality_id = :mid AND ea.status = 'pending'
             ORDER BY e.start_datetime ASC, ea.submitted_at ASC",
            ['mid' => $municipalityId]
        )->fetchAll();
    }

    public static function forTeam($teamId)
    {
        return dbq(
            'SELECT ea.*, e.title AS event_title, e.status AS event_status, e.start_datetime, e.end_datetime, e.location_name
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.team_id = :tid
             ORDER BY e.start_datetime DESC',
            ['tid' => $teamId]
        )->fetchAll();
    }

    public static function create(array $d)
    {
        dbq(
            'INSERT INTO event_applications
             (municipality_id, event_id, team_id, offered_people, offered_vehicle, offered_medical_equipment, comment, status)
             VALUES (:municipality_id, :event_id, :team_id, :offered_people, :offered_vehicle, :offered_medical_equipment, :comment, \'pending\')',
            $d
        );
        return (int) db()->lastInsertId();
    }

    public static function approve($id, $approvedPeople, $adminComment, $reviewerId)
    {
        dbq(
            "UPDATE event_applications
             SET status = 'approved', approved_people = :people, admin_comment = :comment,
                 reviewed_at = NOW(), reviewed_by = :reviewer
             WHERE id = :id",
            ['people' => $approvedPeople, 'comment' => $adminComment, 'reviewer' => $reviewerId, 'id' => $id]
        );
    }

    public static function reject($id, $adminComment, $reviewerId)
    {
        dbq(
            "UPDATE event_applications
             SET status = 'rejected', approved_people = NULL, admin_comment = :comment,
                 reviewed_at = NOW(), reviewed_by = :reviewer
             WHERE id = :id",
            ['comment' => $adminComment, 'reviewer' => $reviewerId, 'id' => $id]
        );
    }

    public static function cancel($id)
    {
        dbq("UPDATE event_applications SET status = 'cancelled' WHERE id = :id", ['id' => $id]);
    }

    /** Short historical summary of a team (used in the review screen). */
    public static function teamHistorySummary($teamId)
    {
        return dbq(
            "SELECT
                COUNT(*) AS events_count,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, e.start_datetime, e.end_datetime) / 60 * ea.approved_people), 0) AS total_hours
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'",
            ['tid' => $teamId]
        )->fetch();
    }
}
