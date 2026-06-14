<?php
/**
 * SynDrasi - Mail service.
 *
 * Global defaults come from config/mail.php. Each municipality can override
 * them from its settings page (Ρυθμίσεις -> Email/SMTP). Drivers:
 *   'log'  -> writes emails to storage/logs/mail.log
 *   'mail' -> PHP mail()
 *   'smtp' -> PHPMailer if installed, otherwise the built-in SmtpMailer
 *
 * All outgoing emails are wrapped in an HTML layout (municipality branding).
 * The plain-text body is also sent as the text/plain alternative part.
 */
class MailService
{
    /** Last error message of the most recent send() call (for the UI). */
    public static $lastError = '';

    /**
     * Send an email.
     *
     * @param int|null $municipalityId  use this municipality's SMTP settings if configured
     * @return bool true on success
     */
    public static function send($toEmail, $toName, $subject, $body, $municipalityId = null)
    {
        self::$lastError = '';
        if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Μη έγκυρη διεύθυνση email παραλήπτη.';
            return false;
        }

        $cfg      = self::resolveConfig($municipalityId);
        $htmlBody = self::wrapHtml($subject, $body, $municipalityId);

        try {
            switch ($cfg['driver']) {
                case 'smtp':
                    return self::sendSmtp($cfg, $toEmail, $toName, $subject, $htmlBody, $body);
                case 'mail':
                    return self::sendPhpMail($cfg, $toEmail, $subject, $htmlBody, $body);
                case 'log':
                default:
                    return self::sendLog($toEmail, $subject, $body);
            }
        } catch (Exception $ex) {
            self::$lastError = $ex->getMessage();
            error_log('Mail failed: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Wrap plain-text body in a clean HTML email layout.
     * Pulls municipality logo + name from settings for branding.
     */
    public static function wrapHtml(string $subject, string $body, $municipalityId = null): string
    {
        $logoUrl   = '';
        $orgName   = 'SynDrasi';
        $accentColor = '#1a6bbf';

        if ($municipalityId) {
            try {
                $s = MunicipalitySetting::all($municipalityId);
                if (!empty($s['branding_logo_url'])) {
                    $logoUrl = htmlspecialchars($s['branding_logo_url'], ENT_QUOTES, 'UTF-8');
                }
                // Try to get municipality name
                $mun = Municipality::find($municipalityId);
                if ($mun && !empty($mun['name'])) 