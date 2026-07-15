# View Translation Wiring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the app actually render in each user's chosen language — wire all 73 view files to the existing translation catalog instead of emitting hardcoded Greek unconditionally.

**Architecture:** A `t()`/`current_language()` helper pair resolves and caches the current user's language per request. A one-time script mechanically replaces the 1801 already-cataloged Greek strings across the 73 views with `t()` calls, matching by exact text content against the existing database catalog (not file position, which two drifted files have already invalidated) and minting new keys for anything not yet cataloged. A small manual follow-up handles one file with language-inappropriate hardcoded pluralization logic, and a new migration ships the small set of newly-discovered strings to production.

**Tech Stack:** Plain PHP (no framework), PDO/MySQL, PHPUnit.

## Global Constraints

- Anonymous requests (no session) always resolve to `'el'` — no browser-language detection.
- No new UI beyond what's already there — the existing `/profile` picker is the only switcher.
- `t()` never escapes; every call site wraps it in `e()` — `<?= e(t('key', 'fallback')) ?>` — matching how all other dynamic content in this codebase is rendered.
- Content not in the existing catalog (dynamic/user-entered data — event titles, municipality names, category names) stays untouched; this project only wires the already-extracted static UI strings plus the small set the wiring pass discovers was never extracted.
- Strings extraction already skipped (34, contain a literal `;`) stay hardcoded Greek — not addressed here.
- This codebase has no DB-backed PHPUnit Unit tests — DB-touching code is verified via CLI smoke checks and the existing `tests/Integration/LocalHttpTest.php` HTTP-level suite, not new isolated Unit tests.
- PHP is at `/c/xampp/php/php.exe`, MySQL client at `/c/xampp/mysql/bin/mysql.exe` (neither on PATH) — use full paths. Add `--default-character-set=utf8mb4` to `mysql.exe` invocations or Greek text comes back mangled.
- No `declare(strict_types=1)` anywhere in this codebase — don't introduce it.

---

### Task 1: `current_language()` and `t()` helpers

**Files:**
- Modify: `app/Helpers/functions.php:201-204` (insert after `current_role()`)

**Interfaces:**
- Produces: `current_language(): string`, `t(string $key, string $fallback = ''): string`.

- [ ] **Step 1: Add the helpers**

In `app/Helpers/functions.php`, immediately after `current_role()` (currently lines 201-204):

```php
function current_role()
{
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function current_language(): string
{
    $user = current_user();
    return $user['language_code'] ?? 'el';
}

function t(string $key, string $fallback = ''): string
{
    static $cache = [];
    $lang = current_language();

    if (!isset($cache[$lang])) {
        $cache[$lang] = [];
        $rows = dbq(
            'SELECT tk.str_key, tv.value FROM translation_keys tk
             JOIN translation_values tv ON tv.key_id = tk.id AND tv.language_code = :lang',
            ['lang' => $lang]
        )->fetchAll();
        foreach ($rows as $row) {
            $cache[$lang][$row['str_key']] = $row['value'];
        }
    }

    if (isset($cache[$lang][$key]) && $cache[$lang][$key] !== '') {
        return $cache[$lang][$key];
    }
    if ($fallback !== '') {
        return $fallback;
    }
    if ($lang !== 'el') {
        if (!isset($cache['el'])) {
            $cache['el'] = [];
            $rows = dbq(
                "SELECT tk.str_key, tv.value FROM translation_keys tk
                 JOIN translation_values tv ON tv.key_id = tk.id AND tv.language_code = 'el'"
            )->fetchAll();
            foreach ($rows as $row) {
                $cache['el'][$row['str_key']] = $row['value'];
            }
        }
        if (isset($cache['el'][$key]) && $cache['el'][$key] !== '') {
            return $cache['el'][$key];
        }
    }
    return $key;
}
```

- [ ] **Step 2: Lint**

