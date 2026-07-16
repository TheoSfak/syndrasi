<?php
/**
 * SynDrasi — Event Calendar View
 * Month grid, colored pills by status, click → event page.
 */
$monthNames = [
    1 => 'Ιανουάριος', 2 => 'Φεβρουάριος', 3 => 'Μάρτιος',
    4 => 'Απρίλιος',   5 => 'Μάιος',        6 => 'Ιούνιος',
    7 => 'Ιούλιος',    8 => 'Αύγουστος',    9 => 'Σεπτέμβριος',
   10 => 'Οκτώβριος', 11 => 'Νοέμβριος',   12 => 'Δεκέμβριος',
];
$terms = $terms ?? authority_context();
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$eventSingularLc = mb_strtolower($terms['event_singular'] ?? 'Δράση', 'UTF-8');

/* Build per-day event index (key = "Y-m-d") */
$byDay = [];
foreach ($events as $ev) {
    // An event may span multiple days — add to every day it covers
    $start = new DateTime($ev['start_datetime']);
    $end   = new DateTime($ev['end_datetime']);
    $cur   = clone $start;
    while ($cur <= $end) {
        if ((int) $cur->format('n') === $month && (int) $cur->format('Y') === $year) {
            $byDay[$cur->format('Y-m-d')][] = $ev;
        }
        $cur->modify('+1 day');
    }
}

/* Calendar grid parameters */
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
// Day-of-week for the 1st (0=Sun … 6=Sat); shift to Mon=0 … Sun=6
$firstDow = (int) (new DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('w');
$firstDow = ($firstDow + 6) % 7; // Mon-first

$today = date('Y-m-d');

/* Status → CSS class + Greek label */
function calStatusClass(string $s): string {
    return match($s) {
        'draft'     => 'cal-draft',
        'open'      => 'cal-open',
        'review'    => 'cal-review',
        'confirmed' => 'cal-confirmed',
        'active'    => 'cal-active',
        'completed' => 'cal-completed',
        'closed'    => 'cal-closed',
        default     => 'cal-other',
    };
}

/* Encode events for JS (for tooltip / search) */
$evJson = json_encode(array_map(fn($e) => [
    'id'    => $e['id'],
    'title' => $e['title'],
    'status'=> $e['status'],
    'start' => $e['start_datetime'],
    'end'   => $e['end_datetime'],
    'url'   => url('/events/' . $e['id']),
], $events), JSON_UNESCAPED_UNICODE);
?>

<style>
/* ── Calendar layout ──────────────────────────────────────────────────── */
.cal-nav {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  margin-bottom: 16px;
}
.cal-nav h2 { font-size: 1.35rem; font-weight: 700; margin: 0; flex: 1; }
.cal-view-toggle .btn { font-size: 13px; }

.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  border-left: 1px solid var(--bs-border-color);
  border-top:  1px solid var(--bs-border-color);
  border-radius: 12px;
  overflow: hidden;
  background: var(--bs-body-bg);
}
.cal-dow-header {
  background: var(--bs-tertiary-bg);
  border-right: 1px solid var(--bs-border-color);
  border-bottom: 1px solid var(--bs-border-color);
  text-align: center;
  font-size: 11px; font-weight: 700;
  padding: 8px 4px;
  color: var(--bs-secondary-color);
  text-transform: uppercase; letter-spacing: .05em;
}
.cal-cell {
  border-right: 1px solid var(--bs-border-color);
  border-bottom: 1px solid var(--bs-border-color);
  min-height: 110px;
  padding: 6px;
  position: relative;
  vertical-align: top;
  transition: background .15s;
}
.cal-cell:hover { background: var(--bs-tertiary-bg); }
.cal-cell.today { background: color-mix(in srgb, var(--bs-primary) 8%, transparent); }
.cal-cell.today .cal-day-num { color: var(--bs-primary); font-weight: 900; }
.cal-cell.other-month { background: var(--bs-tertiary-bg); opacity: .55; }
.cal-cell.has-events { cursor: default; }

