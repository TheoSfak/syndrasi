<?php
/* ── Operational Command Center ─────────────────────────────────────────── */
$eid       = (int) $event['id'];
$csrfToken = csrf_token();
$endTs     = strtotime($event['end_datetime']) * 1000;
$startTs   = strtotime($event['start_datetime']) * 1000;
$defLat    = (float)($mapDefLat  ?: $event['latitude']  ?: 35.3387);
$defLng    = (float)($mapDefLng  ?: $event['longitude'] ?: 25.1442);
$defZoom   = (int)($mapDefZoom  ?: 13);
$playbook  = $playbook ?? null;
$terms     = authority_context((int) $event['municipality_id']);
$eventSingular   = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$orgLabel  = $orgLabel ?? ($terms['short_name'] ?? 'Φορέας');
$orgIcon   = $orgIcon ?? ($terms['icon'] ?? '🏛️');
$closeConfirm = 'Κλείσιμο: ' . $eventSingular . "\nΘα κλείσει και θα πάει για αρχειοθέτηση.";
?>
<style>
body.ops-dark { background:#0a0f1a !important; background-attachment:fixed !important; }
body.ops-dark .card            { background:rgba(255,255,255,.06)!important; border-color:rgba(255,255,255,.1)!important; color:#e2e8f0!important; }
body.ops-dark .card-header     { background:rgba(0,0,0,.3)!important; color:#e2e8f0!important; border-color:rgba(255,255,255,.1)!important; }
body.ops-dark .text-muted      { color:#94a3b8!important; }
body.ops-dark .form-control    { background:rgba(255,255,255,.08)!important; border-color:rgba(255,255,255,.15)!important; color:#fff!important; }
body.ops-dark .list-group-item { background:transparent!important; border-color:rgba(255,255,255,.1)!important; color:#e2e8f0!important; }
body.ops-dark main             { background:transparent!important; }

.ops-stat { display:inline-flex;flex-direction:row;align-items:baseline;gap:.4rem;padding:.3rem .75rem;border-radius:20px;background:rgba(255,255,255,.12);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.18);transition:transform .2s; }
.ops-stat:hover { transform:translateY(-1px); }
.ops-stat .val { font-size:1.1rem;font-weight:900;line-height:1; }
.ops-stat .lbl { font-size:.58rem;text-transform:uppercase;letter-spacing:.7px;opacity:.75;white-space:nowrap; }
.ops-stat.has-alert { border-color:rgba(239,68,68,.6)!important;background:rgba(239,68,68,.2)!important; }
.ops-stat.has-alert .val { color:#fca5a5!important; }

.ops-countdown-wrap { background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.18);border-radius:12px;padding:.3rem .9rem .2rem;text-align:center;backdrop-filter:blur(8px); }
.ops-countdown { font-size:1.85rem;font-weight:900;letter-spacing:.06em;line-height:1;font-variant-numeric:tabular-nums;color:#fff;text-shadow:0 0 12px rgba(255,255,255,.4);transition:color .3s,text-shadow .3s; }
.ops-countdown.warning { color:#fbbf24;text-shadow:0 0 20px rgba(251,191,36,.7),0 0 6px rgba(251,191,36,.4);animation:cdFlash 1.2s ease-in-out infinite; }
.ops-countdown.urgent  { color:#ef4444;text-shadow:0 0 24px rgba(239,68,68,.9),0 0 8px rgba(239,68,68,.5);animation:cdFlash .55s ease-in-out infinite; }
@keyframes cdFlash { 0%,100%{opacity:1;letter-spacing:.06em;} 50%{opacity:.25;letter-spacing:.12em;} }

.ops-header { background:linear-gradient(135deg,#0b1120 0%,#0c3a35 60%,#0d4a44 100%);color:#fff;padding:.65rem 1.2rem;border-radius:20px;margin-bottom:1rem;box-shadow:0 8px 40px rgba(0,0,0,.3); }

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
@keyframes silentPulse { 0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.7)} 65%{box-shadow:0 0 0 9px rgba(239,68,68,0)} }

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

.map-legend { display:flex;flex-wrap:wrap;gap:5px;padding:5px 2px; }
.map-legend-chip { display:inline-flex;align-items:center;gap:5px;background:rgba(128,128,128,.1);border:1px solid rgba(128,128,128,.2);border-radius:20px;padding:2px 10px 2px 6px;font-size:.72rem;white-space:nowrap; }
body.ops-dark .map-legend-chip { background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.15);color:#e2e8f0; }

.ldot { width:9px;height:9px;border-radius:50%;background:#22c55e;display:inline-block;animation:ldp 1.6s ease-in-out infinite; }
@keyframes ldp { 0%,100%{box-shadow:0 0 0 0 rgba(34,197,94,.6)} 50%{box-shadow:0 0 0 6px rgba(34,197,94,0)} }
@keyframes spin { to{transform:rotate(360deg)} }
.spin { animation:spin .6s linear infinite;display:inline-block; }
@keyframes sseFlash { 0%,100%{box-shadow:none} 35%{box-shadow:0 0 0 4px rgba(34,197,94,.65),0 0 16px rgba(34,197,94,.3)} }
.sse-flash { animation:sseFlash .75s ease; }
@keyframes camPulse { 0%,100%{box-shadow:0 0 0 0 rgba(14,165,233,.75)} 60%{box-shadow:0 0 0 5px rgba(14,165,233,0)} }
.cam-badge { animation:camPulse 1.8s ease-in-out infinite; }

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

/* ── Photo Wall ────────────────────────────────────────────────────────── */
.wall-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:3px; }
.wall-cell { position:relative;aspect-ratio:1;overflow:hidden;border-radius:6px;cursor:pointer;background:#111; }
.wall-cell img { width:100%;height:100%;object-fit:cover;transition:transform .2s; display:block; }
.wall-cell:hover img { transform:scale(1.06); }
.wall-ovr { position:absolute;bottom:0;left:0;right:0;padding:3px 5px 3px;background:linear-gradient(to top,rgba(0,0,0,.82) 0%,transparent 100%); pointer-events:none; }
.wall-team { font-size:.6rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.wall-time { font-size:.54rem;color:rgba(255,255,255,.65); }
.wall-cap  { font-size:.57rem;color:rgba(255,255,255,.8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-style:italic; }
@keyframes wallIn { from{opacity:0;transform:scale(.85)} to{opacity:1;transform:scale(1)} }
.wall-new { animation:wallIn .38s ease; }

/* ── Teams Board ───────────────────────────────────────────────────────── */
.board-dot { width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0; }
.board-row { transition:background .15s;border-bottom:1px solid rgba(0,0,0,.06); }
.board-row:last-child { border-bottom:none!important; }
.board-row:hover { background:rgba(0,0,0,.025); }
body.ops-dark .board-row { border-bottom-color:rgba(255,255,255,.07); }
body.ops-dark .board-row:hover { background:rgba(255,255,255,.04); }
.board-num { font-size:1.4rem;line-height:1;font-weight:700; }
.board-lbl { font-size:.62rem;text-transform:uppercase;letter-spacing:.5px;margin-top:1px; }

/* ── Comms thread ──────────────────────────────────────────────────────── */
.msg-thread { max-height:300px;overflow-y:auto;display:flex;flex-direction:column;gap:.4rem; }
.cmsg { padding:.42rem .6rem;border-radius:10px;font-size:.82rem;line-height:1.35;max-width:90%; }
.cmsg-team    { align-self:flex-start;background:rgba(13,148,136,.14);border:1px solid rgba(13,148,136,.4); }
.cmsg-command { align-self:flex-end;  background:rgba(59,130,246,.14);border:1px solid rgba(59,130,246,.4); }
.cmsg-order   { align-self:flex-end;  background:rgba(245,158,11,.16);border:1px solid rgba(245,158,11,.5); }
.cmsg-status  { align-self:flex-start;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.45);font-weight:700; }
.cmsg-meta { font-size:.66rem;opacity:.7;margin-top:2px; }
.playbook-check { display:flex;align-items:flex-start;gap:.45rem;padding:.32rem 0;border-bottom:1px solid rgba(0,0,0,.06); }
.playbook-check:last-child { border-bottom:0; }
.playbook-check input { margin-top:.2rem; }
.playbook-check.done span { text-decoration:line-through;opacity:.58; }
body.ops-dark .playbook-check { border-bottom-color:rgba(255,255,255,.08); }
.playbook-msg-btn { text-align:left;white-space:normal; }
</style>

<!-- ═══════════ COMMAND HEADER ═══════════ -->
<div class="ops-header">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="ldot"></span>
    <span class="fw-bold text-white" style="font-size:1rem;white-space:nowrap">Επιχειρησιακό Κέντρο</span>
    <span class="badge bg-success" id="liveBadge">◉ LIVE SSE</span>
    <span style="opacity:.35">·</span>
    <span class="fw-semibold text-white" style="font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px"><?= e($event['title']) ?></span>
    <span style="opacity:.6;font-size:.72rem;white-space:nowrap">
      <i class="bi bi-clock me-1"></i><?= e(gr_datetime($event['start_datetime'])) ?> &rarr; <?= e(gr_datetime($event['end_datetime'])) ?><?php if ($event['location_name']): ?>&nbsp;&middot;&nbsp;<i class="bi bi-geo-alt me-1"></i><?= e($event['location_name']) ?><?php endif; ?>
    </span>
    <div class="ms-auto d-flex align-items-center gap-2">
      <div class="ops-countdown-wrap text-white">
        <div class="ops-countdown" id="countdown">--</div>
        <div style="font-size:.5rem;opacity:.6;text-transform:uppercase;letter-spacing:1px;margin-top:.15rem" id="cdLabel">Υπολειπόμενος χρόνος</div>
      </div>
      <div class="d-flex gap-1 align-items-center">
        <button class="btn btn-sm btn-outline-light" id="btnDark" title="Mission Control Mode"><i class="bi bi-moon-stars-fill"></i></button>
        <button class="btn btn-sm btn-outline-light" id="btnFull" title="Fullscreen χάρτης"><i class="bi bi-fullscreen"></i></button>
        <button class="btn btn-sm btn-outline-light" id="btnRefresh"><i class="bi bi-arrow-clockwise"></i></button>
        <a class="btn btn-sm btn-outline-light" href="<?= e(url('/operations/events/' . $eid . '/gate-qr')) ?>" target="_blank" title="QR Πύλης — δήλωση παρουσίας"><i class="bi bi-qr-code"></i></a>
        <a class="btn btn-sm btn-outline-light" href="<?= e(url('/events/' . $eid)) ?>"><i class="bi bi-arrow-left"></i></a>
        <form method="post" action="<?= e(url('/events/' . $eid . '/close')) ?>"
              onsubmit="return confirm(<?= e(json_encode($closeConfirm, JSON_UNESCAPED_UNICODE)) ?>)">
          <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
          <button class="btn btn-sm btn-danger" title="Κλείσιμο <?= e($eventSingular) ?>"><i class="bi bi-door-closed-fill"></i></button>
        </form>
      </div>
    </div>
  </div>

  <!-- Stats strip -->
  <div class="d-flex flex-wrap gap-2 mt-2">
    <div class="ops-stat text-white" id="ss-teams"><div class="val" id="sv-teams">--</div><div class="lbl">Ομάδες</div></div>
    <div class="ops-stat text-white" id="ss-ci"><div class="val" id="sv-ci">--</div><div class="lbl">Check-in</div></div>
    <div class="ops-stat text-white" id="ss-pers"><div class="val" id="sv-pers">--</div><div class="lbl">Προσωπικό</div></div>
    <div class="ops-stat text-white" id="ss-cov"><div class="val" id="sv-cov">--%</div><div class="lbl">Κάλυψη</div></div>
    <div class="ops-stat text-white" id="ss-sh"><div class="val" id="sv-sh">--</div><div class="lbl">Ελλείψεις</div></div>
    <div class="ops-stat text-white ms-auto" style="opacity:.7"><div class="val" id="sv-clk">--</div><div class="lbl">Ενημέρωση</div></div>
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

<?php if (current_role() === 'municipality_admin'): ?>
  <!-- FULL WIDTH: Pending applications (municipality_admin only, hidden when none) -->
  <div class="col-12" id="pendingAppsRow" style="display:none">
    <div class="card" style="border:1.5px solid #f59e0b">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:rgba(245,158,11,.1)">
        <span><i class="bi bi-hourglass-split me-1 text-warning"></i><strong>Αιτήσεις Συμμετοχής</strong> <span class="fw-normal small text-muted">— υποβλήθηκαν κατά τη διάρκεια της <?= e($eventSingularLc) ?></span></span>
        <span class="badge bg-warning text-dark" id="pendingAppsBadge">0</span>
      </div>
      <div class="card-body p-2" id="pendingAppsBox"></div>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($playbook)): ?>
  <!-- FULL WIDTH: Mission playbook -->
  <div class="col-12">
    <div class="card" style="border:1.5px solid rgba(13,110,253,.35)">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-journal-check me-1 text-primary"></i><strong><?= e($playbook['title']) ?></strong></span>
        <div class="d-flex flex-wrap gap-1">
          <?php if (!empty($playbook['default_people'])): ?><span class="badge text-bg-light border"><?= (int) $playbook['default_people'] ?> άτομα</span><?php endif; ?>
          <?php if (!empty($playbook['require_vehicle'])): ?><span class="badge text-bg-light border"><i class="bi bi-truck me-1"></i>Όχημα</span><?php endif; ?>
          <?php if (!empty($playbook['require_medical'])): ?><span class="badge text-bg-light border"><i class="bi bi-heart-pulse me-1"></i>Υγειονομικό</span><?php endif; ?>
        </div>
      </div>
      <div class="card-body py-3">
        <div class="row g-3">
          <div class="col-lg-5">
            <div class="small text-muted mb-1">Operational checklist</div>
            <div id="playbookChecklist">
              <?php foreach (($playbook['checklist'] ?? []) as $idx => $item): ?>
                <label class="playbook-check" data-pb-index="<?= (int) $idx ?>">
                  <input type="checkbox" class="form-check-input js-pb-check">
                  <span><?= e($item) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-lg-3">
            <div class="small text-muted mb-1">Δυνατότητες</div>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach (($playbook['capabilities'] ?? []) as $cap): ?>
                <span class="badge text-bg-secondary"><?= e($cap) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="small text-muted mb-1">Έτοιμα μηνύματα</div>
            <div class="d-grid gap-1">
              <?php foreach (($playbook['messages'] ?? []) as $msg): ?>
                <button type="button" class="btn btn-sm btn-outline-primary playbook-msg-btn js-pb-msg" data-message="<?= e($msg) ?>">
                  <i class="bi bi-chat-left-text me-1"></i><?= e($msg) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

  <!-- LEFT: Map + Teams -->
  <div class="col-xl-7">

    <!-- Map -->
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-map me-1 text-success"></i>Χάρτης <?= e($eventSingular) ?></span>
        <span class="badge bg-secondary" id="mapBadge">0 ομάδες</span>
      </div>
      <div class="card-body p-0">
        <div class="map-wrap" id="mapWrap">
          <div id="operationalMap" style="height:480px;border-radius:0 0 12px 12px"></div>
          <div class="map-overlay">
            <button class="btn btn-sm btn-dark" id="mapFullBtn" title="Fullscreen"><i class="bi bi-fullscreen"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Team color legend -->
    <div id="mapLegend" class="map-legend mb-1" style="display:none"></div>

    <!-- Map symbol legend (collapsed by default) -->
    <details class="mb-3" style="font-size:.72rem;color:#6c757d">
      <summary style="cursor:pointer;list-style:none;display:inline-flex;align-items:center;gap:4px;user-select:none">
        <i class="bi bi-info-circle"></i> Υπόμνημα συμβόλων χάρτη
      </summary>
      <div class="d-flex flex-wrap gap-2 mt-2 ps-1">
        <span class="d-inline-flex align-items-center gap-1">
          <span style="width:12px;height:12px;border-radius:50%;background:#94a3b8;border:2px solid #22c55e;display:inline-block;flex-shrink:0"></span>
          GPS στίγμα ομάδας
        </span>
        <span class="d-inline-flex align-items-center gap-1" style="color:#f97316">
          <span style="width:0;height:0;border-left:7px solid transparent;border-right:7px solid transparent;border-top:14px solid #f97316;display:inline-block;flex-shrink:0"></span>
          Μετάβαση
        </span>
        <span class="d-inline-flex align-items-center gap-1" style="color:#ef4444">
          <span style="width:11px;height:11px;background:#ef4444;transform:rotate(45deg);display:inline-block;flex-shrink:0"></span>
          Περιστατικό
        </span>
        <span class="d-inline-flex align-items-center gap-1" style="color:#8b5cf6">
          <span style="background:#8b5cf6;color:#fff;font-size:9px;width:14px;height:14px;border-radius:3px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;line-height:1">★</span>
          Σημείο ενδιαφέροντος
        </span>
        <span class="d-inline-flex align-items-center gap-1">
          <span style="width:20px;border-top:2px dashed #94a3b8;display:inline-block;flex-shrink:0"></span>
          Διαδρομή προς στόχο
        </span>
      </div>
    </details>

    <!-- Teams -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-1 text-primary"></i>Ομάδες</span>
        <span class="badge bg-primary" id="teamBadge">--</span>
      </div>
      <!-- Bulk request toolbar: pick a target (all / a specific team) then request photo or GPS -->
      <div class="card-body p-2 pb-2 border-bottom">
        <div class="row g-1 align-items-end">
          <div class="col-12 col-sm-5">
            <label class="form-label small mb-1">Ζήτησε από</label>
            <select class="form-select form-select-sm" id="reqTargetTeam">
              <option value="">📢 Όλες οι ομάδες</option>
            </select>
          </div>
          <div class="col-12 col-sm-7 d-flex gap-1">
            <button type="button" class="btn btn-outline-info btn-sm flex-fill" id="bulkReqPhoto"><i class="bi bi-camera me-1"></i>Ζήτησε φωτό</button>
            <button type="button" class="btn btn-outline-success btn-sm flex-fill" id="bulkReqGps"><i class="bi bi-geo-alt me-1"></i>Ζήτησε στίγμα</button>
            <button type="button" class="btn btn-outline-warning btn-sm flex-fill" id="bulkReqVideo"><i class="bi bi-camera-video me-1"></i>Ζήτησε βίντεο</button>
          </div>
        </div>
      </div>
      <div class="card-body p-2" id="teamsBox">
        <div class="text-muted small py-3 text-center"><i class="bi bi-arrow-clockwise spin me-1"></i>Φόρτωση…</div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Teams Board + Shortages + Activity + Notes -->
  <div class="col-xl-5 d-flex flex-column gap-3">

    <!-- Teams Board -->
    <div class="card" id="teamBoardCard">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-layout-three-columns me-1 text-info"></i>Πίνακας Ομάδων</span>
        <div class="d-flex align-items-center gap-2">
          <span class="ldot" style="width:7px;height:7px" title="LIVE"></span>
          <span class="small text-muted">LIVE</span>
        </div>
      </div>
      <!-- Summary strip -->
      <div class="row g-0 border-bottom text-center" id="boardSummary">
        <div class="col-4 py-2 border-end">
          <div class="board-num text-success" id="board-present">--</div>
          <div class="text-muted board-lbl">Παρόντες</div>
        </div>
        <div class="col-4 py-2 border-end">
          <div class="board-num text-warning" id="board-transit">--</div>
          <div class="text-muted board-lbl">Αδήλωτοι</div>
        </div>
        <div class="col-4 py-2">
          <div class="board-num" id="board-approved">--</div>
          <div class="text-muted board-lbl">Εγκεκριμένοι</div>
        </div>
      </div>
      <!-- Team rows -->
      <div id="boardList" style="max-height:290px;overflow-y:auto">
        <div class="text-muted small py-3 text-center"><i class="bi bi-arrow-clockwise spin me-1"></i>Φόρτωση…</div>
      </div>
    </div>

    <!-- Photo Wall -->
    <div class="card" id="photoWallCard" style="display:none">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-images me-1 text-warning"></i>Φωτογραφίες Live</span>
        <span class="badge bg-warning text-dark" id="wallBadge">0</span>
      </div>
      <div class="card-body p-2" id="wallGrid"></div>
    </div>

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

    <!-- Resource dispatch requests (Smart Resource Dispatch) -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-box-seam me-1 text-primary"></i>Αιτήματα πόρων</span>
        <span class="badge bg-primary" id="rrBadge">0</span>
      </div>
      <div class="card-body p-2" id="resReqBox">
        <div class="text-muted small py-2 text-center">Κανένα αίτημα πόρου</div>
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
    <!-- Geo order: opens map-picker modal -->
    <div class="mb-3 pt-2 border-top">
      <button type="button" class="btn btn-sm btn-success w-100" data-bs-toggle="modal" data-bs-target="#geoOrderModal">
        <i class="bi bi-geo-alt-fill me-1"></i>Νέα εντολή στον χάρτη (μετάβαση / περιστατικό / σημείο)
      </button>
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
      <input type="text" class="form-control" id="roomInput" maxlength="500" placeholder="Μήνυμα προς όλους (<?= e($orgLabel) ?> + όλες οι ομάδες)…">
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

<!-- Βίντεο ομάδων -->
<div class="card mt-3" id="videosCard" style="display:none">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-camera-video me-1 text-warning"></i>Βίντεο πεδίου</span>
    <span class="badge bg-warning text-dark" id="videosBadge">0</span>
  </div>
  <div class="card-body p-2 d-flex flex-wrap gap-2" id="videosBox"></div>
</div>

<!-- Team video modal (isolated player — not affected by live poll) -->
<div class="modal fade" id="videoModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="videoModalLabel"><i class="bi bi-camera-video me-1"></i>Βίντεο</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2 text-center">
        <video id="videoModalPlayer" controls playsinline preload="auto" style="width:100%;max-height:70vh;border-radius:8px;background:#000"></video>
        <div id="videoModalCaption" class="small text-muted mt-2"></div>
      </div>
      <div class="modal-footer py-2">
        <a id="videoModalDl" href="#" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Αρχειοθέτηση</a>
        <button type="button" id="videoModalDel" class="btn btn-sm btn-outline-danger" style="display:none"><i class="bi bi-trash me-1"></i>Διαγραφή</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
      </div>
    </div>
  </div>
</div>

<!-- Geo-order map picker modal -->
<div class="modal fade" id="geoOrderModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-geo-alt-fill me-1 text-success"></i>Νέα εντολή στον χάρτη</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex gap-2 mb-2">
          <input type="text" class="form-control form-control-sm" id="goSearch" placeholder="Γράψε διεύθυνση (π.χ. Λ. Κνωσού 10, Ηράκλειο)…">
          <button type="button" class="btn btn-sm btn-outline-primary" id="goSearchBtn"><i class="bi bi-search me-1"></i>Αναζήτηση</button>
        </div>
        <div id="goSearchResults" class="list-group small mb-2" style="max-height:130px;overflow:auto"></div>
        <div id="goMap" style="height:300px;border-radius:8px;overflow:hidden;border:1px solid #dee2e6"></div>
        <div class="small text-muted mt-1" id="goCoordsLabel"><i class="bi bi-info-circle me-1"></i>Κάνε κλικ στον χάρτη ή ψάξε διεύθυνση για να βάλεις πινέζα.</div>
        <div class="row g-2 mt-1 align-items-end">
          <div class="col-md-4">
            <label class="form-label small mb-1">Τύπος</label>
            <select class="form-select form-select-sm" id="goKind">
              <option value="move">➡️ Μετάβαση εδώ</option>
              <option value="incident">⚠️ Περιστατικό</option>
              <option value="poi">📍 Σημείο ενδιαφέροντος</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Ομάδα-παραλήπτης</label>
            <select class="form-select form-select-sm" id="goTeam"><option value="">📢 Όλες οι ομάδες</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Σχόλιο</label>
            <input type="text" class="form-control form-control-sm" id="goNote" maxlength="200" placeholder="προαιρετικό">
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Άκυρο</button>
        <button type="button" class="btn btn-success btn-sm" id="goSendBtn"><i class="bi bi-send-fill me-1"></i>Αποστολή</button>
      </div>
    </div>
  </div>
</div>

<!-- Photo viewer modal -->
<div class="modal fade" id="photoModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="photoModalLabel"><i class="bi bi-people-fill me-1"></i>Φωτογραφία</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-1" style="background:#111">
        <img id="photoModalImg" src="" alt="" style="max-width:100%;max-height:68vh;object-fit:contain">
        <div id="photoModalMeta" class="text-light small mt-2 mb-1" style="opacity:.85"></div>
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
  var ORG_LABEL  = <?= json_encode($orgLabel, JSON_UNESCAPED_UNICODE) ?>;
  var ORG_ICON   = <?= json_encode($orgIcon, JSON_UNESCAPED_UNICODE) ?>;
  var EVENT_LC   = <?= json_encode($eventSingularLc, JSON_UNESCAPED_UNICODE) ?>;
  var IS_ADMIN   = <?= json_encode(current_role() === 'municipality_admin') ?>;
  var PLAYBOOK   = <?= json_encode($playbook ?: null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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
      cdLbl.textContent = 'Η ' + EVENT_LC + ' ολοκληρώθηκε';
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
  /* Defensive: if anything (e.g. legacy maps.js) already bound a Leaflet map to
     this container, clear it so re-init can't throw and abort the page script. */
  var opMapEl = document.getElementById('operationalMap');
  if (opMapEl && opMapEl._leaflet_id) { opMapEl._leaflet_id = undefined; opMapEl.innerHTML = ''; }
  var map          = L.map(opMapEl).setView([DEF_LAT, DEF_LNG], DEF_ZOOM);
  var markers      = {};
  var orderMarkers = {};  /* team_id → geo-order target marker */
  var orderLines   = [];  /* dashed lines from ping to target */
  var mapAutoFit   = true; /* fit to team pings on first update only */
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

  function haversineM(lat1, lng1, lat2, lng2) {
    var R = 6371000, d2r = Math.PI / 180;
    var dLat = (lat2 - lat1) * d2r, dLng = (lng2 - lng1) * d2r;
    var a = Math.sin(dLat/2)*Math.sin(dLat/2) +
            Math.cos(lat1*d2r)*Math.cos(lat2*d2r)*Math.sin(dLng/2)*Math.sin(dLng/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  function updateMap(pings, geoOrders) {
    geoOrders = geoOrders || [];

    /* ── Team GPS ping markers (circles) ── */
    var pingByTeam = {};
    var bounds = [];
    pings.forEach(function(p) {
      pingByTeam[p.team_id] = p;
      var key = 'team_' + p.team_id;
      var cls = p.age_min < 5 ? 'fresh' : p.age_min < 20 ? 'stale' : 'old';
      var teamColor    = teamColors[p.team_id] || '#94a3b8';
      var freshnessClr = cls==='fresh'?'#22c55e':cls==='stale'?'#f59e0b':'#ef4444';
      var ph         = lastPhotosByTeam[p.team_id];
      var isSilent   = cls === 'old';   /* no ping for 20+ min */
      var dotBg      = isSilent ? '#94a3b8' : teamColor;
      var dotStyle   = 'background:' + dotBg + ';width:14px;height:14px;border-radius:50%;border:2.5px solid ' + freshnessClr + ';flex-shrink:0;' +
                       (isSilent ? 'animation:silentPulse 1.2s infinite' : 'box-shadow:0 0 8px rgba(0,0,0,.3)');
      var warnBadge  = isSilent
        ? '<div style="background:#ef4444;color:#fff;font-size:8px;width:13px;height:13px;border-radius:50%;border:1.5px solid #fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:900;line-height:1">!</div>'
        : '';
      var html = '<div style="display:flex;flex-direction:row;align-items:center;gap:3px">' +
                 warnBadge +
                 '<div style="' + dotStyle + '"></div>' +
                 (ph ? '<div class="cam-badge" style="background:#0ea5e9;width:12px;height:12px;border-radius:50%;border:1.5px solid #fff;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="bi bi-camera-fill" style="font-size:6px;color:#fff"></i></div>' : '') +
                 '</div>';
      var iconW = 14 + (isSilent ? 16 : 0) + (ph ? 15 : 0);
      var icon = L.divIcon({ className:'', html:html, iconSize:[iconW, 14], iconAnchor:[7,7] });
      var photoSnippet = ph
        ? '<br><img class="photo-thumb" src="' + ph.url + '" data-url="' + ph.url + '" data-label="' + esc(ph.team_name) + '" data-at="' + esc(ph.at) + '"' +
          ' style="max-width:160px;max-height:120px;border-radius:6px;cursor:pointer;margin-top:5px;display:block">' +
          '<div style="font-size:.65rem;color:#555;margin-top:2px">' + esc(ph.time || '') + ' · κλικ για μεγέθυνση</div>'
        : '';
      var ageText = isSilent
        ? '<span style="color:#ef4444;font-weight:700">⚠ Σε σίγη — τελευταίο στίγμα ' + p.age_min + ' λεπτά πριν</span>'
        : p.age_min + ' λεπτά πριν';
      var popup = '<b>' + esc(p.team_name) + '</b><br>' + ageText + photoSnippet;
      if (markers[key]) {
        markers[key].setLatLng([p.latitude, p.longitude]);
        markers[key].setIcon(icon);
        markers[key].setPopupContent(popup);
      } else {
        markers[key] = L.marker([p.latitude, p.longitude], { icon: icon })
          .addTo(map)
          .bindPopup(popup);
      }
      bounds.push([p.latitude, p.longitude]);
    });

    /* ── Geo-order target markers (different shape per type) ── */
    /* Clear previous order markers and connector lines */
    Object.keys(orderMarkers).forEach(function(k) { map.removeLayer(orderMarkers[k]); });
    orderMarkers = {};
    orderLines.forEach(function(l) { map.removeLayer(l); });
    orderLines = [];

    var ORDER_CFG = {
      move:     { color:'#f97316', label:'ΜΕΤΑΒΑΣΗ',    icon: function(c) {
        return '<div style="width:0;height:0;border-left:11px solid transparent;border-right:11px solid transparent;border-top:22px solid ' + c + ';filter:drop-shadow(0 2px 4px rgba(0,0,0,.5))"></div>';
      }, size:[22,22], anchor:[11,22] },
      incident: { color:'#ef4444', label:'ΠΕΡΙΣΤΑΤΙΚΟ', icon: function(c) {
        return '<div style="width:18px;height:18px;background:' + c + ';transform:rotate(45deg);border:2.5px solid #fff;box-shadow:0 0 10px rgba(239,68,68,.7)"></div>';
      }, size:[22,22], anchor:[11,11] },
      poi:      { color:'#8b5cf6', label:'ΣΕΙ',         icon: function(c) {
        return '<div style="background:' + c + ';color:#fff;font-size:12px;width:20px;height:20px;border-radius:5px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 0 8px rgba(139,92,246,.7);line-height:1">★</div>';
      }, size:[20,20], anchor:[10,10] },
    };

    geoOrders.forEach(function(o) {
      var cfg = ORDER_CFG[o.pkind] || ORDER_CFG.move;
      var ping = pingByTeam[o.team_id];
      /* If team's GPS ping is already within 100 m of target, they've arrived — hide marker */
      if (ping && haversineM(ping.latitude, ping.longitude, o.lat, o.lng) < 100) return;

      var icon = L.divIcon({ className:'', html:cfg.icon(cfg.color), iconSize:cfg.size, iconAnchor:cfg.anchor });
      var popup = '<b style="color:' + cfg.color + '">' + cfg.label + '</b> → <b>' + esc(o.team_name) + '</b><br>' +
                  esc(o.body) + '<br><span style="font-size:.72rem;opacity:.65">Εστάλη ' + esc(o.sent_at) + '</span>';
      orderMarkers['o_' + o.team_id] = L.marker([o.lat, o.lng], { icon:icon })
        .addTo(map).bindPopup(popup);

      /* Dashed connector from team's current position to target */
      if (ping) {
        orderLines.push(
          L.polyline([[ping.latitude, ping.longitude], [o.lat, o.lng]], {
            color:cfg.color, weight:2, dashArray:'8 6', opacity:0.72
          }).addTo(map)
        );
      }
    });

    document.getElementById('mapBadge').textContent = pings.length + ' ομάδες';
    if (mapAutoFit && bounds.length > 1) {
      try { map.fitBounds(bounds, { padding:[30,30], maxZoom:16 }); mapAutoFit = false; } catch(e){}
    }
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
    lastTeams = teams || [];
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
      if (!teamColors[t.team_id]) {
        teamColors[t.team_id] = TEAM_COLORS[Object.keys(teamColors).length % TEAM_COLORS.length];
      }
      var tc = teamColors[t.team_id];
      if (cs && cs !== 'not_present') ci++;
      html += '<div class="col-md-6"><div class="card team-card s-' + cs + ' p-2" style="border-left-color:' + tc + '!important">' +
              '<div class="d-flex justify-content-between align-items-start">' +
              '<div><div class="fw-semibold small"><span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:' + tc + ';margin-right:5px;vertical-align:middle;flex-shrink:0"></span>' + esc(t.team_name) + '</div>' +
              '<div class="small mt-1">' + statusIcon(cs) + '</div></div>' +
              '<div class="text-end">' +
              '<span class="fw-bold" style="font-size:1.1rem">' + (t.present_people||0) + '/' + t.approved_people + '</span>' +
              '<div class="small text-muted">άτομα</div></div></div>' +
              (t.ping_lat ? '<div class="mt-1 small"><span class="ping-dot ' + pingCls + '"></span>' + (t.ping_age || '') + '</div>' : '') +
              '<div class="d-flex gap-1 mt-1">' +
              '<button type="button" class="btn btn-outline-info btn-sm flex-fill py-0 req-photo-btn" data-team="' + t.team_id + '"' + (t.photo_pending ? ' disabled' : '') + '><i class="bi bi-camera me-1"></i>' + (t.photo_pending ? 'Ζητήθηκε' : 'Φωτό') + '</button>' +
              '<button type="button" class="btn btn-outline-success btn-sm flex-fill py-0 req-gps-btn" data-team="' + t.team_id + '"' + (t.gps_pending ? ' disabled' : '') + '><i class="bi bi-geo-alt me-1"></i>' + (t.gps_pending ? 'Ζητήθηκε' : 'Στίγμα') + '</button>' +
              '</div>' +
              '</div></div>';
    });
    html += '</div>';
    box.innerHTML = html;
    document.getElementById('teamBadge').textContent = ci + '/' + teams.length + ' παρόντες';
    document.getElementById('sv-teams').textContent = teams.length;
    document.getElementById('sv-ci').textContent = ci;
    renderLegend();
  }

  /* ─── Teams Board ─── */
  function renderTeamBoard(teams, sos, stats) {
    var sosTeamIds = {};
    (sos || []).forEach(function(s) { if (s.status !== 'resolved') sosTeamIds[s.team_id] = true; });

    var present  = (stats && stats.total_present)  || 0;
    var approved = (stats && stats.total_approved) || 0;
    document.getElementById('board-present').textContent  = present;
    document.getElementById('board-transit').textContent  = Math.max(0, approved - present);
    document.getElementById('board-approved').textContent = approved;

    if (!teams || !teams.length) {
      document.getElementById('boardList').innerHTML =
        '<div class="text-muted small py-3 text-center">Δεν υπάρχουν ομάδες.</div>';
      return;
    }

    var statusOrder = { 'present_full':1, 'present_partial':2, 'not_present':3, 'none':3, 'departed':4 };
    var sorted = teams.slice().sort(function(a, b) {
      var ao = sosTeamIds[a.team_id] ? 0 : (statusOrder[a.checkin_status||'none'] || 3);
      var bo = sosTeamIds[b.team_id] ? 0 : (statusOrder[b.checkin_status||'none'] || 3);
      return ao - bo;
    });

    var html = '';
    sorted.forEach(function(t) {
      var cs      = t.checkin_status || 'not_present';
      var hasSos  = !!sosTeamIds[t.team_id];
      var dotClr  = hasSos            ? '#ef4444'
                  : cs === 'present_full'    ? '#22c55e'
                  : cs === 'present_partial' ? '#f59e0b'
                  : cs === 'departed'        ? '#94a3b8' : '#cbd5e1';
      var dotAnim = (hasSos || cs === 'present_full') ? ';animation:pp 1.8s infinite' : '';

      var pingHtml = '';
      // Use the most recent activity: GPS ping OR check-in, whichever is newer.
      var displayAge = t.ping_age_min;
      if (t.checkin_age_min !== null && t.checkin_age_min !== undefined) {
        if (displayAge === null || displayAge === undefined || t.checkin_age_min < displayAge) {
          displayAge = t.checkin_age_min;
        }
      }
      if (displayAge !== null && displayAge !== undefined) {
        var pc = displayAge < 5 ? '#22c55e' : displayAge < 20 ? '#f59e0b' : '#ef4444';
        pingHtml = '<span style="font-size:.7rem;color:' + pc + ';flex-shrink:0">' + displayAge + 'λ</span>';
      } else {
        pingHtml = '<span style="font-size:.7rem;color:#cbd5e1;flex-shrink:0">—</span>';
      }

      var sosBadge = hasSos
        ? ' <span class="badge text-bg-danger" style="font-size:.58rem;animation:sosAlarmPulse 1s infinite;vertical-align:middle">SOS</span>'
        : '';

      var ppl = (cs === 'present_full' || cs === 'present_partial')
        ? (t.present_people || 0) + '/' + t.approved_people
        : '—/' + t.approved_people;

      html += '<div class="board-row d-flex align-items-center gap-2 px-3 py-2">' +
        '<span class="board-dot" style="background:' + dotClr + dotAnim + '"></span>' +
        '<span class="small fw-semibold flex-fill" style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' +
          esc(t.team_name) + sosBadge +
        '</span>' +
        '<span class="small text-muted" style="white-space:nowrap">' + ppl + '</span>' +
        pingHtml +
        '</div>';
    });

    document.getElementById('boardList').innerHTML = html;
    if (Object.keys(sosTeamIds).length > 0) flashEl('boardList');
  }

  /* ─── Photo Wall ─── */
  var wallKnown = {};
  function renderPhotoWall(photos) {
    photos = photos || [];
    var card = document.getElementById('photoWallCard');
    if (!card) return;
    card.style.display = photos.length ? '' : 'none';
    document.getElementById('wallBadge').textContent = photos.length;
    if (!photos.length) { document.getElementById('wallGrid').innerHTML = ''; return; }

    /* newest 9 first — server typically returns oldest-first so we reverse */
    var shown = photos.slice(-9).reverse();
    var html = '<div class="wall-grid">';
    shown.forEach(function(ph) {
      var isNew = !wallKnown[ph.id];
      wallKnown[ph.id] = true;
      var cap = (ph.caption && ph.caption.trim())
        ? '<div class="wall-cap">' + esc(ph.caption) + '</div>' : '';
      html += '<div class="wall-cell photo-thumb' + (isNew ? ' wall-new' : '') + '"' +
              ' data-url="' + ph.url + '" data-label="' + esc(ph.team_name) + '" data-at="' + esc(ph.at || '') + '">' +
              '<img src="' + ph.url + '" alt="' + esc(ph.team_name) + '" loading="lazy">' +
              '<div class="wall-ovr">' +
                '<div class="wall-team">' + esc(ph.team_name) + '</div>' +
                (ph.time ? '<div class="wall-time">' + esc(ph.time) + '</div>' : '') +
                cap +
              '</div>' +
              '</div>';
    });
    html += '</div>';
    document.getElementById('wallGrid').innerHTML = html;
  }

  function renderLegend() {
    var box = document.getElementById('mapLegend');
    if (!box) return;
    var keys = Object.keys(teamColors);
    if (!keys.length) { box.style.display = 'none'; return; }
    var nameMap = {};
    lastTeams.forEach(function(t){ nameMap[t.team_id] = t.team_name; });
    var html = '';
    keys.forEach(function(tid) {
      var name = nameMap[tid];
      if (!name) return;
      html += '<div class="map-legend-chip">' +
              '<div style="width:10px;height:10px;border-radius:50%;background:' + teamColors[tid] + ';flex-shrink:0"></div>' +
              esc(name) + '</div>';
    });
    box.innerHTML = html;
    box.style.display = html ? '' : 'none';
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
      var sug = '';
      if (s.suggestions && s.suggestions.length && s.status !== 'resolved') {
        s.suggestions.forEach(function(g) {
          var itemsTxt = (g.items || []).join(', ');
          sug += '<div class="d-flex align-items-center gap-1 small mt-1">' +
                 '<i class="bi bi-lightbulb-fill text-warning flex-shrink-0"></i>' +
                 '<span class="text-truncate">' + esc(g.team_name) + ' — ' + esc(itemsTxt) +
                 (g.in_event ? ' <span class="badge text-bg-info">στη δράση</span>' : '') + '</span>' +
                 '<button type="button" class="btn btn-outline-primary btn-sm py-0 ms-auto flex-shrink-0 rr-req-btn" data-shortage="' + s.id + '" data-team="' + g.team_id + '" data-item="' + encodeURIComponent((g.items || [])[0] || '') + '">Αίτημα</button>' +
                 '</div>';
        });
      }
      html += '<div class="card sc ' + s.status + ' mb-1 p-2">' +
              '<div class="d-flex justify-content-between align-items-start">' +
              '<div><span class="badge text-bg-' + (sevCls[sev]||'secondary') + ' me-1">' + (sevLbl[sev]||sev) + '</span>' +
              '<strong class="small">' + esc(s.title) + '</strong>' +
              '<div class="text-muted small">' + esc(s.team_name) + '</div></div>' +
              '<span class="badge text-bg-' + (s.status==='open'?'danger':s.status==='acknowledged'?'warning':'success') + '">' +
              (s.status==='open'?'Ανοιχτή':s.status==='acknowledged'?'Σε γνώση':'Λύθηκε') + '</span></div>' +
              sug +
              (actions ? '<div class="d-flex gap-1 mt-2 justify-content-end">' + actions + '</div>' : '') +
              '</div>';
    });
    box.innerHTML = html;
  }

  /* ─── Resource dispatch requests (Smart Resource Dispatch) ─── */
  function renderResourceRequests(items) {
    var box = document.getElementById('resReqBox');
    if (!box) return;
    items = items || [];
    var open = items.filter(function(r){ return r.status === 'pending' || r.status === 'accepted'; }).length;
    var badge = document.getElementById('rrBadge');
    if (badge) badge.textContent = open;
    if (!items.length) {
      box.innerHTML = '<div class="text-muted small py-2 text-center">Κανένα αίτημα πόρου</div>';
      return;
    }
    var stCls = { pending:'secondary', accepted:'info', delivered:'success', declined:'danger', cancelled:'light' };
    var stLbl = { pending:'Εκκρεμεί', accepted:'Αποδεκτό', delivered:'Παραδόθηκε', declined:'Αδυναμία', cancelled:'Ακυρώθηκε' };
    var html = '';
    items.forEach(function(r) {
      var actions = '';
      if (r.status === 'pending' || r.status === 'accepted') {
        actions = '<button type="button" class="btn btn-outline-success btn-sm py-0 rr-del-btn" data-id="' + r.id + '">Παραδόθηκε</button>' +
                  '<button type="button" class="btn btn-outline-secondary btn-sm py-0 rr-can-btn" data-id="' + r.id + '">Ακύρωση</button>';
      }
      var eta = (r.status === 'accepted' && r.eta_minutes) ? ' · ' + r.eta_minutes + "'" : '';
      html += '<div class="card sc mb-1 p-2">' +
              '<div class="d-flex justify-content-between align-items-start">' +
              '<div><strong class="small">' + esc(r.item_label) + '</strong>' +
              '<div class="text-muted small">' + esc(r.team_name) + (r.shortage_title ? ' · ' + esc(r.shortage_title) : '') + '</div></div>' +
              '<span class="badge text-bg-' + (stCls[r.status] || 'secondary') + '">' + (stLbl[r.status] || r.status) + eta + '</span></div>' +
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

  /* ─── SOS audio alert ─── */
  function beepSos() {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      [0, 0.38, 0.76].forEach(function(t) {
        var o = ctx.createOscillator(), g = ctx.createGain();
        o.connect(g); g.connect(ctx.destination);
        o.type = 'sine'; o.frequency.value = 880;
        g.gain.setValueAtTime(0.45, ctx.currentTime + t);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.27);
        o.start(ctx.currentTime + t);
        o.stop(ctx.currentTime + t + 0.27);
      });
    } catch(e) {}
  }

  function checkNewSos(items) {
    var hasNew = false;
    (items || []).forEach(function(s) {
      if (s.status !== 'resolved' && !knownSosIds[s.id]) {
        knownSosIds[s.id] = true;
        if (!sosFirstLoad) hasNew = true;
      }
    });
    sosFirstLoad = false;
    if (hasNew) beepSos();
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
      var who = m.sender_role === 'command' ? ORG_LABEL : (m.team_name || m.sender_name || 'Ομάδα');
      var t = (m.created_at || '').substring(11,16);
      var tag = m.sender_role === 'command' ? (m.team_id ? esc(m.team_name || '') : '📢 Όλες') : '';
      var ackTxt = '';
      if (m.kind === 'order') {
        ackTxt = m.acknowledged_at ? ' · <span style="color:#16a34a">✓ Επιβεβαιώθηκε</span>' : ' · <span style="opacity:.7">αναμονή ACK</span>';
      }
      return '<div class="cmsg ' + cls + '"><div>' +
             (m.kind === 'order' ? '<strong>📋 ΕΝΤΟΛΗ:</strong> ' : '') + esc(m.body || '') + '</div>' +
             '<div class="cmsg-meta">' + esc(who) + (tag ? ' → ' + tag : '') + ' · ' + t + ackTxt + '</div></div>';
    }).join('');
    box.scrollTop = box.scrollHeight;
  }

  /* ─── Shared operations room ─── */
  function renderRoom(msgs) {
    var box = document.getElementById('roomThread');
    if (!box) return;
    msgs = msgs || [];
    document.getElementById('roomBadge').textContent = msgs.length;
    if (!msgs.length) { box.innerHTML = '<div class="text-muted small text-center">Κανένα μήνυμα ακόμη.</div>'; return; }
    box.innerHTML = msgs.map(function (m) {
      var cmd = m.sender_role === 'command';
      var who = cmd ? ORG_LABEL : (m.sender_label || m.team_name || m.sender_name || 'Ομάδα');
      var t = (m.created_at || '').substring(11, 16);
      return '<div class="cmsg ' + (cmd ? 'cmsg-command' : 'cmsg-team') + '"><div>' + esc(m.body || '') +
             '</div><div class="cmsg-meta">' + esc(who) + ' · ' + t + '</div></div>';
    }).join('');
    box.scrollTop = box.scrollHeight;
  }
  (function () {
    var inp = document.getElementById('roomInput');
    var btn = document.getElementById('roomSend');
    function send() { var b = (inp.value || '').trim(); if (!b) return; inp.value = ''; postForm('/operations/events/' + EID + '/room', { body: b }).then(pollStatus); }
    if (btn) btn.addEventListener('click', send);
    if (inp) inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); send(); } });
  })();

  /* ─── Recipient + request-target selects (preserve current selection) ─── */
  function populateTeamSelect(teams) {
    if (!teams) return;
    [['cmsgTeam', '📢 Όλες οι ομάδες (broadcast)'], ['reqTargetTeam', '📢 Όλες οι ομάδες']].forEach(function(pair){
      var sel = document.getElementById(pair[0]);
      if (!sel) return;
      var cur = sel.value;
      var opts = '<option value="">' + pair[1] + '</option>';
      teams.forEach(function(t) { opts += '<option value="' + t.team_id + '">' + esc(t.team_name) + '</option>'; });
      sel.innerHTML = opts;
      sel.value = cur;
    });
  }

  /* ─── POST helper (form-encoded, JSON response) ─── */
  function postForm(path, fields) {
    var body = '_token=' + encodeURIComponent(CSRF);
    Object.keys(fields || {}).forEach(function(k){ body += '&' + k + '=' + encodeURIComponent(fields[k]); });
    return fetch(BASE + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    }).then(function(r){ return r.json().catch(function(){ return {}; }); });
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

  function renderPendingApps(apps) {
    if (!IS_ADMIN) return;
    var row   = document.getElementById('pendingAppsRow');
    var box   = document.getElementById('pendingAppsBox');
    var badge = document.getElementById('pendingAppsBadge');
    if (!row || !box) return;
    apps = apps || [];
    if (badge) badge.textContent = apps.length;
    if (!apps.length) { row.style.display = 'none'; return; }
    row.style.display = '';
    // Save values the admin may have already edited before the poll re-renders.
    var saved = {};
    box.querySelectorAll('[data-app-id]').forEach(function(el) {
      var inp = el.querySelector('.app-people-inp');
      if (inp) saved[el.getAttribute('data-app-id')] = inp.value;
    });
    var html = '';
    apps.forEach(function(a) {
      html += '<div class="d-flex align-items-center gap-2 py-2 border-bottom" style="flex-wrap:wrap" data-app-id="' + a.id + '">' +
              '<div style="flex:1;min-width:160px">' +
                '<div class="fw-semibold small">' + esc(a.team_name) + '</div>' +
                '<div class="text-muted" style="font-size:.72rem">Προσφορά: <strong>' + (a.offered_people || 0) + ' άτομα</strong>' +
                (a.comment ? ' &middot; «' + esc(a.comment) + '»' : '') + '</div>' +
              '</div>' +
              '<div class="d-flex align-items-center gap-1">' +
                '<span class="small text-muted">Εγκρίνω</span>' +
                '<input type="number" class="form-control form-control-sm text-center app-people-inp" min="1" max="999" value="' + (a.offered_people || 1) + '" style="width:64px">' +
                '<span class="small text-muted">άτομα</span>' +
              '</div>' +
              '<div class="d-flex gap-1">' +
                '<button type="button" class="btn btn-sm btn-success app-approve-btn" data-id="' + a.id + '"><i class="bi bi-check-lg me-1"></i>Έγκριση</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger app-reject-btn" data-id="' + a.id + '"><i class="bi bi-x-lg me-1"></i>Απόρριψη</button>' +
              '</div>' +
              '</div>';
    });
    box.innerHTML = html;
    // Restore any values the admin had changed before the re-render.
    box.querySelectorAll('[data-app-id]').forEach(function(el) {
      var v = saved[el.getAttribute('data-app-id')];
      if (v !== undefined) { var inp = el.querySelector('.app-people-inp'); if (inp) inp.value = v; }
    });
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
    renderTeamBoard(d.teams, d.sos, d.stats);
    renderShortages(d.shortages);
    renderResourceRequests(d.resource_requests || []);
    renderActivity(d.activity);
    renderNotes(d.notes);
    if (d.pending_apps !== undefined) renderPendingApps(d.pending_apps);
    if (d.teams) populateTeamSelect(d.teams);
    checkNewSos(d.sos);
    renderSos(d.sos);
    renderMessages(d.messages);
    renderRoom(d.room);
    /* map pings included in SSE snapshot — photos first so GPS popup can show them */
    if (d.photos) { updatePhotos(d.photos); renderPhotoWall(d.photos); }
    if (d.videos) { renderVideos(d.videos); }
    if (d.pings) updateMap(d.pings, d.geo_orders || []);
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
      .then(function(d) { applySnapshot(d); lastSnapshotTs = Date.now(); })
      .catch(function(){});
  }

  function pollLocations() {
    fetch(BASE + '/operations/events/' + EID + '/locations')
      .then(function(r){ return r.json(); })
      .then(function(d) { if (d.ok) { updatePhotos(d.photos); updateMap(d.pings, d.geo_orders || []); } })
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

  /* ─── Team color palette (auto-assigned, persisted per session) ─── */
  var TEAM_COLORS = ['#ef4444','#3b82f6','#f59e0b','#8b5cf6','#10b981',
                     '#f97316','#06b6d4','#ec4899','#84cc16','#6366f1'];
  var teamColors = {};   /* team_id → hex color */

  /* ─── Photos: request, map markers, list, modal ─── */
  var photoMarkers     = {};
  var lastPhotosByTeam = {};   /* team_id → latest photo, for GPS popup */
  var lastTeams    = [];   /* latest team list, for bulk photo/GPS requests */
  var knownSosIds  = {};   /* sos id → true, for new-alert detection */
  var sosFirstLoad = true; /* suppress beep on initial page load */

  function requestPhoto(teamId, btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>…'; }
    var fd = new FormData(); fd.append('_token', CSRF); fd.append('team_id', teamId);
    return fetch(BASE + '/operations/events/' + EID + '/request-photo', { method:'POST', body: fd, headers:{ 'Accept':'application/json' } })
      .then(function(r){ return r.json(); })
      .then(function(){ if (btn) { btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Ζητήθηκε'; } })
      .catch(function(){ if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-camera me-1"></i>Φωτό'; } });
  }

  function requestGps(teamId, btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>…'; }
    var fd = new FormData(); fd.append('_token', CSRF); fd.append('team_id', teamId);
    return fetch(BASE + '/operations/events/' + EID + '/request-gps', { method:'POST', body: fd, headers:{ 'Accept':'application/json' } })
      .then(function(r){ return r.json(); })
      .then(function(){ if (btn) { btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Ζητήθηκε'; } })
      .catch(function(){ if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i>Στίγμα'; } });
  }

  /* Bulk request: target = selected team in #reqTargetTeam, or all current teams when blank. */
  function bulkRequest(kind, btn) {
    var sel = document.getElementById('reqTargetTeam');
    var target = sel ? sel.value : '';
    var fn = kind === 'gps' ? requestGps : requestPhoto;
    var label = kind === 'gps' ? 'στίγμα GPS' : 'φωτογραφία';
    var ids = target ? [target] : lastTeams.map(function(t){ return t.team_id; });
    if (!ids.length) return;
    if (!target && !confirm('Αποστολή αιτήματος (' + label + ') σε ΟΛΕΣ τις ομάδες (' + ids.length + ');')) return;
    if (btn) { btn.disabled = true; }
    Promise.all(ids.map(function(id){ return fn(id, null); }))
      .then(function(){ pollStatus(); })
      .finally(function(){ if (btn) { btn.disabled = false; } });
  }
  document.getElementById('bulkReqPhoto').addEventListener('click', function(){ bulkRequest('photo', this); });
  document.getElementById('bulkReqGps').addEventListener('click', function(){ bulkRequest('gps', this); });

  /* ─── Video: request (single/broadcast) + render received clips ─── */
  function requestVideo(target, instructions) {
    var fd = new FormData(); fd.append('_token', CSRF);
    if (target) { fd.append('team_id', target); } else { fd.append('all', '1'); }
    if (instructions) { fd.append('instructions', instructions); }
    fd.append('max_seconds', '40');
    return fetch(BASE + '/operations/events/' + EID + '/request-video', { method:'POST', body: fd, headers:{ 'Accept':'application/json' } })
      .then(function(r){ return r.json().catch(function(){ return {}; }); });
  }
  document.getElementById('bulkReqVideo').addEventListener('click', function(){
    var sel = document.getElementById('reqTargetTeam');
    var target = sel ? sel.value : '';
    var n = target ? 1 : lastTeams.length;
    if (!target && !n) return;
    var ins = prompt('Οδηγίες βίντεο (προαιρετικά) — π.χ. «30΄΄, δείξε το σημείο και πες τι κάνετε»:', '');
    if (ins === null) return; /* cancelled */
    if (!target && !confirm('Αποστολή αιτήματος βίντεο σε ΟΛΕΣ τις ομάδες (' + n + ');')) return;
    var btn = this; btn.disabled = true;
    requestVideo(target, ins).then(function(){ pollStatus(); }).finally(function(){ btn.disabled = false; });
  });

  function renderVideos(videos) {
    videos = videos || [];
    var card = document.getElementById('videosCard');
    if (!card) return;
    card.style.display = videos.length ? '' : 'none';
    document.getElementById('videosBadge').textContent = videos.length;
    var box = document.getElementById('videosBox');
    if (!videos.length) { box.innerHTML = ''; return; }
    box.innerHTML = videos.map(function(v){
      var dleft = (v.days_left != null) ? '<span class="badge bg-secondary">διαγραφή σε ' + v.days_left + 'ημ.</span>' : '';
      var dur   = (v.duration != null) ? (v.duration + '″') : '';
      var del   = IS_ADMIN ? '<button type="button" class="btn btn-sm btn-outline-danger py-0 vid-del-btn" data-id="' + v.id + '" title="Διαγραφή"><i class="bi bi-trash"></i></button>' : '';
      return '<div class="border rounded p-2" style="width:230px;background:#0d1a1a">' +
        '<div class="vid-open" data-url="' + v.url + '" data-dl="' + v.download + '" data-id="' + v.id + '" data-team="' + esc(v.team_name) + '" data-time="' + esc(v.time) + '" data-caption="' + esc(v.caption || '') + '" style="position:relative;cursor:pointer;height:128px;border-radius:6px;background:#000;display:flex;align-items:center;justify-content:center">' +
          '<i class="bi bi-play-circle-fill" style="font-size:44px;color:#fff;opacity:.9"></i>' +
          (dur ? '<span style="position:absolute;bottom:4px;right:6px;font-size:11px;color:#e5e7eb;background:rgba(0,0,0,.5);padding:0 4px;border-radius:4px">' + dur + '</span>' : '') +
        '</div>' +
        '<div class="small mt-1 d-flex justify-content-between align-items-center"><b>' + esc(v.team_name) + '</b><span class="text-muted">' + esc(v.time) + '</span></div>' +
        '<div class="d-flex gap-1 mt-1 align-items-center flex-wrap">' + dleft +
          '<a href="' + v.download + '" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-download"></i> Αρχειοθέτηση</a>' + del + '</div>' +
        (v.caption ? '<div class="small text-muted mt-1">' + esc(v.caption) + '</div>' : '') +
        '</div>';
    }).join('');
  }

  function openPhoto(url, team, at) {
    document.getElementById('photoModalImg').src = url;
    document.getElementById('photoModalLabel').innerHTML =
      '<i class="bi bi-people-fill me-1"></i>' + esc(team || 'Άγνωστη ομάδα');
    var meta = document.getElementById('photoModalMeta');
    if (meta) { meta.textContent = (team ? 'Ομάδα: ' + team : 'Ομάδα: —') + (at ? '   ·   ' + at : ''); }
    document.getElementById('photoModalDl').href = url;
    if (window.bootstrap) {
      var modalEl = document.getElementById('photoModal');
      /* Escape any CSS-transformed ancestor (transforms break position:fixed, which
         makes the modal/backdrop drift off-screen). Re-parent to <body> so it
         positions correctly and is centered/scrollable within the viewport. */
      if (modalEl.parentNode !== document.body) { document.body.appendChild(modalEl); }
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  function updatePhotos(photos) {
    photos = photos || [];
    Object.keys(photoMarkers).forEach(function(k){ map.removeLayer(photoMarkers[k]); });
    photoMarkers = {};
    lastPhotosByTeam = {};
    var card = document.getElementById('photosCard');
    var box  = document.getElementById('photosBox');
    if (!card || !box) return;
    document.getElementById('photosBadge').textContent = photos.length;
    card.style.display = photos.length ? '' : 'none';
    var html = '';
    photos.forEach(function(ph){
      lastPhotosByTeam[ph.team_id] = ph;
      var border = ph.lat !== null && ph.lng !== null ? '#0ea5e9' : '#94a3b8';
      html += '<div style="display:flex;flex-direction:column;align-items:center;gap:2px">' +
              '<img class="photo-thumb" src="' + ph.url + '" data-url="' + ph.url + '" data-label="' + esc(ph.team_name) + '" data-at="' + esc(ph.at) +
              '" title="' + esc(ph.team_name) + ' · ' + esc(ph.at) + (ph.lat === null ? ' · χωρίς τοποθεσία' : '') +
              '" style="width:70px;height:70px;object-fit:cover;border-radius:8px;cursor:pointer;border:2px solid ' + border + '">' +
              '<div style="font-size:.62rem;text-align:center;max-width:78px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + esc(ph.team_name) + '</div>' +
              '<div style="font-size:.6rem;color:#94a3b8">' + esc(ph.time || '') + '</div>' +
              '</div>';
      if (ph.lat !== null && ph.lng !== null) {
        var icon = L.divIcon({ className:'', html:'<div style="background:#0ea5e9;width:24px;height:24px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 0 8px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center"><i class="bi bi-camera-fill" style="color:#fff;font-size:11px;transform:rotate(45deg)"></i></div>', iconSize:[24,24], iconAnchor:[12,22] });
        var m = L.marker([ph.lat, ph.lng], { icon: icon });
        m.bindPopup('<div style="text-align:center"><img class="photo-thumb" src="' + ph.url + '" data-url="' + ph.url + '" data-label="' + esc(ph.team_name) + '" data-at="' + esc(ph.at) + '" style="max-width:170px;max-height:130px;border-radius:6px;cursor:pointer"><br><b>' + esc(ph.team_name) + '</b><br><span class="text-muted" style="font-size:.72rem">' + esc(ph.at) + '</span></div>');
        m.addTo(map);
        photoMarkers[ph.id] = m;
      }
    });
    box.innerHTML = html;
  }

  /* delegated clicks: request button + photo thumbnails */
  document.addEventListener('click', function(e){
    var thumb = e.target.closest ? e.target.closest('.photo-thumb') : null;
    if (thumb) { openPhoto(thumb.getAttribute('data-url'), thumb.getAttribute('data-label'), thumb.getAttribute('data-at')); return; }
    var rb = e.target.closest ? e.target.closest('.req-photo-btn') : null;
    if (rb && !rb.disabled) { requestPhoto(rb.getAttribute('data-team'), rb); return; }
    var gb = e.target.closest ? e.target.closest('.req-gps-btn') : null;
    if (gb && !gb.disabled) { requestGps(gb.getAttribute('data-team'), gb); }
  });

  /* ─── SSE connection ─── */
  var sseEs = null;
  var lastSnapshotTs = 0;   /* when we last received fresh data (SSE or poll) */

  function setSseBadge(state) {
    var b = document.getElementById('liveBadge');
    if (!b) return;
    if (state === 'live') {
      b.textContent = '◉ LIVE';
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
      try { applySnapshot(JSON.parse(evt.data)); lastSnapshotTs = Date.now(); setSseBadge('live'); } catch(e){}
    });
    /* The stream sends ONE snapshot then closes (shared-hosting friendly), so the
       browser reconnects every few seconds. That normal close must NOT look like an
       error — only flag "reconnecting" if no snapshot has arrived for a while. */
    sseEs.onerror = function() {
      if (Date.now() - lastSnapshotTs > 12000) { setSseBadge('reconnecting'); }
    };
  }

  /* Keep the badge honest based on data freshness, not the per-cycle reconnect. */
  setInterval(function () {
    if (lastSnapshotTs) {
      setSseBadge(Date.now() - lastSnapshotTs <= 12000 ? 'live' : 'reconnecting');
    }
  }, 4000);

  /* Safety net: if SSE ever stops delivering (some hosts block streaming), poll so
     the board still refreshes. pollStatus() updates lastSnapshotTs on success. */
  setInterval(function () {
    if (!lastSnapshotTs || Date.now() - lastSnapshotTs > 15000) { pollStatus(); pollLocations(); }
  }, 10000);

  /* ─── Comms composer ─── */
  function sendCmsg(kind) {
    var bodyEl = document.getElementById('cmsgBody');
    var teamEl = document.getElementById('cmsgTeam');
    var body = (bodyEl.value || '').trim();
    if (!body) { bodyEl.focus(); return; }
    if (kind === 'order' && !confirm('Αποστολή ως ΕΝΤΟΛΗ;\nΗ ομάδα θα κληθεί να επιβεβαιώσει τη λήψη.')) return;
    postForm('/operations/events/' + EID + '/message', { body: body, kind: kind, team_id: teamEl.value })
      .then(function(){ bodyEl.value = ''; pollStatus(); });
  }
  document.getElementById('cmsgSendMsg').addEventListener('click', function(){ sendCmsg('message'); });
  document.getElementById('cmsgSendOrder').addEventListener('click', function(){ sendCmsg('order'); });
  document.getElementById('cmsgBody').addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); sendCmsg('message'); } });

  /* ─── Mission playbook helpers ─── */
  (function(){
    if (!PLAYBOOK) return;
    var key = 'syndrasi.playbook.' + EID;
    var saved = {};
    try { saved = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch(e) { saved = {}; }

    document.querySelectorAll('.js-pb-check').forEach(function(cb){
      var row = cb.closest('.playbook-check');
      var idx = row ? row.dataset.pbIndex : '';
      cb.checked = saved[idx] === true;
      if (row) row.classList.toggle('done', cb.checked);
      cb.addEventListener('change', function(){
        saved[idx] = cb.checked;
        if (row) row.classList.toggle('done', cb.checked);
        localStorage.setItem(key, JSON.stringify(saved));
      });
    });

    document.querySelectorAll('.js-pb-msg').forEach(function(btn){
      btn.addEventListener('click', function(){
        var input = document.getElementById('cmsgBody');
        if (!input) return;
        input.value = btn.dataset.message || btn.textContent.trim();
        input.focus();
      });
    });
  })();

  /* ─── Team video: open in isolated modal (poll-safe) + delete ─── */
  (function(){
    var vModalEl = document.getElementById('videoModal');
    if (!vModalEl) return;
    var player = document.getElementById('videoModalPlayer');

    function openVideo(d){
      player.src = d.url;
      document.getElementById('videoModalLabel').innerHTML = '<i class="bi bi-camera-video me-1"></i>' + esc(d.team || 'Βίντεο') + ' · ' + esc(d.time || '');
      document.getElementById('videoModalCaption').textContent = d.caption || '';
      document.getElementById('videoModalDl').href = d.dl;
      var delBtn = document.getElementById('videoModalDel');
      if (IS_ADMIN) { delBtn.style.display = ''; delBtn.dataset.id = d.id; } else { delBtn.style.display = 'none'; }
      bootstrap.Modal.getOrCreateInstance(vModalEl).show();
      try { player.load(); player.play().catch(function(){}); } catch(e){}
    }
    vModalEl.addEventListener('hidden.bs.modal', function(){ try { player.pause(); player.removeAttribute('src'); player.load(); } catch(e){} });

    function delVideo(id, closeModal){
      if (!id) return;
      if (!confirm('Διαγραφή βίντεο; Δεν αναιρείται.')) return;
      postForm('/operations/videos/' + id + '/delete', {}).then(function(){
        if (closeModal) { try { bootstrap.Modal.getOrCreateInstance(vModalEl).hide(); } catch(e){} }
        pollStatus();
      });
    }

    document.addEventListener('click', function(e){
      if (!e.target || !e.target.closest) return;
      var del = e.target.closest('.vid-del-btn');
      if (del) { e.stopPropagation(); delVideo(del.dataset.id, false); return; }
      var mdel = e.target.closest('#videoModalDel');
      if (mdel) { delVideo(mdel.dataset.id, true); return; }
      var open = e.target.closest('.vid-open');
      if (open) { openVideo({ url: open.dataset.url, dl: open.dataset.dl, id: open.dataset.id, team: open.dataset.team, time: open.dataset.time, caption: open.dataset.caption }); }
    });
  })();

  /* ─── Geo-order modal: map picker + address search (Nominatim, GR) ─── */
  (function(){
    var modalEl = document.getElementById('geoOrderModal');
    if (!modalEl) return;
    var goMap = null, goMarker = null, goLat = null, goLng = null;

    function setPoint(lat, lng, label){
      goLat = lat; goLng = lng;
      if (!goMarker) { goMarker = L.marker([lat,lng]).addTo(goMap); }
      else { goMarker.setLatLng([lat,lng]); }
      goMap.setView([lat,lng], Math.max(goMap.getZoom() || 0, 15));
      document.getElementById('goCoordsLabel').innerHTML =
        '<i class="bi bi-pin-map-fill text-success me-1"></i>' + (label ? esc(label) + ' — ' : '') +
        lat.toFixed(5) + ', ' + lng.toFixed(5);
    }

    modalEl.addEventListener('shown.bs.modal', function(){
      if (!goMap) {
        goMap = L.map('goMap').setView([DEF_LAT, DEF_LNG], DEF_ZOOM);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(goMap);
        goMap.on('click', function(e){ setPoint(e.latlng.lat, e.latlng.lng); });
      }
      setTimeout(function(){ goMap.invalidateSize(); goMap.setView([DEF_LAT, DEF_LNG], DEF_ZOOM); }, 250);
      var sel = document.getElementById('goTeam');
      if (sel && typeof lastTeams !== 'undefined' && lastTeams) {
        var cur = sel.value;
        var opts = '<option value="">📢 Όλες οι ομάδες</option>';
        lastTeams.forEach(function(t){ opts += '<option value="' + t.team_id + '">' + esc(t.team_name) + '</option>'; });
        sel.innerHTML = opts; sel.value = cur;
      }
    });

    function doSearch(){
      var q = (document.getElementById('goSearch').value || '').trim();
      if (!q) return;
      var box = document.getElementById('goSearchResults');
      box.innerHTML = '<div class="text-muted p-1">Αναζήτηση…</div>';
      fetch('https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&countrycodes=gr&limit=5&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(arr){
          if (!arr || !arr.length) { box.innerHTML = '<div class="text-muted p-1">Δεν βρέθηκε διεύθυνση.</div>'; return; }
          box.innerHTML = arr.map(function(it){
            return '<button type="button" class="list-group-item list-group-item-action py-1" data-lat="' + it.lat + '" data-lng="' + it.lon + '">' + esc(it.display_name) + '</button>';
          }).join('');
        })
        .catch(function(){ box.innerHTML = '<div class="text-danger p-1">Σφάλμα αναζήτησης.</div>'; });
    }
    document.getElementById('goSearchBtn').addEventListener('click', doSearch);
    document.getElementById('goSearch').addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
    document.getElementById('goSearchResults').addEventListener('click', function(e){
      var b = e.target.closest('[data-lat]'); if (!b) return;
      setPoint(parseFloat(b.dataset.lat), parseFloat(b.dataset.lng), b.textContent);
      document.getElementById('goSearchResults').innerHTML = '';
      document.getElementById('goSearch').value = b.textContent;
    });

    document.getElementById('goSendBtn').addEventListener('click', function(){
      if (goLat === null || goLng === null) { alert('Όρισε σημείο: κλικ στον χάρτη ή αναζήτηση διεύθυνσης.'); return; }
      var pkind = document.getElementById('goKind').value;
      if (pkind === 'incident' && !confirm('Αποστολή ΠΕΡΙΣΤΑΤΙΚΟΥ;\nΘα σταλεί forced push + SMS στην ομάδα.')) return;
      var btn = this; btn.disabled = true;
      postForm('/operations/events/' + EID + '/message', {
        point_kind: pkind, latitude: goLat, longitude: goLng,
        team_id: document.getElementById('goTeam').value,
        body: document.getElementById('goNote').value
      }).then(function(){
        btn.disabled = false;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        document.getElementById('goNote').value = '';
        if (goMarker && goMap) { goMap.removeLayer(goMarker); goMarker = null; }
        goLat = goLng = null;
        document.getElementById('goCoordsLabel').innerHTML = '<i class="bi bi-info-circle me-1"></i>Κάνε κλικ στον χάρτη ή ψάξε διεύθυνση για να βάλεις πινέζα.';
        document.getElementById('goSearchResults').innerHTML = '';
        pollStatus();
      }).catch(function(){ btn.disabled = false; });
    });
  })();

  /* ─── SOS / shortage / pending-application action buttons (delegated) ─── */
  document.addEventListener('click', function(e){
    if (!e.target || !e.target.closest) return;
    var b;
    if ((b = e.target.closest('.sos-ack-btn'))) { b.disabled = true; postForm('/sos/' + b.dataset.id + '/acknowledge', {}).then(pollStatus); }
    else if ((b = e.target.closest('.sos-res-btn'))) { if (confirm('Κλείσιμο SOS;')) { b.disabled = true; postForm('/sos/' + b.dataset.id + '/resolve', {}).then(pollStatus); } }
    else if ((b = e.target.closest('.sh-ack-btn'))) { b.disabled = true; postForm('/shortages/' + b.dataset.id + '/acknowledge', {}).then(pollStatus); }
    else if ((b = e.target.closest('.sh-res-btn'))) { b.disabled = true; postForm('/shortages/' + b.dataset.id + '/resolve', {}).then(pollStatus); }
    else if ((b = e.target.closest('.rr-req-btn'))) { b.disabled = true; postForm('/operations/events/' + EID + '/resource-request', { team_id: b.dataset.team, shortage_id: b.dataset.shortage, item_label: decodeURIComponent(b.dataset.item) }).then(function(d){ if (d && d.ok === false && d.error) alert(d.error); pollStatus(); }); }
    else if ((b = e.target.closest('.rr-del-btn'))) { b.disabled = true; postForm('/operations/resource-requests/' + b.dataset.id + '/delivered', {}).then(pollStatus); }
    else if ((b = e.target.closest('.rr-can-btn'))) { b.disabled = true; postForm('/operations/resource-requests/' + b.dataset.id + '/cancel', {}).then(pollStatus); }
    else if (IS_ADMIN && (b = e.target.closest('.app-approve-btn'))) {
      var appRow = b.closest('[data-app-id]');
      var inp    = appRow ? appRow.querySelector('.app-people-inp') : null;
      var people = inp ? parseInt(inp.value, 10) : 1;
      if (isNaN(people) || people < 1) people = 1;
      b.disabled = true;
      postForm('/operations/events/' + EID + '/applications/' + b.dataset.id + '/approve',
               { approved_people: people })
        .then(function(d) {
          if (d.ok) { if (appRow) appRow.style.opacity = '.3'; setTimeout(pollStatus, 400); }
          else { b.disabled = false; alert(d.error || 'Σφάλμα.'); }
        }).catch(function(){ b.disabled = false; });
    }
    else if (IS_ADMIN && (b = e.target.closest('.app-reject-btn'))) {
      var teamRow  = b.closest('[data-app-id]');
      var nameEl   = teamRow ? teamRow.querySelector('.fw-semibold') : null;
      if (!confirm('Απόρριψη αίτησης' + (nameEl ? ' από «' + nameEl.textContent.trim() + '»' : '') + ';')) return;
      b.disabled = true;
      postForm('/operations/events/' + EID + '/applications/' + b.dataset.id + '/reject', {})
        .then(function(d) {
          if (d.ok) { if (teamRow) teamRow.style.opacity = '.3'; setTimeout(pollStatus, 400); }
          else { b.disabled = false; alert(d.error || 'Σφάλμα.'); }
        }).catch(function(){ b.disabled = false; });
    }
  });

  /* ─── Boot ─── */
  connectSSE();
  pollStatus();
  /* Locations are now included in the SSE snapshot.
     pollLocations() remains available for the manual refresh button. */

})();
</script>
