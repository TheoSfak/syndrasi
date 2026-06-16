# SynDrasi — Release & Deployment Workflow

> Standing workflow for shipping SynDrasi. Follow every step. **Do not skip the Release.**

## ⚠️ Git: how the owner wants it done (preference)

For **commit / tag / release**, do **NOT** automate git through GUI tools or
computer-use (Git GUI / Explorer / .bat). That is slow and unreliable on this
machine. Instead, **provide the exact commands and the owner runs them in Git Bash
himself.** Ready-to-paste block:

```bash
cd /c/Users/user/Desktop/Syndrasi/syndrasi
git add -A
git commit -m "vX.Y.Z: short summary"
git push origin main
git tag vX.Y.Z
git push origin vX.Y.Z
# then create the GitHub Release (or: gh release create vX.Y.Z --repo TheoSfak/syndrasi --title "..." --notes "...")
```

You may still draft the commit message / release notes and (if asked) create the
GitHub Release via the browser — but the local git commands are run by the owner.

## How we work

1. **Develop on the Desktop working folder** (`C:\Users\user\Desktop\Syndrasi\syndrasi`) — this is the git repo.
2. **Sync** the changes into the XAMPP web root: `C:\xampp\htdocs\syndrasi`
   (this is the live/served copy used for local testing).
3. **Commit** the changes to git.
4. **Tag** a new version (bump `VERSION`, e.g. `v0.8.4`).
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

Two connected folders are usually mounted: the **htdocs** live copy
(`C:\xampp\htdocs\syndrasi`) and the **Desktop** git repo
(`C:\Users\user\Desktop\Syndrasi\syndrasi`). Edit the feature in htdocs, then mirror
the same files to Desktop, then the owner commits/releases from Desktop.

**Mount staleness gotcha (cost us real time):** the sandbox bash mount serves
**stale/truncated reads** of files right after they are edited with the Write/Edit
tools. As a result:

- `cp` / `cat` / `wc` in bash can copy or report a **truncated** version — this
  silently corrupted the Desktop copies once. **Do NOT sync with bash `cp`.**
- `git checkout` on the mount fails with "unable to unlink ... Operation not permitted".
- **Reliable path:** use the **Read tool** (host-authoritative) to read the full
  htdocs file, then the **Write tool** to write it to the Desktop path. Verify by
  reading at a high offset (e.g. offset 9000) and comparing the reported line counts
  on both sides.

**PHP is NOT available in the sandbox** (`php -l` can't run, no root to install). So
PHP syntax must be eye-checked, and the **owner should smoke-test in XAMPP** before
releasing.

## ✅ Current status / work log (last updated 2026-06-16)

### Released: v0.8.3-beta — published on GitHub (Latest)
PWA improvements:
- Bottom-right **install banner** (`public/assets/js/pwa.js`): `beforeinstallprompt`
  on Android/Chromium, manual Share→Add-to-Home instructions on iOS; dismiss stored in
  `localStorage`, re-offers after 7 days; hidden in standalone mode.
- Fixed `public/manifest.json` (removed trailing NUL byte → valid JSON; added
  `id`/`scope`/`categories` + separate `any`/`maskable` icons).
- `public/service-worker.js` made **path-aware** (BASE from `self.location`, works under
  a sub-folder); cache bumped to `syndrasi-v3`.
- iOS standalone meta tags in `views/layouts/header.php`.
- Banner CSS appended to `public/assets/css/app.css`.

### PENDING (in code, NOT yet committed/released) → next release **v0.8.4-beta**
**SMS gateway settings + per-type notification channel.** Implemented in BOTH htdocs
and Desktop, verified complete. Files changed:
- `app/Services/SmsService.php` — added `resolveConfig($mid)` (per-municipality SMS
  config from `municipality_settings`, env fallback); `send()` now takes `$municipalityId`.
- `app/Services/NotificationService.php` — `channelFor()`, `shouldSendSms()`,
  `maybeSms()`; `notifyTeam()`/`notifyMunicipality()` now also send SMS to admins with a
  phone when the channel includes SMS; mobilization SMS passes `$mid`.
- `app/Controllers/SettingsController.php` — `saveSms()`; `saveNotifications()` now stores
  `notify_channel_<type>` (off|email|sms|both) and keeps legacy `notify_email_<type>` in sync.
- `routes/web.php` — `POST /settings/sms`.
- `views/settings/municipality.php` — new **SMS** tab (driver/sender/endpoint/API key) and
  the Ειδοποιήσεις tab now uses a per-type channel selector (Καμία/Μόνο Email/Μόνο SMS/Email+SMS).

No DB migration needed — uses existing `municipality_settings` (key/value) and `users.phone`.
SMS credits are bought directly from the provider (Yuboto/AppText/Vonage); the app only
stores the API key and sends. `config/sms.php` still reads env as the platform default.

### To finish v0.8.4-beta (owner runs in Git Bash)
1. Smoke-test in XAMPP: open Ρυθμίσεις → SMS and → Ειδοποιήσεις, save each, confirm no error.
2. Bump `VERSION` to `0.8.4-beta` (both folders) and add a CHANGELOG entry.
3. Commit → push → tag `v0.8.4-beta` → push tag → publish GitHub Release.
