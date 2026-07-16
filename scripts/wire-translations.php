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
 * Safe to re-run: an already-wired file's Greek text now lives inside PHP
 * echo tags, which get blanked before matching, so re-running finds nothing
 * left to replace in that file.
 *
 * IMPORTANT — text-node blanking is context-aware, not a single blind rule:
 * A blanked PHP/<script> span must become a hard tag-like boundary when it
 * sits BETWEEN two real HTML tags (otherwise two Greek fragments separated
 * only by e.g. a PHP `else:` tag merge into one over-wide match, and
 * replacing that match deletes the tag — the first bug found while building
 * this script). But it must NOT become a boundary when it sits INSIDE an
 * already-open real tag's attribute list (e.g. a `<form>` tag whose
 * `action` attribute is a PHP echo, followed by an `onsubmit` attribute
 * containing Greek text) — treating that as a boundary makes the >...<
 * regex mistake the rest of the tag's real attribute syntax (a closing
 * quote, an attribute name) for a text node, and replacing THAT match
 * deletes real HTML structure while still passing both `php -l` and a naive
 * tag-count check, since a new valid PHP echo tag is also being added at
 * the same time (the second bug found). So:
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
 * Two further, independent safety nets, both applied per-match right before
 * it's accepted for replacement:
 * 1. Any candidate whose cleaned text looks like leaked HTML attribute
 *    syntax (starts with a bare `"`, or contains `="` or `='`) is rejected
 *    outright — genuine translatable UI text never looks like this.
 * 2. Before replacing, each match's raw offset/length is first trimmed to
 *    strip any leading/trailing blanked-PHP padding (an attribute match can
 *    capture up to its closing quote, which may be *past* a live dynamic
 *    value the blanking turned to spaces — e.g. a `title` attribute holding
 *    static text followed by a PHP echo of a variable). Then, unconditionally,
 *    if the real (unblanked) bytes at that trimmed range still contain a
 *    literal PHP opening tag, the match is rejected outright rather than
 *    replaced. This is the authoritative gate: it does
 *    not depend on correctly anticipating every HTML/PHP nesting pattern —
 *    it directly enforces the one invariant that matters ("never delete
 *    live PHP"), so any remaining edge case the boundary logic hasn't
 *    anticipated fails safe (a string stays hardcoded) instead of silently
 *    corrupting a file.
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

/** Blank PHP open/close tag pairs and script blocks to pure spaces. Also
 *  the exact blanking attribute matching uses, unchanged. */
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
         WHERE tk.str_group = :g
         ORDER BY tk.id",
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
        // Attribute matches can capture blanked-PHP padding at their edges
        // (e.g. a title attribute holding Greek text followed by a PHP echo
        // of a variable blanks to the Greek text plus a run of trailing
        // spaces) — the trailing spaces are really a LIVE PHP call in the
        // raw file, not padding to discard. Trim that
        // padding from the OFFSET/LENGTH used for replacement, not just from
        // the display string — cleanText() alone only fixes the display
        // string and left the actual delete-range spanning into live PHP.
        $text = $match['text'];
        $leadingWs = strlen($text) - strlen(ltrim($text));
        $core = rtrim(ltrim($text));
        $offset = $match['offset'] + $leadingWs;
        $length = strlen($core);

        $clean = cleanText($core);
        if ($clean === '' || mb_strlen($clean) < 2 || strpos($clean, ';') !== false) {
            continue;
        }
        if (looksLikeAttributeSyntax($clean)) {
            echo "  SKIPPED (looks like attribute syntax): \"$clean\"\n";
            continue;
        }
        // Final, unconditional safety gate: never delete a byte range that
        // still contains a live <?php/<?= tag in the real file, no matter
        // how the match's boundaries were derived. Catches middle-of-match
        // PHP (e.g. two Greek fragments either side of a dynamic value in
        // one attribute) that edge-trimming alone can't isolate. A rejection
        // here just leaves this one string hardcoded — safe; wiring is
        // best-effort, never deleting live code is not negotiable.
        $rawSpan = substr($raw, $offset, $length);
        if (preg_match('/<\?php|<\?=/', $rawSpan)) {
            echo "  SKIPPED (replacement span would delete live PHP): \"$clean\"\n";
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
            'offset' => $offset,
            'length' => $length,
            'key' => $key,
            'fallback' => $clean,
        ];
    }

    if ($newRows) {
        $pdo->beginTransaction();
        foreach ($newRows as $key => $clean) {
            dbq('INSERT INTO translation_keys (str_key, str_group) VALUES (:k, :g)', ['k' => $key, 'g' => $group]);
            $keyId = $pdo->lastInsertId();
            dbq("INSERT INTO translation_values (key_id, language_code, value) VALUES (:kid, 'el', :v)", ['kid' => $keyId, 'v' => $clean]);
        }
        $pdo->commit();
        foreach ($newRows as $key => $clean) {
            file_put_contents($newKeysLog, $key . "\n", FILE_APPEND);
            $totalNewKeys++;
            echo "  NEW KEY: $key = \"$clean\"\n";
        }
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
