<?php
$terms = authority_context(current_municipality_id());
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$orgLabel = $terms['short_name'] ?? 'Φορέας';
?>
<h1 class="h3 mb-1"><?= e($eventPlural) ?></h1>
<p class="text-muted">Ενεργές <?= e($eventPluralLc) ?> του φορέα (<?= e($orgLabel) ?>) και ιστορικό συμμετοχών της ομάδας σας.</p>

<!-- Nav pills -->
<ul class="nav nav-pills mb-3 small" id="eventsNav">
  <li class="nav-item">
    <a class="nav-link active" href="#tab-active" data-bs-toggle="tab">
      Ενεργές
      <?php if ($events): ?><span class="badge text-bg-light ms-1"><?= count($events) ?></span><?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="#tab-closed" data-bs-toggle="tab">
      Κλειστές / Ολοκληρωμένες
      <?php if ($closedEvents): ?><span class="badge text-bg-light ms-1"><?= count($closedEvents) ?></span><?php endif; ?>
    </a>
  </li>
</ul>

<div class="tab-content">

  <!-- Ενεργές -->
  <div class="tab-pane fade show active" id="tab-active">
    <?php if (!$events): ?>
      <div class="card shadow-sm"><div class="card-body text-muted">
        Δεν υπάρχουν διαθέσιμες <?= e($eventPluralLc) ?> αυτή τη στιγμή.
      </div></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($events as $ev): ?>
          <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h2 class="h6 mb-0"><?= e($ev['title']) ?></h2>
                  <?= status_badge($ev['status']) ?>
                </div>
                <div class="small text-muted mb-2">
                  <i class="bi bi-tag me-1"></i><?= e($ev['category_name'] ?: 'Χωρίς κατηγορία') ?><br>
                  <i class="bi bi-calendar me-1"></i><?= e(gr_datetime($ev['start_datetime'])) ?><br>
                  <?php if ($ev['location_name']): ?><i class="bi bi-geo-alt me-1"></i><?= e($ev['location_name']) ?><br><?php endif; ?>
                  <i class="bi bi-people me-1"></i>Ζητούνται <?= (int) $ev['requested_people'] ?> άτομα
                </div>

                <?php if ($ev['application_id']): ?>
                  <div class="mb-2">
                    <span class="text-muted small">Η δήλωσή σας:</span>
                    <?= status_badge($ev['application_status']) ?>
                    <?php if ($ev['application_status'] === 'approved'): ?>
                      <span class="small text-success fw-semibold"><?= (int) $ev['approved_people'] ?> άτομα</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="mt-auto d-grid gap-2">
                  <a href="<?= e(url('/team/events/' . $ev['id'])) ?>" class="btn btn-outline-primary">
                    <?= $ev['application_id'] ? 'Προβολή' : 'Προβολή & Δήλωση' ?>
                  </a>
                  <?php if ($ev['status'] === 'active' && $ev['application_status'] === 'approved'): ?>
                    <a href="<?= e(url('/team/operations/events/' . $ev['id'])) ?>" class="btn btn-warning">
                      <i class="bi bi-geo-alt me-1"></i>Επιχειρησιακές Ενέργειες
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Κλειστές / Ολοκληρωμένες -->
  <div class="tab-pane fade" id="tab-closed">
    <?php if (!$closedEvents): ?>
      <div class="card shadow-sm"><div class="card-body text-muted">Δεν υπάρχουν κλειστές <?= e($eventPluralLc) ?> με συμμετοχή της ομάδας σας.</div></div>
    <?php else: ?>
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= e($eventSingular) ?></th>
                <th>Ημερομηνία</th>
                <th class="text-center">Άτομα</th>
                <th>Κατάσταση</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($closedEvents as $ev): ?>
                <tr class="text-muted">
                  <td><strong class="text-body"><?= e($ev['title']) ?></strong></td>
                  <td class="small text-nowrap"><?= e(gr_datetime($ev['start_datetime'])) ?></td>
                  <td class="text-center small">
                    <?php if ($ev['actual_people'] !== null): ?>
                      <span class="fw-semibold text-body"><?= (int) $ev['actual_people'] ?></span>
                      <span class="text-muted">/ <?= (int) $ev['approved_people'] ?></span>
                    <?php else: ?>
                      <?= (int) $ev['approved_people'] ?>
                    <?php endif; ?>
                  </td>
                  <td><?= status_badge($ev['status']) ?></td>
                  <td class="text-end d-flex gap-1 justify-content-end">
                    <a href="<?= e(url('/team/events/' . $ev['id'])) ?>" class="btn btn-sm btn-outline-secondary">Προβολή</a>
                    <?php if ($ev['status'] === 'completed'): ?>
                      <a href="<?= e(url('/team/events/' . $ev['id'] . '/debrief')) ?>"
                         class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-clipboard2-check me-1"></i>Debrief
                      </a>
                    <?php endif ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
(function(){
  var hash = window.location.hash;
  if (hash) {
    var t = document.querySelector('#eventsNav a[href="' + hash + '"]');
    if (t) { new bootstrap.Tab(t).show(); }
  }
})();
</script>
