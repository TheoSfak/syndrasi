<?php
/**
 * SynDrasi - Mail settings.
 *
 * driver:
 *   'log'  -> writes emails to storage/logs/mail.log (default, needs nothing)
 *   'mail' -> uses PHP mail()
 *   'smtp' -> uses PHPMailer (run: composer require phpmailer/phpmailer)
 */
return [
    'driver'       => env('MAIL_DRIVER', 'log'),
    'from_email'   => 'no-reply@syndrasi.gr',
    'from_name'    => 'SynDrasi',
    'smtp_host'    => env('MAIL_HOST', ''),
    'smtp_port'    => (int) env('MAIL_PORT', 587),
    'smtp_user'    => env('MAIL_USER', ''),
    'smtp_pass'    => env('MAIL_PASS', ''),
    'smtp_secure'  => 'tls',
];
