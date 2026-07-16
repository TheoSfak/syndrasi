<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0"><i class="bi bi-broadcast-pin text-danger me-1"></i><?= e(t('mobilizations/index.001', 'Κάλεσμα Έκτακτης Ανάγκης')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('mobilizations/index.002', 'Άμεση κινητοποίηση εθελοντών για επείγοντα περιστατικά.')) ?></p>
  </div>
  <a href="<?= e(url('/mobilizations/new')) ?>" class="btn btn-danger">
    <i class="bi bi-plus-lg me-1"></i><?= e(t('mobilizations/index.003', 'Νέο Κάλεσμα')) ?>
  </a>
</div>

<?php if (empty($mobilizations)): ?>
  <div class="card shadow-sm">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-broadcast d-block mb-2" style="font-size:2rem;"></i>
      <?= e(t('mobilizations/index.004', 'Δεν υπάρχουν καλέσματα ακόμη. Πατήστε «Νέο Κάλεσμα» για άμεση κινητοποίηση.')) ?>
    </div>
  </div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= e(t('mobilizations/index.005', 'Τίτλος')) ?></th>
            <th><?= e(t('mobilizations/index.006', 'Σοβαρότητα')) ?></th>
            <th><?= e(t('mobilizations/index.007', 'Κατάσταση')) ?></th>
            <th class="text-center"><?= e(t('mobilizations/index.008', 'Κλήθηκαν')) ?></th>
            <th class="text-center"><?= e(t('mobilizations/index.009', 'Δήλωσαν «Έρχομαι»')) ?></th>
            <th><?= e(t('mobilizations/index.010', 'Έναρξη')) ?></th>
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
                  <span class="badge text-bg-danger"><?= e(t('mobilizations/index.011', 'Ενεργό')) ?></span>
                <?php elseif ($m['status'] === 'open'): ?>
                  <span class="badge text-bg-warning"><?= e(t('mobilizations/index.012', 'Ανοιχτό')) ?></span>
                <?php else: ?>
                  <span class="badge text-bg-secondary"><?= e(t('mobilizations/index.013', 'Έκλεισε')) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= (int) $m['targeted'] ?></td>
              <td class="text-center fw-semibold text-success"><?= (int) $m['confirmed'] ?></td>
              <td class="small text-muted"><?= e(gr_datetime($m['started_at'])) ?></td>
              <td class="text-end">
                <a href="<?= e(url('/mobilizations/' . $m['id'])) ?>" class="btn btn-sm btn-outline-primary">
                  <?= e(t('mobilizations/index.014', 'Πίνακας')) ?> <i class="bi bi-arrow-right"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
