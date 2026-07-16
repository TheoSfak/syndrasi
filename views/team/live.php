<?php
/**
 * Mobile Action Hub — /team/live/{id}
 * Standalone full-screen page (no main layout).
 * Designed for field use: large touch targets, dark theme, high contrast.
 */
$eid      = (int) $event['id'];
$approved = (int) $application['approved_people'];
$terms = authority_context((int) ($event['municipality_id'] ?? current_municipality_id()));
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
$orgLabel = $terms['short_name'] ?? 'Φορέας';
$evStatus  = $event['status'];
$evStarted = !empty($event['start_datetime']) && strtotime($event['start_datetime']) <= time();
$isActive  = $evStatus === 'active' || ($evStarted && in_array($evStatus, ['open', 'confirmed', 'review']));

/* Map coordinates: event location + team's last GPS ping */
$evLat = (isset($event['latitude'])  && $event['latitude']  !== null && $event['latitude']  !== '') ? (float) $event['latitude']  : null;
$evLng = (isset($event['longitude']) && $event['longitude'] !== null && $event['longitude'] !== '') ? (float) $event['longitude'] : null;
$tLat  = ($lastPing && $lastPing['latitude']  !== null) ? (float) $lastPing['latitude']  : null;
$tLng  = ($lastPing && $lastPing['longitude'] !== null) ? (float) $lastPing['longitude'] : null;

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
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
.presence-btns form { width: 100%; }
.pres-btn {
  width: 100%; padding: 18px 8px; border: none; border-radius: 16px;
  font-size: 13px; font-weight: 700;
  cursor: pointer; text-align: center; line-height: 1.3;
  transition: transform .1s, opacity .1s;
  -webkit-tap-highlight-color: transparent;
  display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.pres-btn i { font-size: 28px; }
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
  width: 100%; padding: 22px 20px; border: none; border-radius: 18px;
  background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%); color: #fff; cursor: pointer;
  display: flex; align-items: center; gap: 16px; text-align: left;
  transition: transform .1s;
}
.sos-btn:active { transform: scale(.98); }
.sos-btn:disabled { opacity: .45; cursor: not-allowed; }
.sos-btn-icon { font-size: 36px; flex-shrink: 0; }
.sos-btn-label { font-size: 20px; font-weight: 900; letter-spacing: .04em; }
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
  width: 100%; padding: 18px 12px; border: none; border-radius: 16px; cursor: pointer;
  background: #13302e; color: #7fe6d8; font-size: 14px; font-weight: 700;
  display: flex; align-items: center; gap: 10px; text-align: left; transition: transform .1s;
}
.ping-btn:active { transform: scale(.96); }
.ping-btn:disabled { opacity: .35; cursor: not-allowed; }
.ping-btn i { font-size: 24px; flex-shrink: 0; }
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

