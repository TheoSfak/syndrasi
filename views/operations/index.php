<?php
$hasLive         = !empty($live);
$hasStartingSoon = !empty($startingSoon);
$hasConfirmed    = !empty($confirmed);
$terms = authority_context(current_municipality_id());
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h1 class="h3 mb-0"><i class="bi bi-broadcast me-2"></i><?= e(t('operations/index.001', 'Κέντρο Επιχειρήσεων')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('operations/index.025', 'Παρακολούθηση και έλεγχος')) ?> <?= e($eventPluralLc) ?> <?= e(t('operations/index.026', 'σε πραγματικό χρόνο.')) ?></p>
  </div>
  <a href="<?= e(url('/events')) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-calendar-event me-1"></i><?= e(t('operations/index.003', 'Όλες οι')) ?> <?= e($eventPluralLc) ?>
  </a>
</div>

<?php if (!$hasLive && !$hasStartingSoon): ?>
<div class="alert alert-info d-flex align-items-center gap-3 mb-4">
  <i class="bi bi-info-circle fs-4"></i>
  <div><?= e(t('operations/index.027', 'Δεν υπάρχουν ενεργές ή επικείμενες')) ?> <?= e($eventPluralLc) ?> <?= e(t('operations/index.028', 'αυτή τη στιγμή.')) ?>
    <a href="<?= e(url('/events')) ?>" class="alert-link"><?= e(t('operations/index.005', 'Δείτε όλες τις')) ?> <?= e($eventPluralLc) ?></a>.
  </div>
</div>
<?php endif; ?>

<?php if ($hasStartingSoon): ?>
<!-- ══ Starting within 60 min ════════════════════════════════════ -->
<div class="mb-2 d-flex align-items-center gap-2">
  <i class="bi bi-hourglass-split text-warning fs-5"></i>
  <span class="fw-bold" style="color:#f97316;font-size:.82rem;letter-spacing:.5px;text-transform:uppercase">
    <?= e(t('operations/index.006', 'Ξεκινά σύντομα —')) ?> <?= count($startingSoon) ?>
  </span>
  <span class="text-muted small"><?= e(t('operations/index.007', '· Θα ανοίξει αυτόματα στην ώρα έναρξης')) ?></span>
</div>
<div class="row g-3 mb-4">
  <?php foreach ($startingSoon as $ev): ?>
  <div class="col-lg-6">
    <div class="card h-100" style="border-left:4px solid #f97316 !important;border-radius:1rem !important;">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
          <div>
            <span class="badge" style="background:#f97316">
              <i class="bi bi-hourglass-split me-1"></i><?= e(t('operations/index.029', 'σε')) ?> <?= (int)$ev['mins_until'] ?> <?= e(t('operations/index.030', 'λεπτά')) ?>
            </span>
            <h5 class="fw-bold mb-0 mt-2"><?= e($ev['title']) ?></h5>
            <div class="text-muted small mt-1">
              <i class="bi bi-clock me-1"></i><?= e(gr_time($ev['start_datetime'])) ?> – <?= e(gr_time($ev['end_datetime'])) ?>
              <?php if ($ev['location_name']): ?>
                &nbsp;·&nbsp;<i class="bi bi-geo-alt me-1"></i><?= e($ev['location_name']) ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-end" style="font-size:.78rem;white-space:nowrap">
            <span class="fw-bold" style="font-size:1.1rem;color:#f97316"><?= (int)$ev['teams_approved'] ?></span><br>
            <span class="text-muted"><?= e(t('operations/index.009', 'εγκεκρ. ομάδες')) ?></span>
          </div>
        </div>
        <div class="d-flex gap-2 mt-3 flex-wrap">
          <!-- Early-start override -->
          <form method="post" action="<?= e(url('/events/' . $ev['id'] . '/activate')) ?>"
                onsubmit="return confirm(<?= e(json_encode('Πρόωρη έναρξη ' . $eventSingularLc . ' πριν την προγραμματισμένη ώρα;', JSON_UNESCAPED_UNICODE)) ?>)"
                class="flex-grow-1">
            <?= csrf_field() ?>
            <button class="btn btn-outline-warning w-100 fw-semibold btn-sm">
              <i class="bi bi-skip-start-fill me-1"></i><?= e(t('operations/index.010', 'Πρόωρη έναρξη')) ?>
            </button>
          </form>
          <a href="<?= e(url('/events/' . $ev['id'])) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($hasLive): ?>
<!-- ══ LIVE (auto by time OR manually activated) ════════════════ -->
<div class="mb-2 d-flex align-items-center gap-2">
  <span class="live-dot"></span>
  <span class="fw-bold text-danger" style="font-size:.85rem;letter-spacing:.6px;text-transform:uppercase">
    <?= e(t('operations/index.011', 'Ενεργές τώρα —')) ?> <?= count($live) ?>
  </span>
