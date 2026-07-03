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

**Syncing Desktop → htdocs (proven method as of v0.16.4):** use PowerShell
`Copy-Item` and then **hash-verify every copied file** with `Get-FileHash`
against both trees — this ran dozens of times across the v0.15.12→v0.16.4
pushes with zero truncation. The historical bash-mount truncation bug
(v0.9.4/v0.9.5) applied to bash `cp`/`rsync`; those remain banned for
syncing. `git add/commit/push/tag` via Bash is fine — git reads directly
from disk.

```powershell
Copy-Item "$src\app\*" "$dst\app\" -Recurse -Force
# ...then hash-compare: any mismatch = re-copy, never ship unverified
Get-ChildItem "$src\app" -Recurse -File | ForEach-Object {
  $rel = $_.FullName.Substring($src.Length)
  if ((Get-FileHash $_.FullName).Hash -ne (Get-FileHash "$dst$rel").Hash) { "MISMATCH: $rel" }
}
```

**PHP IS available in this environment** at `C:\xampp\php\php.exe` — run
`php -l`, PHPStan and PHPUnit locally before every release (see
"Verification gates" below). An older note here claimed otherwise; that
applied to a different sandbox.

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
below), and a brand-new DB needs nothing else.

⚠️ The file intentionally has **no `CREATE DATABASE` / `USE`** statement (it
used to, and running it "against a test DB" once wiped the live one). You
create and name the target database yourself:

```bash
mysql -u root -p -e "CREATE DATABASE syndrasi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p syndrasi < database/schema.sql
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

## 🧪 Verification gates — run before EVERY release

All of these must be green before tag/release (CI runs the first three on
every push too, via `.github/workflows/ci.yml`):

```bash
cd /c/Users/user/Desktop/Syndrasi/syndrasi
find app routes config -name '*.php' -print0 | xargs -0 -n1 /c/xampp/php/php.exe -l   # syntax
/c/xampp/php/php.exe vendor/bin/phpstan analyse --memory-limit=512M                    # static analysis (L5)
/c/xampp/php/php.exe vendor/bin/phpunit                                               # ALL suites
```

What the test suites cover (37 tests as of v0.16.4-beta):
- **`tests/Unit/`** — pure logic (event state machine, Greek helpers,
  authority terminology, MediaUploader rejection paths) **plus two release
  gates**: `RouteRoleConsistencyTest` (route role declarations must match
  each controller's `requireRole()` — resolves `Role::` constants and class
  consts via reflection) and `SchemaMigrationsDriftTest` (every migration
  table/column must exist in `schema.sql`).
- **`tests/Integration/`** — real HTTP against the local XAMPP install
  (`http://localhost/syndrasi/public`): token-based field flow
  (accept/409/404), the login form flow, deny-by-default routes, cron
  Bearer auth, and the full ops-stream change-detection cycle. Creates and
  removes its own DB fixtures. **Skips cleanly when Apache/MySQL aren't
  running** (so CI stays green) — if the whole suite shows "skipped",
  start XAMPP; don't ship on skipped integration tests.

Conventions the gates enforce (added v0.16.4-beta):
- Roles are `Role::` class constants (`app/Models/Role.php`) in routes and
  `requireRole()` calls — never raw strings.
- Every live-ops write must call `Event::touchActivity($eventId)` (or go
  through a model `create()` that already does) — otherwise the war-room
  poll's change detection won't see it and the board can appear stale for
  up to 60s.

## ✅ Current status / work log (last updated 2026-07-03, v0.16.4-beta)

### Released: v0.16.1 → v0.16.4-beta (2026-07-02/03)

- **v0.16.1-beta — Resource Dispatch Phase 2**: teams answer resource
  requests (accept + ETA / decline) from the no-login field link and team
  live; push templates `resourceAccepted`/`resourceDeclined` to command
  staff; war-room activity feed shows the request lifecycle.
- **v0.16.2-beta — Phase 3**: delivered request linked to a shortage shows
  a one-click «Επίλυση έλλειψης;» button; Story gets an «Αιτήματα πόρων»
  section (response/delivery durations, ETA, acceptance rate) + timeline
  entries + summary count.
- **v0.16.3-beta — Phase 4 (feature complete)**: «Εικόνα Ετοιμότητας»
  dashboard widget aggregating team readiness (vehicle/medical/per-item
  counts) — no new schema. Smart Resource Dispatch Phases 1–4 done.
