<?php
/**
 * SynDrasi - Event message thread (active-event comms).
 *
 * Carries three kinds of traffic between command (δήμος) and teams during a δράση:
 *   - 'message' : free-text two-way chat
 *   - 'order'   : an instruction from command to one team or all teams (broadcast);
 *                 a team can ACK it (acknowledged_at)
 *   - 'status'  : a one-tap status ping from a team (status_code carries which one)
 *
 * team_id IS NULL only for a command broadcast to every team.
 */
class EventMessage
{
    /** One-tap status ping codes → Greek labels. */
    public const STATUS_LABELS = [
        'arrived'       => 'Φτάσαμε στο σημείο',
        'task_complete' => 'Ολοκληρώθηκε η αποστολή',
        'need_backup'   => 'Χρειαζόμαστε ενίσχυση',
        'returning'     => 'Επιστροφή στη βάση',
        'incident'      => 'Έχουμε περιστατικό',
    ];

    public static function statusLabel(?string $code): string
    {
        return self::STATUS_LABELS[$code] ?? ($code ?? '');
    }

    public static function create(array $d): int
    {
        dbq(
            'INSERT INTO event_messages
               (municipality_id, event_id, team_id, sender_role, sender_user_id, kind, status_code, body)
             VALUES
               (:mid, :eid, :tid, :role, :uid, :kind, :code, :body)',
            [
                'mid'  => $d['mid'],  'eid'  => $d['eid'],  'tid'  => $d['tid'] ?? null,
                'role' => $d['role'], 'uid'  => $d['uid'],  'kind' => $d['kind'] ?? 'message',
                'code' => $d['code'] ?? null, 'body' => $d['body'] ?? null,
            ]
        );
        return (int) db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $row = dbq('SELECT * FROM event_messages WHERE id = :id LIMIT 1', ['id' => $id])->fetch();
        return $row ?: null;
    }

    /**
     * Command view: full event thread (all teams + broadcasts), newest last.
     * Pass $sinceId to fetch only newer rows (for polling).
     */
    public static function forEvent(int $eid, int $sinceId = 0): array
    {
        return dbq(
            "SELECT m.*, t.name AS team_name, u.name AS sender_name
             FROM event_messages m
             LEFT JOIN volunteer_teams t ON t.id = m.team_id
             LEFT JOIN users u ON u.id = m.sender_user_id
             WHERE m.event_id = :eid AND m.id > :since
             ORDER BY m.id ASC",
            ['eid' => $eid, 'since' => $sinceId]
        )->fetchAll();
    }

    /**
     * Team view: this team's thread = messages addressed to the team (team_id)
     * plus command broadcasts (team_id IS NULL). Newest last.
     */
    public static function forTeamEvent(int $eid, int $tid, int $sinceId = 0): array
    {
        return dbq(
            "SELECT m.*, u.name AS sender_name
             FROM event_messages m
             LEFT JOIN users u ON u.id = m.sender_user_id
             WHERE m.event_id = :eid AND m.id > :since
               AND (m.team_id = :tid OR m.team_id IS NULL)
             ORDER BY m.id ASC",
            ['eid' => $eid, 'tid' => $tid, 'since' => $sinceId]
        )->fetchAll();
    }

    /** Team acknowledges a command order. */
    public static function acknowledge(int $id, int $userId): void
    {
        dbq(
            "UPDATE event_messages
             SET acknowledged_at = NOW(), acknowledged_by = :uid
             WHERE id = :id AND kind = 'order' AND acknowledged_at IS NULL",
            ['uid' => $userId, 'id' => $id]
        );
    }
}
