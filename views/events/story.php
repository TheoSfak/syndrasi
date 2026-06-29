<?php
/** SynDrasi — Story / Απολογισμός Δράσης (standalone presentation page). */
$ev     = $story['event'] ?? [];
$sm     = $story['summary'] ?? [];
$teams  = $story['teams'] ?? [];
$tl     = $story['timeline'] ?? [];
$metr   = array_values($story['metrics'] ?? []);
$photos = $story['photos'] ?? [];
$videos = $story['videos'] ?? [];
$shorts = $story['shortages'] ?? [];
$checks = $story['checkins'] ?? [];
$comms  = $story['communications'] ?? [];

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

$eventTitle = (string) ($ev['title'] ?? '');
$startD = $ev['start_datetime'] ?? null;
$endD   = $ev['end_datetime'] ?? null;
$heroPhoto = $photos[0] ?? null;
$heroImage = $heroPhoto ? $photoSrc($heroPhoto) : '';
$mediaCount = (int) ($sm['photos'] ?? 0) + (int) ($sm['videos'] ?? 0);
$duration = $sm['duration_h'] ?? null;
$location = trim((string) ($ev['location_name'] ?? ''));
$impactSentence = sprintf(
    'Σε %s, %d ομάδες κάλυψαν τη δράση με %d εθελοντές και %s ώρες προσφοράς.',
    $duration ? e((string) $duration) . ' ώρες' : 'μία επιχειρησιακή περίοδο',
    (int) ($sm['teams'] ?? 0),
    (int) ($sm['volunteers'] ?? 0),
    e((string) ($sm['hours'] ?? 0))
);

$teamImpact = [];
foreach ($teams as $t) {
    $arr = $t['arrival'] ?: $startD;
    $dep = $t['departure'] ?: $endD;
    $people = $t['actual'] ?? ($checks[$t['id']]['present_people'] ?? $t['approved']);
    $hours = ($arr && $dep) ? round(max(0, (strtotime($dep) - strtotime($arr)) / 3600) * (int) $people, 1) : 0;
    $teamImpact[] = [
        'id' => (int) $t['id'],
        'name' => $t['name'],
        'color' => $t['color'],
        'approved' => (int) $t['approved'],
        'people' => (int) $people,
        'hours' => $hours,
        'arrival' => $t['arrival'],
        'departure' => $t['departure'],
        'commander' => $t['commander'] ?? null,
    ];
}
usort($teamImpact, fn($a, $b) => $b['hours'] <=> $a['hours']);

$timelineGroups = [
    'start' => ['title' => 'Έναρξη & άφιξη', 'items' => []],
    'ops'   => ['title' => 'Επιχειρησιακή ροή', 'items' => []],
    'risk'  => ['title' => 'Περιστατικά & ελλείψεις', 'items' => []],
    'close' => ['title' => 'Ολοκλήρωση', 'items' => []],
];
foreach ($tl as $it) {
    $icon = (string) ($it['icon'] ?? '');
    $title = (string) ($it['title'] ?? '');
    $key = 'ops';
    if (str_contains($icon, 'box-arrow-in') || str_contains($title, 'παρουσίας')) { $key = 'start'; }
    if (str_contains($icon, 'exclamation') || str_contains($title, 'SOS') || str_contains($title, 'Έλλειψη')) { $key = 'risk'; }
    if (str_contains($icon, 'shield-check') || str_contains($title, 'επιλύθηκε') || str_contains($title, 'Αποχώρηση')) { $key = 'close'; }
    $timelineGroups[$key]['items'][] = $it;
}