</div>
<div class="row g-3 mb-4">
  <?php foreach ($live as $ev): ?>
  <?php
    $isManual = ($ev['status'] === 'active');
    // Is this past scheduled end? (manual override to run late)
    $isPastEnd = strtotime($ev['end_datetime']) < time();
  ?>
  <div class="col-lg-6">
    <div class="card command-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
          <div>
            <?php if ($isManual && strtotime($ev['start_datetime']) > time()): ?>
              <!-- Manually started early -->
              <span class="badge text-bg-warning mb-1">
                <i class="bi bi-skip-start-fill me-1"></i><?= e(t('operations/index.012', 'Πρόωρη Έναρξη')) ?>
              </span>
            <?php elseif ($isManual && $isPastEnd): ?>
              <!-- Manually kept open past end time -->
              <span class="badge text-bg-secondary mb-1">
                <i class="bi bi-clock-history me-1"></i><?= e(t('operations/index.013', 'Παράταση')) ?>
              </span>
            <?php else: ?>
              <!-- Normal auto-live -->
              <span class="badge text-bg-danger mb-1">
                <span class="live-dot-sm"></span>LIVE
              </span>
            <?php endif; ?>
            <h5 class="fw-bold mb-0 mt-1"><?= e($ev['title']) ?></h5>
            <div class="text-muted small mt-1">
              <i class="bi bi-clock me-1"></i><?= e(gr_time($ev['start_datetime'])) ?> – <?= e(gr_time($ev['end_datetime'])) ?>
              <?php if ($ev['location_name']): ?>
                &nbsp;·&nbsp;<i class="bi bi-geo-alt me-1"></i><?= e($ev['location_name']) ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="d-flex gap-3 mb-3 flex-wrap">
          <div class="cmd-stat">
            <div class="cmd-stat-val text-success"><?= (int)$ev['teams_present'] ?>/<?= (int)$ev['teams_approved'] ?></div>
            <div class="cmd-stat-lbl"><?= e(t('operations/index.014', 'Ομάδες παρούσες')) ?></div>
          </div>
          <?php if ((int)$ev['open_shortages'] > 0): ?>
          <div class="cmd-stat">
            <div class="cmd-stat-val text-danger"><?= (int)$ev['open_shortages'] ?></div>
            <div class="cmd-stat-lbl"><?= e(t('operations/index.015', 'Ελλείψεις')) ?></div>
          </div>
          <?php endif; ?>
          <div class="cmd-stat">
            <div class="cmd-stat-val text-muted" style="font-size:1rem"><?= e(gr_date($ev['start_datetime'])) ?></div>
            <div class="cmd-stat-lbl"><?= e(t('operations/index.016', 'Ημερομηνία')) ?></div>
          </div>
        </div>

        <!-- Primary action -->
        <a href="<?= e(url('/operations/events/' . $ev['id'])) ?>"
           class="btn btn-danger w-100 fw-bold mb-2">
          <i class="bi bi-broadcast me-2"></i><?= e(t('operations/index.017', 'Άνοιγμα Επιχειρησιακής Σελίδας')) ?>
        </a>

        <!-- Secondary: close (manual). Goes to 'closed' → reconciliation. -->
        <form method="post" action="<?= e(url('/events/' . $ev['id'] . '/close')) ?>"
              onsubmit="return confirm(<?= e(json_encode('Κλείσιμο ' . $eventSingularLc . ' τώρα;', JSON_UNESCAPED_UNICODE)) ?>)">
          <?= csrf_field() ?>
          <button class="btn btn-outline-secondary btn-sm w-100">
            <i class="bi bi-door-closed me-1"></i><?= e(t('operations/index.018', 'Κλείσιμο')) ?> <?= e($eventSingularLc) ?>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($hasConfirmed): ?>
<!-- ══ Upcoming (> 60 min) ══════════════════════════════════════ -->
<div class="card shadow-sm">
  <div class="card-header fw-semibold">
    <i class="bi bi-calendar-check me-1 text-primary"></i>
    <?= e(t('operations/index.031', 'Επερχόμενες επιβεβαιωμένες')) ?> <?= e($eventPluralLc) ?> (<?= count($confirmed) ?>)
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th><?= e($eventSingular) ?></th>
          <th><?= e(t('operations/index.020', 'Κατηγορία')) ?></th>
          <th><?= e(t('operations/index.021', 'Έναρξη')) ?></th>
          <th><?= e(t('operations/index.022', 'Κατάσταση')) ?></th>
          <th class="text-center"><?= e(t('operations/index.023', 'Εγκεκρ.')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($confirmed as $ev): ?>
        <tr>
          <td class="fw-semibold"><?= e($ev['title']) ?></td>
          <td class="text-muted small"><?= e($ev['category_name'] ?: '—') ?></td>
          <td class="small text-nowrap"><?= e(gr_datetime($ev['start_datetime'])) ?></td>
          <td><?= status_badge($ev['status']) ?></td>
          <td class="text-center"><?= (int)$ev['teams_approved'] ?></td>
          <td class="text-end d-flex gap-1 justify-content-end">
            <!-- Early-start override -->
            <form method="post" action="<?= e(url('/events/' . $ev['id'] . '/activate')) ?>"
                  onsubmit="return confirm(<?= e(json_encode('Πρόωρη έναρξη ' . $eventSingularLc . ';', JSON_UNESCAPED_UNICODE)) ?>)">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-warning" title="<?= e(t('operations/index.010', 'Πρόωρη έναρξη')) ?>">
                <i class="bi bi-skip-start-fill"></i>
              </button>
            </form>
            <a href="<?= e(url('/events/' . $ev['id'])) ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-eye me-1"></i><?= e(t('operations/index.024', 'Προβολή')) ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<style>
.live-dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;animation:livePulse 1.6s ease-in-out infinite;}
.live-dot-sm{display:inline-block;width:7px;height:7px;border-radius:50%;background:#fff;margin-right:4px;animation:livePulse 1.6s ease-in-out infinite;}
@keyframes livePulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.55);}60%{box-shadow:0 0 0 8px rgba(239,68,68,0);}}
.command-card{border-left:4px solid #ef4444 !important;transition:transform .2s,box-shadow .2s;}
.command-card:hover{transform:translateY(-4px);}
.cmd-stat{text-align:center;}
.cmd-stat-val{font-size:1.3rem;font-weight:800;line-height:1.1;}
.cmd-stat-lbl{font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;}
</style>
