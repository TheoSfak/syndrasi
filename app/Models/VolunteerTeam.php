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
        dbq(
            'INSERT INTO volunteer_teams
             (municipality_id, name, type, contact_person, email, phone, address,
              telegram_chat_id, has_vehicle, has_medical_equipment, default_people_capacity, notes, status)
             VALUES (:municipality_id, :name, :type, :contact_person, :email, :phone, :address,
              :telegram_chat_id, :has_vehicle, :has_medical_equipment, :default_people_capacity, :notes, :status)',
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
             telegram_chat_id = :telegram_chat_id, notes = :notes, status = :status
             WHERE id = :id',
            $d
        );
    }

    public static function toggleStatus($id)
    {
        dbq(
            "UPDATE volunteer_teams SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id",
            ['id' => $id]
        );
    }
}
