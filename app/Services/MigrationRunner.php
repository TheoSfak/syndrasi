<?php
/**
 * SynDrasi - Database migration runner.
 *
 * Tracks applied migrations in `schema_migrations` and applies any *.sql files
 * in /database/migrations that haven't run yet — so an update can migrate the
 * database fully automatically.
 *
 * On an existing install the current files are baselined as "already applied"
 * (so we never re-run migrations the live DB already has); only NEW files that
 * arrive with a future update will execute.
 */
class MigrationRunner
{
    private static function dir(): string
    {
        return BASE_PATH . '/database/migrations';
    }

    /** Create the tracking table if missing. */
    public static function ensureTable(): void
    {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
               filename   VARCHAR(255) NOT NULL PRIMARY KEY,
               applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Make sure tracking is initialised. If the table is empty but this looks
     * like an existing install (core tables already present), baseline the
     * current migration files as applied rather than re-running them.
     */
    public static function ensureInitialised(): void
    {
        self::ensureTable();
        if (self::appliedCount() > 0) {
            return;
        }
        if (self::looksLikeExistingInstall()) {
            self::baselineExisting();
        }
    }

    private static function looksLikeExistingInstall(): bool
    {
        try {
            // If the core `users` table exists, the schema is already in place.
            db()->query('SELECT 1 FROM users LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** All migration filenames on disk, sorted. */
    public static function allFiles(): array
    {
        $files = glob(self::dir() . '/*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names, SORT_STRING);
        return $names;
    }

    public static function appliedFiles(): array
    {
        return dbq('SELECT filename FROM schema_migrations ORDER BY filename')
            ->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private static function appliedCount(): int
    {
        return (int) dbq('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    }

    /** Files present on disk but not yet recorded as applied. */
    public static function pendingFiles(): array
    {
        $applied = array_flip(self::appliedFiles());
        return array_values(array_filter(self::allFiles(), fn($f) => !isset($applied[$f])));
    }

    /** Record every current file as applied WITHOUT running it (baseline). */
    public static function baselineExisting(): int
    {
        $n = 0;
        foreach (self::allFiles() as $file) {
            if (self::record($file)) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Run all pending migrations in order. Returns ['applied'=>[], 'error'=>null|string].
     * Stops at the first failing file (and does not record it).
     */
    public static function runPending(): array
    {
        self::ensureTable();
        $done = [];
        foreach (self::pendingFiles() as $file) {
            try {
                self::runFile($file);
                self::record($file);
                $done[] = $file;
            } catch (Throwable $e) {
                self::log("FAILED {$file}: " . $e->getMessage());
                return ['applied' => $done, 'error' => $file . ' — ' . $e->getMessage()];
            }
        }
        if ($done) {
            self::log('Applied: ' . implode(', ', $done));
        }
        return ['applied' => $done, 'error' => null];
    }

    private static function runFile(string $file): void
    {
        $path = self::dir() . '/' . $file;
        $sql  = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read ' . $file);
        }
        foreach (self::splitStatements($sql) as $stmt) {
            db()->exec($stmt);
        }
    }

    private static function record(string $file): bool
    {
        return dbq(
            'INSERT IGNORE INTO schema_migrations (filename) VALUES (:f)',
            ['f' => $file]
        )->rowCount() > 0;
    }

    /**
     * Split a .sql file into individual statements.
     * Drops full-line `--` comments and `USE ...;` lines, then splits on `;`.
     * (SynDrasi migrations never contain `;` inside string literals.)
     */
    private static function splitStatements(string $sql): array
    {
        $kept = [];
        foreach (preg_split('/\r\n|\r|\n/', $sql) as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            if (preg_match('/^USE\s+/i', $trim)) {
                continue;
            }
            $kept[] = $line;
        }
        $statements = [];
        foreach (explode(';', implode("\n", $kept)) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $statements[] = $part;
            }
        }
        return $statements;
    }

    private static function log(string $msg): void
    {
        @file_put_contents(
            BASE_PATH . '/storage/logs/update.log',
            '[' . date('Y-m-d H:i:s') . '] [migrate] ' . $msg . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
