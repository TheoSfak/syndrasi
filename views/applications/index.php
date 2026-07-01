<?php
$terms = authority_context((int) ($event['municipality_id'] ?? current_municipality_id()));
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$orgLabel = $terms['short_name'] ?? 'Φορέας';
?>
<h1 class="h3 mb-1">Δηλώσεις Συμμετοχής</h1>
<p class="text-muted">
  <?= e($eventSingular) ?>: <a href="<?= e(url('/events/' . $event['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($event['title']) ?></a>
  <?= status_badge($event['status']) ?> · <?= e(gr_datetime($event['start_datetime'])) ?>
  · Ζητούμενα άτομα: <strong><?= (int) $event['requested_people'] ?></strong>
</p>

<?php if (!$applications): ?>
  <div class="card shadow-sm"><div class="card-body text-muted">
    Δεν υπάρχουν ακόμη δηλώσεις συμμετοχής για αυτή τη <?= e($eventSingularLc) ?>.
  </div></div>
<?php else: ?>

<?php
$pendingApps = array_filter($applications, fn($a) => $a['status'] === 'pending');
$hasPending  = count($pendingApps) > 0;
?>

<?php if ($hasPending): ?>
<!-- ── Bulk action bar ─────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3 border-primary" id="bulk-bar" style="display:none!important">
  <div class="card-body py-2">
    <form method="post" action="<?= e(url('/events/' . $event['id'] . '/applications/bulk')) ?>"
          id="bulk-form" onsubmit="return confirmBulk()">
      <?= csrf_field() ?>
      <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="fw-semibold text-primary" id="selected-count">0 επιλεγμένες</span>
        <div class="d-flex align-items-center gap-2">
          <label class="small fw-semibold mb-0">Εγκεκριμένα άτομα (προεπιλογή = προσφερόμενα):</label>
        </div>
        <div class="ms-auto d-flex gap-2">
          <button type="submit" name="bulk_action" value="approve" class="btn btn-success btn-sm">
            <i class="bi bi-check-all me-1"></i>Μαζική Έγκριση
          </button>
          <button type="submit" name="bulk_action" value="reject" class="btn btn-outline-danger btn-sm"
                  onclick="document.getElementById('reject-confirm').value='1'">
            <i class="bi bi-x-circle me-1"></i>Μαζική Απόρριψη
          </button>
        </div>
        <input type="hidden" name="reject-confirm" id="reject-confirm" value="0">
      </div>
      <!-- hidden inputs for selected ids + approved_people injected by JS -->
      <div id="bulk-hidden"></div>
    </form>
  </div>
</div>

<div class="mb-2 d-flex align-items-center gap-3">
  <div class="form-check">
    <input class="form-check-input" type="checkbox" id="select-all">
    <label class="form-check-label small fw-semibold" for="select-all">Επιλογή όλων εκκρεμών</label>
  </div>
</div>
<?php endif; ?>

