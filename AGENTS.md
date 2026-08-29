# ROOTINS Development Conventions

## Platform and Architecture

- This application uses CodeIgniter 4.7.4 (Composer constraint: `^4.7`) and PHP 8.2 or newer.
- It is a modular CI4 application. Application-wide code is under `app/`; feature modules are under `app/Modules/`.
- The Admin module currently contains the primary implemented application functionality. Other modules may contain scaffolding or partial implementations. Always inspect the current codebase before assuming that a module, controller, model, service, integration, or workflow is implemented, incomplete, unused, or safe to replace.
- Use explicit routes. Improved auto-routing is disabled in `app/Config/Routing.php`.
- Module route files are loaded dynamically from `app/Modules/*/Config/Routes.php` by `app/Config/Routes.php`.

## Business Domain

- ROOTINS is an online healthcare platform focused on doctor consultations, prescriptions, and prescription-based medicine delivery.
- The platform includes Super Admin functionality for managing the application's operational data, users, roles, permissions, doctors, clinics, patients, consultations, prescriptions, medicines, and related workflows.
- Doctor consultation is a core business workflow. Changes affecting consultation, doctor availability, scheduling, consultation fees, or related doctor workflows must preserve existing business rules.
- Prescription management is a core business workflow. Prescription data may be used by downstream medicine-order and delivery workflows, so changes to prescription structure or processing must be reviewed across all consuming components before implementation.
- Medicine delivery and order workflows may interact with external commerce systems. Do not assume that database records and external commerce records are independent.
- Some parts of the application are integrated with Shopify through webhooks and related API integrations.
- Business-critical workflows must be changed conservatively. Prefer small, isolated, backward-compatible changes unless a larger refactor is explicitly requested.

## Shopify and External Integrations

- Shopify is an external commerce integration used by parts of the medicine and order workflow.
- Treat Shopify and other external services as integration boundaries.
- Before modifying an integration, inspect the complete flow: route → controller/webhook handler → request validation/authentication → payload mapping → database changes → business workflow → external response.
- Shopify webhook endpoints must use explicit routes.
- Validate incoming webhook requests according to the existing integration implementation before processing business logic.
- Webhook processing must tolerate duplicate webhook delivery where applicable. Do not blindly create duplicate database records from repeated events.
- Do not assume webhook events will always arrive in the expected order.
- Preserve existing Shopify payload mappings and database contracts unless a change is explicitly required.
- Do not expose Shopify credentials, access tokens, webhook secrets, API keys, or other integration secrets in source code, JavaScript, views, logs, error responses, or commits.
- Never hard-code external integration credentials. Use environment configuration.
- Log integration failures with enough diagnostic information to troubleshoot the problem, but never log credentials, authorization headers, tokens, passwords, or other sensitive values.
- External API failures must not leave partially completed database workflows when transactional consistency is required.
- When an external operation and a local database operation cannot share a transaction, use appropriate failure handling, retry, idempotency, or reconciliation according to the existing workflow.
- Do not change webhook response behavior, payload structure, event handling, or synchronization logic without checking all existing consumers.

## Change Approval and Safety Rules

- Before making destructive, potentially breaking, security-sensitive, production-impacting, or externally visible changes, explain the impact and wait for explicit approval.
- Do not make production deployment, DNS, server, database, queue, cron, webhook, Shopify, R2, or external API configuration changes unless explicitly requested.
- Do not drop, truncate, or recreate existing production tables.
- Do not delete existing functionality merely because it appears unused without confirming that it is safe to remove.
- Do not modify unrelated files or modules while implementing a requested feature.
- Do not overwrite unrelated uncommitted changes.
- Before modifying an existing business workflow, inspect its complete dependency flow rather than changing only the immediately visible file.
- Prefer the smallest safe implementation that satisfies the requested requirement.
- Do not change existing API, AJAX, webhook, JSON, database, session, permission, or storage contracts unless the requirement explicitly requires the change.
- When a requested change affects multiple layers, identify the affected layers before implementation.
- After implementation, review the changed files for unintended side effects and run the most relevant available tests, validation, migration checks, or static checks.
- Never make assumptions about business rules when the existing codebase contains an established implementation. Inspect the existing implementation first.
- If requirements conflict with existing project conventions, identify the conflict and ask for clarification rather than silently changing the architecture.
- Before modifying files, inspect the current Git status and preserve all unrelated uncommitted changes. Never reset, checkout, stash, or discard user changes unless explicitly requested.

