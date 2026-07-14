<?php
/**
 * SynDrasi - User model.
 */
class User
{
    public static function findByEmail($email)
    {
        return dbq('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email])->fetch() ?: null;
    }

    public static function find($id)
    {
        return dbq('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id])->fetch() ?: null;
    }

    public static function updateLastLogin($id)
    {
        dbq('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public static function updatePassword($id, $hash)
    {
        dbq('UPDATE users SET password_hash = :h WHERE id = :id', ['h' => $hash, 'id' => $id]);
    }

    public static function updateLanguage($id, $languageCode)
    {
        dbq('UPDATE users SET language_code = :lc WHERE id = :id', ['lc' => $languageCode, 'id' => $id]);
    }

    public static function teamAdmins($teamId)
    {
        return dbq(
            'SELECT * FROM users WHERE team_id = :tid AND role = :role AND status = :st',
            ['tid' => $teamId, 'role' => 'team_admin', 'st' => 'active']
        )->fetchAll();
    }

    public static function municipalityAdmins($municipalityId)
    {
        return dbq(
            'SELECT * FROM users WHERE municipality_id = :mid AND role = :role AND status = :st',
            ['mid' => $municipalityId, 'role' => 'municipality_admin', 'st' => 'active']
        )->fetchAll();
    }

    /** Command staff of a municipality: admins + event operators (active). */
    public static function commandStaff($municipalityId)
    {
        return dbq(
            "SELECT * FROM users
             WHERE municipality_id = :mid AND status = 'active'
               AND role IN ('municipality_admin','event_operator')",
            ['mid' => $municipalityId]
        )->fetchAll();
    }
}
