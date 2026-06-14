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
      <a class="btn btn-outline-secondary" href="<?= e(url('/events/' . $event['id'] . '/edit')) ?>"><i class="bi bi-pencil me-1"></i>Επεξεργασία</a>
      <a class="btn btn-outline-info" href="<?= e(url('/events/' . $event['id'] . '/applications')) ?>"><i class="bi bi-inbox me-1"></i>Δηλώσεις</a>

      <?php if (in_array($event['status'], ['draft','review'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/publish')) ?>"
              onsubmit="return confirm('Η δράση θα δημοσιευθεί και όλες οι ενεργές ομάδες θα ειδοποιηθούν. Συνέχεια;')">
          <?= csrf_field() ?>
          <button class="btn btn-primary"><i class="bi bi-megaphone me-1"></i>Δημοσίευση</button>
        </form>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['open','review','confirmed'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/activate')) ?>"
              onsubmit="return confirm('Η δράση θα γίνει ενεργή (ημέρα διεξαγωγής). Συνέχεια;')">
          <?= csrf_field() ?>
          <button class="btn btn-warning"><i class="bi bi-broadcast me-1"></i>Έναρξη δράσης</button>
        </form>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['confirmed','active'], true)): ?>
        <a class="btn btn-outline-warning" href="<?= e(url('/operations/events/' . $event['id'])) ?>"><i class="bi bi-geo-alt me-1"></i>Επιχειρησιακή Σελίδα</a>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['open','review','confirmed','active'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/remind')) ?>"
              onsubmit="return confirm('Να σταλεί υπενθύμιση σε όλες τις εγκεκριμένες ομάδες;')">
          <?= csrf_field() ?>
          <button class="btn btn-outline-info"><i class="bi bi-bell me-1"></i>Υπενθύμιση ομάδων</button>
        </form>
      <?php endif; ?>

      <?php if (in_array($event['status'], ['open','review','confirmed','active'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/complete')) ?>"
              onsubmit="return confirm('Η δράση θα κλειστεί και οι ομάδες θα ειδοποιηθούν να υποβάλουν αναφορά. Συνέχεια;')">
          <?= csrf_field() ?>
          <button class="btn btn-secondary"><i class="bi bi-lock me-1"></i>Κλείσιμο δράσης</button>
        </form>
      <?php endif; ?>

      <?php if ($event['status'] === 'closed'): ?>
        <a class="btn btn-info" href="<?= e(url('/events/' . $event['id'] . '/reconcile')) ?>">
          <i class="bi bi-clipboard-check me-1"></i>Αρχειοθέτηση
        </a>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/archive')) ?>"
              onsubmit="return confirm('Η δράση θα αρχειοθετηθεί οριστικά. Συνέχεια;')">
          <?= csrf_field() ?>
          <button class="btn btn-success"><i class="bi bi-archive me-1"></i>Οριστική Αρχειοθέτηση</button>
        </form>
      <?php endif; ?>

      <?php if ($event['status'] === 'completed'): ?>
        <a class="btn btn-outline-primary" href="<?= e(url('/events/' . $event['id'] . '/debriefs')) ?>">
          <i class="bi bi-clipboard2-data me-1"></i>Post-Event Debriefs
        </a>
      <?php endif; ?>

      <?php if (!in_array($event['status'], ['closed','completed','cancelled'], true)): ?>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/cancel')) ?>"
              onsubmit="return confirm('Η δράση θα ακυρωθεί. Είστε σίγουροι;')">
          <?= csrf_field() ?>
          <button class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Ακύρωση</button>
        </form>
      <?php endif; ?>

      <!-- Clone button — always available to municipality_admin -->
      <form method="post" action="<?= e(url('/events/' . $event['id'] . '/clone')) ?>"
            onsubmit="return confirm('Θα δημιουργηθεί αντίγραφο αυτής της δράσης ως πρόχειρο. Συνέχεια;')">
        <?= csrf_field() ?>
        <button class="btn btn-outline-secondary"><i class="bi bi-copy me-1"></i>Κλωνοποίηση</button>
      </form>

      <?php if (!empty($event['public_token']) && $event['status'] !== 'draft'): ?>
        <!-- Share public link -->
        <?php $publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'] . url('/public/events/' . $event['public_token']); ?>
        <div class="input-group" style="max-width:340px">
          <input type="text" class="form-control form-control-sm" id="public-link-input"
                 value="<?= e($publicUrl) ?>" readonly>
          <button class="btn btn-sm btn-outline-success" type="button" onclick="copyPublicLink()"
                  title="Αντιγραφή δημόσιου συνδέσμου" id="copy-btn">
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
    <a class="btn btn-warning" href="<?= e(url('/operations/events/' . $event['id'])) ?>"><i class="bi bi-geo-alt me-1"></i>Επιχειρησιακή Σελίδα</a>
  <?php endif; ?>
</div>

<div class="row g-4 mt-1">
  <div class="col-lg-7">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">Στοιχεία δράσης</div>
      <div class="card-body">
        <?php if ($event['description']): ?><p><?= nl2br(e($event['description'])) ?></p><?php endif; ?>
        <dl class="row mb-0">
          <dt class="col-sm-4">Διεύθυνση</dt><dd class="col-sm-8"><?= e($event['address'] ?: '—') ?></dd>
          <dt class="col-sm-4">Ζητούμενα άτομα</dt><dd class="col-sm-8"><?= (int) $event['requested_people'] ?></dd>
          <dt class="col-sm-4">Όχημα</dt><dd class="col-sm-8"><?= $event['requested_vehicle'] ? 'Απαιτείται' : 'Δεν απαιτείται' ?></dd>
          <dt class="col-sm-4">Υγειονομικός εξοπλισμός</dt><dd class="col-sm-8"><?= $event['requested_medical_equipment'] ? 'Απαιτείται' : 'Δεν απαιτείται' ?></dd>
          <?php if ($event['instructions']): ?>
            <dt class="col-sm-4">Οδηγίες</dt><dd class="col-sm-8"><?= nl2br(e($event['instructions'])) ?></dd>
          <?php endif; ?>
          <?php if ($event['published_at']): ?>
            <dt class="col-sm-4">Δημοσιεύθηκε</dt><dd class="col-sm-8"><?= e(gr_datetime($event['published_at'])) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-inbox me-1"></i> Δηλώσεις συμμετοχής</span>
        <?php if (current_role() === 'municipality_admin'): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/events/' . $event['id'] . '/applications')) ?>">Εγκρίσεις</a>
        <?php endif; ?>
      </div>
      <?php if (!$applications): ?>
        <div class="card-body text-muted">Δεν υπάρχουν ακόμη δηλώσεις συμμετοχής για αυτή τη δράση.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th>Ομάδα</th><th>Προσφορά</th><th>Εγκεκριμένα</th><th>Κατάσταση</th></tr></thead>
            <tbody>
              <?php foreach ($applications as $a): ?>
                <tr>
                  <td><?= e($a['team_name']) ?></td>
                  <td><?= (int) $a['offered_people'] ?> άτομα<?= $a['offered_vehicle'] ? ' · όχημα' : '' ?><?= $a['offered_medical_equipment'] ? ' · υγειον. εξοπλ.' : '' ?></td>
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
        <span><i class="bi bi-clock-history me-1"></i> Βάρδιες
          <?php if ($shifts): ?>
            <span cla