## Namespaces and PSR-4

- `App\\` maps to `app/` and `Config\\` maps to `app/Config/` through Composer.
- CI4 application autoload configuration additionally maps:
  - `Admin\\` to `app/Modules/Admin`
  - `Doctor\\` to `app/Modules/Doctor`
  - `Patient\\` to `app/Modules/Patient`
  - `Farmassist\\` to `app/Modules/Farmassist`
- Place module classes beneath the appropriate module namespace and directory. For example, an admin model belongs in `app/Modules/Admin/Models/...` with an `Admin\\Models\\...` namespace.
- View-prefix constants are defined in `app/Config/Constants.php`: `ADMIN_VIEW`, `DOCTOR_VIEW`, `PATIENT_VIEW`, and `FARMASSIST_VIEW`.

## Module Structure

- Module directories use `Config/`, `Controllers/`, `Models/`, `Services/`, and `Views/`.
- Admin provides the reference implementation:
  - `Controllers/Clinic/Clinic.php` for a standard CRUD screen.
  - `Controllers/Doctor/Doctor.php` for multi-table writes and transactions.
  - `Controllers/Doctor/DoctorSchedule.php`, `DoctorLeave.php`, `DoctorConsultationFee.php`, and `DoctorCommission.php` for related doctor-domain CRUD.
  - `Views/pages/` for pages, `Views/pages/.../modals/` for modal partials, and `Views/parts/` for navigation partials.
  - `public/assets/js/admin/...` for feature-specific client JavaScript.

## Controller Conventions

- Application controllers extend `App\\Controllers\\BaseController`.
- Authenticated admin controllers normally extend `App\\Controllers\\BaseAdminController`.
- `BaseAdminController` checks `session('isLoggedIn')` and makes the session user, roles, and permissions available as protected properties.
- Controllers instantiate their models in constructors using typed protected properties.
- Index actions prepare arrays for views; use `return view(...)` with the existing module view prefix convention.
- AJAX create/update actions commonly check `$this->request->isAJAX()`, validate server-side, then return JSON shaped as `status`, `msg`, and optionally `errors` or `data`.
- Use `try`/`catch (\\Throwable $e)` with `log_message('error', ...)` around operations that can fail. Use database transactions for writes spanning multiple tables.
- Existing delete actions are mixed: some return redirects and flashdata, while create/update actions are generally AJAX JSON. Preserve the behavior of the feature being changed unless the complete route/view/client flow is deliberately updated together.

## Model Conventions

- Models extend `CodeIgniter\\Model`, use lowercase plural snake_case table names, use `id` as the primary key, and return arrays (`$returnType = 'array'`).
- Declare `$allowedFields` for every writable model.
- Most domain models use CI4 timestamps with `created_at` and `updated_at`.
- Soft-delete behavior varies by model. Do not assume a `deleted_at` column means `$useSoftDeletes` is enabled; check the existing model before changing query behavior.
- Put reusable query helpers in models, such as dropdown methods, joined list methods, and schedule/fee/commission overlap checks.

## Views and Layouts

- The root UI template is `app/Views/templates/theme1.php`.
- The Admin layout (`app/Modules/Admin/Views/layout.php`) extends that template and supplies `side-menu` and `top-menu` sections.
- Admin pages extend the Admin layout, populate `main-content`, and add page scripts through a `scripts` section.
- Use `esc()` when rendering database-backed values in views.
- Reuse `templates/_breadcrumb` and `display_session_msg()` where appropriate.

