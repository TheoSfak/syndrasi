<?php
/**
 * SynDrasi - Municipality notification control center.
 */
class NotificationCenterController
{
    public function index()
    {
        requireRole(['municipality_admin']);
        $mid = (int) current_municipality_id();
        $filters = [
            'channel' => $this->allowed($_GET['channel'] ?? 'all', ['all','email','sms','telegram','push','in_app'], 'all'),
            'status' => $this->allowed($_GET['status'] ?? 'all', ['all','queued','pending','sent','failed','skipped','read','unread'], 'all'),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];

        $items = $this->filteredItems($this->allItems($mid), $filters);
        render('notification_center/index', [
            'pageTitle' => 'Κέντρο Ελέγχου Ειδοποιήσεων',
            'filters' => $filters,
            'stats' => $this->stats($mid),
            'items' => array_slice($items, 0, 150),
            'deliveryLogAvailable' => $this->tableExists('notification_deliveries'),
        ]);
    }

    public function retryEmail($id)
    {
        requireRole(['municipality_admin']);
        $mid = (int) current_municipality_id();
        $mailId = (int) $id;
        $row = dbq(
            'SELECT id, sent_at FROM mail_queue WHERE id = :id AND municipality_id = :mid LIMIT 1',
            ['id' => $mailId, 'mid' => $mid]
        )->fetch();
        if (!$row) {
            abort(404, 'Δεν βρέθηκε email για επανάληψη.');
        }

        dbq(
            'UPDATE mail_queue
             SET sent_at = NULL, attempts = 0, last_attempt = NULL, error_msg = NULL
             WHERE id = :id AND municipality_id = :mid',
            ['id' => $mailId, 'mid' => $mid]
        );
        NotificationDelivery::markExternalRef('mail_queue:' . $mailId, 'queued', 0, null);
        audit('notification_center_email_retry', 'mail_queue', $mailId);
        flash_set('success', 'Το email μπήκε ξανά στην ουρά αποστολής.');
        redirect('/notification-center?channel=email&status=queued');
    }

    public function clearHistory()
    {
        requireRole(['municipality_admin']);
        $mid = (int) current_municipality_id();
        if (post_str('confirm') !== 'DELETE') {
            flash_set('danger', 'Για διαγραφή ιστορικού πληκτρολογήστε DELETE.');
            redirect('/notification-center');
        }

        $scope = $this->allowed(post_str('scope', 'all'), ['all','delivery','in_app'], 'all');
        $deleted = ['delivery' => 0, 'mail_queue' => 0, 'in_app' => 0];
        try {
            if (in_array($scope, ['all', 'delivery'], true)) {
                if ($this->tableExists('notification_deliveries')) {
                    $deleted['delivery'] = dbq(
                        'DELETE FROM notification_deliveries WHERE municipality_id = :mid',
                        ['mid' => $mid]
                    )->rowCount();
                }
                $deleted['mail_queue'] = dbq(
                    'DELETE FROM mail_queue WHERE municipality_id = :mid',
                    ['mid' => $mid]
                )->rowCount();
            }
            if (in_array($scope, ['all', 'in_app'], true)) {
                $deleted['in_app'] = dbq(
                    'DELETE FROM notifications WHERE municipality_id = :mid',
                    ['mid' => $mid]
                )->rowCount();
            }
            audit('notification_center_history_cleared', 'municipality', $mid, ['scope' => $scope, 'deleted' => $deleted]);
            flash_set('success', 'Το ιστορικό ειδοποιήσεων διαγράφηκε για τον επιλεγμένο τύπο.');
        } catch (Throwable $e) {
            flash_set('danger', 'Δεν ήταν δυνατή η διαγραφή ιστορικού: ' . $e->getMessage());
        }
        redirect('/notification-center');
    }

