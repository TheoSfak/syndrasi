<?php
$role = current_role();
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$midForTerms = function_exists('current_municipality_id') ? current_municipality_id() : null;
$authorityTerms = $midForTerms ? authority_context($midForTerms) : authority_defaults('municipality');
$authorityTypeForMenu = $authorityTerms['authority_type'] ?? 'municipality';
$eventMenuLabel = t('authority/' . $authorityTypeForMenu . '.event_plural', $authorityTerms['event_plural'] ?? 'Δράσεις');
$teamMenuLabel = t('authority/' . $authorityTypeForMenu . '.team_plural', $authorityTerms['team_plural'] ?? 'Εθελοντικές Ομάδες');

$menus = [];
if ($role === 'municipality_admin') {
    $menus = [
        ['/dashboard',    'bi-speedometer2',              t('layouts/sidebar.003', 'Πίνακας Ελέγχου')],
        ['/mobilizations', 'bi-broadcast-pin',            t('layouts/sidebar.004', 'Κάλεσμα Έκτακτης Ανάγκης')],
        ['/operations',   'bi-broadcast',                 t('layouts/sidebar.005', 'Κέντρο Επιχειρήσεων')],
        ['/operations/war-room', 'bi-diagram-3',          t('layouts/sidebar.006', 'Κέντρο Συντονισμού')],
        ['/fire-service', 'bi-fire',                       t('layouts/sidebar.007', 'Συμβάντα Πυροσβεστικής')],
        ['/notification-center', 'bi-bell-fill',           t('layouts/sidebar.008', 'Έλεγχος Ειδοποιήσεων')],
        ['/events',       'bi-calendar-event',            $eventMenuLabel],
        ['/events/calendar', 'bi-calendar3',              t('layouts/sidebar.009', 'Ημερολόγιο')],
        ['/teams',        'bi-people',                    $teamMenuLabel],
        ['/applications', 'bi-inbox',                     t('layouts/sidebar.010', 'Δηλώσεις Συμμετοχής')],
        ['/statistics',   'bi-bar-chart',                 t('layouts/sidebar.011', 'Στατιστικά & Τάσεις')],
        ['/awards',       'bi-trophy',                    t('layouts/sidebar.012', 'Επιβράβευση Ομάδων')],
        ['/reports',      'bi-file-earmark-arrow-down',   t('layouts/sidebar.013', 'Αναφορές')],
        ['/settings',     'bi-gear',                      t('layouts/sidebar.014', 'Ρυθμίσεις')],
    ];
} elseif ($role === 'event_operator') {
    $menus = [
        ['/dashboard',  'bi-speedometer2',  t('layouts/sidebar.003', 'Πίνακας Ελέγχου')],
        ['/mobilizations', 'bi-broadcast-pin', t('layouts/sidebar.004', 'Κάλεσμα Έκτακτης Ανάγκης')],
        ['/operations', 'bi-broadcast',     t('layouts/sidebar.005', 'Κέντρο Επιχειρήσεων')],
        ['/operations/war-room', 'bi-diagram-3', t('layouts/sidebar.006', 'Κέντρο Συντονισμού')],
        ['/events',     'bi-calendar-event', $eventMenuLabel],
    ];
} elseif ($role === 'team_admin') {
    $menus = [
        ['/team/dashboard', 'bi-speedometer2', t('layouts/sidebar.003', 'Πίνακας Ελέγχου')],
        ['/team/readiness', 'bi-clipboard2-check', t('layouts/sidebar.015', 'Ετοιμότητα Ομάδας')],
        ['/team/events', 'bi-calendar-event', $eventMenuLabel],
        ['/team/applications', 'bi-inbox', t('layouts/sidebar.016', 'Οι Δηλώσεις μας')],
        ['/team/members', 'bi-people', t('layouts/sidebar.017', 'Μέλη Ομάδας')],
        ['/team/statistics', 'bi-bar-chart', t('layouts/sidebar.018', 'Στατιστικά Ομάδας')],
    ];
} elseif ($role === 'super_admin') {
    $menus = [
        ['/admin/dashboard', 'bi-speedometer2', t('layouts/sidebar.003', 'Πίνακας Ελέγχου')],
        ['/admin/municipalities', 'bi-building', t('layouts/sidebar.019', 'Φορείς')],
        ['/admin/teams', 'bi-shield-check', t('layouts/sidebar.020', 'Ομάδες & Εθελοντές')],
        ['/admin/users', 'bi-people', t('layouts/sidebar.021', 'Χρήστες')],
        ['/admin/settings', 'bi-gear', t('layouts/sidebar.014', 'Ρυθμίσεις')],
    ];
}

$base = base_uri();
function sidebar_active($path, $currentPath, $base)
{
    $full = $base . $path;
    // Exact-match only for dashboards and calendar (to avoid swallowing /events prefix)
    if (in_array($path, ['/dashboard','/team/dashboard','/admin/dashboard','/events/calendar'], true)) {
        return $currentPath === $full;
    }
    return strpos($currentPath, $full) === 0;
}
?>
<div class="offcanvas-lg offcanvas-start col-lg-2 d-lg-block sidebar p-0" tabindex="-1" id="sidebarMenu">
  <?php
  $sidebarLogoUrl = '';
  if (in_array($role, ['municipality_admin', 'event_operator'], true)) {
      $mid = current_municipality_id();
      if ($mid && class_exists('MunicipalitySetting')) {
          $sidebarLogoUrl = MunicipalitySetting::get($mid, 'branding_logo', '');
      }
  }
  ?>

  <div class="offcanvas-header d-lg-none">
    <span class="navbar-brand text-white fw-bold mb-0"><i class="bi bi-people-fill me-1"></i> SynDrasi</span>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="<?= e(t('layouts/sidebar.002', 'Κλείσιμο')) ?>"></button>
  </div>

  <?php if (!empty($sidebarLogoUrl)): ?>
  <div class="text-center py-3 px-3">
    <img src="<?= e($sidebarLogoUrl) ?>" alt="<?= e(MunicipalitySetting::get($mid ?? 0, 'org_name', 'Λογότυπο Φορέα')) ?>" style="max-width:100%;max-height:64px;object-fit:contain;">
  </div>
  <?php endif; ?>

  <div class="sidebar-label"><?= e(t('layouts/sidebar.001', 'Μενού')) ?></div>

  <ul class="nav flex-column mb-auto px-1 pb-4">
    <?php foreach ($menus as $m): ?>
    <li class="nav-item">
      <a href="<?= e(url($m[0])) ?>"
         class="nav-link sidebar-link<?= sidebar_active($m[0], $currentPath, $base) ? ' active' : '' ?>">
        <i class="bi <?= e($m[1]) ?> me-2"></i><?= e($m[2]) ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
