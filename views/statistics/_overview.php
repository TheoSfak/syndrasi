<div class="row g-3 mb-4">
  <div class="col-6 col-lg-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= (int) $overview['events_with_coverage'] ?></div>
      <div class="text-muted small">Δράσεις με κάλυψη</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= (int) $overview['active_teams'] ?></div>
      <div class="text-muted small">Ενεργές ομάδες</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= (int) $overview['participations'] ?></div>
      <div class="text-muted small">Συμμετοχές εθελοντών</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= e(gr_number($overview['volunteer_hours'])) ?></div>
      <div class="text-muted small">Ώρες εθελοντισμού</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= $overview['avg_response_minutes'] !== null ? e(gr_number($overview['avg_response_minutes'])) . '′' : '—' ?></div>
      <div class="text-muted small">Μέση απόκριση ομάδων</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-value"><?= (int) $overview['approved_people'] ?>/<?= (int) $overview['requested_people'] ?></div>
      <div class="text-muted small">Εγκεκριμένα / ζητούμενα άτομα</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Δράσεις ανά μήνα</div>
      <div class="card-body">
        <canvas id="eventsByMonthChart" height="120"
                data-values="<?= e(json_encode(array_values($byMonth))) ?>"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Δράσεις ανά κατηγορία</div>
      <div class="card-body">
        <?php if (!$catYear): ?>
          <div class="text-muted">Δεν υπάρχουν ολοκληρωμένες δράσεις για το <?= (int) $year ?>.</div>
        <?php else: ?>
          <canvas id="eventsByCategoryChart" height="220"
                  data-labels="<?= e(json_encode(array_column($catYear, 'category'), JSON_UNESCAPED_UNICODE)) ?>"
                  data-values="<?= e(json_encode(array_map('intval', array_column($catYear, 'total')))) ?>"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-trophy me-1"></i> Κατάταξη ομάδων <?= (int) $year ?></span>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/exports/team-statistics?year=' . $year)) ?>">
      <i class="bi bi-download me-1"></i>Εξαγωγή CSV
    </a>
  </div>
  <?php if (!$ranking): ?>
    <div class="card-body text-muted">Δεν υπάρχουν δεδομένα ομάδων.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>#</th><th>Ομάδα</th><th>Δράσεις</th><th>Ώρες εθελοντισμού</th><th>Συμμετοχές</th><th>Συνέπεια</th><th>Μέση απόκριση</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($ranking as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= e($r['team_name']) ?></strong> <span class="text-muted small"><?= e($r['team_type'] ?: '') ?></span></td>
              <td><?= (int) $r['events_count'] ?></td>
              <td><?= e(gr_number($r['volunteer_hours'], 1)) ?></td>
              <td><?= (int) $r['present_volunteers'] ?></td>
              <td><?= $r['consistency_score'] !== null ? e(gr_number($r['consistency_score'], 1)) . '%' : '—' ?></td>
              <td><?= $r['avg_response_minutes'] !== null ? e(gr_number($r['avg_response_minutes'])) . '′' : '—' ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/statistics/teams/' . $r['team_id'] . '?year=' . $year)) ?>">Αναλυτικά</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