/* ── Resource request card (Smart Resource Dispatch, Φάση 2) ─────────────── */
.resource-pin { background: linear-gradient(135deg, #134e4a 0%, #0f766e 100%); border: 1px solid #2dd4bf; border-radius: 16px; padding: 16px; margin-bottom: 12px; }
.resource-pin-head { font-weight: 900; color: #99f6e4; font-size: 14px; letter-spacing: .03em; display: flex; align-items: center; gap: 6px; }
.resource-pin-body { color: #fff; font-size: 17px; font-weight: 700; margin: 8px 0 12px; line-height: 1.35; }
.resource-pin-input { width: 100%; margin-bottom: 8px; padding: 11px 12px; border-radius: 10px; border: 1px solid #14b8a6; background: #042f2e; color: #ccfbf1; font-size: 14px; }
.resource-pin-input::placeholder { color: #5eead4; opacity: .7; }
.resource-accept-btn { background: #2dd4bf; color: #042f2e; border: none; border-radius: 12px; font-weight: 800; font-size: 15px; padding: 14px 16px; width: 100%; cursor: pointer; }
.resource-accept-btn:active { transform: scale(.98); }
.resource-decline-btn { background: transparent; color: #99f6e4; border: 1px solid #5eead4; border-radius: 12px; font-weight: 700; font-size: 14px; padding: 11px 16px; width: 100%; cursor: pointer; margin-top: 8px; }
.resource-pin-time { font-size: 11px; color: #99f6e4; opacity: .8; margin-top: 8px; text-align: right; }

/* ── Request banners (GPS / photo) ───────────────────────────────────────── */
.req-banner {
  margin: 0 0 0; padding: 10px 16px;
  display: flex; align-items: center; gap: 10px;
  font-size: 13px; font-weight: 700; color: #67e8f9;
  background: #0e3047; border-top: 1px solid #164e63;
  animation: reqPulse 2s infinite;
}
.req-banner i { font-size: 18px; flex-shrink: 0; }
@keyframes reqPulse {
  0%, 100% { background: #0e3047; }
  50%       { background: #0c2a40; }
}
/* Photo upload card */
.photo-toggle {
  width: 100%; padding: 22px 20px; border: none; border-radius: 18px;
  background: linear-gradient(135deg, #0e2d4d 0%, #0a2035 100%);
  color: #67e8f9; cursor: pointer;
  display: flex; align-items: center; gap: 16px; text-align: left;
  transition: background .15s;
  -webkit-tap-highlight-color: transparent;
}
.photo-toggle.has-request { background: linear-gradient(135deg, #0e4d3d 0%, #0a2e26 100%); color: #4ade80; border: 1px solid #166534; animation: sosPulse 2s infinite; }
.photo-toggle:active { transform: scale(.98); }
.photo-toggle:disabled { opacity: .35; cursor: not-allowed; }
.photo-toggle-icon { font-size: 36px; flex-shrink: 0; }
.photo-toggle-label { font-size: 18px; font-weight: 800; color: #e8f5f4; }
.photo-toggle-sub { font-size: 12px; color: #67e8f9; margin-top: 2px; }
.photo-toggle.has-request .photo-toggle-sub { color: #86efac; }
.photo-toggle-arrow { margin-left: auto; font-size: 20px; opacity: .5; transition: transform .2s; }
.photo-toggle.open .photo-toggle-arrow { transform: rotate(90deg); }
#photoUploadForm {
  display: none;
  border-top: 1px solid #1e3333;
}
#photoUploadForm.show { display: block; }
.photo-form-inner { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.photo-file-input {
  background: #0d1a1a; border: 2px dashed #1e4d4d; border-radius: 12px;
  color: #e8f5f4; font-size: 14px; padding: 20px; width: 100%;
  text-align: center; cursor: pointer;
}
.photo-file-input:focus { border-color: #4dd4c4; outline: none; }
.photo-submit {
  background: #0e7490; color: #fff; border: none; border-radius: 10px;
  font-size: 15px; font-weight: 800; padding: 16px;
  cursor: pointer; width: 100%;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s;
}
.photo-submit:active { background: #0c5f78; }
.photo-submit:disabled { opacity: .45; cursor: not-allowed; }
</style>
</head>
<body>

<!-- ── Top bar ─────────────────────────────────────────────────────────── -->
<div class="topbar">
  <a href="<?= e(url('/team/operations/events/' . $eid)) ?>" class="topbar-back">
    <i class="bi bi-chevron-left"></i> <?= e(t('team/live.001', 'Επιστροφή')) ?>
  </a>
  <div class="topbar-event">
    <div class="topbar-event-name"><?= e($event['title']) ?></div>
    <div class="topbar-event-time">
      <?= e(gr_time($event['start_datetime'])) ?>–<?= e(gr_time($event['end_datetime'])) ?>
      <?php if ($event['location_name']): ?> · <?= e($event['location_name']) ?><?php endif; ?>
    </div>
  </div>
  <button class="topbar-refresh" onclick="window.location.reload()" title="<?= e(t('team/live.056', 'Ανανέωση')) ?>">
    <i class="bi bi-arrow-clockwise" id="refreshIcon"></i>
  </button>
</div>

<?php
/* Flash messages */
foreach (flash_get() as $flash):
  $ftype = $flash['type'] ?? 'success';
  $ficon = match($ftype) { 'success' => 'bi-check-circle-fill', 'danger' => 'bi-x-circle-fill', default => 'bi-exclamation-triangle-fill' };
?>
<div class="flash flash-<?= e($ftype) ?>">
  <i class="bi <?= e($ficon) ?>"></i>
  <?= e($flash['message']) ?>
</div>
<?php endforeach; ?>

<!-- ── Inactive notice ─────────────────────────────────────────────────── -->
<?php if (!$isActive): ?>
<div class="inactive-notice">
  <i class="bi bi-hourglass-split"></i>
  Η <?= e($eventSingularLc) ?> <?= e(t('team/live.063', 'δεν είναι ακόμη ενεργή. Οι επιχειρησιακές ενέργειες ενεργοποιούνται όταν')) ?> <?= e($orgLabel) ?> <?= e(t('team/live.064', 'την ξεκινήσει.')) ?>
</div>
<?php endif; ?>

<!-- ── Status banner ───────────────────────────────────────────────────── -->
<div class="status-banner <?= e($bannerClass) ?>">
  <i class="bi <?= e($bannerIcon) ?>"></i>
  <div>
    <div><?= e($bannerLabel) ?></div>
    <?php if ($lastCheckin): ?>
      <div class="banner-meta"><?= e(t('team/live.003', 'Ενημέρωση:')) ?> <?= e(gr_time($lastCheckin['checked_in_at'])) ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="hub-body">

  <!-- Pinned ΕΝΤΟΛΕΣ (unacknowledged orders) -->
  <div id="orderBanner" style="display:none"></div>

  <!-- Resource dispatch requests (Smart Resource Dispatch, Φάση 2) -->
  <div id="resourceBox" style="display:none"></div>

  <!-- ── 0. SOS / Έκτακτη Ανάγκη ────────────────────────────────────── -->
  <div>
    <button class="sos-btn" id="sosBtn" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-exclamation-octagon-fill sos-btn-icon"></i>
      <div style="flex:1">
        <div class="sos-btn-label"><?= e(t('team/live.004', 'SOS — ΚΙΝΔΥΝΟΣ')) ?></div>
        <div class="sos-btn-sub"><?= e(t('team/live.005', 'Πατήστε για άμεση κλήση βοήθειας προς')) ?> <?= e($orgLabel) ?></div>
      </div>
    </button>
    <div class="sos-active-banner" id="sosBanner" style="display:none"></div>
  </div>

  <!-- ── 1. Αποστολή Στίγματος ──────────────────────────────────────── -->
  <div class="action-card">
    <button class="loc-btn" id="locBtn" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-broadcast loc-btn-icon"></i>
      <div>
        <div class="loc-btn-label"><?= e(t('team/live.006', 'Αποστολή Στίγματος')) ?></div>
        <div class="loc-btn-sub">
          <?php if ($lastPing): ?>
            <?= e(t('team/live.065', 'Τελευταίο:')) ?> <?= e(gr_time($lastPing['created_at'])) ?>
          <?php else: ?>
            <?= e(t('team/live.066', 'Στείλτε τη θέση σας προς')) ?> <?= e($orgLabel) ?>
          <?php endif; ?>
        </div>
      </div>
      <i class="bi bi-chevron-right loc-btn-arrow"></i>
    </button>
    <div id="gpsBanner" style="display:none" class="req-banner">
      <i class="bi bi-geo-alt-fill"></i> <?= e($orgLabel) ?> <?= e(t('team/live.008', 'ζητά το στίγμα GPS σας — πατήστε «Αποστολή Στίγματος»')) ?>
    </div>
    <div id="locResult"></div>
  </div>

  <!-- ── 1b. Αποστολή Φωτογραφίας ────────────────────────────────── -->
  <div class="action-card" id="photoCard">
    <button class="photo-toggle" id="photoToggle" onclick="togglePhoto()" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-camera-fill photo-toggle-icon"></i>
      <div>
        <div class="photo-toggle-label"><?= e(t('team/live.009', 'Αποστολή Φωτογραφίας')) ?></div>
        <div class="photo-toggle-sub" id="photoToggleSub"><?= e(t('team/live.010', 'Στείλτε φωτογραφία προς')) ?> <?= e($orgLabel) ?></div>
      </div>
      <i class="bi bi-chevron-right photo-toggle-arrow" id="photoArrow"></i>
    </button>
    <div id="photoBanner" style="display:none" class="req-banner">
      <i class="bi bi-camera-fill"></i> <?= e($orgLabel) ?> <?= e(t('team/live.011', 'ζητά φωτογραφία — τραβήξτε ή επιλέξτε μία παρακάτω')) ?>
    </div>
    <div id="photoUploadForm">
      <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/photo')) ?>"
            enctype="multipart/form-data" class="photo-form-inner" id="photoFormEl">
        <?= csrf_field() ?>
        <input type="hidden" name="_from" value="live">
        <input type="hidden" name="latitude"   id="photoLat">
        <input type="hidden" name="longitude"  id="photoLng">
        <input type="hidden" name="request_id" id="photoRequestId">
        <div class="form-group">
          <label class="form-group" style="font-size:12px;color:#9ca3af;font-weight:600"><?= e(t('team/live.012', 'Φωτογραφία *')) ?></label>
          <input type="file" name="photo" accept="image/*" capture="environment"
                 class="photo-file-input" id="photoFile" required>
        </div>
        <div class="form-group">
          <label style="font-size:12px;color:#9ca3af;font-weight:600"><?= e(t('team/live.013', 'Λεζάντα (προαιρετικό)')) ?></label>
          <input type="text" name="caption" class="form-input" placeholder="<?= e(t('team/live.057', 'π.χ. Κατάσταση στο σημείο…')) ?>" maxlength="200">
        </div>
        <button type="button" class="photo-submit" id="photoSubmitBtn" onclick="submitPhoto()">
          <i class="bi bi-camera-fill"></i> <?= e(t('team/live.009', 'Αποστολή Φωτογραφίας')) ?>
        </button>
      </form>
    </div>
  </div>

  <div class="action-card" id="videoCard">
    <button class="photo-toggle" id="videoToggle" onclick="toggleVideo()" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-camera-video-fill photo-toggle-icon"></i>
      <div>
        <div class="photo-toggle-label"><?= e(t('team/live.014', 'Αποστολή Βίντεο')) ?></div>
        <div class="photo-toggle-sub" id="videoToggleSub"><?= e(t('team/live.015', 'Στείλτε σύντομο βίντεο προς')) ?> <?= e($orgLabel) ?></div>
      </div>
      <i class="bi bi-chevron-right photo-toggle-arrow" id="videoArrow"></i>
    </button>
    <div id="videoBanner" style="display:none" class="req-banner">
      <i class="bi bi-camera-video-fill"></i> <span id="videoBannerText"><?= e($orgLabel) ?> <?= e(t('team/live.016', 'ζητά βίντεο — τραβήξτε ή επιλέξτε ένα παρακάτω')) ?></span>
    </div>
    <div id="videoUploadForm">
      <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/video')) ?>"
            enctype="multipart/form-data" class="photo-form-inner" id="videoFormEl">
        <?= csrf_field() ?>
        <input type="hidden" name="_from" value="live">
        <input type="hidden" name="latitude"   id="videoLat">
        <input type="hidden" name="longitude"  id="videoLng">
        <input type="hidden" name="request_id" id="videoRequestId">
        <div class="form-group">
          <label class="form-group" style="font-size:12px;color:#9ca3af;font-weight:600"><?= e(t('team/live.017', 'Βίντεο * (30–40\'\')')) ?></label>
          <input type="file" name="video" accept="video/*" capture="environment"
                 class="photo-file-input" id="videoFile" required>
        </div>
        <div class="form-group">
          <label style="font-size:12px;color:#9ca3af;font-weight:600"><?= e(t('team/live.013', 'Λεζάντα (προαιρετικό)')) ?></label>
          <input type="text" name="caption" class="form-input" placeholder="<?= e(t('team/live.057', 'π.χ. Κατάσταση στο σημείο…')) ?>" maxlength="200">
        </div>
        <button type="button" class="photo-submit" id="videoSubmitBtn" onclick="submitVideo()">
          <i class="bi bi-camera-video-fill"></i> <?= e(t('team/live.014', 'Αποστολή Βίντεο')) ?>
        </button>
      </form>
    </div>
  </div>

  <!-- ── Χάρτης ──────────────────────────────────────────────────────── -->
  <div class="action-card">
    <div class="presence-header"><i class="bi bi-map"></i> <?= e(t('team/live.018', 'Χάρτης')) ?> <?= e($eventSingular) ?>
      <?php if ($lastPing): ?><span style="font-size:12px;font-weight:400;color:#7ab5ae;margin-left:auto"><?= e(t('team/live.019', 'Στίγμα:')) ?> <?= e(gr_time($lastPing['created_at'])) ?></span><?php endif; ?>
    </div>
    <div id="teamMap" style="height:240px;background:#0a1414"></div>
  </div>

  <!-- ── 2. Δήλωση Παρουσίας ────────────────────────────────────────── -->
  <div class="action-card">
    <div class="presence-header">
      <i class="bi bi-person-check"></i>
      <?= e(t('team/live.020', 'Δήλωση Παρουσίας')) ?>
      <span style="font-size:12px;font-weight:400;color:#7ab5ae;margin-left:auto"><?= $approved ?> <?= e(t('team/live.021', 'εγκεκριμένα')) ?></span>
    </div>
    <div class="presence-btns">
      <!-- Full -->
      <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/checkin')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="present_full">
        <input type="hidden" name="_from" value="live">
        <button type="submit" class="pres-btn pres-btn-full" <?= !$isActive ? 'disabled' : '' ?>>
          <i class="bi bi-check2-all"></i>
          <?= e(t('team/live.022', 'Πλήρης')) ?><br><span style="font-size:10px;font-weight:400"><?= $approved ?> <?= e(t('team/live.023', 'άτομα')) ?></span>
        </button>
      </form>
      <!-- Partial -->
      <button type="button" class="pres-btn pres-btn-partial" id="partialToggle"
              onclick="togglePartial()" <?= !$isActive ? 'disabled' : '' ?>>
        <i class="bi bi-check2"></i>
        <?= e(t('team/live.024', 'Μερική')) ?><br><span style="font-size:10px;font-weight:400"><?= e(t('team/live.025', 'λιγότερα άτομα')) ?></span>
      </button>
      <!-- Departed -->
      <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/checkin')) ?>"
            onsubmit="return confirm(<?= e(json_encode('Αποχώρηση ομάδας από τη ' . $eventSingularLc . ';', JSON_UNESCAPED_UNICODE)) ?>)">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="departed">
        <input type="hidden" name="_from" value="live">
        <button type="submit" class="pres-btn pres-btn-departed" <?= !$isActive ? 'disabled' : '' ?>>
          <i class="bi bi-box-arrow-right"></i>
          <?= e(t('team/live.026', 'Αποχώρηση')) ?>
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
            <i class="bi bi-check-lg"></i> <?= e(t('team/live.027', 'Δήλωση')) ?>
          </button>
        </div>
        <div class="partial-hint"><?= e(t('team/live.067', 'Εγκεκριμένα:')) ?> <?= $approved ?> <?= e(t('team/live.023', 'άτομα')) ?></div>
      </form>
    </div>
  </div>

  <!-- ── 3. Αναφορά Έλλειψης ────────────────────────────────────────── -->
  <div class="action-card">
    <button class="shortage-toggle" id="shortageToggle"
            onclick="toggleShortage()" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-exclamation-triangle shortage-toggle-icon"></i>
      <div>
        <div class="shortage-toggle-label"><?= e(t('team/live.029', 'Αναφορά Έλλειψης')) ?></div>
        <div class="shortage-toggle-sub"><?= e(t('team/live.030', 'Ειδοποιήστε άμεσα')) ?> <?= e($orgLabel) ?></div>
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
          <label><?= e(t('team/live.031', 'Τύπος έλλειψης')) ?></label>
          <select name="shortage_type" class="form-select" required>
            <option value="people"><?= e(t('team/live.032', '👥 Άτομα')) ?></option>
            <option value="equipment"><?= e(t('team/live.033', '🔧 Εξοπλισμός')) ?></option>
            <option value="medical_supplies"><?= e(t('team/live.034', '🏥 Υγειονομικό υλικό')) ?></option>
            <option value="vehicle"><?= e(t('team/live.035', '🚗 Όχημα')) ?></option>
            <option value="other"><?= e(t('team/live.036', '📋 Άλλο')) ?></option>
          </select>
        </div>

        <div class="form-group">
          <label><?= e(t('team/live.037', 'Σοβαρότητα')) ?></label>
          <div class="severity-grid">
            <button type="button" class="sev-btn sev-low" data-val="low" onclick="setSeverity('low')"><?= e(t('team/live.038', 'Χαμηλή')) ?></button>
            <button type="button" class="sev-btn sev-medium selected" data-val="medium" onclick="setSeverity('medium')"><?= e(t('team/live.039', 'Μεσαία')) ?></button>
            <button type="button" class="sev-btn sev-high" data-val="high" onclick="setSeverity('high')"><?= e(t('team/live.040', 'Υψηλή')) ?></button>
            <button type="button" class="sev-btn sev-critical" data-val="critical" onclick="setSeverity('critical')"><?= e(t('team/live.041', 'Κρίσιμη')) ?></button>
          </div>
        </div>

        <div class="form-group">
          <label><?= e(t('team/live.042', 'Σύντομος τίτλος *')) ?></label>
          <input type="text" name="title" class="form-input" required placeholder="<?= e(t('team/live.058', 'π.χ. Λείπουν 2 άτομα')) ?>">
        </div>

        <div class="form-group">
          <label><?= e(t('team/live.043', 'Περιγραφή (προαιρετικό)')) ?></label>
          <textarea name="description" class="form-textarea" rows="2" placeholder="<?= e(t('team/live.059', 'Επιπλέον λεπτομέρειες...')) ?>"></textarea>
        </div>

        <button type="submit" class="shortage-submit"
                onclick="return confirm(<?= e(json_encode('Αποστολή αναφοράς έλλειψης προς ' . $orgLabel . ';', JSON_UNESCAPED_UNICODE)) ?>)">
          <i class="bi bi-send-fill"></i> <?= e(t('team/live.044', 'Αποστολή Αναφοράς')) ?>
        </button>
      </form>
    </div>
  </div>

  <!-- ── Shortage history (live-updated by poll) ───────────────────────── -->
  <div id="shortageHistory">
    <?php if ($shortages): ?>
    <div class="section-title"><i class="bi bi-clock-history me-1"></i><?= e(t('team/live.045', 'Αναφορές μας σε αυτή τη')) ?> <?= e($eventSingularLc) ?></div>
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
    <?php endif; ?>
  </div>

  <!-- ── Γρήγορη ενημέρωση (status pings) ────────────────────────────── -->
  <div class="action-card">
    <div class="presence-header"><i class="bi bi-lightning-charge"></i> <?= e(t('team/live.046', 'Γρήγορη ενημέρωση')) ?></div>
    <div class="ping-grid">
      <button type="button" class="ping-btn" data-code="arrived" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-geo-alt-fill"></i> <?= e(t('team/live.047', 'Φτάσαμε στο σημείο')) ?></button>
      <button type="button" class="ping-btn" data-code="task_complete" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i> <?= e(t('team/live.048', 'Ολοκληρώθηκε')) ?></button>
      <button type="button" class="ping-btn" data-code="need_backup" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-people-fill"></i> <?= e(t('team/live.049', 'Χρειαζόμαστε ενίσχυση')) ?></button>
      <button type="button" class="ping-btn" data-code="returning" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-box-arrow-left"></i> <?= e(t('team/live.050', 'Επιστροφή στη βάση')) ?></button>
      <button type="button" class="ping-btn full" data-code="incident" <?= !$isActive ? 'disabled' : '' ?>><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('team/live.051', 'Έχουμε περιστατικό')) ?></button>
    </div>
  </div>

  <!-- ── Επικοινωνία με τον φορέα ──────────────────────────────────────── -->
  <div class="action-card">
    <div class="presence-header"><i class="bi bi-chat-dots"></i> <?= e(t('team/live.052', 'Επικοινωνία ·')) ?> <?= e($orgLabel) ?></div>
    <div class="msg-list" id="msgList"><div class="msg-empty"><?= e(t('team/live.053', 'Φόρτωση…')) ?></div></div>
    <div class="msg-compose">
      <input type="text" id="msgInput" placeholder="Μήνυμα προς <?= e($orgLabel) ?>…" maxlength="500">
      <button type="button" id="msgSend" title="<?= e(t('team/live.061', 'Αποστολή')) ?>"><i class="bi bi-send"></i></button>
    </div>
  </div>

  <!-- ── Δωμάτιο Επιχείρησης (κοινό κανάλι) ──────────────────────────── -->
  <div class="action-card">
    <div class="presence-header"><i class="bi bi-broadcast-pin"></i> <?= e(t('team/live.054', 'Δωμάτιο Επιχείρησης')) ?></div>
    <div class="msg-list" id="roomList"><div class="msg-empty"><?= e(t('team/live.053', 'Φόρτωση…')) ?></div></div>
    <div class="msg-compose">
      <input type="text" id="roomInput" placeholder="<?= e(t('team/live.062', 'Μήνυμα προς όλους…')) ?>" maxlength="500">
      <button type="button" id="roomSend" title="<?= e(t('team/live.061', 'Αποστολή')) ?>"><i class="bi bi-send"></i></button>
    </div>
  </div>

  <div id="wakeLockIndicator"><i class="bi bi-phone"></i> <?= e(t('team/live.055', 'Η οθόνη παραμένει ενεργή')) ?></div>
</div><!-- .hub-body -->

<script>
(function () {
  'use strict';
  var BASE = window.baseUrl || '';
  var EID  = <?= $eid ?>;
  var CSRF = window.csrfToken || '';
  var IS_ACTIVE = <?= $isActive ? 'true' : 'false' ?>;
  var ORG_LABEL = <?= json_encode($orgLabel, JSON_UNESCAPED_UNICODE) ?>;
  var EVENT_LC = <?= json_encode($eventSingularLc, JSON_UNESCAPED_UNICODE) ?>;

  function postJSON(path, body) {
    return fetch(BASE + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json(); });
  }
  function escapeHtml(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

  /* ── Map (event point + our last ping) ── */
  (function initMap(){
    var el = document.getElementById('teamMap');
    if (!el || typeof L === 'undefined') return;
    var evLat = <?= $evLat !== null ? $evLat : 'null' ?>, evLng = <?= $evLng !== null ? $evLng : 'null' ?>;
    var tLat  = <?= $tLat  !== null ? $tLat  : 'null' ?>, tLng  = <?= $tLng  !== null ? $tLng  : 'null' ?>;
    var center = (tLat !== null) ? [tLat, tLng] : ((evLat !== null) ? [evLat, evLng] : [35.3387, 25.1442]);
    var map = L.map('teamMap').setView(center, 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
    var b = [];
    if (evLat !== null) { L.marker([evLat, evLng]).addTo(map).bindPopup('Σημείο ' + EVENT_LC); b.push([evLat, evLng]); }
    if (tLat  !== null) { L.circleMarker([tLat, tLng], { radius: 9, color: '#22d3ee', fillColor: '#22d3ee', fillOpacity: .85 }).addTo(map).bindPopup('Η θέση μας'); b.push([tLat, tLng]); }
    if (b.length > 1) { try { map.fitBounds(b, { padding: [30, 30], maxZoom: 16 }); } catch (e) {} }
    window.__teamMap = map;
    window.__teamGeo = L.layerGroup().addTo(map);
    setTimeout(function () { map.invalidateSize(); }, 250);
  })();

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
      if (!confirm('ΕΠΙΒΕΒΑΙΩΣΗ SOS\n\nΘα ειδοποιηθεί ΑΜΕΣΑ ' + ORG_LABEL + ' ότι κινδυνεύετε. Συνέχεια;')) return;
      sosBtn.disabled = true;
      sosBtn.querySelector('.sos-btn-sub').textContent = 'Λήψη τοποθεσίας…';
      var send = function (lat, lng, acc) {
        postJSON('/team/operations/events/' + EID + '/sos', { latitude: lat, longitude: lng, accuracy: acc })
          .then(function (d) {
            if (d && d.success) { sosBtn.querySelector('.sos-btn-sub').textContent = 'SOS εστάλη — ειδοποιήθηκε ' + ORG_LABEL; pollComms(); }
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
      var who = m.sender_role === 'command' ? ORG_LABEL : (m.sender_name || 'Ομάδα');
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
        if (IS_ACTIVE) sosBtn.querySelector('.sos-btn-sub').textContent = 'Πατήστε για άμεση κλήση βοήθειας προς ' + ORG_LABEL; }
      return;
    }
    sosBanner.style.display = 'flex';
    if (sos.status === 'acknowledged') {
      sosBanner.className = 'sos-active-banner ack';
      sosBanner.innerHTML = '<i class="bi bi-check2-all"></i> Το SOS ελήφθη από ' + escapeHtml(ORG_LABEL) +
        (sos.ack_name ? ' (' + escapeHtml(sos.ack_name) + ')' : '') + ' — έρχεται βοήθεια.';
      if (sosBtn) sosBtn.classList.remove('sos-pulse');
    } else {
      sosBanner.className = 'sos-active-banner';
      sosBanner.innerHTML = '<i class="bi bi-broadcast-pin"></i> SOS ΕΝΕΡΓΟ — αναμονή επιβεβαίωσης από ' + escapeHtml(ORG_LABEL) + '…';
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
      var head = m.point_kind === 'incident' ? '⚠️ ΠΕΡΙΣΤΑΤΙΚΟ' : (m.point_kind === 'move' ? '➡️ ΜΕΤΑΒΑΣΗ ΣΕ ΣΗΜΕΙΟ' : 'ΕΝΤΟΛΗ ΑΠΟ ' + ORG_LABEL);
      var dir = (m.latitude != null && m.longitude != null)
        ? '<a href="https://www.google.com/maps?q=' + m.latitude + ',' + m.longitude + '" target="_blank" rel="noopener" class="order-pin-btn" style="display:block;text-align:center;text-decoration:none;background:#2563eb;color:#fff;margin-bottom:8px"><i class="bi bi-geo-alt-fill"></i> Οδηγίες (Google Maps)</a>' : '';
      return '<div class="order-pin">' +
        '<div class="order-pin-head"><i class="bi bi-megaphone-fill"></i> ' + head + '</div>' +
        '<div class="order-pin-body">' + escapeHtml(m.body || '') + '</div>' + dir +
        '<button type="button" class="order-pin-btn" onclick="ackOrder(' + m.id + ')"><i class="bi bi-check2-all"></i> Επιβεβαίωση λήψης</button>' +
        '<div class="order-pin-time">' + t + '</div></div>';
    }).join('');
  }

  function renderGeoPoints(msgs) {
    var map = window.__teamMap, grp = window.__teamGeo;
    if (!map || !grp) return;
    grp.clearLayers();
    (msgs || []).forEach(function (m) {
      if (m.latitude == null || m.longitude == null || !m.point_kind) return;
      var color = m.point_kind === 'incident' ? '#dc2626' : (m.point_kind === 'move' ? '#2563eb' : '#0d9488');
      var lbl   = m.point_kind === 'incident' ? '⚠️ Περιστατικό' : (m.point_kind === 'move' ? '➡️ Μετάβαση' : '📍 Σημείο');
      L.circleMarker([m.latitude, m.longitude], { radius: 10, color: color, fillColor: color, fillOpacity: .7 }).addTo(grp)
        .bindPopup('<b>' + lbl + '</b><br>' + escapeHtml(m.body || '') + '<br><a href="https://www.google.com/maps?q=' + m.latitude + ',' + m.longitude + '" target="_blank" rel="noopener">Οδηγίες</a>');
    });
  }

  /* ── Resource dispatch requests (Φάση 2) ────────────────────────── */
  var resourceBoxEl = document.getElementById('resourceBox');
  var lastRrIds = null;
  function renderResourceRequests(list) {
    if (!resourceBoxEl) return;
    list = list || [];
    var ids = list.map(function (r) { return r.id; }).join(',');
    if (ids === lastRrIds) return; /* don't clobber ETA/note inputs while typing */
    lastRrIds = ids;
    if (!list.length) { resourceBoxEl.innerHTML = ''; resourceBoxEl.style.display = 'none'; return; }
    resourceBoxEl.style.display = '';
    resourceBoxEl.innerHTML = list.map(function (r) {
      var t = (r.created_at || '').substr(11, 5);
      return '<div class="resource-pin">' +
        '<div class="resource-pin-head"><i class="bi bi-box-seam"></i> ΑΙΤΗΜΑ ΠΟΡΟΥ ΑΠΟ ' + escapeHtml(ORG_LABEL) + '</div>' +
        '<div class="resource-pin-body">' + escapeHtml(r.item_label || '') + '</div>' +
        '<input type="number" class="resource-pin-input" id="rrEta' + r.id + '" min="1" max="1440" inputmode="numeric" placeholder="ETA σε λεπτά (προαιρετικό)">' +
        '<input type="text" class="resource-pin-input" id="rrNote' + r.id + '" maxlength="255" placeholder="Σχόλιο (προαιρετικό)">' +
        '<button type="button" class="resource-accept-btn" onclick="respondResource(' + r.id + ',\'accept\')"><i class="bi bi-check2-circle"></i> Αποδοχή — το διαθέτουμε</button>' +
        '<button type="button" class="resource-decline-btn" onclick="respondResource(' + r.id + ',\'decline\')">Αδυναμία διάθεσης</button>' +
        '<div class="resource-pin-time">' + t + '</div></div>';
    }).join('');
  }
  window.respondResource = function (id, action) {
    var eta  = document.getElementById('rrEta' + id);
    var note = document.getElementById('rrNote' + id);
    var body = { action: action };
    if (action === 'accept' && eta && eta.value) { body.eta_minutes = parseInt(eta.value, 10); }
    if (note && note.value.trim()) { body.note = note.value.trim(); }
    postJSON('/team/resource-requests/' + id + '/respond', body).then(function (d) {
      if (d && !d.success && d.message) { alert(d.message); }
      lastRrIds = null; /* force re-render on next poll */
      pollComms();
    }).catch(function () {});
  };

  /* ── GPS / Photo request banners ────────────────────────────────── */
  var gpsBannerEl  = document.getElementById('gpsBanner');
  var photoBannerEl = document.getElementById('photoBanner');
  var photoToggleEl = document.getElementById('photoToggle');
  var photoToggleSubEl = document.getElementById('photoToggleSub');

  function renderRequests(photoReq, gpsReq) {
    // GPS request banner
    if (gpsBannerEl) gpsBannerEl.style.display = gpsReq ? 'flex' : 'none';

    // Photo request banner + card highlight
    var hasPhoto = !!(photoReq && photoReq.id);
    if (photoBannerEl) photoBannerEl.style.display = hasPhoto ? 'flex' : 'none';
    if (photoToggleEl) {
      photoToggleEl.classList.toggle('has-request', hasPhoto);
      if (photoToggleSubEl) {
        photoToggleSubEl.textContent = hasPhoto
          ? '⚡ ' + ORG_LABEL + ' ζητά φωτογραφία — πατήστε για να ανοίξει'
          : 'Στείλτε φωτογραφία προς ' + ORG_LABEL;
      }
    }
    // Store request id for the hidden form field
    var ridEl = document.getElementById('photoRequestId');
    if (ridEl) ridEl.value = (photoReq && photoReq.id) ? photoReq.id : '';
    // Auto-open photo form if there's a new request and it's not already open
    if (hasPhoto) {
      var form = document.getElementById('photoUploadForm');
      if (form && !form.classList.contains('show')) {
        form.classList.add('show');
        if (photoToggleEl) photoToggleEl.classList.add('open');
      }
    }
  }

  window.togglePhoto = function () {
    var f   = document.getElementById('photoUploadForm');
    var tog = document.getElementById('photoToggle');
    var arr = document.getElementById('photoArrow');
    f.classList.toggle('show');
    tog.classList.toggle('open');
  };

  window.submitPhoto = function () {
    var fileEl = document.getElementById('photoFile');
    if (!fileEl || !fileEl.files || !fileEl.files.length) {
      alert('Επιλέξτε φωτογραφία πρώτα.');
      return;
    }
    var btn = document.getElementById('photoSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Λήψη GPS…';

    function doSubmit(lat, lng) {
      if (lat !== null) document.getElementById('photoLat').value = lat;
      if (lng !== null) document.getElementById('photoLng').value = lng;
      btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Αποστολή…';
      document.getElementById('photoFormEl').submit();
    }

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function (p) { doSubmit(p.coords.latitude, p.coords.longitude); },
        function ()  { doSubmit(null, null); },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
      );
    } else {
      doSubmit(null, null);
    }
  };

  window.toggleVideo = function () {
    document.getElementById('videoUploadForm').classList.toggle('show');
    document.getElementById('videoToggle').classList.toggle('open');
  };

  window.submitVideo = function () {
    var fileEl = document.getElementById('videoFile');
    if (!fileEl || !fileEl.files || !fileEl.files.length) { alert('Επιλέξτε ή τραβήξτε βίντεο πρώτα.'); return; }
    var btn = document.getElementById('videoSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Λήψη GPS…';
    function doSubmit(lat, lng) {
      if (lat !== null) document.getElementById('videoLat').value = lat;
      if (lng !== null) document.getElementById('videoLng').value = lng;
      btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Αποστολή…';
      document.getElementById('videoFormEl').submit();
    }
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function (p) { doSubmit(p.coords.latitude, p.coords.longitude); },
        function ()  { doSubmit(null, null); },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
      );
    } else { doSubmit(null, null); }
  };

  var videoBannerEl = document.getElementById('videoBanner');
  var videoToggleEl = document.getElementById('videoToggle');
  var videoToggleSubEl = document.getElementById('videoToggleSub');
  function renderVideoRequest(videoReq) {
    var has = !!(videoReq && videoReq.id);
    if (videoBannerEl) {
      videoBannerEl.style.display = has ? 'flex' : 'none';
      var t = document.getElementById('videoBannerText');
      if (has && videoReq.instructions && t) { t.textContent = ORG_LABEL + ' ζητά βίντεο: ' + videoReq.instructions; }
    }
    if (videoToggleEl) {
      videoToggleEl.classList.toggle('has-request', has);
      if (videoToggleSubEl) videoToggleSubEl.textContent = has ? '⚡ ' + ORG_LABEL + ' ζητά βίντεο — πατήστε για να ανοίξει' : 'Στείλτε σύντομο βίντεο προς ' + ORG_LABEL;
    }
    var ridEl = document.getElementById('videoRequestId');
    if (ridEl) ridEl.value = has ? videoReq.id : '';
    if (has) {
      var form = document.getElementById('videoUploadForm');
      if (form && !form.classList.contains('show')) { form.classList.add('show'); if (videoToggleEl) videoToggleEl.classList.add('open'); }
    }
  }

  var shortHistEl = document.getElementById('shortageHistory');
  function renderShortages(list){
    if (!shortHistEl) return;
    list = list || [];
    if (!list.length) { shortHistEl.innerHTML = ''; return; }
    shortHistEl.innerHTML = '<div class="section-title"><i class="bi bi-clock-history me-1"></i>Αναφορές μας σε αυτή τη ' + escapeHtml(EVENT_LC) + '</div>' +
      list.map(function(sh){
        return '<div class="shortage-item"><div class="shortage-row">' +
          '<span class="badge ' + sh.severity_class + '">' + escapeHtml(sh.severity_label) + '</span>' +
          '<span class="shortage-title">' + escapeHtml(sh.title) + '</span>' +
          '<span class="badge ' + sh.status_class + '">' + escapeHtml(sh.status_label) + '</span>' +
          '</div><div class="shortage-meta">' + escapeHtml(sh.type_label) + ' · ' + escapeHtml(sh.time) + '</div></div>';
      }).join('');
  }

  function pollComms() {
    fetch(BASE + '/team/operations/events/' + EID + '/comms?since=0', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.success) {
          renderMsgs(d.messages);
          renderSos(d.sos);
          renderOrders(d.messages);
          renderGeoPoints(d.messages);
          renderRoom(d.room);
          renderRequests(d.photo_request, d.gps_request);
          renderVideoRequest(d.video_request);
          renderResourceRequests(d.resource_requests);
          renderShortages(d.shortages);
        }
      })
      .catch(function () {});
  }

  /* Δωμάτιο Επιχείρησης */
  var roomListEl = document.getElementById('roomList');
  function renderRoom(msgs) {
    if (!roomListEl) return;
    if (!msgs || !msgs.length) { roomListEl.innerHTML = '<div class="msg-empty">Κανένα μήνυμα ακόμη.</div>'; return; }
    roomListEl.innerHTML = msgs.map(function (m) {
      var cmd = m.sender_role === 'command';
      var who = cmd ? ORG_LABEL : (m.sender_label || m.team_name || m.sender_name || 'Ομάδα');
      var t = (m.created_at || '').substr(11, 5);
      return '<div class="msg ' + (cmd ? 'msg-command' : 'msg-team') + '"><div>' + escapeHtml(m.body || '') +
             '</div><div class="msg-time">' + escapeHtml(who) + ' · ' + t + '</div></div>';
    }).join('');
    roomListEl.scrollTop = roomListEl.scrollHeight;
  }
  (function () {
    var s = document.getElementById('roomSend'), i = document.getElementById('roomInput');
    function send() { var b = (i.value || '').trim(); if (!b) return; i.value = ''; postJSON('/team/operations/events/' + EID + '/room', { body: b }).then(pollComms); }
    if (s) s.addEventListener('click', send);
    if (i) i.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); send(); } });
  })();

  pollComms();
  setInterval(pollComms, 5000);

})();
</script>
</body>
</html>
