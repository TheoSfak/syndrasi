<h1 class="h3 mb-1"><?= e(t('settings/index.001', 'Ρυθμίσεις Πλατφόρμας')) ?></h1>
<p class="text-muted"><?= e(t('settings/index.002', 'Γενικές ρυθμίσεις, εργασίες συντήρησης και ενημερώσεις της εφαρμογής SynDrasi.')) ?></p>

<ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general" type="button">
      <i class="bi bi-sliders me-1"></i><?= e(t('settings/index.003', 'Γενικά')) ?>
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
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-languages" type="button">
      <i class="bi bi-translate me-1"></i><?= e(t('settings/index.069', 'Γλώσσες')) ?>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link text-danger" data-bs-toggle="tab" data-bs-target="#tab-danger" type="button">
      <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e(t('settings/index.004', 'Επικίνδυνη Ζώνη')) ?>
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
          <label class="form-label"><?= e(t('settings/index.005', 'Ανακοίνωση πλατφόρμας')) ?></label>
          <textarea name="platform_announcement" class="form-control" rows="3"
                    placeholder="<?= e(t('settings/index.065', 'Προαιρετικό μήνυμα προς όλους τους χρήστες')) ?>"><?= e($settings['platform_announcement'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= e(t('settings/index.006', 'Email υποστήριξης')) ?></label>
          <input type="email" name="support_email" class="form-control" value="<?= e($settings['support_email'] ?? '') ?>">
        </div>
      </div>
      <div class="card-footer bg-white">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i><?= e(t('settings/index.007', 'Αποθήκευση')) ?></button>
      </div>
    </form>
  </div>

  <!-- ── Cron Jobs ────────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-cron">

    <!-- Cron secret -->
    <div class="card shadow-sm mb-3" style="max-width:720px">
      <div class="card-body">
        <h2 class="h5"><?= e(t('settings/index.008', 'Κλειδί ασφαλείας Cron (cron_secret)')) ?></h2>
        <p class="text-muted small mb-3">
          <?= e(t('settings/index.009', 'Χρησιμοποιείται αυτόματα από το σύστημα για την ασφαλή εκτέλεση cron endpoints (αποστολή email, καθαρισμός κ.λπ.). Αν δεν έχει οριστεί, παράγεται αυτόματα την πρώτη φορά που θα σταλεί email.')) ?>
        </p>
        <?php $cs = $settings['cron_secret'] ?? ''; ?>
        <?php if ($cs): ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold"><?= e(t('settings/index.010', 'Τρέχον κλειδί')) ?></label>
            <div class="input-group input-group-sm" style="max-width:480px">
              <input type="text" class="form-control font-monospace" value="<?= e($cs) ?>" readonly id="cronSecretField">
              <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('cronSecretField').value)" title="<?= e(t('settings/index.066', 'Αντιγραφή')) ?>"><i class="bi bi-clipboard"></i></button>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-info small py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <?= e(t('settings/index.011', 'Δεν έχει οριστεί ακόμα. Θα παραχθεί αυτόματα την πρώτη φορά που θα σταλεί email.')) ?>
          </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/admin/settings')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="regenerate_cron_secret" value="1">
          <button class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-repeat me-1"></i><?= $cs ? 'Αναγέννηση κλειδιού' : 'Παραγωγή κλειδιού τώρα' ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Mail queue -->
    <div class="card shadow-sm mb-3" style="max-width:720px">
      <div class="card-body">
        <h2 class="h5"><?= e(t('settings/index.012', 'Ουρά email (mail queue)')) ?></h2>
        <p class="text-muted small mb-3">
          <?= e(t('settings/index.013', 'Τα emails αποθηκεύονται στη βάση και αποστέλλονται αυτόματα μέσω loopback HTTP χωρίς να χρειάζεται cron job. Αν θέλετε εγγυημένη αποστολή ακόμα και χωρίς εισερχόμενο traffic, προσθέστε αυτή την εντολή στο cron (κάθε λεπτό):')) ?>
        </p>
        <?php if ($cs): ?>
          <pre class="bg-light border rounded p-2 small">* * * * *  curl -s -H "Authorization: Bearer <?= e($cs) ?>" "<?= e(rtrim(url('/cron/mail-queue'), '/')) ?>" &gt; /dev/null</pre>
        <?php else: ?>
          <pre class="bg-light border rounded p-2 small">* * * * *  curl -s -H "Authorization: Bearer &lt;cron_secret&gt;" "<?= e(rtrim(url('/cron/mail-queue'), '/')) ?>" &gt; /dev/null</pre>
        <?php endif; ?>
      </div>
    </div>

    <!-- Cleanup -->
    <div class="card shadow-sm" style="max-width:720px">
      <div class="card-body">
        <h2 class="h5"><?= e(t('settings/index.014', 'Καθαρισμός συστήματος')) ?></h2>
        <p class="text-muted small mb-3">
          <?= e(t('settings/index.015', 'Διαγράφει προσωρινές εγγραφές που συσσωρεύονται: μετρητές ορίου συνδέσεων, σημαίες υπενθυμίσεων βαρδιών και ληγμένα tokens επαναφοράς κωδικού. Ασφαλές να εκτελείται όποτε θέλετε.')) ?>
        </p>
        <form method="post" action="<?= e(url('/admin/maintenance/cleanup')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-warning"><i class="bi bi-trash3 me-1"></i><?= e(t('settings/index.016', 'Εκτέλεση καθαρισμού τώρα')) ?></button>
        </form>
      </div>
      <?php if ($cs): ?>
      <div class="card-footer bg-white small text-muted">
        <i class="bi bi-info-circle me-1"></i>
        <?= e(t('settings/index.017', 'Cron (μία φορά την ημέρα):')) ?>
        <code>curl -s -H "Authorization: Bearer <?= e($cs) ?>" "<?= e(rtrim(url('/cron/cleanup'), '/')) ?>"</code>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Updates ──────────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-updates">

    <?php if (!empty($preflight)): ?>
      <div class="alert alert-warning small" style="max-width:820px">
        <strong><?= e(t('settings/index.018', 'Προσοχή στο περιβάλλον διακομιστή:')) ?></strong>
        <ul class="mb-0"><?php foreach ($preflight as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3" style="max-width:820px">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <h2 class="h5 mb-0"><?= e(t('settings/index.019', 'Ενημερώσεις εφαρμογής')) ?></h2>
          <span class="badge text-bg-secondary"><?= e(t('settings/index.020', 'Τρέχουσα έκδοση: v')) ?><?= e($currentVersion) ?></span>
        </div>
        <p class="small text-muted mb-3">
          <?= e(t('settings/index.021', 'Πηγή:')) ?> <code><?= e($updateConfig['owner'] !== '' ? $updateConfig['owner'] . '/' . $updateConfig['repo'] : 'δεν έχει ρυθμιστεί') ?></code> (GitHub Releases)
        </p>

        <?php if (!empty($updateCheck)): ?>
          <?php if (empty($updateCheck['ok'])): ?>
            <div class="alert alert-danger small mb-3"><?= e($updateCheck['error'] ?? 'Σφάλμα ελέγχου.') ?></div>
          <?php else: ?>
            <div class="alert <?= !empty($updateCheck['newer']) ? 'alert-success' : 'alert-secondary' ?> small mb-3">
              <div class="fw-semibold">
                <?php if (!empty($updateCheck['newer'])): ?>
                  <i class="bi bi-arrow-up-circle me-1"></i><?= e(t('settings/index.022', 'Διαθέσιμη νέα έκδοση:')) ?> <?= e($updateCheck['latest']) ?>
                <?php else: ?>
                  <i class="bi bi-check-circle me-1"></i><?= e(t('settings/index.090', 'Είστε ενημερωμένοι (τελευταία:')) ?> <?= e($updateCheck['latest']) ?>)
                <?php endif; ?>
              </div>
              <?php if (!empty($updateCheck['published'])): ?>
                <div class="text-muted"><?= e(t('settings/index.024', 'Δημοσιεύτηκε:')) ?> <?= e($updateCheck['published']) ?></div>
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
            <button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i><?= e(t('settings/index.025', 'Έλεγχος για ενημερώσεις')) ?></button>
          </form>

          <form method="post" action="<?= e(url('/admin/updates/backup')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary"<?= !empty($preflight) ? ' disabled' : '' ?>>
              <i class="bi bi-archive me-1"></i><?= e(t('settings/index.026', 'Δημιουργία Backup')) ?>
            </button>
          </form>

          <?php if (!empty($updateCheck['ok']) && !empty($updateCheck['newer'])): ?>
            <form method="post" action="<?= e(url('/admin/updates/apply')) ?>"
                  onsubmit="return confirm('Εφαρμογή ενημέρωσης <?= e($updateCheck['latest']) ?>; Θα δημιουργηθεί αυτόματα backup και θα τρέξουν τα migrations.');">
              <?= csrf_field() ?>
              <button class="btn btn-success"<?= !empty($preflight) ? ' disabled' : '' ?>>
                <i class="bi bi-cloud-arrow-down me-1"></i><?= e(t('settings/index.027', 'Ενημέρωση σε')) ?> <?= e($updateCheck['latest']) ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-footer bg-white small text-muted">
        <i class="bi bi-shield-check me-1"></i>
        <?= e(t('settings/index.028', 'Η «Ενημέρωση» δημιουργεί αυτόματα backup, διατηρεί τα')) ?> <code>config/</code> <?= e(t('settings/index.029', 'και')) ?>
        <code>storage/</code><?= e(t('settings/index.030', ', και εφαρμόζει τα νέα migrations αυτόματα.')) ?>
      </div>
    </div>

    <!-- Migrations -->
    <div class="card shadow-sm" style="max-width:820px">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0"><i class="bi bi-database-gear me-1"></i><?= e(t('settings/index.031', 'Migrations βάσης δεδομένων')) ?></h2>
          <div>
            <span class="badge text-bg-success me-1"><?= count($migApplied) ?> <?= e(t('settings/index.032', 'εφαρμοσμένα')) ?></span>
            <span class="badge text-bg-<?= count($migPending) ? 'danger' : 'secondary' ?>"><?= count($migPending) ?> <?= e(t('settings/index.033', 'εκκρεμή')) ?></span>
          </div>
        </div>
        <?php if (!empty($migPending)): ?>
          <p class="small mb-1"><?= e(t('settings/index.034', 'Εκκρεμή:')) ?></p>
          <ul class="small mb-2"><?php foreach ($migPending as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
          <form method="post" action="<?= e(url('/admin/migrations/run')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-warning"><i class="bi bi-play-fill me-1"></i><?= e(t('settings/index.035', 'Εκτέλεση εκκρεμών migrations')) ?></button>
          </form>
        <?php else: ?>
          <p class="small text-muted mb-0"><?= e(t('settings/index.036', 'Η βάση είναι ενημερωμένη — καμία εκκρεμής μετάβαση. Οι ενημερώσεις εφαρμόζουν νέα migrations αυτόματα.')) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Backups -->
    <div class="card shadow-sm mt-3" style="max-width:820px">
      <div class="card-body">
        <h2 class="h6 mb-2"><i class="bi bi-archive me-1"></i><?= e(t('settings/index.037', 'Αντίγραφα ασφαλείας')) ?></h2>
        <?php if (empty($backups)): ?>
          <p class="small text-muted mb-0"><?= e(t('settings/index.038', 'Δεν υπάρχουν backups ακόμη. Πάτησε «Δημιουργία Backup» παραπάνω.')) ?></p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th><?= e(t('settings/index.039', 'Αρχείο')) ?></th><th><?= e(t('settings/index.040', 'Ημερομηνία')) ?></th><th class="text-end"><?= e(t('settings/index.041', 'Μέγεθος')) ?></th><th></th></tr></thead>
              <tbody>
                <?php foreach ($backups as $b): ?>
                  <tr>
                    <td class="small"><?= e($b['name']) ?></td>
                    <td class="small text-muted"><?= e(gr_datetime(date('Y-m-d H:i:s', $b['mtime']))) ?></td>
                    <td class="small text-end"><?= gr_number(round($b['size'] / 1024)) ?> KB</td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary py-0" title="<?= e(t('settings/index.067', 'Λήψη')) ?>"
                         href="<?= e(url('/admin/backups/download') . '?file=' . urlencode($b['name'])) ?>">
                        <i class="bi bi-download"></i>
                      </a>
                      <form method="post" action="<?= e(url('/admin/backups/restore')) ?>" class="d-inline"
                            onsubmit="return confirm('Επαναφορά από αυτό το backup; Θα αντικατασταθούν τα τρέχοντα αρχεία κώδικα (config/ συμπεριλαμβάνεται, storage/ όχι). Θα κρατηθεί αυτόματα backup της τωρινής κατάστασης.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="file" value="<?= e($b['name']) ?>">
                        <button class="btn btn-sm btn-outline-warning py-0"><i class="bi bi-arrow-counterclockwise me-1"></i><?= e(t('settings/index.042', 'Επαναφορά')) ?></button>
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
        <i class="bi bi-info-circle me-1"></i><?= e(t('settings/index.043', 'Τα backups αποθηκεύονται στο')) ?> <code>storage/backups/</code> <?= e(t('settings/index.044', '(εκτός web root). Δημιουργούνται αυτόματα πριν από κάθε ενημέρωση/επαναφορά.')) ?>
      </div>
    </div>

  </div>

  <!-- ── Languages ────────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-languages">

    <!-- Language management -->
    <div class="card shadow-sm mb-3" style="max-width:820px">
      <div class="card-body">
        <h2 class="h6 mb-3"><i class="bi bi-globe2 me-1"></i><?= e(t('settings/index.069', 'Γλώσσες')) ?></h2>
        <table class="table table-sm align-middle mb-3">
          <thead class="table-light"><tr><th><?= e(t('settings/index.070', 'Κωδικός')) ?></th><th><?= e(t('settings/index.071', 'Όνομα')) ?></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($languages as $lang): ?>
              <tr>
                <td class="font-monospace small"><?= e($lang['code']) ?></td>
                <td><?= e($lang['name']) ?><?php if ($lang['is_source']): ?> <span class="badge text-bg-secondary"><?= e(t('settings/index.072', 'πηγή')) ?></span><?php endif; ?></td>
                <td>
                  <?php if (!$lang['is_source']): ?>
                  <form method="post" action="<?= e(url('/admin/languages/toggle')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="code" value="<?= e($lang['code']) ?>">
                    <input type="hidden" name="active" value="<?= $lang['is_active'] ? '0' : '1' ?>">
                    <button class="btn btn-sm btn-outline-secondary py-0">
                      <?= $lang['is_active'] ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>
                    </button>
                  </form>
                  <?php else: ?>
                    <span class="badge text-bg-success"><?= e(t('settings/index.073', 'ενεργή')) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <form method="post" action="<?= e(url('/admin/languages/add')) ?>" class="d-flex gap-2 align-items-end flex-wrap">
          <?= csrf_field() ?>
          <div>
            <label class="form-label small mb-1"><?= e(t('settings/index.074', 'Κωδικός (π.χ. de)')) ?></label>
            <input type="text" name="code" class="form-control form-control-sm" style="width:100px" maxlength="10" required>
          </div>
          <div>
            <label class="form-label small mb-1"><?= e(t('settings/index.075', 'Όνομα (π.χ. Deutsch)')) ?></label>
            <input type="text" name="name" class="form-control form-control-sm" style="width:200px" required>
          </div>
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= e(t('settings/index.076', 'Προσθήκη γλώσσας')) ?></button>
        </form>
      </div>
    </div>

    <!-- Translation catalog -->
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
          <div>
            <label class="form-label small mb-1"><?= e(t('settings/index.077', 'Αναφορά')) ?></label>
            <select id="langRef" class="form-select form-select-sm">
              <?php foreach ($languages as $lang): ?>
                <option value="<?= e($lang['code']) ?>" <?= $lang['code'] === 'el' ? 'selected' : '' ?>><?= e($lang['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label small mb-1"><?= e(t('settings/index.078', 'Μετάφραση σε')) ?></label>
            <select id="langTarget" class="form-select form-select-sm">
              <?php foreach ($languages as $lang): ?>
                <option value="<?= e($lang['code']) ?>" <?= $lang['code'] === 'en' ? 'selected' : '' ?>><?= e($lang['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex-grow-1" style="min-width:200px">
            <label class="form-label small mb-1"><?= e(t('settings/index.079', 'Αναζήτηση')) ?></label>
            <input type="text" id="langSearch" class="form-control form-control-sm" placeholder="<?= e(t('settings/index.089', 'Αναζήτηση κειμένου ή κλειδιού…')) ?>">
          </div>
          <div class="btn-group btn-group-sm" role="group" id="langStatusFilter">
            <button type="button" class="btn btn-outline-secondary active" data-status="all"><?= e(t('settings/index.080', 'Όλα')) ?></button>
            <button type="button" class="btn btn-outline-secondary" data-status="missing"><?= e(t('settings/index.081', 'Χωρίς μετάφραση')) ?></button>
            <button type="button" class="btn btn-outline-secondary" data-status="translated"><?= e(t('settings/index.082', 'Μεταφρασμένα')) ?></button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr><th style="width:22%"><?= e(t('settings/index.083', 'Κλειδί')) ?></th><th style="width:39%"><?= e(t('settings/index.077', 'Αναφορά')) ?></th><th style="width:39%"><?= e(t('settings/index.084', 'Μετάφραση')) ?></th></tr>
            </thead>
            <tbody id="langRows">
              <tr><td colspan="3" class="text-muted small"><?= e(t('settings/index.085', 'Φόρτωση…')) ?></td></tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <div class="small text-muted" id="langPageInfo"></div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="langPrev"><?= e(t('settings/index.086', '« Προηγούμενο')) ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="langNext"><?= e(t('settings/index.087', 'Επόμενο »')) ?></button>
          </div>
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="small text-muted" id="langDirtyInfo"></span>
        <button type="button" class="btn btn-primary btn-sm" id="langSaveBtn" disabled>
          <i class="bi bi-save me-1"></i><?= e(t('settings/index.088', 'Αποθήκευση αλλαγών')) ?>
        </button>
      </div>
    </div>
  </div>

<script>
(function () {
  var page = 1, status = 'all', dirty = {};

  function el(id) { return document.getElementById(id); }

  function postJSON(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json(); });
  }

  function buildSearchUrl() {
    var params = new URLSearchParams({
      page: page, status: status,
      q: el('langSearch').value,
      refLang: el('langRef').value,
      targetLang: el('langTarget').value
    });
    return window.baseUrl + '/admin/languages/search?' + params.toString();
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : s;
    return d.innerHTML;
  }

  function rowHtml(row) {
    var current = dirty[row.key_id] !== undefined ? dirty[row.key_id] : (row.target_value || '');
    return '<tr data-key-id="' + row.key_id + '">' +
      '<td class="small text-muted">' + escapeHtml(row.str_key) + '<div class="badge text-bg-light">' + escapeHtml(row.str_group) + '</div></td>' +
      '<td class="small">' + escapeHtml(row.ref_value) + '</td>' +
      '<td><input type="text" class="form-control form-control-sm lang-target-input" value="' + escapeHtml(current) + '"></td>' +
      '</tr>';
  }

  function renderRows(rows) {
    var body = el('langRows');
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="3" class="text-muted small">Δεν βρέθηκαν αποτελέσματα.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(rowHtml).join('');
    Array.prototype.forEach.call(body.querySelectorAll('.lang-target-input'), function (input) {
      input.addEventListener('input', function () {
        var keyId = input.closest('tr').getAttribute('data-key-id');
        dirty[keyId] = input.value;
        updateDirtyUi();
      });
    });
  }

  function updateDirtyUi() {
    var count = Object.keys(dirty).length;
    el('langDirtyInfo').textContent = count ? (count + ' αλλαγές δεν έχουν αποθηκευτεί') : '';
    el('langSaveBtn').disabled = count === 0;
  }

  function load() {
    el('langRows').innerHTML = '<tr><td colspan="3" class="text-muted small">Φόρτωση…</td></tr>';
    fetch(buildSearchUrl(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) return;
        page = d.page;
        renderRows(d.rows);
        el('langPageInfo').textContent = 'Σελίδα ' + d.page + ' από ' + d.pages + ' (' + d.total + ' συνολικά)';
        el('langPrev').disabled = d.page <= 1;
        el('langNext').disabled = d.page >= d.pages;
      });
  }

  el('langRef').addEventListener('change', function () { page = 1; load(); });
  el('langTarget').addEventListener('change', function () { page = 1; dirty = {}; updateDirtyUi(); load(); });

  var searchTimer;
  el('langSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { page = 1; load(); }, 300);
  });

  Array.prototype.forEach.call(el('langStatusFilter').querySelectorAll('button'), function (btn) {
    btn.addEventListener('click', function () {
      Array.prototype.forEach.call(el('langStatusFilter').querySelectorAll('button'), function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      status = btn.getAttribute('data-status');
      page = 1;
      load();
    });
  });

  el('langPrev').addEventListener('click', function () { if (page > 1) { page--; load(); } });
  el('langNext').addEventListener('click', function () { page++; load(); });

  el('langSaveBtn').addEventListener('click', function () {
    var rows = Object.keys(dirty).map(function (keyId) { return { key_id: parseInt(keyId, 10), value: dirty[keyId] }; });
    if (!rows.length) return;
    el('langSaveBtn').disabled = true;
    postJSON(window.baseUrl + '/admin/languages/save', { languageCode: el('langTarget').value, rows: rows })
      .then(function (d) {
        if (!d || !d.success) {
          el('langDirtyInfo').textContent = (d && d.message) ? d.message : 'Η αποθήκευση απέτυχε.';
          el('langSaveBtn').disabled = false;
          return;
        }
        dirty = {};
        updateDirtyUi();
        load();
      })
      .catch(function () {
        el('langDirtyInfo').textContent = 'Η αποθήκευση απέτυχε (σφάλμα δικτύου).';
        el('langSaveBtn').disabled = false;
      });
  });

  if (document.getElementById('tab-languages')) {
    load();
  }
})();
</script>

  <!-- ── Danger Zone ──────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-danger">
    <div class="card border-danger shadow-sm" style="max-width:680px">
      <div class="card-header bg-danger text-white">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong><?= e(t('settings/index.045', 'Διαγραφή δεδομένων λειτουργίας')) ?></strong>
      </div>
      <div class="card-body">
        <p class="mb-2"><?= e(t('settings/index.046', 'Διαγράφει')) ?> <strong><?= e(t('settings/index.047', 'μόνιμα και αμετάκλητα')) ?></strong> <?= e(t('settings/index.048', 'όλα τα δεδομένα δράσεων, εκτάκτων και στατιστικών:')) ?></p>
        <ul class="small mb-3">
          <li><?= e(t('settings/index.049', 'Αποστολές/δράσεις και αιτήσεις συμμετοχής')) ?></li>
          <li><?= e(t('settings/index.050', 'Μηνύματα επιχειρησιακού κέντρου, εντολές, GPS στίγματα')) ?></li>
          <li><?= e(t('settings/index.051', 'SOS, ελλείψεις, παρουσίες, σημειώσεις')) ?></li>
          <li><?= e(t('settings/index.052', 'Κινητοποιήσεις, στατιστικά, φωτογραφίες, αναφορές')) ?></li>
          <li><?= e(t('settings/index.053', 'Ειδοποιήσεις, audit logs, tokens επαναφοράς κωδικού')) ?></li>
        </ul>
        <p class="mb-3"><strong class="text-success"><?= e(t('settings/index.054', 'Παραμένουν:')) ?></strong> <?= e(t('settings/index.055', 'χρήστες, ομάδες, μέλη ομάδων, δήμοι, ρυθμίσεις, κατηγορίες δράσεων, πρότυπα.')) ?></p>

        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#resetDataModal">
          <i class="bi bi-trash3-fill me-1"></i><?= e(t('settings/index.056', 'Διαγραφή όλων των δεδομένων…')) ?>
        </button>
      </div>
    </div>
  </div>

</div><!-- /.tab-content -->

<!-- Reset data confirmation modal — must be a direct body child for z-index to work -->
<div class="modal fade" id="resetDataModal" tabindex="-1" aria-labelledby="resetDataModalLabel" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="resetDataModalLabel"><i class="bi bi-exclamation-octagon-fill me-2"></i><?= e(t('settings/index.057', 'Επιβεβαίωση διαγραφής')) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= e(t('settings/index.068', 'Κλείσιμο')) ?>"></button>
      </div>
      <form method="post" action="<?= e(url('/admin/maintenance/reset-data')) ?>">
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="alert alert-danger small mb-3">
            <strong><?= e(t('settings/index.058', 'Η ενέργεια αυτή είναι μη αναστρέψιμη.')) ?></strong> <?= e(t('settings/index.059', 'Όλα τα δεδομένα δράσεων θα διαγραφούν οριστικά.')) ?>
          </div>
          <label class="form-label"><?= e(t('settings/index.060', 'Για επιβεβαίωση, πληκτρολογήστε')) ?> <kbd><?= e(t('settings/index.061', 'ΔΙΑΓΡΑΦΗ')) ?></kbd> <?= e(t('settings/index.062', 'παρακάτω:')) ?></label>
          <input type="text" name="confirm" class="form-control" autocomplete="off"
                 placeholder="<?= e(t('settings/index.061', 'ΔΙΑΓΡΑΦΗ')) ?>" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('settings/index.063', 'Ακύρωση')) ?></button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-trash3-fill me-1"></i><?= e(t('settings/index.064', 'Διαγραφή οριστικά')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Move modal to <body> so Bootstrap's backdrop z-index stacking works correctly.
  var m = document.getElementById('resetDataModal');
  if (m) { document.body.appendChild(m); }

  // Activate the tab matching the URL hash (so redirects to #danger etc. land right).
  var hash = window.location.hash;
  if (!hash) return;
  var btn = document.querySelector('[data-bs-target="#tab-' + hash.replace('#', '') + '"]');
  if (btn && window.bootstrap) { new bootstrap.Tab(btn).show(); }
});
</script>