```bash
/c/xampp/php/php.exe -l app/Helpers/functions.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke-check against the local DB**

```bash
/c/xampp/php/php.exe -r "
define('BASE_PATH', __DIR__);
require 'app/Helpers/functions.php';
\$_SESSION = [];
assert(current_language() === 'el', 'anonymous must default to el');
assert(t('does/not.exist', 'Fallback text') === 'Fallback text', 'missing key must return fallback');
assert(t('does/not.exist') === 'does/not.exist', 'missing key with no fallback must return the key itself');
\$row = dbq('SELECT tk.str_key FROM translation_keys tk JOIN translation_values tv ON tv.key_id=tk.id AND tv.language_code=\'el\' LIMIT 1')->fetch();
\$key = \$row['str_key'];
\$greekVal = t(\$key, 'SHOULD NOT SEE THIS');
assert(\$greekVal !== 'SHOULD NOT SEE THIS', 'a real key must resolve from the DB, not fall through to the fallback');
echo \"OK: \$key => \$greekVal\n\";
"
```
Expected: `OK: <some key> => <its Greek value>`, no assertion failures.

- [ ] **Step 4: Run the full PHPUnit suite (regression check)**

```bash
/c/xampp/php/php.exe vendor/bin/phpunit
```
Expected: all tests still pass (no new tests yet — this just confirms the helper addition didn't break anything).

- [ ] **Step 5: Commit**

```bash
git add app/Helpers/functions.php
git commit -m "Add current_language()/t() render-time translation lookup helpers"
```

---

### Task 2: Wiring script — build and pilot on 3 files

**Files:**
- Create: `scripts/wire-translations.php`

**Interfaces:**
- Consumes: `db()`, `dbq()` (from `app/Helpers/functions.php`, Task 1).
- Produces: a CLI script; no PHP interfaces consumed by later tasks. Writes to `storage/logs/wiring-new-keys.log` (one newly-minted `str_key` per line, appended — Task 6 reads this to know exactly which keys are new).

- [ ] **Step 1: Write the script**

Create `scripts/wire-translations.php`:

```php
<?php
/**
 * One-time dev tool: wires already-cataloged translation strings into their
 * source view files, replacing hardcoded Greek text with t()/e() calls.
 *
 * Matches text by exact content against the existing translation_keys
 * catalog (not by file position — some view files were edited after the
 * original extraction ran, so their current Greek-text order no longer
 * matches what extraction saw). A string with no existing match gets a
 * newly minted key + seeded 'el' value, logged to
 * storage/logs/wiring-new-keys.log for a later translation pass.
 *
 * Safe to re-run: an already-wired file's Greek text now lives inside
 * <?= ?> tags, which get blanked before matching, so re-running finds
 * nothing left to replace in that file.
 *
 * IMPORTANT — text-node blanking is context-aware, not a single blind rule:
 * A blanked PHP/<script> span must become a hard tag-like boundary when it
 * sits BETWEEN two real HTML tags (otherwise two Greek fragments separated
 * only by e.g. `<?php else: ?>` merge into one over-wide match, and
 * replacing that match deletes the tag — the first bug found while building
 * this script). But it must NOT become a boundary when it sits INSIDE an
 * already-open real tag's attribute list (e.g.
 * `<form action="<?= ... ?>" onsubmit="...Greek...">`) — treating that as a
 * boundary makes the >...< regex mistake the rest of the tag's real
 * attribute syntax (a closing quote, an attribute name) for a text node,
 * and replacing THAT match deletes real HTML structure while still passing
 * both `php -l` and a naive tag-count check, since a new valid `<?= ?>` tag
 * is also being added at the same time (the second bug found). So:
 *
 * 1. Blank all PHP/<script> spans to pure spaces first (`blankToSpaces`) —
 *    this is also exactly what attribute matching uses, unchanged.
 * 2. Scan that space-blanked copy to find every byte range that sits inside
 *    a real `<...>` tag span (`computeInsideTagRanges`) — safe to do only
 *    after step 1, so a PHP comparison like `$a < $b` can never be mistaken
 *    for a real tag's `<`.
 * 3. Re-blank the PHP/<script> spans against the ORIGINAL raw content, this
 *    time choosing per-occurrence: pure spaces if that occurrence's offset
 *    falls inside one of the ranges from step 2 (attribute context — matches
 *    how attribute matching already handles it safely), or a same-length
 *    FAKE TAG (`<` + spaces + `>`) otherwise (top-level context — creates
 *    the real boundary needed to prevent cross-branch merging).
 *
 * Attribute matching is unaffected by any of this — it only ever uses the
 * step-1 pure-space blanking, exactly as before.
 *
 * Safe to re-run: an already-wired file's Greek text now lives inside
 * <?= ?> tags, which get blanked before matching, so re-running finds
 * nothing left to replace in that file.
 *
 * A second, independent safety net: any candidate match whose cleaned text
 * looks like leaked HTML attribute syntax (starts with a bare `"`, or
 * contains `="` or `='`) is rejected outright rather than wired — genuine
 * translatable UI text never looks like this, so this is a cheap backstop
 * against any further variant of the tag-context bug this algorithm hasn't
 * been proven immune to yet.
 *
 * Usage:
 *   php scripts/wire-translations.php                   # wire every view
 *   php scripts/wire-translations.php auth/login.php     # wire only these
 *                                                         # (relative to views/)
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Helpers/functions.php';

function phpStringEscape(string $s): string
{
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $s);
}

function cleanText(string $text): string
{
    return trim(preg_replace('/\s+/', ' ', $text));
}

/** Genuine translatable UI text never looks like this — a defensive
 *  backstop against any HTML-attribute-syntax leak this algorithm's
 *  boundary detection hasn't been proven immune to. */
function looksLikeAttributeSyntax(string $text): bool
{
    return str_starts_with($text, '"') || str_contains($text, '="') || str_contains($text, "='");
}

function blankAsSpaces(string $s): string
{
    return str_repeat(' ', strlen($s));
}

/** Same length as $s, but starts with < and ends with > — a real tag
 *  boundary the >...< text-node regex can never span across. */
function blankAsFakeTag(string $s): string
{
    $len = strlen($s);
    if ($len < 2) {
        return str_repeat(' ', $len);
    }
    return '<' . str_repeat(' ', $len - 2) . '>';
}

/** Blank <?php ?>/<?= ?>/<script> blocks to pure spaces. Also the exact
 *  blanking attribute matching uses, unchanged. */
function blankToSpaces(string $raw): string
{
    $withoutPhp = preg_replace_callback('/<\?php.*?\?>|<\?=.*?\?>/s', function ($m) {
        return blankAsSpaces($m[0]);
    }, $raw);
    return preg_replace_callback('/<script\b[^>]*>.*?<\/script>/is', function ($m) {
        return blankAsSpaces($m[0]);
    }, $withoutPhp);
}

/**
 * Byte ranges (offsets into a space-blanked copy) that sit inside a real
 * HTML tag's <...> span. Must run against an already space-blanked copy so
 * PHP code's own < / > characters (comparison operators) can never be
 * mistaken for a real tag boundary.
 * @return array<int, array{0:int,1:int}> sorted, non-overlapping [start, end) pairs
 */
function computeInsideTagRanges(string $spaceBlanked): array
{
    $ranges = [];
    $depth = 0;
    $tagStart = null;
    $len = strlen($spaceBlanked);
    for ($i = 0; $i < $len; $i++) {
        $ch = $spaceBlanked[$i];
        if ($ch === '<' && $depth === 0) {
            $depth = 1;
            $tagStart = $i;
        } elseif ($ch === '>' && $depth === 1) {
            $ranges[] = [$tagStart, $i + 1];
            $depth = 0;
            $tagStart = null;
        }
    }
    return $ranges;
}

