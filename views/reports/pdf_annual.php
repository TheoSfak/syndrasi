<?php
$terms = authority_context((int) ($mun['id'] ?? current_municipality_id()));
$orgName = $terms['official_name'] ?? ($mun['name'] ?? 'Φορέας');
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
$eventPluralLc = $terms['event_plural_lc'] ?? mb_strtolower($eventPlural, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle) ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; color: #1a1a2e; font-size: 13px; }

/* ── No-print controls ── */
.no-print {
  position: fixed; top: 16px; right: 16px; z-index: 999;
  display: flex; gap: 8px;
}
.btn-print {
  background: #0f766e; color: #fff; border: none;
  padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
}
.btn-back {
  background: #fff; color: #374151; border: 1.5px solid #d1d5db;
  padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 500;
  cursor: pointer; text-decoration: none; display: inline-block;
}

/* ── Pages ── */
.page {
  width: 210mm; min-height: 297mm;
  margin: 20px auto; background: #fff;
  border-radius: 4px; box-shadow: 0 4px 24px rgba(0,0,0,.12);
  page-break-after: always; overflow: hidden;
  display: flex; flex-direction: column;
}
.page:last-child { page-break-after: avoid; }

/* ── Cover page ── */
.cover {
  background: linear-gradient(145deg, #0f766e 0%, #064e3b 100%);
  color: #fff; flex: 1;
  display: flex; flex-direction: column; justify-content: space-between;
  padding: 48px 56px;
}
.cover-top { display: flex; align-items: center; gap: 20px; }
.cover-top img { max-height: 64px; max-width: 140px; object-fit: contain; filter: brightness(0) invert(1); }
.cover-mun { font-size: 15px; font-weight: 700; letter-spacing: .02em; }
.cover-sub { font-size: 11px; opacity: .7; margin-top: 3px; }
.cover-body { text-align: center; }
.cover-label { font-size: 11px; letter-spacing: 4px; text-transform: uppercase; opacity: .7; margin-bottom: 12px; }
.cover-year  { font-size: 96px; font-weight: 900; line-height: 1; letter-spacing: -4px; opacity: .15; position: absolute; left: 50%; transform: translateX(-50%); }
.cover-title { font-size: 34px; font-weight: 800; line-height: 1.15; position: relative; }
.cover-desc  { font-size: 14px; opacity: .75; margin-top: 10px; }
.cover-stats { display: flex; justify-content: center; gap: 40px; }
.cover-stat  { text-align: center; }
.cover-stat .v { font-size: 36px; font-weight: 900; }
.cover-stat .l { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; opacity: .7; margin-top: 4px; }
.cover-footer { font-size: 11px; opacity: .55; text-align: right; }

/* ── Section pages ── */
.page-inner { padding: 40px 48px; flex: 1; display: flex; flex-direction: column; }
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 2.5px solid #0f766e; padding-bottom: 12px; margin-bottom: 24px;
}
.page-header .section-title { font-size: 16px; font-weight: 800; color: #0f766e; }
.page-header .page-meta     { font-size: 10px; color: #9ca3af; }

/* ── Summary cards ── */
.summary-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 28px; }
.s-card {
  border: 1.5px solid #d1fae5; border-radius: 10px; background: #f0fdf4;
  padding: 14px 16px; text-align: center;
}
.s-card .v { font-size: 28px; font-weight: 900; color: #065f46; line-height: 1; }
.s-card .l { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-top: 4px; }

/* ── Monthly bar chart ── */
.chart-wrap { margin-bottom: 28px; }
.chart-title { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 10px; text-transform: uppercase; letter-spacing: .05em; }
.bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
.bar-row .bar-label { width: 28px; font-size: 10px; color: #6b7280; text-align: right; flex-shrink: 0; }
.bar-track { flex: 1; background: #f1f5f9; border-radius: 3px; height: 16px; position: relative; }
.bar-fill  { background: #0f766e; border-radius: 3px; height: 100%; transition: width .3s; }
.bar-row .bar-val { width: 20px; font-size: 10px; color: #374151; font-weight: 600; }

/* ── Category breakdown ── */
.cat-row { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid #f1f5f9; }
.cat-row:last-child { border-bottom: none; }
.cat-name { flex: 1; font-size: 12px; color: #374151; }
.cat-bar-wrap { width: 100px; background: #f1f5f9; border-radius: 3px; height: 8px; }
.cat-bar { background: #0f766e; height: 8px; border-radius: 3px; }
.cat-cnt  { font-size: 12px; font-weight: 700; color: #065f46; min-width: 20px; text-align: right; }

/* ── Events table ── */
.report-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.report-table th {
  background: #f0fdf4; color: #065f46; font-weight: 700;
  padding: 7px 8px; border-bottom: 1.5px solid #d1fae5;
  text-align: left; white-space: nowrap;
}
.report-table td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
.report-table tr:last-child td { border-bottom: none; }
.report-table tr:nth-child(even) td { background: #fafafa; }
.tag { display: inline-block; background: #e0f2fe; color: #0369a1; border-radius: 4px; font-size: 9px; padding: 1px 5px; }

/* ── Team leaderboard ── */
.team-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.team-table th { background: #f0fdf4; color: #065f46; font-weight: 700; padding: 7px 10px; border-bottom: 1.5px solid #d1fae5; text-align: left; }
.team-table td { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; }
.rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 10px; font-weight: 800; }
.rank-1 { background: #fef08a; color: #92400e; }
.rank-2 { background: #e2e8f0; color: #475569; }
.rank-3 { background: #fed7aa; color: #9a3412; }
.rank-n { background: #f1f5f9; color: #64748b; }

/* ── Footer band ── */
.report-footer {
  margin-top: auto; padding-top: 16px;
  border-top: 1px solid #e5e7eb;
  display: flex; justify-content: space-between; align-items: center;
  font-size: 10px; color: #9ca3af;
}

/* ── Print ── */
@media print {
  body { background: #fff; }
  .no-print { display: none !important; }
  .page { margin: 0; box-shadow: none; border-radius: 0; width: 100%; min-height: 0; }
  @page { size: A4 portrait; margin: 0; }
}
</style>
</head>
<body>

<div class="no-print">
  <a href="javascript:history.back()" class="btn-back">&#8592; Πίσω</a>
  <button class="btn-print" onclick="window.print()">&#128438; Εκτύπωση / PDF</button>
</div>

<?php
$monthLabels = ['Ιαν','Φεβ','Μαρ','Απρ','Μάι','Ιουν','Ιουλ','Αυγ','Σεπ','Οκτ','Νοε','Δεκ'];
$maxMonth = max(array_values($monthly)) ?: 1;
?>

<!-- ══════════════════════════════════════════════
     PAGE 1: COVER
     ══════════════════════════════════════════════ -->
<div class="page">
  <div class="cover" style="position:relative">

    <!-- Top: logo + municipality -->
    <div class="cover-top">
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="logo">
      <?php else: ?>
        <span style="font-size:48px;opacity:.8">&#127963;</span>
      <?php endif; ?>
      <div>
        <div class="cover-mun"><?= e($orgName) ?></div>
        <div class="cover-sub">Εθελοντικός Συντονισμός &amp; Πολιτική Προστασία</div>
      </div>
    </div>

    <!-- Centre: report title -->
    <div class="cover-body" style="position:relative;padding:40px 0">
      <div class="cover-label"><?= e(t('reports/pdf_annual.001', 'Ετήσια Έκθεση')) ?></div>
      <div class="cover-year"><?= $year ?></div>
      <div class="cover-title"><?= e(t('reports/pdf_annual.002', 'Εθελοντισμός')) ?><?= "\n" ?><?= $year ?></div>
      <div class="cover-desc"><?= e(t('reports/pdf_annual.029', 'Ετήσια έκθεση')) ?> <?= e($eventPluralLc) ?><?= e(t('reports/pdf_annual.030', ', συμμετοχής ομάδων')) ?><br><?= e(t('reports/pdf_annual.004', 'και εθελοντικών ωρών')) ?></div>
    </div>

    <!-- Summary stats -->
    <div class="cover-stats">
      <div class="cover-stat">
        <div class="v"><?= $totalEvents ?></div>
        <div class="l"><?= e($eventPlural) ?></div>
      </div>
      <div class="cover-stat">
        <div class="v"><?= $totalPeople ?: '—' ?></div>
        <div class="l"><?= e(t('reports/pdf_annual.005', 'Εθελοντές')) ?></div>
      </div>
      <div class="cover-stat">
        <div class="v"><?= number_format($totalHours, 0) ?: '—' ?></div>
        <div class="l"><?= e(t('reports/pdf_annual.006', 'Ώρες')) ?></div>
      </div>
      <div class="cover-stat">
        <div class="v"><?= $totalTeamSlots ?></div>
        <div class="l"><?= e(t('reports/pdf_annual.007', 'Συμμετοχές ομάδων')) ?></div>
      </div>
    </div>

    <div class="cover-footer">
      <?= e(t('reports/pdf_annual.031', 'Εκδόθηκε:')) ?> <?= e(gr_date(date('Y-m-d'))) ?> &nbsp;·&nbsp; SynDrasi
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════
     PAGE 2: SUMMARY + MONTHLY CHART + CATEGORIES
     ══════════════════════════════════════════════ -->
<div class="page">
  <div class="page-inner">
    <div class="page-header">
      <div class="section-title"><i>&#128202;</i> <?= e(t('reports/pdf_annual.008', 'Συνολική Εικόνα')) ?> <?= $year ?></div>
      <div class="page-meta"><?= e($orgName) ?> &nbsp;·&nbsp; <?= $year ?></div>
    </div>

    <!-- KPI cards -->
    <div class="summary-grid">
      <div class="s-card">
        <div class="v"><?= $totalEvents ?></div>
        <div class="l"><?= e($eventPlural) ?></div>
      </div>
      <div class="s-card">
        <div class="v"><?= $totalTeamSlots ?></div>
        <div class="l"><?= e(t('reports/pdf_annual.007', 'Συμμετοχές ομάδων')) ?></div>
      </div>
      <div class="s-card">
        <div class="v"><?= $totalPeople ?: '—' ?></div>
        <div class="l"><?= e(t('reports/pdf_annual.005', 'Εθελοντές')) ?></div>
      </div>
      <div class="s-card">
        <div class="v"><?= number_format($totalHours, 1) ?></div>
        <div class="l"><?= e(t('reports/pdf_annual.009', 'Ώρες εθελοντισμού')) ?></div>
      </div>
    </div>

    <!-- Monthly chart -->
    <div class="chart-wrap">
      <div class="chart-title">&#128197; <?= e($eventPlural) ?> <?= e(t('reports/pdf_annual.032', 'ανά μήνα')) ?></div>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <?php $cnt = $monthly[$m]; $pct = $maxMonth > 0 ? round(($cnt / $maxMonth) * 100) : 0; ?>
        <div class="bar-row">
          <div class="bar-label"><?= $monthLabels[$m - 1] ?></div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="bar-val"><?= $cnt ?: '' ?></div>
        </div>
      <?php endfor; ?>
    </div>

    <!-- Category breakdown -->
    <?php if ($byCategory): ?>
    <div>
      <div class="chart-title" style="margin-bottom:8px">&#127991; Κατανομή ανά κατηγορία</div>
      <?php $maxCat = max(array_column($byCategory,'cnt')) ?: 1; ?>
      <?php foreach ($byCategory as $cat): ?>
        <div class="cat-row">
          <div class="cat-name"><?= e($cat['category_name'] ?? 'Χωρίς κατηγορία') ?></div>
          <div class="cat-bar-wrap">
            <div class="cat-bar" style="width:<?= round($cat['cnt'] / $maxCat * 100) ?>%"></div>
          </div>
          <div class="cat-cnt"><?= (int)$cat['cnt'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="report-footer">
      <span><?= e($orgName) ?> &nbsp;·&nbsp; Εθελοντικός Συντονισμός</span>
      <span><?= e(t('reports/pdf_annual.001', 'Ετήσια Έκθεση')) ?> <?= $year ?></span>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════
     PAGE 3: EVENTS TABLE
     ══════════════════════════════════════════════ -->
<div class="page">
  <div class="page-inner">
    <div class="page-header">
      <div class="section-title">&#128203; Κατάλογος <?= e($eventPlural) ?> <?= $year ?></div>
      <div class="page-meta"><?= $totalEvents ?> <?= e($eventPluralLc) ?></div>
    </div>

    <?php if ($events): ?>
    <table class="report-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?= e(t('reports/pdf_annual.010', 'Τίτλος')) ?></th>
          <th><?= e(t('reports/pdf_annual.011', 'Κατηγορία')) ?></th>
          <th><?= e(t('reports/pdf_annual.012', 'Ημερομηνία')) ?></th>
          <th style="text-align:center"><?= e(t('reports/pdf_annual.013', 'Ομάδες')) ?></th>
          <th style="text-align:center"><?= e(t('reports/pdf_annual.005', 'Εθελοντές')) ?></th>
          <th style="text-align:center"><?= e(t('reports/pdf_annual.014', 'Κατάσταση')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $i => $ev): ?>
        <tr>
          <td style="color:#9ca3af"><?= $i + 1 ?></td>
          <td style="font-weight:600;max-width:180px"><?= e($ev['title']) ?></td>
          <td><?php if ($ev['category_name']): ?><span class="tag"><?= e($ev['category_name']) ?></span><?php endif; ?></td>
          <td style="white-space:nowrap"><?= e(gr_date($ev['start_datetime'])) ?></td>
          <td style="text-align:center"><?= (int)$ev['team_count'] ?></td>
          <td style="text-align:center"><?= $ev['actual_people_sum'] !== null ? (int)$ev['actual_people_sum'] : '—' ?></td>
          <td style="text-align:center">
            <?php if ($ev['status'] === 'completed'): ?>
              <span style="color:#065f46;font-weight:700">&#10003;</span>
            <?php else: ?>
              <span style="color:#6b7280"><?= e(t('reports/pdf_annual.015', 'Αρχ.')) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p style="color:#9ca3af;text-align:center;margin-top:40px"><?= e(t('reports/pdf_annual.033', 'Δεν υπάρχουν ολοκληρωμένες')) ?> <?= e($eventPluralLc) ?> <?= e(t('reports/pdf_annual.034', 'για το')) ?> <?= $year ?>.</p>
    <?php endif; ?>

    <div class="report-footer">
      <span><?= e($orgName) ?></span>
      <span><?= e(t('reports/pdf_annual.001', 'Ετήσια Έκθεση')) ?> <?= $year ?> &nbsp;·&nbsp; σελ. 3</span>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════
     PAGE 4: TEAM LEADERBOARD
     ══════════════════════════════════════════════ -->
<div class="page">
  <div class="page-inner">
    <div class="page-header">
      <div class="section-title">&#127942; Κατάταξη Ομάδων <?= $year ?></div>
      <div class="page-meta"><?= e(t('reports/pdf_annual.017', 'Βάσει ωρών εθελοντισμού')) ?></div>
    </div>

    <?php if ($teamLeaderboard): ?>
    <table class="team-table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th><?= e(t('reports/pdf_annual.018', 'Ομάδα')) ?></th>
          <th style="text-align:center"><?= e($eventPlural) ?></th>
          <th style="text-align:center"><?= e(t('reports/pdf_annual.019', 'Παρουσίες μελών')) ?></th>
          <th style="text-align:right"><?= e(t('reports/pdf_annual.006', 'Ώρες')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($teamLeaderboard as $i => $t): ?>
        <tr>
          <td>
            <?php $rank = $i + 1; ?>
            <span class="rank-badge rank-<?= $rank <= 3 ? $rank : 'n' ?>"><?= $rank ?></span>
          </td>
          <td style="font-weight:600"><?= e($t['team_name']) ?></td>
          <td style="text-align:center"><?= (int)$t['events_attended'] ?></td>
          <td style="text-align:center"><?= (int)$t['member_presences'] ?></td>
          <td style="text-align:right;font-weight:700;color:#065f46"><?= number_format((float)$t['total_hours'], 1) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Total row -->
    <div style="margin-top:16px;padding:10px 12px;background:#f0fdf4;border-radius:6px;display:flex;gap:24px;font-size:11px">
      <span><strong><?= e(t('reports/pdf_annual.020', 'Σύνολο ωρών:')) ?></strong> <?= number_format($totalHours, 1) ?> <?= e(t('reports/pdf_annual.021', 'ώρες')) ?></span>
      <span><strong><?= e(t('reports/pdf_annual.035', 'Σύνολο')) ?> <?= e($eventPluralLc) ?>:</strong> <?= $totalEvents ?></span>
      <span><strong><?= e(t('reports/pdf_annual.023', 'Συμμετοχές ομάδων:')) ?></strong> <?= $totalTeamSlots ?></span>
    </div>

    <?php else: ?>
      <p style="color:#9ca3af;text-align:center;margin-top:40px"><?= e(t('reports/pdf_annual.036', 'Δεν υπάρχουν δεδομένα συμμετοχής για το')) ?> <?= $year ?>.<br>
      <small><?= e(t('reports/pdf_annual.037', 'Τα δεδομένα ομάδων συμπληρώνονται κατά την αρχειοθέτηση')) ?> <?= e($eventPluralLc) ?>.</small></p>
    <?php endif; ?>

    <!-- Signature area -->
    <div style="margin-top:auto;padding-top:32px">
      <div style="display:flex;gap:40px">
        <div style="flex:1;text-align:center">
          <div style="border-top:1.5px solid #374151;margin:28px 16px 8px"></div>
          <div style="font-size:10px;color:#6b7280"><?= e(t('reports/pdf_annual.026', 'Υπεύθυνος Εθελοντισμού')) ?></div>
        </div>
        <div style="flex:1;text-align:center">
          <div style="border-top:1.5px solid #374151;margin:28px 16px 8px"></div>
          <div style="font-size:10px;color:#6b7280"><?= e(t('reports/pdf_annual.027', 'Εκπρόσωπος Φορέα')) ?></div>
        </div>
        <div style="flex:1;text-align:center">
          <div style="border-top:1.5px solid #374151;margin:28px 16px 8px"></div>
          <div style="font-size:10px;color:#6b7280"><?= e(t('reports/pdf_annual.028', 'Ημερομηνία / Σφραγίδα')) ?></div>
        </div>
      </div>
    </div>

    <div class="report-footer">
      <span><?= e($orgName) ?> &nbsp;·&nbsp; Εκδόθηκε <?= e(gr_date(date('Y-m-d'))) ?></span>
      <span><?= e(t('reports/pdf_annual.001', 'Ετήσια Έκθεση')) ?> <?= $year ?> &nbsp;·&nbsp; σελ. 4</span>
    </div>
  </div>
</div>

</body>
</html>
