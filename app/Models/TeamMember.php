<?php
/**
 * SynDrasi - Team member (volunteer roster) model.
 */
class TeamMember
{
    /** All fixed + optional columns that may be saved. */
    public const FIXED_FIELDS = ['full_name', 'phone', 'email', 'date_of_birth', 'address',
        'civil_protection_registry_no', 'role_in_team', 'notes'];

    public const OPTIONAL_FIELDS = ['blood_type', 'driving_license', 'certifications', 'id_number', 'amka'];

    // ------------------------------------------------------------------ Read

    public static function find($id)
    {
        return dbq(
            'SELECT tm.*, vt.name AS team_name, u.status AS login_status, u.email AS login_email
             FROM team_members tm
             JOIN volunteer_teams vt ON vt.id = tm.team_id
             LEFT JOIN users u ON u.id = tm.user_id
             WHERE tm.id = :id LIMIT 1',
            ['id' => $id]
        )->fetch() ?: null;
    }

    public static function findByUser($userId)
    {
        return dbq(
            'SELECT tm.*, vt.name AS team_name
             FROM team_members tm
             JOIN volunteer_teams vt ON vt.id = tm.team_id
             WHERE tm.user_id = :uid LIMIT 1',
            ['uid' => $userId]
        )->fetch() ?: null;
    }

    public static function allByTeam($teamId, $onlyActive = false)
    {
        $sql = 'SELECT tm.*, u.status AS login_status, u.email AS login_email
                FROM team_members tm
                LEFT JOIN users u ON u.id = tm.user_id
                WHERE tm.team_id = :tid';
        if ($onlyActive) {
            $sql .= " AND tm.status = 'active'";
        }
        $sql .= ' ORDER BY tm.is_team_admin DESC, tm.is_assistant_admin DESC, tm.full_name ASC';
        return dbq($sql, ['tid' => $teamId])->fetchAll();
    }

    public static function assistantsByTeam($teamId)
    {
        return dbq(
            "SELECT tm.*, u.status AS login_status, u.email AS login_email, u.last_login_at
             FROM team_members tm
             LEFT JOIN users u ON u.id = tm.user_id
             WHERE tm.team_id = :tid AND tm.is_assistant_admin = 1
             ORDER BY tm.full_name ASC",
            ['tid' => $teamId]
        )->fetchAll();
    }

    public static function allByMunicipality($municipalityId)
    {
        return dbq(
            'SELECT tm.*, vt.name AS team_name
             FROM team_members tm
             JOIN volunteer_teams vt ON vt.id = tm.team_id
             WHERE tm.municipality_id = :mid AND tm.status = \'active\'
             ORDER BY vt.name, tm.full_name',
            ['mid' => $municipalityId]
        )->fetchAll();
    }

    // ----------------------------------------------------------------- Write

