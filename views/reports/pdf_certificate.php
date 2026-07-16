<?php
$terms = authority_context((int) ($event['municipality_id'] ?? ($mun['id'] ?? current_municipality_id())));
$orgName = $terms['official_name'] ?? ($mun['name'] ?? 'Φορέας');
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Georgia, serif;
    background: #f0f4f8;
    color: #1a1a2e;
  }
  .no-print {
    position: fixed; top: 16px; right: 16px; z-index: 999;
    display: flex; gap: 8px;
  }
  .btn-print {
    background: #0f766e; color: #fff; border: none;
    padding: 10px 22px; border-radius: 8px; font-size: 14px;
    font-weight: 600; cursor: pointer;
  }
  .btn-back {
    background: #fff; color: #374151; border: 1.5px solid #d1d5db;
    padding: 10px 18px; border-radius: 8px; font-size: 13px;
    font-weight: 500; cursor: pointer; text-decoration: none;
    display: inline-block;
  }
  .hint {
    text-align: center; padding: 12px 0 4px;
    font-size: 12px; color: #6b7280;
  }
  /* ── Certificate page ─────────────────────────── */
  .cert-page {
    width: 210mm;
    min-height: 148mm; /* A5 landscape feel, use A4 portrait from print dialog */
    margin: 20px auto;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 24px rgba(0,0,0,.12);
    position: relative;
    overflow: hidden;
    page-break-after: always;
  }
  .cert-page:last-child { page-break-after: avoid; }
  /* decorative corner borders */
  .cert-page::before, .cert-page::after {
    content: '';
    position: absolute;
    width: 60px; height: 60px;
    border-color: #0f766e;
    border-style: solid;
    border-width: 0;
  }
  .cert-page::before { top: 14px; left: 14px; border-top-width: 4px; border-left-width: 4px; }
  .cert-page::after  { bottom: 14px; right: 14px; border-bottom-width: 4px; border-right-width: 4px; }
  /* inner corners */
  .cert-inner {
    position: absolute; top: 14px; right: 14px; width: 60px; height: 60px;
    border-top: 4px solid #0f766e; border-right: 4px solid #0f766e;
  }
  .cert-inner2 {
    position: absolute; bottom: 14px; left: 14px; width: 60px; height: 60px;
    border-bottom: 4px solid #0f766e; border-left: 4px solid #0f766e;
  }
  /* ── Content layout ─── */
  .cert-content {
    padding: 36px 56px;
    display: flex; flex-direction: column; align-items: center;
    gap: 16px;
  }
  /* top row: logo + municipality */
  .cert-top {
    display: flex; align-items: center; gap: 18px; width: 100%;
    border-bottom: 1.5px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 4px;
  }
  .cert-top img {
    max-height: 56px; max-width: 120px; object-fit: contain;
  }
  .cert-top .mun-name {
    font-size: 16px; font-weight: 700; color: #0f766e;
  }
  .cert-top .mun-sub {
    font-size: 11px; color: #6b7280; margin-top: 2px;
  }
  /* ── Body ─── */
  .cert-headline {
    font-size: 11px; font-weight: 700; letter-spacing: 3px;
    text-transform: uppercase; color: #9ca3af; margin-top: 4px;
  }
  .cert-title {
    font-size: 30px; font-weight: 800; color: #0f766e;
    letter-spacing: -0.5px; text-align: center;
    line-height: 1.15;
  }
  .cert-body {
    font-size: 15px; text-align: center; color: #374151;
    line-height: 1.75; max-width: 500px;
  }
  .cert-team {
    font-size: 22px; font-weight: 800; color: #111827;
    border-bottom: 2.5px solid #0f766e;
    padding-bottom: 6px; margin: 0 auto;
  }
  .cert-event {
    font-size: 14px; font-weight: 600; color: #0f766e;
    text-align: center;
  }
  .cert-stats {
    display: flex; gap: 32px; margin: 8px 0;
  }
  .cert-stat {
    text-align: center;
    border: 1.5px solid #d1fae5; border-radius: 8px;
    padding: 10px 20px; background: #f0fdf4;
  }
  .cert-stat .v { font-size: 22px; font-weight: 800; color: #065f46; }
  .cert-stat .l { font-size: 11px; color: #6b7280; margin-top: 2px; }
  /* ── Signature area ─── */
  .cert-sigs {
    display: flex; gap: 40px; margin-top: 16px; width: 100%;
    padding-top: 14px; border-top: 1px solid #e5e7eb;
  }
  .cert-sig { flex: 1; text-align: center; }
  .cert-sig .sig-line {
    border-top: 1.5px solid #374151; margin: 28px 16px 8px;
  }
  .cert-sig .sig-lbl { font-size: 11px; color: #6b7280; }
  .cert-date {
    font-size: 12px; color: #9ca3af; text-align: right; width: 100%;
    margin-top: -4px;
  }
  /* ── PRINT ─── */
  @media print {
    body { background: #fff; }
    .no-print, .hint { display: none !important; }
    .cert-page {
      margin: 0; box-shadow: none; border-radius: 0;
      width: 100%;
    }
    @page { size: A4 portrait; margin: 8mm; }
  }
</style>
</head>
<body>

<div class="no-print">
  <a href="javascript:history.back()" class="btn-back">&#8592; Πίσω</a>
  <button class="btn-print" onclick="window.print()">&#128438; Εκτύπωση / PDF</button>
</div>
<div class="hint no-print">
  <?= e(t('reports/pdf_certificate.001', 'Κάθε ομάδα εκτυπώνεται σε ξεχωριστή σελίδα. Συνολικές ομάδες:')) ?> <strong><?= count($teams) ?></strong>
</div>

<?php if (!$teams): ?>
  <div style="text-align:center;padding:60px;color:#6b7280">
    <?= e(t('reports/pdf_certificate.014', 'Δεν υπάρχουν εγκεκριμένες ομάδες για αυτή τη')) ?> <?= e($eventSingularLc) ?>.
  </div>
<?php endif; ?>

<?php foreach ($teams as $t):
  $people = $t['present_people'] !== null ? (int) $t['present_people'] : (int) $t['approved_people'];
?>
<div class="cert-page">
  <div class="cert-inner"></div>
  <div class="cert-inner2"></div>
  <div class="cert-content">

    <!-- Municipality header -->
    <div class="cert-top">
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="Logo">
      <?php else: ?>
        <span style="font-size:36px">🏛</span>
      <?php endif; ?>
      <div>
        <div class="mun-name"><?= e($orgName) ?></div>
        <div class="mun-sub">Εθελοντικός Συντονισμός &amp; Πολιτική Προστασία</div>
      </div>
      <div style="margin-left:auto">
        <div class="cert-date">
          <?= e(gr_date($event['start_datetime'])) ?>
        </div>
      </div>
    </div>

    <!-- Certificate label -->
    <div class="cert-headline"><?= e(t('reports/pdf_certificate.003', 'Πιστοποιητικό Συμμετοχής')) ?></div>

    <div class="cert-title"><?= e(t('reports/pdf_certificate.004', 'Συμμετοχή')) ?><br><?= e(t('reports/pdf_certificate.005', 'σε Εθελοντική')) ?> <?= e($eventSingular) ?></div>

    <div class="cert-body">
      <?= e(t('reports/pdf_certificate.006', 'Πιστοποιείται ότι η εθελοντική ομάδα')) ?>
    </div>

    <div class="cert-team"><?= e($t['team_name']) ?></div>

    <div class="cert-body">
      <?= e(t('reports/pdf_certificate.007', 'συμμετείχε στη')) ?> <?= e($eventSingularLc) ?>
    </div>

    <div class="cert-event">«<?= e($event['title']) ?>»</div>
    <div style="font-size:12px;color:#9ca3af;text-align:center">
      <?= e(gr_date($event['start_datetime'])) ?>
      — <?= e(gr_date($event['end_datetime'])) ?>
      <?php if ($event['location_name']): ?>
        &nbsp;·&nbsp; <?= e($event['location_name']) ?>
      <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="cert-stats">
      <div class="cert-stat">
        <div class="v"><?= $people ?></div>
        <div class="l"><?= e(t('reports/pdf_certificate.008', 'Εθελοντές')) ?></div>
      </div>
      <div class="cert-stat">
        <div class="v"><?= $durationH ?></div>
        <div class="l"><?= e(t('reports/pdf_certificate.009', 'Ώρες υπηρεσίας')) ?></div>
      </div>
      <div class="cert-stat">
        <div class="v"><?= round($people * $durationH, 1) ?></div>
        <div class="l"><?= e(t('reports/pdf_certificate.010', 'Ώρες εθελοντισμού')) ?></div>
      </div>
    </div>

    <!-- Signatures -->
    <div class="cert-sigs">
      <div class="cert-sig">
        <div class="sig-line"></div>
        <div class="sig-lbl"><?= e(t('reports/pdf_certificate.011', 'Υπεύθυνος')) ?> <?= e($eventSingular) ?></div>
      </div>
      <div class="cert-sig">
        <div class="sig-line"></div>
        <div class="sig-lbl"><?= e(t('reports/pdf_certificate.012', 'Εκπρόσωπος Φορέα')) ?></div>
      </div>
      <div class="cert-sig">
        <div class="sig-line"></div>
        <div class="sig-lbl"><?= e(t('reports/pdf_certificate.013', 'Ημερομηνία / Σφραγίδα')) ?></div>
      </div>
    </div>

  </div><!-- /cert-content -->
</div><!-- /cert-page -->
<?php endforeach; ?>

</body>
</html>
