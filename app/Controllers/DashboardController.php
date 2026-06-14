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

        /* ── Core counts ─────────────────────────────── */
        $openEvents = (int) dbq(
            "SELECT COUNT(*) FROM events WHERE municipality_id=:mid AND status='open'", ['mid'=>$mid]
        )->fetchColumn();

        $pendingApplications = (int) dbq(
            "SELECT COUNT(*) FROM event_applications WHERE municipality_id=:mid AND status='pending'", ['mid'=>$mid]
        )->fetchColumn();

        $confirmedEvents = (int) dbq(
            "SELECT COUNT(*) FROM events WHERE municipality_id=:mid AND status IN ('confirmed','review')", ['mid'=>$mid]
        )->fetchColumn();

        $activeToday = dbq(
            "SELECT * FROM events WHERE municipality_id=:mid AND status='active' ORDER BY start_datetime",
            ['mid'=>$mid]
        )->fetchAll();

        $draftEvents = (int) dbq(
            "SELECT COUNT(*) FROM events WHERE municipality_id=:mid AND status='draft'", ['mid'=>$mid]
        )->fetchColumn();

        $completedYear = (int) dbq(
            "SELECT COUNT(*) FROM events WHERE municipality_id=:mid AND status='completed' AND YEAR(start_datetime)=:y",
            ['mid'=>$mid,'y'=>$year]
        )->fetchColumn();

        $totalTeams = (int) dbq(
            "SELECT COUNT(*) FROM volunteer_teams WHERE municipality_id=:mid", ['mid'=>$mid]
        )->fetchColumn();

        $activeTeams = (int) dbq(
            "SELECT COUNT(*) FROM volunteer_teams WHERE municipality_id=:mid AND status='active'", ['mid'=>$mid]
        )->fetchColumn();

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

        /* ── Application approval rate ─────────────── */
        $totalApps = (int) dbq(
            "SELECT COUNT(*) FROM event_applications WHERE municipality_id=:mid AND status IN ('approved','rejected')",
            ['mid'=>$mid]
        )->fetchColumn();
        $approvedApps = (int) dbq(
            "SELECT COUNT(*) FROM event_applications WHERE municipality_id=:mid AND status='approved'",
            ['mid'=>$mid]
        )->fetchColumn();
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

        /* ── Monthly event trend (last 6 months) ─── */
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts  = mktime(0,0,0,$month-$i,1,$year);
            $m   = (int) date('n', $ts);
            $y   = (int) date('Y', $ts);
            $cnt = (int) dbq(
                "SELECT COUNT(*) FROM events WHERE municipality_id=:mid AND YEAR(start_datetime)=:y AND MONTH(start_datetime)=:m",
                ['mid'=>$mid,'y'=>$y,'m'=>$m]
            )->fetchColumn();
            $monthlyTrend[] = [
                'label' => greek_month_short($m),
                'count' => $cnt,
            ];
        }

        /* ── Events by status (this year) ────────── */
        $byStatus = [];
        foreach (['draft','open','review','confirmed','active','completed','cancelled'] as $st) {
            $byStatus[$st] = (int) dbq(
                "SELECT COUNT(*) FROM events WHERE municipality_id=:mid AND status=:s AND YEAR(start_datetime)=:y",
                ['mid'=>$mid,'s'=>$st,'y'=>$year]
            )->fetchColumn();
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

        /* ── Overview (for global ώρες εθελοντισμού) */
        $overview = StatsService::municipalityOverview($mid, $year);

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
            'year'                => $year,
        ]);
    }
}
