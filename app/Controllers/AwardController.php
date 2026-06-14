<?php
/**
 * SynDrasi - Recognition / Awards page (Επιβράβευση Ομάδων).
 */
class AwardController
{
    public function index()
    {
        requireRole(['municipality_admin']);
        $mid  = current_municipality_id();
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $s    = MunicipalitySetting::all($mid);
        $thresholds = [
            'bronze_events' => isset($s['award_bronze_events']) ? (int) $s['award_bronze_events'] : 5,
            'silver_events' => isset($s['award_silver_events']) ? (int) $s['award_silver_events'] : 10,
            'gold_events'   => isset($s['award_gold_events'])   ? (int) $s['award_gold_events']   : 20,
            'min_events'    => isset($s['award_min_events'])    ? (int) $s['award_min_events']    : 3,
        ];
        $awards = StatsService::awards($mid, $year, $thresholds);

        render('awards/index', [
            'pageTitle' => 'Επιβράβευση Ομάδων',
            'year'      => $year,
            'awards'    => $awards,
        ]);
    }
}
