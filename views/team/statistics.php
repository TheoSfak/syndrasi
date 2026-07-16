<div class="d-flex flex-wrap justify-content-between align-items-center mb-1 gap-2">
  <h1 class="h3 mb-0"><?= e(t('team/statistics.001', 'Στατιστικά Ομάδας')) ?></h1>
  <form method="get" action="<?= e(url('/team/statistics')) ?>" class="d-flex align-items-center gap-2">
    <label class="small text-muted"><?= e(t('team/statistics.002', 'Έτος')) ?></label>
    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
        <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </form>
</div>
<p class="text-muted"><?= e($team['name']) ?> <?= e(t('team/statistics.003', '— Η προσφορά της ομάδας σας σε αριθμούς.')) ?></p>

<?php include BASE_PATH . '/views/statistics/_team_stats.php'; ?>