## Route Conventions

- Define routes in the owning module's `Config/Routes.php`, grouped by URI prefix and controller namespace.
- Use explicit HTTP verbs: `get()` for pages/read endpoints and `post()` for create/update/delete endpoints.
- Protected doctor-related admin route groups use the `auth` filter and route-level `permission:<permission_key>` filters.
- Permission keys follow a dot-separated resource/action form, for example `doctor_schedule.view` and `doctor_document.create`.
- Keep client-side endpoints in `public/assets/js/routes.js` synchronized with server route changes.

## Database and Migrations

- Database migrations reside in `app/Database/Migrations/` and use CI4 timestamp-prefixed filenames in the `Y-m-d-His_` format.
- Migration classes use descriptive `Create...` names and define `up()`/`down()` with the Forge API.
- Create indexes and foreign keys in migrations, including uniqueness constraints for identifiers and mapping tables where required.
- Seeders reside in `app/Database/Seeds/`; `DatabaseSeeder` orchestrates baseline roles, permissions, role permissions, and doctor specializations.
- Use a new migration for schema changes; do not alter an already-applied production migration.

## Database Naming

- Tables use lowercase plural snake_case: `clinics`, `doctor_documents`, `role_permissions`.
- Primary keys are normally unsigned integer `id` fields.
- Foreign keys use `<related_table_singular>_id`, such as `doctor_id`, `clinic_id`, and `role_id`.
- Join/mapping tables combine both names, such as `user_roles`, `role_permissions`, and `doctor_specialization_map`.
- Timestamps use `created_at`, `updated_at`, and, where used, `deleted_at`.
- Active/inactive flags are normally `status` stored as `TINYINT(1)`, with `1` active and `0` inactive.

## Validation

- Validation rules are currently defined close to the action in private/protected controller methods, then executed using `$this->validate($rules)`.
- Return the first error as `msg` and all field errors as `errors` for AJAX calls.
- Use CI4 validation rules for required values, types, lengths, enum/list values, uniqueness, and file restrictions.
- For updates, exclude the current row in `is_unique` rules.
- Validate all input on the server; JavaScript validation is not a substitute.

## Authentication and Authorization

- Authentication is custom, not CI4 Shield. `App\\Services\\AuthService` authenticates with the `users` table using `password_verify()`.
- A successful login stores `isLoggedIn`, `user`, `roles`, and `permissions` in the session.
- Roles are resolved through `user_roles`; permissions are resolved through `user_roles`, `role_permissions`, and `permissions`.
- The `auth` filter redirects unauthenticated users to `system/login`.
- The `permission` filter reads its permission key from route arguments. It redirects normal requests and returns JSON for AJAX denials.
- New protected functionality should use both the appropriate base controller and route filters consistently.

## CSRF and Sessions

- CSRF is globally enabled as a before filter.
- CSRF uses cookie protection, token name `csrf_rtn`, header `X-CSRF-TOKEN`, cookie `csrf_rtn_cookie`, and a two-hour expiry. Tokens are not randomized or regenerated per submission.
- Sessions use the CI4 file handler at `writable/session`, cookie `ci_session`, two-hour expiration, and periodic ID regeneration.
- Preserve CSRF support in all new forms and AJAX writes.

## AJAX and API Conventions

- This project is not organized as a separate REST API; JSON endpoints support the server-rendered admin UI.
- jQuery globally sends `X-Requested-With: XMLHttpRequest` in `public/assets/js/app.js`.
- Use `ajaxRequest()` in `public/assets/js/common.js` for loader handling and common HTTP error behavior.
- Successful JSON responses conventionally use `{ status: true|false, msg: string }`; validation errors add `{ errors: { field: message } }`.
- Location AJAX endpoints currently return rendered HTML `<option>` fragments inside `data`; retain this contract if modifying their consumers.
- Use `FormData`, `processData: false`, and `contentType: false` for file uploads.

