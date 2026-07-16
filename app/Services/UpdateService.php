<?php
/**
 * SynDrasi - Self-update from GitHub Releases.
 *
 * Flow (admin clicks "Apply update"):
 *   1. pre-flight capability checks
 *   2. download the release source ZIP (zipball) to storage/updates
 *   3. back up the current code to storage/backups
 *   4. extract and copy files over the app — preserving config/ and storage/
 *   5. run any pending DB migrations automatically
 *
 * Repo owner/name (and an optional token for private repos) live in app_settings.
 */
class UpdateService
{
    private const EXCLUDE = ['config', 'storage', 'vendor', '.git', '.github'];

    /* ── Version / config ──────────────────────────────────────────────────── */

    public static function currentVersion(): string
    {
        $f = BASE_PATH . '/VERSION';
        if (is_file($f)) {
            $v = trim((string) file_get_contents($f));
            if ($v !== '') {
                return $v;
            }
        }
        return '0.0.0';
    }

    public static function config(): array
    {
        $cfg = config('update');
        return [
            'owner' => trim((string) ($cfg['owner'] ?? '')),
            'repo'  => trim((string) ($cfg['repo'] ?? '')),
            'token' => trim((string) ($cfg['token'] ?? '')),
        ];
    }

    /** Create a backup of the current code without applying an update. */
    public static function backupNow(): array
    {
        $problems = self::preflight();
        if ($problems) {
            return ['ok' => false, 'error' => implode(' ', $problems)];
        }
        $name = 'manual-backup_' . date('Ymd_His') . '.zip';
        if (!self::backup(BASE_PATH . '/storage/backups/' . $name)) {
            return ['ok' => false, 'error' => 'Αποτυχία δημιουργίας αντιγράφου ασφαλείας.'];
        }
        self::log('Manual backup -> ' . $name);
        return ['ok' => true, 'name' => $name];
    }

