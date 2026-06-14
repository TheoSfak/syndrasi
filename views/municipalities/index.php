<div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
  <h1 class="h3 mb-0">Δήμοι</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMunicipalityModal">
    <i class="bi bi-plus-lg me-1"></i>Νέος Δήμος
  </button>
</div>
<p class="text-muted">Διαχείριση δήμων της πλατφόρμας.</p>

<div class="card shadow-sm">
  <?php if (!$municipalities): ?>
    <div class="card-body text-muted">Δεν έχουν καταχωρηθεί δήμοι.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Δήμος</th><th>Πόλη</th><th>Επικοινωνία</th><th>Κατάσταση</th><th class="text-end">Ενέργειες</th></tr></thead>
        <tbody>
          <?php foreach ($municipalities as $m): ?>
            <tr>
              <td class="fw-semibold"><?= e($m['name']) ?></td>
              <td><?= e($m['city'] ?: '—') ?></td>
              <td class="small"><?= e($m['email'] ?: '') ?><?= $m['email'] && $m['phone'] ? '<br>' : '' ?><?= e($m['phone'] ?: '') ?></td>
              <td><span class="badge text-bg-<?= $m['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $m['status'] === 'active' ? 'Ενεργός' : 'Ανενεργός' ?></span></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= (int) $m['id'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="post" class="d-inline" action="<?= e(url('/admin/municipalities/' . $m['id'] . '/toggle')) ?>"
                      onsubmit="return confirm('Να αλλάξει η κατάσταση του δήμου;')">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-<?= $m['status'] === 'active' ? 'danger' : 'success' ?>">
                    <?= $m['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- New municipality modal -->
<div class="modal fade" id="newMunicipalityModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= e(url('/admin/municipalities/store')) ?>" class="modal-content">
      <?= csrf_field() ?>
      <div class="modal-header"><h5 class="modal-title">Νέος Δήμος</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Όνομα *</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Πόλη</label><input type="text" name="city" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Διεύθυνση</label><input type="text" name="address" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Τηλέφωνο</label><input type="text" name="phone" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Άκυρο</button>
        <button class="btn btn-primary">Δημιουργία</button>
      </div>
    </form>
  </div>
</div>

<?php foreach ($municipalities as $m): ?>
<div class="modal fade" id="editModal<?= (int) $m['id'] ?>" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= e(url('/admin/municipalities/' . $m['id'] . '/update')) ?>" class="modal-content">
      <?= csrf_field() ?>
      <div class="modal-header"><h5 class="modal-title">Επεξεργασία: <?= e($m['name']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Όνομα *</label><input type="text" name="name" class="form-control" required value="<?= e($m['name']) ?>"></div>
        <div class="mb-2"><label class="form-label">Πόλη</label><input type="text" name="city" class="form-control" value="<?= e($m['city']) ?>"></div>
        <div class="mb-2"><label class="form-label">Διεύθυνση</label><input type="text" name="address" class="form-control" value="<?= e($m['address']) ?>"></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($m['email']) ?>"></div>
        <div class="mb-2"><label class="form-label">Τηλέφωνο</label><input type="text" name="phone" class="form-control" value="<?= e($m['phone']) ?>"></div>
        <div class="mb-2">
          <label class="form-label">Κατάσταση</label>
          <select name="status" class="form-select">
            <option value="active" <?= $m['status'] === 'active' ? 'selected' : '' ?>>Ενεργός</option>
            <option value="inactive" <?= $m['status'] === 'inactive' ? 'selected' : '' ?>>Ανενεργός</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Άκυρο</button>
        <button class="btn btn-primary">Αποθήκευση</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
