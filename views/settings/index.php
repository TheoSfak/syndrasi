<h1 class="h3 mb-1">Ρυθμίσεις Πλατφόρμας</h1>
<p class="text-muted">Γενικές ρυθμίσεις, εργασίες συντήρησης και ενημερώσεις της εφαρμογής SynDrasi.</p>

<ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general" type="button">
      <i class="bi bi-sliders me-1"></i>Γενικά
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cron" type="button">
      <i class="bi bi-clock-history me-1"></i>Cron Jobs
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-updates" type="button">
      <i class="bi bi-cloud-arrow-down me-1"></i>Updates
      <?php if (!empty($updateCheck['ok']) && !empty($updateCheck['newer'])): ?>
        <span class="badge text-bg-danger ms-1">!</span>
      <?php endif; ?>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link text-danger" data-bs-toggle="tab" data-bs-target="#tab-danger" type="button">
      <i class="bi bi-exclamation-triangle-fill me-1"></i>Επικίνδυνη Ζώνη
    </button>
  </li>
</ul>

<div class="tab-content">

  <!-- ── General ──────────────────────────────────────────────────────────── -->
  <div class="tab-pane fade show active" id="tab-general">
    <form method="post" action="<?= e(url('/admin/settings')) ?>" class="card shadow-sm" style="max-width:640px">
      <?= csrf_field() ?>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Ανακοίνωση πλατφόρμας</label>
          <textarea name="platform_announcement" class="form-control" rows="3"
                    placeholder="Προαιρετικό μήνυμα προς όλους τους χρήστες"><?= e($settings['platform_announcement'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Email υποστήριξης</label>
          <input type="email" name="support_email" class="form-control" value="<?= e($settings['support_email'] ?? '') ?>">
        </div>
      </div>
      <div class="card-footer bg-white">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
      </div>
    </form>
  </div>

  <!-- ── Cron Jobs ────────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-cron">
    <div class="card shadow-sm" style="max-width:720px">
      <div class="card-body">
        <h2 class="h5">Καθαρισμός συστήματος</h2>
        <p class="text-muted small mb-3">
          Διαγράφει προσωρινές εγγραφές που συσσωρεύονται: μετρητές ορίου συνδέσεων,
          σημαίες υπενθυμίσεων βαρδιών και ληγμένα tokens επαναφοράς κωδικού.
          Ασφαλές να εκτελείται όποτε θέλετε.
        </p>
        <form method="post" action="<?= e(url('/admin/maintenance/cleanup')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-warning"><i class="bi bi-trash3 me-1"></i>Εκτέλεση καθαρισμού τώρα</button>
        </form>
      </div>
      <div class="card-footer bg-white small text-muted">
        <i class="bi bi-info-circle me-1"></i>
        Σε παραγωγική εγκατάσταση μπορείτε εναλλακτικά να προγραμματίσετε το
        <code>/cron/cleanup?secret=…</code> να τρέχει αυτόματα μία φορά την ημέρα.
      </div>
    </div>
  </div>

  <!-- ── Updates ──────────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-updates">

    <?php if (!empty($preflight)): ?>
      <div class="alert alert-warning small" style="max-width:820px">
        <strong>Προσοχή στο περιβάλλον διακομιστή:</strong>
        <ul class="mb-0"><?php foreach ($preflight as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3" style="max-width:820px">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <h2 class="h5 mb-0">Ενημερώσεις εφαρμογής</h2>
          <span class="badge text-bg-secondary">Τρέχουσα έκδοση: v<?= e($currentVersion) ?></span>
        </div>
        <p class="small text-muted mb-3">
          Πηγή: <code><?= e($updateConfig['owner'] !== '' ? $updateConfig['owner'] . '/' . $updateConfig['repo'] : 'δεν έχει ρυθμιστεί') ?></code> (GitHub Releases)
        </p>

        <?php if (!empty($updateCheck)): ?>
          <?php if (empty($updateCheck['ok'])): ?>
            <div class="alert alert-danger small mb-3"><?= e($updateCheck['error'] ?? 'Σφάλμα ελέγχου.') ?></div>
          <?php else: ?>
            <div class="alert <?= !empty($updateCheck['newer']) ? 'alert-success' : 'alert-secondary' ?> small mb-3">
              <div class="fw-semibold">
                <?php if (!empty($updateCheck['newer'])): ?>
                  <i class="bi bi-arrow-up-circle me-1"></i>Διαθέσιμη νέα έκδοση: <?= e($updateCheck['latest']) ?>
                <?php else: ?>
                  <i class="bi bi-check-circle me-1"></i>Είστε ενημερωμένοι (τελευταία: <?= e($updateCheck['latest']) ?>)
                <?php endif; ?>
              </div>
              <?php if (!empty($updateCheck['published'])): ?>
                <div class="text-muted">Δημοσιεύτηκε: <?= e($updateCheck['published']) ?></div>
              <?php endif; ?>
              <?php if (!empty($updateCheck['notes'])): ?>
                <hr class="my-2"><div style="white-space:pre-wrap"><?= e(mb_strimwidth($updateCheck['notes'], 0, 600, '…')) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2">
          <form method="post" action="<?= e(url('/admin/updates/check')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Έλεγχος για ενημερώσεις</button>
          </form>

          <form method="post" action="<?= e(url('/admin/updates/backup')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary"<?= !empty($preflight) ? ' disabled' : '' ?>>
              <i class="bi bi-archive me-1"></i>Δημιουργία Backup
            </button>
          </form>

          <?php if (!empty($updateCheck['ok']) && !empty($updateCheck['newer'])): ?>
            <form method="post" action="<?= e(url('/admin/updates/apply')) ?>"
                  onsubmit="return confirm('Εφαρμογή ενημέρωσης <?= e($updateCheck['latest']) ?>; Θα δημιουργηθεί αυτόματα backup και θα τρέξουν τα migrations.');">
              <?= csrf_field() ?>
              <button class="btn btn-success"<?= !empty($preflight) ? ' disabled' : '' ?>>
                <i class="bi bi-cloud-arrow-down me-1"></i>Ενημέρωση σε <?= e($updateCheck['latest']) ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-footer bg-white small text-muted">
        <i class="bi bi-shield-check me-1"></i>
        Η «Ενημέρωση» δημιουργεί αυτόματα backup, διατηρεί τα <code>config/</code> και
        <code>storage/</code>, και εφαρμόζει τα νέα migrations αυτόματα.
      </div>
    </div>

    <!-- Migrations -->
    <div class="card shadow-sm" style="max-width:820px">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0"><i class="bi bi-database-gear me-1"></i>Migrations βάσης δεδομένων</h2>
          <div>
            <span class="badge text-bg-success me-1"><?= count($migApplied) ?> εφαρμοσμένα</span>
            <span class="badge text-bg-<?= count($migPending) ? 'danger' : 'secondary' ?>"><?= count($migPending) ?> εκκρεμή</span>
          </div>
        </div>
        <?php if (!empty($migPending)): ?>
          <p class="small mb-1">Εκκρεμή:</p>
          <ul class="small mb-2"><?php foreach ($migPending as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
          <form method="post" action="<?= e(url('/admin/migrations/run')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-warning"><i class="bi bi-play-fill me-1"></i>Εκτέλεση εκκρεμών migrations</button>
          </form>
        <?php else: ?>
          <p class="small text-muted mb-0">Η βάση είναι ενημερωμένη — καμία εκκρεμής μετάβαση. Οι ενημερώσεις εφαρμόζουν νέα migrations αυτόματα.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Backups -->
    <div class="card shadow-sm mt-3" style="max-width:820px">
      <div class="card-body">
        <h2 class="h6 mb-2"><i class="bi bi-archive me-1"></i>Αντίγραφα ασφαλείας</h2>
        <?php if (empty($backups)): ?>
          <p class="small text-muted mb-0">Δεν υπάρχουν backups ακόμη. Πάτησε «Δημιουργία Backup» παραπάνω.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Αρχείο</th><th>Ημερομηνία</th><th class="text-end">Μέγεθος</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($backups as $b): ?>
                  <tr>
                    <td class="small"><?= e($b['name']) ?></td>
                    <td class="small text-muted"><?= e(gr_datetime(date('Y-m-d H:i:s', $b['mtime']))) ?></td>
                    <td class="small text-end"><?= gr_number(round($b['size'] / 1024)) ?> KB</td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary py-0" title="Λήψη"
                         href="<?= e(url('/admin/backups/download') . '?file=' . urlencode($b['name'])) ?>">
                        <i class="bi bi-download"></i>
                      </a>
                      <form method="post" action="<?= e(url('/admin/backups/restore')) ?>" class="d-inline"
                            onsubmit="return confirm('Επαναφορά από αυτό το backup; Θα αντικατασταθούν τα τρέχοντα αρχεία κώδικα (config/ συμπεριλαμβάνεται, storage/ όχι). Θα κρατηθεί αυτόματα backup της τωρινής κατάστασης.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="file" value="<?= e($b['name']) ?>">
                        <button class="btn btn-sm btn-outline-warning py-0"><i class="bi bi-arrow-counterclockwise me-1"></i>Επαναφορά</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-white small text-muted">
        <i class="bi bi-info-circle me-1"></i>Τα backups αποθηκεύονται στο <code>storage/backups/</code> (εκτός web root). Δημιουργούνται αυτόματα πριν από κάθε ενημέρωση/επαναφορά.
      </div>
    </div>

  </div>

  <!-- ── Danger Zone ──────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-danger">
    <div class="card border-danger shadow-sm" style="max-width:680px">
      <div class="card-header bg-danger text-white">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Διαγραφή δεδομένων λειτουργίας</strong>
      </div>
      <div class="card-body">
        <p class="mb-2">Διαγράφει <strong>μόνιμα και αμετάκλητα</strong> όλα τα δεδομένα δράσεων, εκτάκτων και στατιστικών:</p>
        <ul class="small mb-3">
          <li>Δράσεις (events) και αιτήσεις συμμετοχής</li>
          <li>Μηνύματα επιχειρησιακού κέντρου, εντολές, GPS στίγματα</li>
          <li>SOS, ελλείψεις, παρουσίες, σημειώσεις</li>
          <li>Κινητοποιήσεις, στατιστικά, φωτογραφίες, αναφορές</li>
          <li>Ειδοποιήσεις, audit logs, tokens επαναφοράς κωδικού</li>
        </ul>
        <p class="mb-3"><strong class="text-success">Παραμένουν:</strong> χρήστες, ομάδες, μέλη ομάδων, δήμοι, ρυθμίσεις, κατηγορίες δράσεων, πρότυπα.</p>

        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#resetDataModal">
          <i class="bi bi-trash3-fill me-1"></i>Διαγραφή όλων των δεδομένων…
        </button>
      </div>
    </div>
  </div>

</div><!-- /.tab-content -->

<!-- Reset data confirmation modal -->
<div class="modal fade" id="resetDataModal" tabindex="-1" aria-labelledby="resetDataModalLabel" aria-modal="true">
  <div class="modal-dialog">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="resetDataModalLabel"><i class="bi bi-exclamation-octagon-fill me-2"></i>Επιβεβαίωση διαγραφής</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
      </div>
      <form method="post" action="<?= e(url('/admin/maintenance/reset-data')) ?>">
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="alert alert-danger small mb-3">
            <strong>Η ενέργεια αυτή είναι μη αναστρέψιμη.</strong> Όλα τα δεδομένα δράσεων θα διαγραφούν οριστικά.
          </div>
          <label class="form-label">Για επιβεβαίωση, πληκτρολογήστε <kbd>ΔΙΑΓΡΑΦΗ</kbd> παρακάτω:</label>
          <input type="text" name="confirm" class="form-control" autocomplete="off"
                 placeholder="ΔΙΑΓΡΑΦΗ" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-trash3-fill me-1"></i>Διαγραφή οριστικά</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Activate the tab matching the URL hash (so redirects to #cron / #updates / #danger land right).
// Runs on DOMContentLoaded so Bootstrap (loaded in the footer) is available.
document.addEventListener('DOMContentLoaded', function () {
  var hash = window.location.hash;
  if (!hash) return;
  var btn = document.querySelector('[data-bs-target="#tab-' + hash.replace('#', '') + '"]');
  if (btn && window.bootstrap) { new bootstrap.Tab(btn).show(); }
});
</script>
