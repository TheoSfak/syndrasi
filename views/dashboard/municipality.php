<?php
$hoursChange = $hoursLastMonth > 0
    ? round(($hoursThisMonth - $hoursLastMonth) / $hoursLastMonth * 100)
    : ($hoursThisMonth > 0 ? 100 : 0);
$monthLabels = json_encode(array_column($monthlyTrend, 'label'));
$monthCounts = json_encode(array_column($monthlyTrend, 'count'));
?>

<!-- Page header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h1 class="h3 mb-0">Πίνακας Ελέγχου</h1>
    <p class="text-muted small mb-0">Επισκόπηση δραστηριότητας για το <?= $year ?></p>
  </div>
  <?php if ($draftEvents > 0): ?>
  <div class="draft-alert d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
    <span class="fw-semibold small"><?= $draftEvents ?> πρόχειρες δράσεις</span>
    <a href="<?= e(url('/events/drafts')) ?>" class="btn btn-sm btn-warning ms-2">Προβολή</a>
  </div>
  <?php endif; ?>
</div>

<!-- ── Row 1: 8 stat cards ───────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="ds-card g-teal h-100">
      <div class="ds-icon"><i class="bi bi-calendar-check"></i></div>
      <div class="ds-val count-up" data-target="<?= $openEvents ?>"><?= $openEvents ?></div>
      <div class="ds-lbl">Ανοιχτές Δράσεις</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-blue h-100">
      <div class="ds-icon"><i class="bi bi-inbox-fill"></i></div>
      <div class="ds-val count-up" data-target="<?= $pendingApplications ?>"><?= $pendingApplications ?></div>
      <div class="ds-lbl">Εκκρεμείς Δηλώσεις</div>
      <div class="ds-sub">Ποσοστό έγκρισης: <?= $approvalRate ?>%</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-purple h-100">
      <div class="ds-icon"><i class="bi bi-check2-circle"></i></div>
      <div class="ds-val count-up" data-target="<?= $confirmedEvents ?>"><?= $confirmedEvents ?></div>
      <div class="ds-lbl">Επιβεβαιωμένες</div>
      <div class="ds-sub"><?= $completedYear ?> ολοκληρώθηκαν φέτος</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-orange h-100">
      <div class="ds-icon"><i class="bi bi-people-fill"></i></div>
      <div class="ds-val count-up" data-target="<?= $activeTeams ?>"><?= $activeTeams ?></div>
      <div class="ds-lbl">Ενεργές Ομάδες</div>
      <div class="ds-sub"><?= $totalTeams ?> σύνολο</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-green h-100">
      <div class="ds-icon"><i class="bi bi-clock-history"></i></div>
      <div class="ds-val"><?= number_format($hoursThisMonth, 1, ',', '.') ?></div>
      <div class="ds-lbl">Ώρες Εθελοντισμού (μήνας)</div>
      <div class="ds-sub">
        <?php if ($hoursChange > 0): ?>
          <i class="bi bi-arrow-up-right"></i> +<?= $hoursChange ?>% vs προηγ. μήνα
        <?php elseif ($hoursChange < 0): ?>
          <i class="bi bi-arrow-down-right"></i> <?= $hoursChange ?>% vs προηγ. μήνα
        <?php else: ?>
          Ίδιο επίπεδο με προηγ. μήνα
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-amber h-100">
      <div class="ds-icon"><i class="bi bi-award-fill"></i></div>
      <div class="ds-val"><?= number_format((float)($overview['volunteer_hours'] ?? 0), 1, ',', '.') ?></div>
      <div class="ds-lbl">Συνολικές Ώρες Εθελοντισμού</div>
      <div class="ds-sub">Έτος <?= $year ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-rose h-100">
      <div class="ds-icon"><i class="bi bi-broadcast"></i></div>
      <div class="ds-val count-up" data-target="<?= count($activeToday) ?>"><?= count($activeToday) ?></div>
      <div class="ds-lbl">Δράσεις σε Εξέλιξη</div>
      <div class="ds-sub"><?= count($openShortages) ?> ανοιχτές ελλείψεις</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="ds-card g-slate h-100">
      <div class="ds-icon"><i class="bi bi-percent"></i></div>
      <div class="ds-val"><?= $approvalRate ?>%</div>
      <div class="ds-lbl">Ποσοστό Έγκρισης</div>
      <div class="ds-sub">
        <div class="cov-bar-wrap mt-1"><div class="cov-bar-fill" style="width:<?= $approvalRate ?>%"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ── Row 2: Chart + Status breakdown ──────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100" style="border-radius:1rem;">
      <div class="card-body p-4">
        <div class="section-hd">Μηνιαία Τάση Δράσεων (τελευταίοι 6 μήνες)</div>
        <canvas id="monthlyChart" height="80"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100" style="border-radius:1rem;">
      <div class="card-body p-4">
        <div class="section-hd">Κατανομή Δράσεων <?= $year ?></div>
        <div class="d-flex flex-column gap-2 mt-2">
          <?php
          $stColors = [
            'draft'     => ['#64748b','Πρόχειρες'],
            'open'      => ['#2563eb','Ανοιχτές'],
            'review'    => ['#d97706','Σε Αξιολόγηση'],
            'confirmed' => ['#7c3aed','Επιβεβαιωμένες'],
            'active'    => ['#e11d48','Σε Εξέλιξη'],
            'completed' => ['#16a34a','Ολοκληρωμένες'],
            'cancelled' => ['#94a3b8','Ακυρωμένες'],
          ];
          $totalSt = array_sum($byStatus);
          foreach ($byStatus as $st => $cnt):
            if (!$cnt) continue;
            [$color,$label] = $stColors[$st];
            $pct = $totalSt > 0 ? round($cnt/$totalSt*100) : 0;
          ?>
          <div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="small fw-semibold" style="color:<?= e($color) ?>"><?= e($label) ?></span>
              <span class="small text-muted"><?= $cnt ?> (<?= $pct ?>%)</span>
            </div>
            <div class="cov-bar-wrap">
              <div style="width:<?= $pct ?>%;height:100%;border-radius:4px;background:<?= e($color) ?>;"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$totalSt): ?>
            <p class="text-muted small">Δεν υπάρχουν δράσεις για το <?= $year ?>.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Row 3: Top teams + Active / Shortages ─────────────── -->
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:1rem;">
      <div class="card-body p-4">
        <div class="section-hd">Top 5 Ομάδες <?= $year ?> — Ώρες Εθελοντισμού</div>
        <?php if ($topTeams): ?>
        <table class="table top-teams-tbl table-borderless mb-0">
          <thead><tr><th>#</th><th>Ομάδα</th><th class="text-end">Δράσεις</th><th class="text-end">Ώρες</th></tr></thead>
          <tbody>
          <?php foreach ($topTeams as $i => $t): ?>
            <tr>
              <td>
                <?php if ($i===0): ?><i class="bi bi-trophy-fill medal-1 fs-5"></i>
                <?php elseif($i===1): ?><i class="bi bi-trophy-fill medal-2 fs-5"></i>
                <?php elseif($i===2): ?><i class="bi bi-trophy-fill medal-3 fs-5"></i>
                <?php else: ?><span class="text-muted small"><?= $i+1 ?>.</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold small"><?= e($t['team_name']) ?></div>
                <div class="text-muted" style="font-size:.7rem"><?= e($t['team_type']) ?></div>
              </td>
              <td class="text-end small"><?= $t['events_count'] ?></td>
              <td class="text-end fw-bold" style="color:#0d9488"><?= number_format((float)$t['volunteer_hours'],1,',','.') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p class="text-muted small">Δεν υπάρχουν ολοκληρωμένες δράσεις φέτος.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <?php if ($activeToday): ?>
    <div class="card border-0 shadow-sm mb-3" style="border-radius:1rem;border-left:4px solid #e11d48 !important;">
      <div class="card-body p-4">
        <div class="section-hd" style="--c-primary:#e11d48">
          <span class="badge bg-danger me-1" style="font-size:.65rem;animation:pulse-dot 1.5s infinite;">LIVE</span>
          Δράσεις σε Εξέλιξη
        </div>
        <?php foreach ($activeToday as $a): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
          <div>
            <div class="fw-semibold small"><?= e($a['title']) ?></div>
            <div class="text-muted" style="font-size:.7rem"><i class="bi bi-clock me-1"></i><?= gr_time($a['start_datetime']) ?> – <?= gr_time($a['end_datetime']) ?></div>
          </div>
          <a href="<?= e(url('/operations/events/'.$a['id'])) ?>" class="btn btn-sm btn-danger">Άνοιγμα</a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($openShortages): ?>
    <div class="card border-0 shadow-sm" style="border-radius:1rem;">
      <div class="card-body p-4">
        <div class="section-hd" style="--c-primary:#f97316">Ανοιχτές Ελλείψεις</div>
        <?php foreach ($openShortages as $sr): ?>
        <?php $sev = $sr['severity'];
              $sevCls = match($sev){ 'critical'=>'danger','high'=>'warning','medium'=>'info',default=>'secondary'}; ?>
        <div class="d-flex align-items-center gap-2 border-bottom py-2">
          <span class="badge bg-<?= $sevCls ?>" style="font-size:.65rem"><?= strtoupper($sev) ?></span>
          <div class="flex-grow-1">
            <div class="small fw-semibold"><?= e($sr['title'] ?? $sr['description'] ?? 'Έλλειψη') ?></div>
            <div class="text-muted" style="font-size:.7rem"><?= e($sr['team_name']) ?> · <?= e($sr['event_title']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$activeToday && !$openShortages): ?>
    <div class="card border-0 shadow-sm h-100 d-flex align-items-center justify-content-center" style="border-radius:1rem;min-height:200px;">
      <div class="text-center text-muted p-4">
        <i class="bi bi-check-circle-fill fs-1 text-success mb-2 d-block"></i>
        <div class="fw-semibold">Όλα καλά!</div>
        <div class="small">Δεν υπάρχουν ενεργές δράσεις ή ανοιχτές ελλείψεις.</div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Row 4: Upcoming events ───────────────────────────────── -->
