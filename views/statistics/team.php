<div class="d-flex flex-wrap justify-content-between align-items-center mb-1 gap-2">
  <h1 class="h3 mb-0"><?= e(t('statistics/team.001', 'Στατιστικά:')) ?> <?= e($team['name']) ?></h1>
  <form method="get" action="<?= e(url('/statistics/teams/' . $team['id'])) ?>" class="d-flex align-items-center gap-2">
    <label class="small text-muted"><?= e(t('statistics/team.002', 'Έτος')) ?></label>
    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
        <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </form>
</div>
<p class="text-muted"><?= e($team['type'] ?: '') ?><?= $team['contact_person'] ? ' · ' . e($team['contact_person']) : '' ?></p>

<?php include BASE_PATH . '/views/statistics/_team_stats.php'; ?>

<div class="mt-3">
  <a class="btn btn-outline-secondary" href="<?= e(url('/statistics?year=' . $year)) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(t('statistics/team.003', 'Πίσω στα στατιστικά')) ?></a>
</div>
