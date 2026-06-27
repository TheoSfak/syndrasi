<?php
/** SynDrasi — Story / Απολογισμός Δράσης (standalone presentation page). */
$ev    = $story['event'] ?? [];
$sm    = $story['summary'] ?? [];
$teams = $story['teams'] ?? [];
$tl    = $story['timeline'] ?? [];
$metr  = array_values($story['metrics'] ?? []);
$photos= $story['photos'] ?? [];
$videos= $story['videos'] ?? [];
$shorts= $story['shortages'] ?? [];
$checks= $story['checkins'] ?? [];
$download   = !empty($download);
$publicMode = !empty($publicMode);
$isPublic   = !empty($public) || $publicMode;
$absHost    = $absHost ?? '';
$storyToken = $storyToken ?? '';
$photoSrc = function($p) use ($download, $absHost, $publicMode, $storyToken) {
    if ($publicMode) { return url('/public/story/' . $storyToken . '/photo/' . (int) $p['id']); }
    if ($download && !empty($p['data_uri'])) { return $p['data_uri']; }
    $u = url('/operations/photos/' . (int) $p['id']);
    return $download ? $absHost . $u : $u;
};
$videoSrc = function($id) use ($download, $absHost, $publicMode, $storyToken) {
    if ($publicMode) { return url('/public/story/' . $storyToken . '/video/' . (int) $id); }
    $u = url('/operations/videos/' . (int) $id);
    return $download ? $absHost . $u : $u;
};

