# ROOTINS Module Status

**Version:** 0.4 baseline  
**As of:** 2026-08-29  
**Status vocabulary:** Implemented = repository has a usable routed flow verified against acceptance evidence; Partial = meaningful groundwork exists but the complete role workflow is not present; Missing = no verified implementation was found; Unverified = evidence exists but required verification is incomplete; `PROPOSED` = owner-directed baseline not yet approved as final policy or verified in code; `NEEDS_REVIEW` = requires legal, medical, security, or operational review; `NEEDS_DECISION` = blocked by an unresolved business, provider, or compliance decision.

## Summary

| Module | Current status | Evidence observed | Linked requirements | Main next step |
|---|---|---|---|---|
| Admin | Partial | Dashboard, clinics, doctors, schedules, leave, fees, commissions, documents, patients, appointments, consultations, prescriptions, and medicines have Admin controllers/views/routes/models/migrations in varying completeness. | ADM-001 → ADM-007 | Complete end-to-end acceptance, permissions, tests, billing, settings, audit, and reports. |
| Doctor | Missing | Module route file exists but is empty; doctor domain models exist under the Admin/Doctor area, with no verified Doctor module controller/view workflow. | DOC-001 → DOC-005 | Define doctor scope/permissions and build dashboard → appointment → consultation → prescription flow. |
| Patient | Missing | Module route file exists but is empty; patient behavior currently appears represented by Admin patient management and patient-related migrations/models. | PAT-001 → PAT-005 | Build patient authentication/dashboard, booking, secure uploads, visit timeline, reports, prescriptions, and billing views. |
| Farmassist | Missing | Module route file exists but is empty; no verified Farmassist controller/view workflow was found. | FARM-001 → FARM-004 | Define review and fulfilment state machine, medicine/order ownership, billing, and integration boundaries. |

## Cross-cutting status

| Area | Status | Notes |
|---|---|---|
| Authentication/session | Partial | Custom authentication and session conventions exist; role-specific module access still needs completion and verification. |
| Authorization | Partial | Auth/permission filters and Admin permission usage exist; complete permission matrix and audit coverage are required. |
| Database foundation | Partial / active | Clinics, users/roles/permissions, doctors, schedules, leaves, fees, commissions, documents, patients, appointments, consultations, prescriptions, prescription items, and medicines have migrations. |
| Billing/payments | Missing | Requirements describe the domain, but a complete invoice/payment/refund implementation is not verified in the current tree. |
| Audit logs | Missing | Audit menu text exists, but no audit-log controller, model, migration, service, or route was found. |
| Notifications | Missing | SMTP/SMS/WhatsApp/settings are requirements; providers and delivery tracking are not verified. |
| Backup/restore and system logs | Missing | No verified implementation; encryption, retention, and recovery verification remain required. |
| Tests | Partial | CI4 starter test structure exists; feature-specific coverage is still required for implemented Admin workflows. |
| Performance | Unverified | Pagination/filtering and query efficiency are required; response-time and N+1 behavior were not verified. |
| Compliance | NEEDS_REVIEW | India is the baseline jurisdiction; legal applicability, control mapping, and release evidence remain to be reviewed. |

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

| Farmassist role | PROPOSED | Baseline is an internal clinic/platform pharmacy/fulfilment role; external legal-entity support is out of scope until separately reviewed. |
| Prescription state machine | PROPOSED | Proposed states are documented; exact semantics, authority, and implementation are not verified. |
| Inventory/delivery baseline | NEEDS_REVIEW | Proposed batch/expiry and delivery states are documented; operational, legal, and implementation evidence is missing. |
| ABDM/ABHA | PROPOSED | Out of scope for first release but integration-ready; no integration is verified. |
| Telehealth | PROPOSED | Twilio Video is the first provider; room-scoped short-lived tokens, no recording by default, consent, and audit are required. No integration is verified. |
| SMS/WhatsApp | PROPOSED | Twilio Messaging/WhatsApp is the first provider; sender/template/DLT and consent/opt-out setup are not verified. |
| Email | PROPOSED | Twilio SendGrid is the first provider; secrets, configuration, and deliverability setup are not verified. |
| Pharmacy | PROPOSED | Internal ROOTINS pharmacy/inventory/fulfilment is first-release scope; external pharmacy/commerce integration is deferred. |
| Shipping | PROPOSED | Shiprocket is the first adapter target; credentials, serviceability, labels, webhooks, reconciliation, and returns are not verified. |
| Commerce synchronization | Missing | External commerce synchronization is out of scope for the first release; adapter-ready only. |

## First Admin vertical slice status

