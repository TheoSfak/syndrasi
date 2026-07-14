# Languages tab in Settings — translation catalog infrastructure

Date: 2026-07-14
Status: Approved

## Problem

SynDrasi's UI is entirely hardcoded Greek text across 73 view files — there is no
translation function, language table, or i18n mechanism anywhere in the codebase.
The first step toward supporting English (and future languages) is an admin-facing
tool to build and maintain a catalog of translatable strings, without yet rewiring
the app to actually render in a chosen language.

## Scope

**In scope (this project):**
- `languages` and `translation_strings` DB tables, seeded with Greek (source) and English.
- A one-time extraction pass that scans the 73 views for human-readable Greek text
  and seeds `translation_strings` with real keys/values (not placeholder data).
- A new "Γλώσσες" (Languages) tab in `/admin/settings`, gated by the existing
  `Role::MUNICIPALITY_ADMIN` check that already covers the whole Settings page.
- A searchable/filterable table UI where an admin picks a source language (controls
  which column shows as read-only reference) and edits the English translation for
  each string, with bulk save.

**Out of scope (future project):**
- Rewiring the 73 views to actually read from `translation_strings` and render in
  the selected language.
- An end-user-facing language switcher.
- Adding languages beyond Greek/English through the UI (schema supports it; no CRUD
  UI for languages themselves in this slice).
- Machine-translation assistance (auto-suggest via an external API).

## Data model

New migration `041_translation_strings.sql`:

```sql
CREATE TABLE languages (
  code        VARCHAR(10) PRIMARY KEY,   -- 'el', 'en'
  name        VARCHAR(64) NOT NULL,      -- 'Ελληνικά', 'English'
  is_source   TINYINT(1) NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1
);

INSERT INTO languages (code, name, is_source, is_active) VALUES
  ('el', 'Ελληνικά', 1, 1),
  ('en', 'English',  0, 1);

CREATE TABLE translation_strings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  str_key     VARCHAR(190) NOT NULL UNIQUE,  -- e.g. 'settings/index.title'
  str_group   VARCHAR(120) NOT NULL,         -- source view/module, e.g. 'settings/index'
  el          TEXT NOT NULL,
  en          TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (str_group)
);
```

`el` is the extracted Greek source text (always present). `en` starts `NULL`/empty
and is what admins fill in. A string is "missing" when `en` is null or empty.

Only two languages exist for now, so "source language" in the UI is really a
toggle for which of the two columns is shown read-only vs. editable — but the
schema (two independent columns keyed by language code convention `str_key`/`el`/`en`)
is deliberately simple rather than a 3-table normalized design, since a third
language isn't planned in this slice. If a third language is added later, this
table would need a column added or a follow-up migration to a proper
key/value-per-language shape — acceptable, since YAGNI applies today.

## Extraction pass

A one-time PHP script (`scripts/extract-translation-strings.php`, run manually, not
part of the app runtime) walks all files under `views/`, pulls out:
- Text nodes containing Greek characters, ignoring pure-whitespace/pure-punctuation.
- Values of `placeholder`, `title`, `alt`, `aria-label` attributes, and text content
  of `<button>`/`<option>`/`<label>` elements.

For each match it generates a key `"<relative-view-path-without-ext>.<slug-of-text-or-index>"`
and writes an INSERT into a generated SQL seed file, which becomes part of migration
`041`. Duplicate identical strings within the same file collapse to one key reused;
identical strings across different files get separate keys (kept per-file to avoid
accidentally coupling unrelated screens to one shared translation later).

This script is run once during implementation to produce the seed data checked into
the migration. It is not wired into the app and does not run automatically again —
re-running it later (when views actually get wired to translation keys) is a
follow-up project's concern.

## Backend

New model `app/Models/TranslationString.php`, following the existing static-method
style used by `MunicipalitySetting`:
- `search(array $filters, int $page): array` — filters: `q` (search source+target
  text), `status` (`all`|`missing`|`translated`), `group`. Paginated (50/page).
- `saveMany(array $rows)` — bulk upsert `en` (and `el`, if an admin corrects the
  source wording) by `id`, in a transaction, mirroring
  `MunicipalitySetting::setMany`'s `ON DUPLICATE KEY UPDATE` pattern.

New model `app/Models/Language.php`:
- `all(): array` — the two seeded rows, for populating the source-language dropdown.

`SettingsController` additions (both under the existing
`requireRole([Role::MUNICIPALITY_ADMIN])` guard used by every other method in this
controller):
- Extend the existing settings-index data load to include language list + first
  page of strings (so the tab renders without an extra request, consistent with
  how Cron/Updates tabs are populated today).
- `POST /admin/settings/languages/save` — accepts an array of `{id, en}` (or
  `{id, el, en}`), calls `TranslationString::saveMany()`, redirects back to
  `/admin/settings#languages` with a flash message, matching the existing
  pattern for other Settings forms on this page (e.g. `platform_announcement` save).
- `GET /admin/settings/languages/search` — AJAX endpoint returning a JSON page of
  filtered/searched rows for the table (search-as-you-type without a full page
  reload), since ~hundreds of rows shouldn't all be paginated via full-page POSTs.

## UI

New tab button next to Danger Zone in `views/settings/index.php`'s `#settingsTabs`:

```html
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-languages" type="button">
  <i class="bi bi-translate me-1"></i>Γλώσσες
</button>
```

Tab pane contents:
- Header row: source-language `<select>` (Ελληνικά / English, default Ελληνικά),
  a search input, and status filter chips (Όλα / Χωρίς μετάφραση / Μεταφρασμένα).
- Table: columns are **Κλειδί** (key, small/muted, with group as a badge),
  **Πηγή** (source text, read-only, reflects the selected source language),
  **Μετάφραση** (target text, editable `<input>`/`<textarea>` depending on length),
  a small dot/badge indicating missing vs. translated.
- Changes are tracked client-side (dirty rows highlighted); a "Αποθήκευση αλλαγών"
  button at the top/bottom submits all dirty rows in one `saveMany()` call.
- Search/filter changes trigger the AJAX search endpoint and re-render the table
  body without a full page reload, consistent with light JS already used elsewhere
  in this view (e.g. the hash-based tab activation script at the bottom of
  `index.php`).
- Pagination controls (Προηγούμενο/Επόμενο) below the table.

## Error handling

- `saveMany()` runs in a transaction; on failure, nothing is saved and a flash
  error is shown — same pattern as `MunicipalitySetting::setMany`.
- The AJAX search endpoint validates `status`/`page` params defensively (unknown
  status falls back to `all`, non-numeric page falls back to `1`) since this is
  user-controlled query input.
- Extraction script is a one-time dev-time tool; it is not expected to handle
  malformed views gracefully beyond skipping files it can't parse and logging
  which ones were skipped, so a human can check them manually.

## Testing

- Migration applies cleanly on a fresh DB and on top of the existing 40 migrations.
- `TranslationString::search()` / `saveMany()` covered by existing test patterns
  in `tests/` (check `MunicipalitySetting` or similar model for the test style
  already used in this repo before writing new tests).
- Manual verification: load `/admin/settings#languages` as a municipality admin,
  confirm the tab renders, search/filter works, editing + saving a translation
  persists and reloads correctly, and that a non-admin role is still blocked from
  the whole Settings page as before.
