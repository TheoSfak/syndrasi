<?php
/**
 * SynDrasi - Per-municipality key/value settings.
 */
class MunicipalitySetting
{
    /** All settings of a municipality as [key => value]. */
    public static function all($municipalityId)
    {
        $rows = dbq(
            'SELECT setting_key, setting_value FROM municipality_settings WHERE municipality_id = :mid',
            ['mid' => $municipalityId]
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
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

    /** Upsert many settings at once. */
    public static function setMany($municipalityId, array $settings)
    {
        foreach ($settings as $key => $value) {
            dbq(
                'INSERT INTO municipality_settings (municipality_id, setting_key, setting_value)
                 VALUES (:mid, :k, :v)
                 ON DUPLICATE KEY UPDATE setting_value = :v2',
                ['mid' => $municipalityId, 'k' => $key, 'v' => $value, 'v2' => $value]
            );
        }
    }
}
