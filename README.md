# SynDrasi · ΣυνΔράσι

**Municipal volunteer-coordination & civil-protection operations platform**
Πλατφόρμα συντονισμού εθελοντικών ομάδων και πολιτικής προστασίας για δήμους.

> Version **0.5.0-beta** · PHP 8.1+ · MySQL/MariaDB · PWA

SynDrasi helps Greek municipalities coordinate volunteer, rescue and medical teams
for public events and civil-protection operations (δράσεις): publishing events,
collecting team applications, approvals, a live operational map with team pings,
check-ins, shortage reporting, real-time command, statistics, trends and awards.

---

## ✨ Features

### Operations (real-time)
- **Κέντρο Επιχειρήσεων** — single-event command center with a live Leaflet map,
  team status cards, check-in tracking, shortage management, notes & activity feed,
  countdown timer and a dark "mission control" mode.
- **Κέντρο Συντονισμού** *(new in 0.5.0)* — multi-event "war room": every active
  event on one municipality-wide map at once, colour-coded by coverage/shortages,
  with a live list, global totals and one-click drill-down into each command center.
- **Real-time via SSE** — Server-Sent Events using a XAMPP-safe *close-and-retry*
  pattern (one JSON snapshot per request, browser reconnects every 3 s).
- **Mobile Action Hub** (`/team/live/{id}`) — phone-optimized check-in, send-location
  and shortage reporting for team leaders in the field.

### Events & teams
- Full event lifecycle state machine: `draft → published → active → completed → archived`
  (+ `cancelled`, early-end `closed` + reconciliation).
- Volunteer teams & member rosters with configurable custom fields.
- Team applications with bulk approval, shift scheduling, and post-event debriefs.
- Event cloning and per-municipality event defaults.

### Analytics & reporting *(Advanced reporting new in 0.5.0)*
- **Στατιστικά** — single-year overview, by-category, by-month, team ranking.
- **Αναλύσεις & Τάσεις** — multi-year (year-over-year) trends: KPI cards with YoY
  deltas, diachronic charts, monthly comparison, response-time trend, top-team
  hours per year, with CSV export (yearly / category / teams).
- PDF reports (event coverage, certificates, awards, annual) and CSV exports.

### Platform
- Per-municipality settings (SMTP, map defaults, branding, award thresholds,
  notification toggles, member fields, customizable email templates).
- In-app + email notifications (8 branded templates) and Web Push (VAPID).
- Public event pages (no login) and shareable links.
- Super-admin panel: municipalities, users, impersonation, global settings.
- PWA: installable, offline fallback, service worker with stale-while-revalidate caching.

---

## 🧱 Tech stack

| Layer | Choice |
|---|---|
| Language | PHP 8.1+ (no framework, no Composer required) |
| Database | MySQL 8 / MariaDB 10.5+ |
| Frontend | Bootstrap 5, Leaflet.js, Chart.js, vanilla JS |
| Auth | Session-based, role guards (`requireRole`) |
| Real-time | Server-Sent Events (close-and-retry) |
| Push | Web Push (VAPID) + service worker |
| Server | Apache (XAMPP) on Windows, or any PHP host |

---

## 👥 Roles

| Role | Access |
|---|---|
| `super_admin` | Full platform, all municipalities |
| `municipality_admin` | Full access within their municipality |
| `event_operator` | Operations pages only |
| `team_admin` | Team portal: applications, check-ins, live hub, debriefs |

---

## 🚀 Installation

1. **Database**
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p syndrasi < database/seed.sql   # optional demo data
   ```

2. **Configuration** — adjust `config/database.php`, `config/mail.php`, `config/config.php`
   (all support environment-variable overrides: `DB_HOST`, `DB_NAME`, `DB_USER`,
   `DB_PASS`, `MAIL_DRIVER`, …).

3. **Web root** — point Apache at the `public/` directory (the app uses a front
   controller `public/index.php` + `.htaccess`). On XAMPP, place the project under
   `htdocs/` and browse to `http://localhost/syndrasi/public/`.

4. **Writable storage** — ensure `storage/logs`, `storage/exports`, `storage/uploads`
   are writable by the web server.

---

## 🗂️ Project structure

```
app/
  Controllers/   AnalyticsController, OperationController, EventController, …
  Models/        Event, VolunteerTeam, TeamMember, MunicipalitySetting, …
  Services/      StatsService, CsvService, MailService, NotificationService
core/            Router, helpers, base classes
config/          config.php, database.php, mail.php
database/        schema.sql, seed.sql, migrations/
public/          index.php, assets/ (css,js,img), manifest, service-worker.js
routes/          web.php  (all routes)
views/           dashboard, events, operations, analytics, statistics, team, …
```

---

## 📈 Versioning

Semantic-ish: `MAJOR.MINOR.PATCH`. Current line is **beta** until feature-complete.
See [`CHANGELOG.md`](CHANGELOG.md) for the history. Current: **0.5.0-beta**.

---

## 📝 License & credits

Built for Greek municipalities. Proposal & development by **Theodore Sfakianakis**
— theodore.sfakianakis@gmail.com.