.cal-day-num {
  font-size: 13px; font-weight: 600;
  color: var(--bs-body-color);
  margin-bottom: 4px; line-height: 1;
}
.cal-pills { display: flex; flex-direction: column; gap: 2px; }
.cal-pill {
  display: block;
  font-size: 11px; font-weight: 600; line-height: 1.2;
  padding: 3px 6px; border-radius: 4px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  text-decoration: none; color: #fff;
  cursor: pointer;
  transition: opacity .12s, transform .1s;
}
.cal-pill:hover { opacity: .85; transform: translateY(-1px); color: #fff; }

/* Status colors */
.cal-draft     { background: #6c757d; }
.cal-open      { background: #0d6efd; }
.cal-review    { background: #6f42c1; }
.cal-confirmed { background: #0dcaf0; color: #000 !important; }
.cal-active    { background: #198754; }
.cal-completed { background: #20c997; color: #000 !important; }
.cal-closed    { background: #adb5bd; color: #000 !important; }
.cal-other     { background: #495057; }

/* Overflow indicator */
.cal-more {
  font-size: 11px; color: var(--bs-secondary-color); font-weight: 600;
  padding: 2px 4px; cursor: pointer;
}
.cal-more:hover { text-decoration: underline; }

/* ── Legend ──────────────────────────────────────────────────────────── */
.cal-legend {
  display: flex; flex-wrap: wrap; gap: 8px;
  margin-top: 12px;
}
.cal-legend-item {
  display: flex; align-items: center; gap: 5px;
  font-size: 12px; color: var(--bs-secondary-color);
}
.cal-legend-dot {
  width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
}

/* ── Week view ───────────────────────────────────────────────────────── */
.week-grid { display: flex; flex-direction: column; gap: 2px; }
.week-row {
  display: grid;
  grid-template-columns: 80px 1fr;
  min-height: 52px;
  border: 1px solid var(--bs-border-color);
  border-radius: 8px; overflow: hidden;
}
.week-time {
  background: var(--bs-tertiary-bg);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; color: var(--bs-secondary-color);
  padding: 8px;
  border-right: 1px solid var(--bs-border-color);
}
.week-events { display: flex; flex-direction: column; gap: 3px; padding: 6px; }

/* ── Mobile compact ─────────────────────────────────────────────────── */
@media (max-width: 576px) {
  .cal-cell { min-height: 70px; padding: 3px; }
  .cal-day-num { font-size: 11px; }
  .cal-pill { font-size: 10px; padding: 2px 4px; }
  .cal-dow-header { font-size: 9px; padding: 5px 2px; }
}
</style>

<!-- ── Header row ──────────────────────────────────────────────────────── -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0"><?= e(t('events/calendar.001', 'Ημερολόγιο')) ?> <?= e($eventPlural) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('events/calendar.011', 'Κλικ σε')) ?> <?= e($eventSingularLc) ?> <?= e(t('events/calendar.012', 'για λεπτομέρειες.')) ?></p>
  </div>
  <?php if (current_role() === 'municipality_admin'): ?>
  <a href="<?= e(url('/events/create')) ?>" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i><?= e($terms['event_new'] ?? 'Νέα Δράση') ?>
  </a>
  <?php endif; ?>
</div>

<!-- ── View switch tabs ────────────────────────────────────────────────── -->
<ul class="nav nav-pills mb-3 gap-1">
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events')) ?>"><i class="bi bi-list-ul me-1"></i><?= e(t('events/calendar.003', 'Ενεργές')) ?></a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= e(url('/events/calendar')) ?>"><i class="bi bi-calendar3 me-1"></i><?= e(t('events/calendar.001', 'Ημερολόγιο')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events/drafts')) ?>"><i class="bi bi-file-earmark me-1"></i><?= e(t('events/calendar.004', 'Πρόχειρα')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="<?= e(url('/events/completed')) ?>"><i class="bi bi-archive me-1"></i><?= e(t('events/calendar.005', 'Αρχείο')) ?></a></li>
</ul>

<!-- ── Month navigation ────────────────────────────────────────────────── -->
<div class="cal-nav">
  <a href="<?= e(url('/events/calendar?m=' . $prevMonth)) ?>" class="btn btn-outline-secondary">
    <i class="bi bi-chevron-left"></i>
  </a>

  <h2 class="text-center">
    <?= e($monthNames[$month]) ?> <?= $year ?>
  </h2>

  <a href="<?= e(url('/events/calendar?m=' . $nextMonth)) ?>" class="btn btn-outline-secondary">
    <i class="bi bi-chevron-right"></i>
  </a>

  <a href="<?= e(url('/events/calendar?m=' . date('Y-m'))) ?>"
     class="btn btn-outline-primary btn-sm ms-auto"
     <?= date('Y-m') === sprintf('%04d-%02d', $year, $month) ? 'disabled' : '' ?>>
    <?= e(t('events/calendar.006', 'Σήμερα')) ?>
  </a>

  <!-- View toggle: month / week -->
  <div class="btn-group btn-group-sm cal-view-toggle" role="group">
    <button type="button" class="btn btn-outline-secondary active" id="btnMonthView">
      <i class="bi bi-calendar3 me-1"></i><?= e(t('events/calendar.007', 'Μήνας')) ?>
    </button>
    <button type="button" class="btn btn-outline-secondary" id="btnWeekView">
      <i class="bi bi-calendar-week me-1"></i><?= e(t('events/calendar.008', 'Εβδομάδα')) ?>
    </button>
  </div>
</div>

<!-- ── Month grid ──────────────────────────────────────────────────────── -->
<div id="monthView">
  <div class="cal-grid shadow-sm">
    <!-- Day of week headers (Mon … Sun) -->
    <?php $dowLabels = ['Δευ', 'Τρι', 'Τετ', 'Πεμ', 'Παρ', 'Σαβ', 'Κυρ']; ?>
    <?php foreach ($dowLabels as $dl): ?>
      <div class="cal-dow-header"><?= $dl ?></div>
    <?php endforeach; ?>

    <!-- Leading empty cells for the first week -->
    <?php for ($i = 0; $i < $firstDow; $i++): ?>
      <div class="cal-cell other-month"></div>
    <?php endfor; ?>

    <!-- Day cells -->
    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
      <?php
        $dateKey  = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dayEvents = $byDay[$dateKey] ?? [];
        $isToday  = ($dateKey === $today);
        $maxShow  = 3; // max pills before "+X more"
        $classes  = 'cal-cell' . ($isToday ? ' today' : '') . ($dayEvents ? ' has-events' : '');
      ?>
      <div class="<?= $classes ?>" data-date="<?= e($dateKey) ?>">
        <div class="cal-day-num"><?= $d ?></div>
        <?php if ($dayEvents): ?>
          <div class="cal-pills">
            <?php $shown = 0; ?>
            <?php foreach ($dayEvents as $ev): ?>
              <?php if ($shown >= $maxShow): break; endif; ?>
              <a href="<?= e(url('/events/' . $ev['id'])) ?>"
                 class="cal-pill <?= calStatusClass($ev['status']) ?>"
                 title="<?= e($ev['title']) ?> · <?= e(greek_status($ev['status'])) ?> · <?= e(gr_time($ev['start_datetime'])) ?>">
                <?= e(mb_strimwidth($ev['title'], 0, 22, '…')) ?>
              </a>
              <?php $shown++; ?>
            <?php endforeach; ?>
            <?php $remaining = count($dayEvents) - $maxShow; ?>
            <?php if ($remaining > 0): ?>
              <span class="cal-more" onclick="showDayPopover('<?= e($dateKey) ?>', this)">
                +<?= $remaining ?> <?= e(t('events/calendar.013', 'ακόμη')) ?>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endfor; ?>

    <!-- Trailing empty cells to complete last row -->
    <?php
      $totalCells = $firstDow + $daysInMonth;
      $trailing   = (7 - ($totalCells % 7)) % 7;
      for ($i = 0; $i < $trailing; $i++):
    ?>
      <div class="cal-cell other-month"></div>
    <?php endfor; ?>
  </div>

  <!-- Legend -->
  <div class="cal-legend mt-3">
    <?php
    $legendItems = [
      'draft'     => ['#6c757d', 'Πρόχειρη'],
      'open'      => ['#0d6efd', 'Ανοιχτή'],
      'review'    => ['#6f42c1', 'Σε αξιολόγηση'],
      'confirmed' => ['#0dcaf0', 'Οριστικοποιημένη'],
      'active'    => ['#198754', 'Ενεργή'],
      'completed' => ['#20c997', 'Ολοκληρωμένη'],
    ];
    foreach ($legendItems as $st => [$color, $label]):
    ?>
      <div class="cal-legend-item">
        <div class="cal-legend-dot" style="background:<?= $color ?>"></div>
        <?= e($label) ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Week view ───────────────────────────────────────────────────────── -->
<div id="weekView" style="display:none">
  <div class="d-flex align-items-center gap-3 mb-3">
    <button class="btn btn-outline-secondary btn-sm" id="prevWeek"><i class="bi bi-chevron-left"></i></button>
    <span class="fw-semibold" id="weekLabel"></span>
    <button class="btn btn-outline-secondary btn-sm" id="nextWeek"><i class="bi bi-chevron-right"></i></button>
  </div>
  <div class="week-grid" id="weekGrid"></div>
</div>

<!-- ── Popover for +X more ─────────────────────────────────────────────── -->
<div class="modal fade" id="dayModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="dayModalTitle"><?= e($eventPlural) ?> <?= e(t('events/calendar.010', 'ημέρας')) ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="dayModalBody"></div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var BASE = window.baseUrl || '';
  var ALL_EVENTS = <?= $evJson ?>;

  /* Map events by date */
  var evsByDate = {};
  ALL_EVENTS.forEach(function (ev) {
    // Add to every day it covers
    var s = new Date(ev.start.replace(' ', 'T'));
    var e = new Date(ev.end.replace(' ', 'T'));
    for (var d = new Date(s.toDateString()); d <= e; d.setDate(d.getDate() + 1)) {
      var key = d.toISOString().slice(0, 10);
      if (!evsByDate[key]) evsByDate[key] = [];
      evsByDate[key].push(ev);
    }
  });

  /* ── Status colors (must match PHP) ──────────────────────────────── */
  var STATUS_COLORS = {
    'draft': '#6c757d', 'open': '#0d6efd', 'review': '#6f42c1',
    'confirmed': '#0dcaf0', 'active': '#198754',
    'completed': '#20c997', 'closed': '#adb5bd'
  };
  var STATUS_LABELS = {
    'draft': 'Πρόχειρη', 'open': 'Ανοιχτή', 'review': 'Σε αξιολόγηση',
    'confirmed': 'Οριστικοποιημένη', 'active': 'Ενεργή',
    'completed': 'Ολοκληρωμένη', 'closed': 'Κλειστή'
  };
  var GR_DAYS = ['Κυρ', 'Δευ', 'Τρι', 'Τετ', 'Πεμ', 'Παρ', 'Σαβ'];
  var GR_MONTHS = ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαι', 'Ιουν',
                   'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ'];

  function pad2(n) { return String(n).padStart(2, '0'); }
  function dateKey(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
  function timeStr(iso) { return iso.slice(11, 16); }

  function makePill(ev, small) {
    var a = document.createElement('a');
    a.href = BASE + '/events/' + ev.id;
    a.className = 'cal-pill';
    a.style.background = STATUS_COLORS[ev.status] || '#495057';
    if (ev.status === 'confirmed' || ev.status === 'completed' || ev.status === 'closed') {
      a.style.color = '#000';
    }
    a.title = ev.title + ' · ' + (STATUS_LABELS[ev.status] || ev.status) + ' · ' + timeStr(ev.start);
    a.textContent = small
      ? ev.title.slice(0, 22) + (ev.title.length > 22 ? '…' : '')
      : timeStr(ev.start) + ' ' + ev.title;
    return a;
  }

  /* ── Day popover ("+X more") ──────────────────────────────────────── */
  window.showDayPopover = function (dateStr, triggerEl) {
    var evs = evsByDate[dateStr] || [];
    var parts = dateStr.split('-');
    var d = new Date(+parts[0], +parts[1] - 1, +parts[2]);
    document.getElementById('dayModalTitle').textContent =
      GR_DAYS[(d.getDay())] + ', ' + d.getDate() + ' ' + GR_MONTHS[d.getMonth()];

    var body = document.getElementById('dayModalBody');
    body.innerHTML = '';
    evs.forEach(function (ev) {
      var wrap = document.createElement('div');
      wrap.style.marginBottom = '6px';
      wrap.appendChild(makePill(ev, false));
      body.appendChild(wrap);
    });
    new bootstrap.Modal(document.getElementById('dayModal')).show();
  };

  /* ── View toggle ──────────────────────────────────────────────────── */
  var monthView = document.getElementById('monthView');
  var weekView  = document.getElementById('weekView');
  var btnMonth  = document.getElementById('btnMonthView');
  var btnWeek   = document.getElementById('btnWeekView');

  btnMonth.addEventListener('click', function () {
    monthView.style.display = '';
    weekView.style.display  = 'none';
    btnMonth.classList.add('active');
    btnWeek.classList.remove('active');
  });
  btnWeek.addEventListener('click', function () {
    monthView.style.display = 'none';
    weekView.style.display  = '';
    btnWeek.classList.add('active');
    btnMonth.classList.remove('active');
    renderWeek(currentWeekStart);
  });

  /* ── Week view ────────────────────────────────────────────────────── */
  // Start on the Monday of the current month's week containing today
  var today = new Date();
  if (today.getMonth() + 1 !== <?= $month ?> || today.getFullYear() !== <?= $year ?>) {
    today = new Date(<?= $year ?>, <?= $month - 1 ?>, 1);
  }
  // Move to Monday of that week
  var dow = today.getDay(); // 0=Sun
  var diff = (dow === 0) ? -6 : 1 - dow;
  var currentWeekStart = new Date(today);
  currentWeekStart.setDate(today.getDate() + diff);

  function renderWeek(monday) {
    var grid = document.getElementById('weekGrid');
    grid.innerHTML = '';
    var days = [];
    for (var i = 0; i < 7; i++) {
      var d = new Date(monday);
      d.setDate(monday.getDate() + i);
      days.push(d);
    }
    // Label
    document.getElementById('weekLabel').textContent =
      days[0].getDate() + ' ' + GR_MONTHS[days[0].getMonth()] +
      ' – ' + days[6].getDate() + ' ' + GR_MONTHS[days[6].getMonth()] + ' ' + days[6].getFullYear();

    days.forEach(function (d) {
      var key = dateKey(d);
      var isToday = (key === new Date().toISOString().slice(0, 10));
      var row = document.createElement('div');
      row.className = 'week-row' + (isToday ? ' today' : '');
      if (isToday) row.style.borderColor = 'var(--bs-primary)';

      var timeDiv = document.createElement('div');
      timeDiv.className = 'week-time';
      timeDiv.innerHTML = '<div style="text-align:center">' +
        '<div style="font-size:10px;color:var(--bs-secondary-color)">' + GR_DAYS[d.getDay()] + '</div>' +
        '<div style="font-size:' + (isToday ? '18px;color:var(--bs-primary);font-weight:900' : '18px') + '">' + d.getDate() + '</div>' +
        '</div>';

      var evDiv = document.createElement('div');
      evDiv.className = 'week-events';
      var dayEvs = evsByDate[key] || [];
      if (dayEvs.length) {
        dayEvs.forEach(function (ev) { evDiv.appendChild(makePill(ev, false)); });
      } else {
        evDiv.innerHTML = '<span style="color:var(--bs-secondary-color);font-size:12px">—</span>';
      }

      row.appendChild(timeDiv);
      row.appendChild(evDiv);
      grid.appendChild(row);
    });
  }

  document.getElementById('prevWeek').addEventListener('click', function () {
    currentWeekStart.setDate(currentWeekStart.getDate() - 7);
    renderWeek(currentWeekStart);
  });
  document.getElementById('nextWeek').addEventListener('click', function () {
    currentWeekStart.setDate(currentWeekStart.getDate() + 7);
    renderWeek(currentWeekStart);
  });

  /* Load Bootstrap modal */
  if (typeof bootstrap === 'undefined') {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
    document.head.appendChild(s);
  }
})();
</script>
