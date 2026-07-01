<?php
/* ── Advanced Analytics & Trends ─────────────────────────────────────────── */
$thisYear = (int) date('Y');
$terms = authority_context(current_municipality_id());
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';

$labels = array_map('strval', $years);
$evSeries   = []; $hrSeries = []; $ptSeries = []; $rsSeries = [];
foreach ($years as $y) {
    $r = $yearly[$y] ?? ['events'=>0,'participations'=>0,'hours'=>0,'avg_resp'=>null];
    $evSeries[] = (int) $r['events'];
    $hrSeries[] = (float) $r['hours'];
    $ptSeries[] = (int) $r['participations'];
    $rsSeries[] = $r['avg_resp'] !== null ? (int) round((float) $r['avg_resp']) : null;
}

$catLabels = array_map(fn($c) => $c['category'], $byCategory);
$catEvents = array_map(fn($c) => (int) $c['events'], $byCategory);
$catHours  = array_map(fn($c) => round((float) $c['hours'], 1), $byCategory);

$delta = function ($cur, $prev) {
    $cur = (float) $cur; $prev = (float) $prev;
    if ($prev == 0.0) {
        return $cur > 0 ? ['+∞', 'up'] : ['—', 'flat'];
    }
    $d = round(($cur - $prev) / $prev * 100);
    return [($d >= 0 ? '+' : '') . $d . '%', $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat')];
};

$mLabels = ['Ιαν','Φεβ','Μαρ','Απρ','Μάι','Ιουν','Ιουλ','Αυγ','Σεπ','Οκτ','Νοε','Δεκ'];

$kpis = [
    [$eventPlural,     (int) $cur['events'],        (int) $prev['events'],        'bi-calendar-event', false],
    ['Συμμετοχές',     (int) $cur['participations'], (int) $prev['participations'],'bi-people',         false],
    ['Εθελοντικές ώρες', round((float)$cur['hours'],1), round((float)$prev['hours'],1), 'bi-clock-history', false],
    ['Μέσος χρόνος ανταπόκρισης',
        $cur['avg_resp'] !== null ? (int) round((float)$cur['avg_resp']) : null,
        $prev['avg_resp'] !== null ? (int) round((float)$prev['avg_resp']) : null,
        'bi-stopwatch', true],
];
?>

<!-- Used as the "Τάσεις" tab inside the Statistics page (header/year-selector live there). -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <p class="text-muted small mb-0">Διαχρονική εικόνα <?= (int)$years[0] ?>–<?= (int)end($years) ?> · εστίαση έτους <?= (int)$focus ?>.</p>
  <div class="dropdown">
    <button class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
      <i class="bi bi-file-earmark-arrow-down me-1"></i>Εξαγωγή CSV
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li><a class="dropdown-item" href="<?= e(url('/analytics/export?type=yearly&year='.$focus)) ?>">Ετήσιες τάσεις</a></li>
      <li><a class="dropdown-item" href="<?= e(url('/analytics/export?type=category&year='.$focus)) ?>">Ανά κατηγορία</a></li>
      <li><a class="dropdown-item" href="<?= e(url('/analytics/export?type=teams&year='.$focus)) ?>">Ανά ομάδα</a></li>
    </ul>
  </div>
</div>

<!-- KPI cards (focus year vs previous) -->
<div class="row g-3 mb-4">
  <?php foreach ($kpis as $k):
    [$lbl, $cv, $pv, $icon, $lowerBetter] = $k;
    $display = $cv === null ? '—' : $cv;
    [$dTxt, $dDir] = $delta($cv ?? 0, $pv ?? 0);
    // For response time, lower is better → invert color meaning
    $good = $lowerBetter ? ($dDir === 'down') : ($dDir === 'up');
    $dClass = $dDir === 'flat' ? 'text-muted' : ($good ? 'text-success' : 'text-danger');
    $dIcon  = $dDir === 'up' ? 'bi-arrow-up-right' : ($dDir === 'down' ? 'bi-arrow-down-right' : 'bi-dash');
  ?>
  <div class="col-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div class="text-muted small text-uppercase" style="letter-spacing:.5px;font-size:.68rem"><?= e($lbl) ?></div>
          <i class="bi <?= e($icon) ?> text-primary fs-5"></i>
        </div>
        <div class="fw-bold mt-1" style="font-size:1.9rem;line-height:1"><?= e((string)$display) ?><?= $lowerBetter && $cv !== null ? '<span class="fs-6 text-muted"> λ</span>' : '' ?></div>
        <div class="small mt-1 <?= $dClass ?>">
          <i class="bi <?= $dIcon ?>"></i> <?= e($dTxt) ?>
          <span class="text-muted">vs <?= (int)$focus - 1 ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <!-- Year-over-year trend -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="bi bi-bar-chart-line me-1 text-primary"></i>Διαχρονική τάση</div>
      <div class="card-body"><canvas id="yearChart" height="150"></canvas></div>
    </div>
  </div>
  <!-- Category breakdown -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="bi bi-pie-chart me-1 text-primary"></i>Ανά κατηγορία (<?= (int)$years[0] ?>–<?= (int)end($years) ?>)</div>
      <div class="card-body">
        <?php if (empty($byCategory)): ?>
          <div class="text-muted small text-center py-4">Δεν υπάρχουν ολοκληρωμένες <?= e($eventPluralLc) ?> στο διάστημα.</div>
        <?php else: ?>
          <canvas id="catChart" height="200"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Monthly compare -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="bi bi-calendar3 me-1 text-primary"></i><?= e($eventPlural) ?> ανά μήνα · <?= (int)$focus ?> vs <?= (int)$focus - 1 ?></div>
      <div class="card-body"><canvas id="monthChart" height="150"></canvas></div>
    </div>
  </div>
  <!-- Response time trend -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="bi bi-stopwatch me-1 text-primary"></i>Χρόνος ανταπόκρισης (λεπτά)</div>
      <div class="card-body"><canvas id="respChart" height="200"></canvas></div>
    </div>
  </div>
</div>

<!-- Team trends table -->
<div class="card mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-trophy me-1 text-primary"></i>Κορυφαίες ομάδες — εθελοντικές ώρες ανά έτος</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Ομάδα</th>
          <?php foreach ($years as $y): ?><th class="text-center"><?= (int)$y ?></th><?php endforeach; ?>
          <th class="text-end">Σύνολο ωρών</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($teamTrends)): ?>
          <tr><td colspan="<?= count($years) + 2 ?>" class="text-center text-muted py-3">Δεν υπάρχουν δεδομένα.</td></tr>
        <?php else: foreach ($teamTrends as $t): ?>
          <tr>
            <td class="fw-semibold"><?= e($t['team_name']) ?></td>
            <?php foreach ($years as $y): $h = $t['by_year'][$y] ?? 0; ?>
              <td class="text-center <?= $h > 0 ? '' : 'text-muted' ?>"><?= $h > 0 ? e((string)round($h,1)) : '·' ?></td>
            <?php endforeach; ?>
            <td class="text-end fw-bold"><?= e((string)round($t['total_hours'],1)) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;

  var YEARS   = <?= json_encode($labels,     JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var EVENTS  = <?= json_encode($evSeries,   JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var HOURS   = <?= json_encode($hrSeries,   JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var PARTS   = <?= json_encode($ptSeries,   JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var RESP    = <?= json_encode($rsSeries,   JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var MLABELS = <?= json_encode($mLabels,    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var MCUR    = <?= json_encode($monthlyCur, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var MPREV   = <?= json_encode($monthlyPrev, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var CATL    = <?= json_encode($catLabels,  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var CATE    = <?= json_encode($catEvents,  JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var CY      = <?= (int)$focus ?>;

  var teal='#0d9488', tealL='rgba(13,148,136,.15)', amber='#f59e0b', slate='#64748b', blue='#3b82f6';

  new Chart(document.getElementById('yearChart'), {
    data: {
      labels: YEARS,
      datasets: [
        { type:'bar',  label:<?= json_encode($eventPlural, JSON_UNESCAPED_UNICODE) ?>, data:EVENTS, backgroundColor:'rgba(59,130,246,.5)', yAxisID:'y', order:2 },
        { type:'line', label:'Εθελοντικές ώρες', data:HOURS, borderColor:teal, backgroundColor:tealL, tension:.3, fill:true, yAxisID:'y1', order:1 },
        { type:'line', label:'Συμμετοχές', data:PARTS, borderColor:amber, tension:.3, yAxisID:'y1', order:0 }
      ]
    },
    options: {
      responsive:true, interaction:{mode:'index',intersect:false},
      scales:{
        y:{ position:'left', beginAtZero:true, title:{display:true,text:<?= json_encode($eventPlural, JSON_UNESCAPED_UNICODE) ?>} },
        y1:{ position:'right', beginAtZero:true, grid:{drawOnChartArea:false}, title:{display:true,text:'Ώρες / Συμμετοχές'} }
      }
    }
  });

  if (document.getElementById('catChart') && CATL.length) {
    new Chart(document.getElementById('catChart'), {
      type:'bar',
      data:{ labels:CATL, datasets:[{ label:<?= json_encode($eventPlural, JSON_UNESCAPED_UNICODE) ?>, data:CATE, backgroundColor:'rgba(13,148,136,.6)' }] },
      options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,ticks:{precision:0}}} }
    });
  }

  new Chart(document.getElementById('monthChart'), {
    type:'bar',
    data:{ labels:MLABELS, datasets:[
      { label:String(CY),   data:MCUR,  backgroundColor:teal },
      { label:String(CY-1), data:MPREV, backgroundColor:'rgba(100,116,139,.5)' }
    ]},
    options:{ responsive:true, scales:{y:{beginAtZero:true,ticks:{precision:0}}} }
  });

  new Chart(document.getElementById('respChart'), {
    type:'line',
    data:{ labels:YEARS, datasets:[{ label:'Λεπτά', data:RESP, borderColor:blue, backgroundColor:'rgba(59,130,246,.12)', tension:.3, fill:true, spanGaps:true }] },
    options:{ plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
  });
});
</script>
