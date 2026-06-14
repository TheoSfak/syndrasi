<?php
/**
 * SynDrasi - Advanced analytics & trends (municipality admin).
 *
 * Multi-year (year-over-year) trends that complement the single-year
 * StatisticsController. Volunteer hours are computed with the same rule as
 * StatsService: event_hours * COALESCE(present_people, approved_people).
 */
class AnalyticsController
{
    private const SPAN = 5; // number of years shown (current + 4 previous)

    /** GET /analytics */
    public function index()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $focus = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $y1    = $focus;
        $y0    = $focus - (self::SPAN - 1);
        $years = range($y0, $y1);

        $yearly        = $this->yearlySeries($mid, $y0, $y1);
        $teamTrends    = $this->teamTrends($mid, $y0, $y1, $years);
        $byCategory    = $this->categoryBreakdown($mid, $y0, $y1);
        $monthlyCur    = StatsService::eventsByMonth($mid, $focus);
        $monthlyPrev   = StatsService::eventsByMonth($mid, $focus - 1);

        // YoY deltas vs previous year for the focus year
        $cur  = $yearly[$focus]      ?? ['events' => 0, 'participations' => 0, 'hours' => 0, 'avg_resp' => null];
        $prev = $yearly[$focus - 1]  ?? ['events' => 0, 'participations' => 0, 'hours' => 0, 'avg_resp' => null];

