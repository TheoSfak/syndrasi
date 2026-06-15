<?php
/**
 * SynDrasi - Per-member response to an emergency mobilization.
 */
class MobilizationResponse
{
    /**
     * Create one response row per targeted member (bulk, single transaction-safe loop).
     * Returns an array of the created rows (id, member_id, team_id, token) so the
     * caller can fan out notifications without re-querying.
     */
    public static function seedTargets(int $mobilizationId, array $members): array
    {
        $created = [];
        foreach ($members as $m) {
            $token = bin2hex(random_bytes(32)); // 64 hex chars
            dbq(
                'INSERT INTO mobilization_responses (mobilization_id, member_id, team_id, token)
                 VALUES (:mob, :mem, :team, :tok)',
                ['mob' => $mobilizationId, 'mem' => (int) $m['id'], 'team' => (int) $m['team_id'], 'tok' => $token]
            );
            $created[] = [
                'id'        => (int) db()->lastInsertId(),
                'member_id' => (int) $m['id'],
                'team_id'   => (int) $m['team_id'],
                'token'     => $token,
                'full_name' => $m['full_name'] ?? '',
                'email'     => $m['email'] ?? null,
                'phone'     => $m['phone'] ?? null,
                'user_id'   => isset($m['user_id']) && $m['user_id'] !== null ? (int) $m['user_id'] : null,
            ];
        }
        return $created;
    }

    public static function findByToken(string $token)
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        if (strlen($token) !== 64) {
            return null;
        }
        return dbq(
            "SELECT r.*, mob.title, mob.description, mob.severity, mob.status AS mob_status,
                    mob.location_name, mob.latitude, mob.longitude, mob.municipality_id,
                    tm.full_name AS member_name
             FROM mobilization_responses r
             JOIN mobilizations mob ON mob.id = r.mobilization_id
             JOIN team_members tm   ON tm.id = r.member_id
             WHERE r.token = :tok LIMIT 1",
            ['tok' => $token]
        )->fetch() ?: null;
    }

    public static function find($id)
    {
        return dbq('SELECT * FROM mobilization_responses WHERE id = :id LIMIT 1', ['id' => $id])->fetch() ?: null;
    }

    public static function setResponse(int $id, string $response, ?int $etaMinutes, ?string $notes = null): void
    {
        if (!in_array($response, ['coming', 'cant', 'maybe'], true)) {
            return;
        }
        dbq(
            'UPDATE mobilization_responses
             SET response = :resp, eta_minutes = :eta, notes = :notes, responded_at = NOW()
             WHERE id = :id',
            ['resp' => $response, 'eta' => $etaMinutes, 'notes' => $notes, 'id' => $id]
        );
    }

    public static function markNotified(int $id, bool $push): void
    {
        dbq(
            'UPDATE mobilization_responses SET notified_push = :p, notified_at = NOW() WHERE id = :id',
            ['p' => $push ? 1 : 0, 'id' => $id]
        );
    }

    public static function checkIn(int $id): void
    {
        dbq('UPDATE mobilization_responses SET checked_in_at = NOW() WHERE id = :id AND checked_in_at IS NULL',
            ['id' => $id]);
    }

    public static function depart(int $id): void
    {
        dbq('UPDATE mobilization_responses SET departed_at = NOW() WHERE id = :id AND checked_in_at IS NOT NULL',
            ['id' => $id]);
    }
}
