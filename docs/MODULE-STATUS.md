# ROOTINS Module Status

**Version:** 0.5 milestone update
**As of:** 2026-08-29  
**Status vocabulary:** Implemented = repository has a usable routed flow verified against acceptance evidence; Partial = meaningful groundwork exists but the complete role workflow is not present; Missing = no verified implementation was found; Unverified = evidence exists but required verification is incomplete; `PROPOSED` = owner-directed baseline not yet approved as final policy or verified in code; `NEEDS_REVIEW` = requires legal, medical, security, or operational review; `NEEDS_DECISION` = blocked by an unresolved business, provider, or compliance decision.

See `docs/RELEASE-CHECKLIST.md` for the consolidated release checklist, evidence matrix, deployment gates, and sign-off criteria.

## Summary

| Module | Current status | Evidence observed | Linked requirements | Main next step |
|---|---|---|---|---|
| Admin | Partial | Dashboard, clinics, doctors, schedules, leave, fees, commissions, documents, patients, appointments, consultations, prescriptions, medicines, operational reporting, billing, durable audit storage, and an Admin Access page now have routed controllers/views/models/migrations in varying completeness. | ADM-001 → ADM-007 | Add database/browser acceptance, complete clinic-scope policy, settings, and report definitions. |
| Doctor | Partial | Doctor module has authenticated dashboard, appointment-detail, and clinical-workspace routes/controllers/views; queue handling, consultation save/completion, prescription draft/submission, availability/leave, history, and scoped audit events are implemented. | DOC-001 → DOC-005 | Add database-backed integration/UAT tests, richer prescription item UI, telehealth, billing, and final clinical-policy review. |
| Patient | Partial | Patient module now has an authenticated portal resolving the logged-in patient by `users.id`, clinic-scoped doctor browsing, profile/contact updates, appointment booking/rescheduling/cancellation with schedule/leave/conflict checks, consultation reports, prescription history, private document upload/download through short-lived storage URLs, and scoped audit events. | PAT-001 → PAT-005 | Add database/browser acceptance tests, durable notification delivery/preferences, and invoice/payment history once the billing contract exists. |
| Pharmacy | Partial | Pharmacy has an authenticated dashboard and prescription review queue, approved-prescription order creation with transactional batch/expiry stock allocation and duplicate protection, provider-neutral payment reconciliation, controlled delivery transitions, and audit events with clinic scope. | FARM-001 → FARM-004 | Add runtime integration/UAT coverage and finalize legal/operational policy. |

### Doctor appointment milestone — repository verified, runtime UAT pending

The Doctor appointment register now has scoped server-side pagination with search, consultation-type, status, and `dd-mm-yyyy` date-range filters; stable date/time/ID ordering; a searchable patient selector ordered newest-first; service and status fields on create; transactional conflict-safe create/reschedule/cancel actions; an accessible reschedule datepicker modal; queue transition actions using the existing Clinical endpoint; and related patient, consultation, prescription, follow-up, and billing links on appointment details. Routes remain explicit and protected by `auth` plus `appointment.view`, `appointment.create`, and `appointment.edit` permissions. Focused static/unit coverage passes, but authenticated browser/database UAT, availability data verification, and production policy review remain pending.

## Cross-cutting status

| Area | Status | Notes |
|---|---|---|
| Authentication/session | Partial | Custom authentication and session conventions exist; role-specific module access still needs completion and verification. |
| Authorization | Partial | Auth/permission filters and Admin permission usage exist; complete permission matrix and audit coverage are required. |
| Database foundation | Partial / active | Clinics, users/roles/permissions, doctors, schedules, leaves, fees, commissions, documents, patients, appointments, consultations, prescriptions, prescription items, and medicines have migrations. |
| Billing/payments | Partial | Pharmacy medicine-order manual payment reconciliation allows any active payment status, requires a Payment Provider, keeps Reference number and Reason / remarks optional, and records manual reconciliation when no active adapter/credentials are available without calling an unconfigured external provider. Invoice totals, gateway callbacks, refunds, and settlement reconciliation remain incomplete. |
| Audit logs | Partial | Structured `ROOTINS_AUDIT` events now persist sanitized context to `audit_logs` with correlation IDs, actor IDs, safe masking, scoped Admin viewing, and bounded context. Search, retention, and full action coverage are not implemented. |
| Notifications | Partial | Provider-independent dispatch service now validates requests, selects Twilio/SendGrid provider names, returns idempotency/retry metadata, and remains dry-run by default; live delivery, consent, and durable delivery tracking are not implemented. |
| Backup/restore and system logs | Partial | System Logs now reads scoped `audit_logs` with server-side search/filter/pagination. Backup/Restore reports local readiness only and deliberately does not fake backup or restore operations. |
| Tests | Partial | CI4 starter test structure exists; feature-specific coverage is still required for implemented Admin workflows. |
| Reporting/KPIs | Partial | Permission-protected operational overview reports active patients, appointment outcomes, consultations, prescription review queue, and upcoming appointments with optional clinic scope. | Exact KPI definitions, date filters, revenue/order metrics, pagination, and performance tests remain unverified. |
| Performance | Unverified | Pagination/filtering and query efficiency are required; response-time and N+1 behavior were not verified. |
| Compliance | NEEDS_REVIEW | India is the baseline jurisdiction; legal applicability, control mapping, and release evidence remain to be reviewed. |

## Admin identity and access status

| Capability | State | Evidence and limitation |
|---|---|---|
| User management | Partial | `GET /admin/access` and POST create/update/disable routes use `auth` plus `user.create`, `user.edit`, and `user.delete` filters. Passwords are hashed, never returned, and self-disable/delete is blocked. Database/browser acceptance remains outstanding. |
| Role management | Partial | Role create/update/disable routes use `role.manage`; system roles cannot be edited or deleted. |
| Permission management | Partial | Permission create/update/status route uses `role.manage`; existing permission keys cannot be changed after creation to avoid breaking consumers. |
| Role-permission assignment | Partial | Transactional replacement endpoint `POST /admin/access/roles/{id}/permissions` validates active permission IDs and records an audit event. |
| User-role assignment | Partial | User create/update accepts active role IDs and replaces assignments transactionally; duplicate rows are prevented by the existing unique key. |
| Clinic scope | Unverified | User listing and doctor assignment honor the existing session clinic scope when present and derive linked patient/doctor visibility from existing relationships. The `users` schema has no direct `clinic_id`; policy and database-backed scope tests remain required. |
| Central enforcement | Partial | Routes use `auth` and permission filters; controller validation repeats role/status/scope checks. Complete matrix coverage and runtime negative-path testing remain required. |
| Identity/access audit | Partial | User, role, permission, and role-permission changes call the structured audit service and persist durable sanitized events. Full search/retention remain unimplemented. |

IAM UI structure: `/admin/access/users` is the searchable user-management screen; `/admin/access/roles` is the role editor with grouped permission selection, search, select-all/clear-all, and permission counts; `/admin/access/permissions` is the read-oriented grouped permission catalog. The sidebar exposes these as separate `Users`, `Roles`, and `Permissions` entries. Permission creation/editing remains restricted to `role.manage`, and permission keys are immutable after creation. The pages use access-specific responsive cards, badges, filters, empty states, inline status alerts, and keyboard-accessible form controls.