        render('analytics/index', [
            'pageTitle'    => 'Αναλύσεις & Τάσεις',
            'focus'        => $focus,
            'years'        => $years,
            'yearly'       => $yearly,
            'teamTrends'   => $teamTrends,
            'byCategory'   => $byCategory,
            'monthlyCur'   => array_values($monthlyCur),
            'monthlyPrev'  => array_values($monthlyPrev),
            'cur'          => $cur,
            'prev'         => $prev,
        ]);
    }

    /** GET /analytics/export?type=yearly|category|teams */
    public function export()
    {
        requireRole(['municipality_admin']);
        $mid   = current_municipality_id();
        $focus = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $type  = $_GET['type'] ?? 'yearly';
        $y1    = $focus;
        $y0    = $focus - (self::SPAN - 1);

        if ($type === 'category') {
            $rows = [];
            foreach ($this->categoryBreakdown($mid, $y0, $y1) as $c) {
                $rows[] = [$c['category'], (int) $c['events'], round((float) $c['hours'], 1)];
            }
            audit('export', 'analytics_category', null);
            CsvService::download(
                "syndrasi-analytics-category-{$y0}-{$y1}.csv",
                ['Κατηγορία', 'Δράσεις', 'Εθελοντικές ώρες'],
                $rows
            );
            return;
        }

        if ($type === 'teams') {
            $years  = range($y0, $y1);
            $trends = $this->teamTrends($mid, $y0, $y1, $years);
            $header = array_merge(['Ομάδα'], array_map(fn($y) => "Ώρες $y", $years), ['Σύνολο ωρών']);
            $rows   = [];
            foreach ($trends as $t) {
                $row = [$t['team_name']];
                foreach ($years as $y) {
                    $row[] = round((float) ($t['by_year'][$y] ?? 0), 1);
                }
                $row[] = round((float) $t['total_hours'], 1);
                $rows[] = $row;
            }
            audit('export', 'analytics_teams', null);
            CsvService::download("syndrasi-analytics-teams-{$y0}-{$y1}.csv", $header, $rows);
            return;
        }

        // default: yearly trends
        $yearly = $this->yearlySeries($mid, $y0, $y1);
        $rows   = [];
        for ($y = $y0; $y <= $y1; $y++) {
            $r = $yearly[$y] ?? ['events' => 0, 'participations' => 0, 'hours' => 0, 'avg_resp' => null];
            $rows[] = [
                $y,
                (int) $r['events'],
                (int) $r['participations'],
                round((float) $r['hours'], 1),
                $r['avg_resp'] !== null ? (int) round((float) $r['avg_resp']) : '',
            ];
        }
        audit('export', 'analytics_yearly', null);
        CsvService::download(
            "syndrasi-analytics-yearly-{$y0}-{$y1}.csv",
            ['Έτος', 'Δράσεις', 'Συμμετοχές', 'Εθελοντικές ώρες', 'Μέσος χρόνος ανταπόκρισης (λεπτά)'],
            $rows
        );
    }

    // ----------------------------------------------------------- private

    /** Per-year totals keyed by year. */
    private function yearlySeries(int $mid, int $y0, int $y1): array
    {
        $agg = dbq(
            "SELECT YEAR(e.start_datetime) AS yr,
                    COUNT(DISTINCT e.id) AS events,
                    COALESCE(SUM(COALESCE(ci.present_people, ea.approved_people)),0) AS participations,
                    COALESCE(SUM(
                        TIMESTAMPDIFF(MINUTE, e.start_datetime, e.end_datetime) / 60
                        * COALESCE(ci.present_people, ea.approved_people, 0)
                    ),0) AS hours
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             LEFT JOIN (
                SELECT event_id, team_id, MAX(id) AS last_id
                FROM operational_checkins GROUP BY event_id, team_id
             ) lc ON lc.event_id = ea.event_id AND lc.team_id = ea.team_id
             LEFT JOIN operational_checkins ci ON ci.id = lc.last_id
             WHERE ea.municipality_id = :mid AND ea.status = 'approved'
               AND e.status = 'completed'
               AND YEAR(e.start_datetime) BETWEEN :y0 AND :y1
             GROUP BY YEAR(e.start_datetime)",
            ['mid' => $mid, 'y0' => $y0, 'y1' => $y1]
        )->fetchAll();

        $resp = dbq(
            "SELECT YEAR(e.start_datetime) AS yr,
                    AVG(TIMESTAMPDIFF(MINUTE, e.published_at, ea.submitted_at)) AS avg_resp
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.municipality_id = :mid AND e.published_at IS NOT NULL
               AND ea.submitted_at >= e.published_at
               AND YEAR(e.start_datetime) BETWEEN :y0 AND :y1
             GROUP BY YEAR(e.start_datetime)",
            ['mid' => $mid, 'y0' => $y0, 'y1' => $y1]
        )->fetchAll();

        $respMap = [];
        foreach ($resp as $r) {
            $respMap[(int) $r['yr']] = $r['avg_resp'];
        }

        $out = [];
        for ($y = $y0; $y <= $y1; $y++) {
            $out[$y] = ['events' => 0, 'participations' => 0, 'hours' => 0.0, 'avg_resp' => $respMap[$y] ?? null];
        }
        foreach ($agg as $r) {
            $y = (int) $r['yr'];
            $out[$y]['events']         = (int) $r['events'];
            $out[$y]['participations'] = (int) $r['participations'];
            $out[$y]['hours']          = round((float) $r['hours'], 1);
        }
        return $out;
    }

    /** Category breakdown (events + hours) across the whole range. */
    private function categoryBreakdown(int $mid, int $y0, int $y1): array
    {
        return dbq(
            "SELECT COALESCE(c.name, 'Χωρίς κατηγορία') AS category,
                    COUNT(DISTINCT e.id) AS events,
                    COALESCE(SUM(
                        TIMESTAMPDIFF(MINUTE, e.start_datetime, e.end_datetime) / 60
                        * COALESCE(ci.present_people, ea.approved_people, 0)
                    ),0) AS hours
             FROM events e
             LEFT JOIN event_categories c ON c.id = e.category_id
             LEFT JOIN event_applications ea ON ea.event_id = e.id AND ea.status = 'approved'
             LEFT JOIN (
                SELECT event_id, team_id, MAX(id) AS last_id
                FROM operational_checkins GROUP BY event_id, team_id
             ) lc ON lc.event_id = ea.event_id AND lc.team_id = ea.team_id
             LEFT JOIN operational_checkins ci ON ci.id = lc.last_id
             WHERE e.municipality_id = :mid AND e.status = 'completed'
               AND YEAR(e.start_datetime) BETWEEN :y0 AND :y1
             GROUP BY c.name
             ORDER BY events DESC, hours DESC",
            ['mid' => $mid, 'y0' => $y0, 'y1' => $y1]
        )->fetchAll();
    }

    /** Top teams by total hours, with per-year hours for the range. */
    private function teamTrends(int $mid, int $y0, int $y1, array $years): array
    {
        $rows = dbq(
            "SELECT ea.team_id, t.name AS team_name,
                    YEAR(e.start_datetime) AS yr,
                    COALESCE(SUM(
                        TIMESTAMPDIFF(MINUTE, e.start_datetime, e.end_datetime) / 60
                        * COALESCE(ci.present_people, ea.approved_people, 0)
                    ),0) AS hours,
                    COUNT(DISTINCT e.id) AS events
             FROM event_applications ea
             JOIN events e ON e.id = ea.event_id
             JOIN volunteer_teams t ON t.id = ea.team_id
             LEFT JOIN (
                SELECT event_id, team_id, MAX(id) AS last_id
                FROM operational_checkins GROUP BY event_id, team_id
             ) lc ON lc.event_id = ea.event_id AND lc.team_id = ea.team_id
             LEFT JOIN operational_checkins ci ON ci.id = lc.last_id
             WHERE ea.municipality_id = :mid AND ea.status = 'approved'
               AND e.status = 'completed'
               AND YEAR(e.start_datetime) BETWEEN :y0 AND :y1
             GROUP BY ea.team_id, YEAR(e.start_datetime)",
            ['mid' => $mid, 'y0' => $y0, 'y1' => $y1]
        )->fetchAll();

        $teams = [];
        foreach ($rows as $r) {
            $tid = (int) $r['team_id'];
            if (!isset($teams[$tid])) {
                $teams[$tid] = [
                    'team_id'      => $tid,
                    'team_name'    => $r['team_name'],
                    'by_year'      => array_fill_keys($years, 0.0),
                    'total_hours'  => 0.0,
                    'total_events' => 0,
                ];
            }
            $teams[$tid]['by_year'][(int) $r['yr']] = round((float) $r['hours'], 1);
            $teams[$tid]['total_hours']  += (float) $r['hours'];
            $teams[$tid]['total_events'] += (int) $r['events'];
        }

        $teams = array_values($teams);
        usort($teams, fn($a, $b) => $b['total_hours'] <=> $a['total_hours']);
        foreach ($teams as &$t) {
            $t['total_hours'] = round($t['total_hours'], 1);
        }
        unset($t);

        return array_slice($teams, 0, 8);
    }
}
