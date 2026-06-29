<?php
/**
 * SynDrasi - Statistics calculation service.
 *
 * Volunteer hours: present_people * event_hours when a check-in exists,
 * otherwise approved_people * event_hours (basic version).
 */
class StatsService
{
    private static function yearRange($year): array
    {
        $year = (int) $year;
        return [
            'from' => sprintf('%04d-01-01 00:00:00', $year),
            'to'   => sprintf('%04d-01-01 00:00:00', $year + 1),
        ];
    }

    /* -------------------------------------------------- Municipality level */

    public static function municipalityOverview($municipalityId, $year = null)
    {
        $year = $year ?: (int) date('Y');
        $p = ['mid' => $municipalityId] + self::yearRange($year);

        $eventsWithCoverage = (int) dbq(
            "SELECT COUNT(DISTINCT e.id) FROM events e
             JOIN event_applications ea ON ea.event_id = e.id AND ea.status = 'approved'
             WHERE e.municipality_id = :mid AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $activeTeams = (int) dbq(
            "SELECT COUNT(*) FROM volunteer_teams WHERE municipality_id = :mid AND status = 'active'",
            ['mid' => $municipalityId]
        )->fetchColumn();

        $participations = (int) dbq(
            "SELECT COALESCE(SUM(COALESCE(ci.present_people, ea.approved_people)), 0)
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN (
                SELECT event_id, team_id, MAX(id) AS last_id
                FROM operational_checkins GROUP BY event_id, team_id
             ) lc ON lc.event_id = ea.event_id AND lc.team_id = ea.team_id
             LEFT JOIN operational_checkins ci ON ci.id = lc.last_id
             WHERE ea.municipality_id = :mid AND ea.status = 'approved'
               AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $hours = (float) dbq(
            "SELECT COALESCE(SUM(
                TIMESTAMPDIFF(MINUTE, e.start_datetime, e.end_datetime) / 60
                * COALESCE(ci.present_people, ea.approved_people, 0)
             ), 0)
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN (
                SELECT event_id, team_id, MAX(id) AS last_id
                FROM operational_checkins GROUP BY event_id, team_id
             ) lc ON lc.event_id = ea.event_id AND lc.team_id = ea.team_id
             LEFT JOIN operational_checkins ci ON ci.id = lc.last_id
             WHERE ea.municipality_id = :mid AND ea.status = 'approved'
               AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $avgResponseMinutes = dbq(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, e.published_at, ea.submitted_at))
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.municipality_id = :mid AND e.published_at IS NOT NULL
               AND ea.submitted_at >= e.published_at
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $approvedPeople = (int) dbq(
            "SELECT COALESCE(SUM(ea.approved_people),0)
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.municipality_id = :mid AND ea.status = 'approved'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $requested = (int) dbq(
            "SELECT COALESCE(SUM(requested_people),0) FROM events
             WHERE municipality_id = :mid AND status IN ('completed','active','confirmed')
               AND start_datetime >= :from AND start_datetime < :to",
            $p
        )->fetchColumn();

        return [
            'year'                 => $year,
            'events_with_coverage' => $eventsWithCoverage,
            'active_teams'         => $activeTeams,
            'participations'       => $participations,
            'volunteer_hours'      => round($hours, 1),
            'avg_response_minutes' => $avgResponseMinutes !== null ? round((float) $avgResponseMinutes) : null,
            'approved_people'      => $approvedPeople,
            'requested_people'     => $requested,
        ];
    }

    public static function eventsByCategory($municipalityId, $year)
    {
        return dbq(
            "SELECT COALESCE(c.name, 'Χωρίς κατηγορία') AS category, COUNT(*) AS total
             FROM events e LEFT JOIN event_categories c ON c.id = e.category_id
             WHERE e.municipality_id = :mid AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to
             GROUP BY c.name ORDER BY total DESC",
            ['mid' => $municipalityId] + self::yearRange($year)
        )->fetchAll();
    }

    public static function eventsByMonth($municipalityId, $year)
    {
        $rows = dbq(
            "SELECT MONTH(e.start_datetime) AS m, COUNT(*) AS total
             FROM events e
             WHERE e.municipality_id = :mid AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to
             GROUP BY MONTH(e.start_datetime)",
            ['mid' => $municipalityId] + self::yearRange($year)
        )->fetchAll();
        $byMonth = array_fill(1, 12, 0);
        foreach ($rows as $r) {
            $byMonth[(int) $r['m']] = (int) $r['total'];
        }
        return $byMonth;
    }

    /* ---------------------------------------------------------- Team level */

    /** Full statistics for one team for a year. */
    public static function teamStats($teamId, $year = null)
    {
        $year = $year ?: (int) date('Y');
        $p = ['tid' => $teamId] + self::yearRange($year);

        $eventsCount = (int) dbq(
            "SELECT COUNT(*) FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $approvedVolunteers = (int) dbq(
            "SELECT COALESCE(SUM(ea.approved_people),0) FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $presentVolunteers = (int) dbq(
            "SELECT COALESCE(SUM(COALESCE(ci.present_people, ea.approved_people)),0)
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN (
                SELECT event_id, team_id, MAX(id) AS last_id
                FROM operational_checkins GROUP BY event_id, team_id
             ) lc ON lc.event_id = ea.event_id AND lc.team_id = ea.team_id
             LEFT JOIN operational_checkins ci ON ci.id = lc.last_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $hours = (float) dbq(
            "SELECT COALESCE(SUM(
                TIMESTAMPDIFF(MINUTE, e.start_datetime, e.end_datetime) / 60
                * COALESCE(ci.present_people, ea.approved_people, 0)
             ),0)
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN (
                SELECT event_id, team_id, MAX(id) AS last_id
                FROM operational_checkins GROUP BY event_id, team_id
             ) lc ON lc.event_id = ea.event_id AND lc.team_id = ea.team_id
             LEFT JOIN operational_checkins ci ON ci.id = lc.last_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            $p
        )->fetchColumn();

        $categories = dbq(
            "SELECT COALESCE(c.name, 'Χωρίς κατηγορία') AS category, COUNT(*) AS total
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN event_categories c ON c.id = e.category_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to
             GROUP BY c.name ORDER BY total DESC",
            $p
        )->fetchAll();

        $consistency = self::teamConsistency($teamId, $year);
        $response = self::teamAvgResponseMinutes($teamId, $year);

        $shortages = (int) dbq(
            "SELECT COUNT(*) FROM shortage_reports sr
             WHERE sr.team_id = :tid AND sr.created_at >= :from AND sr.created_at < :to",
            $p
        )->fetchColumn();

        return [
            'year'                 => $year,
            'events_count'         => $eventsCount,
            'approved_volunteers'  => $approvedVolunteers,
            'present_volunteers'   => $presentVolunteers,
            'volunteer_hours'      => round($hours, 1),
            'categories'           => $categories,
            'consistency_score'    => $consistency,
            'avg_response_minutes' => $response,
            'shortage_reports'     => $shortages,
        ];
    }