IAM route evidence: `GET /admin/access`; `POST /admin/access/users/create`; `POST /admin/access/users/update/{id}`; `POST /admin/access/users/delete/{id}`; `POST /admin/access/roles/save`; `POST /admin/access/roles/delete/{id}`; `POST /admin/access/roles/{id}/permissions`; and `POST /admin/access/permissions/save`. All are explicit routes with global CSRF/auth filters and the documented permission filters.

## India baseline status

| Area | Current status | Evidence / limitation |
|---|---|---|
| Jurisdiction | Confirmed baseline | India is owner-provided as the initial jurisdiction; exact legal applicability and operating policies remain `NEEDS_REVIEW`. |
| Privacy and health data | NEEDS_REVIEW | DPDP Act/Rules and ABDM consent/health-data guidance are documented as review references; consent, purpose, access, retention, withdrawal, and grievance controls are not implemented/verified. |
| Telemedicine and prescriptions | NEEDS_REVIEW | Indian telemedicine guidance is a review reference; provider identity, patient consent, consultation records, and prescription policy require medical/legal review. |
| Payments | Partial | Cashfree and Razorpay are approved candidate providers; no verified integration, credentials/configuration, reconciliation, refund, or production compliance review exists. |
| Pharmacy and delivery | NEEDS_REVIEW | Indian-standard operating baseline is accepted as direction; licensing, substitution, batch/expiry, delivery, returns, and fulfilment policy are not defined or implemented. |
| Backup and recovery | NEEDS_REVIEW | Encrypted backups and restore testing are required. `[PROPOSED]` baseline is RPO 24 hours/RTO 8 hours; not approved or implemented. |
| KPIs and reports | NEEDS_REVIEW | Standard baseline KPI/report categories are proposed; exact definitions, owners, filters, and targets are not approved or verified. |

| Pharmacy Assistant role | PROPOSED | Baseline is an internal clinic/platform pharmacy/fulfilment role; external legal-entity support is out of scope until separately reviewed. |
| Prescription state machine | PROPOSED | Proposed states are documented; exact semantics, authority, and implementation are not verified. |
| Inventory/delivery baseline | NEEDS_REVIEW | Proposed batch/expiry and delivery states are documented; operational, legal, and implementation evidence is missing. |
| ABDM/ABHA | PROPOSED | Out of scope for first release but integration-ready; no integration is verified. |
| Telehealth | PROPOSED | Twilio Video is the first provider; room-scoped short-lived tokens, no recording by default, consent, and audit are required. No integration is verified. |
| SMS/WhatsApp | Partial | Twilio Messaging/WhatsApp provider placeholders are selected by the notification boundary; sender/template/DLT, consent/opt-out, and live delivery are not verified. |
| Email | Partial | Twilio SendGrid provider placeholder is selected by the notification boundary; secrets, configuration, deliverability, and live delivery are not verified. |
| Pharmacy | PROPOSED | Internal ROOTINS pharmacy/inventory/fulfilment is first-release scope; external pharmacy/commerce integration is deferred. |
| Shipping | PROPOSED | Shiprocket is the first adapter target; credentials, serviceability, labels, webhooks, reconciliation, and returns are not verified. |
| Commerce synchronization | Missing | External commerce synchronization is out of scope for the first release; adapter-ready only. |

## First Admin vertical slice status

**Overall state: Partial / not release-ready.** The repository contains evidence for an Admin-side flow from authentication and permissions through clinic, doctor, patient, appointment, consultation, and prescription areas, but the complete slice has not been verified against its acceptance criteria.

| Slice area | Evidence observed | State | Limitation |
|---|---|---|---|
| Admin authentication | Login route/controller, `AuthService`, session contract, `Auth` filter, and an `auth` filter on the Admin dashboard route exist. | Partial | End-to-end login, logout, session expiry, and negative-path tests were not verified. Dashboard-specific permission policy is not defined. |
| Admin permissions | Permission filter, base-controller checks, permission seed data, and route-level permission filters exist for several areas. | Partial | Complete permission matrix and clinic/data scope tests are not verified. Clinic and doctor route groups now have explicit `auth` and existing `clinic.*`/`doctor.*` permission filters; security regression verification remains required. |
| Clinic management | Admin clinic controller, model, views, explicit CRUD routes, and clinic migration exist. | Unverified | Requires focused validation, authorization, and regression tests before release classification. |
| Doctor management | Admin doctor controller, related models/views, schedules, leave, fees, commissions, documents, routes, and migrations exist. | Unverified | Full workflow and conflict/security tests are not verified. |
| Patient management | Admin patient controller, registration, medical history, documents, views, routes, services, models, and migrations exist. | Unverified | Patient self-service is missing; Admin flow still needs acceptance and isolation tests. |
| Appointment management | Admin appointment controller, calendar, queue, reports, views, routes, model, and migrations exist. | Unverified | State-transition, race-condition, and booking-conflict tests are not verified. |
| Consultation management | Admin consultation controller, views, routes, model, and migration exist. | Unverified | Clinical completion, authorization, and prescription linkage tests are not verified. |
| Prescription management | Admin prescription controller, views, PDF service/routes, models, item model, and migrations exist. | Unverified | Finalization/history, review/fulfilment, and downstream billing are not verified. |
| Audit logging | `App\\Services\\Audit\\AuditLogger`, `audit_logs`, and Admin audit viewing exist with correlation IDs and masking; search, retention, and full action coverage are not implemented. | Partial | Required before the vertical slice can be release-ready. |

### Verification limitations

- The repository now contains focused unit tests for audit logging, notification dispatch, permission denial/allow paths, Patient appointment contracts, and Pharmacy delivery/provider boundaries; database-backed end-to-end tests for clinical and fulfilment workflows were not found.
- The repository now also contains focused Access tests for password policy and IAM route permission boundaries; database-backed user/role/permission and clinic-scope tests were not found.
- Full PHPUnit execution was attempted: all 17 non-database tests passed, while two CI4 example database tests errored because the `sqlite3` PHP extension is unavailable. Static-analysis tools are not installed, and browser/performance verification remains outstanding.
- `Unverified` means repository artifacts and explicit routes were found; it does not mean the feature passed end-to-end acceptance.
- Route output must be reviewed for every protected Admin entry point; controller inheritance alone is not sufficient evidence of route-level authorization coverage. The Admin dashboard, clinic, and doctor route groups now show explicit `auth` and permission filters after this milestone.
- Billing, notifications, external integrations, backup/restore, compliance controls, and operational audit remain outside the verified slice.

### Verification performed

