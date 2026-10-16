# Alcawaka Gardens: development and architecture plan

## Context

The proposal (ARS PDF) and the 40-artboard design canvas (https://claude.ai/artifact/LJGBPj3tz5DBETPxRU2tez) define the product:
- a public booking website (A01 to A08)
- a staff system (B01 to B14)
- an owner mobile view (B13)

Two repos are scaffolded in `/Users/macbook/beris mac/beris tech/websites/alcawaka-gardens/`:
- `BE-alcawaka-gardens`: Laravel 13 with Sanctum and the DDD layout. Facilities is the reference module, plus login, roles and the JSON envelope. 7 tests are green.
- `FE-alcawaka-gardens`: Nuxt 4, Nuxt UI 4 and Pinia. Website and `/portal` live in one app, with home, facilities, portal login, dashboard and facilities management.

This plan covers the rest, top-down:
- the shared architecture for both repos
- a UI guideline every screen must meet (states, loading and disabled, tables with pagination and filters, modals, cards, layouts, uploads with progress, dark mode, accent palettes, top nav)
- the phased build, module by module, mapped to the artboards

### Decisions confirmed with the user
- **Navigation:** a collapsible sidebar plus a top bar. The top bar holds the page title, a Ctrl/Cmd+K search, notifications, the theme menu and the user menu.
- **Themes:** Light, Dark or System, plus accent palette presets (Forest by default, Ocean, Clay, Plum, Slate). The choice is saved to the user's account and mirrored in a cookie, so the server render paints it with no flash.
- **Storage:** uploads go through the API with a progress bar, onto Laravel's Storage disks. Local `public` and `private` disks are used now. Moving to S3, R2 or Spaces later is an `.env` change only.

### Conventions taken from Autoplug (sources)
- **UI rules:** `ap_v2/docs/15-ui-ux-rules.md`, 42 rules, adapted below.
- **Admin patterns** in `ap_v2/autoplug-admin/src/`:
  - `useResource.ts`: states are `idle|loading|refreshing|ready|error`, and a sequence counter throws away stale responses.
  - `useAsyncAction.ts`: idle → pending → success | error, with field errors and a block on double submits.
  - `useNotify.ts`, `DataState.vue`, `TablePagination.vue`, `ColumnFilterHeader.vue`.
  - `layouts/DashboardLayout.vue`: `UDashboardGroup`, `UDashboardSidebar` and `UDashboardSearch`.
- **Web patterns** in `ap_v2/autoplug-web/app/`:
  - `components/FileDrop.vue` and `lib/supabase.ts`: XHR upload with progress.
  - `components/site/ThemeToggle.vue`: color mode inside `ClientOnly`.
  - `components/ui/StateBlock.vue`: empty, no-results, error, denied and offline variants.
  - URL query sync in `pages/vehicles/index.vue`.
- **Backend envelope:** `ap_v2/autoplug-backend/app/Http/Responses/ApiResponse.php` returns `{success, data, message, meta}`. It is already mirrored in our `Controller::respond()`.
- **What we do differently on pagination:** Autoplug v2 uses cursor paging. Hotel tables need page numbers and totals, so we use offset pagination with `meta.total`. At hotel data volume that is cheap.

---

## 1. Backend architecture (`BE-alcawaka-gardens`)

The module shape stays exactly as `AGENTS.md` describes. The Facilities files are the template:
- `app/Http/Controllers/Api/Facilities/FacilityController.php`
- `app/Actions/Facilities/Facility/*`
- `app/Domains/Facilities/Facility/{DTOs,Enums,Repositories,Services}`
- `routes/facilities/facilities.php`
- registration in `app/Providers/DomainServiceProvider.php`

### 1.1 Shared pieces to add in Phase 0 (`app/Support/`, `app/Http/`)

| Piece | Path | Purpose |
|---|---|---|
| `ListQuery` DTO | `app/Support/Query/ListQuery.php` | `page`, `perPage` (10/25/50/100, default 25), `search`, `sort` (field or `-field`), `filters[]`, `from`/`to`. Immutable. |
| `ListRequest` base FormRequest | `app/Http/Requests/ListRequest.php` | Validates the params above. Subclasses declare `sortable()` and `filterable()` whitelists. `toListQuery()`. |
| `AppliesListQuery` trait | `app/Support/Query/AppliesListQuery.php` | Used by Eloquent repositories: `->search($cols)`, whitelisted `orderBy`, `where` per filter, date range in Africa/Kampala, then `paginate()`. Replaces per-repo `applyFilters()` copies. |
| `respondPage()` | `app/Http/Controllers/Controller.php` | Returns `{success, data:[...], message, meta:{current_page, per_page, total, last_page}}` from a paginator plus Resource class. |
| Media domain | `app/Domains/Media/...`, `app/Models/Media/Media.php` | Uploads (see 1.3). |
| Permissions | `config/permissions.php`, `app/Http/Middleware/EnsurePermission.php` (`can:` alias), `UserRole::permissions()` | Role → abilities matrix (deck slide 16). Replaces the coarse `staff` check per route. |
| Audit log | `app/Domains/Audit`, `audit_logs` table, `Auditable` listener | Who did what (check-in, refund, rate change). Shown in the reservation activity timeline (B05). |
| Idempotency | `app/Http/Middleware/Idempotent.php` + `idempotency_keys` table | Required on payments, booking creation and check-out (UI rule 22). |
| Settings | `app/Domains/Settings`, `settings` table (key → json) | Hotel profile, check-in/out times, cancellation policy, tax, SMS sender. |
| Error codes | extend `App\Exceptions\DomainException` with `code()` | The frontend can map known failures to specific copy. The render callback in `bootstrap/app.php` is unchanged otherwise. |

**List endpoint contract** (written once in `docs/api-conventions.md`):

```
GET /api/portal/reservations?page=2&per_page=25&q=nakato&sort=-check_in&filter[status]=confirmed&filter[room_type_id]=…&from=2026-11-01&to=2026-11-30
→ { success, data: [...], message: null, meta: { current_page, per_page, total, last_page } }
```

### 1.2 Domain modules (each one: migration, model, DTOs, repo interface and impl, service, actions, requests, resource, controller, route file, feature tests)

| Domain | Aggregates | Key rules |
|---|---|---|
| Settings | Setting | Manager or owner only |
| Users / Staff | User, Invitation | Invite by email, set password, deactivate, role change. `role` never mass-assignable (already done in `app/Models/User.php`) |
| Facilities | Facility (exists), FacilityBooking | Price rule (exists). Bookings either charge the folio or are paid online |
| Rooms | RoomType, Room, RoomStatus enum | Status: available, occupied, needs_cleaning, under_maintenance, out_of_order |
| Rates | Rate (base per type), SeasonalDiscount | Discount date windows may not overlap per room type |
| Guests | Guest | Phone normalised to +256. Optional ID document (private media) |
| Reservations | Reservation, ReservationNight | State machine (below). Availability engine. No double booking |
| Folios | Folio, FolioCharge | One folio per reservation. Balance = charges − payments |
| Payments | Payment, Refund | Methods: momo_mtn, airtel, card, pesapal, flutterwave, cash, pos. Gateway webhooks, idempotent |
| Housekeeping | (room status transitions) + HousekeepingTask | Check-out → needs_cleaning automatically (event listener) |
| Channels | Channel, SyncLog | Phase 7: iCal export/import per room first, OTA APIs later |
| Notifications | in-app notifications + email/SMS jobs | Booking confirmation by email and SMS/WhatsApp |
| Reports | read-only query services | Occupancy, income, balances, revenue by source; queued CSV/PDF export |
| Media | Media | See 1.3 |
| Audit | AuditLog | Append-only |

**Reservation state machine**, in `Domains/Reservations/Reservation/Enums/ReservationStatus.php` with `canTransitionTo()`:

```
pending_payment → confirmed → checked_in → checked_out
pending_payment → expired (hold timeout, scheduled job)
confirmed       → cancelled | no_show
```

**No double booking.**
- `reservation_nights(room_id, night)` carries a unique index, so the database itself rejects overlaps.
- The service writes the nights inside a transaction and turns a unique-violation into `EntityAlreadyExistsException` with the message "Room 103 is no longer free for those dates".

**Payments.** Port the MTN and Airtel MoMo flow from `ap_v1/BE-autoplug`:
- the request-to-pay
- the callback webhook route, with signature or secret check
- a status-poll job
- a reconciliation command

Payment rows are created before the gateway is called, with an idempotency key. A webhook never trusts the amount: it re-queries the gateway.

### 1.3 File uploads (server)

- **Table** `media`: `id uuid`, `disk`, `path`, `visibility (public|private)`, `collection`, `mime`, `size`, `original_name`, `width`, `height`, `mediable_type`/`mediable_id` (morph), `sort_order`, `uploaded_by`, timestamps.
- **Collections** are declared in `config/media.php`, with mime list, max size, visibility and the permission needed:
  - `room_photos`: public, jpg/png/webp, 5 MB
  - `facility_photos`: public, jpg/png/webp, 5 MB
  - `guest_id`: private, jpg/png/pdf, 8 MB
  - `payment_receipt`: private, pdf/jpg/png, 8 MB
  - `hotel_logo`: public, png/svg/webp, 2 MB
- **Endpoints:**
  - `POST /api/portal/media`: multipart `file`, `collection`, optional `mediable_type`/`mediable_id`. Throttled at 30 per minute.
  - `PATCH /api/portal/media/order`: reorder.
  - `DELETE /api/portal/media/{id}`.
- **Server checks:** the real mime is checked with `$file->getMimeType()`, never the client's. Files are stored under `collection/Y/m/uuid.ext`.
- **Private files** are never public URLs. `MediaResource` returns `url` for public media. For private media it returns a short-lived signed route (`URL::temporarySignedRoute('media.show', 5 min)`), served by a controller that checks the permission.
- **Images** are resized in the browser before upload (≤2000 px, WebP or JPEG at quality 0.85). There is no server image library.
  - Shortcut: no server-side thumbnails. Add `intervention/image` and a queued job if galleries feel slow.
- Moving to S3 or R2: set `FILESYSTEM_DISK`, `MEDIA_PUBLIC_DISK=s3` and `MEDIA_PRIVATE_DISK=s3-private` in `.env`. `temporaryUrl()` then becomes a presigned URL with no code change.

### 1.4 Cross-cutting backend rules
- **Times:** stored in UTC and shown in `Africa/Kampala`. Hotel "nights" are dates, not timestamps.
- **Money:** `*_ugx` unsigned bigint, as already used in the facilities migration.
- **Queues:** database driver now, Redis or Horizon later. Used by email, SMS, exports, payment polling and hold expiry.
- **Scheduler** (`routes/console.php`): expire pending holds every minute, reconcile payments every 5 minutes, send the nightly occupancy snapshot.
- **Tests:** every endpoint gets feature tests for happy path, validation, permission denied, pagination, filter and sort. Domain enums and state machines get plain unit tests.
- **API docs:** Scribe, as in BE-autoplug (`composer docs`).

---

## 2. Frontend architecture (`FE-alcawaka-gardens`)

### 2.1 Structure (Nuxt 4 `app/`)

```
app/
  layouts/        default.vue (website) · portal.vue (staff) · auth.vue (login, reset, invite)
  pages/          index, rooms/, rooms/[slug], facilities, book/ (search → details → pay → done), account/ (guest)
                  portal/ index, calendar, reservations/, reservations/[id], folios/[id], housekeeping,
                  payments, facilities, rooms, rates, guests, reports, channels, staff, settings
  components/
    app/          AppPageHeader, AppStatCard, AppStatusBadge, AppMoney, AppEmptyState, AppStateBlock,
                  AppSkeletonTable, AppConfirmModal, AppFormModal, AppFileDrop, AppImageGallery,
                  AppFilterBar, AppDataTable, AppThemeMenu, AppUserMenu, AppNotifications
    website/      SiteHeader, SiteFooter, BookingBar, RoomCard, FacilityCard (exists, move here)
    portal/       domain components (ReservationTimeline, TapeChart, FolioTable, HousekeepingColumn…)
  composables/    useApi (exists), useDataTable, useAsyncAction, useNotify, useConfirm, useUpload,
                  useCan, useTheme, usePortalSearch
  stores/         auth (exists), preferences
  utils/          money.ts (exists: ugx), dates.ts (Kampala formatting), statuses.ts (status → badge tone/label/icon)
  types/          api.ts (exists; extend with Paginated<T>, Media, Reservation…)
```

### 2.2 Core composables (build once in Phase 0, used by every screen)

- **`useDataTable<T>(endpoint, { defaults, filters })`** combines the admin repo's `useResource.ts` state machine with web's URL sync.
  - Reads and writes `page`, `per_page`, `q`, `sort` and `filter[*]` to the route query (`router.replace`), so back/forward and shared links keep the view.
  - Debounces `q` by 300 ms (`refDebounced`).
  - Exposes `rows`, `meta`, `state: 'loading'|'refreshing'|'ready'|'empty'|'no-results'|'error'`, `activeFilters` (removable chips), `clearFilters()`, `refresh()`.
  - Drops out-of-date responses with a sequence counter.
  - A refresh keeps the current rows dimmed rather than flashing a skeleton again.
- **`useAsyncAction(fn)`** follows `autoplug-admin/src/composables/useAsyncAction.ts`: `pending`, `success` (held 900 ms), `error`, `fieldErrors` mapped from `e.data.errors`, and a block on double submits.
- **`useNotify()`** wraps `useToast()` with `success(msg)` and `error(e)`, using the existing `apiErrorMessage()` from `composables/useApi.ts`.
- **`useConfirm()`** is built on Nuxt UI `useOverlay()` plus `AppConfirmModal`. It returns a promise. Its options are `{ title, description, confirmLabel, tone: 'danger' }` and it runs an async `onConfirm` while the modal shows loading.
- **`useUpload()`** sends the file by XHR to `${apiBase}/portal/media` with the bearer token. It reports `progress` (0 to 100), `status: 'idle'|'resizing'|'uploading'|'done'|'error'`, `cancel()` (AbortController) and `retry()`. Resizing happens on the client first. The pattern follows `autoplug-web/app/lib/supabase.ts`.
- **`useCan(ability)`** reads `auth.user.permissions`, which `/auth/me` must return. It hides or disables controls; the backend still enforces.
- **`useTheme()`** handles mode (`useColorMode()`) and accent (see 2.4).

### 2.3 Layouts and navigation
- **Portal** (`layouts/portal.vue` is rewritten onto Nuxt UI dashboard components, as in `autoplug-admin/src/layouts/DashboardLayout.vue`):
  - `UDashboardGroup` (storage `cookie`, so the server render gets the right sidebar width) contains:
    - `UDashboardSidebar`: collapsible and resizable, a drawer on phones. A `UNavigationMenu` holds the grouped nav below, each item filtered by `useCan`.
      - **Front desk:** Dashboard, Calendar, Reservations, Guests, Payments
      - **Operations:** Housekeeping, Facilities
      - **Management:** Rooms, Rates, Reports, Channels, Staff, Settings
    - `UDashboardPanel`, with `UDashboardNavbar` as the top nav. From left to right it has:
      - sidebar collapse toggle
      - page title and breadcrumbs
      - `UDashboardSearchButton` (Ctrl/Cmd+K → `UDashboardSearch` with pages, plus live groups for guests, reservations and rooms from `GET /api/portal/search?q=`, debounced 250 ms)
      - "New booking" primary button
      - notifications bell (opens a `USlideover`)
      - `AppThemeMenu`
      - `AppUserMenu`
- **Website** (`layouts/default.vue`): a sticky header with logo, Rooms, Facilities, Offers, Contact, My booking and a "Book now" primary button. It becomes a `USlideover` menu below `md`. The footer carries contact details, payment methods and the theme toggle.
- **Auth** (`layouts/auth.vue`): the split brand panel and form from the existing `pages/portal/login.vue`.

### 2.4 Theming: dark mode and accent palettes
- **Mode:** `useColorMode()` from Nuxt UI, with `preference` set to `system` by default. The toggle cycles Light, Dark and System; follow web's `ThemeToggle.vue` pattern and wrap it in `ClientOnly` with a placeholder of the same size.
- **Palettes:** in `assets/css/main.css`, define `--color-{forest,ocean,clay,plum,slate}-{50…950}` under `@theme static`.
  - Forest is the scale that already exists.
  - The others are oklch scales with the same lightness steps, so contrast holds in both modes.
- **Switching:** set `appConfig.ui.colors.primary = accent` at runtime. Nuxt UI reads app config reactively.
- **Persistence:**
  - The `ag_accent` cookie is read in a plugin before render, so there is no flash.
  - `users.preferences` (json: `{ mode, accent, sidebar_collapsed, table_density }`) is synced through `PATCH /api/auth/me/preferences`.
- **Rule:** components use semantic Nuxt UI tokens only:
  - colour classes: `bg-default`, `bg-elevated`, `text-muted`, `text-highlighted`, `border-default`
  - component props: `color="primary"`, `color="neutral"`
  - Hard-coded `forest-*` classes in `layouts/portal.vue`, `layouts/default.vue` and `pages/portal/login.vue` are replaced in Phase 0, so the palette and dark mode switch everything.
  - Status colours stay fixed: success, warning, error and info. They do not follow the accent.
- **`AppThemeMenu`:** a dropdown with a mode segmented control (Light, Dark, System, with icons) and a grid of accent swatches. Each swatch is a real button with `aria-label`, a check icon and a ring on the active one, so active is never shown by colour alone.

---

## 3. UI guidelines (to be saved as `FE-alcawaka-gardens/docs/ui-guidelines.md`)

Adapted from `ap_v2/docs/15-ui-ux-rules.md`. A screen is done only when every state below is designed and built.

### 3.1 States every interactive element handles
default · hover · focus-visible · active/pressed (`active:scale-[0.97]`) · disabled · loading · success · error · empty · permission-denied · offline.

### 3.2 Loading
| Situation | Use |
|---|---|
| Tables, card grids, dashboards, detail pages | Skeleton that matches the final layout (`USkeleton`, `AppSkeletonTable` with the real column widths). No layout shift. |
| Buttons and small actions | `UButton :loading` with an action label (`Saving…`, `Checking in…`). Width stays stable. |
| Uploads, exports, payment approval | Progress bar or step list (A05 pattern) |
| Background refresh | Keep content, dim it (`opacity-60`), show a small spinner in the header. Never blank the table. |
| Under 300 ms | Show nothing. Delay skeletons by 150 ms to avoid flicker. |

### 3.3 Disabled
- Disabled controls must explain why. Use a `UTooltip` such as "Only managers can change rates" or "Room still needs cleaning".
- If the user can never do the action, hide it. If it is temporarily unavailable, disable it with the reason.
- A submit button stays enabled while the form is invalid. Clicking it shows the field errors.

### 3.4 Active and selected
- **Nav items:** background, icon colour and weight together, plus `aria-current="page"`.
- **Tabs and segmented controls:** filled background and bold text.
- **Selected table rows:** checkbox plus row tint.
- **Selected cards and radio cards:** border, tint and a check icon. This is the pattern already in the design (`.rd` cards in A04/B04).
- Never rely on colour alone.

### 3.5 Tables (`AppDataTable` = `UTable` + `useDataTable`)
- **Pagination:** server-side with `UPagination`. "Showing 26–50 of 211" and a per-page select (10, 25, 50, 100).
- **Sorting:** on whitelisted columns only, shown with a header icon.
- **Search:** a box with a 300 ms debounce, a clear button and a spinner while typing.
- **Filters:** a `AppFilterBar` row of selects, date range and status. Active filters show as removable `UBadge` chips, with "Clear filters".
- **URL:** every table state is synced to the URL.
- **Rows:**
  - row hover and focus
  - a row action menu (`UDropdownMenu`, "…")
  - a row click opens the detail slideover or page
  - bulk select only where a bulk action exists (housekeeping "Mark clean", payments export)
- **Settings and layout:** a column-visibility menu and a density toggle (saved in preferences). Sticky header. Tables scroll horizontally inside their card on tablet.
- **Phones:** below `sm` each row stacks into a card (label: value), the admin repo's `tableEnhancements.ts` idea.
- **Four distinct empty states:** none of these may read just "No data".
  - No data yet: "No reservations yet. [New booking]"
  - Search found nothing: "No reservations match 'nakato'. [Clear search]"
  - Filters excluded everything: "No reservations match these filters. [Clear filters]"
  - Load failed: "Couldn't load reservations. [Retry]"

### 3.6 Choosing modal vs slideover vs page
| Use | When | Examples |
|---|---|---|
| **Modal** (`UModal` via `AppFormModal` or `useConfirm`) | Short focused task, at most about 6 fields, or a confirmation | Add charge, record cash payment, add facility, invite staff, delete confirmations, cancel reservation |
| **Slideover** (`USlideover`, right side) | Look at or quick-edit a record while keeping your place in a list | Guest detail, room status, payment detail, notification panel, filters on phone |
| **Full page** | Multi-section or multi-step work, or anything worth a URL | Reservation detail (B05), folio (B06), new booking (B04), reports, settings |

**Modal behaviour** (rule 2):
- **Submit:** button loading, inputs read-only, modal stays open.
- **Success:** a check animation (~600 ms), then close, toast, and refresh the affected list.
- **Error:** stay open, keep the input, show the error inline at the top, button re-enabled with "Try again".
- **Unsaved changes:** Esc or a backdrop click asks "Discard changes?".
- **Focus:** trapped inside and returned to the trigger on close (Nuxt UI does this).

**Destructive actions:** name the consequence, use a red confirm button labelled with the verb ("Cancel reservation", not "OK"), show the loading state, then success. Where other records are affected, show them, as in the admin repo's `useDeletePreview` idea.

### 3.7 Forms
- `UForm` with a zod schema for instant checks. The backend stays authoritative, and its `errors` map onto `UFormField :error`.
- Labels are always visible. Placeholders only show a format ("07:00 – 19:00", "+256 7XX XXX XXX").
- Never clear input on failure.
- Warn before leaving a page with unsaved changes (`onBeforeRouteLeave`).
- Sensible defaults: check-in today, 1 night, 2 adults.
- Long forms are split into `UCard` sections with headings. The primary action sits bottom-right, sticky on long pages.

### 3.8 Cards and page layout
- **Every portal page:** `AppPageHeader` (title, one-line description, primary action on the right, secondary actions in a menu), then content.
- **Dashboard and report pages:** a row of `AppStatCard` KPIs (auto-fit grid, minimum 200 px), then a two-column `flex-wrap` area. The main column grows; the side column is at least 300 px and stacks on phones.
- **Detail pages** (B05/B06): a header card with name, reference, status badge and actions, then a main column (stay, guest) and a side column (payment, activity).
- **Cards:** `UCard` with `#header` for title plus action. Never nest cards more than one level.
- **Spacing:** 4/8 px rhythm, 24 px gaps between sections, 16 px inside cards. Content width is capped on the website (`UContainer`). Portal pages are fluid.

### 3.9 Status badges (`utils/statuses.ts` → `AppStatusBadge`, always text and sometimes an icon)
| Tone | Statuses |
|---|---|
| success | confirmed, paid, clean, open, active, synced |
| info | checked_in / in house, occupied |
| warning | pending_payment, needs_cleaning, partially_paid, booked |
| error | failed, overdue, cancelled, out_of_order |
| neutral | checked_out, expired, inactive, under_maintenance, draft |

### 3.10 File uploads (`AppFileDrop` + `useUpload`)
- **Drop zone:** drag and drop plus a "Choose file" button (keyboard reachable). It shows accepted types and the size limit.
- **Checked before upload:** type and size are validated on the client with a specific message ("Photos must be JPG, PNG or WebP under 5 MB").
- **One tile per file:** thumbnail, name, `Resizing…` then `Uploading 42%` (a `UProgress` bar), Cancel, then `✓ Uploaded` or `✕ Failed [Retry]`. Failed uploads stay visible.
- **Galleries** (rooms, facilities): reorder by drag, set cover, delete with confirmation.
- **Private documents** (guest ID): opened only through the signed link. Never shown as a thumbnail in lists.

### 3.11 Feedback, errors and toasts
- **Toasts** are for lightweight outcomes only ("✓ Room 103 marked clean"). Anything the user must act on stays inline.
- **Error copy** is human and specific. It never shows raw server, SQL or stack text.
- **Offline:** a top banner reads "You're offline. Changes will fail until you reconnect." (`useOnline`).

### 3.12 Accessibility and responsive
- **Interactive elements:** real `<button>`, `<a>` and labelled inputs. Icon buttons get `aria-label` and a tooltip.
- **Contrast:** at least 4.5:1, checked in both light and dark mode and for every accent.
- **Hit targets:** at least 44 px on touch.
- **Keyboard:** Ctrl/Cmd+K, Esc and Tab order are tested.
- **Breakpoints** run from 375 to 1440 px:
  - the website works on phones first
  - the portal works on tablet, and housekeeping on phone
  - the owner view (B13) works on phone
- **Formats:**
  - dates `Fri 13 Nov 2026`, times `24h`, timezone Kampala
  - money `UGX 360,000`

---

## 4. Phased roadmap

Each phase ends deployable, with tests green. The artboard IDs refer to the design canvas.

### Phase 0: Foundations (both repos)
- **BE:**
  - `ListQuery`, `ListRequest`, `AppliesListQuery` and `respondPage()`. Convert the Facilities list to use them.
  - Media domain plus `config/media.php` and the signed private route.
  - Permissions config plus `can:` middleware. `/auth/me` returns `permissions` and `preferences`.
  - Preferences endpoint, idempotency middleware, audit log and settings module.
  - Scribe docs and `docs/api-conventions.md`.
- **FE:**
  - All composables in 2.2 and all `components/app/*`.
  - Portal layout rewritten on the UDashboard components (sidebar, top nav, Ctrl/Cmd+K, notifications slideover), plus the website header/footer and the auth layout.
  - Theming (mode plus 5 accents, cookie and account sync). Replace the hard-coded `forest-*` classes.
  - `docs/ui-guidelines.md` (section 3).
- **Both:** GitHub Actions CI. BE runs `pint --test` and `php artisan test`. FE runs `pnpm lint`, `pnpm typecheck` and `pnpm build`.
- **Exit:** Facilities becomes the full reference screen. It has a paginated, filterable table, an add/edit modal, delete confirm, a photo gallery with upload progress, every empty state, dark mode and every accent.

### Phase 1: Property setup (manager)
- Settings: hotel profile, logo upload, policies and times.
- Room types, with photo gallery and amenities.
- Rooms: number, floor, type and status.
- Rates and seasonal discounts (B10).
- Staff and roles (B12): invite by email, accept invite with set-password page, deactivate, password reset.
- Facilities (B14), finished.

### Phase 2: Reservations core (front desk)
- Guests: list, slideover detail, ID upload.
- Availability service plus `reservation_nights` uniqueness.
- Reservations list (paginated, filtered by status, date range, room type and source).
- New walk-in booking (B04).
- Reservation detail with one-click check-in and activity timeline (B05).
- Calendar tape chart (B03): 7, 14 or 30 days, with click-to-open and a drag-to-create hold later.
- Front-desk dashboard (B02).

### Phase 3: Folio and payments
- Folio charges, a "record cash or POS payment" modal and check-out (B06).
- PDF receipts (dompdf, as in BE-autoplug).
- Payments ledger (B08) with filters by method and status.
- MTN and Airtel MoMo request-to-pay, webhooks, status polling, reconciliation, refunds (manager only).
- Pesapal and Flutterwave for cards.
- Every mutation is idempotent.

### Phase 4: Public website and online booking
- Home (A01), search results (A02), room detail (A03), checkout (A04).
- Payment pending with live polling and the 10-minute hold (A05).
- Confirmed (A06).
- Guest "My bookings" by OTP or magic link (A07), with change and cancel requests that land in the front-desk queue.
- Facilities and facility reservations (A08).
- Email confirmations (Resend) and SMS/WhatsApp (Pahappa comms SDK, as used in BE-autoplug).
- SEO meta and sitemap.
- **Milestone:** this phase completes the "Beta delivery" in the proposal's 50/30/20 plan.

### Phase 5: Operations
- Housekeeping board, phone-first, with bulk "Mark clean" (B07).
- Maintenance issues.
- Facility bookings calendar.
- In-app notifications with a bell count.
- Optional realtime: Laravel Reverb and Echo for calendar and housekeeping updates. Polling at 30 s is enough until then.

### Phase 6: Reports and owner
- Reports (B11): daily, monthly and yearly. Occupancy, income, balances and revenue by source.
- Queued CSV/PDF export with progress and a download link.
- Owner mobile dashboard (B13).

### Phase 7: Channel manager (B09)
- An iCal export and import feed per room, which most OTAs accept, with a sync log and "Sync now".
- Direct OTA APIs only once the client names the channels.

### Phase 8: Hardening and launch
- Load and performance checks (N+1, indexes) and a security review (permissions matrix test, upload checks, rate limits).
- Backups: database and private disk.
- Production `.env` with S3/R2 if chosen.
- Staff training on seeded demo data, then payment gateway go-live.
- **Milestone:** this is the proposal's "Final launch".

---

## 5. Critical files

- **Existing, to extend:**
  - `BE-alcawaka-gardens/app/Http/Controllers/Controller.php` (`respondPage`)
  - `app/Providers/DomainServiceProvider.php` (bindings and route list for every module)
  - `bootstrap/app.php` (middleware aliases)
  - `app/Domains/Users/User/Enums/UserRole.php` (permissions)
  - `app/Models/User.php` (preferences cast)
- **Existing, to refactor:**
  - `FE-alcawaka-gardens/app/layouts/portal.vue`, `layouts/default.vue`, `pages/portal/login.vue` (semantic tokens, UDashboard)
  - `app/assets/css/main.css` (palettes)
  - `app/app.config.ts`
  - `app/composables/useApi.ts` (keep; `useUpload` reuses its token cookie `ag_token`)
  - `app/types/api.ts` (`Paginated<T>`)
- **Templates to copy per module:**
  - BE: `app/Domains/Facilities/Facility/*`, `tests/Feature/Facilities/FacilityTest.php`
  - FE: `app/pages/portal/facilities/index.vue`, once it is upgraded in Phase 0

## 6. Verification

- **Backend:** each module ships feature tests covering:
  - happy path, validation, 401/403, pagination meta, each filter and sort, and the state machine
  - double booking rejection, and a webhook replay that does not double-pay

  Run `php artisan test` and `./vendor/bin/pint --test`, both in CI.
- **Frontend:**
  - `pnpm lint`, `pnpm typecheck` and `pnpm build` in CI.
  - Vitest for `useDataTable` (URL sync, stale-response drop), `useUpload` (progress and abort) and `statuses.ts`.
  - Playwright smoke flows against a seeded backend: staff login, create a walk-in booking, check-in, add charge, check-out, upload a room photo; and the guest search → book → payment pending → confirmed path (with the gateway mocked).
- **Manual checks per phase:**
  - every screen at 375, 768 and 1440 px
  - light and dark mode with each accent
  - keyboard only
  - throttled "Slow 3G" network, to see the loading and disabled states
- **Running both servers:**
  - `php artisan serve` in BE and `pnpm dev` in FE (`NUXT_PUBLIC_API_BASE=http://localhost:8000/api`).
  - The in-app browser preview must be launched from a session opened in `FE-alcawaka-gardens`, which carries its own `.claude/launch.json`. This session's preview config belongs to Worklink.