<?php if ($upcoming): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:1rem;">
  <div class="card-body p-4">
    <div class="section-hd">Επερχόμενες Δράσεις</div>
    <div class="row g-2">
      <?php foreach ($upcoming as $u): ?>
      <div class="col-md-6 col-lg-4">
        <div class="border rounded-3 p-3 h-100" style="background:#f8fafc;">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <span class="fw-semibold small"><?= e($u['title']) ?></span>
            <?= status_badge($u['status']) ?>
          </div>
          <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= gr_date($u['start_datetime']) ?></div>
          <div class="text-muted small"><i class="bi bi-clock me-1"></i><?= gr_time($u['start_datetime']) ?></div>
          <?php if ($u['category_name']): ?>
          <div class="mt-1"><span class="badge text-bg-light border" style="font-size:.65rem"><?= e($u['category_name']) ?></span></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Monthly bar chart
(function(){
  const ctx = document.getElementById('monthlyChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= $monthLabels ?>,
      datasets: [{
        label: 'Δράσεις',
        data: <?= $monthCounts ?>,
        backgroundColor: 'rgba(13,148,136,.75)',
        borderColor: '#0d9488',
        borderWidth: 1,
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 },
             grid: { color: 'rgba(0,0,0,.05)' } },
        x: { grid: { display: false } }
      }
    }
  });
})();

// Count-up animation
document.querySelectorAll('.count-up').forEach(el => {
  const target = parseInt(el.dataset.target, 10);
  if (!target) return;
  let start = 0, duration = 800, step = target / (duration / 16);
  const tick = () => {
    start = Math.min(start + step, target);
    el.textContent = Math.round(start).toLocaleString('el-GR');
    if (start < target) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
});
</script>
