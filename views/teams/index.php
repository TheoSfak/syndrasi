<div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
  <h1 class="h3 mb-0">Εθελοντικές Ομάδες</h1>
  <a href="<?= e(url('/teams/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Νέα Ομάδα</a>
</div>
<p class="text-muted">Οι εθελοντικές, διασωστικές και υγειονομικές ομάδες του δήμου.</p>

<div class="card shadow-sm">
  <?php if (!$teams): ?>
    <div class="card-body text-muted">Δεν έχουν καταχωρηθεί ακόμη ομάδες.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Ομάδα</th><th>Τύπος</th><th>Υπεύθυνος</th><th>Επικοινωνία</th><th>Δυνατότητες</th><th>Κατάσταση</th><th class="text-end">Ενέργειες</th></tr></thead>
        <tbody>
          <?php foreach ($teams as $t): ?>
            <tr>
              <td class="fw-semibold"><?= e($t['name']) ?></td>
              <td><?= e($t['type'] ?: '—') ?></td>
              <td><?= e($t['contact_person'] ?: '—') ?></td>
              <td class="small"><?= e($t['phone'] ?: '') ?><?= $t['phone'] && $t['email'] ? '<br>' : '' ?><?= e($t['email'] ?: '') ?></td>
              <td>
                <?php if ($t['has_vehicle']): ?><span class="badge text-bg-secondary"><i class="bi bi-truck"></i> Όχημα</span><?php endif; ?>
                <?php if ($t['has_medical_equipment']): ?><span class="badge text-bg-secondary"><i class="bi bi-heart-pulse"></i> Υγειον.</span><?php endif; ?>
                <?php if ($t['default_people_capacity']): ?><span class="badge text-bg-light text-dark"><?= (int) $t['default_people_capacity'] ?> άτομα</span><?php endif; ?>
              </td>
              <td><span class="badge text-bg-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $t['status'] === 'active' ? 'Ενεργή' : 'Ανενεργή' ?></span></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="<?= e(url('/teams/' . $t['id'] . '/edit')) ?>" title="Επεξεργασία"><i class="bi bi-pencil"></i></a>
                  <a class="btn btn-outline-info" href="<?= e(url('/statistics/teams/' . $t['id'])) ?>" title="Στατιστικά"><i class="bi bi-bar-chart"></i></a>
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
