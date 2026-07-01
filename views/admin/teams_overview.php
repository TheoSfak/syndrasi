<?php
$filters = $filters ?? ['municipality_id' => 0, 'team_id' => 0, 'status' => '', 'q' => ''];
$statusLabel = ['active' => 'Ενεργό', 'inactive' => 'Ανενεργό'];
$selectedMunicipality = (int) ($filters['municipality_id'] ?? 0);
$selectedTeam = (int) ($filters['team_id'] ?? 0);
$selectedStatus = (string) ($filters['status'] ?? '');
$query = (string) ($filters['q'] ?? '');
?>

<div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
  <div>
    <h1 class="h3 mb-0">Ομάδες & Εθελοντές</h1>
    <p class="text-muted mb-0">Πλήρης εικόνα όλων των διασωστικών/εθελοντικών ομάδων και των μελών που έχουν καταχωρήσει οι αρχηγοί.</p>
  </div>
  <a href="<?= e(url('/admin/dashboard')) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Πίνακας Ελέγχου
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-shield-check"></i></div>
      <div class="stat-value"><?= (int) $counts['teams'] ?></div>
      <div class="text-muted small">Ομάδες συνολικά</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-primary-subtle text-primary mb-2"><i class="bi bi-check-circle"></i></div>
      <div class="stat-value"><?= (int) $counts['active_teams'] ?></div>
      <div class="text-muted small">Ενεργές ομάδες</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-info-subtle text-info mb-2"><i class="bi bi-person-lines-fill"></i></div>
      <div class="stat-value"><?= (int) $counts['members'] ?></div>
      <div class="text-muted small">Εθελοντές roster</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-person-check"></i></div>
      <div class="stat-value"><?= (int) $counts['active_members'] ?></div>
      <div class="text-muted small">Ενεργοί εθελοντές</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-warning-subtle text-warning mb-2"><i class="bi bi-shield-plus"></i></div>
      <div class="stat-value"><?= (int) $counts['assistants'] ?></div>
      <div class="text-muted small">Βοηθοί Αρχηγού</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-secondary-subtle text-secondary mb-2"><i class="bi bi-filter-circle"></i></div>
      <div class="stat-value"><?= count($members) ?></div>
      <div class="text-muted small">Εθελοντές στα φίλτρα</div>
    </div></div>
  </div>
</div>

<form method="get" action="<?= e(url('/admin/teams')) ?>" class="card shadow-sm mb-4">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-semibold">Φορέας</label>
      <select name="municipality_id" class="form-select form-select-sm">
        <option value="">Όλοι οι φορείς</option>
        <?php foreach ($municipalities as $mun): ?>
          <option value="<?= (int) $mun['id'] ?>" <?= $selectedMunicipality === (int) $mun['id'] ? 'selected' : '' ?>><?= e($mun['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-semibold">Ομάδα</label>
      <select name="team_id" class="form-select form-select-sm">
        <option value="">Όλες οι ομάδες</option>
        <?php foreach ($allTeams as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= $selectedTeam === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-semibold">Κατάσταση</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Όλες</option>
        <option value="active" <?= $selectedStatus === 'active' ? 'selected' : '' ?>>Ενεργά</option>
        <option value="inactive" <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>Ανενεργά</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-semibold">Αναζήτηση</label>
      <input type="search" name="q" class="form-control form-control-sm" value="<?= e($query) ?>" placeholder="όνομα, email, τηλέφωνο, ΑΜ ΠΠ...">
    </div>
    <div class="col-md-1 d-grid">
      <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold"><i class="bi bi-shield-check me-1"></i> Ομάδες (<?= count($teams) ?>)</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Ομάδα</th>
          <th>Φορέας</th>
          <th>Υπεύθυνος</th>
          <th>Επικοινωνία</th>
          <th>Δυνατότητες</th>
          <th class="text-center">Μέλη</th>
          <th class="text-center">Login admins</th>
          <th>Κατάσταση</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$teams): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Δεν βρέθηκαν ομάδες με αυτά τα φίλτρα.</td></tr>
        <?php endif; ?>
        <?php foreach ($teams as $t): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($t['name']) ?></div>
              <div class="small text-muted"><?= e($t['type'] ?: '—') ?></div>
            </td>
            <td>
              <a href="<?= e(url('/admin/municipalities/' . $t['municipality_id'])) ?>" class="text-decoration-none"><?= e($t['municipality_name']) ?></a>
            </td>
            <td class="small"><?= e($t['contact_person'] ?: '—') ?></td>
            <td class="small">
              <?= e($t['phone'] ?: '—') ?>
              <div class="text-muted"><?= e($t['email'] ?: '') ?></div>
              <?php if (!empty($t['telegram_chat_id'])): ?><div><span class="badge text-bg-info"><i class="bi bi-telegram"></i> Telegram</span></div><?php endif; ?>
            </td>
            <td>
              <?php if ($t['has_vehicle']): ?><span class="badge text-bg-secondary"><i class="bi bi-truck"></i> Όχημα</span><?php endif; ?>
              <?php if ($t['has_medical_equipment']): ?><span class="badge text-bg-secondary"><i class="bi bi-heart-pulse"></i> Υγειον.</span><?php endif; ?>
              <?php if ($t['default_people_capacity']): ?><span class="badge text-bg-light text-dark"><?= (int) $t['default_people_capacity'] ?> άτομα</span><?php endif; ?>
              <?php if (!$t['has_vehicle'] && !$t['has_medical_equipment'] && !$t['default_people_capacity']): ?><span class="text-muted small">—</span><?php endif; ?>
            </td>
            <td class="text-center">
              <span class="fw-semibold"><?= (int) $t['members_count'] ?></span>
              <div class="small text-muted"><?= (int) $t['active_members_count'] ?> ενεργά</div>
              <?php if ((int) $t['assistant_admins_count'] > 0): ?><span class="badge text-bg-success"><?= (int) $t['assistant_admins_count'] ?> βοηθοί</span><?php endif; ?>
            </td>
            <td class="text-center"><?= (int) $t['login_admins_count'] ?></td>
            <td>
              <span class="badge text-bg-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?>">
                <?= $t['status'] === 'active' ? 'Ενεργή' : 'Ανενεργή' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold"><i class="bi bi-person-lines-fill me-1"></i> Εθελοντές / Μέλη ομάδων (<?= count($members) ?>)</span>
    <span class="small text-muted">Πλήρη στοιχεία roster όπως τα περνά ο αρχηγός ομάδας</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle table-sm">
      <thead class="table-light">
        <tr>
          <th>Εθελοντής</th>
          <th>Φορέας</th>
          <th>Ομάδα</th>
          <th>Επικοινωνία</th>
          <th>Ρόλος / ΑΜ ΠΠ</th>
          <th>Προσωπικά στοιχεία</th>
          <th>Ικανότητες</th>
          <th>Admin πρόσβαση</th>
          <th>Κατάσταση</th>
          <th>Σημειώσεις</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$members): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Δεν βρέθηκαν εθελοντές με αυτά τα φίλτρα.</td></tr>
        <?php endif; ?>
        <?php foreach ($members as $m): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($m['full_name']) ?></div>
              <div class="small text-muted">#<?= (int) $m['id'] ?></div>
            </td>
            <td class="small"><?= e($m['municipality_name']) ?></td>
            <td class="small">
              <div class="fw-semibold"><?= e($m['team_name']) ?></div>
              <div class="text-muted"><?= e($m['team_type'] ?: '—') ?></div>
            </td>
            <td class="small">
              <div><i class="bi bi-telephone me-1 text-muted"></i><?= e($m['phone']) ?></div>
              <div><i class="bi bi-envelope me-1 text-muted"></i><?= e($m['email'] ?: '—') ?></div>
              <div><i class="bi bi-geo-alt me-1 text-muted"></i><?= e($m['address'] ?: '—') ?></div>
            </td>
            <td class="small">
              <div><?= e($m['role_in_team'] ?: '—') ?></div>
              <div class="text-muted">ΑΜ ΠΠ: <?= e($m['civil_protection_registry_no'] ?: '—') ?></div>
            </td>
            <td class="small">
              <div>Γέννηση: <?= e(gr_date($m['date_of_birth'] ?? null)) ?></div>
              <div>ΑΔΤ: <?= e($m['id_number'] ?: '—') ?></div>
              <div>ΑΜΚΑ: <?= e($m['amka'] ?: '—') ?></div>
            </td>
            <td class="small">
              <div>Αίμα: <?= e($m['blood_type'] ?: '—') ?></div>
              <div>Δίπλωμα: <?= e($m['driving_license'] ?: '—') ?></div>
              <div>Πιστοποιήσεις: <?= e($m['certifications'] ?: '—') ?></div>
            </td>
            <td class="small">
              <?php if (!empty($m['is_assistant_admin'])): ?>
                <span class="badge text-bg-success">Βοηθός Αρχηγού</span>
              <?php elseif (!empty($m['is_team_admin'])): ?>
                <span class="badge text-bg-primary">Αρχηγός</span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
              <?php if (!empty($m['login_email'])): ?>
                <div class="mt-1">Login: <?= e($m['login_email']) ?></div>
                <div class="text-muted"><?= e($m['login_status'] === 'active' ? 'ενεργό' : 'ανενεργό') ?> · τελευταίο: <?= e(gr_datetime($m['last_login_at'])) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge text-bg-<?= $m['status'] === 'active' ? 'success' : 'secondary' ?>">
                <?= $m['status'] === 'active' ? 'Ενεργός' : 'Ανενεργός' ?>
              </span>
            </td>
            <td class="small" style="min-width:180px"><?= e($m['notes'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
