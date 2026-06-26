<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0">Κλειστές Δράσεις</h1>
    <p class="text-muted small mb-0">Δράσεις που έληξαν — εκκρεμεί αρχειοθέτηση και τελικές διορθώσεις.</p>
  </div>
</div>

<ul class="nav nav-pills mb-3 small">
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events')) ?>">Ενεργές <span class="badge text-bg-light ms-1"><?= (int) ($tabCounts['active'] ?? 0) ?></span></a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= e(url('/events/closed')) ?>">Κλειστές <span class="badge text-bg-light ms-1"><?= (int) ($tabCounts['closed'] ?? 0) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events/completed')) ?>">Ολοκληρωμένες <span class="badge text-bg-light ms-1"><?= (int) ($tabCounts['completed'] ?? 0) ?></span></a></li>
</ul>

<?php if (!$events): ?>
  <div class="alert alert-success">Δεν υπάρχουν κλειστές δράσεις προς αρχειοθέτηση.</div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Δράση</th>
            <th>Ημερομηνία</th>
            <th class="text-center">Εγκεκρ. Ομάδες</th>
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
              <td class="text-center">
                <?= (int) $e['applications_count'] ?>
              </td>
              <td class="text-end text-nowrap">
                <a href="<?= e(url('/events/' . $e['id'] . '/reconcile')) ?>"
                   class="btn btn-sm btn-outline-info me-1">
                  <i class="bi bi-clipboard-check me-1"></i>Αρχειοθέτηση
                </a>
                <a href="<?= e(url('/events/' . $e['id'])) ?>" class="btn btn-sm btn-outline-secondary">Προβολή</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
