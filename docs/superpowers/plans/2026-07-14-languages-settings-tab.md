# Languages Settings Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the translation-catalog foundation for multi-language support — an N-language schema, an admin UI to manage languages and translate extracted UI strings, and a per-user language preference — without yet rewiring the 73 views to render translated text.

**Architecture:** Three new tables (`languages`, `translation_keys`, `translation_values`) hold an admin-editable translation catalog seeded from a one-time extraction of the app's existing Greek UI text. A new `LanguageController` (mirroring the existing `MaintenanceController` split) backs a new "Γλώσσες" tab on the platform Settings page (`/admin/settings`, `AdminController@settings`, `Role::SUPER_ADMIN`). Independently, `users.language_code` plus a self-service picker on `/profile` lets every user (any role) pick their own language.

**Tech Stack:** Plain PHP (no framework), PDO/MySQL, PHPUnit, vanilla JS (ES5-style, matching existing view scripts), Bootstrap 5 (matching existing admin UI).

## Global Constraints

- Route role guards must exactly match the `requireRole([...])` call at the top of the controller action — enforced by `tests/Unit/RouteRoleConsistencyTest.php`. Every new `['roles' => [Role::SUPER_ADMIN]]` route must pair with a literal `requireRole([Role::SUPER_ADMIN]);` as the first line of its action.
- Every `CREATE TABLE` and `ALTER TABLE ... ADD COLUMN` in a new migration must also land in `database/schema.sql` — enforced by `tests/Unit/SchemaMigrationsDriftTest.php`.
- Migration `.sql` files may never contain a literal `;` character inside a string literal — `MigrationRunner::splitStatements()` naively splits on every `;` regardless of quoting.
- PDO connects with `PDO::ATTR_EMULATE_PREPARES => false` (native prepares). **Reusing the same named placeholder (e.g. `:q`) multiple times in one query throws `PDOException: Invalid parameter number`** — confirmed empirically against the local dev DB during Task 2. (This corrects an earlier, wrong assumption in this plan based on `AdminController.php:250` appearing to do this — that existing code has the same latent bug, unrelated to this feature; out of scope here.) Bind the same value under distinct placeholder names instead (`:q1`, `:q2`, ...). Also, `LIMIT`/`OFFSET` must be interpolated as `(int)`-cast values, never bound as params (existing convention in `Notification.php`, `TeamDebrief.php`; binding them as named params throws under native prepares).
- This codebase has no DB-backed PHPUnit `Unit` tests (`tests/bootstrap.php` never opens a DB connection) — all DB-touching behavior is verified through `tests/Integration/LocalHttpTest.php`, which self-skips cleanly when no local server/DB is reachable. Follow that convention: new DB-touching code gets a throwaway CLI smoke check during development (shown in each task) and permanent coverage added to `LocalHttpTest.php` in the final task, not new isolated Unit tests.
- No `declare(strict_types=1)` anywhere in this codebase — don't introduce it.
- Static-method model classes with no constructor, matching `MunicipalitySetting`/`User`/`Event` style — not instantiated classes.

---

### Task 1: Schema — `languages`, `translation_keys`, `translation_values`, `users.language_code`

**Files:**
- Create: `database/migrations/041_translation_catalog.sql`
- Modify: `database/schema.sql:12-13` (header comment), `database/schema.sql:96-111` (users table), and after `database/schema.sql:906` (new tables)
- Create: `app/Models/Language.php`

**Interfaces:**
- Produces: `Language::all(bool $activeOnly = false): array`, `Language::find(string $code): ?array`, `Language::source(): array`, `Language::isActiveCode(string $code): bool`, `Language::create(string $code, string $name): void`, `Language::setActive(string $code, bool $active): bool` (returns `false` without changing anything if asked to deactivate the source language).

- [ ] **Step 1: Write the migration**

Create `database/migrations/041_translation_catalog.sql`:

```sql
-- 041_translation_catalog.sql
-- Foundation for multi-language UI translation: a `languages` table plus a
-- normalized key/value translation catalog (translation_keys +
-- translation_values), so adding a language later is a data operation, not a
-- schema change. Also adds users.language_code so each user can pick their
-- own language independently (self-service; stored now, but the 73 views
-- don't render translated text yet — that's a separate follow-up project).

CREATE TABLE languages (
  code        VARCHAR(10) PRIMARY KEY,
  name        VARCHAR(64) NOT NULL,
  is_source   TINYINT(1) NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO languages (code, name, is_source, is_active, sort_order) VALUES
  ('el', 'Ελληνικά', 1, 1, 0),
  ('en', 'English',  0, 1, 1);

CREATE TABLE translation_keys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  str_key     VARCHAR(190) NOT NULL,
  str_group   VARCHAR(120) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_translation_keys_str_key (str_key),
  INDEX idx_translation_keys_group (str_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE translation_values (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  key_id         INT NOT NULL,
  language_code  VARCHAR(10) NOT NULL,
  value          TEXT NOT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_translation_values_key_lang (key_id, language_code),
  FOREIGN KEY (key_id) REFERENCES translation_keys(id) ON DELETE CASCADE,
  FOREIGN KEY (language_code) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  ADD COLUMN language_code VARCHAR(10) NULL DEFAULT NULL AFTER status,
  ADD FOREIGN KEY (language_code) REFERENCES languages(code);
```

Note the statement order: `languages` must be created before `translation_values` and before the `users` FK, since `MigrationRunner::runFile()` executes statements one at a time with no `FOREIGN_KEY_CHECKS=0` wrapper (unlike `schema.sql`).

- [ ] **Step 2: Update `database/schema.sql` to match**

In `database/schema.sql:96-111`, replace the `users` table definition:

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NULL,
  team_id INT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  phone VARCHAR(50) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','municipality_admin','team_admin','event_operator') NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  language_code VARCHAR(10) NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_municipality_id (municipality_id),
  INDEX idx_users_team_id (team_id),
  FOREIGN KEY (language_code) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

(`schema.sql` disables `FOREIGN_KEY_CHECKS` near the top of the file, so the forward reference to `languages` — created later in the file — is fine.)

