# Changelog

All notable changes to SynDrasi are documented here.
Format loosely follows [Keep a Changelog](https://keepachangelog.com/);
versioning is `MAJOR.MINOR.PATCH` (beta line until feature-complete).

## [0.9.49-beta] — 2026-06-19

### Fix — Αυτόματη δημιουργία cron_secret · βελτίωση Cron Jobs tab

**Πρόβλημα:** Το async loopback dispatch (αποστολή emails χωρίς cron) δεν δούλευε αν δεν υπήρχε `cron_secret` στη βάση, κάτι που δεν ρυθμίζεται πουθενά αυτόματα.

**Λύση:**
- `dispatchAsync()` τώρα **auto-generates** το `cron_secret` αν δεν υπάρχει (πρώτη αποστολή email) — χωρίς καμία χειροκίνητη ρύθμιση
- Το **Cron Jobs tab** στο `/admin/settings` εμφανίζει τώρα:
  - Το τρέχον `cron_secret` με κουμπί αντιγραφής
  - Κουμπί "Αναγέννηση κλειδιού"
  - Έτοιμη εντολή curl για το `/cron/mail-queue` (με το κλειδί συμπληρωμένο)
  - Έτοιμη εντολή curl για το `/cron/cleanup`

---

## [0.9.48-beta] — 2026-06-19

### Fix — Αριθμός εθελοντών επαναφερόταν κατά το live refresh

Στο Επιχειρησιακό Κέντρο, ο admin δεν μπορούσε να αλλάξει τον αριθμό ατόμων στο input "Εγκρίνω Χ άτομα" γιατί κάθε ~9 δευτερόλεπτα το SSE/poll ξανά-render την κάρτα Αιτήσεων και επανέφερε την τιμή στο `offered_people` της ομάδας.

Fix: `renderPendingApps()` τώρα αποθηκεύει τις τιμές που έχει ήδη επεξεργαστεί ο admin πριν το `innerHTML` rebuild και τις επαναφέρει αμέσως μετά.

---

## [0.9.47-beta] — 2026-06-19

### Fix — Email dispatch: cron job no longer required

Improved `MailService::flushQueue()` with a **fire-and-forget loopback HTTP dispatch** so emails are sent asynchronously on any server without needing a cron job.

**How it works (shutdown handler, runs after the user's redirect is issued):**
1. **PHP-FPM** (`fastcgi_finish_request` available): browser gets the redirect instantly; emails sent by the same worker in the background.
2. **Apache/mod_php** (most shared hosting, incl. 1stop.gr): opens a TCP socket to the current server, writes `GET /cron/mail-queue` with the cron secret, and closes the socket immediately **without reading the response**. The server spawns a separate PHP worker to process and send the emails. The original request exits right away.
3. **Loopback blocked** (rare): falls back to synchronous send — emails block the response (old behaviour) but still get delivered.

**No cron job required.** The `/cron/mail-queue` route and `CronController::processMailQueue()` remain available for environments that want cron for extra reliability (guaranteed delivery even if no incoming HTTP traffic).

**Prerequisite:** a `cron_secret` must be set in app_settings (Ρυθμίσεις → Συντήρηση). If missing, falls back to synchronous.

---

## [0.9.46-beta] — 2026-06-19

### Fix — Email delays (10-15 s) on event publish / team participation

`sendDeferred()` previously relied on `fastcgi_finish_request()` to send emails after the HTTP response — but this only works on PHP-FPM. On Apache/mod_php hosts (including the production server on 1stop.gr) every SMTP connection blocked the user's redirect for ~2-3 s per email. With member-assignment notifications, selecting 5 members produced up to 15 s of waiting.

**Solution: DB-backed mail queue.** `sendDeferred()` now does an instant `INSERT INTO mail_queue` (~0 ms) and returns immediately. A new cron endpoint `/cron/mail-queue` sends the queued messages asynchronously (up to 50 per run, retries 3 times on failure).

**To activate on production:**
1. Run migration `database/migrations/018_mail_queue.sql` on the production database
2. Add a cron job: `* * * * * curl -s -H "Authorization: Bearer <cron_secret>" "https://yoursite/cron/mail-queue" > /dev/null`

Until the migration runs, the old shutdown-function approach stays as a fallback (no breakage).

Technical:
- New table `mail_queue` (id, municipality_id, to_email, to_name, subject, body, attempts, sent_at, error_msg)
- `MailService::sendDeferred()` — INSERT to DB, catches exception and falls back to array
- `MailService::flushQueue()` — now only the fallback path (kept for backward compat)
- `CronController::processMailQueue()` — new `GET /cron/mail-queue` endpoint
- Migration: `database/migrations/018_mail_queue.sql`

---

## [0.9.45-beta] — 2026-06-19

### Feature — Inline team approval from operational command centre

Municipality admins can now approve or reject pending team applications **directly from the operational event page** (`/operations/events/{id}`) while the event is already active — no need to navigate to the applications review screen.

A full-width amber card appears automatically at the top of the command centre grid whenever there are pending applications. For each pending request it shows:
- Team name and number of people offered
- Optional comment left by the team
- An editable "Εγκρίνω X άτομα" number input (pre-filled with the team's offer)
- **Έγκριση** (green) / **Απόρριψη** (red) buttons

Clicking Έγκριση immediately approves the application, sends the standard approval notification to the team, logs an audit entry, and refreshes the board. Clicking Απόρριψη shows a confirmation dialog before rejecting. The card hides itself when no pending applications remain.

Technical:
- Two new routes: `POST /operations/events/{id}/applications/{appId}/approve` and `.../reject` → `OperationController@approveApplication/rejectApplication`
- Restricted to `municipality_admin` role only (event operators cannot approve)
- `pending_apps` array added to both the SSE snapshot (`buildStreamSnapshot`) and the manual poll (`status`) so the panel stays live

---

## [0.9.44-beta] — 2026-06-19

### Fix — Bootstrap modal greyed-out in danger zone

The "Διαγραφή όλων των δεδομένων…" modal was showing as an inaccessible grey overlay. Root cause: Bootstrap 5 does not teleport modals to `<body>` — the modal lived inside `<main>`, while the backdrop is always appended to `<body>`. This placed them in different stacking contexts, making the backdrop render above the dialog. Fixed by moving the modal to `document.body` on `DOMContentLoaded` via an inline script, so both the modal (z-index 1055) and backdrop (z-index 1050) share the same stacking context.

---

## [0.9.43-beta] — 2026-06-19

### Feature — High-priority notification system for emergency operations

Complete overhaul of the notification pipeline for civil protection / fire department scenarios where no alert can be missed:

**Web Push urgency (RFC 8030):** `WebPushService::send()` now accepts an `$urgency` parameter (`very-high | high | normal | low`) and sets the `Urgency:` HTTP header. `very-high` bypasses Android Doze mode so the device wakes even when battery-optimised. SOS alerts and geo-incident orders now send at `very-high`; GPS-arrival and silent-team alerts at `high`.

**GPS arrival notification:** When command staff requests a team's GPS and the team submits it, command now receives an immediate in-app + push notification with a Google Maps link. Implemented via `NotificationService::gpsArrived()` called from `FieldController::location()` when a pending GPS request is fulfilled.

**Silent team alert:** `OperationController::checkSilentTeams()` runs on every SSE snapshot. If a team has not pinged for more than the configured threshold (default 20 min), command staff receive a warning notification. A dedup check on the `notifications` table prevents re-alerting within the same silence window. Threshold is now a per-municipality setting (`ops_silent_team_minutes`, 0 = disabled) under Settings → Municipality → Notifications.

**Field hub (Mission Commander) audio + banner:** When a new order or message arrives on the `/f/{token}` hub page, the browser plays a short beep via Web Audio API and vibrates (mobile). An amber fixed-position flash banner shows the new message text for 6 seconds. First-load seeding avoids false alerts on page open.

---

## [0.9.42-beta] — 2026-06-19

### Security — photo serving MIME whitelist

`servePhoto()` now validates the detected MIME type against an explicit allowlist (`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/heic`, `image/heif`) before streaming the file. Any file whose MIME type doesn't match returns `HTTP 415 Unsupported Media Type` instead of being served blindly.

### Fix — `autoCloseExpired` throttle to once per 60 seconds

The auto-close check was firing a `UPDATE events …` query on every SSE reconnect (~every 3 s) and every war-room page load. It now uses a session timestamp to skip the DB write if it was already executed within the last 60 seconds per municipality — reducing unnecessary writes by ~95% during active monitoring sessions.

---

## [0.9.41-beta] — 2026-06-18

### Feature — Automatic debrief flow on event close

**Admin side:** When a municipality admin closes an event from the operational page, the system now:
1. Sends an in-app notification (+ email if configured) to every approved team: "Debrief δράσης: … — Συμπληρώστε το Post-Event Debrief".
2. Redirects the admin directly to `/events/{id}/debriefs` instead of back to operations, so they can immediately fill in the Municipality After-Action report.

**Team side:** When a team admin visits `/team/events/{id}` for a closed or completed event they participated in, a prominent indigo call-to-action card appears at the top of the page prompting them to fill the debrief. Once submitted, the card turns into a success banner with a link to edit the submission.

---

## [0.9.40-beta] — 2026-06-18

### Feature — Silent team warning on operational map

When a team's last GPS ping is 20+ minutes old, their map marker now visually signals silence: the dot turns **grey** (last known position, not current), a red **!** badge appears beside it, and the marker pulses with a red glow animation. Clicking the marker shows "⚠ Σε σίγη — τελευταίο στίγμα X λεπτά πριν" in the popup instead of the normal age text. Thresholds unchanged: < 5 min = green, 5–20 min = yellow, 20+ min = grey + alarm badge.

### Feature — Audio SOS alert

When a new SOS arrives via the SSE stream or status poll, the browser plays a triple-beep (880 Hz, 3 × 270 ms, 380 ms gap) using the Web Audio API — no audio file needed. The beep is suppressed on the initial page load so it doesn't fire when the operator first opens the page. Subsequent SOS events while the page is open trigger the sound immediately.

---

## [0.9.39-beta] — 2026-06-18

### Feature — SuperAdmin danger zone: erase all operational data

New **"Επικίνδυνη Ζώνη"** tab in SuperAdmin → Platform Settings. A red-bordered card lists exactly what will be deleted and what stays. Clicking the button opens a Bootstrap modal that requires typing `ΔΙΑΓΡΑΦΗ` before the form can be submitted — prevents accidental wipes.

On confirm, 23 operational tables are truncated (`FOREIGN_KEY_CHECKS` disabled for the duration): events, applications, shifts, checkins, notes, shortages, SOS, GPS pings, geo-requests, photos, messages, room messages, reports, debriefs, participations, mobilisations, notifications, audit logs, password reset tokens. **Users, teams, team members, municipalities, categories, templates and platform settings are untouched.**

---

## [0.9.38-beta] — 2026-06-18

### Fixed — map auto-pan on SSE update

`fitBounds` was called on every SSE update (~3 s), snapping the map back to the teams' current GPS coordinates whenever the user had manually panned (e.g. to pick a geo-order target in Heraklion while teams were elsewhere). Map now auto-fits **once** on the first ping update, then never again. Picking mode (`Χάρτη` button) also disables auto-fit immediately so the SSE cannot interrupt mid-selection.

## [0.9.37-beta] — 2026-06-18

### Feature — Geo-order markers on operational map

When command sends a move / incident / poi order with a GPS point, the target now appears on the map as a distinct shape (never a circle):

- **Μετάβαση** → orange downward triangle ▼
- **Περιστατικό** → red rotated diamond ◆
- **Σημείο ενδιαφέροντος** → purple rounded square ★

A dashed coloured line connects the team's current GPS dot to the target. The marker and line disappear automatically once the team's next ping arrives within 100 m (no manual "arrived" step). Broadcast and targeted orders both supported; newest order per team wins. Works across SSE stream, status poll, and manual map refresh.

### Feature — Collapsible symbol legend

A small **"ⓘ Υπόμνημα συμβόλων χάρτη"** toggle sits below the operational map — collapsed by default, zero JS, native `<details>`/`<summary>`. Expands to show all 5 map symbols (GPS circle, move triangle, incident diamond, POI star, dashed connector) with their exact colours.

## [0.9.35-beta] — 2026-06-18

### Security
- `AuthController`: rate-limit keys upgraded from MD5 → SHA256; password-reset tokens now stored as SHA256 hash (DB compromise cannot replay tokens)
- `AdminController`: `stopImpersonation()` now enforces `requireLogin()` and calls `session_regenerate_id(true)`
- `CronController`: cron secret moved from `?secret=` query-string (logged in web server access logs) to `Authorization: Bearer` header only
- `storage/.htaccess`: new file — blocks direct HTTP access to the storage directory

### Fixed
- `maps.js`: critical live-map bug — `data.success` → `data.ok` (operational map was never updating)
- `ApplicationController` / `EventController`: notification calls wrapped in `try/catch(Throwable)` — a mail failure no longer rolls back the approve/reject action
- `ApplicationController` bulk-approve: replaced N+1 `EventApplication::find()` per loop with a single batch `SELECT … IN (…)` query
- `public/index.php`: global exception handler, maintenance-mode 503 check, security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`)
- `UpdateService`: writes `storage/maintenance.lock` before update; `register_shutdown_function` removes it on finish
- `views/events/show.php`: "Κλείσιμο δράσης" button uses `btn-danger`; section label translated to "Απολογισμός Δράσης"; cancel button uses `btn-outline-secondary`; `btn-xs` replaced with `btn-sm py-0 px-1`
- `views/dashboard/municipality.php`: duplicate chart.js script tag removed

### Performance
- `DashboardController`: 13+ DB queries → ~6 using `GROUP BY status` consolidation (monthly trend, per-status year counts, team counts, approval rate)
- `MunicipalitySetting::all()`: per-municipality static cache; `setMany()` wrapped in DB transaction
- `database/migrations/017_perf_indexes.sql`: composite indexes on `location_pings(event_id, created_at)` and `notifications(user_id, is_read)`

### UI / Accessibility
- `views/layouts/flash.php`: Bootstrap Icons per alert type; non-danger alerts auto-dismiss after 5 s
- Toast zone (`footer.php`): `aria-live="polite"`, switches to `assertive` for SOS toasts
- Impersonation banner (`header.php`): `role="alert" aria-live="assertive"`
- Sidebar logo `alt` now shows dynamic org name from settings
- `war-room.php` + `analytics/index.php`: inline `json_encode()` uses `JSON_HEX_TAG | JSON_HEX_AMP` (CSP-safe)
- `service-worker.js`: local assets (`/assets/js/`, `/assets/css/`) switched to network-first → updated CSS/JS loads immediately after deploy without version strings
- `field/hub.php`: Wake Lock API keeps screen on; offline banner appears after 2 consecutive poll failures
- `public/assets/css/app.css`: `.btn-xs` utility, `.no-hover:hover`, `@media (prefers-reduced-motion: reduce)` block

## [0.9.29-beta] — 2026-06-18

### Feature — Επιχειρησιακό Κέντρο: Live Photo Wall

Νέα κάρτα «Φωτογραφίες Live» στη δεξιά στήλη (μεταξύ Πίνακα Ομάδων και Ελλείψεων):

- Grid 3×3 με τις τελευταίες 9 φωτογραφίες (newest first), κρυμμένο αν δεν υπάρχουν φωτό
- Overlay σε κάθε thumbnail: όνομα ομάδας, ώρα αποστολής, λεζάντα (αν υπάρχει)
- Κλικ → άνοιγμα στο υπάρχον fullscreen modal (`#photoModal`)
- Νέες φωτό εμφανίζονται με scale-in animation (`.wall-new`)
- Χρησιμοποιεί τον υπάρχοντα delegated click handler (`.photo-thumb`)
- Ενημερώνεται μαζί με `updatePhotos()` από το SSE/polling

## [0.9.28-beta] — 2026-06-18

### Feature — Επιχειρησιακό Κέντρο: Live Teams Board

Νέο panel «Πίνακας Ομάδων» στη δεξιά στήλη του Επιχειρησιακού Κέντρου:

- **3 summary counters** πάνω: Παρόντες / Αδήλωτοι / Εγκεκριμένοι (από server stats)
- **Compact rows** για κάθε ομάδα: χρωματιστή κουκκίδα status, όνομα, άτομα (παρόντες/εγκεκριμένοι), ηλικία τελευταίου GPS ping
- **Αυτόματο sorting**: SOS πρώτοι (με badge + animation), μετά παρόντες (πλήρεις → μερικοί), μετά αδήλωτοι, τελευταίοι αποχωρήσαντες
- **Ping age color**: πράσινο <5λ / πορτοκαλί <20λ / κόκκινο >20λ
- **SOS flash**: το boardList κάνει flash όταν υπάρχει ενεργό SOS
- Ενημερώνεται από την ήδη υπάρχουσα SSE/polling ροή — χωρίς νέο endpoint

## [0.9.27-beta] — 2026-06-18

### Fixed — Team Live Hub: blank green page

**🔴 Critical:** `/team/live/{id}` εμφάνιζε κενή πράσινη σελίδα λόγω PHP fatal error.

**Bug 1 (Critical):** `get_flash()` → `flash_get()` (η `get_flash()` δεν υπάρχει) — ίδιο bug που είχε διορθωθεί στο `hub.php` (v0.9.24). Το fatal error εκτελούνταν αφού το CSS είχε ήδη σταλεί στον browser (πράσινο background), κόβοντας όλο το body content.

**Bug 2:** `$isActive` έλεγχε μόνο `status = 'active'` — ίδιο root cause με Field Hub + Team Dashboard. Χρησιμοποιεί πλέον την κοινή λογική (open/confirmed/review past start_datetime).

## [0.9.26-beta] — 2026-06-18

### Fixed — Ops Centre: GPS/χάρτης 2 bugs

**Bug 1 (Visual — camera badge ποτέ δεν εμφανιζόταν):**  
Στο `updateMap()` η μεταβλητή `ph` (τελευταία φωτογραφία της ομάδας) χρησιμοποιούνταν στο `html` και `iconSize` ΠΡΙΝ οριστεί (`var ph = lastPhotosByTeam[...]` ήταν από κάτω). Λόγω JS var hoisting, `ph` ήταν πάντα `undefined` → το camera badge badge στους δείκτες ομάδων δεν εμφανιζόταν ποτέ. Λύση: μετακίνηση `var ph` πριν το `html`.

**Bug 2 (Functional — χάρτης παγώνει αν κολλήσει το SSE):**  
Το 15s fallback timer (για shared hosting χωρίς SSE) καλούσε μόνο `pollStatus()`, το οποίο δεν επιστρέφει `pings`. Έτσι `updateMap()` δεν εκτελούνταν και ο χάρτης σταματούσε να ενημερώνεται. Λύση: προστέθηκε `pollLocations()` στο fallback timer.

## [0.9.25-beta] — 2026-06-18

### Fixed & Enhanced — Team Dashboard

**Bug fix:** Ενεργή δράση (confirmed/open past start_datetime) εμφανιζόταν ως «0 Δράσεις σε Εξέλιξη» στο dashboard της ομάδας, γιατί το query έλεγχε μόνο `e.status = 'active'` — ίδιο root cause με το Field Hub (v0.9.24). Διορθώθηκε με την ίδια λογική.

**Redesign dashboard** (`/team/dashboard`):
- Νέα gradient stat cards (ds-card): Δράσεις σε Εξέλιξη / Ολοκληρωμένες φέτος / Ώρες Εθελοντισμού / Ενεργά Μέλη
- LIVE banner με pulse animation όταν τρέχουν δράσεις (+ Field Hub link αν υπάρχει token)
- Γρήγορες Ενέργειες με badges για νέες δράσεις / εκκρεμείς δηλώσεις
- Κατάσταση Ομάδας: αξιοπιστία (progress bar), μέσος χρόνος δήλωσης, μέλη, alert για εκκρεμείς
- Επερχόμενες εγκεκριμένες ως cards (με ημερομηνία, ώρα, τοποθεσία, άτομα)
- Πρόσφατη Δραστηριότητα: τελευταίες 4 ολοκληρωμένες δράσεις με date badge

## [0.9.24-beta] — 2026-06-18

### Fixed — Field Hub: κουμπιά disabled παρόλο που η δράση τρέχει

- Το Field Hub (`/f/{token}`) έδειχνε όλα τα κουμπιά (GPS, status pings, SOS, έλλειψη) ως greyed-out/disabled επειδή ελέγχε αυστηρά `event_status === 'active'`.
- Πρόβλημα: το Operations Command Centre εμφανίζει/επιτρέπει δράσεις ακόμα και με status `'open'/'confirmed'/'review'` αν έχει παρέλθει η `start_datetime` — ο admin έβλεπε τη δράση ζωντανή στο dashboard αλλά ο mission commander είχε κλειδωμένα κουμπιά.
- Λύση: `$isActive` και `requireActive()` ευθυγραμμίστηκαν με τη λογική του OperationController — ενεργό θεωρείται το event με `status = 'active'` Ή με `status IN (open, confirmed, review)` εφόσον `NOW() >= start_datetime`.

## [0.9.23-beta] — 2026-06-18

### Fixed — Settings: bootstrap is not defined / preset buttons broken
- Το hash routing script στις ρυθμίσεις έτρεχε πριν φορτωθεί το Bootstrap JS, προκαλώντας `ReferenceError: bootstrap is not defined` που τερμάτιζε ολόκληρο το script block — με αποτέλεσμα τα preset buttons Οργανισμού να μην λειτουργούν.
- Λύση: μετακίνηση σε `document.addEventListener('DOMContentLoaded', ...)` που τρέχει αφού φορτωθούν όλα τα scripts της σελίδας.

## [0.9.22-beta] — 2026-06-18

### Fixed — Οργανισμός: preset buttons auto-save
- Κλικ σε preset button (Δήμος / Πολ.Προστ. / Πυροσβεστική / Λιμενικό / Άλλο) κάνει πλέον αυτόματο submit της φόρμας — δεν χρειάζεται χωριστό κλικ στο «Αποθήκευση».

## [0.9.21-beta] — 2026-06-18

### Fixed — Security & reliability hardening (code smell pass)
- **🔴 XSS** `hub.php`: `sender_name` στο `renderMsgs` wrapped με `esc()` · `m.id` σε onclick cast με `parseInt` · coordinates σε Google Maps URLs με `parseFloat`
- **🔴 Fatal error** `hub.php`: `get_flash()` → `flash_get()` (λάθος function name προκαλούσε fatal error σε flash messages στο field hub)
- **🔴 PHP-in-JS injection** `hub.php`: coordinates (`evLat`/`evLng`/`tLat`/`tLng`) πλέον εξάγονται μέσω `json_encode()` αντί raw PHP output
- **🟠 Silent failures** `FieldController` & `OperationController`: όλα τα `catch (Throwable $e) {}` πλέον κάνουν `error_log()` ώστε να εντοπίζονται αποτυχίες notification service
- **🟠 Shortage state machine** `OperationController`: το resolve επιτρέπεται μόνο από `open` ή `acknowledged`, όχι από οποιοδήποτε state · προστέθηκε `else { abort(422) }` για invalid actions
- **🟡 Duplicate stats logic** `OperationController`: εξαγωγή σε `calcTeamStats()` helper — `status()` και `buildStreamSnapshot()` χρησιμοποιούν πλέον κοινή μέθοδο

## [0.9.20-beta] — 2026-06-18

### Added — Προφίλ Οργανισμού (Ρυθμίσεις → Οργανισμός)
- Νέα καρτέλα **Οργανισμός** στις ρυθμίσεις δήμου — ο super admin επιλέγει τον τύπο οργανισμού με preset κουμπιά: 🏛️ Δήμος / 🛡️ Πολ. Προστασία / 🚒 Πυροσβεστική / ⚓ Λιμενικό / 🏢 Άλλο.
- Ζωντανή προεπισκόπηση του πλήρους ονόματος και του σύντομου ονόματος (που εμφανίζεται στα μηνύματα επιχείρησης).
- Το σύντομο όνομα οργανισμού (`ORG_LABEL`) αντικαθιστά όλες τις hardcoded αναφορές σε «Δήμος» στο Επιχειρησιακό Κέντρο και το Field Hub.
- Νέα settings keys: `org_type`, `org_name`, `org_name_short` στον πίνακα `municipality_settings`.

## [0.9.19-beta] — 2026-06-18

### Fixed — Εικονίδιο κάμερας στο GPS σήμα: από κάτω → δίπλα + pulse
- Το μπλε badge κάμερας εμφανίζεται πλέον **δίπλα** (οριζόντια) στην κουκκίδα GPS αντί κάτω της, ώστε να μην καλύπτει το χρώμα ομάδας.
- Προστέθηκε pulse animation (μπλε glow) στο badge κάμερας.

## [0.9.18-beta] — 2026-06-18

### Added — Χρωματική κωδικοποίηση ομάδων στον χάρτη
- Κάθε ομάδα αποκτά αυτόματα μοναδικό χρώμα (παλέτα 10 χρωμάτων) για όλη τη διάρκεια της συνεδρίας.
- **GPS σήμα**: η κουκκίδα φιλοξενεί το χρώμα της ομάδας ως γέμισμα· το περίγραμμα δείχνει την παλαιότητα (πράσινο < 5λ / κίτρινο < 20λ / κόκκινο παλαιότερο).
- **Κάρτες ομάδων**: το αριστερό περίγραμμα + μικρή κουκκίδα δίπλα στο όνομα υιοθετούν το χρώμα της ομάδας.
- **Υπόμνημα**: εμφανίζεται αυτόματα κάτω από τον χάρτη με χρωματιστά chips και ονόματα ομάδων.

## [0.9.17-beta] — 2026-06-18

### Added — Χάρτης: badge φωτογραφίας πάνω στο GPS σήμα
- Αν μια ομάδα έχει στείλει φωτογραφία, το GPS σήμα της στον χάρτη εμφανίζει τώρα ένα μικρό μπλε κυκλικό badge με εικονίδιο κάμερας κάτω από την έγχρωμη κουκκίδα — άμεσα ορατή ένδειξη ότι υπάρχει φωτό.

## [0.9.16-beta] — 2026-06-18

### Added — Φωτογραφίες: ετικέτα ομάδας + ώρα · φωτό στο GPS popup
- Κάτω από κάθε thumbnail στην ενότητα «Φωτογραφίες ομάδων» εμφανίζεται τώρα το όνομα της ομάδας και η ώρα αποστολής.
- Κλικ στο GPS σήμα ομάδας (έγχρωμη κουκκίδα στον χάρτη) ανοίγει popup με το τελευταίο φωτό που έστειλε η ομάδα (αν υπάρχει) — κλικ στο φωτό ανοίγει το πλήρες modal.

## [0.9.15-beta] — 2026-06-17

### Fixed — Επιχειρήσεις: Ομάδες παρούσες πάντα 0
- Η σελίδα `/operations` έδειχνε πάντα `0/N Ομάδες παρούσες` γιατί ψάχνε `status='present'` που δεν υπάρχει — οι πραγματικές τιμές είναι `present_full` και `present_partial`. Διορθώθηκε με `IN ('present_full','present_partial')`.

## [0.9.14-beta] — 2026-06-17

### Fixed — Δραστηριότητα: raw κωδικοί check-in στα ελληνικά
- Η λίστα Δραστηριότητας έδειχνε ωμούς κωδικούς (`present_full`, `present_partial`, κτλ.) αντί για ελληνικές ετικέτες. Διορθώθηκε με `CASE WHEN` στο SQL του `buildActivityFeed()`.

## [0.9.13-beta] — 2026-06-17

### Fixed — Επιχειρησιακό Κέντρο: διπλό όνομα ομάδας στο chat
- Τα μηνύματα ομάδας εμφάνιζαν `Ομάδα Α → Ομάδα Α · 20:38` γιατί το `tag` (που προοριζόταν να δείχνει την *αποδέκτρια* ομάδα σε εντολή δήμου) εφαρμοζόταν και στα εισερχόμενα μηνύματα ομάδας. Τώρα το `tag` εμφανίζεται μόνο για μηνύματα `sender_role === 'command'`.

## [0.9.12-beta] — 2026-06-17

### Fixed — War Room: χάρτης έδειχνε Αθήνα με μία δράση
- Ο χάρτης έκανε auto-fit μόνο όταν υπήρχαν **2+ δράσεις** (`bounds.length > 1`). Με μία μόνο δράση έμενε στις default συντεταγμένες (Αθήνα). Πλέον με 1 δράση κάνει `setView` στις συντεταγμένες της με zoom 13.

## [0.9.11-beta] — 2026-06-17

### Fixed — Δήλωση συμμετοχής σε ενεργή δράση
- Ομάδες δεν μπορούσαν να υποβάλουν δήλωση όταν η δράση ήταν ήδη `active` (π.χ. ενεργοποιήθηκε χωρίς να περάσει από φάση `open`). Προστέθηκε το `active` στους επιτρεπτούς statuses τόσο στη φόρμα (`event_show.php`) όσο και στον controller (`TeamPortalController::apply()`).

## [0.9.10-beta] — 2026-06-17

### Added — Field Hub: αποστολή μηνύματος & αναφορά έλλειψης
- **Σύνθεση μηνύματος στο Field Hub** — ο υπεύθυνος αποστολής μπορεί πλέον να στέλνει
  ιδιωτικά μηνύματα προς τον δήμο απευθείας από το `/f/{token}` (νέο compose input στην
  κάρτα «Επικοινωνία με τον δήμο»). Νέο endpoint `POST /f/{token}/message`.
- **Αναφορά Έλλειψης από πεδίο** — νέα κάρτα «Αναφορά Έλλειψης» με επιλογή τύπου
  (άτομα/εξοπλισμός/ιατρικό/όχημα/άλλο), σοβαρότητα (χαμηλή/μεσαία/υψηλή/κρίσιμη),
  τίτλο και περιγραφή. Αποστέλλει στον πίνακα `shortage_reports` και ειδοποιεί τον δήμο.
  Νέο endpoint `POST /f/{token}/shortage`.

## [0.9.9-beta] — 2026-06-17

### Fixed — Field Hub: upload φωτό δεν έκλεινε το αίτημα
- **`FieldController::photo()` δεν καλούσε `PhotoRequest::fulfillForEventTeam()`** —
  μετά από upload φωτό μέσω του Field Hub (`/f/{token}`), το εκκρεμές αίτημα έμενε για πάντα
  `pending`. Αποτέλεσμα: banner φωτό δεν εξαφανιζόταν και το Επιχειρησιακό Κέντρο
  έδειχνε «Ζητήθηκε φωτό» ακόμη κι αφού ο υπεύθυνος είχε στείλει. Τώρα το αίτημα
  κλείνει αυτόματα, ακριβώς όπως στο `TeamPortalController::uploadPhoto`.

## [0.9.8-beta] — 2026-06-17

### Fixed — Mobile Hub: αιτήματα φωτό & GPS δεν εμφανίζονταν
- **`commsFeed()` δεν έδινε `photo_request` / `gps_request`** — το endpoint `/team/operations/events/{id}/comms`
  επέστρεφε μόνο μηνύματα/SOS/room. Τώρα περιλαμβάνει και τα δύο εκκρεμή αιτήματα.
- **Mobile Hub (`/team/live/{id}`) δεν είχε UI για αιτήματα** — προστέθηκε banner GPS στην κάρτα
  τοποθεσίας και νέα κάρτα φωτό (`#photoCard`) με κουμπί toggle, αίτηση εικόνας, auto-GPS, και
  banner που αναβοσβήνει όταν έχει εκκρεμές αίτημα φωτό.
- **`PhotoRequest::fulfillForEventTeam()` έλειπε** — κατά upload χωρίς `request_id`, η upload
  δεν έκλεινε το εκκρεμές αίτημα. Προστέθηκε η method (mirror του `GpsRequest`) και ο controller
  την καλεί πλέον πάντα αυτόματα.

## [0.9.7-beta] — 2026-06-17

### Added — Αίτημα στίγματος GPS (command → ομάδα)
- **«Ζήτησε στίγμα GPS»** — νέο κουμπί δίπλα στο «Φωτό» σε κάθε κάρτα ομάδας στο
  Επιχειρησιακό Κέντρο. Στέλνει αίτημα στην ομάδα (όπως το αίτημα φωτογραφίας)· η κάρτα
  δείχνει «Ζητήθηκε» μέχρι να σταλεί τοποθεσία. Στο πεδίο (field hub) και στο team portal
  εμφανίζεται banner· μόλις η ομάδα στείλει στίγμα, το αίτημα **κλείνει αυτόματα**.
- **Dropdown στόχου + μαζικά αιτήματα** — πάνω από τη λίστα ομάδων, ο διαχειριστής
  επιλέγει «📢 Όλες οι ομάδες» ή συγκεκριμένη ομάδα και ζητά **φωτό** ή **στίγμα** με μία
  κίνηση. Τα ανά-ομάδα κουμπιά παραμένουν.

### Technical
- Migration `016_gps_requests.sql`: νέος πίνακας `gps_requests` (mirror του
  `photo_requests`). Νέο model `GpsRequest`. Endpoint `OperationController::requestGps`
  + route `POST /operations/events/{id}/request-gps`. Flag `gps_pending` στο `status()`
  και στο SSE snapshot. Ειδοποίηση `NotificationService::gpsRequested`. Το
  `FieldController::location` και το `TeamPortalController::sendLocation` εκπληρώνουν
  (`fulfill`) τυχόν εκκρεμές αίτημα GPS· το `comms`/hub εκθέτουν `gps_request`.
- **Χρειάζεται εκτέλεση migration** — σε update μέσω GitHub Release τρέχει **αυτόματα**
  μέσω του `schema_migrations` / `MigrationRunner` (ή Platform Settings → Updates → run
  pending, ή import του `016_gps_requests.sql`).

## [0.9.6-beta] — 2026-06-17

### Fixed
- **Το badge «↺ Επανασύνδεση…» στο Επιχειρησιακό Κέντρο.** Το live stream στέλνει
  ένα snapshot και κλείνει (φιλικό σε shared hosting), οπότε ο browser
  επανασυνδέεται κάθε λίγα δευτερόλεπτα· αυτό το φυσιολογικό κλείσιμο εμφανιζόταν
  λανθασμένα ως «Επανασύνδεση». Πλέον το badge δείχνει **◉ LIVE** όσο φτάνουν
  snapshots (έλεγχος φρεσκάδας), και «Επανασύνδεση» μόνο αν δεν έρθει δεδομένο για
  >12s. Προστέθηκε και **polling fallback** (κάθε 10s) ώστε ο πίνακας να ανανεώνεται
  ακόμη κι αν κάποιος host μπλοκάρει το streaming.
- **Προβολή φωτογραφίας ομάδας (popup).** Το modal «έβγαινε εκτός οθόνης» με γκρι
  layer γιατί το `position:fixed` έσπαγε μέσα σε ancestor με CSS `transform`· τώρα το
  modal μεταφέρεται στο `<body>` πριν εμφανιστεί, είναι `scrollable` και χωράει πάντα
  στην οθόνη. Επιπλέον φαίνεται **καθαρά η ομάδα προέλευσης** (τίτλος + caption με
  ομάδα και ώρα λήψης) ώστε ο διαχειριστής να ξέρει ποια ομάδα έστειλε τη φωτογραφία.

## [0.9.5-beta] — 2026-06-17

### Fixed
- **Site-wide 500 από κατεστραμμένο release.** Το v0.9.4 zip περιείχε truncated
  `routes/web.php` (αστοχία συγχρονισμού). Το release ξαναχτίστηκε από ακέραιη πηγή
  (lint σε όλα τα PHP πριν τη δημοσίευση). Χωρίς αλλαγές κώδικα πέρα από αυτό.

## [0.9.4-beta] — 2026-06-17

### Fixed
- **Επιχειρησιακό Κέντρο: τα panels κολλούσαν στο «Φόρτωση…» & τα μηνύματα δεν
  εμφανίζονταν (production).** Διπλή αρχικοποίηση του χάρτη: το `views/operations/event.php`
  φτιάχνει inline τον επιχειρησιακό χάρτη, αλλά και το global `public/assets/js/maps.js`
  έτρεχε `initOperationalMap()` στο ίδιο container → Leaflet `Map container is already
  initialized` → uncaught error που σταματούσε το script ΠΡΙΝ τρέξουν τα
  `connectSSE()` / `pollStatus()`, οπότε κανένα panel δεν φόρτωνε και οι αποστολές
  μηνυμάτων δεν ανανέωναν το feed. Το `maps.js` δεν αγγίζει πλέον το `#operationalMap`
  (το διαχειρίζεται αποκλειστικά το event.php), με αμυντικό guard (`_leaflet_id`) και
  στις δύο πλευρές. Cache → `syndrasi-v5` ώστε να φτάσει το νέο `maps.js` στους clients.

## [0.9.3-beta] — 2026-06-17

### Fixed
- **Packaging fix — αρχεία που έλειπαν από το release.** Το v0.9.2-beta zip δεν
  περιείχε τα models `EventRoomMessage`, `PhotoRequest`, `EventPhoto`, το view
  `views/statistics/_overview.php`, ούτε τις migrations `011_event_photos.sql` και
  `015_event_room_messages.sql` (αστοχία συγχρονισμού htdocs→repo). Όλα μπήκαν πλέον
  στο αποθετήριο, ώστε ο in-app updater να τα εγκαθιστά και να τρέχει αυτόματα την
  migration `015`. Καμία αλλαγή κώδικα — μόνο πληρότητα πακέτου.

## [0.9.2-beta] — 2026-06-17

### Added
- **Δωμάτιο Επιχείρησης (κοινό κανάλι)** — ένα live, event-wide chat όπου γράφουν και
  βλέπουν όλοι μαζί: δήμος, operators, όλες οι ομάδες και οι Mission Υπεύθυνοι (field
  link). Ξεχωριστό από τα ιδιωτικά per-team threads (αυτά μένουν καθαρά για
  εντολές/SOS). Εμφανίζεται στο Επιχειρησιακό Κέντρο, στο Mobile Action Hub, στη σελίδα
  Επιχειρησιακών Ενεργειών και στο field hub, με live ανανέωση (~5s / SSE). Νέος πίνακας
  `event_room_messages` (migration `015_event_room_messages.sql`) + model
  `EventRoomMessage` (defensive — δεν σπάει αν λείπει η migration).

### Notes
- Χρειάζεται εκτέλεση της migration `015_event_room_messages.sql` (Updates → run pending).
  Χωρίς push για τα μηνύματα δωματίου (live κανάλι· οι κρίσιμες ειδοποιήσεις παραμένουν
  στα per-team threads/SOS).

## [0.9.1-beta] — 2026-06-17

### Added
- **Σύνδεσμος πεδίου για τον Mission Υπεύθυνο (χωρίς login).** Ο αρχηγός ορίζει
  υπεύθυνο δράσης (μέλος χωρίς λογαριασμό)· τώρα παράγεται προσωπικό token link
  `/f/{token}` όπου ο υπεύθυνος στέλνει **GPS στίγμα, status pings, SOS, φωτογραφία**
  και **βλέπει/επιβεβαιώνει εντολές** του δήμου. Όλα εμφανίζονται στο Επιχειρησιακό
  Κέντρο κάτω από το όνομα της ομάδας. Ο αρχηγός μοιράζεται τον σύνδεσμο με **SMS** ή
  **αντιγραφή** από τη σελίδα της δράσης. Νέα migration `013_field_token.sql`
  (`event_applications.field_token`), νέος `FieldController` + view `field/hub`.
- **Ειδοποιήσεις σε όλη την εφαρμογή** — global poller (~15s) που ενημερώνει ζωντανά
  το καμπανάκι 🔔 και βγάζει **toast popup** για κάθε νέα ειδοποίηση (αίτημα φωτο,
  εντολή/μήνυμα, SOS, ελλείψεις), σε όποια σελίδα κι αν είναι ο χρήστης. Νέο endpoint
  `/notifications/poll`.
- **Χάρτης στην πλευρά της ομάδας** (σελίδα Επιχειρησιακών Ενεργειών): σημείο δράσης +
  θέση ομάδας (τελευταίο στίγμα).
- **Καρφιτσωμένο banner ΕΝΤΟΛΗΣ** στην κορυφή των σελίδων της ομάδας (& field hub) για
  κάθε ανεπιβεβαίωτη εντολή — παραμένει μέχρι «Επιβεβαίωση λήψης».

- **Απολογισμός Δήμου (After-Action)** — στη σελίδα Debriefs της δράσης ο διαχειριστής
  γράφει δική του σύνοψη + συμπεράσματα/βελτιώσεις (`municipality_report`), με
  συγκεντρωτικά τα σύνολα από τις αναφορές ομάδων. Χωρίς migration.

- **Χάρτης και στο Mobile Action Hub (Live) και στο field hub** του Mission Υπευθύνου
  (σημείο δράσης + θέση ομάδας).
- **Background web push** — προστέθηκαν `push` + `notificationclick` handlers στον service
  worker (έλειπαν), ώστε να εμφανίζονται popup ειδοποιήσεις και με κλειστή/background
  εφαρμογή. Τα VAPID keys δημιουργούνται αυτόματα· ο χρήστης ενεργοποιεί push με το 🔔.
  Cache → `syndrasi-v4`.
- **Αίτημα φωτογραφίας στον Mission Υπεύθυνο** — banner στο field hub (live polling),
  ώστε το αίτημα να φτάνει στον υπεύθυνο πεδίου, όχι μόνο στον αρχηγό.
- **Geo-εντολές / σημεία δήμου→ομάδα** — ο δήμος στέλνει **σημείο στον χάρτη**
  (κλικ στον χάρτη ή συντεταγμένες) σε μία ομάδα ή σε όλες, με τύπο **Μετάβαση /
  Περιστατικό / Σημείο ενδιαφέροντος**. Η ομάδα το βλέπει ως **pin στον χάρτη** (Live +
  field hub + ops page) και καρφιτσωμένο banner με κουμπί **«Οδηγίες (Google Maps)»** +
  ACK (για Μετάβαση/Περιστατικό). Το **Περιστατικό** φεύγει με **forced push + SMS**
  (team admins + Mission Υπεύθυνος). Migration `014_event_message_geo.sql`.

### Fixed
- Η σελίδα λεπτομερειών δράσης ομάδας (`/team/events/{id}`) δεν «έσκαγε» (500) όταν
  έλειπε η migration του field link — ο έλεγχος είναι πλέον σε try/catch.

### Notes
- Χρειάζεται εκτέλεση της migration `013_field_token.sql` (Platform Settings → Updates
  → run pending, ή import). Οι υπόλοιπες αλλαγές δεν χρειάζονται migration.

## [0.9.0-beta] — 2026-06-16

### Added — Active-event communications (Κέντρο Επιχειρήσεων)
- **SOS / Man-down** — one-tap πλήκτρο SOS στο Mobile Action Hub και στη σελίδα
  Επιχειρησιακών Ενεργειών της ομάδας. Στέλνει αυτόματα GPS, και ειδοποιεί **forced
  push + SMS** όλο το command staff (admins + operators) ανεξάρτητα από ρυθμίσεις.
  Στο Επιχειρησιακό Κέντρο εμφανίζεται κόκκινο alarm banner που αναβοσβήνει, με
  **Επιβεβαίωση** (κλείνει τον loop πίσω στην ομάδα) και **Κλείσιμο**.
- **Αμφίδρομο chat** ανά ομάδα μέσα στη δράση — η ομάδα και ο δήμος ανταλλάσσουν
  μηνύματα· εμφανίζονται live (SSE στο command, ~5s polling στην ομάδα).
- **Broadcast / Εντολή από το command** — ο δήμος στέλνει μήνυμα ή **εντολή** σε μία
  ομάδα ή σε όλες (broadcast). Οι εντολές ζητούν **ACK** από την ομάδα, που φαίνεται
  στο board.
- **Loop επιβεβαίωσης ελλείψεων** — όταν ο δήμος κάνει «Σε γνώση»/«Λύθηκε» μια έλλειψη,
  ειδοποιείται η ομάδα (in-app + push). Υλοποιήθηκαν τα `acknowledgeShortage` /
  `resolveShortage` (τα routes υπήρχαν αλλά έδειχναν σε ανύπαρκτες μεθόδους).
- **Γρήγορα status pings** — ένα-tap: «Φτάσαμε στο σημείο», «Ολοκληρώθηκε», «Χρειαζόμαστε
  ενίσχυση», «Επιστροφή στη βάση», «Έχουμε περιστατικό».

### Technical
- Migration `012_ops_comms.sql`: `sos_alerts` + `event_messages`. Νέα models
  `SosAlert`, `EventMessage`· `User::commandStaff()`. Νέα endpoints σε
  `TeamPortalController` (sos/message/status-ping/ack-order/comms) και
  `OperationController` (sosAck/sosResolve/sendMessage + shortage ack/resolve). Το SSE
  snapshot και το `status()` μεταφέρουν πλέον `sos` + `messages`.
- **Χρειάζεται εκτέλεση migration** (Platform Settings → Updates → run pending, ή import
  του `012_ops_comms.sql`).

## [0.8.4-beta] — 2026-06-16

### Added
- **SMS gateway ρυθμίσεις ανά δήμο** — νέα καρτέλα «SMS» στις Ρυθμίσεις Δήμου όπου ο
  διαχειριστής ορίζει driver / sender / endpoint / **API key** του παρόχου. Αποθηκεύονται
  στο `municipality_settings` (με τις env μεταβλητές του `config/sms.php` ως platform
  default). Νέο `SmsService::resolveConfig()`· το `SmsService::send()` δέχεται πλέον
  προαιρετικό `$municipalityId`.
- **Επιλογέας καναλιού ανά τύπο ειδοποίησης** — η καρτέλα «Ειδοποιήσεις» αντικαθιστά τα
  on/off email toggles με επιλογή **Καμία / Μόνο Email / Μόνο SMS / Email+SMS** ανά τύπο
  (`notify_channel_<type>`). Το `NotificationService` στέλνει πλέον SMS στους διαχειριστές
  με τηλέφωνο όταν το κανάλι το περιλαμβάνει· το κάλεσμα έκτακτης ανάγκης χρησιμοποιεί τα
  per-δήμο SMS credentials. Διατηρείται backward-compat με το legacy `notify_email_<type>`.

### Notes
- Χωρίς migration — χρησιμοποιεί το υπάρχον `municipality_settings` (key/value) και
  `users.phone`. Τα credits SMS αγοράζονται απευθείας από τον πάροχο· η εφαρμογή κρατά
  μόνο το API key και στέλνει.

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