- **v0.16.4-beta — code-smell report fix batch** (see
  `SynDrasi_CodeSmell_Architecture_Report_2026-07-03.md` one level above
  the repo root): live-ops poll change detection via
  `events.last_activity_at` + per-session signature — unchanged polls get
  a skinny `{unchanged:true}` instead of the full snapshot (~95% query
  reduction; full rebuild on any real change and at least 1×/min);
  `ResourceRequestResponder` (deduped Phase 2 respond logic); `Role`
  constants swept over 367 call sites; HTTP integration test suite; route↔
  controller role-consistency gate; schema↔migrations drift gate;
  `schema.sql` `USE` foot-gun removed **and** missing `resource_requests`
  table restored (fresh installs were broken again); Router treats HEAD as
  GET (was 404 — broke monitoring probes); self-updater now health-checks
  the new version after applying and **auto-restores the pre-update
  backup** on definitive failure (5xx/connection refused; ambiguous
  timeouts never trigger rollback).

Migrations now: **040** (`037` rate_limits, `038` shift reminded_at,
`039` resource_requests, `040` event last_activity). `schema.sql` verified
against the live migrated DB: 44 tables, identical.

### Self-updater behaviour (changed in v0.16.4-beta)
After copying files + running migrations, the updater probes its own
`/login` page. HTTP 2xx/3xx → healthy. HTTP 5xx or connection refused →
**automatic rollback** to the pre-update backup zip (note: already-applied
migrations stay applied — old code + newer additive schema is tolerable).
Timeout/other → no rollback, logged as `unknown` (a busy server must not
cause a false restore). The maintenance lock is released *before* the
probe (it 503s everything, including our own request).

### Next steps / open items (as of v0.16.4-beta)
- The two remaining report items, both deliberately deferred because they
  need live browser verification and carry multi-day blast radius: **S5**
  extract the ~1,100-line inline JS from `views/operations/event.php` (+
  `team/live.php`, `field/hub.php`, `events/story.php`) into
  `public/assets/js/`; **C4** split `OperationController` /
  `TeamPortalController` / `NotificationService` god classes.
- Status-literal sweep (`EventStatus`/`ResourceRequestStatus` constants):
  349 literals, many inside views where strings like `'active'` also
  appear in CSS/JS contexts — needs careful per-site review, not regex.
- Production: run Backup → Update from Platform Settings to go
  0.15.x → 0.16.4-beta in one hop (migrations 037–040 apply
  automatically). The new auto-rollback makes this safer, but take the
  manual backup anyway.
- Older item, still open: functional smoke-test on production for room
  chat round-trip, smsbox **Δοκιμαστικό SMS**, push/GPS/camera (require
  HTTPS).

---

## 🗄️ Older work log (2026-07-02 push, kept for context)

### Released on GitHub: **v0.15.12-beta**, superseded shortly after by **v0.16.0-beta**
`v0.15.12-beta` was tagged/pushed/released (not marked prerelease — see
gotcha below) with 38 migrations total (`037`, `038` new that push) and a
regenerated `schema.sql` verified to match the live migrated DB exactly
(43 tables — see "Fresh install" section above, which that push added).
A separate, concurrent piece of work (Smart Resource Dispatch feature —
`docs/RESOURCE_DISPATCH_SPEC.md`, migration `039`, `ResourceRequest`
model/`ResourceMatcher` service) landed on `origin/main` independently
right after and bumped to **v0.16.0-beta**, now the actual latest. Merged
cleanly (no real conflicts — see gotcha below on why the merge initially
looked conflicted).

**⚠️ Two-sessions-same-folder gotcha:** mid-push, `git push` was rejected
because another session (working in this same
`C:\Users\user\Desktop\Syndrasi\syndrasi` folder, not an isolated worktree)
had independently committed and pushed the Resource Dispatch feature.
`git merge` then reported the working tree had uncommitted local changes
AND untracked files that would be "overwritten" — but diffing every one of
those files against `origin/main` (after stripping `\r`, since Windows
CRLF checkout makes every line show as changed even when content is
identical) showed **zero real differences**: the other session's files
were already sitting in this shared folder, just not `git add`ed here yet.
Safe resolution was `git checkout -- <tracked files>` +
`rm <untracked files>` (discarding what were actually just local copies of
already-pushed content) then a clean `git merge`. **Always diff before
assuming a "would be overwritten" conflict is real** — with two sessions
sharing one folder, it usually isn't.

This push's changes (route middleware → app_settings cleanup → schema.sql
fix, one commit per concern — see `git log` for exact diffs):
- **Deny-by-default route middleware** (`app/Helpers/Router.php`,
  `routes/web.php`) — every route now explicitly declares `['public' =>
  true]` or `['roles' => [...]]`; `Router::dispatch()` enforces it before
  the controller runs, instead of relying on every action remembering to
  call `requireRole()`. All 227 existing routes audited; one real gap found
  and fixed (`/mobilizations*` had no route-level guard).
