# Changelog

All notable changes to SynDrasi are documented here.
Format loosely follows [Keep a Changelog](https://keepachangelog.com/);
versioning is `MAJOR.MINOR.PATCH` (beta line until feature-complete).

## [0.8.3-beta] — 2026-06-16

### Added
- **PWA install banner (κάτω δεξιά)** — διακριτικό, dismissible banner που προτρέπει
  τον χρήστη να εγκαταστήσει την εφαρμογή στο κινητό. Σε Android/Chromium πιάνει το
  `beforeinstallprompt` και ενεργοποιεί το native install prompt· σε iOS Safari (που
  δεν στέλνει το event) εμφανίζει οδηγίες «Κοινή χρήση → Προσθήκη στην αρχική οθόνη».
  Κρύβεται σε standalone mode και επανεμφανίζεται 7 μέρες μετά από απόρριψη
  (`localStorage`). Υλοποίηση εξ ολοκλήρου στο `pwa.js` — δεν χρειάστηκε αλλαγή layouts.
- **iOS standalone meta tags** στο `header.php` (`apple-mobile-web-app-capable`,
  status-bar style, app title) ώστε η εγκατεστημένη εφαρμογή να ανοίγει full-screen
  σε iPhone.

### Fixed
- **Άκυρο `manifest.json`** — αφαιρέθηκε ένα τελικό NUL byte που έκανε το αρχείο
  αυστηρά μη-έγκυρο JSON.
- **Service worker με απόλυτα paths** — το `service-worker.js` χρησιμοποιούσε
  leading-slash paths (`/offline.html`, `/assets/...`) που έσπαγαν το offline
  fallback και το precache όταν η εφαρμογή σερβίρεται σε υποφάκελο. Τώρα υπολογίζει
  το BASE από το `self.location` και δουλεύει είτε στη ρίζα είτε σε υποφάκελο.

### Changed
- Βελτιωμένο `manifest.json`: προστέθηκαν `id`, `scope`, `categories`, `dir`, και
  ξεχωριστά `any`/`maskable` icons για σωστή installability. Cache bumped σε
  `syndrasi-v3` (παλιά caches καθαρίζονται αυτόματα στο activate).

## [0.8.2-beta] — 2026-06-15

### Added
- **Platform Settings → Cron Jobs tab** — a one-click "Run cleanup now" button
  (super admin) that triggers the same housekeeping as `/cron/cleanup`, via a new
  shared `MaintenanceService`. No scheduler needed for demos.
- **Platform Settings → Updates tab** — self-update from **GitHub Releases**:
  configure repo owner/name (+ optional token for private repos), check the latest
  release against the local `VERSION`, and apply it. `UpdateService` downloads the
  release ZIP, backs up current code to `storage/backups/`, extracts over the app
  while **preserving `config/` and `storage/`**, and logs to `storage/logs/update.log`.
- **Automatic DB migrations** — new `MigrationRunner` + `schema_migrations` table.
  Updates run any new migration files automatically. Existing migrations (001–010)
  are baselined as applied so they never re-run; the tab shows applied/pending counts
  with a manual "run pending" button.
- Added a `VERSION` file as the source of truth for the running version.

## [0.8.1-beta] — 2026-06-15

### Added
- **SMS channel for mobilization** — new `SmsService` + `config/sms.php`. Default
  `log` driver writes to `storage/logs/sms.log` (zero-setup, testable); set
  `SMS_DRIVER=http` + `SMS_ENDPOINT`/`SMS_API_KEY` for a real gateway.
  `NotificationService::mobilize()` now SMSes targeted volunteers their response link.
- **Member ↔ account link** — migration `010_team_member_user_link.sql` adds
  `team_members.user_id` (backfilled by email). Call-outs now web-push members who
  hold an account directly, instead of only matching by email.

### Note
- Migration `002` (the `team_members` / `event_application_members` roster tables)
  had not been applied to the working database; it has now been run so the
  mobilization and roster features work.

## [0.8.0-beta] — 2026-06-15

### Added
- **Emergency Mobilization (Κάλεσμα Έκτακτης Ανάγκης)** — instant call-out for
  unplanned incidents. A municipality admin/operator creates a call-out (title,
  severity, location, target = whole municipality or selected teams); every targeted
  volunteer gets a personal **no-login token link** (`/m/{token}`) to reply
  *Έρχομαι / Δεν μπορώ / Ίσως* with an ETA and to mark on-site arrival/departure. A
  live command board (`/mobilizations/{id}`) polls a JSON stream and shows
  **Confirmed → En route → On-site → Departed → Declined → No-reply** in real time.
  - New tables via migration `009_mobilizations.sql`: `mobilizations`,
    `mobilization_responses`.
  - New `Mobilization` / `MobilizationResponse` models, `MobilizationController`,
    and `NotificationService::mobilize()` (push to members with accounts, email link
    fallback, in-app + push awareness ping to command staff). SMS hook left as a TODO.
  - Sidebar entry for municipality admins and operators.

### Fixed
- **Router could never match string tokens** — every `{param}` was compiled to a
  numeric `(\d+)` pattern, so the existing `/public/events/{token}` share links (32-char
  hex) silently 404'd. `{token}` now matches `[A-Za-z0-9]+`; other params stay numeric.

## [0.7.3-beta] — 2026-06-14

### Fixed
- **CSRF JSON path crash:** `public/index.php` called the undefined `json_response()`
  when a JSON POST failed CSRF validation, causing a fatal error instead of a clean
  419 response. Now calls `json_out()`.
- **Output escaping:** municipality dashboard category distribution now escapes the
  label/colour values with `e()`.

### Changed
- **Environment is now config-driven:** `config/config.php` reads `APP_ENV` via a new
  `env()` helper and **defaults to `production`** (fail-safe — no error leakage if the
  var is unset). Set `APP_ENV=development` locally to see errors. `database.php` and
  `mail.php` reuse the same `env()` helper.
- **Performance — removed N+1 queries:** event reconciliation and the users admin page
  pre-load related rows in batched queries (`TeamMember::forApplications()`,
  `VolunteerParticipation::forApplications()`, grouped team lookup) instead of querying
  per row inside the view.
- **De-duplicated password validation** behind a single `password_error()` helper used
  by reset, change-password, and admin user flows.

### Added
- **`GET /cron/cleanup`** (token-protected) purges accumulated transient `app_settings`
  rows (login throttling, shift-reminder flags) and spent password-reset tokens.
  Migration `008_app_settings_timestamp.sql` gives `app_settings.updated_at` a default
  so rows can be aged out. Recommend a daily cron.

## [0.7.2-beta] — 2026-06-14

### Changed
- **Operations auto-live:** published (`open`) events now appear automatically in
  Κέντρο Επιχειρήσεων and the War Room once the current time is within their
  start/end window — no manual "Έναρξη δράσης" required (manual start still works).
  Also surfaced in "Ξεκινά σύντομα" within 60 minutes of start.

## [0.7.1-beta] — 2026-06-14

### Fixed
- **Operations visibility:** published (`open`) events could not be started — the
  "Έναρξη δράσης" button was shown but the state machine forbade `open → active`,
  so activation silently failed and the event never appeared in Κέντρο Επιχειρήσεων.
  Added `active` to the allowed transitions from `open`.

## [0.7.0-beta] — 2026-06-14

### Added
- **Event templates** (core fields + shifts): save an event as a reusable template
  ("Ως πρότυπο" on the event page) and spin up new events from it ("Νέα από πρότυπο"
  on the events list). Shifts are stored as offsets and re-created with concrete
  datetimes on the new event. New `event_templates` table (migration 007) + `EventTemplate` model.

## [0.6.0-beta] — 2026-06-14

### Added
- **QR check-in** (optional alternative to the normal flow): operators open a
  full-screen **QR Πύλης** (Gate QR) from the operations command center; team
  leaders scan it on their phones to reach a one-tap check-in page
  (`/team/qr-checkin/{id}`) that reuses the existing check-in endpoint and
  `operational_checkins` table. Present-full / partial / departed supported.

## [0.5.0-beta] — 2026-06-14

First versioned release / initial GitHub sync.

### Added
- **Κέντρο Συντονισμού** (multi-event War Room): municipality-wide live map of all
  active events at once, colour-coded by coverage/shortages, live event list with
  coverage bars and countdowns, global totals, SSE-driven, drill-down to each
  command center.
- **Αναλύσεις & Τάσεις** (Advanced reporting): multi-year year-over-year analytics —
  KPI cards with YoY deltas, diachronic trend chart, monthly comparison,
  response-time trend, top-team hours per year, and CSV export (yearly/category/teams).

### Changed
- Service worker now uses **stale-while-revalidate** for static assets (was
  cache-first) and the cache was bumped to `syndrasi-v2`, fixing stale CSS/JS that
  required a hard refresh (Ctrl+F5) to load the dashboard cards.

### Baseline (pre-0.5.0)
- MVC core, auth, roles, multi-tenancy; municipality dashboard with charts.
- Volunteer teams & members, event lifecycle, applications, approvals, shifts,
  reconciliation, debriefs.
- Single-event operations command center (SSE live map, check-ins, shortages,
  notes, activity), Mobile Action Hub.
- Statistics, awards, CSV exports, PDF reports.
- Per-municipality settings, notifications (in-app + email + Web Push), public
  event pages, super-admin panel, PWA.
