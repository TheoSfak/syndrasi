# Languages tab in Settings — translation catalog + per-user language preference

Date: 2026-07-14
Status: Approved (revised — see "Revision" note)

## Problem

SynDrasi's UI is entirely hardcoded Greek text across 73 view files — there is no
translation function, language table, or i18n mechanism anywhere in the codebase.
Language must ultimately be **independent per user**: a municipality admin might
run their panel in Greek while a team member views theirs in English or German.
This project builds the foundation for that — the translation catalog, language
management, and per-user preference storage — without yet rewiring the 73 views
to actually render in the chosen language.

## Revision note

The original version of this spec fixed the schema to exactly two languages
(Greek/English, flat columns) and deferred per-user preference entirely to a
future project. That was revised after clarifying that language choice is
per-user and that German is a real near-term target, not a hypothetical. This
version uses a normalized N-language schema and adds per-user preference storage
now. The view-wiring project (making the 73 views actually render translated
text) is still out of scope here — see below.

## Scope

**In scope (this project):**
- `languages`, `translation_keys`, `translation_values` tables supporting any
  number of languages, with full CRUD for languages (add/edit/deactivate) in the
  admin UI — not just a fixed Greek/English pair.
- A one-time extraction pass that scans the 73 views for human-readable Greek
  text and seeds the catalog with real keys/values (Greek populated; other
  languages start empty).
- A new "Γλώσσες" (Languages) tab in `/admin/settings`, gated by the existing
  `Role::MUNICIPALITY_ADMIN` check that already covers the whole Settings page.
- A searchable/filterable table UI where an admin picks a reference language and
  an editing language, and translates strings into the editing language, with
  bulk save.
- A `language_code` column on `users`, and a self-service language picker on the
  existing `/profile` page (used by every role) so each user sets their own
  language independently.

**Out of scope (future project):**
- Rewiring the 73 views to actually read from the catalog and render in each
  user's chosen language. Setting a language in `/profile` in this slice stores
  the preference only — it does not yet change anything rendered, and the picker
  says so.
- Machine-translation assistance (auto-suggest via an external API).
- Admin overriding a team member's language for them (self-service only for now).

## Data model

New migration `041_translation_catalog.sql`:

```sql
CREATE TABLE languages (
  code        VARCHAR(10) PRIMARY KEY,   -- 'el', 'en', 'de', ...
  name        VARCHAR(64) NOT NULL,      -- 'Ελληνικά', 'English', 'Deutsch'
  is_source   TINYINT(1) NOT NULL DEFAULT 0,  -- exactly one language is the extraction source
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0
);

INSERT INTO languages (code, name, is_source, is_active, sort_order) VALUES
  ('el', 'Ελληνικά', 1, 1, 0),
  ('en', 'English',  0, 1, 1);

CREATE TABLE translation_keys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  str_key     VARCHAR(190) NOT NULL UNIQUE,  -- e.g. 'settings/index.title'
  str_group   VARCHAR(120) NOT NULL,         -- source view/module, e.g. 'settings/index'
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (str_group)
);

CREATE TABLE translation_values (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  key_id         INT NOT NULL,
  language_code  VARCHAR(10) NOT NULL,
  value          TEXT NOT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE (key_id, language_code),
  FOREIGN KEY (key_id) REFERENCES translation_keys(id) ON DELETE CASCADE,
  FOREIGN KEY (language_code) REFERENCES languages(code)
);
```

Adding German later is a data operation (`INSERT INTO languages ...` via the new
admin UI), not a schema migration. A string is "missing" for a given language when
no `translation_values` row exists for that `(key_id, language_code)`.

`ALTER TABLE users ADD COLUMN language_code VARCHAR(10) NULL DEFAULT NULL, ADD FOREIGN KEY (language_code) REFERENCES languages(code);`
`NULL` means "use the platform default" (the `is_source` language) rather than
duplicating that default into every row.

## Extraction pass

A one-time PHP script (`scripts/extract-translation-strings.php`, run manually
during implementation, not part of the app runtime) walks all files under
`views/`, pulls out:
- Text nodes containing Greek characters, ignoring pure-whitespace/punctuation.
- Values of `placeholder`, `title`, `alt`, `aria-label` attributes, and text
  content of `<button>`/`<option>`/`<label>` elements.

For each match it generates a key `"<relative-view-path-without-ext>.<slug>"` and
writes an INSERT into a generated SQL seed file (part of migration `041`),
inserting one `translation_keys` row plus one `translation_values` row
(`language_code = 'el'`) per string. Duplicate identical strings within the same
file collapse to one key reused; identical strings across different files get
separate keys (kept per-file to avoid coupling unrelated screens later).

This script runs once to produce the seed data checked into the migration. It is
not wired into the app; re-running it later is the view-wiring project's concern.

## Backend

