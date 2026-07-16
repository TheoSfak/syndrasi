<?php
/* views/team/debrief.php
 * POST-EVENT DEBRIEF form for team admin.
 * $event   — the completed event row
 * $debrief — existing debrief row or null (pre-populate if re-editing)
 */
$d = $debrief ?? [];
$rating = (int) ($d['organization_rating'] ?? 3);
$playbook = $playbook ?? null;
$terms = authority_context((int) ($event['municipality_id'] ?? current_municipality_id()));
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
?>
<div class="container py-4" style="max-width:780px">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= url('/team/events') ?>"><?= e($eventPlural) ?></a></li>
      <li class="breadcrumb-item"><a href="<?= url('/team/events/' . $event['id']) ?>"><?= e($event['title']) ?></a></li>
      <li class="breadcrumb-item active">Post-Event Debrief</li>
    </ol>
  </nav>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-gradient text-white d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
      <i class="bi bi-clipboard2-check fs-4"></i>
      <div>
        <div class="fw-bold fs-5">Post-Event Debrief</div>
        <div class="small opacity-75"><?= e($event['title']) ?></div>
      </div>
      <?php if ($debrief): ?>
        <span class="badge bg-success ms-auto"><?= e(t('team/debrief.001', 'Ήδη υποβλήθηκε — μπορείτε να επεξεργαστείτε')) ?></span>
      <?php endif ?>
    </div>

    <div class="card-body p-4">
      <form method="post" action="<?= e(url('/team/events/' . $event['id'] . '/debrief')) ?>">
        <?= csrf_field() ?>

        <!-- Section 1: Numbers -->
        <h6 class="text-uppercase text-muted mb-3 fw-semibold" style="letter-spacing:.05em">
          <i class="bi bi-bar-chart-fill me-1 text-primary"></i><?= e(t('team/debrief.002', 'Αριθμητικά Στοιχεία')) ?>
        </h6>
        <div class="row g-3 mb-4">
          <div class="col-sm-4">
            <label class="form-label"><?= e(t('team/debrief.003', 'Εθελοντές που συμμετείχαν')) ?></label>
            <input type="number" name="actual_volunteers" min="0" class="form-control"
                   value="<?= e($d['actual_volunteers'] ?? '') ?>" required>
          </div>
          <div class="col-sm-4">
            <label class="form-label"><?= e(t('team/debrief.004', 'Συνολικές ώρες εθελοντισμού')) ?></label>
            <input type="number" name="volunteer_hours" min="0" step="0.5" class="form-control"
                   value="<?= e($d['volunteer_hours'] ?? '') ?>" required>
          </div>
          <div class="col-sm-4">
            <label class="form-label"><?= e(t('team/debrief.005', 'Αριθμός συμβάντων')) ?></label>
            <input type="number" name="incidents_count" min="0" class="form-control"
                   value="<?= e($d['incidents_count'] ?? 0) ?>">
          </div>
        </div>

        <!-- Section 2: Qualitative -->
        <h6 class="text-uppercase text-muted mb-3 fw-semibold" style="letter-spacing:.05em">
          <i class="bi bi-chat-square-text-fill me-1 text-success"></i><?= e(t('team/debrief.006', 'Αξιολόγηση')) ?> <?= e($eventSingular) ?>
        </h6>
        <div class="mb-3">
          <label class="form-label">Τι πήγε καλά;</label>
          <textarea name="what_went_well" rows="3" class="form-control"
                    placeholder="<?= e(t('team/debrief.014', 'Περιγράψτε τι λειτούργησε καλά…')) ?>"><?= e($d['what_went_well'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Τι μπορεί να βελτιωθεί;</label>
          <textarea name="what_went_wrong" rows="3" class="form-control"
                    placeholder="<?= e(t('team/debrief.015', 'Περιγράψτε προβλήματα ή περιθώρια βελτίωσης…')) ?>"><?= e($d['what_went_wrong'] ?? '') ?></textarea>
        </div>
        <div class="mb-4">
          <label class="form-label"><?= e(t('team/debrief.007', 'Περιγραφή συμβάντων')) ?> <span class="text-muted"><?= e(t('team/debrief.008', '(προαιρετικό)')) ?></span></label>
          <textarea name="incidents_description" rows="3" class="form-control"
                    placeholder="<?= e(t('team/debrief.016', 'Αναφέρετε λεπτομέρειες για τυχόν συμβάντα…')) ?>"><?= e($d['incidents_description'] ?? '') ?></textarea>
        </div>

        <?php if (!empty($playbook['debrief_questions'])): ?>
        <div class="alert alert-light border mb-4">
          <div class="fw-semibold mb-2"><i class="bi bi-journal-check me-1 text-primary"></i><?= e(t('team/debrief.009', 'Ερωτήσεις playbook')) ?></div>
          <ul class="mb-0 small">
            <?php foreach ($playbook['debrief_questions'] as $question): ?>
              <li><?= e($question) ?></li>
            <?php endforeach; ?>
          </ul>
          <div class="form-text mt-2"><?= e(t('team/debrief.010', 'Χρησιμοποιήστε τις παραπάνω ερωτήσεις για να συμπληρώσετε τα πεδία αξιολόγησης.')) ?></div>
        </div>
        <?php endif; ?>

        <!-- Section 3: Rating -->
        <h6 class="text-uppercase text-muted mb-3 fw-semibold" style="letter-spacing:.05em">
          <i class="bi bi-star-fill me-1 text-warning"></i><?= e(t('team/debrief.011', 'Βαθμολογία Οργάνωσης')) ?>
        </h6>
        <div class="mb-3">
          <label class="form-label d-block"><?= e(t('team/debrief.019', 'Πώς αξιολογείτε την οργάνωση της')) ?> <?= e($eventSingularLc) ?>;</label>
          <div class="d-flex gap-2" id="starRow">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <label class="star-label" title="<?= $s ?> <?= e(t('team/debrief.017', 'αστέρ')) ?><?= $s === 1 ? 'ι' : 'ια' ?>">
                <input type="radio" name="organization_rating" value="<?= $s ?>"
                       <?= $s === $rating ? 'checked' : '' ?> class="d-none star-radio">
                <i class="bi bi-star<?= $s <= $rating ? '-fill' : '' ?> fs-2 star-icon"
                   style="color:<?= $s <= $rating ? '#f59e0b' : '#d1d5db' ?>;cursor:pointer;transition:color .15s"></i>
              </label>
            <?php endfor ?>
            <span class="ms-2 align-self-center text-muted" id="starLabel"><?= $rating ?>/5</span>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label"><?= e(t('team/debrief.012', 'Σχόλια')) ?> <span class="text-muted"><?= e(t('team/debrief.008', '(προαιρετικό)')) ?></span></label>
          <textarea name="comments" rows="3" class="form-control"
                    placeholder="Οποιαδήποτε άλλα σχόλια για τη <?= e($eventSingularLc) ?>…"><?= e($d['comments'] ?? '') ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-circle me-1"></i>
            <?= $debrief ? 'Αποθήκευση Αλλαγών' : 'Υποβολή Debrief' ?>
          </button>
          <a href="<?= url('/team/events/' . $event['id']) ?>" class="btn btn-outline-secondary"><?= e(t('team/debrief.013', 'Ακύρωση')) ?></a>
        </div>
      </form>
    </div><!-- card-body -->
  </div><!-- card -->
</div>

<script>
(function () {
  const radios = document.querySelectorAll('.star-radio');
  const icons  = document.querySelectorAll('.star-icon');
  const label  = document.getElementById('starLabel');

  function paint(val) {
    icons.forEach((ic, i) => {
      const active = i < val;
      ic.className = 'bi bi-star' + (active ? '-fill' : '') + ' fs-2 star-icon';
      ic.style.color = active ? '#f59e0b' : '#d1d5db';
    });
    label.textContent = val + '/5';
  }

  radios.forEach((r, idx) => {
    r.addEventListener('change', () => paint(idx + 1));
  });

  // Hover preview
  icons.forEach((ic, idx) => {
    ic.addEventListener('mouseenter', () => paint(idx + 1));
    ic.addEventListener('mouseleave', () => {
      const checked = document.querySelector('.star-radio:checked');
      paint(checked ? parseInt(checked.value) : 0);
    });
  });
})();
</script>
