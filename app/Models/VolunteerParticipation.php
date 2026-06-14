<?php
/**
 * SynDrasi - Per-member event participation records.
 * Populated during reconciliation; one row per (event_id, member_id).
 */
class VolunteerParticipation
{
    /**
     * Upsert presence records for one application.
     * $presences = [ member_id => ['present' => 1|0, 'notes' => ''] ]
     * $hours     = actual hours the team was present (from arrival/departure or event duration)
     * $commanderId = mission commander member_id
     */
    public static function saveForApplication(
        int $eventId,
        int $teamId,
        int $appId,
        int $municipalityId,
        array $presences,
        float $hours,
        ?int $commanderId
    ): void {
        foreach ($presences as $memberId => $data) {
            $memberId  = (int) $memberId;
            $present   = !empty($data['present']) ? 1 : 0;
            $notes     = isset($data['notes']) ? trim($data['notes']) : null;
            $isCmd     = ($commanderId && $memberId === $commanderId) ? 1 : 0;
            $memberHrs = $present ? $hours : 0.0;

            dbq(
                "INSERT INTO volunteer_participations
                   (municipality_id, event_id, team_id, application_id, member_id,
                    was_present, hours, is_mission_commander, notes)
                 VALUES (:mid, :eid, :tid, :aid, :memid, :present, :hours, :cmd, :notes)
                 ON DUPLICATE KEY UPDATE
                   was_present          = VALUES(was_present),
                   hours                = VALUES(hours),
                   is_mission_commander = VALUES(is_mission_commander),
                   notes                = VALUES(notes)",
                [
                    'mid'     => $municipalityId,
                    'eid'     => $eventId,
                    'tid'     => $teamId,
                    'aid'     => $appId,
                    'memid'   => $memberId,
                    'present' => $present,
                    'hours'   => $memberHrs,
                    'cmd'     => $isCmd,
                    'notes'   => $notes ?: null,
                ]
            );
        }
    }

    /**
     * All participations for a member with event details — for stats/history page.
     */
    public static function forMember(int $memberId): array
    {
        return dbq(
            "SELECT vp.*, e.title AS event_title, e.start_datetime, e.end_datetime,
                    e.location_name, ec.name AS category_name, vt.name AS team_name
             FROM volunteer_participations vp
             JOIN events e  ON e.id  = vp.event_id
             JOIN volunteer_teams vt ON vt.id = vp.team_id
             LEFT JOIN event_categories ec ON ec.id = e.category_id
             WHERE vp.member_id = :mid
             ORDER BY e.start_datetime DESC",
            ['mid' => $memberId]
        )->fetchAll();
    }

    /**
     * Aggregate stats for a member.
     */
    public static function statsForMember(int $memberId): array
    {
        $row = dbq(
            "SELECT
               COUNT(*)                                          AS total_events,
               SUM(CASE WHEN was_present = 1 THEN 1 ELSE 0 END) AS attended_events,
               SUM(hours)                                        AS total_hours,
               SUM(is_mission_commander)                         AS times_commander
             FROM volunteer_participations
             WHERE member_id = :mid",
            ['mid' => $memberId]
        )->fetch();
        return $row ?: ['total_events' => 0, 'attended_events' => 0, 'total_hours' => 0, 'times_commander' => 0];
    }

    /**
     * Members with their presence for a given application — for reconciliation pre-fill.
     */
    public static function forApplication(int $appId): array
    {
        return dbq(
            "SELECT vp.*, tm.full_name AS member_name, tm.specialty
             FROM volunteer_participations vp
             JOIN team_members tm ON tm.id = vp.member_id
             WHERE vp.application_id = :aid
             ORDER BY tm.full_name ASC",
            ['aid' => $appId]
        )->fetchAll();
    }

    /**
     * Municipality-level leaderboard: top members by hours across all events.
     */
    public static function topMembers(int $municipalityId, int $limit = 20): array
    {
        return dbq(
            "SELECT tm.full_name, tm.specialty, vt.name AS team_name,
                    SUM(vp.hours)                         AS total_hours,
                    SUM(vp.was_present)                   AS events_attended,
                    SUM(vp.is_mission_commander)          AS times_commander
             FROM volunteer_participations vp
             JOIN team_members tm ON tm.id  = vp.member_id
             JOIN volunteer_teams vt ON vt.id = vp.team_id
             WHERE vp.municipality_id = :mid
             GROUP BY vp.member_id
             ORDER BY total_hours DESC
             LIMIT :lim",
            ['mid' => $municipalityId, 'lim' => $limit]
        )->fetchAll();
    }
}
