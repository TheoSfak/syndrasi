<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0">Αναφορές & Εξαγωγές</h1>
    <p class="text-muted small mb-0">Εξαγωγές σε CSV και εκτυπώσιμα PDF για επίσημη χρήση.</p>
  </div>
</div>

<div class="row g-4">

  <!-- General exports -->
  <div class="col-lg-5">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-download me-1"></i> Γενικές εξαγωγές CSV</div>
      <div class="list-group list-group-flush">
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= e(url('/exports/events')) ?>">
          Λίστα δράσεων
          <span class="badge text-bg-secondary"><i class="bi bi-filetype-csv"></i> CSV</span>
        </a>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= e(url('/exports/team-statistics')) ?>">
          Ετήσια στατιστικά ομάδων
          <span class="badge text-bg-secondary"><i class="bi bi-filetype-csv"></i> CSV</span>
        </a>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= e(url('/exports/municipality-statistics')) ?>">
          Ετήσια στατιστικά δήμου
          <span class="badge text-bg-secondary"><i class="bi bi-filetype-csv"></i> CSV</span>
        </a>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= e(url('/exports/awards')) ?>">
          Λίστα επιβράβευσης
          <span class="badge text-bg-secondary"><i class="bi bi-filetype-csv"></i> CSV</span>
        </a>
      </div>
    </div>

    <!-- Annual report PDF -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-file-earmark-bar-graph me-1 text-primary"></i> Ετήσια Έκθεση Εθελοντισμού PDF
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          4 σελίδες: εξώφυλλο, μηνιαία ανάλυση, κατάλογος δράσεων και κατάταξη ομάδων. Κατάλληλη για Δημοτικό Συμβούλιο.
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
        <i class="bi bi-trophy me-1 text-warning"></i> Ετήσια Επιβράβευση PDF
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          Εκτυπώσιμη αναφορά βραβείων & κατάταξης για τελετή επιβράβευσης. Επιλέξτε έτος:
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
        <i class="bi bi-calendar-event me-1"></i> Αναφορές ανά δράση
      </div>
      <?php if (!$events): ?>
        <div class="card-body text-muted">Δεν υπάρχουν δράσεις.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Δράση</th>
                <th>Ημερομηνία</th>
                <th>Κατάσταση</th>
                <th class="text-end">Εξαγωγή</th>
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
                         title="Δηλώσεις CSV">
                        <i class="bi bi-filetype-csv"></i> Δηλώσεις
                      </a>
                      <a class="btn btn-sm btn-outline-secondary"
                         href="<?= e(url('/exports/events/' . $ev['id'] . '/coverage')) ?>"
                         title="Κάλυψη CSV">
                        <i class="bi bi-filetype-csv"></i> Κάλυψη
                      </a>
                      <!-- PDF reports (only for completed/active) -->
                      <?php if (in_array($ev['status'], ['active','closed','completed'])): ?>
                        <a class="btn btn-sm btn-outline-danger"
                           href="<?= e(url('/reports/pdf/event/' . $ev['id'] . '/coverage')) ?>"
                           target="_blank" title="Αναφορά Κάλυψης PDF">
                          <i class="bi bi-file-earmark-pdf"></i> PDF Κάλυψη
                        </a>
                        <a class="btn btn-sm btn-outline-success"
                           href="<?= e(url('/reports/pdf/event/' . $ev['id'] . '/certificate')) ?>"
                           target="_blank" title="Πιστοποιητικά Ομάδων">
                          <i class="bi bi-award"></i> Πιστοποιητικά
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
      <div><i class="bi bi-file-earmark-pdf text-danger me-1"></i><strong>PDF Κάλυψη</strong> — Επίσημη αναφορά ανάπτυξης ομάδων με υπογραφές. Εκτυπώνεται από browser.</div>
      <div><i class="bi bi-award text-success me-1"></i><strong>Πιστοποιητικά</strong> — Μία σελίδα ανά ομάδα, κατάλληλη για επίσημη απονομή.</div>
      <div><i class="bi bi-trophy text-warning me-1"></i><strong>Επιβράβευση</strong> — Τελετή βράβευσης με κάλυψη + κατάταξη.</div>
      <div><i class="bi bi-file-earmark-bar-graph text-primary me-1"></i><strong>Ετήσια Έκθεση</strong> — 4 σελίδες για Δημοτικό Συμβούλιο: δράσεις, ανάλυση, κατάταξη ομάδων.</div>
    </div>
  </div>

</div>
