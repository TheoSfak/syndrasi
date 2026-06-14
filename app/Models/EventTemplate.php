<?php
/**
 * SynDrasi - Event template model.
 * Reusable blueprint of an event's core fields + shift structure.
 * Shifts are stored as JSON with minute offsets relative to the event start.
 */
class EventTemplate
{
    /** All templates for a municipality (newest first). */
    public static function forMunicipality($municipalityId): array
    {
        return dbq(
            'SELECT * FROM event_templates WHERE municipality_id = :mid ORDER BY name ASC',
            ['mid' => $municipalityId]
        )->fetchAll();
    }

    /** Find one template (no tenancy check — caller must verify municipality). */
    public static function find($id): ?array
    {
        $row = dbq('SELECT * FROM event_templates WHERE id = :id LIMIT 1', ['id' => $id])->fetch();
        return $row ?: null;
    }

    /** Find one template scoped to a municipality. */
    public static function findForMunicipality($id, $municipalityId): ?array
    {
        $row = dbq(
            'SELECT * FROM event_templates WHERE id = :id AND municipality_id = :mid LIMIT 1',
            ['id' => $id, 'mid' => $municipalityId]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        dbq(
            'INSERT INTO event_templates
             (municipality_id, name, title, category_id, description, location_name, address,
              latitude, longitude, requested_people, requested_vehicle, requested_medical_equipment,
              instructions, shifts_json, created_by)
             VALUES
             (:municipality_id, :name, :title, :category_id, :description, :location_name, :address,
              :latitude, :longitude, :requested_people, :requested_vehicle, :requested_medical_equipment,
              :instructions, :shifts_json, :created_by)',
            $data
        );
        return (int) db()->lastInsertId();
    }

    public static function delete($id, $municipalityId): void
    {
        dbq(
            'DELETE FROM event_templates WHERE id = :id AND municipality_id = :mid',
            ['id' => $id, 'mid' => $municipalityId]
        );
    }
}
