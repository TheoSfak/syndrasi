<?php
/**
 * Mission Commander field hub — /f/{token}  (NO LOGIN)
 * Standalone full-screen mobile page for the assigned commander.
 */
$evStatus  = $app['event_status'];
$evStarted = !empty($app['start_datetime']) && strtotime($app['start_datetime']) <= time();
$isActive  = $evStatus === 'active' || ($evStarted && in_array($evStatus, ['open', 'confirmed', 'review']));
$cname    = $commander['full_name'] ?? 'Υπεύθυνος';
$evLat = (isset($app['latitude'])  && $app['latitude']  !== null && $app['latitude']  !== '') ? (float) $app['latitude']  : null;
$evLng = (isset($app['longitude']) && $app['longitude'] !== null && $app['longitude'] !== '') ? (float) $app['longitude'] : null;
$tLat  = (!empty($lastPing) && $lastPing['latitude']  !== null) ? (float) $lastPing['latitude']  : null;
$tLng  = (!empty($lastPing) && $lastPing['longitude'] !== null) ? (float) $lastPing['longitude'] : null;
$terms = authority_context((int) ($app['municipality_id'] ?? 0));
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$orgLabel = $orgLabel ?? ($terms['short_name'] ?? 'Φορέας');
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d1a1a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
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
  .resource-pin{background:linear-gradient(135deg,#134e4a,#0f766e);border:1px solid #2dd4bf;border-radius:16px;padding:16px;margin-bottom:12px}
  .resource-pin-h{font-weight:900;color:#99f6e4;font-size:14px;display:flex;gap:6px;align-items:center}
  .resource-pin-b{color:#fff;font-size:17px;font-weight:700;margin:8px 0 12px}
  .resource-pin-in{width:100%;margin-bottom:8px;padding:11px 12px;border-radius:10px;border:1px solid #14b8a6;background:#042f2e;color:#ccfbf1;font-size:14px}
  .resource-pin-in::placeholder{color:#5eead4;opacity:.7}
  .resource-accept{background:#2dd4bf;color:#042f2e;border:none;border-radius:12px;font-weight:800;font-size:15px;padding:14px;width:100%;cursor:pointer}
  .resource-decline{background:transparent;color:#99f6e4;border:1px solid #5eead4;border-radius:12px;font-weight:700;font-size:14px;padding:11px;width:100%;cursor:pointer;margin-top:8px}
  #locRes{font-size:13px;color:#4ade80;padding:0 4px;display:none}
  #locRes.err{color:#f87171}
</style>
</head>
<body>

<div class="topbar">
  <div class="ev"><i class="bi bi-broadcast me-1"></i><?= e($app['event_title']) ?></div>
  <div class="meta"><?= e(gr_time($app['start_datetime'])) ?>–<?= e(gr_time($app['end_datetime'])) ?><?php if ($app['location_name']): ?> · <?= e($app['location_name']) ?><?php endif; ?></div>
  <div class="who"><i class="bi bi-person-badge me-1"></i><?= e($app['team_name']) ?> <?= e(t('field/hub.001', '· Υπεύθυνος:')) ?> <?= e($cname) ?></div>
</div>

<div class="body">

  <?php foreach (flash_get() as $flash): $ftype = $flash['type'] ?? 'success';
    $ficon = match($ftype) { 'success' => 'bi-check-circle-fill', 'danger' => 'bi-x-circle-fill', default => 'bi-exclamation-triangle-fill' }; ?>
  <div class="flash flash-<?= e($ftype) ?>">
    <i class="bi <?= e($ficon) ?>"></i><?= e($flash['message']) ?>
  </div>
  <?php endforeach; ?>

  <?php if (!$isActive): ?>
  <div class="inactive"><i class="bi bi-hourglass-split"></i> Η <?= e($eventSingularLc) ?> <?= e(t('field/hub.051', 'δεν είναι ενεργή αυτή τη στιγμή.')) ?></div>
  <?php endif; ?>

  <!-- Pinned orders -->
  <div id="orderBanner" style="display:none"></div>

  <!-- Resource dispatch requests (Smart Resource Dispatch, Φάση 2) -->
  <div id="resourceBox" style="display:none"></div>

  <!-- Offline indicator (shown after 2 consecutive poll failures) -->
  <div id="offlineBanner" role="alert" aria-live="assertive" style="display:none;background:#7c2d12;color:#fef2f2;border:1px solid #ef4444;border-radius:12px;padding:12px 16px;font-size:13px;font-weight:600;gap:10px;align-items:center">
    <i class="bi bi-wifi-off"></i> <?= e(t('field/hub.003', 'Δεν υπάρχει σύνδεση — εμφανίζονται τελευταία δεδομένα.')) ?>
  </div>

  <!-- SOS -->
  <div>
    <button class="sos-btn" id="sosBtn" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-exclamation-octagon-fill sos-ico"></i>
      <div style="flex:1"><div class="sos-lbl"><?= e(t('field/hub.004', 'SOS — ΚΙΝΔΥΝΟΣ')) ?></div><div class="sos-sub"><?= e(t('field/hub.005', 'Άμεση κλήση βοήθειας προς')) ?> <?= e($orgLabel) ?></div></div>
    </button>
    <div class="sos-banner" id="sosBanner" role="alert" aria-live="assertive" style="display:none"></div>
  </div>

  <!-- Στίγμα -->
  <div class="card">
    <div id="gpsReqBanner" style="margin:14px 14px 0;padding:10px 12px;border-radius:10px;background:#13243a;border:1px solid #1e3a5f;color:#cfe3ff;font-size:13px;font-weight:600;display:<?= !empty($gpsRequest) ? 'block' : 'none' ?>">
      <i class="bi bi-geo-alt-fill me-1"></i> <?= e($orgLabel) ?> <?= e(t('field/hub.006', 'ζήτησε το στίγμα σας — πατήστε «Αποστολή Στίγματος».')) ?>
    </div>
    <button class="big-btn" id="locBtn" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-geo-alt-fill big-ico"></i>
      <div style="flex:1"><div class="big-lbl"><?= e(t('field/hub.007', 'Αποστολή Στίγματος')) ?></div><div class="big-sub"><?= e(t('field/hub.008', 'Στείλτε τη θέση σας προς')) ?> <?= e($orgLabel) ?></div></div>
      <i class="bi bi-chevron-right" style="opacity:.5"></i>
    </button>
    <div id="locRes" style="padding:10px 18px 14px"></div>
  </div>

  <!-- Map -->
  <div class="card">
    <div class="hdr"><i class="bi bi-map"></i> <?= e(t('field/hub.009', 'Χάρτης')) ?> <?= e($eventSingular) ?></div>
    <div id="teamMap" style="height:240px;background:#0a1414"></div>
  </div>

  <!-- Status pings -->
  <div class="card">
    <div class="hdr"><i class="bi bi-lightning-charge"></i> <?= e(t('field/hub.010', 'Γρήγορη ενημέρωση')) ?></div>
    <div class="pings">
      <button class="ping" data-code="arrived" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-geo-alt-fill"></i> <?= e(t('field/hub.011', 'Φτάσαμε στο σημείο')) ?></button>
      <button class="ping" data-code="task_complete" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i> <?= e(t('field/hub.012', 'Ολοκληρώθηκε')) ?></button>
      <button class="ping" data-code="need_backup" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-people-fill"></i> <?= e(t('field/hub.013', 'Χρειαζόμαστε ενίσχυση')) ?></button>
      <button class="ping" data-code="returning" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-box-arrow-left"></i> <?= e(t('field/hub.014', 'Επιστροφή στη βάση')) ?></button>
      <button class="ping full" data-code="incident" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('field/hub.015', 'Έχουμε περιστατικό')) ?></button>
    </div>
  </div>

  <!-- Photo -->
  <div class="card">
    <div class="hdr"><i class="bi bi-camera"></i> <?= e(t('field/hub.016', 'Αποστολή φωτογραφίας')) ?></div>
    <div id="photoReqBanner" style="margin:0 14px 8px;padding:10px 12px;border-radius:10px;background:#13243a;border:1px solid #1e3a5f;color:#cfe3ff;font-size:13px;font-weight:600;display:<?= !empty($photoRequest) ? 'block' : 'none' ?>">
      <i class="bi bi-camera-fill me-1"></i> <?= e($orgLabel) ?> <?= e(t('field/hub.017', 'ζήτησε φωτογραφία — τραβήξτε/ανεβάστε μία παρακάτω.')) ?>
    </div>
    <form method="post" action="<?= e(url('/f/' . $token . '/photo')) ?>" enctype="multipart/form-data" id="photoForm">
      <?= csrf_field() ?>
      <input type="hidden" name="latitude" id="phLat"><input type="hidden" name="longitude" id="phLng">
      <div class="field"><label><?= e(t('field/hub.018', 'Φωτογραφία')) ?></label><input type="file" name="photo" accept="image/*" capture="environment" required></div>
      <div class="field"><label><?= e(t('field/hub.019', 'Σχόλιο (προαιρετικό)')) ?></label><input type="text" name="caption" maxlength="255" placeholder="<?= e(t('field/hub.046', 'π.χ. σημείο, κατάσταση')) ?>"></div>
      <div class="field"><button class="submit" id="phBtn"><i class="bi bi-upload me-1"></i><?= e(t('field/hub.020', 'Αποστολή')) ?></button></div>
    </form>
  </div>


  <!-- Video -->
  <div class="card">
    <div class="hdr"><i class="bi bi-camera-video"></i> <?= e(t('field/hub.021', 'Αποστολή βίντεο')) ?></div>
    <div id="videoReqBanner" style="margin:0 14px 8px;padding:10px 12px;border-radius:10px;background:#13243a;border:1px solid #1e3a5f;color:#cfe3ff;font-size:13px;font-weight:600;display:<?= !empty($videoRequest) ? 'block' : 'none' ?>">
      <i class="bi bi-camera-video-fill me-1"></i> <span id="videoReqText"><?= (!empty($videoRequest) && !empty($videoRequest['instructions'])) ? e($videoRequest['instructions']) : e($orgLabel) . ' ζήτησε σύντομο βίντεο — τραβήξτε/ανεβάστε ένα παρακάτω.' ?></span>
    </div>

    <div id="vidIdle" style="padding:0 14px 14px">
      <button type="button" class="submit" id="vidRecordBtn"><i class="bi bi-record-circle me-1"></i><?= e(t('field/hub.022', 'Τράβα τώρα (')) ?><span id="vidMax"><?= !empty($videoRequest['max_seconds']) ? (int) $videoRequest['max_seconds'] : 40 ?></span>'')</button>
      <label class="submit" for="vidGallery" style="background:#1f3a3a;margin-top:8px;display:flex;align-items:center;justify-content:center;cursor:pointer"><i class="bi bi-images me-1"></i><?= e(t('field/hub.023', 'Από gallery')) ?></label>
      <input id="vidGallery" type="file" accept="video/*" capture="environment" style="display:none">
      <div id="vidErr" style="display:none;margin-top:8px;padding:8px 10px;border-radius:8px;background:#3a1620;border:1px solid #6a2330;color:#ffd9df;font-size:12px"></div>
    </div>

    <div id="vidStage" style="display:none;padding:0 14px 14px">
      <div style="position:relative;background:#000;border-radius:12px;overflow:hidden;aspect-ratio:9/16">
        <video id="vidPreview" playsinline muted autoplay style="width:100%;height:100%;object-fit:cover;display:block"></video>
        <div id="vidBar" style="position:absolute;top:0;left:0;height:4px;background:#e23b4e;width:0%"></div>
        <div id="vidTimer" style="position:absolute;top:10px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.6);border-radius:999px;padding:5px 12px;font-weight:700;font-size:14px">00:40</div>
        <div id="vidOverlay" style="position:absolute;left:8px;right:8px;bottom:8px;background:rgba(0,0,0,.55);border-radius:8px;padding:7px 10px;font-size:12px;line-height:1.35"></div>
      </div>
      <div style="display:flex;justify-content:center;margin-top:10px">
        <button type="button" id="vidShutter" aria-label="Record" style="width:64px;height:64px;border-radius:50%;border:4px solid #fff;background:#e23b4e;cursor:pointer"></button>
      </div>
      <button type="button" class="submit" id="vidCancel" style="background:#333;margin-top:10px"><?= e(t('field/hub.024', 'Άκυρο')) ?></button>
    </div>

    <div id="vidReview" style="display:none;padding:0 14px 14px">
      <video id="vidReviewPlayer" controls playsinline style="width:100%;border-radius:12px;background:#000;display:block"></video>
      <div class="field"><label><?= e(t('field/hub.025', 'Λεζάντα (προαιρετικά)')) ?></label><input type="text" id="vidCaption" maxlength="200" placeholder="<?= e(t('field/hub.046', 'π.χ. σημείο, κατάσταση')) ?>"></div>
      <div id="vidProg" style="display:none;height:8px;background:#0d1a1a;border-radius:999px;overflow:hidden;margin:8px 0"><div id="vidProgBar" style="height:100%;width:0;background:#0e7490;transition:width .2s"></div></div>
      <div style="display:flex;gap:8px">
        <button type="button" class="submit" id="vidRetake" style="background:#333;flex:1"><?= e(t('field/hub.026', '↺ Ξανά')) ?></button>
        <button type="button" class="submit" id="vidSend" style="flex:2"><i class="bi bi-upload me-1"></i><?= e(t('field/hub.020', 'Αποστολή')) ?></button>
      </div>
    </div>
  </div>
  <!-- Comms (read + ack + compose) -->
  <div class="card">
    <div class="hdr"><i class="bi bi-chat-dots"></i> <?= e(t('field/hub.027', 'Επικοινωνία ·')) ?> <?= e($orgLabel) ?></div>
    <div class="msg-list" id="msgList"><div style="color:#4b7070;font-size:12px;text-align:center;padding:14px"><?= e(t('field/hub.028', 'Φόρτωση…')) ?></div></div>
    <div style="display:flex;gap:8px;padding:0 14px 14px">
      <input type="text" id="msgInput" maxlength="500" placeholder="Μήνυμα προς <?= e($orgLabel ?? 'τον Δήμο') ?>…"
             style="flex:1;background:#0d1a1a;border:1px solid #1e3333;border-radius:10px;color:#e8f5f4;font-size:14px;padding:12px;outline:none">
      <button type="button" id="msgSend" style="background:#0e7490;color:#fff;border:none;border-radius:10px;padding:0 16px;font-size:18px;cursor:pointer"><i class="bi bi-send"></i></button>
    </div>
  </div>

  <!-- Αναφορά Έλλειψης -->
  <div class="card">
    <button type="button" id="shortageToggle" onclick="toggleShortage()"
            style="width:100%;padding:22px 20px;background:#1a1111;border:none;border-radius:18px;color:#f87171;cursor:pointer;display:flex;align-items:center;gap:16px;text-align:left" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-exclamation-triangle" style="font-size:34px;flex-shrink:0"></i>
      <div style="flex:1"><div style="font-size:18px;font-weight:800;color:#e8f5f4"><?= e(t('field/hub.029', 'Αναφορά Έλλειψης')) ?></div>
        <div style="font-size:12px;color:#f87171;margin-top:2px"><?= e(t('field/hub.030', 'Ειδοποιήστε άμεσα')) ?> <?= e($orgLabel) ?></div></div>
      <i class="bi bi-chevron-right" id="shortageArrow" style="opacity:.5;transition:transform .2s"></i>
    </button>
    <div id="shortageForm" style="display:none;border-top:1px solid #2a1818">
      <form method="post" action="<?= e(url('/f/' . $token . '/shortage')) ?>" style="padding:16px;display:flex;flex-direction:column;gap:10px">
        <?= csrf_field() ?>
        <input type="hidden" name="severity" id="shSeverityInput" value="medium">
        <div style="display:flex;flex-direction:column;gap:4px">
          <label style="font-size:12px;color:#9ca3af;font-weight:600"><?= e(t('field/hub.031', 'Τύπος έλλειψης')) ?></label>
          <select name="shortage_type" required style="background:#0d1a1a;border:1px solid #1e3333;border-radius:10px;color:#e8f5f4;font-size:15px;padding:12px 14px;outline:none;width:100%">
            <option value="people"><?= e(t('field/hub.032', '👥 Άτομα')) ?></option>
            <option value="equipment"><?= e(t('field/hub.033', '🔧 Εξοπλισμός')) ?></option>
            <option value="medical_supplies"><?= e(t('field/hub.034', '🏥 Υγειονομικό υλικό')) ?></option>
            <option value="vehicle"><?= e(t('field/hub.035', '🚗 Όχημα')) ?></option>
            <option value="other"><?= e(t('field/hub.036', '📋 Άλλο')) ?></option>
          </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
          <label style="font-size:12px;color:#9ca3af;font-weight:600"><?= e(t('field/hub.037', 'Σοβαρότητα')) ?></label>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px">
            <button type="button" class="sh-sev" data-val="low"      onclick="setShSev('low')"      style="padding:10px 4px;border-radius:8px;border:2px solid transparent;font-size:11px;font-weight:700;cursor:pointer;background:#1a2e1a;color:#86efac"><?= e(t('field/hub.038', 'Χαμηλή')) ?></button>
            <button type="button" class="sh-sev" data-val="medium"   onclick="setShSev('medium')"   style="padding:10px 4px;border-radius:8px;border:2px solid #fde047;font-size:11px;font-weight:700;cursor:pointer;background:#2a2a0d;color:#fde047"><?= e(t('field/hub.039', 'Μεσαία')) ?></button>
            <button type="button" class="sh-sev" data-val="high"     onclick="setShSev('high')"     style="padding:10px 4px;border-radius:8px;border:2px solid transparent;font-size:11px;font-weight:700;cursor:pointer;background:#2a1a0d;color:#fb923c"><?= e(t('field/hub.040', 'Υψηλή')) ?></button>
            <button type="button" class="sh-sev" data-val="critical" onclick="setShSev('critical')" style="padding:10px 4px;border-radius:8px;border:2px solid transparent;font-size:11px;font-weight:700;cursor:pointer;background:#2a0d0d;color:#f87171"><?= e(t('field/hub.041', 'Κρίσιμη')) ?></button>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
          <label style="font-size:12px;color:#9ca3af;font-weight:600"><?= e(t('field/hub.042', 'Σύντομος τίτλος *')) ?></label>
          <input type="text" name="title" required placeholder="<?= e(t('field/hub.048', 'π.χ. Λείπουν 2 άτομα')) ?>"
                 style="background:#0d1a1a;border:1px solid #1e3333;border-radius:10px;color:#e8f5f4;font-size:15px;padding:12px 14px;outline:none;width:100%">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px">
          <label style="font-size:12px;color:#9ca3af;font-weight:600"><?= e(t('field/hub.043', 'Περιγραφή (προαιρετικό)')) ?></label>
          <textarea name="description" rows="2" placeholder="<?= e(t('field/hub.049', 'Επιπλέον λεπτομέρειες…')) ?>"
                    style="background:#0d1a1a;border:1px solid #1e3333;border-radius:10px;color:#e8f5f4;font-size:15px;padding:12px 14px;outline:none;width:100%;resize:none"></textarea>
        </div>
        <button type="submit" onclick="return confirm('Αποστολή αναφοράς έλλειψης προς <?= e(addslashes($orgLabel)) ?>;')"
                style="background:#b91c1c;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:800;padding:16px;cursor:pointer;width:100%;display:flex;align-items:center;justify-content:center;gap:8px">
          <i class="bi bi-send-fill"></i> <?= e(t('field/hub.044', 'Αποστολή Αναφοράς')) ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Δωμάτιο Επιχείρησης (κοινό κανάλι) -->
  <div class="card">
    <div class="hdr"><i class="bi bi-broadcast-pin"></i> <?= e(t('field/hub.045', 'Δωμάτιο Επιχείρησης')) ?></div>
    <div class="msg-list" id="roomList"><div style="color:#4b7070;font-size:12px;text-align:center;padding:14px"><?= e(t('field/hub.028', 'Φόρτωση…')) ?></div></div>
    <div style="display:flex;gap:8px;padding:0 14px 14px">
      <input type="text" id="roomInput" maxlength="500" placeholder="<?= e(t('field/hub.050', 'Μήνυμα προς όλους…')) ?>" style="flex:1;background:#0d1a1a;border:1px solid #1e3333;border-radius:10px;color:#e8f5f4;font-size:14px;padding:12px;outline:none">
      <button type="button" id="roomSend" style="background:#0e7490;color:#fff;border:none;border-radius:10px;padding:0 16px;font-size:18px;cursor:pointer"><i class="bi bi-send"></i></button>
    </div>
  </div>

</div>

<script>
(function () {
  'use strict';
  var BASE = window.baseUrl || '', CSRF = window.csrfToken || '';
  var TOKEN = '<?= e($token) ?>', IS_ACTIVE = <?= $isActive ? 'true' : 'false' ?>;
  var ORG_LABEL = <?= json_encode($orgLabel, JSON_UNESCAPED_UNICODE) ?>;
  var EVENT_LC = <?= json_encode($eventSingularLc, JSON_UNESCAPED_UNICODE) ?>;
  var ORG_ICON  = <?= json_encode($orgIcon  ?? '🏛️') ?>;
  function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML;}
  function postJSON(p,b){return fetch(BASE+'/f/'+TOKEN+p,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(b||{})}).then(function(r){return r.json();});}

  /* Map */
  (function(){
    var el=document.getElementById('teamMap'); if(!el||typeof L==='undefined')return;
    var evLat=<?= json_encode($evLat) ?>,evLng=<?= json_encode($evLng) ?>;
    var tLat=<?= json_encode($tLat) ?>,tLng=<?= json_encode($tLng) ?>;
    var center=(tLat!==null)?[tLat,tLng]:((evLat!==null)?[evLat,evLng]:[35.3387,25.1442]);
    var map=L.map('teamMap').setView(center,14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
    var b=[];
    if(evLat!==null){L.marker([evLat,evLng]).addTo(map).bindPopup('Σημείο ' + EVENT_LC);b.push([evLat,evLng]);}
    if(tLat!==null){L.circleMarker([tLat,tLng],{radius:9,color:'#22d3ee',fillColor:'#22d3ee',fillOpacity:.85}).addTo(map).bindPopup('Η θέση μας');b.push([tLat,tLng]);}
    if(b.length>1){try{map.fitBounds(b,{padding:[30,30],maxZoom:16});}catch(e){}}
    window.__teamMap=map; window.__teamGeo=L.layerGroup().addTo(map);
    setTimeout(function(){map.invalidateSize();},250);
  })();

  /* SOS */
  var sosBtn=document.getElementById('sosBtn'),sosBanner=document.getElementById('sosBanner');
  var sosArmed=false,sosArmTimer=null;
  function sosStatus(msg,ack){
    if(!sosBanner)return;
    sosBanner.className=ack?'sos-banner ack':'sos-banner';
    sosBanner.style.display='flex';
    sosBanner.innerHTML='<i class="bi '+(ack?'bi-info-circle-fill':'bi-exclamation-octagon-fill')+'"></i> '+esc(msg);
  }
  function armSos(){
    sosArmed=true;
    sosBtn.querySelector('.sos-sub').textContent='Πατήστε ξανά για αποστολή SOS';
    sosStatus('Επιβεβαίωση SOS: πατήστε ξανά το κόκκινο κουμπί μέσα σε 6 δευτερόλεπτα.',false);
    clearTimeout(sosArmTimer);
    sosArmTimer=setTimeout(function(){
      sosArmed=false;
      if(!sosBtn.disabled){sosBtn.querySelector('.sos-sub').textContent='Άμεση κλήση βοήθειας προς ' + ORG_LABEL;}
      if(sosBanner&&sosBanner.textContent.indexOf('Επιβεβαίωση SOS')!==-1){sosBanner.style.display='none';}
    },6000);
  }
  sosBtn.addEventListener('click',function(){
    if(!sosArmed){armSos();return;}
    clearTimeout(sosArmTimer);sosArmed=false;
    sosBtn.disabled=true;sosBtn.querySelector('.sos-sub').textContent='Λήψη τοποθεσίας…';
    var send=function(la,ln,ac){postJSON('/sos',{latitude:la,longitude:ln,accuracy:ac}).then(function(d){
      if(d&&d.success){sosBtn.querySelector('.sos-sub').textContent='SOS εστάλη — ειδοποιήθηκε ' + ORG_LABEL;sosStatus('SOS εστάλη — ειδοποιήθηκε ' + ORG_LABEL + '.',true);pollComms();}
      else{sosBtn.disabled=false;sosBtn.querySelector('.sos-sub').textContent='Αποτυχία — δοκιμάστε ξανά';sosStatus((d&&d.message)||'Το SOS δεν στάλθηκε. Δοκιμάστε ξανά.',false);}
    }).catch(function(){sosBtn.disabled=false;sosBtn.querySelector('.sos-sub').textContent='Σφάλμα σύνδεσης';sosStatus('Σφάλμα σύνδεσης. Ελέγξτε το δίκτυο και δοκιμάστε ξανά.',false);});};
    if(navigator.geolocation){navigator.geolocation.getCurrentPosition(function(p){send(p.coords.latitude,p.coords.longitude,p.coords.accuracy);},function(err){
      var m=err&&err.code===1?'Δεν δόθηκε άδεια GPS. Το SOS θα σταλεί χωρίς στίγμα.':'Δεν βρέθηκε GPS έγκαιρα. Το SOS θα σταλεί χωρίς στίγμα.';
      sosStatus(m,false);send(null,null,null);
    },{enableHighAccuracy:true,timeout:8000,maximumAge:0});}else{sosStatus('Η συσκευή δεν υποστηρίζει GPS. Το SOS θα σταλεί χωρίς στίγμα.',false);send(null,null,null);}
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

  /* Private message to command */
  (function(){
    var inp=document.getElementById('msgInput'),btn=document.getElementById('msgSend');
    if(!inp||!btn)return;
    function send(){var b=(inp.value||'').trim();if(!b)return;inp.value='';
      postJSON('/message',{body:b}).then(function(d){if(d&&d.success)pollComms();else alert((d&&d.message)||'Αποτυχία αποστολής.');})
      .catch(function(){alert('Σφάλμα σύνδεσης.');});}
    btn.addEventListener('click',send);
    inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();send();}});
  })();

  /* Shortage form toggle + severity */
  window.toggleShortage=function(){
    var f=document.getElementById('shortageForm'),a=document.getElementById('shortageArrow');
    var open=f.style.display==='block';
    f.style.display=open?'none':'block';
    a.style.transform=open?'':'rotate(90deg)';
  };
  window.setShSev=function(val){
    document.getElementById('shSeverityInput').value=val;
    document.querySelectorAll('.sh-sev').forEach(function(b){
      b.style.borderColor=b.dataset.val===val?'currentColor':'transparent';
    });
  };

  /* Ack order */
  window.ackOrder=function(id){postJSON('/ack-order',{message_id:id}).then(pollComms);};

  /* ── Audio + vibration alerts ────────────────────────────────────────── */
  function beepAlert(n, freq, dur) {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      for (var i = 0; i < n; i++) {
        (function(beat) {
          var o = ctx.createOscillator(), g = ctx.createGain();
          o.connect(g); g.connect(ctx.destination);
          o.type = 'sine'; o.frequency.value = freq;
          var t0 = ctx.currentTime + beat * (dur + 0.07);
          g.gain.setValueAtTime(0.5, t0);
          g.gain.exponentialRampToValueAtTime(0.001, t0 + dur);
          o.start(t0); o.stop(t0 + dur);
        })(i);
      }
    } catch(e) {}
  }
  function vibrateDevice(pattern) {
    try { if (navigator.vibrate) { navigator.vibrate(pattern); } } catch(e) {}
  }
  /* 2 short beeps for new command order — hard to miss on a phone */
  function alertNewOrder() { beepAlert(2, 660, 0.2); vibrateDevice([200, 100, 200]); }
  /* 1 quick beep for GPS/photo request */
  function alertNewRequest() { beepAlert(1, 880, 0.15); vibrateDevice([120]); }

  /* Flash a banner at the top of the body to draw attention */
  var newMsgFlash = null;
  function showNewMsgFlash(text) {
    if (!newMsgFlash) {
      newMsgFlash = document.createElement('div');
      newMsgFlash.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#f59e0b;color:#1c1400;font-weight:900;font-size:15px;padding:14px 20px;text-align:center;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 20px rgba(0,0,0,.5)';
      document.body.prepend(newMsgFlash);
    }
    newMsgFlash.innerHTML = '<i class="bi bi-megaphone-fill"></i> ' + text;
    newMsgFlash.style.display = 'flex';
    clearTimeout(newMsgFlash._timer);
    newMsgFlash._timer = setTimeout(function(){ newMsgFlash.style.display='none'; }, 6000);
  }

  /* Track which message IDs and request states we've already seen */
  var knownMsgIds = null;   /* null = first poll load (seed without alerting) */
  var prevPhotoReq = false;
  var prevGpsReq   = false;

  /* Comms polling */
  var msgList=document.getElementById('msgList'),orderBanner=document.getElementById('orderBanner');
  function renderOrders(msgs){
    var p=(msgs||[]).filter(function(m){return m.kind==='order'&&!m.acknowledged_at;});
    if(!p.length){orderBanner.innerHTML='';orderBanner.style.display='none';return;}
    orderBanner.style.display='';
    orderBanner.innerHTML=p.map(function(m){var t=(m.created_at||'').substr(11,5);
      var head=m.point_kind==='incident'?'⚠️ ΠΕΡΙΣΤΑΤΙΚΟ':(m.point_kind==='move'?'➡️ ΜΕΤΑΒΑΣΗ ΣΕ ΣΗΜΕΙΟ':'ΕΝΤΟΛΗ ΑΠΟ ' + ORG_LABEL);
      var dir=(m.latitude!=null&&m.longitude!=null)?'<a href="https://www.google.com/maps?q='+parseFloat(m.latitude)+','+parseFloat(m.longitude)+'" target="_blank" rel="noopener" class="order-pin-btn" style="display:block;text-align:center;text-decoration:none;background:#2563eb;color:#fff;margin-bottom:8px"><i class="bi bi-geo-alt-fill"></i> Οδηγίες (Google Maps)</a>':'';
      return '<div class="order-pin"><div class="order-pin-h"><i class="bi bi-megaphone-fill"></i> '+head+'</div><div class="order-pin-b">'+esc(m.body||'')+'</div>'+dir+'<button class="order-pin-btn" onclick="ackOrder('+parseInt(m.id,10)+')"><i class="bi bi-check2-all"></i> Επιβεβαίωση λήψης</button><div style="font-size:11px;color:#fde68a;opacity:.8;margin-top:6px;text-align:right">'+t+'</div></div>';
    }).join('');
  }
  /* Resource dispatch requests (Φάση 2) */
  var resourceBox=document.getElementById('resourceBox'),lastRrIds=null;
  function renderResourceRequests(list){
    if(!resourceBox)return;
    list=list||[];
    var ids=list.map(function(r){return r.id;}).join(',');
    if(ids===lastRrIds)return; /* don't clobber inputs while typing */
    var hasNew=lastRrIds!==null&&list.some(function(r){return (','+lastRrIds+',').indexOf(','+r.id+',')===-1;});
    if(hasNew){alertNewRequest();showNewMsgFlash('Αίτημα πόρου από ' + ORG_LABEL + '!');}
    lastRrIds=ids;
    if(!list.length){resourceBox.innerHTML='';resourceBox.style.display='none';return;}
    resourceBox.style.display='';
    resourceBox.innerHTML=list.map(function(r){
      var t=(r.created_at||'').substr(11,5);
      return '<div class="resource-pin"><div class="resource-pin-h"><i class="bi bi-box-seam"></i> ΑΙΤΗΜΑ ΠΟΡΟΥ ΑΠΟ '+esc(ORG_LABEL)+'</div>'+
        '<div class="resource-pin-b">'+esc(r.item_label||'')+'</div>'+
        '<input type="number" class="resource-pin-in" id="rrEta'+r.id+'" min="1" max="1440" inputmode="numeric" placeholder="ETA σε λεπτά (προαιρετικό)">'+
        '<input type="text" class="resource-pin-in" id="rrNote'+r.id+'" maxlength="255" placeholder="Σχόλιο (προαιρετικό)">'+
        '<button class="resource-accept" onclick="respondResource('+parseInt(r.id,10)+',\'accept\')"><i class="bi bi-check2-circle"></i> Αποδοχή — το διαθέτουμε</button>'+
        '<button class="resource-decline" onclick="respondResource('+parseInt(r.id,10)+',\'decline\')">Αδυναμία διάθεσης</button>'+
        '<div style="font-size:11px;color:#99f6e4;opacity:.8;margin-top:6px;text-align:right">'+t+'</div></div>';
    }).join('');
  }
  window.respondResource=function(id,action){
    var eta=document.getElementById('rrEta'+id),note=document.getElementById('rrNote'+id);
    var body={action:action};
    if(action==='accept'&&eta&&eta.value){body.eta_minutes=parseInt(eta.value,10);}
    if(note&&note.value.trim()){body.note=note.value.trim();}
    postJSON('/resource-requests/'+id+'/respond',body).then(function(d){
      if(d&&!d.success&&d.message){alert(d.message);}
      lastRrIds=null;pollComms();
    }).catch(function(){});
  };
  function renderGeoPoints(msgs){
    var map=window.__teamMap,grp=window.__teamGeo; if(!map||!grp)return; grp.clearLayers();
    (msgs||[]).forEach(function(m){
      if(m.latitude==null||m.longitude==null||!m.point_kind)return;
      var color=m.point_kind==='incident'?'#dc2626':(m.point_kind==='move'?'#2563eb':'#0d9488');
      var lbl=m.point_kind==='incident'?'⚠️ Περιστατικό':(m.point_kind==='move'?'➡️ Μετάβαση':'📍 Σημείο');
      L.circleMarker([m.latitude,m.longitude],{radius:10,color:color,fillColor:color,fillOpacity:.7}).addTo(grp)
        .bindPopup('<b>'+lbl+'</b><br>'+esc(m.body||'')+'<br><a href="https://www.google.com/maps?q='+parseFloat(m.latitude)+','+parseFloat(m.longitude)+'" target="_blank" rel="noopener">Οδηγίες</a>');
    });
  }
  function renderMsgs(msgs){
    if(!msgs||!msgs.length){msgList.innerHTML='<div style="color:#4b7070;font-size:12px;text-align:center;padding:14px">Καμία επικοινωνία ακόμη.</div>';return;}
    msgList.innerHTML=msgs.map(function(m){
      var cls=m.kind==='order'?'msg-order':(m.kind==='status'?'msg-status':(m.sender_role==='command'?'msg-cmd':'msg-team'));
      var who=m.sender_role==='command'?ORG_LABEL:(m.sender_name||'Ομάδα');var t=(m.created_at||'').substr(11,5);
      var h='<div class="msg '+cls+'"><div>'+(m.kind==='order'?'📋 <strong>ΕΝΤΟΛΗ:</strong> ':'')+esc(m.body||'')+'</div>';
      if(m.kind==='order'){h+=m.acknowledged_at?'<div style="font-size:11px;color:#4ade80;margin-top:4px"><i class="bi bi-check2-all"></i> Επιβεβαιώθηκε</div>':'<button class="order-pin-btn" style="margin-top:6px;padding:8px" onclick="ackOrder('+m.id+')">Επιβεβαίωση λήψης</button>';}
      h+='<div class="msg-t">'+esc(who)+' · '+t+'</div></div>';return h;
    }).join('');msgList.scrollTop=msgList.scrollHeight;
  }
  function renderSos(sos){
    if(!sos){sosBanner.style.display='none';if(sosBtn){sosBtn.classList.remove('sos-pulse');sosBtn.disabled=!IS_ACTIVE;if(IS_ACTIVE)sosBtn.querySelector('.sos-sub').textContent='Άμεση κλήση βοήθειας προς ' + ORG_LABEL;}return;}
    sosBanner.style.display='flex';
    if(sos.status==='acknowledged'){sosBanner.className='sos-banner ack';sosBanner.innerHTML='<i class="bi bi-check2-all"></i> Το SOS ελήφθη από ' + esc(ORG_LABEL) + (sos.ack_name?' ('+esc(sos.ack_name)+')':'')+' — έρχεται βοήθεια.';sosBtn.classList.remove('sos-pulse');}
    else{sosBanner.className='sos-banner';sosBanner.innerHTML='<i class="bi bi-broadcast-pin"></i> SOS ΕΝΕΡΓΟ — αναμονή επιβεβαίωσης…';sosBtn.classList.add('sos-pulse');}
    sosBtn.disabled=true;sosBtn.querySelector('.sos-sub').textContent='SOS ενεργό';
  }
  /* Δωμάτιο Επιχείρησης */
  var roomList=document.getElementById('roomList');
  function renderRoom(msgs){
    if(!roomList)return;
    if(!msgs||!msgs.length){roomList.innerHTML='<div style="color:#4b7070;font-size:12px;text-align:center;padding:14px">Κανένα μήνυμα ακόμη.</div>';return;}
    roomList.innerHTML=msgs.map(function(m){
      var cmd=m.sender_role==='command';var who=cmd?ORG_LABEL:(m.sender_label||m.team_name||m.sender_name||'Ομάδα');var t=(m.created_at||'').substr(11,5);
      return '<div class="msg '+(cmd?'msg-cmd':'msg-team')+'"><div>'+esc(m.body||'')+'</div><div class="msg-t">'+esc(who)+' · '+t+'</div></div>';
    }).join('');roomList.scrollTop=roomList.scrollHeight;
  }
  (function(){var s=document.getElementById('roomSend'),i=document.getElementById('roomInput');
    function send(){var b=(i.value||'').trim();if(!b)return;i.value='';postJSON('/room',{body:b}).then(pollComms);}
    if(s)s.addEventListener('click',send);if(i)i.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();send();}});})();

  // Wake Lock: keep screen on while field hub is active
  var wakeLock = null;
  function requestWakeLock(){if('wakeLock' in navigator){navigator.wakeLock.request('screen').then(function(wl){wakeLock=wl;wl.addEventListener('release',function(){wakeLock=null;});}).catch(function(){});}}
  requestWakeLock();
  document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible'&&wakeLock===null){requestWakeLock();}});

  var pollFails=0;
  function pollComms(){
    fetch(BASE+'/f/'+TOKEN+'/comms',{headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){return r.json();})
    .then(function(d){
      pollFails=0;
      var ob=document.getElementById('offlineBanner');if(ob)ob.style.display='none';
      if(!d||!d.success)return;

      /* ── Detect new command messages / orders ── */
      var msgs = d.messages || [];
      if (knownMsgIds === null) {
        /* First load: seed known IDs without alerting */
        var seed = {};
        msgs.forEach(function(m){ seed[m.id] = true; });
        knownMsgIds = seed;
      } else {
        var hasNewOrder = false, hasNewMsg = false;
        msgs.forEach(function(m){
          if (!knownMsgIds[m.id] && m.sender_role === 'command') {
            if (m.kind === 'order') { hasNewOrder = true; }
            else                    { hasNewMsg   = true; }
            knownMsgIds[m.id] = true;
          }
        });
        if (hasNewOrder) {
          alertNewOrder();
          showNewMsgFlash('Νέα εντολή από τον ' + ORG_LABEL + ' — δείτε παρακάτω');
        } else if (hasNewMsg) {
          alertNewRequest();
          showNewMsgFlash('Νέο μήνυμα από τον ' + ORG_LABEL);
        }
      }

      /* ── Detect new GPS / photo requests ── */
      var photoNow = !!d.photo_request;
      var gpsNow   = !!d.gps_request;
      if (photoNow && !prevPhotoReq) {
        alertNewRequest();
        showNewMsgFlash('Αίτημα φωτογραφίας από τον ' + ORG_LABEL + ' — τραβήξτε παρακάτω!');
      }
      if (gpsNow && !prevGpsReq) {
        alertNewRequest();
        showNewMsgFlash('Αίτημα στίγματος GPS από τον ' + ORG_LABEL + ' — πατήστε Αποστολή Στίγματος!');
      }
      prevPhotoReq = photoNow;
      prevGpsReq   = gpsNow;

      renderMsgs(msgs);
      renderOrders(msgs);
      renderGeoPoints(msgs);
      renderSos(d.sos);
      renderRoom(d.room);
      renderResourceRequests(d.resource_requests);
      var pb=document.getElementById('photoReqBanner');if(pb)pb.style.display=photoNow?'block':'none';
      var gb=document.getElementById('gpsReqBanner');if(gb)gb.style.display=gpsNow?'block':'none';
    })
    .catch(function(){
      pollFails++;
      var ob=document.getElementById('offlineBanner');
      if(ob&&pollFails>=2)ob.style.display='flex';
    });
  }
  /* Instant initial render (field connections are flaky — don't wait for the first poll) */
  renderResourceRequests(<?= json_encode($resourceRequests ?? [], JSON_UNESCAPED_UNICODE) ?>);
  pollComms();setInterval(pollComms,5000);
})();
</script>

