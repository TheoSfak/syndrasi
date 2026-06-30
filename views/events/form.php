<?php
$isEdit = $event !== null && !empty($event['id']);
$templateId = $templateId ?? null;
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
$terms = $terms ?? authority_context();
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventNew = $terms['event_new'] ?? 'Νέα Δράση';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
?>
<h1 class="h3 mb-1"><?= e($isEdit ? 'Επεξεργασία ' . $eventSingular : $eventNew) ?></h1>
<p class="text-muted">Συμπληρώστε τα στοιχεία της <?= e($eventSingularLc) ?>. Τα πεδία με * είναι υποχρεωτικά.</p>

<form method="post" action="<?= e(url($isEdit ? '/events/' . $event['id'] . '/update' : '/events/store')) ?>" class="card shadow-sm">
  <?= csrf_field() ?>
  <?php if (!empty($templateId)): ?><input type="hidden" name="template_id" value="<?= (int) $templateId ?>"><?php endif; ?>
  <div class="card-body row g-3">
    <div class="col-md-8">
      <label class="form-label">Τίτλος <?= e($eventSingularLc) ?> *</label>
      <input type="text" name="title" class="form-control" required value="<?= e($v('title')) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Τύπος <?= e($eventSingularLc) ?></label>
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
        <label class="form-check-label" for="reqMedical">Απαιτείται υγειονομικός εξοπλισμός</label>
      </div>
    </div>

    <div class="col-12">
      <label class="form-label">Οδηγίες προς τις ομάδες</label>
      <textarea name="instructions" class="form-control" rows="2" placeholder="π.χ. Προσέλευση 30 λεπτά πριν την έναρξη."><?= e($v('instructions', !$isEdit ? ($defaultInstructions ?? '') : '')) ?></textarea>
    </div>
  </div>

  <div class="card-footer d-flex flex-wrap gap-2">
    <?php if ($isEdit): ?>
      <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση αλλαγών</button>
    <?php else: ?>
      <button class="btn btn-outline-secondary" type="submit" name="action" value="draft"><i class="bi bi-file-earmark me-1"></i>Αποθήκευση ως πρόχειρη</button>
      <button class="btn btn-primary" type="submit" name="action" value="publish"
              onclick="return confirm('Η <?= e($eventSingularLc) ?> θα δημοσιευθεί και όλες οι ενεργές ομάδες θα ειδοποιηθούν. Συνέχεια;')">
        <i class="bi bi-megaphone me-1"></i>Δημοσίευση στις ομάδες
      </button>
    <?php endif; ?>
    <a class="btn btn-link text-muted" href="<?= e(url('/events')) ?>">Άκυρο</a>
  </div>
</form>

<script>
(function () {
  const urlInput    = document.getElementById('mapsUrlInput');
  const latField    = document.getElementById('latField');
  const lngField    = document.getElementById('lngField');
  const coordDisp   = document.getElementById('mapsCoordDisplay');
  const latDisp     = document.getElementById('mapsLatDisplay');
  const lngDisp     = document.getElementById('mapsLngDisplay');
  const statusOk    = document.getElementById('mapsParseStatus');
  const statusErr   = document.getElementById('mapsParseError');
  const clearBtn    = document.getElementById('mapsClearBtn');

  /**
   * Try every known Google Maps URL pattern and return {lat, lng} or null.
   *
   * Patterns handled:
   *  /maps/place/.../@lat,lng,...z
   *  /maps?q=lat,lng
   *  /maps/search/.../@lat,lng
   *  maps.google.com/?q=lat,lng
   *  maps.google.com/?ll=lat,lng
   *  /maps/dir/.../lat,lng
   *  short URL geo:lat,lng inside href  (edge case)
   */
  function parseGoogleMapsUrl(raw) {
    const url = raw.trim();
    if (!url) return null;

    const patterns = [
      // @lat,lng,zoom   (most common share link)
      /@(-?\d+\.?\d*),(-?\d+\.?\d*)/,
      // q=lat,lng
      /[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/,
      // ll=lat,lng
      /[?&]ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/,
      // destination/lat,lng  (directions)
      /\/(-?\d+\.?\d*),(-?\d+\.?\d*)(?:[,\/]|$)/,
    ];

    for (const re of patterns) {
      const m = url.match(re);
      if (m) {
        const lat = parseFloat(m[1]);
        const lng = parseFloat(m[2]);
        if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
          return { lat: lat.toFixed(7).replace(/\.?0+$/, ''),
                   lng: lng.toFixed(7).replace(/\.?0+$/, '') };
        }
      }
    }
    return null;
  }

  function applyCoords(coords) {
    latField.value = coords.lat;
    lngField.value = coords.lng;
    latDisp.textContent = coords.lat;
    lngDisp.textContent = coords.lng;
    coordDisp.classList.remove('d-none');
    statusOk.classList.remove('d-none');
    statusErr.classList.add('d-none');
    urlInput.classList.remove('is-invalid');
    urlInput.classList.add('is-valid');
  }

  function clearCoords() {
    latField.value = '';
    lngField.value = '';
    coordDisp.classList.add('d-none');
    statusOk.classList.add('d-none');
    statusErr.classList.add('d-none');
    urlInput.classList.remove('is-valid', 'is-invalid');
  }

  function onInput() {
    const val = urlInput.value.trim();
    if (!val) { clearCoords(); return; }
    const coords = parseGoogleMapsUrl(val);
    if (coords) {
      applyCoords(coords);
    } else {
      clearCoords();
      statusErr.classList.remove('d-none');
      urlInput.classList.add('is-invalid');
    }
  }

  urlInput.addEventListener('input',  onInput);
  urlInput.addEventListener('paste',  function () { setTimeout(onInput, 50); });
  clearBtn.addEventListener('click', function () {
    urlInput.value = '';
    clearCoords();
    urlInput.focus();
  });

  // On edit page: if lat/lng already set, restore display state
  (function () {
    const lat = latField.value.trim();
    const lng = lngField.value.trim();
    if (lat && lng) {
      latDisp.textContent = lat;
      lngDisp.textContent = lng;
      coordDisp.classList.remove('d-none');
    }
  })();
})();
</script>