In `database/schema.sql:900-906`, right after the `schema_migrations` table and before `SET FOREIGN_KEY_CHECKS = 1;`, insert:

```sql
-- ------------------------------------------------------------
-- Multi-language UI translation catalog (see migration 041).
CREATE TABLE languages (
  code        VARCHAR(10) PRIMARY KEY,
  name        VARCHAR(64) NOT NULL,
  is_source   TINYINT(1) NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE translation_keys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  str_key     VARCHAR(190) NOT NULL,
  str_group   VARCHAR(120) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_translation_keys_str_key (str_key),
  INDEX idx_translation_keys_group (str_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE translation_values (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  key_id         INT NOT NULL,
  language_code  VARCHAR(10) NOT NULL,
  value          TEXT NOT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_translation_values_key_lang (key_id, language_code),
  FOREIGN KEY (key_id) REFERENCES translation_keys(id) ON DELETE CASCADE,
  FOREIGN KEY (language_code) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Also add the seed insert next to the other seed data, right after the `SET FOREIGN_KEY_CHECKS = 1;` line (`database/schema.sql:908`):

```sql
-- ------------------------------------------------------------
-- Default languages (Greek is the extraction source)
INSERT INTO languages (code, name, is_source, is_active, sort_order) VALUES
  ('el', 'Ελληνικά', 1, 1, 0),
  ('en', 'English',  0, 1, 1);
```

Finally, in `database/schema.sql:12-13`, update the stale comment:

```
-- ✅ FRESH INSTALL — this file is a FULL, up-to-date dump of the schema
-- produced by all files in database/migrations/ (current through
-- migration 041). A brand new database created from this file alone already
```

- [ ] **Step 3: Run the existing drift/consistency tests**

```bash
vendor/bin/phpunit tests/Unit/SchemaMigrationsDriftTest.php tests/Unit/RouteRoleConsistencyTest.php
```

Expected: both pass (no routes changed yet in this task; the schema drift test now checks the new tables/column against `schema.sql` and must find no drift).

- [ ] **Step 4: Write `app/Models/Language.php`**

```php
<?php
/**
 * SynDrasi - Language catalog (used by the Languages settings tab and by
 * per-user language preference).
 */
class Language
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM languages';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, name';
        return dbq($sql)->fetchAll();
    }

    public static function find(string $code): ?array
    {
        $row = dbq('SELECT * FROM languages WHERE code = :c LIMIT 1', ['c' => $code])->fetch();
        return $row ?: null;
    }

    public static function source(): array
    {
        return dbq('SELECT * FROM languages WHERE is_source = 1 LIMIT 1')->fetch();
    }

    public static function isActiveCode(string $code): bool
    {
        return (bool) dbq(
            'SELECT 1 FROM languages WHERE code = :c AND is_active = 1 LIMIT 1',
            ['c' => $code]
        )->fetchColumn();
    }

    public static function create(string $code, string $name): void
    {
        $nextOrder = (int) dbq('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM languages')->fetchColumn();
        dbq(
            'INSERT INTO languages (code, name, is_source, is_active, sort_order) VALUES (:c, :n, 0, 1, :o)',
            ['c' => $code, 'n' => $name, 'o' => $nextOrder]
        );
    }

    /** Returns false (and changes nothing) if asked to deactivate the source language. */
    public static function setActive(string $code, bool $active): bool
    {
        if (!$active) {
            $lang = self::find($code);
            if ($lang && (bool) $lang['is_source']) {
                return false;
            }
        }
        dbq('UPDATE languages SET is_active = :a WHERE code = :c', ['a' => $active ? 1 : 0, 'c' => $code]);
        return true;
    }
}
```

- [ ] **Step 5: Smoke-check against a local dev DB**

Requires a local MySQL/XAMPP install with the app's DB already migrated through 040 (see `DEPLOY.md`). Apply the new migration and exercise the model:

```bash
mysql -u root -p syndrasi < database/migrations/041_translation_catalog.sql
php -r "
define('BASE_PATH', __DIR__);
require 'app/Helpers/functions.php';
require 'app/Models/Language.php';
\$all = Language::all();
assert(count(\$all) === 2);
assert(Language::source()['code'] === 'el');
assert(Language::isActiveCode('en') === true);
assert(Language::isActiveCode('xx') === false);
Language::create('de', 'Deutsch');
assert(Language::isActiveCode('de') === true);
assert(Language::setActive('el', false) === false, 'must refuse to deactivate source');
assert(Language::setActive('de', false) === true);
assert(Language::isActiveCode('de') === false);
echo \"OK\n\";
"
```

Expected output: `OK`. Then clean up the throwaway `de` row so it doesn't linger: `mysql -u root -p syndrasi -e "DELETE FROM languages WHERE code = 'de'"`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/041_translation_catalog.sql database/schema.sql app/Models/Language.php
git commit -m "Add languages/translation catalog schema and Language model"
```

---

### Task 2: Extraction script + seed migration + `TranslationString` model

**Files:**
- Create: `scripts/extract-translation-strings.php`
- Create: `database/migrations/042_translation_catalog_seed.sql` (generated by running the script — see Step 2)
- Create: `app/Models/TranslationString.php`

**Interfaces:**
- Consumes: `translation_keys`/`translation_values` tables from Task 1.
- Produces: `TranslationString::search(array $filters, int $page = 1): array` returning `['rows' => [...], 'total' => int, 'page' => int, 'pages' => int]` where each row is `['key_id' => int, 'str_key' => string, 'str_group' => string, 'ref_value' => ?string, 'target_value' => ?string]`. `TranslationString::saveMany(array $rows, string $languageCode): void` where `$rows` is a list of `['key_id' => int, 'value' => string]`.

- [ ] **Step 1: Write the extraction script**

Create `scripts/extract-translation-strings.php`:

