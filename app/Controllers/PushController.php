<?php
/**
 * SynDrasi - PushController.
 * Manages Web Push subscriptions and serves the VAPID public key.
 */
class PushController
{
    /**
     * GET /push/vapid-key  — Returns the VAPID public key for JS PushManager.subscribe.
     */
    public function vapidKey()
    {
        requireLogin();
        // Auto-setup VAPID keys on first request
        WebPushService::setup();
        json_out(['key' => WebPushService::getPublicKeyBase64()]);
    }

    /**
     * POST /push/subscribe  — Save a new push subscription.
     * Body: { endpoint, keys: { p256dh, auth } }
     */
    public function subscribe()
    {
        requireLogin();
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['endpoint']) || empty($body['keys']['p256dh']) || empty($body['keys']['auth'])) {
            json_out(['success' => false, 'message' => 'Invalid subscription data.'], 400);
        }

        $userId = $_SESSION['user_id'];
        dbq(
            "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth_key)
             VALUES (:uid, :ep, :p256dh, :auth)
             ON DUPLICATE KEY UPDATE p256dh=:p256dh, auth_key=:auth",
            [
                'uid'    => $userId,
                'ep'     => $body['endpoint'],
                'p256dh' => $body['keys']['p256dh'],
                'auth'   => $body['keys']['auth'],
            ]
        );
        json_out(['success' => true]);
    }

    /**
     * POST /push/unsubscribe  — Remove a push subscription.
     * Body: { endpoint }
     */
    public function unsubscribe()
    {
        requireLogin();
        $body = json_decode(file_get_contents('php://input'), true);
        if (!empty($body['endpoint'])) {
            dbq(
                'DELETE FROM push_subscriptions WHERE user_id = :uid AND endpoint = :ep',
                ['uid' => $_SESSION['user_id'], 'ep' => $body['endpoint']]
            );
        }
        json_out(['success' => true]);
    }
}