- **First automated safety net**: `composer.json` (dev-only deps), PHPStan
  level 5 + baseline, PHPUnit (29 tests), GitHub Actions CI on push/PR.
  PHPStan caught 2 real bugs immediately (possibly-undefined vars after a
  `redirect()` inside a `catch` — fixed by giving `redirect()`/`json_out()`/
  `render()`/`abort()` a `never` return type, since PHP didn't know they
  always exit).
- **`MediaUploader` service** — deduped photo/video upload validation that
  was copy-pasted 4x across `FieldController`/`TeamPortalController`.
- **Unified `OperationController` snapshot queries** — `status()`,
  `stream()`, `locations()` each had their own (already-drifted) copy of
  the shortages/notes/pending-apps/pings queries; now share one source of
  truth.
- **`app_settings` cleanup** — login/reset rate-limiting moved to a
  dedicated `rate_limits` table; the per-shift cron reminder flag moved
  from one `app_settings` row per shift forever to `event_shifts
  .reminded_at`.
- **`schema.sql` regenerated** (see "Fresh install" section) — it was
  missing ~26 tables (`event_shifts`, `team_members`, `shift_applications`,
  `video_requests`, more); a fresh install using `schema.sql` alone was
  broken before this push.

**⚠️ New gotcha found this push — GitHub Release "latest" cache lag:**
`UpdateService` calls `GET /repos/{owner}/{repo}/releases/latest`, which by
GitHub's own design excludes prereleases/drafts. Toggling a release's
`prerelease` flag via `gh release edit` does NOT reliably invalidate
GitHub's cache for that endpoint — it stayed stale for 15+ minutes in one
observed case, even though the release's own metadata (`prerelease: false`,
newest `created_at`) was immediately correct via `gh release view`. If
`/releases/latest` still returns the old tag after editing a release,
**delete and recreate the release** (`gh release delete vX.Y.Z --cleanup-tag=false`
then `gh release create vX.Y.Z ...` again) — this forced an immediate
refresh both times it was tried. Moral: don't mark a beta release as a
GitHub "prerelease" in the first place (breaks `/releases/latest`); this
repo's convention is `-beta` in the version string, not the GitHub
prerelease flag.

**⚠️ `schema.sql` safety note (RESOLVED in v0.16.4-beta):** the file used
to open with `CREATE DATABASE IF NOT EXISTS syndrasi; USE syndrasi;`, which
made it silently target the live DB no matter which database you pointed
the `mysql` CLI at — running it "against a scratch DB" once dropped and
recreated every live table (recovered from a same-morning backup; the
backups live in `C:\xampp\mysql\_syndrasi_db_backups_2026-07-02\`). As of
v0.16.4-beta those statements are **removed**: the file applies only to
the database named on the command line, and testing against a scratch DB
(`mysql -u root scratch_db < database/schema.sql`) is safe. A PHPUnit
drift guard (`SchemaMigrationsDriftTest`) additionally fails the build if
any migration's table/column is missing from schema.sql — this class of
bug recurred twice in one week before the guard existed.

**⚠️ Two copies of the codebase gotcha (reconfirmed this push):**
`C:\xampp\htdocs\syndrasi` (what Apache actually serves) is a **separate,
non-git-tracked directory**, not a symlink to the Desktop repo. The
"Correct workflow" section above says to dual-write every file with the
Write/Edit tool to avoid a bash-mount truncation bug from `v0.9.4`/`v0.9.5`.
This push instead: (1) did all edits only in the Desktop repo, (2) at the
end, used PowerShell `Copy-Item` (not bash `cp`) for the specific list of
changed files from `git status`, (3) verified byte-for-byte via `diff`
after every copy. This worked and caught no truncation — but it's a
different method than documented above, so if the truncation bug is
specifically a bash-mount issue (not a general `cp`/`Copy-Item` issue),
prefer the dual-write approach for anything you can't immediately `diff`
and verify. **Also: PHP *is* available in this environment** at
`C:\xampp\php\php.exe` (contradicts the older note below that it isn't) —
`php -l`, PHPStan, and PHPUnit all ran directly against the Desktop repo
this push.

Older release history (from the v0.9.x packaging-fix push, kept for
context — many releases happened between this and the v0.15.12-beta push
above; see `git log`/CHANGELOG.md for the full history):
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

*(The "next steps" that used to sit here are superseded by the
v0.16.4-beta section above.)*
