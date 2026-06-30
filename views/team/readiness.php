<?php
$team = $team ?? [];
$readinessOptions = $readinessOptions ?? [];
$selectedReadiness = VolunteerTeam::readinessItems($team);
$optionKeys = [];
foreach ($readinessOptions as $opt) {
    $optionKeys[mb_strtolower((string) $opt, 'UTF-8')] = true;
}
foreach ($selectedReadiness as $item) {
    $key = mb_strtolower((string) $item, 'UTF-8');
    if (!isset($optionKeys[$key])) {
        $readinessOptions[] = $item;
        $optionKeys[$key] = true;
    }
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-1 gap-2">
  <div>
    <h1 class="h3 mb-1">Ετοιμότητα Ομάδας</h1>
    <p class="text-muted mb-0">Δηλώστε τι μπορεί να διαθέσει η ομάδα ώστε οι αποστολές να κάνουν σωστή αντιστοίχιση.</p>
  </div>
</div>

<form method="post" action="<?= e(url('/team/readiness')) ?>" class="card shadow-sm">
  <?= csrf_field() ?>
  <div class="card-body row g-3">
    <div class="col-md-4">
      <label class="form-label">Τυπική δύναμη</label>
      <div class="input-group">
        <input type="number" min="0" name="default_people_capacity" class="form-control" value="<?= e($team['default_people_capacity'] ?? '') ?>">
        <span class="input-group-text">άτομα</span>
      </div>
    </div>
    <div class="col-md-4 d-flex align-items-center">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="has_vehicle" id="hasVehicle" value="1" <?= !empty($team['has_vehicle']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="hasVehicle">Διαθέτουμε όχημα</label>
      </div>
    </div>
    <div class="col-md-4 d-flex align-items-center">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="has_medical_equipment" id="hasMedical" value="1" <?= !empty($team['has_medical_equipment']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="hasMedical">Υγειονομικός εξοπλισμός</label>
      </div>
    </div>

    <div class="col-12">
      <label class="form-label d-flex align-items-center justify-content-between gap-2">
        <span>Δυνατότητες και εξοπλισμός</span>
        <span class="badge text-bg-light border" id="readinessCount"><?= count($selectedReadiness) ?></span>
      </label>
      <div class="border rounded bg-light p-3">
        <?php if (!$readinessOptions): ?>
          <div class="text-muted small mb-3">Δεν υπάρχουν ακόμα προτεινόμενα αντικείμενα από playbooks για αυτόν τον φορέα.</div>
        <?php else: ?>
          <div class="row g-2 mb-3">
            <?php foreach ($readinessOptions as $idx => $item): ?>
              <?php $checked = in_array($item, $selectedReadiness, true); ?>
              <div class="col-md-4 col-lg-3">
                <label class="list-group-item d-flex align-items-center gap-2 h-100 rounded border bg-white" for="ready_item_<?= (int) $idx ?>">
                  <input class="form-check-input readiness-cb mt-0" type="checkbox" name="readiness_items[]" id="ready_item_<?= (int) $idx ?>" value="<?= e($item) ?>" <?= $checked ? 'checked' : '' ?>>
                  <span class="small"><?= e($item) ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <label class="form-label small fw-semibold">Άλλα που διαθέτει η ομάδα</label>
        <textarea name="readiness_items_extra" id="readinessItemsExtra" class="form-control" rows="2" placeholder="Ένα ανά γραμμή"></textarea>
      </div>
    </div>
  </div>
  <div class="card-footer bg-white d-flex gap-2">
    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση ετοιμότητας</button>
    <a class="btn btn-link text-muted" href="<?= e(url('/team/dashboard')) ?>">Άκυρο</a>
  </div>
</form>

<script>
(function () {
  const countEl = document.getElementById('readinessCount');
  const checks = document.querySelectorAll('.readiness-cb');
  const extra = document.getElementById('readinessItemsExtra');

  function refresh() {
    const checked = Array.from(checks).filter(function (cb) { return cb.checked; }).length;
    const extraCount = extra && extra.value.trim()
      ? extra.value.split(/\r?\n/).filter(function (line) { return line.trim() !== ''; }).length
      : 0;
    if (countEl) countEl.textContent = checked + extraCount;
  }

  checks.forEach(function (cb) { cb.addEventListener('change', refresh); });
  if (extra) extra.addEventListener('input', refresh);
  refresh();
})();
</script>
