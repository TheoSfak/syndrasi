<?php
/**
 * SynDrasi - Notification model.
 */
class Notification
{
    public static function forUser($userId, $limit = 50)
    {
        return dbq(
            'SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT ' . (int) $limit,
            ['uid' => $userId]
        )->fetchAll();
    }

    public static function unreadCount($userId)
    {
        return (int) dbq(
            'SELECT COUNT(*) AS c FROM notifications WHERE user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        )->fetchColumn();
    }

    public static function markRead($id, $userId)
    {
        dbq('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid', ['id' => $id, 'uid' => $userId]);
    }

    public static function markAllRead($userId)
    {
        dbq('UPDATE notifications SET is_read = 1 WHERE user_id = :uid', ['uid' => $userId]);
    }

    public static function create(array $d)
    {
        $defaults = [
            'municipality_id' => null, 'user_id' => null, 'team_id' => null, 'event_id' => null,
            'title' => '', 'message' => '', 'type' => null, 'email_sent' => 0,
        ];
        $d = array_merge($defaults, $d);
        dbq(
            'INSERT INTO notifications (municipality_id, user_id, team_id, event_id, title, message, type, email_sent)
             VALUES (:municipality_id, :user_id, :team_id, :event_id, :title, :message, :type, :email_sent)',
            $d
        );
        return (int) db()->lastInsertId();
    }
}
