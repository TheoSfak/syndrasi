<style>
.org-preset-btn{display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 16px;border:2px solid #dee2e6;border-radius:12px;background:#fff;cursor:pointer;transition:all .15s;min-width:90px;color:inherit;}
.org-preset-btn:hover{border-color:#0d6efd;background:#f0f5ff;}
.org-preset-btn.active{border-color:#0d6efd;background:#dbeafe;color:#1d4ed8;}
.org-preset-icon{font-size:1.6rem;line-height:1;}
.org-preset-label{font-size:.75rem;font-weight:600;white-space:nowrap;}
.org-preview-box{background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:12px 14px;}
</style>

<h1 class="h3 mb-1">Ρυθμίσεις Δήμου</h1>
<p class="text-muted mb-3"><?= e($municipality['name']) ?></p>

<?php
$v = function ($key, $default = '') use ($settings) {
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
};
$notifyOn = function ($key) use ($settings) {
    if (!isset($settings[$key])) return true; // default ON
    return $settings[$key] === '1';
};
$driver = $v('mail_driver');

$tzOptions = [
    'Europe/Athens'    => 'Αθήνα (UTC+2/+3)',
    'Europe/Nicosia'   => 'Λευκωσία (UTC+2/+3)',
    'Europe/Istanbul'  => 'Κωνσταντινούπολη (UTC+3)',
    'Europe/Rome'      => 'Ρώμη (UTC+1/+2)',
    'Europe/Paris'     => 'Παρίσι (UTC+1/+2)',
    'Europe/Berlin'    => 'Βερολίνο (UTC+1/+2)',
    'Europe/London'    => 'Λονδίνο (UTC+0/+1)',
    'UTC'              => 'UTC',
];
?>

<ul class="nav nav-tabs mb-4" id="settingsTabs">
  <li class="nav-item"><a class="nav-link" href="#tab-organisation"   data-bs-toggle="tab"><i class="bi bi-building me-1"></i>Οργανισμός</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-mail"           data-bs-toggle="tab"><i class="bi bi-envelope me-1"></i>Email</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-map"            data-bs-toggle="tab"><i class="bi bi-map me-1"></i>Χάρτης</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-awards"         data-bs-toggle="tab"><i class="bi bi-trophy me-1"></i>Βραβεία</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-notifications"  data-bs-toggle="tab"><i class="bi bi-bell me-1"></i>Ειδοποιήσεις</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-sms"            data-bs-toggle="tab"><i class="bi bi-chat-dots me-1"></i>SMS</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-telegram"       data-bs-toggle="tab"><i class="bi bi-telegram me-1"></i>Telegram</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-event-defaults" data-bs-toggle="tab"><i class="bi bi-calendar-plus me-1"></i>Δράσεις</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-branding"       data-bs-toggle="tab"><i class="bi bi-palette me-1"></i>Εμφάνιση</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-members"          data-bs-toggle="tab"><i class="bi bi-people me-1"></i>Μέλη Ομάδων</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-email-templates" data-bs-toggle="tab"><i class="bi bi-envelope-paper me-1"></i>Πρότυπα Email</a></li>
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
              <label class="form-label">Τρόπος αποστολής email</label>
              <select name="mail_driver" class="form-select">
                <option value="" <?= $driver === '' ? 'selected' : '' ?>>Προεπιλογή πλατφόρμας (<?= e(config('mail')['driver']) ?>)</option>
                <option value="smtp" <?= $driver === 'smtp' ? 'selected' : '' ?>>SMTP (προτεινόμενο για πραγματική αποστολή)</option>
                <option value="mail" <?= $driver === 'mail' ? 'selected' : '' ?>>PHP mail()</option>
                <option value="log"  <?= $driver === 'log'  ? 'selected' : '' ?>>Μόνο καταγραφή (log, για δοκιμές)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email αποστολέα (From)</label>
              <input type="email" name="mail_from_email" class="form-control" value="<?= e($v('mail_from_email')) ?>" placeholder="no-reply@dimos.gr">
            </div>
            <div class="col-md-6">
              <label class="form-label">Όνομα αποστολέα</label>
              <input type="text" name="mail_from_name" class="form-control" value="<?= e($v('mail_from_name')) ?>" placeholder="<?= e($municipality['name']) ?>">
            </div>
            <div class="col-12"><hr class="my-1"><strong class="small text-muted">ΡΥΘΜΙΣΕΙΣ SMTP</strong></div>
            <div class="col-md-8">
              <label class="form-label">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control" value="<?= e($v('smtp_host')) ?>" placeholder="π.χ. smtp.gmail.com">
            </div>
            <div class="col-md-4">
              <label class="form-label">Θύρα</label>
              <input type="number" name="smtp_port" class="form-control" value="<?= e($v('smtp_port', '587')) ?>" placeholder="587">
            </div>
            <div class="col-md-6">
              <label class="form-label">Όνομα χρήστη SMTP</label>
              <input type="text" name="smtp_user" class="form-control" value="<?= e($v('smtp_user')) ?>" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label">Κωδικός SMTP</label>
              <input type="password" name="smtp_pass" class="form-control" autocomplete="new-password"
                     placeholder="<?= $v('smtp_pass') !== '' ? '••••••••  (αφήστε κενό για να μην αλλάξει)' : '' ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Ασφάλεια σύνδεσης</label>
              <select name="smtp_secure" class="form-select">
                <option value="tls" <?= $v('smtp_secure', 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (θύρα 587)</option>
                <option value="ssl" <?= $v('smtp_secure') === 'ssl' ? 'selected' : '' ?>>SSL (θύρα 465)</option>
                <option value=""   <?= ($v('smtp_secure', 'tls') === '' && isset($settings['smtp_secure'])) ? 'selected' : '' ?>>Χωρίς κρυπτογράφηση</option>
              </select>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-send-check me-1"></i> Δοκιμή αποστολής</div>
          <div class="card-body">
            <p class="small text-muted mb-3">Στέλνει δοκιμαστικό email στο <strong><?= e(current_user()['email']) ?></strong> με τις αποθηκευμένες ρυθμίσεις.</p>
            <form method="post" action="<?= e(url('/settings/mail/test')) ?>">
              <?= csrf_field() ?>
              <button class="btn btn-outline-primary w-100"><i class="bi bi-envelope-paper me-1"></i>Αποστολή δοκιμαστικού email</button>
            </form>
          </div>
        </div>
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Τρέχουσα κατάσταση</div>
          <ul class="list-group list-group-flush small">
            <li class="list-group-item d-flex justify-content-between">
              <span>Ενεργός τρόπος</span>
              <strong><?= e($effective['driver']) ?><?= $driver === '' ? ' (προεπιλογή)' : '' ?></strong>
            </li>
            <li class="list-group-item d-flex justify-content-between">
              <span>Αποστολέας</span>
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

  <!-- Map defaults -->
  <div class="tab-pane fade" id="tab-map">
    <div class="row g-4">
      <div class="col-lg-6">
        <form method="post" action="<?= e(url('/settings/map')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-1"></i> Προεπιλογές Επιχειρησιακού Χάρτη</div>
          <div class="card-body row g-3">
            <p class="text-muted small mb-0">Κέντρο και ζουμ χάρτη όταν μια δράση δεν έχει καταχωρημένες συντεταγμένες.</p>
            <div class="col-md-6">
              <label class="form-label">Γεωγρ. πλάτος (lat)</label>
              <input type="text" name="map_lat" class="form-control" value="<?= e($v('map_lat')) ?>" placeholder="π.χ. 35.3387">
              <div class="form-text">−90 έως 90 · κενό = fallback Ελλάδα</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Γεωγρ. μήκος (lng)</label>
              <input type="text" name="map_lng" class="form-control" value="<?= e($v('map_lng')) ?>" placeholder="π.χ. 25.1442">
              <div class="form-text">−180 έως 180</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Επίπεδο ζουμ (1–19)</label>
              <input type="number" name="map_zoom" min="1" max="19" class="form-control" value="<?= e($v('map_zoom', '13')) ?>">
              <div class="form-text">13 = πόλη · 15 = οδός · 10 = νομός</div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
      </div>
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">Παραδείγματα συντεταγμένων</div>
          <ul class="list-group list-group-flush small">
            <li class="list-group-item d-flex justify-content-between"><span>Ηράκλειο Κρήτης</span><code>35.3387, 25.1442</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Αθήνα</span><code>37.9838, 23.7275</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Θεσσαλονίκη</span><code>40.6401, 22.9444</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Πάτρα</span><code>38.2466, 21.7346</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Λάρισα</span><code>39.6390, 22.4191</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Ρόδος</span><code>36.4349, 28.2176</code></li>
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
          <div class="card-header bg-white fw-semibold"><i class="bi bi-trophy me-1"></i> Βαθμίδες Συμμετοχής</div>
          <div class="card-body row g-3">
            <p class="text-muted small mb-0">Αριθμός δράσεων που χρειάζεται μια ομάδα για κάθε βαθμίδα στην κατάταξη.</p>
            <div class="col-md-4">
              <label class="form-label">🥉 Χάλκινη</label>
              <input type="number" name="award_bronze_events" min="1" class="form-control" value="<?= e($v('award_bronze_events', '5')) ?>">
              <div class="form-text">ελάχ. δράσεις</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">🥈 Ασημένια</label>
              <input type="number" name="award_silver_events" min="1" class="form-control" value="<?= e($v('award_silver_events', '10')) ?>">
              <div class="form-text">ελάχ. δράσεις</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">🥇 Χρυσή</label>
              <input type="number" name="award_gold_events" min="1" class="form-control" value="<?= e($v('award_gold_events', '20')) ?>">
              <div class="form-text">ελάχ. δράσεις</div>
            </div>
            <div class="col-12"><hr class="my-1"></div>
            <div class="col-md-8">
              <label class="form-label">Ελάχ. δράσεις για Συνέπεια & Απόκριση</label>
              <input type="number" name="award_min_events" min="1" class="form-control" value="<?= e($v('award_min_events', '3')) ?>">
              <div class="form-text">Ομάδες με λιγότερες δράσεις δεν λαμβάνουν αυτά τα ποιοτικά βραβεία.</div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
      </div>
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Πώς λειτουργεί</div>
          <div class="card-body small text-muted">
            <p>Τα εικονίδια 🥉🥈🥇 εμφανίζονται δίπλα στο όνομα κάθε ομάδας στη σελίδα <strong>Επιβράβευση Ομάδων</strong>, βάσει του αριθμού δράσεων που συμμετείχε.</p>
            <p>Τα τέσσερα ποιοτικά βραβεία (Καλύτερη Προσφορά, Πιο Δραστήρια κ.λπ.) ανακηρύσσονται ανεξάρτητα.</p>
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
          <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-1"></i> Κανάλι ειδοποιήσεων</div>
          <div class="card-body">
            <p class="text-muted small">Οι in-app ειδοποιήσεις (κουδούνι) στέλνονται πάντα. Εδώ επιλέγετε αν θα αποστέλλεται επιπλέον <strong>Email</strong>, <strong>SMS</strong> ή <strong>και τα δύο</strong> ανά τύπο. Το Telegram ενεργοποιείται ανεξάρτητα ανά τύπο και απαιτεί ρυθμίσεις στην καρτέλα «Telegram».</p>
            <?php
            $notifTypes = [
                ['event_published',        'Νέα δράση δημοσιεύτηκε',  'Σε όλες τις ενεργές ομάδες'],
                ['application_submitted',  'Νέα δήλωση συμμετοχής',   'Στους διαχειριστές δήμου'],
                ['application_approved',   'Έγκριση συμμετοχής',      'Στην ομάδα'],
                ['application_rejected',   'Απόρριψη συμμετοχής',     'Στην ομάδα'],
                ['shortage_reported',      'Αναφορά έλλειψης',        'Στους διαχειριστές δήμου'],
                ['event_reminder',         'Υπενθύμιση δράσης',       'Χειροκίνητη, κουμπί «Υπενθύμιση»'],
                ['event_completed',        'Ολοκλήρωση δράσης',       'Στο command group και στις εγκεκριμένες ομάδες'],
            ];
            $opsNotifTypes = [
                ['photo_request',          'Αίτημα φωτογραφίας',      'Στην ομάδα κατά την ενεργή δράση'],
                ['video_request',          'Αίτημα βίντεο',           'Στην ομάδα κατά την ενεργή δράση'],
                ['gps_request',            'Αίτημα στίγματος GPS',    'Στην ομάδα κατά την ενεργή δράση'],
                ['photo_uploaded',         'Φωτογραφία ελήφθη',       'Στους διαχειριστές δήμου'],
                ['video_uploaded',         'Βίντεο ελήφθη',           'Στους διαχειριστές δήμου'],
                ['gps_arrived',            'Στίγμα GPS ελήφθη',       'Στους διαχειριστές δήμου'],
                ['ops_message',            'Μήνυμα επιχειρήσεων',     'Δήμος ↔ ομάδα, μη κρίσιμο'],
                ['ops_geo',                'Σημείο/μετακίνηση',       'Μη κρίσιμο ή forced όταν είναι εντολή'],
                ['team_silent',            'Ομάδα σε σίγη',           'Στους διαχειριστές δήμου'],
                ['shortage_update',        'Ενημέρωση έλλειψης',      'Στην ομάδα'],
                ['sos_ack',                'Επιβεβαίωση SOS',         'Στην ομάδα'],
            ];
            $telegramExternalTypes = [
                ['fire_service_crete',      'Συμβάντα Πυροσβεστικής Κρήτης', 'Νέα συμβάντα ή αλλαγές κατάστασης σε ΣΕ ΕΞΕΛΙΞΗ / ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ'],
                ['fire_risk_crete',         'Χάρτης κινδύνου πυρκαγιάς Κρήτης', 'Ημερήσια πρόβλεψη κινδύνου ανά Π.Ε. Κρήτης από την Πολιτική Προστασία'],
            ];
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $fireRiskCronUrl = $scheme . '://' . $host . url('/cron/fire-risk-map');
            $fireRiskCronCommand = 'curl -s -H "Authorization: Bearer TOKEN" "' . $fireRiskCronUrl . '" > /dev/null';
            $opsTypeKeys = array_map(static function ($row) { return $row[0]; }, $opsNotifTypes);
            $channelOpts = ['off' => 'Καμία', 'email' => 'Μόνο Email', 'sms' => 'Μόνο SMS', 'both' => 'Email + SMS'];
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
              <div class="fw-semibold mb-1"><i class="bi bi-broadcast-pin text-info me-1"></i>Επιχειρησιακές ειδοποιήσεις</div>
              <p class="small text-muted mb-2">Οι in-app/push ειδοποιήσεις παραμένουν πάντα ενεργές. Εδώ επιλέγετε επιπλέον Email/SMS και Telegram ανά τύπο.</p>
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
              <div class="fw-semibold mb-1"><i class="bi bi-fire text-danger me-1"></i>Εξωτερικές πηγές</div>
              <p class="small text-muted mb-2">Αποστολή μόνο μέσω Telegram. Τα συμβάντα Πυροσβεστικής αποστέλλονται μία φορά ανά συμβάν και ξανά μόνο αν αλλάξει σχετική κατάσταση.</p>
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
                    <div class="fw-semibold small">Χειροκίνητος έλεγχος χάρτη κινδύνου</div>
                    <div class="small text-muted">Τρέχει τώρα τον ημερήσιο χάρτη Πολιτικής Προστασίας. Δεν στέλνει διπλά αν έχει ήδη σταλεί για την ίδια ημερομηνία.</div>
                  </div>
                  <button type="submit" form="fireRiskManualForm" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-fire me-1"></i>Έλεγχος τώρα
                  </button>
                </div>
                <div class="small text-muted mb-1">Cron κάθε 60 λεπτά:</div>
                <code class="d-block small text-break"><?= e($fireRiskCronCommand) ?></code>
              </div>
            </div>
          </div>
            <div class="border-top mt-1 pt-3 px-1 pb-2">
              <div class="fw-semibold mb-1"><i class="bi bi-shield-exclamation text-warning me-1"></i>Ειδοποιήσεις Επιχείρησης (Ops)</div>
              <p class="small text-muted mb-2">Εφαρμόζονται μόνο κατά τη διάρκεια ενεργών δράσεων. SOS, περιστατικό και εντολή αποστέλλονται forced και σε Telegram όταν υπάρχει ρύθμιση.</p>
              <div class="d-flex align-items-center gap-3 py-2">
                <div class="flex-grow-1">
                  <div class="fw-semibold small">Ειδοποίηση σίγης ομάδας</div>
                  <div class="text-muted" style="font-size:12px">Αν ομάδα δεν στείλει GPS για τόσα λεπτά, ο αρχηγός λαμβάνει ειδοποίηση. <strong>0 = απενεργοποιημένο</strong>.</div>
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                  <input type="number" name="ops_silent_team_minutes" min="0" max="120"
                         value="<?= e($settings['ops_silent_team_minutes'] ?? '20') ?>"
                         class="form-control form-control-sm text-center" style="width:72px">
                  <span class="small text-muted">λεπτά</span>
                </div>
              </div>
            </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
        <form id="fireRiskManualForm" method="post" action="<?= e(url('/settings/fire-risk-map/sync')) ?>">
          <?= csrf_field() ?>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Σημείωση</div>
          <div class="card-body small text-muted">
            <p>Η απενεργοποίηση ενός τύπου δεν επηρεάζει τις in-app ειδοποιήσεις — αυτές εμφανίζονται πάντα στο κουδούνι.</p>
            <p>Χρήσιμο όταν ο SMTP δεν έχει ρυθμιστεί ακόμα ή σε περίοδο δοκιμών για να αποφύγετε spam.</p>
            <p class="mb-0"><strong>Ops:</strong> SOS, περιστατικά και εντολές φεύγουν forced και σε Telegram όταν έχει οριστεί Bot Token και Chat ID.</p>
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
              <label class="form-label">Τρόπος αποστολής SMS</label>
              <select name="sms_driver" class="form-select">
                <option value=""       <?= $smsDriver === ''       ? 'selected' : '' ?>>Προεπιλογή πλατφόρμας (<?= e(config('sms')['driver']) ?>)</option>
                <option value="smsbox" <?= $smsDriver === 'smsbox' ? 'selected' : '' ?>>smsbox.gr</option>
                <option value="http"   <?= $smsDriver === 'http'   ? 'selected' : '' ?>>Γενικό HTTP gateway</option>
                <option value="log"    <?= $smsDriver === 'log'    ? 'selected' : '' ?>>Μόνο καταγραφή (log, για δοκιμές)</option>
                <option value="none"   <?= $smsDriver === 'none'   ? 'selected' : '' ?>>Απενεργοποιημένο</option>
              </select>
              <div class="form-text">Για το <strong>smsbox.gr</strong> συμπληρώστε username + password (συνιστάται) ή επικολλήστε ένα ενεργό sesskey στο πεδίο «API Key / Password».</div>
            </div>
            <div class="col-md-5">
              <label class="form-label">Όνομα αποστολέα (Sender / from)</label>
              <input type="text" name="sms_sender" class="form-control" value="<?= e($v('sms_sender')) ?>" placeholder="SynDrasi" maxlength="11">
              <div class="form-text">Έως 11 χαρακτήρες (εγκεκριμένο alphanumeric sender ID).</div>
            </div>
            <div class="col-md-7">
              <label class="form-label">Username (smsbox)</label>
              <input type="text" name="sms_username" class="form-control" value="<?= e($v('sms_username')) ?>" autocomplete="off" placeholder="username λογαριασμού smsbox">
              <div class="form-text">Αφήστε κενό αν θα χρησιμοποιήσετε απευθείας sesskey.</div>
            </div>
            <div class="col-12"><hr class="my-1"><strong class="small text-muted">ΔΙΑΠΙΣΤΕΥΤΗΡΙΑ</strong></div>
            <div class="col-md-6">
              <label class="form-label">API Key / Password / sesskey</label>
              <input type="password" name="sms_api_key" class="form-control" autocomplete="new-password"
                     placeholder="<?= $smsKeySet ? '•••••••• (αποθηκευμένο — αφήστε κενό για να μην αλλάξει)' : 'password (με username) ή sesskey' ?>">
              <div class="form-text"><?= $smsKeySet ? 'Υπάρχει ήδη αποθηκευμένο. Συμπληρώστε μόνο για αλλαγή.' : 'Με username → βάλτε password. Χωρίς username → βάλτε sesskey (λήγει σε 2 ώρες).' ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Endpoint (μόνο για γενικό HTTP gateway)</label>
              <input type="url" name="sms_endpoint" class="form-control" value="<?= e($v('sms_endpoint')) ?>" placeholder="https://api.provider.gr/send">
              <div class="form-text">Το smsbox.gr δεν χρειάζεται endpoint.</div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Πώς λειτουργεί</div>
          <div class="card-body small text-muted">
            <p>Τα <strong>credits SMS</strong> τα αγοράζετε απευθείας από τον πάροχο. Εδώ καταχωρείτε μόνο το κλειδί σύνδεσης ώστε η εφαρμογή να στέλνει μέσω του λογαριασμού σας.</p>
            <p>Στην καρτέλα <strong>Ειδοποιήσεις</strong> επιλέγετε ανά τύπο αν θα φεύγει Email, SMS ή και τα δύο.</p>
            <p>Σε λειτουργία <em>log</em>, τα μηνύματα γράφονται στο <code>storage/logs/sms.log</code> για δοκιμές χωρίς χρέωση.</p>
          </div>
        </div>
        <div class="card shadow-sm mt-3">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-send-check me-1"></i> Δοκιμαστικό SMS</div>
          <div class="card-body">
            <p class="small text-muted mb-2">Αποθηκεύστε πρώτα τις ρυθμίσεις, μετά στείλτε ένα δοκιμαστικό SMS.</p>
            <form method="post" action="<?= e(url('/settings/sms/test')) ?>" class="d-flex gap-2">
              <?= csrf_field() ?>
              <input type="text" name="test_to" class="form-control" placeholder="π.χ. 69XXXXXXXX" required>
              <button class="btn btn-outline-primary" type="submit"><i class="bi bi-send me-1"></i>Αποστολή</button>
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
                <label class="form-check-label fw-semibold" for="telegramEnabled">Ενεργοποίηση Telegram αποστολών</label>
              </div>
              <div class="form-text">Αφορά command group δήμου και group/channel ομάδων. Τα προσωπικά Telegram DM δεν είναι μέρος αυτού του MVP.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Bot Token</label>
              <input type="password" name="telegram_bot_token" class="form-control" autocomplete="new-password"
                     placeholder="<?= $tgTokenSet ? '•••••••• (αποθηκευμένο — αφήστε κενό για να μην αλλάξει)' : '123456789:AA...' ?>">
              <div class="form-text"><?= $tgTokenSet ? 'Υπάρχει ήδη αποθηκευμένο token. Συμπληρώστε μόνο για αλλαγή.' : 'Δημιουργείται από το BotFather.' ?></div>
            </div>
            <div class="col-12">
              <label class="form-label">Command / Δήμος Chat ID</label>
              <input type="text" name="telegram_command_chat_id" class="form-control" value="<?= e($tgCommandChat) ?>" placeholder="π.χ. -1001234567890">
              <div class="form-text">Group/channel όπου θα πηγαίνουν ειδοποιήσεις προς τον δήμο/φορέα.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Κοινό Chat ID ομάδων / εθελοντών</label>
              <input type="text" name="telegram_team_chat_id" class="form-control" value="<?= e($tgTeamChat) ?>" placeholder="π.χ. -1001234567890">
              <div class="form-text">Group όπου μπορούν να είναι μέσα όλες οι εθελοντικές ομάδες και οι admins. Χρησιμοποιείται για ειδοποιήσεις προς ομάδες όταν η ομάδα δεν έχει δικό της Telegram Chat ID.</div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-send-check me-1"></i> Δοκιμαστικό Telegram</div>
          <div class="card-body">
            <p class="small text-muted mb-3">Αποθηκεύστε πρώτα τις ρυθμίσεις και μετά δοκιμάστε κάθε group.</p>
            <form method="post" action="<?= e(url('/settings/telegram/test')) ?>" class="d-grid gap-2">
              <?= csrf_field() ?>
              <button class="btn btn-outline-primary" name="test_target" value="command"><i class="bi bi-send me-1"></i>Test Command group</button>
              <button class="btn btn-outline-info" name="test_target" value="teams"><i class="bi bi-people me-1"></i>Test κοινό group ομάδων</button>
            </form>
          </div>
        </div>
        <div class="card shadow-sm mt-3">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Στήσιμο bot</div>
          <div class="card-body small text-muted">
            <ol class="ps-3 mb-3">
              <li class="mb-2">
                Ανοίξτε το <a href="https://t.me/BotFather" target="_blank" rel="noopener">BotFather</a>, στείλτε <code>/newbot</code> και αντιγράψτε το <strong>Bot Token</strong>.
              </li>
              <li class="mb-2">
                Δημιουργήστε ή ανοίξτε το Telegram group/channel και προσθέστε το bot ως μέλος. Για channels, δώστε στο bot δικαίωμα δημοσίευσης.
              </li>
              <li class="mb-2">
                Στείλτε ένα δοκιμαστικό μήνυμα μέσα στο group, π.χ. <code>test syndrasi</code>.
              </li>
              <li class="mb-2">
                Ανοίξτε στον browser:
                <code>https://api.telegram.org/bot123456789:ABCdefYourRealToken/getUpdates</code>
                αλλάζοντας το <code>123456789:ABCdefYourRealToken</code> με το πραγματικό Bot Token από το BotFather, χωρίς <code>&lt;</code> και <code>&gt;</code>.
                Βρείτε το <code>chat.id</code>. Συνήθως τα group/channel IDs είναι αρνητικά, π.χ. <code>-1001234567890</code>.
              </li>
              <li class="mb-2">
                Αν αυτό είναι το group του δήμου/φορέα, βάλτε το ID στο <strong>Command / Δήμος Chat ID</strong>. Αν είναι το κοινό group όπου θα είναι μέσα όλες οι εθελοντικές ομάδες, βάλτε το και στο <strong>Κοινό Chat ID ομάδων / εθελοντών</strong>. Μπορεί να είναι το ίδιο ID και στα δύο πεδία.
              </li>
              <li>
                Πατήστε <strong>Αποστολή δοκιμαστικού</strong>. Αν το μήνυμα εμφανιστεί στο group, το Telegram είναι έτοιμο.
              </li>
            </ol>
            <div class="border-top pt-3">
              <div class="fw-semibold text-body mb-2">Groups ομάδων</div>
              <p>Αν θέλετε ένα κοινό group για όλους τους εθελοντές/admins, βάλτε το <code>chat.id</code> στο <strong>Κοινό Chat ID ομάδων / εθελοντών</strong> και αφήστε κενά τα επιμέρους Telegram Chat ID των ομάδων.</p>
              <p class="mb-0">Το πεδίο <strong>Εθελοντικές Ομάδες → Επεξεργασία → Telegram Chat ID ομάδας</strong> χρειάζεται μόνο αν κάποια ομάδα έχει δικό της ξεχωριστό Telegram group και θέλετε τα μηνύματά της να πηγαίνουν εκεί αντί για το κοινό group.</p>
            </div>
            <div class="border-top pt-3 mt-3">
              <div class="fw-semibold text-body mb-2">Χρήσιμα links</div>
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
          <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-plus me-1"></i> Προεπιλογές Δράσεων</div>
          <div class="card-body row g-3">
            <div class="col-md-5">
              <label class="form-label">Προθεσμία δηλώσεων</label>
              <div class="input-group">
                <input type="number" name="event_application_deadline_days" min="0" class="form-control"
                       value="<?= e($v('event_application_deadline_days', '0')) ?>">
                <span class="input-group-text">ημέρες πριν</span>
              </div>
              <div class="form-text">0 = χωρίς προθεσμία. Εμφανίζεται ως υπενθύμιση στη φόρμα δράσης.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Προεπιλεγμένες οδηγίες ομάδων</label>
              <textarea name="event_default_instructions" class="form-control" rows="4"
                        placeholder="π.χ. Προσέλευση 30 λεπτά πριν την έναρξη. Υποχρεωτική στολή ομάδας."><?= e($v('event_default_instructions')) ?></textarea>
              <div class="form-text">Προ-συμπληρώνεται στο πεδίο «Οδηγίες» κάθε νέας δράσης. Μπορείτε να το αλλάξετε ανά δράση.</div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
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
          <div class="card-header bg-white fw-semibold"><i class="bi bi-palette me-1"></i> Εμφάνιση & Ζώνη Ώρας</div>
          <div class="card-body row g-3">
            <div class="col-12">
              <label class="form-label">URL λογότυπου δήμου</label>
              <input type="url" name="branding_logo_url" class="form-control"
                     value="<?= e($v('branding_logo_url')) ?>"
                     placeholder="https://www.dimos.gr/logo.png">
              <div class="form-text">Εμφανίζεται στο πλευρικό μενού (mobile) αντί για το εικονίδιο. PNG με διαφανές φόντο ιδανικά.</div>
            </div>
            <?php if ($v('branding_logo_url')): ?>
            <div class="col-12">
              <label class="form-label small text-muted">Προεπισκόπηση</label><br>
              <img src="<?= e($v('branding_logo_url')) ?>" alt="Logo"
                   style="max-height:60px;max-width:200px;object-fit:contain;border:1px solid #dee2e6;border-radius:6px;padding:6px;background:#fff;">
            </div>
            <?php endif; ?>
            <div class="col-md-7">
              <label class="form-label">Ζώνη ώρας</label>
              <select name="timezone" class="form-select">
                <?php foreach ($tzOptions as $tzVal => $tzLabel): ?>
                  <option value="<?= e($tzVal) ?>" <?= $v('timezone', 'Europe/Athens') === $tzVal ? 'selected' : '' ?>><?= e($tzLabel) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Επηρεάζει την εμφάνιση ημερομηνιών/ωρών στην πλατφόρμα.</div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
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
        'blood_type'      => ['label' => 'Ομάδα Αίματος',          'hint' => 'π.χ. A+, O−'],
        'driving_license' => ['label' => 'Δίπλωμα Οδήγησης',       'hint' => 'Κατηγορία αδείας οδήγησης'],
        'certifications'  => ['label' => 'Πιστοποιήσεις',           'hint' => 'ΕΚΑΒ, πρώτες βοήθειες κλπ.'],
        'id_number'       => ['label' => 'Αριθμός Ταυτότητας',      'hint' => 'ΑΔΤ μέλους'],
        'amka'            => ['label' => 'ΑΜΚΑ',                    'hint' => 'Αριθμός Μητρώου Κοιν. Ασφάλισης'],
    ];
    ?>
    <div class="row g-4">
      <div class="col-lg-8">
        <form method="post" action="<?= e(url('/settings/member-fields')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i> Προαιρετικά πεδία μελών ομάδων</div>
          <div class="card-body">
            <p class="text-muted small mb-3">
              Τα πεδία <strong>Ονοματεπώνυμο</strong>, <strong>Τηλέφωνο</strong>, <strong>Email</strong>,
              <strong>ΑΜ Πολιτικής Προστασίας</strong> κλπ. είναι πάντα διαθέσιμα.
              Επιλέξτε ποια επιπλέον πεδία εμφανίζονται στη φόρμα μέλους.
            </p>
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th>Πεδίο</th>
                  <th class="text-center" style="width:110px">Ορατό</th>
                  <th class="text-center" style="width:130px">Υποχρεωτικό</th>
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
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
      </div>
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Οδηγίες</div>
          <div class="card-body small text-muted">
            <p><strong>Ορατό</strong>: εμφανίζεται στη φόρμα προσθήκης/επεξεργασίας μέλους.</p>
            <p><strong>Υποχρεωτικό</strong>: αν ενεργοποιηθεί, ο team_admin δεν μπορεί να αποθηκεύσει μέλος χωρίς να συμπληρώσει αυτό το πεδίο.</p>
            <p>Το «Υποχρεωτικό» ενεργοποιείται μόνο αν το πεδίο είναι ορατό.</p>
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
            <h2 class="h5 mb-0"><i class="bi bi-envelope-paper me-2"></i>Πρότυπα Email</h2>
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Αποθήκευση όλων</button>
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
                    <span class="badge text-bg-warning ms-1 small">Προσαρμοσμένο</span>
                  <?php else: ?>
                    <span class="badge text-bg-light text-muted ms-1 small">Προεπιλογή</span>
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
                    <label class="form-label fw-semibold small">Θέμα email (Subject)</label>
                    <input type="text" class="form-control"
                           name="tpl[<?= e($type) ?>][subject]"
                           id="subj_<?= e($type) ?>"
                           value="<?= e($stored['subject']) ?>">
                  </div>

                  <!-- Body -->
                  <div class="mb-3">
                    <label class="form-label fw-semibold small">Σώμα email (Body)</label>
                    <textarea class="form-control font-monospace small"
                              name="tpl[<?= e($type) ?>][body]"
                              id="body_<?= e($type) ?>"
                              rows="8"><?= e($stored['body']) ?></textarea>
                    <div class="form-text">Κλικ σε placeholder για εισαγωγή στο σημείο του cursor.</div>
                  </div>

                  <!-- Placeholder badges -->
                  <div class="mb-3">
                    <span class="small fw-semibold text-muted me-2">Μεταβλητές:</span>
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
                      <i class="bi bi-arrow-counterclockwise me-1"></i>Επαναφορά αρχικών
                    </button>
                    <span class="small text-muted align-self-center">
                      <i class="bi bi-people me-1"></i>Παραλήπτης: <?= e($tplDef['recipient']) ?>
                    </span>
                  </div>

                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div><!-- /accordion -->

          <div class="mt-3 text-end">
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Αποθήκευση όλων</button>
          </div>
        </form>
      </div>

      <div class="col-xl-4">
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Πώς λειτουργεί</div>
          <div class="card-body small text-muted">
            <p>Κάθε τύπος email έχει ένα <strong>Θέμα</strong> (Subject) και ένα <strong>Σώμα</strong> (Body).</p>
            <p>Χρησιμοποιήστε τα <strong>{placeholders}</strong> για να εισάγετε δυναμικές τιμές που αντικαθίστανται αυτόματα κατά την αποστολή (π.χ. <code>{event_title}</code>).</p>
            <p>Κάντε κλικ σε ένα badge placeholder για εισαγωγή στο cursor του textarea.</p>
            <p>Τα emails στέλνονται ως <strong>HTML</strong> με αυτόματο wrapper που περιλαμβάνει το λογότυπο του δήμου (από την καρτέλα Εμφάνιση).</p>
            <hr>
            <p class="mb-0">Το κουμπί <strong>Επαναφορά αρχικών</strong> επαναφέρει το default κείμενο — η αλλαγή γίνεται μόνιμη μετά την Αποθήκευση.</p>
          </div>
        </div>
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-palette me-1"></i> Branding</div>
          <div class="card-body small text-muted">
            <p class="mb-1">Το λογότυπο και το όνομα του δήμου εμφανίζονται αυτόματα στην κεφαλίδα κάθε email.</p>
            <a href="#tab-branding" class="tpl-switch-tab">Ρύθμιση λογότυπου →</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ Οργανισμός ══════════════════════════════════════════════════════ -->
  <div class="tab-pane fade" id="tab-organisation">
    <div class="row g-4">
      <div class="col-lg-7">
        <form method="post" action="<?= e(url('/settings/organisation')) ?>" class="card shadow-sm">
          <?= csrf_field() ?>
          <input type="hidden" name="org_type" id="orgType" value="<?= e($v('org_type', 'municipality')) ?>">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-1"></i> Προφίλ Οργανισμού</div>
          <div class="card-body row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold mb-2">Τύπος οργανισμού</label>
              <div class="d-flex flex-wrap gap-2" id="orgPresets">
                <button type="button" class="org-preset-btn" data-type="municipality"
                        data-name="Δήμος <?= e($municipality['name']) ?>" data-short="Δήμος">
                  <span class="org-preset-icon">🏛️</span><span class="org-preset-label">Δήμος</span>
                </button>
                <button type="button" class="org-preset-btn" data-type="civil_protection"
                        data-name="Πολιτική Προστασία <?= e($municipality['name']) ?>" data-short="Πολ.Προστ.">
                  <span class="org-preset-icon">🛡️</span><span class="org-preset-label">Πολ. Προστασία</span>
                </button>
                <button type="button" class="org-preset-btn" data-type="fire_service"
                        data-name="Πυροσβεστική <?= e($municipality['name']) ?>" data-short="Πυρ/κή">
                  <span class="org-preset-icon">🚒</span><span class="org-preset-label">Πυροσβεστική</span>
                </button>
                <button type="button" class="org-preset-btn" data-type="coast_guard"
                        data-name="Λιμενικό <?= e($municipality['name']) ?>" data-short="Λιμενικό">
                  <span class="org-preset-icon">⚓</span><span class="org-preset-label">Λιμενικό</span>
                </button>
                <button type="button" class="org-preset-btn" data-type="custom" data-name="" data-short="">
                  <span class="org-preset-icon">🏢</span><span class="org-preset-label">Άλλο</span>
                </button>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Πλήρες όνομα</label>
              <input type="text" name="org_name" id="orgName" class="form-control"
                     value="<?= e($v('org_name', 'Δήμος ' . $municipality['name'])) ?>"
                     placeholder="π.χ. Πυροσβεστική Χανίων" maxlength="120">
              <div class="form-text">Εμφανίζεται στη σελίδα σύνδεσης και στις επίσημες αναφορές.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Σύντομο όνομα <span class="text-muted fw-normal">(για μηνύματα)</span></label>
              <input type="text" name="org_name_short" id="orgNameShort" class="form-control"
                     value="<?= e($v('org_name_short', 'Δήμος')) ?>"
                     placeholder="π.χ. Πυρ/κή" maxlength="40">
              <div class="form-text">Εμφανίζεται ως αποστολέας στα μηνύματα επιχείρησης.</div>
            </div>
            <div class="col-12">
              <div class="org-preview-box">
                <div class="small text-muted mb-2"><i class="bi bi-eye me-1"></i>Προεπισκόπηση εμφάνισης</div>
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span id="orgPreviewIcon" style="font-size:1.2rem">🏛️</span>
                  <strong id="orgPreviewName"><?= e($v('org_name', 'Δήμος ' . $municipality['name'])) ?></strong>
                </div>
                <div class="small text-muted">
                  Μήνυμα επιχείρησης: <strong id="orgPreviewShort"><?= e($v('org_name_short', 'Δήμος')) ?></strong>
                  <span class="text-muted"> → ΕΟΔ Χανίων · 14:30</span>
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm border-info">
          <div class="card-header bg-white fw-semibold text-info"><i class="bi bi-info-circle me-1"></i>Πού αλλάζει αυτό;</div>
          <div class="card-body small">
            <ul class="mb-0 ps-3">
              <li class="mb-1">Αποστολέας μηνυμάτων στο <strong>Επιχειρησιακό Κέντρο</strong> (π.χ. «Πυρ/κή → ΕΟΔ»)</li>
              <li class="mb-1">Αποστολέας στο <strong>Field Hub</strong> (η σελίδα πεδίου ομάδων)</li>
              <li class="mb-1">Κεφαλίδα επικοινωνίας στη <strong>Mobile Εφαρμογή ομάδας</strong></li>
              <li>Εμφανιζόμενο όνομα στις <strong>αναφορές PDF</strong> (σε επόμενη έκδοση)</li>
            </ul>
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

/* ── Οργανισμός tab: preset buttons + live preview ───────────────────── */
(function () {
  var ICONS = { municipality:'🏛️', civil_protection:'🛡️', fire_service:'🚒', coast_guard:'⚓', custom:'🏢' };
  var typeInput   = document.getElementById('orgType');
  var nameInput   = document.getElementById('orgName');
  var shortInput  = document.getElementById('orgNameShort');
  var prevName    = document.getElementById('orgPreviewName');
  var prevShort   = document.getElementById('orgPreviewShort');
  var prevIcon    = document.getElementById('orgPreviewIcon');
  var presets     = document.querySelectorAll('.org-preset-btn');

  if (!typeInput) return;

  function markActive() {
    var cur = typeInput.value;
    presets.forEach(function (b) { b.classList.toggle('active', b.dataset.type === cur); });
    if (prevIcon) prevIcon.textContent = ICONS[cur] || '🏢';
  }

  function updatePreview() {
    if (prevName)  prevName.textContent  = (nameInput  && nameInput.value.trim())  || '—';
    if (prevShort) prevShort.textContent = (shortInput && shortInput.value.trim()) || '—';
  }

  presets.forEach(function (btn) {
    btn.addEventListener('click', function () {
      typeInput.value = btn.dataset.type;
      if (btn.dataset.name  && nameInput)  nameInput.value  = btn.dataset.name;
      if (btn.dataset.short && shortInput) shortInput.value = btn.dataset.short;
      markActive();
      updatePreview();
      typeInput.form.submit();
    });
  });

  if (nameInput)  nameInput.addEventListener('input',  updatePreview);
  if (shortInput) shortInput.addEventListener('input', updatePreview);

  markActive();
  updatePreview();
})();

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
