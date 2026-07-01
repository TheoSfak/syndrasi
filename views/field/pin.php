<?php
/* SynDrasi — Field PIN gate (/f/{token}, no login). Standalone page. */
$terms = authority_context((int) ($app['municipality_id'] ?? 0));
$eventSingularLc = mb_strtolower($terms['event_singular'] ?? 'Δράση', 'UTF-8');
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d1a1a">
<title><?= e($pageTitle ?? 'PIN') ?></title>
<link rel="icon" href="<?= e(url('/assets/img/icons/icon-192.png')) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0d1a1a;color:#e8f5f4;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:24px}
  .box{width:100%;max-width:360px;text-align:center}
  .logo{font-size:34px;color:#2dd4bf;margin-bottom:6px}
  h1{font-size:18px;font-weight:800;margin-bottom:4px}
  .sub{font-size:13px;color:#7ab5ae;margin-bottom:22px;line-height:1.4}
  .pin-input{width:100%;font-size:34px;letter-spacing:14px;text-align:center;font-weight:800;background:#0a1414;border:1px solid #1e3333;border-radius:14px;color:#e8f5f4;padding:16px 10px;outline:none}
  .pin-input:focus{border-color:#2dd4bf}
  .btn{width:100%;margin-top:14px;background:#0e7490;color:#fff;border:none;border-radius:12px;padding:15px;font-size:16px;font-weight:700;cursor:pointer}
  .btn:active{transform:scale(.99)}
  .err{margin-top:14px;background:#3a1620;border:1px solid #6a2330;color:#ffd9df;border-radius:10px;padding:10px 12px;font-size:13px}
  .hint{margin-top:18px;font-size:12px;color:#4b7070;line-height:1.5}
</style>
</head>
<body>
  <div class="box">
    <div class="logo"><i class="bi bi-shield-lock-fill"></i></div>
    <h1>Πεδίο <?= e($eventSingularLc) ?></h1>
    <div class="sub"><?= e($app['event_title'] ?? '') ?><br>Εισάγετε το 4ψήφιο PIN που σας στάλθηκε.</div>

    <form method="post" action="<?= e(url('/f/' . $token . '/pin')) ?>">
      <?= csrf_field() ?>
      <input class="pin-input" type="text" name="pin" inputmode="numeric" pattern="[0-9]*"
             maxlength="4" autocomplete="one-time-code" autofocus placeholder="••••"
             oninput="this.value=this.value.replace(/[^0-9]/g,'')">
      <?php if (!empty($error)): ?>
        <div class="err"><i class="bi bi-x-circle me-1"></i>Λάθος PIN. Δοκιμάστε ξανά.</div>
      <?php endif; ?>
      <button class="btn" type="submit"><i class="bi bi-unlock me-1"></i>Είσοδος</button>
    </form>

    <div class="hint">Μετά τη σωστή εισαγωγή, η συσκευή σας θα θυμάται το PIN.<br>Αν δεν το γνωρίζετε, ζητήστε το από τον υπεύθυνο της ομάδας σας.</div>
  </div>
</body>
</html>
