<?php $unreadCount = is_logged_in() ? Notification::unreadCount($_SESSION['user_id']) : 0; ?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f766e">
<title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?>SynDrasi</title>
<link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
<link rel="icon" href="<?= e(url('/assets/img/icons/icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('/assets/img/icons/icon-192.png')) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<link href="<?= e(url('/assets/css/app.css')) ?>" rel="stylesheet">
<script>window.csrfToken = '<?= e(csrf_token()) ?>'; window.baseUrl = '<?= e(base_uri()) ?>';</script>
</head>
<body class="bg-light">

<?php if (!empty($_SESSION['impersonating_user_id'])): ?>
<div style="background:#b45309;color:#fff;text-align:center;padding:8px 16px;font-size:13px;font-weight:700;position:sticky;top:0;z-index:2000;display:flex;align-items:center;justify-content:center;gap:16px;">
  <i class="bi bi-person-fill-gear"></i>
  Λειτουργείτε ως <strong><?= e(current_user()['name'] ?? '–') ?></strong> (Impersonation από <?= e($_SESSION['impersonating_user_name'] ?? 'Super Admin') ?>)
  <form method="post" action="<?= e(url('/admin/stop-impersonation')) ?>" style="margin:0">
    <?= csrf_field() ?>
    <button type="submit" style="background:#fff;color:#92400e;border:none;border-radius:6px;padding:4px 14px;font-weight:700;cursor:pointer;font-size:13px;">
      <i class="bi bi-box-arrow-right me-1"></i>Επιστροφή
    </button>
  </form>
</div>
<?php endif; ?>

<nav class="navbar navbar-dark syndrasi-topbar sticky-top">
  <div class="container-fluid">
    <div class="d-flex align-items-center">
      <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-label="Μενού">
        <i class="bi bi-list"></i>
      </button>
      <a class="navbar-brand fw-bold" href="<?= e(url('/')) ?>">
        <i class="bi bi-people-fill me-1"></i> SynDrasi
      </a>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if (is_logged_in()): ?>
      <button id="pushBtn" class="btn btn-outline-light d-none" title="Ειδοποιήσεις Push" style="position:relative">
        <i class="bi bi-bell-slash" id="pushIcon"></i>
      </button>
      <?php endif; ?>
      <a href="<?= e(url('/notifications')) ?>" class="btn btn-outline-light position-relative" title="Ειδοποιήσεις">
        <i class="bi bi-bell"></i>
        <?php if ($unreadCount > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int) $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <div class="dropdown">
        <button class="btn btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle me-1"></i>
          <span class="d-none d-md-inline"><?= e(current_user() ? current_user()['name'] : '') ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= e(url('/profile')) ?>"><i class="bi bi-person me-2"></i>Το προφίλ μου</a></li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <form method="post" action="<?= e(url('/logout')) ?>">
              <?= csrf_field() ?>
              <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Αποσύνδεση</button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <?php include BASE_PATH . '/views/layouts/sidebar.php'; ?>
    <main class="col-lg-10 ms-sm-auto px-3 px-md-4 py-4">
      <?php include BASE_PATH . '/views/layouts/flash.php'; ?>