- Read-only inspection of Git status, module route files, Admin routes, controllers, models, services, views, migrations, filters, and test files.
- Confirmed that `app/Config/Routes.php` dynamically loads module route files.
- Confirmed explicit Admin routes for clinics, doctors, patients, appointments, consultations, prescriptions, and medicines.
- Confirmed authentication/session and permission-filter evidence in the application; confirmed structured audit logging and durable sanitized audit-log storage exist, but complete action coverage is not implemented.
- `php spark routes` completed successfully and showed explicit Admin routes; the dashboard, clinic, and doctor entry routes now show route-level `auth` plus the existing permission keys.
- PHP lint completed successfully for 191 application PHP files.
- `vendor/bin/phpunit tests/unit --testdox` completed successfully with 16 tests and 47 assertions. Full `vendor/bin/phpunit --testdox` passed all 17 non-database tests (19 total discovered) but had 2 environment errors because `sqlite3` is not loaded; PHPUnit also emitted a non-blocking no-code-coverage warning.
- The current working tree contains prior application, migration, service, module, and focused-test changes; the latest IAM pass added application routes/controllers/views/tests and preserved unrelated changes. `php spark migrate:status` shows `CreateMedicineOrdersAndInventory` is unapplied; no migration was executed.

## Verified repository inventory

- CodeIgniter 4 framework constraint is `^4.7` and PHP constraint is `^8.2`.
- Module route files are dynamically loaded by `app/Config/Routes.php`.
- Admin currently contains routed areas for clinics, doctors, doctor schedules/leaves/fees/commissions/documents, patients, patient medical history/documents, appointments, consultations, prescriptions, and medicines.
- Patient has a verified authenticated portal route/controller/view with profile, scoped doctor browsing, appointment booking/rescheduling/cancellation, record history, and private document actions; invoice/payment-history visibility and database/browser acceptance remain incomplete. Pharmacy has authenticated prescription review routes; Doctor has a scoped dashboard route, appointment detail, and clinical actions.
- The working tree contains unrelated/uncommitted application changes. Documentation work must not overwrite or reset them.

## Release gates by module

### Admin

- Complete the permission matrix and CRUD validation.
- Add appointment/queue transition and consultation/prescription consistency tests.
- Complete medicine rules, billing, audit events, reports, and dashboard KPI definitions.
- Verify pagination, query efficiency, security regression coverage, and requirement traceability.

### Doctor

- Complete database-backed workflow tests and UAT for queue, consultation, prescription, availability, and history actions.
- Add richer prescription item handling, telehealth, billing, and final clinical-policy review.
- Retain doctor-to-clinic and doctor-to-patient scope enforcement and audit coverage.

### Patient

- Add database-backed and browser/UAT coverage for patient authentication, isolation, appointment conflicts, schedule/leave checks, and private document access.
- Define and implement invoice/payment-history read visibility after the billing schema and refund/tax policy are approved.
- Add durable notification preferences/delivery tracking after consent and provider deployment decisions.

### Pharmacy

- Define the prescription review state machine and approval/cancellation authority.
- Implement medicine availability, fulfilment, and billing ownership rules.
- Add external-order idempotency and exception handling.
- Preserve original doctor instructions and audit all review decisions.

## Final release audit — 2026-08-29

This is a read-only repository audit. `PASS` means the stated evidence was observed and the safe check succeeded; it does not mean production approval. `PARTIAL` means a bounded implementation exists but release acceptance is incomplete. `UNVERIFIED` means repository evidence exists without sufficient runtime or integration proof. `FAIL` identifies a release-blocking deficiency.

| Area | Result | Evidence and release interpretation |
|---|---|---|
| Admin | PARTIAL | Explicit authenticated and permission-filtered routes and broad Admin CRUD foundations exist; end-to-end authorization, clinic-scope, workflow, and browser acceptance are not proven. |
| Doctor | PARTIAL | Authenticated scoped dashboard, appointment detail, and clinical workspace exist with queue, consultation, prescription, availability/leave, and history actions; database-backed acceptance remains unverified. |
| Patient | PARTIAL | Authenticated clinic-scoped Patient portal now supports profile, appointment booking/rescheduling/cancellation, consultation/prescription history, and private document operations; runtime isolation, billing-history contract, and production storage/provider verification remain incomplete. |
| Pharmacy | PARTIAL | Authenticated dashboard/review queue, review transitions, approved-prescription order creation, payment reconciliation, and delivery transitions exist with Pharmacy Assistant role and cross-record clinic-scope checks; runtime/UAT remains. |
| Billing/payments | PARTIAL | Provider-neutral Cashfree/Razorpay payment-status reconciliation with duplicate handling exists; invoice totals, callbacks, refunds, tax, and settlement reconciliation remain incomplete. |
| Notifications | PARTIAL | Provider-independent dry-run dispatcher with idempotency/retry metadata exists; no live delivery, consent, durable delivery tracking, or provider readiness is verified. |
| Audit/security | PARTIAL | CSRF, auth filters, permission denial, correlation IDs, masking, durable sanitized audit storage, and scoped Admin audit viewing have evidence; complete event coverage and runtime security regression tests are incomplete. |
| Backup/recovery | FAIL / MISSING | No verified encrypted backup, restore, verification, or recovery runbook implementation exists. Proposed RPO 24h/RTO 8h is not approved. |
| Production readiness | UNVERIFIED / NOT APPROVED | No deployment, secrets, provider onboarding, legal/compliance sign-off, database migration rehearsal, monitoring, or browser/UAT evidence was verified. |

### Release blockers

- Complete database/browser acceptance and private-record isolation tests for the Patient portal.
- Complete Doctor consultation/prescription workflow and Admin clinical acceptance tests.
- Complete billing refunds/cancellations and tax behavior after `NEEDS_REVIEW` decisions.
- Extend audit coverage and database-backed clinical, financial, authorization, and status-transition tests.
- Establish encrypted backup/restore procedures, verification evidence, and approved RPO/RTO.
- Complete India legal/medical/security review, consent/purpose/retention policy, and provider deployment readiness.
- Run authenticated browser/UAT, integration, performance, static-analysis, and production-like migration rehearsal checks.

### Deferrable without blocking the application baseline

- ABDM/ABHA linking and consent-based exchange (proposed first-release exclusion).
- External commerce synchronization (adapter-ready only).
- Live Twilio, Shiprocket, Cashfree, and Razorpay integrations until deployment onboarding is approved; dry-run boundaries can remain for development.
- Advanced KPI/report targets and nonessential dashboard polish after definitions are approved.

## Completion-effort inventory — 2026-08-29

### Skipped because implementation already exists

- Admin route authentication/permission filters and normal-request permission denial fix.
- Doctor authenticated, doctor/clinic-scoped dashboard.
- Pharmacy authenticated prescription review queue and approve/reject/clarification transitions, including cross-record clinic checks.
- Patient authenticated portal with scoped profile, appointment booking/rescheduling/cancellation, consultation/prescription history, and private document upload/download.
- Admin Access page with user management, role management, permission management, user-role assignment, role-permission assignment, scope validation, password hashing, status safeguards, and identity/access audit events.
- Admin IAM UI split into separate searchable Users, Roles, and read-oriented Permissions screens with responsive access-specific styling.
- Provider-independent dry-run notification dispatcher with retry/idempotency metadata.
- Structured audit logger with correlation IDs and safe masking.
- Existing Admin clinic, doctor, patient, appointment, consultation, prescription, medicine, and document foundations.

### Completed in this pass