$jsTeams = array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'color' => $t['color']], $teams);
$startD = $ev['start_datetime'] ?? null;
$endD   = $ev['end_datetime'] ?? null;
?><!DOCTYPE html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Απολογισμός Δράσης') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<style>
  :root{ --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; --brand:#0d9488; }
  *{box-sizing:border-box}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:var(--ink);background:#f1f5f9;margin:0}
  .wrap{max-width:1000px;margin:0 auto;padding:0 16px 64px}
  .hero{background:linear-gradient(135deg,#0f766e,#0d9488 55%,#14b8a6);color:#fff;border-radius:0 0 22px 22px;padding:30px 24px 26px;box-shadow:0 12px 30px rgba(13,148,136,.25)}
  .hero .top{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
  .hero img{height:46px;border-radius:8px;background:#fff;padding:4px}
  .hero h1{font-size:1.7rem;font-weight:800;margin:10px 0 4px}
  .hero .meta{opacity:.92;font-size:.95rem}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-top:18px}
  .stat{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);border-radius:14px;padding:12px 14px}
  .stat .v{font-size:1.5rem;font-weight:800;line-height:1}
  .stat .l{font-size:.72rem;text-transform:uppercase;letter-spacing:.6px;opacity:.9;margin-top:4px}
  .sec{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px 22px;margin-top:22px;box-shadow:0 2px 10px rgba(15,23,42,.04)}
  .sec h2{font-size:1.15rem;font-weight:800;margin:0 0 14px;display:flex;align-items:center;gap:8px}
  .sec h2 .bi{color:var(--brand)}
  .legend{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
  .legend span{display:inline-flex;align-items:center;gap:6px;font-size:.82rem;color:var(--muted)}
  .legend i{width:12px;height:12px;border-radius:3px;display:inline-block}
  #map{height:380px;border-radius:12px;border:1px solid var(--line)}
  /* timeline */
  .tl{position:relative;margin-left:6px}
  .tl::before{content:'';position:absolute;left:13px;top:4px;bottom:4px;width:2px;background:var(--line)}
  .tl-item{position:relative;padding:6px 0 6px 40px;min-height:34px}
  .tl-dot{position:absolute;left:4px;top:6px;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;box-shadow:0 0 0 3px #fff}
  .tl-time{font-size:.72rem;color:var(--muted);font-variant-numeric:tabular-nums}
  .tl-title{font-weight:700;font-size:.92rem}
  .tl-detail{font-size:.84rem;color:#334155}
  .tl-team{font-size:.72rem;color:var(--brand);font-weight:700}
  .badge-actor{font-size:.66rem;padding:.12rem .4rem;border-radius:6px;font-weight:700}
  .a-command{background:#fef3c7;color:#92400e}
  .a-team{background:#dbeafe;color:#1e40af}
  table{width:100%;border-collapse:collapse;font-size:.86rem}
  th,td{padding:7px 9px;border-bottom:1px solid var(--line);text-align:left}
  th{font-size:.72rem;text-transform:uppercase;letter-spacing:.4px;color:var(--muted)}
  .num{text-align:right;font-variant-numeric:tabular-nums}
  .gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
  .gal a,.gal .vid{display:block;border-radius:10px;overflow:hidden;border:1px solid var(--line);background:#000;aspect-ratio:4/3}
  .gal img,.gal video{width:100%;height:100%;object-fit:cover;display:block}
  .cap{font-size:.72rem;color:var(--muted);padding:4px 2px}
  .toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 0}
  .pill{font-size:.72rem;padding:.18rem .55rem;border-radius:999px;font-weight:700}
  .p-open{background:#fee2e2;color:#991b1b}.p-ack{background:#dbeafe;color:#1e40af}.p-res{background:#dcfce7;color:#166534}
  @media print{ body{background:#fff} .noprint{display:none!important} .sec,.hero{box-shadow:none} #map{height:320px} }
</style>
</head>
<body>
<div class="hero">
  <div class="wrap" style="padding-bottom:0">
    <div class="top">
      <?php if (!empty($logo)): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
      <span style="font-weight:700;letter-spacing:.5px"><?= e($orgLabel ?? 'Δήμος') ?> · Απολογισμός Δράσης</span>
      <?php if ($isPublic): ?><span class="badge bg-light text-dark ms-1">Δημόσια έκδοση</span><?php endif; ?>
    </div>
    <h1><?= e($ev['title'] ?? '') ?></h1>
    <div class="meta">
      <i class="bi bi-clock me-1"></i><?= e(gr_datetime($startD)) ?> → <?= e(gr_datetime($endD)) ?>
      <?php if (!empty($ev['location_name'])): ?> &nbsp;·&nbsp; <i class="bi bi-geo-alt me-1"></i><?= e($ev['location_name']) ?><?php endif; ?>
      <?php if (!empty($sm['duration_h'])): ?> &nbsp;·&nbsp; διάρκεια <?= e($sm['duration_h']) ?>ω<?php endif; ?>
    </div>
    <div class="stats">
      <div class="stat"><div class="v"><?= (int) ($sm['teams'] ?? 0) ?></div><div class="l">Ομάδες</div></div>
      <div class="stat"><div class="v"><?= (int) ($sm['volunteers'] ?? 0) ?></div><div class="l">Εθελοντές</div></div>
      <div class="stat"><div class="v"><?= e($sm['hours'] ?? 0) ?></div><div class="l">Εθελοντ. ώρες</div></div>
      <div class="stat"><div class="v"><?= (int) ($sm['orders'] ?? 0) ?></div><div class="l">Εντολές</div></div>
      <div class="stat"><div class="v"><?= (int) ($sm['pings'] ?? 0) ?></div><div class="l">Στίγματα</div></div>
      <div class="stat"><div class="v"><?= (int) ($sm['photos'] ?? 0) + (int) ($sm['videos'] ?? 0) ?></div><div class="l">Φωτό/Βίντεο</div></div>
      <div class="stat"><div class="v"><?= (int) ($sm['shortages'] ?? 0) ?></div><div class="l">Ελλείψεις</div></div>
      <?php if ((int) ($sm['sos'] ?? 0) > 0): ?><div class="stat"><div class="v"><?= (int) $sm['sos'] ?></div><div class="l">SOS</div></div><?php endif; ?>
    </div>
  </div>
</div>

<div class="wrap">
  <?php if (!$download && !$publicMode): ?>
  <div class="toolbar noprint">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/events/' . ($ev['id'] ?? ''))) ?>"><i class="bi bi-arrow-left me-1"></i>Πίσω</a>
    <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Εκτύπωση / PDF</button>
    <a class="btn btn-sm btn-outline-success" href="<?= e(url('/events/' . ($ev['id'] ?? '') . '/story/download')) ?>"><i class="bi bi-download me-1"></i>Λήψη HTML</a>
    <button class="btn btn-sm btn-outline-info" onclick="publishStory()"><i class="bi bi-share me-1"></i>Δημόσιος σύνδεσμος</button>
    <?php if (empty($public)): ?>
      <a class="btn btn-sm btn-outline-secondary" href="?view=public"><i class="bi bi-eye me-1"></i>Δημόσια προβολή</a>
    <?php else: ?>
      <a class="btn btn-sm btn-outline-secondary" href="?"><i class="bi bi-eye-fill me-1"></i>Εσωτερική προβολή</a>
    <?php endif; ?>
  </div>
  <script>
    var STORY_BASE = <?= json_encode(base_uri(), JSON_UNESCAPED_SLASHES) ?>;
    var STORY_EID  = <?= (int) ($ev['id'] ?? 0) ?>;
    var STORY_CSRF = <?= json_encode(csrf_token()) ?>;
    function publishStory(){
      fetch(STORY_BASE + '/events/' + STORY_EID + '/story/publish', { method:'POST', headers:{ 'X-CSRF-Token': STORY_CSRF, 'X-Requested-With':'XMLHttpRequest' } })
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d && d.url) { window.prompt('Δημόσιος σύνδεσμος (αντιγράψτε & μοιραστείτε):', d.url); } else { alert('Σφάλμα δημιουργίας συνδέσμου.'); } })
        .catch(function(){ alert('Σφάλμα δικτύου.'); });
    }
  </script>
  <?php endif; ?>

  <!-- ── Χάρτης διαδρομών ───────────────────────────────────────────── -->
  <div class="sec">
    <h2><i class="bi bi-map"></i> Χάρτης δράσης & διαδρομές ομάδων</h2>
    <div class="legend">
      <?php foreach ($teams as $t): ?>
        <span><i style="background:<?= e($t['color']) ?>"></i><?= e($t['name']) ?></span>
      <?php endforeach; ?>
    </div>
    <div id="map"></div>
  </div>

  <!-- ── Μετρικές απόκρισης ─────────────────────────────────────────── -->
  <div class="sec">
    <h2><i class="bi bi-speedometer2"></i> Χρόνοι απόκρισης ανά ομάδα</h2>
    <div class="table-responsive">
      <table>
        <thead><tr>
          <th>Ομάδα</th>
          <th class="num">Στίγμα (μ.ό.)</th>
          <th class="num">Φωτό (μ.ό.)</th>
          <th class="num">Βίντεο (μ.ό.)</th>
          <th class="num">ACK εντολής (μ.ό.)</th>
          <th class="num">Ανταπόκριση</th>
        </tr></thead>
        <tbody>
          <?php foreach ($metr as $m):
            $tot = ($m['gps']['sent'] + $m['photo']['sent'] + $m['video']['sent']);
            $ans = ($m['gps']['answered'] + $m['photo']['answered'] + $m['video']['answered']);
            $rate = $tot ? round($ans / $tot * 100) : null;
          ?>
          <tr>
            <td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?= e($m['color']) ?>;margin-right:6px"></span><?= e($m['team']) ?></td>
            <td class="num"><?= e($m['gps']['avg_label']) ?><?php if ($m['gps']['sent']): ?> <span class="text-muted">(<?= (int) $m['gps']['answered'] ?>/<?= (int) $m['gps']['sent'] ?>)</span><?php endif; ?></td>
            <td class="num"><?= e($m['photo']['avg_label']) ?><?php if ($m['photo']['sent']): ?> <span class="text-muted">(<?= (int) $m['photo']['answered'] ?>/<?= (int) $m['photo']['sent'] ?>)</span><?php endif; ?></td>
            <td class="num"><?= e($m['video']['avg_label']) ?><?php if ($m['video']['sent']): ?> <span class="text-muted">(<?= (int) $m['video']['answered'] ?>/<?= (int) $m['video']['sent'] ?>)</span><?php endif; ?></td>
            <td class="num"><?= e($m['ack']['avg_label']) ?><?php if ($m['ack']['sent']): ?> <span class="text-muted">(<?= (int) $m['ack']['answered'] ?>/<?= (int) $m['ack']['sent'] ?>)</span><?php endif; ?></td>
            <td class="num"><?= $rate !== null ? (int) $rate . '%' : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top:16px"><canvas id="respChart" height="120"></canvas></div>
    <div class="cap">Μέσος χρόνος απόκρισης (λεπτά) ανά ομάδα & τύπο αιτήματος.</div>
  </div>

  <!-- ── Timeline ───────────────────────────────────────────────────── -->
  <div class="sec">
    <h2><i class="bi bi-clock-history"></i> Χρονολόγιο γεγονότων</h2>
    <div class="tl">
      <?php $lastDate = null; foreach ($tl as $it): ?>
        <?php if ($it['date'] !== $lastDate): $lastDate = $it['date']; ?>
          <div style="font-size:.74rem;font-weight:800;color:var(--muted);margin:10px 0 4px;padding-left:6px"><?= e(gr_datetime($it['at']) ? date('d/m/Y', strtotime($it['at'])) : $it['date']) ?></div>
        <?php endif; ?>
        <div class="tl-item">
          <span class="tl-dot" style="background:<?= e($it['color']) ?>"><i class="bi <?= e($it['icon']) ?>"></i></span>
          <div class="tl-time"><?= e($it['time']) ?>
            <span class="badge-actor <?= $it['actor'] === 'command' ? 'a-command' : 'a-team' ?>"><?= $it['actor'] === 'command' ? 'Δήμος' : 'Ομάδα' ?></span>
            <?php if (!empty($it['team'])): ?><span class="tl-team"><?= e($it['team']) ?></span><?php endif; ?>
          </div>
          <div class="tl-title"><?= e($it['title']) ?></div>
          <?php if (!empty($it['detail'])): ?><div class="tl-detail"><?= e($it['detail']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$tl): ?><div class="text-muted small">Δεν καταγράφηκαν γεγονότα.</div><?php endif; ?>
    </div>
  </div>

  <!-- ── Παρουσίες & ώρες ───────────────────────────────────────────── -->
  <div class="sec">
    <h2><i class="bi bi-people-fill"></i> Παρουσίες & εθελοντικές ώρες</h2>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Ομάδα</th><th>Παρουσία</th><th class="num">Άτομα</th><th>Άφιξη → Αναχώρηση</th><?php if (!$isPublic): ?><th>Υπεύθυνος</th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($teams as $t): $c = $checks[$t['id']] ?? null;
            $st = $c['status'] ?? null;
            $stLabel = ['present_full'=>'Πλήρης','present_partial'=>'Μερική','departed'=>'Αποχώρησε'][$st] ?? '—';
            $ppl = $t['actual'] ?? ($c['present_people'] ?? $t['approved']);
          ?>
          <tr>
            <td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?= e($t['color']) ?>;margin-right:6px"></span><?= e($t['name']) ?></td>
            <td><?= e($stLabel) ?></td>
            <td class="num"><?= (int) $ppl ?> / <?= (int) $t['approved'] ?></td>
            <td class="small"><?= $t['arrival'] ? e(gr_time($t['arrival'])) : '—' ?> → <?= $t['departure'] ? e(gr_time($t['departure'])) : '—' ?></td>
            <?php if (!$isPublic): ?><td class="small"><?= e($t['commander']['full_name'] ?? '—') ?><?php if (!empty($t['commander']['phone'])): ?> · <?= e($t['commander']['phone']) ?><?php endif; ?></td><?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Ελλείψεις ──────────────────────────────────────────────────── -->
  <?php if ($shorts): ?>
  <div class="sec">
    <h2><i class="bi bi-exclamation-triangle"></i> Ελλείψεις / αναφορές</h2>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Ομάδα</th><th>Αναφορά</th><th>Κατάσταση</th><th class="num">Χρόνος επίλυσης</th></tr></thead>
        <tbody>
          <?php foreach ($shorts as $sh):
            $teamNm = '';
            foreach ($teams as $t) { if ($t['id'] === (int) $sh['team_id']) { $teamNm = $t['name']; break; } }
            $pill = $sh['status'] === 'resolved' ? 'p-res' : ($sh['status'] === 'acknowledged' ? 'p-ack' : 'p-open');
            $plabel = ['resolved'=>'Επιλύθηκε','acknowledged'=>'Ελήφθη'][$sh['status']] ?? 'Εκκρεμεί';
            $rt = $sh['resolved_at'] ? StoryService::dur(strtotime($sh['resolved_at']) - strtotime($sh['created_at'])) : '—';
          ?>
          <tr>
            <td><?= e($teamNm) ?></td>
            <td><strong><?= e($sh['title']) ?></strong> <span class="text-muted small"><?= e(shortage_type_label($sh['shortage_type'])) ?></span></td>
            <td><span class="pill <?= $pill ?>"><?= e($plabel) ?></span></td>
            <td class="num"><?= e($rt) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Gallery ────────────────────────────────────────────────────── -->
  <?php if ($photos || $videos): ?>
  <div class="sec">
    <h2><i class="bi bi-images"></i> Οπτικό υλικό</h2>
    <div class="gal">
      <?php foreach ($photos as $p): ?>
        <div>
          <a href="<?= e($photoSrc($p)) ?>" target="_blank"><img loading="lazy" src="<?= e($photoSrc($p)) ?>" alt=""></a>
          <?php if (!empty($p['caption'])): ?><div class="cap"><?= e($p['caption']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php foreach ($videos as $v): ?>
        <div>
          <div class="vid"><video controls preload="metadata" src="<?= e($videoSrc($v['id'])) ?>"></video></div>
          <div class="cap"><?= e($v['caption'] ?? '') ?><?php if (!empty($v['duration_sec'])): ?> · <?= (int) $v['duration_sec'] ?>″<?php endif; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="text-center text-muted small" style="margin-top:24px">
    Δημιουργήθηκε από το SynDrasi · <?= e(gr_datetime(date('Y-m-d H:i:s'))) ?>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
  var TEAMS   = <?= json_encode($jsTeams, JSON_UNESCAPED_UNICODE) ?>;
  var PINGS   = <?= json_encode($story['pingsByTeam'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var POINTS  = <?= json_encode($story['mapPoints'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var METRICS = <?= json_encode($metr, JSON_UNESCAPED_UNICODE) ?>;

  /* ── Map ── */
  (function(){
    var map = L.map('map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
    var colorById = {}; TEAMS.forEach(function(t){ colorById[t.id] = t.color; });
    var bounds = [];
    Object.keys(PINGS).forEach(function(tid){
      var pts = PINGS[tid].map(function(p){ return [p.lat, p.lng]; });
      if (!pts.length) return;
      pts.forEach(function(c){ bounds.push(c); });
      L.polyline(pts, { color: colorById[tid] || '#2563eb', weight: 4, opacity: .8 }).addTo(map);
      L.circleMarker(pts[0], { radius: 5, color: '#16a34a', fillColor:'#16a34a', fillOpacity:1 }).addTo(map).bindPopup('Έναρξη');
      L.circleMarker(pts[pts.length-1], { radius: 6, color: colorById[tid]||'#2563eb', fillColor: colorById[tid]||'#2563eb', fillOpacity:1 }).addTo(map);
    });
    var ICON = { move:'➡️', incident:'⚠️', poi:'📍', photo:'📷', video:'🎥' };
    POINTS.forEach(function(p){
      if (p.lat == null) return;
      bounds.push([p.lat, p.lng]);
      L.marker([p.lat, p.lng]).addTo(map).bindPopup('<b>' + (ICON[p.kind]||'') + ' ' + (p.label||'') + '</b><br>' + (p.team||'') + (p.body ? '<br>' + p.body : ''));
    });
    if (bounds.length) { map.fitBounds(bounds, { padding:[28,28], maxZoom:16 }); }
    else { map.setView([35.34, 25.13], 12); }
    setTimeout(function(){ map.invalidateSize(); }, 200);
  })();

  /* ── Response chart ── */
  (function(){
    if (!window.Chart || !METRICS.length) return;
    var labels = METRICS.map(function(m){ return m.team; });
    function ser(key){ return METRICS.map(function(m){ return m[key].avg_min; }); }
    new Chart(document.getElementById('respChart'), {
      type: 'bar',
      data: { labels: labels, datasets: [
        { label: 'Στίγμα', data: ser('gps'),   backgroundColor:'#2563eb' },
        { label: 'Φωτό',   data: ser('photo'), backgroundColor:'#16a34a' },
        { label: 'Βίντεο', data: ser('video'), backgroundColor:'#ea6c0a' },
        { label: 'ACK',    data: ser('ack'),   backgroundColor:'#9333ea' }
      ]},
      options: { responsive:true, scales:{ y:{ beginAtZero:true, title:{ display:true, text:'λεπτά' } } }, plugins:{ legend:{ position:'bottom' } } }
    });
  })();
</script>
</body>
</html>
