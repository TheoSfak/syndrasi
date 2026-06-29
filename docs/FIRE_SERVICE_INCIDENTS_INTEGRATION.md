# Fire Service Live Incidents Integration

Technical implementation guide for importing live incidents from the official Hellenic Fire Service page into SynDrasi, with filtering by region/regional unit and conversion into draft operational actions.

## 1. Goal

The feature imports the official live incidents shown at:

```text
https://www.fireservice.gr/el/energa-symvanta
```

The public page embeds the actual incident content through an iframe/source page:

```text
https://www.fireservice.gr/apps/fire2019/symvanta/page.php
```

The MVP goal was:

- Municipality admins can view Fire Service incidents inside SynDrasi.
- Data can be refreshed manually from the UI.
- Data can also be refreshed automatically by cron every 5 minutes.
- Incidents can be filtered by Περιφέρεια, Νομός/Περιφερειακή Ενότητα, category, status, and text.
- The app keeps 7 days of incident history.
- The municipality dashboard shows a red alert for current incidents in Περιφέρεια Κρήτης.
- A municipality admin can create a draft SynDrasi action from any Fire Service incident.
- Map/geocoding is intentionally left for a later phase.

## 2. Files Added Or Changed

New files:

```text
app/Controllers/FireServiceController.php
app/Services/FireServiceIncidentService.php
database/migrations/024_fire_service_incidents.sql
views/fire_service/index.php
docs/FIRE_SERVICE_INCIDENTS_INTEGRATION.md
```

Changed files:

```text
routes/web.php
app/Controllers/CronController.php
app/Controllers/DashboardController.php
views/dashboard/municipality.php
views/layouts/sidebar.php
VERSION
CHANGELOG.md
```

## 3. Source Page Behavior

The official page is not a JSON API. It is HTML rendered inside an iframe.

The iframe page contains three tab sections:

```text
L1 -> ΔΑΣΙΚΕΣ ΠΥΡΚΑΓΙΕΣ
P1 -> ΑΣΤΙΚΕΣ ΠΥΡΚΑΓΙΕΣ
Q1 -> ΠΑΡΟΧΕΣ ΒΟΗΘΕΙΑΣ
```

Inside each section, incidents are grouped under status headings:

```text
ΣΕ ΕΞΕΛΙΞΗ
ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ
ΠΛΗΡΗΣ ΕΛΕΓΧΟΣ
ΛΗΞΗ
```

Each incident appears as an HTML block containing lines like:

```text
ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ
Δ. ΗΡΑΚΛΕΙΟΥ - ΗΡΑΚΛΕΙΟΥ
ΒΙΟΜΗΧΑΝΙΑ – ΒΙΟΤΕΧΝΙΑ
ΕΝΑΡΞΗ 25/06/2026
ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ 28/06/2026
Τελευταία Ενημέρωση πριν από 20 ώρες
```

Because this is HTML and not an API contract, the parser has a safety guard: if the page loads but zero incidents are parsed, the sync is treated as failed and the cached snapshot is preserved.

## 4. Database Schema

Migration:

```text
database/migrations/024_fire_service_incidents.sql
```

### `fire_service_fetches`

Stores each fetch attempt.

Important columns:

```sql
id INT AUTO_INCREMENT PRIMARY KEY
source_url VARCHAR(255)
fetched_at DATETIME
success TINYINT(1)
http_status INT NULL
incidents_found INT
error_message VARCHAR(500) NULL
raw_hash CHAR(64) NULL
```

Purpose:

- Display last sync status.
- Debug upstream failures.
- Keep a 7-day fetch history.

### `fire_service_incidents`

Stores normalized incidents.

Important columns:

```sql
fingerprint CHAR(64) UNIQUE
category VARCHAR(80)
status_label VARCHAR(80)
region VARCHAR(120)
regional_unit VARCHAR(120)
municipality VARCHAR(160)
area_text VARCHAR(255)
location_text VARCHAR(255)
raw_text TEXT
first_seen_at DATETIME
last_seen_at DATETIME
last_fetch_id INT
is_current TINYINT(1)
created_event_id INT NULL
```

Purpose:

- Keep a deduplicated incident history.
- Mark which incidents are present in the latest snapshot.
- Link an incident to a created SynDrasi draft event.

## 5. Sync Flow

Core service:

```text
FireServiceIncidentService::sync()
```

High-level flow:

```text
1. Create fire_service_fetches row with success = 0.
2. Download official iframe HTML.
3. Parse HTML into normalized incident arrays.
4. If parsed count is zero, fail safely and keep old cached data.
5. Mark previous current incidents as is_current = 0.
6. Upsert newly parsed incidents by fingerprint.
7. Mark newly parsed incidents as is_current = 1.
8. Delete incidents/fetches older than 7 days.
9. Mark fetch row success = 1 with count and raw hash.
```

Important behavior:

- `first_seen_at` remains the original discovery time.
- `last_seen_at` updates on every successful sync where the incident is still present.
- `is_current = 1` means the incident exists in the latest successfully parsed snapshot.
- Historical rows remain queryable for 7 days.

## 6. Parser Strategy

Parser entrypoint:

```text
FireServiceIncidentService::parse(string $html): array
```

The parser:

1. Extracts each tab section by id: `L1`, `P1`, `Q1`.
2. Walks status headings and incident blocks in order.
3. Converts `<br>` and table cells into line breaks.
4. Strips HTML tags.
5. Reads:
   - `region` from lines starting with `ΠΕΡΙΦΕΡΕΙΑ`
   - municipality line from lines starting with `Δ.`
   - location/type from the next meaningful line
6. Creates a stable fingerprint from:

```text
category | region | municipality | area | location
```

This avoids treating every status update as a brand-new incident.

## 7. Regional Unit Mapping For Crete

The Fire Service page does not provide a clean `Νομός` field. It provides a line like:

```text
Δ. ΗΡΑΚΛΕΙΟΥ - ΗΡΑΚΛΕΙΟΥ
```

For the MVP, Crete regional units are inferred with a municipality-name mapping:

```text
Π.Ε. ΗΡΑΚΛΕΙΟΥ
Π.Ε. ΛΑΣΙΘΙΟΥ
Π.Ε. ΡΕΘΥΜΝΟΥ
Π.Ε. ΧΑΝΙΩΝ
```

Implemented in:

```text
FireServiceIncidentService::creteRegionalUnitForText()
```

This same method is used for:

- parsing incidents
- deriving the municipality admin default filter

If the municipality cannot be mapped to a specific regional unit, default filtering still uses:

```text
ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ
```

## 8. Routes

Added in:

```text
routes/web.php
```

Routes:

```php
$router->get('/fire-service', 'FireServiceController@index');
$router->post('/fire-service/sync', 'FireServiceController@sync');
$router->post('/fire-service/{id}/create-event', 'FireServiceController@createEvent');
$router->get('/cron/fire-service', 'CronController@fireService');
```

Access:

- `/fire-service`: municipality admins only
- manual sync: municipality admins only
- create event: municipality admins only
- cron endpoint: protected by `Authorization: Bearer <cron_secret>`

## 9. UI Page

View:

```text
views/fire_service/index.php
```

The page includes:

- title and short explanation
- manual refresh button
- latest fetch status
- official source link
- cron command snippet
- filters
- incident table
- create/open draft action button

Filters:

```text
Περιφέρεια
Νομός / Π.Ε.
Κατηγορία
Κατάσταση
Αναζήτηση
Τρέχον snapshot ή 7ήμερο ιστορικό
```

Default for municipality admins:

- region: `ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ`
- regional unit: inferred when possible from the municipality name/city
- current snapshot only

## 10. Sidebar Entry

Changed:

```text
views/layouts/sidebar.php
```

Added only to municipality admin menu:

```php
['/fire-service', 'bi-fire', 'Συμβάντα Πυροσβεστικής']
```

Event operators do not see this MVP page.

## 11. Manual Refresh

Controller method:

```text
FireServiceController::sync()
```

Flow:

```text
POST /fire-service/sync
CSRF validated by global POST middleware
requires municipality_admin
calls FireServiceIncidentService::sync()
sets flash success/failure
redirects back to /fire-service
```

This is for immediate “pull now” behavior from the UI.

