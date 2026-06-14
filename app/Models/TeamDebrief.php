<?php
/**
 * SynDrasi - TeamDebrief model.
 */
class TeamDebrief
{
    public static function findByEventTeam(int $eventId, int $teamId): ?array
    {
        return dbq(
            'SELECT d.*, u.name AS submitted_by_name
             FROM team_debriefs d
             JOIN users u ON u.id = d.submitted_by
             WHERE d.event_id = :eid AND d.team_id = :tid LIMIT 1',
            ['eid' => $eventId, 'tid' => $teamId]
        )->fetch() ?: null;
    }

    public static function forEvent(int $eventId): array
    {
        return dbq(
            'SELECT d.*, t.name AS team_name, u.name AS submitted_by_name
             FROM team_debriefs d
             JOIN volunteer_teams t ON t.id = d.team_id
             JOIN users u ON u.id = d.submitted_by
             WHERE d.event_id = :eid
             ORDER BY d.submitted_at ASC',
            ['eid' => $eventId]
        )->fetchAll();
    }

    /** Stats for event debrief overview page */
    public static function statsForEvent(int $eventId): array
    {
        $row = dbq(
            'SELECT
               COUNT(*)                          AS debrief_count,
               SUM(actual_volunteers)            AS total_volunteers,
               SUM(volunteer_hours)              AS total_hours,
               SUM(incidents_count)              AS total_incidents,
               ROUND(AVG(organization_rating),1) AS avg_rating
             FROM team_debriefs
             WHERE event_id = :eid',
            ['eid' => $eventId]
        )->fetch();
        return $row ?: [];
    }

    /** Global stats per municipality (for dashboard) */
    public static function globalStats(int $municipalityId): array
    {
        return dbq(
            'SELECT
               COUNT(*)                          AS total_debriefs,
               SUM(d.volunteer_hours)            AS total_hours,
               SUM(d.incidents_count)            AS total_incidents,
               ROUND(AVG(d.organization_rating),1) AS avg_rating
             FROM team_debriefs d
             WHERE d.municipality_id = :mid',
            ['mid' => $municipalityId]
        )->fetch() ?: [];
    }

    /** Top-rated teams (for stats page) */
    public static function topRatedTeams(int $municipalityId, int $limit = 5): array
    {
        return dbq(
            'SELECT t.name AS team_name, t.id AS team_id,
                    COUNT(d.id) AS debrief_count,
                    ROUND(AVG(d.organization_rating),1) AS avg_rating,
                    SUM(d.volunteer_hours) AS total_hours
             FROM team_debriefs d
             JOIN volunteer_teams t ON t.id = d.team_id
             WHERE d.municipality_id = :mid
             GROUP BY d.team_id
             HAVING debrief_count >= 1
             ORDER BY avg_rating DESC, total_hours DESC
             LIMIT ' . (int)$limit,
            ['mid' => $municipalityId]
        )->fetchAll();
    }

    public static function upsert(array $data): void
    {
        dbq(
            'INSERT INTO team_debriefs
               (event_id, team_id, municipality_id, submitted_by,
                actual_volunteers, volunteer_hours, incidents_count,
                what_went_well, what_went_wrong, incidents_description,
                organization_rating, comments)
             VALUES
               (:event_id, :team_id, :municipality_id, :submitted_by,
                :actual_volunteers, :volunteer_hours, :incidents_count,
                :what_went_well, :what_went_wrong, :incidents_description,
                :organization_rating, :comments)
             ON DUPLICATE KEY UPDATE
               submitted_by          = VALUES(submitted_by),
               actual_volunteers     = VALUES(actual_volunteers),
               volunteer_hours       = VALUES(volunteer_hours),
               incidents_count       = VALUES(incidents_count),
               what_went_well        = VALUES(what_went_well),
               what_went_wrong       = VALUES(what_went_wrong),
               incidents_description = VALUES(incidents_description),
               organization_rating   = VALUES(organization_rating),
               comments              = VALUES(comments),
               updated_at            = NOW()',
            $data
        );
    }
}
