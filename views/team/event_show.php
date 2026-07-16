<?php
$terms = authority_context((int) ($event['municipality_id'] ?? current_municipality_id()));
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$orgLabel = $terms['short_name'] ?? 'Φορέας';
$requestedItems = [];
if (!empty($event['requested_items_json'])) {
    $decodedItems = json_decode((string) $event['requested_items_json'], true);
    $requestedItems = is_array($decodedItems) ? array_values(array_filter(array_map('trim', $decodedItems), fn($item) => $item !== '')) : [];
}
?>
<h1 class="h3 mb-1"><?= e($event['title']) ?> <?= status_badge($event['status']) ?></h1>
<p class="text-muted">
  <?= e(gr_datetime($event['start_datetime'])) ?> – <?= e(gr_datetime($event['end_datetime'])) ?>
  <?php if ($event['location_name']): ?> · <i class="bi bi-geo-alt"></i> <?= e($event['location_name']) ?><?php endif; ?>
</p>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><?= e(t('team/event_show.001', 'Στοιχεία')) ?> <?= e($eventSingularLc) ?></div>
      <div class="card-body">
        <?php if ($event['description']): ?><p><?= nl2br(e($event['description'])) ?></p><?php endif; ?>
        <dl class="row mb-0">
          <dt class="col-sm-4"><?= e(t('team/event_show.002', 'Διεύθυνση')) ?></dt><dd class="col-sm-8"><?= e($event['address'] ?: '—') ?></dd>
          <dt class="col-sm-4"><?= e(t('team/event_show.003', 'Ζητούμενα άτομα')) ?></dt><dd class="col-sm-8"><?= (int) $event['requested_people'] ?></dd>
          <dt class="col-sm-4"><?= e(t('team/event_show.004', 'Όχημα')) ?></dt><dd class="col-sm-8"><?= $event['requested_vehicle'] ? 'Απαιτείται' : 'Δεν απαιτείται' ?></dd>
          <dt class="col-sm-4"><?= e(t('team/event_show.005', 'Υγειονομικός εξοπλισμός')) ?></dt><dd class="col-sm-8"><?= $event['requested_medical_equipment'] ? 'Απαιτείται' : 'Δεν απαιτείται' ?></dd>
          <?php if ($requestedItems): ?>
            <dt class="col-sm-4"><?= e(t('team/event_show.006', 'Ζητούμενα αντικείμενα')) ?></dt>
            <dd class="col-sm-8">
              <div class="d-flex flex-wrap gap-1">
                <?php foreach ($requestedItems as $item): ?>
                  <span class="badge text-bg-light border"><?= e($item) ?></span>
                <?php endforeach; ?>
              </div>
            </dd>
          <?php endif; ?>
          <?php if ($event['instructions']): ?>
            <dt class="col-sm-4"><?= e(t('team/event_show.007', 'Οδηγίες')) ?></dt><dd class="col-sm-8"><?= nl2br(e($event['instructions'])) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <?php if ($event['latitude'] !== null && $event['longitude'] !== null): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-map me-1"></i> <?= e(t('team/event_show.008', 'Τοποθεσία')) ?></div>
        <div class="card-body p-2">
          <div id="eventMap" data-lat="<?= e($event['latitude']) ?>" data-lng="<?= e($event['longitude']) ?>" data-title="<?= e($event['title']) ?>"></div>
        </div>
      </div>
    <?php endif; ?>

    <?php /* ── Βάρδιες section for team portal ─────────────────────────── */ ?>
    <?php if ($shifts): ?>
    <div id="tab-shifts" class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-clock-history me-1"></i> <?= e(t('team/event_show.009', 'Βάρδιες')) ?> <?= e($eventSingularLc) ?>
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
                <?php if ($sh['required_people']): ?> · <?= (int) $sh['required_people'] ?> <?= e(t('team/event_show.074', 'ζητούμενα')) ?><?php endif; ?>
              </div>
              <?php if ($sh['notes']): ?><div class="text-muted small fst-italic"><?= e($sh['notes']) ?></div><?php endif; ?>
            </div>
            <div class="flex-shrink-0">
              <?php if ($hasApp): ?>
                <?= status_badge($appStatus) ?>
                <?php if ($appStatus === 'approved'): ?>
                  <div class="small text-success fw-semibold"><?= (int)$sh['approved_people'] ?> <?= e(t('team/event_show.011', 'εγκρ.')) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge bg-light text-muted"><?= e(t('team/event_show.012', 'Χωρίς δήλωση')) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($hasApp && $appStatus === 'pending' && in_array($event['status'], ['published','open','review','active'], true)): ?>
            <form method="post" action="<?= e(url('/team/shift-applications/' . $appId . '/cancel')) ?>"
                  class="mt-2" onsubmit="return confirm('Ακύρωση δήλωσης βάρδιας;')">
              <?= csrf_field() ?>
              <button class="btn btn-xs btn-outline-danger"><?= e(t('team/event_show.013', 'Ακύρωση δήλωσης')) ?></button>
            </form>
          <?php elseif (!$hasApp && in_array($event['status'], ['published','open','review'], true)): ?>
            <form method="post" action="<?= e(url('/team/events/' . $event['id'] . '/shifts/' . $sh['id'] . '/apply')) ?>"
                  class="mt-2 d-flex gap-2 align-items-center">
              <?= csrf_field() ?>
              <input type="number" name="offered_people" class="form-control form-control-sm" style="width:90px"
                     min="1" max="99" value="1" required>
              <span class="small text-muted"><?= e(t('team/event_show.014', 'άτομα')) ?></span>
              <button class="btn btn-xs btn-primary"><i class="bi bi-send me-1"></i><?= e(t('team/event_show.015', 'Δήλωση για βάρδια')) ?></button>
            </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <?php if (!empty($ownMatch)): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-stars me-1"></i> <?= e(t('team/event_show.016', 'Match ομάδας')) ?></span>
          <span class="badge text-bg-<?= e($ownMatch['level_class']) ?>"><?= (int) $ownMatch['score'] ?>%</span>
        </div>
        <div class="card-body">
          <div class="fw-semibold mb-2"><?= e($ownMatch['level']) ?></div>
          <?php if (!empty($ownMatch['missing'])): ?>
            <div class="small text-muted mb-2"><?= e(t('team/event_show.017', 'Λείπουν ή δεν έχουν δηλωθεί:')) ?></div>
            <div class="d-flex flex-wrap gap-1 mb-3">
              <?php foreach (array_slice($ownMatch['missing'], 0, 8) as $missing): ?>
                <span class="badge text-bg-warning text-dark border"><?= e($missing) ?></span>
              <?php endforeach; ?>
              <?php if (count($ownMatch['missing']) > 8): ?><span class="badge text-bg-light border">+<?= count($ownMatch['missing']) - 8 ?></span><?php endif; ?>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/team/readiness')) ?>">
              <i class="bi bi-clipboard2-check me-1"></i><?= e(t('team/event_show.018', 'Ενημέρωση ετοιμότητας')) ?>
            </a>
          <?php else: ?>
            <div class="small text-success"><i class="bi bi-check-circle me-1"></i><?= e(t('team/event_show.019', 'Η ομάδα καλύπτει όλα τα βασικά ζητούμενα.')) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (in_array($event['status'], ['closed', 'completed'], true) && ($application['status'] ?? '') === 'approved'): ?>
      <?php if (!$myDebrief): ?>
        <div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff">
          <div class="card-body d-flex align-items-start gap-3 p-4">
            <i class="bi bi-clipboard2-check fs-2 flex-shrink-0" style="opacity:.85"></i>
            <div class="flex-grow-1">
              <div class="fw-bold fs-5 mb-1">Post-Event Debrief</div>
              <p class="mb-3 small" style="opacity:.9">Η <?= e($eventSingularLc) ?> <?= e(t('team/event_show.075', 'ολοκληρώθηκε. Αφιερώστε 2 λεπτά να καταγράψετε τι πήγε καλά, τι μπορεί να βελτιωθεί και τα αριθμητικά στοιχεία της ομάδας.')) ?></p>
              <a href="<?= e(url('/team/events/' . $event['id'] . '/debrief')) ?>"
                 class="btn btn-light fw-semibold" style="color:#6366f1">
                <i class="bi bi-pencil-square me-1"></i><?= e(t('team/event_show.021', 'Συμπλήρωση Debrief')) ?>
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
          <i class="bi bi-clipboard2-check-fill fs-5"></i>
          <div class="flex-grow-1">
            <strong><?= e(t('team/event_show.022', 'Debrief υποβλήθηκε')) ?></strong>
            <div class="small"><?= e(t('team/event_show.076', 'από')) ?> <?= e($myDebrief['submitted_by_name']) ?> · <?= e(gr_datetime($myDebrief['submitted_at'])) ?></div>
          </div>
          <a href="<?= e(url('/team/events/' . $event['id'] . '/debrief')) ?>" class="btn btn-sm btn-outline-success ms-auto"><?= e(t('team/event_show.024', 'Επεξεργασία')) ?></a>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$application): ?>

      <?php if (in_array($event['status'], ['open', 'review', 'active'], true)): ?>

        <?php if (!$teamMembers): ?>
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= e(t('team/event_show.025', 'Πρέπει να έχετε τουλάχιστον ένα ενεργό μέλος για να υποβάλετε δήλωση.')) ?>
            <a href="<?= e(url('/team/members/create')) ?>" class="alert-link"><?= e(t('team/event_show.026', 'Προσθέστε μέλη')) ?></a>.
          </div>
        <?php else: ?>
          <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white fw-semibold"><i class="bi bi-send me-1"></i> <?= e(t('team/event_show.027', 'Δήλωση Συμμετοχής')) ?></div>
            <div class="card-body">
              <form method="post" action="<?= e(url('/team/events/' . $event['id'] . '/apply')) ?>"
                    id="applyForm">
                <?= csrf_field() ?>

                <!-- Member selection -->
                <label class="form-label fw-semibold"><?= e(t('team/event_show.028', 'Επιλογή μελών')) ?> <span class="text-danger">*</span></label>
                <p class="text-muted small mb-2"><?= e(t('team/event_show.029', 'Επιλέξτε ποια μέλη θα συμμετέχουν. Τα μέλη με')) ?> <span class="text-warning fw-bold">⚠</span> <?= e(t('team/event_show.077', 'έχουν άλλη εγκεκριμένη')) ?> <?= e($eventSingularLc) ?> <?= e(t('team/event_show.078', 'την ίδια περίοδο.')) ?></p>

                <div class="list-group mb-3" id="memberList">
                  <?php foreach ($teamMembers as $m):
                    $isConflict = in_array($m['id'], $conflictingMemberIds ?? [], false);
                  ?>
                    <label class="list-group-item d-flex align-items-center gap-2 cursor-pointer <?= $isConflict ? 'list-group-item-warning' : '' ?>"
                           for="mem_<?= $m['id'] ?>">
                      <input class="form-check-input member-cb flex-shrink-0" type="checkbox"
                             name="member_ids[]" value="<?= (int) $m['id'] ?>"
                             id="mem_<?= $m['id'] ?>"
                             <?= $isConflict ? 'title="Δεσμευμένο σε άλλη ' . e($eventSingularLc) . '"' : '' ?>>
                      <div class="flex-grow-1">
                        <span class="fw-semibold">
                          <?php if ($m['is_team_admin']): ?><i class="bi bi-shield-fill text-primary me-1" title="<?= e(t('team/event_show.071', 'Διαχειριστής')) ?>"></i><?php endif; ?>
                          <?= e($m['full_name']) ?>
                          <?php if ($isConflict): ?><span class="text-warning ms-1" title="<?= e(t('team/event_show.072', 'Δεσμευμένο σε άλλη')) ?> <?= e($eventSingularLc) ?>">⚠</span><?php endif; ?>
                        </span>
                        <?php if ($m['role_in_team']): ?><div class="small text-muted"><?= e($m['role_in_team']) ?></div><?php endif; ?>
                      </div>
                    </label>
                  <?php endforeach; ?>
                </div>

                <!-- Mission Commander -->
                <div class="mb-3" id="commanderSection">
                  <label class="form-label fw-semibold"><?= e(t('team/event_show.031', 'Mission Υπεύθυνος')) ?> <span class="text-danger">*</span></label>
                  <p class="text-muted small mb-1"><?= e(t('team/event_show.079', 'Αυτό το πρόσωπο θα στέλνει στίγμα και ενημερώσεις κατά τη')) ?> <?= e($eventSingularLc) ?>.</p>
                  <select name="mission_commander_id" class="form-select" id="commanderSelect" required>
                    <option value=""><?= e(t('team/event_show.033', '— Επιλέξτε μέλος —')) ?></option>
                    <?php foreach ($teamMembers as $m): ?>
                      <option value="<?= (int) $m['id'] ?>" data-member-id="<?= (int) $m['id'] ?>" disabled>
                        <?= e($m['full_name']) ?><?= $m['is_team_admin'] ? ' (Διαχ.)' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text"><?= e(t('team/event_show.034', 'Μόνο τα επιλεγμένα μέλη εμφανίζονται εδώ.')) ?></div>
                </div>

                <!-- Offered count (auto) -->
                <div class="mb-3">
                  <label class="form-label"><?= e(t('team/event_show.035', 'Άτομα που δηλώνονται')) ?></label>
                  <div class="input-group">
                    <input type="number" name="offered_people" id="offeredPeople" class="form-control"
                           value="0" min="1" readonly>
                    <span class="input-group-text"><?= e(t('team/event_show.076', 'από')) ?> <?= count($teamMembers) ?> <?= e(t('team/event_show.080', 'ενεργά μέλη')) ?></span>
                  </div>
                </div>

                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" name="offered_vehicle" id="offVehicle" value="1" <?= $team['has_vehicle'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="offVehicle"><?= e(t('team/event_show.037', 'Διαθέσιμο όχημα')) ?></label>
                </div>
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" name="offered_medical_equipment" id="offMedical" value="1" <?= $team['has_medical_equipment'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="offMedical"><?= e(t('team/event_show.038', 'Διαθέσιμος υγειονομικός εξοπλισμός')) ?></label>
                </div>
                <div class="mb-3">
                  <label class="form-label"><?= e(t('team/event_show.039', 'Σχόλιο (προαιρετικό)')) ?></label>
                  <textarea name="comment" class="form-control" rows="2"></textarea>
                </div>

                <button class="btn btn-primary btn-lg w-100" id="submitBtn" disabled>
                  <i class="bi bi-send me-1"></i><?= e(t('team/event_show.040', 'Υποβολή Δήλωσης')) ?>
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="alert alert-secondary">Η <?= e($eventSingularLc) ?> <?= e(t('team/event_show.081', 'δεν δέχεται δηλώσεις συμμετοχής αυτή τη στιγμή.')) ?></div>
      <?php endif; ?>

    <?php else: ?>

      <!-- Existing application card -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><?= e(t('team/event_show.042', 'Η δήλωσή σας')) ?> <?= status_badge($application['status']) ?></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-7"><?= e(t('team/event_show.043', 'Προσφερόμενα άτομα')) ?></dt><dd class="col-5"><?= (int) $application['offered_people'] ?></dd>
            <?php if ($application['status'] === 'approved'): ?>
              <dt class="col-7"><?= e(t('team/event_show.044', 'Εγκεκριμένα άτομα')) ?></dt><dd class="col-5 fw-bold text-success"><?= (int) $application['approved_people'] ?></dd>
            <?php endif; ?>
            <dt class="col-7"><?= e(t('team/event_show.004', 'Όχημα')) ?></dt><dd class="col-5"><?= $application['offered_vehicle'] ? 'Ναι' : 'Όχι' ?></dd>
            <dt class="col-7"><?= e(t('team/event_show.045', 'Υγειον. εξοπλισμός')) ?></dt><dd class="col-5"><?= $application['offered_medical_equipment'] ? 'Ναι' : 'Όχι' ?></dd>
            <dt class="col-7"><?= e(t('team/event_show.046', 'Υποβλήθηκε')) ?></dt><dd class="col-5"><?= e(gr_datetime($application['submitted_at'])) ?></dd>
          </dl>
          <?php if ($application['admin_comment']): ?>
            <div class="alert alert-info mt-3 mb-0 small"><strong><?= e(t('team/event_show.082', 'Σχόλιο')) ?> <?= e($orgLabel) ?>:</strong> <?= e($application['admin_comment']) ?></div>
          <?php endif; ?>

          <!-- Members & commander assigned -->
          <?php if ($applicationMembers): ?>
            <hr class="my-3">
            <div class="mb-1 fw-semibold small"><?= e(t('team/event_show.083', 'Ορισμένα μέλη (')) ?><?= count($applicationMembers) ?>)</div>
            <ul class="list-unstyled small mb-0">
              <?php foreach ($applicationMembers as $m): ?>
                <li>
                  <?php if ((int) $m['id'] === (int) ($application['mission_commander_id'] ?? 0)): ?>
                    <i class="bi bi-star-fill text-warning me-1" title="<?= e(t('team/event_show.031', 'Mission Υπεύθυνος')) ?>"></i>
                  <?php else: ?>
                    <i class="bi bi-person me-1 text-muted"></i>
                  <?php endif; ?>
                  <?= e($m['full_name']) ?>
                  <?php if ((int) $m['id'] === (int) ($application['mission_commander_id'] ?? 0)): ?>
                    <span class="badge text-bg-warning ms-1"><?= e(t('team/event_show.031', 'Mission Υπεύθυνος')) ?></span>
                  <?php endif; ?>
                  <?php if ($m['is_team_admin']): ?><span class="badge bg-primary ms-1"><?= e(t('team/event_show.049', 'Διαχ.')) ?></span><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (!empty($fieldToken)):
            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $fieldUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('/f/' . $fieldToken);
            $fieldPin = EventApplication::ensureFieldPin((int) $application['id']);
          ?>
          <hr class="my-3">
          <div class="p-3 rounded border border-2 border-warning bg-warning-subtle">
            <div class="fw-semibold mb-1"><i class="bi bi-link-45deg me-1"></i><?= e(t('team/event_show.050', 'Σύνδεσμος πεδίου — Mission Υπεύθυνος')) ?></div>
            <div class="small text-muted mb-2"><?= e(t('team/event_show.051', 'Ο/Η')) ?> <strong><?= e($commander['full_name'] ?? '—') ?></strong> <?= e(t('team/event_show.052', 'μπορεί να στέλνει στίγμα, SOS, ενημερώσεις και να λαμβάνει εντολές')) ?> <strong><?= e(t('team/event_show.053', 'χωρίς λογαριασμό')) ?></strong><?= e(t('team/event_show.054', ', από αυτόν τον προσωπικό σύνδεσμο.')) ?></div>
            <div class="input-group input-group-sm mb-2">
              <input type="text" class="form-control" id="fieldLinkInput" value="<?= e($fieldUrl) ?>" readonly onclick="this.select()">
              <button class="btn btn-outline-secondary" type="button" onclick="copyFieldLink()"><i class="bi bi-clipboard me-1"></i><?= e(t('team/event_show.055', 'Αντιγραφή')) ?></button>
            </div>
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
              <span class="small text-muted"><i class="bi bi-shield-lock me-1"></i><?= e(t('team/event_show.056', 'PIN συσκευής:')) ?></span>
              <span class="badge bg-dark fs-6" style="letter-spacing:3px"><?= e($fieldPin) ?></span>
              <form method="post" action="<?= e(url('/team/applications/' . $application['id'] . '/regenerate-pin')) ?>" class="m-0"
                    onsubmit="return confirm('Δημιουργία νέου PIN; Οι ήδη συνδεδεμένες συσκευές θα ζητήσουν ξανά κωδικό.');">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-arrow-repeat me-1"></i><?= e(t('team/event_show.057', 'Νέο PIN')) ?></button>
              </form>
            </div>
            <div class="small text-muted mb-2"><?= e(t('team/event_show.058', 'Στείλτε το PIN μαζί με τον σύνδεσμο.')) ?></div>
            <div class="d-flex flex-wrap gap-2">
              <form method="post" action="<?= e(url('/team/applications/' . $application['id'] . '/send-field-link')) ?>" class="m-0">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-success" type="submit" <?= empty($commander['phone']) ? 'disabled title="Ο υπεύθυνος δεν έχει τηλέφωνο"' : '' ?>>
                  <i class="bi bi-chat-dots me-1"></i><?= e(t('team/event_show.059', 'Αποστολή με SMS')) ?><?= !empty($commander['phone']) ? ' (' . e($commander['phone']) . ')' : '' ?>
                </button>
              </form>
              <a class="btn btn-sm btn-outline-primary" href="<?= e($fieldUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i><?= e(t('team/event_show.060', 'Άνοιγμα')) ?></a>
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
              <i class="bi bi-geo-alt me-1"></i><?= e(t('team/event_show.061', 'Επιχειρησιακές Ενέργειες')) ?>
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Edit members (only before first check-in) -->
      <?php if ($canEditMembers && $teamMembers): ?>
        <div class="card shadow-sm mb-4 border-warning">
          <div class="card-header bg-warning-subtle fw-semibold">
            <i class="bi bi-pencil me-1"></i><?= e(t('team/event_show.062', 'Τροποποίηση μελών / Mission Υπεύθυνου')) ?>
          </div>
          <div class="card-body">
            <p class="small text-muted"><?= e(t('team/event_show.063', 'Μπορείτε να τροποποιήσετε τον κατάλογο μελών μέχρι το πρώτο check-in.')) ?></p>
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
                           <?= $isConflict && !$isAssigned ? 'title="Δεσμευμένο σε άλλη ' . e($eventSingularLc) . '"' : '' ?>>
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
                <label class="form-label fw-semibold"><?= e(t('team/event_show.031', 'Mission Υπεύθυνος')) ?> <span class="text-danger">*</span></label>
                <select name="mission_commander_id" class="form-select" id="editCommanderSelect" required>
                  <option value=""><?= e(t('team/event_show.064', '— Επιλέξτε —')) ?></option>
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
                <i class="bi bi-save me-1"></i><?= e(t('team/event_show.065', 'Αποθήκευση αλλαγών')) ?>
              </button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($application['status'] === 'pending'): ?>
        <form method="post" action="<?= e(url('/team/applications/' . $application['id'] . '/cancel')) ?>"
              onsubmit="return confirm('Να ακυρωθεί η δήλωση συμμετοχής;')">
          <?= csrf_field() ?>
          <button class="btn btn-outline-danger w-100 mb-4"><?= e(t('team/event_show.013', 'Ακύρωση δήλωσης')) ?></button>
        </form>
      <?php endif; ?>

      <?php if ($application['status'] === 'approved' && in_array($event['status'], ['active', 'completed'], true)): ?>
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-file-earmark-text me-1"></i> <?= e(t('team/event_show.066', 'Αναφορά Ομάδας')) ?>
            <?php if ($myReport): ?><span class="badge text-bg-success ms-1"><?= e(t('team/event_show.046', 'Υποβλήθηκε')) ?></span><?php endif; ?>
          </div>
          <div class="card-body">
            <form method="post" action="<?= e(url('/team/events/' . $event['id'] . '/report')) ?>">
              <?= csrf_field() ?>
              <div class="row g-2 mb-3">
                <div class="col-4">
                  <label class="form-label small"><?= e(t('team/event_show.067', 'Περιστατικά')) ?></label>
                  <input type="number" min="0" name="incidents_count" class="form-control" value="<?= $myReport ? (int) $myReport['incidents_count'] : 0 ?>">
                </div>
                <div class="col-4">
                  <label class="form-label small"><?= e(t('team/event_show.068', 'Διακομιδές')) ?></label>
                  <input type="number" min="0" name="transfers_count" class="form-control" value="<?= $myReport ? (int) $myReport['transfers_count'] : 0 ?>">
                </div>
                <div class="col-4">
                  <label class="form-label small"><?= e(t('team/event_show.069', 'Πρώτες βοήθειες')) ?></label>
                  <input type="number" min="0" name="first_aid_count" class="form-control" value="<?= $myReport ? (int) $myReport['first_aid_count'] : 0 ?>">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label small"><?= e(t('team/event_show.070', 'Σύνοψη')) ?></label>
                <textarea name="summary" class="form-control" rows="3" placeholder="<?= e(t('team/event_show.073', 'Σύντομη περιγραφή της παρουσίας της ομάδας')) ?>"><?= $myReport ? e($myReport['summary']) : '' ?></textarea>
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
