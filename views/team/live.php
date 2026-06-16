<?php
/**
 * Mobile Action Hub — /team/live/{id}
 * Standalone full-screen page (no main layout).
 * Designed for field use: large touch targets, dark theme, high contrast.
 */
$eid      = (int) $event['id'];
$approved = (int) $application['approved_people'];
$isActive = ($event['status'] === 'active');

/* Current check-in state helpers */
$checkinStatus = $lastCheckin ? $lastCheckin['status'] : null;
$checkinPeople = $lastCheckin ? (int) $lastCheckin['present_people'] : 0;
$isPresent     = $checkinStatus && in_array($checkinStatus, ['present_full', 'present_partial'], true);
$isDeparted    = $checkinStatus === 'departed';

/* Status banner config */
$bannerClass = 'banner-idle';
$bannerIcon  = 'bi-clock';
$bannerLabel = 'Δεν έχετε δηλώσει παρουσία';
if ($isPresent) {
    $bannerClass = 'banner-present';
    $bannerIcon  = 'bi-check-circle-fill';
    $bannerLabel = $checkinStatus === 'present_full'
        ? 'ΠΑΡΩΝ ΜΕ ΟΛΗ ΤΗΝ ΟΜΑΔΑ · ' . $approved . ' άτομα'
        : 'ΠΑΡΩΝ ΜΕ ΕΛΛΕΙΨΕΙΣ · ' . $checkinPeople . '/' . $approved . ' άτομα';
} elseif ($isDeparted) {
    $bannerClass = 'banner-departed';
    $bannerIcon  = 'bi-box-arrow-right';
    $bannerLabel = 'ΑΠΟΧΩΡΗΣΑΤΕ';
}
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
<script>window.csrfToken = '<?= e(csrf_token()) ?>'; window.baseUrl = '<?= e(base_uri()) ?>';</script>
<style>
/* ── Reset & base ───────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
  height: 100%;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #0d1a1a;
  color: #e8f5f4;
  -webkit-tap-highlight-color: transparent;
  overscroll-behavior: none;
}
body { min-height: 100dvh; }

/* ── Top bar ────────────────────────────────────────────────────────────── */
.topbar {
  position: sticky; top: 0; z-index: 100;
  background: #0d1a1a;
  border-bottom: 1px solid #1e3333;
  padding: env(safe-area-inset-top, 0) 16px 0;
  display: flex; align-items: center; justify-content: space-between;
  min-height: 56px;
  gap: 8px;
}
.topbar-back {
  display: flex; align-items: center; gap: 6px;
  color: #4dd4c4; text-decoration: none;
  font-size: 14px; font-weight: 600;
  padding: 10px 0;
  white-space: nowrap;
}
.topbar-back:hover { color: #80e8dd; }
.topbar-event {
  flex: 1;
  text-align: center;
  overflow: hidden;
}
.topbar-event-name {
  font-size: 13px; font-weight: 700; color: #e8f5f4;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.topbar-event-time { font-size: 11px; color: #7ab5ae; }
.topbar-refresh {
  background: none; border: none; color: #4dd4c4; cursor: pointer;
  font-size: 20px; padding: 10px 0;
  display: flex; align-items: center;
}

/* ── Status banner ──────────────────────────────────────────────────────── */
.status-banner {
  display: flex; align-items: center; gap: 12px;
  margin: 12px 16px 0;
  padding: 14px 16px;
  border-radius: 14px;
  font-weight: 700; font-size: 15px;
}
.status-banner i { font-size: 22px; flex-shrink: 0; }
.banner-idle    { background: #1a2e2e; color: #7ab5ae; border: 1px solid #1e3333; }
.banner-present { background: #0d2e1a; color: #4ade80; border: 1px solid #166534; }
.banner-departed{ background: #2a2a1a; color: #facc15; border: 1px solid #713f12; }
.banner-meta { font-size: 12px; font-weight: 400; opacity: .75; margin-top: 2px; }

/* ── Warning for inactive event ─────────────────────────────────────────── */
.inactive-notice {
  margin: 12px 16px 0;
  padding: 12px 16px;
  border-radius: 12px;
  background: #2a1a0d;
  border: 1px solid #78350f;
  color: #fbbf24;
  font-size: 13px;
  display: flex; align-items: center; gap: 10px;
}
.inactive-notice i { font-size: 18px; flex-shrink: 0; }

/* ── Main scroll area ───────────────────────────────────────────────────── */
.hub-body {
  padding: 16px;
  padding-bottom: calc(env(safe-area-inset-bottom, 0) + 24px);
  display: flex; flex-direction: column; gap: 12px;
}

/* ── Action card ────────────────────────────────────────────────────────── */
.action-card {
  background: #111e1e;
  border: 1px solid #1e3333;
  border-radius: 18px;
  overflow: hidden;
}

/* ── Location button ────────────────────────────────────────────────────── */
.loc-btn {
  width: 100%; padding: 22px 20px;
  background: linear-gradient(135deg, #0e4d4d 0%, #0d3a3a 100%);
  border: none; border-radius: 18px;
  color: #4dd4c4; cursor: pointer;
  display: flex; align-items: center; gap: 16px;
  text-align: left;
  transition: background .15s, transform .1s;
  -webkit-tap-highlight-color: transparent;
}
.loc-btn:active { transform: scale(.98); background: linear-gradient(135deg, #0a3d3d 0%, #0a2a2a 100%); }
.loc-btn:disabled { opacity: .4; cursor: not-allowed; transform: none; }
.loc-btn-icon { font-size: 36px; flex-shrink: 0; }
.loc-btn-label { font-size: 18px; font-weight: 800; color: #e8f5f4; }
.loc-btn-sub   { font-size: 12px; color: #7ab5ae; margin-top: 2px; }
.loc-btn-arrow { margin-left: auto; font-size: 20px; opacity: .5; }
#locResult { padding: 10px 20px 14px; font-size: 13px; color: #4ade80; display: none; }
#locResult.error { color: #f87171; }

/* ── Presence section ───────────────────────────────────────────────────── */
.presence-header {
  padding: 18px 20px 14px;
  font-size: 15px; font-weight: 800; color: #e8f5f4;
  display: flex; align-items: center; gap: 10px;
}
.presence-header i { font-size: 22px; color: #4dd4c4; }
.presence-btns {
  display: grid; grid-template-columns: 1fr 1fr 1fr;
  gap: 10px; padding: 0 14px 14px;
}
.pres-btn {
  padding: 16px 6px; border: none; border-radius: 14px;
  font-size: 12px; font-weight: 700;
  cursor: pointer; text-align: center; line-height: 1.3;
  transition: transform .1s, opacity .1s;
  -webkit-tap-highlight-color: transparent;
  display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.pres-btn i { font-size: 26px; }
.pres-btn:active { transform: scale(.95); opacity: .8; }
.pres-btn:disabled { opacity: .35; cursor: not-allowed; transform: none; }
.pres-btn-full     { background: #166534; color: #4ade80; }
.pres-btn-partial  { background: #713f12; color: #facc15; }
.pres-btn-departed { background: #1e1e1e; color: #9ca3af; border: 1px solid #374151; }

/* ── Partial inline form ────────────────────────────────────────────────── */
#partialForm {
  display: none;
  padding: 0 14px 14px;
}
#partialForm.show { display: block; }
.partial-inner {
  background: #0d2b1a;
  border: 1px solid #166534;
  border-radius: 14px;
  padding: 16px;
}
.partial-inner label { font-size: 13px; color: #86efac; margin-bottom: 6px; display: block; }
.partial-row { display: flex; gap: 10px; align-items: center; }
.partial-row input[type=number] {
  flex: 1;
  background: #0d1a1a; border: 2px solid #166534; border-radius: 10px;
  color: #e8f5f4; font-size: 28px; font-weight: 700; text-align: center;
  padding: 12px 8px; outline: none;
  -moz-appearance: textfield;
}
.partial-row input[type=number]:focus { border-color: #4ade80; }
.partial-row input[type=number]::-webkit-inner-spin-button,
.partial-row input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; }
.partial-submit {
  background: #facc15; color: #1a1400; border: none; border-radius: 10px;
  font-size: 14px; font-weight: 800; padding: 14px 18px; cursor: pointer;
  flex-shrink: 0; transition: transform .1s;
}
.partial-submit:active { transform: scale(.96); }
.partial-hint { font-size: 12px; color: #7ab5ae; margin-top: 6px; }
/* Stepper buttons */
.stepper { display: flex; gap: 8px; margin-bottom: 8px; }
.step-btn {
  flex: 1; background: #1a2e2e; border: 1px solid #1e3333;
  border-radius: 10px; color: #4dd4c4; font-size: 22px; font-weight: 700;
  padding: 10px; cursor: pointer; text-align: center;
  transition: background .1s;
}
.step-btn:active { background: #0e3d3d; }

/* ── Shortage section ───────────────────────────────────────────────────── */
.shortage-toggle {
  width: 100%; padding: 22px 20px;
  background: #1a1111; border: none; border-radius: 18px;
  color: #f87171; cursor: pointer;
  display: flex; align-items: center; gap: 16px;
  text-align: left;
  transition: background .15s;
  -webkit-tap-highlight-color: transparent;
}
.shortage-toggle:active { background: #1f1414; }
.shortage-toggle:disabled { opacity: .35; cursor: not-allowed; }
.shortage-toggle-icon { font-size: 36px; flex-shrink: 0; }
.shortage-toggle-label { font-size: 18px; font-weight: 800; color: #e8f5f4; }
.shortage-toggle-sub   { font-size: 12px; color: #f87171; margin-top: 2px; }
.shortage-toggle-arrow { margin-left: auto; font-size: 20px; opacity: .5; transition: transform .2s; }
.shortage-toggle.open .shortage-toggle-arrow { transform: rotate(90deg); }

#shortageForm {
  display: none;
  border-top: 1px solid #2a1818;
}
#shortageForm.show { display: block; }
.shortage-form-inner { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.form-row { display: flex; gap: 10px; }
.form-row .form-group { flex: 1; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group label { font-size: 12px; color: #9ca3af; font-weight: 600; }
.form-select, .form-input, .form-textarea {
  background: #0d1a1a; border: 1px solid #1e3333; border-radius: 10px;
  color: #e8f5f4; font-size: 15px; padding: 12px 14px; outline: none;
  width: 100%; appearance: none; -webkit-appearance: none;
  font-family: inherit;
}
.form-select:focus, .form-input:focus, .form-textarea:focus {
  border-color: #4dd4c4;
}
.form-textarea { resize: none; }
.severity-grid {
  display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 6px;
}
.sev-btn {
  padding: 10px 4px; border-radius: 8px; border: 2px solid transparent;
  font-size: 11px; font-weight: 700; cursor: pointer; text-align: center;
  transition: transform .1s;
}
.sev-btn:active { transform: scale(.95); }
.sev-low      { background: #1a2e1a; color: #86efac; }
.sev-medium   { background: #2a2a0d; color: #fde047; }
.sev-high     { background: #2a1a0d; color: #fb923c; }
.sev-critical { background: #2a0d0d; color: #f87171; }
.sev-btn.selected { border-color: currentColor; }
.shortage-submit {
  background: #b91c1c; color: #fff; border: none; border-radius: 10px;
  font-size: 15px; font-weight: 800; padding: 16px;
  cursor: pointer; width: 100%; margin-top: 4px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s;
}
.shortage-submit:active { background: #991b1b; }

/* ── Shortage history ───────────────────────────────────────────────────── */
.section-title {
  font-size: 12px; font-weight: 700; color: #4dd4c4;
  text-transform: uppercase; letter-spacing: .08em;
  padding: 4px 4px 8px;
}
.shortage-item {
  background: #111e1e; border: 1px solid #1e3333;
  border-radius: 12px; padding: 12px 14px;
  margin-bottom: 8px;
}
.shortage-item:last-child { margin-bottom: 0; }
.shortage-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.badge {
  display: inline-flex; align-items: center;
  padding: 3px 8px; border-radius: 20px;
  font-size: 11px; font-weight: 700; white-space: nowrap;
}
.badge-low      { background: #1a2e1a; color: #86efac; }
.badge-medium   { background: #2a2a0d; color: #fde047; }
.badge-high     { background: #2a1a0d; color: #fb923c; }
.badge-critical { background: #2a0d0d; color: #f87171; }
.badge-open     { background: #1a2a2a; color: #7ab5ae; }
.badge-acknowledged { background: #1a2a3a; color: #60a5fa; }
.badge-resolved { background: #0d2e1a; color: #4ade80; }
.shortage-title { font-size: 14px; font-weight: 700; color: #e8f5f4; flex: 1; }
.shortage-meta  { font-size: 11px; color: #4b7070; margin-top: 4px; }

/* ── Flash message ──────────────────────────────────────────────────────── */
.flash {
  margin: 12px 16px 0;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 14px; font-weight: 600;
  display: flex; align-items: center; gap: 10px;
}
.flash-success { background: #0d2e1a; color: #4ade80; border: 1px solid #166534; }
.flash-danger  { background: #2a0d0d; color: #f87171; border: 1px solid #7f1d1d; }
.flash-warning { background: #2a1a0d; color: #facc15; border: 1px solid #78350f; }
.flash i { font-size: 18px; flex-shrink: 0; }

/* ── Wake lock indicator ────────────────────────────────────────────────── */
#wakeLockIndicator {
  display: none;
  font-size: 10px; color: #4dd4c4; opacity: .6;
  text-align: center; padding: 4px;
}

/* ── SOS ────────────────────────────────────────────────────────────────── */
.sos-btn {
  width: 100%; padding: 24px 20px; border: none; border-radius: 18px;
  background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%); color: #fff; cursor: pointer;
  display: flex; align-items: center; gap: 16px; text-align: left;
  transition: transform .1s;
}
.sos-btn:active { transform: scale(.98); }
.sos-btn:disabled { opacity: .45; cursor: not-allowed; }
.sos-btn-icon { font-size: 40px; flex-shrink: 0; }
.sos-btn-label { font-size: 22px; font-weight: 900; letter-spacing: .04em; }
.sos-btn-sub { font-size: 12px; opacity: .9; margin-top: 2px; }
.sos-pulse { animation: sosPulse 1.2s infinite; }
@keyframes sosPulse {
  0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.6); }
  70%  { box-shadow: 0 0 0 18px rgba(239,68,68,0); }
  100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
}
.sos-active-banner {
  margin-top: 10px; padding: 12px 14px; border-radius: 12px;
  background: #2a0d0d; border: 1px solid #7f1d1d; color: #fca5a5;
  font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px;
}
.sos-active-banner.ack { background: #0d2233; border-color: #1e40af; color: #93c5fd; }

/* ── Quick status pings ──────────────────────────────────────────────────── */
.ping-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 0 14px 14px; }
.ping-btn {
  padding: 14px 10px; border: none; border-radius: 12px; cursor: pointer;
  background: #13302e; color: #7fe6d8; font-size: 13px; font-weight: 700;
  display: flex; align-items: center; gap: 8px; text-align: left; transition: transform .1s;
}
.ping-btn:active { transform: scale(.96); }
.ping-btn:disabled { opacity: .35; cursor: not-allowed; }
.ping-btn i { font-size: 20px; flex-shrink: 0; }
.ping-btn.full { grid-column: 1 / -1; background: #3a1414; color: #fca5a5; }

/* ── Comms thread ────────────────────────────────────────────────────────── */
.msg-list { padding: 0 14px 12px; display: flex; flex-direction: column; gap: 8px; max-height: 300px; overflow-y: auto; }
.msg { padding: 10px 12px; border-radius: 12px; font-size: 13px; line-height: 1.35; max-width: 90%; }
.msg-time { font-size: 10px; opacity: .6; margin-top: 3px; }
.msg-command { align-self: flex-start; background: #13243a; color: #cfe3ff; border: 1px solid #1e3a5f; }
.msg-team    { align-self: flex-end;   background: #0e3d3a; color: #cdebe6; }
.msg-order   { align-self: flex-start; background: #3a2a0d; color: #fcd9a5; border: 1px solid #78510f; }
.msg-status  { align-self: flex-end;   background: #13302e; color: #9be8da; font-weight: 700; }
.msg-ack-btn { margin-top: 6px; background: #facc15; color: #1a1400; border: none; border-radius: 8px; padding: 7px 12px; font-size: 12px; font-weight: 800; cursor: pointer; }
.msg-acked   { margin-top: 4px; font-size: 11px; color: #4ade80; font-weight: 700; }
.msg-empty   { color: #4b7070; font-size: 12px; text-align: center; padding: 14px; }
.msg-compose { display: flex; gap: 8px; padding: 0 14px 14px; }
.msg-compose input { flex: 1; background: #0d1a1a; border: 1px solid #1e3333; border-radius: 10px; color: #e8f5f4; font-size: 14px; padding: 12px; outline: none; }
.msg-compose button { background: #0e7490; color: #fff; border: none; border-radius: 10px; padding: 0 16px; font-size: 18px; cursor: pointer; }

/* ── Pinned order banner ─────────────────────────────────────────────────── */
.order-pin { background: linear-gradient(135deg, #7c2d12 0%, #b45309 100%); border: 1px solid #f59e0b; border-radius: 16px; padding: 16px; margin-bottom: 12px; animation: sosPulse 1.4s infinite; }
.order-pin-head { font-weight: 900; color: #fde68a; font-size: 14px; letter-spacing: .03em; display: flex; align-items: center; gap: 6px; }
.order-pin-body { color: #fff; font-size: 16px; margin: 8px 0 12px; line-height: 1.35; }
.order-pin-btn { background: #facc15; color: #1a1400; border: none; border-radius: 12px; font-weight: 800; font-size: 15px; padding: 14px 16px; width: 100%; cursor: pointer; }
.order-pin-btn:active { transform: scale(.98); }
.order-pin-time { font-size: 11px; color: #fde68a; opacity: .8; margin-top: 8px; text-align: right; }
</style>
</head>
<body>

<!-- ── Top bar ─────────────────────────────────────────────────────────── -->
<div class="topbar">
  <a href="<?= e(url('/team/operations/events/' . $eid)) ?>" class="topbar-back">
    <i class="bi bi-chevron-left"></i> Επιστροφή
  </a>
  <div class="topbar-event">
    <div class="topbar-event-name"><?= e($event['title']) ?></div>
    <div class="topbar-event-time">
      <?= e(gr_time($event['start_datetime'])) ?>–<?= e(gr_time($event['end_datetime'])) ?>
      <?php if ($event['location_name']): ?> · <?= e($event['location_name']) ?><?php endif; ?>
    </div>
  </div>
  <button class="topbar-refresh" onclick="window.location.reload()" title="Ανανέωση">
    <i class="bi bi-arrow-clockwise" id="refreshIcon"></i>
  </button>
</div>

<?php
/* Flash messages */
$flash = get_flash();
if ($flash):
  $ftype = $flash['type'] ?? 'success';
  $ficon = match($ftype) { 'success' => 'bi-check-circle-fill', 'danger' => 'bi-x-circle-fill', default => 'bi-exclamation-triangle-fill' };
?>
<div class="flash flash-<?= e($ftype) ?>">
  <i class="bi <?= e($ficon) ?>"></i>
  <?= e($flash['message']) ?>
</div>
<?php endif; ?>

<!-- ── Inactive notice ─────────────────────────────────────────────────── -->
<?php if (!$isActive): ?>
<div class="inactive-notice">
  <i class="bi bi-hourglass-split"></i>
  Η δράση δεν είναι ακόμη ενεργή. Οι επιχειρησιακές ενέργειες ενεργοποιούνται όταν ο δήμος ξεκινήσει τη δράση.
</div>
<?php endif; ?>

<!-- ── Status banner ───────────────────────────────────────────────────── -->
<div class="status-banner <?= e($bannerClass) ?>">
  <i class="bi <?= e($bannerIcon) ?>"></i>
  <div>
    <div><?= e($bannerLabel) ?></div>
    <?php if ($lastCheckin): ?>
      <div class="banner-meta">Ενημέρωση: <?= e(gr_time($lastCheckin['checked_in_at'])) ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="hub-body">

  <!-- Pinned ΕΝΤΟΛΕΣ (unacknowledged orders) -->
  <div id="orderBanner" style="display:none"></div>

  <!-- ── 0. SOS / Έκτακτη Ανάγκη ────────────────────────────────────── -->
  <div>
    <button class="sos-btn" id="sosBtn" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-exclamation-octagon-fill sos-btn-icon"></i>
      <div style="flex:1">
        <div class="sos-btn-label">SOS — ΚΙΝΔΥΝΟΣ</div>
        <div class="sos-btn-sub">Πατήστε για άμεση κλήση βοήθειας στον δήμο</div>
      </div>
    </button>
    <div class="sos-active-banner" id="sosBanner" style="display:none"></div>
  </div>

  <!-- ── 1. Αποστολή Στίγματος ──────────────────────────────────────── -->
  <div class="action-card">
    <button class="loc-btn" id="locBtn" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-broadcast loc-btn-icon"></i>
      <div>
        <div class="loc-btn-label">Αποστολή Στίγματος</div>
        <div class="loc-btn-sub">
          <?php if ($lastPing): ?>
            Τελευταίο: <?= e(gr_time($lastPing['created_at'])) ?>
          <?php else: ?>
            Στείλτε τη θέση σας στον δήμο
          <?php endif; ?>
        </div>
      </div>
      <i class="bi bi-chevron-right loc-btn-arrow"></i>
    </button>
    <div id="locResult"></div>
  </div>

  <!-- ── 2. Δήλωση Παρουσίας ────────────────────────────────────────── -->
  <div class="action-card">
    <div class="presence-header">
      <i class="bi bi-person-check"></i>
      Δήλωση Παρουσίας
      <span style="font-size:12px;font-weight:400;color:#7ab5ae;margin-left:auto"><?= $approved ?> εγκεκριμένα</span>
    </div>
    <div class="presence-btns">
      <!-- Full -->
      <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/checkin')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="present_full">
        <input type="hidden" name="_from" value="live">
        <button type="submit" class="pres-btn pres-btn-full" <?= !$isActive ? 'disabled' : '' ?>>
          <i class="bi bi-check2-all"></i>
          Πλήρης<br><span style="font-size:10px;font-weight:400"><?= $approved ?> άτομα</span>
        </button>
      </form>
      <!-- Partial -->
      <button type="button" class="pres-btn pres-btn-partial" id="partialToggle"
              onclick="togglePartial()" <?= !$isActive ? 'disabled' : '' ?>>
        <i class="bi bi-check2"></i>
        Μερική<br><span style="font-size:10px;font-weight:400">λιγότερα άτομα</span>
      </button>
      <!-- Departed -->
      <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/checkin')) ?>"
            onsubmit="return confirm('Αποχώρηση ομάδας από τη δράση;')">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="departed">
        <input type="hidden" name="_from" value="live">
        <button type="submit" class="pres-btn pres-btn-departed" <?= !$isActive ? 'disabled' : '' ?>>
          <i class="bi bi-box-arrow-right"></i>
          Αποχώρηση
        </button>
      </form>
    </div>

    <!-- Inline partial form -->
    <div id="partialForm">
      <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/checkin')) ?>" class="partial-inner">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="present_partial">
        <input type="hidden" name="_from" value="live">
        <label>Πόσα άτομα είναι παρόντα; (από <?= $approved ?>)</label>
        <div class="stepper">
          <button type="button" class="step-btn" onclick="stepCount(-1)">−</button>
          <button type="button" class="step-btn" onclick="stepCount(1)">+</button>
        </div>
        <div class="partial-row">
          <input type="number" name="present_people" id="partialCount"
                 min="1" max="<?= max(1, $approved - 1) ?>"
                 value="<?= max(1, $approved - 1) ?>" required>
          <button type="submit" class="partial-submit">
            <i class="bi bi-check-lg"></i> Δήλωση
          </button>
        </div>
        <div class="partial-hint">Εγκεκριμένα: <?= $approved ?> άτομα</div>
      </form>
    </div>
  </div>

  <!-- ── 3. Αναφορά Έλλειψης ────────────────────────────────────────── -->
  <div class="action-card">
    <button class="shortage-toggle" id="shortageToggle"
            onclick="toggleShortage()" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-exclamation-triangle shortage-toggle-icon"></i>
      <div>
        <div class="shortage-toggle-label">Αναφορά Έλλειψης</div>
        <div class="shortage-toggle-sub">Ειδοποιήστε άμεσα τον δήμο</div>
      </div>
      <i class="bi bi-chevron-right shortage-toggle-arrow" id="shortageArrow"></i>
    </button>

    <div id="shortageForm">
      <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/shortage')) ?>"
            class="shortage-form-inner" id="shortageFormEl">
        <?= csrf_field() ?>
        <input type="hidden" name="_from" value="live">
        <input type="hidden" name="severity" id="severityInput" value="medium">

        <div class="form-group">
          <label>Τύπος έλλειψης</label>
          <select name="shortage_type" class="form-select" required>
            <option value="people">👥 Άτομα</option>
            <option value="equipment">🔧 Εξοπλισμός</option>
            <option value="medical_supplies">🏥 Υγειονομικό υλικό</option>
            <option value="vehicle">🚗 Όχημα</option>
            <option value="other">📋 Άλλο</option>
          </select>
        </div>

        <div class="form-group">
          <label>Σοβαρότητα</label>
          <div class="severity-grid">
            <button type="button" class="sev-btn sev-low" data-val="low" onclick="setSeverity('low')">Χαμηλή</button>
            <button type="button" class="sev-btn sev-medium selected" data-val="medium" onclick="setSeverity('medium')">Μεσαία</button>
            <button type="button" class="sev-btn sev-high" data-val="high" onclick="setSeverity('high')">Υψηλή</button>
            <button type="button" class="sev-btn sev-critical" data-val="critical" onclick="setSeverity('critical')">Κρίσιμη</button>
          </div>
        </div>

        <div class="form-group">
          <label>Σύντομος τίτλος *</label>
          <input type="text" name="title" class="form-input" required placeholder="π.χ. Λείπουν 2 άτομα">
        </div>

        <div class="form-group">
          <label>Περιγραφή (προαιρετικό)</label>
          <textarea name="description" class="form-textarea" rows="2" placeholder="Επιπλέον λεπτομέρειες..."></textarea>
        </div>

        <button type="submit" class="shortage-submit"
                onclick="return confirm('Αποστολή αναφοράς έλλειψης στον δήμο;')">
          <i class="bi bi-send-fill"></i> Αποστολή Αναφοράς
        </button>
      </form>
    </div>
  </div>

  <!-- ── Shortage history ───────────────────────────────────────────── -->
  <?php if ($shortages): ?>
  <div>
    <div class="section-title"><i class="bi bi-clock-history me-1"></i>Αναφορές μας σε αυτή τη δράση</div>
    <?php foreach ($shortages as $sh): ?>
      <div class="shortage-item">
        <div class="shortage-row">
          <?php
            $svClass = match($sh['severity']) { 'low' => 'badge-low', 'high' => 'badge-high', 'critical' => 'badge-critical', default => 'badge-medium' };
            $stClass = match($sh['status'])   { 'acknowledged' => 'badge-acknowledged', 'resolved' => 'badge-resolved', default => 'badge-open' };
            $stLabel = match($sh['status'])   { 'acknowledged' => 'Λήφθηκε', 'resolved' => 'Επιλύθηκε', default => 'Ανοιχτό' };
          ?>
          <span class="badge <?= $svClass ?>"><?= e(severity_label($sh['severity'])) ?></span>
          <span class="shortage-title"><?= e($sh['title']) ?></span>
          <span class="badge <?= $stClass ?>"><?= e($stLabel) ?></span>
        </div>
        <div class="shortage-meta"><?= e(shortage_type_label($sh['shortage_type'])) ?> · <?= e(gr_time($sh['created_at'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Γρήγορη ενημέρωση (status pings) ────────────────────────────── -->
  <div class="action-card">
    <div class="presence-header"><i class="bi bi-lightning-charge"></i> Γρήγορη ενημέρωση</div>
    <div class="ping-grid">
      <button type="button" class="ping-btn" data-code="arrived" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-geo-alt-fill"></i> Φτάσαμε στο σημείο</button>
      <button type="button" class="ping-btn" data-code="task_complete" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i> Ολοκληρώθηκε</button>
      <button type="button" class="ping-btn" data-code="need_backup" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-people-fill"></i> Χρειαζόμαστε ενίσχυση</button>
      <button type="button" class="ping-btn" data-code="returning" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-box-arrow-left"></i> Επιστροφή στη βάση</button>
      <button type="button" class="ping-btn full" data-code="incident" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-exclamation-triangle-fill"></i> Έχουμε περιστατικό</button>
    </div>
  </div>

  <!-- ── Επικοινωνία με τον δήμο ──────────────────────────────────────── -->
  <div class="action-card">
    <div class="presence-header"><i class="bi bi-chat-dots"></i> Επικοινωνία με τον δήμο</div>
    <div class="msg-list" id="msgList"><div class="msg-empty">Φόρτωση…</div></div>
    <div class="msg-compose">
      <input type="text" id="msgInput" placeholder="Μήνυμα προς τον δήμο…" maxlength="500">
      <button type="button" id="msgSend" title="Αποστολή"><i class="bi bi-send"></i></button>
    </div>
  </div>

  <div id="wakeLockIndicator"><i class="bi bi-phone"></i> Η οθόνη παραμένει ενεργή</div>
</div><!-- .hub-body -->

<script>
(function () {
  'use strict';
  var BASE = window.baseUrl || '';
  var EID  = <?= $eid ?>;
  var CSRF = window.csrfToken || '';
  var IS_ACTIVE = <?= $isActive ? 'true' : 'false' ?>;

  function postJSON(path, body) {
    return fetch(BASE + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json(); });
  }
  function escapeHtml(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

  /* ── Wake Lock (keep screen on) ─────────────────────────────────── */
  var wakeLock = null;
  async function requestWakeLock() {
    if ('wakeLock' in navigator) {
      try {
        wakeLock = await navigator.wakeLock.request('screen');
        document.getElementById('wakeLockIndicator').style.display = 'block';
        wakeLock.addEventListener('release', function () {
          document.getElementById('wakeLockIndicator').style.display = 'none';
        });
      } catch (e) {}
    }
  }
  requestWakeLock();
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') requestWakeLock();
  });

  /* ── Auto-refresh every 60s ─────────────────────────────────────── */
  var refreshTimer = setTimeout(function () {
    window.location.reload();
  }, 60000);

  // Reset timer on any interaction
  document.addEventListener('touchstart', function () {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(function () { window.location.reload(); }, 60000);
  }, { passive: true });

  /* ── Refresh icon animation ─────────────────────────────────────── */
  document.querySelector('.topbar-refresh').addEventListener('click', function () {
    var icon = document.getElementById('refreshIcon');
    icon.style.transition = 'transform .5s';
    icon.style.transform  = 'rotate(360deg)';
    setTimeout(function () { icon.style.transform = ''; icon.style.transition = ''; }, 500);
  });

  /* ── Send Location ──────────────────────────────────────────────── */
  var locBtn = document.getElementById('locBtn');
  var locResult = document.getElementById('locResult');

  if (locBtn) {
    locBtn.addEventListener('click', function () {
      if (!navigator.geolocation) {
        showLocResult('Η συσκευή δεν υποστηρίζει αποστολή τοποθεσίας.', true);
        return;
      }
      locBtn.disabled = true;
      locBtn.querySelector('.loc-btn-sub').textContent = 'Λήψη GPS…';

      navigator.geolocation.getCurrentPosition(
        function (pos) {
          fetch(BASE + '/team/operations/events/' + EID + '/send-location', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': CSRF,
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              latitude:  pos.coords.latitude,
              longitude: pos.coords.longitude,
              accuracy:  pos.coords.accuracy
            })
          })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.success) {
              showLocResult('✓ Στίγμα στάλθηκε επιτυχώς!', false);
              locBtn.querySelector('.loc-btn-sub').textContent = 'Τώρα';
            } else {
              showLocResult(d.message || 'Σφάλμα αποστολής.', true);
              locBtn.querySelector('.loc-btn-sub').textContent = 'Αποτυχία — δοκιμάστε ξανά';
            }
          })
          .catch(function () {
            showLocResult('Σφάλμα σύνδεσης. Ελέγξτε το internet.', true);
          })
          .finally(function () {
            locBtn.disabled = false;
          });
        },
        function (err) {
          var msg = err.code === 1 ? 'Δεν δόθηκε άδεια τοποθεσίας.' : 'Δεν ήταν δυνατή η λήψη GPS.';
          showLocResult(msg, true);
          locBtn.disabled = false;
          locBtn.querySelector('.loc-btn-sub').textContent = 'Δοκιμάστε ξανά';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    });
  }

  function showLocResult(msg, isErr) {
    locResult.textContent = msg;
    locResult.className = isErr ? 'error' : '';
    locResult.style.display = 'block';
    setTimeout(function () { locResult.style.display = 'none'; }, 4000);
  }

  /* ── Partial presence ───────────────────────────────────────────── */
  window.togglePartial = function () {
    var f = document.getElementById('partialForm');
    f.classList.toggle('show');
  };

  window.stepCount = function (delta) {
    var inp = document.getElementById('partialCount');
    var val = parseInt(inp.value, 10) || 1;
    var min = parseInt(inp.min, 10) || 1;
    var max = parseInt(inp.max, 10) || 99;
    inp.value = Math.min(max, Math.max(min, val + delta));
  };

  /* ── Shortage panel ─────────────────────────────────────────────── */
  window.toggleShortage = function () {
    var f   = document.getElementById('shortageForm');
    var tog = document.getElementById('shortageToggle');
    f.classList.toggle('show');
    tog.classList.toggle('open');
  };

  window.setSeverity = function (val) {
    document.getElementById('severityInput').value = val;
    document.querySelectorAll('.sev-btn').forEach(function (b) {
      b.classList.toggle('selected', b.dataset.val === val);
    });
  };

  /* ── SOS ────────────────────────────────────────────────────────── */
  var sosBtn = document.getElementById('sosBtn');
  var sosBanner = document.getElementById('sosBanner');
  if (sosBtn) {
    sosBtn.addEventListener('click', function () {
      if (!confirm('ΕΠΙΒΕΒΑΙΩΣΗ SOS\n\nΘα ειδοποιηθεί ΑΜΕΣΑ ο δήμος ότι κινδυνεύετε. Συνέχεια;')) return;
      sosBtn.disabled = true;
      sosBtn.querySelector('.sos-btn-sub').textContent = 'Λήψη τοποθεσίας…';
      var send = function (lat, lng, acc) {
        postJSON('/team/operations/events/' + EID + '/sos', { latitude: lat, longitude: lng, accuracy: acc })
          .then(function (d) {
            if (d && d.success) { sosBtn.querySelector('.sos-btn-sub').textContent = 'SOS εστάλη — ο δήμος ειδοποιήθηκε'; pollComms(); }
            else { sosBtn.disabled = false; sosBtn.querySelector('.sos-btn-sub').textContent = (d && d.message) || 'Αποτυχία — δοκιμάστε ξανά'; }
          })
          .catch(function () { sosBtn.disabled = false; sosBtn.querySelector('.sos-btn-sub').textContent = 'Σφάλμα σύνδεσης — δοκιμάστε ξανά'; });
      };
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function (p) { send(p.coords.latitude, p.coords.longitude, p.coords.accuracy); },
          function () { send(null, null, null); },
          { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
        );
      } else { send(null, null, null); }
    });
  }

  /* ── Quick status pings ─────────────────────────────────────────── */
  document.querySelectorAll('.ping-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.disabled) return;
      var orig = b.innerHTML;
      b.disabled = true; b.style.opacity = '.6';
      postJSON('/team/operations/events/' + EID + '/status-ping', { code: b.dataset.code })
        .then(function () {
          b.innerHTML = '<i class="bi bi-check2"></i> Στάλθηκε';
          setTimeout(function () { b.innerHTML = orig; b.disabled = false; b.style.opacity = ''; }, 2500);
          pollComms();
        })
        .catch(function () { b.innerHTML = orig; b.disabled = false; b.style.opacity = ''; });
    });
  });

  /* ── Comms: send + ack ──────────────────────────────────────────── */
  var msgInput = document.getElementById('msgInput');
  var msgSend  = document.getElementById('msgSend');
  function sendMsg() {
    var body = (msgInput.value || '').trim();
    if (!body) return;
    msgInput.value = '';
    postJSON('/team/operations/events/' + EID + '/message', { body: body }).then(pollComms);
  }
  if (msgSend)  { msgSend.addEventListener('click', sendMsg); }
  if (msgInput) { msgInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sendMsg(); } }); }

  window.ackOrder = function (id) {
    postJSON('/team/operations/events/' + EID + '/ack-order', { message_id: id }).then(pollComms);
  };

  /* ── Comms polling (~5s) ────────────────────────────────────────── */
  var msgListEl = document.getElementById('msgList');
  function renderMsgs(msgs) {
    if (!msgListEl) return;
    if (!msgs || !msgs.length) { msgListEl.innerHTML = '<div class="msg-empty">Καμία επικοινωνία ακόμη.</div>'; return; }
    msgListEl.innerHTML = msgs.map(function (m) {
      var cls = m.kind === 'order' ? 'msg-order'
              : (m.kind === 'status' ? 'msg-status'
              : (m.sender_role === 'command' ? 'msg-command' : 'msg-team'));
      var who = m.sender_role === 'command' ? 'Δήμος' : (m.sender_name || 'Ομάδα');
      var t = (m.created_at || '').substr(11, 5);
      var html = '<div class="msg ' + cls + '"><div>' +
                 (m.kind === 'order' ? '📋 <strong>ΕΝΤΟΛΗ:</strong> ' : '') + escapeHtml(m.body || '') + '</div>';
      if (m.kind === 'order') {
        html += m.acknowledged_at
          ? '<div class="msg-acked"><i class="bi bi-check2-all"></i> Επιβεβαιώθηκε</div>'
          : '<button class="msg-ack-btn" onclick="ackOrder(' + m.id + ')">Επιβεβαίωση λήψης</button>';
      }
      html += '<div class="msg-time">' + who + ' · ' + t + '</div></div>';
      return html;
    }).join('');
    msgListEl.scrollTop = msgListEl.scrollHeight;
  }
  function renderSos(sos) {
    if (!sosBanner) return;
    if (!sos) {
      sosBanner.style.display = 'none';
      if (sosBtn) { sosBtn.classList.remove('sos-pulse'); sosBtn.disabled = !IS_ACTIVE;
        if (IS_ACTIVE) sosBtn.querySelector('.sos-btn-sub').textContent = 'Πατήστε για άμεση κλήση βοήθειας στον δήμο'; }
      return;
    }
    sosBanner.style.display = 'flex';
    if (sos.status === 'acknowledged') {
      sosBanner.className = 'sos-active-banner ack';
      sosBanner.innerHTML = '<i class="bi bi-check2-all"></i> Το SOS ελήφθη από τον δήμο' +
        (sos.ack_name ? ' (' + escapeHtml(sos.ack_name) + ')' : '') + ' — έρχεται βοήθεια.';
      if (sosBtn) sosBtn.classList.remove('sos-pulse');
    } else {
      sosBanner.className = 'sos-active-banner';
      sosBanner.innerHTML = '<i class="bi bi-broadcast-pin"></i> SOS ΕΝΕΡΓΟ — αναμονή επιβεβαίωσης από τον δήμο…';
      if (sosBtn) sosBtn.classList.add('sos-pulse');
    }
    if (sosBtn) { sosBtn.disabled = true; sosBtn.querySelector('.sos-btn-sub').textContent = 'SOS ενεργό'; }
  }
  var orderBannerEl = document.getElementById('orderBanner');
  function renderOrders(msgs) {
    if (!orderBannerEl) return;
    var pending = (msgs || []).filter(function (m) { return m.kind === 'order' && !m.acknowledged_at; });
    if (!pending.length) { orderBannerEl.innerHTML = ''; orderBannerEl.style.display = 'none'; return; }
    orderBannerEl.style.display = '';
    orderBannerEl.innerHTML = pending.map(function (m) {
      var t = (m.created_at || '').substr(11, 5);
      return '<div class="order-pin">' +
        '<div class="order-pin-head"><i class="bi bi-megaphone-fill"></i> ΕΝΤΟΛΗ ΑΠΟ ΤΟΝ ΔΗΜΟ</div>' +
        '<div class="order-pin-body">' + escapeHtml(m.body || '') + '</div>' +
        '<button type="button" class="order-pin-btn" onclick="ackOrder(' + m.id + ')"><i class="bi bi-check2-all"></i> Επιβεβαίωση λήψης</button>' +
        '<div class="order-pin-time">' + t + '</div></div>';
    }).join('');
  }

  function pollComms() {
    fetch(BASE + '/team/operations/events/' + EID + '/comms?since=0', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.success) { renderMsgs(d.messages); renderSos(d.sos); renderOrders(d.messages); } })
      .catch(function () {});
  }
  pollComms();
  setInterval(pollComms, 5000);

})();
</script>
</body>
</html>
