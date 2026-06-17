<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
  <div class="flex-grow-1">
    <h1 class="h3 mb-1">Επιχειρησιακές Ενέργειες <?= status_badge($event['status']) ?></h1>
    <p class="text-muted mb-0">
      <strong><?= e($event['title']) ?></strong> ·
      <?= e(gr_time($event['start_datetime'])) ?>–<?= e(gr_time($event['end_datetime'])) ?> ·
      <i class="bi bi-geo-alt"></i> <?= e($event['location_name'] ?: '—') ?> ·
      Εγκεκριμένα άτομα: <strong><?= (int) $application['approved_people'] ?></strong>
    </p>
  </div>
  <?php if ($event['status'] === 'active'): ?>
  <a href="<?= e(url('/team/live/' . $event['id'])) ?>"
     class="btn btn-danger fw-bold d-flex align-items-center gap-2" style="white-space:nowrap">
    <i class="bi bi-broadcast"></i> Live Mode
    <span class="badge bg-white text-danger" style="font-size:10px">MOBILE</span>
  </a>
  <?php endif; ?>
</div>

<?php if ($event['status'] !== 'active'): ?>
  <div class="alert alert-warning">
    Η δράση δεν είναι ενεργή αυτή τη στιγμή. Οι επιχειρησιακές ενέργειες είναι διαθέσιμες όταν ο δήμος ενεργοποιήσει τη δράση.
  </div>
<?php endif; ?>

<!-- Pinned ΕΝΤΟΛΕΣ (unacknowledged orders) -->
<div id="orderBanner" style="display:none"></div>

<!-- SOS + Γρήγορη ενημέρωση + Επικοινωνία -->
<?php $opsActive = ($event['status'] === 'active'); ?>
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card shadow-sm h-100 border-danger border-2">
      <div class="card-body">
        <button type="button" id="sosBtn" class="btn btn-danger w-100 fw-bold py-3 d-flex align-items-center justify-content-center gap-2"
                style="font-size:1.15rem" <?= !$opsActive ? 'disabled' : '' ?>>
          <i class="bi bi-exclamation-octagon-fill fs-3"></i> SOS — ΚΙΝΔΥΝΟΣ
        </button>
        <div id="sosBanner" class="alert mt-2 mb-0 py-2 small" style="display:none"></div>
        <hr class="my-3">
        <div class="small fw-semibold text-muted mb-2"><i class="bi bi-lightning-charge me-1"></i>Γρήγορη ενημέρωση</div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm ping-btn" data-code="arrived" <?= !$opsActive ? 'disabled' : '' ?>>Φτάσαμε στο σημείο</button>
          <button type="button" class="btn btn-outline-secondary btn-sm ping-btn" data-code="task_complete" <?= !$opsActive ? 'disabled' : '' ?>>Ολοκληρώθηκε</button>
          <button type="button" class="btn btn-outline-secondary btn-sm ping-btn" data-code="need_backup" <?= !$opsActive ? 'disabled' : '' ?>>Χρειαζόμαστε ενίσχυση</button>
          <button type="button" class="btn btn-outline-secondary btn-sm ping-btn" data-code="returning" <?= !$opsActive ? 'disabled' : '' ?>>Επιστροφή στη βάση</button>
          <button type="button" class="btn btn-outline-danger btn-sm ping-btn" data-code="incident" <?= !$opsActive ? 'disabled' : '' ?>>Έχουμε περιστατικό</button>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-dots me-1"></i> Επικοινωνία με τον δήμο</div>
      <div class="card-body d-flex flex-column">
        <div id="msgList" class="flex-grow-1 mb-2" style="max-height:260px;overflow-y:auto;min-height:120px">
          <div class="text-muted small text-center py-3">Φόρτωση…</div>
        </div>
        <div class="input-group">
          <input type="text" id="msgInput" class="form-control" placeholder="Μήνυμα προς τον δήμο…" maxlength="500">
          <button class="btn btn-primary" id="msgSend" type="button"><i class="bi bi-send"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Δωμάτιο Επιχείρησης (κοινό κανάλι) -->
