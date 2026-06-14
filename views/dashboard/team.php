<h1 class="h3 mb-1">Πίνακας Ελέγχου</h1>
<p class="text-muted"><?= e($team['name']) ?></p>

<?php if ($todayOperational): ?>
  <div class="card shadow-sm mb-4 border-warning">
    <div class="card-header bg-warning-subtle fw-semibold"><i class="bi bi-broadcast me-1"></i> Σημερινές επιχειρησιακές ενέργειες</div>
    <div class="list-group list-group-flush">
      <?php foreach ($todayOperational as $ev): ?>
        <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <strong><?= e($ev['title']) ?></strong>
            <div class="text-muted small">
              <?= e(gr_time($ev['start_datetime'])) ?>–<?= e(gr_time($ev['end_datetime'])) ?> ·
              Εγκεκριμένα άτομα: <?= (int) $ev['approved_people'] ?>
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="<?= e(url('/team/live/' . $ev['id'])) ?>" class="btn btn-danger fw-bold">
              <i class="bi bi-broadcast me-1"></i>Live Mode
            </a>
            <a href="<?= e(url('/team/operations/events/' . $ev['id'])) ?>" class="btn btn-outline-warning btn-sm">
              <i class="bi bi-list-ul me-1"></i>Πλήρης
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-primary-subtle text-primary mb-2"><i class="bi bi-calendar-event"></i></div>
      <div class="stat-value"><?= (int) $availableEvents ?></div>
      <div class="text-muted small">Νέες διαθέσιμες δράσεις</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-warning-subtle text-warning mb-2"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-value"><?= (int) $pendingApplications ?></div>
      <div class="text-muted small">Δηλώσεις σε Εγκρίσεις</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-value"><?= count($upcomingApproved) ?></div>
      <div class="text-muted small">Εγκεκριμένες επερχόμενες</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100"><div class="card-body">
      <div class="stat-icon bg-info-subtle text-info mb-2"><i class="bi bi-clock-history"></i></div>
      <div class="stat-value"><?= e(gr_number($stats['volunteer_hours'])) ?></div>
      <div class="text-muted small">Ώρες παρουσίας <?= (int) $stats['year'] ?></div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6 col-lg-3">
    <a href="<?= e(url('/team/events')) ?>" class="btn btn-op btn-primary"><i class="bi bi-calendar-event me-1"></i> Δράσεις</a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="<?= e(url('/team/applications')) ?>" class="btn btn-op btn-outline-primary"><i class="bi bi-inbox me-1"></i> Οι Δηλώσεις μας</a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="<?= e(url('/team/statistics')) ?>" class="btn btn-op btn-outline-secondary"><i class="bi bi-bar-chart me-1"></i> Στατιστικά</a>
  </div>
  <div class="col-md-6 col-lg-3">
    <a href="<?= e(url('/notifications')) ?>" class="btn btn-op btn-outline-secondary"><i class="bi bi-bell me-1"></i> Ειδοποιήσεις</a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar3 me-1"></i> Εγκεκριμένες επερχόμενες δράσεις</div>
  <?php if (!$upcomingApproved): ?>
    <div class="card-body text-muted">Δεν υπάρχουν εγκεκριμένες επερχόμενες δράσεις.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Δράση</th><th>Ημερομηνία</th><th>Εγκεκριμένα άτομα</th><th>Κατάσταση</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($upcomingApproved as $ev): ?>
            <tr>
              <td><?= e($ev['title']) ?></td>
              <td><?= e(gr_datetime($ev['start_datetime'])) ?></td>
              <td><?= (int) $ev['approved_people'] ?></td>
              <td><?= status_badge($ev['status']) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/team/events/' . $ev['id'])) ?>">Προβολή</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
