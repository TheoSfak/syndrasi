<?php
/**
 * SynDrasi - Emergency mobilization (call-out) model.
 */
class Mobilization
{
    public const VALID_SEVERITIES = ['low', 'medium', 'high', 'critical'];
    public const VALID_STATUSES   = ['open', 'active', 'stood_down'];

    public static function find($id)
    {
        return dbq(
            'SELECT mob.*, u.name AS creator_name, e.title AS event_title
             FROM mobilizations mob
             LEFT JOIN users u  ON u.id = mob.created_by
             LEFT JOIN events e ON e.id = mob.event_id
             WHERE mob.id = :id LIMIT 1',
            ['id' => $id]
        )->fetch() ?: null;
    }

    /** Find a mobilization and enforce municipality isolation. */
    public static function findForCurrent($id)
    {
        $mob = self::find($id);
        if (!$mob) {
            abort(404, 'Το κάλεσμα δεν βρέθηκε.');
        }
        requireMunicipalityAccess($mob['municipality_id']);
        return $mob;
    }

    public static function forMunicipality($municipalityId)
    {
        return dbq(
            "SELECT mob.*, u.name AS creator_name,
                    (SELECT COUNT(*) FROM mobilization_responses r
                       WHERE r.mobilization_id = mob.id) AS targeted,
                    (SELECT COUNT(*) FROM mobilization_responses r
                       WHERE r.mobilization_id = mob.id AND r.response = 'coming') AS confirmed
             FROM mobilizations mob
             LEFT JOIN users u ON u.id = mob.created_by
             WHERE mob.municipality_id = :mid
             ORDER BY FIELD(mob.status,'active','open','stood_down'), mob.started_at DESC",
            ['mid' => $municipalityId]
        )->fetchAll();
    }

    /** Active call-outs for a municipality (for banners / war room). */
    public static function activeForMunicipality($municipalityId)
    {
        return dbq(
            "SELECT * FROM mobilizations
             WHERE municipality_id = :mid AND status IN ('open','active')
             ORDER BY started_at DESC",
            ['mid' => $municipalityId]
        )->fetchAll();
    }

    public static function create(array $d)
    {
        dbq(
            'INSERT INTO mobilizations
               (municipality_id, created_by, event_id, title, description, severity,
                location_name, latitude, longitude, status)
             VALUES
               (:municipality_id, :created_by, :event_id, :title, :description, :severity,
                :location_name, :latitude, :longitude, :status)',
            $d
        );
        return (int) db()->lastInsertId();
    }

    public static function standDown($id)
    {
        dbq(
            "UPDATE mobilizations SET status = 'stood_down', ended_at = NOW()
             WHERE id = :id AND status != 'stood_down'",
            ['id' => $id]
        );
    }

    public static function activate($id)
    {
        dbq("UPDATE mobilizations SET status = 'active' WHERE id = :id AND status = 'open'", ['id' => $id]);
    }

    /**
     * Live snapshot: the call-out plus derived counts and the per-member roster.
     * Two queries total — no per-member work.
     */
    public static function snapshot($id): array
    {
        $mob = self::find($id);
        if (!$mob) {
            return ['ok' => false];
        }

        $rows = dbq(
            "SELECT r.id, r.member_id, r.team_id, r.response, r.eta_minutes,
                    r.responded_at, r.checked_in_at, r.departed_at,
                    tm.full_name AS member_name, tm.phone AS member_phone,
                    vt.name AS team_name
             FROM mobilization_responses r
             JOIN team_members tm   ON tm.id = r.member_id
             JOIN volunteer_teams vt ON vt.id = r.team_id
             WHERE r.mobilization_id = :id
             ORDER BY
               FIELD(r.response,'coming','maybe','pending','cant'),
               (r.checked_in_at IS NULL),
               r.eta_minutes IS NULL, r.eta_minutes ASC,
               tm.full_name ASC",
            ['id' => $id]
        )->fetchAll();

        $counts = [
            'targeted'  => count($rows),
            'confirmed' => 0,
            'en_route'  => 0,
            'on_site'   => 0,
            'departed'  => 0,
            'declined'  => 0,
            'no_reply'  => 0,
        ];
        foreach ($rows as $r) {
            if ($r['departed_at'] !== null) {
                $counts['departed']++;
            } elseif ($r['checked_in_at'] !== null) {
                $counts['on_site']++;
            } elseif ($r['response'] === 'coming') {
                $counts['confirmed']++;
                if ($r['eta_minutes'] !== null) {
                    $counts['en_route']++;
                }
            } elseif ($r['response'] === 'cant') {
                $counts['declined']++;
            } elseif ($r['response'] === 'pending') {
                $counts['no_reply']++;
            }
        }

        return [
            'ok'      => true,
            'id'      => (int) $mob['id'],
            'status'  => $mob['status'],
            'title'   => $mob['title'],
            'counts'  => $counts,
            'roster'  => $rows,
            'at'      => date('Y-m-d H:i:s'),
        ];
    }
}
