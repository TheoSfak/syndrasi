# SynDrasi Deploy Notes

Last updated: 2026-06-30  
Current release line: `0.15.2-beta`

This file summarizes what has been built so far in the recent SynDrasi work cycle and what must be checked when deploying to production.

## Current State

SynDrasi now includes:

- Public/action story presentation pages for completed actions.
- Interactive story map replay with GPS points, requests, team movement arrows, and movement overview mode.
- Field communications/transcript section in the story page.
- Fire Service live incidents integration.
- Telegram notification channel across normal, operational, Fire Service, and fire-risk flows.
- Team assistant chief accounts.
- Superadmin overview of all teams and volunteers.
- Civil Protection fire-risk map Telegram alerts with manual upload fallback.
- Fire Service incident -> emergency mobilization flow.
- Demo volunteer email sanitization for production safety.
- Municipality email history/statistics tab with clear-history action.

The GitHub Actions idea for fetching the Civil Protection fire-risk map was removed in `0.14.19-beta` because it was not reliable enough for production.

## Major Features Built

### 1. Completed Action Story Page

Main routes:

- `GET /events/{id}/story`
- `GET /events/{id}/story/download`
- `POST /events/{id}/story/publish`
- `GET /public/story/{token}`
- `GET /public/story/{token}/photo/{id}`
- `GET /public/story/{token}/video/{id}`

Main files:

- `app/Controllers/EventController.php`
- `app/Controllers/PublicEventController.php`
- `app/Services/StoryService.php`
- `views/events/story.php`
- `database/migrations/022_story_token.sql`

Capabilities:

- Standalone presentation page for closed/completed actions.
- Public share link with token.
- Downloadable HTML report.
- Gallery with photos/videos.
- Timeline, attendance, shortages, check-ins, team metrics.
- Public mode hides sensitive operational/personal data.
- Media is served through controlled routes instead of exposing storage paths.

Map/story enhancements:

- Replay of action timeline.
- GPS points and requested GPS/photo/video moments.
- Movement arrows from team last known position to ordered destination.
- "Μετακινήσεις" overview button that draws movement routes without needing to scrub the replay bar.
- Filter for all teams or selected teams.
- Communications transcript between municipality/command and teams.

## 2. Fire Service Incidents

Main routes:

- `GET /fire-service`
- `POST /fire-service/sync`
- `POST /fire-service/{id}/create-event`
- `GET /cron/fire-service`

Main files:

- `app/Controllers/FireServiceController.php`
- `app/Services/FireServiceIncidentService.php`
- `views/fire_service/index.php`
- `database/migrations/024_fire_service_incidents.sql`
- `database/migrations/027_fire_service_telegram_notifications.sql`
- `docs/FIRE_SERVICE_INCIDENTS_INTEGRATION.md`

Capabilities:

- Pulls official active incidents from the Fire Service source.
- Stores 7 days of incident history.
- Municipality admins can filter by region, regional unit, category, status, and text.
- Manual "Άμεση ενημέρωση" button.
- Cron endpoint intended every 5 minutes.
- Dashboard red alert for current Crete incidents.
- Create draft SynDrasi action from an incident.
- Start an emergency mobilization directly from a current incident.
- Telegram notifications for Crete incidents in statuses:
  - `ΣΕ ΕΞΕΛΙΞΗ`
  - `ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ`
- Telegram dedupe: one message per municipality/incident/status, and resend only on relevant status change.

Production cron:

```bash
*/5 * * * * curl -s -H "Authorization: Bearer <cron_secret>" "https://1stop.gr/public/cron/fire-service" > /dev/null
```

## 3. Telegram Notifications

Main files:

- `app/Services/TelegramService.php`
- `app/Services/NotificationService.php`
- `app/Controllers/SettingsController.php`
- `views/settings/municipality.php`
- `database/migrations/026_telegram_notifications.sql`

Capabilities:

- Per-municipality Telegram bot token.
- Command group chat ID.
- Shared team/volunteer group chat ID.
- Optional per-team Telegram chat ID.
- Test buttons for command group and shared team group.
- Telegram toggle per notification type.
- Email/SMS/Telegram settings checked from `NotificationService`.
- Request-level Telegram dedupe avoids duplicate posts when command/team chats are the same group.

Important behavior:

- Team-facing messages use team-specific chat first.
- If no team-specific chat exists, they fall back to the shared team group.
- A municipality can use one Telegram group for admins and all volunteers by putting the same `chat.id` in both command and shared team fields.
- Telegram cannot send directly to a phone number. Users must be in the configured group/channel/chat.

Forced/critical flows:

- SOS
- incident/order style operational messages
- emergency operational events

These send through Telegram when Telegram is configured, even when normal non-critical notification channels are more conservative.

## 4. Assistant Chiefs

Main routes:

- `GET /teams/{id}/assistants`
- `POST /teams/{id}/members/{memberId}/assistant/revoke`
- `POST /team/members/{id}/assistant/promote`
- `POST /team/members/{id}/assistant/revoke`

Main files:

- `app/Controllers/TeamMemberController.php`
- `app/Controllers/TeamController.php`
- `app/Models/TeamMember.php`
- `views/team/members/index.php`
- `views/teams/assistants.php`
- `database/migrations/025_team_assistant_admins.sql`

Capabilities:

- A team chief can promote active team members to "Βοηθός Αρχηγού".
- Assistant chiefs get a linked `team_admin` login.
- They receive reset/invite email to set password.
- They have the same operational/team rights as a team chief.
- They cannot promote or revoke other assistants.
- Municipality admins can see and revoke assistant access if needed.
- Revoking assistant status disables the linked login account.

## 5. Superadmin Team/Volunteer Overview

Main route:

- `GET /admin/teams`

Main files:

- `app/Controllers/AdminController.php`
- `views/admin/teams_overview.php`

Capabilities:

- Superadmin can view all municipalities, teams, team admins, assistants, and volunteers.
- Includes contact details, roster fields, capabilities, Telegram presence, linked login status, active member counts, assistant counts.
- Filters by municipality, team, status, and search text.

## 6. Civil Protection Fire-Risk Map Alerts

Main routes:

- `GET /cron/fire-risk-map`
- `POST /settings/fire-risk-map/sync`
- `POST /settings/fire-risk-map/upload`
- `POST /cron/fire-risk-map/ingest`
- `GET /public/fire-risk-map/{YYYYMMDD}`

Main files:

- `app/Services/FireRiskMapService.php`
- `app/Controllers/CronController.php`
- `app/Controllers/SettingsController.php`
- `app/Controllers/FireRiskMapController.php`
- `views/settings/municipality.php`
- `database/migrations/028_fire_risk_map_notifications.sql`

Capabilities:

- Attempts to fetch the official Civil Protection map automatically.
- Parses the map image colors for Crete regional units:
  - Χανιά
  - Ρέθυμνο
  - Ηράκλειο
  - Λασίθι
- Sends Telegram text with level and label per regional unit.
- Sends to command group and shared team group.
- Dedupe: one fire-risk Telegram per municipality per map date.
- Manual button "Έλεγχος τώρα" for automatic check.
- Manual image upload fallback if Civil Protection blocks the production server with `403 Forbidden`.
- Uploaded maps are stored in `storage/uploads/fire_risk_maps`.
- Local public read-only map URL is generated for Telegram.
- Generic protected ingest endpoint remains for a future non-GitHub fetcher.

Production cron:

```bash
0 * * * * curl -s -H "Authorization: Bearer <cron_secret>" "https://1stop.gr/public/cron/fire-risk-map" > /dev/null
```

Manual fallback:

1. Go to Settings -> Notifications.
2. Find "Χειροκίνητος έλεγχος χάρτη κινδύνου".
3. Upload the official map image.
4. Select the correct map date.
5. Press "Ανέβασμα & αποστολή".

Important known limitation:

- The official Civil Protection site may return `403 Forbidden` to production server requests.
- The reliable fallback currently is manual upload or a future external fetcher that posts to `/cron/fire-risk-map/ingest`.
- GitHub Actions fetcher was tried and removed.

## 7. Fire Service Incident Mobilization

Main route:

- `GET /fire-service/{id}/mobilize`
- `POST /fire-service/{id}/mobilize`

Main files:

- `app/Controllers/FireServiceController.php`
- `app/Services/FireServiceIncidentService.php`
- `views/fire_service/index.php`

Capabilities:

