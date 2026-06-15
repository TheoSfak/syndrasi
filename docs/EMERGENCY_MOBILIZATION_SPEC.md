# Feature Spec — Emergency Mobilization (Κάλεσμα Έκτακτης Ανάγκης)

**Status:** Draft for review · **Target version:** 0.8.0-beta
**Goal:** Turn a fire/flood/incident into an instant call-out: one button notifies eligible
volunteers, each replies *Έρχομαι / Δεν μπορώ* (+ ETA), and a live command board shows
**confirmed → en route → on-site → departed** in real time, with QR check-in on arrival.

This reuses what already exists: `WebPushService`, `NotificationService`, the war-room
live-snapshot pattern (`OperationController::buildWarRoomSnapshot`), QR check-in, the public
token pattern (migration 006), and the `severity`/`status_color` helpers.

---

## 1. The core design constraint

`push_subscriptions` is keyed by **`user_id`** (login accounts), but the roster you mobilize
is **`team_members`**, which has **no `user_id`** — only `phone` and `email`. So a call-out
must reach people through two channels:

- **Push** → to `users` in the municipality who have an active subscription (team admins,
  operators, and any member who also has an account).
- **Tokenized link** → every targeted `team_member` gets a unique URL (SMS/email) that opens
  their response screen **without login** — same idea as `public_event_tokens`.

Responses are therefore keyed to **`member_id`**, and the token is the identity for
no-account volunteers.

---

## 2. Database — migration `009_mobilizations.sql`

```sql
-- The call-out itself
CREATE TABLE mobilizations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  created_by      INT NULL,                       -- users.id
  event_id        INT NULL,                        -- optional link to an existing event
  title           VARCHAR(255) NOT NULL,
  description     TEXT NULL,
  severity        ENUM('low','medium','high','critical') NOT NULL DEFAULT 'high',
  location_name   VARCHAR(255) NULL,
  latitude        DECIMAL(10,7) NULL,
  longitude       DECIMAL(10,7) NULL,
  status          ENUM('open','active','stood_down') NOT NULL DEFAULT 'open',
  started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at        DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mob_muni (municipality_id),
  INDEX idx_mob_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per targeted member = who was called + how they responded
CREATE TABLE mobilization_responses (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  mobilization_id INT NOT NULL,
  member_id       INT NOT NULL,                    -- team_members.id
  team_id         INT NOT NULL,
  token           CHAR(64) NOT NULL UNIQUE,        -- no-login response link
  response        ENUM('pending','coming','cant','maybe') NOT NULL DEFAULT 'pending',
  eta_minutes     INT NULL,
  notified_push   TINYINT(1) NOT NULL DEFAULT 0,
  notified_at     DATETIME NULL,
  responded_at    DATETIME NULL,
  checked_in_at   DATETIME NULL,                   -- on-site (QR or manual)
  departed_at     DATETIME NULL,
  notes           VARCHAR(255) NULL,
  UNIQUE KEY uq_mob_member (mobilization_id, member_id),
  INDEX idx_mr_mob (mobilization_id),
  INDEX idx_mr_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Live status is derived**, not stored, from `mobilization_responses`:

| Bucket | Condition |
|---|---|
| Confirmed | `response='coming'` |
| En route | `response='coming' AND eta_minutes IS NOT NULL AND checked_in_at IS NULL` |
| On-site | `checked_in_at IS NOT NULL AND departed_at IS NULL` |
| Departed | `departed_at IS NOT NULL` |
| Declined | `response='cant'` |
| No reply | `response='pending'` |

---

## 3. Models

- **`Mobilization`** — `create()`, `find()`, `forMunicipality()`, `activeForMunicipality()`,
  `standDown($id)`, `snapshot($id)` (the derived counts + response list, single query).
- **`MobilizationResponse`** — `seedTargets($mobId, array $memberIds)` (bulk-insert one row
  per member with a `bin2hex(random_bytes(32))` token), `findByToken($t)`, `setResponse(...)`,
  `checkIn($id)`, `depart($id)`.

Batched throughout (no per-member queries) — same discipline as the recent N+1 fixes.

---

## 4. Routes (`routes/web.php`, follow existing style)

```php
/* Emergency mobilization — command side (municipality_admin / event_operator) */
$router->get ('/mobilizations',                 'MobilizationController@index');
$router->get ('/mobilizations/new',             'MobilizationController@create');
$router->post('/mobilizations',                 'MobilizationController@store');     // creates + broadcasts
$router->get ('/mobilizations/{id}',            'MobilizationController@show');       // live command board
$router->get ('/mobilizations/{id}/stream',     'MobilizationController@stream');     // JSON snapshot (polled)
$router->post('/mobilizations/{id}/stand-down', 'MobilizationController@standDown');
$router->post('/mobilizations/{id}/checkin',    'MobilizationController@checkin');    // QR/manual on-site