function isInsideTag(array $sortedRanges, int $offset): bool
{
    foreach ($sortedRanges as [$start, $end]) {
        if ($offset < $start) {
            return false;
        }
        if ($offset < $end) {
            return true;
        }
    }
    return false;
}

function blankForTextNodes(string $raw): string
{
    $spaceBlanked = blankToSpaces($raw);
    $insideTagRanges = computeInsideTagRanges($spaceBlanked);

    $result = preg_replace_callback('/<\?php.*?\?>|<\?=.*?\?>/s', function ($m) use ($insideTagRanges) {
        return isInsideTag($insideTagRanges, $m[0][1]) ? blankAsSpaces($m[0][0]) : blankAsFakeTag($m[0][0]);
    }, $raw, -1, $count, PREG_OFFSET_CAPTURE);

    return preg_replace_callback('/<script\b[^>]*>.*?<\/script>/is', function ($m) {
        return blankAsFakeTag($m[0]);
    }, $result);
}

function blankForAttributes(string $raw): string
{
    return blankToSpaces($raw);
}

/** @return array<int, array{offset:int, length:int, text:string}> */
function findMatches(string $blankedForText, string $blankedForAttr): array
{
    $matches = [];
    if (preg_match_all('/>([^<>]*[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}][^<>]*)</u', $blankedForText, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as [$text, $offset]) {
            $matches[] = ['offset' => $offset, 'length' => strlen($text), 'text' => $text];
        }
    }
    if (preg_match_all('/\b(?:placeholder|title|alt|aria-label)="([^"]*[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}][^"]*)"/u', $blankedForAttr, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as [$text, $offset]) {
            $matches[] = ['offset' => $offset, 'length' => strlen($text), 'text' => $text];
        }
    }
    return $matches;
}

$requested = array_slice($argv, 1);
$viewsDir = str_replace('\\', '/', BASE_PATH . '/views'); // BASE_PATH is backslash-separated on Windows
$newKeysLog = BASE_PATH . '/storage/logs/wiring-new-keys.log';

$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        // getPathname() joins with the OS separator (backslash on Windows)
        // regardless of $viewsDir's own separator, so normalize here too.
        $files[] = str_replace('\\', '/', $file->getPathname());
    }
}
sort($files);

if ($requested) {
    $wanted = array_map(function ($p) use ($viewsDir) {
        return $viewsDir . '/' . str_replace('\\', '/', $p);
    }, $requested);
    $files = array_values(array_intersect($files, $wanted));
}

$pdo = db();
$totalReplacements = 0;
$totalNewKeys = 0;

foreach ($files as $path) {
    $relative = str_replace('\\', '/', substr($path, strlen($viewsDir) + 1));
    $group = preg_replace('/\.php$/', '', $relative);
    $raw = file_get_contents($path);
    $rawMatches = findMatches(blankForTextNodes($raw), blankForAttributes($raw));
    if (!$rawMatches) {
        continue;
    }

    $existing = [];
    $maxIndex = 0;
    $rows = dbq(
        "SELECT tk.str_key, tv.value FROM translation_keys tk
         JOIN translation_values tv ON tv.key_id = tk.id AND tv.language_code = 'el'
         WHERE tk.str_group = :g",
        ['g' => $group]
    )->fetchAll();
    foreach ($rows as $row) {
        $existing[$row['value']] = $row['str_key'];
        if (preg_match('/\.(\d+)$/', $row['str_key'], $mm)) {
            $maxIndex = max($maxIndex, (int) $mm[1]);
        }
    }

    $seenInFile = [];
    $replacements = [];
    $newRows = [];

    foreach ($rawMatches as $match) {
        $clean = cleanText($match['text']);
        if ($clean === '' || mb_strlen($clean) < 2 || strpos($clean, ';') !== false) {
            continue;
        }
        if (looksLikeAttributeSyntax($clean)) {
            echo "  SKIPPED (looks like attribute syntax): \"$clean\"\n";
            continue;
        }
        if (isset($seenInFile[$clean])) {
            $key = $seenInFile[$clean];
        } elseif (isset($existing[$clean])) {
            $key = $existing[$clean];
            $seenInFile[$clean] = $key;
        } else {
            $maxIndex++;
            $key = $group . '.' . str_pad((string) $maxIndex, 3, '0', STR_PAD_LEFT);
            $seenInFile[$clean] = $key;
            $newRows[$key] = $clean;
        }
        $replacements[] = [
            'offset' => $match['offset'],
            'length' => $match['length'],
            'key' => $key,
            'fallback' => $clean,
        ];
    }

    foreach ($newRows as $key => $clean) {
        dbq('INSERT INTO translation_keys (str_key, str_group) VALUES (:k, :g)', ['k' => $key, 'g' => $group]);
        $keyId = $pdo->lastInsertId();
        dbq("INSERT INTO translation_values (key_id, language_code, value) VALUES (:kid, 'el', :v)", ['kid' => $keyId, 'v' => $clean]);
        file_put_contents($newKeysLog, $key . "\n", FILE_APPEND);
        $totalNewKeys++;
        echo "  NEW KEY: $key = \"$clean\"\n";
    }

    usort($replacements, function ($a, $b) { return $b['offset'] <=> $a['offset']; });
    foreach ($replacements as $r) {
        $call = "<?= e(t('{$r['key']}', '" . phpStringEscape($r['fallback']) . "')) ?>";
        $raw = substr_replace($raw, $call, $r['offset'], $r['length']);
    }
    file_put_contents($path, $raw);
    $totalReplacements += count($replacements);
    echo "Wired {$relative}: " . count($replacements) . " spans (" . count($newRows) . " new)\n";
}

