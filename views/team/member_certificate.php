<?php
$terms = authority_context((int) ($mun['id'] ?? current_municipality_id()));
$orgName = $terms['official_name'] ?? ($mun['name'] ?? 'Φορέας');
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? mb_strtolower($eventPlural, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= e(t('team/member_certificate.001', 'Πιστοποιητικό Εθελοντή —')) ?> <?= e($member['full_name']) ?></title>
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

  /* ── Certificate page ─────────────────────────── */
  .cert-page {
    width: 210mm;
    min-height: 148mm;
    margin: 30px auto;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 24px rgba(0,0,0,.12);
    position: relative;
    overflow: hidden;
  }
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
  .cert-inner  { position: absolute; top: 14px;    right: 14px;  width: 60px; height: 60px; border-top: 4px solid #0f766e; border-right: 4px solid #0f766e; }
  .cert-inner2 { position: absolute; bottom: 14px; left: 14px;   width: 60px; height: 60px; border-bottom: 4px solid #0f766e; border-left: 4px solid #0f766e; }

  .cert-content {
    padding: 36px 56px;
    display: flex; flex-direction: column; align-items: center;
    gap: 14px;
  }

  /* top row */
  .cert-top {
    display: flex; align-items: center; gap: 18px; width: 100%;
    border-bottom: 1.5px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 4px;
  }
  .cert-top img { max-height: 56px; max-width: 120px; object-fit: contain; }
  .cert-top .mun-name { font-size: 16px; font-weight: 700; color: #0f766e; }
  .cert-top .mun-sub  { font-size: 11px; color: #6b7280; margin-top: 2px; }
  .cert-date { font-size: 12px; color: #9ca3af; text-align: right; }

  .cert-headline {
    font-size: 11px; font-weight: 700; letter-spacing: 3px;
    text-transform: uppercase; color: #9ca3af; margin-top: 4px;
  }
  .cert-title {
    font-size: 28px; font-weight: 800; color: #0f766e;
    text-align: center; line-height: 1.2;
  }
  .cert-body {
    font-size: 15px; text-align: center; color: #374151;
    line-height: 1.75; max-width: 520px;
  }
  .cert-name {
    font-size: 24px; font-weight: 800; color: #111827;
    border-bottom: 2.5px solid #0f766e;
    padding-bottom: 6px;
  }
  .cert-team-label {
    font-size: 13px; color: #6b7280; text-align: center;
  }

  /* Stats row */
  .cert-stats { display: flex; gap: 24px; margin: 8px 0; }
  .cert-stat {
    text-align: center;
    border: 1.5px solid #d1fae5; border-radius: 8px;
    padding: 10px 20px; background: #f0fdf4;
  }
  .cert-stat .v { font-size: 22px; font-weight: 800; color: #065f46; }
  .cert-stat .l { font-size: 11px; color: #6b7280; margin-top: 2px; }

  /* Event list */
  .cert-events {
    width: 100%; border-collapse: collapse; font-size: 11px; color: #374151;
    margin-top: 4px;
  }
  .cert-events th {
    background: #f0fdf4; color: #065f46; font-weight: 700;
    padding: 5px 8px; border-bottom: 1.5px solid #d1fae5; text-align: left;
  }
  .cert-events td {
    padding: 5px 8px; border-bottom: 1px solid #f3f4f6;
  }
  .cert-events tr:last-child td { border-bottom: none; }

  /* Signatures */
  .cert-sigs {
    display: flex; gap: 40px; margin-top: 16px; width: 100%;
    padding-top: 14px; border-top: 1px solid #e5e7eb;
  }
  .cert-sig { flex: 1; text-align: center; }
  .cert-sig .sig-line { border-top: 1.5px solid #374151; margin: 28px 16px 8px; }
  .cert-sig .sig-lbl  { font-size: 11px; color: #6b7280; }

  @media print {
    body { background: #fff; }
    .no-print { display: none !important; }
    .cert-page { margin: 0; box-shadow: none; border-radius: 0; width: 100%; }
    @page { size: A4 portrait; margin: 8mm; }
  }
</style>
</head>
<body>

<div class="no-print">
  <a href="javascript:history.back()" class="btn-back">&#8592; Πίσω</a>
  <button class="btn-print" onclick="window.print()">&#128438; Εκτύπωση / PDF</button>
</div>

<div class="cert-page">
  <div class="cert-inner"></div>
  <div class="cert-inner2"></div>
  <div class="cert-content">

    <!-- Municipality header -->
    <div class="cert-top">
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="Logo">
      <?php else: ?>
        <span style="font-size:36px">&#127963;</span>
      <?php endif; ?>
      <div>
        <div class="mun-name"><?= e($orgName) ?></div>
        <div class="mun-sub">Εθελοντικός Συντονισμός &amp; Πολιτική Προστασία</div>
      </div>
      <div style="margin-left:auto">
        <div class="cert-date"><?= e(t('team/member_certificate.002', 'Εκδόθηκε:')) ?> <?= e(gr_date(date('Y-m-d'))) ?></div>
      </div>
    </div>

    <div class="cert-headline"><?= e(t('team/member_certificate.003', 'Πιστοποιητικό Εθελοντικής')) ?> <?= e($eventSingular) ?></div>

    <div class="cert-title"><?= e(t('team/member_certificate.004', 'Βεβαίωση')) ?><br><?= e(t('team/member_certificate.005', 'Εθελοντικής Συμμετοχής')) ?></div>

    <div class="cert-body"><?= e(t('team/member_certificate.006', 'Πιστοποιείται ότι ο/η εθελοντής')) ?></div>

    <div class="cert-name"><?= e($member['full_name']) ?></div>

    <div class="cert-team-label">
      <?= e(t('team/member_certificate.007', 'Ομάδα:')) ?> <strong><?= e($teamName) ?></strong>
      <?php if ($member['role_in_team']): ?>
        · <?= e($member['role_in_team']) ?>
      <?php endif; ?>
    </div>

    <div class="cert-body">
      <?= e(t('team/member_certificate.017', 'συμμετείχε σε εθελοντικές')) ?> <?= e($eventPluralLc) ?> <?= e(t('team/member_certificate.018', 'του φορέα ως μέλος της ομάδας πολιτικής προστασίας με τα παρακάτω συνολικά στοιχεία:')) ?>
    </div>

    <div class="cert-stats">
      <div class="cert-stat">
        <div class="v"><?= (int) $stats['attended_events'] ?></div>
        <div class="l"><?= e($eventPlural) ?></div>
      </div>
      <div class="cert-stat">
        <div class="v"><?= number_format((float)($stats['total_hours'] ?? 0), 1) ?></div>
        <div class="l"><?= e(t('team/member_certificate.009', 'Ώρες εθελοντισμού')) ?></div>
      </div>
      <?php if ((int) $stats['times_commander'] > 0): ?>
      <div class="cert-stat">
        <div class="v"><?= (int) $stats['times_commander'] ?></div>
        <div class="l"><?= e(t('team/member_certificate.010', 'Φορές Υπεύθυνος')) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Last 10 attended events -->
    <?php $attended = array_filter($participations, fn($p) => (int)$p['was_present']); ?>
    <?php if ($attended): ?>
    <table class="cert-events">
      <thead>
        <tr>
          <th><?= e($eventSingular) ?></th>
          <th><?= e(t('team/member_certificate.011', 'Ημερομηνία')) ?></th>
          <th style="text-align:right"><?= e(t('team/member_certificate.012', 'Ώρες')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice(array_values($attended), 0, 12) as $p): ?>
        <tr>
          <td><?= e($p['event_title']) ?></td>
          <td><?= e(gr_date($p['start_datetime'])) ?></td>
          <td style="text-align:right"><?= number_format((float)$p['hours'], 1) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (count($attended) > 12): ?>
        <tr><td colspan="3" style="color:#9ca3af;font-style:italic"><?= e(t('team/member_certificate.019', '...και')) ?> <?= count($attended) - 12 ?> <?= e(t('team/member_certificate.020', 'ακόμη')) ?> <?= e($eventPluralLc) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="cert-sigs">
      <div class="cert-sig">
        <div class="sig-line"></div>
        <div class="sig-lbl"><?= e(t('team/member_certificate.014', 'Υπεύθυνος Ομάδας')) ?></div>
      </div>
      <div class="cert-sig">
        <div class="sig-line"></div>
        <div class="sig-lbl"><?= e(t('team/member_certificate.015', 'Εκπρόσωπος Φορέα')) ?></div>
      </div>
      <div class="cert-sig">
        <div class="sig-line"></div>
        <div class="sig-lbl"><?= e(t('team/member_certificate.016', 'Ημερομηνία / Σφραγίδα')) ?></div>
      </div>
    </div>

  </div><!-- /cert-content -->
</div><!-- /cert-page -->

</body>
</html>
