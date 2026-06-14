<?php
/**
 * SynDrasi - Statistics dashboard (municipality admin).
 */
class StatisticsController
{
    public function index()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

        $overview = StatsService::municipalityOverview($mid, $year);
        $byCategory = StatsService::eventsByCategory($mid, $year);
        $byMonth = StatsService::eventsByMonth($mid, $year);
        $ranking = StatsService::teamRanking($mid, $year);

        render('statistics/index', [
            'pageTitle'  => 'Στατιστικά',
            'year'       => $year,
            'overview'   => $overview,
            'byCategory' => $byCategory,
            'byMonth'    => $byMonth,
            'ranking'    => $ranking,
        ]);
    }

    public function team($id)
    {
        requireRole(['municipality_admin']);
        $team = VolunteerTeam::find($id);
        if (!$team) {
            abort(404, 'Η ομάδα δεν βρέθηκε.');
        }
        requireMunicipalityAccess($team['municipality_id']);

        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $stats = StatsService::teamStats($team['id'], $year);

        $history = dbq(
            "SELECT e.title, e.start_datetime, e.end_datetime, c.name AS category_name,
                    ea.approved_people, ci.present_people, ci.status AS checkin_status
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN event_categories c ON c.id = e.category_id
             LEFT JOIN (
                SELECT oc1.* FROM operational_checkins oc1
                JOIN (SELECT event_id, team_id, MAX(id) AS last_id FROM operational_checkins GROUP BY event_id, team_id) x
                  ON x.last_id = oc1.id
             ) ci ON ci.event_id = ea.event_id AND ci.team_id = ea.team_id
             WHERE ea.team_id = :tid AND ea.status = 'approved' AND e.status = 'completed'
               AND YEAR(e.start_datetime) = :year
             ORDER BY e.start_datetime DESC",
            ['tid' => $team['id'], 'year' => $year]
        )->fetchAll();

        render('statistics/team', [
            'pageTitle' => 'Στατιστικά: ' . $team['name'],
            'team'      => $team,
            'stats'     => $stats,
            'history'   => $history,
            'year'      => $year,
        ]);
    }
}