echo "Total: $totalReplacements spans wired, $totalNewKeys new keys, across " . count($files) . " files.\n";
```

- [ ] **Step 2: Lint**

```bash
/c/xampp/php/php.exe -l scripts/wire-translations.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Run it on the pilot set**

Delete any stale log from a previous attempt, then run on three deliberately diverse files: `auth/login.php` (small, unwired-drift-free), `settings/index.php` (large, contains the Languages-tab text that postdates extraction — will mint new keys), `team/event_show.php` (has several gap-preserving "dynamic value stripped" strings):

```bash
rm -f storage/logs/wiring-new-keys.log
/c/xampp/php/php.exe scripts/wire-translations.php auth/login.php settings/index.php team/event_show.php
```
Expected: a `Wired <file>: N spans (M new)` line per file — `auth/login.php` should show `0 new` (its content predates no drift), `settings/index.php` should show `M > 0` (the Languages tab postdates extraction), and a non-empty `storage/logs/wiring-new-keys.log`. Don't expect an exact span count for any file — a string repeated multiple times within one file reuses one key across every occurrence, so span count can exceed the file's distinct-key count; that's correct dedup behavior, not a bug.

- [ ] **Step 4: Lint the 3 wired files**

```bash
/c/xampp/php/php.exe -l views/auth/login.php
/c/xampp/php/php.exe -l views/settings/index.php
/c/xampp/php/php.exe -l views/team/event_show.php
```
Expected: `No syntax errors detected` for all three. **A lint pass alone does not prove the wiring is safe** — see Step 4b.

- [ ] **Step 4b: Verify no PHP structure was lost (the check that would have caught the file-corruption class of bug in the original algorithm)**

For each of the 3 pilot files, confirm the wired version has exactly `N` more `<?php`/`<?=` tag-opening sequences than the original (`N` = that file's span count from Step 3's output — replacement only ever *adds* one new `<?= ?>` tag per replaced span, it must never remove an existing one):

```bash
for f in auth/login.php settings/index.php team/event_show.php; do
  before=$(git show HEAD:views/$f | grep -oE '<\?php|<\?=' | wc -l)
  after=$(grep -oE '<\?php|<\?=' views/$f | wc -l)
  echo "$f: before=$before after=$after diff=$((after - before))"
done
```
Expected: each file's `diff` exactly equals that file's span count printed in Step 3. If any file's diff is *less* than its span count, a real `<?php`/`<?=` tag was deleted during wiring — stop immediately, do not proceed further, and treat it as a bug in the wiring script (this is exactly the class of bug that corrupted an earlier version of this algorithm: a text-node match spanned across a PHP control-flow tag with no real HTML tag between two Greek fragments, and replacing that match deleted the tag).

**Passing Step 4b is necessary but not sufficient** — a second bug class was found where a PHP tag *inside* an already-open real tag's attribute list (e.g. `<form action="<?= ?>" onsubmit="...">`) got wired in a way that deleted real attribute syntax (a closing quote, an attribute name) while still adding a new, valid `<?= ?>` tag — so the tag count still balanced even though the file was corrupted. Step 4c below is the check that catches this class instead.

- [ ] **Step 4c: Verify no `t()` call's fallback text looks like leaked HTML attribute syntax**

Write and run:

```php
<?php
$viewsDir = 'C:/Users/user/Desktop/Syndrasi/syndrasi/.claude/worktrees/view-translation-wiring/views';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
$bad = [];
foreach ($rii as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $content = file_get_contents($file->getPathname());
    if (preg_match_all("/t\('([^']+)',\s*'((?:[^'\\\\]|\\\\.)*)'\)/", $content, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $fallback = stripslashes($match[2]);
            if (str_starts_with($fallback, '"') || str_contains($fallback, '="') || str_contains($fallback, "='")) {
                $bad[] = $file->getPathname() . ': ' . $match[1] . ' = "' . $fallback . '"';
            }
        }
    }
}
if ($bad) {
    echo "SUSPICIOUS FALLBACK TEXT (possible attribute-syntax leak):\n" . implode("\n", $bad) . "\n";
    exit(1);
}
echo "OK: no suspicious t() fallback text found.\n";
```

Expected: `OK: no suspicious t() fallback text found.` Any `SUSPICIOUS FALLBACK TEXT` line means real HTML attribute syntax leaked into a `t()` call's fallback argument — stop immediately and treat it as a bug in the wiring script, not something to patch around by editing the affected view file by hand (the algorithm needs to not produce this in the first place, since Task 3 runs this same script across 70 more files next).

- [ ] **Step 5: Verify every `t()` key referenced in the 3 files exists in the database**

```bash
grep -oE "t\('[^']+'" views/auth/login.php views/settings/index.php views/team/event_show.php | sed -E "s/.*t\('//" | sort -u > /tmp/wired_keys_pilot.txt
"/c/xampp/mysql/bin/mysql.exe" -u root syndrasi --skip-column-names -e "SELECT str_key FROM translation_keys" | sort -u > /tmp/db_keys.txt
comm -23 /tmp/wired_keys_pilot.txt /tmp/db_keys.txt
```
Expected: no output (every key referenced in code exists in the database). Any line printed is a bug — stop and investigate before proceeding.

- [ ] **Step 6: Verify Greek rendering is unchanged (manual)**

With a local server reachable (`http://localhost/syndrasi/public`), log in as a user with no `language_code` set (or the seed super-admin) and load `/login`, `/admin/settings`, and a real event's `team/event_show` page. Confirm every visible Greek label reads exactly as before — this is expected by construction (`t()` returns the exact DB value for `'el'`, which is the same text that was hardcoded), but confirm nothing looks broken (no literal `t(...)` text leaking onto the page, no missing labels).

