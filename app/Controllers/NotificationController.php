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
        flash_set('success', 'Όλες οι ειδοποιήσεις σημειώθηκαν ως αναγνωσμένες.');
        redirect('/notifications');
    }
}
