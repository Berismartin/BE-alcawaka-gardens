# BE-alcawaka-gardens: architecture rules

Laravel 13 API for the Alcawaka Gardens accommodation and reservation system.
It follows the same DDD layout as BE-autoplug. `app/Domains/Facilities` is the
reference module: copy its shape for every new module.

## Request flow

```
routes/<module>/<file>.php
  -> Http/Controllers/Api/<Module>/<Entity>Controller    (no business logic, no queries)
  -> Http/Requests/<Module>/<Entity>/*Request             (validation only)
  -> Actions/<Module>/<Entity>/*Action                    (request array -> DTO -> service -> Resource)
  -> Domains/<Module>/<Entity>/DTOs/*DTO                  (immutable, readonly)
  -> Domains/<Module>/<Entity>/Services/*Service          (business rules, transactions)
  -> Domains/<Module>/<Entity>/Repositories/*Interface    (services depend on the interface only)
  -> Domains/<Module>/<Entity>/Repositories/*Repository   (the only place that queries Eloquent)
  -> Models/<Module>/<Entity>                              (Eloquent)
  -> Http/Resources/<Module>/<Entity>/*Resource           (what the API exposes)
```

## Rules

- Controllers accept the request, validate with a FormRequest, call one Action and return `$this->respond(...)`.
- Every response uses one envelope: `{"success": true, "data": ..., "message": ...}`. Errors return
  `{"success": false, "message": ..., "errors": {...}}` (see `bootstrap/app.php`).
- Never pass raw request arrays into a Service: convert them to a DTO first.
- Business-rule failures throw an `App\Exceptions\DomainException` subclass: `EntityNotFoundException` (404),
  `EntityAlreadyExistsException` (409) or `EntityValidationException` (422).
- Enums and value objects that describe the business (for example `FacilityAccess` and `UserRole`) live in
  `Domains/<Module>/<Entity>/Enums` and are plain PHP.
- Use constructor injection only. Depend on interfaces, never `new` a repository or service.
- Money is stored as whole Ugandan Shillings (`*_ugx`, unsigned bigint). UGX has no minor unit.
- IDs are UUIDs (`HasUuids`).

## Adding a module

1. Write the migration and model in `Models/<Module>/`.
2. Add the DTOs, RepositoryInterface, Repository and Service in `Domains/<Module>/<Entity>/`.
3. Add the Actions, FormRequests, Resource and Controller.
4. Add `routes/<module>/<file>.php`.
5. In `Providers/DomainServiceProvider`, add one `$bindings` entry and one `ROUTE_FILES` entry.
6. Add a feature test in `tests/Feature/<Module>/`.

## Route areas

- `/api/...` is the public website, readable without signing in.
- `/api/portal/...` is the staff system, behind `auth:sanctum` plus the `staff` middleware (role is not `guest`).
- `/api/auth/...` handles login, `me` and logout with Sanctum bearer tokens.

## Before committing

```
./vendor/bin/pint
php artisan test
```