- Patient authenticated dashboard at `GET /patient`, resolving the active patient by logged-in `users.id` and restricting appointment, visit, and prescription reads by patient and clinic scope.
- Admin operational overview at `GET /admin/reports/overview`, with existing appointment-view permission, role guard, optional clinic scope, cross-record clinic consistency, and read-only KPI/table output.
- Doctor appointment detail at `GET /doctor/appointments/{id}`, with role guard, doctor/clinic/patient isolation, linked consultation/prescription status, escaped output, and an audit event.
- Doctor clinical workspace at `GET /doctor/clinical`, with queue transitions, consultation completion, prescription draft/submission, availability/leave, consultation history, scope validation, transactions, duplicate handling, and audit events.
- Pharmacy dashboard at `GET /pharmacy`, approved-prescription order creation, payment reconciliation, and delivery lifecycle endpoints with clinic scope, transaction/idempotency controls, and audit events.

### Remaining unique application-code inventory

- Admin: database/browser IAM acceptance tests, complete scope/permission coverage, settings, and report definitions.
- Doctor: database-backed workflow tests, richer prescription item handling, telehealth, billing, and final clinical-policy/UAT evidence; appointment detail, queue, consultation save/completion, prescription draft/submission, availability/leave, and history are complete for this milestone.
- Patient: database/browser acceptance for booking/reschedule/cancel and private-record isolation, notification preferences, and billing visibility.
- Pharmacy: runtime inventory/order/payment/delivery tests and final policy/UAT evidence; the medicine-order migration is applied locally.
- Billing: provider abstraction for Cashfree/Razorpay, settlement reconciliation, and policy-configured refunds/cancellations/tax behavior.
- Notifications/integrations: durable delivery status, consent/opt-out enforcement, and sandbox adapters for approved providers; live credentials remain deployment-only.
- Reporting/security/operations: additional scoped KPI/report definitions, complete security regression tests, backup/restore implementation, monitoring, and release evidence.

## Status maintenance rule

Update this file when a routed end-to-end workflow is delivered, when a requirement changes state, or when an architectural dependency is discovered. Requirement states are `Planned`, `In progress`, `Implemented`, `Verified`, or `Deferred`. A migration, model, or view alone does not make a module implemented; the intended role must be able to use the complete authorized workflow and its acceptance evidence must exist.

### Doctor completion pass — 2026-09-01

Existing Doctor dashboard, clinical workspace, appointment scheduling/detail/reschedule/cancel,
queue transitions, consultation save/completion, prescription draft/submission, follow-ups,
scoped patient create/edit/history, billing visibility, availability/leave, and paginated
records were retained as existing partial workflow evidence. This pass added the previously
disabled Doctor destinations: permission-aware Profile & Settings (`GET/POST /doctor/profile`),
clinic-scoped Notifications with read state and server-side pagination (`GET /doctor/notifications`,
`POST /doctor/notifications/{id}/read`), and a scoped integration-ready Video Consultation entry
(`GET /doctor/video/{appointmentId}`) for video appointments. Notification persistence is provided
by migration `2026-09-01-120000_CreateDoctorNotifications`, applied locally with `php spark migrate`.

Static verification: all Doctor PHP files and the new migration lint successfully; Doctor routes
registered with `auth` and permission filters; `tests/unit/DoctorWorkflowTest.php` passed 7 tests
and 52 assertions; the Doctor module contains no JavaScript DataTables usage; `git diff --check`
passed. Authenticated browser UAT, live Twilio room/token creation, provider credentials, and
database-backed negative-path tests remain unverified/deployment-gated. Doctor remains `Partial`
until those acceptance checks and final clinical/provider review are complete.

Read-only database security checks are now defined in
`tests/integration/DoctorDatabaseSecurityTest.php`. They execute only when a provisioned
`database.tests` connection and required tables are available; the current local PHPUnit
configuration has no test connection and no SQLite extension, so these checks are explicitly
skipped locally rather than treated as passing evidence.

### Navigation evidence — 2026-08-29

Admin, Doctor, Patient, and Pharmacy now have distinct panel navigation with
accessible labels, active route states, valid logout routing, and role/
permission-aware links where permissions exist. Admin dead `#` action links were
removed; unavailable Settings screens remain explicitly marked planned. The
navigation regression checks are static and route-list based; authenticated
browser/UAT verification remains outstanding.

### UI/UX modernization evidence — 2026-08-30

The shared theme now provides centralized ROOTINS design tokens and reusable
visual rules for cards, forms, tables, buttons, badges, alerts, focus states,
loading overlays, responsive content, and mobile navigation. All panel layouts
use the shared sidebar/header shell; the Patient portal uses labelled controls
and a searchable `btSelect` doctor selector. The theme exposes a skip link and
live alert region, and common success/error notifications are non-blocking
toasts. Existing Bootstrap 4.6, jQuery, DataTables, and bootstrap-select
contracts are preserved.

State: `Partial / UI runtime verification pending`. Static navigation/UI tests
cover shared tokens, accessibility hooks, panel layouts, and the Patient
large-data selector. Authenticated browser checks, full-page visual review,
and server-side search/pagination for very large datasets remain outstanding;
these are release evidence gaps, not claims of full UI verification.

### Admin billing and audit evidence — 2026-08-29

Admin billing now has scoped invoice draft creation, server-calculated totals,
transactional line-item persistence, idempotency handling, controlled
finalization, and payment-status updates at `/admin/billing`. Durable sanitized
audit events are persisted to `audit_logs` and are read through the scoped
`/admin/audit` screen. The invoice/audit migration is applied locally. Runtime
database acceptance, tax/refund policy, settlement reconciliation, and legal
sign-off remain release gates.

## Exact pending items

- `[NEEDS_REVIEW]` Indian privacy, medical-record, tax, consent, retention, and compliance applicability.
- `[NEEDS_DECISION]` Patient identity verification, duplicate detection, and controlled merge policy.
- `[NEEDS_REVIEW]` Cashfree/Razorpay production configuration, merchant onboarding, settlement, refunds, partial payments, disputes, and cancellation charges.
- `[NEEDS_REVIEW]` Deployment readiness for Twilio Video, Twilio Messaging/WhatsApp, Twilio SendGrid, internal ROOTINS pharmacy, and Shiprocket; Cashfree/Razorpay remain approved candidates pending production review.
- `[NEEDS_REVIEW]` Legal/medical confirmation of the proposed internal Pharmacy Assistant role and prescription review authority.
- `[NEEDS_REVIEW]` Proposed inventory, batch, expiry, substitution, delivery, and order-reconciliation rules.
- `[NEEDS_REVIEW]` Backup encryption/key ownership, retention, restore frequency, and final recovery targets; RPO 24 hours/RTO 8 hours remain proposed.

## India-specific blockers

