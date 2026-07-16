<?php
$terms = $terms ?? authority_context((int) ($event['municipality_id'] ?? 0));
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$orgShort = $terms['short_name'] ?? $terms['short'] ?? 'Φορέας';
$requestedItems = [];
if (!empty($event['requested_items_json'])) {
    $decodedItems = json_decode((string) $event['requested_items_json'], true);
    $requestedItems = is_array($decodedItems) ? array_values(array_filter(array_map('trim', $decodedItems), fn($item) => $item !== '')) : [];
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-start mb-1 gap-2">
  <div>
    <h1 class="h3 mb-1"><?= e($event['title']) ?> <?= status_badge($event['status']) ?></h1>
    <p class="text-muted mb-0">
      <?= e($event['category_name'] ?: 'Χωρίς κατηγορία') ?> ·
      <?= e(gr_datetime($event['start_datetime'])) ?> – <?= e(gr_datetime($event['end_datetime'])) ?>
      <?php if ($event['location_name']): ?> · <i class="bi bi-geo-alt"></i> <?= e($event['location_name']) ?><?php endif; ?>
    </p>
  </div>
  <?php if (current_role() === 'municipality_admin'): ?>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?= e(url('/events/' . $event['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i><?= e(t('events/show.001', 'Επεξεργασία')) ?></a>
      <a class="btn btn-outline-info" href="<?= e(url('/events/' . $event['id'] . '/applications')) ?>"><i class="bi bi-inbox me-1"></i><?= e(t('events/show.002', 'Δηλώσεις')) ?></a>

      <?php if (in_array($event['status'], ['draft','review'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/publish')) ?>"
              onsubmit="return confirm('Η <?= e($eventSingularLc) ?> θα δημοσιευθεί και όλες οι ενεργές ομάδες θα ειδοποιηθούν. Συνέχεια;')">
          <?= csrf_field() ?>
          <button class="btn btn-primary"><i class="bi bi-megaphone me-1"></i><?= e(t('events/show.003', 'Δημοσίευση')) ?></button>
        </form>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['open','review','confirmed'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/activate')) ?>"
              onsubmit="return confirm('Η <?= e($eventSingularLc) ?> θα γίνει ενεργή (ημέρα διεξαγωγής). Συνέχεια;')">
          <?= csrf_field() ?>
          <button class="btn btn-warning"><i class="bi bi-broadcast me-1"></i><?= e(t('events/show.004', 'Έναρξη')) ?> <?= e($eventSingularLc) ?></button>
        </form>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['confirmed','active'], true)): ?>
        <a class="btn btn-outline-warning" href="<?= e(url('/operations/events/' . $event['id'])) ?>"><i class="bi bi-geo-alt me-1"></i><?= e(t('events/show.005', 'Επιχειρησιακή Σελίδα')) ?></a>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['open','review','confirmed','active'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/remind')) ?>"
              onsubmit="return confirm('Να σταλεί υπενθύμιση σε όλες τις εγκεκριμένες ομάδες;')">
          <?= csrf_field() ?>
          <button class="btn btn-outline-info"><i class="bi bi-bell me-1"></i><?= e(t('events/show.006', 'Υπενθύμιση ομάδων')) ?></button>
        </form>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['open','review','confirmed','active'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/close')) ?>"
              onsubmit="return confirm('Η <?= e($eventSingularLc) ?> θα κλειστεί και οι ομάδες θα ειδοποιηθούν να υποβάλουν αναφορά. Συνέχεια;')">
          <?= csrf_field() ?>
          <button class="btn btn-danger"><i class="bi bi-lock me-1"></i><?= e(t('events/show.007', 'Κλείσιμο')) ?> <?= e($eventSingularLc) ?></button>
        </form>
      <?php endif; ?>

      <?php if ($event['status'] === 'closed'): ?>
        <a class="btn btn-warning" href="<?= e(url('/events/' . $event['id'] . '/reconcile')) ?>">
          <i class="bi bi-clipboard-check me-1"></i><?= e(t('events/show.008', 'Απολογισμός-Στοιχεία')) ?>
        </a>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/archive')) ?>"
              onsubmit="return confirm('Η <?= e($eventSingularLc) ?> θα ολοκληρωθεί οριστικά και θα μετακινηθεί στις Ολοκληρωμένες. Συνέχεια;')">
          <?= csrf_field() ?>
          <button class="btn btn-success"><i class="bi bi-archive me-1"></i><?= e(t('events/show.009', 'Ολοκλήρωση')) ?></button>
        </form>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['closed','completed'], true)): ?>
        <a class="btn btn-info" href="<?= e(url('/events/' . $event['id'] . '/story')) ?>" target="_blank"><i class="bi bi-easel2 me-1"></i><?= e(t('events/show.010', 'Παρουσίαση')) ?> <?= e($eventSingular) ?></a>
      <?php endif; ?>

      <?php if ($event['status'] === 'completed'): ?>
        <a class="btn btn-outline-primary" href="<?= e(url('/events/' . $event['id'] . '/debriefs')) ?>">
          <i class="bi bi-clipboard2-data me-1"></i><?= e(t('events/show.011', 'Απολογισμός')) ?> <?= e($eventSingular) ?>
        </a>
      <?php endif; ?>

      <?php if (!in_array($event['status'], ['closed','completed','cancelled'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/cancel')) ?>"
              onsubmit="return confirm('Η <?= e($eventSingularLc) ?> θα ακυρωθεί. Είστε σίγουροι;')">
          <?= csrf_field() ?>
          <button class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i><?= e(t('events/show.012', 'Ακύρωση')) ?></button>
        </form>
      <?php endif; ?>

      <!-- Clone button — always available to municipality_admin -->
      <form method="post" action="<?= e(url('/events/' . $event['id'] . '/clone')) ?>"
            onsubmit="return confirm('Θα δημιουργηθεί αντίγραφο αυτής της <?= e($eventSingularLc) ?> ως πρόχειρο. Συνέχεια;')">
        <?= csrf_field() ?>
        <button class="btn btn-outline-secondary"><i class="bi bi-copy me-1"></i><?= e(t('events/show.013', 'Κλωνοποίηση')) ?></button>
      </form>

      <!-- Save as reusable template -->
      <form method="post" action="<?= e(url('/events/' . $event['id'] . '/save-template')) ?>" class="input-group" style="max-width:300px">
        <?= csrf_field() ?>
        <input type="text" name="template_name" class="form-control form-control-sm" placeholder="<?= e(t('events/show.051', 'Όνομα προτύπου (προαιρετικό)')) ?>">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-bookmark-plus me-1"></i><?= e(t('events/show.014', 'Ως πρότυπο')) ?></button>
      </form>

      <?php if (!empty($event['public_token']) && $event['status'] !== 'draft'): ?>
        <!-- Share public link -->
        <?php $publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'] . url('/public/events/' . $event['public_token']); ?>
        <div class="input-group" style="max-width:340px">
          <input type="text" class="form-control form-control-sm" id="public-link-input"
                 value="<?= e($publicUrl) ?>" readonly>
          <button class="btn btn-sm btn-outline-success" type="button" onclick="copyPublicLink()"
                  title="<?= e(t('events/show.052', 'Αντιγραφή δημόσιου συνδέσμου')) ?>" id="copy-btn">
            <i class="bi bi-share"></i>
          </button>
        </div>
        <script>
        function copyPublicLink() {
          var inp = document.getElementById('public-link-input');
          inp.select(); inp.setSelectionRange(0, 999);
          navigator.clipboard.writeText(inp.value).then(function() {
            var btn = document.getElementById('copy-btn');
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            btn.classList.replace('btn-outline-success','btn-success');
            setTimeout(function(){ btn.innerHTML='<i class="bi bi-share"></i>'; btn.classList.replace('btn-success','btn-outline-success'); }, 2000);
          });
        }
        </script>
      <?php endif; ?>
    </div>
  <?php elseif (in_array($event['status'], ['confirmed','active'], true)): ?>
    <a class="btn btn-warning" href="<?= e(url('/operations/events/' . $event['id'])) ?>"><i class="bi bi-geo-alt me-1"></i><?= e(t('events/show.005', 'Επιχειρησιακή Σελίδα')) ?></a>
  <?php endif; ?>
</div>

<div class="row g-4 mt-1">
  <div class="col-lg-7">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><?= e(t('events/show.015', 'Στοιχεία')) ?> <?= e($eventSingularLc) ?></div>
      <div class="card-body">
        <?php if ($event['description']): ?><p><?= nl2br(e($event['description'])) ?></p><?php endif; ?>
        <dl class="row mb-0">
          <dt class="col-sm-4"><?= e(t('events/show.016', 'Διεύθυνση')) ?></dt><dd class="col-sm-8"><?= e($event['address'] ?: '—') ?></dd>
          <dt class="col-sm-4"><?= e(t('events/show.017', 'Ζητούμενα άτομα')) ?></dt><dd class="col-sm-8"><?= (int) $event['requested_people'] ?></dd>
          <dt class="col-sm-4"><?= e(t('events/show.018', 'Όχημα')) ?></dt><dd class="col-sm-8"><?= $event['requested_vehicle'] ? 'Απαιτείται' : 'Δεν απαιτείται' ?></dd>
          <dt class="col-sm-4"><?= e(t('events/show.019', 'Υγειονομικός εξοπλισμός')) ?></dt><dd class="col-sm-8"><?= $event['requested_medical_equipment'] ? 'Απαιτείται' : 'Δεν απαιτείται' ?></dd>
          <?php if ($requestedItems): ?>
            <dt class="col-sm-4"><?= e(t('events/show.020', 'Ζητούμενα αντικείμενα')) ?></dt>
            <dd class="col-sm-8">
              <div class="d-flex flex-wrap gap-1">
                <?php foreach ($requestedItems as $item): ?>
                  <span class="badge text-bg-light border"><?= e($item) ?></span>
                <?php endforeach; ?>
              </div>
            </dd>
          <?php endif; ?>
          <?php if ($event['instructions']): ?>
            <dt class="col-sm-4"><?= e(t('events/show.021', 'Οδηγίες')) ?></dt><dd class="col-sm-8"><?= nl2br(e($event['instructions'])) ?></dd>
          <?php endif; ?>
          <?php if ($event['published_at']): ?>
            <dt class="col-sm-4"><?= e(t('events/show.022', 'Δημοσιεύθηκε')) ?></dt><dd class="col-sm-8"><?= e(gr_datetime($event['published_at'])) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-inbox me-1"></i> <?= e(t('events/show.023', 'Δηλώσεις συμμετοχής')) ?></span>
        <?php if (current_role() === 'municipality_admin'): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/events/' . $event['id'] . '/applications')) ?>"><?= e(t('events/show.024', 'Εγκρίσεις')) ?></a>
        <?php endif; ?>
      </div>
      <?php if (!$applications): ?>
        <div class="card-body text-muted"><?= e(t('events/show.055', 'Δεν υπάρχουν ακόμη δηλώσεις συμμετοχής για αυτή τη')) ?> <?= e($eventSingularLc) ?>.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th><?= e(t('events/show.026', 'Ομάδα')) ?></th><th><?= e(t('events/show.027', 'Προσφορά')) ?></th><th><?= e(t('events/show.028', 'Εγκεκριμένα')) ?></th><th><?= e(t('events/show.029', 'Κατάσταση')) ?></th></tr></thead>
            <tbody>
              <?php foreach ($applications as $a): ?>
                <tr>
                  <td><?= e($a['team_name']) ?></td>
                  <td><?= (int) $a['offered_people'] ?> <?= e(t('events/show.030', 'άτομα')) ?><?= $a['offered_vehicle'] ? ' · όχημα' : '' ?><?= $a['offered_medical_equipment'] ? ' · υγειον. εξοπλ.' : '' ?></td>
                  <td><?= $a['approved_people'] !== null ? (int) $a['approved_people'] : '—' ?></td>
                  <td><?= status_badge($a['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php /* ── Βάρδιες (Shifts) section — municipality admin only ─────────── */ ?>
    <?php if (current_role() === 'municipality_admin'): ?>
    <div id="tab-shifts" class="card shadow-sm mt-4">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1"></i> <?= e(t('events/show.031', 'Βάρδιες')) ?>
          <?php if ($shifts): ?>
            <span class="badge bg-primary ms-1"><?= count($shifts) ?></span>
          <?php endif; ?>
        </span>
        <?php if (!in_array($event['status'], ['completed','cancelled'], true)): ?>
        <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#addShiftForm">
          <i class="bi bi-plus-circle me-1"></i><?= e(t('events/show.032', 'Νέα Βάρδια')) ?>
        </button>
        <?php endif; ?>
      </div>

      <?php if (!in_array($event['status'], ['completed','cancelled'], true)): ?>
      <div class="collapse" id="addShiftForm">
        <div class="card-body border-bottom bg-light">
          <form method="post" action="<?= e(url('/events/' . $event['id'] . '/shifts/store')) ?>" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-4">
              <label class="form-label fw-semibold"><?= e(t('events/show.033', 'Όνομα βάρδιας *')) ?></label>
              <input type="text" name="name" class="form-control form-control-sm" placeholder="<?= e(t('events/show.053', 'π.χ. Πρωινή βάρδια')) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold"><?= e(t('events/show.034', 'Έναρξη *')) ?></label>
              <input type="datetime-local" name="start_datetime" class="form-control form-control-sm" required
                     value="<?= e(date('Y-m-d\TH:i', strtotime($event['start_datetime']))) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold"><?= e(t('events/show.035', 'Λήξη *')) ?></label>
              <input type="datetime-local" name="end_datetime" class="form-control form-control-sm" required
                     value="<?= e(date('Y-m-d\TH:i', strtotime($event['end_datetime']))) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label fw-semibold"><?= e(t('events/show.036', 'Ζητούμενα')) ?></label>
              <input type="number" name="required_people" class="form-control form-control-sm" min="0" value="0">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold"><?= e(t('events/show.037', 'Σημειώσεις')) ?></label>
              <input type="text" name="notes" class="form-control form-control-sm" placeholder="<?= e(t('events/show.054', 'Προαιρετικές οδηγίες για τη βάρδια')) ?>">
            </div>
            <div class="col-12 text-end">
              <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-plus-circle me-1"></i><?= e(t('events/show.038', 'Προσθήκη βάρδιας')) ?></button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!$shifts): ?>
        <div class="card-body text-muted"><i class="bi bi-info-circle me-1"></i> <?= e(t('events/show.056', 'Δεν έχουν οριστεί βάρδιες για αυτή τη')) ?> <?= e($eventSingularLc) ?>.</div>
      <?php else: ?>
        <?php
          // Index shift applications by shift_id for quick lookup
          $shiftAppsByShift = [];
          foreach ($shiftApps as $sa) {
              $shiftAppsByShift[$sa['shift_id']][] = $sa;
          }
        ?>
        <div class="list-group list-group-flush">
          <?php foreach ($shifts as $sh): ?>
          <div class="list-group-item px-3 py-3">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
              <div>
                <div class="fw-semibold"><i class="bi bi-clock me-1 text-primary"></i><?= e($sh['name']) ?></div>
                <div class="text-muted small">
                  <?= e(gr_datetime($sh['start_datetime'])) ?> – <?= e(gr_time($sh['end_datetime'])) ?>
                  <?php if ($sh['required_people']): ?>
                    · <span class="text-secondary"><?= (int) $sh['required_people'] ?> <?= e(t('events/show.040', 'ζητούμενα')) ?></span>
                  <?php endif; ?>
                  <?php if ($sh['notes']): ?>
                    · <em><?= e($sh['notes']) ?></em>
                  <?php endif; ?>
                </div>
              </div>
              <?php if (!in_array($event['status'], ['completed','cancelled'], true)): ?>
              <div class="d-flex gap-1 flex-shrink-0">
                <button class="btn btn-sm py-0 px-1btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#editShift<?= (int)$sh['id'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="post" action="<?= e(url('/events/' . $event['id'] . '/shifts/' . $sh['id'] . '/delete')) ?>"
                      onsubmit="return confirm('Διαγραφή βάρδιας «<?= addslashes(e($sh['name'])) ?>»;')">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm py-0 px-1btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                </form>
              </div>
              <?php endif; ?>
            </div>

            <?php /* Edit collapse */ ?>
            <div class="collapse mt-2" id="editShift<?= (int)$sh['id'] ?>">
              <form method="post" action="<?= e(url('/events/' . $event['id'] . '/shifts/' . $sh['id'] . '/update')) ?>" class="row g-2 bg-light rounded p-2">
                <?= csrf_field() ?>
                <div class="col-md-4">
                  <input type="text" name="name" class="form-control form-control-sm" value="<?= e($sh['name']) ?>" required>
                </div>
                <div class="col-md-3">
                  <input type="datetime-local" name="start_datetime" class="form-control form-control-sm" required
                         value="<?= e(date('Y-m-d\TH:i', strtotime($sh['start_datetime']))) ?>">
                </div>
                <div class="col-md-3">
                  <input type="datetime-local" name="end_datetime" class="form-control form-control-sm" required
                         value="<?= e(date('Y-m-d\TH:i', strtotime($sh['end_datetime']))) ?>">
                </div>
                <div class="col-md-2">
                  <input type="number" name="required_people" class="form-control form-control-sm" min="0" value="<?= (int)$sh['required_people'] ?>">
                </div>
                <div class="col-10">
                  <input type="text" name="notes" class="form-control form-control-sm" placeholder="<?= e(t('events/show.037', 'Σημειώσεις')) ?>" value="<?= e($sh['notes']) ?>">
                </div>
                <div class="col-2 text-end">
                  <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-save"></i></button>
                </div>
              </form>
            </div>

            <?php /* Shift applications for this shift */ ?>
            <?php $appsForShift = $shiftAppsByShift[$sh['id']] ?? []; ?>
            <?php if ($appsForShift): ?>
            <div class="mt-2">
              <table class="table table-sm table-bordered mb-0 small">
                <thead class="table-light"><tr><th><?= e(t('events/show.026', 'Ομάδα')) ?></th><th><?= e(t('events/show.027', 'Προσφορά')) ?></th><th><?= e(t('events/show.028', 'Εγκεκριμένα')) ?></th><th><?= e(t('events/show.029', 'Κατάσταση')) ?></th><th></th></tr></thead>
                <tbody>
                  <?php foreach ($appsForShift as $sa): ?>
                  <tr>
                    <td><?= e($sa['team_name']) ?></td>
                    <td><?= (int)$sa['offered_people'] ?></td>
                    <td><?= $sa['approved_people'] ? (int)$sa['approved_people'] : '—' ?></td>
                    <td><?= status_badge($sa['status']) ?></td>
                    <td class="text-end" style="min-width:180px">
                      <?php if ($sa['status'] === 'pending'): ?>
                      <form method="post" action="<?= e(url('/shift-applications/' . $sa['id'] . '/approve')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="approved_people" value="<?= (int)$sa['offered_people'] ?>">
                        <button class="btn btn-sm py-0 px-1btn-success"><?= e(t('events/show.041', '✓ Έγκριση')) ?></button>
                      </form>
                      <form method="post" action="<?= e(url('/shift-applications/' . $sa['id'] . '/reject')) ?>" class="d-inline ms-1">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm py-0 px-1btn-outline-danger"><?= e(t('events/show.042', '✗ Απόρριψη')) ?></button>
                      </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
              <div class="text-muted small mt-1"><i class="bi bi-people me-1"></i><?= e(t('events/show.043', 'Δεν υπάρχουν δηλώσεις για αυτή τη βάρδια.')) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <?php if ($event['latitude'] !== null && $event['longitude'] !== null): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-map me-1"></i> <?= e(t('events/show.044', 'Τοποθεσία')) ?></div>
        <div class="card-body p-2">
          <div id="eventMap" data-lat="<?= e($event['latitude']) ?>" data-lng="<?= e($event['longitude']) ?>" data-title="<?= e($event['title']) ?>"></div>
        </div>
      </div>
    <?php endif; ?>

    <?php if (current_role() === 'municipality_admin' && !empty($teamMatches)): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-stars me-1"></i> <?= e(t('events/show.045', 'Προτεινόμενες ομάδες')) ?></span>
          <span class="badge text-bg-light border"><?= count($teamMatches) ?></span>
        </div>
        <div class="list-group list-group-flush">
          <?php foreach (array_slice($teamMatches, 0, 5) as $tm): ?>
            <div class="list-group-item">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold"><?= e($tm['name']) ?></div>
                  <div class="small text-muted">
                    <?= $tm['default_people_capacity'] ? (int) $tm['default_people_capacity'] . ' άτομα' : 'Χωρίς δηλωμένη δύναμη' ?>
                    <?= $tm['has_vehicle'] ? ' · όχημα' : '' ?>
                    <?= $tm['has_medical_equipment'] ? ' · υγειον.' : '' ?>
                  </div>
                </div>
                <span class="badge text-bg-<?= e($tm['match']['level_class']) ?>"><?= (int) $tm['match']['score'] ?>%</span>
              </div>
              <?php if (!empty($tm['match']['missing'])): ?>
                <div class="small text-muted mt-2">
                  <?= e(t('events/show.046', 'Λείπουν:')) ?>
                  <?php foreach (array_slice($tm['match']['missing'], 0, 4) as $missing): ?>
                    <span class="badge text-bg-warning text-dark border"><?= e($missing) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($tm['match']['missing']) > 4): ?><span class="badge text-bg-light border">+<?= count($tm['match']['missing']) - 4 ?></span><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-text me-1"></i> <?= e(t('events/show.047', 'Αναφορές')) ?> <?= e($eventSingularLc) ?></span>
      </div>
      <?php if (empty($reports)): ?>
        <div class="card-body text-muted small"><?= e(t('events/show.048', 'Δεν υπάρχουν αναφορές ακόμη.')) ?></div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($reports as $r): ?>
          <div class="list-group-item">
            <div class="fw-semibold small">
              <i class="bi bi-people me-1 text-primary"></i><?= e($r['team_name'] ?? $orgShort) ?>
              <span class="text-muted fw-normal">· <?= e(gr_datetime($r['created_at'])) ?></span>
            </div>
            <div class="small text-muted mt-1">
              <?= e(t('events/show.049', 'Περιστατικά:')) ?> <strong><?= (int) $r['incidents_count'] ?></strong> <?= e(t('events/show.050', '· Διακομιδές:')) ?> <strong><?= (int) $r['transfers_count'] ?></strong> ·
              Α&#39; βοήθειες: <strong><?= (int) $r['first_aid_count'] ?></strong>
            </div>
            <?php if (!empty($r['summary'])): ?>
              <div class="small mt-1"><?= nl2br(e($r['summary'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['notes'])): ?>
              <div class="small text-muted mt-1"><em><?= nl2br(e($r['notes'])) ?></em></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
