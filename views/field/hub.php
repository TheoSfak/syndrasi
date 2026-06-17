<?php
/**
 * Mission Commander field hub — /f/{token}  (NO LOGIN)
 * Standalone full-screen mobile page for the assigned commander.
 */
$isActive = ($app['event_status'] === 'active');
$cname    = $commander['full_name'] ?? 'Υπεύθυνος';
$evLat = (isset($app['latitude'])  && $app['latitude']  !== null && $app['latitude']  !== '') ? (float) $app['latitude']  : null;
$evLng = (isset($app['longitude']) && $app['longitude'] !== null && $app['longitude'] !== '') ? (float) $app['longitude'] : null;
$tLat  = (!empty($lastPing) && $lastPing['latitude']  !== null) ? (float) $lastPing['latitude']  : null;
$tLng  = (!empty($lastPing) && $lastPing['longitude'] !== null) ? (float) $lastPing['longitude'] : null;
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d1a1a">
<meta name="apple-mobile-web-app-capable" content="yes">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= e(url('/assets/img/icons/icon-192.png')) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>window.csrfToken = '<?= e(csrf_token()) ?>'; window.baseUrl = '<?= e(base_uri()) ?>';</script>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0d1a1a;color:#e8f5f4;min-height:100dvh;-webkit-tap-highlight-color:transparent}
  .topbar{position:sticky;top:0;z-index:100;background:#0d1a1a;border-bottom:1px solid #1e3333;padding:14px 16px}
  .topbar .ev{font-size:14px;font-weight:800;color:#e8f5f4}
  .topbar .meta{font-size:12px;color:#7ab5ae;margin-top:2px}
  .topbar .who{font-size:12px;color:#4dd4c4;margin-top:4px}
  .body{padding:16px;display:flex;flex-direction:column;gap:12px;padding-bottom:32px}
  .card{background:#111e1e;border:1px solid #1e3333;border-radius:18px;overflow:hidden}
  .inactive{margin:0;padding:12px 16px;border-radius:12px;background:#2a1a0d;border:1px solid #78350f;color:#fbbf24;font-size:13px;display:flex;gap:10px;align-items:center}
  .flash{padding:12px 16px;border-radius:12px;font-size:14px;font-weight:600;display:flex;gap:10px;align-items:center}
  .flash-success{background:#0d2e1a;color:#4ade80;border:1px solid #166534}
  .flash-danger{background:#2a0d0d;color:#f87171;border:1px solid #7f1d1d}
  .sos-btn{width:100%;padding:24px 20px;border:none;border-radius:18px;background:linear-gradient(135deg,#dc2626,#7f1d1d);color:#fff;cursor:pointer;display:flex;align-items:center;gap:16px;text-align:left}
  .sos-btn:active{transform:scale(.98)} .sos-btn:disabled{opacity:.45}
  .sos-ico{font-size:40px} .sos-lbl{font-size:22px;font-weight:900;letter-spacing:.04em} .sos-sub{font-size:12px;opacity:.9;margin-top:2px}
  .sos-pulse{animation:sp 1.2s infinite}
  @keyframes sp{0%{box-shadow:0 0 0 0 rgba(239,68,68,.6)}70%{box-shadow:0 0 0 18px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
  .sos-banner{margin-top:10px;padding:12px 14px;border-radius:12px;background:#2a0d0d;border:1px solid #7f1d1d;color:#fca5a5;font-size:13px;font-weight:600;display:flex;gap:8px;align-items:center}
  .sos-banner.ack{background:#0d2233;border-color:#1e40af;color:#93c5fd}
  .big-btn{width:100%;padding:22px 20px;background:linear-gradient(135deg,#0e4d4d,#0d3a3a);border:none;border-radius:18px;color:#e8f5f4;cursor:pointer;display:flex;align-items:center;gap:16px;text-align:left}
  .big-btn:active{transform:scale(.98)} .big-btn:disabled{opacity:.4}
  .big-ico{font-size:34px;color:#4dd4c4} .big-lbl{font-size:18px;font-weight:800} .big-sub{font-size:12px;color:#7ab5ae;margin-top:2px}
  .hdr{padding:16px 18px 10px;font-size:15px;font-weight:800;display:flex;gap:10px;align-items:center}
  .hdr i{color:#4dd4c4;font-size:20px}
  .pings{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 14px 14px}
  .ping{padding:14px 10px;border:none;border-radius:12px;cursor:pointer;background:#13302e;color:#7fe6d8;font-size:13px;font-weight:700;display:flex;gap:8px;align-items:center;text-align:left}
  .ping:active{transform:scale(.96)} .ping:disabled{opacity:.35}
  .ping.full{grid-column:1/-1;background:#3a1414;color:#fca5a5}
  .field{display:flex;flex-direction:column;gap:4px;padding:0 16px 14px}
  .field label{font-size:12px;color:#9ca3af;font-weight:600}
  .field input[type=file],.field input[type=text]{background:#0d1a1a;border:1px solid #1e3333;border-radius:10px;color:#e8f5f4;font-size:15px;padding:12px 14px;outline:none;width:100%}
  .submit{background:#0e7490;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:800;padding:14px;cursor:pointer;width:100%}
  .msg-list{padding:0 14px 12px;display:flex;flex-direction:column;gap:8px;max-height:300px;overflow-y:auto}
  .msg{padding:10px 12px;border-radius:12px;font-size:13px;line-height:1.35;max-width:92%}
  .msg-cmd{align-self:flex-start;background:#13243a;color:#cfe3ff;border:1px solid #1e3a5f}
  .msg-team{align-self:flex-end;background:#0e3d3a;color:#cdebe6}
  .msg-order{align-self:flex-start;background:#3a2a0d;color:#fcd9a5;border:1px solid #78510f}
  .msg-status{align-self:flex-end;background:#13302e;color:#9be8da;font-weight:700}
  .msg-t{font-size:10px;opacity:.6;margin-top:3px}
  .order-pin{background:linear-gradient(135deg,#7c2d12,#b45309);border:1px solid #f59e0b;border-radius:16px;padding:16px;animation:sp 1.4s infinite}
  .order-pin-h{font-weight:900;color:#fde68a;font-size:14px;display:flex;gap:6px;align-items:center}
  .order-pin-b{color:#fff;font-size:16px;margin:8px 0 12px}
  .order-pin-btn{background:#facc15;color:#1a1400;border:none;border-radius:12px;font-weight:800;font-size:15px;padding:14px;width:100%;cursor:pointer}
  #locRes{font-size:13px;color:#4ade80;padding:0 4px;display:none}
  #locRes.err{color:#f87171}
</style>
</head>
<body>

<div class="topbar">
  <div class="ev"><i class="bi bi-broadcast me-1"></i><?= e($app['event_title']) ?></div>
  <div class="meta"><?= e(gr_time($app['start_datetime'])) ?>–<?= e(gr_time($app['end_datetime'])) ?><?php if ($app['location_name']): ?> · <?= e($app['location_name']) ?><?php endif; ?></div>
  <div class="who"><i class="bi bi-person-badge me-1"></i><?= e($app['team_name']) ?> · Υπεύθυνος: <?= e($cname) ?></div>
</div>

<div class="body">

  <?php $flash = get_flash(); if ($flash): $ft = $flash['type'] ?? 'success'; ?>
  <div class="flash flash-<?= e($ft === 'danger' ? 'danger' : 'success') ?>">
    <i class="bi <?= $ft === 'danger' ? 'bi-x-circle-fill' : 'bi-check-circle-fill' ?>"></i><?= e($flash['message']) ?>
  </div>
  <?php endif; ?>

  <?php if (!$isActive): ?>
  <div class="inactive"><i class="bi bi-hourglass-split"></i> Η δράση δεν είναι ενεργή αυτή τη στιγμή.</div>
  <?php endif; ?>

  <!-- Pinned orders -->
  <div id="orderBanner" style="display:none"></div>

  <!-- SOS -->
  <div>
    <button class="sos-btn" id="sosBtn" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-exclamation-octagon-fill sos-ico"></i>
      <div style="flex:1"><div class="sos-lbl">SOS — ΚΙΝΔΥΝΟΣ</div><div class="sos-sub">Άμεση κλήση βοήθειας στον δήμο</div></div>
    </button>
    <div class="sos-banner" id="sosBanner" style="display:none"></div>
  </div>

  <!-- Στίγμα -->
  <div class="card">
    <div id="gpsReqBanner" style="margin:14px 14px 0;padding:10px 12px;border-radius:10px;background:#13243a;border:1px solid #1e3a5f;color:#cfe3ff;font-size:13px;font-weight:600;display:<?= !empty($gpsRequest) ? 'block' : 'none' ?>">
      <i class="bi bi-geo-alt-fill me-1"></i> Ο δήμος ζήτησε το στίγμα σας — πατήστε «Αποστολή Στίγματος».
    </div>
    <button class="big-btn" id="locBtn" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-geo-alt-fill big-ico"></i>
      <div style="flex:1"><div class="big-lbl">Αποστολή Στίγματος</div><div class="big-sub">Στείλτε τη θέση σας στον δήμο</div></div>
      <i class="bi bi-chevron-right" style="opacity:.5"></i>
    </button>
    <div id="locRes" style="padding:10px 18px 14px"></div>
  </div>

  <!-- Map -->
  <div class="card">
    <div class="hdr"><i class="bi bi-map"></i> Χάρτης Δράσης</div>
    <div id="teamMap" style="height:240px;background:#0a1414"></div>
  </div>

  <!-- Status pings -->
  <div class="card">
    <div class="hdr"><i class="bi bi-lightning-charge"></i> Γρήγορη ενημέρωση</div>
    <div class="pings">
      <button class="ping" data-code="arrived" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-geo-alt-fill"></i> Φτάσαμε στο σημείο</button>
      <button class="ping" data-code="task_complete" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i> Ολοκληρώθηκε</button>
      <button class="ping" data-code="need_backup" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-people-fill"></i> Χρειαζόμαστε ενίσχυση</button>
      <button class="ping" data-code="returning" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-box-arrow-left"></i> Επιστροφή στη βάση</button>
      <button class="ping full" data-code="incident" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-exclamation-triangle-fill"></i> Έχουμε περιστατικό</button>
    </div>
  </div>

  <!-- Photo -->
  <div class="card">
    <div class="hdr"><i class="bi bi-camera"></i> Αποστολή φωτογραφίας</div>
    <div id="photoReqBanner" style="margin:0 14px 8px;padding:10px 12px;border-radius:10px;background:#13243a;border:1px solid #1e3a5f;color:#cfe3ff;font-size:13px;font-weight:600;display:<?= !empty($photoRequest) ? 'block' : 'none' ?>">
      <i class="bi bi-camera-fill me-1"></i> Ο δήμος ζήτησε φωτογραφία — τραβήξτε/ανεβάστε μία παρακάτω.
    </div>
    <form method="post" action="<?= e(url('/f/' . $token . '/photo')) ?>" enctype="multipart/form-data" id="photoForm">
      <?= csrf_field() ?>
      <input type="hidden" name="latitude" id="phLat"><input type="hidden" name="longitude" id="phLng">
      <div class="field"><label>Φωτογραφία</label><input type="file" name="photo" accept="image/*" capture="environment" required></div>
      <div class="field"><label>Σχόλιο (προαιρετικό)</label><input type="text" name="caption" maxlength="255" placeholder="π.χ. σημείο, κατάσταση"></div>
      <div class="field"><button class="submit" id="phBtn"><i class="bi bi-upload me-1"></i>Αποστολή</button></div>
    </form>
  </div>

  <!-- Comms (read + ack) -->
  <div class="card">
    <div class="hdr"><i class="bi bi-chat-dots"></i> Μηνύματα δήμου</div>
    <div class="msg-list" id="msgList"><div style="color:#4b7070;font-size:12px;text-align:center;padding:14px">Φόρτωση…</div></div>
  </div>

  <!-- Δωμάτιο Επιχείρησης (κοινό κανάλι) -->
  <div class="card">
    <div class="hdr"><i class="bi bi-broadcast-pin"></i> Δωμάτιο Επιχείρησης</div>
    <div class="msg-list" id="roomList"><div style="color:#4b7070;font-size:12px;text-align:center;padding:14px">Φόρτωση…</div></div>
    <div style="display:flex;gap:8px;padding:0 14px 14px">
      <input type="text" id="roomInput" maxlength="500" placeholder="Μήνυμα προς όλους…" style="flex:1;background:#0d1a1a;border:1px solid #1e3333;border-radius:10px;color:#e8f5f4;font-size:14px;padding:12px;outline:none">
      <button type="button" id="roomSend" style="background:#0e7490;color:#fff;border:none;border-radius:10px;padding:0 16px;font-size:18px;cursor:pointer"><i class="bi bi-send"></i></button>
    </div>
  </div>

</div>

<script>
(function () {
  'use strict';
  var BASE = window.baseUrl || '', CSRF = window.csrfToken || '';
  var TOKEN = '<?= e($token) ?>', IS_ACTIVE = <?= $isActive ? 'true' : 'false' ?>;
  function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML;}
  function postJSON(p,b){return fetch(BASE+'/f/'+TOKEN+p,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(b||{})}).then(function(r){return r.json();});}

  /* Map */
  (function(){
    var el=document.getElementById('teamMap'); if(!el||typeof L==='undefined')return;
    var evLat=<?= $evLat!==null?$evLat:'null' ?>,evLng=<?= $evLng!==null?$evLng:'null' ?>;
    var tLat=<?= $tLat!==null?$tLat:'null' ?>,tLng=<?= $tLng!==null?$tLng:'null' ?>;
    var center=(tLat!==null)?[tLat,tLng]:((evLat!==null)?[evLat,evLng]:[35.3387,25.1442]);
    var map=L.map('teamMap').setView(center,14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
    var b=[];
    if(evLat!==null){L.marker([evLat,evLng]).addTo(map).bindPopup('Σημείο δράσης');b.push([evLat,evLng]);}
    if(tLat!==null){L.circleMarker([tLat,tLng],{radius:9,color:'#22d3ee',fillColor:'#22d3ee',fillOpacity:.85}).addTo(map).bindPopup('Η θέση μας');b.push([tLat,tLng]);}
    if(b.length>1){try{map.fitBounds(b,{padding:[30,30],maxZoom:16});}catch(e){}}
    window.__teamMap=map; window.__teamGeo=L.layerGroup().addTo(map);
    setTimeout(function(){map.invalidateSize();},250);
  })();

  /* SOS */
  var sosBtn=document.getElementById('sosBtn'),sosBanner=document.getElementById('sosBanner');
  sosBtn.addEventListener('click',function(){
    if(!confirm('ΕΠΙΒΕΒΑΙΩΣΗ SOS\n\nΘα ειδοποιηθεί ΑΜΕΣΑ ο δήμος ότι κινδυνεύετε. Συνέχεια;'))return;
    sosBtn.disabled=true;sosBtn.querySelector('.sos-sub').textContent='Λήψη τοποθεσίας…';
    var send=function(la,ln,ac){postJSON('/sos',{latitude:la,longitude:ln,accuracy:ac}).then(function(d){
      if(d&&d.success){sosBtn.querySelector('.sos-sub').textContent='SOS εστάλη — ο δήμος ειδοποιήθηκε';pollComms();}
      else{sosBtn.disabled=false;sosBtn.querySelector('.sos-sub').textContent=(d&&d.message)||'Αποτυχία';}
    }).catch(function(){sosBtn.disabled=false;sosBtn.querySelector('.sos-sub').textContent='Σφάλμα σύνδεσης';});};
    if(navigator.geolocation){navigator.geolocation.getCurrentPosition(function(p){send(p.coords.latitude,p.coords.longitude,p.coords.accuracy);},function(){send(null,null,null);},{enableHighAccuracy:true,timeout:8000,maximumAge:0});}else{send(null,null,null);}
  });

  /* Στίγμα */
  var locBtn=document.getElementById('locBtn'),locRes=document.getElementById('locRes');
  locBtn.addEventListener('click',function(){
    if(!navigator.geolocation){showLoc('Η συσκευή δεν υποστηρίζει τοποθεσία.',true);return;}
    locBtn.disabled=true;locBtn.querySelector('.big-sub').textContent='Λήψη GPS…';
    navigator.geolocation.getCurrentPosition(function(p){
      postJSON('/location',{latitude:p.coords.latitude,longitude:p.coords.longitude,accuracy:p.coords.accuracy})
        .then(function(d){locBtn.disabled=false;if(d&&d.success){showLoc('✓ Στίγμα στάλθηκε!',false);locBtn.querySelector('.big-sub').textContent='Τώρα';}else{showLoc((d&&d.message)||'Σφάλμα',true);}})
        .catch(function(){locBtn.disabled=false;showLoc('Σφάλμα σύνδεσης.',true);});
    },function(){locBtn.disabled=false;showLoc('Δεν δόθηκε άδεια τοποθεσίας.',true);},{enableHighAccuracy:true,timeout:10000,maximumAge:0});
  });
  function showLoc(m,e){locRes.textContent=m;locRes.className=e?'err':'';locRes.style.display='block';setTimeout(function(){locRes.style.display='none';},4000);}

  /* Status pings */
  document.querySelectorAll('.ping').forEach(function(b){b.addEventListener('click',function(){
    if(b.disabled)return;var o=b.innerHTML;b.disabled=true;b.style.opacity='.6';
    postJSON('/status',{code:b.dataset.code}).then(function(){b.innerHTML='<i class="bi bi-check2"></i> Στάλθηκε';setTimeout(function(){b.innerHTML=o;b.disabled=false;b.style.opacity='';},2500);pollComms();}).catch(function(){b.innerHTML=o;b.disabled=false;b.style.opacity='';});
  });});

  /* Photo: attach GPS then submit */
  var photoForm=document.getElementById('photoForm'),submitted=false;
  photoForm.addEventListener('submit',function(e){
    if(submitted)return;e.preventDefault();var btn=document.getElementById('phBtn');btn.disabled=true;btn.innerHTML='<i class="bi bi-geo-alt me-1"></i>Τοποθεσία…';
    function go(){submitted=true;photoForm.submit();}
    if(navigator.geolocation){navigator.geolocation.getCurrentPosition(function(p){document.getElementById('phLat').value=p.coords.latitude;document.getElementById('phLng').value=p.coords.longitude;go();},function(){go();},{enableHighAccuracy:true,timeout:8000,maximumAge:30000});}else{go();}
  });

  /* Ack order */
  window.ackOrder=function(id){postJSON('/ack-order',{message_id:id}).then(pollComms);};

  /* Comms polling */
  var msgList=document.getElementById('msgList'),orderBanner=document.getElementById('orderBanner');
  function renderOrders(msgs){
    var p=(msgs||[]).filter(function(m){return m.kind==='order'&&!m.acknowledged_at;});
    if(!p.length){orderBanner.innerHTML='';orderBanner.style.display='none';return;}
    orderBanner.style.display='';
    orderBanner.innerHTML=p.map(function(m){var t=(m.created_at||'').substr(11,5);
      var head=m.point_kind==='incident'?'⚠️ ΠΕΡΙΣΤΑΤΙΚΟ':(m.point_kind==='move'?'➡️ ΜΕΤΑΒΑΣΗ ΣΕ ΣΗΜΕΙΟ':'ΕΝΤΟΛΗ ΑΠΟ ΤΟΝ ΔΗΜΟ');
      var dir=(m.latitude!=null&&m.longitude!=null)?'<a href="https://www.google.com/maps?q='+m.latitude+','+m.longitude+'" target="_blank" rel="noopener" class="order-pin-btn" style="display:block;text-align:center;text-decoration:none;background:#2563eb;color:#fff;margin-bottom:8px"><i class="bi bi-geo-alt-fill"></i> Οδηγίες (Google Maps)</a>':'';
      return '<div class="order-pin"><div class="order-pin-h"><i class="bi bi-megaphone-fill"></i> '+head+'</div><div class="order-pin-b">'+esc(m.body||'')+'</div>'+dir+'<button class="order-pin-btn" onclick="ackOrder('+m.id+')"><i class="bi bi-check2-all"></i> Επιβεβαίωση λήψης</button><div style="font-size:11px;color:#fde68a;opacity:.8;margin-top:6px;text-align:right">'+t+'</div></div>';
    }).join('');
  }
  function renderGeoPoints(msgs){
    var map=window.__teamMap,grp=window.__teamGeo; if(!map||!grp)return; grp.clearLayers();
    (msgs||[]).forEach(function(m){
      if(m.latitude==null||m.longitude==null||!m.point_kind)return;
      var color=m.point_kind==='incident'?'#dc2626':(m.point_kind==='move'?'#2563eb':'#0d9488');
      var lbl=m.point_kind==='incident'?'⚠️ Περιστατικό':(m.point_kind==='move'?'➡️ Μετάβαση':'📍 Σημείο');
      L.circleMarker([m.latitude,m.longitude],{radius:10,color:color,fillColor:color,fillOpacity:.7}).addTo(grp)
        .bindPopup('<b>'+lbl+'</b><br>'+esc(m.body||'')+'<br><a href="https://www.google.com/maps?q='+m.latitude+','+m.longitude+'" target="_blank" rel="noopener">Οδηγίες</a>');
    });
  }
  function renderMsgs(msgs){
    if(!msgs||!msgs.length){msgList.innerHTML='<div style="color:#4b7070;font-size:12px;text-align:center;padding:14px">Καμία επικοινωνία ακόμη.</div>';return;}
    msgList.innerHTML=msgs.map(function(m){
      var cls=m.kind==='order'?'msg-order':(m.kind==='status'?'msg-status':(m.sender_role==='command'?'msg-cmd':'msg-team'));
      var who=m.sender_role==='command'?'Δήμος':(m.sender_name||'Ομάδα');var t=(m.created_at||'').substr(11,5);
      var h='<div class="msg '+cls+'"><div>'+(m.kind==='order'?'📋 <strong>ΕΝΤΟΛΗ:</strong> ':'')+esc(m.body||'')+'</div>';
      if(m.kind==='order'){h+=m.acknowledged_at?'<div style="font-size:11px;color:#4ade80;margin-top:4px"><i class="bi bi-check2-all"></i> Επιβεβαιώθηκε</div>':'<button class="order-pin-btn" style="margin-top:6px;padding:8px" onclick="ackOrder('+m.id+')">Επιβεβαίωση λήψης</button>';}
      h+='<div class="msg-t">'+who+' · '+t+'</div></div>';return h;
    }).join('');msgList.scrollTop=msgList.scrollHeight;
  }
  function renderSos(sos){
    if(!sos){sosBanner.style.display='none';if(sosBtn){sosBtn.classList.remove('sos-pulse');sosBtn.disabled=!IS_ACTIVE;if(IS_ACTIVE)sosBtn.querySelector('.sos-sub').textContent='Άμεση κλήση βοήθειας στον δήμο';}return;}
    sosBanner.style.display='flex';
    if(sos.status==='acknowledged'){sosBanner.className='sos-banner ack';sosBanner.innerHTML='<i class="bi bi-check2-all"></i> Το SOS ελήφθη από τον δήμο'+(sos.ack_name?' ('+esc(sos.ack_name)+')':'')+' — έρχεται βοήθεια.';sosBtn.classList.remove('sos-pulse');}
    else{sosBanner.className='sos-banner';sosBanner.innerHTML='<i class="bi bi-broadcast-pin"></i> SOS ΕΝΕΡΓΟ — αναμονή επιβεβαίωσης…';sosBtn.classList.add('sos-pulse');}
    sosBtn.disabled=true;sosBtn.querySelector('.sos-sub').textContent='SOS ενεργό';
  }
  /* Δωμάτιο Επιχείρησης */
  var roomList=document.getElementById('roomList');
  function renderRoom(msgs){
    if(!roomList)return;
    if(!msgs||!msgs.length){roomList.innerHTML='<div style="color:#4b7070;font-size:12px;text-align:center;padding:14px">Κανένα μήνυμα ακόμη.</div>';return;}
    roomList.innerHTML=msgs.map(function(m){
      var cmd=m.sender_role==='command';var who=cmd?'Δήμος':(m.sender_label||m.team_name||m.sender_name||'Ομάδα');var t=(m.created_at||'').substr(11,5);
      return '<div class="msg '+(cmd?'msg-cmd':'msg-team')+'"><div>'+esc(m.body||'')+'</div><div class="msg-t">'+esc(who)+' · '+t+'</div></div>';
    }).join('');roomList.scrollTop=roomList.scrollHeight;
  }
  (function(){var s=document.getElementById('roomSend'),i=document.getElementById('roomInput');
    function send(){var b=(i.value||'').trim();if(!b)return;i.value='';postJSON('/room',{body:b}).then(pollComms);}
    if(s)s.addEventListener('click',send);if(i)i.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();send();}});})();

  function pollComms(){fetch(BASE+'/f/'+TOKEN+'/comms',{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(d){if(d&&d.success){renderMsgs(d.messages);renderOrders(d.messages);renderGeoPoints(d.messages);renderSos(d.sos);renderRoom(d.room);var pb=document.getElementById('photoReqBanner');if(pb)pb.style.display=d.photo_request?'block':'none';var gb=document.getElementById('gpsReqBanner');if(gb)gb.style.display=d.gps_request?'block':'none';}}).catch(function(){});}
  pollComms();setInterval(pollComms,5000);
})();
</script>
</body>
</html>
