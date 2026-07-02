<?php
/**
 * SynDrasi - Shared photo/video upload validation & storage.
 *
 * storePhoto()/storeVideo() only validate, save the file, and insert the DB
 * row; they never set flash messages, redirect, notify, or audit — callers
 * differ on those (e.g. FieldController swallows notification failures
 * silently, TeamPortalController lets them bubble and also calls audit()),
 * so that stays the caller's responsibility.
 */
class MediaUploader
{
    private const PHOTO_MAX_BYTES = 12 * 1024 * 1024;
    private const PHOTO_ALLOWED   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const VIDEO_MAX_BYTES = 60 * 1024 * 1024;
    private const VIDEO_ALLOWED   = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
    private const DURATION_MIN    = 1;
    private const DURATION_MAX    = 600;

    /**
     * @param array $file $_FILES['photo'] entry
     * @param array $ctx  mid, eid, tid, uid (nullable), rid (nullable), caption (nullable)
     * @return array{ok:bool,id?:int,lat?:?float,lng?:?float,error?:string}
     */
    public static function storePhoto(array $file, array $ctx): array
    {
        if (empty($file) || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Δεν επιλέχθηκε έγκυρη φωτογραφία.'];
        }
        if ((int) $file['size'] > self::PHOTO_MAX_BYTES) {
            return ['ok' => false, 'error' => 'Η φωτογραφία είναι πολύ μεγάλη (μέγιστο 12MB).'];
        }
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? '';
        if (!isset(self::PHOTO_ALLOWED[$mime])) {
            return ['ok' => false, 'error' => 'Επιτρέπονται μόνο εικόνες JPG / PNG / WebP.'];
        }

        $dir = BASE_PATH . EventPhoto::DIR;
        if (!self::ensureDir($dir)) {
            return ['ok' => false, 'error' => 'Αδυναμία αποθήκευσης (φάκελος).'];
        }
        $name = self::generateName((int) $ctx['eid'], (int) $ctx['tid'], self::PHOTO_ALLOWED[$mime]);
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Αποτυχία αποθήκευσης φωτογραφίας.'];
        }

        [$lat, $lng] = self::clampLatLng(post_float_or_null('latitude'), post_float_or_null('longitude'));

        $id = EventPhoto::create([
            'mid' => $ctx['mid'], 'eid' => $ctx['eid'], 'tid' => $ctx['tid'],
            'uid' => $ctx['uid'] ?? null, 'rid' => $ctx['rid'] ?? null, 'file' => $name,
            'lat' => $lat, 'lng' => $lng, 'caption' => $ctx['caption'] ?? null,
        ]);

        return ['ok' => true, 'id' => $id, 'lat' => $lat, 'lng' => $lng];
    }

    /**
     * @param array $file $_FILES['video'] entry
     * @param array $ctx  mid, eid, tid, uid (nullable), rid (nullable), caption (nullable)
     * @return array{ok:bool,id?:int,lat?:?float,lng?:?float,error?:string}
     */
    public static function storeVideo(array $file, array $ctx): array
    {
        if (empty($file) || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Δεν επιλέχθηκε έγκυρο βίντεο.'];
        }
        if ((int) $file['size'] > self::VIDEO_MAX_BYTES) {
            return ['ok' => false, 'error' => 'Το βίντεο είναι πολύ μεγάλο (μέγιστο 60MB).'];
        }
        // Trust the server-detected MIME, not the browser-supplied one.
        $detected = function_exists('mime_content_type') ? (mime_content_type($file['tmp_name']) ?: '') : (string) ($file['type'] ?? '');
        $base = strtolower(trim(explode(';', $detected)[0]));
        if (!isset(self::VIDEO_ALLOWED[$base])) {
            return ['ok' => false, 'error' => 'Επιτρέπονται μόνο βίντεο MP4 / WebM / MOV.'];
        }

        $dir = BASE_PATH . EventVideo::DIR;
        if (!self::ensureDir($dir)) {
            return ['ok' => false, 'error' => 'Αδυναμία αποθήκευσης (φάκελος).'];
        }
        $name = self::generateName((int) $ctx['eid'], (int) $ctx['tid'], self::VIDEO_ALLOWED[$base]);
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'Αποτυχία αποθήκευσης βίντεο.'];
        }

        [$lat, $lng] = self::clampLatLng(post_float_or_null('latitude'), post_float_or_null('longitude'));
        $dur = post_int('duration');
        if ($dur < self::DURATION_MIN || $dur > self::DURATION_MAX) {
            $dur = null;
        }

        $id = EventVideo::create([
            'mid' => $ctx['mid'], 'eid' => $ctx['eid'], 'tid' => $ctx['tid'],
            'uid' => $ctx['uid'] ?? null, 'rid' => $ctx['rid'] ?? null, 'file' => $name,
            'mime' => $base, 'dur' => $dur, 'size' => (int) $file['size'],
            'lat' => $lat, 'lng' => $lng, 'caption' => $ctx['caption'] ?? null,
        ]);

        return ['ok' => true, 'id' => $id, 'lat' => $lat, 'lng' => $lng];
    }

    private static function ensureDir(string $dir): bool
    {
        return is_dir($dir) || @mkdir($dir, 0775, true);
    }

    private static function generateName(int $eventId, int $teamId, string $ext): string
    {
        return 'ev' . $eventId . '_t' . $teamId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    }

    /** Clamp to valid ranges; if either is invalid/missing, both become null (all-or-nothing pairing). */
    private static function clampLatLng(?float $lat, ?float $lng): array
    {
        if ($lat !== null && ($lat < -90 || $lat > 90)) { $lat = null; }
        if ($lng !== null && ($lng < -180 || $lng > 180)) { $lng = null; }
        if ($lat === null || $lng === null) { $lat = null; $lng = null; }
        return [$lat, $lng];
    }
}