<script>
(function () {
  'use strict';
  var BASE = window.baseUrl || '', CSRF = window.csrfToken || '';
  var TOKEN = '<?= e($token) ?>';
  var MAX = parseInt(document.getElementById('vidMax') ? document.getElementById('vidMax').textContent : '40', 10) || 40;
  var stream=null, recorder=null, chunks=[], blob=null, startTs=0, timerInt=null, geo=null, durSec=null;

  var $=function(id){return document.getElementById(id);};
  function fmt(s){var m=String(Math.floor(s/60)).padStart(2,'0'),x=String(s%60).padStart(2,'0');return m+':'+x;}
  function showSection(which){
    ['vidIdle','vidStage','vidReview'].forEach(function(s){var el=$(s);if(el)el.style.display='none';});
    var t=$(which); if(t)t.style.display='block';
  }
  function err(msg){var e=$('vidErr');if(e){e.textContent=msg;e.style.display='block';}}
  function grabGeo(){
    if(!navigator.geolocation)return;
    navigator.geolocation.getCurrentPosition(function(p){geo={lat:p.coords.latitude,lng:p.coords.longitude};},function(){},{enableHighAccuracy:true,timeout:6000});
  }
  function overlayText(){
    var b=$('videoReqText'); return b?b.textContent.trim():'';
  }

  /* LIVE CAPTURE */
  $('vidRecordBtn').addEventListener('click', function(){
    if($('vidErr'))$('vidErr').style.display='none';
    if(!navigator.mediaDevices || !window.MediaRecorder){
      err('Η συσκευή δεν υποστηρίζει live εγγραφή. Χρησιμοποιήστε «Από gallery».');return;
    }
    grabGeo();
    navigator.mediaDevices.getUserMedia({video:{facingMode:'environment',width:{ideal:1280},height:{ideal:720}},audio:true})
      .then(function(s){
        stream=s; $('vidPreview').srcObject=s; showSection('vidStage');
        $('vidShutter').style.borderRadius='50%'; $('vidTimer').textContent=fmt(MAX);
        $('vidBar').style.width='0%'; $('vidOverlay').textContent=overlayText();
      })
      .catch(function(){ showSection('vidIdle'); err('Δεν δόθηκε άδεια κάμερας/μικροφώνου. Δοκιμάστε «Από gallery».'); });
  });

  function pickMime(){
    var c=['video/mp4;codecs=h264','video/mp4','video/webm;codecs=vp9','video/webm;codecs=vp8','video/webm'];
    for(var i=0;i<c.length;i++){if(window.MediaRecorder&&MediaRecorder.isTypeSupported(c[i]))return c[i];}
    return '';
  }
  $('vidShutter').addEventListener('click', function(){
    if(recorder&&recorder.state==='recording')stopRec();else startRec();
  });
  function startRec(){
    chunks=[]; var m=pickMime();
    try{recorder=m?new MediaRecorder(stream,{mimeType:m,videoBitsPerSecond:2500000}):new MediaRecorder(stream);}
    catch(e){recorder=new MediaRecorder(stream);}
    recorder.ondataavailable=function(e){if(e.data&&e.data.size)chunks.push(e.data);};
    recorder.onstop=finishRec; recorder.start(); startTs=Date.now();
    $('vidShutter').style.borderRadius='14px';
    timerInt=setInterval(function(){
      var el=Math.floor((Date.now()-startTs)/1000), left=Math.max(0,MAX-el);
      $('vidTimer').textContent=fmt(left); $('vidBar').style.width=Math.min(100,(el/MAX)*100)+'%';
      if(left<=0)stopRec();
    },200);
  }
  function stopRec(){
    if(timerInt){clearInterval(timerInt);timerInt=null;}
    if(recorder&&recorder.state!=='inactive')recorder.stop();
    $('vidShutter').style.borderRadius='50%';
  }
  function finishRec(){
    durSec=Math.round((Date.now()-startTs)/1000);
    blob=new Blob(chunks,{type:(chunks[0]&&chunks[0].type)||'video/webm'});
    stopStream(); loadReview(blob);
  }

  /* GALLERY */
  $('vidGallery').addEventListener('change', function(e){
    var f=e.target.files&&e.target.files[0]; if(!f)return;
    grabGeo(); blob=f; durSec=null; loadReview(f);
  });

  function loadReview(b){
    $('vidReviewPlayer').src=URL.createObjectURL(b);
    if(durSec==null){$('vidReviewPlayer').onloadedmetadata=function(){var d=Math.round($('vidReviewPlayer').duration);if(isFinite(d))durSec=d;};}
    $('vidProg').style.display='none'; $('vidProgBar').style.width='0';
    showSection('vidReview');
  }

  /* SEND (real upload) */
  $('vidSend').addEventListener('click', function(){
    if(!blob)return;
    $('vidSend').disabled=true; $('vidRetake').disabled=true; $('vidProg').style.display='block';
    var fd=new FormData();
    var ext=(blob.type.indexOf('mp4')>=0)?'mp4':(blob.type.indexOf('quicktime')>=0?'mov':'webm');
    fd.append('video', blob, 'clip.'+ext);
    fd.append('_token', CSRF);
    fd.append('caption', $('vidCaption').value||'');
    if(geo){fd.append('latitude',geo.lat);fd.append('longitude',geo.lng);}
    if(durSec){fd.append('duration',durSec);}
    var xhr=new XMLHttpRequest();
    xhr.open('POST', BASE+'/f/'+TOKEN+'/video', true);
    xhr.setRequestHeader('X-CSRF-Token', CSRF);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
    xhr.upload.onprogress=function(ev){if(ev.lengthComputable)$('vidProgBar').style.width=Math.round(ev.loaded/ev.total*100)+'%';};
    xhr.onload=function(){
      if(xhr.status>=200&&xhr.status<400){ location.reload(); }
      else{ $('vidSend').disabled=false;$('vidRetake').disabled=false; err('Αποτυχία αποστολής ('+xhr.status+'). Δοκιμάστε ξανά.'); showSection('vidIdle'); }
    };
    xhr.onerror=function(){ $('vidSend').disabled=false;$('vidRetake').disabled=false; err('Σφάλμα δικτύου. Δοκιμάστε ξανά.'); showSection('vidIdle'); };
    xhr.send(fd);
  });

  $('vidRetake').addEventListener('click', function(){ resetMedia(); showSection('vidIdle'); });
  $('vidCancel').addEventListener('click', function(){ stopRec(); stopStream(); showSection('vidIdle'); });

  function stopStream(){ if(stream){stream.getTracks().forEach(function(t){t.stop();});stream=null;} }
  function resetMedia(){ stopStream(); blob=null; chunks=[]; durSec=null; $('vidReviewPlayer').removeAttribute('src'); $('vidGallery').value=''; }

  /* Light poll: keep the request banner + max duration in sync */
  function pollVideoReq(){
    fetch(BASE+'/f/'+TOKEN+'/comms',{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json();})
      .then(function(d){
        var vr=d&&d.video_request; var banner=$('videoReqBanner');
        if(vr){
          if(banner)banner.style.display='block';
          if(vr.instructions&&$('videoReqText'))$('videoReqText').textContent=vr.instructions;
          if(vr.max_seconds){MAX=parseInt(vr.max_seconds,10)||MAX; if($('vidMax'))$('vidMax').textContent=MAX;}
        } else if(banner){ banner.style.display='none'; }
      }).catch(function(){});
  }
  setInterval(pollVideoReq, 8000);
})();
</script>
</body>
</html>
