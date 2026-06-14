<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f766e">
<title>Σύνδεση | SynDrasi</title>
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
        <div class="display-5 text-success mb-2"><i class="bi bi-people-fill" style="color:#0f766e"></i></div>
        <h1 class="h3 fw-bold mb-1">SynDrasi</h1>
        <p class="text-muted mb-0">Πλατφόρμα συντονισμού δράσεων<br>και εθελοντικών ομάδων</p>
      </div>

      <?php include BASE_PATH . '/views/layouts/flash.php'; ?>

      <form method="post" action="<?= e(url('/login')) ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="email">Email</label>
          <input type="email" class="form-control form-control-lg" id="email" name="email" required autofocus
                 value="<?= e(old('email')) ?>" placeholder="you@example.gr">
        </div>
        <div class="mb-4">
          <label class="form-label" for="password">Κωδικός πρόσβασης</label>
          <input type="password" class="form-control form-control-lg" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-lg w-100 text-white" style="background:#0f766e">
          <i class="bi bi-box-arrow-in-right me-1"></i> Σύνδεση
        </button>
        <div class="text-center mt-3 small">
          <a href="<?= e(url('/forgot-password')) ?>" class="text-decoration-none" style="color:#0f766e">
            <i class="bi bi-key me-1"></i>Ξεχάσατε τον κωδικό;
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
<script src="<?= e(url('/assets/js/pwa.js')) ?>"></script>
</body>
</html>