**Overall state: Partial / not release-ready.** The repository contains evidence for an Admin-side flow from authentication and permissions through clinic, doctor, patient, appointment, consultation, and prescription areas, but the complete slice has not been verified against its acceptance criteria.

| Slice area | Evidence observed | State | Limitation |
|---|---|---|---|
| Admin authentication | Login route/controller, `AuthService`, session contract, `Auth` filter, and an `auth` filter on the Admin dashboard route exist. | Partial | End-to-end login, logout, session expiry, and negative-path tests were not verified. Dashboard-specific permission policy is not defined. |
| Admin permissions | Permission filter, base-controller checks, permission seed data, and route-level permission filters exist for several areas. | Partial | Complete permission matrix and clinic/data scope tests are not verified. Clinic/doctor routes have no visible route-level auth/permission filters; clinic/doctor controllers use `BaseAdminController`, which needs explicit security regression verification. |
| Clinic management | Admin clinic controller, model, views, explicit CRUD routes, and clinic migration exist. | Unverified | Requires focused validation, authorization, and regression tests before release classification. |
| Doctor management | Admin doctor controller, related models/views, schedules, leave, fees, commissions, documents, routes, and migrations exist. | Unverified | Full workflow and conflict/security tests are not verified. |
| Patient management | Admin patient controller, registration, medical history, documents, views, routes, services, models, and migrations exist. | Unverified | Patient self-service is missing; Admin flow still needs acceptance and isolation tests. |
| Appointment management | Admin appointment controller, calendar, queue, reports, views, routes, model, and migrations exist. | Unverified | State-transition, race-condition, and booking-conflict tests are not verified. |
| Consultation management | Admin consultation controller, views, routes, model, and migration exist. | Unverified | Clinical completion, authorization, and prescription linkage tests are not verified. |
| Prescription management | Admin prescription controller, views, PDF service/routes, models, item model, and migrations exist. | Unverified | Finalization/history, review/fulfilment, and downstream billing are not verified. |
| Audit logging | Audit menu label exists, but no audit-log controller, model, migration, or service was found. | Missing | Required before the vertical slice can be release-ready. |

### Verification limitations

- The inspected test suite contains CI4 starter tests only; feature-specific tests for this slice were not found.
- No full PHPUnit execution, static-analysis, performance, authorization regression, or browser workflow verification was performed during this documentation reconciliation; only PHPUnit test discovery was run.
- `Unverified` means repository artifacts and explicit routes were found; it does not mean the feature passed end-to-end acceptance.
- Route output must be reviewed for every protected Admin entry point; controller inheritance alone is not sufficient evidence of route-level authorization coverage. The Admin dashboard route now shows `csrf auth` after this milestone.
- Billing, notifications, external integrations, backup/restore, compliance controls, and operational audit remain outside the verified slice.

### Verification performed

- Read-only inspection of Git status, module route files, Admin routes, controllers, models, services, views, migrations, filters, and test files.
- Confirmed that `app/Config/Routes.php` dynamically loads module route files.
- Confirmed explicit Admin routes for clinics, doctors, patients, appointments, consultations, prescriptions, and medicines.
- Confirmed authentication/session and permission-filter evidence in the application; confirmed no verified audit-log implementation in the inspected tree.
- `php spark routes` completed successfully and showed explicit Admin routes; the dashboard now shows route-level `csrf auth` after the authorized milestone, while clinic and doctor entry-route filter coverage remains to be verified.
- PHP lint completed successfully for 191 application PHP files.
- `vendor/bin/phpunit --list-tests` completed successfully with five starter tests; no feature-specific Admin vertical-slice tests were discovered.
- Focused `HealthTest` and `ExampleSessionTest` execution passed: 3 tests, 4 assertions. PHPUnit emitted one non-blocking warning because no code-coverage driver is available.
- Confirmed no application files other than the authorized Admin route change were modified during this milestone; no migrations or tests were changed.

## Verified repository inventory

- CodeIgniter 4 framework constraint is `^4.7` and PHP constraint is `^8.2`.
- Module route files are dynamically loaded by `app/Config/Routes.php`.
- Admin currently contains routed areas for clinics, doctors, doctor schedules/leaves/fees/commissions/documents, patients, patient medical history/documents, appointments, consultations, prescriptions, and medicines.
- Doctor, Patient, and Farmassist route files are currently empty in the inspected working tree.
- The working tree contains unrelated/uncommitted application changes. Documentation work must not overwrite or reset them.

## Release gates by module

### Admin

- Complete the permission matrix and CRUD validation.
- Add appointment/queue transition and consultation/prescription consistency tests.
- Complete medicine rules, billing, audit events, reports, and dashboard KPI definitions.
- Verify pagination, query efficiency, security regression coverage, and requirement traceability.