```php
<?php
/**
 * One-time dev tool: scans views/ for hardcoded Greek UI text and (re)generates
 * database/migrations/042_translation_catalog_seed.sql, seeding translation_keys
 * + translation_values (language_code = 'el') with the extracted strings.
 *
 * Not part of the app runtime — run manually:
 *   php scripts/extract-translation-strings.php
 *
 * Known limitation: text split across nested inline tags (e.g.
 * "<p>Some <strong>bold</strong> text</p>") is extracted as separate
 * fragments ("Some ", " text") rather than one sentence. Acceptable for a
 * first pass — an admin can still translate each fragment.
 */

define('BASE_PATH', dirname(__DIR__));
$viewsDir = BASE_PATH . '/views';
$outFile  = BASE_PATH . '/database/migrations/042_translation_catalog_seed.sql';
$skipLog  = BASE_PATH . '/storage/logs/translation-extraction-skipped.log';

function sqlEscape(string $s): string
{
    return str_replace(["\\", "'"], ["\\\\", "''"], $s);
}

function extractOne(string $text, string $group, array &$seenInFile, int &$index, array &$statements, array &$skipped, int &$total): void
{
    $clean = trim(preg_replace('/\s+/', ' ', $text));
    if ($clean === '' || mb_strlen($clean) < 2) {
        return;
    }
    if (isset($seenInFile[$clean])) {
        return; // duplicate within this file — key already emitted
    }
    if (strpos($clean, ';') !== false) {
        // Migrations may never contain a literal ';' inside a string literal
        // (MigrationRunner splits statements on raw ';'). Skip and log instead.
        $skipped[] = "$group: contains ';' — $clean";
        return;
    }
    $index++;
    $key = $group . '.' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);
    $seenInFile[$clean] = $key;
    $total++;

    $escKey = sqlEscape($key);
    $escGroup = sqlEscape($group);
    $escText = sqlEscape($clean);

    $statements[] = "INSERT INTO translation_keys (str_key, str_group) VALUES ('$escKey', '$escGroup')";
    $statements[] = "INSERT INTO translation_values (key_id, language_code, value) "
        . "SELECT id, 'el', '$escText' FROM translation_keys WHERE str_key = '$escKey'";
}

$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);

$statements = [];
$skipped = [];
$total = 0;

foreach ($files as $path) {
    $relative = str_replace('\\', '/', substr($path, strlen($viewsDir) + 1));
    $group = preg_replace('/\.php$/', '', $relative);
    $raw = file_get_contents($path);

    // Blank out PHP blocks so PHP code is never mistaken for UI text.
    $withoutPhp = preg_replace_callback('/<\?php.*?\?>|<\?=.*?\?>/s', function ($m) {
        return str_repeat(' ', strlen($m[0]));
    }, $raw);

    $seenInFile = [];
    $index = 0;

    // Text nodes: content between > and < containing a Greek character.
    if (preg_match_all('/>([^<>]*[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}][^<>]*)</u', $withoutPhp, $m)) {
        foreach ($m[1] as $text) {
            extractOne($text, $group, $seenInFile, $index, $statements, $skipped, $total);
        }
    }

    // Attribute values: placeholder / title / alt / aria-label containing Greek.
    if (preg_match_all('/\b(?:placeholder|title|alt|aria-label)="([^"]*[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}][^"]*)"/u', $withoutPhp, $m)) {
        foreach ($m[1] as $text) {
            extractOne($text, $group, $seenInFile, $index, $statements, $skipped, $total);
        }
    }
}

$header = "-- 042_translation_catalog_seed.sql\n"
    . "-- Auto-generated by scripts/extract-translation-strings.php — DO NOT hand-edit.\n"
    . "-- Seeds translation_keys/translation_values with Greek UI text extracted from views/.\n"
    . "-- Regenerate by re-running the script (it always overwrites this file).\n\n";

file_put_contents($outFile, $header . implode(";\n", $statements) . ";\n");

if ($skipped) {
    file_put_contents($skipLog, implode("\n", $skipped) . "\n");
}

echo "Extracted $total strings from " . count($files) . " views.\n";
echo "Wrote: $outFile\n";
if ($skipped) {
    echo count($skipped) . " strings skipped (contain literal ';') — see $skipLog\n";
}
```

- [ ] **Step 2: Run the script to generate the seed migration**

```bash
php scripts/extract-translation-strings.php
```

Expected: prints a count in the low hundreds to low thousands (73 views), writes `database/migrations/042_translation_catalog_seed.sql`. Open the generated file and spot-check 5-10 lines for sane, correctly-escaped Greek text (no stray HTML fragments, no unescaped quotes). If the skip log reports any strings, open `storage/logs/translation-extraction-skipped.log` and confirm each skipped string is genuinely not worth extracting automatically (or is fine to leave for manual entry later via the "add" flow — there is none for individual strings in this slice, so just note them as a known gap).

- [ ] **Step 3: Write `app/Models/TranslationString.php`**

```php
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
```

- [ ] **Step 4: Smoke-check against local dev DB**

```bash
mysql -u root -p syndrasi < database/migrations/042_translation_catalog_seed.sql
php -r "
define('BASE_PATH', __DIR__);
require 'app/Helpers/functions.php';
require 'app/Models/TranslationString.php';
\$r = TranslationString::search(['status' => 'missing'], 1);
assert(\$r['total'] > 0, 'expected extracted strings to exist and be missing en translations');
\$firstKeyId = \$r['rows'][0]['key_id'];
TranslationString::saveMany([['key_id' => \$firstKeyId, 'value' => 'Test EN value']], 'en');
\$r2 = TranslationString::search(['q' => 'Test EN value', 'targetLang' => 'en'], 1);
assert(\$r2['total'] === 1);
assert(\$r2['rows'][0]['target_value'] === 'Test EN value');
echo \"OK\n\";
"
mysql -u root -p syndrasi -e "DELETE FROM translation_values WHERE value = 'Test EN value'"
```

Expected output: `OK`.

- [ ] **Step 5: Commit**

```bash
git add scripts/extract-translation-strings.php database/migrations/042_translation_catalog_seed.sql app/Models/TranslationString.php storage/logs/.gitkeep
git commit -m "Add UI-string extraction script, seed catalog, and TranslationString model"
```

