<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h3 mb-0">Βοηθοί Αρχηγού</h1>
    <p class="text-muted small mb-0">Ομάδα: <strong><?= e($team['name'] ?? '') ?></strong></p>
  </div>
  <a href="<?= e(url('/teams')) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Ομάδες
  </a>
</div>

<div class="alert alert-light border small">
  Οι βοηθοί έχουν πρόσβαση ως υπεύθυνοι ομάδας, αλλά δεν μπορούν να ορίσουν ή να αφαιρέσουν άλλους βοηθούς.
  Από εδώ ο διαχειριστής δήμου μπορεί να αφαιρέσει πρόσβαση σε περίπτωση ανάγκης.
</div>

<div class="card shadow-sm">
  <?php if (empty($assistants)): ?>
    <div class="card-body text-muted">Δεν έχουν οριστεί Βοηθοί Αρχηγού για αυτή την ομάδα.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Μέλος</th>
            <th>Επικοινωνία</th>
            <th>Login</th>
            <th>Ορίστηκε</th>
            <th class="text-muted small">Τελ. σύνδεση</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assistants as $a): ?>
            <tr>
              <td>
                <div class="fw-semibold">
                  <i class="bi bi-shield-fill text-success me-1"></i><?= e($a['full_name']) ?>
                </div>
                <div class="small text-muted"><?= e($a['role_in_team'] ?: '—') ?></div>
              </td>
              <td class="small">
                <?= e($a['phone'] ?: '—') ?>
                <div class="text-muted"><?= e($a['email'] ?: ($a['login_email'] ?? '—')) ?></div>
              </td>
              <td>
                <span class="badge text-bg-<?= ($a['login_status'] ?? '') === 'active' ? 'success' : 'secondary' ?>">
                  <?= ($a['login_status'] ?? '') === 'active' ? 'Ενεργό' : 'Ανενεργό' ?>
                </span>
              </td>
              <td class="small text-muted"><?= e(gr_datetime($a['assistant_promoted_at'] ?? null)) ?></td>
              <td class="small text-muted"><?= e(gr_datetime($a['last_login_at'] ?? null)) ?></td>
              <td class="text-end">
                <form method="post" action="<?= e(url('/teams/' . $team['id'] . '/members/' . $a['id'] . '/assistant/revoke')) ?>" class="d-inline"
                      onsubmit="return confirm('Να αφαιρεθεί η πρόσβαση Βοηθού Αρχηγού;');">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-shield-x me-1"></i>Αφαίρεση
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
