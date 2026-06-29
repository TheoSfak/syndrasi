<?php
/* ── War Room — Multi-event operational map ──────────────────────────────── */
$defLat   = (float)($mapDefLat  ?: 38.0);
$defLng   = (float)($mapDefLng  ?: 23.7);
$defZoom  = (int)  ($mapDefZoom ?: 11);
$initJson = json_encode($snapshot ?? ['ok'=>true,'events'=>[],'totals'=>[]], JSON_UNESCAPED_UNICODE);
?>
<style>
.wr-header{background:linear-gradient(135deg,#0b1120 0%,#7f1d1d 55%,#b91c1c 100%);color:#fff;padding:1rem 1.3rem;border-radius:18px;margin-bottom:1rem;box-shadow:0 8px 40px rgba(0,0,0,.3);}
.wr-stat{display:flex;flex-direction:column;align-items:center;padding:.5rem .9rem;border-radius:14px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.2);min-width:90px;}
.wr-stat .val{font-size:1.6rem;font-weight:900;line-height:1;}
.wr-stat .lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.7px;opacity:.85;margin-top:2px;white-space:nowrap;}
.wr-stat.alert{border-color:rgba(255,255,255,.5);background:rgba(0,0,0,.25);}
.wr-stat.alert .val{color:#fecaca;}
.wr-grid{display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;}
.wr-list{flex:1 1 360px;max-width:430px;max-height:74vh;overflow-y:auto;padding-right:.25rem;}
.wr-mapcol{flex:2 1 480px;min-width:300px;}
#warMap{height:74vh;border-radius:18px;box-shadow:0 6px 30px rgba(0,0,0,.18);}
.wr-mapwrap.fullscreen{position:fixed;inset:0;z-index:2000;background:#fff;padding:10px;}
.wr-mapwrap.fullscreen #warMap{height:100%!important;border-radius:0;}
.ev-card{border-radius:14px!important;border-left:5px solid #94a3b8!important;cursor:pointer;transition:transform .15s,box-shadow .15s;}
.ev-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.13)!important;}
.ev-card.sev-ok{border-left-color:#22c55e!important;}
.ev-card.sev-warn{border-left-color:#f59e0b!important;}
.ev-card.sev-crit{border-left-color:#ef4444!important;}
.cov-bar{height:7px;border-radius:6px;background:#e5e7eb;overflow:hidden;}
.cov-fill{height:100%;border-radius:6px;transition:width .5s;}
.wr-empty{padding:2.5rem 1rem;text-align:center;color:#6b7280;}
.sse-flash{animation:wrFlash .8s ease;}
@keyframes wrFlash{0%{background:rgba(34,197,94,.25);}100%{background:transparent;}}
.live-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:#4ade80;margin-right:5px;animation:wrPulse 1.6s ease-in-out infinite;}
@keyframes wrPulse{0%,100%{box-shadow:0 0 0 0 rgba(74,222,128,.6);}60%{box-shadow:0 0 0 7px rgba(74,222,128,0);}}
.wr-evpin{font-weight:800;font-size:11px;color:#fff;text-align:center;line-height:26px;width:26px;height:26px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 8px rgba(0,0,0,.4);}
</style>

<div class="wr-header">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <h1 class="h4 mb-1 fw-bold"><i class="bi bi-diagram-3 me-2"></i>Κέντρο Συντονισμού — Όλες οι Δράσεις</h1>
      <div class="small" style="opacity:.85">Συνολική εικόνα ενεργών επιχειρήσεων σε πραγματικό χρόνο.
        <span id="liveBadge" class="badge bg-success ms-1">◉ LIVE SSE</span>
        <span class="ms-2"><i class="bi bi-clock me-1"></i><span id="wrClock">—</span></span>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <div class="wr-stat"><div class="val" id="t-events">0</div><div class="lbl">Ενεργές</div></div>
      <div class="wr-stat"><div class="val" id="t-present">0</div><div class="lbl">Παρόντες</div></div>
      <div class="wr-stat"><div class="val" id="t-cov">0%</div><div class="lbl">Κάλυψη</div></div>
      <div class="wr-stat" id="t-sh-wrap"><div class="val" id="t-sh">0</div><div class="lbl">Ελλείψεις</div></div>
      <button id="btnFull" class="btn btn-light btn-sm" title="Πλήρης οθόνη χάρτη"><i class="bi bi-arrows-fullscreen"></i></button>
    </div>
  </div>
</div>

<div class="wr-grid">
  <div class="wr-list" id="evList">
    <div class="wr-empty">Φόρτωση…</div>
  </div>
  <div class="wr-mapcol">
    <div class="wr-mapwrap" id="mapWrap">
      <div id="warMap"></div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  'use strict';
  var BASE     = <?= json_encode(url('')) ?>;
  var DEF_LAT  = <?= $defLat ?>, DEF_LNG = <?= $defLng ?>, DEF_ZOOM = <?= $defZoom ?>;
  var INIT     = <?= json_encode(json_decode($initJson, true), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  var map = L.map('warMap').setView([DEF_LAT, DEF_LNG], DEF_ZOOM);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'© OpenStreetMap', maxZoom:19 }).addTo(map);

  var evMarkers = {};   // event_id -> marker
  var teamLayer = L.layerGroup().addTo(map);
  var prevShortById = {};
  var lastSnapshotAt = Date.now();
  var pollInFlight = false;

  function esc(s){ return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }
  function pad(n){ return n<10 ? '0'+n : ''+n; }

  function severity(ev){
    if (ev.stats.open_shortages > 0) return 'crit';
    if (ev.stats.coverage < 60)      return 'warn';
    return 'ok';
  }
  var sevColor = { ok:'#22c55e', warn:'#f59e0b', crit:'#ef4444' };
  var statusLbl = { active:'ΕΝΕΡΓΗ', confirmed:'ΣΕ ΕΞΕΛΙΞΗ', review:'ΣΕ ΕΞΕΛΙΞΗ' };

  function remaining(end_ts){
    var d = end_ts - Date.now();
    if (d <= 0) return 'έληξε';
    var s = Math.floor(d/1000), h = Math.floor(s/3600), m = Math.floor((s%3600)/60);
    return (h>0 ? h+'ω ' : '') + m + 'λ';
  }

  function evPin(ev){
    var sev = severity(ev);
    return L.divIcon({ className:'', iconSize:[26,26], iconAnchor:[13,13],
      html:'<div class="wr-evpin" style="background:'+sevColor[sev]+'">'+ev.stats.coverage+'</div>' });
  }

  function popupHtml(ev){
    return '<div style="min-width:170px"><b>'+esc(ev.title)+'</b>'+
      (ev.location_name ? '<div class="text-muted small">'+esc(ev.location_name)+'</div>' : '')+
      '<div class="small mt-1">Κάλυψη: <b>'+ev.stats.coverage+'%</b> ('+ev.stats.people_present+'/'+ev.stats.people_approved+' άτομα)</div>'+
      '<div class="small">Ομάδες παρούσες: '+ev.stats.teams_present+'/'+ev.stats.teams_total+'</div>'+
      (ev.stats.open_shortages>0 ? '<div class="small text-danger fw-semibold">⚠ '+ev.stats.open_shortages+' ελλείψεις</div>' : '')+
      '<a href="'+BASE+'/operations/events/'+ev.id+'" class="btn btn-sm btn-danger w-100 mt-2">Επιχειρησιακή Σελίδα</a></div>';
  }

  function focusEvent(id){
    var mk = evMarkers[id];
    if (mk){ map.setView(mk.getLatLng(), Math.max(map.getZoom(), 14), { animate:true }); mk.openPopup(); }
  }
  window.__wrFocus = focusEvent;

  function renderList(d){
    var box = document.getElementById('evList');
    if (!d.events || !d.events.length){
      box.innerHTML = '<div class="wr-empty"><i class="bi bi-moon-stars fs-3 d-block mb-2"></i>Καμία ενεργή δράση αυτή τη στιγμή.</div>';
      return;
    }
    var html = '';
    d.events.forEach(function(ev){
      var sev = severity(ev), col = sevColor[sev];
      var noLoc = (ev.lat === null || ev.lng === null);
      html += '<div class="card ev-card sev-'+sev+' mb-2 p-2" onclick="__wrFocus('+ev.id+')">'+
        '<div class="d-flex justify-content-between align-items-start gap-2">'+
          '<div class="flex-grow-1">'+
            '<span class="badge" style="background:'+col+';font-size:.62rem">'+(statusLbl[ev.status]||ev.status)+'</span>'+
            (noLoc ? ' <span class="badge text-bg-light" title="Χωρίς συντεταγμένες" style="font-size:.6rem"><i class="bi bi-geo-alt-fill"></i> —</span>' : '')+
            '<div class="fw-bold small mt-1">'+esc(ev.title)+'</div>'+
            (ev.location_name ? '<div class="text-muted" style="font-size:.72rem"><i class="bi bi-geo-alt me-1"></i>'+esc(ev.location_name)+'</div>' : '')+
          '</div>'+
          '<div class="text-end" style="white-space:nowrap">'+
            '<div class="fw-bold" style="font-size:1.15rem;color:'+col+'">'+ev.stats.coverage+'%</div>'+
            '<div class="text-muted" style="font-size:.6rem">κάλυψη</div>'+
          '</div>'+
        '</div>'+
        '<div class="cov-bar mt-2"><div class="cov-fill" style="width:'+Math.min(ev.stats.coverage,100)+'%;background:'+col+'"></div></div>'+
        '<div class="d-flex justify-content-between align-items-center mt-2" style="font-size:.72rem">'+
          '<span><i class="bi bi-people me-1"></i>'+ev.stats.people_present+'/'+ev.stats.people_approved+' άτομα · '+ev.stats.teams_present+'/'+ev.stats.teams_total+' ομάδες</span>'+
          (ev.stats.open_shortages>0 ? '<span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>'+ev.stats.open_shortages+'</span>' : '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i><span class="wr-rem" data-end="'+ev.end_ts+'">'+remaining(ev.end_ts)+'</span></span>')+
        '</div>'+
        '<a href="'+BASE+'/operations/events/'+ev.id+'" class="btn btn-outline-danger btn-sm w-100 mt-2" onclick="event.stopPropagation()"><i class="bi bi-broadcast me-1"></i>Άνοιγμα</a>'+
      '</div>';
    });
    box.innerHTML = html;
  }

  function renderMap(d){
    var seen = {};
    var bounds = [];
    (d.events||[]).forEach(function(ev){
      if (ev.lat === null || ev.lng === null) return;
      seen[ev.id] = true;
      bounds.push([ev.lat, ev.lng]);
      if (evMarkers[ev.id]){
        evMarkers[ev.id].setLatLng([ev.lat, ev.lng]).setIcon(evPin(ev)).setPopupContent(popupHtml(ev));
      } else {
        evMarkers[ev.id] = L.marker([ev.lat, ev.lng], { icon: evPin(ev), zIndexOffset:1000 })
          .addTo(map).bindPopup(popupHtml(ev));
      }
    });
    // remove markers for events no longer active
    Object.keys(evMarkers).forEach(function(id){
      if (!seen[id]){ map.removeLayer(evMarkers[id]); delete evMarkers[id]; }
    });
    // team pings (small secondary dots)
    teamLayer.clearLayers();
    (d.events||[]).forEach(function(ev){
      (ev.pings||[]).forEach(function(p){
        var c = p.age_min < 5 ? '#22c55e' : p.age_min < 20 ? '#f59e0b' : '#ef4444';
        L.marker([p.latitude, p.longitude], { icon: L.divIcon({ className:'', iconSize:[10,10], iconAnchor:[5,5],
          html:'<div style="background:'+c+';width:10px;height:10px;border-radius:50%;border:1.5px solid #fff;opacity:.85"></div>' }) })
          .bindPopup('<b>'+esc(p.team_name)+'</b><br>'+esc(ev.title)+'<br>'+p.age_min+' λεπτά πριν')
          .addTo(teamLayer);
      });
      (ev.photos||[]).forEach(function(ph){
        if (ph.lat === null || ph.lng === null) return;
        L.marker([ph.lat, ph.lng], { icon: L.divIcon({ className:'', iconSize:[22,22], iconAnchor:[11,20],
          html:'<div style="background:#0ea5e9;width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 0 6px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center"><i class="bi bi-camera-fill" style="color:#fff;font-size:10px;transform:rotate(45deg)"></i></div>' }) })
          .bindPopup('<div style="text-align:center"><img class="wr-photo-thumb" src="'+ph.url+'" data-url="'+ph.url+'" data-label="'+esc(ph.team_name)+'" style="max-width:170px;max-height:130px;border-radius:6px;cursor:pointer"><br><b>'+esc(ph.team_name)+'</b><br><span class="text-muted" style="font-size:.72rem">'+esc(ev.title)+' · '+esc(ph.at)+'</span></div>')
          .addTo(teamLayer);
      });
    });
    if (bounds.length === 1 && !map.__fitted){ map.setView(bounds[0], 13); map.__fitted=true; }
    else if (bounds.length > 1 && !map.__fitted){ try{ map.fitBounds(bounds, { padding:[40,40], maxZoom:14 }); map.__fitted=true; }catch(e){} }
  }

  function applySnapshot(d){
    if (!d || !d.ok) return;
    lastSnapshotAt = Date.now();
    var t = d.totals || {};
    document.getElementById('t-events').textContent  = t.events || 0;
    document.getElementById('t-present').textContent = t.people_present || 0;
    document.getElementById('t-cov').textContent     = (t.coverage || 0) + '%';
    document.getElementById('t-sh').textContent      = t.open_shortages || 0;
    document.getElementById('t-sh-wrap').classList.toggle('alert', (t.open_shortages||0) > 0);
    document.getElementById('wrClock').textContent   = d.ts || '';
    renderList(d);
    renderMap(d);
    // flash event cards whose shortages increased
    (d.events||[]).forEach(function(ev){
      var prev = prevShortById[ev.id] || 0;
      if (ev.stats.open_shortages > prev){
        var mk = evMarkers[ev.id];
        if (mk) mk.openPopup();
      }
      prevShortById[ev.id] = ev.stats.open_shortages;
    });
  }

  /* per-second countdown refresh on cards */
  setInterval(function(){
    document.querySelectorAll('.wr-rem').forEach(function(el){
      var end = parseInt(el.getAttribute('data-end'),10);
      if (end){ el.textContent = remaining(end); }
    });
  }, 1000);

  /* fullscreen toggle */
  document.getElementById('btnFull').addEventListener('click', function(){
    document.getElementById('mapWrap').classList.toggle('fullscreen');
    setTimeout(function(){ map.invalidateSize(); }, 200);
  });

  /* SSE live updates (close-and-retry) */
  function setLive(mode){
    var b = document.getElementById('liveBadge'); if(!b) return;
    if (mode === 'sse'){ b.textContent='◉ LIVE SSE'; b.className='badge bg-success ms-1'; }
    else if (mode === 'poll'){ b.textContent='↻ LIVE POLL'; b.className='badge bg-info text-dark ms-1'; }
    else { b.textContent='↺ Επανασύνδεση…'; b.className='badge bg-warning text-dark ms-1'; }
  }
  var es = null;
  function connectSSE(){
    if (es){ try{ es.close(); }catch(e){} }
    es = new EventSource(BASE + '/operations/war-room/stream');
    es.onopen = function(){ setLive('sse'); };
    es.addEventListener('update', function(evt){ try{ applySnapshot(JSON.parse(evt.data)); setLive('sse'); }catch(e){} });
    es.onerror = function(){
      setLive('retry');
      setTimeout(function(){
        if (Date.now() - lastSnapshotAt > 4500) { pollStatus(); }
      }, 1800);
    };
  }

  function pollStatus(){
    if (pollInFlight) return;
    pollInFlight = true;
    fetch(BASE + '/operations/war-room/status', { headers:{ 'X-Requested-With':'XMLHttpRequest' }, cache:'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(d){ applySnapshot(d); setLive('poll'); })
      .catch(function(){ setLive('retry'); })
      .finally(function(){ pollInFlight = false; });
  }

  setInterval(function(){
    if (Date.now() - lastSnapshotAt > 10000) { pollStatus(); }
  }, 5000);

  /* boot with server-rendered snapshot, then go live */
  applySnapshot(INIT);
  connectSSE();
})();
</script>

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
<script>
document.addEventListener('click', function (e) {
  var t = e.target.closest ? e.target.closest('.wr-photo-thumb') : null;
  if (!t || !window.bootstrap) return;
  document.getElementById('photoModalImg').src = t.getAttribute('data-url');
  document.getElementById('photoModalLabel').textContent = t.getAttribute('data-label') || 'Φωτογραφία';
  document.getElementById('photoModalDl').href = t.getAttribute('data-url');
  bootstrap.Modal.getOrCreateInstance(document.getElementById('photoModal')).show();
});
</script>
