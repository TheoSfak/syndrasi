<?php
$isEdit = $team !== null;
$v = function ($key, $default = '') use ($team) {
    $oldVal = old($key, null);
    if ($oldVal !== null && $oldVal !== '') return $oldVal;
    return $team && isset($team[$key]) && $team[$key] !== null ? $team[$key] : $default;
};
$readinessOptions = $readinessOptions ?? [];
$selectedReadiness = [];
$oldReadiness = old('readiness_items', null);
if (is_array($oldReadiness)) {
    $selectedReadiness = array_values(array_filter(array_map('trim', $oldReadiness), fn($item) => $item !== ''));
} elseif ($team && !empty($team['readiness_items_json'])) {
    $decodedReadiness = json_decode((string) $team['readiness_items_json'], true);
    $selectedReadiness = is_array($decodedReadiness) ? array_values(array_filter(array_map('trim', $decodedReadiness), fn($item) => $item !== '')) : [];
}
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
$readinessExtra = old('readiness_items_extra', '');
?>
<h1 class="h3 mb-1"><?= $isEdit ? 'Επεξεργασία Ομάδας' : 'Νέα Ομάδα' ?></h1>
<p class="text-muted"><?= e(t('teams/form.001', 'Στοιχεία εθελοντικής ομάδας.')) ?></p>

<form method="post" action="<?= e(url($isEdit ? '/teams/' . $team['id'] . '/update' : '/teams/store')) ?>" class="card shadow-sm">
  <?= csrf_field() ?>
  <div class="card-body row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= e(t('teams/form.002', 'Όνομα ομάδας *')) ?></label>
      <input type="text" name="name" class="form-control" required value="<?= e($v('name')) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= e(t('teams/form.003', 'Τύπος')) ?></label>
      <input type="text" name="type" class="form-control" value="<?= e($v('type')) ?>" placeholder="<?= e(t('teams/form.023', 'π.χ. Διασωστική, Υγειονομική')) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= e(t('teams/form.004', 'Υπεύθυνος επικοινωνίας')) ?></label>
      <input type="text" name="contact_person" class="form-control" value="<?= e($v('contact_person')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($v('email')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(t('teams/form.005', 'Τηλέφωνο')) ?></label>
      <input type="text" name="phone" class="form-control" value="<?= e($v('phone')) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= e(t('teams/form.006', 'Telegram Chat ID ομάδας')) ?></label>
      <input type="text" name="telegram_chat_id" class="form-control" value="<?= e($v('telegram_chat_id')) ?>" placeholder="<?= e(t('teams/form.024', 'π.χ. -1001234567890')) ?>">
      <div class="form-text"><?= e(t('teams/form.007', 'Προαιρετικό. Συμπληρώστε μόνο αν αυτή η ομάδα έχει δικό της ξεχωριστό Telegram group. Αλλιώς χρησιμοποιείται το κοινό Chat ID ομάδων από τις Ρυθμίσεις.')) ?></div>
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= e(t('teams/form.008', 'Διεύθυνση')) ?></label>
      <input type="text" name="address" class="form-control" value="<?= e($v('address')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(t('teams/form.009', 'Τυπική δύναμη (άτομα)')) ?></label>
      <input type="number" min="0" name="default_people_capacity" class="form-control" value="<?= e($v('default_people_capacity')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(t('teams/form.010', 'Κατάσταση')) ?></label>
      <select name="status" class="form-select">
        <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>><?= e(t('teams/form.011', 'Ενεργή')) ?></option>
        <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>><?= e(t('teams/form.012', 'Ανενεργή')) ?></option>
      </select>
    </div>
    <div class="col-md-6 d-flex align-items-center gap-4">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="has_vehicle" id="hasVehicle" value="1" <?= $v('has_vehicle') ? 'checked' : '' ?>>
        <label class="form-check-label" for="hasVehicle"><?= e(t('teams/form.013', 'Διαθέτει όχημα')) ?></label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="has_medical_equipment" id="hasMedical" value="1" <?= $v('has_medical_equipment') ? 'checked' : '' ?>>
        <label class="form-check-label" for="hasMedical"><?= e(t('teams/form.014', 'Διαθέτει υγειονομικό εξοπλισμό')) ?></label>
      </div>
    </div>
    <div class="col-12">
      <label class="form-label d-flex align-items-center justify-content-between gap-2">
        <span><?= e(t('teams/form.015', 'Επιχειρησιακή ετοιμότητα')) ?></span>
        <span class="badge text-bg-light border" id="readinessCount"><?= count($selectedReadiness) ?></span>
      </label>
      <div class="border rounded bg-light p-3">
        <?php if (!$readinessOptions): ?>
          <div class="text-muted small mb-2"><?= e(t('teams/form.016', 'Δεν υπάρχουν ακόμα playbook αντικείμενα για αυτόν τον τύπο φορέα. Προσθέστε custom δυνατότητες παρακάτω.')) ?></div>
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
        <label class="form-label small fw-semibold"><?= e(t('teams/form.017', 'Έξτρα δυνατότητες / εξοπλισμός')) ?></label>
        <textarea name="readiness_items_extra" id="readinessItemsExtra" class="form-control" rows="2" placeholder="<?= e(t('teams/form.025', 'Ένα ανά γραμμή, π.χ. χειριστής drone')) ?>"><?= e($readinessExtra) ?></textarea>
      </div>
      <div class="form-text"><?= e(t('teams/form.018', 'Αυτά συγκρίνονται με τα ζητούμενα αντικείμενα κάθε αποστολής για να βγαίνει match score.')) ?></div>
    </div>
    <div class="col-12">
      <label class="form-label"><?= e(t('teams/form.019', 'Σημειώσεις')) ?></label>
      <textarea name="notes" class="form-control" rows="2"><?= e($v('notes')) ?></textarea>
    </div>

    <?php if (!$isEdit): ?>
      <div class="col-12">
        <hr>
        <label class="form-label"><?= e(t('teams/form.020', 'Email υπευθύνου για δημιουργία λογαριασμού (προαιρετικό)')) ?></label>
        <input type="email" name="admin_email" class="form-control" placeholder="<?= e(t('teams/form.026', 'Αν συμπληρωθεί, θα δημιουργηθεί λογαριασμός υπευθύνου ομάδας και θα σταλεί προσωρινός κωδικός.')) ?>">
        <div class="form-text"><?= e(t('teams/form.021', 'Ο υπεύθυνος θα λάβει email με προσωρινό κωδικό πρόσβασης.')) ?></div>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-footer bg-white d-flex gap-2">
    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= $isEdit ? 'Αποθήκευση' : 'Δημιουργία ομάδας' ?></button>
    <a class="btn btn-link text-muted" href="<?= e(url('/teams')) ?>"><?= e(t('teams/form.022', 'Άκυρο')) ?></a>
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
