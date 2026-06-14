<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f766e">
<title>Επαναφορά κωδικού | SynDrasi</title>
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
        <div class="display-5 mb-2"><i class="bi bi-key-fill" style="color:#0f766e"></i></div>
        <h1 class="h3 fw-bold mb-1">Επαναφορά κωδικού</h1>
        <p class="text-muted mb-0">Εισάγετε το email σας και θα σας στείλουμε σύνδεσμο επαναφοράς.</p>
      </div>

      <?php include BASE_PATH . '/views/layouts/flash.php'; ?>

      <form method="post" action="<?= e(url('/forgot-password')) ?>">
        <?= csrf_field() ?>
        <div class="mb-4">
          <label class="form-label" for="email">Email λογαριασμού</label>
          <input type="email" class="form-control form-control-lg" id="email" name="email"
                 required autofocus placeholder="you@example.gr" value="<?= e(old('email')) ?>">
        </div>
        <button type="submit" class="btn btn-lg w-100 text-white mb-3" style="background:#0f766e">
          <i class="bi bi-send me-1"></i> Αποστολή συνδέσμου
        </button>
        <div class="text-center small">
          <a href="<?= e(url('/login')) ?>" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i>Πίσω στη σύνδεση
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
