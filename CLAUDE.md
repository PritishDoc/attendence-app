# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Attendify** — multi-tenant SaaS attendance platform. Zero-dependency stack: PHP 8 backend (no Composer, no framework), vanilla-JS/HTML frontend (no build step, no npm), MySQL. There is no test suite, linter, or package manager in this repo — verification is done by running the app and hitting endpoints.

## Commands

```bash
# Local dev server (router.php is required — it emulates the .htaccess rewrites)
php -S localhost:8000 router.php

# Verify DB connectivity / list tables
php test_db.php

# Import base schema (runs database/schema.sql against the configured DB)
php import_schema.php

# Apply an ad-hoc migration script
php scratch_db_phase3.php
```

Deployment is automatic: pushing to `main` triggers [.github/workflows/deploy.yml](.github/workflows/deploy.yml), which FTP-syncs the whole repo to Hostinger (`public_html/attendify/`). There is no staging environment.

**⚠️ The DB credentials hardcoded in [api/config/database.php](api/config/database.php) point at the live production MySQL server.** Every local `php -S` run, every `scratch_db*.php` script, and every test check-in writes to production data. Confirm with the user before running anything that mutates schema or rows.

## Request flow

`.htaccess` (prod) / `router.php` (dev) → `/api/*` → [api/index.php](api/index.php) → controller.

[api/index.php](api/index.php) is a single flat `if/elseif` chain matching `$uri` + `$method` (with `preg_match` for path params). Adding an endpoint requires **two** edits there: the `require_once` at the top *and* the route branch. Models/services not listed in the top requires block are pulled in by the controllers that need them (e.g. `Leave.php` via LeaveController).

Controllers are static-method-only classes; they never return — they terminate via `Response::success()` / `Response::error()`, which `exit`. Response envelope is always `{success, message, data?}` or `{success, message, errors?}`; paginated lists use `Response::paginated()` which emits `{success, data, pagination}` (no `message`).

Models wrap PDO directly (`Database::getInstance()->getConnection()`), always prepared statements. Controllers also open raw PDO connections inline for one-off queries — both idioms are normal here.

## Two incompatible auth idioms (important)

Older controllers (Auth, Company, Employee, Attendance, Department, Dashboard, Tracking, AttendanceRequest, Leave) use the real middleware from [api/middleware/auth.php](api/middleware/auth.php):

```php
$auth = authenticate();                          // returns JWT payload: user_id, company_id, role, email
requireRole($auth, [ROLE_COMPANY_ADMIN]);        // 'company_admin'
```

Newer Phase-3 controllers (Profile, Team, AdminEmployee, FileProxy) call `requireAuth(['company', 'super_admin'])` — **a function that does not exist anywhere in the codebase**, and use `'company'` where the role enum value is `'company_admin'`. Those routes fatal-error at runtime. Use the `authenticate()` + `requireRole()` idiom for new code, and expect to fix `requireAuth` call sites when touching those files.

Related casing trap: the file is `api/config/database.php`, but the Phase-3 files `require_once '../config/Database.php'`. This works on Windows dev and breaks on the Linux production host.

## Auth model

Access token (15 min) lives **only in a JS module-scope variable** (`_accessToken` in [public/js/auth.js](public/js/auth.js)) — never localStorage. Refresh token (30 day, 90 day absolute cap) is an httpOnly `SameSite=Strict` cookie, bcrypt-hashed into `users.refresh_token_hash`.

- `localStorage.attendify_session = 'true'` is only a hint that a cookie session may exist; `attendify_user` caches the user object for UI.
- On page load / 401, [public/js/api.js](public/js/api.js) POSTs `/auth/refresh-token`, stores the new token in memory, and replays queued requests (`isRefreshing` + `pendingRequests` guard against a refresh stampede).
- Refresh tokens rotate on every use. The previous hash is kept with a 60-second `grace_period_expires_at` window to absorb concurrent-tab races; presenting a token that matches neither hash is treated as theft and wipes all sessions.
- JWT is a hand-rolled HS256 implementation in [api/helpers/jwt.php](api/helpers/jwt.php) — `JWT_SECRET` is a literal in [api/config/constants.php](api/config/constants.php).

**Employee device lock:** employees must send `device_uuid` (from `getDeviceFingerprint()` in [public/js/utils.js](public/js/utils.js), a random UUID persisted in localStorage) on login. First login binds it to `users.device_uuid`; a mismatch is a hard 403 until an admin calls `POST /employees/:id/reset-device`. Employee login without `device_uuid` is rejected outright.

## Multi-tenancy

Every tenant-scoped table has `company_id`. The pattern in every controller:

```php
$companyId = $auth['role'] === ROLE_SUPER_ADMIN ? ($_GET['company_id'] ?? null) : $auth['company_id'];
```

Super admin may cross tenants (and has `company_id = NULL`); company admin is pinned to their own via `requireCompany()`; employees are pinned to `$auth['user_id']`. Any new query touching tenant data must filter on `company_id` from the token, never from the request body.

