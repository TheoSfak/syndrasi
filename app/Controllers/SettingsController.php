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
        $telegramEffective = TelegramService::resolveConfig($mid);

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
            'telegramEffective'    => $telegramEffective,
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

        $types = [
            'event_published',
            'application_submitted',
            'application_approved',
            'application_rejected',
            'shortage_reported',
            'event_reminder',
            'event_completed',
            'photo_request',
            'video_request',
            'gps_request',
            'photo_uploaded',
            'video_uploaded',
            'gps_arrived',
            'ops_message',
            'ops_geo',
            'team_silent',
            'shortage_update',
            'sos_ack',
        ];
        $opsTypes = [
            'photo_request',
            'video_request',
            'gps_request',
            'photo_uploaded',
            'video_uploaded',
            'gps_arrived',
            'ops_message',
            'ops_geo',
            'team_silent',
            'shortage_update',
            'sos_ack',
        ];

        $allowed  = ['off', 'email', 'sms', 'both'];
        $settings = [];
        foreach ($types as $t) {
            $default = in_array($t, $opsTypes, true) ? 'off' : 'email';
            $ch = isset($_POST['notify_channel_' . $t]) ? (string) $_POST['notify_channel_' . $t] : $default;
            if (!in_array($ch, $allowed, true)) { $ch = $default; }
            $settings['notify_channel_' . $t] = $ch;
            $settings['notify_telegram_' . $t] = !empty($_POST['notify_telegram_' . $t]) ? '1' : '0';
            // Keep the legacy email flag in sync for backward compatibility
            $settings['notify_email_' . $t] = in_array($ch, ['email', 'both'], true) ? '1' : '0';
        }

        // Operational alert: configurable silence threshold (0 = disabled, max 120 min)
        $settings['ops_silent_team_minutes'] = (string) max(0, min(120, (int) ($_POST['ops_silent_team_minutes'] ?? 20)));

        MunicipalitySetting::setMany($mid, $settings);
        audit('municipality_notification_settings_updated', 'municipality', $mid);

        flash_set('success', 'Οι ρυθμίσεις ειδοποιήσεων αποθηκεύτηκαν.');
        redirect('/settings#tab-notifications');
    }

    /* ── SMS gateway ──────────────────────────────────────────────────── */

    public function saveSms()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $driver = post_str('sms_driver');
        if (!in_array($driver, ['', 'log', 'http', 'smsbox', 'none'], true)) {
            flash_set('danger', 'Μη έγκυρος τρόπος αποστολής SMS.');
            redirect('/settings#tab-sms');
        }

        $endpoint = post_str('sms_endpoint');
        if ($endpoint !== '' && !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            flash_set('danger', 'Μη έγκυρο URL gateway (SMS Endpoint).');
            redirect('/settings#tab-sms');
        }

        if ($driver === 'http' && $endpoint === '') {
            flash_set('danger', 'Για τον τρόπο HTTP απαιτείται το URL του gateway (Endpoint).');
            redirect('/settings#tab-sms');
        }

        $settings = [
            'sms_driver'   => $driver,
            'sms_sender'   => post_str('sms_sender'),
            'sms_endpoint' => $endpoint,
            'sms_username' => post_str('sms_username'),
        ];

        // Keep the stored API key / password when the field is left empty
        $key = isset($_POST['sms_api_key']) ? trim((string) $_POST['sms_api_key']) : '';
        if ($key !== '') {
            $settings['sms_api_key'] = $key;
        }

        MunicipalitySetting::setMany($mid, $settings);
        audit('municipality_sms_settings_updated', 'municipality', $mid, 'driver: ' . ($driver !== '' ? $driver : 'default'));

        flash_set('success', 'Οι ρυθμίσεις SMS αποθηκεύτηκαν.');
        redirect('/settings#tab-sms');
    }

    /** POST /settings/sms/test — send a test SMS to a number, using saved settings. */
    public function testSms()
    {
        set_time_limit(60);
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $to = preg_replace('/[^\d+]/', '', (string) post_str('test_to'));
        if ($to === '') {
            flash_set('danger', 'Δώστε αριθμό κινητού για τη δοκιμή.');
            redirect('/settings#tab-sms');
        }
        $ok = SmsService::send($to, 'SynDrasi: δοκιμαστικό SMS — η σύνδεση λειτουργεί.', $mid);
        audit('municipality_sms_test', 'municipality', $mid, $ok ? 'success' : 'failed: ' . SmsService::lastError());

        if ($ok) {
            $drv = SmsService::resolveConfig($mid)['driver'] ?? '';
            flash_set('success', $drv === 'log'
                ? 'Το δοκιμαστικό SMS καταγράφηκε στο storage/logs/sms.log (driver: log).'
                : 'Το δοκιμαστικό SMS στάλθηκε στο ' . $to . '.');
        } else {
            flash_set('danger', 'Αποτυχία αποστολής SMS: ' . (SmsService::lastError() ?: 'άγνωστο σφάλμα'));
        }
        redirect('/settings#tab-sms');
    }

    /* ── Telegram Bot ─────────────────────────────────────────────────── */

    public function saveTelegram()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();

        $enabled = post_bool('telegram_enabled') ? '1' : '0';
        $commandChatId = post_str('telegram_command_chat_id');
        $teamChatId = post_str('telegram_team_chat_id');

        $settings = [
            'telegram_enabled' => $enabled,
            'telegram_command_chat_id' => $commandChatId,
            'telegram_team_chat_id' => $teamChatId,
        ];

        $token = isset($_POST['telegram_bot_token']) ? trim((string) $_POST['telegram_bot_token']) : '';
        if ($token !== '') {
            $settings['telegram_bot_token'] = $token;
        }

        MunicipalitySetting::setMany($mid, $settings);
        audit('municipality_telegram_settings_updated', 'municipality', $mid, 'enabled: ' . $enabled);

        flash_set('success', 'Οι ρυθμίσεις Telegram αποθηκεύτηκαν.');
        redirect('/settings#tab-telegram');
    }

    public function testTelegram()
    {
        set_time_limit(60);
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();
        $municipality = Municipality::find($mid);
        $target = isset($_POST['test_target']) ? (string) $_POST['test_target'] : 'command';
        $cfg = TelegramService::resolveConfig($mid);

        if ($target === 'teams') {
            $ok = TelegramService::sendToChat(
                $cfg,
                (string) ($cfg['team_chat_id'] ?? ''),
                'Δοκιμαστικό Telegram SynDrasi',
                'Το κοινό Telegram group ομάδων λειτουργεί για ' . ($municipality['name'] ?? 'τον δήμο') . '.'
            );
        } else {
            $ok = TelegramService::sendCommand(
                $mid,
                'Δοκιμαστικό Telegram SynDrasi',
                'Η σύνδεση Telegram command group λειτουργεί για ' . ($municipality['name'] ?? 'τον δήμο') . '.'
            );
        }
        audit('municipality_telegram_test', 'municipality', $mid, $ok ? 'success' : 'failed: ' . TelegramService::lastError());

        if ($ok) {
            flash_set('success', $target === 'teams'
                ? 'Το δοκιμαστικό Telegram στάλθηκε στο κοινό group ομάδων.'
                : 'Το δοκιμαστικό Telegram στάλθηκε στο command chat.');
        } else {
            flash_set('danger', 'Αποτυχία αποστολής Telegram: ' . (TelegramService::lastError() ?: 'άγνωστο σφάλμα'));
        }
        redirect('/settings#tab-telegram');
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

    /* ── Organisation profile ─────────────────────────────────────────── */

    public function saveOrganisation()
    {
        requireRole(['municipality_admin']);
        $mid          = current_municipality_id();
        $municipality = Municipality::find($mid);

        $validTypes = ['municipality', 'civil_protection', 'fire_service', 'coast_guard', 'custom'];
        $type = post_str('org_type');
        if (!in_array($type, $validTypes, true)) {
            $type = 'municipality';
        }

        $name      = post_str('org_name');
        $nameShort = post_str('org_name_short');
        $cityName  = $municipality['name'] ?? '';

        if ($name === '') {
            $prefixes = ['municipality'=>'Δήμος','civil_protection'=>'Πολιτική Προστασία',
                         'fire_service'=>'Πυροσβεστική','coast_guard'=>'Λιμενικό','custom'=>''];
            $prefix = $prefixes[$type] ?? 'Δήμος';
            $name   = $cityName !== '' ? $prefix . ' ' . $cityName : $prefix;
        }
        if ($nameShort === '') {
            $shorts = ['municipality'=>'Δήμος','civil_protection'=>'Πολ.Προστ.',
                       'fire_service'=>'Πυρ/κή','coast_guard'=>'Λιμενικό','custom'=>$name];
            $nameShort = $shorts[$type] ?? 'Δήμος';
        }

        MunicipalitySetting::setMany($mid, [
            'org_type'       => $type,
            'org_name'       => $name,
            'org_name_short' => $nameShort,
        ]);
        audit('municipality_org_updated', 'municipality', $mid, 'type:' . $type);

        flash_set('success', 'Το προφίλ οργανισμού αποθηκεύτηκε.');
        redirect('/settings#tab-organisation');
    }
}
