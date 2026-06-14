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
    'driver'       => getenv('MAIL_DRIVER') !== false ? getenv('MAIL_DRIVER') : 'log',
    'from_email'   => 'no-reply@syndrasi.gr',
    'from_name'    => 'SynDrasi',
    'smtp_host'    => getenv('MAIL_HOST') !== false ? getenv('MAIL_HOST') : '',
    'smtp_port'    => getenv('MAIL_PORT') !== false ? (int) getenv('MAIL_PORT') : 587,
    'smtp_user'    => getenv('MAIL_USER') !== false ? getenv('MAIL_USER') : '',
    'smtp_pass'    => getenv('MAIL_PASS') !== false ? getenv('MAIL_PASS') : '',
    'smtp_secure'  => 'tls',
];
