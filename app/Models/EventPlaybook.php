<?php
/**
 * SynDrasi - Mission playbooks tied to event categories.
 */
class EventPlaybook
{
    public static function forCategory(?int $categoryId): ?array
    {
        if (!$categoryId) {
            return null;
        }

        try {
            $row = dbq(
                'SELECT p.*, c.name AS category_name
                 FROM event_playbooks p
                 JOIN event_categories c ON c.id = p.category_id
                 WHERE p.category_id = :cid
                 LIMIT 1',
                ['cid' => $categoryId]
            )->fetch();
        } catch (Throwable $e) {
            return null;
        }

        return $row ? self::normalise($row) : null;
    }

    public static function forEvent(array $event): ?array
    {
        return self::forCategory(isset($event['category_id']) ? (int) $event['category_id'] : null);
    }

    public static function forMunicipality(int $municipalityId): array
    {
        $type = authority_context($municipalityId)['authority_type'] ?? 'municipality';

        try {
            $rows = dbq(
                'SELECT p.*, c.name AS category_name
                 FROM event_playbooks p
                 JOIN event_categories c ON c.id = p.category_id
                 WHERE p.authority_type = :type
                 ORDER BY c.name ASC',
                ['type' => normalize_authority_type($type)]
            )->fetchAll();
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $pb = self::normalise($row);
            $out[(int) $pb['category_id']] = $pb;
        }
        return $out;
    }

    private static function normalise(array $row): array
    {
        foreach (['capabilities_json', 'requested_items_json', 'checklist_json', 'messages_json', 'debrief_questions_json'] as $key) {
            $decoded = json_decode((string) ($row[$key] ?? ''), true);
            $row[str_replace('_json', '', $key)] = is_array($decoded) ? array_values($decoded) : [];
        }

        $row['id'] = (int) $row['id'];
        $row['category_id'] = (int) $row['category_id'];
        $row['default_people'] = (int) ($row['default_people'] ?? 0);
        $row['require_vehicle'] = (int) ($row['require_vehicle'] ?? 0);
        $row['require_medical'] = (int) ($row['require_medical'] ?? 0);

        if (!$row['requested_items'] && $row['capabilities']) {
            $row['requested_items'] = $row['capabilities'];
        }

        unset($row['capabilities_json'], $row['requested_items_json'], $row['checklist_json'], $row['messages_json'], $row['debrief_questions_json']);
        return $row;
    }
}