(If `storage/logs/translation-extraction-skipped.log` was created, check whether `storage/logs/` is already gitignored — if so don't force-add the log file itself, only the generated seed migration and script.)

---

### Task 3: `LanguageController` + routes + wire into `AdminController::settings()`

**Files:**
- Create: `app/Controllers/LanguageController.php`
- Modify: `routes/web.php` (add 4 routes near the existing `/admin/settings*` block, `routes/web.php:292-303`)
- Modify: `app/Controllers/AdminController.php:472-482` (`settings()` — pass `languages` + initial `stringsPage` to the view)

**Interfaces:**
- Consumes: `Language::all()`, `Language::find()`, `Language::create()`, `Language::setActive()` (Task 1); `TranslationString::search()`, `TranslationString::saveMany()` (Task 2).
- Produces: routes `GET /admin/languages/search`, `POST /admin/languages/save`, `POST /admin/languages/add`, `POST /admin/languages/toggle`, all `requireRole([Role::SUPER_ADMIN])`. `AdminController::settings()` now also passes `'languages' => array, 'stringsPage' => array` (same shape as `TranslationString::search()`'s return) to the `settings/index` view.

- [ ] **Step 1: Write `app/Controllers/LanguageController.php`**

```php
<?php
/**
 * SynDrasi - Language & translation catalog management (super admin).
 * Backs the "Γλώσσες" tab in Platform Settings.
 */
class LanguageController
{
    public function search()
    {
        requireRole([Role::SUPER_ADMIN]);
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $status = in_array($_GET['status'] ?? '', ['missing', 'translated'], true) ? $_GET['status'] : 'all';
        $refLang = self::validLangOr((string) ($_GET['refLang'] ?? ''), 'el');
        $targetLang = self::validLangOr((string) ($_GET['targetLang'] ?? ''), 'en');

        $result = TranslationString::search([
            'q'          => (string) ($_GET['q'] ?? ''),
            'status'     => $status,
            'group'      => (string) ($_GET['group'] ?? ''),
            'refLang'    => $refLang,
            'targetLang' => $targetLang,
        ], $page);

        json_out(['success' => true] + $result);
    }

    public function save()
    {
        requireRole([Role::SUPER_ADMIN]);
        $body = json_input();
        $languageCode = self::validLangOr((string) ($body['languageCode'] ?? ''), '');
        $rows = is_array($body['rows'] ?? null) ? $body['rows'] : [];

        if ($languageCode === '' || !$rows) {
            flash_set('danger', 'Δεν επιλέχθηκαν αλλαγές για αποθήκευση.');
            redirect('/admin/settings#languages');
        }

        $clean = [];
        foreach ($rows as $row) {
            if (!isset($row['key_id'], $row['value'])) {
                continue;
            }
            $clean[] = ['key_id' => (int) $row['key_id'], 'value' => (string) $row['value']];
        }

        TranslationString::saveMany($clean, $languageCode);
        audit('translation_strings_updated', 'languages', null, ['language' => $languageCode, 'count' => count($clean)]);
        flash_set('success', 'Αποθηκεύτηκαν ' . count($clean) . ' μεταφράσεις.');
        redirect('/admin/settings#languages');
    }

    public function add()
    {
        requireRole([Role::SUPER_ADMIN]);
        $code = strtolower(trim(post_str('code')));
        $name = trim(post_str('name'));

        if (!preg_match('/^[a-z]{2,10}$/', $code) || $name === '') {
            flash_set('danger', 'Μη έγκυρος κωδικός ή όνομα γλώσσας.');
            redirect('/admin/settings#languages');
        }
        if (Language::find($code)) {
            flash_set('danger', 'Η γλώσσα υπάρχει ήδη.');
            redirect('/admin/settings#languages');
        }

        Language::create($code, $name);
        audit('language_added', 'languages', null, ['code' => $code]);
        flash_set('success', 'Η γλώσσα «' . $name . '» προστέθηκε.');
        redirect('/admin/settings#languages');
    }

    public function toggle()
    {
        requireRole([Role::SUPER_ADMIN]);
        $code = post_str('code');
        $active = (bool) post_bool('active');

        if (!Language::setActive($code, $active)) {
            flash_set('danger', 'Δεν είναι δυνατή η απενεργοποίηση της γλώσσας πηγής.');
            redirect('/admin/settings#languages');
        }

        flash_set('success', 'Η κατάσταση της γλώσσας ενημερώθηκε.');
        redirect('/admin/settings#languages');
    }

    private static function validLangOr(string $code, string $default): string
    {
        return ($code !== '' && Language::find($code)) ? $code : $default;
    }
}
```

- [ ] **Step 2: Add routes**

In `routes/web.php`, right after the `/admin/settings` block (after line 293, before the `/* ── Maintenance & updates ── */`-style comment at line ~296), add:

```php
$router->get('/admin/languages/search', 'LanguageController@search', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/languages/save',   'LanguageController@save',   ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/languages/add',    'LanguageController@add',    ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/languages/toggle', 'LanguageController@toggle', ['roles' => [Role::SUPER_ADMIN]]);
```

- [ ] **Step 3: Wire into `AdminController::settings()`**

In `app/Controllers/AdminController.php:472-482`, change:

```php
        render('settings/index', [
            'pageTitle'      => 'Ρυθμίσεις Πλατφόρμας',
            'settings'       => $settings,
            'updateConfig'   => UpdateService::config(),
            'currentVersion' => UpdateService::currentVersion(),
            'preflight'      => UpdateService::preflight(),
            'updateCheck'    => $updateCheck,
            'migApplied'     => MigrationRunner::appliedFiles(),
            'migPending'     => MigrationRunner::pendingFiles(),
            'backups'        => UpdateService::listBackups(),
        ]);
```

to:

```php
        render('settings/index', [
            'pageTitle'      => 'Ρυθμίσεις Πλατφόρμας',
            'settings'       => $settings,
            'updateConfig'   => UpdateService::config(),
            'currentVersion' => UpdateService::currentVersion(),
            'preflight'      => UpdateService::preflight(),
            'updateCheck'    => $updateCheck,
            'migApplied'     => MigrationRunner::appliedFiles(),
            'migPending'     => MigrationRunner::pendingFiles(),
            'backups'        => UpdateService::listBackups(),
            'languages'      => Language::all(),
            'stringsPage'    => TranslationString::search([]),
        ]);
```

- [ ] **Step 4: Run route/role consistency test**

```bash
vendor/bin/phpunit tests/Unit/RouteRoleConsistencyTest.php
```

Expected: PASS (the 4 new routes' `['roles' => [Role::SUPER_ADMIN]]` matches each action's `requireRole([Role::SUPER_ADMIN]);`).

- [ ] **Step 5: Manual endpoint smoke test (requires local server + DB)**

With XAMPP/local server running and a super_admin user available, log in via the browser at `http://localhost/syndrasi/public/login`, then in the same browser tab open dev tools and run:

```js
fetch('/syndrasi/public/admin/languages/search?targetLang=en&status=missing', {headers:{'X-Requested-With':'XMLHttpRequest'}})
  .then(r => r.json()).then(console.log)
```

Expected: `{success: true, rows: [...], total: <n>, page: 1, pages: <n>}` with `total` matching the count extracted in Task 2.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/LanguageController.php routes/web.php app/Controllers/AdminController.php
git commit -m "Add Languages tab backend: LanguageController, routes, settings() wiring"
```

---

### Task 4: Languages tab UI

**Files:**
- Modify: `views/settings/index.php` (tab button, tab pane, JS — insert points detailed below)

**Interfaces:**
- Consumes: `$languages` (from `Language::all()`), `$stringsPage` (from `TranslationString::search()`) — both passed by `AdminController::settings()` (Task 3). Calls `GET /admin/languages/search`, `POST /admin/languages/save`, `POST /admin/languages/add`, `POST /admin/languages/toggle` (Task 3). Uses `window.csrfToken` / `window.baseUrl`, both set globally in `views/layouts/header.php:25`.

- [ ] **Step 1: Add the tab button**

In `views/settings/index.php`, the tab button list currently ends with the Danger Zone button (lines 23-27):

```html
  <li class="nav-item" role="presentation">
    <button class="nav-link text-danger" data-bs-toggle="tab" data-bs-target="#tab-danger" type="button">
      <i class="bi bi-exclamation-triangle-fill me-1"></i>Επικίνδυνη Ζώνη
    </button>
  </li>
</ul>
```

Insert a new `<li>` immediately before it (after the Updates tab button, before Danger Zone):

```html
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-languages" type="button">
      <i class="bi bi-translate me-1"></i>Γλώσσες
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link text-danger" data-bs-toggle="tab" data-bs-target="#tab-danger" type="button">
      <i class="bi bi-exclamation-triangle-fill me-1"></i>Επικίνδυνη Ζώνη
    </button>
  </li>
</ul>
```

- [ ] **Step 2: Add the tab pane**

The Updates tab pane closes at `views/settings/index.php:267` (`</div>` closing `#tab-updates`), immediately followed by the Danger Zone pane opening at line 270. Insert this new pane between them:

```html
  <!-- ── Languages ────────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-languages">

    <!-- Language management -->
    <div class="card shadow-sm mb-3" style="max-width:820px">
      <div class="card-body">
        <h2 class="h6 mb-3"><i class="bi bi-globe2 me-1"></i>Γλώσσες</h2>
        <table class="table table-sm align-middle mb-3">
          <thead class="table-light"><tr><th>Κωδικός</th><th>Όνομα</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($languages as $lang): ?>
              <tr>
                <td class="font-monospace small"><?= e($lang['code']) ?></td>
                <td><?= e($lang['name']) ?><?php if ($lang['is_source']): ?> <span class="badge text-bg-secondary">πηγή</span><?php endif; ?></td>
                <td>
                  <?php if (!$lang['is_source']): ?>
                  <form method="post" action="<?= e(url('/admin/languages/toggle')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="code" value="<?= e($lang['code']) ?>">
                    <input type="hidden" name="active" value="<?= $lang['is_active'] ? '0' : '1' ?>">
                    <button class="btn btn-sm btn-outline-secondary py-0">
                      <?= $lang['is_active'] ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>
                    </button>
                  </form>
                  <?php else: ?>
                    <span class="badge text-bg-success">ενεργή</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <form method="post" action="<?= e(url('/admin/languages/add')) ?>" class="d-flex gap-2 align-items-end flex-wrap">
          <?= csrf_field() ?>
          <div>
            <label class="form-label small mb-1">Κωδικός (π.χ. de)</label>
            <input type="text" name="code" class="form-control form-control-sm" style="width:100px" maxlength="10" required>
          </div>
          <div>
            <label class="form-label small mb-1">Όνομα (π.χ. Deutsch)</label>
            <input type="text" name="name" class="form-control form-control-sm" style="width:200px" required>
          </div>
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Προσθήκη γλώσσας</button>
        </form>
      </div>
    </div>

    <!-- Translation catalog -->
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
          <div>
            <label class="form-label small mb-1">Αναφορά</label>
            <select id="langRef" class="form-select form-select-sm">
              <?php foreach ($languages as $lang): ?>
                <option value="<?= e($lang['code']) ?>" <?= $lang['code'] === 'el' ? 'selected' : '' ?>><?= e($lang['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label small mb-1">Μετάφραση σε</label>
            <select id="langTarget" class="form-select form-select-sm">
              <?php foreach ($languages as $lang): ?>
                <option value="<?= e($lang['code']) ?>" <?= $lang['code'] === 'en' ? 'selected' : '' ?>><?= e($lang['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex-grow-1" style="min-width:200px">
            <label class="form-label small mb-1">Αναζήτηση</label>
            <input type="text" id="langSearch" class="form-control form-control-sm" placeholder="Αναζήτηση κειμένου ή κλειδιού…">
          </div>
          <div class="btn-group btn-group-sm" role="group" id="langStatusFilter">
            <button type="button" class="btn btn-outline-secondary active" data-status="all">Όλα</button>
            <button type="button" class="btn btn-outline-secondary" data-status="missing">Χωρίς μετάφραση</button>
            <button type="button" class="btn btn-outline-secondary" data-status="translated">Μεταφρασμένα</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr><th style="width:22%">Κλειδί</th><th style="width:39%">Αναφορά</th><th style="width:39%">Μετάφραση</th></tr>
            </thead>
            <tbody id="langRows">
              <tr><td colspan="3" class="text-muted small">Φόρτωση…</td></tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <div class="small text-muted" id="langPageInfo"></div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="langPrev">« Προηγούμενο</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="langNext">Επόμενο »</button>
          </div>
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="small text-muted" id="langDirtyInfo"></span>
        <button type="button" class="btn btn-primary btn-sm" id="langSaveBtn" disabled>
          <i class="bi bi-save me-1"></i>Αποθήκευση αλλαγών
        </button>
      </div>
    </div>
  </div>
```

- [ ] **Step 3: Add the tab's JS**

Right after the closing `</div>` of the `#tab-languages` pane from Step 2 (still before the closing `</div><!-- /.tab-content -->` at the original line 293), add:

```html
<script>
(function () {
  var page = 1, status = 'all', dirty = {};

  function el(id) { return document.getElementById(id); }

  function postJSON(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    }).then(function (r) { return r.json(); });
  }

  function buildSearchUrl() {
    var params = new URLSearchParams({
      page: page, status: status,
      q: el('langSearch').value,
      refLang: el('langRef').value,
      targetLang: el('langTarget').value
    });
    return window.baseUrl + '/admin/languages/search?' + params.toString();
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : s;
    return d.innerHTML;
  }

  function rowHtml(row) {
    var current = dirty[row.key_id] !== undefined ? dirty[row.key_id] : (row.target_value || '');
    return '<tr data-key-id="' + row.key_id + '">' +
      '<td class="small text-muted">' + escapeHtml(row.str_key) + '<div class="badge text-bg-light">' + escapeHtml(row.str_group) + '</div></td>' +
      '<td class="small">' + escapeHtml(row.ref_value) + '</td>' +
      '<td><input type="text" class="form-control form-control-sm lang-target-input" value="' + escapeHtml(current) + '"></td>' +
      '</tr>';
  }

  function renderRows(rows) {
    var body = el('langRows');
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="3" class="text-muted small">Δεν βρέθηκαν αποτελέσματα.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(rowHtml).join('');
    Array.prototype.forEach.call(body.querySelectorAll('.lang-target-input'), function (input) {
      input.addEventListener('input', function () {
        var keyId = input.closest('tr').getAttribute('data-key-id');
        dirty[keyId] = input.value;
        updateDirtyUi();
      });
    });
  }

  function updateDirtyUi() {
    var count = Object.keys(dirty).length;
    el('langDirtyInfo').textContent = count ? (count + ' αλλαγές δεν έχουν αποθηκευτεί') : '';
    el('langSaveBtn').disabled = count === 0;
  }

  function load() {
    el('langRows').innerHTML = '<tr><td colspan="3" class="text-muted small">Φόρτωση…</td></tr>';
    fetch(buildSearchUrl(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success) return;
        page = d.page;
        renderRows(d.rows);
        el('langPageInfo').textContent = 'Σελίδα ' + d.page + ' από ' + d.pages + ' (' + d.total + ' συνολικά)';
        el('langPrev').disabled = d.page <= 1;
        el('langNext').disabled = d.page >= d.pages;
      });
  }

  el('langRef').addEventListener('change', function () { page = 1; load(); });
  el('langTarget').addEventListener('change', function () { page = 1; dirty = {}; updateDirtyUi(); load(); });

  var searchTimer;
  el('langSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () { page = 1; load(); }, 300);
  });

  Array.prototype.forEach.call(el('langStatusFilter').querySelectorAll('button'), function (btn) {
    btn.addEventListener('click', function () {
      Array.prototype.forEach.call(el('langStatusFilter').querySelectorAll('button'), function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      status = btn.getAttribute('data-status');
      page = 1;
      load();
    });
  });

  el('langPrev').addEventListener('click', function () { if (page > 1) { page--; load(); } });
  el('langNext').addEventListener('click', function () { page++; load(); });

  el('langSaveBtn').addEventListener('click', function () {
    var rows = Object.keys(dirty).map(function (keyId) { return { key_id: parseInt(keyId, 10), value: dirty[keyId] }; });
    if (!rows.length) return;
    postJSON(window.baseUrl + '/admin/languages/save', { languageCode: el('langTarget').value, rows: rows })
      .then(function () { dirty = {}; updateDirtyUi(); load(); });
  });

  if (document.getElementById('tab-languages')) {
    load();
  }
})();
</script>
```

- [ ] **Step 4: Manual browser verification**

Start the local dev server (or use the existing XAMPP setup — see `DEPLOY.md`), log in as a super_admin, visit `/admin/settings`, click the "Γλώσσες" tab. Verify:
- The languages table lists Ελληνικά (πηγή badge) and English (ενεργή badge with an "Απενεργοποίηση" button).
- The translation table loads rows with Greek reference text and empty target inputs.
- Typing in a target input marks it dirty (footer shows "N αλλαγές..." and enables Save).
- Clicking "Αποθήκευση αλλαγών" saves and the table reloads with the value now the "current" one.
- Adding a language (e.g. code `de`, name `Deutsch`) via the small form adds a row to the languages table.
- Clicking "Απενεργοποίηση" on the Greek row is impossible (no button rendered — only English/added languages get the toggle button, since `is_source` languages render a plain badge instead per the template's `<?php if (!$lang['is_source']): ?>` guard).

- [ ] **Step 5: Commit**

```bash
git add views/settings/index.php
git commit -m "Add Languages tab UI: language management + translation catalog table"
```

---

### Task 5: Per-user language preference (`/profile`)

**Files:**
- Modify: `app/Helpers/functions.php:182-186` (`current_user()` — add `language_code` to the SELECT list)
- Modify: `app/Models/User.php` (add `updateLanguage()`)
- Modify: `app/Controllers/AuthController.php` (add `updateLanguage()` action; extend `profile()`'s render data)
- Modify: `routes/web.php` (add `POST /profile/language`)
- Modify: `views/auth/profile.php` (add the picker card)

**Interfaces:**
- Consumes: `Language::all(true)`, `Language::isActiveCode()` (Task 1).
- Produces: `User::updateLanguage($id, ?string $languageCode): void`. Route `POST /profile/language` (no role restriction — any logged-in user, matching `/profile` and `/profile/password`).

- [ ] **Step 1: Add `language_code` to `current_user()`'s SELECT list**

In `app/Helpers/functions.php:182-186`, change:

```php
            $user = dbq(
                'SELECT id, name, email, role, municipality_id, team_id, status, last_login_at, created_at
                 FROM users WHERE id = :id AND status = :st LIMIT 1',
                ['id' => $_SESSION['user_id'], 'st' => 'active']
            )->fetch();
```

to:

```php
            $user = dbq(
                'SELECT id, name, email, role, municipality_id, team_id, status, language_code, last_login_at, created_at
                 FROM users WHERE id = :id AND status = :st LIMIT 1',
                ['id' => $_SESSION['user_id'], 'st' => 'active']
            )->fetch();
```

- [ ] **Step 2: Add `User::updateLanguage()`**

In `app/Models/User.php`, add (after `updatePassword`):

```php
    public static function updateLanguage($id, $languageCode)
    {
        dbq('UPDATE users SET language_code = :lc WHERE id = :id', ['lc' => $languageCode, 'id' => $id]);
    }
```

- [ ] **Step 3: Add the controller action and extend `profile()`**

In `app/Controllers/AuthController.php`, change `profile()` (currently lines 256-268):

```php
    public function profile()
    {
        requireLogin();
        $user = current_user();
        $municipality = $user['municipality_id'] ? Municipality::find($user['municipality_id']) : null;
        $team = $user['team_id'] ? VolunteerTeam::find($user['team_id']) : null;
        render('auth/profile', [
            'pageTitle'    => 'Το προφίλ μου',
            'user'         => $user,
            'municipality' => $municipality,
            'team'         => $team,
            'languages'    => Language::all(true),
        ]);
    }
```

And add, right after `changePassword()`:

```php
    public function updateLanguage()
    {
        requireLogin();
        $user = current_user();
        $code = post_str('language_code');

        if ($code !== '' && !Language::isActiveCode($code)) {
            flash_set('danger', 'Μη έγκυρη γλώσσα.');
            redirect('/profile');
        }

        User::updateLanguage($user['id'], $code !== '' ? $code : null);
        flash_set('success', 'Η γλώσσα ενημερώθηκε.');
        redirect('/profile');
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, right after `$router->post('/profile/password', 'AuthController@changePassword');` (line 50):

```php
$router->post('/profile/language', 'AuthController@updateLanguage');
```

- [ ] **Step 5: Add the picker to `views/auth/profile.php`**

In `views/auth/profile.php`, right after the closing `</div>` of the "Στοιχεία" card (originally lines 6-35, closing at line 35) and still inside the same `col-lg-6` column, add:

```html
    <div class="card shadow-sm mt-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-translate me-1"></i> Γλώσσα</div>
      <div class="card-body">
        <form method="post" action="<?= e(url('/profile/language')) ?>">
          <?= csrf_field() ?>
          <div class="mb-2">
            <select name="language_code" class="form-select">
              <option value="">— Προεπιλογή πλατφόρμας —</option>
              <?php foreach ($languages as $lang): ?>
                <option value="<?= e($lang['code']) ?>" <?= ($user['language_code'] ?? '') === $lang['code'] ? 'selected' : '' ?>>
                  <?= e($lang['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="small text-muted mb-2">
            Η επιλογή γλώσσας θα εφαρμοστεί σε επόμενη ενημέρωση της εφαρμογής.
          </p>
          <button class="btn btn-primary btn-sm" type="submit">Αποθήκευση</button>
        </form>
      </div>
    </div>
```

- [ ] **Step 6: Run route/role consistency test**

```bash
vendor/bin/phpunit tests/Unit/RouteRoleConsistencyTest.php
```

Expected: PASS (`/profile/language` has no `roles` option and `updateLanguage()`'s only guard is `requireLogin()`, which the test explicitly skips — same as the existing `/profile` and `/profile/password` routes).

- [ ] **Step 7: Manual browser verification**

Log in as any role, visit `/profile`, confirm the "Γλώσσα" card renders with Ελληνικά/English options, select English, save, confirm the flash message and that the select now shows English selected after reload. Confirm `SELECT language_code FROM users WHERE email = '<your test email>'` shows `en`.

- [ ] **Step 8: Commit**

```bash
git add app/Helpers/functions.php app/Models/User.php app/Controllers/AuthController.php routes/web.php views/auth/profile.php
git commit -m "Add self-service per-user language preference on /profile"
```

---

### Task 6: Integration test coverage + full suite verification

**Files:**
- Modify: `tests/Integration/LocalHttpTest.php` (add a super_admin fixture, a dedicated test translation key, and 4 new test methods)

**Interfaces:**
- Consumes: everything from Tasks 1-5.

- [ ] **Step 1: Add fixtures**

In `tests/Integration/LocalHttpTest.php`, add new static properties near the existing ones (around line 27):

```php
    private const SUPER_ADMIN_EMAIL = 'httptest_superadmin@test.local';
    private const SUPER_ADMIN_PASS  = 'HttpTest#Super12345';
    private static int $superAdminId = 0;
    private static int $testTranslationKeyId = 0;
```

In `fixturesUp()`, after the existing municipality_admin user insert (end of the method, before its closing `}`), add:

```php
        $hashSuper = password_hash(self::SUPER_ADMIN_PASS, PASSWORD_DEFAULT);
        $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, status) VALUES ('HTTP Test Super Admin', ?, ?, 'super_admin', 'active')"
        )->execute([self::SUPER_ADMIN_EMAIL, $hashSuper]);
        self::$superAdminId = (int) $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO translation_keys (str_key, str_group) VALUES ('httptest.sample', 'httptest')")->execute();
        self::$testTranslationKeyId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO translation_values (key_id, language_code, value) VALUES (?, 'el', 'Δοκιμαστικό κείμενο')"
        )->execute([self::$testTranslationKeyId]);
```

In `fixturesDown()`, add cleanup:

```php
        $pdo->exec('DELETE FROM translation_values WHERE key_id = ' . self::$testTranslationKeyId);
        $pdo->exec('DELETE FROM translation_keys WHERE id = ' . self::$testTranslationKeyId);
        $pdo->exec("DELETE FROM languages WHERE code = 'de'"); // in case a test run leaves it behind
        $pdo->prepare('DELETE FROM users WHERE email = ?')->execute([self::SUPER_ADMIN_EMAIL]);
        $pdo->prepare('DELETE FROM audit_logs WHERE user_id = ?')->execute([self::$superAdminId]);
```

- [ ] **Step 2: Add a small login helper**

The existing `testLoginAndOpsStreamShortCircuit()` inlines its login flow. Add a private helper right after `csrfFrom()` so new tests can log in as either fixture user without duplicating the flow:

```php
    private function loginAs(string $email, string $password): void
    {
        [, $html] = $this->http('GET', '/login');
        $csrf = $this->csrfFrom($html);
        [$code] = $this->http('POST', '/login', [
            'form' => ['email' => $email, 'password' => $password, '_token' => $csrf],
        ]);
        $this->assertSame(302, $code, 'login should redirect on success');
    }
```

- [ ] **Step 3: Add the Languages-tab test methods**

Add at the end of the class, before the final closing `}`:

```php
    /* ── Languages settings tab (super admin) ─────────────────────────── */

    public function testSuperAdminCanManageLanguagesAndTranslations(): void
    {
        $this->loginAs(self::SUPER_ADMIN_EMAIL, self::SUPER_ADMIN_PASS);

        [$code, $html] = $this->http('GET', '/admin/settings');
        $this->assertSame(200, $code);
        $this->assertStringContainsString('tab-languages', $html);
        $csrf = $this->csrfFrom($html);

        // Add a language.
        [$code] = $this->http('POST', '/admin/languages/add', [
            'form' => ['code' => 'de', 'name' => 'Deutsch', '_token' => $csrf],
        ]);
        $this->assertSame(302, $code);
        $row = self::$pdo->query("SELECT * FROM languages WHERE code = 'de'")->fetch();
        $this->assertNotFalse($row, 'language should have been created');
        $this->assertSame(1, (int) $row['is_active']);

        // Search finds the fixture key as missing for 'de'.
        [$code, $body] = $this->http('GET', '/admin/languages/search?targetLang=de&status=missing&q=httptest', [
            'headers' => ['X-Requested-With: XMLHttpRequest'],
        ]);
        $this->assertSame(200, $code);
        $data = json_decode($body, true);
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(1, $data['total']);

        // Save a translation for it.
        [$code, $body] = $this->http('POST', '/admin/languages/save', [
            'json' => [
                'languageCode' => 'de',
                'rows' => [['key_id' => self::$testTranslationKeyId, 'value' => 'Testtext']],
                '_token' => $csrf,
            ],
            'headers' => ['X-CSRF-Token: ' . $csrf, 'X-Requested-With: XMLHttpRequest'],
        ]);
        $this->assertSame(302, $code, $body);
        $val = self::$pdo->query(
            'SELECT value FROM translation_values WHERE key_id = ' . self::$testTranslationKeyId . " AND language_code = 'de'"
        )->fetchColumn();
        $this->assertSame('Testtext', $val);

        // Clean up the language this test added.
        self::$pdo->exec("DELETE FROM translation_values WHERE language_code = 'de'");
        self::$pdo->exec("DELETE FROM languages WHERE code = 'de'");
    }

    public function testCannotDeactivateSourceLanguage(): void
    {
        $this->loginAs(self::SUPER_ADMIN_EMAIL, self::SUPER_ADMIN_PASS);
        [, $html] = $this->http('GET', '/admin/settings');
        $csrf = $this->csrfFrom($html);

        [$code] = $this->http('POST', '/admin/languages/toggle', [
            'form' => ['code' => 'el', 'active' => '0', '_token' => $csrf],
        ]);
        $this->assertSame(302, $code);

        $active = self::$pdo->query("SELECT is_active FROM languages WHERE code = 'el'")->fetchColumn();
        $this->assertSame('1', (string) $active, 'source language must stay active');
    }

    /* ── Per-user language preference (/profile) ──────────────────────── */

    public function testProfileLanguagePickerPersistsAndRejectsInvalidCode(): void
    {
        $this->loginAs(self::TEST_EMAIL, self::TEST_PASS);
        [, $html] = $this->http('GET', '/profile');
        $this->assertStringContainsString('name="language_code"', $html);
        $csrf = $this->csrfFrom($html);

        // Invalid code is rejected — no change persisted.
        [$code] = $this->http('POST', '/profile/language', [
            'form' => ['language_code' => 'xx', '_token' => $csrf],
        ]);
        $this->assertSame(302, $code);
        $val = self::$pdo->query("SELECT language_code FROM users WHERE email = '" . self::TEST_EMAIL . "'")->fetchColumn();
        $this->assertNull($val, 'invalid language code must not be persisted');

        // Valid code persists.
        [$code] = $this->http('POST', '/profile/language', [
            'form' => ['language_code' => 'en', '_token' => $csrf],
        ]);
        $this->assertSame(302, $code);
        $val = self::$pdo->query("SELECT language_code FROM users WHERE email = '" . self::TEST_EMAIL . "'")->fetchColumn();
        $this->assertSame('en', $val);
    }
```

- [ ] **Step 4: Run the full suite**

```bash
vendor/bin/phpunit
```

Expected: all tests pass. If no local server/DB is reachable, the four new methods (and the rest of `LocalHttpTest`) report as skipped, not failed — confirm the run summary shows `OK, but there were skipped tests!` rather than any failures, and that `tests/Unit/*` all pass unconditionally.

- [ ] **Step 5: Commit**

```bash
git add tests/Integration/LocalHttpTest.php
git commit -m "Add integration test coverage for Languages tab and profile language picker"
```

---

## Post-implementation note

This plan intentionally stops short of making the 73 views render translated text — `users.language_code` and the translation catalog exist and are editable, but nothing reads them yet outside the admin tools built here. Wiring the views is a separate follow-up project (as scoped in the approved design spec).
