<div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
  <h1 class="h3 mb-0"><?= e(t('municipalities/index.001', 'Φορείς')) ?></h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMunicipalityModal">
    <i class="bi bi-plus-lg me-1"></i><?= e(t('municipalities/index.002', 'Νέος Φορέας')) ?>
  </button>
</div>
<p class="text-muted"><?= e(t('municipalities/index.003', 'Διαχείριση δήμων, πολιτικής προστασίας, πυροσβεστικής και λιμενικού.')) ?></p>

<?php $authorityOptions = $authorityOptions ?? authority_options(); ?>

<div class="card shadow-sm">
  <?php if (!$municipalities): ?>
    <div class="card-body text-muted"><?= e(t('municipalities/index.004', 'Δεν έχουν καταχωρηθεί φορείς.')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th><?= e(t('municipalities/index.005', 'Φορέας')) ?></th><th><?= e(t('municipalities/index.006', 'Τύπος')) ?></th><th><?= e(t('municipalities/index.007', 'Πόλη')) ?></th><th><?= e(t('municipalities/index.008', 'Επικοινωνία')) ?></th><th><?= e(t('municipalities/index.009', 'Κατάσταση')) ?></th><th class="text-end"><?= e(t('municipalities/index.010', 'Ενέργειες')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($municipalities as $m): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= e($m['official_name'] ?: $m['name']) ?></div>
                <div class="small text-muted"><?= e($m['name']) ?></div>
              </td>
              <td><span class="badge text-bg-light border"><?= e(($authorityOptions[$m['authority_type'] ?? 'municipality']['label'] ?? 'Δήμος')) ?></span></td>
              <td><?= e($m['city'] ?: '—') ?></td>
              <td class="small"><?= e($m['email'] ?: '') ?><?= $m['email'] && $m['phone'] ? '<br>' : '' ?><?= e($m['phone'] ?: '') ?></td>
              <td><span class="badge text-bg-<?= $m['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $m['status'] === 'active' ? 'Ενεργός' : 'Ανενεργός' ?></span></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= (int) $m['id'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="post" class="d-inline" action="<?= e(url('/admin/municipalities/' . $m['id'] . '/toggle')) ?>"
                      onsubmit="return confirm('Να αλλάξει η κατάσταση του φορέα;')">
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

<!-- New authority modal -->
<div class="modal fade" id="newMunicipalityModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= e(url('/admin/municipalities/store')) ?>" class="modal-content">
      <?= csrf_field() ?>
      <div class="modal-header"><h5 class="modal-title"><?= e(t('municipalities/index.002', 'Νέος Φορέας')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.011', 'Όνομα *')) ?></label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-2">
          <label class="form-label"><?= e(t('municipalities/index.012', 'Τύπος φορέα')) ?></label>
          <select name="authority_type" class="form-select">
            <?php foreach ($authorityOptions as $type => $opt): ?>
              <option value="<?= e($type) ?>"><?= e($opt['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.013', 'Πλήρες εμφανιζόμενο όνομα')) ?></label><input type="text" name="official_name" class="form-control" placeholder="<?= e(t('municipalities/index.023', 'π.χ. Πυροσβεστική Ηρακλείου')) ?>"></div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.014', 'Σύντομο όνομα')) ?></label><input type="text" name="short_name" class="form-control" placeholder="<?= e(t('municipalities/index.024', 'π.χ. Πυρ/κή')) ?>"></div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.007', 'Πόλη')) ?></label><input type="text" name="city" class="form-control"></div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.015', 'Διεύθυνση')) ?></label><input type="text" name="address" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.016', 'Τηλέφωνο')) ?></label><input type="text" name="phone" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal"><?= e(t('municipalities/index.017', 'Άκυρο')) ?></button>
        <button class="btn btn-primary"><?= e(t('municipalities/index.018', 'Δημιουργία')) ?></button>
      </div>
    </form>
  </div>
</div>

<?php foreach ($municipalities as $m): ?>
<div class="modal fade" id="editModal<?= (int) $m['id'] ?>" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= e(url('/admin/municipalities/' . $m['id'] . '/update')) ?>" class="modal-content">
      <?= csrf_field() ?>
      <div class="modal-header"><h5 class="modal-title"><?= e(t('municipalities/index.019', 'Επεξεργασία:')) ?> <?= e($m['official_name'] ?: $m['name']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.011', 'Όνομα *')) ?></label><input type="text" name="name" class="form-control" required value="<?= e($m['name']) ?>"></div>
        <div class="mb-2">
          <label class="form-label"><?= e(t('municipalities/index.012', 'Τύπος φορέα')) ?></label>
          <select name="authority_type" class="form-select">
            <?php foreach ($authorityOptions as $type => $opt): ?>
              <option value="<?= e($type) ?>" <?= ($m['authority_type'] ?? 'municipality') === $type ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.013', 'Πλήρες εμφανιζόμενο όνομα')) ?></label><input type="text" name="official_name" class="form-control" value="<?= e($m['official_name'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.014', 'Σύντομο όνομα')) ?></label><input type="text" name="short_name" class="form-control" value="<?= e($m['short_name'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.007', 'Πόλη')) ?></label><input type="text" name="city" class="form-control" value="<?= e($m['city']) ?>"></div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.015', 'Διεύθυνση')) ?></label><input type="text" name="address" class="form-control" value="<?= e($m['address']) ?>"></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($m['email']) ?>"></div>
        <div class="mb-2"><label class="form-label"><?= e(t('municipalities/index.016', 'Τηλέφωνο')) ?></label><input type="text" name="phone" class="form-control" value="<?= e($m['phone']) ?>"></div>
        <div class="mb-2">
          <label class="form-label"><?= e(t('municipalities/index.009', 'Κατάσταση')) ?></label>
          <select name="status" class="form-select">
            <option value="active" <?= $m['status'] === 'active' ? 'selected' : '' ?>><?= e(t('municipalities/index.020', 'Ενεργός')) ?></option>
            <option value="inactive" <?= $m['status'] === 'inactive' ? 'selected' : '' ?>><?= e(t('municipalities/index.021', 'Ανενεργός')) ?></option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal"><?= e(t('municipalities/index.017', 'Άκυρο')) ?></button>
        <button class="btn btn-primary"><?= e(t('municipalities/index.022', 'Αποθήκευση')) ?></button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
