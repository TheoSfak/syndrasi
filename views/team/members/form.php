<?php
$v = function ($name, $default = '') use ($member) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return htmlspecialchars($_POST[$name] ?? $default, ENT_QUOTES);
    }
    return $member ? htmlspecialchars($member[$name] ?? $default, ENT_QUOTES) : htmlspecialchars($default, ENT_QUOTES);
};
?>
<div class="d-flex align-items-center mb-3 gap-2">
  <a href="<?= e(url('/team/members')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h1 class="h3 mb-0"><?= e($pageTitle) ?></h1>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="<?= e($isEdit ? url('/team/members/' . $member['id']) : url('/team/members')) ?>">
      <?= csrf_field() ?>

      <!-- Fixed fields -->
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Ονοματεπώνυμο <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= $v('full_name') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Τηλέφωνο <span class="text-danger">*</span></label>
          <input type="tel" name="phone" class="form-control" value="<?= $v('phone') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= $v('email') ?>">
          <div class="form-text">Χρησιμοποιείται για αποστολή ειδοποίησης συμμετοχής σε δράση.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Ημερομηνία Γέννησης</label>
          <input type="date" name="date_of_birth" class="form-control" value="<?= $v('date_of_birth') ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Διεύθυνση</label>
          <input type="text" name="address" class="form-control" value="<?= $v('address') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">ΑΜ Πολιτικής Προστασίας</label>
          <input type="text" name="civil_protection_registry_no" class="form-control" value="<?= $v('civil_protection_registry_no') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Ειδικότητα / Ρόλος στην ομάδα</label>
          <input type="text" name="role_in_team" class="form-control" value="<?= $v('role_in_team') ?>" placeholder="π.χ. Διασώστης, Οδηγός, Πρώτες Βοήθειες">
        </div>
      </div>

      <!-- Configurable optional fields -->
      <?php
      $visibleOptional = array_filter($fieldConfig, fn($c) => !empty($c['visible']));
      if ($visibleOptional):
      ?>
      <hr class="my-4">
      <h6 class="text-muted mb-3">Επιπλέον Στοιχεία</h6>
      <div class="row g-3">
        <?php foreach ($visibleOptional as $field => $conf):
          $required = !empty($conf['required']);
          $labels = [
            'blood_type'      => 'Ομάδα Αίματος',
            'driving_license' => 'Δίπλωμα Οδήγησης',
            'certifications'  => 'Πιστοποιήσεις',
            'id_number'       => 'Αριθμός Ταυτότητας',
            'amka'            => 'ΑΜΚΑ',
          ];
          $label = $labels[$field] ?? $field;
        ?>
          <div class="col-md-6">
            <label class="form-label"><?= e($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?></label>
            <?php if ($field === 'certifications'): ?>
              <textarea name="<?= e($field) ?>" class="form-control" rows="2"
                <?= $required ? 'required' : '' ?>><?= $v($field) ?></textarea>
            <?php else: ?>
              <input type="text" name="<?= e($field) ?>" class="form-control" value="<?= $v($field) ?>"
                <?= $required ? 'required' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Notes -->
      <div class="row g-3 mt-1">
        <div class="col-12">
          <label class="form-label">Σημειώσεις</label>
          <textarea name="notes" class="form-control" rows="2"><?= $v('notes') ?></textarea>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Αποθήκευση' : 'Προσθήκη Μέλους' ?>
        </button>
        <a href="<?= e(url('/team/members')) ?>" class="btn btn-outline-secondary">Ακύρωση</a>
      </div>
    </form>
  </div>
</div>
