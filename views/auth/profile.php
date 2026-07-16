<h1 class="h3 mb-1"><?= e(t('auth/profile.001', 'Το προφίλ μου')) ?></h1>
<p class="text-muted"><?= e(t('auth/profile.002', 'Στοιχεία λογαριασμού και αλλαγή κωδικού πρόσβασης.')) ?></p>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-1"></i> <?= e(t('auth/profile.003', 'Στοιχεία')) ?></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4"><?= e(t('auth/profile.004', 'Όνομα')) ?></dt><dd class="col-sm-8"><?= e($user['name']) ?></dd>
          <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($user['email']) ?></dd>
          <dt class="col-sm-4"><?= e(t('auth/profile.005', 'Τηλέφωνο')) ?></dt><dd class="col-sm-8"><?= e($user['phone'] ?: '—') ?></dd>
          <dt class="col-sm-4"><?= e(t('auth/profile.006', 'Ρόλος')) ?></dt>
          <dd class="col-sm-8">
            <?php
            $roles = [
                'super_admin' => 'Διαχειριστής Πλατφόρμας',
                'municipality_admin' => 'Διαχειριστής Φορέα',
                'team_admin' => 'Υπεύθυνος Ομάδας',
                'event_operator' => 'Χειριστής Επιχειρήσεων',
            ];
            echo e(isset($roles[$user['role']]) ? $roles[$user['role']] : $user['role']);
            ?>
          </dd>
          <?php if ($municipality): ?>
            <dt class="col-sm-4"><?= e(t('auth/profile.007', 'Φορέας')) ?></dt><dd class="col-sm-8"><?= e($municipality['official_name'] ?: $municipality['name']) ?></dd>
          <?php endif; ?>
          <?php if ($team): ?>
            <dt class="col-sm-4"><?= e(t('auth/profile.008', 'Ομάδα')) ?></dt><dd class="col-sm-8"><?= e($team['name']) ?></dd>
          <?php endif; ?>
          <dt class="col-sm-4"><?= e(t('auth/profile.009', 'Τελευταία σύνδεση')) ?></dt><dd class="col-sm-8"><?= e(gr_datetime($user['last_login_at'])) ?></dd>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm mt-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-translate me-1"></i> <?= e(t('auth/profile.015', 'Γλώσσα')) ?></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('/profile/language')) ?>">
          <?= csrf_field() ?>
          <div class="mb-2">
            <select name="language_code" class="form-select">
              <option value=""><?= e(t('auth/profile.016', '— Προεπιλογή πλατφόρμας —')) ?></option>
              <?php foreach ($languages as $lang): ?>
                <option value="<?= e($lang['code']) ?>" <?= ($user['language_code'] ?? '') === $lang['code'] ? 'selected' : '' ?>>
                  <?= e($lang['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="small text-muted mb-2">
            <?= e(t('auth/profile.017', 'Η επιλογή γλώσσας θα εφαρμοστεί σε επόμενη ενημέρωση της εφαρμογής.')) ?>
          </p>
          <button class="btn btn-primary btn-sm" type="submit"><?= e(t('auth/profile.014', 'Αποθήκευση')) ?></button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-key me-1"></i> <?= e(t('auth/profile.010', 'Αλλαγή κωδικού πρόσβασης')) ?></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('/profile/password')) ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label"><?= e(t('auth/profile.011', 'Τρέχων κωδικός')) ?></label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= e(t('auth/profile.012', 'Νέος κωδικός (τουλάχιστον 8 χαρακτήρες)')) ?></label>
            <input type="password" name="new_password" class="form-control" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= e(t('auth/profile.013', 'Επιβεβαίωση νέου κωδικού')) ?></label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
          </div>
          <button class="btn btn-primary" type="submit"><?= e(t('auth/profile.014', 'Αποθήκευση')) ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
