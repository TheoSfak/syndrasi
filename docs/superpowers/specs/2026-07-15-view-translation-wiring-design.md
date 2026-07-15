# View translation wiring — render the app in each user's chosen language

Date: 2026-07-15
Status: Approved

## Problem

The Languages settings tab (shipped in v0.18.0-beta/v0.19.0-beta) built a complete,
fully-translated catalog of the app's 1801 UI strings (Greek source + English,
1801/1801) and a per-user language preference (`users.language_code`, self-service
picker on `/profile`) — but neither is actually *used* to render anything yet. Every
one of the 73 view files still emits its hardcoded Greek text unconditionally,
regardless of what language a user has selected. This project wires the views to
the catalog so that language choice genuinely takes effect, independently per user
(an admin can view the app in Greek while a team member views it in English,
simultaneously, based purely on each account's own `language_code`).

## Scope

**In scope:**
- `t(string $key, string $fallback = ''): string` render-time lookup helper +
  `current_language(): string` resolver, both in `app/Helpers/functions.php`.
- A new one-time script, `scripts/wire-translations.php`, that mechanically
  replaces the 1801 already-extracted Greek text spans across the 73 view files
  with `<?= e(t('key', 'original Greek fallback')) ?>` calls, reusing the exact
  matching/keying logic already proven correct by `scripts/extract-translation-strings.php`.
- Running that script once, across all 73 files, and fixing up anything it
  can't safely auto-wire (currently known: `views/team/debrief.php`'s hardcoded
  Greek plural suffix logic).
- Full verification: `php -l` on every changed file, the existing PHPUnit suite,
  and a representative browser spot-check in both languages.

**Out of scope:**
- Anything not already in the translation catalog — dynamic/user-entered content
  (event titles, descriptions, municipality names, category names) was never
  extracted and stays as-is; this project only wires the 1801 static UI strings.
- Strings extraction already skipped (34 remaining, contain a literal `;`) —
  these stay permanently hardcoded Greek; not addressed here.
- Any UI for switching language beyond the existing `/profile` picker (no header
  quick-switcher, per explicit decision).
- Tooling to keep the catalog in sync as developers add new UI text going
  forward — after this ships, `extract-translation-strings.php` no longer finds
  anything inside already-wired files (their Greek text now lives in the DB, not
  in raw HTML), so future UI text needs manual `t()` calls. Out of scope to
  automate; noted as a process change for future development.

## Language resolution

```php
function current_language(): string
{
    $user = current_user();
    return $user['language_code'] ?? 'el';
}
```

Anonymous requests (no session — login page, public event links, the no-login
field hub) always resolve to `'el'`, the source language, per explicit decision —
no browser-language detection. `current_user()['language_code'] ?? 'el'` is safe
even when `current_user()` returns `null`: PHP's `??` does not warn on array
access into `null`.

## The `t()` helper

```php
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

One query per language per request (not per string), via a request-lifetime
static cache — 1801 rows is trivial to hold in memory. Every wired call site
supplies its own `$fallback` (the original Greek), so the "look up source
language" branch only matters for hand-written `t()` calls that omit it.

**Escaping**: every call site wraps `t()` in `e()` — `<?= e(t('key', 'fallback')) ?>` —
matching how every other piece of dynamic content in this codebase is already
rendered. Checked empirically: none of the 1801 stored translations contain
HTML entities (so no double-escaping risk), but 13 English translations contain
a literal `"` from natural phrasing (e.g. `Confirmed "On My Way"`) — `e()`
handles that correctly in both text-node and attribute position, which is why
`t()` itself does *not* escape (kept as a pure data-fetch function, escaping is
the call site's job, consistent with how `e($var)` is used everywhere else in
this codebase).

## The wiring script

