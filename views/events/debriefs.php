<?php
/* views/events/debriefs.php
 * Municipality admin: all debriefs for a completed event + aggregate stats.
 * $event         — the event row
 * $debriefs      — array of debrief rows (with team_name, submitted_by_name)
 * $stats         — aggregate: debrief_count, total_volunteers, total_hours, total_incidents, avg_rating
 * $approvedCount — how many teams were approved (for completion rate)
 */
$completionRate = $approvedCount > 0
    ? round(($stats['debrief_count'] ?? 0) / $approvedCount * 100)
    : 0;
?>
<div class="container py-4">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= url('/events') ?>">Δράσεις</a></li>
      <li class="breadcrumb-item"><a href="<?= url('/events/' . $event['id']) ?>"><?= e($event['title']) ?></a></li>
      <li class="breadcrumb-item active">Post-Event Debriefs</li>
    </ol>
  </nav>

  <div class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-clipboard2-data-fill fs-3 text-primary"></i>
    <div>
      <h4 class="mb-0 fw-bold">Post-Event Debriefs</h4>
      <div class="text-muted small"><?= e($event['title']) ?></div>
    </div>
  </div>

  <!-- Aggregate stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body">
          <div class="fs-1 fw-bold text-primary"><?= (int)($stats['debrief_count'] ?? 0) ?>/<?= $approvedCount ?></div>
          <div class="text-muted small">Ποσοστό Ολοκλήρωσης</div>
          <div class="progress mt-2" style="height:6px">
            <div class="progress-bar bg-primary" style="width:<?= $completionRate ?>%"></div>
          </div>
          <div class="text-muted small mt-1"><?= $completionRate ?>%</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body">
          <div class="fs-1 fw-bold text-success"><?= number_format((float)($stats['total_hours'] ?? 0), 1) ?></div>
          <div class="text-muted small">Συνολικές Ώρες</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body">
          <div class="fs-1 fw-bold text-danger"><?= (int)($stats['total_incidents'] ?? 0) ?></div>
          <div class="text-muted small">Συνολικά Συμβάντα</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body">
          <?php $avg = (float)($stats['avg_rating'] ?? 0); ?>
          <div class="fs-1 fw-bold text-warning">
            <?= $avg > 0 ? number_format($avg, 1) : '—' ?>
            <?php if ($avg > 0): ?>
              <i class="bi bi-star-fill fs-4"></i>
            <?php endif ?>
          </div>
          <div class="text-muted small">Μέση Βαθμολογία Οργάνωσης</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Municipality after-action report -->
  <div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-header bg-white fw-semibold">
      <i class="bi bi-clipboard-check me-1"></i> Απολογισμός Δήμου (After-Action)
      <?php if (!empty($muniReport)): ?><span class="badge text-bg-success ms-1">Αποθηκευμένος</span><?php endif; ?>
    </div>
    <div class="card-body">
      <form method="post" action="<?= e(url('/events/' . $event['id'] . '/municipality-debrief')) ?>">
        <?= csrf_field() ?>
        <div class="d-flex flex-wrap gap-3 mb-3 small text-muted">
          <span>Σύνολα από αναφορές ομάδων:</span>
          <span><strong><?= (int) ($teamAgg['i'] ?? 0) ?></strong> περιστατικά</span>
          <span><strong><?= (int) ($teamAgg['t'] ?? 0) ?></strong> διακομιδές</span>
          <span><strong><?= (int) ($teamAgg['f'] ?? 0) ?></strong> πρώτες βοήθειες</span>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Συνολική αξιολόγηση / Σύνοψη</label>
          <textarea name="summary" class="form-control" rows="3" placeholder="Πώς πήγε η δράση συνολικά, συντονισμός, ανταπόκριση ομάδων…"><?= e($muniReport['summary'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Συμπεράσματα &amp; βελτιώσεις (lessons learned)</label>
          <textarea name="notes" class="form-control" rows="3" placeholder="Τι να βελτιωθεί, ενέργειες για την επόμενη φορά…"><?= e($muniReport['notes'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Αποθήκευση Απολογισμού</button>
        <?php if (!empty($muniReport)): ?>
          <span class="small text-muted ms-2">Τελευταία ενημέρωση: <?= e(gr_datetime($muniReport['updated_at'] ?? $muniReport['created_at'])) ?></span>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Debriefs list -->
  <?php if (empty($debriefs)): ?>
    <div class="alert alert-info d-flex align-items-center gap-2">
      <i class="bi bi-info-circle-fill"></i>
      Καμία ομάδα δεν έχει υποβάλει debrief ακόμα.
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($debriefs as $db): ?>
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2 bg-light">
              <i class="bi bi-people-fill text-primary"></i>
              <strong><?= e($db['team_name']) ?></strong>
              <span class="ms-auto text-muted small">
                από <?= e($db['submitted_by_name']) ?>
                · <?= date('d/m/Y H:i', strtotime($db['submitted_at'])) ?>
                <?php if ($db['updated_at']): ?>
                  <span class="text-info">(επεξεργάστηκε <?= date('d/m/Y H:i', strtotime($db['updated_at'])) ?>)</span>
                <?php endif ?>
              </span>
            </div>
            <div class="card-body">

              <!-- Numbers row -->
              <div class="row g-3 mb-3">
                <div class="col-sm-4">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-person-check-fill text-success fs-5"></i>
                    <div>
                      <div class="fw-semibold"><?= (int)$db['actual_volunteers'] ?></div>
                      <div class="text-muted small">Εθελοντές</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-fill text-primary fs-5"></i>
                    <div>
                      <div class="fw-semibold"><?= number_format((float)$db['volunteer_hours'], 1) ?>h</div>
                      <div class="text-muted small">Ώρες</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                    <div>
                      <div class="fw-semibold"><?= (int)$db['incidents_count'] ?></div>
                      <div class="text-muted small">Συμβάντα</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Rating -->
              <div class="mb-3 d-flex align-items-center gap-2">
                <span class="text-muted small">Βαθμολογία:</span>
                <?php for ($s = 1; $s <= 5; $s++): ?>
                  <i class="bi bi-star<?= $s <= (int)$db['organization_rating'] ? '-fill' : '' ?>"
                     style="color:<?= $s <= (int)$db['organization_rating'] ? '#f59e0b' : '#d1d5db' ?>"></i>
                <?php endfor ?>
                <span class="fw-semibold"><?= (int)$db['organization_rating'] ?>/5</span>
              </div>

              <!-- Qualitative fields -->
              <div class="row g-3">
                <?php if ($db['what_went_well']): ?>
                  <div class="col-md-6">
                    <div class="p-3 rounded" style="background:#f0fdf4;border-left:3px solid #22c55e">
                      <div class="fw-semibold text-success small mb-1"><i class="bi bi-check-circle me-1"></i>Τι πήγε καλά</div>
                      <div class="small"><?= nl2br(e($db['what_went_well'])) ?></div>
                    </div>
                  </div>
                <?php endif ?>
                <?php if ($db['what_went_wrong']): ?>
                  <div class="col-md-6">
                    <div class="p-3 rounded" style="background:#fff7ed;border-left:3px solid #f97316">
                      <div class="fw-semibold text-warning small mb-1"><i class="bi bi-arrow-up-circle me-1"></i>Περιθώρια βελτίωσης</div>
                      <div class="small"><?= nl2br(e($db['what_went_wrong'])) ?></div>
                    </div>
                  </div>
                <?php endif ?>
                <?php if ($db['incidents_description']): ?>
                  <div class="col-12">
                    <div class="p-3 rounded" style="background:#fef2f2;border-left:3px solid #ef4444">
                      <div class="fw-semibold text-danger small mb-1"><i class="bi bi-exclamation-circle me-1"></i>Περιγραφή Συμβάντων</div>
                      <div class="small"><?= nl2br(e($db['incidents_description'])) ?></div>
                    </div>
                  </div>
                <?php endif ?>
                <?php if ($db['comments']): ?>
                  <div class="col-12">
                    <div class="p-3 rounded bg-light">
                      <div class="fw-semibold text-muted small mb-1"><i class="bi bi-chat-left-text me-1"></i>Σχόλια</div>
                      <div class="small"><?= nl2br(e($db['comments'])) ?></div>
                    </div>
                  </div>
                <?php endif ?>
              </div>

            </div><!-- card-body -->
          </div><!-- card -->
        </div>
      <?php endforeach ?>
    </div><!-- row -->
  <?php endif ?>

</div>
