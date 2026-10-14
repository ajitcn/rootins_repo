# ROOTINS application audit inventory

Audit start: 2026-10-09  
Scope: Admin, Doctor, Patient, Pharmacy, Core API, Shopify, shared layouts/assets, routes, migrations, security, and responsive/print behavior.

This inventory is deliberately limited to confirmed defects or clear inconsistencies. Existing unrelated worktree changes were preserved.

## Confirmed findings

| Module | Severity | Issue | Evidence | Action |
|---|---:|---|---|---|
| Shared authentication | High | Staff logout redirects to `/system/login` as a server-root URL, which drops the `/rootins/public` application prefix. | `app/Controllers/UserAuth.php` returned `redirect()->to('/system/login')`. | Fix with `base_url('system/login')`; verify through `127.0.0.1/rootins/public`. |
| Shared authentication | Medium | `UserAuth::index()` contains an empty already-logged-in branch and still renders the staff login page for an active staff session. | `app/Controllers/UserAuth.php` has `if (session()->has('user')) {}` with no redirect. | Redirect active staff sessions through the existing role-aware redirect service. |
| Shared authorization | Medium | AJAX permission denial returns JSON with HTTP 200 instead of an authorization status. | `app/Filters/Permission.php` calls `setJSON()` without `setStatusCode(403)`. | Return 403 while preserving the existing JSON contract. |
| Shared theme/UI | Low | Root layout comment says Bootstrap 5 while the loaded dependency is Bootstrap 4.6.2. | `app/Views/templates/theme1.php`. | Correct the stale label only. |
| Shared UI | Medium | Legacy module-specific palette and component rules remain in the cascade after the shared monochrome/status layer; static scan still finds gradients and older blue/purple status selectors. | `public/assets/css/style.css` and module CSS. | Audit selectors against rendered pages; remove or neutralize only rules that visibly conflict with the shared layer. |
| Shared UI | Medium | Responsive menu/table rules are duplicated across Doctor, Patient, and Pharmacy and require viewport verification at 375px/320px. | Module CSS contains separate static dropdown positioning and action sizing rules. | Verify live pages; consolidate only reproduced conflicts. |
| Patient | Medium | The monochrome override removed the patient login gradient but left the mobile brand panel's light background and white copy, producing a large blank/inaccessible-looking panel. | Live 375px Patient login rendering showed only the icon while the brand copy was unreadable. | Restore the brand panel as a charcoal surface with readable white copy. |
| Doctor | Medium | Appointment view controls exposed only a List control although both list and grid views and the view-switching handler are implemented; at narrow widths this rendered as an unlabeled icon-only control. | `appointments.php` had only `data-appointment-view="list"`, while `appointments_grid.php` and the JS grid branch exist. | Add the Grid control and keep both labels visible at narrow widths. |
| Shopify | Low | The Shopify configuration card subtitle used muted dark text on the new charcoal section header, reducing contrast. | Live 375px Shopify integration rendering. | Use the shared light header-copy color for muted card-header text. |
| Doctor | Low | The appointment patient avatar retained a blue gradient outside the shared monochrome selectors. | Static gradient scan found the rule in `style.css`. | Neutralize the avatar surface through the shared monochrome layer. |
| Core API / Shopify / shared authorization | Medium | External-boundary failure logs included raw exception messages, which can expose provider, storage, or request details. | Static scan found exception-message concatenation in permission refresh, upload cleanup, webhook, appointment, handoff, and integration handlers. | Replace those log messages with safe event descriptions while preserving client-facing errors and workflow behavior. |
| Core API | — | Signed authentication, timestamp checks, idempotency enforcement, clinic resolution, upload limits, and endpoint documentation are present. | `CoreApiAuthentication`, `V1`, endpoint definitions, focused tests. | Verify; no confirmed defect yet. |
| Shopify | — | Webhook route is explicit, CSRF-exempt only for the webhook path, HMAC verification and duplicate-event handling are present. | `Shopify\Config\Routes`, `Webhook`, webhook event service. | Verify; no confirmed contract defect yet. |
| Pharmacy | — | Legacy `/farmassist` compatibility redirects are explicit and preserve query strings with 307 redirects. | `Pharmacy\Config\Routes`, `LegacyRedirect`. | Preserve and verify; do not remove. |
| Database migrations | — | Migration status is available and currently reports all discovered migrations as migrated. | `php spark migrate:status`. | Compare migration filenames/classes and verify status after fixes. |

## Module audit checklist

### Admin

- Routes, permissions, clinic scope, access-control refresh, CRUD validation, AJAX loaders/errors, tables, cards, modals, More Actions, responsive and print layouts.
- Integration screens: Core API credential lifecycle/documentation and Shopify configuration/order workflows.

### Doctor

- Doctor/clinic scope, patient scope, appointment/queue/clinical/prescription workflows, availability, video consultation, forms, AJAX duplicate-submit protection, responsive tables/cards, and print behavior.

### Patient

- Patient authentication/session scope, profile and password setup, appointment/order/invoice/prescription/document workflows, download/upload boundaries, status rendering, mobile card/list layouts, and redirects.

### Pharmacy

- Pharmacy authorization refresh, inventory/list-grid views, prescription review, order/payment/delivery/billing workflows, legacy redirects, More Actions menus, AJAX state handling, and mobile/table overflow.

### Core API

- Explicit routes, signature authentication, timestamp/rate limits, idempotency/replay behavior, clinic/patient scope, upload validation/storage cleanup, response/documentation parity, and sensitive logging.

### Shopify

- Webhook HMAC/CSRF boundary, duplicate delivery/idempotency, customer/order/prescription mappings, handoff token scope/expiry, API error handling, secrets/logging, and integration UI.

## Verification plan

- PHP lint and focused module tests.
- JavaScript syntax and duplicate-submit/static event-handler scan.
- CSS structural validation plus rendered browser checks at desktop, tablet, 375px, and 320px.
- Route listing and migration status.
- Permission/auth/scope review and targeted tests.
- Old-theme, gradient, table-cell-status, duplicate-dot, and inconsistent-label scans.
- Print and overflow checks.
- `git diff --check`.