- `[NEEDS_REVIEW]` Legal/medical interpretation of the DPDP Act/Rules, ABDM health-data and consent guidance, applicable Indian telemedicine guidance, RBI payment-aggregator requirements, and pharmacy/tax/consumer rules.
- `[NEEDS_DECISION]` Approved consent notices, purposes, withdrawal/deletion behavior, grievance process, and retention schedule.
- `[NEEDS_REVIEW]` Cashfree/Razorpay credential ownership, merchant onboarding, webhook ownership, settlement/reconciliation, refund/dispute process, and production enablement.
- `[NEEDS_REVIEW]` Final Indian pharmacy/delivery operating rules, including substitution, inventory, batch/expiry, returns, cancellations, refunds, and customer support.
- `[NEEDS_REVIEW]` Proposed RPO 24 hours and RTO 8 hours before adoption as service targets.
- `[NEEDS_REVIEW]` Twilio Video, Twilio Messaging/WhatsApp, Twilio SendGrid, and Shiprocket provider contracts, credentials, configuration, consent/template/DLT, serviceability, webhook, and production-readiness details.
- `[NEEDS_REVIEW]` Rehearse and approve `2026-08-29-100000_CreateMedicineOrdersAndInventory` for deployment; local migration status is applied and no production migration status was verified.
- `[NEEDS_DECISION]` Whether and when ABDM/ABHA linking or consent-based exchange should move beyond the proposed first-release exclusion.

## Exact next coding tasks

1. Verify unauthenticated and unauthorized dashboard, clinic, billing, audit, and doctor behavior at runtime; decide/document whether a dashboard-specific permission is required.
2. Add feature tests for login/session behavior, permission denial, clinic scope, patient isolation, appointment transitions/conflicts, consultation completion, and prescription finalization.
3. Extend audit event coverage and database-backed security tests across all authentication, permission, clinical, financial, configuration, and status-sensitive actions.
4. Re-run route listing, linting, PHPUnit, static analysis, and targeted security tests after the code changes.
5. Implement the proposed prescription state machine, inventory/expiry controls, substitution approval, delivery states, and controlled amendment/reversal workflow after `NEEDS_REVIEW` decisions.
6. Add provider-abstracted Cashfree/Razorpay payment flows with idempotency, reconciliation, refunds, disputes, and safe failure handling after merchant/configuration review.
7. Implement consent/purpose/retention controls, encrypted backups, restore testing, and proposed KPI/report foundations after policy decisions are approved.

### Admin Billing/Audit UI evidence — 2026-08-30

Billing and Audit Logs now use the shared Admin shell with page headings, scoped
summary cards, responsive cards/tables, readable status badges, labelled controls,
empty states, and safe action confirmations. Billing has searchable, sortable,
filterable, paginated DataTables for invoice/payment status. Audit Logs has
search, date, action-group, and clinic filters plus a keyboard-accessible detail
modal that displays only sanitized context. Actions remain behind the existing
`billing.*` and `audit.view` route permissions and clinic-scoped controllers.

Static UI/navigation tests, PHP lint, route listing, and `git diff --check` pass.
The screens currently consume the controller’s latest 100 scoped records, so
server-side pagination/search for larger datasets and authenticated browser/UAT
verification remain `Partial`/`Unverified` release evidence gaps. Tax, refunds,
settlement reconciliation, retention, and legal review remain unchanged blockers.

### Doctor panel evidence — 2026-08-30

The Doctor panel now has permission-filtered dashboard and clinical workspace
navigation plus a scoped `GET /doctor/patients` register and
`GET /doctor/patients/{id}` profile/history view. Patient reads require an
active Doctor session, assigned doctor relationship, and matching clinic; the
patient profile records appointments, consultations, and prescriptions only
within that scope. Clinical queue, consultation, and prescription writes now
also carry explicit route-level `appointment.edit`, `consultation.edit`, and
`prescription.create` filters. Doctor screens use shared responsive cards,
headers, badges, labelled fields, searchable/paginated patient and history
tables, empty states, and safe action feedback.

Focused Doctor/navigation tests, PHP lint, route listing, and diff checks are
the current evidence. Authenticated browser/UAT and database-backed isolation
tests remain unverified. Doctor billing/payment visibility, live telehealth,
notifications, prescription print/download, profile/settings, attachment
handling, and richer vitals/clinical fields remain blocked until approved
Doctor routes/services and provider boundaries are available.

### Doctor required-destination evidence — 2026-08-30

Required Doctor destinations are now represented by explicit permission-aware
routes and active-state menu entries: `/doctor/clinical`, `/doctor/patients`,
`/doctor/appointments`, `/doctor/follow-ups`, `/doctor/billing`, and
`/doctor/prescriptions`. These data surfaces are clinic- and doctor-scoped,
searchable, paginated, responsive, and read/action appropriate. The Doctor
Appointments screen now supports scoped patient selection, creation,
rescheduling, and cancellation; creation/rescheduling validate configured
doctor schedules, leave, and duplicate time conflicts. Prescription records
show full doctor-authored item details and provide a print action. Video
consultation, notifications, profile, and a server-backed download remain
planned because no approved Doctor provider/service routes exist. Billing
mutations remain outside this Doctor-only pass; existing Admin workflows remain
the owning implementation.

### Admin menu coverage — 2026-08-30

The Admin sidebar now groups the requested destinations under Operations,
Access Control, Masters, Settings, and System. Existing clinic, doctor,
patient, appointment, billing, audit, access, and medicine routes remain
permission-aware. Doctor specializations, departments, services, fee and
commission templates, payment modes, tax rates, settings, backup/restore, and
system logs now have configured Admin navigation destinations; fee and
commission templates reuse their existing doctor-domain routes. The legacy
`?planned=` marker is retained only in a navigation-test comment. Clinic
Management remains present at `/admin/clinics`.

### Clinic Management evidence — 2026-08-30

Status: `Partial / static workflow verified`. The clinic list now has
searching, status filtering, server-side pagination, responsive table controls,
empty-state handling, and detail links. Create and update use the existing
CSRF/AJAX contract with server-side validation, duplicate name/code handling,
safe generic errors, and structured audit events. The existing delete route is
now a status-based deactivation; activation is available through the status
field in the update flow. The detail screen is available at
`/admin/clinics/view/{id}`. Route filters remain `auth` plus the clinic
permissions. Database-backed acceptance, authenticated browser/UAT, and
production policy verification remain outstanding.

### Admin Settings/Masters foundation — 2026-08-30

Status: `Partial / domain-model refactor, migration, seed, and route checks verified`. The former generic controller/model behavior is now resolved through fixed Admin domain model classes for each Master and Settings group, with per-master field schemas; the existing generic tables remain the compatibility storage so existing records are not lost. Existing proper doctor/medicine domain models remain the owning implementations where already present. Routes and responsive views cover the requested keys with `settings.manage`
authorization, clinic scoping, server-side pagination, filters, validation,
safe errors, CSRF fields, and audit events. Migrations
`2026-08-30-130000_CreateAdminMasterSettings` and
`2026-08-30-131000_AddAdminConfigurationForeignKeys` are applied locally;
`php spark db:table` confirms both tables. The idempotent
`AdminConfigurationSeeder` provides defaults for all seven master types and
ten settings groups; running it twice leaves one global copy of each default.
System Logs is a read-only, scoped, searchable, server-paginated audit viewer.
Backup/Restore is limited to non-mutating readiness checks and release
guidance; no restore operation is claimed. Authenticated browser CRUD and
responsive UAT remain unverified. Encrypted backup execution, secret-provider
onboarding, and production recovery verification remain deployment blockers.

