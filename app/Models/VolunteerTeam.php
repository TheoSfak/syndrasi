<?php
/**
 * SynDrasi - Volunteer team model.
 */
class VolunteerTeam
{
    public static function find($id)
    {
        return dbq('SELECT * FROM volunteer_teams WHERE id = :id LIMIT 1', ['id' => $id])->fetch() ?: null;
    }

    public static function forMunicipality($municipalityId, $onlyActive = false)
    {
        $sql = 'SELECT * FROM volunteer_teams WHERE municipality_id = :mid';
        if ($onlyActive) {
            $sql .= " AND status = 'active'";
        }
        $sql .= ' ORDER BY name';
        return dbq($sql, ['mid' => $municipalityId])->fetchAll();
    }

    public static function create(array $d)
    {
        if (!array_key_exists('readiness_items_json', $d)) {
            $d['readiness_items_json'] = null;
        }
        dbq(
            'INSERT INTO volunteer_teams
             (municipality_id, name, type, contact_person, email, phone, address,
              telegram_chat_id, has_vehicle, has_medical_equipment, default_people_capacity, readiness_items_json, notes, status)
             VALUES (:municipality_id, :name, :type, :contact_person, :email, :phone, :address,
              :telegram_chat_id, :has_vehicle, :has_medical_equipment, :default_people_capacity, :readiness_items_json, :notes, :status)',
            $d
        );
        return (int) db()->lastInsertId();
    }

    public static function update($id, array $d)
    {
        $d['id'] = $id;
        dbq(
            'UPDATE volunteer_teams SET name = :name, type = :type, contact_person = :contact_person,
             email = :email, phone = :phone, address = :address, has_vehicle = :has_vehicle,
             has_medical_equipment = :has_medical_equipment, default_people_capacity = :default_people_capacity,
             readiness_items_json = :readiness_items_json, telegram_chat_id = :telegram_chat_id, notes = :notes, status = :status
             WHERE id = :id',
            $d
        );
    }

    public static function updateReadiness(int $id, array $data): void
    {
        dbq(
            'UPDATE volunteer_teams
             SET has_vehicle = :has_vehicle,
                 has_medical_equipment = :has_medical_equipment,
                 default_people_capacity = :default_people_capacity,
                 readiness_items_json = :readiness_items_json
             WHERE id = :id',
            [
                'has_vehicle'             => (int) ($data['has_vehicle'] ?? 0),
                'has_medical_equipment'   => (int) ($data['has_medical_equipment'] ?? 0),
                'default_people_capacity' => !empty($data['default_people_capacity']) ? (int) $data['default_people_capacity'] : null,
                'readiness_items_json'    => $data['readiness_items_json'] ?? null,
                'id'                      => $id,
            ]
        );
    }

    public static function readinessItems(array $team): array
    {
        $decoded = json_decode((string) ($team['readiness_items_json'] ?? ''), true);
        return is_array($decoded) ? array_values(array_filter(array_map('trim', $decoded), fn($item) => $item !== '')) : [];
    }

    public static function readinessOptionsForMunicipality(int $municipalityId): array
    {
        $type = authority_context($municipalityId)['authority_type'] ?? 'municipality';
        try {
            $rows = dbq(
                'SELECT requested_items_json, capabilities_json
                 FROM event_playbooks
                 WHERE authority_type = :type
                 ORDER BY title ASC',
                ['type' => normalize_authority_type($type)]
            )->fetchAll();
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            foreach (['requested_items_json', 'capabilities_json'] as $key) {
                $decoded = json_decode((string) ($row[$key] ?? ''), true);
                if (!is_array($decoded)) {
                    continue;
                }
                foreach ($decoded as $item) {
                    $item = preg_replace('/\s+/', ' ', trim((string) $item));
                    if ($item === '') {
                        continue;
                    }
                    $lookup = mb_strtolower($item, 'UTF-8');
                    if (!isset($seen[$lookup])) {
                        $seen[$lookup] = true;
                        $out[] = $item;
                    }
                }
            }
        }
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    public static function toggleStatus($id)
    {
        dbq(
            "UPDATE volunteer_teams SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id",
            ['id' => $id]
        );
    }
}
