<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; font-family: 'Segoe UI', system-ui, sans-serif; }
    .hero {
      background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
      color: #fff; padding: 2.5rem 1.5rem 3rem;
    }
    .hero .status-pill {
      display: inline-block;
      background: rgba(255,255,255,.18);
      border: 1px solid rgba(255,255,255,.3);
      border-radius: 999px; font-size: .75rem;
      padding: .25rem .85rem; letter-spacing: .04em;
      text-transform: uppercase; font-weight: 600; margin-bottom: .75rem;
    }
    .hero h1 { font-size: clamp(1.4rem, 4vw, 2rem); font-weight: 800; margin-bottom: .3rem; }
    .hero .sub { opacity: .82; font-size: .95rem; }
    .card-detail { border: none; border-radius: 1rem; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
    .detail-row { display: flex; gap: .75rem; align-items: flex-start; padding: .7rem 0; border-bottom: 1px solid #f1f5f9; }
    .detail-row:last-child { border-bottom: none; }
    .detail-icon { color: #0f766e; font-size: 1.1rem; min-width: 1.3rem; margin-top: .1rem; }
    .detail-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 600; }
    .detail-value { font-size: .95rem; color: #1e293b; font-weight: 500; }
    .stat-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: .75rem; text-align: center; padding: 1rem; }
    .stat-box .v { font-size: 1.6rem; font-weight: 800; color: #065f46; }
    .stat-box .l { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-top: .15rem; }
    .mun-bar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: .75rem 1.5rem; display: flex; align-items: center; gap: .75rem; }
    .mun-bar img { max-height: 36px; max-width: 100px; object-fit: contain; }
    .mun-bar .name { font-weight: 700; color: #0f766e; font-size: .95rem; }
    .mun-bar .sub  { font-size: .75rem; color: #94a3b8; }
    .badge-status { font-size: .8rem; }
    .instructions-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: .75rem; padding: 1rem 1.25rem; }
    footer { background: #f1f5f9; border-top: 1px solid #e2e8f0; padding: 1.25rem 1.5rem; text-align: center; font-size: .78rem; color: #94a3b8; margin-top: 2.5rem; }
    @media (max-width: 576px) { .hero { padding: 2rem 1rem 2.5rem; } }
  </style>
</head>
<body>

<!-- Municipality bar -->
<div class="mun-bar">
  <?php if ($logo): ?>
    <img src="<?= e($logo) ?>" alt="Logo">
  <?php else: ?>
    <span style="font-size:1.5rem">🏛</span>
  <?php endif; ?>
  <div>
    <div class="name"><?= e($event['municipality_name']) ?></div>
    <div class="sub">Εθελοντικός Συντονισμός</div>
  </div>
  <div class="ms-auto">
    <span class="badge text-bg-<?= e(status_color($event['status'])) ?> badge-status">
      <?= e(greek_status($event['status'])) ?>
    </span>
  </div>
</div>

<!-- Hero -->
<div class="hero">
  <div class="container" style="max-width:680px">
    <div class="status-pill">
      <?= e($event['category_name'] ?? 'Εθελοντική Δράση') ?>
    </div>
    <h1><?= e($event['title']) ?></h1>
    <div class="sub">
      <i class="bi bi-calendar3 me-1"></i>
      <?= e(gr_datetime($event['start_datetime'])) ?>
      <?php if ($event['end_datetime'] !== $event['start_datetime']): ?>
        &nbsp;–&nbsp; <?= e(gr_datetime($event['end_datetime'])) ?>
      <?php endif; ?>
    </div>
    <?php if ($event['location_name']): ?>
      <div class="sub mt-1"><i class="bi bi-geo-alt me-1"></i><?= e($event['location_name']) ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- Main content -->
<div class="container py-4" style="max-width:680px">

  <?php if ($event['description']): ?>
  <div class="card card-detail mb-3 p-4">
    <p class="mb-0" style="color:#374151;line-height:1.75;white-space:pre-line"><?= e($event['description']) ?></p>
  </div>
  <?php endif; ?>

  <!-- Stats row -->
  <div class="row g-3 mb-3">
    <?php if ($event['requested_people']): ?>
    <div class="col-4">
      <div class="stat-box">
        <div class="v"><?= (int) $event['requested_people'] ?></div>
        <div class="l">Ζητούμενοι εθελοντές</div>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($approvedTeams): ?>
    <div class="col-4">
      <div class="stat-box">
        <div class="v"><?= $approvedTeams ?></div>
        <div class="l">Ομάδες συμμετοχής</div>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($event['requested_vehicle'] || $event['requested_medical_equipment']): ?>
    <div class="col-4">
      <div class="stat-box">
        <div class="v" style="font-size:1.3rem">
          <?= $event['requested_vehicle'] ? '<i class="bi bi-truck"></i>' : '' ?>
          <?= $event['requested_medical_equipment'] ? '<i class="bi bi-heart-pulse"></i>' : '' ?>
        </div>
        <div class="l">Απαιτούμενος<br>εξοπλισμός</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Details card -->
  <div class="card card-detail mb-3 p-4">
    <?php if ($event['location_name']): ?>
    <div class="detail-row">
      <i class="bi bi-geo-alt-fill detail-icon"></i>
      <div>
        <div class="detail-label">Τοποθεσία</div>
        <div class="detail-value"><?= e($event['location_name']) ?></div>
        <?php if ($event['address']): ?>
          <div style="font-size:.83rem;color:#64748b"><?= e($event['address']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="detail-row">
      <i class="bi bi-clock detail-icon"></i>
      <div>
        <div class="detail-label">Έναρξη</div>
        <div class="detail-value"><?= e(gr_datetime($event['start_datetime'])) ?></div>
      </div>
    </div>

    <div class="detail-row">
      <i class="bi bi-clock-history detail-icon"></i>
      <div>
        <div class="detail-label">Λήξη</div>
        <div class="detail-value"><?= e(gr_datetime($event['end_datetime'])) ?></div>
      </div>
    </div>

    <?php if ($event['category_name']): ?>
    <div class="detail-row">
      <i class="bi bi-tag detail-icon"></i>
      <div>
        <div class="detail-label">Κατηγορία</div>
        <div class="detail-value"><?= e($event['category_name']) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($event['requested_people']): ?>
    <div class="detail-row">
      <i class="bi bi-people detail-icon"></i>
      <div>
        <div class="detail-label">Ζητούμενα άτομα</div>
        <div class="detail-value"><?= (int) $event['requested_people'] ?> εθελοντές</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Map (if coordinates available) -->
  <?php if ($event['latitude'] && $event['longitude']): ?>
  <div class="card card-detail mb-3 overflow-hidden">
    <div class="card-body p-0">
      <iframe
        width="100%" height="220" style="border:0; display:block"
        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
        src="https://www.openstreetmap.org/export/embed.html?bbox=<?= e($event['longitude']-0.005) ?>,<?= e($event['latitude']-0.005) ?>,<?= e($event['longitude']+0.005) ?>,<?= e($event['latitude']+0.005) ?>&amp;layer=mapnik&amp;marker=<?= e($event['latitude']) ?>,<?= e($event['longitude']) ?>">
      </iframe>
    </div>
    <?php if ($event['address']): ?>
    <div class="card-body py-2 px-3">
      <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($event['address']) ?>"
         target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-map me-1"></i>Άνοιγμα σε χάρτη
      </a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Instructions -->
  <?php if ($event['instructions']): ?>
  <div class="instructions-box mb-3">
    <div class="fw-semibold mb-2" style="color:#92400e"><i class="bi bi-info-circle me-1"></i>Οδηγίες συμμετοχής</div>
    <div style="white-space:pre-line;color:#78350f;font-size:.92rem"><?= e($event['instructions']) ?></div>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<footer>
  Δράση του <strong><?= e($event['municipality_name']) ?></strong> μέσω SynDrasi
  &nbsp;·&nbsp; Αυτή η σελίδα είναι δημόσια προβολή — χωρίς προσωπικά δεδομένα.
</footer>

</body>
</html>
