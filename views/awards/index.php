<?php
$terms = authority_context(current_municipality_id());
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-1 gap-2">
  <h1 class="h3 mb-0"><?= e(t('awards/index.001', 'Επιβράβευση Ομάδων')) ?></h1>
  <div class="d-flex align-items-center gap-2">
    <form method="get" action="<?= e(url('/awards')) ?>" class="d-flex align-items-center gap-2">
      <label class="small text-muted"><?= e(t('awards/index.002', 'Έτος')) ?></label>
      <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
          <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </form>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/exports/awards?year=' . $year)) ?>">
      <i class="bi bi-download me-1"></i><?= e(t('awards/index.003', 'Εξαγωγή CSV')) ?>
    </a>
  </div>
</div>
<p class="text-muted"><?= e(t('awards/index.004', 'Αυτόματη ετήσια κατάταξη και αναγνώριση της προσφοράς των εθελοντικών ομάδων.')) ?></p>

<?php
$cards = [
    ['key' => 'best_contribution', 'icon' => 'bi-heart-fill', 'color' => 'danger', 'title' => 'Καλύτερη Προσφορά', 'metric' => function ($w) { return gr_number($w['volunteer_hours'], 1) . ' ώρες εθελοντισμού'; }],
    ['key' => 'most_active', 'icon' => 'bi-lightning-fill', 'color' => 'warning', 'title' => 'Πιο Δραστήρια Ομάδα', 'metric' => function ($w) use ($eventPluralLc) { return $w['events_count'] . ' ' . $eventPluralLc; }],
    ['key' => 'most_consistent', 'icon' => 'bi-check-circle-fill', 'color' => 'success', 'title' => 'Μεγαλύτερη Συνέπεια', 'metric' => function ($w) { return gr_number($w['consistency_score'], 1) . '% συνέπεια'; }],
    ['key' => 'fastest_response', 'icon' => 'bi-stopwatch-fill', 'color' => 'info', 'title' => 'Ταχύτερη Απόκριση', 'metric' => function ($w) { return gr_number($w['avg_response_minutes']) . ' λεπτά μέση απόκριση'; }],
];
?>

<div class="row g-3 mb-4">
  <?php foreach ($cards as $c): $w = $awards[$c['key']]; ?>
    <div class="col-md-6 col-xl-3">
      <div class="card award-card shadow-sm h-100 text-center">
        <div class="card-body">
          <div class="award-icon text-<?= e($c['color']) ?> mb-2"><i class="bi <?= e($c['icon']) ?>"></i></div>
          <h2 class="h6 text-muted mb-2"><?= e($c['title']) ?></h2>
          <?php if ($w): ?>
            <div class="fw-bold"><?= e($w['team_name']) ?></div>
            <div class="small text-muted mt-1"><?= e($c['metric']($w)) ?></div>
          <?php else: ?>
            <div class="text-muted"><?= e(t('awards/index.005', '— Δεν υπάρχουν επαρκή δεδομένα —')) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm">
  <?php $t = $awards['thresholds']; ?>
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-list-ol me-1"></i> <?= e(t('awards/index.006', 'Πλήρης κατάταξη')) ?> <?= (int) $year ?></span>
    <span class="small text-muted fw-normal">
      🥉 <?= (int) $t['bronze_events'] ?>+ <?= e($eventPluralLc) ?> &nbsp;
      🥈 <?= (int) $t['silver_events'] ?>+ &nbsp;
      🥇 <?= (int) $t['gold_events'] ?>+
    </span>
  </div>
  <?php if (!$awards['ranking']): ?>
    <div class="card-body text-muted"><?= e(t('awards/index.007', 'Δεν υπάρχουν δεδομένα ομάδων για αυτό το έτος.')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th><?= e(t('awards/index.008', 'Ομάδα')) ?></th><th><?= e($eventPlural) ?></th><th><?= e(t('awards/index.009', 'Ώρες εθελοντισμού')) ?></th><th><?= e(t('awards/index.010', 'Συνέπεια')) ?></th><th><?= e(t('awards/index.011', 'Μέση απόκριση')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($awards['ranking'] as $i => $r): ?>
            <tr>
              <td>
                <?php if ($i === 0): ?><i class="bi bi-trophy-fill text-warning"></i>
                <?php elseif ($i === 1): ?><i class="bi bi-trophy text-secondary"></i>
                <?php elseif ($i === 2): ?><i class="bi bi-trophy text-danger"></i>
                <?php else: ?><?= $i + 1 ?><?php endif; ?>
              </td>
              <td>
                <strong><?= e($r['team_name']) ?></strong>
                <span class="text-muted small"><?= e($r['team_type'] ?: '') ?></span>
                <?php if ($r['tier'] === 'gold'):   ?><span title="<?= e(t('awards/index.012', 'Χρυσή συμμετοχή')) ?>">🥇</span>
                <?php elseif ($r['tier'] === 'silver'): ?><span title="<?= e(t('awards/index.013', 'Ασημένια συμμετοχή')) ?>">🥈</span>
                <?php elseif ($r['tier'] === 'bronze'): ?><span title="<?= e(t('awards/index.014', 'Χάλκινη συμμετοχή')) ?>">🥉</span>
                <?php endif; ?>
              </td>
              <td><?= (int) $r['events_count'] ?></td>
              <td><?= e(gr_number($r['volunteer_hours'], 1)) ?></td>
              <td><?= $r['consistency_score'] !== null ? e(gr_number($r['consistency_score'], 1)) . '%' : '—' ?></td>
              <td><?= $r['avg_response_minutes'] !== null ? e(gr_number($r['avg_response_minutes'])) . '′' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
