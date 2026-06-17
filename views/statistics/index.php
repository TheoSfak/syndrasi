<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">Στατιστικά &amp; Τάσεις</h1>
  <form method="get" action="<?= e(url('/statistics')) ?>" class="d-flex align-items-center gap-2">
    <label class="small text-muted mb-0">Έτος</label>
    <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 6; $y--): ?>
        <option value="<?= $y ?>" <?= $y === (int) $year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </form>
</div>

<ul class="nav nav-tabs mb-3" id="statsTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">
      <i class="bi bi-bar-chart me-1"></i>Επισκόπηση <?= (int) $year ?>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-trends" type="button">
      <i class="bi bi-graph-up-arrow me-1"></i>Τάσεις (5ετία)
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-overview">
    <?php include BASE_PATH . '/views/statistics/_overview.php'; ?>
  </div>
  <div class="tab-pane fade" id="tab-trends">
    <?php include BASE_PATH . '/views/analytics/index.php'; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.bootstrap) return;
  // Open the Trends tab directly when redirected to /statistics#trends.
  var h = window.location.hash;
  if (h === '#trends' || h === '#tab-trends') {
    var t = document.querySelector('[data-bs-target="#tab-trends"]');
    if (t) { new bootstrap.Tab(t).show(); }
  }
  // Charts inside a hidden tab render at 0px — nudge them to resize when shown.
  document.querySelectorAll('#statsTabs [data-bs-toggle="tab"]').forEach(function (btn) {
    btn.addEventListener('shown.bs.tab', function () { window.dispatchEvent(new Event('resize')); });
  });
});
</script>