### Shared UI foundation evidence — 2026-08-31

Status: `Shared shell updated / static coverage verified`. The Admin, Doctor,
Patient, and Pharmacy layouts all inherit `templates/theme1`; shared styling
now provides the Valex-inspired dark sidebar, light workspace, compact topbar,
cards, tables, forms, badges, modals, focus states, and responsive desktop,
tablet, and mobile breakpoints. DataTables CSS, JavaScript, initializers, and
legacy table classes were removed from the application; queue and report
responses render through lightweight DOM updates while existing server/query
contracts remain unchanged. Focused navigation, patient/doctor workflow tests,
PHP lint, route listing, and `git diff --check` passed. Authenticated visual
UAT remains unverified because no signed-in local browser session was
available; the full suite still has two unrelated SQLite-extension errors.

### Doctor, Patient, and Pharmacy UI rollout — 2026-09-01

Status: `Partial / static coverage verified`. All three module layouts continue to
inherit `templates/theme1` and use the shared Valex-inspired workspace treatment.
Doctor and Pharmacy dashboards now use consistent page context, responsive
statistic cards, action links, and shared topbar controls. The Patient dashboard
uses the same accessible page heading and secure-portal context. Existing routes,
permissions, clinic scope, CSRF, searchable selects, pagination, and workflow
endpoints were preserved. No DataTables usage remains in these panels.

Static PHP lint, route listing, focused workflow/navigation tests, and diff checks
pass. Authenticated browser and responsive visual UAT remain `UNVERIFIED` because
no local signed-in browser session was available. JavaScript CLI syntax validation
is pending because the local runtime has no `node` executable.

### Doctor, Patient, and Pharmacy UI completion pass — 2026-09-01

Status: `Partial / static verification complete`. Remaining inconsistent pages were
aligned with the shared UI foundation: Doctor appointment details, Patient portal,
and Pharmacy prescription review now use accessible page context, responsive
action areas, scoped/status badges, and shared empty-state treatment. Existing
routes, permissions, clinic scope, CSRF, searchable controls, pagination, and
workflow behavior were not changed. No DataTables usage remains in these panels.

Authenticated browser UAT and responsive visual inspection remain `UNVERIFIED`
because no signed-in local browser session was available. JavaScript CLI syntax
validation remains pending because Node.js is not installed in the local runtime.

### Patient and Pharmacy list workflow UI — 2026-09-01

Status: `Partial / static and focused workflow verification complete`. Patient portal
records now use clinic- and patient-scoped server-side pagination for appointments,
visits, prescriptions, invoices, and private documents, with preserved search/filter
query state and an accessible filter modal. Pharmacy prescription review and order
lists now use server-side pagination, searchable filters, status controls, and
responsive pagination actions. Existing CSRF, permission, audit, and write workflow
contracts were preserved; no DataTables usage was introduced.

Authenticated browser UAT and responsive visual inspection remain `UNVERIFIED`.
Provider-backed notification, payment, delivery, and external integration behavior
remains deployment/configuration dependent.

### Patient notifications and Pharmacy audit trail — 2026-09-01

Status: `Partial / code and static verification complete`. Patient notifications now
have a dedicated clinic/patient-scoped read model, list/read routes, unread/read
state, server-side pagination, search/status filters, accessible filter UI, and
audited read actions. Migration `2026-09-01-100000_CreatePatientNotifications`
is pending local application; it was not executed in this pass.

Pharmacy now has a permission-protected, clinic-scoped, read-only audit route at
`/pharmacy/audit` with action/status/date/search filters, server-side pagination,
detail modal, metadata icon, and no edit/delete controls. Authenticated browser UAT
and live notification data/provider behavior remain `UNVERIFIED`.

### Verification — Patient notifications and Pharmacy audit — 2026-09-01

`php spark migrate` applied `CreatePatientNotifications` successfully. The local
MySQL schema confirms the notification table, composite indexes
`patient_id_status_created_at` and `clinic_id_created_at`, and foreign keys to
`patients.id` and `clinics.id`. Two idempotent `LOCAL UAT` notification records
(unread/read) were created for local verification only. Focused tests, PHP lint,
route listing, DataTables scan, and `git diff --check` passed. Authenticated browser
UAT of list/read actions, patient isolation in a live session, Pharmacy detail
modal rendering, and responsive behavior remains `UNVERIFIED`.

### Patient location fields — 2026-09-01

Status: `Partial / local schema and static verification complete`. Admin patient
registration and create/edit forms support Country, State/Division, City, and
Locality with searchable selectors and manual fallback. Existing hierarchical
lookups are reused; normalized missing values are created in parent scope and
stored by ID. The Doctor patient create/update API accepts the same location
contract. Migration `2026-09-01-140000_AddPatientLocationLocalities` is applied
to the local `rootins` database in batch 24. Authenticated browser UAT and the
Doctor form UI remain `UNVERIFIED`.

### Complete project review evidence — 2026-09-02

Status: `Static/runtime review complete; release readiness remains PARTIAL`.
The review verified that the currently registered module routes load through
the explicit route table, all application PHP files pass syntax lint, all
listed migrations are applied locally, and no application initializer uses
JavaScript DataTables. The shared datepicker converts native date controls at
runtime to `dd-mm-yyyy` and submits the hidden database value as `yyyy-mm-dd`.

A historical log entry identified the Doctor patient-update defect as blank
optional location values reaching `LocationService::normalize()`. The current
guard returns `null` for blank location values before normalization, and the
Doctor update flow now preserves existing optional values; no additional code
change was made during this review because the confirmed fix is already in the
working tree.

Verification performed: `php spark migrate:status` (no pending migrations),
`php spark routes`, PHP lint across `app/`, full PHPUnit (`62 tests; 327
assertions; 2 environment errors and 4 skips`), and `git diff --check`.
The two PHPUnit errors are caused by the unavailable `sqlite3` PHP extension
in the test environment. Authenticated browser/UAT and responsive visual
verification remain `UNVERIFIED`; Node.js is unavailable for CLI JavaScript
syntax checking. Source-level legacy date placeholders remain in three dense
legacy views, but the shared initializer overrides them before interaction.
Production provider onboarding, legal/compliance sign-off, backup/restore
execution, and live notification/payment/delivery configuration remain
deployment blockers.

### Shared patient creation and Doctor workflow plan — 2026-09-02

Implementation sequence:

1. Reuse `Admin\\Services\\PatientRegistrationService` as the single
   transaction boundary for Admin, Doctor, import, and API patient creation.
2. Add source attribution and normalized appointment-service storage through a
   reversible migration.
3. Validate clinic/doctor scope, duplicate mobile identity, and appointment
   conflicts before committing an optional appointment.
4. Expose the approved external API only when `ROOTINS_API_TOKEN` is configured;
   keep rate limiting, safe responses, audit logging, and provider credentials
   outside source control.
5. Add focused contract tests, then complete authenticated UAT and provider
   deployment checks.

The shared service now supports source types `admin`, `doctor`, `api`,
`patient`, and `import`, records source user/reference metadata, rejects active
duplicate mobile identities within a clinic, and creates an optional
appointment atomically with the patient. The Doctor create flow records
`doctor` attribution. The explicit `POST /api/v1/patients` boundary accepts
the same patient contract and optional appointment payload, with a bearer
token from environment configuration and a 60-request/minute cache limit.

