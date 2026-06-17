<h1 class="h3 mb-1"><?= e($event['title']) ?> <?= status_badge($event['status']) ?></h1>
<p class="text-muted">
  <?= e(gr_datetime($event['start_datetime'])) ?> – <?= e(gr_datetime($event['end_datetime'])) ?>
  <?php if ($event['location_name']): ?> · <i class="bi bi-geo-alt"></i> <?= e($event['location_name']) ?><?php endif; ?>
</p>

<div class="row g-4">
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
        </dl>
      </div>
    </div>

    <?php if ($event['latitude'] !== null && $event['longitude'] !== null): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-map me-1"></i> Τοποθεσία</div>
        <div class="card-body p-2">
          <div id="eventMap" data-lat="<?= e($event['latitude']) ?>" data-lng="<?= e($event['longitude']) ?>" data-title="<?= e($event['title']) ?>"></div>
        </div>
      </div>
    <?php endif; ?>

    <?php /* ── Βάρδιες section for team portal ─────────────────────────── */ ?>
    <?php if ($shifts): ?>
    <div id="tab-shifts" class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-clock-history me-1"></i> Βάρδιες δράσης
        <span class="badge bg-primary ms-1"><?= count($shifts) ?></span>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($shifts as $sh): ?>
        <?php
          $appStatus = $sh['app_status'] ?? null;
          $appId     = $sh['app_id']     ?? null;
          $hasApp    = $appStatus !== null;
          $borderClass = match($appStatus) {
              'approved' => 'border-start border-4 border-success',
              'pending'  => 'border-start border-4 border-warning',
              'rejected' => 'border-start border-4 border-danger',
              default    => '',
          };
        ?>
        <div class="list-group-item py-3 <?= $borderClass ?> ps-3">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="fw-semibold"><i class="bi bi-clock text-primary me-1"></i><?= e($sh['name']) ?></div>
              <div class="text-muted small"><?= e(gr_datetime($sh['start_datetime'])) ?> – <?= e(gr_time($sh['end_datetime'])) ?>
                <?php if ($sh['required_people']): ?> · <?= (int) $sh['required_people'] ?> ζητούμενα<?php endif; ?>
              </div>
              <?php if ($sh['notes']): ?><div class="text-muted small fst-italic"><?= e($sh['notes']) ?></div><?php endif; ?>
            </div>
            <div class="flex-shrink-0">
              <?php if ($hasApp): ?>
                <?= status_badge($appStatus) ?>
                <?php if ($appStatus === 'approved'): ?>
                  <div class="small text-success fw-semibold"><?= (int)$sh['approved_people'] ?> εγκρ.</div>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge bg-light text-muted">Χωρίς δήλωση</span>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($hasApp && $appStatus === 'pending' && in_array($event['status'], ['published','open','review','active'], true)): ?>
            <form method="post" action="<?= e(url('/team/shift-applications/' . $appId . '/cancel')) ?>"
                  class="mt-2" onsubmit="return confirm('Ακύρωση δήλωσης βάρδιας;')">
              <?= csrf_field() ?>
              <button class="btn btn-xs btn-outline-danger">Ακύρωση δήλωσης</button>
            </form>
          <?php elseif (!$hasApp && in_array($event['status'], ['published','open','review'], true)): ?>
            <form method="post" action="<?= e(url('/team/events/' . $event['id'] . '/shifts/' . $sh['id'] . '/apply')) ?>"
                  class="mt-2 d-flex gap-2 align-items-center">
              <?= csrf_field() ?>
              <input type="number" name="offered_people" class="form-control form-control-sm" style="width:90px"
                     min="1" max="99" value="1" required>
              <span class="small text-muted">άτομα</span>
              <button class="btn btn-xs btn-primary"><i class="bi bi-send me-1"></i>Δήλωση για βάρδια</button>
            </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">

    <?php if (!$application): ?>

      <?php if (in_array($event['status'], ['open', 'review', 'active'], true)): ?>

        <?php if (!$teamMembers): ?>
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Πρέπει να έχετε τουλάχιστον ένα ενεργό μέλος για να υποβάλετε δήλωση.
            <a href="<?= e(url('/team/members/create')) ?>" class="alert-link">Προσθέστε μέλη</a>.
          </div>
        <?php else: ?>
          <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white fw-semibold"><i class="bi bi-send me-1"></i> Δήλωση Συμμετοχής</div>
            <div class="card-body">
              <form method="post" action="<?= e(url('/team/events/' . $event['id'] . '/apply')) ?>"
                    id="applyForm">
                <?= csrf_field() ?>

                <!-- Member selection -->
                <label class="form-label fw-semibold">Επιλογή μελών <span class="text-danger">*</span></label>
                <p class="text-muted small mb-2">Επιλέξτε ποια μέλη θα συμμετέχουν. Τα μέλη με <span class="text-warning fw-bold">⚠</span> έχουν άλλη εγκεκριμένη δράση την ίδια περίοδο.</p>

                <div class="list-group mb-3" id="memberList">
                  <?php foreach ($teamMembers as $m):
                    $isConflict = in_array($m['id'], $conflictingMemberIds ?? [], false);
                  ?>
                    <label class="list-group-item d-flex align-items-center gap-2 cursor-pointer <?= $isConflict ? 'list-group-item-warning' : '' ?>"
                           for="mem_<?= $m['id'] ?>">
                      <input class="form-check-input member-cb flex-shrink-0" type="checkbox"
                             name="member_ids[]" value="<?= (int) $m['id'] ?>"
                             id="mem_<?= $m['id'] ?>"
                             <?= $isConflict ? 'title="Δεσμευμένο σε άλλη δράση"' : '' ?>>
                      <div class="flex-grow-1">
                        <span class="fw-semibold">
                          <?php if ($m['is_team_admin']): ?><i class="bi bi-shield-fill text-primary me-1" title="Διαχειριστής"></i><?php endif; ?>
                          <?= e($m['full_name']) ?>
                          <?php if ($isConflict): ?><span class="text-warning ms-1" title="Δεσμευμένο σε άλλη δράση">⚠</span><?php endif; ?>
                        </span>
                        <?php if ($m['role_in_team']): ?><div class="small text-muted"><?= e($m['role_in_team']) ?></div><?php endif; ?>
                      </div>
                    </label>
                  <?php endforeach; ?>
                </div>

                <!-- Mission Commander -->
                <div class="mb-3" id="commanderSection">
                  <label class="form-label fw-semibold">Mission Υπεύθυνος <span class="text-danger">*</span></label>
                  <p class="text-muted small mb-1">Αυτό το πρόσωπο θα στέλνει στίγμα και ενημερώσεις κατά τη δράση.</p>
                  <select name="mission_commander_id" class="form-select" id="commanderSelect" required>
                    <option value="">— Επιλέξτε μέλος —</option>
                    <?php foreach ($teamMembers as $m): ?>
                      <option value="<?= (int) $m['id'] ?>" data-member-id="<?= (int) $m['id'] ?>" disabled>
                        <?= e($m['full_name']) ?><?= $m['is_team_admin'] ? ' (Διαχ.)' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Μόνο τα επιλεγμένα μέλη εμφανίζονται εδώ.</div>
                </div>

                <!-- Offered count (auto) -->
                <div class="mb-3">
                  <label class="form-label">Άτομα που δηλώνονται</label>
                  <div class="input-group">
                    <input type="number" name="offered_people" id="offeredPeople" class="form-control"
                           value="0" min="1" readonly>
                    <span class="input-group-text">από <?= count($teamMembers) ?> ενεργά μέλη</span>
                  </div>
                </div>

                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" name="offered_vehicle" id="offVehicle" value="1" <?= $team['has_vehicle'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="offVehicle">Διαθέσιμο όχημα</label>
                </div>
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" name="offered_medical_equipment" id="offMedical" value="1" <?= $team['has_medical_equipment'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="offMedical">Διαθέσιμος υγειονομικός εξοπλισμός</label>
                </div>
                <div class="mb-3">
                  <label class="form-label">Σχόλιο (προαιρετικό)</label>
                  <textarea name="comment" class="form-control" rows="2"></textarea>
                </div>

                <button class="btn btn-primary btn-lg w-100" id="submitBtn" disabled>
                  <i class="bi bi-send me-1"></i>Υποβολή Δήλωσης
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="alert alert-secondary">Η δράση δεν δέχεται δηλώσεις συμμετοχής αυτή τη στιγμή.</div>
      <?php endif; ?>

    <?php else: ?>

      <!-- Existing application card -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Η δήλωσή σας <?= status_badge($application['status']) ?></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-7">Προσφερόμενα άτομα</dt><dd class="col-5"><?= (int) $application['offered_people'] ?></dd>
            <?php if ($application['status'] === 'approved'): ?>
              <dt class="col-7">Εγκεκριμένα άτομα</dt><dd class="col-5 fw-bold text-success"><?= (int) $application['approved_people'] ?></dd>
            <?php endif; ?>
            <dt class="col-7">Όχημα</dt><dd class="col-5"><?= $application['offered_vehicle'] ? 'Ναι' : 'Όχι' ?></dd>
            <dt class="col-7">Υγειον. εξοπλισμός</dt><dd class="col-5"><?= $application['offered_medical_equipment'] ? 'Ναι' : 'Όχι' ?></dd>
            <dt class="col-7">Υποβλήθηκε</dt><dd class="col-5"><?= e(gr_datetime($application['submitted_at'])) ?></dd>
          </dl>
          <?php if ($application['admin_comment']): ?>
            <div class="alert alert-info mt-3 mb-0 small"><strong>Σχόλιο δήμου:</strong> <?= e($application['admin_comment']) ?></div>
          <?php endif; ?>

          <!-- Members & commander assigned -->
          <?php if ($applicationMembers): ?>
            <hr class="my-3">
            <div class="mb-1 fw-semibold small">Ορισμένα μέλη (<?= count($applicationMembers) ?>)</div>
            <ul class="list-unstyled small mb-0">
              <?php foreach ($applicationMembers as $m): ?>
                <li>
                  <?php if ((int) $m['id'] === (int) ($application['mission_commander_id'] ?? 0)): ?>
                    <i class="bi bi-star-fill text-warning me-1" title="Mission Υπεύθυνος"></i>
                  <?php else: ?>
                    <i class="bi bi-person me-1 text-muted"></i>
                  <?php endif; ?>
                  <?= e($m['full_name']) ?>
                  <?php if ((int) $m['id'] === (int) ($application['mission_commander_id'] ?? 0)): ?>
                    <span class="badge text-bg-warning ms-1">Mission Υπεύθυνος</span>
                  <?php endif; ?>
                  <?php if ($m['is_team_admin']): ?><span class="badge bg-primary ms-1">Διαχ.</span><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (!empty($fieldToken)):
            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $fieldUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('/f/' . $fieldToken);
          ?>
          <hr class="my-3">
          <div class="p-3 rounded border border-2 border-warning bg-warning-subtle">
            <div class="fw-semibold mb-1"><i class="bi bi-link-45deg me-1"></i>Σύνδεσμος πεδίου — Mission Υπεύθυνος</div>
            <div class="small text-muted mb-2">Ο/Η <strong><?= e($commander['full_name'] ?? '—') ?></strong> μπορεί να στέλνει στίγμα, SOS, ενημερώσεις και να λαμβάνει εντολές <strong>χωρίς λογαριασμό</strong>, από αυτόν τον προσωπικό σύνδεσμο.</div>
            <div class="input-group input-group-sm mb-2">
              <input type="text" class="form-control" id="fieldLinkInput" value="<?= e($fieldUrl) ?>" readonly onclick="this.select()">
              <button class="btn btn-outline-secondary" type="button" onclick="copyFieldLink()"><i class="bi bi-clipboard me-1"></i>Αντιγραφή</button>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <form method="post" action="<?= e(url('/team/applications/' . $application['id'] . '/send-field-link')) ?>" class="m-0">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-success" type="submit" <?= empty($commander['phone']) ? 'disabled title="Ο υπεύθυνος δεν έχει τηλέφωνο"' : '' ?>>
                  <i class="bi bi-chat-dots me-1"></i>Αποστολή με SMS<?= !empty($commander['phone']) ? ' (' . e($commander['phone']) . ')' : '' ?>
                </button>
              </form>
              <a class="btn btn-sm btn-outline-primary" href="<?= e($fieldUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Άνοιγμα</a>
            </div>
          </div>
          <script>
          function copyFieldLink(){var i=document.getElementById('fieldLinkInput');i.select();i.setSelectionRange(0,99999);
            if(navigator.clipboard){navigator.clipboard.writeText(i.value).then(function(){},function(){});}else{try{document.execCommand('copy');}catch(e){}}}
          </script>
          <?php endif; ?>
        </div>

        <?php if ($application['status'] === 'approved' && $event['status'] === 'active'): ?>
          <div class="card-footer bg-white">
            <a href="<?= e(url('/team/operations/events/' . $event['id'])) ?>" class="btn btn-warning w-100">
              <i class="bi bi-geo-alt me-1"></i>Επιχειρησιακές Ενέργειες
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Edit members (only before first check-in) -->
      <?php if ($canEditMembers && $teamMembers): ?>
        <div class="card shadow-sm mb-4 border-warning">
          <div class="card-header bg-warning-subtle fw-semibold">
            <i class="bi bi-pencil me-1"></i>Τροποποίηση μελών / Mission Υπεύθυνου
          </div>
          <div class="card-body">
            <p class="small text-muted">Μπορείτε να τροποποιήσετε τον κατάλογο μελών μέχρι το πρώτο check-in.</p>
            <form method="post" action="<?= e(url('/team/events/' . $event['id'] . '/application/members')) ?>"
                  id="editMembersForm">
              <?= csrf_field() ?>

              <div class="list-group mb-3">
                <?php
                $assignedIds = array_column($applicationMembers, 'id');
                foreach ($teamMembers as $m):
                  $isConflict = in_array($m['id'], $conflictingMemberIds ?? [], false);
                  $isAssigned = in_array($m['id'], $assignedIds, false);
                ?>
                  <label class="list-group-item d-flex align-items-center gap-2 cursor-pointer <?= $isConflict && !$isAssigned ? 'list-group-item-warning' : '' ?>"
                         for="edit_mem_<?= $m['id'] ?>">
                    <input class="form-check-input edit-member-cb flex-shrink-0" type="checkbox"
                           name="member_ids[]" value="<?= (int) $m['id'] ?>"
                           id="edit_mem_<?= $m['id'] ?>"
                           <?= $isAssigned ? 'checked' : '' ?>
                           <?= $isConflict && !$isAssigned ? 'title="Δεσμευμένο σε άλλη δράση"' : '' ?>>
                    <div class="flex-grow-1">
                      <span class="fw-semibold">
                        <?php if ($m['is_team_admin']): ?><i class="bi bi-shield-fill text-primary me-1"></i><?php endif; ?>
                        <?= e($m['full_name']) ?>
                        <?php if ($isConflict && !$isAssigned): ?><span class="text-warning">⚠</span><?php endif; ?>
                      </span>
                      <?php if ($m['role_in_team']): ?><div class="small text-muted"><?= e($m['role_in_team']) ?></div><?php endif; ?>
                    </div>
                  </label>
                <?php endforeach; ?>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold">Mission Υπεύθυνος <span class="text-danger">*</span></label>
                <select name="mission_commander_id" class="form-select" id="editCommanderSelect" required>
                  <option value="">— Επιλέξτε —</option>
                  <?php foreach ($teamMembers as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" data-member-id="<?= (int) $m['id'] ?>"
                            <?= (int) $m['id'] === (int) ($application['mission_commander_id'] ?? 0) ? 'selected' : '' ?>
                            <?= !in_array($m['id'], $assignedIds, false) ? 'disabled' : '' ?>>
                      <?= e($m['full_name']) ?><?= $m['is_team_admin'] ? ' (Διαχ.)' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <button class="btn btn-warning w-100">
                <i class="bi bi-save me-1"></i>Αποθήκευση αλλαγών
              </button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($application['status'] === 'pending'): ?>
        <form method="post" action="<?= e(url('/team/applications/' . $application['id'] . '/cancel')) ?>"
              onsubmit="return confirm('Να ακυρωθεί η δήλωση συμμετοχής;')">
          <?= csrf_field() ?>
          <button class="btn btn-outline-danger w-100 mb-4">Ακύρωση δήλωσης</button>
        </form>
      <?php endif; ?>

      <?php if ($application['status'] === 'approved' && in_array($event['status'], ['active', 'completed'], true)): ?>
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-file-earmark-text me-1"></i> Αναφορά Ομάδας
            <?php if ($myReport): ?><span class="badge text-bg-success ms-1">Υποβλήθηκε</span><?php endif; ?>
          </div>
          <div class="card-body">
            <form method="post" action="<?= e(url('/team/events/' . $event['id'] . '/report')) ?>">
              <?= csrf_field() ?>
              <div class="row g-2 mb-3">
                <div class="col-4">
                  <label class="form-label small">Περιστατικά</label>
                  <input type="number" min="0" name="incidents_count" class="form-control" value="<?= $myReport ? (int) $myReport['incidents_count'] : 0 ?>">
                </div>
                <div class="col-4">
                  <label class="form-label small">Διακομιδές</label>
                  <input type="number" min="0" name="transfers_count" class="form-control" value="<?= $myReport ? (int) $myReport['transfers_count'] : 0 ?>">
                </div>
                <div class="col-4">
                  <label class="form-label small">Πρώτες βοήθειες</label>
                  <input type="number" min="0" name="first_aid_count" class="form-control" value="<?= $myReport ? (int) $myReport['first_aid_count'] : 0 ?>">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label small">Σύνοψη</label>
                <textarea name="summary" class="form-control" rows="3" placeholder="Σύντομη περιγραφή της παρουσίας της ομάδας"><?= $myReport ? e($myReport['summary']) : '' ?></textarea>
              </div>
              <button class="btn btn-primary w-100"><?= $myReport ? 'Ενημέρωση αναφοράς' : 'Υποβολή αναφοράς' ?></button>
            </form>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  /* ---- Apply form: member checkboxes → update offered count + commander dropdown ---- */
  function syncForm(cbSelector, selectId, countId, submitId) {
    var cbs = document.querySelectorAll(cbSelector);
    var sel = document.getElementById(selectId);
    var cnt = document.getElementById(countId);
    var btn = submitId ? document.getElementById(submitId) : null;
    if (!cbs.length || !sel) return;

    function update() {
      var checked = Array.from(cbs).filter(function (c) { return c.checked; });
      var ids = checked.map(function (c) { return parseInt(c.value, 10); });

      // Update count
      if (cnt) cnt.value = ids.length;

      // Rebuild commander options
      Array.from(sel.options).forEach(function (opt) {
        var mid = parseInt(opt.dataset.memberId, 10);
        if (isNaN(mid)) return; // placeholder
        opt.disabled = !ids.includes(mid);
        if (opt.disabled && opt.selected) {
          opt.selected = false;
          sel.value = '';
        }
      });

      // Enable/disable submit
      if (btn) btn.disabled = ids.length === 0 || sel.value === '';
    }

    cbs.forEach(function (cb) { cb.addEventListener('change', update); });
    sel.addEventListener('change', function () {
      if (btn) {
        var checked = Array.from(cbs).filter(function (c) { return c.checked; });
        btn.disabled = checked.length === 0 || sel.value === '';
      }
    });

    update();
  }

  syncForm('.member-cb',      'commanderSelect',     'offeredPeople', 'submitBtn');
  syncForm('.edit-member-cb', 'editCommanderSelect',  null,            null);
})();
</script>
