<?php
/**
 * SynDrasi - Reports page and CSV exports (UTF-8 BOM).
 */
class ReportController
{
    private function terms(): array
    {
        $ctx = authority_context(current_municipality_id());
        $ctx['event_singular_lc'] = mb_strtolower($ctx['event_singular'] ?? 'Δράση', 'UTF-8');
        return $ctx;
    }

    public function index()
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $events = dbq(
            "SELECT e.id, e.title, e.start_datetime, e.status FROM events e
             WHERE e.municipality_id = :mid
             ORDER BY e.start_datetime DESC LIMIT 100",
            ['mid' => current_municipality_id()]
        )->fetchAll();
        render('reports/index', ['pageTitle' => 'Αναφορές', 'events' => $events]);
    }

    public function exportEvents()
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $events = Event::forMunicipality(current_municipality_id(), []);
        $rows = [];
        foreach ($events as $e) {
            $rows[] = [
                $e['id'], $e['title'], $e['category_name'], greek_status($e['status']),
                gr_datetime($e['start_datetime']), gr_datetime($e['end_datetime']),
                $e['location_name'], $e['requested_people'], $e['applications_count'],
            ];
        }
        audit('export', 'events', null, 'events.csv');
        CsvService::download('syndrasi-events.csv',
            ['ID', 'Τίτλος', 'Κατηγορία', 'Κατάσταση', 'Έναρξη', 'Λήξη', 'Τοποθεσία', 'Ζητούμενα άτομα', 'Δηλώσεις'],
            $rows
        );
    }

    public function exportEventApplications($eventId)
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $event = Event::findForCurrent($eventId);
        $terms = $this->terms();
        $apps = EventApplication::forEvent($event['id']);
        $rows = [];
        foreach ($apps as $a) {
            $rows[] = [
                $a['team_name'], greek_status($a['status']), $a['offered_people'],
                $a['approved_people'] !== null ? $a['approved_people'] : '',
                $a['offered_vehicle'] ? 'Ναι' : 'Όχι',
                $a['offered_medical_equipment'] ? 'Ναι' : 'Όχι',
                gr_datetime($a['submitted_at']), $a['comment'], $a['admin_comment'],
            ];
        }
        audit('export', 'event_applications', $event['id']);
        CsvService::download('syndrasi-event-' . $event['id'] . '-applications.csv',
            ['Ομάδα', 'Κατάσταση', 'Προσφερόμενα άτομα', 'Εγκεκριμένα άτομα', 'Όχημα', 'Υγειονομικός εξοπλισμός', 'Υποβλήθηκε', 'Σχόλιο ομάδας', 'Σχόλιο ' . ($terms['short_name'] ?? 'φορέα')],
            $rows
        );
    }

    public function exportEventCoverage($eventId)
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $event = Event::findForCurrent($eventId);
        $rows = [];
        $teams = dbq(
            "SELECT t.name AS team_name, ea.approved_people,
                    ci.status AS checkin_status, ci.present_people, ci.checked_in_at,
                    lp.created_at AS last_ping_at
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             LEFT JOIN (
                SELECT oc1.* FROM operational_checkins oc1
                JOIN (SELECT event_id, team_id, MAX(id) AS last_id FROM operational_checkins GROUP BY event_id, team_id) x
                  ON x.last_id = oc1.id
             ) ci ON ci.event_id = ea.event_id AND ci.team_id = ea.team_id
             LEFT JOIN (
                SELECT lp1.* FROM location_pings lp1
                JOIN (SELECT event_id, team_id, MAX(id) AS last_id FROM location_pings GROUP BY event_id, team_id) y
                  ON y.last_id = lp1.id
             ) lp ON lp.event_id = ea.event_id AND lp.team_id = ea.team_id
             WHERE ea.event_id = :eid AND ea.status = 'approved'
             ORDER BY t.name",
            ['eid' => $event['id']]
        )->fetchAll();
        foreach ($teams as $t) {
            $rows[] = [
                $t['team_name'], $t['approved_people'],
                $t['checkin_status'] ? greek_status($t['checkin_status']) : 'Χωρίς δήλωση',
                $t['present_people'] !== null ? $t['present_people'] : '',
                gr_datetime($t['checked_in_at']), gr_datetime($t['last_ping_at']),
            ];
        }
        audit('export', 'event_coverage', $event['id']);
        CsvService::download('syndrasi-event-' . $event['id'] . '-coverage.csv',
            ['Ομάδα', 'Εγκεκριμένα άτομα', 'Κατάσταση παρουσίας', 'Παρόντα άτομα', 'Δήλωση παρουσίας', 'Τελευταίο στίγμα'],
            $rows
        );
    }

    public function exportTeamStatistics()
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $terms = $this->terms();
        $eventPlural = $terms['event_plural'] ?? 'Δράσεις';
        $ranking = StatsService::teamRanking(current_municipality_id(), $year);
        $rows = [];
        foreach ($ranking as $i => $r) {
            $rows[] = [
                $i + 1, $r['team_name'], $r['team_type'], $r['events_count'],
                str_replace('.', ',', (string) $r['volunteer_hours']),
                $r['present_volunteers'],
                $r['consistency_score'] !== null ? str_replace('.', ',', (string) $r['consistency_score']) . '%' : '',
                $r['avg_response_minutes'] !== null ? $r['avg_response_minutes'] : '',
            ];
        }
        audit('export', 'team_statistics', null, 'year: ' . $year);
        CsvService::download('syndrasi-team-statistics-' . $year . '.csv',
            ['#', 'Ομάδα', 'Τύπος', $eventPlural, 'Ώρες εθελοντισμού', 'Συμμετοχές εθελοντών', 'Συνέπεια', 'Μέση απόκριση (λεπτά)'],
            $rows
        );
    }

    public function exportMunicipalityStatistics()
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $terms = $this->terms();
        $o = StatsService::municipalityOverview(current_municipality_id(), $year);
        $rows = [
            ['Έτος', $o['year']],
            [($terms['event_plural'] ?? 'Δράσεις') . ' με κάλυψη', $o['events_with_coverage']],
            ['Ενεργές εθελοντικές ομάδες', $o['active_teams']],
            ['Συνολικές συμμετοχές εθελοντών', $o['participations']],
            ['Συνολικές ώρες εθελοντισμού', str_replace('.', ',', (string) $o['volunteer_hours'])],
            ['Μέση απόκριση ομάδων (λεπτά)', $o['avg_response_minutes'] !== null ? $o['avg_response_minutes'] : '—'],
            ['Εγκεκριμένα άτομα', $o['approved_people']],
            ['Ζητούμενα άτομα', $o['requested_people']],
        ];
        audit('export', 'municipality_statistics', null, 'year: ' . $year);
        CsvService::download('syndrasi-municipality-statistics-' . $year . '.csv', ['Μέγεθος', 'Τιμή'], $rows);
    }

    public function exportAwards()
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $mid  = current_municipality_id();
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $terms = $this->terms();
        $eventPlural = $terms['event_plural'] ?? 'Δράσεις';
        $eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
        $s    = MunicipalitySetting::all($mid);
        $thresholds = [
            'bronze_events' => isset($s['award_bronze_events']) ? (int) $s['award_bronze_events'] : 5,
            'silver_events' => isset($s['award_silver_events']) ? (int) $s['award_silver_events'] : 10,
            'gold_events'   => isset($s['award_gold_events'])   ? (int) $s['award_gold_events']   : 20,
            'min_events'    => isset($s['award_min_events'])    ? (int) $s['award_min_events']    : 3,
        ];
        $awards = StatsService::awards($mid, $year, $thresholds);
        $rows = [];
        $labels = [
            'best_contribution' => 'Καλύτερη Προσφορά (ώρες εθελοντισμού)',
            'most_active'       => 'Πιο Δραστήρια Ομάδα (αριθμός ' . $eventPluralLc . ')',
            'most_consistent'   => 'Μεγαλύτερη Συνέπεια',
            'fastest_response'  => 'Ταχύτερη Απόκριση',
        ];
        foreach ($labels as $key => $label) {
            $w = $awards[$key];
            $rows[] = [
                $label,
                $w ? $w['team_name'] : '—',
                $w ? $w['events_count'] : '',
                $w ? str_replace('.', ',', (string) $w['volunteer_hours']) : '',
            ];
        }
        $rows[] = [];
        $rows[] = ['Πλήρης κατάταξη', '', '', ''];
        foreach ($awards['ranking'] as $i => $r) {
            $rows[] = [($i+1).'. '.$r['team_name'], $r['events_count'] . ' ' . $eventPluralLc, str_replace('.', ',', (string)$r['volunteer_hours']).' ώρες', ''];
        }
        audit('export', 'awards', null, 'year: '.$year);
        CsvService::download('syndrasi-awards-'.$year.'.csv', ['Βραβείο','Ομάδα',$eventPlural,'Ώρες'], $rows);
    }

    /* ─── PDF print views (standalone HTML, render with $withLayout=false) ─ */

    public function pdfCoverage($eventId)
    {
        requireRole([Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]);
        $event = Event::findForCurrent($eventId);
        $mid   = current_municipality_id();
        $mun   = Municipality::find($mid);
        $s     = MunicipalitySetting::all($mid);
        $logo  = $s['branding_logo_url'] ?? null;
        $teams = dbq(
            "SELECT t.name AS team_name, t.type AS team_type,
                    ea.approved_people, ea.offered_vehicle, ea.offered_medical_equipment,
                    ci.status AS checkin_status, ci.present_people, ci.checked_in_at,
                    lp.created_at AS last_ping_at
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id = ea.team_id
             LEFT JOIN (
               SELECT oc1.* FROM operational_checkins oc1
               JOIN (SELECT event_id,team_id,MAX(id) lid FROM operational_checkins GROUP BY event_id,team_id) x ON x.lid=oc1.id
             ) ci ON ci.event_id=ea.event_id AND ci.team_id=ea.team_id
             LEFT JOIN (
               SELECT lp1.* FROM location_pings lp1
               JOIN (SELECT event_id,team_id,MAX(id) lid FROM location_pings GROUP BY event_id,team_id) y ON y.lid=lp1.id
             ) lp ON lp.event_id=ea.event_id AND lp.team_id=ea.team_id
             WHERE ea.event_id=:eid AND ea.status='approved' ORDER BY t.name",
            ['eid'=>$event['id']]
        )->fetchAll();
        $shortages = dbq(
            "SELECT sr.*, t.name AS team_name FROM shortage_reports sr
             JOIN volunteer_teams t ON t.id=sr.team_id
             WHERE sr.event_id=:eid ORDER BY sr.created_at",
            ['eid'=>$event['id']]
        )->fetchAll();
        $totalApproved = array_sum(array_column($teams,'approved_people'));
        $totalPresent  = array_sum(array_filter(array_column($teams,'present_people'),fn($v)=>$v!==null));
        $durationH     = round((strtotime($event['end_datetime'])-strtotime($event['start_datetime']))/3600,1);
        audit('pdf_coverage','event',(int)$eventId);
        render('reports/pdf_coverage',[
            'pageTitle'=>'Αναφορά Κάλυψης — '.$event['title'],
            'event'=>$event,'mun'=>$mun,'logo'=>$logo,'teams'=>$teams,
            'shortages'=>$shortages,'totalApproved'=>$totalApproved,
            'totalPresent'=>$totalPresent,'durationH'=>$durationH,
        ],false);
    }

    public function pdfCertificate($eventId)
    {
        requireRole([Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]);
        $event = Event::findForCurrent($eventId);
        $mid   = current_municipality_id();
        $mun   = Municipality::find($mid);
        $s     = MunicipalitySetting::all($mid);
        $logo  = $s['branding_logo_url'] ?? null;
        $teams = dbq(
            "SELECT t.name AS team_name, t.type AS team_type, ea.approved_people,
                    ci.present_people, ci.checked_in_at
             FROM event_applications ea
             JOIN volunteer_teams t ON t.id=ea.team_id
             LEFT JOIN (
               SELECT oc1.* FROM operational_checkins oc1
               JOIN (SELECT event_id,team_id,MAX(id) lid FROM operational_checkins GROUP BY event_id,team_id) x ON x.lid=oc1.id
             ) ci ON ci.event_id=ea.event_id AND ci.team_id=ea.team_id
             WHERE ea.event_id=:eid AND ea.status='approved' ORDER BY t.name",
            ['eid'=>$event['id']]
        )->fetchAll();
        $durationH = round((strtotime($event['end_datetime'])-strtotime($event['start_datetime']))/3600,1);
        audit('pdf_certificate','event',(int)$eventId);
        render('reports/pdf_certificate',[
            'pageTitle'=>'Πιστοποιητικά Συμμετοχής — '.$event['title'],
            'event'=>$event,'mun'=>$mun,'logo'=>$logo,
            'teams'=>$teams,'durationH'=>$durationH,
        ],false);
    }

    public function pdfAwards($year)
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $year = (int)$year;
        if ($year < 2020 || $year > 2040) { abort(400,'Μη έγκυρο έτος.'); }
        $mid  = current_municipality_id();
        $mun  = Municipality::find($mid);
        $s    = MunicipalitySetting::all($mid);
        $logo = $s['branding_logo_url'] ?? null;
        $thresholds = [
            'bronze_events' => (int)($s['award_bronze_events'] ?? 5),
            'silver_events' => (int)($s['award_silver_events'] ?? 10),
            'gold_events'   => (int)($s['award_gold_events']   ?? 20),
            'min_events'    => (int)($s['award_min_events']    ?? 3),
        ];
        $awards  = StatsService::awards($mid,$year,$thresholds);
        $ranking = StatsService::teamRanking($mid,$year);
        audit('pdf_awards',null,null,'year: '.$year);
        render('reports/pdf_awards',[
            'pageTitle'=>'Επιβράβευση Εθελοντισμού '.$year,
            'mun'=>$mun,'logo'=>$logo,'year'=>$year,
            'awards'=>$awards,'ranking'=>$ranking,
        ],false);
    }

    /** GET /reports/pdf/annual/{year} — full-year branded PDF for city council. */
    public function pdfAnnual($year)
    {
        requireRole([Role::MUNICIPALITY_ADMIN]);
        $year = (int) $year;
        if ($year < 2020 || $year > 2040) { abort(400, 'Μη έγκυρο έτος.'); }
        $mid  = current_municipality_id();
        $mun  = Municipality::find($mid);
        $s    = MunicipalitySetting::all($mid);
        $logo = $s['branding_logo_url'] ?? null;

        // All completed + archived (cancelled = archived) events this year
        $events = dbq(
            "SELECT e.*, ec.name AS category_name,
                    COUNT(DISTINCT ea.id)   AS team_count,
                    SUM(ea.actual_people)   AS actual_people_sum,
                    SUM(ea.approved_people) AS approved_people_sum
             FROM events e
             LEFT JOIN event_categories ec ON ec.id = e.category_id
             LEFT JOIN event_applications ea ON ea.event_id = e.id AND ea.status = 'approved'
             WHERE e.municipality_id = :mid
               AND e.status IN ('completed','cancelled')
               AND YEAR(e.start_datetime) = :yr
             GROUP BY e.id
             ORDER BY e.start_datetime ASC",
            ['mid' => $mid, 'yr' => $year]
        )->fetchAll();

        // Totals
        $totalEvents    = count($events);
        $totalTeamSlots = (int) array_sum(array_column($events, 'team_count'));
        $totalPeople    = (int) array_sum(array_column($events, 'actual_people_sum'));

        // Total volunteer hours from participations
        $totalHours = (float) dbq(
            "SELECT COALESCE(SUM(vp.hours), 0)
             FROM volunteer_participations vp
             JOIN events e ON e.id = vp.event_id
             WHERE e.municipality_id = :mid AND YEAR(e.start_datetime) = :yr",
            ['mid' => $mid, 'yr' => $year]
        )->fetchColumn();

        // Monthly event count
        $monthlyRaw = dbq(
            "SELECT MONTH(start_datetime) AS m, COUNT(*) AS cnt
             FROM events
             WHERE municipality_id = :mid AND YEAR(start_datetime) = :yr
               AND status IN ('completed','cancelled')
             GROUP BY MONTH(start_datetime)
             ORDER BY m ASC",
            ['mid' => $mid, 'yr' => $year]
        )->fetchAll();
        $monthly = array_fill(1, 12, 0);
        foreach ($monthlyRaw as $row) { $monthly[(int)$row['m']] = (int)$row['cnt']; }

        // Category breakdown
        $byCategory = dbq(
            "SELECT ec.name AS category_name, COUNT(e.id) AS cnt
             FROM events e
             LEFT JOIN event_categories ec ON ec.id = e.category_id
             WHERE e.municipality_id = :mid AND YEAR(e.start_datetime) = :yr
               AND e.status IN ('completed','cancelled')
             GROUP BY e.category_id
             ORDER BY cnt DESC",
            ['mid' => $mid, 'yr' => $year]
        )->fetchAll();

        // Team leaderboard for the year
        $teamLeaderboard = dbq(
            "SELECT vt.name AS team_name,
                    COUNT(DISTINCT vp.event_id) AS events_attended,
                    COALESCE(SUM(vp.hours), 0)  AS total_hours,
                    SUM(vp.was_present)          AS member_presences
             FROM volunteer_participations vp
             JOIN volunteer_teams vt ON vt.id = vp.team_id
             JOIN events e ON e.id = vp.event_id
             WHERE e.municipality_id = :mid AND YEAR(e.start_datetime) = :yr
             GROUP BY vp.team_id
             ORDER BY total_hours DESC
             LIMIT 20",
            ['mid' => $mid, 'yr' => $year]
        )->fetchAll();

        audit('pdf_annual', null, null, 'year: ' . $year);

        render('reports/pdf_annual', [
            'pageTitle'       => 'Ετήσια Έκθεση Εθελοντισμού ' . $year,
            'mun'             => $mun,
            'logo'            => $logo,
            'year'            => $year,
            'events'          => $events,
            'totalEvents'     => $totalEvents,
            'totalTeamSlots'  => $totalTeamSlots,
            'totalPeople'     => $totalPeople,
            'totalHours'      => $totalHours,
            'monthly'         => $monthly,
            'byCategory'      => $byCategory,
            'teamLeaderboard' => $teamLeaderboard,
        ], false);
    }
}
