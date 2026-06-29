<?php
/**
 * SynDrasi - Municipality dashboard with rich stats.
 */
class DashboardController
{
    public function municipality()
    {
        requireRole(['municipality_admin', 'event_operator']);
        $mid  = current_municipality_id();
        $year = (int) date('Y');
        $month = (int) date('n');

        /* ── Core event counts (one query replaces 4 separate COUNTs) ── */
        $eventStatusRows = dbq(
            "SELECT status, COUNT(*) AS cnt FROM events WHERE municipality_id=:mid GROUP BY status",
            ['mid' => $mid]
        )->fetchAll();
        $esc = [];
        foreach ($eventStatusRows as $r) { $esc[$r['status']] = (int) $r['cnt']; }
        $openEvents      = $esc['open']                              ?? 0;
        $confirmedEvents = ($esc['confirmed'] ?? 0) + ($esc['review'] ?? 0);
        $draftEvents     = $esc['draft']                             ?? 0;

        $pendingApplications = (int) dbq(
            "SELECT COUNT(*) FROM event_applications WHERE municipality_id=:mid AND status='pending'", ['mid'=>$mid]
        )->fetchColumn();

        $activeToday = dbq(
            "SELECT * FROM events WHERE municipality_id=:mid AND status='active' ORDER BY start_datetime",
            ['mid'=>$mid]
        )->fetchAll();

        $completedYear = (int) dbq(
            "SELECT COUNT(*) FROM events WHERE municipality_id=:mid AND status='completed' AND YEAR(start_datetime)=:y",
            ['mid'=>$mid,'y'=>$year]
        )->fetchColumn();

        /* ── Team counts (one query replaces 2) ── */
        $teamRow = dbq(
            "SELECT COUNT(*) AS total, SUM(status='active') AS active_count
             FROM volunteer_teams WHERE municipality_id=:mid",
            ['mid' => $mid]
        )->fetch();
        $totalTeams  = (int) ($teamRow['total']        ?? 0);
        $activeTeams = (int) ($teamRow['active_count'] ?? 0);

        /* ── Volunteer hours this month vs last month ─ */
        $hoursThisMonth = (float) dbq(
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE,e.start_datetime,e.end_datetime)/60 * ea.approved_people),0)
             FROM event_applications ea JOIN events e ON e.id=ea.event_id
             WHERE ea.municipality_id=:mid AND ea.status='approved' AND e.status='completed'
               AND YEAR(e.start_datetime)=:y AND MONTH(e.start_datetime)=:m",
            ['mid'=>$mid,'y'=>$year,'m'=>$month]
        )->fetchColumn();

        $hoursLastMonth = (float) dbq(
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE,e.start_datetime,e.end_datetime)/60 * ea.approved_people),0)
             FROM event_applications ea JOIN events e ON e.id=ea.event_id
             WHERE ea.municipality_id=:mid AND ea.status='approved' AND e.status='completed'
               AND YEAR(e.start_datetime)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))
               AND MONTH(e.start_datetime)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH))",
            ['mid'=>$mid]
        )->fetchColumn();

        /* ── Application approval rate (one query replaces 2) ── */
        $appRateRow = dbq(
            "SELECT COUNT(*) AS total, SUM(status='approved') AS approved_count
             FROM event_applications WHERE municipality_id=:mid AND status IN ('approved','rejected')",
            ['mid' => $mid]
        )->fetch();
        $totalApps    = (int) ($appRateRow['total']          ?? 0);
        $approvedApps = (int) ($appRateRow['approved_count'] ?? 0);
        $approvalRate = $totalApps > 0 ? round($approvedApps / $totalApps * 100) : 0;

        /* ── Open shortages ───────────────────────── */
        $openShortages = dbq(
            "SELECT sr.*, t.name AS team_name, e.title AS event_title
             FROM shortage_reports sr
             JOIN volunteer_teams t ON t.id=sr.team_id
             JOIN events e ON e.id=sr.event_id
             WHERE sr.municipality_id=:mid AND sr.status='open'
             ORDER BY FIELD(sr.severity,'critical','high','medium','low'), sr.created_at DESC LIMIT 5",
            ['mid'=>$mid]
        )->fetchAll();

        /* ── Monthly event trend (one query replaces 6) ── */
        $monthlyRows = dbq(
            "SELECT YEAR(start_datetime) AS y, MONTH(start_datetime) AS m, COUNT(*) AS cnt
             FROM events WHERE municipality_id=:mid
               AND start_datetime >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 MONTH), '%Y-%m-01')
             GROUP BY YEAR(start_datetime), MONTH(start_datetime)",
            ['mid' => $mid]
        )->fetchAll();
        $monthMap = [];
        foreach ($monthlyRows as $r) { $monthMap[(int)$r['y'] . '-' . (int)$r['m']] = (int)$r['cnt']; }

        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts   = mktime(0, 0, 0, $month - $i, 1, $year);
            $mNum = (int) date('n', $ts);
            $yNum = (int) date('Y', $ts);
            $monthlyTrend[] = [
                'label' => greek_month_short($mNum),
                'count' => $monthMap[$yNum . '-' . $mNum] ?? 0,
            ];
        }

        /* ── Events by status this year (one query replaces 7) ── */
        $statusYearRows = dbq(
            "SELECT status, COUNT(*) AS cnt FROM events
             WHERE municipality_id=:mid AND YEAR(start_datetime)=:y GROUP BY status",
            ['mid'=>$mid,'y'=>$year]
        )->fetchAll();
        $byStatus = array_fill_keys(['draft','open','review','confirmed','active','completed','cancelled'], 0);
        foreach ($statusYearRows as $r) {
            if (isset($byStatus[$r['status']])) { $byStatus[$r['status']] = (int) $r['cnt']; }
        }

        /* ── Top 5 teams this year ────────────────── */
        $topTeams = dbq(
            "SELECT t.name AS team_name, t.type AS team_type,
                    COUNT(DISTINCT ea.event_id) AS events_count,
                    SUM(TIMESTAMPDIFF(MINUTE,e.start_datetime,e.end_datetime)/60 * ea.approved_people) AS volunteer_hours
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id=ea.team_id
             JOIN events e ON e.id=ea.event_id
             WHERE ea.municipality_id=:mid AND ea.status='approved' AND e.status='completed'
               AND YEAR(e.start_datetime)=:y
             GROUP BY ea.team_id
             ORDER BY volunteer_hours DESC LIMIT 5",
            ['mid'=>$mid,'y'=>$year]
        )->fetchAll();

        /* ── Overview (global volunteer hours) ── */
        $overview = StatsService::municipalityOverview($mid, $year);

        $fireCreteAlert = ['total' => 0, 'by_status' => [], 'latest' => [], 'fetch' => null];
        if (current_role() === 'municipality_admin') {
            try {
                $fireCreteAlert = FireServiceIncidentService::creteAlert();
            } catch (Throwable $e) {
                error_log('Fire Service dashboard alert failed: ' . $e->getMessage());
            }
        }

        $upcoming = dbq(
            "SELECT e.*, c.name AS category_name
             FROM events e LEFT JOIN event_categories c ON c.id=e.category_id
             WHERE e.municipality_id=:mid AND e.status IN ('open','review','confirmed')
               AND e.start_datetime >= NOW()
             ORDER BY e.start_datetime ASC LIMIT 6",
            ['mid'=>$mid]
        )->fetchAll();

        render('dashboard/municipality', [
            'pageTitle'           => 'Πίνακας Ελέγχου',
            'openEvents'          => $openEvents,
            'pendingApplications' => $pendingApplications,
            'confirmedEvents'     => $confirmedEvents,
            'draftEvents'         => $draftEvents,
            'completedYear'       => $completedYear,
            'totalTeams'          => $totalTeams,
            'activeTeams'         => $activeTeams,
            'hoursThisMonth'      => round($hoursThisMonth, 1),
            'hoursLastMonth'      => round($hoursLastMonth, 1),
            'approvalRate'        => $approvalRate,
            'activeToday'         => $activeToday,
            'openShortages'       => $openShortages,
            'monthlyTrend'        => $monthlyTrend,
            'byStatus'            => $byStatus,
            'topTeams'            => $topTeams,
            'upcoming'            => $upcoming,
            'overview'            => $overview,
            'fireCreteAlert'      => $fireCreteAlert,
            'year'                => $year,
        ]);
    }
}
