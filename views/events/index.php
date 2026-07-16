<?php
$terms = $terms ?? authority_context();
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$eventSingular = $terms['event_singular'] ?? 'Δράση';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
  <h1 class="h3 mb-0"><?= e($eventPlural) ?></h1>
  <?php if (current_role() === 'municipality_admin'): ?>
    <a href="<?= e(url('/events/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= e($terms['event_new'] ?? 'Νέα Δράση') ?></a>
  <?php endif; ?>
</div>
<p class="text-muted"><?= e(t('events/index.020', 'Όλες οι')) ?> <?= e($eventPluralLc) ?> <?= e(t('events/index.021', 'του φορέα.')) ?></p>

<form class="card shadow-sm mb-3" method="get" action="<?= e(url('/events')) ?>">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1"><?= e(t('events/index.002', 'Αναζήτηση τίτλου')) ?></label>
      <input type="text" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('events/index.016', 'π.χ. φεστιβάλ')) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1"><?= e(t('events/index.003', 'Κατάσταση')) ?></label>
      <select name="status" class="form-select">
        <option value=""><?= e(t('events/index.004', 'Όλες')) ?></option>
        <?php foreach (['draft','open','review','confirmed','active','completed','cancelled'] as $st): ?>
          <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= e(greek_status($st)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1"><?= e(t('events/index.005', 'Κατηγορία')) ?></label>
      <select name="category_id" class="form-select">
        <option value=""><?= e(t('events/index.004', 'Όλες')) ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1"><?= e(t('events/index.006', 'Από')) ?></label>
      <input type="date" name="from" class="form-control" value="<?= e($filters['from']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1"><?= e(t('events/index.007', 'Έως')) ?></label>
      <input type="date" name="to" class="form-control" value="<?= e($filters['to']) ?>">
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i><?= e(t('events/index.008', 'Φίλτρα')) ?></button>
      <a class="btn btn-outline-secondary" href="<?= e(url('/events')) ?>"><?= e(t('events/index.009', 'Καθαρισμός')) ?></a>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <?php if (!$events): ?>
    <div class="card-body text-muted"><?= e(t('events/index.022', 'Δεν βρέθηκαν')) ?> <?= e($eventPluralLc) ?> <?= e(t('events/index.023', 'με αυτά τα κριτήρια.')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th><?= e($eventSingular) ?></th><th><?= e(t('events/index.011', 'Τύπος')) ?></th><th><?= e(t('events/index.012', 'Ημερομηνία')) ?></th><th><?= e(t('events/index.003', 'Κατάσταση')) ?></th><th><?= e(t('events/index.013', 'Δηλώσεις')) ?></th><th class="text-end"><?= e(t('events/index.014', 'Ενέργειες')) ?></th></tr>
        </thead>
        <tbody>
          <?php foreach ($events as $ev): ?>
            <tr>
              <td>
                <a class="fw-semibold text-decoration-none" href="<?= e(url('/events/' . $ev['id'])) ?>"><?= e($ev['title']) ?></a>
                <div class="small text-muted"><?= e($ev['location_name'] ?: '') ?></div>
              </td>
              <td><?= e($ev['category_name'] ?: '—') ?></td>
              <td><?= e(gr_datetime($ev['start_datetime'])) ?></td>
              <td><?= status_badge($ev['status']) ?></td>
              <td>
                <?= (int) $ev['applications_count'] ?>
                <?php if ((int) $ev['pending_count'] > 0): ?>
                  <span class="badge text-bg-warning"><?= (int) $ev['pending_count'] ?> <?= e(t('events/index.015', 'εκκρεμείς')) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-primary" href="<?= e(url('/events/' . $ev['id'])) ?>" title="<?= e(t('events/index.017', 'Προβολή')) ?>"><i class="bi bi-eye"></i></a>
                  <?php if (current_role() === 'municipality_admin'): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/events/' . $ev['id'] . '/edit')) ?>" title="<?= e(t('events/index.018', 'Επεξεργασία')) ?>"><i class="bi bi-pencil"></i></a>
                    <a class="btn btn-outline-info" href="<?= e(url('/events/' . $ev['id'] . '/applications')) ?>" title="<?= e(t('events/index.013', 'Δηλώσεις')) ?>"><i class="bi bi-inbox"></i></a>
                  <?php endif; ?>
                  <?php if (in_array($ev['status'], ['confirmed','active'], true)): ?>
                    <a class="btn btn-outline-warning" href="<?= e(url('/operations/events/' . $ev['id'])) ?>" title="<?= e(t('events/index.019', 'Επιχειρησιακή Σελίδα')) ?>"><i class="bi bi-geo-alt"></i></a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
