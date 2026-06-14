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