- [ ] **Step 7: Verify English rendering works (manual)**

Set your account's language to English via `/profile`, reload the same three pages, confirm translated text now appears in place of the Greek, and the page layout isn't broken (no unclosed tags, no attribute quoting issues).

- [ ] **Step 8: Commit**

```bash
git add scripts/wire-translations.php views/auth/login.php views/settings/index.php views/team/event_show.php storage/logs/wiring-new-keys.log
git commit -m "Add view-wiring script; pilot on 3 diverse view files"
```

---

### Task 3: Full wiring run across all 73 views

**Files:**
- Modify: all `views/**/*.php` files not already wired by Task 2 (70 remaining).

**Interfaces:**
- Consumes: `scripts/wire-translations.php` (Task 2), `t()`/`e()` (Task 1).

- [ ] **Step 1: Run the script with no arguments (wires everything; already-wired files are skipped as no-ops), saving its output**

```bash
/c/xampp/php/php.exe scripts/wire-translations.php | tee /tmp/wiring-run.log
```
Expected: a `Wired <file>: N spans (M new)` line for every file with Greek content (the 3 pilot files will show `0 spans` since their matchable Greek text is already gone), ending with a `Total: ...` summary line.

- [ ] **Step 2: Lint every view file**

```bash
find views -name '*.php' -print0 | xargs -0 -n1 /c/xampp/php/php.exe -l 2>&1 | grep -v "No syntax errors"
```
Expected: no output (empty = every file is clean). **A lint pass alone does not prove wiring is safe — see Step 2b.**

- [ ] **Step 2b: Verify no PHP structure was lost, across every wired file**

