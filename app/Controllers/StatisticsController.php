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

        // ── Tab 1: single-year overview ──────────────────────────────────────
        $overview = StatsService::municipalityOverview($mid, $year);
        $catYear  = StatsService::eventsByCategory($mid, $year); // single-year category pie
        $byMonth  = StatsService::eventsByMonth($mid, $year);
        $ranking  = StatsService::teamRanking($mid, $year);

        // ── Tab 2: multi-year trends (formerly the Analytics page) ───────────
        $focus = $year;
        $y1    = $focus;
        $y0    = $focus - (AnalyticsController::SPAN - 1);
        $years = range($y0, $y1);

        $yearly      = AnalyticsController::yearlySeries($mid, $y0, $y1);
        $teamTrends  = AnalyticsController::teamTrends($mid, $y0, $y1, $years);
        $byCategory  = AnalyticsController::categoryBreakdown($mid, $y0, $y1); // multi-year breakdown
        $monthlyCur  = StatsService::eventsByMonth($mid, $focus);
        $monthlyPrev = StatsService::eventsByMonth($mid, $focus - 1);
        $cur  = $yearly[$focus]     ?? ['events' => 0, 'participations' => 0, 'hours' => 0, 'avg_resp' => null];
        $prev = $yearly[$focus - 1] ?? ['events' => 0, 'participations' => 0, 'hours' => 0, 'avg_resp' => null];

        render('statistics/index', [
            'pageTitle'   => 'Στατιστικά & Τάσεις',
            'year'        => $year,
            'overview'    => $overview,
            'catYear'     => $catYear,
            'byMonth'     => $byMonth,
            'ranking'     => $ranking,
            'focus'       => $focus,
            'years'       => array_values($years),
            'yearly'      => $yearly,
            'teamTrends'  => $teamTrends,
            'byCategory'  => $byCategory,
            'monthlyCur'  => array_values($monthlyCur),
            'monthlyPrev' => array_values($monthlyPrev),
            'cur'         => $cur,
            'prev'        => $prev,
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
