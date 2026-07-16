<div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
  <div>
    <h1 class="h3 mb-0"><?= e(t('dashboard/admin.001', 'Πίνακας Ελέγχου Πλατφόρμας')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('dashboard/admin.002', 'Συνολική εικόνα χρήσης της πλατφόρμας SynDrasi.')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(url('/admin/municipalities')) ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-building me-1"></i><?= e(t('dashboard/admin.003', 'Φορείς')) ?>
    </a>
    <a href="<?= e(url('/admin/users')) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-people me-1"></i><?= e(t('dashboard/admin.004', 'Χρήστες')) ?>
    </a>
    <a href="<?= e(url('/admin/teams')) ?>" class="btn btn-outline-success btn-sm">
      <i class="bi bi-shield-check me-1"></i><?= e(t('dashboard/admin.005', 'Ομάδες & Εθελοντές')) ?>
    </a>
  </div>
</div>

<!-- ── Global stat cards ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-primary-subtle text-primary mb-2"><i class="bi bi-building"></i></div>
        <div class="stat-value"><?= (int) $counts['municipalities'] ?></div>
        <div class="text-muted small"><?= e(t('dashboard/admin.003', 'Φορείς')) ?>
          <span class="badge text-bg-success ms-1"><?= (int) $counts['active_municipalities'] ?> <?= e(t('dashboard/admin.006', 'ενεργοί')) ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-people"></i></div>
        <div class="stat-value"><?= (int) $counts['teams'] ?></div>
        <div class="text-muted small"><?= e(t('dashboard/admin.007', 'Εθελοντικές ομάδες')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-info-subtle text-info mb-2"><i class="bi bi-person-check"></i></div>
        <div class="stat-value"><?= (int) $counts['users'] ?></div>
        <div class="text-muted small"><?= e(t('dashboard/admin.004', 'Χρήστες')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-warning-subtle text-warning mb-2"><i class="bi bi-calendar-event"></i></div>
        <div class="stat-value"><?= (int) $counts['events'] ?></div>
        <div class="text-muted small"><?= e(t('dashboard/admin.008', 'Αποστολές/δράσεις συνολικά')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-primary-subtle text-primary mb-2"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-value"><?= (int) $counts['events_year'] ?></div>
        <div class="text-muted small"><?= e(t('dashboard/admin.009', 'Αποστολές/δράσεις φέτος')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-clipboard-check"></i></div>
        <div class="stat-value"><?= (int) $counts['applications'] ?></div>
        <div class="text-muted small"><?= e(t('dashboard/admin.010', 'Δηλώσεις συμμετοχής')) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ── Per-municipality usage + audit log ─────────────────────────── -->
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-building me-1"></i> <?= e(t('dashboard/admin.011', 'Χρήση ανά φορέα')) ?></span>
        <a href="<?= e(url('/admin/municipalities')) ?>" class="btn btn-sm btn-outline-primary"><?= e(t('dashboard/admin.012', 'Διαχείριση')) ?></a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th><?= e(t('dashboard/admin.013', 'Φορέας')) ?></th>
              <th><?= e(t('dashboard/admin.014', 'Κατάσταση')) ?></th>
              <th class="text-center"><?= e(t('dashboard/admin.015', 'Ομάδες')) ?></th>
              <th class="text-center"><?= e(t('dashboard/admin.016', 'Αποστολές/δράσεις')) ?></th>
              <th class="text-center"><?= e(t('dashboard/admin.004', 'Χρήστες')) ?></th>
              <th class="text-center"><?= e(t('dashboard/admin.017', 'Ώρες εθελ.')) ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$municipalityUsage): ?>
              <tr><td colspan="7" class="text-muted py-4 text-center"><?= e(t('dashboard/admin.018', 'Δεν υπάρχουν φορείς.')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($municipalityUsage as $m): ?>
              <tr>
                <td class="fw-semibold"><?= e($m['name']) ?></td>
                <td>
                  <span class="badge text-bg-<?= $m['status'] === 'active' ? 'success' : 'secondary' ?>">
                    <?= $m['status'] === 'active' ? 'Ενεργός' : 'Ανενεργός' ?>
                  </span>
                </td>
                <td class="text-center"><?= (int) $m['teams_count'] ?></td>
                <td class="text-center"><?= (int) $m['events_count'] ?></td>
                <td class="text-center"><?= (int) $m['users_count'] ?></td>
                <td class="text-center"><?= number_format((float) ($m['volunteer_hours'] ?? 0), 0) ?></td>
                <td>
                  <a href="<?= e(url('/admin/municipalities/' . $m['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('dashboard/admin.021', 'Λεπτομέρειες')) ?>">
                    <i class="bi bi-eye"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-journal-text me-1"></i> <?= e(t('dashboard/admin.019', 'Πρόσφατη δραστηριότητα')) ?></div>
      <ul class="list-group list-group-flush" style="max-height:420px;overflow-y:auto">
        <?php if (!$recentAudit): ?>
          <li class="list-group-item text-muted small"><?= e(t('dashboard/admin.020', 'Δεν υπάρχει καταγεγραμμένη δραστηριότητα.')) ?></li>
        <?php endif; ?>
        <?php foreach ($recentAudit as $log): ?>
          <li class="list-group-item small py-2">
            <div class="text-muted" style="font-size:11px"><?= e(gr_datetime($log['created_at'])) ?></div>
            <strong><?= e($log['user_name'] ?: 'Σύστημα') ?></strong>:
            <?= e($log['action']) ?>
            <?php if ($log['entity_type']): ?>
              <span class="text-muted">(<?= e($log['entity_type']) ?> #<?= e($log['entity_id']) ?>)</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
