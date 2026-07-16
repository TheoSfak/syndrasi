<?php
  $sevColors = ['critical' => '#b91c1c', 'high' => '#c2410c', 'medium' => '#a16207', 'low' => '#475569'];
  $accent = $sevColors[$r['severity']] ?? '#b91c1c';
  $ended  = ($r['mob_status'] === 'stood_down');
  $action = e(url('/m/' . $r['token'] . '/respond'));
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background:#f8fafc; font-family:'Segoe UI', system-ui, sans-serif; }
    .hero { background:<?= $accent ?>; color:#fff; padding:1.75rem 1.25rem; }
    .hero .sev { display:inline-block; background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.35);
                 border-radius:999px; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em;
                 font-weight:700; padding:.2rem .8rem; margin-bottom:.6rem; }
    .hero h1 { font-size:clamp(1.3rem,5vw,1.8rem); font-weight:800; margin:0; }
    .wrap { max-width:520px; margin:0 auto; padding:1.25rem; }
    .btn-xl { padding:1rem; font-size:1.15rem; font-weight:700; }
    .card { border:none; border-radius:1rem; box-shadow:0 2px 12px rgba(0,0,0,.07); }
  </style>
</head>
<body>
  <div class="hero">
    <div class="wrap p-0">
      <span class="sev"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= e(severity_label($r['severity'])) ?></span>
      <h1><?= e($r['title']) ?></h1>
    </div>
  </div>

  <div class="wrap">
    <?php foreach (flash_get() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> small"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <p class="text-muted small mb-3"><?= e(t('mobilizations/respond.001', 'Γεια σας')) ?> <strong><?= e($r['member_name']) ?></strong>.</p>

    <?php if ($r['description']): ?>
      <div class="card mb-3"><div class="card-body small"><?= nl2br(e($r['description'])) ?></div></div>
    <?php endif; ?>

    <?php if ($r['location_name'] || $r['latitude']): ?>
      <div class="card mb-3"><div class="card-body py-2 small">
        <i class="bi bi-geo-alt-fill me-1" style="color:<?= $accent ?>"></i>
        <?= e($r['location_name'] ?: 'Τοποθεσία') ?>
        <?php if ($r['latitude'] && $r['longitude']): ?>
          · <a target="_blank" rel="noopener"
               href="https://www.google.com/maps/search/?api=1&query=<?= e($r['latitude']) ?>,<?= e($r['longitude']) ?>"><?= e(t('mobilizations/respond.002', 'Χάρτης')) ?></a>
        <?php endif; ?>
      </div></div>
    <?php endif; ?>

    <?php if ($ended): ?>
      <div class="alert alert-secondary text-center"><?= e(t('mobilizations/respond.003', 'Το κάλεσμα έχει λήξει. Ευχαριστούμε!')) ?></div>
    <?php else: ?>

      <?php if ($r['response'] !== 'pending'): ?>
        <div class="alert alert-light border small">
          <?= e(t('mobilizations/respond.004', 'Η τρέχουσα απάντησή σας:')) ?>
          <strong>
            <?= $r['response'] === 'coming' ? 'Έρχομαι' : ($r['response'] === 'cant' ? 'Δεν μπορώ' : 'Ίσως') ?>
          </strong><?php if ($r['eta_minutes'] !== null): ?> · ETA <?= (int) $r['eta_minutes'] ?>′<?php endif; ?><?= e(t('mobilizations/respond.019', '. Μπορείτε να την αλλάξετε παρακάτω.')) ?>
        </div>
      <?php endif; ?>

      <?php if ($r['checked_in_at'] && !$r['departed_at']): ?>
        <!-- Already on-site -->
        <form method="post" action="<?= $action ?>" class="d-grid mb-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="departed">
          <button class="btn btn-dark btn-xl"><i class="bi bi-box-arrow-right me-1"></i><?= e(t('mobilizations/respond.006', 'Αποχωρώ από το σημείο')) ?></button>
        </form>
        <p class="text-center text-success small"><i class="bi bi-check-circle-fill"></i> <?= e(t('mobilizations/respond.007', 'Έχετε δηλώσει άφιξη.')) ?></p>
      <?php elseif (!$r['departed_at']): ?>

        <form method="post" action="<?= $action ?>" class="mb-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="coming">
          <label class="form-label small fw-semibold">Σε πόση ώρα μπορείτε να είστε εκεί;</label>
          <div class="input-group mb-2">
            <select name="eta_minutes" class="form-select">
              <option value="">— ETA —</option>
              <option value="10"><?= e(t('mobilizations/respond.008', '10 λεπτά')) ?></option>
              <option value="20"><?= e(t('mobilizations/respond.009', '20 λεπτά')) ?></option>
              <option value="30"><?= e(t('mobilizations/respond.010', '30 λεπτά')) ?></option>
              <option value="45"><?= e(t('mobilizations/respond.011', '45 λεπτά')) ?></option>
              <option value="60"><?= e(t('mobilizations/respond.012', '1 ώρα')) ?></option>
              <option value="90"><?= e(t('mobilizations/respond.013', '1.5 ώρα')) ?></option>
            </select>
          </div>
          <div class="d-grid"><button class="btn btn-success btn-xl"><i class="bi bi-check-lg me-1"></i><?= e(t('mobilizations/respond.014', 'ΕΡΧΟΜΑΙ')) ?></button></div>
        </form>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <form method="post" action="<?= $action ?>" class="d-grid">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="maybe">
              <button class="btn btn-outline-info"><?= e(t('mobilizations/respond.015', 'Ίσως')) ?></button>
            </form>
          </div>
          <div class="col-6">
            <form method="post" action="<?= $action ?>" class="d-grid">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cant">
              <button class="btn btn-outline-secondary"><?= e(t('mobilizations/respond.016', 'Δεν μπορώ')) ?></button>
            </form>
          </div>
        </div>

        <?php if ($r['response'] === 'coming'): ?>
          <form method="post" action="<?= $action ?>" class="d-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="arrived">
            <button class="btn btn-primary btn-xl"><i class="bi bi-geo-fill me-1"></i><?= e(t('mobilizations/respond.017', 'Έφτασα στο σημείο')) ?></button>
          </form>
        <?php endif; ?>

      <?php else: ?>
        <div class="alert alert-secondary text-center"><?= e(t('mobilizations/respond.018', 'Έχετε δηλώσει αποχώρηση. Ευχαριστούμε για τη συμμετοχή!')) ?></div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</body>
</html>
