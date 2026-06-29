<?php
$isEdit = $team !== null;
$v = function ($key, $default = '') use ($team) {
    $oldVal = old($key, null);
    if ($oldVal !== null && $oldVal !== '') return $oldVal;
    return $team && isset($team[$key]) && $team[$key] !== null ? $team[$key] : $default;
};
?>
<h1 class="h3 mb-1"><?= $isEdit ? 'Επεξεργασία Ομάδας' : 'Νέα Ομάδα' ?></h1>
<p class="text-muted">Στοιχεία εθελοντικής ομάδας.</p>

<form method="post" action="<?= e(url($isEdit ? '/teams/' . $team['id'] . '/update' : '/teams/store')) ?>" class="card shadow-sm">
  <?= csrf_field() ?>
  <div class="card-body row g-3">
    <div class="col-md-6">
      <label class="form-label">Όνομα ομάδας *</label>
      <input type="text" name="name" class="form-control" required value="<?= e($v('name')) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Τύπος</label>
      <input type="text" name="type" class="form-control" value="<?= e($v('type')) ?>" placeholder="π.χ. Διασωστική, Υγειονομική">
    </div>
    <div class="col-md-6">
      <label class="form-label">Υπεύθυνος επικοινωνίας</label>
      <input type="text" name="contact_person" class="form-control" value="<?= e($v('contact_person')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($v('email')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Τηλέφωνο</label>
      <input type="text" name="phone" class="form-control" value="<?= e($v('phone')) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Telegram Chat ID ομάδας</label>
      <input type="text" name="telegram_chat_id" class="form-control" value="<?= e($v('telegram_chat_id')) ?>" placeholder="π.χ. -1001234567890">
      <div class="form-text">Προαιρετικό group/channel της ομάδας για Telegram ειδοποιήσεις.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Διεύθυνση</label>
      <input type="text" name="address" class="form-control" value="<?= e($v('address')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Τυπική δύναμη (άτομα)</label>
      <input type="number" min="0" name="default_people_capacity" class="form-control" value="<?= e($v('default_people_capacity')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Κατάσταση</label>
      <select name="status" class="form-select">
        <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>>Ενεργή</option>
        <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>>Ανενεργή</option>
      </select>
    </div>
    <div class="col-md-6 d-flex align-items-center gap-4">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="has_vehicle" id="hasVehicle" value="1" <?= $v('has_vehicle') ? 'checked' : '' ?>>
        <label class="form-check-label" for="hasVehicle">Διαθέτει όχημα</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="has_medical_equipment" id="hasMedical" value="1" <?= $v('has_medical_equipment') ? 'checked' : '' ?>>
        <label class="form-check-label" for="hasMedical">Διαθέτει υγειονομικό εξοπλισμό</label>
      </div>
    </div>
    <div class="col-12">
      <label class="form-label">Σημειώσεις</label>
      <textarea name="notes" class="form-control" rows="2"><?= e($v('notes')) ?></textarea>
    </div>

    <?php if (!$isEdit): ?>
      <div class="col-12">
        <hr>
        <label class="form-label">Email υπευθύνου για δημιουργία λογαριασμού (προαιρετικό)</label>
        <input type="email" name="admin_email" class="form-control" placeholder="Αν συμπληρωθεί, θα δημιουργηθεί λογαριασμός υπευθύνου ομάδας και θα σταλεί προσωρινός κωδικός.">
        <div class="form-text">Ο υπεύθυνος θα λάβει email με προσωρινό κωδικό πρόσβασης.</div>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-footer bg-white d-flex gap-2">
    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= $isEdit ? 'Αποθήκευση' : 'Δημιουργία ομάδας' ?></button>
    <a class="btn btn-link text-muted" href="<?= e(url('/teams')) ?>">Άκυρο</a>
  </div>
</form>
