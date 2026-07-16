<?php
$terms = authority_context(current_municipality_id());
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$eventPlural = $terms['event_plural'] ?? 'Δράσεις';
?>
<div class="d-flex align-items-center mb-3 gap-2">
  <a href="<?= e(url('/team/members')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h1 class="h3 mb-0"><?= e($member['full_name']) ?></h1>
    <p class="text-muted small mb-0">
      <?= e($member['role_in_team'] ?: ($member['specialty'] ?? 'Μέλος')) ?>
      <?= e(t('team/member_stats.001', '· Ομάδα:')) ?> <strong><?= e($teamName) ?></strong>
    </p>
  </div>
  <div class="ms-auto">
    <a href="<?= e(url('/team/members/' . $member['id'] . '/certificate')) ?>" target="_blank"
       class="btn btn-outline-success">
      <i class="bi bi-file-earmark-text me-1"></i><?= e(t('team/member_stats.002', 'Πιστοποιητικό PDF')) ?>
    </a>
  </div>
</div>

<!-- Stats cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card shadow-sm text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-primary"><?= (int) $stats['attended_events'] ?></div>
        <div class="small text-muted"><?= e($eventPlural) ?> <?= e(t('team/member_stats.003', '(παρών)')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-success"><?= number_format((float)($stats['total_hours'] ?? 0), 1) ?></div>
        <div class="small text-muted"><?= e(t('team/member_stats.004', 'Ώρες εθελοντισμού')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-secondary"><?= (int) $stats['total_events'] ?></div>
        <div class="small text-muted"><?= e(t('team/member_stats.005', 'Σύνολο εκχωρήσεων')) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-warning"><?= (int) $stats['times_commander'] ?></div>
        <div class="small text-muted"><?= e(t('team/member_stats.006', 'Φορές Υπεύθυνος')) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Participation history -->
<div class="card shadow-sm">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-clock-history me-1"></i> <?= e(t('team/member_stats.007', 'Ιστορικό Συμμετοχών')) ?></span>
    <span class="badge text-bg-secondary"><?= count($participations) ?></span>
  </div>
  <?php if ($participations): ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th><?= e($eventSingular) ?></th>
          <th><?= e(t('team/member_stats.008', 'Κατηγορία')) ?></th>
          <th><?= e(t('team/member_stats.009', 'Ημερομηνία')) ?></th>
          <th class="text-center"><?= e(t('team/member_stats.010', 'Παρουσία')) ?></th>
          <th class="text-center"><?= e(t('team/member_stats.011', 'Ώρες')) ?></th>
          <th class="text-center"><?= e(t('team/member_stats.012', 'Υπεύθυνος')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($participations as $p): ?>
        <tr>
          <td>
            <a href="<?= e(url('/events/' . $p['event_id'])) ?>" class="text-decoration-none fw-semibold">
              <?= e($p['event_title']) ?>
            </a>
          </td>
          <td class="small text-muted"><?= e($p['category_name'] ?? '—') ?></td>
          <td class="small"><?= e(gr_date($p['start_datetime'])) ?></td>
          <td class="text-center">
            <?php if ((int) $p['was_present']): ?>
              <span class="badge text-bg-success"><i class="bi bi-check"></i> <?= e(t('team/member_stats.013', 'Παρών')) ?></span>
            <?php else: ?>
              <span class="badge text-bg-secondary"><?= e(t('team/member_stats.014', 'Απών')) ?></span>
            <?php endif; ?>
          </td>
          <td class="text-center fw-semibold"><?= number_format((float)$p['hours'], 1) ?></td>
          <td class="text-center">
            <?= (int) $p['is_mission_commander'] ? '<i class="bi bi-star-fill text-warning"></i>' : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="card-body text-muted small">
    <i class="bi bi-info-circle me-1"></i><?= e(t('team/member_stats.015', 'Δεν υπάρχουν καταγεγραμμένες συμμετοχές ακόμη. Οι εγγραφές δημιουργούνται κατά την αρχειοθέτηση δράσεων.')) ?>
  </div>
  <?php endif; ?>
</div>
