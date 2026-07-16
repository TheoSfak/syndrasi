<?php
/* Shared team statistics block. Expects: $team, $stats, $history, $year */
$terms = authority_context(current_municipality_id());
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= (int) $stats['events_count'] ?></div>
      <div class="text-muted small"><?= e($eventPlural) ?> <?= (int) $year ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= e(gr_number($stats['volunteer_hours'])) ?></div>
      <div class="text-muted small"><?= e(t('statistics/_team_stats.001', 'Ώρες παρουσίας')) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= (int) $stats['present_volunteers'] ?></div>
      <div class="text-muted small"><?= e(t('statistics/_team_stats.002', 'Συνολικές συμμετοχές εθελοντών')) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= $stats['consistency_score'] !== null ? e(gr_number($stats['consistency_score'], 1)) . '%' : '—' ?></div>
      <div class="text-muted small"><?= e(t('statistics/_team_stats.003', 'Δείκτης συνέπειας')) ?></div>
    </div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><?= e(t('statistics/_team_stats.004', 'Κατηγορίες')) ?> <?= e($eventPluralLc) ?></div>
      <?php if (!$stats['categories']): ?>
        <div class="card-body text-muted"><?= e(t('statistics/_team_stats.017', 'Δεν υπάρχουν ολοκληρωμένες')) ?> <?= e($eventPluralLc) ?> <?= e(t('statistics/_team_stats.018', 'για το')) ?> <?= (int) $year ?>.</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($stats['categories'] as $c): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?= e($c['category']) ?></span>
              <span class="fw-semibold"><?= (int) $c['total'] ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><?= e(t('statistics/_team_stats.006', 'Δείκτες')) ?></div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between">
          <span><?= e(t('statistics/_team_stats.007', 'Εγκεκριμένοι εθελοντές')) ?></span><span class="fw-semibold"><?= (int) $stats['approved_volunteers'] ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span><?= e(t('statistics/_team_stats.008', 'Μέση ταχύτητα απόκρισης')) ?></span>
          <span class="fw-semibold"><?= $stats['avg_response_minutes'] !== null ? e(gr_number($stats['avg_response_minutes'])) . ' λεπτά' : '—' ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span><?= e(t('statistics/_team_stats.009', 'Αναφορές έλλειψης')) ?></span><span class="fw-semibold"><?= (int) $stats['shortage_reports'] ?></span>
        </li>
      </ul>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><?= e(t('statistics/_team_stats.010', 'Ιστορικό')) ?> <?= e($eventPluralLc) ?> <?= (int) $year ?></div>
      <?php if (!$history): ?>
        <div class="card-body text-muted"><?= e(t('statistics/_team_stats.017', 'Δεν υπάρχουν ολοκληρωμένες')) ?> <?= e($eventPluralLc) ?> <?= e(t('statistics/_team_stats.019', 'για αυτό το έτος.')) ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th><?= e($eventSingular) ?></th><th><?= e(t('statistics/_team_stats.012', 'Ημερομηνία')) ?></th><th><?= e(t('statistics/_team_stats.013', 'Κατηγορία')) ?></th><th><?= e(t('statistics/_team_stats.014', 'Εγκεκριμένα')) ?></th><th><?= e(t('statistics/_team_stats.015', 'Παρόντα')) ?></th><th><?= e(t('statistics/_team_stats.016', 'Παρουσία')) ?></th></tr></thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= e($h['title']) ?></td>
                  <td><?= e(gr_date($h['start_datetime'])) ?></td>
                  <td><?= e($h['category_name'] ?: '—') ?></td>
                  <td><?= (int) $h['approved_people'] ?></td>
                  <td><?= $h['present_people'] !== null ? (int) $h['present_people'] : '—' ?></td>
                  <td><?= $h['checkin_status'] ? status_badge($h['checkin_status']) : '<span class="text-muted small">Χωρίς δήλωση</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
