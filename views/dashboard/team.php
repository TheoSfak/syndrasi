<?php
$year = (int) date('Y');
$monthsShort = ['Ιαν','Φεβ','Μαρ','Απρ','Μαϊ','Ιουν','Ιουλ','Αυγ','Σεπ','Οκτ','Νοε','Δεκ'];
?>

<!-- Page header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h3 mb-0">Πίνακας Ελέγχου</h1>
    <p class="text-muted small mb-0"><?= e($team['name']) ?> · <?= e($team['type'] ?? '') ?></p>
  </div>
  <?php if ($todayOperational): ?>
  <span class="badge bg-danger px-3 py-2" style="font-size:.8rem;animation:pulse-dot 1.5s infinite">
    <i class="bi bi-broadcast me-1"></i><?= count($todayOperational) ?> Δράση σε Εξέλιξη
  </span>
  <?php elseif ($availableEvents > 0): ?>
  <a href="<?= e(url('/team/events')) ?>" class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 text-decoration-none" style="font-size:.8rem">
    <i class="bi bi-bell me-1"></i><?= $availableEvents ?> νέες δράσεις διαθέσιμες
  </a>
  <?php endif; ?>
</div>

<?php if ($todayOperational): ?>
<!-- ── LIVE Operational ───────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:1rem;border-left:4px solid #e11d48 !important;">
  <div class="card-body p-4">
    <div class="section-hd" style="--c-primary:#e11d48">
      <span class="badge bg-danger me-1" style="font-size:.65rem;animation:pulse-dot 1.5s infinite">LIVE</span>
      Δράσεις σε Εξέλιξη
    </div>
    <?php foreach ($todayOperational as $ev): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-3">
      <div>
        <div class="fw-semibold"><?= e($ev['title']) ?></div>
        <div class="text-muted small mt-1">
          <i class="bi bi-clock me-1"></i><?= e(gr_time($ev['start_datetime'])) ?>–<?= e(gr_time($ev['end_datetime'])) ?>
          <?php if ($ev['location_name']): ?>· <i class="bi bi-geo-alt me-1"></i><?= e($ev['location_name']) ?><?php endif; ?>
          · <i class="bi bi-people me-1"></i><?= (int) $ev['approved_people'] ?> εγκεκριμένα άτομα
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url('/team/live/' . $ev['id'])) ?>" class="btn btn-danger fw-bold">
          <i class="bi bi-broadcast me-1"></i>Live Mode
        </a>
        <a href="<?= e(url('/team/operations/events/' . $ev['id'])) ?>" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-list-ul me-1"></i>Λεπτομέρειες
        </a>
        <?php if (!empty($ev['field_token'])): ?>
        <a href="<?= e(url('/f/' . $ev['field_token'])) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
          <i class="bi bi-person-badge me-1"></i>Field Hub
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Stat cards ────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="ds-card g-rose h-100">
      <div class="ds-icon"><i class="bi bi-broadcast"></i></div>
      <div class="ds-val count-up" data-target="<?= count($todayOperational) ?>"><?= count($todayOperational) ?></div>
      <div class="ds-lbl">Δράσεις σε Εξέλιξη</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-green h-100">
      <div class="ds-icon"><i class="bi bi-check2-circle"></i></div>
      <div class="ds-val count-up" data-target="<?= $completedThisYear ?>"><?= $completedThisYear ?></div>
      <div class="ds-lbl">Ολοκληρωμένες <?= $year ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-amber h-100">
      <div class="ds-icon"><i class="bi bi-clock-history"></i></div>
      <div class="ds-val"><?= number_format((float)($stats['volunteer_hours'] ?? 0), 1, ',', '.') ?></div>
      <div class="ds-lbl">Ώρες Εθελοντισμού <?= $year ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-blue h-100">
      <div class="ds-icon"><i class="bi bi-people-fill"></i></div>
      <div class="ds-val count-up" data-target="<?= $activeMemberCount ?>"><?= $activeMemberCount ?></div>
      <div class="ds-lbl">Ενεργά Μέλη</div>
      <div class="ds-sub"><?= $memberCount ?> σύνολο</div>
    </div>
  </div>
</div>