$jsTeams = array_map(fn($t) => ['id' => (int) $t['id'], 'name' => $t['name'], 'color' => $t['color']], $teams);
$jsGallery = [];
foreach ($photos as $p) {
    $jsGallery[] = [
        'type' => 'photo',
        'src' => $photoSrc($p),
        'caption' => (string) ($p['caption'] ?? ''),
        'team' => '',
    ];
}
foreach ($videos as $v) {
    $jsGallery[] = [
        'type' => 'video',
        'src' => $videoSrc($v['id']),
        'caption' => (string) ($v['caption'] ?? ''),
        'team' => '',
    ];
}
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
  :root{
    --ink:#111827;--muted:#667085;--line:#e6eaf0;--paper:#ffffff;--soft:#f6f8fb;
    --brand:#0f766e;--blue:#2563eb;--amber:#d97706;--rose:#be123c;--night:#111827;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{margin:0;background:var(--soft);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
  a{color:inherit}
  .containerx{width:min(1180px,calc(100% - 32px));margin:0 auto}
  .hero{min-height:88vh;position:relative;color:#fff;display:flex;align-items:flex-end;overflow:hidden;background:#111827}
  .hero::before{content:"";position:absolute;inset:0;background-image:var(--hero);background-size:cover;background-position:center;filter:saturate(1.04);transform:scale(1.02)}
  .hero::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(10,18,32,.9),rgba(10,18,32,.58) 48%,rgba(10,18,32,.12)),linear-gradient(0deg,rgba(10,18,32,.86),rgba(10,18,32,0) 55%)}
  .hero.no-photo::before{background:linear-gradient(135deg,#111827 0%,#0f766e 58%,#d97706 100%);filter:none}
  .hero-inner{position:relative;z-index:1;padding:34px 0 38px;width:100%}
  .kicker{display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-weight:800;letter-spacing:.04em;text-transform:uppercase;font-size:.78rem;color:#d1fae5}
  .kicker img{height:46px;max-width:160px;object-fit:contain;background:#fff;border-radius:8px;padding:4px}
  .public-chip{display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,.32);background:rgba(255,255,255,.14);border-radius:999px;padding:5px 10px;color:#fff;text-transform:none;letter-spacing:0}
  h1.hero-title{font-size:clamp(2.4rem,7vw,5.8rem);line-height:.98;font-weight:900;max-width:980px;margin:18px 0 14px;letter-spacing:0}
  .hero-lede{font-size:clamp(1.05rem,2vw,1.35rem);max-width:760px;color:#e5edf4;margin:0 0 24px}
  .hero-meta{display:flex;gap:14px;flex-wrap:wrap;color:#d9e5ef;font-size:.95rem}
  .hero-meta span{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.11);border:1px solid rgba(255,255,255,.18);border-radius:999px;padding:8px 12px}
  .hero-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:28px}
  .hero-stat{background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(8px);border-radius:8px;padding:14px 14px;min-height:86px}
  .hero-stat .v{font-size:clamp(1.4rem,3vw,2.1rem);font-weight:900;line-height:1;font-variant-numeric:tabular-nums}
  .hero-stat .l{font-size:.72rem;color:#dce7ef;text-transform:uppercase;letter-spacing:.08em;margin-top:8px}
  .story-nav{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
  .story-nav .containerx{display:flex;gap:6px;overflow:auto;padding:9px 0}
  .story-nav a{white-space:nowrap;text-decoration:none;color:#334155;border-radius:999px;padding:8px 12px;font-size:.86rem;font-weight:800}
  .story-nav a:hover{background:#eef2f7;color:#0f766e}
  .toolbar{display:flex;gap:8px;flex-wrap:wrap;padding:18px 0 0}
  .section{padding:34px 0}
  .section-head{display:flex;justify-content:space-between;gap:16px;align-items:end;margin-bottom:16px}
  .eyebrow{font-size:.74rem;text-transform:uppercase;letter-spacing:.1em;color:var(--brand);font-weight:900;margin-bottom:6px}
  h2{font-size:clamp(1.45rem,3vw,2.2rem);font-weight:900;margin:0;letter-spacing:0}
  .section-sub{color:var(--muted);margin:8px 0 0;max-width:720px}
  .panel{background:var(--paper);border:1px solid var(--line);border-radius:8px;box-shadow:0 12px 34px rgba(15,23,42,.06)}
  .impact-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}
  .impact-copy{padding:26px}
  .impact-copy p{font-size:1.08rem;line-height:1.7;color:#334155;margin:0}
  .impact-mini{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:16px}
  .mini{border:1px solid var(--line);border-radius:8px;padding:14px;background:#fbfcfe}
  .mini i{color:var(--brand);font-size:1.25rem}
  .mini b{display:block;font-size:1.25rem;margin-top:8px}
  .mini span{color:var(--muted);font-size:.82rem}
  .map-shell{display:grid;grid-template-columns:minmax(0,1fr) 260px;gap:0;overflow:hidden}
  .map-stage{min-width:0;background:#f8fafc}
  .replay-panel{display:grid;grid-template-columns:auto auto minmax(120px,1fr) auto;gap:10px;align-items:center;padding:12px;border-bottom:1px solid var(--line);background:#fff}
  .replay-btn{border:0;border-radius:8px;background:#111827;color:#fff;font-weight:900;padding:9px 13px;display:inline-flex;align-items:center;gap:7px}
  .replay-btn.active{background:#2563eb}
  .replay-ghost{border:1px solid var(--line);border-radius:8px;background:#fff;color:#334155;font-weight:900;padding:9px 11px}
  .replay-speed{border:1px solid var(--line);border-radius:8px;background:#fff;color:#334155;font-weight:800;padding:8px 10px}
  .replay-range{width:100%;accent-color:var(--brand)}
  .movement-panel{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:10px 12px;border-bottom:1px solid var(--line);background:#fff}
  .movement-panel select{border:1px solid var(--line);border-radius:8px;background:#fff;color:#334155;font-weight:800;padding:8px 10px}
  .movement-hint{color:var(--muted);font-size:.8rem;font-weight:700}
  .replay-current{padding:12px;border-bottom:1px solid var(--line);background:#fbfcfe;display:flex;gap:12px;align-items:flex-start;min-height:72px}
  .replay-current .rc-icon{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;color:#fff;flex:0 0 auto}
  .replay-current .rc-title{font-weight:900}
  .replay-current .rc-detail{font-size:.84rem;color:var(--muted);margin-top:2px}
  .replay-current .rc-time{font-size:.74rem;color:var(--brand);font-weight:900;text-transform:uppercase;letter-spacing:.05em}
  #map{height:560px;min-height:62vh;background:#dbe4ee}
  .map-side{border-left:1px solid var(--line);padding:16px;max-height:560px;overflow:auto;background:#fbfcfe}
  .team-filter{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px;margin-bottom:8px;cursor:pointer}
  .event-filter{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:8px;margin-bottom:7px;cursor:pointer;font-size:.86rem}
  .event-filter input,.team-filter input{accent-color:var(--brand)}
  .filter-group-title{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:900;margin:16px 0 8px}
  .team-filter input{accent-color:var(--brand)}
  .swatch{width:14px;height:14px;border-radius:4px;display:inline-block;flex:0 0 auto}
  .map-help{font-size:.8rem;color:var(--muted);line-height:1.45;margin-top:12px}
  .route-icon{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;border:2px solid #fff;box-shadow:0 4px 14px rgba(15,23,42,.25);font-weight:900}
  .replay-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;color:#fff;border:2px solid #fff;box-shadow:0 8px 18px rgba(15,23,42,.28);font-weight:900}
  .move-arrow-head{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;color:#fff;border:2px solid #fff;box-shadow:0 8px 20px rgba(37,99,235,.3);font-size:18px}
  .move-time-badge{background:#fff;border:2px solid #2563eb;color:#111827;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:900;box-shadow:0 8px 18px rgba(15,23,42,.18);white-space:nowrap}
  .metrics-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px}
  .table-wrap{overflow:auto}
  table{width:100%;border-collapse:collapse;font-size:.88rem}
  th,td{padding:10px 11px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}
  th{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
  .num{text-align:right;font-variant-numeric:tabular-nums}
  .metric-chart{padding:16px;display:flex;align-items:center}
  .team-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
  .team-card{padding:16px;border-top:5px solid var(--brand)}
  .team-card h3{font-size:1rem;font-weight:900;margin:0 0 8px}
  .team-card .big{font-size:1.6rem;font-weight:900;line-height:1}
  .team-card .muted{color:var(--muted);font-size:.82rem}
  .team-card .rowx{display:flex;justify-content:space-between;gap:12px;margin-top:12px}
  .timeline-layout{display:grid;grid-template-columns:280px minmax(0,1fr);gap:18px}
  .phase-list{position:sticky;top:58px;align-self:start}
  .phase{display:block;text-decoration:none;border-left:4px solid var(--line);padding:10px 12px;margin-bottom:8px;background:#fff;border-radius:0 8px 8px 0;color:#334155;font-weight:800}
  .phase:hover{border-left-color:var(--brand);color:var(--brand)}
  .phase small{display:block;color:var(--muted);font-weight:700;margin-top:2px}
  .tl-group{margin-bottom:24px}
  .tl-title{font-size:1rem;font-weight:900;margin:0 0 10px;color:#334155}
  .tl{position:relative;margin-left:7px}
  .tl::before{content:"";position:absolute;left:15px;top:2px;bottom:2px;width:2px;background:var(--line)}
  .tl-item{position:relative;padding:8px 0 10px 46px;min-height:42px}
  .tl-dot{position:absolute;left:4px;top:8px;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;box-shadow:0 0 0 4px #fff}
  .tl-time{font-size:.74rem;color:var(--muted);font-variant-numeric:tabular-nums;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .tl-name{font-weight:900;font-size:.95rem;margin-top:2px}
  .tl-detail{font-size:.86rem;color:#445267;margin-top:2px}
  .actor{font-size:.66rem;padding:.14rem .44rem;border-radius:6px;font-weight:900}
  .actor.command{background:#fff7ed;color:#9a3412}
  .actor.team{background:#dbeafe;color:#1d4ed8}
  .actor.system{background:#f1f5f9;color:#475569}
  .team-tag{font-size:.75rem;color:var(--brand);font-weight:900}
  .comm-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
  .comm-filter{border:1px solid var(--line);background:#fff;color:#334155;border-radius:999px;padding:8px 12px;font-weight:900;font-size:.82rem}
  .comm-filter.active{background:#111827;color:#fff;border-color:#111827}
  .comm-list{display:grid;gap:10px}
  .comm-item{border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px 16px;display:grid;grid-template-columns:42px minmax(0,1fr);gap:12px}
  .comm-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;color:#fff;margin-top:2px}
  .comm-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;color:var(--muted);font-size:.76rem;font-weight:800}
  .comm-kind{border-radius:999px;padding:.16rem .48rem;background:#eef2f7;color:#334155}
  .comm-actor.command{color:#9a3412}.comm-actor.team{color:#1d4ed8}
  .comm-body{margin-top:7px;color:#1f2937;line-height:1.55;white-space:pre-wrap}
  .comm-ack{margin-top:7px;color:#15803d;font-size:.78rem;font-weight:900}
  .short-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
  .short-card{padding:16px;border-left:5px solid var(--rose)}
  .pill{font-size:.74rem;padding:.22rem .6rem;border-radius:999px;font-weight:900;display:inline-flex}
  .p-open{background:#fee2e2;color:#991b1b}.p-ack{background:#dbeafe;color:#1e40af}.p-res{background:#dcfce7;color:#166534}
  .gallery{columns:4 190px;column-gap:12px}
  .media-card{break-inside:avoid;display:block;width:100%;margin:0 0 12px;padding:0;text-align:left;border-radius:8px;overflow:hidden;border:1px solid var(--line);background:#111827;cursor:pointer;position:relative;font:inherit;color:inherit}
  .media-card img,.media-card video{width:100%;display:block}
  .media-card video{aspect-ratio:4/3;object-fit:cover}
  .media-caption{background:#fff;padding:8px 10px;color:#475569;font-size:.8rem}
  .play-badge{position:absolute;top:10px;right:10px;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.9);color:#111827}
  .thanks{background:#111827;color:#fff;border-radius:8px;padding:30px;display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center}
  .thanks h2{color:#fff}
  .thanks p{color:#cbd5e1;margin:8px 0 0}
  .footer-note{text-align:center;color:var(--muted);font-size:.84rem;padding:28px 0 38px}
  .lightbox{position:fixed;inset:0;background:rgba(7,12,20,.92);z-index:2000;display:none;align-items:center;justify-content:center;padding:22px}
  .lightbox.open{display:flex}
  .lightbox-frame{width:min(1000px,100%);max-height:92vh}
  .lightbox-media{background:#000;border-radius:8px;overflow:hidden;display:grid;place-items:center}
  .lightbox-media img,.lightbox-media video{max-width:100%;max-height:78vh;display:block}
  .lightbox-cap{color:#e5e7eb;margin-top:10px}
  .lightbox-close{position:absolute;top:14px;right:16px;background:#fff;border:0;border-radius:50%;width:42px;height:42px;font-size:20px}
  @media (max-width:900px){
    .hero{min-height:82vh}.hero-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    .impact-grid,.map-shell,.metrics-grid,.timeline-layout,.thanks{grid-template-columns:1fr}
    .replay-panel{grid-template-columns:auto auto 1fr;gap:8px}.replay-speed{grid-column:1 / -1;width:100%}
    .map-side{border-left:0;border-top:1px solid var(--line);max-height:none}
    #map{height:430px;min-height:0}.phase-list{position:static}.section-head{display:block}
  }
  @media print{
    body{background:#fff}.noprint,.story-nav,.lightbox{display:none!important}.hero{min-height:auto;color:#111827;background:#fff}
    .hero::before,.hero::after{display:none}.hero-inner{padding:20px 0}.hero-lede,.hero-meta span,.kicker{color:#334155}
    .hero-stats{grid-template-columns:repeat(3,1fr)}.hero-stat,.panel{box-shadow:none;border-color:#d1d5db;background:#fff;color:#111827}
    #map{height:340px;min-height:0}.section{padding:20px 0}.thanks{color:#111827;background:#fff;border:1px solid #d1d5db}.thanks h2{color:#111827}.thanks p{color:#334155}
  }
</style>
</head>
<body>
<header class="hero <?= $heroImage ? '' : 'no-photo' ?>" style="--hero:url('<?= e($heroImage) ?>')">
  <div class="hero-inner">
    <div class="containerx">
      <div class="kicker">
        <?php if (!empty($logo)): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <span><?= e($orgLabel ?? 'Δήμος') ?> · Απολογισμός Δράσης</span>
        <?php if ($isPublic): ?><span class="public-chip"><i class="bi bi-globe2"></i>Δημόσια έκδοση</span><?php endif; ?>
      </div>
      <h1 class="hero-title"><?= e($eventTitle) ?></h1>
      <p class="hero-lede"><?= $impactSentence ?></p>
      <div class="hero-meta">
        <span><i class="bi bi-calendar-event"></i><?= e(gr_datetime($startD)) ?> → <?= e(gr_datetime($endD)) ?></span>
        <?php if ($location !== ''): ?><span><i class="bi bi-geo-alt-fill"></i><?= e($location) ?></span><?php endif; ?>
        <?php if ($duration): ?><span><i class="bi bi-hourglass-split"></i>Διάρκεια <?= e((string) $duration) ?>ω</span><?php endif; ?>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><div class="v"><?= (int) ($sm['teams'] ?? 0) ?></div><div class="l">Ομάδες</div></div>
        <div class="hero-stat"><div class="v"><?= (int) ($sm['volunteers'] ?? 0) ?></div><div class="l">Εθελοντές</div></div>
        <div class="hero-stat"><div class="v"><?= e($sm['hours'] ?? 0) ?></div><div class="l">Ώρες προσφοράς</div></div>
        <div class="hero-stat"><div class="v"><?= (int) ($sm['orders'] ?? 0) ?></div><div class="l">Εντολές</div></div>
        <div class="hero-stat"><div class="v"><?= (int) ($sm['pings'] ?? 0) ?></div><div class="l">Στίγματα</div></div>
        <div class="hero-stat"><div class="v"><?= $mediaCount ?></div><div class="l">Φωτό / Βίντεο</div></div>
      </div>
    </div>
  </div>
</header>

<nav class="story-nav noprint" aria-label="Πλοήγηση απολογισμού">
  <div class="containerx">
    <a href="#summary">Σύνοψη</a>
    <a href="#map-section">Χάρτης</a>
    <a href="#teams">Ομάδες</a>
    <a href="#timeline">Χρονολόγιο</a>
    <?php if ($comms): ?><a href="#communications">Επικοινωνίες</a><?php endif; ?>
    <a href="#media">Υλικό</a>
  </div>
</nav>

<main class="containerx">
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

  <section class="section" id="summary">
    <div class="section-head">
      <div>
        <div class="eyebrow">Η εικόνα της δράσης</div>
        <h2>Από την κινητοποίηση μέχρι την ολοκλήρωση</h2>
        <p class="section-sub">Συγκεντρωμένη εικόνα της επιχειρησιακής παρουσίας, της ανταπόκρισης των ομάδων και του υλικού που καταγράφηκε στο πεδίο.</p>
      </div>
    </div>
    <div class="impact-grid">
      <div class="panel impact-copy">
        <p><?= $impactSentence ?> Καταγράφηκαν <?= (int) ($sm['pings'] ?? 0) ?> στίγματα, <?= (int) ($sm['orders'] ?? 0) ?> επιχειρησιακές εντολές και <?= $mediaCount ?> τεκμήρια από το πεδίο.</p>
      </div>
      <div class="panel impact-mini">
        <div class="mini"><i class="bi bi-people-fill"></i><b><?= (int) ($sm['volunteers'] ?? 0) ?></b><span>συνολική δύναμη</span></div>
        <div class="mini"><i class="bi bi-clock-history"></i><b><?= e($sm['hours'] ?? 0) ?></b><span>ώρες προσφοράς</span></div>
        <div class="mini"><i class="bi bi-exclamation-triangle"></i><b><?= (int) ($sm['shortages'] ?? 0) ?></b><span>αναφορές ελλείψεων</span></div>
        <div class="mini"><i class="bi bi-shield-check"></i><b><?= (int) ($sm['sos'] ?? 0) ?></b><span>SOS συμβάντα</span></div>
      </div>
    </div>
  </section>

  <section class="section" id="map-section">
    <div class="section-head">
      <div>
        <div class="eyebrow">Live αποτύπωση</div>
        <h2>Χάρτης δράσης & διαδρομές ομάδων</h2>
        <p class="section-sub">Οι διαδρομές, τα σημεία ενδιαφέροντος και το υλικό πεδίου εμφανίζονται μαζί ώστε η ροή της δράσης να διαβάζεται με μια ματιά.</p>
      </div>
    </div>
    <div class="panel map-shell">
      <div class="map-stage">
        <div class="replay-panel">
          <button type="button" class="replay-btn" id="replayPlay"><i class="bi bi-play-fill"></i><span>Play</span></button>
          <button type="button" class="replay-ghost" id="replayReset" title="Αρχή"><i class="bi bi-skip-backward-fill"></i></button>
          <input type="range" min="0" max="0" value="0" class="replay-range" id="replayRange" aria-label="Χρονογραμμή replay">
          <select class="replay-speed" id="replaySpeed" aria-label="Ταχύτητα replay">
            <option value="1">1x</option>
            <option value="2">2x</option>
            <option value="4">4x</option>
          </select>
        </div>
        <div class="movement-panel">
          <button type="button" class="replay-btn" id="movementOverview"><i class="bi bi-signpost-split-fill"></i><span>Μετακινήσεις</span></button>
          <select id="movementScope" aria-label="Ομάδες μετακινήσεων">
            <option value="selected">Επιλεγμένες ομάδες</option>
            <option value="all">Όλες οι ομάδες</option>
          </select>
          <span class="movement-hint" id="movementHint">Δείχνει όλα τα βελάκια μετακίνησης με την ώρα τους.</span>
        </div>
        <div class="replay-current" id="replayCurrent">
          <div class="rc-icon" style="background:#64748b"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="rc-time">Replay δράσης</div>
            <div class="rc-title">Πατήστε Play για να εμφανιστούν τα γεγονότα με σειρά.</div>
            <div class="rc-detail">Μπορείτε να σύρετε τη μπάρα χρόνου ή να φιλτράρετε ομάδες και τύπους γεγονότων.</div>
          </div>
        </div>
        <div id="map"></div>
      </div>
      <aside class="map-side">
        <h3 class="h6 fw-bold mb-2">Φίλτρα ομάδων</h3>
        <?php foreach ($teams as $t): ?>
          <label class="team-filter">
            <input type="checkbox" class="route-toggle" value="<?= (int) $t['id'] ?>" checked>
            <span class="swatch" style="background:<?= e($t['color']) ?>"></span>
            <span><?= e($t['name']) ?></span>
          </label>
        <?php endforeach; ?>
        <div class="filter-group-title">Γεγονότα replay</div>
        <label class="event-filter"><input type="checkbox" class="event-toggle" value="gps_request" checked><i class="bi bi-broadcast-pin text-primary"></i><span>Ζητήθηκε στίγμα</span></label>
        <label class="event-filter"><input type="checkbox" class="event-toggle" value="gps_response" checked><i class="bi bi-geo-alt-fill text-success"></i><span>Απάντηση GPS</span></label>
        <label class="event-filter"><input type="checkbox" class="event-toggle" value="ping" checked><i class="bi bi-dot text-success"></i><span>Στίγματα ομάδων</span></label>
        <label class="event-filter"><input type="checkbox" class="event-toggle" value="photo" checked><i class="bi bi-camera-fill text-info"></i><span>Φωτογραφίες</span></label>
        <label class="event-filter"><input type="checkbox" class="event-toggle" value="video" checked><i class="bi bi-camera-video-fill text-info"></i><span>Βίντεο</span></label>
        <label class="event-filter"><input type="checkbox" class="event-toggle" value="incident" checked><i class="bi bi-exclamation-triangle-fill text-danger"></i><span>Περιστατικά / SOS</span></label>
        <label class="event-filter"><input type="checkbox" class="event-toggle" value="shortage" checked><i class="bi bi-tools text-warning"></i><span>Ελλείψεις</span></label>
        <label class="event-filter"><input type="checkbox" class="event-toggle" value="order" checked><i class="bi bi-megaphone-fill text-secondary"></i><span>Σημεία / μετακινήσεις</span></label>
        <div class="map-help">
          <i class="bi bi-info-circle me-1"></i>Το replay δείχνει ζητήματα, αιτήματα GPS, απαντήσεις και στίγματα με χρονολογική σειρά.
        </div>
      </aside>
    </div>
  </section>

  <section class="section">
    <div class="section-head">
      <div>
        <div class="eyebrow">Ανταπόκριση</div>
        <h2>Χρόνοι απόκρισης ανά ομάδα</h2>
        <p class="section-sub">Μέσος χρόνος σε αιτήματα στίγματος, φωτογραφίας, βίντεο και επιβεβαίωση εντολών.</p>
      </div>
    </div>
    <div class="metrics-grid">
      <div class="panel table-wrap">
        <table>
          <thead><tr>
            <th>Ομάδα</th>
            <th class="num">Στίγμα</th>
            <th class="num">Φωτό</th>
            <th class="num">Βίντεο</th>
            <th class="num">ACK</th>
            <th class="num">Ανταπόκριση</th>
          </tr></thead>
          <tbody>
            <?php foreach ($metr as $m):
              $tot = ($m['gps']['sent'] + $m['photo']['sent'] + $m['video']['sent']);
              $ans = ($m['gps']['answered'] + $m['photo']['answered'] + $m['video']['answered']);
              $rate = $tot ? round($ans / $tot * 100) : null;
            ?>
            <tr>
              <td><span class="swatch" style="background:<?= e($m['color']) ?>"></span> <?= e($m['team']) ?></td>
              <td class="num"><?= e($m['gps']['avg_label']) ?></td>
              <td class="num"><?= e($m['photo']['avg_label']) ?></td>
              <td class="num"><?= e($m['video']['avg_label']) ?></td>
              <td class="num"><?= e($m['ack']['avg_label']) ?></td>
              <td class="num"><?= $rate !== null ? (int) $rate . '%' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="panel metric-chart"><canvas id="respChart" height="260"></canvas></div>
    </div>
  </section>

  <section class="section" id="teams">
    <div class="section-head">
      <div>
        <div class="eyebrow">Αναγνώριση</div>
        <h2>Οι ομάδες της δράσης</h2>
        <p class="section-sub">Κάθε κάρτα δείχνει την επιχειρησιακή παρουσία και τη συνεισφορά της ομάδας στον συνολικό απολογισμό.</p>
      </div>
    </div>
    <div class="team-grid">
      <?php foreach ($teamImpact as $t): ?>
        <article class="panel team-card" style="border-top-color:<?= e($t['color']) ?>">
          <h3><?= e($t['name']) ?></h3>
          <div class="rowx">
            <div><div class="big"><?= (int) $t['people'] ?></div><div class="muted">άτομα παρόντα</div></div>
            <div class="text-end"><div class="big"><?= e((string) $t['hours']) ?></div><div class="muted">ώρες</div></div>
          </div>
          <div class="rowx muted">
            <span><i class="bi bi-box-arrow-in-right me-1"></i><?= $t['arrival'] ? e(gr_time($t['arrival'])) : '—' ?></span>
            <span><i class="bi bi-box-arrow-right me-1"></i><?= $t['departure'] ? e(gr_time($t['departure'])) : '—' ?></span>
          </div>
          <?php if (!$isPublic && !empty($t['commander'])): ?>
            <div class="muted mt-2"><i class="bi bi-person-badge me-1"></i><?= e($t['commander']['full_name'] ?? '—') ?><?php if (!empty($t['commander']['phone'])): ?> · <?= e($t['commander']['phone']) ?><?php endif; ?></div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" id="timeline">
    <div class="section-head">
      <div>
        <div class="eyebrow">Όπως συνέβη</div>
        <h2>Χρονολόγιο γεγονότων</h2>
        <p class="section-sub">Η δράση χωρίζεται σε φάσεις για να φαίνεται καθαρά η επιχειρησιακή ροή.</p>
      </div>
    </div>
    <div class="timeline-layout">
      <aside class="phase-list noprint">
        <?php foreach ($timelineGroups as $key => $group): if (!$group['items']) { continue; } ?>
          <a class="phase" href="#phase-<?= e($key) ?>"><?= e($group['title']) ?><small><?= count($group['items']) ?> γεγονότα</small></a>
        <?php endforeach; ?>
      </aside>
      <div>
        <?php foreach ($timelineGroups as $key => $group): if (!$group['items']) { continue; } ?>
          <section class="panel tl-group" id="phase-<?= e($key) ?>">
            <div style="padding:16px 18px">
              <h3 class="tl-title"><?= e($group['title']) ?></h3>
              <div class="tl">
                <?php foreach ($group['items'] as $it): ?>
                  <div class="tl-item">
                    <span class="tl-dot" style="background:<?= e($it['color']) ?>"><i class="bi <?= e($it['icon']) ?>"></i></span>
                    <div class="tl-time">
                      <?= e($it['date']) ?> · <?= e($it['time']) ?>
                      <span class="actor <?= e($it['actor']) ?>"><?= $it['actor'] === 'command' ? 'Δήμος' : ($it['actor'] === 'team' ? 'Ομάδα' : 'Σύστημα') ?></span>
                      <?php if (!empty($it['team'])): ?><span class="team-tag"><?= e($it['team']) ?></span><?php endif; ?>
                    </div>
                    <div class="tl-name"><?= e($it['title']) ?></div>
                    <?php if (!empty($it['detail'])): ?><div class="tl-detail"><?= e($it['detail']) ?></div><?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </section>
        <?php endforeach; ?>
        <?php if (!$tl): ?><div class="panel p-4 text-muted">Δεν καταγράφηκαν γεγονότα.</div><?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ($comms): ?>
  <section class="section" id="communications">
    <div class="section-head">
      <div>
        <div class="eyebrow">Επικοινωνίες πεδίου</div>
        <h2>Διάλογοι Δήμου και ομάδων</h2>
        <p class="section-sub">Οι βασικές εντολές, ενημερώσεις και απαντήσεις της δράσης, με ευαίσθητα στοιχεία μασκαρισμένα όπου εντοπίζονται.</p>
      </div>
    </div>
    <div class="comm-toolbar noprint" aria-label="Φίλτρα επικοινωνιών">
      <button type="button" class="comm-filter active" data-comm-filter="all">Όλα</button>
      <button type="button" class="comm-filter" data-comm-filter="command">Δήμος / φορέας</button>
      <button type="button" class="comm-filter" data-comm-filter="team">Ομάδες</button>
      <button type="button" class="comm-filter" data-comm-filter="order">Εντολές</button>
      <button type="button" class="comm-filter" data-comm-filter="status">Ενημερώσεις</button>
      <button type="button" class="comm-filter" data-comm-filter="move">Μετακινήσεις</button>
      <button type="button" class="comm-filter" data-comm-filter="message">Μηνύματα</button>
    </div>
    <div class="comm-list" id="commList">
      <?php foreach ($comms as $c): ?>
        <article class="comm-item" data-actor="<?= e($c['actor']) ?>" data-type="<?= e($c['type']) ?>">
          <div class="comm-icon" style="background:<?= e($c['color']) ?>"><i class="bi <?= e($c['icon']) ?>"></i></div>
          <div>
            <div class="comm-meta">
              <span><?= e($c['date']) ?> · <?= e($c['time']) ?></span>
              <span class="comm-actor <?= e($c['actor']) ?>"><?= e($c['actor_label']) ?></span>
              <?php if (!empty($c['team'])): ?><span class="team-tag"><?= e($c['team']) ?></span><?php endif; ?>
              <span class="comm-kind"><?= e($c['type_label']) ?></span>
              <?php if (!empty($c['has_geo'])): ?><span><i class="bi bi-geo-alt-fill"></i>Σημείο χάρτη</span><?php endif; ?>
            </div>
            <div class="comm-body"><?= e($c['body'] !== '' ? $c['body'] : 'Χωρίς κείμενο.') ?></div>
            <?php if (!empty($c['acknowledged_at'])): ?>
              <div class="comm-ack"><i class="bi bi-check2-all me-1"></i>Επιβεβαιώθηκε <?= e(gr_time($c['acknowledged_at'])) ?></div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($shorts): ?>
  <section class="section">
    <div class="section-head">
      <div>
        <div class="eyebrow">Διαχείριση αναγκών</div>
        <h2>Ελλείψεις / αναφορές</h2>
      </div>
    </div>
    <div class="short-grid">
      <?php foreach ($shorts as $sh):
        $teamNm = '';
        foreach ($teams as $t) { if ($t['id'] === (int) $sh['team_id']) { $teamNm = $t['name']; break; } }
        $pill = $sh['status'] === 'resolved' ? 'p-res' : ($sh['status'] === 'acknowledged' ? 'p-ack' : 'p-open');
        $plabel = ['resolved'=>'Επιλύθηκε','acknowledged'=>'Ελήφθη'][$sh['status']] ?? 'Εκκρεμεί';
        $rt = $sh['resolved_at'] ? StoryService::dur(strtotime($sh['resolved_at']) - strtotime($sh['created_at'])) : '—';
      ?>
        <article class="panel short-card">
          <div class="d-flex justify-content-between gap-2 align-items-start">
            <strong><?= e($sh['title']) ?></strong>
            <span class="pill <?= $pill ?>"><?= e($plabel) ?></span>
          </div>
          <div class="text-muted small mt-1"><?= e($teamNm) ?> · <?= e(shortage_type_label($sh['shortage_type'])) ?></div>
          <div class="small mt-3"><i class="bi bi-stopwatch me-1"></i>Χρόνος επίλυσης: <strong><?= e($rt) ?></strong></div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($photos || $videos): ?>
  <section class="section" id="media">
    <div class="section-head">
      <div>
        <div class="eyebrow">Από το πεδίο</div>
        <h2>Οπτικό υλικό</h2>
        <p class="section-sub">Φωτογραφίες και βίντεο που τεκμηριώνουν τη δράση.</p>
      </div>
    </div>
    <div class="gallery">
      <?php $idx = 0; foreach ($photos as $p): ?>
        <button type="button" class="media-card" data-gallery="<?= $idx++ ?>" aria-label="Άνοιγμα φωτογραφίας">
          <img loading="lazy" src="<?= e($photoSrc($p)) ?>" alt="">
          <?php if (!empty($p['caption'])): ?><div class="media-caption"><?= e($p['caption']) ?></div><?php endif; ?>
        </button>
      <?php endforeach; ?>
      <?php foreach ($videos as $v): ?>
        <button type="button" class="media-card" data-gallery="<?= $idx++ ?>" aria-label="Άνοιγμα βίντεο">
          <span class="play-badge"><i class="bi bi-play-fill"></i></span>
          <video muted preload="metadata" src="<?= e($videoSrc($v['id'])) ?>"></video>
          <div class="media-caption"><?= e($v['caption'] ?? 'Βίντεο') ?><?php if (!empty($v['duration_sec'])): ?> · <?= (int) $v['duration_sec'] ?>″<?php endif; ?></div>
        </button>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="section">
    <div class="thanks">
      <div>
        <div class="eyebrow" style="color:#fbbf24">Ευχαριστούμε</div>
        <h2>Η δράση ολοκληρώθηκε χάρη στη συνεργασία όλων.</h2>
        <p>Ο απολογισμός κρατά ζωντανή την εικόνα της προσφοράς και βοηθά τον δήμο να αναγνωρίζει την πραγματική συμβολή των εθελοντικών ομάδων.</p>
      </div>
      <div style="font-size:3rem"><i class="bi bi-award"></i></div>
    </div>
  </section>

  <div class="footer-note">Δημιουργήθηκε από το SynDrasi · <?= e(gr_datetime(date('Y-m-d H:i:s'))) ?></div>
</main>

<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Προβολή υλικού">
  <button type="button" class="lightbox-close" id="lightboxClose" aria-label="Κλείσιμο"><i class="bi bi-x"></i></button>
  <div class="lightbox-frame">
    <div class="lightbox-media" id="lightboxMedia"></div>
    <div class="lightbox-cap" id="lightboxCap"></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
  var TEAMS   = <?= json_encode($jsTeams, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var PINGS   = <?= json_encode($story['pingsByTeam'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var POINTS  = <?= json_encode($story['mapPoints'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var REPLAY  = <?= json_encode($story['replayEvents'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var METRICS = <?= json_encode($metr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var GALLERY = <?= json_encode($jsGallery, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  function escHtml(s){ var d=document.createElement('div'); d.textContent=(s==null?'':String(s)); return d.innerHTML; }

  (function(){
    var map = L.map('map', { scrollWheelZoom:false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
    var colorById = {}, nameById = {}, routeLayers = {}, bounds = [];
    var replayMarkerLayer = L.layerGroup().addTo(map);
    var replayLineLayer = L.layerGroup().addTo(map);
    var movementLayer = L.layerGroup().addTo(map);
    var replayTimer = null;
    var replayIndex = 0;
    var movementOverviewOn = false;
    TEAMS.forEach(function(t){ colorById[t.id] = t.color; nameById[t.id] = t.name; });

    Object.keys(PINGS).forEach(function(tid){
      var pts = (PINGS[tid] || []).map(function(p){ return [p.lat, p.lng]; });
      if (!pts.length) return;
      pts.forEach(function(c){ bounds.push(c); });
      var group = L.layerGroup();
      L.polyline(pts, { color: colorById[tid] || '#2563eb', weight: 4, opacity: .82 }).addTo(group);
      L.marker(pts[0], { icon: L.divIcon({ className:'', iconSize:[30,30], iconAnchor:[15,15], html:'<div class="route-icon" style="background:#16a34a"><i class="bi bi-play-fill"></i></div>' }) }).bindPopup('Έναρξη · ' + escHtml(nameById[tid] || '')).addTo(group);
      L.marker(pts[pts.length-1], { icon: L.divIcon({ className:'', iconSize:[30,30], iconAnchor:[15,15], html:'<div class="route-icon" style="background:' + (colorById[tid] || '#2563eb') + '"><i class="bi bi-geo-alt-fill"></i></div>' }) }).bindPopup('Τελευταίο στίγμα · ' + escHtml(nameById[tid] || '')).addTo(group);
      group.addTo(map);
      routeLayers[tid] = group;
    });

    var iconByKind = { move:'bi-arrow-right', incident:'bi-exclamation-triangle-fill', poi:'bi-pin-map-fill', photo:'bi-camera-fill', video:'bi-camera-video-fill' };
    var colorByKind = { move:'#2563eb', incident:'#be123c', poi:'#d97706', photo:'#0891b2', video:'#7c3aed' };
    POINTS.forEach(function(p){
      if (p.lat == null) return;
      bounds.push([p.lat, p.lng]);
      var color = colorByKind[p.kind] || '#334155';
      var icon = iconByKind[p.kind] || 'bi-geo-alt-fill';
      L.marker([p.lat, p.lng], { icon: L.divIcon({ className:'', iconSize:[30,30], iconAnchor:[15,15], html:'<div class="route-icon" style="background:' + color + '"><i class="bi ' + icon + '"></i></div>' }) })
        .bindPopup('<b>' + escHtml(p.label || '') + '</b><br>' + escHtml(p.team || '') + (p.body ? '<br>' + escHtml(p.body) : ''))
        .addTo(map);
    });
    if (bounds.length) { map.fitBounds(bounds, { padding:[34,34], maxZoom:16 }); }
    else { map.setView([35.34, 25.13], 12); }
    setTimeout(function(){ map.invalidateSize(); }, 200);

    document.querySelectorAll('.route-toggle').forEach(function(cb){
      cb.addEventListener('change', function(){
        var layer = routeLayers[cb.value];
        if (!layer) return;
        if (cb.checked) { layer.addTo(map); }
        else { map.removeLayer(layer); }
        rebuildReplay();
        if (movementOverviewOn) drawMovementOverview();
      });
    });

    var playBtn = document.getElementById('replayPlay');
    var resetBtn = document.getElementById('replayReset');
    var range = document.getElementById('replayRange');
    var speed = document.getElementById('replaySpeed');
    var current = document.getElementById('replayCurrent');
    var movementBtn = document.getElementById('movementOverview');
    var movementScope = document.getElementById('movementScope');
    var movementHint = document.getElementById('movementHint');
    var kindColor = {
      ping:'#16a34a', gps_request:'#7c3aed', gps_response:'#0f766e',
      photo_request:'#0891b2', video_request:'#7c3aed', photo:'#0891b2', video:'#7c3aed',
      incident:'#be123c', sos:'#be123c', shortage:'#d97706', order:'#334155', move:'#2563eb', poi:'#d97706'
    };
    var kindIcon = {
      ping:'bi-record-circle-fill', gps_request:'bi-broadcast-pin', gps_response:'bi-geo-alt-fill',
      photo_request:'bi-camera', video_request:'bi-camera-video', photo:'bi-camera-fill', video:'bi-camera-video-fill',
      incident:'bi-exclamation-triangle-fill', sos:'bi-exclamation-octagon-fill', shortage:'bi-tools',
      order:'bi-megaphone-fill', move:'bi-arrow-right', poi:'bi-pin-map-fill'
    };

    function selectedTeamIds(){
      var ids = {};
      document.querySelectorAll('.route-toggle').forEach(function(cb){ if (cb.checked) ids[cb.value] = true; });
      return ids;
    }
    function selectedKinds(){
      var kinds = {};
      document.querySelectorAll('.event-toggle').forEach(function(cb){ if (cb.checked) kinds[cb.value] = true; });
      return kinds;
    }
    function kindVisible(ev, kinds){
      if (ev.kind === 'sos') return !!kinds.incident;
      if (ev.kind === 'photo_request') return !!kinds.photo;
      if (ev.kind === 'video_request') return !!kinds.video;
      if (ev.kind === 'move' || ev.kind === 'poi') return !!kinds.order;
      return kinds[ev.kind] !== false;
    }
    function filteredReplay(){
      var ids = selectedTeamIds();
      var kinds = selectedKinds();
      return (REPLAY || []).filter(function(ev){
        if (ev.team_id != null && !ids[String(ev.team_id)]) return false;
        return kindVisible(ev, kinds);
      });
    }
    function replayPopup(ev){
      var html = '<b>' + escHtml(ev.title || '') + '</b>';
      if (ev.team) html += '<br><span>' + escHtml(ev.team) + '</span>';
      if (ev.kind === 'move' && ev.origin_lat != null && ev.origin_lng != null) {
        html += '<br><span>Βέλος από τελευταίο στίγμα προς νέο σημείο.</span>';
      }
      if (ev.detail) html += '<br><span>' + escHtml(ev.detail) + '</span>';
      if (ev.at) html += '<br><small>' + escHtml(ev.at.slice(0,16).replace('T',' ')) + '</small>';
      return html;
    }
    function replayIcon(ev){
      var color = kindColor[ev.kind] || colorById[ev.team_id] || '#334155';
      var icon = kindIcon[ev.kind] || 'bi-geo-alt-fill';
      return L.divIcon({ className:'', iconSize:[34,34], iconAnchor:[17,17],
        html:'<div class="replay-icon" style="background:' + color + '"><i class="bi ' + icon + '"></i></div>' });
    }
    function moveBearing(fromLat, fromLng, toLat, toLng){
      var y = Math.sin((toLng - fromLng) * Math.PI / 180) * Math.cos(toLat * Math.PI / 180);
      var x = Math.cos(fromLat * Math.PI / 180) * Math.sin(toLat * Math.PI / 180) -
        Math.sin(fromLat * Math.PI / 180) * Math.cos(toLat * Math.PI / 180) * Math.cos((toLng - fromLng) * Math.PI / 180);
      return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }
    function moveArrowIcon(ev){
      var angle = moveBearing(parseFloat(ev.origin_lat), parseFloat(ev.origin_lng), parseFloat(ev.lat), parseFloat(ev.lng));
      var color = colorById[ev.team_id] || kindColor.move || '#2563eb';
      return L.divIcon({ className:'', iconSize:[30,30], iconAnchor:[15,15],
        html:'<div class="move-arrow-head" style="background:' + color + ';transform:rotate(' + angle + 'deg)"><i class="bi bi-arrow-up"></i></div>' });
    }
    function hasMoveOrigin(ev){
      if (ev.kind !== 'move' || ev.origin_lat == null || ev.origin_lng == null || ev.lat == null || ev.lng == null) return false;
      return Math.abs(parseFloat(ev.origin_lat) - parseFloat(ev.lat)) > 0.00001 || Math.abs(parseFloat(ev.origin_lng) - parseFloat(ev.lng)) > 0.00001;
    }
    function movementPopup(ev){
      var html = '<b>Μετακίνηση ομάδας</b>';
      if (ev.team) html += '<br><span>' + escHtml(ev.team) + '</span>';
      if (ev.at) html += '<br><small>' + escHtml(ev.at.slice(0,16).replace('T',' ')) + '</small>';
      if (ev.detail) html += '<br><span>' + escHtml(ev.detail) + '</span>';
      return html;
    }
    function movementEvents(){
      var useSelected = !movementScope || movementScope.value !== 'all';
      var ids = selectedTeamIds();
      return (REPLAY || []).filter(function(ev){
        if (!hasMoveOrigin(ev)) return false;
        if (useSelected && ev.team_id != null && !ids[String(ev.team_id)]) return false;
        return true;
      });
    }
    function movementTimeIcon(ev){
      var color = colorById[ev.team_id] || kindColor.move || '#2563eb';
      var time = ev.at ? ev.at.slice(11,16) : '';
      return L.divIcon({ className:'', iconSize:[88,24], iconAnchor:[44,12],
        html:'<div class="move-time-badge" style="border-color:' + color + '">' + escHtml(time) + (ev.team ? ' · ' + escHtml(ev.team) : '') + '</div>' });
    }
    function setMovementHint(count){
      if (!movementHint) return;
      if (!movementOverviewOn) { movementHint.textContent = 'Δείχνει όλα τα βελάκια μετακίνησης με την ώρα τους.'; return; }
      movementHint.textContent = count ? ('Εμφανίζονται ' + count + ' μετακινήσεις στον χάρτη.') : 'Δεν υπάρχουν μετακινήσεις για το τρέχον φίλτρο.';
    }
    function drawMovementOverview(){
      movementLayer.clearLayers();
      var events = movementEvents();
      setMovementHint(events.length);
      if (!movementOverviewOn || !events.length) return;
      var overviewBounds = [];
      events.forEach(function(ev, idx){
        var color = colorById[ev.team_id] || kindColor.move || '#2563eb';
        var from = [parseFloat(ev.origin_lat), parseFloat(ev.origin_lng)];
        var to = [parseFloat(ev.lat), parseFloat(ev.lng)];
        var mid = [(from[0] + to[0]) / 2, (from[1] + to[1]) / 2];
        overviewBounds.push(from, to);
        L.polyline([from, to], { color: color, weight: 5, opacity:.9, dashArray:'12,8' })
          .bindPopup(movementPopup(ev))
          .addTo(movementLayer);
        L.circleMarker(from, { radius:6, color:color, fillColor:'#fff', fillOpacity:1, weight:3 })
          .bindPopup('Αφετηρία μετακίνησης · ' + escHtml(ev.team || ''))
          .addTo(movementLayer);
        L.marker(mid, { icon: moveArrowIcon(ev), zIndexOffset: 5200 + idx })
          .bindPopup(movementPopup(ev))
          .addTo(movementLayer);
        L.marker(to, { icon: movementTimeIcon(ev), zIndexOffset: 5400 + idx })
          .bindPopup(movementPopup(ev))
          .addTo(movementLayer);
      });
      if (overviewBounds.length) map.fitBounds(overviewBounds, { padding:[44,44], maxZoom:16 });
    }
    function toggleMovementOverview(force){
      movementOverviewOn = typeof force === 'boolean' ? force : !movementOverviewOn;
      if (movementBtn) movementBtn.classList.toggle('active', movementOverviewOn);
      if (!movementOverviewOn) {
        movementLayer.clearLayers();
        setMovementHint(0);
        return;
      }
      stopReplay();
      drawMovementOverview();
    }
    function setCurrent(ev, total){
      if (!current) return;
      if (!ev) {
        current.innerHTML = '<div class="rc-icon" style="background:#64748b"><i class="bi bi-clock-history"></i></div><div><div class="rc-time">Replay δράσης</div><div class="rc-title">Δεν υπάρχουν γεγονότα για τα τρέχοντα φίλτρα.</div><div class="rc-detail">Αλλάξτε φίλτρα ή ομάδα για να δείτε περισσότερα.</div></div>';
        return;
      }
      var color = kindColor[ev.kind] || colorById[ev.team_id] || '#334155';
      var icon = kindIcon[ev.kind] || 'bi-geo-alt-fill';
      var detail = ev.detail || '';
      if (ev.kind === 'move' && ev.origin_lat != null && ev.origin_lng != null) {
        detail = (detail ? detail + ' · ' : '') + 'Βέλος από το τελευταίο γνωστό στίγμα προς τον προορισμό.';
      }
      current.innerHTML = '<div class="rc-icon" style="background:' + color + '"><i class="bi ' + icon + '"></i></div>' +
        '<div><div class="rc-time">' + escHtml((ev.at || '').slice(11,16)) + ' · ' + (replayIndex + 1) + '/' + total + '</div>' +
        '<div class="rc-title">' + escHtml(ev.title || '') + (ev.team ? ' · ' + escHtml(ev.team) : '') + '</div>' +
        '<div class="rc-detail">' + escHtml(detail) + '</div></div>';
    }
    function drawReplay(events, upto){
      replayMarkerLayer.clearLayers();
      replayLineLayer.clearLayers();
      var partial = {};
      var lastMarker = null;
      for (var i = 0; i <= upto && i < events.length; i++) {
        var ev = events[i];
        if (ev.kind === 'ping' && ev.lat != null && ev.lng != null && ev.team_id != null) {
          (partial[ev.team_id] = partial[ev.team_id] || []).push([ev.lat, ev.lng]);
        }
        if (ev.lat == null || ev.lng == null) continue;
        var marker = L.marker([ev.lat, ev.lng], { icon: replayIcon(ev), zIndexOffset: 3000 + i })
          .bindPopup(replayPopup(ev))
          .addTo(replayMarkerLayer);
        lastMarker = marker;
        if (hasMoveOrigin(ev)) {
          var color = colorById[ev.team_id] || kindColor.move || '#2563eb';
          var from = [parseFloat(ev.origin_lat), parseFloat(ev.origin_lng)];
          var to = [parseFloat(ev.lat), parseFloat(ev.lng)];
          var mid = [(from[0] + to[0]) / 2, (from[1] + to[1]) / 2];
          L.polyline([from, to], { color: color, weight: 4, opacity:.9, dashArray:'10,7' }).addTo(replayLineLayer);
          L.circleMarker(from, { radius:6, color:color, fillColor:'#fff', fillOpacity:1, weight:3 })
            .bindPopup('Αφετηρία μετακίνησης · ' + escHtml(ev.team || ''))
            .addTo(replayMarkerLayer);
          L.marker(mid, { icon: moveArrowIcon(ev), zIndexOffset: 3600 + i })
            .bindPopup('Κατεύθυνση μετακίνησης · ' + escHtml(ev.team || ''))
            .addTo(replayMarkerLayer);
        }
        if (ev.kind === 'gps_request' && ev.response_lat != null && ev.response_lng != null) {
          L.polyline([[ev.lat, ev.lng], [ev.response_lat, ev.response_lng]], {
            color:'#7c3aed', weight:2, opacity:.75, dashArray:'5,7'
          }).addTo(replayLineLayer);
          L.circleMarker([ev.response_lat, ev.response_lng], {
            radius:7, color:'#0f766e', fillColor:'#0f766e', fillOpacity:.9, weight:2
          }).bindPopup('Απάντηση GPS · ' + escHtml(ev.team || '')).addTo(replayMarkerLayer);
        }
      }
      Object.keys(partial).forEach(function(tid){
        if (partial[tid].length > 1) {
          L.polyline(partial[tid], { color: colorById[tid] || '#2563eb', weight: 6, opacity: .95 }).addTo(replayLineLayer);
        }
      });
      if (lastMarker) {
        var ll = lastMarker.getLatLng();
        map.panTo(ll, { animate:true, duration:.35 });
        lastMarker.openPopup();
      }
    }
    function rebuildReplay(){
      var events = filteredReplay();
      if (!range) return;
      range.max = Math.max(0, events.length - 1);
      if (replayIndex > events.length - 1) replayIndex = Math.max(0, events.length - 1);
      range.value = replayIndex;
      drawReplay(events, replayIndex);
      setCurrent(events[replayIndex], events.length);
    }
    function stopReplay(){
      if (replayTimer) { clearInterval(replayTimer); replayTimer = null; }
      if (playBtn) { playBtn.innerHTML = '<i class="bi bi-play-fill"></i><span>Play</span>'; }
    }
    function startReplay(){
      var events = filteredReplay();
      if (!events.length) return;
      stopReplay();
      if (playBtn) { playBtn.innerHTML = '<i class="bi bi-pause-fill"></i><span>Pause</span>'; }
      var delay = {1:1100, 2:650, 4:320}[speed ? speed.value : '1'] || 1100;
      replayTimer = setInterval(function(){
        events = filteredReplay();
        if (!events.length || replayIndex >= events.length - 1) { stopReplay(); return; }
        replayIndex++;
        range.value = replayIndex;
        drawReplay(events, replayIndex);
        setCurrent(events[replayIndex], events.length);
      }, delay);
    }
    if (playBtn) {
      playBtn.addEventListener('click', function(){ replayTimer ? stopReplay() : startReplay(); });
    }
    if (resetBtn) {
      resetBtn.addEventListener('click', function(){ stopReplay(); replayIndex = 0; rebuildReplay(); });
    }
    if (range) {
      range.addEventListener('input', function(){ stopReplay(); replayIndex = parseInt(range.value, 10) || 0; rebuildReplay(); });
    }
    if (speed) {
      speed.addEventListener('change', function(){ if (replayTimer) startReplay(); });
    }
    if (movementBtn) {
      movementBtn.addEventListener('click', function(){ toggleMovementOverview(); });
    }
    if (movementScope) {
      movementScope.addEventListener('change', function(){ if (movementOverviewOn) drawMovementOverview(); });
    }
    document.querySelectorAll('.event-toggle').forEach(function(cb){
      cb.addEventListener('change', function(){ stopReplay(); replayIndex = 0; rebuildReplay(); });
    });
    rebuildReplay();
  })();

  (function(){
    var filters = document.querySelectorAll('[data-comm-filter]');
    var items = document.querySelectorAll('.comm-item');
    if (!filters.length || !items.length) return;
    function visible(item, filter){
      if (filter === 'all') return true;
      if (filter === 'command' || filter === 'team') return item.dataset.actor === filter;
      if (filter === 'order') return item.dataset.type === 'order' || item.dataset.type === 'point' || item.dataset.type === 'incident';
      return item.dataset.type === filter;
    }
    filters.forEach(function(btn){
      btn.addEventListener('click', function(){
        var filter = btn.dataset.commFilter || 'all';
        filters.forEach(function(b){ b.classList.toggle('active', b === btn); });
        items.forEach(function(item){ item.style.display = visible(item, filter) ? '' : 'none'; });
      });
    });
  })();

  (function(){
    if (!window.Chart || !METRICS.length) return;
    var labels = METRICS.map(function(m){ return m.team; });
    function ser(key){ return METRICS.map(function(m){ return m[key].avg_min; }); }
    new Chart(document.getElementById('respChart'), {
      type: 'bar',
      data: { labels: labels, datasets: [
        { label: 'Στίγμα', data: ser('gps'),   backgroundColor:'#2563eb' },
        { label: 'Φωτό',   data: ser('photo'), backgroundColor:'#16a34a' },
        { label: 'Βίντεο', data: ser('video'), backgroundColor:'#d97706' },
        { label: 'ACK',    data: ser('ack'),   backgroundColor:'#be123c' }
      ]},
      options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, title:{ display:true, text:'λεπτά' } } }, plugins:{ legend:{ position:'bottom' } } }
    });
  })();

  (function(){
    var box = document.getElementById('lightbox');
    var media = document.getElementById('lightboxMedia');
    var cap = document.getElementById('lightboxCap');
    var close = document.getElementById('lightboxClose');
    function openItem(i){
      var item = GALLERY[i]; if (!item) return;
      media.innerHTML = item.type === 'video'
        ? '<video controls autoplay src="' + escHtml(item.src) + '"></video>'
        : '<img src="' + escHtml(item.src) + '" alt="">';
      cap.textContent = item.caption || '';
      box.classList.add('open');
    }
    function closeBox(){ box.classList.remove('open'); media.innerHTML=''; cap.textContent=''; }
    document.querySelectorAll('[data-gallery]').forEach(function(btn){
      btn.addEventListener('click', function(){ openItem(parseInt(btn.getAttribute('data-gallery'), 10)); });
    });
    close.addEventListener('click', closeBox);
    box.addEventListener('click', function(e){ if (e.target === box) closeBox(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeBox(); });
  })();
</script>
</body>
</html>
