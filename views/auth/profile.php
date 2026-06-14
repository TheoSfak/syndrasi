<h1 class="h3 mb-1">Το προφίλ μου</h1>
<p class="text-muted">Στοιχεία λογαριασμού και αλλαγή κωδικού πρόσβασης.</p>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-1"></i> Στοιχεία</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Όνομα</dt><dd class="col-sm-8"><?= e($user['name']) ?></dd>
          <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($user['email']) ?></dd>
          <dt class="col-sm-4">Τηλέφωνο</dt><dd class="col-sm-8"><?= e($user['phone'] ?: '—') ?></dd>
          <dt class="col-sm-4">Ρόλος</dt>
          <dd class="col-sm-8">
            <?php
            $roles = [
                'super_admin' => 'Διαχειριστής Πλατφόρμας',
                'municipality_admin' => 'Διαχειριστής Δήμου',
                'team_admin' => 'Υπεύθυνος Ομάδας',
                'event_operator' => 'Χειριστής Δράσεων',
            ];
            echo e(isset($roles[$user['role']]) ? $roles[$user['role']] : $user['role']);
            ?>
          </dd>
          <?php if ($municipality): ?>
            <dt class="col-sm-4">Δήμος</dt><dd class="col-sm-8"><?= e($municipality['name']) ?></dd>
          <?php endif; ?>
          <?php if ($team): ?>
            <dt class="col-sm-4">Ομάδα</dt><dd class="col-sm-8"><?= e($team['name']) ?></dd>
          <?php endif; ?>
          <dt class="col-sm-4">Τελευταία σύνδεση</dt><dd class="col-sm-8"><?= e(gr_datetime($user['last_login_at'])) ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-key me-1"></i> Αλλαγή κωδικού πρόσβασης</div>
      <div class="card-body">
        <form method="post" action="<?= e(url('/profile/password')) ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Τρέχων κωδικός</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Νέος κωδικός (τουλάχιστον 8 χαρακτήρες)</label>
            <input type="password" name="new_password" class="form-control" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Επιβεβαίωση νέου κωδικού</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
          </div>
          <button class="btn btn-primary" type="submit">Αποθήκευση</button>
        </form>
      </div>
    </div>
  </div>
</div>
