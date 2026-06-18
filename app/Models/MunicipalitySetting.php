<?php
/**
 * SynDrasi - Per-municipality key/value settings.
 */
class MunicipalitySetting
{
    /** All settings of a municipality as [key => value]. */
    public static function all($municipalityId)
    {
        static $allCache = [];
        $mid = (int) $municipalityId;
        if (!array_key_exists($mid, $allCache)) {
            $rows = dbq(
                'SELECT setting_key, setting_value FROM municipality_settings WHERE municipality_id = :mid',
                ['mid' => $mid]
            )->fetchAll();
            $out = [];
            foreach ($rows as $row) {
                $out[$row['setting_key']] = $row['setting_value'];
            }
            $allCache[$mid] = $out;
        }
        return $allCache[$mid];
    }

    public static function get($municipalityId, $key, $default = null)
    {
        static $cache = [];
        $cacheKey = $municipalityId . '.' . $key;
        if (!array_key_exists($cacheKey, $cache)) {
            $val = dbq(
                'SELECT setting_value FROM municipality_settings WHERE municipality_id = :mid AND setting_key = :k LIMIT 1',
                ['mid' => $municipalityId, 'k' => $key]
            )->fetchColumn();
            $cache[$cacheKey] = ($val !== false && $val !== null) ? $val : null;
        }
        return $cache[$cacheKey] ?? $default;
    }

    /** Short display label for the organisation (used in chat, ops map, etc.). */
    public static function orgLabelShort(array $settings): string
    {
        if (!empty($settings['org_name_short'])) return $settings['org_name_short'];
        $map = ['municipality'=>'Δήμος','civil_protection'=>'Πολ.Προστ.','fire_service'=>'Πυρ/κή','coast_guard'=>'Λιμενικό'];
        return $map[$settings['org_type'] ?? 'municipality'] ?? 'Δήμος';
    }

    /** Full organisation name, optionally built from type + municipality name. */
    public static function orgName(array $settings, string $munName = ''): string
    {
        if (!empty($settings['org_name'])) return $settings['org_name'];
        $prefixes = ['municipality'=>'Δήμος','civil_protection'=>'Πολιτική Προστασία','fire_service'=>'Πυροσβεστική','coast_guard'=>'Λιμενικό'];
        $prefix = $prefixes[$settings['org_type'] ?? 'municipality'] ?? 'Δήμος';
        return $munName !== '' ? $prefix . ' ' . $munName : $prefix;
    }

    /** Emoji icon for the organisation type. */
    public static function orgIcon(array $settings): string
    {
        $map = ['municipality'=>'🏛️','civil_protection'=>'🛡️','fire_service'=>'🚒','coast_guard'=>'⚓','custom'=>'🏢'];
        return $map[$settings['org_type'] ?? 'municipality'] ?? '🏛️';
    }

    /** Upsert many settings at once. */
    public static function setMany($municipalityId, array $settings)
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                dbq(
                    'INSERT INTO municipality_settings (municipality_id, setting_key, setting_value)
                     VALUES (:mid, :k, :v)
                     ON DUPLICATE KEY UPDATE setting_value = :v2',
                    ['mid' => $municipalityId, 'k' => $key, 'v' => $value, 'v2' => $value]
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
