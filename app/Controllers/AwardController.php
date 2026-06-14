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
            'silver_events' => isset($s['award