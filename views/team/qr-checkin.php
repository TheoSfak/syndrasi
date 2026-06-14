<?php
/**
 * QR Check-in — /team/qr-checkin/{id}
 * Standalone one-tap presence page reached by scanning the operator's Gate QR.
 * Optional alternative to the Mobile Action Hub; posts to the same checkin endpoint.
 */
$eid      = (int) $event['id'];
$approved = (int) $application['approved_people'];
$isActive = ($event['status'] === 'active');

$checkinStatus = $lastCheckin ? $lastCheckin['status'] : null;
$checkinPeople = $lastCheckin ? (int) $lastCheckin['present_people'] : 0;
$isPresent     = $checkinStatus && in_array($checkinStatus, ['present_full', 'present_partial'], true);
$isDeparted    = $checkinStatus === 'departed';
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle ?? 'QR Παρουσία') ?> · SynDrasi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body{background:#0b1120;color:#e2e8f0;min-height:100vh;}
  .qr-wrap{max-width:480px;margin:0 auto;padding:1.1rem 1rem 2.5rem;}
  .qr-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:1.1rem;}
  .qr-banner{border-radius:16px;padding:1rem;text-align:center;font-weight:800;margin-bottom:1rem;}
  .b-idle{background:#1f2937;color:#cbd5e1;border:1px solid #374151;}
  .b-present{background:#052e1b;color:#4ade80;border:1px solid #14532d;}
  .b-departed{background:#2a2a1a;color:#facc15;border:1px solid #713f12;}
  .pbtn{display:flex;align-items:center;justify-content:center;gap:.6rem;width:100%;border:none;border-radius:16px;padding:1.15rem;font-size:1.15rem;font-weight:800;margin-bottom:.7rem;}
  .pbtn:disabled{opacity:.45;}
  .pbtn-full{background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff;}
  .pbtn-partial{background:linear-gradient(135deg,#d97706,#f59e0b);color:#1a1300;}
  .pbtn-departed{background:#1e1e1e;color:#9ca3af;border:1px solid #374151;}
  .mut{color:#94a3b8;}
</style>
</head>
<body>
<div class="qr-wrap">

  <div class="text-center mb-3">
    <div class="mut small text-uppercase" style="letter-spacing:1px"><i class="bi bi-qr-code-scan me-1"></i>QR Παρουσία</div>
    <h1 class="h4 fw-bold mt-1 mb-0"><?= e($event['title']) ?></h1>
    <div class="mut small mt-1">
      Ομάδα: <strong><?= e($application['team_name'] ?? '—') ?></strong> · Εγκεκριμένα άτομα: <strong><?= $approved ?></strong>
    </div>
  </div>

  <?php if ($isPresent): ?>
    <div class="qr-banner b-present">
      <i class="bi bi-check-circle-fill me-1"></i>
      <?= $checkinStatus === 'present_full'
            ? 'ΠΑΡΩΝ ΜΕ ΟΛΗ ΤΗΝ ΟΜΑΔΑ · ' . $approved . ' άτομα'
            : 'ΠΑΡΩΝ · ' . $checkinPeople . '/' . $approved . ' άτομα' ?>
    </div>
  <?php elseif ($isDeparted): ?>
    <div class="qr-banner b-departed"><i class="bi bi-box-arrow-right me-1"></i>ΑΠΟΧΩΡΗΣΑΤΕ</div>
  <?php else: ?>
    <div class="qr-banner b-idle"><i class="bi bi-clock me-1"></i>Δεν έχετε δηλώσει παρουσία</div>
  <?php endif; ?>

  <?php if (!$isActive): ?>
    <div class="alert alert-warning text-center">Η δράση δεν είναι ενεργή αυτή τη στιγμή.</div>
  <?php endif; ?>

  <div class="qr-card">
    <!-- Παρών με όλη την ομάδα -->
    <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/checkin')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="present_full">
      <input type="hidden" name="_from" value="qr">
      <button type="submit" class="pbtn pbtn-full" <?= !$isActive ? 'disabled' : '' ?>>
        <i class="bi bi-check-circle-fill"></i> Παρών — όλη η ομάδα (<?= $approved ?>)
      </button>
    </form>

    <!-- Μερική παρουσία -->
    <button type="button" class="pbtn pbtn-partial" onclick="document.getElementById('qrPartial').classList.toggle('d-none')" <?= !$isActive ? 'disabled' : '' ?>>
      <i class="bi bi-people-fill"></i> Μερική παρουσία
    </button>
    <form id="qrPartial" class="d-none mb-2" method="post" action="<?= e(url('/team/operations/events/' . $eid . '/checkin')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="present_partial">
      <input type="hidden" name="_from" value="qr">
      <label class="form-label small mut">Αριθμός παρόντων ατόμων (1–<?= max(1, $approved - 1) ?>)</label>
      <div class="d-flex gap-2">
        <input type="number" name="present_people" class="form-control form-control-lg" min="1" max="<?= max(1, $approved - 1) ?>" required>
        <button type="submit" class="btn btn-warning btn-lg fw-bold" <?= !$isActive ? 'disabled' : '' ?>>OK</button>
      </div>
    </form>

    <!-- Αποχώρηση -->
    <form method="post" action="<?= e(url('/team/operations/events/' . $eid . '/checkin')) ?>"
          onsubmit="return confirm('Δήλωση αποχώρησης της ομάδας;')">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="departed">
      <input type="hidden" name="_from" value="qr">
      <button type="submit" class="pbtn pbtn-departed" <?= !$isActive ? 'disabled' : '' ?>>
        <i class="bi bi-box-arrow-right"></i> Αποχώρηση
      </button>
    </form>
  </div>

  <div class="text-center mt-3">
    <a href="<?= e(url('/team/live/' . $eid)) ?>" class="btn btn-sm btn-outline-light">
      <i class="bi bi-grid-1x2 me-1"></i>Πλήρες Mobile Hub
    </a>
  </div>

</div>
</body>
</html>
