<div class="d-flex align-items-center mb-3 gap-2">
  <a href="<?= e(url('/events/' . $event['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h1 class="h3 mb-0">Αρχειοθέτηση</h1>
    <p class="text-muted small mb-0"><?= e($event['title']) ?> — <?= e(gr_datetime($event['start_datetime'])) ?></p>
  </div>
</div>

<div class="alert alert-info small">
  <i class="bi bi-info-circle me-1"></i>
  Καταχωρήστε τα πραγματικά δεδομένα κάθε ομάδας και επιλέξτε ποια μέλη παρευρέθηκαν.
  Μετά την αποθήκευση, πατήστε <strong>«Οριστική Αρχειοθέτηση»</strong> από τη σελίδα της δράσης.
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <form method="post" action="<?= e(url('/events/' . $event['id'] . '/reconcile')) ?>">
      <?= csrf_field() ?>

      <?php if ($applications): ?>
        <?php foreach ($applications as $app): ?>
          <?php
            // Assigned members + existing participation are pre-loaded by the
            // controller (batched) to avoid N+1 queries here.
            $appMembers = $membersByApp[$app['id']] ?? [];
            $existingVp = $existingByApp[$app['id']] ?? [];
            // Calculate hours from actual times if available
            $arriv  = $app['actual_arrival_time']  ?? $event['start_datetime'];
            $depart = $app['actual_departure_time'] ?? $event['end_datetime'];
            $calcHours = ($arriv && $depart)
              ? max(0, round((strtotime($depart) - strtotime($arriv)) / 3600, 2))
              : round((strtotime($event['end_datetime']) - strtotime($event['start_datetime'])) / 3600, 2);
          ?>
          <input type="hidden" name="app_id[]" value="<?= (int) $app['id'] ?>">
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span><i class="bi bi-people me-1"></i><?= e($app['team_name']) ?></span>
              <span class="small text-muted">Εγκρίθηκαν: <strong><?= (int) $app['approved_people'] ?></strong> άτομα</span>
            </div>
            <div class="card-body">

              <!-- Team actuals -->
              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label small">Άτομα που ήρθαν</label>
                  <input type="number" min="0"
                         name="actual_people[<?= (int) $app['id'] ?>]"
                         class="form-control"
                         value="<?= $app['actual_people'] !== null ? (int) $app['actual_people'] : (int) $app['approved_people'] ?>">
                  <div class="form-text">Εγκεκριμένα: <?= (int) $app['approved_people'] ?></div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small">Ώρα άφιξης</label>
                  <input type="datetime-local"
                         name="actual_arrival_time[<?= (int) $app['id'] ?>]"
                         class="form-control"
                         value="<?= $app['actual_arrival_time'] ? date('Y-m-d\TH:i', strtotime($app['actual_arrival_time'])) : date('Y-m-d\TH:i', strtotime($event['start_datetime'])) ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label small">Ώρα αναχώρησης</label>
                  <input type="datetime-local"
                         name="actual_departure_time[<?= (int) $app['id'] ?>]"
                         class="form-control"
                         value="<?= $app['actual_departure_time'] ? date('Y-m-d\TH:i', strtotime($app['actual_departure_time'])) : date('Y-m-d\TH:i', strtotime($event['end_datetime'])) ?>">
                </div>
              </div>

              <!-- Member attendance -->
              <?php if ($appMembers): ?>
                <div class="border rounded p-3 bg-light">
                  <div class="fw-semibold small mb-2"><i class="bi bi-person-check me-1"></i>Παρουσία μελών (<?= count($appMembers) ?> εκχωρημένα)</div>
                  <?php foreach ($appMembers as $member): ?>
                    <?php
                      $vp = $existingVp[$member['id']] ?? null;
                      $isPresent = $vp ? (int) $vp['was_present'] : 1; // default present
                    ?>
                    <div class="d-flex align-items-center gap-3 py-1 border-bottom">
                      <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox"
                               id="mem-<?= (int)$app['id'] ?>-<?= (int)$member['id'] ?>"
                               name="member_present[<?= (int)$app['id'] ?>][<?= (int)$member['id'] ?>]"
                               value="1" <?= $isPresent ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mem-<?= (int)$app['id'] ?>-<?= (int)$member['id'] ?>">
                          <strong><?= e($member['full_name']) ?></strong>
                          <?php if ($member['specialty']): ?>
                            <span class="text-muted small">(<?= e($member['specialty']) ?>)</span>
                          <?php endif; ?>
                          <?php if ((int)$member['id'] === (int)$app['mission_commander_id']): ?>
                            <span class="badge text-bg-warning ms-1 small">Υπεύθυνος</span>
                          <?php endif; ?>
                        </label>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <div class="small text-muted mt-2">
                    <i class="bi bi-clock me-1"></i>Υπολογισμένες ώρες/μέλος: <strong><?= $calcHours ?> ώρες</strong>
                    (από ώρες άφιξης–αναχώρησης)
                  </div>
                </div>
              <?php else: ?>
                <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>Δεν υπάρχουν εκχωρημένα μέλη για αυτή τη δήλωση.</div>
              <?php endif; ?>

            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="alert alert-warning">Δεν υπάρχουν εγκεκριμένες ομάδες για αυτή τη δράση.</div>
      <?php endif; ?>

      <!-- Municipality notes -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-journal-text me-1"></i> Σημειώσεις Δήμου</div>
        <div class="card-body">
          <textarea name="reconciliation_notes" class="form-control" rows="4"
                    placeholder="Γενικές παρατηρήσεις για τη δράση, ανακοίνωση αποτελεσμάτων κλπ."><?= e($event['reconciliation_notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Αποθήκευση στοιχείων
        </button>
        <a href="<?= e(url('/events/' . $event['id'])) ?>" class="btn btn-outline-secondary">Πίσω στη δράση</a>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Στοιχεία Δράσης</div>
      <div class="card-body small">
        <dl class="row mb-0">
          <dt class="col-6">Έναρξη</dt><dd class="col-6"><?= e(gr_datetime($event['start_datetime'])) ?></dd>
          <dt class="col-6">Λήξη</dt><dd class="col-6"><?= e(gr_datetime($event['end_datetime'])) ?></dd>
          <dt class="col-6">Τοποθεσία</dt><dd class="col-6"><?= e($event['location_name'] ?: '—') ?></dd>
          <dt class="col-6">Ζητούμενα</dt><dd class="col-6"><?= (int) $event['requested_people'] ?> άτομα</dd>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm mb-3 border-success">
      <div class="card-header bg-white fw-semibold text-success"><i class="bi bi-person-lines-fill me-1"></i> Εθελοντικές Ώρες</div>
      <div class="card-body small text-muted">
        <p>Η παρουσία κάθε μέλους καταγράφεται αυτόματα κατά την αποθήκευση.</p>
        <p>Οι ώρες υπολογίζονται από τις ώρες άφιξης–αναχώρησης που ορίσατε παραπάνω.</p>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold text-success"><i class="bi bi-archive me-1"></i> Οριστική Αρχειοθέτηση</div>
      <div class="card-body small text-muted">
        <p>Αφού αποθηκεύσετε τα στοιχεία παραπάνω, πατήστε το κουμπί <strong>«Οριστική Αρχειοθέτηση»</strong> από τη σελίδα της δράσης.</p>
        <p>Μετά την αρχειοθέτηση η δράση μετακινείται στις <strong>Ολοκληρωμένες</strong> και δεν μπορεί να τροποποιηθεί.</p>
        <a href="<?= e(url('/events/' . $event['id'])) ?>" class="btn btn-success btn-sm w-100 mt-1">
          <i class="bi bi-archive me-1"></i>Πήγαινε στη δράση →
        </a>
      </div>
    </div>
  </div>
</div>
