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

      <?php if (in_array($event['status'], ['open', 'review'], true)): ?>

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
                          <?php if ($isConflict): ?><span class="text-warning ms-1" title="Δεσμευμένο σε άλλη δράση">⚠</span><?php end