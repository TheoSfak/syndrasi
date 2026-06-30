<?php
/**
 * SynDrasi - Municipality model.
 */
class Municipality
{
    public static function find($id)
    {
        return dbq('SELECT * FROM municipalities WHERE id = :id LIMIT 1', ['id' => $id])->fetch() ?: null;
    }

    public static function all()
    {
        return dbq('SELECT * FROM municipalities ORDER BY name')->fetchAll();
    }

    public static function create(array $d)
    {
        $d['authority_type'] = normalize_authority_type($d['authority_type'] ?? 'municipality');
        dbq(
            'INSERT INTO municipalities (name, authority_type, official_name, short_name, city, address, email, phone, status)
             VALUES (:name, :authority_type, :official_name, :short_name, :city, :address, :email, :phone, :status)',
            $d
        );
        return (int) db()->lastInsertId();
    }

    public static function update($id, array $d)
    {
        $d['id'] = $id;
        $d['authority_type'] = normalize_authority_type($d['authority_type'] ?? 'municipality');
        dbq(
            'UPDATE municipalities SET name = :name, authority_type = :authority_type,
             official_name = :official_name, short_name = :short_name, city = :city, address = :address,
             email = :email, phone = :phone, status = :status WHERE id = :id',
            $d
        );
    }
}
