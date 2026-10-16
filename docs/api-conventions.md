# API conventions

These apply to every endpoint. The Facilities and Media modules are the working examples.

## Envelope

The success and error envelopes share one shape:

```json
{ "success": true,  "data": {...} | [...], "message": "Facility created." | null }
{ "success": false, "message": "A price is required for paid or reservation facilities.", "errors": { "price_ugx": ["..."] } }
```

| Status | When |
|---|---|
| 401 | Not signed in |
| 403 | Signed in, but missing the permission |
| 404 | Record not found |
| 409 | Conflict: duplicate, or a request still in progress |
| 422 | Validation or business rule failed (`errors` is keyed by field) |

Raw exception text is never returned.

## Paginated lists

```
GET /api/portal/facilities?page=2&per_page=25&q=pool&sort=-price_ugx&filter[access]=paid&filter[is_active]=true&from=2026-11-01&to=2026-11-30
```

| Param | Rules |
|---|---|
| `page` | Integer, 1 or more |
| `per_page` | 10, 25 (the default), 50 or 100 |
| `q` | Searches the columns the repository chooses |
| `sort` | `field` (ascending) or `-field` (descending). Only fields the endpoint's `ListRequest::sortable()` allows |
| `filter[key]` | Only keys in `ListRequest::filters()`. Any other key returns 422. `true`/`false` become booleans |
| `from`, `to` | `Y-m-d` hotel-local days (Africa/Kampala), whole day inclusive |

The response adds `meta`:

```json
{ "success": true, "data": [...], "message": null, "meta": { "current_page": 2, "per_page": 25, "total": 211, "last_page": 9 } }
```

To build one:
1. Subclass `App\Http\Requests\ListRequest`.
2. Have the repository use the `App\Support\Query\AppliesListQuery` trait and call `paginateList()`.
3. Return the result from the controller with `respondPage($paginator, Resource::class)`.

## Permissions

- Abilities follow the pattern `<area>.<view|manage>`, and `manage` implies `view`. The matrix lives in `App\Domains\Users\User\Enums\UserRole::permissions()`.
- Protect routes with `->middleware('permission:facilities.manage')`.
- `GET /api/auth/me` returns `permissions` so the frontend can hide or disable controls. The backend still enforces every check.

## File uploads

```
POST /api/portal/media   (multipart)   collection, file, mediable_type, mediable_id
PATCH /api/portal/media/order          { "ids": [uuid, ...] }      gallery order
DELETE /api/portal/media/{id}
```

- Each collection is defined in `config/media.php`. The definition sets allowed types, max size, `public` or `private` visibility, the permission needed, and which records the file may belong to.
- The type is checked from the file content.
- Public files return a normal `url`. Private files return a signed `url` that lasts 5 minutes; never store it.
- Owner models use `App\Models\Concerns\HasMedia`. When the owner is deleted, its files are removed from disk.
- Owner aliases (for example `facility`) are registered in `DomainServiceProvider::boot()` (`Relation::morphMap`).
- Local development needs `php artisan storage:link` once.

## Idempotency (money and bookings)

- Mutations that must never run twice use `->middleware('idempotent:required')`. This covers payments, booking creation, check-out and refunds.
- The client sends `Idempotency-Key: <uuid>` per user action, and reuses that key when retrying.
- A repeat returns the first response, with the `Idempotent-Replayed: true` header.
- Reusing a key with a different body returns 422. A repeat while the first request is still running returns 409.

## Audit log

- Services record important changes with `AuditLogger::record('facility.updated', $model, AuditLogger::diff($before, $data))`.
- Read them through `GET /api/portal/audit-logs?filter[subject_type]=facility&filter[subject_id]=...`. This needs `audit.view`, which managers and owners have.

## Formats

- **Money:** `*_ugx` holds whole shillings as an integer.
- **IDs:** UUID strings, except `users.id`.
- **Timestamps:** ISO 8601 UTC. Hotel nights are `Y-m-d` dates.
