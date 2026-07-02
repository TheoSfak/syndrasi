# SynDrasi — Release & Deployment Workflow

> Standing workflow for shipping SynDrasi. Follow every step. **Do not skip the Release.**

## ✅ Git: Claude runs everything via the Bash tool

For **commit / tag / release**, Claude runs all git commands directly via the
**Bash tool** (same as VS Code). No need for the owner to run anything manually.

Do **NOT** use computer-use GUI tools (Git GUI / Explorer / .bat) — those are
unreliable. The Bash tool is fine.

Typical release sequence Claude runs:
```bash
cd /c/Users/user/Desktop/Syndrasi/syndrasi
git add -A
git commit -m "vX.Y.Z: short summary"
git push origin main
git tag vX.Y.Z
git push origin vX.Y.Z
gh release create vX.Y.Z --repo TheoSfak/syndrasi --title "vX.Y.Z" --notes "..."
```

## How we work

1. **Edit in the Desktop git repo** (`C:\Users\user\Desktop\Syndrasi\syndrasi`) — this is the source of truth and the git repo.
2. **Write the same file to htdocs** (`C:\xampp\htdocs\syndrasi`) for local XAMPP testing — always dual-write both locations at the same time using the Write/Edit tools. **Never rely on bash `cp`/rsync to sync** (bash mount truncates files written by the Write/Edit tools).
3. **Commit** the changes to git from the Desktop folder.
4. **Tag** a new version (bump `VERSION`, e.g. `v0.9.7`).
5. **Create the actual GitHub _Release_** for that tag — ⚠️ **NEVER FORGET THIS.**

## Why the Release matters

The in-app self-updater (Platform Settings → **Updates**) checks GitHub for the
newest version. It prefers a published **Release**; it will fall back to the newest
**tag** if no Release exists. A tag *without* a Release still works for the updater,
but a Release is the intended deliverable — it carries release notes and is what the
"Check for updates" / "Update" buttons surface to admins.

**Rule of thumb:** a tag is not done until it also has a Release.

## Pre-push safety check

The GitHub repo (`TheoSfak/syndrasi`) must contain the **latest** code before anyone
runs "Update" on another install. If you tag/release an older state, installs that
apply the update will roll back to it. Always: **push current code → bump VERSION →
tag → publish Release**, in that order.

## Quick reference

- Repo: `TheoSfak/syndrasi` (public) — configured in `config/update.php`
- Version source of truth: `VERSION` file (keep it in sync with the tag/Release)
- Migrations run automatically on update via `schema_migrations` (see MigrationRunner)
- Updater preserves `config/` and `storage/`, and backs up to `storage/backups/`

---

## 🔧 Assistant working notes (Cowork / Claude) — IMPORTANT for new sessions

**Source of truth: Desktop git repo** (`C:\Users\user\Desktop\Syndrasi\syndrasi`).
**Live test copy: htdocs** (`C:\xampp\htdocs\syndrasi`).

**Correct workflow:**
1. Write/Edit every file to the **Desktop path first** (source of truth).
2. Immediately write the **same content** to the **htdocs path** using the Write tool (for XAMPP testing).
3. Run git from Desktop.

**Mount staleness gotcha — why bash `cp` is banned:** the bash mount serves
**stale/truncated reads** of ANY file recently written by the Write/Edit tools,
regardless of whether it's in Desktop or htdocs. So:

- **Do NOT use bash `cp` or `rsync` to sync the two folders** — it silently copies truncated content. This is what caused v0.9.4/v0.9.5 packaging failures.
- **Always dual-write** both paths in the same session using the Write/Edit tools.
- `git add/commit/push/tag` via Bash is fine — git reads directly from disk.

