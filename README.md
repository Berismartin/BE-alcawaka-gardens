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
| GET `search`, POST (*idempotent*), POST `lookup` | `/api/booking/...` | website guests (rate-limited) |
| GET `{reference}`, `{reference}/receipt` (PDF), POST `{reference}/pay`, `/request`, `/simulate` | `/api/booking/...` | the guest's booking key (`key`); `simulate` only while `PAYMENT_SIMULATOR` is on |
| POST (*idempotent*), GET `{id}`, POST `{id}/pay`, `{id}/simulate` | `/api/facility-bookings/...` | website guests: day passes (priced per person) and event space (one price), paid online; the key proves ownership |
| GET | `/api/portal/dashboard` | `dashboard.view` |
| GET | `/api/portal/reports`, `/api/portal/reports/export` (CSV) | `reports.view` |
| GET | `/api/portal/reservations`, `/{id}`, `/api/portal/calendar`, `/api/portal/availability` | `reservations.view` |
| POST (*idempotent*), PATCH `{id}` | `/api/portal/reservations` | `reservations.manage` |
| POST | `/api/portal/reservations/{id}/check-in`, `/check-out` (*idempotent*), `/cancel`, `/no-show` | `reservations.manage` |
| POST, DELETE `{chargeId}` | `/api/portal/reservations/{id}/charges` (folio) | `reservations.manage` |
| POST | `/api/portal/reservations/{id}/receipt` (email, PDF attached) | `reservations.manage` |
| GET | `/api/portal/reservations/{id}/receipt.pdf` (an invoice while money is due) | `reservations.view` |
| POST (*idempotent*) | `/api/portal/reservations/{id}/payments` | `payments.record` |
| GET | `/api/portal/payments`, `/summary`, `/export` (CSV) | `payments.view` |
| PATCH | `/api/portal/payments/{id}/settle` (approve or fail a pending payment) | `payments.record` |
| POST (*idempotent*) | `/api/portal/payments/{id}/refund` | `payments.refund` |
| GET, GET `{id}` | `/api/portal/guests` | `guests.view` |
| POST, PATCH `{id}`, DELETE `{id}` | `/api/portal/guests` | `guests.manage` |
| GET | `/api/portal/room-types`, `/api/portal/rooms`, `/api/portal/seasonal-discounts` | `rooms.view` |
| POST, PATCH, DELETE | `/api/portal/room-types`, `/api/portal/rooms` | `rooms.manage` |
| POST, PUT, DELETE | `/api/portal/seasonal-discounts` | `rates.manage` |
| PATCH | `/api/portal/rooms/{id}/status` | `housekeeping.manage` (closing a room logs a maintenance issue; reopening it marks it fixed) |
| GET, POST, POST `{id}/resolve` | `/api/portal/maintenance-issues` | `housekeeping.view` / `maintenance.report` / `housekeeping.manage` |
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
| POST | `/api/portal/enquiries/{id}/apply` (a guest's date change or cancellation: done in one click, guest emailed) | `reservations.manage` |
| GET `staff`, POST `login` | `/api/pos/...` | a registered POS terminal (`X-Terminal-Key` header); PIN sign-in locks after 5 wrong tries |
| PATCH | `/api/auth/me/pin` | signed-in staff (with their password) |
| GET, POST, DELETE `{id}` | `/api/portal/pos/terminals` | `pos.manage` (managers) |
| DELETE | `/api/portal/pos/staff/{id}/pin` | `pos.manage` |

## Online payments (Pesapal)

Website guests pay rooms and facility bookings on Pesapal's hosted page (MTN MoMo, Airtel Money, Visa/Mastercard).
Locally `PAYMENT_GATEWAY=simulator` replaces Pesapal with a test panel on the booking page.

| Method | Path | Who |
|---|---|---|
| GET, POST | `/api/payments/pesapal/ipn` | Pesapal (status is always re-checked with Pesapal; replies in Pesapal's IPN format) |
| POST | `/api/payments/return` | the guest's browser back from Pesapal (`order_tracking_id`) |
| POST | `/api/portal/payments/{id}/check` | `payments.record` ("Check with Pesapal") |

Rules: Pesapal is the only source of truth for online payments (staff can't hand-approve them); settlement locks the
payment row, so a repeated IPN never counts twice; refunds go to Pesapal first and complete when Pesapal reports the
payment reversed (one refund per payment, mobile money in full only); money that arrives after a hold lapsed or that
overpays a bill is kept and staff are alerted to refund or rebook.

Going live:
1. `.env`: `PAYMENT_GATEWAY=pesapal`, `PESAPAL_ENV=live`, `PESAPAL_CONSUMER_KEY`, `PESAPAL_CONSUMER_SECRET` (from the Pesapal merchant dashboard), `APP_URL` = the public https API address, `FRONTEND_URL` = the website.
2. `php artisan payments:pesapal-register-ipn` and put the printed id in `PESAPAL_IPN_ID`.
3. Keep the scheduler running: `payments:reconcile` settles payments whose IPN never arrived.

Sandbox: `PESAPAL_ENV=sandbox` with Pesapal's public demo keys (see `.env.example`).

## Scheduled jobs

Run `php artisan schedule:work` locally (cron `schedule:run` in production):
- every minute: online payments are reconciled with Pesapal (before holds expire)
- every minute: unpaid website holds expire and give their room back
- every 15 minutes: OTA calendar feeds are synced (`channels`)
- daily: old idempotency keys are pruned

Mail (invitations, password resets, booking confirmations, receipts) goes through Resend: set `RESEND_API_KEY` and a `MAIL_FROM_ADDRESS` on a domain verified in Resend. Use `MAIL_MAILER=log` to write emails to `storage/logs/laravel.log` instead. Links in emails point at `FRONTEND_URL`. Tests never send real mail.

## Online payments (simulated for now)

Website bookings hold their room for `PAYMENT_HOLD_MINUTES` (10) as *pending payment* and create a pending
MTN, Airtel, card or Pesapal payment. No gateway is connected yet: with `PAYMENT_SIMULATOR=true` (the default
outside production) the booking page shows a test panel that approves or declines it, which confirms the
booking and emails the guest. Set `PAYMENT_SIMULATOR=false` in production; the gateway webhook replaces the
simulator by settling the pending payment the same way.

See [docs/api-conventions.md](docs/api-conventions.md) for pagination, errors, uploads and idempotency, and
[docs/development-plan.md](docs/development-plan.md) for the roadmap.

## Deploying (shared VM with WiFi billing and Campus 360)

The API runs in Docker on the VM; the website, staff portal and POS (`FE-alcawaka-gardens`) are hosted
elsewhere (e.g. Vercel, with `NUXT_PUBLIC_API_BASE=https://api.<your-domain>/api`).

- **Ports:** none published. The WiFi billing Caddy (`beris-caddy`) already owns 80/443 and proxies
  `api.<your-domain>` to `alcawaka-api:8080` over its Docker network. Other ports in use on that VM
  (WiFi billing: 8043, 8843, 29810–29816 for Omada, 1812–1813/udp for RADIUS) are untouched.
- **Containers:** `alcawaka-api` (Nginx + PHP-FPM), `alcawaka-scheduler`, `alcawaka-postgres`, all prefixed so
  nothing collides with `beris-*` or `campus360-*`. Own database and volumes (`alcawaka_postgres-data`,
  `alcawaka_storage` for photos and ID documents).

```sh
# on the VM
git clone <repo> /apps/be-alcawaka && cd /apps/be-alcawaka
cp .env.production.example .env                      # fill in every <...>; PROXY_NETWORK from: docker network ls | grep beris
docker compose build
docker run --rm alcawaka-api:latest php artisan key:generate --show   # paste into APP_KEY
docker compose up -d
docker compose exec alcawaka-api php artisan db:seed --force
docker compose exec -it alcawaka-api php artisan staff:create-manager you@example.com "Your Name"
# Add docker/caddy/alcawaka.caddy (real domain) to /apps/wifibilling/docker/caddy/Caddyfile, then:
docker exec beris-caddy caddy reload --config /etc/caddy/Caddyfile
# Pesapal (once https://api.<your-domain> answers): register the IPN, paste the id into PESAPAL_IPN_ID, restart
docker compose exec alcawaka-api php artisan payments:pesapal-register-ipn
docker compose up -d
```

Updating: `git pull && docker compose up -d --build` (migrations run on start). Backups: `docker exec alcawaka-postgres
pg_dump -U alcawaka alcawaka > alcawaka-$(date +%F).sql`, plus the `alcawaka_storage` volume.
