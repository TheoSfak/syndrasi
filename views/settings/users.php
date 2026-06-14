<?php
$roleLabels = [
    'super_admin'        => 'Διαχειριστής Πλατφόρμας',
    'municipality_admin' => 'Διαχειριστής Δήμου',
    'team_admin'         => 'Υπεύθυνος Ομάδας',
    'event_operator'     => 'Χειριστής Δράσεων',
];
$roleBadge = [
    'super_admin'        => 'danger',
    'municipality_admin' => 'primary',
    'team_admin'         => 'success',
    'event_operator'     => 'info',
];

/* Build teams list indexed by municipality_id for JS */
$teamsByMuni = [];
foreach ($municipalities as $mun) {
    $teams = dbq(
        'SELECT id, name FROM volunteer_teams WHERE municipality_id = :mid AND status = :s ORDER BY name',
        ['mid' => $mun['id'], 's' => 'active']
    )->fetchAll();
    if ($teams) {
        $teamsByMuni[(int) $mun['id']] = $teams;
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0">Χρήστες</h1>
    <p class="text-muted small mb-0">Διαχείριση όλων των λογαριασμών της πλατφόρμας.</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newUserModal">
    <i class="bi bi-plus-lg me-1"></i>Νέος Χρήστης
  </button>
</div>

<!-- Search/filter bar -->
<div class="card shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="row g-2 align-items-center">
      <div class="col-md-4">
        <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Αναζήτηση ονόματος / email…">
      </div>
      <div class="col-md-3">
        <select id="filterRole" class="form-select form-select-sm">
          <option value="">Όλοι οι ρόλοι</option>
          <?php foreach ($roleLabels as $k => $lbl): ?>
            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select id="filterMuni" class="form-select form-select-sm">
          <option value="">Όλοι οι δήμοι</option>
          <?php foreach ($municipalities as $mun): ?>
            <option value="<?= (int) $mun['id'] ?>"><?= e($mun['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 text-muted small" id="filterCount"></div>
    </div>
  </div>
</div>

<!-- Users table -->
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle" id="usersTable">
      <thead class="table-light">
        <tr>
          <th>Όνομα / Email</th>
          <th>Ρόλος</th>
          <th>Δήμος / Ομάδα</th>
          <th>Κατάσταση</th>
          <th class="text-muted small">Τελ. σύνδεση</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr data-name="<?= e(mb_strtolower($u['name'] . ' ' . $u['email'])) ?>"
              data-role="<?= e($u['role']) ?>"
              data-muni="<?= (int) ($u['municipality_id'] ?? 0) ?>">
            <td>
              <div class="fw-semibold"><?= e($u['name']) ?></div>
              <div class="text-muted small"><?= e($u['email']) ?></div>
            </td>
            <td>
              <span class="badge text-bg-<?= $roleBadge[$u['role']] ?? 'secondary' ?>">
                <?= e($roleLabels[$u['role']] ?? $u['role']) ?>
              </span>
            </td>
            <td class="small">
              <?= e($u['municipality_name'] ?: '—') ?>
              <?php if ($u['team_name']): ?>
                <div class="text-muted"><?= e($u['team_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge text-bg-<?= ($u['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>">
                <?= ($u['status'] ?? 'active') === 'active' ? 'Ενεργός' : 'Ανενεργός' ?>
              </span>
            </td>
            <td class="small text-muted"><?= e(gr_datetime($u['last_login_at'])) ?></td>
            <td class="text-end text-nowrap">
              <!-- Edit -->
              <button class="btn btn-sm btn-outline-secondary me-1"
                      onclick="openEditUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)"
                      title="Επεξεργασία">
                <i class="bi bi-pencil"></i>
              </button>
              <!-- Reset password -->
              <button class="btn btn-sm btn-outline-secondary me-1"
                      onclick="openResetPw(<?= (int) $u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')"
                      title="Επαναφορά κωδικού">
                <i class="bi bi-key"></i>
              </button>
              <!-- Impersonate -->
        