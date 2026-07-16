<?php
$terms = authority_context(current_municipality_id());
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0"><?= e(t('team/members/index.001', 'Μέλη Ομάδας')) ?></h1>
  <a href="<?= e(url('/team/members/create')) ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-person-plus me-1"></i><?= e(t('team/members/index.002', 'Νέο Μέλος')) ?>
  </a>
</div>
<p class="text-muted small"><?= e(t('team/members/index.003', 'Κατάλογος εθελοντών της ομάδας')) ?> <strong><?= e($team['name'] ?? '') ?></strong><?= e(t('team/members/index.022', '. Τα μέλη ορίζονται κατά τη δήλωση συμμετοχής σε')) ?> <?= e($eventPluralLc) ?>.</p>
<?php if (empty($canManageAssistants)): ?>
  <div class="alert alert-info small">
    <?= e(t('team/members/index.005', 'Έχετε πλήρη πρόσβαση διαχείρισης ομάδας ως Βοηθός Αρχηγού. Ο ορισμός ή η αφαίρεση άλλων βοηθών γίνεται μόνο από τον αρχηγό της ομάδας.')) ?>
  </div>
<?php endif; ?>

<?php if (!$members): ?>
  <div class="alert alert-info">
    <?= e(t('team/members/index.006', 'Δεν έχουν καταχωρηθεί μέλη ακόμα.')) ?>
    <a href="<?= e(url('/team/members/create')) ?>"><?= e(t('team/members/index.007', 'Προσθέστε το πρώτο μέλος.')) ?></a>
  </div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th><?= e(t('team/members/index.008', 'Ονοματεπώνυμο')) ?></th>
          <th><?= e(t('team/members/index.009', 'Τηλέφωνο')) ?></th>
          <th>Email</th>
          <th><?= e(t('team/members/index.010', 'Ρόλος / Ειδικότητα')) ?></th>
          <th><?= e(t('team/members/index.011', 'ΑΜ Πολ. Προστ.')) ?></th>
          <th><?= e(t('team/members/index.012', 'Κατάσταση')) ?></th>
          <th><?= e(t('team/members/index.013', 'Πρόσβαση')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
          <tr class="<?= $m['status'] === 'inactive' ? 'text-muted' : '' ?>">
            <td>
              <?php if (!empty($m['is_team_admin'])): ?>
                <i class="bi bi-shield-fill <?= !empty($m['is_assistant_admin']) ? 'text-success' : 'text-primary' ?> me-1" title="<?= !empty($m['is_assistant_admin']) ? 'Βοηθός Αρχηγού' : 'Αρχηγός Ομάδας' ?>"></i>
              <?php endif; ?>
              <strong><?= e($m['full_name']) ?></strong>
              <?php if (!empty($m['is_assistant_admin'])): ?>
                <span class="badge bg-success ms-1"><?= e(t('team/members/index.014', 'Βοηθός Αρχηγού')) ?></span>
              <?php elseif (!empty($m['is_team_admin'])): ?>
                <span class="badge bg-primary ms-1"><?= e(t('team/members/index.015', 'Αρχηγός')) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e($m['phone']) ?></td>
            <td class="small"><?= e($m['email'] ?? '—') ?></td>
            <td class="small"><?= e($m['role_in_team'] ?? '—') ?></td>
            <td class="small"><?= e($m['civil_protection_registry_no'] ?? '—') ?></td>
            <td>
              <?php if ($m['status'] === 'active'): ?>
                <span class="badge bg-success"><?= e(t('team/members/index.016', 'Ενεργό')) ?></span>
              <?php else: ?>
                <span class="badge bg-secondary"><?= e(t('team/members/index.017', 'Ανενεργό')) ?></span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($m['is_assistant_admin'])): ?>
                <span class="badge text-bg-<?= ($m['login_status'] ?? '') === 'active' ? 'success' : 'secondary' ?>">
                  <?= ($m['login_status'] ?? '') === 'active' ? 'Login ενεργό' : 'Login ανενεργό' ?>
                </span>
              <?php elseif (!empty($m['is_team_admin'])): ?>
                <span class="badge text-bg-primary"><?= e(t('team/members/index.018', 'Κύρια πρόσβαση')) ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <a href="<?= e(url('/team/members/' . $m['id'] . '/stats')) ?>" class="btn btn-sm btn-outline-info" title="<?= e(t('team/members/index.019', 'Στατιστικά')) ?>">
                <i class="bi bi-bar-chart-line"></i>
              </a>
              <a href="<?= e(url('/team/members/' . $m['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i>
              </a>
              <?php if (!empty($canManageAssistants) && $m['status'] === 'active' && empty($m['is_team_admin'])): ?>
                <form method="post" action="<?= e(url('/team/members/' . $m['id'] . '/assistant/promote')) ?>" class="d-inline"
                      onsubmit="return confirm('Να οριστεί ως Βοηθός Αρχηγού; Θα λάβει email πρόσκλησης.');">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-success" title="<?= e(t('team/members/index.020', 'Ορισμός Βοηθού Αρχηγού')) ?>">
                    <i class="bi bi-shield-plus"></i>
                  </button>
                </form>
              <?php endif; ?>
              <?php if (!empty($canManageAssistants) && !empty($m['is_assistant_admin'])): ?>
                <form method="post" action="<?= e(url('/team/members/' . $m['id'] . '/assistant/revoke')) ?>" class="d-inline"
                      onsubmit="return confirm('Να αφαιρεθεί η πρόσβαση Βοηθού Αρχηγού;');">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e(t('team/members/index.021', 'Αφαίρεση Βοηθού Αρχηγού')) ?>">
                    <i class="bi bi-shield-x"></i>
                  </button>
                </form>
              <?php endif; ?>
              <?php if (empty($m['is_team_admin']) || !empty($m['is_assistant_admin'])): ?>
                <form method="post" action="<?= e(url('/team/members/' . $m['id'] . '/toggle')) ?>" class="d-inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm <?= $m['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                    title="<?= $m['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>">
                    <i class="bi <?= $m['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