## 12. Cron Refresh

Controller method:

```text
CronController::fireService()
```

Cron endpoint:

```text
GET /cron/fire-service
```

Security:

```http
Authorization: Bearer <cron_secret>
```

Recommended cron:

```bash
*/5 * * * * curl -s -H "Authorization: Bearer <cron_secret>" "https://1stop.gr/public/cron/fire-service" > /dev/null
```

The `cron_secret` is the same platform setting used by other SynDrasi cron endpoints.

## 13. Dashboard Red Alert

Changed:

```text
app/Controllers/DashboardController.php
views/dashboard/municipality.php
```

Dashboard controller calls:

```php
FireServiceIncidentService::creteAlert()
```

Only for:

```php
current_role() === 'municipality_admin'
```

The alert counts current incidents where:

```sql
is_current = 1
AND region = 'ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ'
```

It displays:

- total current Crete incidents
- count per status
- latest fetch timestamp
- link to `/fire-service?region=ΠΕΡΙΦΕΡΕΙΑ ΚΡΗΤΗΣ`

## 14. Creating A Draft SynDrasi Event

Service method:

```text
FireServiceIncidentService::createEventDraft()
```

Controller route:

```text
POST /fire-service/{id}/create-event
```

Behavior:

1. Finds the incident.
2. If it already has `created_event_id`, redirects to that event.
3. Creates a draft SynDrasi event with:
   - title: `Πυροσβεστικό συμβάν: <location>`
   - description: official source summary
   - location name: municipality/area from the Fire Service incident
   - address: incident location/type text
   - start: now
   - end: now + 4 hours
   - status: `draft`
4. Stores `created_event_id` on the incident row.
5. Redirects admin to the event edit page.

This keeps the admin in control before publishing to teams.

## 15. Safety And Failure Handling

Important safety choices:

- No scraping on every page view.
- Fetching happens only through manual sync or cron.
- If the official source fails, the app keeps cached data.
- If the source loads but parser returns zero incidents, the sync fails safely.
- Fetch attempts are logged in DB.
- Raw HTML is not stored, only raw normalized text and hash.
- Old data is cleaned after 7 days.
- Create-event is idempotent per incident via `created_event_id`.

## 16. Porting Checklist For Another App

To move this integration to another PHP app:

1. Add tables equivalent to:
   - `fire_service_fetches`
   - `fire_service_incidents`
2. Copy the parser/fetch service logic from:
   - `FireServiceIncidentService`
3. Adapt DB helpers:
   - replace `dbq()`, `db()`, `Event::create()`, `current_user_id()`, etc.
4. Add a protected cron route:
   - `GET /cron/fire-service`
5. Add a manual sync route:
   - `POST /fire-service/sync`
6. Add an incidents list page with filters.
7. Add a dashboard alert query for the desired region.
8. Add a “create draft action/event” flow if the target app has an operational event model.
9. Keep the zero-parsed-incidents safety guard.
10. Add monitoring/logging for parser failures, because the upstream HTML may change.

## 17. Minimal Parser Test

A simple smoke test can be:

```php
require 'app/Services/FireServiceIncidentService.php';

$html = file_get_contents(FireServiceIncidentService::SOURCE_URL);
$items = FireServiceIncidentService::parse($html ?: '');

if (count($items) < 1) {
    throw new RuntimeException('No incidents parsed');
}

echo 'Parsed incidents: ' . count($items) . PHP_EOL;
```

During implementation validation, the live parser returned more than 100 incidents and several incidents in Περιφέρεια Κρήτης.

## 18. Known Limitations

- The official source is HTML, not a stable API.
- There are no coordinates in the source.
- Regional unit mapping is heuristic, currently focused on Crete.
- The fingerprint does not include status, so status changes update the same incident instead of creating duplicates.
- The integration should be monitored after deployment for upstream HTML changes.

## 19. Suggested Phase 2

- Add geocoding for municipality/area to display approximate map markers.
- Add configurable municipality-to-regional-unit mapping in settings.
- Add notification records or push alerts for newly seen incidents by municipality filter.
- Add severity scoring by category/status.
- Add “ignore/hide incident” per municipality.
- Add import source health card in admin settings.
