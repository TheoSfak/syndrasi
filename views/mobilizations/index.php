<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0"><i class="bi bi-broadcast-pin text-danger me-1"></i>Κάλεσμα Έκτακτης Ανάγκης</h1>
    <p class="text-muted small mb-0">Άμεση κινητοποίηση εθελοντών για επείγοντα περιστατικά.</p>
  </div>
  <a href="<?= e(url('/mobilizations/new')) ?>" class="btn btn-danger">
    <i class="bi bi-plus-lg me-1"></i>Νέο Κάλεσμα
  </a>
</div>

<?php if (empty($mobilizations)): ?>
  <div class="card shadow-sm">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-broadcast d-block mb-2" style="font-size:2rem;"></i>
      Δεν υπάρχουν καλέσματα ακόμη. Πατήστε «Νέο Κάλεσμα» για άμεση κινητοποίηση.
    </div>
  </div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Τίτλος</th>
            <th>Σοβαρότητα</th>
            <th>Κατάσταση</th>
            <th class="text-center">Κλήθηκαν</th>
            <th class="text-center">Δήλωσαν «Έρχομαι»</th>
            <th>Έναρξη</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mobilizations as $m): ?>
            <tr>
              <td class="fw-semibold"><?= e($m['title']) ?></td>
              <td><span class="badge text-bg-<?= e(status_color($m['severity'])) ?>"><?= e(severity_label($m['severity'])) ?></span></td>
              <td>
                <?php if ($m['status'] === 'active'): ?>
                  <span class="badge text-bg-danger">Ενεργό</span>
                <?php elseif ($m['status'] === 'open'): ?>
                  <span class="badge text-bg-warning">Ανοιχτό</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Έκλεισε</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= (int) $m['targeted'] ?></td>
              <td class="text-center fw-semibold text-success"><?= (int) $m['confirmed'] ?></td>
              <td class="small text-muted"><?= e(gr_datetime($m['started_at'])) ?></td>
              <td class="text-end">
                <a href="<?= e(url('/mobilizations/' . $m['id'])) ?>" class="btn btn-sm btn-outline-primary">
                  Πίνακας <i class="bi bi-arrow-right"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
