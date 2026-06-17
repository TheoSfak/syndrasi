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
        $ok = $zip->extractTo(BASE_PATH);
        $zip->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Η εξαγωγή του backup απέτυχε.'];
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
        $zip->extractTo($exDir);
        $zip->close();

        $root = self::singleSubdir($exDir);
        if ($root === null) {
            return ['ok' => false, 'error' => 'Μη αναμενόμενη δομή ZIP.'];
        }

        // 4. Copy files over the app (preserving config/ and storage/)
        $copied = self::copyTree($root, BASE_PATH);
        self::log("Copied {$copied} files from " . basename($root));

        // 5. Run pending migrations
        $mig = MigrationRunner::runPending();

        // Cleanup temp artefacts (keep the backup).
        self::rrmdir($exDir);
        @unlink($zipPath);

        $newVersion = self::currentVersion();
        self::log("Update complete. Version now {$newVersion}. Migrations: "
            . (empty($mig['applied']) ? 'none' : implode(', ', $mig['applied']))
            . ($mig['error'] ? ' | ERROR: ' . $mig['error'] : ''));

        return [
            'ok'        => $mig['error'] === null,
            'error'     => $mig['error'],
            'version'   => $newVersion,
            'files'     => $copied,
            'migrated'  => $mig['applied'],
            'backup'    => basename($backup),
        ];
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

    /** Copy everything from $src into $dst, skipping protected top-level dirs. */
    private static function copyTree(string $src, string $dst): int
    {
        $count = 0;
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
                }
            }
        }
        return $count;
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

    private static function