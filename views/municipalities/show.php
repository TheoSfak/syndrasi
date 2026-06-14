<?php
$roleLabels = [
    'super_admin'      => 'Super Admin',
    'municipality_admin' => 'Διαχ. Δήμου',
    'event_operator'   => 'Χειριστής',
    'team_admin'       => 'Διαχ. Ομάδας',
];
$roleBadge = [
    'super_admin'      => 'danger',
    'municipality_admin' => 'primary',
    'event_operator'   => 'info',
    'team_admin'       => 'success',
];
?>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <a href="<?= e(url('/admin/municipalities')) ?>" class="text-muted small text-decoration-none">
      <i class="bi bi-chevron-left"></i> Δήμοι
    </a>
    <h1 class="h3 mb-0 mt-1"><?= e($m['name']) ?></h1>
    <?php if ($m['city']): ?>
      <p class="text-muted mb-0 small"><i class="bi bi-geo-alt me-1"></i><?= e($m['city']) ?></p>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <span class="badge text-bg-<?= $m['status'] === 'active' ? 'success' : 'secondary' ?> fs-6 align-self-center">
      <?= $m['status'] === 'active' ? 'Ενεργός' : 'Ανενεργός' ?>
    </span>
    <!-- Edit municipality modal trigger -->
    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editMuniModal">
      <i class="bi bi-pencil me-1"></i>Επεξεργασία
    </button>
    <!-- Toggle status -->
    <form method="post" action="<?= e(url('/admin/municipalities/' . $m['id'] . '/toggle')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm btn-outline-<?= $m['status'] === 'active' ? 'warning' : 'success' ?>"
              onclick="return confirm('Αλλαγή κατάστασης δήμου;')">
        <i class="bi bi-toggle-<?= $m['status'] === 'active' ? 'on' : 'off' ?> me-1"></i>
        <?= $m['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>
      </button>
    </form>
  </div>
</div>

<!-- Stats cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-warning-subtle text-warning mb-2"><i class="bi bi-calendar-event"></i></div>
      <div class="stat-value"><?= (int) $stats['events_total'] ?></div>
      <div class="text-muted small">Δράσεις συνολικά</div>
    </div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-broadcast"></i></div>
      <div class="stat-value"><?= (int) $stats['events_active'] ?></div>
      <div class="text-muted small">Ενεργές δράσεις</div>
    </div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-primary-subtle text-primary mb-2"><i class="bi bi-calendar-check"></i></div>
      <div class="stat-value"><?= (int) $stats['events_year'] ?></div>
      <div class="text-muted small">Φέτος</div>
    </div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-info-subtle text-info mb-2"><i class="bi bi-clipboard-check"></i></div>
      <div class="stat-value"><?= (int) $stats['applications'] ?></div>
      <div class="text-muted small">Δηλώσεις</div>
    </div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-person-check"></i></div>
      <div class="stat-value"><?= (int) $stats['approved'] ?></div>
      <div class="text-muted small">Εγκεκριμένες</div>
    </div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-warning-subtle text-warning mb-2"><i class="bi bi-clock-history"></i></div>
      <div class="stat-value"><?= number_format($stats['volunteer_hours'], 0) ?></div>
      <div class="text-muted small">Ώρες εθελοντισμού</div>
    </div></div>
  </div>
</div>

<div class="row g-4">
  <!-- Users table -->
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-people me-1"></i> Χρήστες (<?= count($users) ?>)</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Όνομα</th>
              <th>Ρόλος</th>
              <th>Ομάδα</th>
              <th>Κατάσταση</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$users): ?>
              <tr><td colspan="5" class="text-muted py-3 text-center">Δεν υπάρχουν χρήστες.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <strong><?= e($u['name']) ?></strong>
                  <div class="small text-muted"><?= e($u['email']) ?></div>
                </td>
                <td>
                  <span class="badge text-bg-<?= $roleBadge[$u['role']] ?? 'secondary' ?>">
                    <?= e($roleLabels[$u['role']] ?? $u['role']) ?>
                  </span>
                </td>
                <td class="small text-muted"><?= e($u['team_name'] ?: '–') ?></td>
                <td>
                  <span class="badge text-bg-<?= ($u['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>">
                    <?= ($u['status'] ?? 'active') === 'active' ? 'Ενεργός' : 'Ανενεργός' ?>
                  </span>
                </td>
                <td class="text-end text-nowrap">
                  <?php if ($u['role'] !== 'super_admin'): ?>
                    <form method="post" action="<?= e(url('/admin/impersonate/' . $u['id'])) ?>" class="d-inline">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-sm btn-outline-warning" title="Impersonate"
                              onclick="return confirm('Σύνδεση ως <?= e(addslashes($u['name'])) ?>;')">
                        <i class="bi bi-person-fill-gear"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Teams + info -->
  <div class="col-lg-4 d-flex flex-column gap-4">
    <!-- Municipality info -->
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Στοιχεία Δήμου</div>
      <ul class="list-group list-group-flush small">
        <?php if ($m['city']): ?>
          <li class="list-group-item"><i class="bi bi-geo me-2 text-muted"></i><?= e($m['city']) ?></li>
        <?php endif; ?>
        <?php if ($m['address']): ?>
          <li class="list-group-item"><i class="bi bi-signpost me-2 text-muted"></i><?= e($m['address']) ?></li>
        <?php endif; ?>
        <?php if ($m['email']): ?>
          <li class="list-group-item"><i class="bi bi-envelope me-2 text-muted"></i><?= e($m['email']) ?></li>
        <?php endif; ?>
        <?php if ($m['phone']): ?>
          <li class="list-group-item"><i class="bi bi-telephone me-2 text-muted"></i><?= e($m['phone']) ?></li>
        <?php endif; ?>
        <?php if (!$m['city'] && !$m['address'] && !$m['email'] && !$m['phone']): ?>
          <li class="list-group-item text-muted">Δεν έχουν καταχωρηθεί στοιχεία.</li>
        <?php endif; ?>
      </ul>
    </div>

    <!-- Teams list -->
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i> Ομάδες (<?= count($teams) ?>)</div>
      <ul class="list-group list-group-flush small">
        <?php if (!$teams): ?>
          <li class="list-group-item text-muted">Δεν υπάρχουν ομάδες.</li>
        <?php endif; ?>
        <?php foreach ($teams as $t): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= e($t['name']) ?>
            <span class="badge text-bg-<?= ($t['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>">
              <?= ($t['status'] ?? 'active') === 'active' ? 'Ενεργή' : 'Ανενεργή' ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<!-- Edit Municipality Modal -->
<div class="modal fade" id="editMuniModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= e(url('/admin/municipalities/' . $m['id'] . '/update')) ?>">
      <?= csrf_field() ?>
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Επεξεργασία Δήμου</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Όνομα <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= e($m['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Πόλη</label>
            <input type="text" name="city" class="form-control" value="<?= e($m['city'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Διεύθυνση</label>
            <input type="text" name="address" class="form-control" value="<?= e($m['address'] ?? '') ?>">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($m['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Τηλέφωνο</label>
              <input type="text" name="phone" class="form-control" value="<?= e($m['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Κατάσταση</label>
            <select name="status" class="form-select">
              <option value="active" <?= $m['status'] === 'active' ? 'selected' : '' ?>>Ενεργός</option>
              <option value="inactive" <?= $m['status'] === 'inactive' ? 'selected' : '' ?>>Ανενεργός</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Άκυρο</button>
          <button type="submit" class="btn btn-primary">Αποθήκευση</button>
        </div>
      </div>
    </form>
  </div>
</div>
