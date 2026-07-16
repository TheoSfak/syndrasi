<?php
$channelLabels = [
    'email' => ['Email', 'envelope', 'primary'],
    'sms' => ['SMS', 'chat-dots', 'info'],
    'telegram' => ['Telegram', 'telegram', 'info'],
    'push' => ['Push', 'bell', 'secondary'],
    'in_app' => ['In-app', 'app-indicator', 'dark'],
];
$statusLabels = [
    'queued' => ['Σε ουρά', 'warning'],
    'pending' => ['Σε αναμονή', 'warning'],
    'sent' => ['Στάλθηκε', 'success'],
    'failed' => ['Απέτυχε', 'danger'],
    'skipped' => ['Παραλείφθηκε', 'secondary'],
    'read' => ['Διαβάστηκε', 'success'],
    'unread' => ['Αδιάβαστη', 'warning'],
];
$short = function ($text, $len = 140) {
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    return mb_strlen($plain) > $len ? mb_substr($plain, 0, $len - 1) . '…' : $plain;
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1"><?= e(t('notification_center/index.001', 'Κέντρο Ελέγχου Ειδοποιήσεων')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('notification_center/index.002', 'Ενιαίο ιστορικό αποστολών ανά κανάλι, παραλήπτη και κατάσταση.')) ?></p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(url('/settings#tab-notifications')) ?>">
    <i class="bi bi-sliders me-1"></i><?= e(t('notification_center/index.003', 'Ρυθμίσεις καναλιών')) ?>
  </a>
</div>

<?php if (empty($deliveryLogAvailable)): ?>
  <div class="alert alert-warning">
    <div class="fw-semibold"><?= e(t('notification_center/index.004', 'Το νέο delivery log δεν έχει δημιουργηθεί ακόμα.')) ?></div>
    <div class="small"><?= e(t('notification_center/index.005', 'Τρέξτε τις migrations για πλήρη ιστορικό SMS/Telegram/push. Μέχρι τότε φαίνονται όσα υπάρχουν από email και in-app ειδοποιήσεις.')) ?></div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ([
      ['Σύνολο', 'total', 'collection', 'dark'],
      ['Τελευταίο 24ωρο', 'last_24h', 'clock-history', 'primary'],
      ['Στάλθηκαν', 'sent', 'send-check', 'success'],
      ['Σε ουρά', 'queued', 'hourglass-split', 'warning'],
      ['Απέτυχαν', 'failed', 'exclamation-octagon', 'danger'],
      ['Αδιάβαστες in-app', 'unread', 'bell', 'secondary'],
  ] as [$label, $key, $icon, $color]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card shadow-sm h-100">
        <div class="card-body py-3">
          <div class="small text-muted"><i class="bi bi-<?= e($icon) ?> me-1"></i><?= e($label) ?></div>
          <div class="fs-4 fw-bold text-<?= e($color) ?>"><?= (int) ($stats[$key] ?? 0) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="get" action="<?= e(url('/notification-center')) ?>" class="row g-3 align-items-end">
      <div class="col-md-2">
        <label class="form-label small fw-semibold"><?= e(t('notification_center/index.006', 'Κανάλι')) ?></label>
        <select name="channel" class="form-select">
          <?php foreach (['all' => 'Όλα', 'email' => 'Email', 'sms' => 'SMS', 'telegram' => 'Telegram', 'push' => 'Push', 'in_app' => 'In-app'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $filters['channel'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold"><?= e(t('notification_center/index.007', 'Κατάσταση')) ?></label>
        <select name="status" class="form-select">
          <?php foreach (['all' => 'Όλες', 'queued' => 'Σε ουρά', 'sent' => 'Στάλθηκε', 'failed' => 'Απέτυχε', 'skipped' => 'Παραλείφθηκε', 'read' => 'Διαβάστηκε', 'unread' => 'Αδιάβαστη'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold"><?= e(t('notification_center/index.008', 'Από')) ?></label>
        <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold"><?= e(t('notification_center/index.009', 'Έως')) ?></label>
        <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold"><?= e(t('notification_center/index.010', 'Αναζήτηση')) ?></label>
        <input type="search" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('notification_center/index.025', 'παραλήπτης, θέμα, σφάλμα')) ?>">
      </div>
      <div class="col-md-1 d-grid">
        <button class="btn btn-primary" type="submit" title="<?= e(t('notification_center/index.026', 'Φιλτράρισμα')) ?>"><i class="bi bi-search"></i></button>
      </div>
    </form>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-9">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="fw-semibold"><i class="bi bi-activity me-1"></i><?= e(t('notification_center/index.011', 'Ιστορικό αποστολών')) ?></div>
        <span class="small text-muted"><?= e(t('notification_center/index.012', 'Εμφάνιση έως 150 πιο πρόσφατων αποτελεσμάτων')) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(t('notification_center/index.006', 'Κανάλι')) ?></th>
              <th><?= e(t('notification_center/index.013', 'Παραλήπτης')) ?></th>
              <th><?= e(t('notification_center/index.014', 'Μήνυμα')) ?></th>
              <th><?= e(t('notification_center/index.007', 'Κατάσταση')) ?></th>
              <th><?= e(t('notification_center/index.015', 'Ώρα')) ?></th>
              <th class="text-end"><?= e(t('notification_center/index.016', 'Ενέργεια')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($items)): ?>
              <tr><td colspan="6" class="text-center text-muted py-5"><?= e(t('notification_center/index.017', 'Δεν βρέθηκαν ειδοποιήσεις με τα τρέχοντα φίλτρα.')) ?></td></tr>
            <?php else: ?>
              <?php foreach ($items as $item): ?>
                <?php
                  $channel = $channelLabels[$item['channel']] ?? [$item['channel'], 'bell', 'secondary'];
                  $status = $statusLabels[$item['status']] ?? [$item['status'], 'secondary'];
                  $canRetry = $item['channel'] === 'email' && in_array($item['status'], ['queued', 'failed'], true) && !empty($item['retry_mail_id']);
                ?>
                <tr>
                  <td>
                    <span class="badge text-bg-<?= e($channel[2]) ?>">
                      <i class="bi bi-<?= e($channel[1]) ?> me-1"></i><?= e($channel[0]) ?>
                    </span>
                  </td>
                  <td style="min-width:170px">
                    <div class="fw-semibold small"><?= e($item['recipient'] ?: '—') ?></div>
                    <?php if (!empty($item['address'])): ?><div class="text-muted small text-break"><?= e($item['address']) ?></div><?php endif; ?>
                    <?php if (!empty($item['team'])): ?><div class="small text-muted"><i class="bi bi-people me-1"></i><?= e($item['team']) ?></div><?php endif; ?>
                  </td>
                  <td style="min-width:260px">
                    <div class="fw-semibold small"><?= e($short($item['title'], 90)) ?></div>
                    <div class="text-muted small"><?= e($short($item['message'], 150)) ?></div>
                    <?php if (!empty($item['event'])): ?><div class="small text-muted"><i class="bi bi-calendar-event me-1"></i><?= e($item['event']) ?></div><?php endif; ?>
                    <?php if (!empty($item['error'])): ?><div class="small text-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= e($short($item['error'], 140)) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <span class="badge text-bg-<?= e($status[1]) ?>"><?= e($status[0]) ?></span>
                    <?php if (!empty($item['attempts'])): ?><div class="small text-muted mt-1"><?= (int) $item['attempts'] ?> <?= e(t('notification_center/index.018', 'προσπάθειες')) ?></div><?php endif; ?>
                  </td>
                  <td class="small text-muted" style="min-width:120px"><?= e(gr_datetime($item['created_at'])) ?></td>
                  <td class="text-end">
                    <?php if ($canRetry): ?>
                      <form method="post" action="<?= e(url('/notification-center/mail/' . (int) $item['retry_mail_id'] . '/retry')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-primary" type="submit" title="<?= e(t('notification_center/index.027', 'Επανάληψη αποστολής email')) ?>">
                          <i class="bi bi-arrow-clockwise"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-3">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1"></i><?= e(t('notification_center/index.019', 'Ανά κανάλι')) ?></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($channelLabels as $key => $meta): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <span><i class="bi bi-<?= e($meta[1]) ?> me-1"></i><?= e($meta[0]) ?></span>
            <span class="badge text-bg-light border"><?= (int) ($stats['channels'][$key] ?? 0) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white text-danger fw-semibold"><i class="bi bi-trash me-1"></i><?= e(t('notification_center/index.020', 'Διαγραφή ιστορικού')) ?></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('/notification-center/clear')) ?>"
              onsubmit="return confirm('Να διαγραφεί οριστικά το επιλεγμένο ιστορικό ειδοποιήσεων;');">
          <?= csrf_field() ?>
          <label class="form-label small fw-semibold"><?= e(t('notification_center/index.021', 'Τι να διαγραφεί')) ?></label>
          <select name="scope" class="form-select mb-3">
            <option value="all"><?= e(t('notification_center/index.022', 'Όλο το ιστορικό')) ?></option>
            <option value="delivery">Email/SMS/Telegram/Push</option>
            <option value="in_app"><?= e(t('notification_center/index.023', 'Μόνο in-app ειδοποιήσεις')) ?></option>
          </select>
          <label class="form-label small fw-semibold"><?= e(t('notification_center/index.024', 'Πληκτρολογήστε DELETE')) ?></label>
          <input type="text" name="confirm" class="form-control mb-3" placeholder="DELETE" autocomplete="off">
          <button class="btn btn-outline-danger w-100" type="submit">
            <i class="bi bi-trash me-1"></i><?= e(t('notification_center/index.020', 'Διαγραφή ιστορικού')) ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
