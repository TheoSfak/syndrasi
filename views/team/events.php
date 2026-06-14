<h1 class="h3 mb-1">Δράσεις</h1>
<p class="text-muted">Ενεργές δράσεις του δήμου και ιστορικό συμμετοχών της ομάδας σας.</p>

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
        Δεν υπάρχουν διαθέσιμες δράσεις αυτή τη στιγμή.
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
              