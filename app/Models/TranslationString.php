<?php
/**
 * SynDrasi - Translation catalog search/edit (backs the Languages settings
 * tab). Rows are translation_keys joined against translation_values for a
 * chosen reference language and target language.
 */
class TranslationString
{
    private const PAGE_SIZE = 50;

    /**
     * @param array{q?:string,status?:string,group?:string,refLang?:string,targetLang?:string} $filters
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public static function search(array $filters, int $page = 1): array
    {
        $refLang    = $filters['refLang'] ?? 'el';
        $targetLang = $filters['targetLang'] ?? 'en';
        $q          = trim((string) ($filters['q'] ?? ''));
        $status     = $filters['status'] ?? 'all';
        $group      = trim((string) ($filters['group'] ?? ''));
        $page       = max(1, $page);

        $where = [];
        $params = ['refLang' => $refLang, 'targetLang' => $targetLang];

        if ($q !== '') {
            // PDO with ATTR_EMULATE_PREPARES=false does not support reusing the
            // same named placeholder more than once in a single prepared
            // statement (throws "Invalid parameter number") — bind the same
            // value under three distinct names instead.
            $where[] = '(refv.value LIKE :q1 OR tgtv.value LIKE :q2 OR tk.str_key LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        if ($group !== '') {
            $where[] = 'tk.str_group = :group';
            $params['group'] = $group;
        }
        if ($status === 'missing') {
            $where[] = "(tgtv.value IS NULL OR tgtv.value = '')";
        } elseif ($status === 'translated') {
            $where[] = "(tgtv.value IS NOT NULL AND tgtv.value <> '')";
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $joinSql = 'FROM translation_keys tk
            LEFT JOIN translation_values refv ON refv.key_id = tk.id AND refv.language_code = :refLang
            LEFT JOIN translation_values tgtv ON tgtv.key_id = tk.id AND tgtv.language_code = :targetLang';

        $total = (int) dbq("SELECT COUNT(*) $joinSql $whereSql", $params)->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page  = min($page, $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $rows = dbq(
            "SELECT tk.id AS key_id, tk.str_key, tk.str_group,
                    refv.value AS ref_value, tgtv.value AS target_value
             $joinSql $whereSql
             ORDER BY tk.str_group, tk.str_key
             LIMIT " . self::PAGE_SIZE . " OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Bulk upsert values for one language.
     * @param array<int, array{key_id:int, value:string}> $rows
     */
    public static function saveMany(array $rows, string $languageCode): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                dbq(
                    'INSERT INTO translation_values (key_id, language_code, value)
                     VALUES (:kid, :lang, :val)
                     ON DUPLICATE KEY UPDATE value = :val2',
                    ['kid' => (int) $row['key_id'], 'lang' => $languageCode, 'val' => $row['value'], 'val2' => $row['value']]
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
