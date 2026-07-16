<?php
/**
 * SynDrasi - In-app notifications.
 */
class NotificationController
{
    public function index()
    {
        requireLogin();
        $notifications = Notification::forUser(current_user_id());
        render('dashboard/notifications', [
            'pageTitle'     => 'Ειδοποιήσεις',
            'notifications' => $notifications,
        ]);
    }

    /** GET /notifications/poll — live bell badge + recent unread (for app-wide toasts). */
    public function poll()
    {
        requireLogin();
        $uid  = current_user_id();
        $rows = dbq(
            "SELECT id, title, message, type, created_at
             FROM notifications WHERE user_id = :uid AND is_read = 0
             ORDER BY id DESC LIMIT 10",
            ['uid' => $uid]
        )->fetchAll();
        json_out([
            'ok'    => true,
            'count' => Notification::unreadCount($uid),
            'items' => $rows,
        ]);
    }

    public function markRead($id)
    {
        requireLogin();
        Notification::markRead((int) $id, $_SESSION['user_id']);
        redirect('/notifications');
    }

    public function markAllRead()
    {
        requireLogin();
        Notification::markAllRead(current_user_id());
        flash_set('success', t('controllers/NotificationController.001', 'Όλες οι ειδοποιήσεις σημειώθηκαν ως αναγνωσμένες.'));
        redirect('/notifications');
    }
}
