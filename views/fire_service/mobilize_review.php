<?php
$statusClass = [
    'ΣΕ ΕΞΕΛΙΞΗ' => 'danger',
    'ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ' => 'warning',
    'ΠΛΗΡΗΣ ΕΛΕΓΧΟΣ' => 'success',
    'ΛΗΞΗ' => 'info',
][$incident['status_label'] ?? ''] ?? 'secondary';
$totalTeams = count($teams);
$totalMembers = array_sum(array_map(fn($t) => (int) ($t['active_members'] ?? 0), $teams));
$terms = authority_context(current_municipality_id());
$orgLabel = $terms['short_name'] ?? 'φορέα';
?>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
  <div class="d-flex align-items-start gap-2">
    <a href="<?= e(url('/fire-service')) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left"></i>
    </a>
    <div>
      <h1 class="h3 mb-1">Έλεγχος Κινητοποίησης</h1>
      <p class="text-muted small mb-0">Επιλέξτε ομάδες και δυνατότητες πριν σταλεί το κάλεσμα στους εθελοντές.</p>
    </div>
  </div>
</div>

<?php if ($existing): ?>
  <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <div class="fw-semibold">Υπάρχει ήδη ενεργό κάλεσμα για αυτό το συμβάν.</div>
      <div class="small">Δεν θα σταλεί δεύτερη ειδοποίηση για το ίδιο συμβάν όσο ο υπάρχων πίνακας είναι ενεργός.</div>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/mobilizations/' . (int) $existing['id'])) ?>">
      <i class="bi bi-display me-1"></i>Άνοιγμα live πίνακα
    </a>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/fire-service/' . (int) $incidentId . '/mobilize')) ?>"
      onsubmit="return confirm('Να σταλεί τώρα το κάλεσμα στους επιλεγμένους εθελοντές;');">
  <?= csrf_field() ?>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
          <i class="bi bi-fire text-danger me-1"></i>Στοιχεία συμβάντος
        </div>
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge text-bg-<?= e($statusClass) ?>"><?= e($incident['status_label'] ?: '—') ?></span>
            <span class="badge text-bg-secondary"><?= e($incident['category'] ?: '—') ?></span>
            <span class="badge text-bg-dark"><?= e(severity_label($severity)) ?></span>
          </div>

          <h2 class="h5 mb-2"><?= e($title) ?></h2>
          <div class="small text-muted mb-3">
            <?= e(trim(($incident['region'] ?: '') . ' / ' . ($incident['regional_unit'] ?: ''), ' /')) ?>
          </div>

          <dl class="row small mb-0">
            <dt class="col-sm-3">Τοποθεσία</dt>
            <dd class="col-sm-9"><?= e($locationName ?: '—') ?></dd>
            <dt class="col-sm-3">Τελευταία εμφάνιση</dt>
            <dd class="col-sm-9"><?= e(gr_datetime($incident['last_seen_at'])) ?></dd>
            <dt class="col-sm-3">Πηγή</dt>
            <dd class="col-sm-9">Πυροσβεστικό Σώμα</dd>
          </dl>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">
          <i class="bi bi-card-text me-1"></i>Κείμενο που θα συνοδεύει το κάλεσμα
        </div>
        <div class="card-body">
          <pre class="bg-light border rounded p-3 small mb-0" style="white-space:pre-wrap;"><?= e($description) ?></pre>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
          <i class="bi bi-sliders me-1"></i>Κριτήρια αποστολής
        </div>
        <div class="card-body">
          <div class="row g-2 mb-3">
            <div class="col-6">
              <div class="border rounded p-2 h-100">
                <div class="small text-muted">Ομάδες</div>
                <div class="fs-5 fw-bold"><?= (int) $totalTeams ?></div>
              </div>
            </div>
            <div class="col-6">
              <div class="border rounded p-2 h-100">
                <div class="small text-muted">Ενεργά μέλη</div>
                <div class="fs-5 fw-bold"><?= (int) $totalMembers ?></div>
              </div>
            </div>
          </div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input js-capability" type="checkbox" role="switch" value="1" name="require_vehicle" id="requireVehicle">
            <label class="form-check-label" for="requireVehicle">Μόνο ομάδες με όχημα</label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input js-capability" type="checkbox" role="switch" value="1" name="require_medical" id="requireMedical">
            <label class="form-check-label" for="requireMedical">Μόνο ομάδες με ιατρικό εξοπλισμό</label>
          </div>

          <div class="d-flex gap-2 mb-3">
            <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllTeams">Όλες</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearTeams">Καμία</button>
          </div>

          <div class="border rounded overflow-hidden">
            <?php if (!$teams): ?>
              <div class="p-3 text-muted small">Δεν υπάρχουν ενεργές ομάδες στον φορέα (<?= e($orgLabel) ?>).</div>
            <?php else: ?>
              <?php foreach ($teams as $team): ?>
                <?php $members = (int) ($team['active_members'] ?? 0); ?>
                <label class="d-flex gap-2 align-items-start p-3 border-bottom js-team-row"
                       data-vehicle="<?= (int) $team['has_vehicle'] ?>"
                       data-medical="<?= (int) $team['has_medical_equipment'] ?>"
                       data-members="<?= $members ?>">
                  <input class="form-check-input mt-1 js-team-check" type="checkbox" name="team_ids[]"
                         value="<?= (int) $team['id'] ?>" <?= $members > 0 ? 'checked' : 'disabled' ?>>
                  <span class="flex-grow-1">
                    <span class="fw-semibold d-block"><?= e($team['name']) ?></span>
                    <span class="small text-muted">
                      <?= e($team['type'] ?: 'Ομάδα') ?> · <?= $members ?> ενεργά μέλη
                    </span>
                    <span class="d-flex flex-wrap gap-1 mt-1">
                      <?php if ((int) $team['has_vehicle'] === 1): ?><span class="badge text-bg-light border">Όχημα</span><?php endif; ?>
                      <?php if ((int) $team['has_medical_equipment'] === 1): ?><span class="badge text-bg-light border">Ιατρικός εξοπλισμός</span><?php endif; ?>
                    </span>
                  </span>
                </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Θα ειδοποιηθούν <strong id="selectedMembers">0</strong> εθελοντές από <strong id="selectedTeams">0</strong> ομάδες.
          </div>
        </div>
      </div>

      <div class="d-grid gap-2">
        <?php if ($existing): ?>
          <button type="submit" class="btn btn-danger btn-lg" disabled>
            <i class="bi bi-broadcast-pin me-1"></i>Υπάρχει ήδη ενεργό κάλεσμα
          </button>
        <?php else: ?>
          <button type="submit" class="btn btn-danger btn-lg" id="sendMobilization">
            <i class="bi bi-broadcast-pin me-1"></i>Αποστολή Καλέσματος
          </button>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('/fire-service')) ?>">Άκυρο</a>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  var vehicle = document.getElementById('requireVehicle');
  var medical = document.getElementById('requireMedical');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.js-team-row'));
  var selectedTeams = document.getElementById('selectedTeams');
  var selectedMembers = document.getElementById('selectedMembers');
  var sendButton = document.getElementById('sendMobilization');

  function eligible(row) {
    if (vehicle && vehicle.checked && row.dataset.vehicle !== '1') return false;
    if (medical && medical.checked && row.dataset.medical !== '1') return false;
    return parseInt(row.dataset.members || '0', 10) > 0;
  }

  function refresh() {
    var teamCount = 0;
    var memberCount = 0;
    rows.forEach(function (row) {
      var check = row.querySelector('.js-team-check');
      var ok = eligible(row);
      row.classList.toggle('text-muted', !ok);
      row.style.opacity = ok ? '1' : '.55';
      if (!ok) {
        check.checked = false;
        check.disabled = true;
      } else {
        check.disabled = false;
      }
      if (check.checked && !check.disabled) {
        teamCount++;
        memberCount += parseInt(row.dataset.members || '0', 10);
      }
    });
    selectedTeams.textContent = teamCount;
    selectedMembers.textContent = memberCount;
    if (sendButton) {
      sendButton.disabled = memberCount === 0;
    }
  }

  document.querySelectorAll('.js-team-check,.js-capability').forEach(function (el) {
    el.addEventListener('change', refresh);
  });
  document.getElementById('selectAllTeams').addEventListener('click', function () {
    rows.forEach(function (row) {
      var check = row.querySelector('.js-team-check');
      if (eligible(row)) check.checked = true;
    });
    refresh();
  });
  document.getElementById('clearTeams').addEventListener('click', function () {
    rows.forEach(function (row) { row.querySelector('.js-team-check').checked = false; });
    refresh();
  });
  refresh();
})();
</script>
