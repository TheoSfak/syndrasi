<h1 class="h3 mb-1">Δηλώσεις Συμμετοχής</h1>
<p class="text-muted">Όλες οι εκκρεμείς δηλώσεις ομάδων, ομαδοποιημένες ανά δράση.</p>

<?php if (!$applications): ?>
  <div class="card shadow-sm"><div class="card-body text-muted">
    Δεν υπάρχουν εκκρεμείς δηλώσεις συμμετοχής. Όλα είναι ενημερωμένα!
  </div></div>
<?php else: ?>
  <?php
  $grouped = [];
  foreach ($applications as $a) {
      $grouped[$a['event_id']]['title'] = $a['event_title'];
      $grouped[$a['event_id']]['start'] = $a['start_datetime'];
      $grouped[$a['event_id']]['items'][] = $a;
  }
  ?>
  <?php foreach ($grouped as $eventId => $g): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <strong><?= e($g['title']) ?></strong>
          <span class="text-muted small ms-2"><?= e(gr_datetime($g['start'])) ?></span>
        </div>
        <a class="btn btn-sm btn-primary" href="<?= e(url('/events/' . $eventId . '/applications')) ?>">
          <i class="bi bi-pencil-square me-1"></i>Εγκρίσεις
        </a>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($g['items'] as $a): ?>
          <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
            <div>
              <strong><?= e($a['team_name']) ?></strong>
              — <?= (int) $a['offered_people'] ?> άτομα
              <?= $a['offered_vehicle'] ? '· όχημα' : '' ?>
              <?= $a['offered_medical_equipment'] ? '· υγειον. εξοπλισμός' : '' ?>
            </div>
            <span class="text-muted small"><?= e(gr_datetime($a['submitted_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