    /** consistency = completed attendances / approved applications * 100 */
    public static function teamConsistency($teamId, $year)
    {
        $row = dbq(
            "SELECT
               COUNT(*) AS approved_apps,
               SUM(CASE WHEN ci.id IS NOT NULL AND ci.status IN ('present_full','present_partial','departed') THEN 1
                        WHEN ci.id IS NULL THEN 1 ELSE 0 END) AS attended
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN (
                SELECT oc1.* FROM operational_checkins oc1
                JOIN (SELECT event_id, team_id, MAX(id) AS last_id FROM operational_checkins GROUP BY event_id, team_id) x
                  ON x.last_id = oc1.id
             ) ci ON ci.event_id = ea.event_id AND ci.team_id = ea.team_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            ['tid' => $teamId] + self::yearRange($year)
        )->fetch();
        $apps = (int) $row['approved_apps'];
        if ($apps === 0) {
            return null;
        }
        return round((int) $row['attended'] / $apps * 100, 1);
    }

    public static function teamAvgResponseMinutes($teamId, $year)
    {
        $val = dbq(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, e.published_at, ea.submitted_at))
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.team_id = :tid AND e.published_at IS NOT NULL
               AND ea.submitted_at >= e.published_at
               AND e.start_datetime >= :from AND e.start_datetime < :to",
            ['tid' => $teamId] + self::yearRange($year)
        )->fetchColumn();
        return $val !== null ? (int) round((float) $val) : null;
    }

    /** Ranking table of all teams for a municipality/year. */
    public static function teamRanking($municipalityId, $year)
    {
        $teams = VolunteerTeam::forMunicipality($municipalityId);
        $ranking = [];
        foreach ($teams as $team) {
            $s = self::teamStats($team['id'], $year);
            $ranking[] = [
                'team_id'              => $team['id'],
                'team_name'            => $team['name'],
                'team_type'            => $team['type'],
                'events_count'         => $s['events_count'],
                'volunteer_hours'      => $s['volunteer_hours'],
                'present_volunteers'   => $s['present_volunteers'],
                'consistency_score'    => $s['consistency_score'],
                'avg_response_minutes' => $s['avg_response_minutes'],
            ];
        }
        usort($ranking, function ($a, $b) {
            if ($b['volunteer_hours'] == $a['volunteer_hours']) {
                return $b['events_count'] - $a['events_count'];
            }
            return ($b['volunteer_hours'] < $a['volunteer_hours']) ? -1 : 1;
        });
        return $ranking;
    }

    /**
     * Award winners for the year (per spec 13.7).
     * @param array $thresholds Optional overrides: bronze_events, silver_events, gold_events, min_events
     */
    public static function awards($municipalityId, $year, array $thresholds = [])
    {
        $thresholds += [
            'bronze_events' => 5,
            'silver_events' => 10,
            'gold_events'   => 20,
            'min_events'    => 3,
        ];
        $minEvents = (int) $thresholds['min_events'];

        $ranking = self::teamRanking($municipalityId, $year);

        // Add participation tier badge to each team
        foreach ($ranking as &$r) {
            $count = (int) $r['events_count'];
            if ($count >= (int) $thresholds['gold_events']) {
                $r['tier'] = 'gold';
            } elseif ($count >= (int) $thresholds['silver_events']) {
                $r['tier'] = 'silver';
            } elseif ($count >= (int) $thresholds['bronze_events']) {
                $r['tier'] = 'bronze';
            } else {
                $r['tier'] = '';
            }
        }
        unset($r);

        $bestContribution = null;
        $mostActive = null;
        $mostConsistent = null;
        $fastestResponse = null;

        foreach ($ranking as $r) {
            if ($r['volunteer_hours'] > 0 && ($bestContribution === null || $r['volunteer_hours'] > $bestContribution['volunteer_hours'])) {
                $bestContribution = $r;
            }
            if ($r['events_count'] > 0 && ($mostActive === null || $r['events_count'] > $mostActive['events_count'])) {
                $mostActive = $r;
            }
            if ($r['events_count'] >= $minEvents && $r['consistency_score'] !== null
                && ($mostConsistent === null || $r['consistency_score'] > $mostConsistent['consistency_score'])) {
                $mostConsistent = $r;
            }
            if ($r['avg_response_minutes'] !== null && $r['events_count'] >= $minEvents
                && ($fastestResponse === null || $r['avg_response_minutes'] < $fastestResponse['avg_response_minutes'])) {
                $fastestResponse = $r;
            }
        }

        return [
            'best_contribution' => $bestContribution,
            'most_active'       => $mostActive,
            'most_consistent'   => $mostConsistent,
            'fastest_response'  => $fastestResponse,
            'ranking'           => $ranking,
            'thresholds'        => $thresholds,
        ];
    }
}