<?php foreach ($applications as $a): ?>
  <div class="card shadow-sm mb-3 app-card <?= $a['status'] === 'pending' ? 'app-pending' : '' ?>"
       data-id="<?= (int) $a['id'] ?>" data-offered="<?= (int) $a['offered_people'] ?>">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div class="d-flex align-items-start gap-3">
          <?php if ($a['status'] === 'pending'): ?>
            <div class="pt-1">
              <input class="form-check-input app-checkbox" type="checkbox"
                     id="chk-<?= (int) $a['id'] ?>" value="<?= (int) $a['id'] ?>"
                     data-offered="<?= (int) $a['offered_people'] ?>">
            </div>
          <?php endif; ?>
          <div>
            <h2 class="h5 mb-1"><?= e($a['team_name']) ?> <?= status_badge($a['status']) ?></h2>
            <div class="text-muted small mb-2">
              <?= e($a['team_type'] ?: '') ?><?= $a['team_phone'] ? ' · ' . e($a['team_phone']) : '' ?>
              · Υποβλήθηκε: <?= e(gr_datetime($a['submitted_at'])) ?>
            </div>
            <div class="mb-1">
              <span class="badge text-bg-light text-dark border"><i class="bi bi-people me-1"></i><?= (int) $a['offered_people'] ?> άτομα</span>
              <?php if ($a['offered_vehicle']): ?><span class="badge text-bg-light text-dark border"><i class="bi bi-truck me-1"></i>Όχημα</span><?php endif; ?>
              <?php if ($a['offered_medical_equipment']): ?><span class="badge text-bg-light text-dark border"><i class="bi bi-heart-pulse me-1"></i>Υγειονομικός εξοπλισμός</span><?php endif; ?>
            </div>
            <?php if (!empty($a['match'])): ?>
              <div class="border rounded p-2 bg-light mb-2" style="max-width:720px">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <span class="badge text-bg-<?= e($a['match']['level_class']) ?>">
                    <?= (int) $a['match']['score'] ?>% match
                  </span>
                  <span class="small fw-semibold"><?= e($a['match']['level']) ?></span>
                </div>
                <?php if (!empty($a['match']['missing'])): ?>
                  <div class="small text-muted">
                    Λείπουν:
                    <?php foreach (array_slice($a['match']['missing'], 0, 6) as $missing): ?>
                      <span class="badge text-bg-warning text-dark border"><?= e($missing) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($a['match']['missing']) > 6): ?>
                      <span class="badge text-bg-light border">+<?= count($a['match']['missing']) - 6 ?></span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="small text-success"><i class="bi bi-check-circle me-1"></i>Καλύπτει όλα τα βασικά ζητούμενα.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($a['comment']): ?>
              <div class="small"><i class="bi bi-chat-left-text me-1"></i><?= e($a['comment']) ?></div>
            <?php endif; ?>
            <?php if (!empty($a['history'])): ?>
              <div class="small text-muted mt-2">
                <i class="bi bi-clock-history me-1"></i>Ιστορικό ομάδας:
                <?= (int) $a['history']['events_count'] ?> ολοκληρωμένες <?= e($eventPluralLc) ?>,
                <?= e(gr_number((float) $a['history']['total_hours'], 1)) ?> ώρες εθελοντισμού
                · <a href="<?= e(url('/statistics/teams/' . $a['team_id'])) ?>">πλήρη στατιστικά</a>
              </div>
            <?php endif; ?>
            <?php if ($a['admin_comment']): ?>
              <div class="small mt-2"><strong>Σχόλιο <?= e($orgLabel) ?>:</strong> <?= e($a['admin_comment']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($a['status'] === 'pending'): ?>
          <div style="min-width:280px">
            <!-- Per-app approved people for bulk (hidden, shown when checkbox ticked) -->
            <div class="bulk-people-input mb-2" id="bulk-people-<?= (int)$a['id'] ?>" style="display:none">
              <label class="form-label small mb-1 text-primary">Άτομα (για μαζική έγκριση)</label>
              <input type="number" min="1" max="<?= (int)$a['offered_people'] ?>"
                     class="form-control form-control-sm bulk-people-field"
                     value="<?= (int)$a['offered_people'] ?>"
                     data-id="<?= (int)$a['id'] ?>">
            </div>
            <form method="post" action="<?= e(url('/applications/' . $a['id'] . '/approve')) ?>" class="border rounded p-3 mb-2 bg-light"
                  onsubmit="return confirm('Να εγκριθεί η συμμετοχή της ομάδας;')">
              <?= csrf_field() ?>
              <label class="form-label small mb-1">Εγκεκριμένα άτομα *</label>
              <input type="number" name="approved_people" class="form-control mb-2" min="1"
                     max="<?= (int) $a['offered_people'] ?>" value="<?= (int) $a['offered_people'] ?>" required>
              <label class="form-label small mb-1">Οδηγίες / σχόλιο (προαιρετικό)</label>
              <input type="text" name="admin_comment" class="form-control mb-2" placeholder="π.χ. σημείο συνάντησης">
              <button class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Έγκριση</button>
            </form>
            <form method="post" action="<?= e(url('/applications/' . $a['id'] . '/reject')) ?>"
                  onsubmit="return confirm('Να απορριφθεί η δήλωση της ομάδας;')">
              <?= csrf_field() ?>
              <div class="input-group">
                <input type="text" name="admin_comment" class="form-control" placeholder="Λόγος απόρριψης (προαιρετικό)">
                <button class="btn btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Απόρριψη</button>
              </div>
            </form>
          </div>
        <?php elseif ($a['status'] === 'approved'): ?>
          <div class="text-end">
            <div class="fs-4 fw-bold text-success"><?= (int) $a['approved_people'] ?></div>
            <div class="small text-muted">εγκεκριμένα άτομα</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php endif; ?>

<a class="btn btn-outline-secondary" href="<?= e(url('/exports/events/' . $event['id'] . '/applications')) ?>">
  <i class="bi bi-download me-1"></i>Εξαγωγή δηλώσεων CSV
</a>

<?php if ($hasPending ?? false): ?>
<script>
(function() {
  const bar        = document.getElementById('bulk-bar');
  const countEl    = document.getElementById('selected-count');
  const hiddenWrap = document.getElementById('bulk-hidden');
  const selectAll  = document.getElementById('select-all');
  const checkboxes = document.querySelectorAll('.app-checkbox');

  function refresh() {
    const checked = [...document.querySelectorAll('.app-checkbox:checked')];
    bar.style.removeProperty('display');
    bar.style.display = checked.length ? 'block' : 'none';
    countEl.textContent = checked.length + ' επιλεγμένες';

    // Show/hide per-app people inputs
    checkboxes.forEach(cb => {
      const inp = document.getElementById('bulk-people-' + cb.value);
      if (inp) inp.style.display = cb.checked ? 'block' : 'none';
    });

    // Rebuild hidden inputs
    hiddenWrap.innerHTML = '';
    checked.forEach(cb => {
      const idInput = document.createElement('input');
      idInput.type = 'hidden'; idInput.name = 'app_ids[]'; idInput.value = cb.value;
      hiddenWrap.appendChild(idInput);

      const pField = document.querySelector('.bulk-people-field[data-id="' + cb.value + '"]');
      const pInput = document.createElement('input');
      pInput.type = 'hidden';
      pInput.name = 'approved_people[' + cb.value + ']';
      pInput.value = pField ? pField.value : cb.dataset.offered;
      hiddenWrap.appendChild(pInput);
    });
  }

  checkboxes.forEach(cb => cb.addEventListener('change', () => {
    selectAll.indeterminate = true;
    refresh();
  }));

  selectAll.addEventListener('change', () => {
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    refresh();
  });

  window.confirmBulk = function() {
    const checked = document.querySelectorAll('.app-checkbox:checked').length;
    if (!checked) { alert('Δεν επιλέξατε καμία δήλωση.'); return false; }
    return confirm('Να εφαρμοστεί η ενέργεια σε ' + checked + ' δηλώσεις;');
  };
})();
</script>
<?php endif; ?>