Migration `2026-09-02-110000_AddPatientSourceAndAppointmentService` is applied
locally in batch 26. Existing Admin/Doctor UI workflows and location/mobile
contracts remain in place. API integration tests with an approved external
platform, authenticated browser UAT, and final source-data backfill review
remain outstanding.

### Dashboard modernization evidence — 2026-09-02

Status: `Implemented for the reviewed dashboard presentation; authenticated
visual UAT remains UNVERIFIED`. The Admin dashboard no longer uses demo values
or fake appointment/follow-up rows: its KPI cards query live clinics, doctors,
patients, today’s appointments, finalized invoice revenue, paid invoices,
medicine orders, and scoped audit alerts. Doctor cards use the authenticated
doctor and clinic scope for appointments, consultations, prescriptions,
patients, follow-ups, and invoice visibility. The Patient portal exposes live
summary counts including unread patient notifications. Pharmacy adds a
clinic-scoped audit-alert KPI to its existing review/order/delivery dashboard.

All four dashboard views retain the shared page context, responsive card
layout, accessible labels, role-specific links, and server-side data access;
no DataTables or business-workflow changes were introduced. Focused
Navigation/Doctor/Patient tests passed after the dashboard changes. Browser
visual checks at authenticated desktop/tablet/mobile widths remain
UNVERIFIED because no signed-in local browser session is available.

### Doctor New Patient modal — 2026-09-02

Status: `Implemented / static verification passed`. The Doctor My Patients
screen now exposes a visible `New Patient` action and accessible scroll-safe
modal. The modal contains personal/contact fields, shared searchable location
and country-code controls, `source_type=doctor`, and an opt-in
`Create appointment now` section. Appointment creation uses the shared
registration transaction and preserves clinic scope, duplicate handling,
conflict checks, CSRF, permissions, audit behavior, and safe responses.

Focused Patient/Doctor/Navigation tests passed (34 tests, 249 assertions),
changed PHP lint passed, route listing confirmed the Doctor patient routes,
and `git diff --check` passed. Browser UAT and JavaScript CLI syntax checking
remain unverified because no authenticated session or Node.js runtime is
available.

### Doctor service-selector autoload fix — 2026-09-02

The Doctor My Patients page previously imported
`Admin\\Models\\Configuration\\ServiceModel`, but that class is declared in
the aggregate `MasterModels.php` file rather than an autoloadable
`ServiceModel.php` path. The page therefore failed before rendering. The
controller now uses the existing PSR-4-loadable `AdminMasterRecordModel` and
explicitly scopes `master_type=services` plus clinic/global records. No
duplicate model was added.

### Doctor appointment patient search and multi-service selection — 2026-09-08

Status: `Partial / static and focused verification passed`. The Doctor
appointment create modal no longer embeds the full patient dataset. It loads
patients through the scoped `GET /doctor/appointments/patients/search` endpoint
with debounced search, pagination, stable newest-first ordering, and result
labels containing patient name, mobile, and ID. Appointment creation accepts
one or more in-scope active services through `service_ids[]`, preserves the
legacy service-name snapshot, and records normalized selections in the new
`appointment_services` relation within the appointment transaction. Existing
conflict, permission, clinic-scope, CSRF, audit, and safe-error behavior is
preserved.

Migration `2026-09-08-120000_CreateAppointmentServices` is applied to the local
database. Focused Doctor tests pass (28 tests, 455 assertions), changed PHP
lint passes, the Doctor appointment routes are registered, and the inline
appointment JavaScript parses successfully. Authenticated browser/network UAT,
live availability data, and production provider configuration remain
unverified; no live provider calls are made by this change.

### Doctor Clinical Workspace completion pass — 2026-09-08

Status: `Partial / static and focused verification passed`. The workspace now
uses the scoped live patient autocomplete route, shows patient results without
embedding the patient register, exposes appointment/patient/prescription/
follow-up/billing/video links, validates and normalizes follow-up dates as
`dd-mm-yyyy`, renders field-level follow-up/diagnosis/treatment errors, and
offers a valid `in_consultation` queue transition before completion. Completing
a consultation now marks its in-scope appointment completed in the same
transaction, preserving audit and clinic-scope checks.

Existing consultation notes, prescription draft/submission, today queue,
history, availability, billing visibility, notifications, and provider-neutral
video boundary remain available. Attachments, consent/recording metadata, live
video tokens, and authenticated browser UAT remain `UNVERIFIED` or
`NEEDS_REVIEW` because no approved storage/video contract or signed-in browser
session is available; no fake recording or provider behavior was added.

### Doctor Clinical Workspace tabs and context — 2026-09-08

Status: `Partial / static and focused verification passed`. The clinical page
now has a compact appointment context bar and seven deep-linkable workflow
sections: Overview, Consultation, Prescription, Follow-up, Video consultation,
Billing, and History and documents. Queue and today’s appointments are owned
by Overview; patient search and the selected patient summary remain compact in
the context bar. Desktop uses a vertical left workflow rail and a right active
content pane; smaller screens collapse the rail into a horizontally scrollable
tab strip. Only the active pane is visible; the selected appointment is carried in the
`appointment_id` query parameter and the active section in the URL hash, so
reload, deep links, and browser navigation retain context. Consultation and
prescription forms are rendered only in their dedicated panes rather than
repeated under every queue row.

The tab strip is keyboard-navigable and horizontally scrollable on small
screens. Existing scoped routes, permissions, CSRF-protected AJAX actions,
server-side appointment/history pagination, patient autocomplete, audit calls,
and safe provider-neutral video/billing links remain unchanged. Static and
focused verification passed; authenticated browser UAT, live video consent or
recording behavior, and attachment/vitals support remain `UNVERIFIED` or
`NEEDS_REVIEW`.

### Doctor Clinical Workspace progressive stepper — 2026-09-08

Status: `Partial / static and focused verification passed`. The workspace now
guides the selected appointment through Patient Summary, Consultation,
Prescription, Follow-up, Video, Billing, and History/Documents using a visible
progress stepper. Queue and today’s appointments are retained in a collapsed
Overview drawer, keeping the appointment flow available without competing with
the active clinical step. Prescription and follow-up steps are unavailable
until the selected consultation is completed; video is unavailable for
non-video appointments. Save Draft, Complete, Cancel, Back, and Next controls
reuse the existing AJAX endpoints and scope/permission contracts.

The selected context remains above the stepper, legacy `#overview` links map to
Patient Summary, and URL hash navigation remains deep-linkable. Static DOM,
JavaScript, PHP lint, route, focused test, and diff checks passed. Browser UAT,
live provider behavior, attachments, and vitals remain `UNVERIFIED`.

### Doctor modal-first UX rule — 2026-09-08

Status: `Partial / static verification passed`. Doctor patient create/edit,
appointment create/reschedule/filter, follow-up create, notification filter, and
prescription detail preview use the shared `ri-scroll-modal` shell with a
scrollable body and visible header/footer actions. `RootinsModal` centralizes
initial focus, keyboard focus trapping, focus restoration, and background scroll
lock for dynamically opened Bootstrap modals. Prescription details now open in a
same-page modal instead of an inline expanding block.

