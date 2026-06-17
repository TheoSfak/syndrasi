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
    <div class="card shadow-sm h-100 <?= !empty($gpsRequest) ? 'border-info border-2' : '' ?>">
      <div class="card-body text-center">
        <i class="bi bi-geo-alt display-6 text-primary"></i>
        <h2 class="h5 mt-2">Αποστολή Στίγματος</h2>
        <?php if (!empty($gpsRequest)): ?>
          <div class="alert alert-info py-2 small mb-2"><i class="bi bi-geo-alt-fill me-1"></i>Ο δήμος ζήτησε το στίγμα σας — πατήστε «Αποστολή Στίγματος».</div>
        <?php endif; ?>
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
        <?php else: ?>
          <p class="small text-muted mb-2">Δεν έχετε δηλώσει ακόμη παρουσία.</p>
        <?php endif; ?>

        <div class="d-grid gap-2">
          <form method="post" action="<?= e(url('/team/operations/events/' . $event['id'] . '/checkin')) ?>"
                onsubmit="return confirm('Δήλωση: Παρών με όλη την ομάδα (<?= (int) $application['approved_people'] ?> άτομα);')">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="present_full">
            <button class="btn btn-success btn-op" <?= $event['status'] !== 'active' ? 'disabled' : '' ?>>
              <i class="bi bi-check2-all me-1"></i>Παρών με όλη την ομάδα
            </button>
          </form>
          <button class="btn btn-warning btn-op" type="button" data-bs-toggle="modal" data-bs-target="#partialModal" <?= $event['status'] !== 'active' ? 'disabled' : '' ?>>
            <i class="bi bi-check2 me-1"></i>Παρών με ελλείψεις
          </button>
          <form method="post" action="<?= e(url('/team/operations/events/' . $event['id'] . '/checkin')) ?>"
                onsubmit="return confirm('Δήλωση αποχώρησης της ομάδας από τη δράση;')">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="departed">
            <button class="btn btn-outline-dark btn-op" <?= $event['status'] !== 'active' ? 'disabled' : '' ?>>
              <i class="bi bi-box-arrow-right me-1"></i>Αποχώρηση
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i> Αναφορά Έλλειψης</div>
  <div class="card-body">
    <form method="post" action="<?= e(url('/team/operations/events/' . $event['id'] . '/shortage')) ?>"
          onsubmit="return confirm('Να σταλεί η αναφορά έλλειψης στον δήμο;')">
      <?= csrf_field() ?>
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label small">Τύπος έλλειψης *</label>
          <select name="shortage_type" class="form-select" required>
            <option value="people">Άτομα</option>
            <option value="equipment">Εξοπλισμός</option>
            <option value="medical_supplies">Υγειονομικό υλικό</option>
            <option value="vehicle">Όχημα</option>
            <option value="other">Άλλο</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small">Σοβαρότητα</label>
          <select name="severity" class="form-select">
            <option value="low">Χαμηλή</option>
            <option value="medium" selected>Μεσαία</option>
            <option value="high">Υψηλή</option>
            <option value="critical">Κρίσιμη</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small">Σύντομος τίτλος *</label>
          <input type="text" name="title" class="form-control" required placeholder="π.χ. Λείπουν 2 άτομα">
        </div>
        <div class="col-12">
          <label class="form-label small">Περιγραφή</label>
          <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-12">
          <button class="btn btn-danger btn-op" <?= $event['status'] !== 'active' ? 'disabled' : '' ?>>
            <i class="bi bi-send me-1"></i>Αποστολή Αναφοράς Έλλειψης
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php if ($shortages): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Οι αναφορές μας για αυτή τη δράση</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($shortages as $s): ?>
        <li class="list-group-item small">
          <span class="badge text-bg-<?= e(status_color($s['severity'])) ?>"><?= e(severity_label($s['severity'])) ?></span>
          <strong class="ms-1"><?= e($s['title']) ?></strong>
          — <?= e(shortage_type_label($s['shortage_type'])) ?>
          · <?= status_badge($s['status'] === 'open' ? 'pending' : $s['status']) ?>
          <span class="text-muted">· <?= e(gr_datetime($s['created_at'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<script>
(function () {
  'use strict';
  var BASE = window.baseUrl || '';
  var CSRF = window.csrfToken || '';
  var EID  = <?= (int) $event['id'] ?>;
  var IS_ACTIVE = <?= $opsActive ? 'true' : 'false' ?>;

  function postJSON(path, body) {
    return fetch(BASE + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json(); });
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

  /* SOS */
  var sosBtn = document.getElementById('sosBtn');
  var sosBanner = document.getElementById('sosBanner');
  if (sosBtn) {
    sosBtn.addEventListener('click', function () {
      if (!confirm('ΕΠΙΒΕΒΑΙΩΣΗ SOS\n\nΘα ειδοποιηθεί ΑΜΕΣΑ ο δήμος ότι κινδυνεύετε. Συνέχεια;')) return;
      sosBtn.disabled = true;
      var send = function (lat, lng, acc) {
        postJSON('/team/operations/events/' + EID + '/sos', { latitude: lat, longitude: lng, accuracy: acc })
          .then(function () { pollComms(); })
          .catch(function () { sosBtn.disabled = false; });
      };
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function (p) { send(p.coords.latitude, p.coords.longitude, p.coords.accuracy); },
          function () { send(null, null, null); },
          { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
        );
      } else { send(null, null, null); }
    });
  }

  /* Quick status pings */
  document.querySelectorAll('.ping-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.disabled) return;
      var orig = b.innerHTML;
      b.disabled = true;
      postJSON('/team/operations/events/' + EID + '/status-ping', { code: b.dataset.code })
        .then(function () { b.innerHTML = '✓ Στάλθηκε'; setTimeout(function () { b.innerHTML = orig; b.disabled = false; }, 2500); pollComms(); })
        .catch(function () { b.innerHTML = orig; b.disabled = false; });
    });
  });

  /* Comms */
  var msgInput = document.getElementById('msgInput');
  var msgSend  = document.getElementById('msgSend');
  function sendMsg() {
    var body = (msgInput.value || '').trim();
    if (!body) return;
    msgInput.value = '';
    postJSON('/team/operations/events/' + EID + '/message', { body: body }).then(pollComms);
  }
  if (msgSend)  { msgSend.addEventListener('click', sendMsg); }
  if (msgInput) { msgInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sendMsg(); } }); }
  window.ackOrder = function (id) { postJSON('/team/operations/events/' + EID + '/ack-order', { message_id: id }).then(pollComms); };

  var msgListEl = document.getElementById('msgList');
  function renderMsgs(msgs) {
    if (!msgListEl) return;
    if (!msgs || !msgs.length) { msgListEl.innerHTML = '<div class="text-muted small text-center py-3">Καμία επικοινωνία ακόμη.</div>'; return; }
    msgListEl.innerHTML = msgs.map(function (m) {
      var fromCmd = m.sender_role === 'command';
      var align = fromCmd ? '' : 'text-end';
      var bg = m.kind === 'order' ? 'bg-warning-subtle border border-warning'
             : (m.kind === 'status' ? 'bg-success-subtle' : (fromCmd ? 'bg-primary-subtle' : 'bg-light'));
      var who = fromCmd ? 'Δήμος' : (m.sender_name || 'Ομάδα');
      var t = (m.created_at || '').substr(11, 5);
      var html = '<div class="' + align + ' mb-2"><div class="d-inline-block text-start p-2 rounded ' + bg + '" style="max-width:90%">' +
                 (m.kind === 'order' ? '<strong>📋 ΕΝΤΟΛΗ:</strong> ' : '') + esc(m.body || '');
      if (m.kind === 'order') {
        html += m.acknowledged_at
          ? '<div class="small text-success mt-1"><i class="bi bi-check2-all"></i> Επιβεβαιώθηκε</div>'
          : '<div class="mt-1"><button class="btn btn-warning btn-sm py-0" onclick="ackOrder(' + m.id + ')">Επιβεβαίωση λήψης</button></div>';
      }
      html += '<div class="text-muted" style="font-size:.68rem">' + esc(who) + ' · ' + t + '</div></div></div>';
      return html;
    }).join('');
    msgListEl.scrollTop = msgListEl.scrollHeight;
  }
  function renderSos(sos) {
    if (!sosBanner) return;
    if (!sos) {
      sosBanner.style.display = 'none';
      if (sosBtn) sosBtn.disabled = !IS_ACTIVE;
      return;
    }
    sosBanner.style.display = 'block';
    if (sos.status === 'acknowledged') {
      sosBanner.className = 'alert alert-info mt-2 mb-0 py-2 small';
      sosBanner.innerHTML = '<i class="bi bi-check2-all"></i> Το SOS ελήφθη από τον δήμο' + (sos.ack_name ? ' (' + esc(sos.ack_name) + ')' : '') + ' — έρχεται βοήθεια.';
    } else {
      sosBanner.className = 'alert alert-danger mt-2 mb-0 py-2 small';
      sosBanner.innerHTML = '<i class="bi bi-broadcast-pin"></i> SOS ΕΝΕΡΓΟ — αναμονή επιβεβαίωσης από τον δήμο…';
    }
    if (sosBtn) sosBtn.disabled = true;
  }
  var orderBannerEl = document.getElementById('orderBanner');
  function renderOrders(msgs) {
    if (!orderBannerEl) return;
    var pending = (msgs || []).filter(function (m) { return m.kind === 'order' && !m.acknowledged_at; });
    if (!pending.length) { orderBannerEl.innerHTML = ''; orderBannerEl.style.display = 'none'; return; }
    orderBannerEl.style.display = '';
    orderBannerEl.innerHTML = pending.map(function (m) {
      var who = m.sender_name || 'Δήμος';
      var t = (m.created_at || '').substr(11, 5);
      var isInc = m.point_kind === 'incident';
      var head = isInc ? '⚠️ ΠΕΡΙΣΤΑΤΙΚΟ' : (m.point_kind === 'move' ? '➡️ ΜΕΤΑΒΑΣΗ ΣΕ ΣΗΜΕΙΟ' : 'ΕΝΤΟΛΗ ΑΠΟ ΤΟΝ ΔΗΜΟ');
      var cls  = isInc ? 'alert-danger border-danger' : 'alert-warning border-warning';
      var dir  = (m.latitude != null && m.longitude != null)
        ? '<a href="https://www.google.com/maps?q=' + m.latitude + ',' + m.longitude + '" target="_blank" rel="noopener" class="btn btn-primary"><i class="bi bi-geo-alt-fill me-1"></i>Οδηγίες</a>' : '';
      return '<div class="alert ' + cls + ' border-2 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2" role="alert">' +
        '<div><div class="fw-bold"><i class="bi bi-megaphone-fill me-1"></i>' + head + '</div>' +
        '<div class="fs-6">' + esc(m.body || '') + '</div>' +
        '<div class="small text-muted">' + esc(who) + ' · ' + t + '</div></div>' +
        '<div class="d-flex gap-2">' + dir +
        '<button type="button" class="btn btn-warning fw-bold" onclick="ackOrder(' + m.id + ')"><i class="bi bi-check2-all me-1"></i>Επιβεβαίωση λήψης</button>' +
        '</div></div>';
    }).join('');
  }

  function renderGeoPoints(msgs) {
    var map = window.__teamMap, grp = window.__teamGeo;
    if (!map || !grp) return;
    grp.clearLayers();
    (msgs || []).forEach(function (m) {
      if (m.latitude == null || m.longitude == null || !m.point_kind) return;
      var color = m.point_kind === 'incident' ? '#dc2626' : (m.point_kind === 'move' ? '#2563eb' : '#0d9488');
      var lbl   = m.point_kind === 'incident' ? '⚠️ Περιστατικό' : (m.point_kind === 'move' ? '➡️ Μετάβαση' : '📍 Σημείο');
      L.circleMarker([m.latitude, m.longitude], { radius: 10, color: color, fillColor: color, fillOpacity: .7 }).addTo(grp)
        .bindPopup('<b>' + lbl + '</b><br>' + esc(m.body || '') + '<br><a href="https://www.google.com/maps?q=' + m.latitude + ',' + m.longitude + '" target="_blank" rel="noopener">Οδηγίες</a>');
    });
  }

  function pollComms() {
    fetch(BASE + '/team/operations/events/' + EID + '/comms?since=0', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.success) { renderMsgs(d.messages); renderSos(d.sos); renderOrders(d.messages); renderGeoPoints(d.messages); renderRoom(d.room); } })
      .catch(function () {});
  }

  /* Δωμάτιο Επιχείρησης */
  var roomList = document.getElementById('roomList');
  function renderRoom(msgs) {
    if (!roomList) return;
    msgs = msgs || [];
    var rb = document.getElementById('roomBadge'); if (rb) rb.textContent = msgs.length;
    if (!msgs.length) { roomList.innerHTML = '<div class="text-muted small text-center py-3">Κανένα μήνυμα ακόμη.</div>'; return; }
    roomList.innerHTML = msgs.map(function (m) {
      var cmd = m.sender_role === 'command';
      var who = cmd ? 'Δήμος' : (m.sender_label || m.team_name || m.sender_name || 'Ομάδα');
      var t = (m.created_at || '').substr(11, 5);
      var bg = cmd ? 'bg-primary-subtle' : 'bg-light';
      var align = cmd ? '' : 'text-end';
      return '<div class="' + align + ' mb-2"><div class="d-inline-block text-start p-2 rounded ' + bg + '" style="max-width:90%">' +
             esc(m.body || '') + '<div class="text-muted" style="font-size:.68rem">' + esc(who) + ' · ' + t + '</div></div></div>';
    }).join('');
    roomList.scrollTop = roomList.scrollHeight;
  }
  (function () {
    var s = document.getElementById('roomSend'), i = document.getElementById('roomInput');
    function send() { var b = (i.value || '').trim(); if (!b) return; i.value = ''; postJSON('/team/operations/events/' + EID + '/room', { body: b }).then(pollComms); }
    if (s) s.addEventListener('click', send);
    if (i) i.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); send(); } });
  })();

  pollComms();
  setInterval(pollComms, 5000);
})();
</script>

<!-- Partial presence modal -->
<div class="modal fade" id="partialModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= e(url('/team/operations/events/' . $event['id'] . '/checkin')) ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="present_partial">
      <div class="modal-header">
        <h5 class="modal-title">Παρών με ελλείψεις</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Πόσα άτομα είναι παρόντα; *</label>
          <input type="number" name="present_people" class="form-control form-control-lg" min="1"
                 max="<?= max(1, (int) $application['approved_people'] - 1) ?>" required>
          <div class="form-text">Εγκεκριμένα άτομα: <?= (int) $application['approved_people'] ?></div>
        </div>
        <div class="mb-2">
          <label class="form-label">Σχόλιο (προαιρετικό)</label>
          <input type="text" name="message" class="form-control" placeholder="π.χ. Ένα μέλος ασθένησε">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Άκυρο</button>
        <button type="submit" class="btn btn-warning">Δήλωση Παρουσίας</button>
      </div>
    </form>
  </div>
</div>
