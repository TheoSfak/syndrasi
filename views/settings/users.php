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
              <?php if ($u['role'] !== 'super_admin' && (int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                <form method="post" action="<?= e(url('/admin/impersonate/' . $u['id'])) ?>" class="d-inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-warning me-1" title="Impersonation"
                          onclick="return confirm('Σύνδεση ως <?= e(addslashes($u['name'])) ?>;')">
                    <i class="bi bi-person-fill-gear"></i>
                  </button>
                </form>
              <?php endif; ?>
              <!-- Toggle status -->
              <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/toggle')) ?>" class="d-inline"
                      onsubmit="return confirm('Αλλαγή κατάστασης;')">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-<?= ($u['status'] ?? 'active') === 'active' ? 'danger' : 'success' ?>"
                          title="<?= ($u['status'] ?? 'active') === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>">
                    <i class="bi bi-toggle-<?= ($u['status'] ?? 'active') === 'active' ? 'on' : 'off' ?>"></i>
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

<!-- ═══════════════ New User Modal ═══════════════ -->
<div class="modal fade" id="newUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= e(url('/admin/users/store')) ?>" class="modal-content">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title">Νέος Χρήστης</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Όνομα <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Τηλέφωνο</label>
            <input type="text" name="phone" class="form-control">
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mt-2">
          <label class="form-label fw-semibold">Ρόλος <span class="text-danger">*</span></label>
          <select name="role" id="newRole" class="form-select" required onchange="syncTeamDropdown('newMuni','newTeam','newRole')">
            <?php foreach ($roleLabels as $k => $lbl): ?>
              <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt-2" id="newMuniWrap">
          <label class="form-label fw-semibold">Δήμος</label>
          <select name="municipality_id" id="newMuni" class="form-select" onchange="syncTeamDropdown('newMuni','newTeam','newRole')">
            <option value="">— Επιλέξτε —</option>
            <?php foreach ($municipalities as $mun): ?>
              <option value="<?= (int) $mun['id'] ?>"><?= e($mun['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt-2" id="newTeamWrap" style="display:none">
          <label class="form-label fw-semibold">Ομάδα (για υπεύθυνο ομάδας)</label>
          <select name="team_id" id="newTeam" class="form-select">
            <option value="">— Επιλέξτε δήμο πρώτα —</option>
          </select>
        </div>
        <div class="mt-2">
          <label class="form-label fw-semibold">Κωδικός <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" minlength="8" required>
          <div class="form-text">Τουλάχιστον 8 χαρακτήρες.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Άκυρο</button>
        <button class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Δημιουργία</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════ Edit User Modal ═══════════════ -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="" id="editUserForm" class="modal-content">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title">Επεξεργασία Χρήστη</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Όνομα <span class="text-danger">*</span></label>
            <input type="text" name="name" id="editName" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Τηλέφωνο</label>
            <input type="text" name="phone" id="editPhone" class="form-control">
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" id="editEmail" class="form-control" required>
        </div>
        <div class="mt-2">
          <label class="form-label fw-semibold">Ρόλος <span class="text-danger">*</span></label>
          <select name="role" id="editRole" class="form-select" required onchange="syncTeamDropdown('editMuni','editTeam','editRole')">
            <?php foreach ($roleLabels as $k => $lbl): ?>
              <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt-2" id="editMuniWrap">
          <label class="form-label fw-semibold">Δήμος</label>
          <select name="municipality_id" id="editMuni" class="form-select" onchange="syncTeamDropdown('editMuni','editTeam','editRole')">
            <option value="">— Επιλέξτε —</option>
            <?php foreach ($municipalities as $mun): ?>
              <option value="<?= (int) $mun['id'] ?>"><?= e($mun['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt-2" id="editTeamWrap" style="display:none">
          <label class="form-label fw-semibold">Ομάδα</label>
          <select name="team_id" id="editTeam" class="form-select">
            <option value="">—</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Άκυρο</button>
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Αποθήκευση</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════ Reset Password Modal ═══════════════ -->
<div class="modal fade" id="resetPwModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form method="post" action="" id="resetPwForm" class="modal-content">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title">Επαναφορά Κωδικού</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2 text-muted small">Νέος κωδικός για <strong id="resetPwName"></strong>:</p>
        <input type="password" name="password" class="form-control" minlength="8" required placeholder="Τουλάχιστον 8 χαρακτήρες">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Άκυρο</button>
        <button class="btn btn-warning btn-sm"><i class="bi bi-key me-1"></i>Αλλαγή</button>
      </div>
    </form>
  </div>
</div>

<!-- Teams-by-municipality data for JS -->
<script>
var TEAMS_BY_MUNI = <?= json_encode($teamsByMuni, JSON_UNESCAPED_UNICODE) ?>;

function syncTeamDropdown(muniSelId, teamSelId, roleSelId) {
    var role   = document.getElementById(roleSelId).value;
    var muniId = parseInt(document.getElementById(muniSelId).value) || 0;
    var teamSel  = document.getElementById(teamSelId);
    var teamWrap = document.getElementById(teamSelId + 'Wrap');
    var muniWrap = document.getElementById(muniSelId + 'Wrap');

    // Hide muni+team for super_admin
    if (role === 'super_admin') {
        if (muniWrap) muniWrap.style.display = 'none';
        if (teamWrap) teamWrap.style.display  = 'none';
        return;
    }
    if (muniWrap) muniWrap.style.display = '';

    // Show team dropdown only for team_admin
    var needTeam = (role === 'team_admin');
    if (teamWrap) teamWrap.style.display = needTeam ? '' : 'none';
    if (!needTeam) return;

    // Populate team dropdown
    var teams = TEAMS_BY_MUNI[muniId] || [];
    teamSel.innerHTML = '<option value="">— Επιλέξτε —</option>';
    teams.forEach(function(t) {
        var opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.name;
        teamSel.appendChild(opt);
    });
}

function openEditUser(u) {
    document.getElementById('editUserForm').action = window.baseUrl + '/admin/users/' + u.id + '/update';
    document.getElementById('editName').value  = u.name || '';
    document.getElementById('editPhone').value = u.phone || '';
    document.getElementById('editEmail').value = u.email || '';
    document.getElementById('editRole').value  = u.role  || '';

    // Sync muni
    var muniSel = document.getElementById('editMuni');
    muniSel.value = u.municipality_id || '';

    syncTeamDropdown('editMuni', 'editTeam', 'editRole');

    // Set team after populating
    setTimeout(function() {
        document.getElementById('editTeam').value = u.team_id || '';
    }, 50);

    var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function openResetPw(uid, name) {
    document.getElementById('resetPwForm').action = window.baseUrl + '/admin/users/' + uid + '/reset-password';
    document.getElementById('resetPwName').textContent = name;
    new bootstrap.Modal(document.getElementById('resetPwModal')).show();
}

/* ── Live search/filter ─────────────────────────── */
(function() {
    var rows = document.querySelectorAll('#usersTable tbody tr');
    var counter = document.getElementById('filterCount');

    function applyFilter() {
        var q     = document.getElementById('userSearch').value.toLowerCase();
        var role  = document.getElementById('filterRole').value;
        var muni  = document.getElementById('filterMuni').value;
        var vis   = 0;
        rows.forEach(function(r) {
            var ok = true;
            if (q    && r.dataset.name.indexOf(q) === -1)  ok = false;
            if (role && r.dataset.role !== role)            ok = false;
            if (muni && r.dataset.muni !== muni)            ok = false;
            r.style.display = ok ? '' : 'none';
            if (ok) vis++;
        });
        counter.textContent = vis + ' / ' + rows.length + ' χρήστες';
    }

    document.getElementById('userSearch').addEventListener('input', applyFilter);
    document.getElementById('filterRole').addEventListener('change', applyFilter);
    document.getElementById('filterMuni').addEventListener('change', applyFilter);
    applyFilter();
})();
</script>