New model `app/Models/TranslationString.php` (static-method style, matching
`MunicipalitySetting`):
- `search(array $filters, int $page): array` — filters: `q` (search reference +
  target text), `status` (`all`|`missing`|`translated`), `group`, `refLang`,
  `targetLang`. Paginated (50/page). Joins `translation_keys` to both language's
  `translation_values` rows.
- `saveMany(array $rows, string $languageCode)` — bulk upsert `value` per
  `key_id` for the given language, in a transaction
  (`INSERT ... ON DUPLICATE KEY UPDATE`, matching `MunicipalitySetting::setMany`).

New model `app/Models/Language.php`:
- `all(bool $activeOnly = false): array`
- `create(string $code, string $name): void`
- `setActive(string $code, bool $active): void`
- `source(): array` — the `is_source = 1` row.

`SettingsController` additions, under the existing
`requireRole([Role::MUNICIPALITY_ADMIN])` guard:
- Extend the settings-index data load to include the language list + first page
  of strings for the default reference/target pair (Greek → English), consistent
  with how Cron/Updates tabs are populated today.
- `POST /admin/settings/languages/save` — `{languageCode, rows: [{key_id, value}]}`,
  calls `TranslationString::saveMany()`, redirects back to
  `/admin/settings#languages` with a flash message.
- `GET /admin/settings/languages/search` — AJAX endpoint returning a JSON page of
  filtered/searched rows (search-as-you-type without full-page reload).
- `POST /admin/settings/languages/add` — `{code, name}`, calls `Language::create()`.
- `POST /admin/settings/languages/toggle` — `{code, active}`, calls
  `Language::setActive()`. Deactivating the `is_source` language is rejected.

`AuthController` addition:
- `POST /profile/language` — `{language_code}` (must be an active language code
  or empty for "platform default"), updates the logged-in user's own
  `users.language_code`, redirects back to `/profile` with a flash message. No
  role check needed beyond being logged in — self-service only.

## UI

**Settings → Languages tab** — new tab button next to Danger Zone in
`views/settings/index.php`'s `#settingsTabs`:

```html
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-languages" type="button">
  <i class="bi bi-translate me-1"></i>Γλώσσες
</button>
```

Tab pane:
- A small "languages" card at the top: list of languages (name, code, active
  toggle, source badge) with an "add language" inline form (code + name) —
  this is how German gets added when it's ready.
- Below it, the translation table: reference-language `<select>` and
  target-language `<select>` (defaults: Ελληνικά → English), search input, and
  status filter chips (Όλα / Χωρίς μετάφραση / Μεταφρασμένα).
- Table columns: **Κλειδί** (key, muted, with group as a badge), **Αναφορά**
  (reference text, read-only, reflects the selected reference language),
  **Μετάφραση** (target text, editable, for the selected target language), a
  small badge for missing vs. translated.
- Dirty rows are tracked client-side and highlighted; a "Αποθήκευση αλλαγών"
  button submits all dirty rows for the current target language in one
  `saveMany()` call.
- Search/filter changes hit the AJAX search endpoint and re-render the table
  body, consistent with the light JS already used in this view (e.g. the
  hash-based tab activation script at the bottom of `index.php`).
- Pagination controls (Προηγούμενο/Επόμενο) below the table.

**Profile → language picker** — new card (or field in the existing "Στοιχεία"
card) in `views/auth/profile.php`:
- A `<select>` of active languages, current value = `user.language_code` (or
  blank/"— προεπιλογή —" if null), posting to `/profile/language`.
- Small muted helper text under it: "Η επιλογή γλώσσας θα εφαρμοστεί σε επόμενη
  ενημέρωση της εφαρμογής." (selection is stored but not yet applied to the
  rendered UI) — so users aren't confused when nothing visibly changes.

## Error handling

- `saveMany()` and language add/toggle run in transactions; on failure nothing
  is saved and a flash error is shown, matching `MunicipalitySetting::setMany`.
- The AJAX search endpoint validates `status`/`page`/`refLang`/`targetLang`
  defensively (unknown values fall back to sane defaults) since these are
  user-controlled query params.
- `Language::setActive('el', false)` (or whichever code is `is_source`) is
  rejected with a flash error — the source language must always stay active.
- `/profile/language` rejects any `language_code` that isn't null/empty or an
  active language code (guards against posting an inactive/unknown code).
- Extraction script is a one-time dev-time tool; skips files it can't parse and
  logs which ones were skipped for manual follow-up, rather than failing hard.

## Testing

- Migration applies cleanly on a fresh DB and on top of the existing 40
  migrations, including the `users.language_code` FK.
- `TranslationString::search()` / `saveMany()` and `Language::create()` /
  `setActive()` covered by tests following the existing test style used for
  other models in `tests/`.
- Manual verification: load `/admin/settings#languages` as a municipality admin;
  confirm the tab renders, adding a language (e.g. Deutsch) works, search/filter
  works, editing + saving a translation for a given target language persists and
  reloads correctly. Confirm `/profile` shows the language picker for every role,
  saving it persists `users.language_code`, and a non-admin role is still blocked
  from the rest of the Settings page as before.
