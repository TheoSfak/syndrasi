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
