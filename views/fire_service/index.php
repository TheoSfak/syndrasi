<?php
$statusClass = function ($status) {
    return [
        'ΣΕ ΕΞΕΛΙΞΗ' => 'danger',
        'ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ' => 'warning',
        'ΠΛΗΡΗΣ ΕΛΕΓΧΟΣ' => 'success',
        'ΛΗΞΗ' => 'info',
    ][$status] ?? 'secondary';
};
$cronUrl = url('/cron/fire-service');
$terms = authority_context(current_municipality_id());
$eventSingularLc = mb_strtolower($terms['event_singular'] ?? 'Δράση', 'UTF-8');
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h3 mb-1"><i class="bi bi-fire text-danger me-1"></i><?= e(t('fire_service/index.001', 'Συμβάντα Πυροσβεστικής')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('fire_service/index.002', 'Αυτόματη λήψη από το Πυροσβεστικό Σώμα, με φίλτρα για Κρήτη, Π.Ε. και περιοχή.')) ?></p>
  </div>
  <form method="post" action="<?= e(url('/fire-service/sync')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-danger" type="submit">
      <i class="bi bi-arrow-clockwise me-1"></i><?= e(t('fire_service/index.003', 'Άμεση ενημέρωση')) ?>
    </button>
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2">
          <div>
            <div class="fw-bold"><?= e(t('fire_service/index.004', 'Κατάσταση λήψης')) ?></div>
            <div class="text-muted small">
              <?php if ($latestFetch): ?>
                <?= e(t('fire_service/index.029', 'Τελευταία προσπάθεια:')) ?> <?= e(gr_datetime($latestFetch['fetched_at'])) ?> ·
                <?php if ((int) $latestFetch['success'] === 1): ?>
                  <span class="text-success fw-semibold"><?= (int) $latestFetch['incidents_found'] ?> <?= e(t('fire_service/index.006', 'συμβάντα')) ?></span>
                <?php else: ?>
                  <span class="text-danger fw-semibold"><?= e(t('fire_service/index.007', 'Αποτυχία')) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <?= e(t('fire_service/index.008', 'Δεν έχει γίνει ακόμη λήψη. Πατήστε «Άμεση ενημέρωση».')) ?>
              <?php endif; ?>
            </div>
          </div>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener">
            <?= e(t('fire_service/index.009', 'Πηγή')) ?> <i class="bi bi-box-arrow-up-right"></i>
          </a>
        </div>
        <?php if (!empty($latestFetch['error_message'])): ?>
          <div class="alert alert-warning small mt-3 mb-0"><?= e($latestFetch['error_message']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="fw-bold mb-2"><?= e(t('fire_service/index.010', 'Cron κάθε 5 λεπτά')) ?></div>
        <pre class="bg-light border rounded p-2 small mb-0">*/5 * * * * curl -s -H "Authorization: Bearer &lt;cron_secret&gt;" "<?= e($cronUrl) ?>" &gt; /dev/null</pre>
      </div>
    </div>
  </div>
</div>

<form method="get" action="<?= e(url('/fire-service')) ?>" class="card shadow-sm mb-3">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-semibold"><?= e(t('fire_service/index.011', 'Περιφέρεια')) ?></label>
      <select name="region" class="form-select">
        <option value=""><?= e(t('fire_service/index.012', 'Όλες')) ?></option>
        <?php $regions = array_unique(array_merge(['ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ'], $options['regions'] ?? [])); sort($regions, SORT_NATURAL); ?>
        <?php foreach ($regions as $r): ?>
          <option value="<?= e($r) ?>" <?= ($filters['region'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-semibold"><?= e(t('fire_service/index.013', 'Νομός / Π.Ε.')) ?></label>
      <select name="regional_unit" class="form-select">
        <option value=""><?= e(t('fire_service/index.012', 'Όλες')) ?></option>
        <?php $units = array_unique(array_merge(['Π.Ε. ΗΡΑΚΛΕΙΟΥ','Π.Ε. ΛΑΣΙΘΙΟΥ','Π.Ε. ΡΕΘΥΜΝΟΥ','Π.Ε. ΧΑΝΙΩΝ'], $options['regional_units'] ?? [])); sort($units, SORT_NATURAL); ?>
        <?php foreach ($units as $u): ?>
          <option value="<?= e($u) ?>" <?= ($filters['regional_unit'] ?? '') === $u ? 'selected' : '' ?>><?= e($u) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-semibold"><?= e(t('fire_service/index.014', 'Κατηγορία')) ?></label>
      <select name="category" class="form-select">
        <option value=""><?= e(t('fire_service/index.012', 'Όλες')) ?></option>
        <?php foreach (($options['categories'] ?? []) as $c): ?>
          <option value="<?= e($c) ?>" <?= ($filters['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-semibold"><?= e(t('fire_service/index.015', 'Κατάσταση')) ?></label>
      <select name="status_label" class="form-select">
        <option value=""><?= e(t('fire_service/index.012', 'Όλες')) ?></option>
        <?php foreach (($options['statuses'] ?? []) as $s): ?>
          <option value="<?= e($s) ?>" <?= ($filters['status_label'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-semibold"><?= e(t('fire_service/index.016', 'Αναζήτηση')) ?></label>
      <input type="search" name="q" class="form-control" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e(t('fire_service/index.028', 'π.χ. Ηράκλειο')) ?>">
    </div>
    <div class="col-md-8">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="history" value="1" id="historyChk" <?= ($filters['current'] ?? '1') === '0' ? 'checked' : '' ?>>
        <label class="form-check-label" for="historyChk"><?= e(t('fire_service/index.017', 'Εμφάνιση και ιστορικού 7 ημερών, όχι μόνο τρέχον snapshot')) ?></label>
      </div>
    </div>
    <div class="col-md-4 text-md-end">
      <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i><?= e(t('fire_service/index.018', 'Φιλτράρισμα')) ?></button>
      <a class="btn btn-outline-secondary" href="<?= e(url('/fire-service')) ?>"><?= e(t('fire_service/index.019', 'Default Κρήτης')) ?></a>
    </div>
  </div>
</form>

<?php if (!$incidents): ?>
  <div class="card shadow-sm">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-fire d-block mb-2" style="font-size:2rem;"></i>
      <?= e(t('fire_service/index.020', 'Δεν βρέθηκαν συμβάντα με τα τρέχοντα φίλτρα.')) ?>
    </div>
  </div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= e(t('fire_service/index.021', 'Συμβάν')) ?></th>
            <th><?= e(t('fire_service/index.022', 'Περιοχή')) ?></th>
            <th><?= e(t('fire_service/index.015', 'Κατάσταση')) ?></th>
            <th><?= e(t('fire_service/index.023', 'Τελευταία εμφάνιση')) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incidents as $i): ?>
            <tr class="<?= (int) $i['is_current'] === 1 ? '' : 'table-light text-muted' ?>">
              <td>
                <div class="fw-semibold"><?= e($i['location_text'] ?: $i['category']) ?></div>
                <div class="small text-muted"><?= e($i['category']) ?></div>
                <div class="small"><?= e($i['raw_text']) ?></div>
              </td>
              <td>
                <div class="small fw-semibold"><?= e($i['region'] ?: '—') ?></div>
                <div class="small text-muted"><?= e($i['regional_unit'] ?: '—') ?></div>
                <div class="small"><?= e(trim(($i['municipality'] ?: '') . ' / ' . ($i['area_text'] ?: ''), ' /')) ?></div>
              </td>
              <td>
                <span class="badge text-bg-<?= e($statusClass($i['status_label'])) ?>"><?= e($i['status_label']) ?></span>
                <?php if ((int) $i['is_current'] !== 1): ?><span class="badge text-bg-secondary"><?= e(t('fire_service/index.024', 'Ιστορικό')) ?></span><?php endif; ?>
              </td>
              <td class="small text-muted"><?= e(gr_datetime($i['last_seen_at'])) ?></td>
              <td class="text-end">
                <?php if ((int) $i['is_current'] === 1): ?>
                  <a href="<?= e(url('/fire-service/' . (int) $i['id'] . '/mobilize')) ?>" class="btn btn-sm btn-danger mb-1">
                    <i class="bi bi-broadcast-pin"></i> <?= e(t('fire_service/index.025', 'Κινητοποίηση')) ?>
                  </a>
                <?php endif; ?>
                <?php if (!empty($i['created_event_id'])): ?>
                  <a href="<?= e(url('/events/' . (int) $i['created_event_id'] . '/edit')) ?>" class="btn btn-sm btn-outline-success">
                    <?= e(t('fire_service/index.026', 'Άνοιγμα')) ?> <?= e($eventSingularLc) ?>
                  </a>
                <?php else: ?>
                  <form method="post" action="<?= e(url('/fire-service/' . (int) $i['id'] . '/create-event')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger" type="submit">
                      <i class="bi bi-plus-lg"></i> <?= e(t('fire_service/index.027', 'Δημιουργία')) ?> <?= e($eventSingularLc) ?>
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
<?php endif; ?>
