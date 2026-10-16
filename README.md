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

The seeder creates a local-only staff login: `manager@alcawaka.test` / `password`.

## Tests

```bash
php artisan test
./vendor/bin/pint
```

## API

| Method | Path | Who |
|---|---|---|
| GET | `/api/health` | anyone |
| POST | `/api/auth/login` | anyone (rate-limited) |
| GET | `/api/auth/me`, POST `/api/auth/logout` | signed in |
| PATCH | `/api/auth/me/preferences` | signed in (theme mode, accent, density) |
| GET | `/api/facilities` | public website |
| GET (paginated), GET `{id}` | `/api/portal/facilities` | `facilities.view` |
| POST, PATCH `{id}`, DELETE `{id}` | `/api/portal/facilities` | `facilities.manage` |
| POST, PATCH `/order`, DELETE `{id}` | `/api/portal/media` | the collection's permission |
| GET | `/api/media/{id}/file` | signed link only (private files) |
| GET | `/api/portal/audit-logs` | `audit.view` |

See [docs/api-conventions.md](docs/api-conventions.md) for pagination, errors, uploads and idempotency, and
[docs/development-plan.md](docs/development-plan.md) for the roadmap.