## Schema is not fully in schema.sql

[database/schema.sql](database/schema.sql) covers Phase 1–2 (companies, users, attendance, leaves + policies/balances/holidays, departments, live_tracking, subscriptions, company_settings, attendance_requests). ~16 Phase-3 tables (`files`, `file_access_logs`, `employee_addresses`/`_experience`/`_education`/`_family`/`_documents`, expenses, advances, incentives, resignations, `system_audit_logs`, …) plus the `ALTER TABLE users` that adds the encrypted joining-detail columns exist **only** in [scratch_db_phase3.php](scratch_db_phase3.php); [scratch_db.php](scratch_db.php) holds the leave-module migration. Migrations are ad-hoc idempotent PHP scripts run once against production; read these scratch files to know the real table shapes, and fold new DDL back into `schema.sql` when adding tables.

Phase-3 rows are keyed by an app-generated `uuid` (routes match `[a-f0-9\-]+`), not the auto-increment `id`.

## Domain rules worth knowing before editing attendance/leave code

- Check-in is blocked (HTTP 403 with a non-standard `{success:false, blocked:true, type, message}` body) on an approved leave day or approved WFH/outdoor request; a *pending* request only attaches a `warning` to the success response.
- GPS: Haversine is implemented twice — `Attendance::calculateDistance()` (PHP) and `Geo.calculateDistance()` (JS). Office check-in rejects distance > `companies.office_radius` (default 200 m). Only enforced when `attendance_type === 'office'` and coordinates were supplied.
- Late/half-day/full-day thresholds come from the per-company `company_settings` row, falling back to the `LATE_THRESHOLD_MINUTES` / `HALF_DAY_HOURS` / `FULL_DAY_HOURS` constants.
- Leave day counting (`LeaveController::calculateLeaveDays`) excludes Sat/Sun and `company_holidays`, and splits totals per calendar year because balances are per `leave_year`. `LOP` skips the balance check. Applications are capped at 60 days in the future and rejected on overlap with existing leaves or WFH/outdoor requests.
- Employee creation enforces `companies.max_employees` (driven by the `PLANS` constant) and auto-generates `EMP-00N` codes.
- `is_first_login = 1` forces the frontend into a password-change flow via `/auth/change-initial-password`.
- Timezone is globally `Asia/Kolkata`; SQL uses `CURDATE()`/`NOW()`, so server and app timezone must agree.

## Frontend conventions

No framework, no bundler, no modules — scripts are plain `<script src>` tags exporting onto `window`. Each page is a self-contained HTML file with the sidebar/topbar markup duplicated inline (no templating), and page logic in a trailing inline `<script>`. Larger features get a companion file under `public/js/<role>/` (e.g. [public/js/employee/leaves.js](public/js/employee/leaves.js)).

Standard include order at the bottom of every dashboard page:

```html
<script src="../js/api.js"></script>   <!-- window.api: get/post/put/patch/delete + refresh logic -->
<script src="../js/auth.js"></script>  <!-- window.Auth -->
<script src="../js/utils.js"></script> <!-- showToast, formatDate, openModal, statusBadge, getDeviceFingerprint, ... -->
<script>
    Auth.requireRole(['employee']);   // client-side gate only; the API is the real authority
    populateSidebarUser();
</script>
```

Pages live under `public/{admin,company,employee}/` matching the three roles, and `Auth.redirectToDashboard()` maps role → landing page. Styles are four hand-written stylesheets (`main`, `components`, `dashboard`, `auth`) implementing a dark glassmorphism theme; extend the existing utility/component classes rather than adding per-page CSS.

Maps use the **Google Maps JS API** (key inlined in [public/employee/index.html](public/employee/index.html) and [public/company/tracking.html](public/company/tracking.html)) despite README/docs mentioning Leaflet. Charts are hand-rolled SVG in [public/js/charts.js](public/js/charts.js).

PWA: [public/sw.js](public/sw.js) is network-first for HTML, cache-first + stale-while-revalidate for assets, and skips `/api/*` entirely. Bump `CACHE_NAME` when changing the cached asset list, or stale JS will be served.

## Files & sensitive data

Uploads go through `FileService` to `storage/private/<company_id>/<employee_id>/<uuid>.<ext>` — outside the web root — and are served only via `GET /api/files/:uuid`, which enforces company isolation, employee-owns-file access, and logs to `file_access_logs`. Never link to a storage path directly. Sensitive employee fields (bank/PAN/Aadhaar in AdminEmployeeController) are AES-256-CBC encrypted via `EncryptionService` with the key from the `APP_KEY` env var (which falls back to a hardcoded literal).

## Notes

- The `scratch/` directory is gitignored; `scratch_db*.php` at the root are not, and are excluded from neither the repo nor the FTP deploy.
- `HierarchyService::rebuildOrgPath()` is a stub — manager hierarchy was removed, so `users.org_path` is just the employee's own id.
- `Attendance::getHistory()` contains a SQL syntax error (`BETWEEN ? ?`, missing `AND`) and will throw if called.
