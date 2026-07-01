<?php
/**
 * SynDrasi - Event model.
 */
class Event
{
    /** All valid values for events.status. */
    public const VALID_STATUSES = [
        'draft', 'open', 'review', 'confirmed', 'active', 'closed', 'completed', 'cancelled',
    ];

    /**
     * Allowed forward transitions per status.
     * Used by controllers to validate state changes before committing them.
     */
    private const TRANSITIONS = [
        'draft'     => ['open', 'cancelled'],
        'open'      => ['review', 'confirmed', 'active', 'closed', 'completed', 'cancelled'],
        'review'    => ['confirmed', 'active', 'closed', 'completed', 'cancelled'],
        'confirmed' => ['active', 'closed', 'completed', 'cancelled'],
        'active'    => ['closed', 'completed'],
        'closed'    => ['completed', 'cancelled'],
        'completed' => ['cancelled'],
        'cancelled' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function find($id)
    {
        return dbq(
            'SELECT e.*, c.name AS category_name
             FROM events e LEFT JOIN event_categories c ON c.id = e.category_id
             WHERE e.id = :id LIMIT 1',
            ['id' => $id]
        )->fetch() ?: null;
    }

    /** Find an event and enforce municipality isolation. */
    public static function findForCurrent($id)
    {
        $event = self::find($id);
        if (!$event) {
            $terms = authority_context(current_municipality_id());
            $eventSingularLc = mb_strtolower($terms['event_singular'] ?? 'Δράση', 'UTF-8');
            abort(404, 'Η ' . $eventSingularLc . ' δεν βρέθηκε.');
        }
        requireMunicipalityAccess($event['municipality_id']);
        return $event;
    }

    public static function forMunicipality($municipalityId, array $filters = [])
    {
        $sql = 'SELECT e.*, c.name AS category_name,
                COALESCE(ac.applications_count, 0) AS applications_count,
                COALESCE(ac.pending_count, 0) AS pending_count
                FROM events e
                LEFT JOIN event_categories c ON c.id = e.category_id
                LEFT JOIN (
                    SELECT event_id,
                           COUNT(*) AS applications_count,
                           SUM(status = \'pending\') AS pending_count
                    FROM event_applications
                    GROUP BY event_id
                ) ac ON ac.event_id = e.id
                WHERE e.municipality_id = :mid';
        $params = ['mid' => $municipalityId];

        if (!empty($filters['status'])) {
            $sql .= ' AND e.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND e.category_id = :cat';
            $params['cat'] = $filters['category_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND e.start_datetime >= :dfrom';
            $params['dfrom'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND e.start_datetime <= :dto';
            $params['dto'] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND e.title LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        $sql .= ' ORDER BY e.start_datetime DESC';
        return dbq($sql, $params)->fetchAll();
    }

    /**
     * Fetch events for a municipality filtered to specific statuses.
     * Accepts optional text/date filters.
     */
    /** Counts of events per tab group (active / closed / completed) for a municipality. */
    public static function statusCounts($municipalityId): array
    {
        $row = dbq(
            "SELECT
                SUM(status IN ('open','review','confirmed','active')) AS active_cnt,
                SUM(status = 'closed')    AS closed_cnt,
                SUM(status = 'completed') AS completed_cnt
             FROM events WHERE municipality_id = :mid",
            ['mid' => $municipalityId]
        )->fetch() ?: [];
        return [
            'active'    => (int) ($row['active_cnt'] ?? 0),
            'closed'    => (int) ($row['closed_cnt'] ?? 0),
            'completed' => (int) ($row['completed_cnt'] ?? 0),
        ];
    }

    public static function forMunicipalityByStatuses($municipalityId, array $statuses, array $filters = [])
    {
        if (empty($statuses)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_merge([$municipalityId], $statuses);

        $extra = '';
        if (!empty($filters['q'])) {
            $extra .= ' AND e.title LIKE ?';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['from'])) {
            $extra .= ' AND e.start_datetime >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $extra .= ' AND e.start_datetime <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $stmt = db()->prepare(
            "SELECT e.*, c.name AS category_name,
                    COALESCE(ac.applications_count, 0) AS applications_count,
                    COALESCE(ac.pending_count, 0) AS pending_count
             FROM events e
             LEFT JOIN event_categories c ON c.id = e.category_id
             LEFT JOIN (
                 SELECT event_id,
                        COUNT(*) AS applications_count,
                        SUM(status = 'pending') AS pending_count
                 FROM event_applications
                 GROUP BY event_id
             ) ac ON ac.event_id = e.id
             WHERE e.municipality_id = ? AND e.status IN ($placeholders)
             $extra
             ORDER BY e.start_datetime DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Events visible to a team (open/confirmed/active in the team municipality). */
    public static function availableForTeam($municipalityId, $teamId)
    {
        return dbq(
            "SELECT e.*, c.name AS category_name, ea.id AS application_id, ea.status AS application_status,
                    ea.offered_people, ea.approved_people
             FROM events e
             LEFT JOIN event_categories c ON c.id = e.category_id
             LEFT JOIN event_applications ea ON ea.event_id = e.id AND ea.team_id = :tid
             WHERE e.municipality_id = :mid
               AND e.status IN ('open','review','confirmed','active')
             ORDER BY e.start_datetime ASC",
            ['mid' => $municipalityId, 'tid' => $teamId]
        )->fetchAll();
    }

    /** Closed events where the team had an approved application. */
    public static function closedForTeam($municipalityId, $teamId)
    {
        return dbq(
            "SELECT e.*, c.name AS category_name, ea.id AS application_id,
                    ea.approved_people, ea.actual_people
             FROM events e
             LEFT JOIN event_categories c ON c.id = e.category_id
             JOIN event_applications ea ON ea.event_id = e.id AND ea.team_id = :tid AND ea.status = 'approved'
             WHERE e.municipality_id = :mid AND e.status IN ('closed','completed')
             ORDER BY e.end_datetime DESC",
            ['mid' => $municipalityId, 'tid' => $teamId]
        )->fetchAll();
    }

    // --------------------------------------------------------- Static helpers

    public static function categories($municipalityId = null): array
    {
        if ($municipalityId) {
            try {
                $type = authority_context((int) $municipalityId)['authority_type'] ?? 'municipality';
                return dbq(
                    'SELECT id, name FROM event_categories
                     WHERE authority_type = :type
                     ORDER BY name ASC',
                    ['type' => normalize_authority_type($type)]
                )->fetchAll();
            } catch (Throwable $e) {
                // Older DB before migration 032: fall through to legacy list.
            }
        }
        return dbq('SELECT id, name FROM event_categories ORDER BY name ASC')->fetchAll();
    }

    public static function create(array $data): int
    {
        // Auto-generate a unique public share token if not provided
        if (empty($data['public_token'])) {
            $data['public_token'] = bin2hex(random_bytes(16));
        }
        if (!array_key_exists('requested_items_json', $data)) {
            $data['requested_items_json'] = null;
        }
        dbq(
            "INSERT INTO events
             (municipality_id, category_id, title, description, location_name, address,
              latitude, longitude, start_datetime, end_datetime,
              requested_people, requested_vehicle, requested_medical_equipment,
              requested_items_json, instructions, status, created_by, public_token)
             VALUES
             (:municipality_id, :category_id, :title, :description, :location_name, :address,
              :latitude, :longitude, :start_datetime, :end_datetime,
              :requested_people, :requested_vehicle, :requested_medical_equipment,
              :requested_items_json, :instructions, :status, :created_by, :public_token)",
            $data
        );
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        dbq(
            "UPDATE events
             SET category_id = :category_id,
                 title = :title,
                 description = :description,
                 location_name = :location_name,
                 address = :address,
                 latitude = :latitude,
                 longitude = :longitude,
                 start_datetime = :start_datetime,
                 end_datetime = :end_datetime,
                 requested_people = :requested_people,
                 requested_vehicle = :requested_vehicle,
                 requested_medical_equipment = :requested_medical_equipment,
                 requested_items_json = :requested_items_json,
                 instructions = :instructions
             WHERE id = :id",
            array_merge($data, ['id' => $id])
        );
    }

    public static function markPublished(int $id): void
    {
        dbq(
            "UPDATE events SET status = 'open', published_at = NOW() WHERE id = :id",
            ['id' => $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid event status: '{$status}'");
        }
        dbq(
            "UPDATE events SET status = :status WHERE id = :id",
            ['status' => $status, 'id' => $id]
        );
    }
}
