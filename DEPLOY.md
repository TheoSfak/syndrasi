# SynDrasi — Release & Deployment Workflow

> Standing workflow for shipping SynDrasi. Follow every step. **Do not skip the Release.**

## How we work

1. **Develop on the Desktop working folder.**
2. **Sync** the changes into the XAMPP web root: `C:\xampp\htdocs\syndrasi`
   (this is the live/served copy used for local testing).
3. **Commit** the changes to git.
4. **Tag** a new version (bump `VERSION`, e.g. `v0.8.3`).
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
