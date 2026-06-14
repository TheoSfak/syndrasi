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
  <li class="nav-item"><a class="nav-link" href="#tab-mail"           data-bs-toggle="tab"><i class="bi bi-envelope me-1"></i>Email</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-map"            data-bs-toggle="tab"><i class="bi bi-map me-1"></i>Χάρτης</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-awards"         data-bs-toggle="tab"><i class="bi bi-trophy me-1"></i>Βραβεία</a></li>
  <li class="nav-item"><a class="nav-link" href="#tab-notifications"  data-bs-toggle="tab"><i class="bi bi-bell me-1"></i>Ειδοποιήσεις</a></li>
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
          <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-1"></i> Αποστολή email ειδοποιήσεων</div>
          <div class="card-body">
            <p class="text-muted small">Οι in-app ειδοποιήσεις (κουδούνι) στέλνονται πάντα. Εδώ ελέγχετε αν αποστέλλεται και email.</p>
            <?php
            $toggles = [
                ['notify_email_event_published',        'Νέα δράση δημοσιεύτηκε',       'Ειδοποίηση email σε όλες τις ενεργές ομάδες'],
                ['notify_email_application_submitted',  'Νέα δήλωση συμμετοχής',        'Ειδοποίηση email στους διαχειριστές δήμου'],
                ['notify_email_application_approved',   'Έγκριση συμμετοχής',           'Ειδοποίηση email στην ομάδα'],
                ['notify_email_application_rejected',   'Απόρριψη συμμετοχής',          'Ειδοποίηση email στην ομάδα'],
                ['notify_email_shortage_reported',      'Αναφορά έλλειψης',             'Ειδοποίηση email στους διαχειριστές δήμου'],
                ['notify_email_event_reminder',         'Υπενθύμιση δράσης',            'Ειδοποίηση email (χειροκίνητη, κουμπί «Υπενθύμιση»)'],
                ['notify_email_event_completed',        'Ολοκλήρωση δράσης',            'Ειδοποίηση email στις εγκεκριμένες ομάδες'],
            ];
            ?>
            <div class="list-group list-group-flush">
              <?php foreach ($toggles as [$key, $label, $desc]): ?>
              <label class="list-group-item d-flex justify-content-between align-items-start py-3 cursor-pointer" for="<?= e($key) ?>">
                <div>
                  <div class="fw-semibold"><?= e($label) ?></div>
                  <div class="small text-muted"><?= e($desc) ?></div>
                </div>
                <div class="form-check form-switch ms-3 mt-1 flex-shrink-0">
                  <input class="form-check-input" type="checkbox" name="<?= e($key) ?>" id="<?= e($key) ?>"
                         value="1" <?= $notifyOn($key) ? 'checked' : '' ?>>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="card-footer bg-white">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
          </div>
        </form>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Σημείωση</div>
          <div class="card-body small text-muted">
            <p>Η απενεργοποίηση ενός τύπου δεν επηρεάζει τις in-app ειδοποιήσεις — αυτές εμφανίζονται πάντα στο κουδούνι.</p>
            <p>Χρήσιμο όταν ο SMTP δεν έχει ρυθμιστεί ακόμα ή σε περίοδο δοκιμών για να αποφύγετε spam.</p>
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
  <div class="tab-p