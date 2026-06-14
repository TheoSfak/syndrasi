# Changelog

All notable changes to SynDrasi are documented here.
Format loosely follows [Keep a Changelog](https://keepachangelog.com/);
versioning is `MAJOR.MINOR.PATCH` (beta line until feature-complete).

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