Record the commit before this task ran (Task 2's commit — call it `$TASK2_SHA`), then for every file the run actually touched, confirm its `<?php`/`<?=` tag count grew by exactly its reported span count:

```bash
TASK2_SHA=<the commit hash Task 2 committed — fill in the real value>
grep -oE '^Wired [^:]+: [0-9]+ spans' /tmp/wiring-run.log | sed -E 's/^Wired (.+): ([0-9]+) spans/\1 \2/' | while read -r file spans; do
  before=$(git show "$TASK2_SHA:views/$file" 2>/dev/null | grep -oE '<\?php|<\?=' | wc -l)
  after=$(grep -oE '<\?php|<\?=' "views/$file" | wc -l)
  diff=$((after - before))
  if [ "$diff" != "$spans" ]; then
    echo "MISMATCH: $file — expected +$spans tags, got +$diff"
  fi
done
```
Expected: no `MISMATCH` lines. Any mismatch means a file lost real PHP structure during wiring — stop immediately and treat it as a bug in the wiring script, the same class of bug Task 2 found and fixed (a text-node match spanning across a PHP tag with no real HTML tag between two Greek fragments).

- [ ] **Step 2c: Verify no `t()` call's fallback text looks like leaked HTML attribute syntax, across every wired file**

Run the same content-sanity script from Task 2's Step 4c (adjust the `foreach ($m as $match)` loop to scan every file under `views/`, not just the 3 pilot files — the script as written in Task 2's brief already does this, it wasn't pilot-file-specific).

Expected: `OK: no suspicious t() fallback text found.` Any `SUSPICIOUS FALLBACK TEXT` output means the same class of bug Task 2 found and fixed (a PHP tag inside an open real tag's attribute list) — stop immediately.

- [ ] **Step 3: Verify every `t()` key referenced anywhere in `views/` exists in the database**

```bash
grep -rhoE "t\('[^']+'" views/ | sed -E "s/.*t\('//" | sort -u > /tmp/wired_keys_all.txt
"/c/xampp/mysql/bin/mysql.exe" -u root syndrasi --skip-column-names -e "SELECT str_key FROM translation_keys" | sort -u > /tmp/db_keys_all.txt
comm -23 /tmp/wired_keys_all.txt /tmp/db_keys_all.txt
wc -l /tmp/wired_keys_all.txt
```
Expected: `comm` prints no output. `wc -l` should print a number close to 1801 (the wiring pass reuses one key per unique string per file — some files have internal duplicates that all point at the same key, so the count of *distinct referenced keys* can be slightly under 1801 plus however many new keys Task 2+3 minted).

- [ ] **Step 4: Run the full PHPUnit suite**

```bash
/c/xampp/php/php.exe vendor/bin/phpunit
```
Expected: all tests pass (this exercises login/dashboard/settings pages over real HTTP if a local server is reachable — a real functional signal that wiring didn't break page rendering, beyond just `php -l`).

- [ ] **Step 5: Note the new-keys count for later tasks**

```bash
wc -l storage/logs/wiring-new-keys.log
```
Record this number — Task 4 translates exactly these keys, Task 6 packages exactly these keys into a migration.

- [ ] **Step 6: Commit**

```bash
git add views/ storage/logs/wiring-new-keys.log
git commit -m "Wire all 73 view files to the translation catalog"
```

---

### Task 4: Translate the newly-minted keys

**Files:**
- None (data-only task — writes to the `translation_values` table).

**Interfaces:**
- Consumes: `storage/logs/wiring-new-keys.log` (Task 3), `TranslationString::saveMany()` (existing, from the Languages-tab project).

- [ ] **Step 1: List the new keys with their Greek text and source view**

```bash
sort -u storage/logs/wiring-new-keys.log > /tmp/new_keys.txt
while read -r key; do
  "/c/xampp/mysql/bin/mysql.exe" -u root --default-character-set=utf8mb4 syndrasi -e "
    SELECT tk.str_key, tk.str_group, tv.value FROM translation_keys tk
    JOIN translation_values tv ON tv.key_id = tk.id AND tv.language_code = 'el'
    WHERE tk.str_key = '$key';"
done < /tmp/new_keys.txt
```

- [ ] **Step 2: Read each referenced view file for context**

Every `str_group` printed above corresponds to `views/<str_group>.php`. Read each one in full to see exactly where the new string is used (this is expected to be almost entirely `views/settings/index.php`'s Languages tab and `views/auth/profile.php`'s language picker — both added after the original extraction — but confirm from the actual query output rather than assuming).

- [ ] **Step 3: Translate carefully, using the same domain glossary as the original 1801-string translation pass**

- Δράση/Δράσεις → Event/Events; Ομάδα/Ομάδες → Team/Teams; Εθελοντής → Volunteer; Δήμος → Municipality; Φορέας → Organization; "SynDrasi" never translated. (Full glossary: see any commit message from the original translation batches if more terms are needed.)
- Write and run a PHP script:

```php
<?php
define('BASE_PATH', 'C:/Users/user/Desktop/Syndrasi/syndrasi');
require BASE_PATH . '/app/Helpers/functions.php';
require BASE_PATH . '/app/Models/TranslationString.php';

$translations = [
    // one entry per key printed in Step 1, e.g.:
    // 'settings/index.070' => 'Languages',
];

$rows = [];
foreach ($translations as $key => $value) {
    $keyId = dbq('SELECT id FROM translation_keys WHERE str_key = :k', ['k' => $key])->fetchColumn();
    if ($keyId === false) { echo "MISSING KEY: $key\n"; continue; }
    $rows[] = ['key_id' => (int) $keyId, 'value' => $value];
}
TranslationString::saveMany($rows, 'en');
echo "Saved " . count($rows) . " translations.\n";
```

Run via `/c/xampp/php/php.exe /path/to/script.php`. The printed count must equal the number of unique keys from `/tmp/new_keys.txt`, with zero `MISSING KEY` lines.

- [ ] **Step 4: Verify completeness**

```bash
"/c/xampp/mysql/bin/mysql.exe" -u root syndrasi --skip-column-names -e "
SELECT tk.str_key FROM translation_keys tk
LEFT JOIN translation_values tv ON tv.key_id = tk.id AND tv.language_code = 'en'
WHERE tv.id IS NULL;"
```
Expected: no output (every key, old and new, now has an English value).

- [ ] **Step 5: Commit**

No file changes to commit (data-only) — skip the commit step for this task, or if you created a throwaway translation script, do not commit it (matches how prior translation-pass scratch scripts were kept out of the repo).

---

### Task 5: Fix `views/team/debrief.php` pluralization

**Files:**
- Modify: `views/team/debrief.php:105`

**Interfaces:**
- Consumes: `t()`, `e()` (Task 1).

**Context:** Before Task 3's wiring ran, this line built a Greek plural suffix directly in PHP: `title="<?= $s ?> αστέρ<?= $s === 1 ? 'ι' : 'ια' ?>"`. The wiring pass mechanically (and validly) replaced the static `αστέρ` span with a `t()` call, producing something equivalent to `title="<?= $s ?> <?= e(t('team/debrief.017', 'αστέρ')) ?><?= $s === 1 ? 'ι' : 'ια' ?>"` — syntactically fine, but for an English-preferring user this renders "1 starι" / "5 starια", since the suffix logic itself is still Greek-only. Read the file first to confirm the exact current text at this line (it should closely match the above, but confirm before editing).

- [ ] **Step 1: Mint two new keys — singular and plural — continuing this file's existing numbering**

```bash
"/c/xampp/mysql/bin/mysql.exe" -u root syndrasi --skip-column-names -e "
SELECT MAX(CAST(SUBSTRING_INDEX(str_key, '.', -1) AS UNSIGNED)) FROM translation_keys WHERE str_group = 'team/debrief';"
```
Note the printed number (`N`). Then, using PHP:

```php
<?php
define('BASE_PATH', 'C:/Users/user/Desktop/Syndrasi/syndrasi');
require BASE_PATH . '/app/Helpers/functions.php';
require BASE_PATH . '/app/Models/TranslationString.php';

$n = /* the number printed above */;
$singularKey = sprintf('team/debrief.%03d', $n + 1);
$pluralKey   = sprintf('team/debrief.%03d', $n + 2);

dbq('INSERT INTO translation_keys (str_key, str_group) VALUES (:k, :g)', ['k' => $singularKey, 'g' => 'team/debrief']);
$singularId = db()->lastInsertId();
dbq('INSERT INTO translation_keys (str_key, str_group) VALUES (:k, :g)', ['k' => $pluralKey, 'g' => 'team/debrief']);
$pluralId = db()->lastInsertId();

TranslationString::saveMany([['key_id' => $singularId, 'value' => 'αστέρι']], 'el');
TranslationString::saveMany([['key_id' => $pluralId, 'value' => 'αστέρια']], 'el');
TranslationString::saveMany([['key_id' => $singularId, 'value' => 'star']], 'en');
TranslationString::saveMany([['key_id' => $pluralId, 'value' => 'stars']], 'en');

echo "Singular key: $singularKey, plural key: $pluralKey\n";
```

Record the two printed key names for the next step.

- [ ] **Step 2: Fix the view**

In `views/team/debrief.php`, replace the wired-but-broken line (the exact current text — confirm by reading the file, it will reference `team/debrief.017`) with (substituting the two key names from Step 1):

```php
              <label class="star-label" title="<?= $s ?> <?= e($s === 1 ? t('team/debrief.019', 'αστέρι') : t('team/debrief.020', 'αστέρια')) ?>">
```

(Key numbers `.019`/`.020` are illustrative — use the exact ones Step 1 actually printed.)

- [ ] **Step 3: Lint**

```bash
/c/xampp/php/php.exe -l views/team/debrief.php
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Verify manually**

Log in as an English-preferring test user, open a debrief page with an event you can rate, hover/inspect the star labels for both `$s = 1` and `$s > 1` — confirm "1 star" and e.g. "3 stars" (not "1 starι"). Switch back to Greek and confirm "1 αστέρι" / "3 αστέρια" render exactly as before.

- [ ] **Step 5: Commit**

```bash
git add views/team/debrief.php
git commit -m "Fix team/debrief.php pluralization: separate singular/plural translation keys"
```

---

### Task 6: Release migration for the newly-minted keys

**Files:**
- Create: `database/migrations/044_translation_catalog_new_keys.sql`
- Modify: `database/schema.sql`

**Interfaces:**
- Consumes: the keys in `storage/logs/wiring-new-keys.log` (Task 3) plus the two keys minted in Task 5.

- [ ] **Step 1: Generate the migration**

```php
<?php
define('BASE_PATH', 'C:/Users/user/Desktop/Syndrasi/syndrasi');
require BASE_PATH . '/app/Helpers/functions.php';

function sqlEscape($s) { return str_replace(["\\", "'"], ["\\\\", "''"], $s); }

$keys = array_unique(array_filter(array_map('trim', file(BASE_PATH . '/storage/logs/wiring-new-keys.log'))));
// Add the two team/debrief pluralization keys minted by hand in Task 5:
$keys[] = 'team/debrief.019'; // use the actual key names Task 5 printed
$keys[] = 'team/debrief.020';
$keys = array_unique($keys);

$statements = [];
foreach ($keys as $key) {
    $row = dbq(
        "SELECT tk.str_group, el.value AS el_val, en.value AS en_val
         FROM translation_keys tk
         JOIN translation_values el ON el.key_id = tk.id AND el.language_code = 'el'
         JOIN translation_values en ON en.key_id = tk.id AND en.language_code = 'en'
         WHERE tk.str_key = :k",
        ['k' => $key]
    )->fetch();
    if (!$row) { echo "SKIP (incomplete): $key\n"; continue; }
    $k = sqlEscape($key);
    $g = sqlEscape($row['str_group']);
    $el = sqlEscape($row['el_val']);
    $en = sqlEscape($row['en_val']);
    $statements[] = "INSERT INTO translation_keys (str_key, str_group) VALUES ('$k', '$g') ON DUPLICATE KEY UPDATE str_group = str_group";
    $statements[] = "INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', '$el' FROM translation_keys WHERE str_key = '$k' ON DUPLICATE KEY UPDATE value = '$el'";
    $statements[] = "INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', '$en' FROM translation_keys WHERE str_key = '$k' ON DUPLICATE KEY UPDATE value = '$en'";
}

$header = "-- 044_translation_catalog_new_keys.sql\n"
    . "-- Strings discovered during view-wiring that predated (or postdated) the\n"
    . "-- original extraction — the Languages tab and profile language picker were\n"
    . "-- both added to their view files after migration 042 ran, plus 2 keys from\n"
    . "-- the team/debrief.php pluralization fix. Idempotent (ON DUPLICATE KEY UPDATE).\n\n";

file_put_contents(
    BASE_PATH . '/database/migrations/044_translation_catalog_new_keys.sql',
    $header . implode(";\n", $statements) . ";\n"
);
echo "Wrote " . count($statements) . " statements for " . count($keys) . " keys.\n";
```

Check for any semicolons in the new Greek/English text before finalizing (same constraint as every prior migration in this catalog — `MigrationRunner` splits on raw `;`):

```bash
grep -c ";" database/migrations/044_translation_catalog_new_keys.sql
```
The count should equal the number of statements (one trailing `;` per line) — if any line has more than one `;`, a translated value contains a literal semicolon and must be fixed before this migration is usable.

- [ ] **Step 2: Test the migration on a scratch database**

```bash
"/c/xampp/mysql/bin/mysql.exe" -u root -e "DROP DATABASE IF EXISTS syndrasi_044_check; CREATE DATABASE syndrasi_044_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"/c/xampp/mysql/bin/mysql.exe" -u root syndrasi_044_check < database/schema.sql
"/c/xampp/mysql/bin/mysql.exe" -u root syndrasi_044_check < database/migrations/044_translation_catalog_new_keys.sql
"/c/xampp/mysql/bin/mysql.exe" -u root syndrasi_044_check -e "SELECT COUNT(*) FROM translation_values WHERE language_code = 'en';"
"/c/xampp/mysql/bin/mysql.exe" -u root -e "DROP DATABASE syndrasi_044_check;"
```
Expected: the migration applies with no errors, and the `en` count on the scratch DB now equals 1801 plus the number of new keys.

- [ ] **Step 3: Apply it to the local dev DB through the tracked migration runner**

```bash
/c/xampp/php/php.exe -r "
define('BASE_PATH', __DIR__);
require 'app/Helpers/functions.php';
require 'app/Services/MigrationRunner.php';
print_r(MigrationRunner::runPending());
"
```
Expected: `[applied] => [0] => 044_translation_catalog_new_keys.sql`, `[error] =>` empty.

- [ ] **Step 4: Append the same seed data to `schema.sql`**

Following the exact same pattern as the `043_translation_catalog_en_seed.sql` splice: find the end of the existing translation seed block in `database/schema.sql` (search for the last `INSERT INTO translation_values` line before the `-- Default categories` section), and insert the new migration's statements (its body, minus the header comment) immediately after it, with a short header comment identifying it as coming from migration 044.

- [ ] **Step 5: Verify fresh-install parity**

```bash
"/c/xampp/mysql/bin/mysql.exe" -u root -e "DROP DATABASE IF EXISTS schema_check; CREATE DATABASE schema_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"/c/xampp/mysql/bin/mysql.exe" -u root schema_check < database/schema.sql
"/c/xampp/mysql/bin/mysql.exe" -u root schema_check -e "SELECT language_code, COUNT(*) FROM translation_values GROUP BY language_code;"
"/c/xampp/mysql/bin/mysql.exe" -u root -e "DROP DATABASE schema_check;"
```
Expected: `el` and `en` counts match each other exactly, and match the live DB's counts from Task 6 Step 3.

- [ ] **Step 6: Run `tests/Unit/SchemaMigrationsDriftTest.php`**

```bash
/c/xampp/php/php.exe vendor/bin/phpunit tests/Unit/SchemaMigrationsDriftTest.php
```
Expected: pass (this migration has no `CREATE TABLE`/`ADD COLUMN`, only `INSERT`s, so the drift test's regexes find nothing to check — but it must still run clean).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/044_translation_catalog_new_keys.sql database/schema.sql
git commit -m "Add migration for translation keys discovered during view-wiring"
```

---

### Task 7: Integration test coverage — language actually changes rendering

**Files:**
- Modify: `tests/Integration/LocalHttpTest.php`

**Interfaces:**
- Consumes: `loginAs()` helper, `self::$pdo`, `self::TEST_EMAIL`/`self::TEST_PASS` fixture (all already present from the Languages-tab project's test additions).

- [ ] **Step 1: Add two test methods**

At the end of the class, before the final closing `}`:

```php
    /* ── View wiring actually changes rendered output ─────────────────── */

    public function testDefaultLanguageRendersGreek(): void
    {
        $this->loginAs(self::TEST_EMAIL, self::TEST_PASS);
        [$code, $html] = $this->http('GET', '/profile');
        $this->assertSame(200, $code);
        $this->assertStringContainsString('Το προφίλ μου', $html);
    }

    public function testEnglishLanguagePreferenceChangesRenderedText(): void
    {
        $this->loginAs(self::TEST_EMAIL, self::TEST_PASS);
        [, $html] = $this->http('GET', '/profile');
        $csrf = $this->csrfFrom($html);

        [$code] = $this->http('POST', '/profile/language', [
            'form' => ['language_code' => 'en', '_token' => $csrf],
        ]);
        $this->assertSame(302, $code);

        [$code2, $html2] = $this->http('GET', '/profile');
        $this->assertSame(200, $code2);
        $this->assertStringNotContainsString('Το προφίλ μου', $html2, 'page should no longer render the Greek heading');

        // Reset back to platform default so this test is independently re-runnable
        // and doesn't leak state into other tests sharing the same fixture user.
        [, $html3] = $this->http('GET', '/profile');
        $csrf2 = $this->csrfFrom($html3);
        $this->http('POST', '/profile/language', [
            'form' => ['language_code' => '', '_token' => $csrf2],
        ]);
    }
```

- [ ] **Step 2: Lint**

```bash
/c/xampp/php/php.exe -l tests/Integration/LocalHttpTest.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Run the full suite**

```bash
/c/xampp/php/php.exe vendor/bin/phpunit
```
Expected: all tests pass, including the 2 new ones (if a local server is reachable — otherwise the whole `LocalHttpTest` class skips cleanly, matching this codebase's established convention; if it skips, note that in your report and rely on Task 8's manual browser verification for actual proof).

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/LocalHttpTest.php
git commit -m "Add integration test coverage: language preference changes rendered output"
```

---

### Task 8: Final verification

**Files:**
- None (verification only).

- [ ] **Step 1: Full regression suite**

```bash
find app routes config -name '*.php' -print0 | xargs -0 -n1 /c/xampp/php/php.exe -l 2>&1 | grep -v "No syntax errors"
find views -name '*.php' -print0 | xargs -0 -n1 /c/xampp/php/php.exe -l 2>&1 | grep -v "No syntax errors"
/c/xampp/php/php.exe vendor/bin/phpstan analyse --memory-limit=512M
/c/xampp/php/php.exe vendor/bin/phpunit
```
All four must be clean (no lint output, 0 PHPStan errors, full PHPUnit suite green).

- [ ] **Step 2: Browser spot-check, Greek (default) user**

Log in as a user with no `language_code` set. Load, and visually confirm nothing looks broken or shows literal `t(...)`/PHP-looking text: `/dashboard`, `/admin/settings` (all 5 tabs), `/admin/teams`, `/profile`, `/events` (index and one event's `show` page), `/operations` (index and one active event), a `team/event_show` page, `field/hub` (via a real field token if available), `/login`.

- [ ] **Step 3: Browser spot-check, English user**

Switch the same account's language to English via `/profile`. Reload every page from Step 2 (except `/login`, which is anonymous and should still show Greek). Confirm translated text appears throughout, no broken HTML, no untranslated Greek fragments in places that should now be English (dynamic content like event titles is expected to remain in whatever language it was entered in — that's correct, not a bug).

- [ ] **Step 4: Confirm anonymous pages stay Greek regardless**

While logged out (or in an incognito/private window), load `/login` and any public event link you have — confirm both render Greek even if you just set your own account to English in Step 3 (anonymous requests always resolve to `'el'`, per this project's explicit scope).

- [ ] **Step 5: Report**

Summarize: lint/phpstan/phpunit results, the exact list of pages checked in each language, anything that looked off (even minor), and the final new-key count from Task 3/Task 6.

---

## Post-implementation note

Extraction (`scripts/extract-translation-strings.php`) is now a historical, one-time tool — it won't find new strings inside any file wired by this project, since their Greek text lives in the database, not as raw HTML. Any UI text added to these views going forward needs a manual `t('group.NNN', 'Ελληνικό κείμενο')` call and a corresponding `translation_keys`/`translation_values` row (both languages) — there is no tooling in this project to automate that; noted as a process change for future development, not a task here.
