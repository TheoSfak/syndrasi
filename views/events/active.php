<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0">Ενεργές Δράσεις</h1>
    <p class="text-muted small mb-0">Δράσεις σε εξέλιξη ή αναμονή έναρξης.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(url('/events/calendar')) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar3 me-1"></i>Ημερολόγιο
    </a>
    <a href="<?= e(url('/events/drafts')) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-file-earmark me-1"></i>Πρόχειρα
    </a>
    <?php if (!empty($templates)): ?>
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" type="button">
        <i class="bi bi-bookmark me-1"></i>Νέα από πρότυπο
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($templates as $tpl): ?>
        <li class="d-flex align-items-center justify-content-between pe-2">
          <a class="dropdown-item" href="<?= e(url('/events/create?template=' . (int) $tpl['id'])) ?>"><?= e($tpl['name']) ?></a>
          <form method="post" action="<?= e(url('/event-templates/' . (int) $tpl['id'] . '/delete')) ?>"
                onsubmit="return confirm('Διαγραφή προτύπου;')">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-link text-danger p-0" title="Διαγραφή"><i class="bi bi-trash"></i></button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
    <a href="<?= e(url('/events/create')) ?>" class="btn btn-sm btn-primary">
      <i class="bi bi-plus-lg me-1"></i>Νέα Δράση
    </a>
  </div>
</div>

<!-- Status navigation -->
<ul class="nav nav-pills mb-3 small">
  <li class="nav-item"><a class="nav-link active" href="<?= e(url('/events')) ?>">Ενεργές <span class="badge text-bg-light ms-1"><?= count($events) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events/closed')) ?>">Κλειστές</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events/completed')) ?>">Ολοκληρωμένες</a></li>
</ul>

<?php if (!$events): ?>
  <div class="alert alert-info">Δεν υπάρχουν ενεργές δράσεις αυτή τη στιγμή.</div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Δράση</th>
            <th>Ημερομηνία</th>
            <th>Κατάσταση</th>
            <th class="text-center">Δηλώσεις</th>
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
              <td class="small text-nowrap">
                <?= e(gr_datetime($e['start_datetime'])) ?>
              </td>
              <td><?= status_badge($e['status']) ?></td>
              <td class="text-center">
                <?= (int) $e['applications_count'] ?>
                <?php if ((int) $e['pending_count'] > 0): ?>
                  <span class="badge text-bg-warning ms-1"><?= (int) $e['pending_count'] ?> νέες</span>
                <?php endif; ?>
              </td>
              <td class="text-end text-nowrap">
                <a href="<?= e(url('/events/' . $e['id'])) ?>" class="btn btn-sm btn-outline-primary">Προβολή</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
