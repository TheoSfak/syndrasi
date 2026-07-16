<?php
$terms = authority_context(current_municipality_id());
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$orgLabel = $terms['short_name'] ?? 'φορέα';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0"><?= e(t('reports/index.001', 'Αναφορές & Εξαγωγές')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('reports/index.002', 'Εξαγωγές σε CSV και εκτυπώσιμα PDF για επίσημη χρήση.')) ?></p>
  </div>
</div>

<div class="row g-4">

  <!-- General exports -->
  <div class="col-lg-5">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-download me-1"></i> <?= e(t('reports/index.003', 'Γενικές εξαγωγές CSV')) ?></div>
      <div class="list-group list-group-flush">
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= e(url('/exports/events')) ?>">
          <?= e(t('reports/index.004', 'Λίστα')) ?> <?= e($eventPluralLc) ?>
          <span class="badge text-bg-secondary"><i class="bi bi-filetype-csv"></i> CSV</span>
        </a>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= e(url('/exports/team-statistics')) ?>">
          <?= e(t('reports/index.005', 'Ετήσια στατιστικά ομάδων')) ?>
          <span class="badge text-bg-secondary"><i class="bi bi-filetype-csv"></i> CSV</span>
        </a>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= e(url('/exports/municipality-statistics')) ?>">
          <?= e(t('reports/index.006', 'Ετήσια στατιστικά φορέα')) ?>
          <span class="badge text-bg-secondary"><i class="bi bi-filetype-csv"></i> CSV</span>
        </a>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= e(url('/exports/awards')) ?>">
          <?= e(t('reports/index.007', 'Λίστα επιβράβευσης')) ?>
          <span class="badge text-bg-secondary"><i class="bi bi-filetype-csv"></i> CSV</span>
        </a>
      </div>
    </div>

    <!-- Annual report PDF -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-file-earmark-bar-graph me-1 text-primary"></i> <?= e(t('reports/index.008', 'Ετήσια Έκθεση Εθελοντισμού PDF')) ?>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          <?= e(t('reports/index.031', '4 σελίδες: εξώφυλλο, μηνιαία ανάλυση, κατάλογος')) ?> <?= e($eventPluralLc) ?> <?= e(t('reports/index.032', 'και κατάταξη ομάδων. Κατάλληλη για επίσημη παρουσίαση του φορέα.')) ?>
        </p>
        <?php $curYear = (int) date('Y'); ?>
        <div class="d-flex gap-2 flex-wrap">
          <?php for ($y = $curYear; $y >= $curYear - 3; $y--): ?>
            <a href="<?= e(url('/reports/pdf/annual/' . $y)) ?>" target="_blank"
               class="btn btn-sm <?= $y === $curYear ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">
              <i class="bi bi-file-earmark-bar-graph me-1"></i><?= $y ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <!-- Annual award PDF -->
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-trophy me-1 text-warning"></i> <?= e(t('reports/index.010', 'Ετήσια Επιβράβευση PDF')) ?>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          <?= e(t('reports/index.011', 'Εκτυπώσιμη αναφορά βραβείων & κατάταξης για τελετή επιβράβευσης. Επιλέξτε έτος:')) ?>
        </p>
        <div class="d-flex gap-2 flex-wrap">
          <?php for ($y = $curYear; $y >= $curYear - 3; $y--): ?>
            <a href="<?= e(url('/reports/pdf/awards/' . $y)) ?>" target="_blank"
               class="btn btn-sm <?= $y === $curYear ? 'btn-warning fw-bold' : 'btn-outline-secondary' ?>">
              <i class="bi bi-printer me-1"></i><?= $y ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Per-event exports + PDFs -->
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-calendar-event me-1"></i> <?= e(t('reports/index.012', 'Αναφορές ανά')) ?> <?= e($eventSingularLc) ?>
      </div>
      <?php if (!$events): ?>
        <div class="card-body text-muted"><?= e(t('reports/index.033', 'Δεν υπάρχουν')) ?> <?= e($eventPluralLc) ?>.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th><?= e($eventSingular) ?></th>
                <th><?= e(t('reports/index.014', 'Ημερομηνία')) ?></th>
                <th><?= e(t('reports/index.015', 'Κατάσταση')) ?></th>
                <th class="text-end"><?= e(t('reports/index.016', 'Εξαγωγή')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($events as $ev): ?>
                <tr>
                  <td class="fw-semibold"><?= e($ev['title']) ?></td>
                  <td class="small text-nowrap"><?= e(gr_date($ev['start_datetime'])) ?></td>
                  <td><?= status_badge($ev['status']) ?></td>
                  <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end flex-wrap">
                      <!-- CSV exports -->
                      <a class="btn btn-sm btn-outline-secondary"
                         href="<?= e(url('/exports/events/' . $ev['id'] . '/applications')) ?>"
                         title="<?= e(t('reports/index.027', 'Δηλώσεις CSV')) ?>">
                        <i class="bi bi-filetype-csv"></i> <?= e(t('reports/index.017', 'Δηλώσεις')) ?>
                      </a>
                      <a class="btn btn-sm btn-outline-secondary"
                         href="<?= e(url('/exports/events/' . $ev['id'] . '/coverage')) ?>"
                         title="<?= e(t('reports/index.028', 'Κάλυψη CSV')) ?>">
                        <i class="bi bi-filetype-csv"></i> <?= e(t('reports/index.018', 'Κάλυψη')) ?>
                      </a>
                      <!-- PDF reports (only for completed/active) -->
                      <?php if (in_array($ev['status'], ['active','closed','completed'])): ?>
                        <a class="btn btn-sm btn-outline-danger"
                           href="<?= e(url('/reports/pdf/event/' . $ev['id'] . '/coverage')) ?>"
                           target="_blank" title="<?= e(t('reports/index.029', 'Αναφορά Κάλυψης PDF')) ?>">
                          <i class="bi bi-file-earmark-pdf"></i> <?= e(t('reports/index.019', 'PDF Κάλυψη')) ?>
                        </a>
                        <a class="btn btn-sm btn-outline-success"
                           href="<?= e(url('/reports/pdf/event/' . $ev['id'] . '/certificate')) ?>"
                           target="_blank" title="<?= e(t('reports/index.030', 'Πιστοποιητικά Ομάδων')) ?>">
                          <i class="bi bi-award"></i> <?= e(t('reports/index.020', 'Πιστοποιητικά')) ?>
                        </a>
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

    <!-- PDF legend -->
    <div class="mt-3 p-3 bg-light rounded border d-flex gap-3 flex-wrap align-items-start" style="font-size:12px">
      <div><i class="bi bi-file-earmark-pdf text-danger me-1"></i><strong><?= e(t('reports/index.019', 'PDF Κάλυψη')) ?></strong> <?= e(t('reports/index.021', '— Επίσημη αναφορά ανάπτυξης ομάδων με υπογραφές. Εκτυπώνεται από browser.')) ?></div>
      <div><i class="bi bi-award text-success me-1"></i><strong><?= e(t('reports/index.020', 'Πιστοποιητικά')) ?></strong> <?= e(t('reports/index.022', '— Μία σελίδα ανά ομάδα, κατάλληλη για επίσημη απονομή.')) ?></div>
      <div><i class="bi bi-trophy text-warning me-1"></i><strong><?= e(t('reports/index.023', 'Επιβράβευση')) ?></strong> <?= e(t('reports/index.024', '— Τελετή βράβευσης με κάλυψη + κατάταξη.')) ?></div>
      <div><i class="bi bi-file-earmark-bar-graph text-primary me-1"></i><strong><?= e(t('reports/index.025', 'Ετήσια Έκθεση')) ?></strong> <?= e(t('reports/index.034', '— 4 σελίδες για')) ?> <?= e($orgLabel) ?>: <?= e($eventPluralLc) ?><?= e(t('reports/index.035', ', ανάλυση, κατάταξη ομάδων.')) ?></div>
    </div>
  </div>

</div>
