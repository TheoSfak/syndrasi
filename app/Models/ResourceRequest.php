<?php
/**
 * SynDrasi - Resource dispatch request (Smart Resource Dispatch, Φάση 1).
 * Ο χειριστής ζητά από μια ομάδα να διαθέσει έναν πόρο σε ενεργή δράση.
 * pending → accepted/declined (Φάση 2, από την ομάδα) → delivered / cancelled.
 */
class ResourceRequest
{
    public static function find(int $id): ?array
    {
        return dbq('SELECT * FROM resource_requests WHERE id = :id LIMIT 1', ['id' => $id])->fetch() ?: null;
    }

    /** @param array $d keys: mid, eid, shortage_id|null, from_team_id, item_label, requested_by|null */
    public static function create(array $d): int
    {
        dbq(
            'INSERT INTO resource_requests
             (municipality_id, event_id, shortage_id, from_team_id, item_label, requested_by)
             VALUES (:mid, :eid, :sid, :tid, :item, :uid)',
            [
                'mid'  => (int) $d['mid'],
                'eid'  => (int) $d['eid'],
                'sid'  => $d['shortage_id'] !== null ? (int) $d['shortage_id'] : null,
                'tid'  => (int) $d['from_team_id'],
                'item' => (string) $d['item_label'],
                'uid'  => $d['requested_by'] !== null ? (int) $d['requested_by'] : null,
            ]
        );
        return (int) db()->lastInsertId();
    }

    /** Ομάδα απαντά (Φάση 2). Μόνο από pending. Returns true αν άλλαξε γραμμή. */
    public static function respond(int $id, string $status, ?string $note = null, ?int $etaMinutes = null): bool
    {
        if (!in_array($status, ['accepted', 'declined'], true)) {
            return false;
        }
        return dbq(
            "UPDATE resource_requests
             SET status = :status, response_note = :note, eta_minutes = :eta, responded_at = NOW()
             WHERE id = :id AND status = 'pending'",
            ['status' => $status, 'note' => $note, 'eta' => $etaMinutes, 'id' => $id]
        )->rowCount() > 0;
    }

    /** Χειριστής: ο πόρος παραδόθηκε. Από pending ή accepted. */
    public static function markDelivered(int $id): bool
    {
        return dbq(
            "UPDATE resource_requests SET status = 'delivered', delivered_at = NOW()
             WHERE id = :id AND status IN ('pending','accepted')",
            ['id' => $id]
        )->rowCount() > 0;
    }

    /** Χειριστής: ακύρωση αιτήματος. Από pending ή accepted. */
    public static function cancel(int $id): bool
    {
        return dbq(
            "UPDATE resource_requests SET status = 'cancelled'
             WHERE id = :id AND status IN ('pending','accepted')",
            ['id' => $id]
        )->rowCount() > 0;
    }

    /** Όλα τα αιτήματα μιας δράσης, με όνομα ομάδας + τίτλο/κατάσταση έλλειψης (για war-room). */
    public static function forEvent(int $eid): array
    {
        return dbq(
            "SELECT rr.*, t.name AS team_name, sr.title AS shortage_title, sr.status AS shortage_status
             FROM resource_requests rr
             JOIN volunteer_teams t ON t.id = rr.from_team_id
             LEFT JOIN shortage_reports sr ON sr.id = rr.shortage_id
             WHERE rr.event_id = :eid
             ORDER BY FIELD(rr.status,'pending','accepted','delivered','declined','cancelled'),
                      rr.created_at DESC
             LIMIT 40",
            ['eid' => $eid]
        )->fetchAll();
    }

    /** Εκκρεμή αιτήματα προς μια ομάδα (Φάση 2: field hub / team live). */
    public static function pendingForTeam(int $teamId, ?int $eid = null): array
    {
        $sql = "SELECT rr.*, e.title AS event_title
                FROM resource_requests rr
                JOIN events e ON e.id = rr.event_id
                WHERE rr.from_team_id = :tid AND rr.status = 'pending'";
        $p   = ['tid' => $teamId];
        if ($eid !== null) {
            $sql .= ' AND rr.event_id = :eid';
            $p['eid'] = $eid;
        }
        return dbq($sql . ' ORDER BY rr.created_at DESC', $p)->fetchAll();
    }

    /** Υπάρχει ήδη ανοιχτό (pending/accepted) αίτημα ίδιας έλλειψης προς την ίδια ομάδα; */
    public static function hasOpenForShortageTeam(int $shortageId, int $teamId): bool
    {
        return (bool) dbq(
            "SELECT 1 FROM resource_requests
             WHERE shortage_id = :sid AND from_team_id = :tid
               AND status IN ('pending','accepted') LIMIT 1",
            ['sid' => $shortageId, 'tid' => $teamId]
        )->fetchColumn();
    }

    /** Team ids με ανοιχτό αίτημα ανά shortage — για να μην ξαναπροταθούν. */
    public static function openTeamIdsByShortage(int $eid): array
    {
        $rows = dbq(
            "SELECT shortage_id, from_team_id FROM resource_requests
             WHERE event_id = :eid AND shortage_id IS NOT NULL
               AND status IN ('pending','accepted')",
            ['eid' => $eid]
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['shortage_id']][(int) $r['from_team_id']] = true;
        }
        return $out;
    }

    /** Auto-cancel pending αιτημάτων όταν η έλλειψη επιλύεται χειροκίνητα. */
    public static function cancelPendingForShortage(int $shortageId): void
    {
        dbq(
            "UPDATE resource_requests SET status = 'cancelled'
             WHERE shortage_id = :sid AND status = 'pending'",
            ['sid' => $shortageId]
        );
    }
}
