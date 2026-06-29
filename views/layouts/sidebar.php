<?php
$role = current_role();
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$menus = [];
if ($role === 'municipality_admin') {
    $menus = [
        ['/dashboard',    'bi-speedometer2',              'Πίνακας Ελέγχου'],
        ['/mobilizations', 'bi-broadcast-pin',            'Κάλεσμα Έκτακτης Ανάγκης'],
        ['/operations',   'bi-broadcast',                 'Κέντρο Επιχειρήσεων'],
        ['/operations/war-room', 'bi-diagram-3',          'Κέντρο Συντονισμού'],
        ['/fire-service', 'bi-fire',                       'Συμβάντα Πυροσβεστικής'],
        ['/events',       'bi-calendar-event',            'Δράσεις'],
        ['/events/calendar', 'bi-calendar3',              'Ημερολόγιο'],
        ['/teams',        'bi-people',                    'Εθελοντικές Ομάδες'],
        ['/applications', 'bi-inbox',                     'Δηλώσεις Συμμετοχής'],
        ['/statistics',   'bi-bar-chart',                 'Στατιστικά & Τάσεις'],
        ['/awards',       'bi-trophy',                    'Επιβράβευση Ομάδων'],
        ['/reports',      'bi-file-earmark-arrow-down',   'Αναφορές'],
        ['/settings',     'bi-gear',                      'Ρυθμίσεις'],
    ];
} elseif ($role === 'event_operator') {
    $menus = [
        ['/dashboard',  'bi-speedometer2',  'Πίνακας Ελέγχου'],
        ['/mobilizations', 'bi-broadcast-pin', 'Κάλεσμα Έκτακτης Ανάγκης'],
        ['/operations', 'bi-broadcast',     'Κέντρο Επιχειρήσεων'],
        ['/operations/war-room', 'bi-diagram-3', 'Κέντρο Συντονισμού'],
        ['/events',     'bi-calendar-event','Δράσεις'],
    ];
} elseif ($role === 'team_admin') {
    $menus = [
        ['/team/dashboard', 'bi-speedometer2', 'Πίνακας Ελέγχου'],
        ['/team/events', 'bi-calendar-event', 'Δράσεις'],
        ['/team/applications', 'bi-inbox', 'Οι Δηλώσεις μας'],
        ['/team/members', 'bi-people', 'Μέλη Ομάδας'],
        ['/team/statistics', 'bi-bar-chart', 'Στατιστικά Ομάδας'],
    ];
} elseif ($role === 'super_admin') {
    $menus = [
        ['/admin/dashboard', 'bi-speedometer2', 'Πίνακας Ελέγχου'],
        ['/admin/municipalities', 'bi-building', 'Δήμοι'],
        ['/admin/users', 'bi-people', 'Χρήστες'],
        ['/admin/settings', 'bi-gear', 'Ρυθμίσεις'],
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
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Κλείσιμο"></button>
  </div>

  <?php if (!empty($sidebarLogoUrl)): ?>
  <div class="text-center py-3 px-3">
    <img src="<?= e($sidebarLogoUrl) ?>" alt="<?= e(MunicipalitySetting::get($mid ?? 0, 'org_name', 'Λογότυπο Δήμου')) ?>" style="max-width:100%;max-height:64px;object-fit:contain;">
  </div>
  <?php endif; ?>

  <div class="sidebar-label">Μενού</div>

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