<!-- ── Row 2: Quick actions + Inbox ─────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:1rem">
      <div class="card-body p-4">
        <div class="section-hd">Γρήγορες Ενέργειες</div>
        <div class="d-grid gap-2">
          <a href="<?= e(url('/team/events')) ?>" class="btn btn-primary text-start">
            <i class="bi bi-calendar-event me-2"></i> Δράσεις
            <?php if ($availableEvents > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $availableEvents ?> νέες</span>
            <?php endif; ?>
          </a>
          <a href="<?= e(url('/team/applications')) ?>" class="btn btn-outline-primary text-start">
            <i class="bi bi-inbox me-2"></i> Οι Δηλώσεις μας
            <?php if ($pendingApplications > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $pendingApplications ?> σε αναμονή</span>
            <?php endif; ?>
          </a>
          <a href="<?= e(url('/team/members')) ?>" class="btn btn-outline-secondary text-start">
            <i class="bi bi-people me-2"></i> Μέλη Ομάδας
          </a>
          <a href="<?= e(url('/team/statistics')) ?>" class="btn btn-outline-secondary text-start">
            <i class="bi bi-bar-chart me-2"></i> Στατιστικά
          </a>
          <a href="<?= e(url('/notifications')) ?>" class="btn btn-outline-secondary text-start">
            <i class="bi bi-bell me-2"></i> Ειδοποιήσεις
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:1rem">
      <div class="card-body p-4">
        <div class="section-hd">Κατάσταση Ομάδας</div>
        <div class="d-flex flex-column gap-3">
          <!-- Consistency score -->
          <?php $score = $stats['consistency_score'] ?? null; ?>
          <div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="small fw-semibold text-muted">Αξιοπιστία (<?= $year ?>)</span>
              <span class="small fw-bold <?= $score === null ? 'text-muted' : ($score >= 80 ? 'text-success' : ($score >= 50 ? 'text-warning' : 'text-danger')) ?>">
                <?= $score !== null ? $score . '%' : '—' ?>
              </span>
            </div>
            <div class="cov-bar-wrap"><div class="cov-bar-fill" style="width:<?= $score ?? 0 ?>%"></div></div>
          </div>
          <!-- Avg response time -->
          <?php $resp = $stats['avg_response_minutes'] ?? null; ?>
          <div class="d-flex justify-content-between">
            <span class="small text-muted">Μέσος χρόνος δήλωσης</span>
            <span class="small fw-bold"><?= $resp !== null ? ($resp < 60 ? $resp . ' λεπτά' : round($resp/60, 1) . ' ώρες') : '—' ?></span>
          </div>
          <!-- Members -->
          <div class="d-flex justify-content-between">
            <span class="small text-muted">Μέλη ομάδας</span>
            <span class="small fw-bold"><?= $activeMemberCount ?> ενεργά / <?= $memberCount ?> σύνολο</span>
          </div>
          <!-- Pending applications -->
          <?php if ($pendingApplications > 0): ?>
          <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#fff8e1;border:1px solid #ffe082">
            <span class="small fw-semibold text-warning-emphasis"><i class="bi bi-hourglass-split me-1"></i><?= $pendingApplications ?> δηλώσεις σε αναμονή</span>
            <a href="<?= e(url('/team/applications')) ?>" class="btn btn-sm btn-warning py-0 px-2">Προβολή</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Upcoming approved events ──────────────────────────────── -->
<?php
// Exclude events that are already showing in the operational section
$operationalIds = array_column($todayOperational, 'id');
$upcoming = array_filter($upcomingApproved, fn($ev) => !in_array($ev['id'], $operationalIds));
?>
<?php if ($upcoming): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:1rem">
  <div class="card-body p-4">
    <div class="section-hd">Εγκεκριμένες Επερχόμενες Δράσεις</div>
    <div class="row g-2 mt-1">
      <?php foreach ($upcoming as $ev): ?>
      <div class="col-md-6 col-lg-4">
        <div class="border rounded-3 p-3 h-100" style="background:#f8fafc">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <span class="fw-semibold small"><?= e($ev['title']) ?></span>
            <?= status_badge($ev['status']) ?>
          </div>
          <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= e(gr_date($ev['start_datetime'])) ?></div>
          <div class="text-muted small"><i class="bi bi-clock me-1"></i><?= e(gr_time($ev['start_datetime'])) ?>–<?= e(gr_time($ev['end_datetime'])) ?></div>
          <?php if ($ev['location_name']): ?><div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e($ev['location_name']) ?></div><?php endif; ?>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="badge bg-light border text-dark" style="font-size:.65rem"><i class="bi bi-people me-1"></i><?= (int) $ev['approved_people'] ?> άτομα</span>
            <a class="btn btn-sm btn-outline-primary py-0 px-2" href="<?= e(url('/team/events/' . $ev['id'])) ?>">Προβολή</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php elseif (!$todayOperational): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:1rem">
  <div class="card-body p-4 text-center text-muted">
    <i class="bi bi-calendar3 fs-2 mb-2 d-block opacity-50"></i>
    <div class="fw-semibold">Δεν υπάρχουν εγκεκριμένες επερχόμενες δράσεις</div>
    <?php if ($availableEvents > 0): ?>
    <div class="small mt-1">Υπάρχουν <strong><?= $availableEvents ?></strong> δράσεις διαθέσιμες για δήλωση.</div>
    <a href="<?= e(url('/team/events')) ?>" class="btn btn-sm btn-primary mt-2">Δείτε τις δράσεις</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Recent completed ───────────────────────────────────────── -->
<?php if ($recentCompleted): ?>
<div class="card border-0 shadow-sm" style="border-radius:1rem">
  <div class="card-body p-4">
    <div class="section-hd">Πρόσφατη Δραστηριότητα</div>
    <div class="mt-2">
      <?php foreach ($recentCompleted as $ev): ?>
      <div class="d-flex align-items-center gap-3 border-bottom py-2">
        <div class="flex-shrink-0 text-center" style="min-width:44px">
          <div class="fw-bold" style="font-size:1.1rem;line-height:1;color:#0d9488"><?= date('d', strtotime($ev['start_datetime'])) ?></div>
          <div class="text-muted" style="font-size:.65rem;text-transform:uppercase"><?= $monthsShort[date('n', strtotime($ev['start_datetime'])) - 1] ?></div>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold small"><?= e($ev['title']) ?></div>
          <div class="text-muted" style="font-size:.7rem">
            <?= e(gr_time($ev['start_datetime'])) ?>–<?= e(gr_time($ev['end_datetime'])) ?>
            <?php if ($ev['location_name']): ?>· <?= e($ev['location_name']) ?><?php endif; ?>
          </div>
        </div>
        <div class="flex-shrink-0">
          <span class="badge bg-success-subtle text-success border border-success" style="font-size:.65rem">
            <i class="bi bi-check2 me-1"></i>Ολοκλ.
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.count-up').forEach(function(el) {
  var target = parseInt(el.dataset.target, 10);
  if (!target) return;
  var start = 0, duration = 700, step = target / (duration / 16);
  var tick = function() {
    start = Math.min(start + step, target);
    el.textContent = Math.round(start).toLocaleString('el-GR');
    if (start < target) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
});
</script>