    private function allItems(int $mid): array
    {
        $items = [];
        if ($this->tableExists('notification_deliveries')) {
            foreach ($this->deliveryItems($mid) as $row) {
                $items[] = [
                    'source' => 'delivery',
                    'id' => (int) $row['id'],
                    'channel' => $row['channel'],
                    'status' => $row['status'],
                    'recipient' => $row['recipient_label'] ?: ($row['user_name'] ?: $row['recipient_address']),
                    'address' => $row['recipient_address'] ?: ($row['user_email'] ?? ''),
                    'title' => $row['title'],
                    'message' => (string) ($row['message'] ?? ''),
                    'type' => $row['type'] ?: '',
                    'team' => $row['team_name'] ?: '',
                    'event' => $row['event_title'] ?: '',
                    'created_at' => $row['created_at'],
                    'attempts' => (int) $row['attempts'],
                    'error' => (string) ($row['error_msg'] ?? ''),
                    'retry_mail_id' => $this->mailIdFromRef((string) ($row['external_ref'] ?? '')),
                ];
            }
        }

        foreach ($this->legacyMailItems($mid) as $row) {
            $items[] = [
                'source' => 'mail_queue',
                'id' => (int) $row['id'],
                'channel' => 'email',
                'status' => $this->mailStatus($row),
                'recipient' => $row['to_name'] ?: $row['to_email'],
                'address' => $row['to_email'],
                'title' => $row['subject'],
                'message' => (string) ($row['body'] ?? ''),
                'type' => '',
                'team' => '',
                'event' => '',
                'created_at' => $row['created_at'],
                'attempts' => (int) $row['attempts'],
                'error' => (string) ($row['error_msg'] ?? ''),
                'retry_mail_id' => (int) $row['id'],
            ];
        }

        foreach ($this->inAppItems($mid) as $row) {
            $items[] = [
                'source' => 'notifications',
                'id' => (int) $row['id'],
                'channel' => 'in_app',
                'status' => ((int) $row['is_read'] === 1) ? 'read' : 'unread',
                'recipient' => $row['user_name'] ?: ($row['team_name'] ?: 'Χρήστης'),
                'address' => $row['user_email'] ?: '',
                'title' => $row['title'],
                'message' => (string) $row['message'],
                'type' => $row['type'] ?: '',
                'team' => $row['team_name'] ?: '',
                'event' => $row['event_title'] ?: '',
                'created_at' => $row['created_at'],
                'attempts' => 0,
                'error' => '',
                'retry_mail_id' => null,
            ];
        }

        usort($items, fn($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return $items;
    }

    private function deliveryItems(int $mid): array
    {
        try {
            return dbq(
                "SELECT nd.*, u.name AS user_name, u.email AS user_email,
                        vt.name AS team_name, e.title AS event_title
                 FROM notification_deliveries nd
                 LEFT JOIN users u ON u.id = nd.recipient_user_id
                 LEFT JOIN volunteer_teams vt ON vt.id = nd.team_id
                 LEFT JOIN events e ON e.id = nd.event_id
                 WHERE nd.municipality_id = :mid
                 ORDER BY nd.created_at DESC, nd.id DESC
                 LIMIT 500",
                ['mid' => $mid]
            )->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function legacyMailItems(int $mid): array
    {
        try {
            if ($this->tableExists('notification_deliveries')) {
                return dbq(
                    "SELECT mq.*
                     FROM mail_queue mq
                     LEFT JOIN notification_deliveries nd ON nd.external_ref = CONCAT('mail_queue:', mq.id)
                     WHERE mq.municipality_id = :mid AND nd.id IS NULL
                     ORDER BY mq.created_at DESC, mq.id DESC
                     LIMIT 200",
                    ['mid' => $mid]
                )->fetchAll();
            }
            return dbq(
                'SELECT * FROM mail_queue WHERE municipality_id = :mid ORDER BY created_at DESC, id DESC LIMIT 200',
                ['mid' => $mid]
            )->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function inAppItems(int $mid): array
    {
        try {
            return dbq(
                "SELECT n.*, u.name AS user_name, u.email AS user_email,
                        vt.name AS team_name, e.title AS event_title
                 FROM notifications n
                 LEFT JOIN users u ON u.id = n.user_id
                 LEFT JOIN volunteer_teams vt ON vt.id = n.team_id
                 LEFT JOIN events e ON e.id = n.event_id
                 WHERE n.municipality_id = :mid
                 ORDER BY n.created_at DESC, n.id DESC
                 LIMIT 300",
                ['mid' => $mid]
            )->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function filteredItems(array $items, array $filters): array
    {
        return array_values(array_filter($items, function ($item) use ($filters) {
            if ($filters['channel'] !== 'all' && $item['channel'] !== $filters['channel']) {
                return false;
            }
            if ($filters['status'] !== 'all' && $item['status'] !== $filters['status']) {
                if (!($filters['status'] === 'pending' && $item['status'] === 'queued')) {
                    return false;
                }
            }
            if ($filters['date_from'] !== '' && strtotime($item['created_at']) < strtotime($filters['date_from'] . ' 00:00:00')) {
                return false;
            }
            if ($filters['date_to'] !== '' && strtotime($item['created_at']) > strtotime($filters['date_to'] . ' 23:59:59')) {
                return false;
            }
            if ($filters['q'] !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $item['recipient'], $item['address'], $item['title'], $item['message'],
                    $item['type'], $item['team'], $item['event'], $item['error'],
                ]));
                if (mb_strpos($haystack, mb_strtolower($filters['q'])) === false) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function stats(int $mid): array
    {
        $stats = [
            'total' => 0,
            'last_24h' => 0,
            'sent' => 0,
            'queued' => 0,
            'failed' => 0,
            'skipped' => 0,
            'unread' => 0,
            'channels' => ['email' => 0, 'sms' => 0, 'telegram' => 0, 'push' => 0, 'in_app' => 0],
        ];

        if ($this->tableExists('notification_deliveries')) {
            try {
                foreach (dbq(
                    "SELECT channel, status, COUNT(*) AS total,
                            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS last_24h
                     FROM notification_deliveries
                     WHERE municipality_id = :mid
                     GROUP BY channel, status",
                    ['mid' => $mid]
                )->fetchAll() as $r) {
                    $count = (int) $r['total'];
                    $stats['total'] += $count;
                    $stats['last_24h'] += (int) $r['last_24h'];
                    $stats['channels'][$r['channel']] = ($stats['channels'][$r['channel']] ?? 0) + $count;
                    if (isset($stats[$r['status']])) {
                        $stats[$r['status']] += $count;
                    }
                }
            } catch (Throwable $e) { /* keep partial stats */ }
        }

        try {
            $legacyJoin = $this->tableExists('notification_deliveries')
                ? "LEFT JOIN notification_deliveries nd ON nd.external_ref = CONCAT('mail_queue:', mq.id)"
                : '';
            $legacyWhere = $this->tableExists('notification_deliveries') ? 'AND nd.id IS NULL' : '';
            $mail = dbq(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN mq.sent_at IS NOT NULL THEN 1 ELSE 0 END) AS sent,
                        SUM(CASE WHEN mq.sent_at IS NULL AND mq.attempts < 3 THEN 1 ELSE 0 END) AS queued,
                        SUM(CASE WHEN mq.sent_at IS NULL AND mq.attempts >= 3 THEN 1 ELSE 0 END) AS failed,
                        SUM(CASE WHEN mq.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS last_24h
                 FROM mail_queue mq {$legacyJoin}
                 WHERE mq.municipality_id = :mid {$legacyWhere}",
                ['mid' => $mid]
            )->fetch() ?: [];
            foreach (['total','sent','queued','failed','last_24h'] as $key) {
                $stats[$key] += (int) ($mail[$key] ?? 0);
            }
            $stats['channels']['email'] += (int) ($mail['total'] ?? 0);
        } catch (Throwable $e) { /* mail_queue may not exist on old installs */ }

        try {
            $inApp = dbq(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread,
                        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS last_24h
                 FROM notifications
                 WHERE municipality_id = :mid",
                ['mid' => $mid]
            )->fetch() ?: [];
            $total = (int) ($inApp['total'] ?? 0);
            $stats['total'] += $total;
            $stats['channels']['in_app'] += $total;
            $stats['unread'] += (int) ($inApp['unread'] ?? 0);
            $stats['last_24h'] += (int) ($inApp['last_24h'] ?? 0);
        } catch (Throwable $e) { /* ignore */ }

        return $stats;
    }

    private function mailStatus(array $row): string
    {
        if (!empty($row['sent_at'])) {
            return 'sent';
        }
        return ((int) ($row['attempts'] ?? 0) >= 3) ? 'failed' : 'queued';
    }

    private function mailIdFromRef(string $ref): ?int
    {
        if (preg_match('/^mail_queue:(\d+)$/', $ref, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            dbq('SELECT 1 FROM ' . $table . ' LIMIT 1');
            return $cache[$table] = true;
        } catch (Throwable $e) {
            return $cache[$table] = false;
        }
    }

    private function allowed(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
