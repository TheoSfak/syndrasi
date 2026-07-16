<?php
$terms = $terms ?? authority_context();
$teamPlural = $terms['team_plural'] ?? 'Εθελοντικές Ομάδες';
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
$authorityLabel = $terms['official_name'] ?? $terms['label'] ?? 'φορέα';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
  <h1 class="h3 mb-0"><?= e($teamPlural) ?></h1>
  <a href="<?= e(url('/teams/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= e(t('teams/index.001', 'Νέα Ομάδα')) ?></a>
</div>
<p class="text-muted"><?= e(t('teams/index.017', 'Οι ομάδες που μπορούν να αναλάβουν')) ?> <?= e($eventPluralLc) ?> <?= e(t('teams/index.018', 'για')) ?> <?= e($authorityLabel) ?>.</p>

<div class="card shadow-sm">
  <?php if (!$teams): ?>
    <div class="card-body text-muted"><?= e(t('teams/index.003', 'Δεν έχουν καταχωρηθεί ακόμη ομάδες.')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th><?= e(t('teams/index.004', 'Ομάδα')) ?></th><th><?= e(t('teams/index.005', 'Τύπος')) ?></th><th><?= e(t('teams/index.006', 'Υπεύθυνος')) ?></th><th><?= e(t('teams/index.007', 'Επικοινωνία')) ?></th><th><?= e(t('teams/index.008', 'Δυνατότητες')) ?></th><th><?= e(t('teams/index.009', 'Κατάσταση')) ?></th><th class="text-end"><?= e(t('teams/index.010', 'Ενέργειες')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($teams as $t): ?>
            <tr>
              <td class="fw-semibold"><?= e($t['name']) ?></td>
              <td><?= e($t['type'] ?: '—') ?></td>
              <td><?= e($t['contact_person'] ?: '—') ?></td>
              <td class="small"><?= e($t['phone'] ?: '') ?><?= $t['phone'] && $t['email'] ? '<br>' : '' ?><?= e($t['email'] ?: '') ?></td>
              <td>
                <?php if ($t['has_vehicle']): ?><span class="badge text-bg-secondary"><i class="bi bi-truck"></i> <?= e(t('teams/index.011', 'Όχημα')) ?></span><?php endif; ?>
                <?php if ($t['has_medical_equipment']): ?><span class="badge text-bg-secondary"><i class="bi bi-heart-pulse"></i> <?= e(t('teams/index.012', 'Υγειον.')) ?></span><?php endif; ?>
                <?php if ($t['default_people_capacity']): ?><span class="badge text-bg-light text-dark"><?= (int) $t['default_people_capacity'] ?> <?= e(t('teams/index.013', 'άτομα')) ?></span><?php endif; ?>
                <?php if (!empty($t['telegram_chat_id'])): ?><span class="badge text-bg-info"><i class="bi bi-telegram"></i> Telegram</span><?php endif; ?>
              </td>
              <td><span class="badge text-bg-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $t['status'] === 'active' ? 'Ενεργή' : 'Ανενεργή' ?></span></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="<?= e(url('/teams/' . $t['id'] . '/edit')) ?>" title="<?= e(t('teams/index.014', 'Επεξεργασία')) ?>"><i class="bi bi-pencil"></i></a>
                  <a class="btn btn-outline-info" href="<?= e(url('/statistics/teams/' . $t['id'])) ?>" title="<?= e(t('teams/index.015', 'Στατιστικά')) ?>"><i class="bi bi-bar-chart"></i></a>
                  <a class="btn btn-outline-success" href="<?= e(url('/teams/' . $t['id'] . '/assistants')) ?>" title="<?= e(t('teams/index.016', 'Βοηθοί Αρχηγού')) ?>"><i class="bi bi-shield-check"></i></a>
                </div>
                <form method="post" class="d-inline" action="<?= e(url('/teams/' . $t['id'] . '/toggle')) ?>"
                      onsubmit="return confirm('Να αλλάξει η κατάσταση της ομάδας;')">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-<?= $t['status'] === 'active' ? 'danger' : 'success' ?>">
                    <?= $t['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
