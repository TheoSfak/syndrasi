<?php
/**
 * SynDrasi — WebPushService.
 * Sends Web Push notifications (RFC 8291) using VAPID (RFC 8292).
 * Pure PHP 8.1+, no Composer. Uses PHP's OpenSSL extension.
 *
 * Usage:
 *   WebPushService::setup();                        // generate & store VAPID keys once
 *   $pubKey = WebPushService::getPublicKeyBase64(); // give to JS
 *   WebPushService::send($subscription, $payload);  // send a push
 */
class WebPushService
{
    // ── VAPID key management ──────────────────────────────────────────────────

    /**
     * Resolve an openssl.cnf path that works on this server.
     * Needed on Windows/XAMPP where the default OPENSSL_CONF may be missing.
     */
    private static function opensslConfig(): array
    {
        $candidates = [
            getenv('OPENSSL_CONF') ?: '',
            'C:/xampp/apache/conf/openssl.cnf',
            'C:/xampp/php/extras/openssl/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
        ];
        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return ['config' => $path];
            }
        }
        return [];
    }

    /**
     * Generate VAPID P-256 key pair and store in app_settings.
     * Idempotent — skips if keys already exist.
     */
    public static function setup(): void
    {
        $existing = dbq("SELECT setting_value FROM app_settings WHERE setting_key = 'vapid_public_key'")->fetchColumn();
        if ($existing) { return; }

        $opts = array_merge(
            ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC],
            self::opensslConfig()
        );
        $key = openssl_pkey_new($opts);
        if (!$key) { throw new RuntimeException('OpenSSL EC key generation failed: ' . openssl_error_string()); }

        $details    = openssl_pkey_get_details($key);
        $privateKey = '';
        openssl_pkey_export($key, $privateKey);

        // Uncompressed P-256 public key: 0x04 + 32-byte X + 32-byte Y (65 bytes)
        $xBin = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $yBin = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $pubKeyBin = "\x04" . $xBin . $yBin;

        // Store both — private as PEM, public as base64url
        dbq("INSERT INTO app_settings (setting_key, setting_value) VALUES ('vapid_private_pem', :v1) ON DUPLICATE KEY UPDATE setting_value=:v2",
            ['v1' => $privateKey, 'v2' => $privateKey]);
        $pubKeyB64 = self::b64url_encode($pubKeyBin);
        dbq("INSERT INTO app_settings (setting_key, setting_value) VALUES ('vapid_public_key', :v1) ON DUPLICATE KEY UPDATE setting_value=:v2",
            ['v1' => $pubKeyB64, 'v2' => $pubKeyB64]);
    }

    /** Return the VAPID public key as base64url (for JS PushManager.subscribe). */
    public static function getPublicKeyBase64(): string
    {
        return dbq("SELECT setting_value FROM app_settings WHERE setting_key = 'vapid_public_key'")->fetchColumn() ?: '';
    }

    // ── Push sending ──────────────────────────────────────────────────────────

    /**
     * Send a push notification to a single subscription.
     *
     * @param array  $subscription  ['endpoint'=>string,'p256dh'=>string,'auth_key'=>string]
     * @param array  $payload       ['title'=>string, 'body'=>string, 'url'=>string]
     * @param int    $ttl           Time-To-Live in seconds (default 24h)
     * @return bool
     */
    public static function send(array $subscription, array $payload, int $ttl = 86400): bool
    {
        $endpoint = $subscription['endpoint'];
        $p256dh   = $subscription['p256dh'];
        $authKey  = $subscription['auth_key'];

        if (!$endpoint || !$p256dh || !$authKey) { return false; }

        try {
            $bodyJson    = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $encrypted   = self::encryptPayload($bodyJson, $p256dh, $authKey);
            $vapidHeader = self::buildVapidHeader($endpoint, $ttl);

            return self::httpPost($endpoint, $encrypted['ciphertext'], $encrypted['salt'],
                $encrypted['server_public_key'], $vapidHeader, $ttl);
        } catch (Throwable $e) {
            error_log('[WebPush] Send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send to all subscriptions of a user. Returns count of successful sends.
     */
    public static function sendToUser(int $userId, array $payload): int
    {
        $subs = dbq(
            'SELECT * FROM push_subscriptions WHERE user_id = :uid',
            ['uid' => $userId]
        )->fetchAll();

        $ok = 0;
        foreach ($subs as $sub) {
            if (self::send($sub, $payload)) { $ok++; }
        }
        return $ok;
    }

    // ── Payload encryption (RFC 8291 / RFC 8188) ──────────────────────────────

    private static function encryptPayload(string $plaintext, string $p256dhB64, string $authB64): array
    {
        // Decode subscriber keys
        $receiverPubKey = self::b64url_decode($p256dhB64);   // 65 bytes uncompressed
        $authSecret     = self::b64url_decode($authB64);      // 16 bytes

        // 1. Generate ephemeral sender key pair (P-256)
        $senderKey = openssl_pkey_new(array_merge(
            ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC],
            self::opensslConfig()
        ));
        $senderDetails = openssl_pkey_get_details($senderKey);
        $senderPubBin  = "\x04" . str_pad($senderDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                                 . str_pad($senderDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        // 2. Import receiver's public key as OpenSSL resource
        $receiverKeyRes = openssl_pkey_get_public(self::binToPem($receiverPubKey));
        if (!$receiverKeyRes) { throw new RuntimeException('Invalid p256dh key'); }

        // 3. ECDH shared secret
        $sharedSecret = openssl_pkey_derive($receiverKeyRes, $senderKey, 32);
        if ($sharedSecret === false) { throw new RuntimeException('ECDH failed: ' . openssl_error_string()); }

        // 4. HKDF to derive content encryption key + nonce (RFC 8291 §3.3)
        // PRK = HKDF-Extract(auth_secret, shared_secret)
        // info = "WebPush: info\x00" + receiverPubKey + senderPubBin
        $prk = hash_hmac('sha256', $sharedSecret, $authSecret, true);

        $ikm = self::hkdfExpand($prk,
            "Content-Encoding: auth\x00",
            32
        );

        // Generate a random 16-byte salt
        $salt = random_bytes(16);

        // Derive CEK (16 bytes) and nonce (12 bytes)
        $prk2 = hash_hmac('sha256', $ikm, $salt, true);

        $cekInfo  = "Content-Encoding: aes128gcm\x00";
        $cek = self::hkdfExpand($prk2,
            "Content-Encoding: aes128gcm\x00",
            16
        );
        $nonce = self::hkdfExpand($prk2,
            "Content-Encoding: nonce\x00",
            12
        );

        // Alternative per-spec derivation using context string
        // Build context = label + 0x00 + length_of_receiver(2 bytes big-endian) + receiverPubKey + length_of_sender + senderPubBin
        $context = "P-256\x00"
            . "\x00\x41" . $receiverPubKey    // 0x0041 = 65
            . "\x00\x41" . $senderPubBin;

        $cek2Info   = "Content-Encoding: aesgcm\x00" . $context;
        $nonce2Info = "Content-Encoding: nonce\x00"  . $context;

        // Use the newer aes128gcm spec (RFC 8291 replaces older aesgcm)
        // For simplicity, use a single-record encryption (no chunking, record_size = payload + 17)
        $tag   = '';
        $plain = $plaintext . "\x02"; // padding delimiter
        $cipher = openssl_encrypt($plain, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) { throw new RuntimeException('AES-GCM failed'); }
        $ciphertext = $cipher . $tag;

        return [
            'ciphertext'        => $ciphertext,
            'salt'              => $salt,
            'server_public_key' => $senderPubBin,
        ];
    }

    // ── VAPID JWT ─────────────────────────────────────────────────────────────

    private static function buildVapidHeader(string $endpoint, int $ttl): string
    {
        $privatePem = dbq("SELECT setting_value FROM app_settings WHERE setting_key = 'vapid_private_pem'")->fetchColumn();
        $publicKey  = self::getPublicKeyBase64();
        if (!$privatePem || !$publicKey) { throw new RuntimeException('VAPID keys not set up. Call WebPushService::setup() first.'); }

        $parts  = parse_url($endpoint);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $header  = self::b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::b64url_encode(json_encode([
            'aud' => $origin,
            'exp' => time() + 43200,   // 12 hours
            'sub' => 'mailto:admin@syndrasi.local',
        ]));

        $input = $header . '.' . $payload;
        $key   = openssl_pkey_get_private($privatePem);
        $sig   = '';
        openssl_sign($input, $sig, $key, OPENSSL_ALGO_SHA256);

        // openssl_sign produces DER-encoded signature; convert to raw (r||s) for JWT
        $sig = self::derToRaw($sig);
        $jwt = $input . '.' . self::b64url_encode($sig);

        return 'vapid t=' . $jwt . ', k=' . $publicKey;
    }

    // ── HTTP POST to push endpoint ────────────────────────────────────────────

    private static function httpPost(
        string $endpoint,
        string $ciphertext,
        string $salt,
        string $serverPubKey,
        string $vapidHeader,
        int    $ttl
    ): bool {
        // Build Content-Encoding: aes128gcm record header (16-byte salt, 4-byte record size, 1-byte key len, 65-byte key)
        $recordSize = strlen($ciphertext);
        $header = $salt                              // 16 bytes salt
            . pack('N', $recordSize)                // 4 bytes big-endian record size
            . chr(65)                               // 1 byte key length (65 = uncompressed P-256)
            . $serverPubKey;                        // 65 bytes

        $body = $header . $ciphertext;

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . $ttl,
            'Authorization: ' . $vapidHeader,
            'Content-Length: ' . strlen($body),
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) { error_log('[WebPush] cURL error: ' . $error); return false; }

        // 201 = Created (success), 410 = Gone (subscription expired — delete it)
        if ($code === 410) {
            // Subscription expired: clean it up
            dbq('DELETE FROM push_subscriptions WHERE endpoint = :ep', ['ep' => $endpoint]);
        }

        return $code >= 200 && $code < 300;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function b64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    /**
     * HKDF-Expand (RFC 5869).
     * T(1) = HMAC-Hash(PRK, "" || info || 0x01)  (only first block needed here)
     */
    private static function hkdfExpand(string $prk, string $info, int $length): string
    {
        $t = '';
        $okm = '';
        $i = 0;
        while (strlen($okm) < $length) {
            $i++;
            $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }
        return substr($okm, 0, $length);
    }

    /**
     * Convert a raw 65-byte uncompressed P-256 public key to PEM format.
     */
    private static function binToPem(string $keyBin): string
    {
        // SubjectPublicKeyInfo DER wrapper for P-256 uncompressed key
        $der = "\x30\x59"                          // SEQUENCE
             . "\x30\x13"                          // SEQUENCE (algorithm)
               . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" // OID ecPublicKey
               . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" // OID prime256v1
             . "\x03\x42\x00"                      // BIT STRING (66 bytes)
             . $keyBin;                            // 65 bytes key
        return "-----BEGIN PUBLIC KEY-----\r\n"
             . chunk_split(base64_encode($der), 64, "\r\n")
             . "-----END PUBLIC KEY-----\r\n";
    }

    /**
     * Convert DER-encoded ECDSA signature to raw r||s (32+32 bytes).
     */
    private static function derToRaw(string $der): string
    {
        // DER: 30 [len] 02 [rlen] [r bytes] 02 [slen] [s bytes]
        $offset = 2; // skip 30 [len]
        $rLen   = ord($der[$offset + 1]);
        $rOff   = $offset + 2;
        if (ord($der[$rOff]) === 0x00) { $rLen--; $rOff++; }
        $r = str_pad(substr($der, $rOff, $rLen), 32, "\x00", STR_PAD_LEFT);

        $sOff0 = $rOff + $rLen;
        $sLen  = ord($der[$sOff0 + 1]);
        $sOff  = $sOff0 + 2;
        if (ord($der[$sOff]) === 0x00) { $sLen--; $sOff++; }
        $s = str_pad(substr($der, $sOff, $sLen), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }
}