/* Volunteer side — token link, NO login required */
$router->get ('/m/{token}',          'MobilizationController@respondForm');   // {token} = string, see note
$router->post('/m/{token}/respond',  'MobilizationController@respond');       // coming/cant + ETA
```

> **Router note:** the current router only matches numeric `{param}` (`\d+`). Add a second
> placeholder form (e.g. `{token}` → `[A-Za-z0-9]+`) in `Router::dispatch()`. Small, isolated
> change — one extra `preg_replace` branch.

---

## 5. Controller — `MobilizationController`

- `store()` — `requireRole(['municipality_admin','event_operator'])`; validates; inserts the
  `mobilizations` row; resolves the target set (whole municipality, or chosen teams);
  `MobilizationResponse::seedTargets(...)`; then fires **`NotificationService::mobilize(...)`**;
  `audit('mobilization_created', 'mobilization', $id)`; redirect to the command board.
- `show()` — renders the live board with the first `snapshot()`.
- `stream()` — returns `json_out($mob->snapshot())`; the board polls it every
  `config('config')['map_refresh_seconds']` (already 45s) — same mechanism as the war room.
- `respondForm()/respond()` — public, looked up **by token**; no `requireLogin`. Records
  `coming/cant` + optional `eta_minutes`, stamps `responded_at`.
- `checkin()` — sets `checked_in_at`; reachable from the existing QR check-in surface.

Municipality isolation via `requireMunicipalityAccess($mob['municipality_id'])` on every
command-side action.

---

## 6. Notification fan-out — extend `NotificationService`

`NotificationService::mobilize(array $mob, array $responses)`:

1. **Push** — for each targeted member who maps to a `user` with subscriptions, send via
   `WebPushService::sendToUser($userId, [...])` with a deep link to `/m/{token}`; set
   `notified_push = 1`.
2. **In-app** — insert `notifications` rows (`type = 'mobilization'`) for users.
3. **Fallback** — for members without an account, queue email (`MailService`) and/or SMS to
   `team_members.phone` containing the token link. *(SMS needs a provider — see §8.)*

Keep it resilient: wrap each channel in try/catch and `error_log` on failure (mirrors the
existing `audit()` pattern) so one dead subscription never blocks the rest.

---

## 7. Views

| View | Who | Contents |
|---|---|---|
| `views/mobilizations/index.php` | command | active + past call-outs, status badges (`status_badge()`) |
| `views/mobilizations/form.php` | command | title, severity (reuse `severity_label`), location/map pin, target = whole municipality or specific teams, optional link to an active event |
| `views/mobilizations/show.php` | command | **live board**: big counters (Confirmed / En route / On-site / Declined / No reply), sortable roster with ETA + check-in time, map of on-site pins, **Stand down** button; polls `/stream` |
| `views/mobilizations/respond.php` | volunteer (token) | incident summary + map, huge **Έρχομαι / Δεν μπορώ** buttons, ETA picker, and a personal QR for on-arrival check-in |

Surface a red **"Ενεργό κάλεσμα"** banner in the war room and dashboards while any
mobilization is `active`.

---

## 8. Out of scope / dependencies to decide

- **SMS provider** — Greece options (e.g. Yuboto, AppText, Vonage). Without SMS, the
  fallback is email + the tokenized link only. Needs a config block + a tiny `SmsService`.
- **Roster→account mapping** — push only reaches members who also have a `user`. Worth
  adding an optional `team_members.user_id` later so members can self-respond while logged in.
- **Geo** — reuses the existing Leaflet map assets (`public/assets/js/maps.js`).

---

## 9. Suggested build order

1. Migration 009 + `Mobilization` / `MobilizationResponse` models.
2. Router `{token}` support.
3. Command flow: create → seed targets → command board with polling (no notifications yet).
4. Volunteer token response screen + live counts.
5. Push fan-out via `NotificationService::mobilize`, then email fallback.
6. QR on-site check-in wired to `checked_in_at`.
7. (Optional) SMS provider, war-room banner, `team_members.user_id`.

Steps 1–4 already deliver a working call-out you can demo; 5–6 make it field-ready.
