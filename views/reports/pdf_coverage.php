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
  :root {
    --teal: #0f766e;
    --teal-light: #ccfbf1;
    --grey: #6b7280;
    --border: #d1d5db;
  }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #111827;
    background: #f8fafc;
    padding: 24px;
  }
  /* ── Print button (hidden when printing) ─── */
  .no-print {
    position: fixed; top: 16px; right: 16px; z-index: 999;
    display: flex; gap: 8px;
  }
  .btn-print {
    background: var(--teal); color: #fff; border: none;
    padding: 10px 22px; border-radius: 8px; font-size: 14px;
    font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;
  }
  .btn-back {
    background: #fff; color: #374151; border: 1.5px solid var(--border);
    padding: 10px 18px; border-radius: 8px; font-size: 13px;
    font-weight: 500; cursor: pointer; text-decoration: none;
    display: flex; align-items: center; gap: 6px;
  }
  /* ── Document wrapper ─── */
  .doc {
    max-width: 860px; margin: 0 auto;
    background: #fff; border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0,0,0,.10);
    overflow: hidden;
  }
  /* ── Header band ─── */
  .doc-header {
    background: var(--teal);
    color: #fff;
    padding: 28px 36px 22px;
    display: flex; align-items: center; gap: 24px;
  }
  .doc-header .logo-wrap img {
    max-height: 64px; max-width: 140px;
    object-fit: contain; filter: brightness(0) invert(1);
  }
  .doc-header .logo-wrap .logo-placeholder {
    width: 64px; height: 64px; border-radius: 50%;
    background: rgba(255,255,255,.20);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px;
  }
  .doc-title { flex: 1; }
  .doc-title h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
  .doc-title .sub { font-size: 13px; opacity: .85; }
  .doc-stamp {
    text-align: right; font-size: 11px; opacity: .75; min-width: 130px;
    line-height: 1.7;
  }
  /* ── Event info strip ─── */
  .event-strip {
    background: var(--teal-light);
    padding: 18px 36px;
    display: flex; flex-wrap: wrap; gap: 24px;
    border-bottom: 2px solid var(--teal);
  }
  .event-strip .field label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; color: var(--teal); display: block; margin-bottom: 3px;
  }
  .event-strip .field span { font-weight: 600; font-size: 13px; }
  /* ── Body section ─── */
  .doc-body { padding: 28px 36px; }
  .section-title {
    font-size: 12px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .7px; color: var(--grey);
    border-bottom: 1.5px solid var(--border);
    padding-bottom: 6px; margin-bottom: 14px; margin-top: 24px;
  }
  .section-title:first-child { margin-top: 0; }
  /* ── Coverage table ─── */
  table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
  thead tr { background: #f1f5f9; }
  th {
    padding: 9px 10px; text-align: left;
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--grey);
    border-bottom: 2px solid var(--border);
  }
  td { padding: 9px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafafa; }
  .badge {
    display: inline-block; padding: 3px 9px; border-radius: 20px;
    font-size: 11px; font-weight: 600;
  }
  .badge-present  { background: #d1fae5; color: #065f46; }
  .badge-partial  { background: #fef9c3; color: #854d0e; }
  .badge-departed { background: #fee2e2; color: #991b1b; }
  .badge-none     { background: #f3f4f6; color: #6b7280; }
  .totals-row td  { font-weight: 700; background: #f8fafc; border-top: 2px solid var(--border); }
  /* ── Shortages ─── */
  .shortage-item {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 10px 0; border-bottom: 1px solid #f0f0f0;
  }
  .shortage-item:last-child { border-bottom: none; }
  .sev-badge {
    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
    white-space: nowrap;
  }
  .sev-critical { background: #fecaca; color: #7f1d1d; }
  .sev-high     { background: #fed7aa; color: #7c2d12; }
  .sev-medium   { background: #fef9c3; color: #713f12; }
  .sev-low      { background: #f3f4f6; color: #374151; }
  /* ── Summary box ─── */
  .summary-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
    margin: 20px 0;
  }
  .summary-card {
    background: #f8fafc; border-radius: 8px;
    padding: 14px 16px; text-align: center;
    border: 1px solid var(--border);
  }
  .summary-card .val { font-size: 26px; font-weight: 800; color: var(--teal); }
  .summary-card .lbl { font-size: 11px; color: var(--grey); margin-top: 2px; }
  /* ── Signature block ─── */
  .sig-block {
    display: flex; gap: 32px; margin-top: 40px; padding-top: 24px;
    border-top: 1.5px solid var(--border);
  }
  .sig-line { flex: 1; text-align: center; }
  .sig-line .line { border-top: 1.5px solid #374151; margin: 36px 24px 8px; }
  .sig-line .name-label { font-size: 11px; color: var(--grey); }
  /* ── Footer ─── */
  .doc-footer {
    background: #f8fafc; padding: 12px 36px;
    font-size: 10px; color: var(--grey); text-align: center;
    border-top: 1px solid var(--border);
  }
  /* ── PRINT ─────────────────────────────── */
  @media print {
    body { background: #fff; padding: 0; }
    .no-print { display: none !important; }
    .doc { box-shadow: none; border-radius: 0; }
    @page { size: A4; margin: 14mm 14mm 14mm 14mm; }
  }
</style>
</head>
<body>

<!-- Print / back controls -->
<div class="no-print">
  <a href="javascript:history.back()" class="btn-back">&#8592; Πίσω</a>
  <button class="btn-print" onclick="window.print()">&#128438; Εκτύπωση / PDF</button>
</div>

<?php
/* Helper: checkin status → badge */
function coverageBadge($status): string {
    if (!$status) return '<span class="badge badge-none">Χωρίς δήλωση</span>';
    return match ($status) {
        'present'  => '<span class="badge badge-present">Παρόν</span>',
        'partial'  => '<span class="badge badge-partial">Μερική</span>',
        'departed' => '<span class="badge badge-departed">Αποχώρησε</span>',
        default    => '<span class="badge badge-none">' . e($status) . '</span>',
    };
}
function sevBadge($sev): string {
    return match($sev) {
        'critical' => '<span class="sev-badge sev-critical">Κρίσιμη</span>',
        'high'     => '<span class="sev-badge sev-high">Υψηλή</span>',
        'medium'   => '<span class="sev-badge sev-medium">Μέτρια</span>',
        default    => '<span class="sev-badge sev-low">Χαμηλή</span>',
    };
}
$coverage = $totalApproved > 0 ? round($totalPresent / $totalApproved * 100) : 0;
?>

<div class="doc">

  <!-- Header -->
  <div class="doc-header">
    <div class="logo-wrap">
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="Logo">
      <?php else: ?>
        <div class="logo-placeholder">🏛</div>
      <?php endif; ?>
    </div>
    <div class="doc-title">
      <h1><?= e($orgName) ?></h1>
      <div class="sub"><?= e(t('reports/pdf_coverage.001', 'Αναφορά Επιχειρησιακής Κάλυψης')) ?></div>
    </div>
    <div class="doc-stamp">
      <?= e(t('reports/pdf_coverage.002', 'Εκτυπώθηκε')) ?><br><?= date('d/m/Y H:i') ?><br>
      <strong><?= e(t('reports/pdf_coverage.031', 'Αρ.')) ?> <?= e($eventSingularLc) ?> #<?= (int) $event['id'] ?></strong>
    </div>
  </div>

  <!-- Event info strip -->
  <div class="event-strip">
    <div class="field">
      <label><?= e(t('reports/pdf_coverage.004', 'Τίτλος')) ?> <?= e($eventSingularLc) ?></label>
      <span><?= e($event['title']) ?></span>
    </div>
    <div class="field">
      <label><?= e(t('reports/pdf_coverage.005', 'Έναρξη')) ?></label>
      <span><?= e(gr_datetime($event['start_datetime'])) ?></span>
    </div>
    <div class="field">
      <label><?= e(t('reports/pdf_coverage.006', 'Λήξη')) ?></label>
      <span><?= e(gr_datetime($event['end_datetime'])) ?></span>
    </div>
    <div class="field">
      <label><?= e(t('reports/pdf_coverage.007', 'Διάρκεια')) ?></label>
      <span><?= $durationH ?> <?= e(t('reports/pdf_coverage.008', 'ώρες')) ?></span>
    </div>
    <?php if ($event['location_name']): ?>
    <div class="field">
      <label><?= e(t('reports/pdf_coverage.009', 'Τοποθεσία')) ?></label>
      <span><?= e($event['location_name']) ?></span>
    </div>
    <?php endif; ?>
    <div class="field">
      <label><?= e(t('reports/pdf_coverage.010', 'Κατηγορία')) ?></label>
      <span><?= e($event['category_name'] ?? '—') ?></span>
    </div>
  </div>

  <div class="doc-body">

    <!-- Summary cards -->
    <div class="section-title"><?= e(t('reports/pdf_coverage.011', 'Σύνοψη')) ?></div>
    <div class="summary-grid">
      <div class="summary-card">
        <div class="val"><?= count($teams) ?></div>
        <div class="lbl"><?= e(t('reports/pdf_coverage.012', 'Εγκεκριμένες ομάδες')) ?></div>
      </div>
      <div class="summary-card">
        <div class="val"><?= $totalApproved ?></div>
        <div class="lbl"><?= e(t('reports/pdf_coverage.013', 'Εγκεκριμένα άτομα')) ?></div>
      </div>
      <div class="summary-card">
        <div class="val"><?= $totalPresent ?></div>
        <div class="lbl"><?= e(t('reports/pdf_coverage.014', 'Επιβεβαιωμένη παρουσία')) ?></div>
      </div>
      <div class="summary-card">
        <div class="val" style="color:<?= $coverage >= 80 ? '#065f46' : ($coverage >= 50 ? '#854d0e' : '#991b1b') ?>">
          <?= $coverage ?>%
        </div>
        <div class="lbl"><?= e(t('reports/pdf_coverage.015', 'Κάλυψη')) ?></div>
      </div>
    </div>

    <!-- Teams table -->
    <div class="section-title"><?= e(t('reports/pdf_coverage.016', 'Ανάπτυξη ομάδων')) ?></div>
    <?php if (!$teams): ?>
      <p style="color:var(--grey)"><?= e(t('reports/pdf_coverage.017', 'Δεν υπάρχουν εγκεκριμένες ομάδες.')) ?></p>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th><?= e(t('reports/pdf_coverage.018', 'Ομάδα')) ?></th>
          <th class="text-center"><?= e(t('reports/pdf_coverage.019', 'Εγκ. άτομα')) ?></th>
          <th><?= e(t('reports/pdf_coverage.020', 'Παρουσία')) ?></th>
          <th class="text-center"><?= e(t('reports/pdf_coverage.021', 'Παρόντα')) ?></th>
          <th><?= e(t('reports/pdf_coverage.022', 'Δήλωση παρουσίας')) ?></th>
          <th><?= e(t('reports/pdf_coverage.023', 'Εξοπλισμός')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teams as $t): ?>
          <tr>
            <td><strong><?= e($t['team_name']) ?></strong><?= $t['team_type'] ? '<br><span style="color:var(--grey);font-size:11px">'.e($t['team_type']).'</span>' : '' ?></td>
            <td style="text-align:center"><?= (int) $t['approved_people'] ?></td>
            <td><?= coverageBadge($t['checkin_status']) ?></td>
            <td style="text-align:center"><?= $t['present_people'] !== null ? (int) $t['present_people'] : '—' ?></td>
            <td style="font-size:11px;color:var(--grey)"><?= $t['checked_in_at'] ? e(gr_datetime($t['checked_in_at'])) : '—' ?></td>
            <td style="font-size:11px">
              <?= $t['offered_vehicle'] ? '🚐 Όχημα&nbsp;' : '' ?>
              <?= $t['offered_medical_equipment'] ? '🩺 Υγ. εξ.' : '' ?>
              <?= (!$t['offered_vehicle'] && !$t['offered_medical_equipment']) ? '—' : '' ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr class="totals-row">
          <td><?= e(t('reports/pdf_coverage.024', 'ΣΥΝΟΛΑ')) ?></td>
          <td style="text-align:center"><?= $totalApproved ?></td>
          <td></td>
          <td style="text-align:center"><?= $totalPresent ?></td>
          <td colspan="2"></td>
        </tr>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- Shortage reports -->
    <?php if ($shortages): ?>
    <div class="section-title" style="margin-top:30px"><?= e(t('reports/pdf_coverage.032', 'Αναφορές ελλείψεων (')) ?><?= count($shortages) ?>)</div>
    <?php foreach ($shortages as $sh): ?>
      <div class="shortage-item">
        <?= sevBadge($sh['severity'] ?? 'low') ?>
        <div>
          <strong><?= e($sh['team_name']) ?></strong>
          <span style="color:var(--grey);font-size:11px;margin-left:8px"><?= e(gr_datetime($sh['created_at'])) ?></span>
          <?php if ($sh['description']): ?>
            <div style="margin-top:3px"><?= e($sh['description']) ?></div>
          <?php endif; ?>
          <?php if ($sh['items_needed']): ?>
            <div style="color:var(--grey);font-size:11px"><?= e(t('reports/pdf_coverage.026', 'Ανάγκη:')) ?> <?= e($sh['items_needed']) ?></div>
          <?php endif; ?>
        </div>
        <div style="margin-left:auto;font-size:11px;color:var(--grey)">
          <?= $sh['resolved_at'] ? '✅ Επιλύθηκε' : ($sh['acknowledged_at'] ? '👁 Ελήφθη' : 'Ανοιχτή') ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="sig-block">
      <div class="sig-line">
        <div class="line"></div>
        <div class="name-label"><?= e(t('reports/pdf_coverage.027', 'Υπεύθυνος')) ?> <?= e($eventSingular) ?></div>
      </div>
      <div class="sig-line">
        <div class="line"></div>
        <div class="name-label"><?= e(t('reports/pdf_coverage.028', 'Διαχειριστής Φορέα')) ?></div>
      </div>
      <div class="sig-line">
        <div class="line"></div>
        <div class="name-label"><?= e(t('reports/pdf_coverage.029', 'Ημερομηνία / Σφραγίδα')) ?></div>
      </div>
    </div>

  </div><!-- /doc-body -->

  <div class="doc-footer">
    <?= e(t('reports/pdf_coverage.033', 'Παράχθηκε αυτόματα από το SynDrasi ·')) ?> <?= e($orgName) ?> · <?= date('d/m/Y H:i') ?>
  </div>

</div><!-- /doc -->

</body>
</html>
