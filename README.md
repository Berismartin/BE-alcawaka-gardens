# BE-alcawaka-gardens

The backend API for the Alcawaka Gardens accommodation and reservation system. It covers
bookings, the front desk, payments, housekeeping, facilities and reports.

- PHP 8.4, Laravel 13, Sanctum (bearer tokens)
- DDD layout: read [AGENTS.md](AGENTS.md) before adding code
- The frontend lives in the sibling repo `FE-alcawaka-gardens` (Nuxt)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link     # public uploads at /storage
php artisan serve            # http://localhost:8000
```

Local development uses SQLite (`database/database.sqlite`). For MySQL or Postgres, set the `DB_*` values in `.env`.

The seeder creates one local-only login per role, all with password `password` and POS PIN `2580`: `manager@`, `frontdesk@`, `housekeeping@` and `owner@alcawaka.test`.

## Tests

```bash
php artisan test
./vendor/bin/pint
```

## API

All `/api/portal/...` routes need a staff bearer token plus the ability shown. Abilities come from
`UserRole::permissions()`; managers hold every ability. Routes marked *idempotent* require an `Idempotency-Key` header.

| Method | Path | Who |
|---|---|---|
| GET | `/api/health` | anyone |
| POST | `/api/auth/login`, `/api/auth/accept-invite`, `/api/auth/forgot-password`, `/api/auth/reset-password` | anyone (rate-limited) |
| GET | `/api/auth/me`, POST `/api/auth/logout` | signed in |
| PATCH | `/api/auth/me/preferences` | signed in (theme mode, accent, density) |
| GET | `/api/facilities`, `/api/room-types` | public website |
| GET | `/api/ical/{token}.ics` | OTAs (the token is the secret) |
| GET | `/api/portal/dashboard` | `dashboard.view` |
| GET | `/api/portal/reports`, `/api/portal/reports/export` (CSV) | `reports.view` |
| GET | `/api/portal/reservations`, `/{id}`, `/api/portal/calendar`, `/api/portal/availability` | `reservations.view` |
| POST (*idempotent*), PATCH `{id}` | `/api/portal/reservations` | `reservations.manage` |
| POST | `/api/portal/reservations/{id}/check-in`, `/check-out` (*idempotent*), `/cancel`, `/no-show` | `reservations.manage` |
| POST, DELETE `{chargeId}` | `/api/portal/reservations/{id}/charges` (folio) | `reservations.manage` |
| POST | `/api/portal/reservations/{id}/receipt` (email) | `reservations.manage` |
| POST (*idempotent*) | `/api/portal/reservations/{id}/payments` | `payments.record` |
| GET | `/api/portal/payments`, `/summary`, `/export` (CSV) | `payments.view` |
| PATCH | `/api/portal/payments/{id}/settle` (approve or fail a pending payment) | `payments.record` |
| POST (*idempotent*) | `/api/portal/payments/{id}/refund` | `payments.refund` |
| GET, GET `{id}` | `/api/portal/guests` | `guests.view` |
| POST, PATCH `{id}`, DELETE `{id}` | `/api/portal/guests` | `guests.manage` |
| GET | `/api/portal/room-types`, `/api/portal/rooms`, `/api/portal/seasonal-discounts` | `rooms.view` |
| POST, PATCH, DELETE | `/api/portal/room-types`, `/api/portal/rooms` | `rooms.manage` |
| POST, PUT, DELETE | `/api/portal/seasonal-discounts` | `rates.manage` |
| PATCH | `/api/portal/rooms/{id}/status` | `housekeeping.manage` |
| GET (paginated), GET `{id}` | `/api/portal/facilities` | `facilities.view` |
| POST, PATCH `{id}`, DELETE `{id}` | `/api/portal/facilities` | `facilities.manage` |
| GET | `/api/portal/facility-bookings`, `/summary` | `facilities.view` |
| POST (*idempotent*), POST `{id}/pay` (*idempotent*), POST `{id}/cancel` | `/api/portal/facility-bookings` | `facilities.book` |
| GET, POST, PATCH `{id}`, POST `{id}/resend-invite` | `/api/portal/staff` | `staff.manage` |
| GET, PATCH | `/api/portal/settings` | any staff to read, `settings.manage` to change |
| GET, POST, PUT `{id}`, DELETE `{id}`, POST `/sync`, GET `/availability` | `/api/portal/channels` | `channels.manage` |
| GET, POST `/read`, POST `{id}/read` | `/api/portal/notifications` | any staff (their own) |
| GET | `/api/portal/search?q=` | any staff (results filtered by ability) |
| POST, PATCH `/order`, DELETE `{id}` | `/api/portal/media` | the collection's permission |
| GET | `/api/media/{id}/file` | signed link only (private files) |
| GET | `/api/portal/audit-logs` | `audit.view` |
| GET | `/api/site` (page text, hotel contacts and every published website entry) | public website |
| POST | `/api/enquiries` (event/contact form, 5 a minute) | public website |
| GET, PUT | `/api/portal/content/site` (website page text) | `content.manage` |
| GET, POST, PATCH `{id}`, DELETE `{id}` | `/api/portal/content` (`filter[type]=event_type\|event\|gallery\|testimonial\|faq`) | `content.manage` |
| GET, PATCH `{id}` | `/api/portal/enquiries` | `enquiries.view` / `enquiries.manage` |
| GET `staff`, POST `login` | `/api/pos/...` | a registered POS terminal (`X-Terminal-Key` header); PIN sign-in locks after 5 wrong tries |
| PATCH | `/api/auth/me/pin` | signed-in staff (with their password) |
| GET, POST, DELETE `{id}` | `/api/portal/pos/terminals` | `pos.manage` (managers) |
| DELETE | `/api/portal/pos/staff/{id}/pin` | `pos.manage` |

## Scheduled jobs

Run `php artisan schedule:work` locally (cron `schedule:run` in production):
- every minute: unpaid website holds expire and give their room back
- every 15 minutes: OTA calendar feeds are synced (`channels`)
- daily: old idempotency keys are pruned

Mail (invitations, password resets, receipts) uses `MAIL_MAILER=log` locally; links point at `FRONTEND_URL`.

See [docs/api-conventions.md](docs/api-conventions.md) for pagination, errors, uploads and idempotency, and
[docs/development-plan.md](docs/development-plan.md) for the roadmap.
