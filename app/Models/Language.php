<?php
/**
 * SynDrasi - Language catalog (used by the Languages settings tab and by
 * per-user language preference).
 */
class Language
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM languages';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, name';
        return dbq($sql)->fetchAll();
    }

    public static function find(string $code): ?array
    {
        $row = dbq('SELECT * FROM languages WHERE code = :c LIMIT 1', ['c' => $code])->fetch();
        return $row ?: null;
    }

    public static function source(): array
    {
        return dbq('SELECT * FROM languages WHERE is_source = 1 LIMIT 1')->fetch();
    }

    public static function isActiveCode(string $code): bool
    {
        return (bool) dbq(
            'SELECT 1 FROM languages WHERE code = :c AND is_active = 1 LIMIT 1',
            ['c' => $code]
        )->fetchColumn();
    }

    public static function create(string $code, string $name): void
    {
        $nextOrder = (int) dbq('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM languages')->fetchColumn();
        dbq(
            'INSERT INTO languages (code, name, is_source, is_active, sort_order) VALUES (:c, :n, 0, 1, :o)',
            ['c' => $code, 'n' => $name, 'o' => $nextOrder]
        );
    }

    /** Returns false (and changes nothing) if asked to deactivate the source language. */
    public static function setActive(string $code, bool $active): bool
    {
        if (!$active) {
            $lang = self::find($code);
            if ($lang && (bool) $lang['is_source']) {
                return false;
            }
        }
        dbq('UPDATE languages SET is_active = :a WHERE code = :c', ['a' => $active ? 1 : 0, 'c' => $code]);
        return true;
    }
}
