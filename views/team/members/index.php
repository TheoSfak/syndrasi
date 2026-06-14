<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Μέλη Ομάδας</h1>
  <a href="<?= e(url('/team/members/create')) ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-person-plus me-1"></i>Νέο Μέλος
  </a>
</div>
<p class="text-muted small">Κατάλογος εθελοντών της ομάδας <strong><?= e($team['name'] ?? '') ?></strong>. Τα μέλη ορίζονται κατά τη δήλωση συμμετοχής σε δράσεις.</p>

<?php if (!$members): ?>
  <div class="alert alert-info">
    Δεν έχουν καταχωρηθεί μέλη ακόμα.
    <a href="<?= e(url('/team/members/create')) ?>">Προσθέστε το πρώτο μέλος.</a>
  </div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Ονοματεπώνυμο</th>
          <th>Τηλέφωνο</th>
          <th>Email</th>
          <th>Ρόλος / Ειδικότητα</th>
          <th>ΑΜ Πολ. Προστ.</th>
          <th>Κατάσταση</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
          <tr class="<?= $m['status'] === 'inactive' ? 'text-muted' : '' ?>">
            <td>
              <?php if ($m['is_team_admin']): ?>
                <i class="bi bi-shield-fill text-primary me-1" title="Διαχειριστής Ομάδας"></i>
              <?php endif; ?>
              <strong><?= e($m['full_name']) ?></strong>
              <?php if ($m['is_team_admin']): ?><span class="badge bg-primary ms-1">Διαχειριστής</span><?php endif; ?>
            </td>
            <td><?= e($m['phone']) ?></td>
            <td class="small"><?= e($m['email'] ?? '—') ?></td>
            <td class="small"><?= e($m['role_in_team'] ?? '—') ?></td>
            <td class="small"><?= e($m['civil_protection_registry_no'] ?? '—') ?></td>
            <td>
              <?php if ($m['status'] === 'active'): ?>
                <span class="badge bg-success">Ενεργό</span>
              <?php else: ?>
                <span class="badge bg-secondary">Ανενεργό</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <a href="<?= e(url('/team/members/' . $m['id'] . '/stats')) ?>" class="btn btn-sm btn-outline-info" title="Στατιστικά">
                <i class="bi bi-bar-chart-line"></i>
              </a>
              <a href="<?= e(url('/team/members/' . $m['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i>
              </a>
              <?php if (!$m['is_team_admin']): ?>
                <form method="post" action="<?= e(url('/team/members/' . $m['id'] . '/toggle')) ?>" class="d-inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm <?= $m['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                    title="<?= $m['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>">
                    <i class="bi <?= $m['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle