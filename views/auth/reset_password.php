<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f766e">
<title><?= e(t('auth/reset_password.001', 'Νέος κωδικός | SynDrasi')) ?></title>
<link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
<link rel="icon" href="<?= e(url('/assets/img/icons/icon-192.png')) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="login-page">
  <div class="card login-card shadow-lg">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="display-5 mb-2"><i class="bi bi-shield-lock-fill" style="color:#0f766e"></i></div>
        <h1 class="h3 fw-bold mb-1"><?= e(t('auth/reset_password.002', 'Νέος κωδικός')) ?></h1>
        <p class="text-muted mb-0"><?= e(t('auth/reset_password.003', 'Ορίστε τον νέο κωδικό πρόσβασής σας.')) ?></p>
      </div>

      <?php include BASE_PATH . '/views/layouts/flash.php'; ?>

      <form method="post" action="<?= e(url('/reset-password')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3">
          <label class="form-label" for="password"><?= e(t('auth/reset_password.002', 'Νέος κωδικός')) ?></label>
          <input type="password" class="form-control form-control-lg" id="password" name="password"
                 required autofocus minlength="8" placeholder="<?= e(t('auth/reset_password.008', 'Τουλάχιστον 8 χαρακτήρες')) ?>">
          <div class="form-text"><?= e(t('auth/reset_password.004', 'Ελάχιστο 8 χαρακτήρες.')) ?></div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="confirm"><?= e(t('auth/reset_password.005', 'Επιβεβαίωση κωδικού')) ?></label>
          <input type="password" class="form-control form-control-lg" id="confirm" name="confirm"
                 required minlength="8" placeholder="<?= e(t('auth/reset_password.009', 'Επαναλάβετε τον κωδικό')) ?>">
        </div>
        <button type="submit" class="btn btn-lg w-100 text-white mb-3" style="background:#0f766e">
          <i class="bi bi-check-circle me-1"></i> <?= e(t('auth/reset_password.006', 'Αλλαγή κωδικού')) ?>
        </button>
        <div class="text-center small">
          <a href="<?= e(url('/login')) ?>" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i><?= e(t('auth/reset_password.007', 'Πίσω στη σύνδεση')) ?>
          </a>
        </div>
      </form>
    </div>
    <div class="card-footer text-center text-muted small py-3">
      <?= e(config('config')['footer_text']) ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
