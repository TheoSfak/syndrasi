<?php
$terms = $terms ?? authority_context();
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$eventSingular = $terms['event_singular'] ?? 'Δράση';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0"><?= e(t('events/drafts.001', 'Πρόχειρες')) ?> <?= e($eventPlural) ?></h1>
    <p class="text-muted small mb-0"><?= e($eventPlural) ?> <?= e(t('events/drafts.002', 'που δεν έχουν δημοσιευτεί ακόμα στις ομάδες.')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(url('/events')) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i><?= e(t('events/drafts.003', 'Ενεργές')) ?>
    </a>
    <a href="<?= e(url('/events/create')) ?>" class="btn btn-sm btn-primary">
      <i class="bi bi-plus-lg me-1"></i><?= e($terms['event_new'] ?? 'Νέα Δράση') ?>
    </a>
  </div>
</div>

<?php if (!$events): ?>
  <div class="alert alert-info">
    <?= e(t('events/drafts.010', 'Δεν υπάρχουν πρόχειρες')) ?> <?= e($eventPluralLc) ?>.
    <a href="<?= e(url('/events/create')) ?>"><?= e(t('events/drafts.011', 'Δημιουργήστε μία νέα')) ?> <?= e(mb_strtolower($eventSingular)) ?>.</a>
  </div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= e($eventSingular) ?></th>
            <th><?= e(t('events/drafts.006', 'Προγραμματισμένη για')) ?></th>
            <th><?= e(t('events/drafts.007', 'Δημιουργήθηκε')) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $e): ?>
            <tr>
              <td>
                <strong><?= e($e['title']) ?></strong>
                <?php if ($e['location_name']): ?>
                  <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($e['location_name']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small text-nowrap"><?= e(gr_datetime($e['start_datetime'])) ?></td>
              <td class="small text-nowrap"><?= e(gr_datetime($e['created_at'])) ?></td>
              <td class="text-end text-nowrap">
                <a href="<?= e(url('/events/' . $e['id'])) ?>" class="btn btn-sm btn-outline-primary me-1"><?= e(t('events/drafts.008', 'Προβολή')) ?></a>
                <a href="<?= e(url('/events/' . $e['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary"><?= e(t('events/drafts.009', 'Επεξεργασία')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