- Municipality admin can press "Κινητοποίηση" on a current Fire Service incident.
- Admin reviews the incident, active teams, selected teams, and capability filters before sending.
- SynDrasi creates an active emergency mobilization with incident title, description, location, and inferred severity.
- The existing mobilization fan-out sends personal response links through the configured channels.
- Admin is redirected to the live mobilization board to watch volunteer replies.

## 8. Municipality Email History

Main route:

- `POST /settings/mail/history/clear`

Main files:

- `app/Controllers/SettingsController.php`
- `views/settings/municipality.php`

Capabilities:

- Municipality admin can open Settings -> Ιστορικό Email.
- Shows totals for all queued/deferred emails of that municipality.
- Shows sent, pending, failed, last 24h, last 7d, recent rows, daily totals, and frequent recipients.
- Municipality admin can delete all email history for their municipality after typing `DELETE`.

## Database Migrations To Ensure Applied

Recent relevant migrations:

- `022_story_token.sql`
- `024_fire_service_incidents.sql`
- `025_team_assistant_admins.sql`
- `026_telegram_notifications.sql`
- `027_fire_service_telegram_notifications.sql`
- `028_fire_risk_map_notifications.sql`
- `029_sanitize_demo_volunteer_emails.sql`

After update, run migrations from Superadmin -> Maintenance/Migrations or the existing migration runner.

Migration `029` replaces volunteer/team/admin demo recipients with non-deliverable `@syndrasi.local` addresses, preserving only `theodore.sfakianakis@gmail.com` and `irmaiden@gmail.com`.

## Production Settings Checklist

### Cron Secret

Set/verify `cron_secret` in Superadmin settings. Cron endpoints require:

```http
Authorization: Bearer <cron_secret>
```

### Telegram

For each municipality that should receive Telegram:

- Enable Telegram sending.
- Add Bot Token.
- Add Command Chat ID.
- Add shared team/volunteer Chat ID.
- Enable Telegram toggles under Settings -> Notifications:
  - completed action
  - operational alerts as needed
  - Fire Service Crete incidents
  - Fire-risk map Crete

### Storage Permissions

The web server must be able to write:

- `storage/logs`
- `storage/uploads`
- `storage/uploads/fire_risk_maps`
- `storage/exports`
- `storage/backups`
- `storage/updates`

### Fire Service Cron

Recommended every 5 minutes:

```bash
*/5 * * * * curl -s -H "Authorization: Bearer <cron_secret>" "https://1stop.gr/public/cron/fire-service" > /dev/null
```

### Fire-Risk Map Cron

Recommended every 60 minutes:

```bash
0 * * * * curl -s -H "Authorization: Bearer <cron_secret>" "https://1stop.gr/public/cron/fire-risk-map" > /dev/null
```

If automatic fetch fails because of Civil Protection `403 Forbidden`, use manual upload.

## Production Smoke Tests

After deploying a release:

1. Open `/settings`.
2. Confirm Settings page loads without "Σφάλμα συστήματος".
3. Send Telegram test to command group.
4. Send Telegram test to shared team group.
5. Open `/fire-service`.
6. Press "Άμεση ενημέρωση".
7. Confirm incidents list updates or a clear error is shown.
8. Confirm dashboard red alert appears for active Crete incidents.
9. Open a completed action.
10. Open "Παρουσίαση Δράσης".
11. Check story map, replay, movement overview, communications, gallery.
12. Publish public story link and open it in a private/incognito window.
13. Run fire-risk map "Έλεγχος τώρα".
14. If automatic fetch fails, upload a map image manually and confirm Telegram output.
15. Verify no duplicate Telegram is sent for the same fire-risk date.
16. On `/fire-service`, open "Κινητοποίηση" from a current incident, select teams/capabilities, send in a test municipality, and confirm the live mobilization board opens with targeted volunteers.
17. Verify demo volunteer emails are sanitized in production after migrations run.
18. Open Settings -> Ιστορικό Email and confirm email counters/recent rows load; do not clear production history unless intentionally requested.

## Release Process

Production uses GitHub releases/latest.

For every deployable change:

1. Update `VERSION`.
2. Update `CHANGELOG.md`.
3. Commit.
4. Create tag.
5. Push branch and tag.
6. Create GitHub Release and mark it latest.
7. Run updater on production.
8. Run migrations.
9. Perform smoke tests.

## Latest Release In This Snapshot

Latest release at the time this file was written:

- `v0.15.2-beta`
- Purpose: add municipality email history/statistics and clear-history action.
