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

/* ── SOS alarm ─────────────────────────────────────────────────────────── */
.sos-alarm { display:none;margin-bottom:1rem;border-radius:16px;padding:1rem 1.2rem;background:linear-gradient(135deg,#7f1d1d,#dc2626);color:#fff;animation:sosAlarmPulse 1s infinite; }
.sos-alarm.show { display:block; }
@keyframes sosAlarmPulse { 0%{box-shadow:0 0 0 0 rgba(239,68,68,.55)} 70%{box-shadow:0 0 0 14px rgba(239,68,68,0)} 100%{box-shadow:0 0 0 0 rgba(239,68,68,0)} }
.sos-alarm-head { display:flex;align-items:center;gap:.6rem;font-weight:900;font-size:1.15rem;letter-spacing:.04em;margin-bottom:.4rem; }
.sos-alarm-item { display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;padding:.55rem 0;border-top:1px solid rgba(255,255,255,.25); }
.sos-alarm-item .sa-team { font-weight:800; }
.sos-alarm-item .sa-meta { font-size:.78rem;opacity:.9; }
.sos-alarm-item .btn { --bs-btn-padding-y:.15rem; }

/* ── Comms thread ──────────────────────────────────────────────────────── */
.msg-thread { max-height:300px;overflow-y:auto;display:flex;flex-direction:column;gap:.4rem; }
.cmsg { padding:.42rem .6rem;border-radius:10px;font-size:.82rem;line-height:1.35;max-width:90%; }
.cmsg-team    { align-self:flex-start;background:rgba(13,148,136,.14);border:1px solid rgba(13,148,136,.4); }
.cmsg-command { align-self:flex-end;  background:rgba(59,130,246,.14);border:1px solid rgba(59,130,246,.4); }
.cmsg-order   { align-self:flex-end;  background:rgba(245,158,11,.16);border:1px solid rgba(245,158,11,.5); }
.cmsg-status  { align-self:flex-start;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.45);font-weight:700; }
.cmsg-meta { font-size:.66rem;opacity:.7;margin-top:2px; }
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
        <a class="btn btn-sm btn-outline-light" href="<?= e(url('/operations/events/' . $eid . '/gate-qr')) ?>" target="_blank" title="QR Πύλης — δήλωση παρουσίας"><i class="bi bi-qr-code"></i></a>
        <a class="btn btn-sm btn-outline-light" href="<?= e(url('/events/' . $eid)) ?>"><i class="bi bi-arrow-left"></i></a>
        <!-- Κλείσιμο δράσης (→ closed, για αρχειοθέτηση) -->
        <form method="post" action="<?= e(url('/events/' . $eid . '/close')) ?>"
              onsubmit="return confirm('Κλείσιμο δράσης;\nΗ δράση θα κλείσει και θα πάει για αρχειοθέτηση.')">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <button class="btn btn-sm btn-danger w-100" title="Κλείσιμο Δράσης">
            <i class="bi bi-door-closed-fill"></i>
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

<!-- ═══════════ SOS ALARM ═══════════ -->
<div class="sos-alarm" id="sosAlarm"></div>

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

<!-- ═══════════ COMMS (command ↔ teams) ═══════════ -->
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-chat-dots me-1 text-primary"></i>Επικοινωνία με ομάδες</span>
    <span class="badge bg-primary" id="msgBadge">0</span>
  </div>
  <div class="card-body">
    <div class="row g-2 align-items-end mb-3">
      <div class="col-md-3">
        <label class="form-label small mb-1">Παραλήπτης</label>
        <select class="form-select form-select-sm" id="cmsgTeam">
          <option value="">📢 Όλες οι ομάδες (broadcast)</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small mb-1">Μήνυμα / εντολή</label>
        <input type="text" class="form-control form-control-sm" id="cmsgBody" maxlength="500" placeholder="Γράψτε μήνυμα ή εντολή…">
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="button" class="btn btn-sm btn-primary flex-fill" id="cmsgSendMsg"><i class="bi bi-send me-1"></i>Μήνυμα</button>
        <button type="button" class="btn btn-sm btn-warning flex-fill fw-bold" id="cmsgSendOrder" title="Στέλνεται ως εντολή — ζητά επιβεβαίωση λήψης από την ομάδα"><i class="bi bi-megaphone me-1"></i>Εντολή</button>
      </div>
    </div>
    <!-- Geo point sender -->
    <div class="row g-2 align-items-end mb-3 pt-2 border-top">
      <div class="col-12"><span class="small text-muted"><i class="bi bi-geo-alt me-1"></i>Αποστολή <strong>σημείου</strong> στην ομάδα (παραλήπτης από πάνω)</span></div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Τύπος</label>
        <select class="form-select form-select-sm" id="geoKind">
          <option value="move">➡️ Μετάβαση εδώ</option>
          <option value="incident">⚠️ Περιστατικό</option>
          <option value="poi">📍 Σημείο ενδιαφέροντος</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Συντεταγμένες</label>
        <input type="text" class="form-control form-control-sm" id="geoCoords" placeholder="lat, lng">
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Σχόλιο</label>
        <input type="text" class="form-control form-control-sm" id="geoNote" maxlength="200" placeholder="προαιρετικό">
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="geoPickBtn" title="Κλικ στον χάρτη"><i class="bi bi-crosshair me-1"></i>Χάρτη</button>
        <button type="button" class="btn btn-sm btn-success flex-fill" id="geoSendBtn"><i class="bi bi-send-fill me-1"></i>Σημείο</button>
      </div>
    </div>
    <div class="msg-thread" id="msgThread"><div class="text-muted small text-center">Καμία επικοινωνία ακόμη.</div></div>
  </div>
</div>

<!-- ═══════════ ΔΩΜΑΤΙΟ ΕΠΙΧΕΙΡΗΣΗΣ (κοινό κανάλι) ═══════════ -->
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-broadcast-pin me-1 text-success"></i>Δωμάτιο Επιχείρησης <span class="small text-muted fw-normal">— κοινό κανάλι όλων των ομάδων</span></span>
    <span class="badge bg-success" id="roomBadge">0</span>
  </div>
  <div class="card-body">
    <div class="msg-thread" id="roomThread" style="max-height:260px"><div class="text-muted small text-center">Κανένα μήνυμα ακόμη.</div></div>
    <div class="input-group input-group-sm mt-2">
      <input type="text" class="form-control" id="roomInput" maxlength="500" placeholder="Μήνυμα προς όλους (δήμος + όλες οι ομάδες)…">
      <button class="btn btn-success" type="button" id="roomSend"><i class="bi bi-send me-1"></i>Αποστολή</button>
    </div>
  </div>
</div>

<!-- Φωτογραφίες ομάδων -->
<div class="card mt-3" id="photosCard" style="display:none">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-images me-1 text-info"></i>Φωτογραφίες ομάδων</span>
    <span class="badge bg-info" id="photosBadge">0</span>
  </div>
  <div class="card-body p-2 d-flex flex-wrap gap-2" id="photosBox"></div>
</div>

<!-- Photo viewer modal -->
<div class="modal fade" id="photoModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="photoModalLabel">Φωτογραφία</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-1" style="background:#111">
        <img id="photoModalImg" src="" alt="" style="max-width:100%;max-height:75vh">
      </div>
      <div class="modal-footer py-2">
        <a id="photoModalDl" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Άνοιγμα σε νέα καρτέλα</a>
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

  /* ─── Poll status ─── */
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
              '<button type="button" class="btn btn-outline-info btn-sm w-100 mt-1 py-0 req-photo-btn" data-team="' + t.team_id + '"' + (t.photo_pending ? ' disabled' : '') + '><i class="bi bi-camera me-1"></i>' + (t.photo_pending ? 'Ζητήθηκε φωτό' : 'Ζήτησε φωτό') + '</button>' +
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
      var actions = '';
      if (s.status === 'open') {
        actions = '<button type="button" class="btn btn-outline-warning btn-sm py-0 sh-ack-btn" data-id="' + s.id + '">Σε γνώση</button>' +
                  '<button type="button" class="btn btn-outline-success btn-sm py-0 sh-res-btn" data-id="' + s.id + '">Λύθηκε</button>';
      } else if (s.status === 'acknowledged') {
        actions = '<button type="button" class="btn btn-outline-success btn-sm py-0 sh-res-btn" data-id="' + s.id + '">Λύθηκε</button>';
      }
      html += '<div class="card sc ' + s.status + ' mb-1 p-2">' +
              '<div class="d-flex justify-content-between align-items-start">' +
              '<div><span class="badge text-bg-' + (sevCls[sev]||'secondary') + ' me-1">' + (sevLbl[sev]||sev) + '</span>' +
              '<strong class="small">' + esc(s.title) + '</strong>' +
              '<div class="text-muted small">' + esc(s.team_name) + '</div></div>' +
              '<span class="badge text-bg-' + (s.status==='open'?'danger':s.status==='acknowledged'?'warning':'success') + '">' +
              (s.status==='open'?'Ανοιχτή':s.status==='acknowledged'?'Σε γνώση':'Λύθηκε') + '</span></div>' +
              (actions ? '<div class="d-flex gap-1 mt-2 justify-content-end">' + actions + '</div>' : '') +
              '</div>';
    });
    box.innerHTML = html;
  }

  /* ─── SOS alarm ─── */
  function renderSos(items) {
    var box = document.getElementById('sosAlarm');
    if (!box) return;
    items = items || [];
    if (!items.length) { box.className = 'sos-alarm'; box.innerHTML = ''; return; }
    var html = '<div class="sos-alarm-head"><i class="bi bi-exclamation-octagon-fill"></i> SOS — ΕΝΕΡΓΟ ΠΕΡΙΣΤΑΤΙΚΟ (' + items.length + ')</div>';
    items.forEach(function(s) {
      var geo = (s.latitude && s.longitude)
        ? '<a class="btn btn-light btn-sm" target="_blank" rel="noopener" href="https://www.google.com/maps?q=' + s.latitude + ',' + s.longitude + '"><i class="bi bi-geo-alt-fill"></i> Χάρτης</a>'
        : '<span class="sa-meta">χωρίς τοποθεσία</span>';
      var actions = s.status === 'active'
        ? '<button type="button" class="btn btn-light btn-sm sos-ack-btn" data-id="' + s.id + '">Επιβεβαίωση</button>'
        : '<span class="badge text-bg-info">Σε γνώση' + (s.ack_name ? ' · ' + esc(s.ack_name) : '') + '</span>';
      html += '<div class="sos-alarm-item">' +
              '<span class="sa-team"><i class="bi bi-people-fill me-1"></i>' + esc(s.team_name) + '</span>' +
              '<span class="sa-meta">' + esc((s.created_at||'').substring(11,16)) + (s.note ? ' · ' + esc(s.note) : '') + '</span>' +
              '<span class="ms-auto d-flex gap-1 align-items-center">' + geo + actions +
              '<button type="button" class="btn btn-outline-light btn-sm sos-res-btn" data-id="' + s.id + '">Κλείσιμο</button>' +
              '</span></div>';
    });
    box.innerHTML = html;
    box.className = 'sos-alarm show';
  }

  /* ─── Comms thread ─── */
  function renderMessages(msgs) {
    var box = document.getElementById('msgThread');
    if (!box) return;
    msgs = msgs || [];
    document.getElementById('msgBadge').textContent = msgs.length;
    if (!msgs.length) { box.innerHTML = '<div class="text-muted small text-center">Καμία επικοινωνία ακόμη.</div>'; return; }
    box.innerHTML = msgs.map(function(m) {
      var cls = m.kind === 'order' ? 'cmsg-order'
              : (m.kind === 'status' ? 'cmsg-status'
              : (m.sender_role === 'command' ? 'cmsg-command' : 'cmsg-team'));
      var who = m.sender_role === 'command' ? 'Δήμος'