    public static function create(array $d)
    {
        dbq(
            'INSERT INTO team_members
             (team_id, municipality_id, full_name, phone, email, date_of_birth, address,
              civil_protection_registry_no, role_in_team, notes,
              blood_type, driving_license, certifications, id_number, amka,
              is_team_admin, status)
             VALUES
             (:team_id, :municipality_id, :full_name, :phone, :email, :date_of_birth, :address,
              :civil_protection_registry_no, :role_in_team, :notes,
              :blood_type, :driving_license, :certifications, :id_number, :amka,
              :is_team_admin, \'active\')',
            $d
        );
        return (int) db()->lastInsertId();
    }

    public static function update($id, array $d)
    {
        $d['id'] = $id;
        dbq(
            'UPDATE team_members SET
             full_name = :full_name, phone = :phone, email = :email,
             date_of_birth = :date_of_birth, address = :address,
             civil_protection_registry_no = :civil_protection_registry_no,
             role_in_team = :role_in_team, notes = :notes,
             blood_type = :blood_type, driving_license = :driving_license,
             certifications = :certifications, id_number = :id_number, amka = :amka
             WHERE id = :id',
            $d
        );
    }

    public static function toggleStatus($id)
    {
        dbq(
            "UPDATE team_members SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id",
            ['id' => $id]
        );
    }

    public static function promoteAssistant($id, $userId, $promotedBy): void
    {
        dbq(
            'UPDATE team_members
             SET user_id = :uid,
                 is_team_admin = 1,
                 is_assistant_admin = 1,
                 assistant_promoted_at = NOW(),
                 assistant_promoted_by = :by,
                 status = \'active\'
             WHERE id = :id',
            ['uid' => $userId, 'by' => $promotedBy, 'id' => $id]
        );
    }

    public static function revokeAssistant($id, bool $disableUser = true): void
    {
        $member = self::find($id);
        if (!$member) {
            return;
        }

        dbq(
            'UPDATE team_members
             SET is_team_admin = 0,
                 is_assistant_admin = 0,
                 assistant_promoted_at = NULL,
                 assistant_promoted_by = NULL
             WHERE id = :id AND is_assistant_admin = 1',
            ['id' => $id]
        );

        if ($disableUser && !empty($member['user_id'])) {
            dbq(
                "UPDATE users SET status = 'inactive' WHERE id = :uid AND role = 'team_admin'",
                ['uid' => $member['user_id']]
            );
        }
    }

    // --------------------------------------------------------- Application M2M

    /** Replace the member list for an application (within a transaction). */
    public static function setApplicationMembers($applicationId, array $memberIds)
    {
        dbq('DELETE FROM event_application_members WHERE application_id = :aid', ['aid' => $applicationId]);
        foreach ($memberIds as $mid) {
            dbq(
                'INSERT IGNORE INTO event_application_members (application_id, member_id) VALUES (:aid, :mid)',
                ['aid' => $applicationId, 'mid' => (int) $mid]
            );
        }
    }

    /** Members attached to a specific application (with full member data). */
    public static function forApplication($applicationId)
    {
        return dbq(
            'SELECT tm.* FROM event_application_members eam
             JOIN team_members tm ON tm.id = eam.member_id
             WHERE eam.application_id = :aid
             ORDER BY tm.is_team_admin DESC, tm.full_name ASC',
            ['aid' => $applicationId]
        )->fetchAll();
    }

    /**
     * Batched version of forApplication() — avoids N+1 when listing many
     * applications. Returns a map: applicationId => [member rows].
     */
    public static function forApplications(array $applicationIds)
    {
        $map = [];
        if (empty($applicationIds)) {
            return $map;
        }
        $ids = array_map('intval', $applicationIds);
        foreach ($ids as $id) {
            $map[$id] = [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT eam.application_id, tm.*
             FROM event_application_members eam
             JOIN team_members tm ON tm.id = eam.member_id
             WHERE eam.application_id IN ($placeholders)
             ORDER BY tm.is_team_admin DESC, tm.full_name ASC"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['application_id']][] = $row;
        }
        return $map;
    }

    // ------------------------------------------------------ Conflict checking

    /**
     * Returns member IDs (from $candidateIds) that are already committed
     * to another approved application overlapping the given time window.
     * Excludes $excludeApplicationId (current application being edited).
     */
    public static function conflictingMembers(
        array $candidateIds,
        string $startDatetime,
        string $endDatetime,
        $excludeApplicationId = null
    ) {
        if (empty($candidateIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $params = $candidateIds;
        $params[] = $startDatetime;
        $params[] = $endDatetime;

        $excludeSql = '';
        if ($excludeApplicationId) {
            $excludeSql = ' AND ea.id != ?';
            $params[] = (int) $excludeApplicationId;
        }

        $rows = db()->prepare(
            "SELECT DISTINCT eam.member_id
             FROM event_application_members eam
             JOIN event_applications ea ON ea.id = eam.application_id
             JOIN events e ON e.id = ea.event_id
             WHERE eam.member_id IN ($placeholders)
               AND ea.status = 'approved'
               AND e.status IN ('open','review','confirmed','active')
               AND e.start_datetime < ?
               AND e.end_datetime   > ?
               $excludeSql"
        );
        $rows->execute($params);
        return array_column($rows->fetchAll(PDO::FETCH_ASSOC), 'member_id');
    }

    // -------------------------------------------- Municipality field config

    /**
     * Decode member_fields_config from municipality_settings.
     * Returns array keyed by field name → ['visible' => bool, 'required' => bool].
     */
    public static function fieldConfig($municipalityId)
    {
        $json = MunicipalitySetting::get($municipalityId, 'member_fields_config', '');
        $config = $json ? json_decode($json, true) : [];
        $defaults = [];
        foreach (self::OPTIONAL_FIELDS as $f) {
            $defaults[$f] = ['visible' => false, 'required' => false];
        }
        return array_merge($defaults, is_array($config) ? $config : []);
    }
}
