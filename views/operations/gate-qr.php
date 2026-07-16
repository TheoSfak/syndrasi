<?php
/**
 * Gate QR — /operations/events/{id}/gate-qr
 * Full-screen scannable QR. Team leaders scan it to open the one-tap check-in
 * page (/team/qr-checkin/{id}) on their phones.
 */
$eid    = (int) $event['id'];
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$qrUrl  = $scheme . '://' . $host . url('/team/qr-checkin/' . $eid);
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'QR Πύλης') ?> · SynDrasi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  body{background:#0b1120;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;}
  .gate{max-width:560px;text-align:center;padding:1.5rem;}
  .gate h1{font-weight:800;}
  .qrbox{background:#fff;display:inline-block;padding:20px;border-radius:20px;box-shadow:0 12px 50px rgba(0,0,0,.4);}
  .qrbox img, .qrbox canvas{display:block;}
  .urlbox{word-break:break-all;font-size:.8rem;color:#94a3b8;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:.5rem .75rem;margin-top:1rem;}
  @media print{ body{background:#fff;color:#000;} .noprint{display:none!important;} .qrbox{box-shadow:none;border:1px solid #ccc;} }
</style>
</head>
<body>
<div class="gate">
  <div class="text-uppercase small mb-1" style="letter-spacing:1.5px;color:#94a3b8"><i class="bi bi-qr-code me-1"></i><?= e(t('operations/gate-qr.001', 'QR Πύλης — Δήλωση Παρουσίας')) ?></div>
  <h1 class="h3 mb-1"><?= e($event['title']) ?></h1>
  <p class="mb-4" style="color:#cbd5e1"><?= e(t('operations/gate-qr.002', 'Σαρώστε με το κινητό σας για να δηλώσετε παρουσία της ομάδας σας.')) ?></p>

  <div class="qrbox"><div id="qr"></div></div>

  <div class="urlbox"><?= e($qrUrl) ?></div>

  <div class="noprint mt-4 d-flex gap-2 justify-content-center">
    <button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer me-1"></i><?= e(t('operations/gate-qr.003', 'Εκτύπωση')) ?></button>
    <a class="btn btn-outline-light" href="<?= e(url('/operations/events/' . $eid)) ?>"><i class="bi bi-arrow-left me-1"></i><?= e(t('operations/gate-qr.004', 'Πίσω')) ?></a>
  </div>
</div>

<script>
  new QRCode(document.getElementById('qr'), {
    text: <?= json_encode($qrUrl) ?>,
    width: 320, height: 320,
    correctLevel: QRCode.CorrectLevel.M
  });
</script>
</body>
</html>