### Doctor

- Add role-specific routes/controllers/views.
- Enforce doctor-to-clinic and doctor-to-patient scope.
- Support consultation and prescription finalization.
- Test schedule/leave conflicts, authorization, and clinical-record protection.

### Patient

- Add patient authentication and self-service boundaries.
- Implement secure document access and appointment conflict rules.
- Add visit/report timeline, notification preferences, and payment visibility.
- Test that patients cannot access another patient's records.

### Farmassist

- Define the prescription review state machine and approval/cancellation authority.
- Implement medicine availability, fulfilment, and billing ownership rules.
- Add external-order idempotency and exception handling.
- Preserve original doctor instructions and audit all review decisions.

## Status maintenance rule

Update this file when a routed end-to-end workflow is delivered, when a requirement changes state, or when an architectural dependency is discovered. Requirement states are `Planned`, `In progress`, `Implemented`, `Verified`, or `Deferred`. A migration, model, or view alone does not make a module implemented; the intended role must be able to use the complete authorized workflow and its acceptance evidence must exist.

## Exact pending items

- `[NEEDS_REVIEW]` Indian privacy, medical-record, tax, consent, retention, and compliance applicability.
- `[NEEDS_DECISION]` Patient identity verification, duplicate detection, and controlled merge policy.
- `[NEEDS_REVIEW]` Cashfree/Razorpay production configuration, merchant onboarding, settlement, refunds, partial payments, disputes, and cancellation charges.
- `[NEEDS_REVIEW]` Deployment readiness for Twilio Video, Twilio Messaging/WhatsApp, Twilio SendGrid, internal ROOTINS pharmacy, and Shiprocket; Cashfree/Razorpay remain approved candidates pending production review.
- `[NEEDS_REVIEW]` Legal/medical confirmation of the proposed internal Farmassist role and prescription review authority.
- `[NEEDS_REVIEW]` Proposed inventory, batch, expiry, substitution, delivery, and order-reconciliation rules.
- `[NEEDS_REVIEW]` Backup encryption/key ownership, retention, restore frequency, and final recovery targets; RPO 24 hours/RTO 8 hours remain proposed.

## India-specific blockers

- `[NEEDS_REVIEW]` Legal/medical interpretation of the DPDP Act/Rules, ABDM health-data and consent guidance, applicable Indian telemedicine guidance, RBI payment-aggregator requirements, and pharmacy/tax/consumer rules.
- `[NEEDS_DECISION]` Approved consent notices, purposes, withdrawal/deletion behavior, grievance process, and retention schedule.
- `[NEEDS_REVIEW]` Cashfree/Razorpay credential ownership, merchant onboarding, webhook ownership, settlement/reconciliation, refund/dispute process, and production enablement.
- `[NEEDS_REVIEW]` Final Indian pharmacy/delivery operating rules, including substitution, inventory, batch/expiry, returns, cancellations, refunds, and customer support.
- `[NEEDS_REVIEW]` Proposed RPO 24 hours and RTO 8 hours before adoption as service targets.
- `[NEEDS_REVIEW]` Twilio Video, Twilio Messaging/WhatsApp, Twilio SendGrid, and Shiprocket provider contracts, credentials, configuration, consent/template/DLT, serviceability, webhook, and production-readiness details.
- `[NEEDS_DECISION]` Whether and when ABDM/ABHA linking or consent-based exchange should move beyond the proposed first-release exclusion.

## Exact next coding tasks

1. Verify unauthenticated dashboard behavior at runtime and decide/document whether a dashboard-specific permission is required.
2. Verify or add explicit auth/permission coverage for clinic and doctor route groups, preserving existing controller guards and route contracts.
3. Add feature tests for login/session behavior, permission denial, clinic scope, patient isolation, appointment transitions/conflicts, consultation completion, and prescription finalization.
4. Implement audit events and storage for authentication, permissions, clinical, financial, configuration, and status-sensitive actions.
5. Re-run route listing, linting, PHPUnit, static analysis, and targeted security tests after the code changes.
6. Implement the proposed prescription state machine, inventory/expiry controls, substitution approval, delivery states, and controlled amendment/reversal workflow after `NEEDS_REVIEW` decisions.
7. Add provider-abstracted Cashfree/Razorpay payment flows with idempotency, reconciliation, refunds, disputes, and safe failure handling after merchant/configuration review.
8. Implement consent/purpose/retention controls, encrypted backups, restore testing, and proposed KPI/report foundations after policy decisions are approved.
