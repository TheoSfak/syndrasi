<?php
/**
 * SynDrasi - Per-municipality settings (Ρυθμίσεις Δήμου).
 */
class SettingsController
{
    public function index()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();
        $municipality = Municipality::find($mid);
        $settings  = MunicipalitySetting::all($mid);
        $effective = MailService::resolveConfig($mid);

        // Email template definitions + stored values for the UI
        $emailTemplates      = EmailTemplate::definitions();
        $emailTemplateValues = [];
        foreach ($emailTemplates as $type => $def) {
            $emailTemplateValues[$type] = EmailTemplate::getStored($mid, $type);
        }

        render('settings/municipality', [
            'pageTitle'            => 'Ρυθμίσεις Δήμου',
            'municipality'         => $municipality,
            'settings'             => $settings,
            'effective'            => $effective,
            'emailTemplates'       => $emailTemplates,
            'emailTemplateValues'  => $emailTemplateValues,
        ]);
    }

    /* ── Email / SMTP ─────────────────────────────────────────────────── */

    public function saveMail()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $driver = post_str('mail_driver');
        if (!in_array($driver, ['', 'log', 'mail', 'smtp'], true)) {
            flash_set('danger', 'Μη έγκυρος τρόπος αποστολής email.');
            redirect('/settings');
        }

        $fromEmail = post_str('mail_from_email');
        if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('danger', 'Μη έγκυρη διεύθυνση αποστολέα (From).');
            redirect('/settings');
        }

        $port = post_int('smtp_port');
        if ($driver === 'smtp') {
            if (post_str('smtp_host') === '') {
                flash_set('danger', 'Για SMTP απαιτείται SMTP Host.');
                redirect('/settings');
            }
            if ($port < 1 || $port > 65535) {
                flash_set('danger', 'Μη έγκυρη θύρα SMTP (συνήθως 587 ή 465).');
                redirect('/settings');
            }
        }

        $secure = post_str('smtp_secure');
        if (!in_array($secure, ['', 'tls', 'ssl'], true)) {
            $secure = 'tls';
        }

        $settings = [
            'mail_driver'     => $driver,
            'mail_from_email' => $fromEmail,
            'mail_from_name'  => post_str('mail_from_name'),
            'smtp_host'       => post_str('smtp_host'),
            'smtp_port'       => $port > 0 ? (string) $port : '',
            'smtp_user'       => post_str('smtp_user'),
            'smtp_secure'     => $secure,
        ];

        // Keep the stored password when the field is left empty
        $pass = isset($_POST['smtp_pass']) ? (string) $_POST['smtp_pass'] : '';
        if ($pass !== '') {
            $settings['smtp_pass'] = $pass;
        }

        MunicipalitySetting::setMany($mid, $settings);
        audit('municipality_mail_settings_updated', 'municipality', $mid, 'driver: ' . ($driver !== '' ? $driver : 'default'));

        flash_set('success', 'Οι ρυθμίσεις email αποθηκεύτηκαν.');
        redirect('/settings');
    }

    /** Send a test email to the logged-in admin using the saved settings. */
    public function testMail()
    {
        // Prevent PHP execution timeout from killing the script before the redirect.
        // The SMTP attempt can legitimately take ~5s; ensure we always finish cleanly.
        set_time_limit(60);
        ignore_user_abort(true);

        requireRole(['municipality_admin']);
        $mid = current_municipality_id();
        $user = current_user();
        $municipality = Municipality::find($mid);
        $effective = MailService::resolveConfig($mid);

        $ok = MailService::send(
            $user['email'],
            $user['name'],
            'Δοκιμαστικό email SynDrasi',
            "Αυτό είναι ένα δοκιμαστικό μήνυμα από την πλατφόρμα SynDrasi.\n\n"
            . 'Δήμος: ' . $municipality['name'] . "\n"
            . 'Τρόπος αποστολής: ' . $effective['driver'] . "\n"
            . 'Ημερομηνία: ' . gr_datetime(date('Y-m-d H:i:s')) . "\n\n"
            . 'Αν λάβατε αυτό το μήνυμα, οι ρυθμίσεις email λειτουργούν σωστά.',
            $mid
        );
        audit('municipality_mail_test', 'municipality', $mid, $ok ? 'success' : 'failed: ' . MailService::$lastError);

        if ($ok) {
            if ($effective['driver'] === 'log') {
                flash_set('success', 'Το δοκιμαστικό email καταγράφηκε στο storage/logs/mail.log (τρόπος αποστολής: log).');
            } else {
                flash_set('success', 'Το δοκιμαστικό email στάλθηκε στο ' . $user['email'] . '. Ελέγξτε τα εισερχόμενά σας.');
            }
        } else {
            flash_set('danger', 'Αποτυχία αποστολής: ' . (MailService::$lastError ?: 'Άγνωστο σφάλμα.'));
        }
        redirect('/settings');
    }

    /* ── Map defaults ─────────────────────────────────────────────────── */

    public function saveMap()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $lat  = trim(post_str('map_lat'));
        $lng  = trim(post_str('map_lng'));
        $zoom = post_int('map_zoom');

        if ($lat !== '' && (!is_numeric($lat) || (float) $lat < -90 || (float) $lat > 90)) {
            flash_set('danger', 'Μη έγκυρο γεωγρ. πλάτος (−90 έως 90).');
            redirect('/settings');
        }
        if ($lng !== '' && (!is_numeric($lng) || (float) $lng < -180 || (float) $lng > 180)) {
            flash_set('danger', 'Μη έγκυρο γεωγρ. μήκος (−180 έως 180).');
            redirect('/settings');
        }
        if ($zoom < 1 || $zoom > 19) { $zoom = 13; }

        MunicipalitySetting::setMany($mid, [
            'map_lat'  => $lat,
            'map_lng'  => $lng,
            'map_zoom' => (string) $zoom,
        ]);
        audit('municipality_map_settings_updated', 'municipality', $mid, 'lat:' . $lat . ' lng:' . $lng . ' zoom:' . $zoom);

        flash_set('success', 'Οι ρυθμίσεις χάρτη αποθηκεύτηκαν.');
        redirect('/settings');
    }

    /* ── Award thresholds ─────────────────────────────────────────────── */

    public function saveAwards()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $bronze  = post_int('award_bronze_events');
        $silver  = post_int('award_silver_events');
        $gold    = post_int('award_gold_events');
        $minEvts = post_int('award_min_events');

        if ($bronze  < 1) { $bronze  = 5;  }
        if ($silver  < 1) { $silver  = 10; }
        if ($gold    < 1) { $gold    = 20; }
        if ($minEvts < 1) { $minEvts = 3;  }

        MunicipalitySetting::setMany($mid, [
            'award_bronze_events' => (string) $bronze,
            'award_silver_events' => (string) $silver,
            'award_gold_events'   => (string) $gold,
            'award_min_events'    => (string) $minEvts,
        ]);
        audit('municipality_award_settings_updated', 'municipality', $mid, 'bronze:' . $bronze . ' silver:' . $silver . ' gold:' . $gold);

        flash_set('success', 'Οι ρυθμίσεις βραβείων αποθηκεύτηκαν.');
        redirect('/settings');
    }

    /* ── Notification toggles ─────────────────────────────────────────── */

    public function saveNotifications()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $keys = [
            'notify_email_event_published',
            'notify_email_application_submitted',
            'notify_email_application_approved',
            'notify_email_application_rejected',
            'notify_email_shortage_reported',
            'notify_email_event_reminder',
            'notify_email_event_completed',
        ];

        $settings = [];
        foreach ($keys as $k) {
            $settings[$k] = isset($_POST[$k]) ? '1' : '0';
        }

        MunicipalitySetting::setMany($mid, $settings);
        audit('municipality_notification_settings_updated', 'municipality', $mid);

        flash_set('success', 'Οι ρυθμίσεις ειδοποιήσεων αποθηκεύτηκαν.');
        redirect('/settings');
    }

    /* ── Event defaults ───────────────────────────────────────────────── */

    public function saveEventDefaults()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $deadlineDays = post_int('event_application_deadline_days');
        if ($deadlineDays < 0) { $deadlineDays = 0; }

        MunicipalitySetting::setMany($mid, [
            'event_application_deadline_days' => (string) $deadlineDays,
            'event_default_instructions'      => post_str('event_default_instructions'),
        ]);
        audit('municipality_event_defaults_updated', 'municipality', $mid);

        flash_set('success', 'Οι προεπιλογές δράσεων αποθηκεύτηκαν.');
        redirect('/settings');
    }

    /* ── Member fields config ────────────────────────────────────────── */

    public function saveMemberFields()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $optionalFields = ['blood_type', 'driving_license', 'certifications', 'id_number', 'amka'];
        $visibleFields  = isset($_POST['mf_visible'])  && is_array($_POST['mf_visible'])  ? $_POST['mf_visible']  : [];
        $requiredFields = isset($_POST['mf_required']) && is_array($_POST['mf_required']) ? $_POST['mf_required'] : [];

        $config = [];
        foreach ($optionalFields as $f) {
            $visible  = in_array($f, $visibleFields, true);
            $required = $visible && in_array($f, $requiredFields, true);
            $config[$f] = ['visible' => $visible, 'required' => $required];
        }

        MunicipalitySetting::setMany($mid, ['member_fields_config' => json_encode($config)]);
        audit('municipality_member_fields_updated', 'municipality', $mid);

        flash_set('success', 'Η διαμόρφωση πεδίων μελών αποθηκεύτηκε.');
        redirect('/settings#tab-members');
    }

    /* ── Email templates ─────────────────────────────────────────────────── */

    public function saveEmailTemplates()
    {
        requireRole(['municipality_admin']);
        $mid  = current_municipality_id();
        $defs = EmailTemplate::definitions();

        $tplPost = isset($_POST['tpl']) && is_array($_POST['tpl']) ? $_POST['tpl'] : [];
        $toSave  = [];

        foreach ($defs as $type => $def) {
            if (!isset($tplPost[$type])) {
                continue;
            }
            $subject = trim((string) ($tplPost[$type]['subject'] ?? ''));
            $body    = trim((string) ($tplPost[$type]['body']    ?? ''));

            // If both match the defaults exactly, clear the override so default is used
            if ($subject === $def['subject'] && $body === $def['body']) {
                $toSave['email_tpl_' . $type] = '';
            } else {
                $toSave['email_tpl_' . $type] = json_encode([
                    'subject' => $subject !== '' ? $subject : $def['subject'],
                    'body'    => $body    !== '' ? $body    : $def['body'],
                ]);
            }
        }

        MunicipalitySetting::setMany($mid, $toSave);
        audit('municipality_email_templates_updated', 'municipality', $mid);

        flash_set('success', 'Τα πρότυπα email αποθηκεύτηκαν.');
        redirect('/settings#tab-email-templates');
    }

    /* ── Branding & timezone ──────────────────────────────────────────── */

    public function saveBranding()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $logoUrl = post_str('branding_logo_url');
        if ($logoUrl !== '' && !filter_var($logoUrl, FILTER_VALIDATE_URL)) {
            flash_set('danger', 'Μη έγκυρο URL λογότυπου. Χρησιμοποιήστε πλήρες URL (https://...).');
            redirect('/settings');
        }

        $tz = post_str('timezone');
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            $tz = 'Europe/Athens';
        }

        MunicipalitySetting::setMany($mid, [
            'branding_logo_url' => $logoUrl,
            'timezone'          => $tz,
        ]);
        audit('municipality_branding_updated', 'municipality', $mid, 'tz:' . $tz);

        flash_set('success', 'Οι ρυθμίσεις εμφάνισης αποθηκεύτηκαν.');
        redirect('/settings');
    }
}
