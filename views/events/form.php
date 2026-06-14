<?php
$isEdit = $event !== null;
$v = function ($key, $default = '') use ($event) {
    $oldVal = old($key, null);
    if ($oldVal !== null && $oldVal !== '') return $oldVal;
    return $event && isset($event[$key]) && $event[$key] !== null ? $event[$key] : $default;
};
$dtVal = function ($key) use ($event) {
    $oldVal = old($key, '');
    if ($oldVal !== '') return $oldVal;
    if ($event && !empty($event[$key])) return date('Y-m-d\TH:i', strtotime($event[$key]));
    return '';
};
?>
<h1 class="h3 mb-1"><?= $isEdit ? 'Επεξεργασία Δράσης' : 'Νέα Δράση' ?></h1>
<p class="text-muted">Συμπληρώστε τα στοιχεία της δράσης. Τα πεδία με * είναι υποχρεωτικά.</p>

<form method="post" action="<?= e(url($isEdit ? '/events/' . $event['id'] . '/update' : '/events/store')) ?>" class="card shadow-sm">
  <?= csrf_field() ?>
  <div class="card-body row g-3">
    <div class="col-md-8">
      <label class="form-label">Τίτλος *</label>
      <input type="text" name="title" class="form-control" required value="<?= e($v('title')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Κατηγορία</label>
      <select name="category_id" class="form-select">
        <option value="">— Επιλέξτε —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $v('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <label class="form-label">Περιγραφή</label>
      <textarea name="description" class="form-control" rows="3"><?= e($v('description')) ?></textarea>
    </div>

    <div class="col-md-6">
      <label class="form-label">Τοποθεσία</label>
      <input type="text" name="location_name" class="form-control" value="<?= e($v('location_name')) ?>" placeholder="π.χ. Πλατεία Ελευθερίας">
    </div>
    <div class="col-md-6">
      <label class="form-label">Διεύθυνση</label>
      <input type="text" name="address" class="form-control" value="<?= e($v('address')) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label d-flex align-items-center gap-2">
        <i class="bi bi-geo-alt-fill text-danger"></i>
        Google Maps URL
        <span id="mapsParseStatus" class="badge bg-success d-none">✓ Συντεταγμένες εξήχθησαν</span>
        <span id="mapsParseError"  class="badge bg-danger  d-none">✗ Δεν βρέθηκαν συντεταγμένες</span>
      </label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-link-45deg text-muted"></i></span>
        <input type="text" id="mapsUrlInput" class="form-control"
               placeholder="Επικολλήστε σύνδεσμο Google Maps…"
               value="">
        <button type="button" class="btn btn-outline-secondary" id="mapsClearBtn" title="Καθαρισμός">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div id="mapsCoordDisplay" class="form-text text-success fw-semibold <?= ($v('latitude') === '') ? 'd-none' : '' ?>">
        📍 <span id="mapsLatDisplay"><?= e($v('latitude')) ?></span> , <span id="mapsLngDisplay"><?= e($v('longitude')) ?></span>
      </div>
      <div class="form-text text-muted">
        Ανοίξτε τη τοποθεσία στο Google Maps, αντιγράψτε τον σύνδεσμο από τη γραμμή διευθύνσεων και επικολλήστε τον εδώ.
      </div>
      <!-- Hidden fields actually submitted to the server -->
      <input type="hidden" name="latitude"  id="latField" value="<?= e($v('latitude')) ?>">
      <input type="hidden" name="longitude" id="lngField" value="<?= e($v('longitude')) ?>">
    </div>

    <div class="col-md-6">
      <div class="row g-3">
        <div class="col-6">
          <label class="form-label">Έναρξη *</label>
          <input type="datetime-local" name="start_datetime" class="form-control" required value="<?= e($dtVal('start_datetime')) ?>">
        </div>
        <div class="col-6">
          <label class="form-label">Λήξη *</label>
          <input type="datetime-local" name="end_datetime" class="form-control" required value="<?= e($dtVal('end_datetime')) ?>">
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Ζητούμενα άτομα</label>
      <input type="number" name="requested_people" min="0" class="form-control" value="<?= e($v('requested_people', 0)) ?>">
    </div>
    <div class="col-md-4 d-flex align-items-center">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="requested_vehicle" id="reqVehicle" value="1" <?= $v('requested_vehicle') ? 'checked' : '' ?>>
        <label class="form-check-label" for="reqVehicle">Απαιτείται όχημα</label>
      </div>
    </div>
    <div class="col-md-4 d-flex align-items-center">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="requested_medical_equipment" id="reqMedical" value="1" <?= $v('requested_medical_equipment') ? 'checked' : '' ?>>
        <label c