    /** List existing backups (newest first). */
    public static function listBackups(): array
    {
        $out = [];
        foreach (glob(BASE_PATH . '/storage/backups/*.zip') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => filesize($f), 'mtime' => filemtime($f)];
        }
        usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $out;
    }

    /** Validate a backup filename and return its full path, or null. */
    public static function backupFile(string $name): ?string
    {
        $name = basename($name);
        if (!preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name)) {
            return null;
        }
        $path = BASE_PATH . '/storage/backups/' . $name;
        return is_file($path) ? $path : null;
    }

    /**
     * Restore a backup: snapshot the current state first, then extract the
     * backup over the app (config/ included; storage/ untouched — backups never
     * contain it). Reverts changed files without deleting newer ones.
     */
    public static function restoreBackup(string $name): array
    {
        $path = self::backupFile($name);
        if ($path === null) {
            return ['ok' => false, 'error' => 'Μη έγκυρο αρχείο backup.'];
        }
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => 'Λείπει η επέκταση PHP "zip".'];
        }
        // Safety: back up the current state before overwriting it.
        self::backupNow();

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['ok' => false, 'error' => 'Αδυναμία ανοίγματος του backup.'];
        }
        $extract = self::extractZipTo($zip, BASE_PATH);
        $zip->close();
        if (!$extract['ok']) {
            return ['ok' => false, 'error' => $extract['error']];
        }
        self::log('Restored backup ' . $name);
        return ['ok' => true, 'name' => $name];
    }

    /* ── Check latest release ──────────────────────────────────────────────── */

    public static function checkLatest(): array
    {
        $cfg = self::config();
        if ($cfg['owner'] === '' || $cfg['repo'] === '') {
            return ['ok' => false, 'error' => 'Το αποθετήριο GitHub δεν έχει ρυθμιστεί στο config/update.php.'];
        }

        $base = "https://api.github.com/repos/{$cfg['owner']}/{$cfg['repo']}";

        // Prefer a published Release (carries notes + date)…
        $resp = self::httpGet($base . '/releases/latest', $cfg['token']);
        $data = $resp['ok'] ? json_decode($resp['body'], true) : null;
        if (is_array($data) && !empty($data['tag_name'])) {
            $latest = $data['tag_name'];
            return [
                'ok'        => true,
                'current'   => self::currentVersion(),
                'latest'    => $latest,
                'newer'     => self::isNewer(self::currentVersion(), $latest),
                'name'      => $data['name'] ?? $latest,
                'notes'     => $data['body'] ?? '',
                'published' => isset($data['published_at']) ? substr($data['published_at'], 0, 10) : '',
                'zip_url'   => $data['zipball_url'] ?? '',
            ];
        }

        // …otherwise fall back to the newest tag (works without formal Releases).
        $resp = self::httpGet($base . '/tags?per_page=100', $cfg['token']);
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => 'GitHub: ' . $resp['error']];
        }
        $tags = json_decode($resp['body'], true);
        if (!is_array($tags) || empty($tags)) {
            return ['ok' => false, 'error' => 'Δεν βρέθηκαν εκδόσεις (releases ή tags) στο αποθετήριο.'];
        }
        $best = null;
        foreach ($tags as $t) {
            if (empty($t['name'])) {
                continue;
            }
            if ($best === null || self::isNewer($best['name'], $t['name'])) {
                $best = $t;
            }
        }
        if ($best === null) {
            return ['ok' => false, 'error' => 'Δεν βρέθηκε έγκυρη έκδοση στα tags.'];
        }
        $latest = $best['name'];
        return [
            'ok'        => true,
            'current'   => self::currentVersion(),
            'latest'    => $latest,
            'newer'     => self::isNewer(self::currentVersion(), $latest),
            'name'      => $latest,
            'notes'     => '',
            'published' => '',
            'zip_url'   => $best['zipball_url'] ?? '',
        ];
    }

    /** Loose "is B newer than A" — normalises a leading v and -beta suffix. */
    public static function isNewer(string $current, string $latest): bool
    {
        $norm = fn($v) => preg_replace('/^v/i', '', trim($v));
        return version_compare($norm($current), $norm($latest), '<');
    }

    /* ── Pre-flight ────────────────────────────────────────────────────────── */

    public static function preflight(): array
    {
        $problems = [];
        if (!class_exists('ZipArchive')) {
            $problems[] = 'Λείπει η επέκταση PHP "zip" (ZipArchive).';
        }
        if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
            $problems[] = 'Απαιτείται cURL ή allow_url_fopen για λήψη από το GitHub.';
        }
        if (!is_writable(BASE_PATH)) {
            $problems[] = 'Ο φάκελος της εφαρμογής δεν είναι εγγράψιμος.';
        }
        foreach (['storage/updates', 'storage/backups'] as $d) {
            $full = BASE_PATH . '/' . $d;
            if (!is_dir($full) && !@mkdir($full, 0775, true)) {
                $problems[] = 'Δεν μπορεί να δημιουργηθεί ο φάκελος ' . $d . '.';
            }
        }
        return $problems;
    }

    /* ── Apply update ──────────────────────────────────────────────────────── */

    public static function applyUpdate(): array
    {
        $problems = self::preflight();
        if ($problems) {
            return ['ok' => false, 'error' => implode(' ', $problems)];
        }

        $info = self::checkLatest();
        if (!$info['ok']) {
            return ['ok' => false, 'error' => $info['error']];
        }
        if (empty($info['zip_url'])) {
            return ['ok' => false, 'error' => 'Η έκδοση δεν έχει διαθέσιμο ZIP.'];
        }

        $cfg     = self::config();
        $stamp   = date('Ymd_His');
        $zipPath = BASE_PATH . '/storage/updates/release_' . $stamp . '.zip';
        $exDir   = BASE_PATH . '/storage/updates/extract_' . $stamp;

        // 1. Download
        $dl = self::httpGet($info['zip_url'], $cfg['token'], true);
        if (!$dl['ok']) {
            return ['ok' => false, 'error' => 'Λήψη απέτυχε: ' . $dl['error']];
        }
        if (@file_put_contents($zipPath, $dl['body']) === false) {
            return ['ok' => false, 'error' => 'Αποτυχία αποθήκευσης του ZIP.'];
        }
        self::log("Downloaded {$info['latest']} -> " . basename($zipPath));

        // 2. Back up current code
        $backup = BASE_PATH . '/storage/backups/pre-update_' . $stamp . '.zip';
        if (!self::backup($backup)) {
            return ['ok' => false, 'error' => 'Αποτυχία δημιουργίας αντιγράφου ασφαλείας — η ενημέρωση ακυρώθηκε.'];
        }
        self::log('Backup -> ' . basename($backup));

        // 3. Extract
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'Αδυναμία ανοίγματος του ληφθέντος ZIP.'];
        }
        @mkdir($exDir, 0775, true);
        $extract = self::extractZipTo($zip, $exDir);
        $zip->close();
        if (!$extract['ok']) {
            self::rrmdir($exDir);
            @unlink($zipPath);
            return ['ok' => false, 'error' => $extract['error']];
        }

        $root = self::singleSubdir($exDir);
        if ($root === null) {
            return ['ok' => false, 'error' => 'Μη αναμενόμενη δομή ZIP.'];
        }

        // 4. Copy files over the app (preserving config/ and storage/)
        $lockFile = BASE_PATH . '/storage/maintenance.lock';
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
        register_shutdown_function(function () use ($lockFile) { @unlink($lockFile); });
        $copyResult = self::copyTree($root, BASE_PATH);
        $copied = $copyResult['count'];
        self::log("Copied {$copied} files from " . basename($root));
        if ($copyResult['failed']) {
            self::log('COPY FAILURES (' . count($copyResult['failed']) . '): ' . implode(', ', $copyResult['failed']));
        }

        // Deployed files are on disk now, but OPcache may still be serving
        // pre-update bytecode for already-hot files (e.g. functions.php,
        // required on every request) until its next per-file revalidation —
        // which can lag past the health-check probe fired a moment later.
        // Drop the whole cache now so every request after this point,
        // including the probe below, compiles the files just written.
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        // 5. Run pending migrations
        $mig = MigrationRunner::runPending();

        // Cleanup temp artefacts (keep the backup).
        self::rrmdir($exDir);
        @unlink($zipPath);

        // 6. Post-update health check. The maintenance lock must come off
        //    first (it 503s every request, including our own probe).
        @unlink($lockFile);
        $health = self::healthCheck();
        if ($health['status'] === 'dead') {
            self::log('HEALTH CHECK FAILED (' . $health['detail'] . ') — rolling back to ' . basename($backup));
            $restore = self::restoreBackup(basename($backup));
            return [
                'ok'          => false,
                'error'       => 'Η νέα έκδοση δεν αποκρίνεται (' . $health['detail'] . '). '
                               . ($restore['ok']
                                   ? 'Έγινε αυτόματη επαναφορά στο προηγούμενο backup. Προσοχή: τυχόν νέα migrations έχουν ήδη εφαρμοστεί στη βάση.'
                                   : 'Η αυτόματη επαναφορά ΑΠΕΤΥΧΕ (' . $restore['error'] . ') — απαιτείται χειροκίνητη επαναφορά του backup ' . basename($backup) . '.'),
                'rolled_back' => $restore['ok'],
                'backup'      => basename($backup),
            ];
        }

        $newVersion = self::currentVersion();
        self::log("Update complete. Version now {$newVersion}. Health: {$health['status']}. Migrations: "
            . (empty($mig['applied']) ? 'none' : implode(', ', $mig['applied']))
            . ($mig['error'] ? ' | ERROR: ' . $mig['error'] : ''));

        return [
            'ok'        => $mig['error'] === null,
            'error'     => $mig['error'],
            'version'   => $newVersion,
            'files'     => $copied,
            'migrated'  => $mig['applied'],
            'backup'    => basename($backup),
            'health'    => $health['status'],
        ];
    }

    /**
     * Probe the app's own public login page after an update.
     * Returns status 'healthy' (HTTP 2xx/3xx), 'dead' (5xx or connection
     * refused — the new code is broken, safe to auto-rollback), or
     * 'unknown' (timeout / no HTTP context — do NOT rollback on ambiguity,
     * a busy server must not trigger a false restore).
     */
    private static function healthCheck(): array
    {
        if (empty($_SERVER['HTTP_HOST']) || !function_exists('curl_init')) {
            return ['status' => 'unknown', 'detail' => 'no HTTP context for self-probe'];
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $probe  = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('/login');

        $ch = curl_init($probe);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false, // self-probe on same host; cert may be local
        ]);
        curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errNo = curl_errno($ch);
        curl_close($ch);

        if ($code >= 200 && $code < 400) {
            return ['status' => 'healthy', 'detail' => 'HTTP ' . $code];
        }
        if ($code >= 500 || $errNo === CURLE_COULDNT_CONNECT) {
            return ['status' => 'dead', 'detail' => $code ? 'HTTP ' . $code : 'connection refused'];
        }
        return ['status' => 'unknown', 'detail' => $code ? 'HTTP ' . $code : 'curl error ' . $errNo];
    }

    /* ── HTTP ──────────────────────────────────────────────────────────────── */

    private static function httpGet(string $url, string $token = '', bool $binary = false): array
    {
        // NOTE: GitHub's zipball/archive endpoint returns HTTP 415 if asked for
        // application/octet-stream (that media type is only for release *assets*).
        // Use */* for binary downloads so codeload serves the source archive.
        $headers = [
            'User-Agent: SynDrasi-Updater',
            'Accept: ' . ($binary ? '*/*' : 'application/vnd.github+json'),
        ];
        if ($token !== '') {
            $headers[] = 'Authorization: token ' . $token;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($body === false) {
                return ['ok' => false, 'error' => $err ?: 'cURL error'];
            }
            if ($code >= 400) {
                return ['ok' => false, 'error' => 'HTTP ' . $code];
            }
            return ['ok' => true, 'body' => $body];
        }

        // Fallback: stream wrapper
        $ctx  = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 120]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return ['ok' => false, 'error' => 'Δεν ήταν δυνατή η λήψη (allow_url_fopen).'];
        }
        return ['ok' => true, 'body' => $body];
    }

    /* ── Filesystem helpers ────────────────────────────────────────────────── */

    private static function backup(string $dest): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $base = realpath(BASE_PATH);
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            $rel  = ltrim(str_replace($base, '', $path), DIRECTORY_SEPARATOR . '/');
            $top  = explode('/', str_replace('\\', '/', $rel))[0];
            if (in_array($top, ['storage', 'vendor', '.git'], true)) {
                continue; // don't back up bulky/local dirs
            }
            if ($file->isDir()) {
                $zip->addEmptyDir($rel);
            } else {
                $zip->addFile($path, $rel);
            }
        }
        return $zip->close();
    }

    /**
     * Extract a ZIP without allowing absolute paths, parent traversal, stream
     * wrappers, Windows drive paths, or symlink entries.
     */
    private static function extractZipTo(ZipArchive $zip, string $dest): array
    {
        if (!is_dir($dest) && !@mkdir($dest, 0775, true)) {
            return ['ok' => false, 'error' => 'Δεν μπορεί να δημιουργηθεί ο φάκελος εξαγωγής.'];
        }

        $root = realpath($dest);
        if ($root === false) {
            return ['ok' => false, 'error' => 'Μη έγκυρος φάκελος εξαγωγής.'];
        }
        $root = rtrim(str_replace('\\', '/', $root), '/');

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $rel = self::safeZipEntryName((string) $name);
            if ($rel === null) {
                return ['ok' => false, 'error' => 'Το ZIP περιέχει μη ασφαλή διαδρομή αρχείου.'];
            }
            if (self::isZipSymlink($zip, $i)) {
                return ['ok' => false, 'error' => 'Το ZIP περιέχει μη ασφαλές symbolic link.'];
            }

            $isDir = str_ends_with((string) $name, '/');
            $target = $root . '/' . $rel;
            $targetDir = $isDir ? $target : dirname($target);
            if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
                return ['ok' => false, 'error' => 'Αποτυχία δημιουργίας φακέλου κατά την εξαγωγή.'];
            }

            $realDir = realpath($targetDir);
            if ($realDir === false || !self::isWithinPath($realDir, $root)) {
                return ['ok' => false, 'error' => 'Το ZIP προσπάθησε να γράψει εκτός εφαρμογής.'];
            }
            if ($isDir) {
                continue;
            }
            if (is_link($target)) {
                return ['ok' => false, 'error' => 'Το ZIP προσπάθησε να αντικαταστήσει symbolic link.'];
            }
            if (file_exists($target)) {
                $realTarget = realpath($target);
                if ($realTarget === false || !self::isWithinPath($realTarget, $root)) {
                    return ['ok' => false, 'error' => 'Το ZIP προσπάθησε να γράψει εκτός εφαρμογής.'];
                }
            }

            $in = $zip->getStream((string) $name);
            if ($in === false) {
                return ['ok' => false, 'error' => 'Αποτυχία ανάγνωσης αρχείου από το ZIP.'];
            }
            $out = @fopen($target, 'wb');
            if ($out === false) {
                fclose($in);
                return ['ok' => false, 'error' => 'Αποτυχία εγγραφής αρχείου κατά την εξαγωγή.'];
            }
            stream_copy_to_stream($in, $out);
            fclose($out);
            fclose($in);
        }

        return ['ok' => true];
    }

    private static function safeZipEntryName(string $name): ?string
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '://')) {
            return null;
        }
        $name = str_replace('\\', '/', $name);
        if ($name === '' || $name[0] === '/' || preg_match('/^[A-Za-z]:/', $name) || str_contains($name, ':')) {
            return null;
        }

        $parts = [];
        foreach (explode('/', $name) as $part) {
            if ($part === '') {
                continue;
            }
            if ($part === '.' || $part === '..') {
                return null;
            }
            $parts[] = $part;
        }
        return $parts ? implode('/', $parts) : null;
    }

    private static function isWithinPath(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return $path === $root || str_starts_with($path, $root . '/');
    }

    private static function isZipSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attr = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return false;
        }
        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return false;
        }
        return (($attr >> 16) & 0170000) === 0120000;
    }

    /**
     * Copy everything from $src into $dst, skipping protected top-level dirs.
     * Returns ['count' => copied file count, 'failed' => [relative paths that
     * did not copy]] — failures are never silently dropped, so a partial
     * deploy is always visible in the update log even if the health-check
     * probe happens not to catch it.
     */
    private static function copyTree(string $src, string $dst): array
    {
        $count = 0;
        $failed = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $rel = ltrim(str_replace($src, '', $file->getPathname()), DIRECTORY_SEPARATOR . '/');
            $relUnix = str_replace('\\', '/', $rel);
            $top = explode('/', $relUnix)[0];
            if (in_array($top, self::EXCLUDE, true)) {
                continue;
            }
            $target = $dst . '/' . $rel;
            if ($file->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0775, true);
                }
            } else {
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                if (@copy($file->getPathname(), $target)) {
                    $count++;
                } else {
                    $failed[] = $relUnix;
                }
            }
        }
        return ['count' => $count, 'failed' => $failed];
    }

    private static function singleSubdir(string $dir): ?string
    {
        $entries = array_values(array_filter(glob($dir . '/*') ?: [], 'is_dir'));
        return count($entries) === 1 ? $entries[0] : null;
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /* ── Settings storage ──────────────────────────────────────────────────── */

    private static function setting(string $key): string
    {
        $v = dbq('SELECT setting_value FROM app_settings WHERE setting_key = :k LIMIT 1', ['k' => $key])->fetchColumn();
        return $v !== false ? (string) $v : '';
    }

    private static function putSetting(string $key, string $value): void
    {
        dbq(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2',
            ['k' => $key, 'v' => $value, 'v2' => $value]
        );
    }

    private static function log(string $msg): void
    {
        @file_put_contents(
            BASE_PATH . '/storage/logs/update.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