`scripts/wire-translations.php` reuses the *exact* matching and key-assignment
logic from `scripts/extract-translation-strings.php` — same regexes, same
PHP/`<script>`-blanking, same order (all text-node matches in a file first,
*then* all attribute matches), same per-file duplicate-text dedup (a string
repeated verbatim in one file reuses the first occurrence's key) — so every key
it computes is guaranteed to already exist in `translation_keys`, with no drift
between what extraction found and what wiring replaces.

It differs from extraction in two ways:

1. **It records byte offsets, not SQL statements.** Blanking PHP/`<script>`
   blocks before matching replaces them with equal-length runs of spaces, which
   means every other byte in the file keeps its exact original offset — so
   offsets computed against the blanked copy (via `PREG_OFFSET_CAPTURE`) are
   valid positions in the real, unblanked file content.
2. **It replaces every occurrence of a repeated string, not just the first.**
   Extraction's dedup means a string repeated three times in one file only ever
   got *one* key — but all three occurrences must now be replaced with a call to
   that *same* key, not just the first one. The script tracks, per file, a map
   of `cleaned text → key` (reusing the exact same cleaning/dedup rule) and
   replaces every raw match whose cleaned text is in that map.

Replacements are collected as a list of `(start_offset, length, replacement)`
and applied to the real file content **back-to-front** (highest offset first),
so splicing an earlier replacement never invalidates a not-yet-applied later
offset.

- A text-node match `>Ρυθμίσεις Πλατφόρμας<` (inner span only, not the
  delimiters) becomes `<?= e(t('settings/index.001', 'Ρυθμίσεις Πλατφόρμας')) ?>`.
- An attribute match `placeholder="Αναζήτηση"` (value span only, quotes
  untouched) becomes `placeholder="<?= e(t('field/hub.010', 'Αναζήτηση')) ?>"`.

The fallback argument is the exact original text (PHP-string-escaped: `\` → `\\`,
`'` → `\'`), not a re-fetch from the database — so even if the DB were wiped, the
page would still render its original Greek content.

Matches extraction already skipped (contain `;`, or under 2 characters) are left
untouched by the wiring script too — they have no key, so there is nothing to
replace them with; they remain hardcoded Greek.

## Known special case: `views/team/debrief.php`

Line ~105 builds a Greek plural suffix directly in PHP string concatenation
(`&lt;?= $s ?&gt; αστέρ&lt;?= $s === 1 ? 'ι' : 'ια' ?&gt;`) around the already-extracted
key `team/debrief.017` (Greek stem `"αστέρ"`). Auto-wiring that key alone would
produce grammatically broken English ("1 starι"/"5 starια") once rendered for an
English-preferring user. This one file gets a manual fix as part of this
project: replace the hardcoded suffix logic with two full translation keys (one
for the singular, one for the plural — already-established codebase pattern for
this kind of thing, e.g. `gr_number()`'s style) rather than stem+suffix
concatenation, so both languages pluralize correctly.

## Testing

- `php -l` on every one of the 73 modified view files — must be 100% clean
  before proceeding.
- Full existing PHPUnit suite (currently 40 tests: pure-logic unit tests plus
  live HTTP integration tests covering login, dashboard, settings, and the
  operations stream) must stay green — a real functional regression signal, not
  just a syntax check.
- Before running the script across all 73 files, prove it out against a small,
  deliberately diverse pilot set (e.g. `views/auth/login.php`, one small file;
  `views/settings/index.php`, one large file with the Languages tab itself in
  it; `views/team/event_show.php`, a file with several of the gap-preserving
  "dynamic value stripped" strings) — confirm Greek rendering is byte-identical
  to before wiring, and English rendering shows translated text with no broken
  HTML.
- After running across all 73 files: browser spot-check ~12–15 representative
  pages (covering settings, dashboard, events, field hub, operations, auth) as
  both a Greek-preferring and an English-preferring user — not an exhaustive
  click-through of all 73, which the automated checks above are relied on to
  cover at the syntax/regression level.

## Rollout

No new migration needed — the schema (`languages`, `translation_keys`,
`translation_values`, `users.language_code`) and the full bilingual catalog
already shipped in v0.18.0-beta/v0.19.0-beta and are already on every install
that's updated. This project only changes application code (the two new helper
functions plus the 73 view files), so it ships as a normal code release — no
new migration, no schema.sql change.
