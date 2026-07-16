<?php
$terms = $terms ?? authority_context();
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$eventSingular = $terms['event_singular'] ?? 'Δράση';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0"><?= e(t('events/completed.001', 'Ολοκληρωμένες')) ?> <?= e($eventPlural) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('events/completed.012', 'Αρχείο αρχειοθετημένων')) ?> <?= e($eventPluralLc) ?>.</p>
  </div>
</div>

<ul class="nav nav-pills mb-3 small">
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events')) ?>"><?= e(t('events/completed.003', 'Ενεργές')) ?> <span class="badge text-bg-light ms-1"><?= (int) ($tabCounts['active'] ?? 0) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events/closed')) ?>"><?= e(t('events/completed.004', 'Κλειστές')) ?> <span class="badge text-bg-light ms-1"><?= (int) ($tabCounts['closed'] ?? 0) ?></span></a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= e(url('/events/completed')) ?>"><?= e(t('events/completed.001', 'Ολοκληρωμένες')) ?> <span class="badge text-bg-light ms-1"><?= (int) ($tabCounts['completed'] ?? 0) ?></span></a></li>
</ul>

<!-- Search -->
<form method="get" class="row g-2 mb-3">
  <div class="col-md-5">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="<?= e(t('events/completed.009', 'Αναζήτηση τίτλου...')) ?>" value="<?= e($q) ?>">
  </div>
  <div class="col-md-3">
    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>" title="<?= e(t('events/completed.010', 'Από')) ?>">
  </div>
  <div class="col-md-3">
    <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>" title="<?= e(t('events/completed.011', 'Έως')) ?>">
  </div>
  <div class="col-md-1">
    <button class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-search"></i></button>
  </div>
</form>

<?php if (!$events): ?>
  <div class="alert alert-info"><?= e(t('events/completed.013', 'Δεν βρέθηκαν ολοκληρωμένες')) ?> <?= e($eventPluralLc) ?>.</div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= e($eventSingular) ?></th>
            <th><?= e(t('events/completed.006', 'Ημερομηνία')) ?></th>
            <th class="text-center"><?= e(t('events/completed.007', 'Συμμετοχές')) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $e): ?>
            <tr class="text-muted">
              <td>
                <strong class="text-body"><?= e($e['title']) ?></strong>
                <?php if ($e['location_name']): ?>
                  <div class="small"><i class="bi bi-geo-alt"></i> <?= e($e['location_name']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small text-nowrap"><?= e(gr_datetime($e['start_datetime'])) ?></td>
              <td class="text-center"><?= (int) $e['applications_count'] ?></td>
              <td class="text-end">
                <a href="<?= e(url('/events/' . $e['id'])) ?>" class="btn btn-sm btn-outline-secondary"><?= e(t('events/completed.008', 'Προβολή')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
