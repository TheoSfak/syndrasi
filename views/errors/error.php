<?php if (!is_logged_in()): ?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Σφάλμα | SynDrasi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php endif; ?>

<div class="container py-5 text-center">
  <h1 class="display-4 fw-bold text-muted"><?= isset($code) ? (int) $code : 500 ?></h1>
  <p class="lead">
    <?php
    $msg = isset($message) && $message ? $message : null;
    if (!$msg) {
        $codes = [403 => 'Δεν έχετε δικαίωμα πρόσβασης.', 404 => 'Η σελίδα δεν βρέθηκε.'];
        $c = isset($code) ? (int) $code : 500;
        $msg = isset($codes[$c]) ? $codes[$c] : 'Παρουσιάστηκε σφάλμα.';
    }
    echo e($msg);
    ?>
  </p>
  <a href="<?= e(url('/')) ?>" class="btn btn-primary mt-2">Επιστροφή στην αρχική</a>
</div>

<?php if (!is_logged_in()): ?>
</body>
</html>
<?php endif; ?>
