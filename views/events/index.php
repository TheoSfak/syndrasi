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
<p class="text-muted">Όλες οι <?= e($eventPluralLc) ?> του φορέα.</p>

<form class="card shadow-sm mb-3" method="get" action="<?= e(url('/events')) ?>">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small mb-1">Αναζήτηση τίτλου</label>
      <input type="text" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="π.χ. φεστιβάλ">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Κατάσταση</label>
      <select name="status" class="form-select">
        <option value="">Όλες</option>
        <?php foreach (['draft','open','review','confirmed','active','completed','cancelled'] as $st): ?>
          <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= e(greek_status($st)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Κατηγορία</label>
      <select name="category_id" class="form-select">
        <option value="">Όλες</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Από</label>
      <input type="date" name="from" class="form-control" value="<?= e($filters['from']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small mb-1">Έως</label>
      <input type="date" name="to" class="form-control" value="<?= e($filters['to']) ?>">
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Φίλτρα</button>
      <a class="btn btn-outline-secondary" href="<?= e(url('/events')) ?>">Καθαρισμός</a>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <?php if (!$events): ?>
    <div class="card-body text-muted">Δεν βρέθηκαν <?= e($eventPluralLc) ?> με αυτά τα κριτήρια.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th><?= e($eventSingular) ?></th><th>Τύπος</th><th>Ημερομηνία</th><th>Κατάσταση</th><th>Δηλώσεις</th><th class="text-end">Ενέργειες</th></tr>
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
                  <span class="badge text-bg-warning"><?= (int) $ev['pending_count'] ?> εκκρεμείς</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-primary" href="<?= e(url('/events/' . $ev['id'])) ?>" title="Προβολή"><i class="bi bi-eye"></i></a>
                  <?php if (current_role() === 'municipality_admin'): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/events/' . $ev['id'] . '/edit')) ?>" title="Επεξεργασία"><i class="bi bi-pencil"></i></a>
                    <a class="btn btn-outline-info" href="<?= e(url('/events/' . $ev['id'] . '/applications')) ?>" title="Δηλώσεις"><i class="bi bi-inbox"></i></a>
                  <?php endif; ?>
                  <?php if (in_array($ev['status'], ['confirmed','active'], true)): ?>
                    <a class="btn btn-outline-warning" href="<?= e(url('/operations/events/' . $ev['id'])) ?>" title="Επιχειρησιακή Σελίδα"><i class="bi bi-geo-alt"></i></a>
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
