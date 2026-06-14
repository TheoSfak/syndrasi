<?php
/* ── Operational Command Center ─────────────────────────────────────────── */
$eid       = (int) $event['id'];
$csrfToken = csrf_token();
$endTs     = strtotime($event['end_datetime']) * 1000;
$startTs   = strtotime($event['start_datetime']) * 1000;
$defLat    = (float)($mapDefLat  ?: $event['latitude']  ?: 35.3387);
$defLng    = (float)($mapDefLng  ?: $event['longitude'] ?: 25.1442);
$defZoom   = (int)($mapDefZoom  ?: 13);
?>
<style>
body.ops-dark { background:#0a0f1a !important; background-attachment:fixed !important; }
body.ops-dark .card            { background:rgba(255,255,255,.06)!important; border-color:rgba(255,255,255,.1)!important; color:#e2e8f0!important; }
body.ops-dark .card-header     { background:rgba(0,0,0,.3)!important; color:#e2e8f0!important; border-color:rgba(255,255,255,.1)!important; }
body.ops-dark .text-muted      { color:#94a3b8!important; }
body.ops-dark .form-control    { background:rgba(255,255,255,.08)!important; border-color:rgba(255,255,255,.15)!important; color:#fff!important; }
body.ops-dark .list-group-item { background:transparent!important; border-color:rgba(255,255,255,.1)!important; color:#e2e8f0!important; }
body.ops-dark main             { background:transparent!important; }

.ops-stat { display:flex;flex-direction:column;align-items:center;padding:.55rem 1rem;border-radius:14px;background:rgba(255,255,255,.13);backdrop-filter:blur(8px);min-width:100px;border:1px solid rgba(255,255,255,.2);transition:transform .2s; }
.ops-stat:hover { transform:translateY(-2px); }
.ops-stat .val { font-size:1.75rem;font-weight:900;line-height:1; }
.ops-stat .lbl { font-size:.65rem;text-transform:uppercase;letter-spacing:.8px;opacity:.8;margin-top:2px;white-space:nowrap; }
.ops-stat.has-alert { border-color:rgba(239,68,68,.6)!important;background:rgba(239,68,68,.2)!important; }
.ops-stat.has-alert .val { color:#fca5a5!important; }

.ops-countdown-wrap { background:rgba(0,0,0,.35);border:1.5px solid rgba(255,255,255,.18);border-radius:16px;padding:.55rem 1.4rem .45rem;text-align:center;min-width:180px;backdrop-filter:blur(8px); }
.ops-countdown { font-size:3rem;font-weight:900;letter-spacing:.06em;line-height:1;font-variant-numeric:tabular-nums;color:#fff;text-shadow:0 0 18px rgba(255,255,255,.5);transition:color .3s,text-shadow .3s; }
.ops-countdown.warning { color:#fbbf24;text-shadow:0 0 24px rgba(251,191,36,.7),0 0 8px rgba(251,191,36,.4);animation:cdFlash 1.2s ease-in-out infinite; }
.ops-countdown.urgent  { color:#ef4444;text-shadow:0 0 30px rgba(239,68,68,.9),0 0 10px rgba(239,68,68,.5);animation:cdFlash .55s ease-in-out infinite; }
@keyframes cdFlash { 0%,100%{opacity:1;letter-spacing:.06em;} 50%{opacity:.25;letter-spacing:.12em;} }

.ops-header { background:linear-gradient(135deg,#0b1120 0%,#0c3a35 60%,#0d4a44 100%);color:#fff;padding:1.1rem 1.4rem;border-radius:20px;margin-bottom:1rem;box-shadow:0 8px 40px rgba(0,0,0,.3); }

.team-card { border-radius:14px!important;border-left:4px solid #94a3b8!important;transition:transform .2s,box-shadow .2s; }
.team-card.s-present_full    { border-left-color:#22c55e!important; }
.team-card.s-present_partial { border-left-color:#f59e0b!important; }
.team-card.s-departed        { border-left-color:#94a3b8!important; }
.team-card.s-not_present     { border-left-color:#e2e8f0!important; }
.team-card:hover { transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,.15)!important; }

.ping-dot { width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:4px;flex-shrink:0; }
.ping-dot.fresh { background:#22c55e;animation:pp 1.8s infinite; }
.ping-dot.stale { background:#f59e0b; }
.ping-dot.old   { background:#ef4444; }
.ping-dot.none  { background:#cbd5e1; }
@keyframes pp { 0%{box-shadow:0 0 0 0 rgba(34,197,94,.6)} 70%{box-shadow:0 0 0 7px rgba(34,197,94,0)} 100%{box-shadow:0 0 0 0 rgba(34,197,94,0)} }

.sc { border-radius:12px!important;transition:all .2s; }
.sc.open         { border:1.5px solid #ef4444!important;background:rgba(239,68,68,.06)!important; }
.sc.acknowledged { border:1.5px solid #f59e0b!important;background:rgba(245,158,11,.06)!important; }
.sc.resolved     { opacity:.55; }

.af-item { display:flex;gap:.55rem;align-items:flex-start;padding:.42rem 0;border-bottom:1px solid rgba(0,0,0,.05);animation:afIn .3s ease both; }
@keyframes afIn { from{opacity:0;transform:translateX(-6px)} to{opacity:1;transform:none} }
.af-icon { width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.73rem; }
.af-time { font-size:.68rem;color:#94a3b8;white-space:nowrap; }

.map-wrap { position:relative; }
.map-wrap.fullscreen { position:fixed;inset:0;z-index:9999;background:#000; }
.map-wrap.fullscreen #operationalMap { height:100vh!important;border-radius:0!important; }
.map-overlay { position:absolute;top:10px;right:10px;z-index:1000;display:flex;gap:6px;opacity:0;transition:opacity .2s; }
.map-wrap:hover .map-overlay { opacity:1; }

.ldot { width:9px;height:9px;border-radius:50%;background:#22c55e;display:inline-block;animation:ldp 1.6s ease-in-out infinite; }
@keyframes ldp { 0%,100%{box-shadow:0 0 0 0 rgba(34,197,94,.6)} 50%{box-shadow:0 0 0 6px rgba(34,197,94,0)} }
@keyframes spin { to{transform:rotate(360deg)} }
.spin { animation:spin .6s linear infinite;display:inline-block; }
@keyframes sseFlash { 0%,100%{box-shadow:none} 35%{box-shadow:0 0 0 4px rgba(34,197,94,.65),0 0 16px rgba(34,197,94,.3)} }
.sse-flash { animation:sseFlash .75s ease; }

.shift-bar-wrap { position:relative;height:32px;border-radius:8px;overflow:hidden;background:rgba(255,255,255,.1); }
.shift-bar { position:absolute;top:4px;height:24px;border-radius:6px;display:flex;align-items:center;padding:0 8px;font-size:.68rem;font-weight:700;white-space:nowrap;overflow:hidden;transition:all .3s; }
.shift-bar.upcoming { background:rgba(148,163,184,.4);color:#e2e8f0; }
.shift-bar.active   { background:rgba(34,197,94,.7);color:#fff;box-shadow:0 0 10px rgba(34,197,94,.5); }
.shift-bar.ended    { background:rgba(100,116,139,.3);color:#94a3b8; }
.shift-pill { display:inline-flex;align-items:center;gap:6px;padding:4px 12px 4px 8px;border-radius:50px;font-size:.75rem;font-weight:600;margin-bottom:4px; }
.shift-pill.upcoming { background:rgba(148,163,184,.2);border:1px solid rgba(148,163,184,.4); }
.shift-pill.active   { background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.5);color:#4ade80; }
.shift-pill.ended    { background:rgba(100,116,139,.1);border:1px solid rgba(100,116,139,.3);opacity:.7; }
</style>

<!-- ═══════════ COMMAND HEADER ═══════════ -->
<div class="ops-header">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="ldot"></span>
        <h1 class="h4 mb-0 fw-bold text-white">Επιχειρησιακό Κέντρο</h1>
        <span class="badge bg-success" id="liveBadge">◉ LIVE SSE</span>
      </div>
      <div class="text-white fw-semibold fs-6"><?= e($event['title']) ?></div>
      <div class="small mt-1" style="opacity:.75">
        <i class="bi bi-clock me-1"></i><?= e(gr_datetime($event['start_datetime'])) ?> &rarr; <?= e(gr_datetime($event['end_datetime'])) ?>
        <?php if ($event['location_name']): ?>
          &nbsp;&middot;&nbsp;<i class="bi bi-geo-alt me-1"></i><?= e($event['location_name']) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <div class="ops-countdown-wrap text-white">
        <div class="ops-countdown" id="countdown">--</div>
        <div style="font-size:.6rem;opacity:.65;text-transform:uppercase;letter-spacing:1px;margin-top:.2rem" id="cdLabel">Υπολειπόμενος χρόνος</div>
      </div>
      <div class="d-flex flex-column gap-1">
        <button class="btn btn-sm btn-outline-light" id="btnDark" title="Mission Control Mode"><i class="bi bi-moon-stars-fill"></i></button>
        <button class="btn btn-sm btn-outline-light" id="btnFull" title="Fullscreen χάρτης"><i class="bi bi-fullscreen"></i></button>
        <button class="btn btn-sm btn-outline-light" id="btnRefresh"><i class="bi bi-arrow-clockwise"></i></button>
        <a class="btn btn-sm btn-outline-light" href="<?= e(url('/events/' . $eid)) ?>"><i class="bi bi-arrow-left"></i></a>
        <!-- Πρόωρη Λήξη -->
        <form method="post" action="<?= e(url('/events/' . $eid . '/complete')) ?>"
              onsubmit="return confirm('Πρόωρη λήξη δράσης;\nΗ δράση θα οριστεί ως ΟΛΟΚΛΗΡΩΜΕΝΗ τώρα.')">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <button class="btn btn-sm btn-danger w-100" title="Πρόωρη Λήξη Δράσης">
            <i class="bi bi-stop-circle-fill"></i>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Stats strip -->
  <div class="d-flex flex-wrap gap-2 mt-3">
    <div class="ops-stat text-white" id="ss-teams"><div class="val" id="sv-teams">--</div><div class="lbl">Ομάδες</div></div>
    <div class="ops-stat text-white" id="ss-ci"><div class="val" id="sv-ci">--</div><div class="lbl">Check-in</div></div>
    <div class="ops-stat text-white" id="ss-pers"><div class="val" id="sv-pers">--</div><div class="lbl">Προσωπικό</div></div>
    <div class="ops-stat text-white" id="ss-cov"><div class="val" id="sv-cov">--%</div><div class="lbl">Κάλυψη</div></div>
    <div class="ops-stat text-white" id="ss-sh"><div class="val" id="sv-sh">--</div><div class="lbl">Ελλείψεις</div></div>
    <div class="ops-stat text-white ms-auto" style="opacity:.7"><div class="val" style="font-size:1.1rem" id="sv-clk">--</div><div class="lbl">Ενημέρωση</div></div>
  </div>
</div>

<!-- ═══════════ MAIN GRID ═══════════ -->
<div class="row g-3">

  <!-- FULL WIDTH: Shifts (shown only if shifts exist) -->
  <div class="col-12" id="shiftsRow" style="display:none">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1"></i>Βάρδιες</span>
        <span class="badge bg-primary" id="shiftsBadge">0</span>
      </div>
      <div class="card-body py-2 px-3" id="shiftsBox"><div class="text-muted small">Φόρτωση βαρδιών…</div></div>
    </div>
  </div>

  <!-- LEFT: Map + Teams -->
  <div class="col-xl-7">

    <!-- Map -->
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-map me-1 text-success"></i>Χάρτης Δράσης</span>
        <span class="badge bg-secondary" id="mapBadge">0 ομάδες</span>
      </div>
      <div class="card-body p-0">
        <div class="map-wrap" id="mapWrap">
          <div id="operationalMap" style="height:320px;border-radius:0 0 12px 12px"></div>
          <div class="map-overlay">
            <button class="btn btn-sm btn-dark" id="mapFullBtn" title="Fullscreen"><i class="bi bi-fullscreen"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Teams -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-1 text-primary"></i>Ομάδες</span>
        <span class="badge bg-primary" id="teamBadge">--</span>
      </div>
      <div class="card-body p-2" id="teamsBox">
        <div class="text-muted small py-3 text-center"><i class="bi bi-arrow-clockwise spin me-1"></i>Φόρτωση…</div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Activity + Shortages + Notes -->
  <div class="col-xl-5 d-flex flex-column gap-3">

    <!-- Shortage reports -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle me-1 text-danger"></i>Ελλείψεις</span>
        <span class="badge bg-danger" id="shBadge">0</span>
      </div>
      <div class="card-body p-2" id="shortagesBox">
        <div class="text-muted small py-2 text-center">Φόρτωση…</div>
      </div>
    </div>

    <!-- Activity feed -->
    <div class="card flex-grow-1">
      <div class="card-header">
        <i class="bi bi-activity me-1 text-info"></i>Δραστηριότητα
      </div>
      <div class="card-body p-2 overflow-auto" style="max-height:260px" id="activityBox">
        <div class="text-muted small py-2 text-center">Φόρτωση…</div>
      </div>
    </div>

    <!-- Notes -->
    <div class="card">
      <div class="card-header"><i class="bi bi-journal-text me-1 text-warning"></i>Σημειώσεις</div>
      <div class="card-body p-2">
        <form id="noteForm" class="d-flex gap-2 mb-2">
          <input type="text" class="form-control form-control-sm" id="noteInput" placeholder="Νέα σημείωση…" maxlength="500">
          <button class="btn btn-sm btn-warning fw-bold" type="submit"><i class="bi bi-send"></i></button>
        </form>
        <div id="notesBox" style="max-height:160px;overflow-y:auto">
          <div class="text-muted small text-center">Φόρτωση…</div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function () {
  'use strict';

  /* ─── Config ─── */
  var EID        = <?= $eid ?>;
  var CSRF       = <?= json_encode($csrfToken) ?>;
  var END_TS     = <?= $endTs ?>;
  var START_TS   = <?= $startTs ?>;
  var DEF_LAT    = <?= $defLat ?>;
  var DEF_LNG    = <?= $defLng ?>;
  var DEF_ZOOM   = <?= $defZoom ?>;
  var POLL_MS    = <?= (int)($config['map_refresh_seconds'] ?? 20) * 1000 ?>;
  var BASE       = <?= json_encode(url('')) ?>;

  /* ─── Countdown ─── */
  var cdEl    = document.getElementById('countdown');
  var cdLbl   = document.getElementById('cdLabel');

  function formatDuration(ms) {
    if (ms <= 0) return 'ΕΛΗΞΕ';
    var s = Math.floor(ms / 1000);
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    var sc = s % 60;
    if (h > 0) return pad(h) + ':' + pad(m) + ':' + pad(sc);
    return pad(m) + ':' + pad(sc);
  }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }

  function tick() {
    var now  = Date.now();
    var diff = END_TS - now;
    cdEl.textContent = formatDuration(diff);
    if (diff <= 0) {
      cdEl.textContent = 'ΕΛΗΞΕ';
      cdLbl.textContent = 'Η δράση ολοκληρώθηκε';
      cdEl.classList.remove('warning', 'urgent');
    } else if (diff < 900000) {          /* < 15 min */
      cdEl.classList.add('urgent');
      cdEl.classList.remove('warning');
    } else if (diff < 3600000) {         /* < 1 h */
      cdEl.classList.add('warning');
      cdEl.classList.remove('urgent');
    } else {
      cdEl.classList.remove('warning', 'urgent');
    }
    /* Show "starting" if not yet begun */
    if (now < START_TS) {
      var toStart = START_TS - now;
      cdEl.textContent = formatDuration(toStart);
      cdLbl.textContent = 'Εκκίνηση σε';
    } else {
      cdLbl.textContent = 'Υπολειπόμενος χρόνος';
    }
  }
  tick();
  setInterval(tick, 1000);

  /* ─── Map ─── */
  var map      = L.map('operationalMap').setView([DEF_LAT, DEF_LNG], DEF_ZOOM);
  var markers  = {};
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19
  }).addTo(map);

  /* Event location pin */
  <?php if ($event['latitude'] && $event['longitude']): ?>
  L.marker([<?= (float)$event['latitude'] ?>, <?= (float)$event['longitude'] ?>], {
    icon: L.divIcon({ className:'', html:'<div style="background:#3b82f6;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 6px #3b82f6"></div>', iconSize:[14,14], iconAnchor:[7,7] })
  }).addTo(map).bindPopup('<b><?= e(addslashes($event['location_name'] ?: $event['title'])) ?></b>');
  <?php endif; ?>

  function updateMap(pings) {
    var bounds = [];
    pings.forEach(function(p) {
      var key = 'team_' + p.team_id;
      var cls = p.age_min < 5 ? 'fresh' : p.age_min < 20 ? 'stale' : 'old';
      var html = '<div style="background:' + (cls==='fresh'?'#22c55e':cls==='stale'?'#f59e0b':'#ef4444') +
                 ';width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 8px rgba(0,0,0,.3)"></div>';
      var icon = L.divIcon({ className:'', html:html, iconSize:[12,12], iconAnchor:[6,6] });
      if (markers[key]) {
        markers[key].setLatLng([p.latitude, p.longitude]);
        markers[key].setIcon(icon);
        markers[key].setPopupContent('<b>' + p.team_name + '</b><br>' + p.age_min + ' λεπτά πριν');
      } else {
        markers[key] = L.marker([p.latitude, p.longitude], { icon: icon })
          .addTo(map)
          .bindPopup('<b>' + p.team_name + '</b><br>' + p.age_min + ' λεπτά πριν');
      }
      bounds.push([p.latitude, p.longitude]);
    });
    document.getElementById('mapBadge').textContent = pings.length + ' ομάδες';
    if (bounds.length > 1) { try { map.fitBounds(bounds, { padding:[30,30], maxZoom:16 }); } catch(e){} }
  }

  /* ─── Map fullscreen ─── */
  var mapWrap = document.getElementById('mapWrap');
  document.getElementById('mapFullBtn').addEventListener('click', function() {
    mapWrap.classList.toggle('fullscreen');
    setTimeout(function(){ map.invalidateSize(); }, 200);
  });
  document.getElementById('btnFull').addEventListener('click', function() {
    mapWrap.classList.toggle('fullscreen');
    setTimeout(function(){ map.invalidateSize(); }, 200);
  });

  /* ─── Dark mode ─── */
  document.getElementById('btnDark').addEventListener('click', function() {
    document.body.classList.toggle('ops-dark');
  });

  /* ─── Render helpers ─── */
  function statusIcon(s) {
    var icons = { present_full:'bi-check-circle-fill text-success', present_partial:'bi-check-circle text-warning',
                  not_present:'bi-circle text-muted', departed:'bi-box-arrow-right text-secondary' };
    var labels = { present_full:'Παρόντες', present_partial:'Μερική παρουσία', not_present:'Αναμένονται', departed:'Αποχώρησαν' };
    var ic = icons[s] || 'bi-question-circle text-muted';
    var lb = labels[s] || (s || 'Αναμένεται');
    return '<i class="bi ' + ic + ' me-1"></i>' + lb;
  }

  function renderTeams(teams) {
    var box = document.getElementById('teamsBox');
    if (!teams || !teams.length) {
      box.innerHTML = '<div class="text-muted small py-3 text-center">Δεν υπάρχουν εγκεκριμένες ομάδες.</div>';
      return;
    }
    var ci = 0;
    var html = '<div class="row g-2">';
    teams.forEach(function(t) {
      var cs = t.checkin_status || 'none';
      var pingCls = 'none';
      if (t.ping_age_min !== null) {
        pingCls = t.ping_age_min < 5 ? 'fresh' : t.ping_age_min < 20 ? 'stale' : 'old';
      }
      if (cs && cs !== 'not_present') ci++;
      html += '<div class="col-md-6"><div class="card team-card s-' + cs + ' p-2">' +
              '<div class="d-flex justify-content-between align-items-start">' +
              '<div><div class="fw-semibold small">' + esc(t.team_name) + '</div>' +
              '<div class="small mt-1">' + statusIcon(cs) + '</div></div>' +
              '<div class="text-end">' +
              '<span class="fw-bold" style="font-size:1.1rem">' + (t.present_people||0) + '/' + t.approved_people + '</span>' +
              '<div class="small text-muted">άτομα</div></div></div>' +
              (t.ping_lat ? '<div class="mt-1 small"><span class="ping-dot ' + pingCls + '"></span>' + (t.ping_age || '') + '</div>' : '') +
              '</div></div>';
    });
    html += '</div>';
    box.innerHTML = html;
    document.getElementById('teamBadge').textContent = ci + '/' + teams.length + ' παρόντες';
    document.getElementById('sv-teams').textContent = teams.length;
    document.getElementById('sv-ci').textContent = ci;
  }

  function renderShortages(items) {
    var box = document.getElementById('shortagesBox');
    var open = items.filter(function(s){ return s.status === 'open'; }).length;
    document.getElementById('shBadge').textContent = open;
    var ssSh = document.getElementById('ss-sh');
    ssSh.classList.toggle('has-alert', open > 0);
    document.getElementById('sv-sh').textContent = open;
    if (!items.length) {
      box.innerHTML = '<div class="text-muted small py-2 text-center"><i class="bi bi-check-circle text-success me-1"></i>Δεν υπάρχουν ελλείψεις</div>';
      return;
    }
    var sevCls = { critical:'danger', high:'warning', medium:'secondary', low:'light' };
    var sevLbl = { critical:'ΚΡΙΣΙΜΗ', high:'ΥΨΗΛΗ', medium:'ΜΕΤΡΙΑ', low:'ΧΑΜΗΛΗ' };
    var html = '';
    items.forEach(function(s) {
      var sev = s.severity || 'medium';
      html += '<div class="card sc ' + s.status + ' mb-1 p-2">' +
              '<div class="d-flex justify-content-between align-items-start">' +
              '<div><span class="badge text-bg-' + (sevCls[sev]||'secondary') + ' me-1">' + (sevLbl[sev]||sev) + '</span>' +
              '<strong class="small">' + esc(s.title) + '</strong>' +
              '<div class="text-muted small">' + esc(s.team_name) + '</div></div>' +
              '<span class="badge text-bg-' + (s.status==='open'?'danger':s.status==='acknowledged'?'warning':'success') + '">' +
              (s.status==='open'?'Ανοιχτή':s.status==='acknowledged'?'Σε γνώση':'Λύθηκε') + '</span></div></div>';
    });
    box.innerHTML = html;
  }

  function renderActivity(items) {
    var box = document.getElementById('activityBox');
    if (!items || !items.length) {
      box.innerHTML = '<div class="text-muted small py-2 text-center">Δεν υπάρχει δραστηριότητα ακόμα.</div>';
      return;
    }
    var iconMap = { checkin:'bi-check-circle-fill', shortage:'bi-exclamation-triangle-fill', note:'bi-journal-text' };
    var clsMap  = { checkin:'bg-success text-white', shortage:'bg-danger text-white', note:'bg-warning text-dark' };
    var html = '';
    items.forEach(function(a) {
      var ic = iconMap[a.type] || 'bi-dot';
      var cl = clsMap[a.type]  || 'bg-secondary text-white';
      html += '<div class="af-item"><div class="af-icon ' + cl + '"><i class="bi ' + ic + '"></i></div>' +
              '<div class="flex-grow-1"><div class="small fw-semibold">' + esc(a.actor) + '</div>' +
              '<div class="small text-muted" style="font-size:.73rem">' + esc(a.title) + '</div></div>' +
              '<div class="af-time">' + esc((a.ts||'').substring(11,16)) + '</div></div>';
    });
    box.innerHTML = html;
  }

  function renderNotes(notes) {
    var box = document.getElementById('notesBox');
    if (!notes || !notes.length) {
      box.innerHTML = '<div class="text-muted small text-center">Δεν υπάρχουν σημειώσεις.</div>';
      return;
    }
    var html = '';
    notes.forEach(function(n) {
      html += '<div class="d-flex gap-2 mb-1 align-items-start">' +
              '<span class="badge bg-warning text-dark mt-1" style="font-size:.6rem">' + esc((n.created_at||'').substring(11,16)) + '</span>' +
              '<div class="small">' + esc(n.note) + '</div></div>';
    });
    box.innerHTML = html;
  }

  /* ─── Apply a full snapshot (from SSE or manual poll) ─── */
  function applySnapshot(d) {
    if (!d || !d.ok) return;
    /* read previous counts before DOM update for change detection */
    var prevCi = parseInt(document.getElementById('sv-ci').textContent, 10);
    var prevSh = parseInt(document.getElementById('sv-sh').textContent, 10);
    /* stats bar */
    document.getElementById('sv-pers').textContent = d.stats.total_present || 0;
    document.getElementById('sv-cov').textContent  = (d.stats.coverage || 0) + '%';
    document.getElementById('sv-clk').textContent  = d.ts;
    /* sections */
    renderTeams(d.teams);
    renderShortages(d.shortages);
    renderActivity(d.activity);
    renderNotes(d.notes);
    /* map pings included in SSE snapshot */
    if (d.pings) updateMap(d.pings);
    /* flash sections where count increased */
    if (!isNaN(prevCi) && (d.stats.checked_in || 0) > prevCi) flashEl('teamsBox');
    if (!isNaN(prevSh) && (d.stats.open_shortages || 0) > prevSh) flashEl('shortagesBox');
  }

  function flashEl(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('sse-flash');
    void el.offsetWidth; /* force reflow to restart animation */
    el.classList.add('sse-flash');
    setTimeout(function(){ el.classList.remove('sse-flash'); }, 800);
  }

  /* Manual status poll — used by the refresh button */
  function pollStatus() {
    fetch(BASE + '/operations/events/' + EID + '/status')
      .then(function(r){ return r.json(); })
      .then(function(d) { applySnapshot(d); })
      .catch(function(){});
  }

  function pollLocations() {
    fetch(BASE + '/operations/events/' + EID + '/locations')
      .then(function(r){ return r.json(); })
      .then(function(d) { if (d.ok) updateMap(d.pings); })
      .catch(function(){});
  }

  /* ─── Refresh button ─── */
  document.getElementById('btnRefresh').addEventListener('click', function() {
    var ic = this.querySelector('i');
    ic.classList.add('spin');
    pollStatus(); pollLocations();
    setTimeout(function(){ ic.classList.remove('spin'); }, 1000);
  });

  /* ─── Note form ─── */
  document.getElementById('noteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var inp = document.getElementById('noteInput');
    var txt = inp.value.trim();
    if (!txt) return;
    inp.disabled = true;
    fetch(BASE + '/operations/events/' + EID + '/note', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: '_token=' + encodeURIComponent(CSRF) + '&note=' + encodeURIComponent(txt)
    })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.ok) { inp.value = ''; pollStatus(); }
    })
    .finally(function(){ inp.disabled = false; inp.focus(); });
  });

  /* ─── Utility ─── */
  function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ─── SSE connection ─── */
  var sseEs = null;

  function setSseBadge(state) {
    var b = document.getElementById('liveBadge');
    if (!b) return;
    if (state === 'live') {
      b.textContent = '◉ LIVE SSE';
      b.className   = 'badge bg-success';
    } else {
      b.textContent = '↺ Επανασύνδεση…';
      b.className   = 'badge bg-warning text-dark';
    }
  }

  function connectSSE() {
    if (sseEs) { try { sseEs.close(); } catch(e){} }
    sseEs = new EventSource(BASE + '/operations/events/' + EID + '/stream');
    sseEs.onopen = function() { setSseBadge('live'); };
    sseEs.addEventListener('update', function(evt) {
      try { applySnapshot(JSON.parse(evt.data)); } catch(e){}
    });
    sseEs.onerror = function() { setSseBadge('reconnecting'); };
  }

  /* ─── Boot ─── */
  connectSSE();
  /* Locations are now included in the SSE snapshot.
     pollLocations() remains available for the manual refresh button. */

})();
</script>
