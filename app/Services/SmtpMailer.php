<?php
/**
 * SynDrasi - Minimal native SMTP client (no Composer required).
 * Supports: SSL (smtps, port 465), STARTTLS (port 587), AUTH LOGIN, UTF-8.
 * Used as fallback when PHPMailer is not installed.
 */
class SmtpMailer
{
    private $socket;
    private $log = [];

    /**
     * @param array $cfg  keys: smtp_host, smtp_port, smtp_user, smtp_pass,
     *                    smtp_secure ('ssl'|'tls'|''), from_email, from_name
     * @return true|string  true on success, error message string on failure
     */
    public static function send(array $cfg, $toEmail, $toName, $subject, $body, $plainBody = null)
    {
        $mailer = new self();
        try {
            $mailer->doSend($cfg, $toEmail, $toName, $subject, $body, $plainBody);
            return true;
        } catch (Exception $ex) {
            $mailer->close();
            return $ex->getMessage();
        }
    }

    private function doSend(array $cfg, $toEmail, $toName, $subject, $body, $plainBody = null)
    {
        $host = trim((string) $cfg['smtp_host']);
        $port = (int) $cfg['smtp_port'];
        $secure = isset($cfg['smtp_secure']) ? strtolower(trim((string) $cfg['smtp_secure'])) : '';
        if ($host === '' || $port < 1) {
            throw new Exception('Δεν έχουν οριστεί SMTP host/port.');
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $this->socket = @stream_socket_client($remote, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) {
            throw new Exception('Αποτυχία σύνδεσης στον SMTP server (' . $errstr . ').');
        }
        stream_set_timeout($this->socket, 5);

        $this->expect([220], 'καλωσόρισμα server');
        $this->command('EHLO ' . gethostname(), [250]);

        if ($secure === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('Αποτυχία STARTTLS με τον SMTP server.');
            }
            $this->command('EHLO ' . gethostname(), [250]);
        }

        if (!empty($cfg['smtp_user'])) {
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($cfg['smtp_user']), [334]);
            $this->command(base64_encode($cfg['smtp_pass']), [235], true);
        }

        $fromEmail = $cfg['from_email'];
        $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
        $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
        $this->command('DATA', [354]);

        $headers = [];
        $headers[] = 'From: ' . self::encodeHeader($cfg['from_name']) . ' <' . $fromEmail . '>';
        $headers[] = 'To: ' . ($toName !== '' ? self::encodeHeader($toName) . ' ' : '') . '<' . $toEmail . '>';
        $headers[] = 'Subject: ' . self::encodeHeader($subject);
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'MIME-Version: 1.0';

        if ($plainBody !== null) {
            // Multipart/alternative: plain text + HTML
            $boundary = md5(uniqid('smtp', true));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $mime  = implode("\r\n", $headers) . "\r\n\r\n";
            $mime .= "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($plainBody)) . "\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($body)) . "\r\n"
                . "--{$boundary}--";
            $data = $mime;
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
        }
        // dot-stuffing
        $data = preg_replace('/^\./m', '..', $data);

        fwrite($this->socket, $data . "\r\n.\r\n");
        $this->expect([250], 'αποστολή μηνύματος');

        $this->command('QUIT', [221]);
        $this->close();
    }

    private function command($cmd, array $expectedCodes, $sensitive = false)
    {
        fwrite($this->socket, $cmd . "\r\n");
        $this->log[] = $sensitive ? '[credentials]' : $cmd;
        $this->expect($expectedCodes, $sensitive ? 'AUTH' : $cmd);
    }

    private function expect(array $codes, $context)
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        // Detect stream timeout (fgets returned false and the socket timed out)
        if ($response === '') {
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                throw new Exception('Timeout σύνδεσης SMTP (' . $context . '): δεν απάντησε ο server εντός 5 δευτερολέπτων.');
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            $clean = trim(preg_replace('/\s+/', ' ', $response));
            throw new Exception('SMTP σφάλμα (' . $context . '): ' . ($clean !== '' ? $clean : 'χωρίς απάντηση'));
        }
    }

    private static function encodeHeader($text)
    {
        if (preg_match('/[^\x20-\x7e]/', (string) $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return (string) $text;
    }

    private function close()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