<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-broadcast-pin me-1 text-success"></i> Δωμάτιο Επιχείρησης <span class="small text-muted">— κοινό κανάλι όλων</span></span>
    <span class="badge bg-success" id="roomBadge">0</span>
  </div>
  <div class="card-body">
    <div id="roomList" style="max-height:240px;overflow-y:auto;min-height:90px"><div class="text-muted small text-center py-3">Φόρτωση…</div></div>
    <div class="input-group mt-2">
      <input type="text" id="roomInput" class="form-control" placeholder="Μήνυμα προς όλους…" maxlength="500">
      <button class="btn btn-success" id="roomSend" type="button"><i class="bi bi-send"></i></button>
    </div>
  </div>
</div>

<!-- Χάρτης Δράσης (θέση ομάδας + σημείο δράσης) -->
<?php
$evLat = isset($event['latitude'])  && $event['latitude']  !== null && $event['latitude']  !== '' ? (float) $event['latitude']  : null;
$evLng = isset($event['longitude']) && $event['longitude'] !== null && $event['longitude'] !== '' ? (float) $event['longitude'] : null;
$tLat  = $lastPing && $lastPing['latitude']  !== null ? (float) $lastPing['latitude']  : null;
$tLng  = $lastPing && $lastPing['longitude'] !== null ? (float) $lastPing['longitude'] : null;
?>
<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-map me-1 text-success"></i> Χάρτης Δράσης</span>
    <?php if ($lastPing): ?><span class="small text-muted">Τελευταίο στίγμα: <?= e(gr_time($lastPing['created_at'])) ?></span>
    <?php else: ?><span class="small text-muted">Στείλτε στίγμα για να φανεί η θέση σας</span><?php endif; ?>
  </div>
  <div id="teamMap" style="height:300px;border-radius:0 0 .5rem .5rem;background:#eef2f3"></div>