**PHP is NOT available in the sandbox** (`php -l` can't run, no root to install). So
PHP syntax must be eye-checked, and the **owner should smoke-test in XAMPP** before
releasing.

## 🔁 Release sync — ALWAYS mirror, NEVER cherry-pick files

**Lesson learned (v0.9.2 shipped broken):** syncing htdocs→repo with a hand-picked
`cp file1 file2 …` list silently left new files out of the release — missing models
(`EventRoomMessage`, `PhotoRequest`, `EventPhoto`), a view, and migrations `011`/`015`.
Production then "updated" to code that fatally errored / had no DB tables.

**Rule:** before every release, mirror the WHOLE tree, then verify the diff is clean.

```bash
# 1. Mirror htdocs → repo (preserve repo-local dirs; don't ship local junk)
rsync -a \
  --exclude='.git/' --exclude='config/' --exclude='storage/' \
  --exclude='_backups/' --exclude='.gitignore' \
  --exclude='node_modules/' --exclude='vendor/' --exclude='CODE_SMELL_REPORT.md' \
  /c/xampp/htdocs/syndrasi/ /c/Users/user/Desktop/Syndrasi/syndrasi/

# 2. Prove the repo matches htdocs (must print nothing but the excludes)
diff -rq -x .git -x config -x storage -x _backups -x .gitignore -x vendor \
  /c/xampp/htdocs/syndrasi /c/Users/user/Desktop/Syndrasi/syndrasi
```

Pay special attention to NEW files (models, controllers, views, and **every**
`database/migrations/*.sql`) — a missing migration file means the in-app updater has
nothing to run, so the feature's table never gets created on production.

## 🆕 Fresh install (new database)

`schema.sql` is a **full** dump of the schema produced by every file in
`database/migrations/` — regenerate it whenever you add a migration (see
below), and a brand-new DB needs nothing else:

```bash
mysql -u root -p < database/schema.sql
```

You do **not** need to run `database/migrations/*.sql` by hand on a fresh
install. On first request, `MigrationRunner::ensureInitialised()` sees the
`users` table already exists and "baselines" every migration file currently
on disk as already-applied (recorded in `schema_migrations`, not re-run) —
because their effect is already present in `schema.sql`. The in-app
self-updater still auto-runs any *pending* migrations that are added after
that point, same as on an existing install.

### Regenerating schema.sql (release step)

Whenever a new `database/migrations/NNN_*.sql` file is added, regenerate
`schema.sql` from a fully-migrated dev DB before releasing, so it never
drifts from what the migrations actually produce (this is what caused
`schema.sql` to be missing ~26 tables prior to 2026-07-02):

```bash
mysqldump -u root --no-data --skip-comments --skip-add-locks --skip-set-charset syndrasi > /tmp/dump.sql
```

Hand-clean the dump into `schema.sql`'s existing style (uppercase types, no
backticks, `INDEX name (col)` instead of `KEY`, drop `AUTO_INCREMENT=N`
starting values, keep real `CONSTRAINT ... FOREIGN KEY` clauses verbatim),
then diff a fresh import against the live DB to confirm they match:

```bash
mysql -u root -e "SHOW TABLES FROM syndrasi;" | sort > live_tables.txt
mysql -u root -e "CREATE DATABASE schema_check; USE schema_check; SOURCE database/schema.sql;"
mysql -u root -e "SHOW TABLES FROM schema_check;" | sort > fresh_tables.txt
diff fresh_tables.txt live_tables.txt   # must be empty
mysqldump -u root --no-data schema_check > fresh_structure.sql
mysqldump -u root --no-data syndrasi > live_structure.sql
diff <(sed -E 's/ AUTO_INCREMENT=[0-9]+//' fresh_structure.sql) <(sed -E 's/ AUTO_INCREMENT=[0-9]+//' live_structure.sql)   # must be empty
mysql -u root -e "DROP DATABASE schema_check;"
```

## ✅ Current status / work log (last updated 2026-06-17)

### Released on GitHub: **v0.9.3-beta** (Latest) — production is LIVE on it
Production self-updated to `0.9.3-beta`; all **15 migrations** applied; tables
`event_room_messages` + `event_photos` confirmed present.

Release history of this push:
- **v0.9.2-beta** — Δωμάτιο Επιχείρησης (shared event room chat, table
  `event_room_messages` / migration `015`, model `EventRoomMessage`, panel on command /
  team / Live / field surfaces); smsbox.gr SMS driver + Δοκιμαστικό SMS button; audit
  fixes (SSE `GET /operations/events/{id}/stream`, `ShiftController::teamApply()` status
  `['open','review','confirmed','active']`, schema.sql fresh-install note).
- **v0.9.2 shipped INCOMPLETE** — the zip was missing models `EventRoomMessage`,
  `PhotoRequest`, `EventPhoto`, view `views/statistics/_overview.php`, and migrations
  `011`/`015` (hand-picked `cp` sync left them out). See "Release sync" above.
- **v0.9.3-beta** — packaging fix: added the missing files + full htdocs→repo mirror.
  No new features; makes room chat + photo features actually work on a clean install.
- Also fixed this push: **updater HTTP 415** — `UpdateService::httpGet()` requested the
  GitHub zipball with `Accept: application/octet-stream`, which GitHub rejects (415);
  changed to `Accept: */*`. NOTE: the broken updater can't self-deliver its own fix —
  this one line must be hand-patched once on any install still on the old updater.

### Next steps / open items
- Functional smoke-test on production: room chat round-trip; smsbox **Δοκιμαστικό SMS**
  (owner enters credentials); confirm push/GPS/camera work (require HTTPS).
- Nothing else pending on the deploy/migration side — repo == htdocs, DB current.
