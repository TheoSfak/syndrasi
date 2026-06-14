<h1 class="h3 mb-1">Δηλώσεις Συμμετοχής</h1>
<p class="text-muted">
  Δράση: <a href="<?= e(url('/events/' . $event['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($event['title']) ?></a>
  <?= status_badge($event['status']) ?> · <?= e(gr_datetime($event['start_datetime'])) ?>
  · Ζητούμενα άτομα: <strong><?= (int) $event['requested_people'] ?></strong>
</p>

<?php if (!$applications): ?>
  <div class="card shadow-sm"><div class="card-body text-muted">
    Δεν υπάρχουν ακόμη δηλώσεις συμμετοχής για αυτή τη δράση.
  </div></div>
<?php endif; ?>

<?php foreach ($applications as $a): ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
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
          <?php if ($a['comment']): ?>
            <div class="small"><i class="bi bi-chat-left-text me-1"></i><?= e($a['comment']) ?></div>
          <?php endif; ?>
          <?php if (!empty($a['history'])): ?>
            <div class="small text-muted mt-2">
              <i class="bi bi-clock-history me-1"></i>Ιστορικό ομάδας:
              <?= (int) $a['history']['events_count'] ?> ολοκληρωμένες δράσεις,
              <?= e(gr_number((float) $a['history']['total_hours'], 1)) ?> ώρες εθελοντισμού
              · <a href="<?= e(url('/statistics/teams/' . $a['team_id'])) ?>">πλήρη στατιστικά</a>
            </div>
          <?php endif; ?>
          <?php if ($a['admin_comment']): ?>
            <div class="small mt-2"><strong>Σχόλιο δήμου:</strong> <?= e($a['admin_comment']) ?></div>
          <?php endif; ?>
        </div>

        <?php if ($a['status'] === 'pending'): ?>
          <div style="min-width:280px">
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

<a class="btn btn-outline-secondary" href="<?= e(url('/exports/events/' . $event['id'] . '/applications')) ?>">
  <i class="bi bi-download me-1"></i>Εξαγωγή δηλώσεων CSV
</a>