</div>
<script>
window.addEventListener('load', function () {
  if (typeof L === 'undefined' || !document.getElementById('teamMap')) return;
  var evLat = <?= $evLat !== null ? $evLat : 'null' ?>, evLng = <?= $evLng !== null ? $evLng : 'null' ?>;
  var tLat  = <?= $tLat  !== null ? $tLat  : 'null' ?>, tLng  = <?= $tLng  !== null ? $tLng  : 'null' ?>;
  var center = (tLat !== null) ? [tLat, tLng] : ((evLat !== null) ? [evLat, evLng] : [35.3387, 25.1442]);
  var map = L.map('teamMap').setView(center, 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
  var b = [];
  if (evLat !== null) {
    L.marker([evLat, evLng]).addTo(map).bindPopup('<b>Σημείο δράσης</b><br><?= e(addslashes($event['location_name'] ?: $event['title'])) ?>');
    b.push([evLat, evLng]);
  }
  if (tLat !== null) {
    L.circleMarker([tLat, tLng], { radius: 9, color: '#0d6efd', fillColor: '#0d6efd', fillOpacity: .85 })
      .addTo(map).bindPopup('Η θέση της ομάδας μας');
    b.push([tLat, tLng]);
  }
  if (b.length > 1) { try { map.fitBounds(b, { padding: [40, 40], maxZoom: 16 }); } catch (e) {} }
  window.__teamMap = map;
  window.__teamGeo = L.layerGroup().addTo(map);
  setTimeout(function () { map.invalidateSize(); }, 200);
});
</script>

<!-- Αίτημα / Αποστολή Φωτογραφίας -->
<div class="card shadow-sm mb-4 <?= $photoRequest ? 'border-info border-2' : '' ?>">
  <div class="card-body">
    <?php if ($photoRequest): ?>
      <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-camera-fill fs-5"></i>
        <div><strong>Ο δήμος ζήτησε φωτογραφία</strong> για αυτή τη δράση. Τραβήξτε ή ανεβάστε μία παρακάτω.</div>
      </div>
    <?php endif; ?>
    <h2 class="h5 mb-1"><i class="bi bi-camera me-1 text-info"></i>Αποστολή Φωτογραφίας στον δήμο</h2>
    <p class="small text-muted">Εμφανίζεται στον επιχειρησιακό χάρτη του δήμου στο σημείο που τραβήχτηκε (αν επιτρέψετε τοποθεσία).</p>
    <form method="post" action="<?= e(url('/team/operations/events/' . $event['id'] . '/photo')) ?>" enctype="multipart/form-data" id="photoForm">
      <?= csrf_field() ?>
      <input type="hidden" name="latitude" id="photoLat">
      <input type="hidden" name="longitude" id="photoLng">
      <input type="hidden" name="request_id" value="<?= $photoRequest ? (int) $photoRequest['id'] : '' ?>">
      <div class="row g-2 align-items-end">
        <div class="col-sm-5">
          <label class="form-label small mb-1">Φωτογραφία</label>
          <input type="file" name="photo" accept="image/*" capture="environment" class="form-control" required>
        </div>
        <div class="col-sm-5">
          <label class="form-label small mb-1">Σχόλιο (προαιρετικό)</label>
          <input type="text" name="caption" maxlength="255" class="form-control" placeholder="π.χ. σημείο, κατάσταση…">
        </div>
        <div class="col-sm-2 d-grid">
          <button class="btn btn-info text-white" id="photoSubmit"><i class="bi bi-upload me-1"></i>Αποστολή</button>
        </div>
      </div>
      <div id="photoGeoNote" class="small text-muted mt-1"></div>
    </form>
    <?php if (!empty($teamPhotos)): ?>
      <div class="small text-muted mt-3 mb-1">Σταλμένες φωτογραφίες:</div>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($teamPhotos as $tp): ?>
          <a href="<?= e(url('/operations/photos/' . $tp['id'])) ?>" target="_blank" rel="noopener"
             title="<?= e(gr_datetime($tp['created_at'])) ?><?= $tp['latitude'] === null ? ' · χωρίς τοποθεσία' : '' ?>">
            <img src="<?= e(url('/operations/photos/' . $tp['id'])) ?>" loading="lazy"
                 style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:2px solid <?= $tp['latitude'] !== null ? '#0ea5e9' : '#94a3b8' ?>">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('photoForm');
  if (!form) return;
  var note = document.getElementById('photoGeoNote');
  var submitted = false;
  form.addEventListener('submit', function (e) {
    if (submitted) return;            // second pass: let it submit for real
    e.preventDefault();
    var btn = document.getElementById('photoSubmit');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i>Τοποθεσία…';
    function go() { submitted = true; form.submit(); }
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(function (pos) {
        document.getElementById('photoLat').value = pos.coords.latitude;
        document.getElementById('photoLng').value = pos.coords.longitude;
        go();
      }, function () {
        if (note) note.textContent = 'Χωρίς τοποθεσία — η φωτό θα σταλεί χωρίς σημείο στον χάρτη.';
        go();
      }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 });
    } else { go(); }
  });
})();
</script>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <i class="bi bi-geo-alt display-6 text-primary"></i>
        <h2 class="h5 mt-2">Αποστολή Στίγματος</h2>
        <p class="small text-muted">
          Στείλτε τη θέση της ομάδας σας στον δήμο. Το στίγμα στέλνεται μόνο όταν πατήσετε το κουμπί.
        </p>
        <?php if ($lastPing): ?>
          <p class="small text-success mb-2"><i class="bi bi-check-circle me-1"></i>Τελευταίο στίγμα: <?= e(gr_datetime($lastPing['created_at'])) ?></p>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-op" id="sendLocationBtn"
                onclick="sendTeamLocation(<?= (int) $event['id'] ?>)" <?= $event['status'] !== 'active' ? 'disabled' : '' ?>>
          <i class="bi bi-broadcast me-1"></i>Αποστολή Στίγματος
        </button>
        <div id="locationResult" class="small mt-2"></div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <i class="bi bi-person-check display-6 text-success"></i>
        <h2 class="h5 mt-2">Δήλωση Παρουσίας</h2>
        <?php if ($lastCheckin): ?>
          <p class="small mb-2">
            Τρέχουσα κατάσταση: <?= status_badge($lastCheckin['status']) ?>
            <?php if (in_array($lastCheckin['status'], ['present_full', 'present_partial'], true)): ?>
              (<?= (int) $lastCheckin['present_people'] ?> άτομα)
            <?php endif; ?>
            <span class="text-muted">· <?= e(gr_time($lastCheckin['checked_in_at'])) ?></span>
          </p>
  