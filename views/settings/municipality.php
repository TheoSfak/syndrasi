<style>
.org-preset-btn{display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 16px;border:2px solid #dee2e6;border-radius:12px;background:#fff;cursor:pointer;transition:all .15s;min-width:90px;color:inherit;}
.org-preset-btn:hover{border-color:#0d6efd;background:#f0f5ff;}
.org-preset-btn.active{border-color:#0d6efd;background:#dbeafe;color:#1d4ed8;}
.org-preset-icon{font-size:1.6rem;line-height:1;}
.org-preset-label{font-size:.75rem;font-weight:600;white-space:nowrap;}
.org-preview-box{background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:12px 14px;}
</style>

<?php
$authorityContext = authority_context((int) $municipality['id']);
$orgShort = $authorityContext['short_name'] ?? 'φορέας';
$eventSingularLc = mb_strtolower(t('authority/' . ($authorityContext['authority_type'] ?? 'municipality') . '.event_singular', $authorityContext['event_singular'] ?? 'Δράση'), 'UTF-8');
$eventPluralLc = $authorityContext['event_plural_lc'] ?? 'δράσεις';
?>
<h1 class="h3 mb-1"><?= e(t('settings/municipality.001', 'Ρυθμίσεις Φορέα')) ?></h1>
<p class="text-muted mb-3"><?= e($authorityContext['official_name'] ?? $municipality['name']) ?></p>

<?php
$v = function ($key, $default = '') use ($settings) {
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
};
$notifyOn = function ($key) use ($settings) {
    if (!isset($settings[$key])) return true; // default ON
    return $settings[$key] === '1';
};
$driver = $v('mail_driver');
$mailHistory = $mailHistory ?? [
    'available' => false,
    'stats' => ['total' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0, 'last_24h' => 0, 'last_7d' => 0],
    'recent' => [],
    'daily' => [],
    'recipients' => [],
    'error' => null,
];

$tzOptions = [
    'Europe/Athens'    => t('settings/municipality.226', 'Αθήνα (UTC+2/+3)'),
    'Europe/Nicosia'   => t('settings/municipality.227', 'Λευκωσία (UTC+2/+3)'),
    'Europe/Istanbul'  => t('settings/municipality.228', 'Κωνσταντινούπολη (UTC+3)'),
    'Europe/Rome'      => t('settings/municipality.229', 'Ρώμη (UTC+1/+2)'),
    'Europe/Paris'     => t('settings/municipality.230', 'Παρίσι (UTC+1/+2)'),
    'Europe/Berlin'    => t('settings/municipality.231', 'Βερολίνο (UTC+1/+2)'),
    'Europe/London'    => t('settings/municipality.232', 'Λονδίνο (UTC+0/+1)'),
    'UTC'              => 'UTC',
];
?>

<ul class="nav nav-tabs mb-4" id="settingsTabs">
  <li class="nav-item"><a class="nav-link" href="#tab-mail"           data-bs-toggle="tab"><i class="bi bi-envelope me-1"></i>Email</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-mail-history"    data-bs-toggle="tab"><i class="bi bi-clock-history me-1"></i><?= e(t('settings/municipality.002', 'Ιστορικό Email')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-map"            data-bs-toggle="tab"><i class="bi bi-map me-1"></i><?= e(t('settings/municipality.003', 'Χάρτης')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-awards"         data-bs-toggle="tab"><i class="bi bi-trophy me-1"></i><?= e(t('settings/municipality.004', 'Βραβεία')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-notifications"  data-bs-toggle="tab"><i class="bi bi-bell me-1"></i><?= e(t('settings/municipality.005', 'Ειδοποιήσεις')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-sms"            data-bs-toggle="tab"><i class="bi bi-chat-dots me-1"></i>SMS</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-telegram"       data-bs-toggle="tab"><i class="bi bi-telegram me-1"></i>Telegram</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-event-defaults" data-bs-toggle="tab"><i class="bi bi-calendar-plus me-1"></i><?= e(t('authority/' . ($authorityContext['authority_type'] ?? 'municipality') . '.event_plural', $authorityContext['event_plural'] ?? 'Δράσεις')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-branding"       data-bs-toggle="tab"><i class="bi bi-palette me-1"></i><?= e(t('settings/municipality.006', 'Εμφάνιση')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-members"          data-bs-toggle="tab"><i class="bi bi-people me-1"></i><?= e(t('settings/municipality.007', 'Μέλη Ομάδων')) ?></a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-email-templates" data-bs-toggle="tab"><i class="bi bi-envelope-paper me-1"></i><?= e(t('settings/municipality.008', 'Πρότυπα Email')) ?></a></li>
</ul>

<div class="tab-content">

  <!-- Email / SMTP -->
  <div class="tab-pane fade" id="tab-mail">
    <div class="row g-4">
      <div class="col-lg-7">
        <form method="post" action="<?= e(url('/settings/mail')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-envelope me-1"></i> Email / SMTP</div>
          <div class="card-body row g-3">
            <div class="col-12">
              <label class="form-label"><?= e(t('settings/municipality.009', 'Τρόπος αποστολής email')) ?></label>
              <select name="mail_driver" class="form-select">
                <option value="" <?= $driver === '' ? 'selected' : '' ?>><?= e(t('settings/municipality.214', 'Προεπιλογή πλατφόρμας (')) ?><?= e(config('mail')['driver']) ?>)</option>
                <option value="smtp" <?= $driver === 'smtp' ? 'selected' : '' ?>><?= e(t('settings/municipality.011', 'SMTP (προτεινόμενο για πραγματική αποστολή)')) ?></option>
                <option value="mail" <?= $driver === 'mail' ? 'selected' : '' ?>>PHP mail()</option>
                <option value="log"  <?= $driver === 'log'  ? 'selected' : '' ?>><?= e(t('settings/municipality.012', 'Μόνο καταγραφή (log, για δοκιμές)')) ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.013', 'Email αποστολέα (From)')) ?></label>
              <input type="email" name="mail_from_email" class="form-control" value="<?= e($v('mail_from_email')) ?>" placeholder="no-reply@dimos.gr">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.014', 'Όνομα αποστολέα')) ?></label>
              <input type="text" name="mail_from_name" class="form-control" value="<?= e($v('mail_from_name')) ?>" placeholder="<?= e($municipality['name']) ?>">
            </div>
            <div class="col-12"><hr class="my-1"><strong class="small text-muted"><?= e(t('settings/municipality.015', 'ΡΥΘΜΙΣΕΙΣ SMTP')) ?></strong></div>
            <div class="col-md-8">
              <label class="form-label">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control" value="<?= e($v('smtp_host')) ?>" placeholder="<?= e(t('settings/municipality.207', 'π.χ. smtp.gmail.com')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= e(t('settings/municipality.016', 'Θύρα')) ?></label>
              <input type="number" name="smtp_port" class="form-control" value="<?= e($v('smtp_port', '587')) ?>" placeholder="587">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.017', 'Όνομα χρήστη SMTP')) ?></label>
              <input type="text" name="smtp_user" class="form-control" value="<?= e($v('smtp_user')) ?>" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.018', 'Κωδικός SMTP')) ?></label>
              <input type="password" name="smtp_pass" class="form-control" autocomplete="new-password"
                     placeholder="<?= $v('smtp_pass') !== '' ? t('settings/municipality.242', '••••••••  (αφήστε κενό για να μην αλλάξει)') : '' ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.019', 'Ασφάλεια σύνδεσης')) ?></label>
              <select name="smtp_secure" class="form-select">
                <option value="tls" <?= $v('smtp_secure', 'tls') === 'tls' ? 'selected' : '' ?>><?= e(t('settings/municipality.020', 'STARTTLS (θύρα 587)')) ?></option>
                <option value="ssl" <?= $v('smtp_secure') === 'ssl' ? 'selected' : '' ?>><?= e(t('settings/municipality.021', 'SSL (θύρα 465)')) ?></option>
                <option value=""   <?= ($v('smtp_secure', 'tls') === '' && isset($settings['smtp_secure'])) ? 'selected' : '' ?>><?= e(t('settings/municipality.022', 'Χωρίς κρυπτογράφηση')) ?></option>
              </select>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-send-check me-1"></i> <?= e(t('settings/municipality.024', 'Δοκιμή αποστολής')) ?></div>
          <div class="card-body">
            <p class="small text-muted mb-3"><?= e(t('settings/municipality.025', 'Στέλνει δοκιμαστικό email στο')) ?> <strong><?= e(current_user()['email']) ?></strong> <?= e(t('settings/municipality.026', 'με τις αποθηκευμένες ρυθμίσεις.')) ?></p>
            <form method="post" action="<?= e(url('/settings/mail/test')) ?>">
              <?= csrf_field() ?>
              <button class="btn btn-outline-primary w-100"><i class="bi bi-envelope-paper me-1"></i><?= e(t('settings/municipality.027', 'Αποστολή δοκιμαστικού email')) ?></button>
            </form>
          </div>
        </div>
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> <?= e(t('settings/municipality.028', 'Τρέχουσα κατάσταση')) ?></div>
          <ul class="list-group list-group-flush small">
            <li class="list-group-item d-flex justify-content-between">
              <span><?= e(t('settings/municipality.029', 'Ενεργός τρόπος')) ?></span>
              <strong><?= e($effective['driver']) ?><?= $driver === '' ? ' ' . t('settings/municipality.243', '(προεπιλογή)') : '' ?></strong>
            </li>
            <li class="list-group-item d-flex justify-content-between">
              <span><?= e(t('settings/municipality.030', 'Αποστολέας')) ?></span>
              <strong><?= e($effective['from_email']) ?></strong>
            </li>
            <?php if ($effective['driver'] === 'smtp'): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span>SMTP server</span>
              <strong><?= e($effective['smtp_host'] ?: '—') ?>:<?= e($effective['smtp_port']) ?></strong>
            </li>
            <?php endif; ?>
            <li class="list-group-item text-muted">Gmail → smtp.gmail.com : 587, STARTTLS, App Password. Office 365 → smtp.office365.com : 587.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Email history -->
  <div class="tab-pane fade" id="tab-mail-history">
    <?php
      $mhStats = $mailHistory['stats'] ?? [];
      $mailStatus = function ($row) {
          if (!empty($row['sent_at'])) {
              return ['success', t('settings/municipality.233', 'Στάλθηκε')];
          }
          if ((int) ($row['attempts'] ?? 0) >= 3) {
              return ['danger', t('settings/municipality.234', 'Απέτυχε')];
          }
          return ['warning', t('settings/municipality.235', 'Σε αναμονή')];
      };
    ?>
    <?php if (empty($mailHistory['available'])): ?>
      <div class="alert alert-warning">
        <div class="fw-semibold"><?= e(t('settings/municipality.031', 'Το ιστορικό email δεν είναι διαθέσιμο.')) ?></div>
        <div class="small"><?= e($mailHistory['error'] ?? t('settings/municipality.236', 'Δεν βρέθηκε ο πίνακας mail_queue.')) ?></div>
      </div>
    <?php else: ?>
      <div class="row g-3 mb-3">
        <?php foreach ([
          [t('settings/municipality.237', 'Σύνολο'), 'total', 'envelope'],
          [t('settings/municipality.238', 'Στάλθηκαν'), 'sent', 'send-check'],
          [t('settings/municipality.235', 'Σε αναμονή'), 'pending', 'hourglass-split'],
          [t('settings/municipality.239', 'Απέτυχαν'), 'failed', 'exclamation-octagon'],
          [t('settings/municipality.240', 'Τελευταίο 24ωρο'), 'last_24h', 'clock'],
          [t('settings/municipality.241', 'Τελευταίες 7 ημέρες'), 'last_7d', 'calendar-week'],
        ] as [$label, $key, $icon]): ?>
          <div class="col-sm-6 col-lg-2">
            <div class="card shadow-sm h-100">
              <div class="card-body py-3">
                <div class="small text-muted"><i class="bi bi-<?= e($icon) ?> me-1"></i><?= e($label) ?></div>
                <div class="fs-4 fw-bold"><?= (int) ($mhStats[$key] ?? 0) ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-4">
        <div class="col-xl-8">
          <div class="card shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div class="fw-semibold"><i class="bi bi-envelope-paper me-1"></i><?= e(t('settings/municipality.032', 'Πρόσφατα email')) ?></div>
              <span class="small text-muted"><?= e(t('settings/municipality.033', 'Τελευταίες 50 εγγραφές του φορέα')) ?></span>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th><?= e(t('settings/municipality.034', 'Παραλήπτης')) ?></th>
                    <th><?= e(t('settings/municipality.035', 'Θέμα')) ?></th>
                    <th><?= e(t('settings/municipality.036', 'Κατάσταση')) ?></th>
                    <th><?= e(t('settings/municipality.037', 'Δημιουργήθηκε')) ?></th>
                    <th><?= e(t('settings/municipality.038', 'Προσπάθειες')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($mailHistory['recent'])): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4"><?= e(t('settings/municipality.039', 'Δεν υπάρχει ιστορικό email για αυτόν τον φορέα.')) ?></td></tr>
                  <?php else: ?>
                    <?php foreach ($mailHistory['recent'] as $row): ?>
                      <?php [$badge, $label] = $mailStatus($row); ?>
                      <tr>
                        <td>
                          <div class="fw-semibold small"><?= e($row['to_email']) ?></div>
                          <?php if (!empty($row['to_name'])): ?><div class="text-muted small"><?= e($row['to_name']) ?></div><?php endif; ?>
                        </td>
                        <td>
                          <div class="small"><?= e(mb_substr((string) $row['subject'], 0, 90)) ?></div>
                          <?php if (!empty($row['error_msg'])): ?><div class="text-danger small"><?= e(mb_substr((string) $row['error_msg'], 0, 120)) ?></div><?php endif; ?>
                        </td>
                        <td><span class="badge text-bg-<?= e($badge) ?>"><?= e($label) ?></span></td>
                        <td class="small text-muted"><?= e(gr_datetime($row['created_at'])) ?></td>
                        <td class="small"><?= (int) $row['attempts'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-xl-4">
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart me-1"></i><?= e(t('settings/municipality.040', 'Ανά ημέρα')) ?></div>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th><?= e(t('settings/municipality.041', 'Ημέρα')) ?></th><th><?= e(t('settings/municipality.042', 'Σύνολο')) ?></th><th>OK</th><th>Fail</th></tr></thead>
                <tbody>
                  <?php if (empty($mailHistory['daily'])): ?>
                    <tr><td colspan="4" class="text-muted text-center py-3"><?= e(t('settings/municipality.043', 'Δεν υπάρχουν στοιχεία.')) ?></td></tr>
                  <?php else: ?>
                    <?php foreach ($mailHistory['daily'] as $day): ?>
                      <tr>
                        <td class="small"><?= e(gr_date($day['day'])) ?></td>
                        <td><?= (int) $day['total'] ?></td>
                        <td class="text-success"><?= (int) $day['sent'] ?></td>
                        <td class="text-danger"><?= (int) $day['failed'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-lines-fill me-1"></i><?= e(t('settings/municipality.044', 'Συχνότεροι παραλήπτες')) ?></div>
            <ul class="list-group list-group-flush">
              <?php if (empty($mailHistory['recipients'])): ?>
                <li class="list-group-item text-muted small"><?= e(t('settings/municipality.045', 'Δεν υπάρχουν παραλήπτες.')) ?></li>
              <?php else: ?>
                <?php foreach ($mailHistory['recipients'] as $r): ?>
                  <li class="list-group-item d-flex justify-content-between gap-2">
                    <span class="small text-truncate" title="<?= e($r['to_email']) ?>"><?= e($r['to_email']) ?></span>
                    <span class="badge text-bg-light border"><?= (int) $r['total'] ?></span>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </div>

          <div class="card shadow-sm border-danger">
            <div class="card-header bg-white text-danger fw-semibold"><i class="bi bi-trash me-1"></i><?= e(t('settings/municipality.046', 'Διαγραφή ιστορικού')) ?></div>
            <div class="card-body">
              <p class="small text-muted"><?= e(t('settings/municipality.215', 'Διαγράφει όλες τις εγγραφές `mail_queue` αυτού του φορέα (')) ?><?= e($orgShort) ?><?= e(t('settings/municipality.216', '), μαζί με επιτυχημένες, αποτυχημένες και pending αποστολές.')) ?></p>
              <form method="post" action="<?= e(url('/settings/mail/history/clear')) ?>"
                    onsubmit="return confirm(<?= e(json_encode(t('settings/municipality.244', 'Να διαγραφεί οριστικά όλο το ιστορικό email αυτού του φορέα;'), JSON_UNESCAPED_UNICODE)) ?>);">
                <?= csrf_field() ?>
                <label class="form-label small fw-semibold"><?= e(t('settings/municipality.048', 'Πληκτρολογήστε DELETE')) ?></label>
                <input type="text" name="confirm" class="form-control mb-2" placeholder="DELETE" autocomplete="off">
                <button class="btn btn-outline-danger w-100" type="submit">
                  <i class="bi bi-trash me-1"></i><?= e(t('settings/municipality.049', 'Διαγραφή όλου του ιστορικού')) ?>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Map defaults -->
  <div class="tab-pane fade" id="tab-map">
    <div class="row g-4">
      <div class="col-lg-6">
        <form method="post" action="<?= e(url('/settings/map')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-1"></i> <?= e(t('settings/municipality.050', 'Προεπιλογές Επιχειρησιακού Χάρτη')) ?></div>
          <div class="card-body row g-3">
            <p class="text-muted small mb-0"><?= e(t('settings/municipality.217', 'Κέντρο και ζουμ χάρτη όταν μια')) ?> <?= e($eventSingularLc) ?> <?= e(t('settings/municipality.218', 'δεν έχει καταχωρημένες συντεταγμένες.')) ?></p>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.052', 'Γεωγρ. πλάτος (lat)')) ?></label>
              <input type="text" name="map_lat" class="form-control" value="<?= e($v('map_lat')) ?>" placeholder="<?= e(t('settings/municipality.208', 'π.χ. 35.3387')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.053', '−90 έως 90 · κενό = fallback Ελλάδα')) ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.054', 'Γεωγρ. μήκος (lng)')) ?></label>
              <input type="text" name="map_lng" class="form-control" value="<?= e($v('map_lng')) ?>" placeholder="<?= e(t('settings/municipality.209', 'π.χ. 25.1442')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.055', '−180 έως 180')) ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.056', 'Επίπεδο ζουμ (1–19)')) ?></label>
              <input type="number" name="map_zoom" min="1" max="19" class="form-control" value="<?= e($v('map_zoom', '13')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.057', '13 = πόλη · 15 = οδός · 10 = νομός')) ?></div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
      </div>
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><?= e(t('settings/municipality.058', 'Παραδείγματα συντεταγμένων')) ?></div>
          <ul class="list-group list-group-flush small">
            <li class="list-group-item d-flex justify-content-between"><span><?= e(t('settings/municipality.059', 'Ηράκλειο Κρήτης')) ?></span><code>35.3387, 25.1442</code></li>
            <li class="list-group-item d-flex justify-content-between"><span><?= e(t('settings/municipality.060', 'Αθήνα')) ?></span><code>37.9838, 23.7275</code></li>
            <li class="list-group-item d-flex justify-content-between"><span><?= e(t('settings/municipality.061', 'Θεσσαλονίκη')) ?></span><code>40.6401, 22.9444</code></li>
            <li class="list-group-item d-flex justify-content-between"><span><?= e(t('settings/municipality.062', 'Πάτρα')) ?></span><code>38.2466, 21.7346</code></li>
            <li class="list-group-item d-flex justify-content-between"><span><?= e(t('settings/municipality.063', 'Λάρισα')) ?></span><code>39.6390, 22.4191</code></li>
            <li class="list-group-item d-flex justify-content-between"><span><?= e(t('settings/municipality.064', 'Ρόδος')) ?></span><code>36.4349, 28.2176</code></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Award thresholds -->
  <div class="tab-pane fade" id="tab-awards">
    <div class="row g-4">
      <div class="col-lg-6">
        <form method="post" action="<?= e(url('/settings/awards')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-trophy me-1"></i> <?= e(t('settings/municipality.065', 'Βαθμίδες Συμμετοχής')) ?></div>
          <div class="card-body row g-3">
            <p class="text-muted small mb-0"><?= e(t('settings/municipality.066', 'Αριθμός δράσεων που χρειάζεται μια ομάδα για κάθε βαθμίδα στην κατάταξη.')) ?></p>
            <div class="col-md-4">
              <label class="form-label"><?= e(t('settings/municipality.067', '🥉 Χάλκινη')) ?></label>
              <input type="number" name="award_bronze_events" min="1" class="form-control" value="<?= e($v('award_bronze_events', '5')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.068', 'ελάχ.')) ?> <?= e($eventPluralLc) ?></div>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= e(t('settings/municipality.069', '🥈 Ασημένια')) ?></label>
              <input type="number" name="award_silver_events" min="1" class="form-control" value="<?= e($v('award_silver_events', '10')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.068', 'ελάχ.')) ?> <?= e($eventPluralLc) ?></div>
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= e(t('settings/municipality.070', '🥇 Χρυσή')) ?></label>
              <input type="number" name="award_gold_events" min="1" class="form-control" value="<?= e($v('award_gold_events', '20')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.068', 'ελάχ.')) ?> <?= e($eventPluralLc) ?></div>
            </div>
            <div class="col-12"><hr class="my-1"></div>
            <div class="col-md-8">
              <label class="form-label"><?= e(t('settings/municipality.219', 'Ελάχ.')) ?> <?= e($eventPluralLc) ?> <?= e(t('settings/municipality.220', 'για Συνέπεια & Απόκριση')) ?></label>
              <input type="number" name="award_min_events" min="1" class="form-control" value="<?= e($v('award_min_events', '3')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.221', 'Ομάδες με λιγότερες')) ?> <?= e($eventPluralLc) ?> <?= e(t('settings/municipality.222', 'δεν λαμβάνουν αυτά τα ποιοτικά βραβεία.')) ?></div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
      </div>
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> <?= e(t('settings/municipality.073', 'Πώς λειτουργεί')) ?></div>
          <div class="card-body small text-muted">
            <p><?= e(t('settings/municipality.074', 'Τα εικονίδια 🥉🥈🥇 εμφανίζονται δίπλα στο όνομα κάθε ομάδας στη σελίδα')) ?> <strong><?= e(t('settings/municipality.075', 'Επιβράβευση Ομάδων')) ?></strong><?= e(t('settings/municipality.076', ', βάσει του αριθμού δράσεων που συμμετείχε.')) ?></p>
            <p><?= e(t('settings/municipality.077', 'Τα τέσσερα ποιοτικά βραβεία (Καλύτερη Προσφορά, Πιο Δραστήρια κ.λπ.) ανακηρύσσονται ανεξάρτητα.')) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Notification toggles -->
  <div class="tab-pane fade" id="tab-notifications">
    <div class="row g-4">
      <div class="col-lg-7">
        <form method="post" action="<?= e(url('/settings/notifications')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-1"></i> <?= e(t('settings/municipality.078', 'Κανάλι ειδοποιήσεων')) ?></div>
          <div class="card-body">
            <p class="text-muted small"><?= e(t('settings/municipality.079', 'Οι in-app ειδοποιήσεις (κουδούνι) στέλνονται πάντα. Εδώ επιλέγετε αν θα αποστέλλεται επιπλέον')) ?> <strong>Email</strong>, <strong>SMS</strong> ή <strong><?= e(t('settings/municipality.080', 'και τα δύο')) ?></strong> <?= e(t('settings/municipality.081', 'ανά τύπο. Το Telegram ενεργοποιείται ανεξάρτητα ανά τύπο και απαιτεί ρυθμίσεις στην καρτέλα «Telegram».')) ?></p>
            <?php
            $eventSingular = $authorityContext['event_singular'] ?? 'Δράση';
            $eventSingularLc = mb_strtolower($eventSingular, 'UTF-8');
            $eventPluralLc = $authorityContext['event_plural_lc'] ?? 'δράσεις';
            $orgShort = $authorityContext['short_name'] ?? $authorityContext['short'] ?? 'Φορέας';
            $notifTypes = [
                ['event_published',        t('emails/event_published.label', 'Νέα αποστολή/δράση δημοσιεύτηκε'),  t('settings/municipality.246', 'Σε όλες τις ενεργές ομάδες')],
                ['application_submitted',  t('emails/application_submitted.label', 'Νέα δήλωση συμμετοχής'),   t('settings/municipality.247', 'Στους διαχειριστές φορέα')],
                ['application_approved',   t('emails/application_approved.label', 'Έγκριση συμμετοχής'),      t('settings/municipality.248', 'Στην ομάδα')],
                ['application_rejected',   t('emails/application_rejected.label', 'Απόρριψη συμμετοχής'),     t('settings/municipality.248', 'Στην ομάδα')],
                ['shortage_reported',      t('emails/shortage_reported.label', 'Αναφορά έλλειψης'),        t('settings/municipality.247', 'Στους διαχειριστές φορέα')],
                ['event_reminder',         t('emails/event_reminder.label', 'Υπενθύμιση αποστολής/δράσης'),       t('settings/municipality.249', 'Χειροκίνητη, κουμπί «Υπενθύμιση»')],
                ['event_completed',        t('settings/municipality.245', 'Ολοκλήρωση αποστολής/δράσης'),       t('settings/municipality.250', 'Στο command group και στις εγκεκριμένες ομάδες')],
            ];
            $opsNotifTypes = [
                ['photo_request',          t('settings/municipality.251', 'Αίτημα φωτογραφίας'),      t('settings/municipality.252', 'Στην ομάδα κατά την ενεργή αποστολή/δράση')],
                ['video_request',          t('settings/municipality.253', 'Αίτημα βίντεο'),           t('settings/municipality.252', 'Στην ομάδα κατά την ενεργή αποστολή/δράση')],
                ['gps_request',            t('settings/municipality.254', 'Αίτημα στίγματος GPS'),    t('settings/municipality.252', 'Στην ομάδα κατά την ενεργή αποστολή/δράση')],
                ['photo_uploaded',         t('settings/municipality.255', 'Φωτογραφία ελήφθη'),       t('settings/municipality.247', 'Στους διαχειριστές φορέα')],
                ['video_uploaded',         t('settings/municipality.256', 'Βίντεο ελήφθη'),           t('settings/municipality.247', 'Στους διαχειριστές φορέα')],
                ['gps_arrived',            t('settings/municipality.257', 'Στίγμα GPS ελήφθη'),       t('settings/municipality.247', 'Στους διαχειριστές φορέα')],
                ['ops_message',            t('settings/municipality.258', 'Μήνυμα επιχειρήσεων'),     $orgShort . ' ' . t('settings/municipality.259', '↔ ομάδα, μη κρίσιμο')],
                ['ops_geo',                t('settings/municipality.260', 'Σημείο/μετακίνηση'),       t('settings/municipality.261', 'Μη κρίσιμο ή forced όταν είναι εντολή')],
                ['team_silent',            t('settings/municipality.262', 'Ομάδα σε σίγη'),           t('settings/municipality.247', 'Στους διαχειριστές φορέα')],
                ['shortage_update',        t('settings/municipality.263', 'Ενημέρωση έλλειψης'),      t('settings/municipality.248', 'Στην ομάδα')],
                ['sos_ack',                t('settings/municipality.264', 'Επιβεβαίωση SOS'),         t('settings/municipality.248', 'Στην ομάδα')],
            ];
            $telegramExternalTypes = [
                ['fire_service_crete',      t('settings/municipality.265', 'Συμβάντα Πυροσβεστικής Κρήτης'), t('settings/municipality.266', 'Νέα συμβάντα ή αλλαγές κατάστασης σε ΣΕ ΕΞΕΛΙΞΗ / ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ')],
                ['fire_risk_crete',         t('settings/municipality.267', 'Χάρτης κινδύνου πυρκαγιάς Κρήτης'), t('settings/municipality.268', 'Ημερήσια πρόβλεψη κινδύνου ανά Π.Ε. Κρήτης από την Πολιτική Προστασία')],
            ];
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $fireRiskCronUrl = $scheme . '://' . $host . url('/cron/fire-risk-map');
            $fireRiskIngestUrl = $scheme . '://' . $host . url('/cron/fire-risk-map/ingest');
            $fireRiskCronCommand = 'curl -s -H "Authorization: Bearer TOKEN" "' . $fireRiskCronUrl . '" > /dev/null';
            $fireRiskIngestCommand = 'curl -s -H "Authorization: Bearer TOKEN" -F "map_date=YYYY-MM-DD" -F "fire_risk_map=@/path/to/map.jpg" "' . $fireRiskIngestUrl . '"';
            $fireRiskDefaultDate = date('Y-m-d', strtotime('+1 day'));
            $opsTypeKeys = array_map(static function ($row) { return $row[0]; }, $opsNotifTypes);
            $channelOpts = ['off' => t('settings/municipality.269', 'Καμία'), 'email' => t('settings/municipality.270', 'Μόνο Email'), 'sms' => t('settings/municipality.271', 'Μόνο SMS'), 'both' => 'Email + SMS'];
            // Effective channel: explicit notify_channel_*, else legacy notify_email_*.
            $channelOf = function ($type) use ($settings, $opsTypeKeys) {
                $ch = $settings['notify_channel_' . $type] ?? null;
                if (in_array($ch, ['off', 'email', 'sms', 'both'], true)) { return $ch; }
                if (isset($settings['notify_email_' . $type])) {
                    return $settings['notify_email_' . $type] === '0' ? 'off' : 'email';
                }
                return in_array($type, $opsTypeKeys, true) ? 'off' : 'email';
            };
            ?>
            <div class="list-group list-group-flush">
              <?php foreach ($notifTypes as [$type, $label, $desc]): $cur = $channelOf($type); $tgOn = ($settings['notify_telegram_' . $type] ?? '0') === '1'; ?>
              <div class="list-group-item d-flex justify-content-between align-items-center py-3 gap-3">
                <div class="me-3">
                  <div class="fw-semibold"><?= e($label) ?></div>
                  <div class="small text-muted"><?= e($desc) ?></div>
                </div>
                <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                  <select name="notify_channel_<?= e($type) ?>" class="form-select form-select-sm flex-shrink-0" style="width:auto;min-width:140px">
                    <?php foreach ($channelOpts as $val => $optLabel): ?>
                    <option value="<?= e($val) ?>" <?= $cur === $val ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="notify_telegram_<?= e($type) ?>" id="tg_<?= e($type) ?>" value="1" <?= $tgOn ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="tg_<?= e($type) ?>">Telegram</label>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="border-top mt-3 pt-3">
              <div class="fw-semibold mb-1"><i class="bi bi-broadcast-pin text-info me-1"></i><?= e(t('settings/municipality.082', 'Επιχειρησιακές ειδοποιήσεις')) ?></div>
              <p class="small text-muted mb-2"><?= e(t('settings/municipality.083', 'Οι in-app/push ειδοποιήσεις παραμένουν πάντα ενεργές. Εδώ επιλέγετε επιπλέον Email/SMS και Telegram ανά τύπο.')) ?></p>
              <div class="list-group list-group-flush">
                <?php foreach ($opsNotifTypes as [$type, $label, $desc]): $cur = $channelOf($type); $tgOn = ($settings['notify_telegram_' . $type] ?? '0') === '1'; ?>
                <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 gap-3">
                  <div>
                    <div class="fw-semibold small"><?= e($label) ?></div>
                    <div class="small text-muted"><?= e($desc) ?></div>
                  </div>
                  <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                    <select name="notify_channel_<?= e($type) ?>" class="form-select form-select-sm flex-shrink-0" style="width:auto;min-width:140px">
                      <?php foreach ($channelOpts as $val => $optLabel): ?>
                      <option value="<?= e($val) ?>" <?= $cur === $val ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" name="notify_telegram_<?= e($type) ?>" id="tg_<?= e($type) ?>" value="1" <?= $tgOn ? 'checked' : '' ?>>
                      <label class="form-check-label small" for="tg_<?= e($type) ?>">Telegram</label>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="border-top mt-3 pt-3">
              <div class="fw-semibold mb-1"><i class="bi bi-fire text-danger me-1"></i><?= e(t('settings/municipality.084', 'Εξωτερικές πηγές')) ?></div>
              <p class="small text-muted mb-2"><?= e(t('settings/municipality.085', 'Αποστολή μόνο μέσω Telegram. Τα συμβάντα Πυροσβεστικής αποστέλλονται μία φορά ανά συμβάν και ξανά μόνο αν αλλάξει σχετική κατάσταση.')) ?></p>
              <div class="list-group list-group-flush">
                <?php foreach ($telegramExternalTypes as [$type, $label, $desc]): $tgOn = ($settings['notify_telegram_' . $type] ?? '0') === '1'; ?>
                <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 gap-3">
                  <div>
                    <div class="fw-semibold small"><?= e($label) ?></div>
                    <div class="small text-muted"><?= e($desc) ?></div>
                  </div>
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="notify_telegram_<?= e($type) ?>" id="tg_<?= e($type) ?>" value="1" <?= $tgOn ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="tg_<?= e($type) ?>">Telegram</label>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <div class="border rounded p-3 mt-3 bg-light">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                  <div>
                    <div class="fw-semibold small"><?= e(t('settings/municipality.086', 'Χειροκίνητος έλεγχος χάρτη κινδύνου')) ?></div>
                    <div class="small text-muted"><?= e(t('settings/municipality.087', 'Τρέχει τώρα τον ημερήσιο χάρτη Πολιτικής Προστασίας. Δεν στέλνει διπλά αν έχει ήδη σταλεί για την ίδια ημερομηνία.')) ?></div>
                  </div>
                  <button type="submit" form="fireRiskManualForm" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-fire me-1"></i><?= e(t('settings/municipality.088', 'Έλεγχος τώρα')) ?>
                  </button>
                </div>
                <div class="small text-muted mb-1"><?= e(t('settings/municipality.089', 'Cron κάθε 60 λεπτά:')) ?></div>
                <code class="d-block small text-break"><?= e($fireRiskCronCommand) ?></code>
                <hr>
                <div class="fw-semibold small mb-2"><?= e(t('settings/municipality.090', 'Fallback όταν η Πολιτική Προστασία μπλοκάρει το server')) ?></div>
                <div class="row g-2 align-items-end">
                  <div class="col-md-4">
                    <label class="form-label small mb-1" for="fireRiskUploadDate"><?= e(t('settings/municipality.091', 'Ημερομηνία χάρτη')) ?></label>
                    <input type="date" id="fireRiskUploadDate" name="map_date" form="fireRiskUploadForm" class="form-control form-control-sm" value="<?= e($fireRiskDefaultDate) ?>">
                  </div>
                  <div class="col-md-5">
                    <label class="form-label small mb-1" for="fireRiskUploadFile"><?= e(t('settings/municipality.092', 'Εικόνα χάρτη')) ?></label>
                    <input type="file" id="fireRiskUploadFile" name="fire_risk_map" form="fireRiskUploadForm" class="form-control form-control-sm" accept="image/*">
                  </div>
                  <div class="col-md-3 d-grid">
                    <button type="submit" form="fireRiskUploadForm" class="btn btn-danger btn-sm">
                      <i class="bi bi-upload me-1"></i><?= e(t('settings/municipality.093', 'Ανέβασμα & αποστολή')) ?>
                    </button>
                  </div>
                </div>
                <div class="small text-muted mt-2"><?= e(t('settings/municipality.094', 'Το upload αναλύει την εικόνα τοπικά, κρατά δημόσιο link για Telegram και δεν στέλνει δεύτερη ειδοποίηση για ίδια ημερομηνία.')) ?></div>
                <div class="small text-muted mt-2 mb-1">External fetcher upload:</div>
                <code class="d-block small text-break"><?= e($fireRiskIngestCommand) ?></code>
              </div>
            </div>
          </div>
            <div class="border-top mt-1 pt-3 px-1 pb-2">
              <div class="fw-semibold mb-1"><i class="bi bi-shield-exclamation text-warning me-1"></i><?= e(t('settings/municipality.095', 'Ειδοποιήσεις Επιχείρησης (Ops)')) ?></div>
              <p class="small text-muted mb-2"><?= e(t('settings/municipality.096', 'Εφαρμόζονται μόνο κατά τη διάρκεια ενεργών δράσεων. SOS, περιστατικό και εντολή αποστέλλονται forced και σε Telegram όταν υπάρχει ρύθμιση.')) ?></p>
              <div class="d-flex align-items-center gap-3 py-2">
                <div class="flex-grow-1">
                  <div class="fw-semibold small"><?= e(t('settings/municipality.097', 'Ειδοποίηση σίγης ομάδας')) ?></div>
                  <div class="text-muted" style="font-size:12px"><?= e(t('settings/municipality.098', 'Αν ομάδα δεν στείλει GPS για τόσα λεπτά, ο αρχηγός λαμβάνει ειδοποίηση.')) ?> <strong><?= e(t('settings/municipality.099', '0 = απενεργοποιημένο')) ?></strong>.</div>
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                  <input type="number" name="ops_silent_team_minutes" min="0" max="120"
                         value="<?= e($settings['ops_silent_team_minutes'] ?? '20') ?>"
                         class="form-control form-control-sm text-center" style="width:72px">
                  <span class="small text-muted"><?= e(t('settings/municipality.100', 'λεπτά')) ?></span>
                </div>
              </div>
            </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
        <form id="fireRiskManualForm" method="post" action="<?= e(url('/settings/fire-risk-map/sync')) ?>">
          <?= csrf_field() ?>
        </form>
        <form id="fireRiskUploadForm" method="post" action="<?= e(url('/settings/fire-risk-map/upload')) ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> <?= e(t('settings/municipality.101', 'Σημείωση')) ?></div>
          <div class="card-body small text-muted">
            <p><?= e(t('settings/municipality.102', 'Η απενεργοποίηση ενός τύπου δεν επηρεάζει τις in-app ειδοποιήσεις — αυτές εμφανίζονται πάντα στο κουδούνι.')) ?></p>
            <p><?= e(t('settings/municipality.103', 'Χρήσιμο όταν ο SMTP δεν έχει ρυθμιστεί ακόμα ή σε περίοδο δοκιμών για να αποφύγετε spam.')) ?></p>
            <p class="mb-0"><strong>Ops:</strong> <?= e(t('settings/municipality.104', 'SOS, περιστατικά και εντολές φεύγουν forced και σε Telegram όταν έχει οριστεί Bot Token και Chat ID.')) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- SMS gateway -->
  <div class="tab-pane fade" id="tab-sms">
    <?php $smsDriver = $v('sms_driver'); $smsKeySet = ($v('sms_api_key') !== ''); ?>
    <div class="row g-4">
      <div class="col-lg-7">
        <form method="post" action="<?= e(url('/settings/sms')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-dots me-1"></i> SMS Gateway</div>
          <div class="card-body row g-3">
            <div class="col-12">
              <label class="form-label"><?= e(t('settings/municipality.105', 'Τρόπος αποστολής SMS')) ?></label>
              <select name="sms_driver" class="form-select">
                <option value=""       <?= $smsDriver === ''       ? 'selected' : '' ?>><?= e(t('settings/municipality.214', 'Προεπιλογή πλατφόρμας (')) ?><?= e(config('sms')['driver']) ?>)</option>
                <option value="smsbox" <?= $smsDriver === 'smsbox' ? 'selected' : '' ?>>smsbox.gr</option>
                <option value="http"   <?= $smsDriver === 'http'   ? 'selected' : '' ?>><?= e(t('settings/municipality.106', 'Γενικό HTTP gateway')) ?></option>
                <option value="log"    <?= $smsDriver === 'log'    ? 'selected' : '' ?>><?= e(t('settings/municipality.012', 'Μόνο καταγραφή (log, για δοκιμές)')) ?></option>
                <option value="none"   <?= $smsDriver === 'none'   ? 'selected' : '' ?>><?= e(t('settings/municipality.107', 'Απενεργοποιημένο')) ?></option>
              </select>
              <div class="form-text"><?= e(t('settings/municipality.108', 'Για το')) ?> <strong>smsbox.gr</strong> <?= e(t('settings/municipality.109', 'συμπληρώστε username + password (συνιστάται) ή επικολλήστε ένα ενεργό sesskey στο πεδίο «API Key / Password».')) ?></div>
            </div>
            <div class="col-md-5">
              <label class="form-label"><?= e(t('settings/municipality.110', 'Όνομα αποστολέα (Sender / from)')) ?></label>
              <input type="text" name="sms_sender" class="form-control" value="<?= e($v('sms_sender')) ?>" placeholder="SynDrasi" maxlength="11">
              <div class="form-text"><?= e(t('settings/municipality.111', 'Έως 11 χαρακτήρες (εγκεκριμένο alphanumeric sender ID).')) ?></div>
            </div>
            <div class="col-md-7">
              <label class="form-label">Username (smsbox)</label>
              <input type="text" name="sms_username" class="form-control" value="<?= e($v('sms_username')) ?>" autocomplete="off" placeholder="<?= e(t('settings/municipality.210', 'username λογαριασμού smsbox')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.112', 'Αφήστε κενό αν θα χρησιμοποιήσετε απευθείας sesskey.')) ?></div>
            </div>
            <div class="col-12"><hr class="my-1"><strong class="small text-muted"><?= e(t('settings/municipality.113', 'ΔΙΑΠΙΣΤΕΥΤΗΡΙΑ')) ?></strong></div>
            <div class="col-md-6">
              <label class="form-label">API Key / Password / sesskey</label>
              <input type="password" name="sms_api_key" class="form-control" autocomplete="new-password"
                     placeholder="<?= $smsKeySet ? t('settings/municipality.272', '•••••••• (αποθηκευμένο — αφήστε κενό για να μην αλλάξει)') : t('settings/municipality.273', 'password (με username) ή sesskey') ?>">
              <div class="form-text"><?= $smsKeySet ? t('settings/municipality.274', 'Υπάρχει ήδη αποθηκευμένο. Συμπληρώστε μόνο για αλλαγή.') : t('settings/municipality.275', 'Με username → βάλτε password. Χωρίς username → βάλτε sesskey (λήγει σε 2 ώρες).') ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= e(t('settings/municipality.114', 'Endpoint (μόνο για γενικό HTTP gateway)')) ?></label>
              <input type="url" name="sms_endpoint" class="form-control" value="<?= e($v('sms_endpoint')) ?>" placeholder="https://api.provider.gr/send">
              <div class="form-text"><?= e(t('settings/municipality.115', 'Το smsbox.gr δεν χρειάζεται endpoint.')) ?></div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> <?= e(t('settings/municipality.073', 'Πώς λειτουργεί')) ?></div>
          <div class="card-body small text-muted">
            <p><?= e(t('settings/municipality.116', 'Τα')) ?> <strong>credits SMS</strong> <?= e(t('settings/municipality.117', 'τα αγοράζετε απευθείας από τον πάροχο. Εδώ καταχωρείτε μόνο το κλειδί σύνδεσης ώστε η εφαρμογή να στέλνει μέσω του λογαριασμού σας.')) ?></p>
            <p><?= e(t('settings/municipality.118', 'Στην καρτέλα')) ?> <strong><?= e(t('settings/municipality.005', 'Ειδοποιήσεις')) ?></strong> <?= e(t('settings/municipality.119', 'επιλέγετε ανά τύπο αν θα φεύγει Email, SMS ή και τα δύο.')) ?></p>
            <p><?= e(t('settings/municipality.120', 'Σε λειτουργία')) ?> <em>log</em><?= e(t('settings/municipality.121', ', τα μηνύματα γράφονται στο')) ?> <code>storage/logs/sms.log</code> <?= e(t('settings/municipality.122', 'για δοκιμές χωρίς χρέωση.')) ?></p>
          </div>
        </div>
        <div class="card shadow-sm mt-3">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-send-check me-1"></i> <?= e(t('settings/municipality.123', 'Δοκιμαστικό SMS')) ?></div>
          <div class="card-body">
            <p class="small text-muted mb-2"><?= e(t('settings/municipality.124', 'Αποθηκεύστε πρώτα τις ρυθμίσεις, μετά στείλτε ένα δοκιμαστικό SMS.')) ?></p>
            <form method="post" action="<?= e(url('/settings/sms/test')) ?>" class="d-flex gap-2">
              <?= csrf_field() ?>
              <input type="text" name="test_to" class="form-control" placeholder="<?= e(t('settings/municipality.211', 'π.χ. 69XXXXXXXX')) ?>" required>
              <button class="btn btn-outline-primary" type="submit"><i class="bi bi-send me-1"></i><?= e(t('settings/municipality.125', 'Αποστολή')) ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Telegram Bot -->
  <div class="tab-pane fade" id="tab-telegram">
    <?php
      $tgEnabled = !empty($telegramEffective['enabled']);
      $tgTokenSet = !empty($telegramEffective['bot_token']);
      $tgCommandChat = $telegramEffective['command_chat_id'] ?? '';
      $tgTeamChat = $telegramEffective['team_chat_id'] ?? '';
    ?>
    <div class="row g-4">
      <div class="col-lg-7">
        <form method="post" action="<?= e(url('/settings/telegram')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-telegram me-1"></i> Telegram Bot</div>
          <div class="card-body row g-3">
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="telegram_enabled" id="telegramEnabled" value="1" <?= $tgEnabled ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="telegramEnabled"><?= e(t('settings/municipality.126', 'Ενεργοποίηση Telegram αποστολών')) ?></label>
              </div>
              <div class="form-text"><?= e(t('settings/municipality.127', 'Αφορά command group φορέα και group/channel ομάδων. Τα προσωπικά Telegram DM δεν είναι μέρος αυτού του MVP.')) ?></div>
            </div>
            <div class="col-12">
              <label class="form-label">Bot Token</label>
              <input type="password" name="telegram_bot_token" class="form-control" autocomplete="new-password"
                     placeholder="<?= $tgTokenSet ? t('settings/municipality.272', '•••••••• (αποθηκευμένο — αφήστε κενό για να μην αλλάξει)') : '123456789:AA...' ?>">
              <div class="form-text"><?= $tgTokenSet ? t('settings/municipality.276', 'Υπάρχει ήδη αποθηκευμένο token. Συμπληρώστε μόνο για αλλαγή.') : t('settings/municipality.277', 'Δημιουργείται από το BotFather.') ?></div>
            </div>
            <div class="col-12">
              <label class="form-label"><?= e(t('settings/municipality.128', 'Command / Φορέας Chat ID')) ?></label>
              <input type="text" name="telegram_command_chat_id" class="form-control" value="<?= e($tgCommandChat) ?>" placeholder="<?= e(t('settings/municipality.212', 'π.χ. -1001234567890')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.129', 'Group/channel όπου θα πηγαίνουν ειδοποιήσεις προς τον φορέα.')) ?></div>
            </div>
            <div class="col-12">
              <label class="form-label"><?= e(t('settings/municipality.130', 'Κοινό Chat ID ομάδων / εθελοντών')) ?></label>
              <input type="text" name="telegram_team_chat_id" class="form-control" value="<?= e($tgTeamChat) ?>" placeholder="<?= e(t('settings/municipality.212', 'π.χ. -1001234567890')) ?>">
              <div class="form-text"><?= e(t('settings/municipality.131', 'Group όπου μπορούν να είναι μέσα όλες οι εθελοντικές ομάδες και οι admins. Χρησιμοποιείται για ειδοποιήσεις προς ομάδες όταν η ομάδα δεν έχει δικό της Telegram Chat ID.')) ?></div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-send-check me-1"></i> <?= e(t('settings/municipality.132', 'Δοκιμαστικό Telegram')) ?></div>
          <div class="card-body">
            <p class="small text-muted mb-3"><?= e(t('settings/municipality.133', 'Αποθηκεύστε πρώτα τις ρυθμίσεις και μετά δοκιμάστε κάθε group.')) ?></p>
            <form method="post" action="<?= e(url('/settings/telegram/test')) ?>" class="d-grid gap-2">
              <?= csrf_field() ?>
              <button class="btn btn-outline-primary" name="test_target" value="command"><i class="bi bi-send me-1"></i>Test Command group</button>
              <button class="btn btn-outline-info" name="test_target" value="teams"><i class="bi bi-people me-1"></i><?= e(t('settings/municipality.134', 'Test κοινό group ομάδων')) ?></button>
            </form>
          </div>
        </div>
        <div class="card shadow-sm mt-3">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> <?= e(t('settings/municipality.135', 'Στήσιμο bot')) ?></div>
          <div class="card-body small text-muted">
            <ol class="ps-3 mb-3">
              <li class="mb-2">
                <?= e(t('settings/municipality.136', 'Ανοίξτε το')) ?> <a href="https://t.me/BotFather" target="_blank" rel="noopener">BotFather</a><?= e(t('settings/municipality.137', ', στείλτε')) ?> <code>/newbot</code> <?= e(t('settings/municipality.138', 'και αντιγράψτε το')) ?> <strong>Bot Token</strong>.
              </li>
              <li class="mb-2">
                <?= e(t('settings/municipality.139', 'Δημιουργήστε ή ανοίξτε το Telegram group/channel και προσθέστε το bot ως μέλος. Για channels, δώστε στο bot δικαίωμα δημοσίευσης.')) ?>
              </li>
              <li class="mb-2">
                <?= e(t('settings/municipality.140', 'Στείλτε ένα δοκιμαστικό μήνυμα μέσα στο group, π.χ.')) ?> <code>test syndrasi</code>.
              </li>
              <li class="mb-2">
                <?= e(t('settings/municipality.141', 'Ανοίξτε στον browser:')) ?>
                <code>https://api.telegram.org/bot123456789:ABCdefYourRealToken/getUpdates</code>
                <?= e(t('settings/municipality.142', 'αλλάζοντας το')) ?> <code>123456789:ABCdefYourRealToken</code> <?= e(t('settings/municipality.143', 'με το πραγματικό Bot Token από το BotFather, χωρίς')) ?> <code>&lt;</code> <?= e(t('settings/municipality.144', 'και')) ?> <code>&gt;</code><?= e(t('settings/municipality.145', '. Βρείτε το')) ?> <code>chat.id</code><?= e(t('settings/municipality.146', '. Συνήθως τα group/channel IDs είναι αρνητικά, π.χ.')) ?> <code>-1001234567890</code>.
              </li>
              <li class="mb-2">
                <?= e(t('settings/municipality.147', 'Αν αυτό είναι το group του φορέα, βάλτε το ID στο')) ?> <strong><?= e(t('settings/municipality.128', 'Command / Φορέας Chat ID')) ?></strong><?= e(t('settings/municipality.148', '. Αν είναι το κοινό group όπου θα είναι μέσα όλες οι εθελοντικές ομάδες, βάλτε το και στο')) ?> <strong><?= e(t('settings/municipality.130', 'Κοινό Chat ID ομάδων / εθελοντών')) ?></strong><?= e(t('settings/municipality.149', '. Μπορεί να είναι το ίδιο ID και στα δύο πεδία.')) ?>
              </li>
              <li>
                <?= e(t('settings/municipality.150', 'Πατήστε')) ?> <strong><?= e(t('settings/municipality.151', 'Αποστολή δοκιμαστικού')) ?></strong><?= e(t('settings/municipality.152', '. Αν το μήνυμα εμφανιστεί στο group, το Telegram είναι έτοιμο.')) ?>
              </li>
            </ol>
            <div class="border-top pt-3">
              <div class="fw-semibold text-body mb-2"><?= e(t('settings/municipality.153', 'Groups ομάδων')) ?></div>
              <p><?= e(t('settings/municipality.154', 'Αν θέλετε ένα κοινό group για όλους τους εθελοντές/admins, βάλτε το')) ?> <code>chat.id</code> <?= e(t('settings/municipality.155', 'στο')) ?> <strong><?= e(t('settings/municipality.130', 'Κοινό Chat ID ομάδων / εθελοντών')) ?></strong> <?= e(t('settings/municipality.156', 'και αφήστε κενά τα επιμέρους Telegram Chat ID των ομάδων.')) ?></p>
              <p class="mb-0"><?= e(t('settings/municipality.157', 'Το πεδίο')) ?> <strong><?= e(t('settings/municipality.158', 'Εθελοντικές Ομάδες → Επεξεργασία → Telegram Chat ID ομάδας')) ?></strong> <?= e(t('settings/municipality.159', 'χρειάζεται μόνο αν κάποια ομάδα έχει δικό της ξεχωριστό Telegram group και θέλετε τα μηνύματά της να πηγαίνουν εκεί αντί για το κοινό group.')) ?></p>
            </div>
            <div class="border-top pt-3 mt-3">
              <div class="fw-semibold text-body mb-2"><?= e(t('settings/municipality.160', 'Χρήσιμα links')) ?></div>
              <ul class="mb-0 ps-3">
                <li><a href="https://core.telegram.org/bots/tutorial" target="_blank" rel="noopener">Telegram Bots tutorial</a></li>
                <li><a href="https://core.telegram.org/bots/api#sendmessage" target="_blank" rel="noopener">Bot API: sendMessage</a></li>
                <li><a href="https://core.telegram.org/bots/api#getupdates" target="_blank" rel="noopener">Bot API: getUpdates</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Event defaults -->
  <div class="tab-pane fade" id="tab-event-defaults">
    <div class="row g-4">
      <div class="col-lg-7">
        <form method="post" action="<?= e(url('/settings/event-defaults')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-plus me-1"></i> <?= e(t('settings/municipality.161', 'Προεπιλογές')) ?> <?= e(t('authority/' . ($authorityContext['authority_type'] ?? 'municipality') . '.event_plural', $authorityContext['event_plural'] ?? 'Δράσεων')) ?></div>
          <div class="card-body row g-3">
            <div class="col-md-5">
              <label class="form-label"><?= e(t('settings/municipality.162', 'Προθεσμία δηλώσεων')) ?></label>
              <div class="input-group">
                <input type="number" name="event_application_deadline_days" min="0" class="form-control"
                       value="<?= e($v('event_application_deadline_days', '0')) ?>">
                <span class="input-group-text"><?= e(t('settings/municipality.163', 'ημέρες πριν')) ?></span>
              </div>
              <div class="form-text"><?= e(t('settings/municipality.223', '0 = χωρίς προθεσμία. Εμφανίζεται ως υπενθύμιση στη φόρμα')) ?> <?= e($eventSingularLc) ?>.</div>
            </div>
            <div class="col-12">
              <label class="form-label"><?= e(t('settings/municipality.165', 'Προεπιλεγμένες οδηγίες ομάδων')) ?></label>
              <textarea name="event_default_instructions" class="form-control" rows="4"
                        placeholder="<?= e(t('settings/municipality.213', 'π.χ. Προσέλευση 30 λεπτά πριν την έναρξη. Υποχρεωτική στολή ομάδας.')) ?>"><?= e($v('event_default_instructions')) ?></textarea>
              <div class="form-text"><?= e(t('settings/municipality.224', 'Προ-συμπληρώνεται στο πεδίο «Οδηγίες» κάθε νέας')) ?> <?= e($eventSingularLc) ?><?= e(t('settings/municipality.225', '. Μπορείτε να το αλλάξετε ανά')) ?> <?= e($eventSingularLc) ?>.</div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Branding & timezone -->
  <div class="tab-pane fade" id="tab-branding">
    <div class="row g-4">
      <div class="col-lg-7">
        <form method="post" action="<?= e(url('/settings/branding')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-palette me-1"></i> <?= e(t('settings/municipality.167', 'Εμφάνιση & Ζώνη Ώρας')) ?></div>
          <div class="card-body row g-3">
            <div class="col-12">
              <label class="form-label"><?= e(t('settings/municipality.168', 'URL λογότυπου φορέα')) ?></label>
              <input type="url" name="branding_logo_url" class="form-control"
                     value="<?= e($v('branding_logo_url')) ?>"
                     placeholder="https://www.dimos.gr/logo.png">
              <div class="form-text"><?= e(t('settings/municipality.169', 'Εμφανίζεται στο πλευρικό μενού (mobile) αντί για το εικονίδιο. PNG με διαφανές φόντο ιδανικά.')) ?></div>
            </div>
            <?php if ($v('branding_logo_url')): ?>
            <div class="col-12">
              <label class="form-label small text-muted"><?= e(t('settings/municipality.170', 'Προεπισκόπηση')) ?></label><br>
              <img src="<?= e($v('branding_logo_url')) ?>" alt="Logo"
                   style="max-height:60px;max-width:200px;object-fit:contain;border:1px solid #dee2e6;border-radius:6px;padding:6px;background:#fff;">
            </div>
            <?php endif; ?>
            <div class="col-md-7">
              <label class="form-label"><?= e(t('settings/municipality.171', 'Ζώνη ώρας')) ?></label>
              <select name="timezone" class="form-select">
                <?php foreach ($tzOptions as $tzVal => $tzLabel): ?>
                  <option value="<?= e($tzVal) ?>" <?= $v('timezone', 'Europe/Athens') === $tzVal ? 'selected' : '' ?>><?= e($tzLabel) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text"><?= e(t('settings/municipality.172', 'Επηρεάζει την εμφάνιση ημερομηνιών/ωρών στην πλατφόρμα.')) ?></div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Member Fields Config -->
  <div class="tab-pane fade" id="tab-members">
    <?php
    $memberFieldsRaw = $v('member_fields_config', '');
    $mfc = $memberFieldsRaw ? (json_decode($memberFieldsRaw, true) ?: []) : [];
    $memberFieldDefs = [
        'blood_type'      => ['label' => t('settings/municipality.278', 'Ομάδα Αίματος'),          'hint' => t('settings/municipality.279', 'π.χ. A+, O−')],
        'driving_license' => ['label' => t('settings/municipality.280', 'Δίπλωμα Οδήγησης'),       'hint' => t('settings/municipality.281', 'Κατηγορία αδείας οδήγησης')],
        'certifications'  => ['label' => t('settings/municipality.282', 'Πιστοποιήσεις'),           'hint' => t('settings/municipality.283', 'ΕΚΑΒ, πρώτες βοήθειες κλπ.')],
        'id_number'       => ['label' => t('settings/municipality.284', 'Αριθμός Ταυτότητας'),      'hint' => t('settings/municipality.285', 'ΑΔΤ μέλους')],
        'amka'            => ['label' => t('settings/municipality.286', 'ΑΜΚΑ'),                    'hint' => t('settings/municipality.287', 'Αριθμός Μητρώου Κοιν. Ασφάλισης')],
    ];
    ?>
    <div class="row g-4">
      <div class="col-lg-8">
        <form method="post" action="<?= e(url('/settings/member-fields')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i> <?= e(t('settings/municipality.173', 'Προαιρετικά πεδία μελών ομάδων')) ?></div>
          <div class="card-body">
            <p class="text-muted small mb-3">
              <?= e(t('settings/municipality.174', 'Τα πεδία')) ?> <strong><?= e(t('settings/municipality.175', 'Ονοματεπώνυμο')) ?></strong>, <strong><?= e(t('settings/municipality.176', 'Τηλέφωνο')) ?></strong>, <strong>Email</strong>,
              <strong><?= e(t('settings/municipality.177', 'ΑΜ Πολιτικής Προστασίας')) ?></strong> <?= e(t('settings/municipality.178', 'κλπ. είναι πάντα διαθέσιμα. Επιλέξτε ποια επιπλέον πεδία εμφανίζονται στη φόρμα μέλους.')) ?>
            </p>
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th><?= e(t('settings/municipality.179', 'Πεδίο')) ?></th>
                  <th class="text-center" style="width:110px"><?= e(t('settings/municipality.180', 'Ορατό')) ?></th>
                  <th class="text-center" style="width:130px"><?= e(t('settings/municipality.181', 'Υποχρεωτικό')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($memberFieldDefs as $field => $def):
                  $cfg = $mfc[$field] ?? ['visible' => false, 'required' => false];
                  $visible  = !empty($cfg['visible']);
                  $required = !empty($cfg['required']);
                ?>
                <tr>
                  <td>
                    <strong><?= e($def['label']) ?></strong>
                    <div class="small text-muted"><?= e($def['hint']) ?></div>
                  </td>
                  <td class="text-center">
                    <div class="form-check d-inline-block">
                      <input class="form-check-input member-visible-cb" type="checkbox"
                             name="mf_visible[]" value="<?= e($field) ?>"
                             id="mfv_<?= e($field) ?>"
                             data-field="<?= e($field) ?>"
                             <?= $visible ? 'checked' : '' ?>>
                    </div>
                  </td>
                  <td class="text-center">
                    <div class="form-check d-inline-block">
                      <input class="form-check-input member-required-cb" type="checkbox"
                             name="mf_required[]" value="<?= e($field) ?>"
                             id="mfr_<?= e($field) ?>"
                             data-field="<?= e($field) ?>"
                             <?= $required ? 'checked' : '' ?>
                             <?= !$visible ? 'disabled' : '' ?>>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.023', 'Αποθήκευση')) ?></button>
          </div>
        </form>
      </div>
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> <?= e(t('settings/municipality.182', 'Οδηγίες')) ?></div>
          <div class="card-body small text-muted">
            <p><strong><?= e(t('settings/municipality.180', 'Ορατό')) ?></strong><?= e(t('settings/municipality.183', ': εμφανίζεται στη φόρμα προσθήκης/επεξεργασίας μέλους.')) ?></p>
            <p><strong><?= e(t('settings/municipality.181', 'Υποχρεωτικό')) ?></strong><?= e(t('settings/municipality.184', ': αν ενεργοποιηθεί, ο team_admin δεν μπορεί να αποθηκεύσει μέλος χωρίς να συμπληρώσει αυτό το πεδίο.')) ?></p>
            <p><?= e(t('settings/municipality.185', 'Το «Υποχρεωτικό» ενεργοποιείται μόνο αν το πεδίο είναι ορατό.')) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Email Templates -->
  <div class="tab-pane fade" id="tab-email-templates">
    <div class="row g-4">
      <div class="col-xl-8">
        <form method="post" action="<?= e(url('/settings/email-templates')) ?>">
          <?= csrf_field() ?>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h5 mb-0"><i class="bi bi-envelope-paper me-2"></i><?= e(t('settings/municipality.008', 'Πρότυπα Email')) ?></h2>
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.186', 'Αποθήκευση όλων')) ?></button>
          </div>

          <div class="accordion" id="emailTplAccordion">
            <?php foreach ($emailTemplates as $type => $tplDef):
              $stored    = $emailTemplateValues[$type];
              $isCustom  = $stored['is_custom'];
              $collapseId = 'tpl_' . $type;
            ?>
            <div class="accordion-item border mb-2 rounded shadow-sm overflow-hidden">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed py-3" type="button"
                        data-bs-toggle="collapse" data-bs-target="#<?= e($collapseId) ?>">
                  <i class="bi <?= e($tplDef['icon']) ?> me-2 text-primary"></i>
                  <span class="fw-semibold me-2"><?= e($tplDef['label']) ?></span>
                  <?php if ($isCustom): ?>
                    <span class="badge text-bg-warning ms-1 small"><?= e(t('settings/municipality.187', 'Προσαρμοσμένο')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-light text-muted ms-1 small"><?= e(t('settings/municipality.188', 'Προεπιλογή')) ?></span>
                  <?php endif; ?>
                  <span class="ms-auto me-3 small text-muted fw-normal d-none d-md-inline">
                    <i class="bi bi-people me-1"></i><?= e($tplDef['recipient']) ?>
                  </span>
                </button>
              </h2>
              <div id="<?= e($collapseId) ?>" class="accordion-collapse collapse"
                   data-bs-parent="#emailTplAccordion">
                <div class="accordion-body bg-light">

                  <!-- Subject -->
                  <div class="mb-3">
                    <label class="form-label fw-semibold small"><?= e(t('settings/municipality.189', 'Θέμα email (Subject)')) ?></label>
                    <input type="text" class="form-control"
                           name="tpl[<?= e($type) ?>][subject]"
                           id="subj_<?= e($type) ?>"
                           value="<?= e($stored['subject']) ?>">
                  </div>

                  <!-- Body -->
                  <div class="mb-3">
                    <label class="form-label fw-semibold small"><?= e(t('settings/municipality.190', 'Σώμα email (Body)')) ?></label>
                    <textarea class="form-control font-monospace small"
                              name="tpl[<?= e($type) ?>][body]"
                              id="body_<?= e($type) ?>"
                              rows="8"><?= e($stored['body']) ?></textarea>
                    <div class="form-text"><?= e(t('settings/municipality.191', 'Κλικ σε placeholder για εισαγωγή στο σημείο του cursor.')) ?></div>
                  </div>

                  <!-- Placeholder badges -->
                  <div class="mb-3">
                    <span class="small fw-semibold text-muted me-2"><?= e(t('settings/municipality.192', 'Μεταβλητές:')) ?></span>
                    <?php foreach ($tplDef['vars'] as $varKey => $varLabel): ?>
                      <span class="badge text-bg-secondary me-1 mb-1 cursor-pointer tpl-var-badge"
                            style="font-size:.78rem;cursor:pointer;"
                            data-var="{<?= e($varKey) ?>}"
                            data-target="body_<?= e($type) ?>"
                            title="<?= e($varLabel) ?>">
                        {<?= e($varKey) ?>}
                      </span>
                    <?php endforeach; ?>
                  </div>

                  <!-- Actions -->
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary tpl-reset-btn"
                            data-type="<?= e($type) ?>"
                            data-default-subject="<?= e($tplDef['subject']) ?>"
                            data-default-body="<?= e($tplDef['body']) ?>">
                      <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(t('settings/municipality.193', 'Επαναφορά αρχικών')) ?>
                    </button>
                    <span class="small text-muted align-self-center">
                      <i class="bi bi-people me-1"></i><?= e(t('settings/municipality.194', 'Παραλήπτης:')) ?> <?= e($tplDef['recipient']) ?>
                    </span>
                  </div>

                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div><!-- /accordion -->

          <div class="mt-3 text-end">
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i><?= e(t('settings/municipality.186', 'Αποθήκευση όλων')) ?></button>
          </div>
        </form>
      </div>

      <div class="col-xl-4">
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> <?= e(t('settings/municipality.073', 'Πώς λειτουργεί')) ?></div>
          <div class="card-body small text-muted">
            <p><?= e(t('settings/municipality.195', 'Κάθε τύπος email έχει ένα')) ?> <strong><?= e(t('settings/municipality.035', 'Θέμα')) ?></strong> <?= e(t('settings/municipality.196', '(Subject) και ένα')) ?> <strong><?= e(t('settings/municipality.197', 'Σώμα')) ?></strong> (Body).</p>
            <p><?= e(t('settings/municipality.198', 'Χρησιμοποιήστε τα')) ?> <strong>{placeholders}</strong> <?= e(t('settings/municipality.199', 'για να εισάγετε δυναμικές τιμές που αντικαθίστανται αυτόματα κατά την αποστολή (π.χ.')) ?> <code>{event_title}</code>).</p>
            <p><?= e(t('settings/municipality.200', 'Κάντε κλικ σε ένα badge placeholder για εισαγωγή στο cursor του textarea.')) ?></p>
            <p><?= e(t('settings/municipality.201', 'Τα emails στέλνονται ως')) ?> <strong>HTML</strong> <?= e(t('settings/municipality.202', 'με αυτόματο wrapper που περιλαμβάνει το λογότυπο του φορέα (από την καρτέλα Εμφάνιση).')) ?></p>
            <hr>
            <p class="mb-0"><?= e(t('settings/municipality.203', 'Το κουμπί')) ?> <strong><?= e(t('settings/municipality.193', 'Επαναφορά αρχικών')) ?></strong> <?= e(t('settings/municipality.204', 'επαναφέρει το default κείμενο — η αλλαγή γίνεται μόνιμη μετά την Αποθήκευση.')) ?></p>
          </div>
        </div>
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-palette me-1"></i> Branding</div>
          <div class="card-body small text-muted">
            <p class="mb-1"><?= e(t('settings/municipality.205', 'Το λογότυπο και το όνομα του φορέα εμφανίζονται αυτόματα στην κεφαλίδα κάθε email.')) ?></p>
            <a href="#tab-branding" class="tpl-switch-tab"><?= e(t('settings/municipality.206', 'Ρύθμιση λογότυπου →')) ?></a>
          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /tab-content -->

<script>
// Auto-uncheck "required" when "visible" is unchecked
document.querySelectorAll('.member-visible-cb').forEach(function (cb) {
  cb.addEventListener('change', function () {
    var field = this.dataset.field;
    var reqCb = document.querySelector('.member-required-cb[data-field="' + field + '"]');
    if (reqCb) {
      reqCb.disabled = !this.checked;
      if (!this.checked) reqCb.checked = false;
    }
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var hash = window.location.hash;
  if (hash) {
    var tab = document.querySelector('#settingsTabs a[href="' + hash + '"]');
    if (tab) { new bootstrap.Tab(tab).show(); return; }
  }
  var first = document.querySelector('#settingsTabs .nav-link');
  if (first) { new bootstrap.Tab(first).show(); }
});

// Email template: click placeholder badge → insert {var} at textarea cursor
document.querySelectorAll('.tpl-var-badge').forEach(function (badge) {
  badge.addEventListener('click', function () {
    var targetId = this.dataset.target;
    var varText  = this.dataset.var;
    var ta = document.getElementById(targetId);
    if (!ta) { return; }
    ta.focus();
    var start = ta.selectionStart;
    var end   = ta.selectionEnd;
    ta.value  = ta.value.substring(0, start) + varText + ta.value.substring(end);
    ta.selectionStart = ta.selectionEnd = start + varText.length;
  });
});

// Email template: reset to default values
document.querySelectorAll('.tpl-reset-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var type    = this.dataset.type;
    var subjEl  = document.getElementById('subj_' + type);
    var bodyEl  = document.getElementById('body_' + type);
    if (subjEl) { subjEl.value = this.dataset.defaultSubject; }
    if (bodyEl) { bodyEl.value = this.dataset.defaultBody; }
  });
});

// "Ρύθμιση λογότυπου →" link inside the templates tab
document.querySelectorAll('.tpl-switch-tab').forEach(function (a) {
  a.addEventListener('click', function (e) {
    e.preventDefault();
    var target = document.querySelector('#settingsTabs a[href="#tab-branding"]');
    if (target) { new bootstrap.Tab(target).show(); }
  });
});
</script>
