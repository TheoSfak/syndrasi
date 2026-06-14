<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    background: #0f172a;
    color: #f1f5f9;
  }
  .no-print {
    position: fixed; top: 16px; right: 16px; z-index: 999;
    display: flex; gap: 8px;
  }
  .btn-print {
    background: #f59e0b; color: #1a1a1a; border: none;
    padding: 10px 22px; border-radius: 8px; font-size: 14px;
    font-weight: 700; cursor: pointer;
  }
  .btn-back {
    background: rgba(255,255,255,.1); color: #f1f5f9;
    border: 1.5px solid rgba(255,255,255,.2);
    padding: 10px 18px; border-radius: 8px; font-size: 13px;
    font-weight: 500; cursor: pointer; text-decoration: none;
    display: inline-block;
  }
  /* ── Cover page ─── */
  .cover {
    min-height: 100vh;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: linear-gradient(160deg, #0f172a 0%, #134e4a 60%, #0f172a 100%);
    padding: 60px 40px;
    text-align: center;
    page-break-after: always;
    position: relative;
    overflow: hidden;
  }
  .cover::before {
    content: '';
    position: absolute; inset: 0;
    background:
      radial-gradient(ellipse at 20% 50%, rgba(245,158,11,.08) 0%, transparent 60%),
      radial-gradient(ellipse at 80% 30%, rgba(15,118,110,.15) 0%, transparent 55%);
    pointer-events: none;
  }
  .cover-star {
    font-size: 64px; margin-bottom: 20px; line-height: 1;
    filter: drop-shadow(0 0 20px rgba(245,158,11,.6));
  }
  .cover-mun {
    font-size: 14px; letter-spacing: 4px; text-transform: uppercase;
    color: #94a3b8; margin-bottom: 16px;
  }
  .cover-title {
    font-size: 42px; font-weight: 900; letter-spacing: -1px;
    background: linear-gradient(135deg, #fde68a, #f59e0b, #fbbf24);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.15; margin-bottom: 10px;
  }
  .cover-subtitle {
    font-size: 18px; color: #94a3b8; margin-bottom: 6px;
  }
  .cover-year {
    font-size: 80px; font-weight: 900;
    color: rgba(255,255,255,.05);
    position: absolute; bottom: 30px; right: 40px;
    line-height: 1;
    pointer-events: none;
  }
  .cover-logo { max-height: 70px; max-width: 180px; object-fit: contain; margin-bottom: 24px; opacity: .9; }
  /* ── Interior pages ─── */
  .page {
    background: #fff; color: #1a1a2e;
    padding: 44px 52px;
    min-height: 297mm; /* A4 */
    page-break-after: always;
  }
  .page:last-child { page-break-after: avoid; }
  .page-header {
    display: flex; align-items: center; gap: 16px;
    border-bottom: 3px solid #0f766e;
    padding-bottom: 14px; margin-bottom: 30px;
  }
  .page-header .ph-icon { font-size: 24px; }
  .page-header h2 { font-size: 22px; font-weight: 800; color: #0f172a; }
  .page-header .ph-sub { font-size: 12px; color: #6b7280; margin-top: 2px; }
  /* ── Award cards ─── */
  .awards-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 32px;
  }
  .award-card {
    border-radius: 12px; overflow: hidden;
    border: 2px solid #e5e7eb;
  }
  .award-card-header {
    padding: 14px 18px; font-weight: 700; font-size: 13px;
    display: flex; align-items: center; gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,.2);
  }
  .award-card-body { padding: 18px; }
  .award-team {
    font-size: 19px; font-weight: 800; color: #0f172a; margin-bottom: 10px;
  }
  .award-stats { display: flex; gap: 16px; flex-wrap: wrap; }
  .award-stat { font-size: 12px; color: #6b7280; }
  .award-stat strong { color: #0f172a; font-size: 15px; display: block; }
  /* colour themes per award */
  .aw-contribution .award-card-header { background: linear-gradient(135deg,#1d4ed8,#3b82f6); color:#fff; }
  .aw-active .award-card-header      { background: linear-gradient(135deg,#15803d,#22c55e); color:#fff; }
  .aw-consistent .award-card-header  { background: linear-gradient(135deg,#7c3aed,#a855f7); color:#fff; }
  .aw-fastest .award-card-header     { background: linear-gradient(135deg,#b45309,#f59e0b); color:#fff; }
  .award-card.empty .award-team { color: #9ca3af; font-size:15px; font-weight:500; }
  /* ── Ranking table ─── */
  .rank-table { width: 100%; border-collapse: collapse; }
  .rank-table thead tr { background: #f8fafc; }
  .rank-table th {
    padding: 10px 12px; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px; color: #6b7280;
    border-bottom: 2px solid #e5e7eb; text-align: left;
  }
  .rank-table td {
    padding: 11px 12px; border-bottom: 1px solid #f1f5f9;
    font-size: 12.5px; vertical-align: middle;
  }
  .rank-table tr:last-child td { border-bottom: none; }
  .rank-num {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 13px;
  }
  .rank-1 { background: #fef9c3; color: #854d0e; }
  .rank-2 { background: #f3f4f6; color: #374151; }
  .rank-3 { background: #fef3c7; color: #92400e; }
  .rank-n { background: #f9fafb; color: #9ca3af; }
  /* ── Footer ─── */
  .page-footer {
    margin-top: 40px; padding-top: 14px;
    border-top: 1px solid #e5e7eb;
    font-size: 10px; color: #9ca3af; text-align: center;
  }
  /* ── PRINT ─── */
  @media print {
    body { background: #fff; }
    .no-print { display: none !important; }
    .cover {
      background: #0f172a !important;
      -webkit-print-color-adjust: exact; print-color-adjust: exact;
      color-adjust: exact;
    }
    .award-card-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .page { min-height: auto; }
    @page { size: A4 portrait; margin: 10mm; }
  }
</style>
</head>
<body>

<div class="no-print">
  <a href="javascript:history.back()" class="btn-back">&#8592; Πίσω</a>
  <button class="btn-print" onclick="window.print()">&#127775; Εκτύπωση / PDF</button>
</div>

<!-- ══ Cover page ══════════════════════════════════ -->
<div class="cover">
  <?php if ($logo): ?>
    <img src="<?= e($logo) ?>" class="cover-logo" alt="Logo">
  <?php else: ?>
    <div style="font-size:48px;margin-bottom:20px">🏛</div>
  <?php endif; ?>
  <div class="cover-mun"><?= e($mun['name'] ?? '') ?></div>
  <div class="cover-star">⭐</div>
  <div class="cover-title">Επιβράβευση<br>Εθελοντισμού</div>
  <div class="cover-subtitle"><?= (int) $year ?> · Ετήσια Αναγνώριση Εθελοντικών Ομάδων</div>
  <div class="cover-year"><?= (int) $year ?></div>
</div>

<!-- ══ Awards page ═════════════════════════════════ -->
<div class="page">
  <div class="page-header">
    <span class="ph-icon">🏆</span>
    <div>
      <h2>Βραβεία <?= (int) $year ?></h2>
      <div class="ph-sub"><?= e($mun['name'] ?? '') ?> · Εθελοντικές Ομάδες</div>
    </div>
  </div>

  <div class="awards-grid">
    <?php
    $awardDefs = [
      'best_contribution' => ['label' => 'Καλύτερη Συνεισφορά', 'sub' => 'Ώρες εθελοντισμού', 'icon' => '💙', 'cls' => 'aw-contribution'],
      'most_active'       => ['label' => 'Πιο Δραστήρια Ομάδα', 'sub' => 'Αριθμός δράσεων',    'icon' => '💚', 'cls' => 'aw-active'],
      'most_consistent'   => ['label' => 'Μεγαλύτερη Συνέπεια', 'sub' => 'Συνέπεια παρουσίας', 'icon' => '💜', 'cls' => 'aw-consistent'],
      'fastest_response'  => ['label' => 'Ταχύτερη Απόκριση',   'sub' => 'Χρόνος απόκρισης',   'icon' => '🧡', 'cls' => 'aw-fastest'],
    ];
    foreach ($awardDefs as $key => $def):
      $w = $awards[$key] ?? null;
    ?>
    <div class="award-card <?= e($def['cls']) ?> <?= !$w ? 'empty' : '' ?>">
      <div class="award-card-header">
        <?= $def['icon'] ?> <?= e($def['label']) ?>
      </div>
      <div class="award-card-body">
        <?php if ($w): ?>
          <div class="award-team"><?= e($w['team_name']) ?></div>
          <div class="award-stats">
            <div class="award-stat">
              <strong><?= (int) ($w['events_count'] ?? 0) ?></strong>
              Δράσεις
            </div>
            <div class="award-stat">
              <strong><?= number_format((float) ($w['volunteer_hours'] ?? 0), 1) ?></strong>
              Ώρες εθελ.
            </div>
            <?php if (isset($w['consistency_score']) && $w['consistency_score'] !== null): ?>
            <div class="award-stat">
              <strong><?= number_format((float) $w['consistency_score'], 1) ?>%</strong>
              Συνέπεια
            </div>
            <?php endif; ?>
            <?php if (isset($w['avg_response_minutes']) && $w['avg_response_minutes'] !== null): ?>
            <div class="award-stat">
              <strong><?= (int) $w['avg_response_minutes'] ?> λεπτά</strong>
              Απόκριση
            </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="award-team">Δεν υπάρχουν αρκετά δεδομένα</div>
          <div style="font-size:12px;color:#9ca3af">Απαιτούνται ολοκληρωμένες δράσεις.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Ranking table -->
  <div style="margin-top:28px">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#6b7280;border-bottom:2px solid #e5e7eb;padding-bottom:8px;margin-bottom:14px">
      Πλήρης Κατάταξη <?= (int) $year ?>
    </div>
    <table class="rank-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Ομάδα</th>
          <th>Τύπος</th>
          <th class="text-center">Δράσεις</th>
          <th class="text-center">Ώρες εθελ.</th>
          <th class="text-center">Εθελοντές</th>
          <th class="text-center">Συνέπεια</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$ranking): ?>
          <tr><td colspan="7" style="color:#9ca3af;text-align:center;padding:20px">Δεν υπάρχουν δεδομένα για το <?= (int) $year ?>.</td></tr>
        <?php endif; ?>
        <?php foreach ($ranking as $i => $r):
          $pos = $i + 1;
          $cls = $pos === 1 ? 'rank-1' : ($pos === 2 ? 'rank-2' : ($pos === 3 ? 'rank-3' : 'rank-n'));
          $medal = $pos === 1 ? '🥇' : ($pos === 2 ? '🥈' : ($pos === 3 ? '🥉' : ''));
        ?>
          <tr>
            <td>
              <div class="rank-num <?= $cls ?>"><?= $medal ?: $pos ?></div>
            </td>
            <td><strong><?= e($r['team_name']) ?></strong></td>
            <td style="font-size:11px;color:#6b7280"><?= e($r['team_type'] ?? '—') ?></td>
            <td style="text-align:center"><?= (int) ($r['events_count'] ?? 0) ?></td>
            <td style="text-align:center"><?= number_format((float) ($r['volunteer_hours'] ?? 0), 1) ?></td>
            <td style="text-align:center"><?= (int) ($r['present_volunteers'] ?? 0) ?></td>
            <td style="text-align:center">
              <?= isset($r['consistency_score']) && $r['consistency_score'] !== null
                    ? number_format((float) $r['consistency_score'], 1) . '%' : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="page-footer">
    <?= e($mun['name'] ?? '') ?> · Εθελοντικός Συντονισμός · <?= (int) $year ?> ·
    Παράχθηκε από το SynDrasi · <?= date('d/m/Y') ?>
  </div>
</div><!-- /page -->

</body>
</html>
