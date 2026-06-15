# SynDrasi — Code Smell & Code Review Report

**Date:** 2026-06-14 · **Version reviewed:** 0.7.2-beta
**Scope:** 105 PHP files (~18,240 lines), custom MVC (no framework), MySQL/PDO backend.

---

## Executive summary

This is a **well-built custom PHP MVC application**, noticeably more disciplined than most homegrown frameworks. Security fundamentals are strong: every DB query is parameterized, CSRF is enforced globally, sessions are hardened, passwords use `password_hash`/`password_verify`, login has rate-limiting and email-enumeration protection, and multi-write operations use transactions.

The issues below are therefore mostly about **one real bug, production-readiness, and maintainability** — not gaping security holes. One bug should be fixed today; the rest is cleanup that will pay off as the codebase grows.

| Severity | Count | Theme |
|---|---|---|
| 🔴 High | 1 | Undefined function breaks the CSRF-failure path on JSON requests |
| 🟠 Medium | 4 | Hardcoded `development` env, god methods, N+1 + logic in views, duplicated validation |
| 🟡 Low | 5 | Redundant mail paths, rate-limit row buildup, one unescaped output, no base Model, getenv boilerplate |

---

## 🔴 High

### 1. `json_response()` is called but never defined
`public/index.php:65` calls `json_response(...)` when a JSON POST fails CSRF validation, but the function defined in `functions.php` is `json_out()`. There is no `json_response` anywhere in the codebase.

```php
// public/index.php:65
if (wants_json()) {
    json_response([...], 419);   // ← Call to undefined function
}
```

**Impact:** Any AJAX/JSON POST with a missing or stale CSRF token triggers a **fatal `Call to undefined function`** instead of the intended clean `419` JSON response. Because `env` is currently `development` (see #2), the fatal error and stack trace are rendered to the client. This is squarely on the security path, so it's the most important fix.

**Fix:** rename the call to `json_out($data, 419)`.

---

## 🟠 Medium

### 2. Environment is hardcoded to `development` in a deployed copy
`config/config.php` sets `'env' => 'development'`, while `config/database.php` and `config/mail.php` correctly read from `getenv()`. This copy lives under `xampp/htdocs`, so in any non-local deployment `display_errors` is **on** and `error_reporting(E_ALL)` leaks full paths and stack traces to users (and compounds #1).

**Fix:** drive `env` from the environment like the other config files:
```php
'env' => getenv('APP_ENV') !== false ? getenv('APP_ENV') : 'production',
```
Defaulting to `production` is the safer fail-closed choice.

### 3. God methods mixing query + compute + presentation
Several methods are long and do too much — data access, calculation, and view-model assembly all inline:

| Method | Lines |
|---|---|
| `EmailTemplate::definitions()` | 168 |
| `DashboardController::municipality()` | 157 |
| `OperationController::buildWarRoomSnapshot()` | 110 |
| `EventController::saveReconciliation()` | 104 |
| `ReportController::pdfAnnual()` | 100 |
| `OperationController::buildStreamSnapshot()` | 100 |

These are hard to test and to change safely. The dashboards and snapshot builders in particular bundle many independent SQL aggregations into one function. Extract the per-metric queries into `StatsService`/model methods and keep controllers thin (gather → render).

### 4. Database queries and N+1 patterns inside views
`views/events/reconcile.php` and `views/settings/users.php` run `dbq()` / model calls **inside the view**, including in a `foreach` loop:

```php
// views/events/reconcile.php (inside foreach $applications)
$appMembers = TeamMember::forApplication($app['id']);          // 1 query per app
foreach (VolunteerParticipation::forApplication($app['id'])...) // +1 query per app
```

This is both a layering smell (data fetching in the presentation layer) and an **N+1**: a reconciliation with 20 teams fires ~40 extra queries. The same pattern appears in `NotificationService.php:327` (`TeamMember::find` in a loop) and `ApplicationController.php:96`. Move the fetching into the controller and pass a pre-assembled array to the view; batch the per-app lookups into a single `WHERE app_id IN (...)` query.

### 5. Duplicated password-validation logic
The "min 8 chars + confirmation match" block is copy-pasted across `AuthController::doResetPassword()`, `AuthController::changePassword()`, and the admin/team user-creation flows, each with its own Greek strings. Extract a single `validate_password($new, $confirm): ?string` helper (returns an error message or null) so the rule and its wording live in one place.

---

## 🟡 Low

### 6. Two parallel mail implementations
There is a custom `SmtpMailer.php` (hand-rolled SMTP + MIME) **and** a PHPMailer code path referenced in config/comments. Two ways to send mail doubles the maintenance surface and the boundary/MIME-building code is duplicated between `MailService.php` and `SmtpMailer.php`. Pick one driver path and delete the other.

### 7. Rate-limit rows accumulate forever
Login throttling stores `login_fail_*` / `login_lock_*` keys in `app_settings` (`AuthController::setRateSetting`) and never deletes them. Over time the table fills with stale per-IP+email rows. Add cleanup (e.g. in `CronController`) or move throttling to a TTL store / a dedicated table with a timestamp you can prune.

### 8. One unescaped view output
`views/dashboard/municipality.php:134` echoes `<?= $label ?>` / `<?= $color ?>` without `e()`. These currently come from an app-controlled category map so risk is low, but it's inconsistent with the otherwise-disciplined `e()` usage everywhere else. Wrap in `e()` for consistency and future-proofing.

### 9. No base Model — repeated CRUD boilerplate
Models are static "data-mapper" classes (fine in itself), but `find()`, `all()`, and simple `SELECT * ... WHERE id` patterns are re-implemented in nearly every model. A small `BaseModel` (table name + generic `find`/`where`) would remove a lot of near-identical code.

### 10. Repeated `getenv(...) !== false ? ... : default` boilerplate
The config files repeat the same ternary for every key. A tiny `env($key, $default)` helper would tidy `database.php` and `mail.php` and make the `config.php` fix in #2 cleaner.

---

## What's already done well (keep it)

- **SQL:** 100% parameterized via `dbq()`; dynamic `WHERE`/`LIMIT` use bound params or `(int)` casts — no injection found.
- **CSRF:** enforced globally for every POST in the front controller; `hash_equals` comparison; helper `csrf_field()`.
- **Sessions:** httponly + SameSite=Lax + Secure-when-HTTPS cookies; `session_regenerate_id(true)` on login; auto-destroy when the user record is gone.
- **Auth:** `password_hash`/`password_verify`, login rate-limiting with lockout, email-enumeration-safe password reset, 64-char token + 1-hour expiry + single-use.
- **Output:** consistent `e()` escaping across views (one exception, #8).
- **Data integrity:** real DB transactions around multi-row writes (`saveReconciliation`, team-portal apply).
- **Access control:** centralized `requireLogin` / `requireRole` / municipality & team data-isolation middleware.
- **Secrets:** none hardcoded — DB, mail, and VAPID keys all come from env or the DB.

---

## Suggested priority order

1. **Today:** Fix #1 (`json_response` → `json_out`) and #2 (env from getenv, default `production`).
2. **This sprint:** #4 (pull queries out of views, kill the N+1s) and #5 (shared password validation).
3. **Backlog:** #3 (decompose god methods), #6 (single mail path), #7 (rate-limit cleanup), #9/#10 (base model + env helper), #8 (escape `$label`).