## JavaScript and CSS

- Feature-specific JavaScript belongs under `public/assets/js/admin/<feature>/`.
- Shared scripts are `app.js`, `routes.js`, and `common.js`; include page scripts through the view's `scripts` section.
- The UI uses CDN-hosted Bootstrap 4.6.2, jQuery 3.7.1, DataTables 1.13.11, bootstrap-select, bootstrap-datepicker, jquery-timepicker, SweetAlert2, Bootstrap Icons, Font Awesome, and Manrope.
- Project styles are in `public/assets/css/theme1.css` and `public/assets/css/style.css`.
- Use the existing Bootstrap 4 markup/API and existing helper functions (`showSuccess`, `showError`, `show_loader`, `hide_loader`, `bindFormError`, `clearFormErrors`) rather than introducing competing patterns.

## File Upload and Storage

- Doctor document uploads are handled by `Admin\\Controllers\\Doctor\\DoctorDocument` and stored in Cloudflare R2 through `App\\Services\\Common\\R2StorageLibrary`.
- R2 configuration comes from environment variables: `R2_BUCKET`, `R2_ENDPOINT`, `R2_ACCESS_KEY`, `R2_SECRET_KEY`, and `R2_PUBLIC_URL`.
- Store the R2 object key in `file_path`, not a local filesystem path.
- Validate uploaded files with CI4 file rules. The current doctor-document flow requires an upload on create and permits PDF, JPG/JPEG, PNG, DOC, and DOCX up to 10 MB.
- For storage and database operations, upload the replacement before deleting an existing object; remove newly uploaded objects if the database write fails; log cleanup failures.

## Development Rules

- Do not change existing configuration, routes, client route constants, views, or controllers independently when another layer consumes the changed behavior. Trace the full request flow first.
- Add only explicitly routed controller methods. Keep public controller methods limited to intended endpoints.
- Add authentication and granular permissions before exposing a new admin action.
- Use migrations and seeders for database changes. Preserve foreign keys, indexes, uniqueness constraints, and timestamp conventions.
- Follow the current server-rendered view plus jQuery/AJAX approach for adjacent functionality unless a broader approved refactor is in scope.
- Preserve error handling, JSON response contracts, and flash-message behavior expected by existing JavaScript and views.
- Do not expose environment secrets, R2 credentials, or database credentials in source, views, JavaScript, logs, or commits.
- Add focused application tests when extending workflows; current tests are primarily CI4 starter examples and do not cover the implemented admin features.
- Before implementing a new feature, first search the existing codebase for similar functionality and reuse established patterns rather than introducing a new architectural pattern.
- Before creating a new table, model, service, controller, or JavaScript component, verify that an existing component cannot appropriately support the requirement.
- Do not introduce new third-party libraries or frameworks without explicit approval.
- Do not upgrade CodeIgniter, PHP, Bootstrap, jQuery, or other major dependencies as part of an unrelated feature.

## Rules to Avoid Breaking Existing Functionality

- Do not enable soft deletes globally or change model soft-delete settings without reviewing every affected query and migration.
- Do not change a permission key without updating seeders, assigned roles, route filters, menus, and session-based authorization expectations.
- Do not change session key names (`isLoggedIn`, `user`, `roles`, `permissions`) without updating authentication, filters, base controllers, and consumers.
- Do not change JSON field names or the HTML-option response format without updating all JavaScript callers.
- Do not replace CDN framework versions or Bootstrap 4 APIs without checking every layout, modal, table, and feature script.
- Do not delete an R2 object before its database replacement/update succeeds; tolerate and log storage cleanup failures after a successful database change.
- Do not modify existing applied migrations to alter production schema; append a migration instead.
- Keep future work isolated from unrelated uncommitted work in the repository.