The Clinical Workspace remains a dedicated workflow exception because it contains
multi-step consultation state. Full patient and appointment history, video
provider entry, and other complex record workflows remain dedicated-page
exceptions until an authorized lazy-detail endpoint and browser evidence are
available. Static PHP/JavaScript/DOM checks and focused Doctor tests are the
current evidence; authenticated responsive browser UAT and live provider behavior
remain `UNVERIFIED`.

### Doctor Clinical Workspace OPD picker and prefilled consultation context — 2026-09-09

Status: `Partial / static and focused verification passed`. Overview now opens
an accessible searchable `Today’s OPD / Queue` modal. Its appointment search is
server-side, debounced, clinic/doctor scoped, permission protected, keyboard
navigable, and stale-request safe. Selecting a result carries the appointment
context into `/doctor/clinical`, where the compact context bar and active-pane
workflow remain available without embedding the full appointment dataset.

The selected appointment query preloads the existing scoped consultation and
prescription/item data. Consultation fields prefill chief complaints, clinical
notes, diagnosis, advice/treatment plan, diagnostic tests, diet plan, and follow-up
date; prescription fields prefill the first existing prescription item where
available. The workflow now exposes an appointment-setup panel plus nested,
keyboard-navigable consultation tabs for the consultation form, chief complaints,
diagnosis, advice, diagnostic tests, diet plan, and follow-up setup. Queue actions
remain status- and permission-aware and use the existing
audited transition endpoint.

The new consultation fields require the reversible migration
`2026-09-09-120000_AddOpdConsultationFields`; it was applied successfully to the
local database during this pass and migration status is clean. Attachments,
vitals, live video consent/tokens/recording metadata, and authenticated browser
UAT remain `UNVERIFIED` or `NEEDS_REVIEW`; no live provider calls or fake recording
behavior were added.

### Doctor Clinical Workspace OPD layout and unified save — 2026-09-09

Status: `Partial / static and focused verification passed`. The Doctor workspace
follows the OPD composition: the topbar exposes patient search, notifications, and
profile; the page header keeps the `OPD Consultation` context and `Today’s OPD`
queue action; and the quick-action toolbar exposes only verified past-visit,
consultation, prescription, and payment-history routes. Unsupported payment-entry
and image routes were not invented.

The main workspace uses a responsive two-column composition. The left rail contains
Patient Info, patient identifier/age/location/appointment data, BP/Pulse cards, and
Visit Timeline. The right rail keeps the consultation, prescription, follow-up,
appointment, video, billing, and history panels visible in the OPD order, with teal
headers and responsive consultation cards. The queue modal is outside the workspace
scroll container, so its modal scrolling and backdrop behavior remain independent.

Consultation and prescription fields now share one `#clinical-workspace-form` with
one sticky `Save Consultation` action. The new `POST /doctor/clinical/save` endpoint
validates the scoped appointment, consultation fields, optional prescription items,
status transitions, permissions, and finalized-record protections before one database
transaction. A failure rolls back the combined write; successful saves emit a scoped
audit event. The former section-level save buttons and Clinical Workspace AJAX
handlers are no longer used; legacy endpoints remain for backward compatibility with
other callers.

Static DOM/CSS/PHP checks, route checks, JavaScript syntax checks, and focused Doctor
tests passed. Authenticated browser rendering at desktop/tablet/mobile widths and
database-backed transaction/UAT evidence remain `UNVERIFIED` in this environment.

### Doctor Prescription database-backed option catalog — 2026-09-10

Status: `Implemented / static and focused verification passed`. Doctor
Prescription duration, frequency, dose, intake, and status values load from
dedicated database catalogs through `Admin\\Models\\PrescriptionOptionModel`;
medicine remains an AJAX-backed active `medicines` lookup. The earlier
`2026-09-10-160000_SeedPrescriptionOptions` migration remains the historical
legacy seed; `2026-09-11-120000_RefactorAdminMasterRecords` backfills those
values into the dedicated tables without deleting the legacy source.

Submitted option values are checked against active, clinic/global-scope records
before the consultation/prescription transaction. Medicine IDs are checked
against active medicine records and resolved to the historical `medicine_name`
snapshot, preserving the existing downstream prescription schema and history.
Dynamic rows initialize the same database-backed selectors and the Doctor view
contains no hard-coded prescription option arrays. Migration status, PHP/JS
lint, route listing, focused Doctor tests, and `git diff --check` passed; browser
UAT remains deployment/environment dependent.

### Admin dedicated master catalogs — 2026-09-11

Status: `Implemented / migration, backfill, seed, and focused verification passed`.

The legacy `admin_master_records` table is retained for compatibility and audit
reconciliation, but current Admin master CRUD and Doctor service/option reads no
longer use it. The dedicated mapping is:

| Master | Dedicated table |
| --- | --- |
| Doctor Specializations | `doctor_specializations` |
| Departments | `admin_departments` |
| Services | `admin_services` |
| Consultation Fee Templates | `consultation_fee_templates` |
| Commission Templates | `commission_templates` |
| Payment Modes | `payment_modes` |
| Tax Rates | `tax_rates` |
| Medicines | `medicines` (existing domain table) |
| Prescription Durations | `prescription_durations` |
| Prescription Frequencies | `prescription_frequencies` |
| Prescription Doses | `prescription_doses` |
| Prescription Intakes | `prescription_intakes` |
| Prescription Statuses | `prescription_status_options` |

Migration `2026-09-11-120000_RefactorAdminMasterRecords` creates the dedicated
catalogs with normalized uniqueness, status and scope indexes, clinic/user
foreign keys, timestamps, and `legacy_master_id` compatibility mapping. It also
updates `appointment_services.service_id` to reference `admin_services` and
backfills existing legacy rows without deleting the legacy source. The migration
and `AdminConfigurationSeeder` are idempotent; local migration status is clean.

`Admin\Models\PrescriptionOptionModel` reads the dedicated prescription tables,
and Admin Configuration uses one PSR-4-loadable model per catalog. Authenticated
browser UAT remains environment-dependent; static checks, focused tests, route
listing, database table inspection, and local migration/seed execution passed.

## Central medicine stock rule

`medicine_inventory_batches` is the centralized stock pool. Its legacy
`clinic_id` is retained nullable for compatibility/reporting only and is never
used to scope availability, FEFO allocation, or cancellation release. Orders,
prescriptions, invoices, and audit events retain clinic ownership.

Migration `2026-10-02-130000_CentralizeMedicineInventory` merges duplicate
rows by medicine, batch, and expiry into the lowest row id, sums each existing
quantity once, and retains zero-quantity `merged` history rows. It then applies
the central uniqueness key. Stock adjustments are recorded in
`inventory_stock_movements` and require the Pharmacy Assistant adjustment permission,
a reason, confirmation, and a non-negative resulting quantity.

Doctors and patients intentionally receive medicine master/order contents only;
central batch, expiry, stock quantity, warehouse, and allocation fields are
not part of their response contracts.
