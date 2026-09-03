# ROOTINS Requirements Register

**Version:** 0.4 baseline  
**Date:** 2026-08-29  
**Priority:** P0 = release-critical, P1 = core release, P2 = later/optional

## Product and access

| ID | Requirement | Priority | Acceptance summary |
|---|---|---:|---|
| CORE-001 | Provide separate Admin, Doctor, Patient, and Farmassist dashboards. | P0 | Authorized users land on the correct dashboard and see only permitted actions/data. |
| CORE-002 | Authenticate users and enforce role, permission, and clinic scope. | P0 | Unauthorized page, AJAX, and record access is denied consistently. |
| CORE-003 | Record audit events for security, clinical, financial, configuration, and status-sensitive actions. | P0 | Event includes actor, action, target, time, scope, and safe metadata. |
| CORE-004 | Enforce the approved appointment lifecycle and reject invalid state transitions. | P0 | Transitions are permission-controlled, timestamped, attributable, and safe against duplicate submission. |
| CORE-005 | Preserve finalized clinical and financial records through controlled amendment/reversal workflows. | P0 | Ordinary users cannot overwrite or erase finalized records. |
| CORE-006 | Prevent duplicate patients, appointments, prescriptions, payments, and integration callbacks where applicable. | P0 | Repeated requests are rejected or resolved idempotently without duplicate business records. |
| CORE-007 | Apply India-baseline consent, purpose limitation, least privilege, secure transfer/storage, and attributable audit controls. | P0 | Processing purpose, access, consent state, and sensitive actions are controlled and auditable. |
| CORE-008 | Preserve clinical and payment records needed for Indian policy, legal, dispute, and operational review. | P0 | Retention/deletion behavior follows an approved schedule and documented exceptions. |
| CORE-009 | Implement the proposed prescription state machine and preserve doctor-authored instructions. | P0 | `draft` → `submitted` → `clarification_requested` → `approved`/`rejected` → `cancelled`/`fulfilled`; amendments are attributable and authority-controlled. |
| CORE-010 | Make retention, deletion, and withdrawal policy configurable and controlled rather than hard-coding a legal retention period. | P0 | Clinical, financial, audit, consent, and communication records follow approved policy and preservation exceptions. |

## Admin module

| ID | Requirement | Priority | Acceptance summary |
|---|---|---:|---|
| ADM-001 | Manage clinics, clinic settings, locations, and operational status. | P0 | Authorized admin can create/update and changes affect scoped workflows. |
| ADM-002 | Manage doctors, documents, specializations, schedules, leave, fees, and commissions. | P0 | Doctor availability and billing rules honor active configuration and conflicts. |
| ADM-003 | Manage patients, registration, transfers, medical history, documents, and visit records. | P0 | Patient timeline and access scope remain consistent after changes. |
| ADM-004 | Manage appointments, calendar, check-in, queue, consultation, and reports. | P0 | Appointment state transitions are validated and visible to relevant roles. |
| ADM-005 | Manage prescriptions, medicines, prescription templates, and PDF/print output. | P0 | Prescription data and item history remain accurate and attributable. |
| ADM-006 | Manage users, roles, permissions, and access assignments. | P0 | Permission changes are guarded and auditable. |
| ADM-007 | Provide operational reports for appointments, visits, prescriptions, revenue, and exceptions. | P1 | Reports are filtered by permitted scope and use defined date/status rules. |

## Doctor module

| ID | Requirement | Priority | Acceptance summary |
|---|---|---:|---|
| DOC-001 | Provide doctor dashboard with schedule, queue, appointments, and alerts. | P0 | Doctor sees actionable current and upcoming work. |
| DOC-002 | View and manage assigned appointments through allowed states. | P0 | Doctor cannot alter unrelated clinic/patient appointments. |
| DOC-003 | Conduct in-person and tele-consultations with notes, findings, reports, and follow-up. | P0 | Completed consultation is linked to the appointment and patient. |
| DOC-004 | Create, revise before finalization, sign/finalize, and view prescriptions. | P0 | Finalized prescriptions are protected and available to authorized fulfilment users. |
| DOC-005 | Configure/view own availability, leave, consultation fees, and commissions as permitted. | P1 | Rules are visible and conflict-safe. |

## Patient module

| ID | Requirement | Priority | Acceptance summary |
|---|---|---:|---|
| PAT-001 | Provide patient profile, consent/contact preferences, and clinic information. | P0 | Patient can update allowed fields and sees verification state. |
| PAT-002 | Book, reschedule, cancel, and view in-person, video, and telephone consultations. | P0 | Booking checks availability, fee, policy, and duplicate conflicts. |
| PAT-003 | Upload a prescription and supporting documents securely. | P0 | File type/size/ownership validation and status tracking are enforced. |
| PAT-004 | View visits, consultations, reports, prescriptions, documents, invoices, and payments. | P0 | Only the patient's permitted records are returned. |
| PAT-005 | Receive appointment, consultation, prescription, billing, and delivery notifications. | P1 | Preferences and delivery status are respected. |
| PAT-006 | Capture and manage consent, withdrawal, communication preferences, and permitted health-record sharing. | P0 | Consent is purpose-bound, attributable, reviewable, and respected by downstream workflows. |

## Farmassist module

| ID | Requirement | Priority | Acceptance summary |
|---|---|---:|---|
| FARM-001 | Show prescription review queue and complete attributable review actions. | P0 | Farmassist can approve, request clarification, or cancel according to policy; each action records a reason where required. |
| FARM-002 | View prescription items, medicine master data, availability, substitutions policy, and exceptions. | P0 | Changes cannot silently alter the doctor's clinical instruction. |
| FARM-003 | Create/manage medicine fulfilment billing and order status. | P0 | Charges, payment state, and fulfilment state are linked and auditable. |
| FARM-004 | Integrate or reconcile external commerce/delivery status where configured. | P1 | Duplicate/out-of-order callbacks do not duplicate or regress local records. |
| FARM-005 | Apply India-baseline prescription, pharmacy, fulfilment, cancellation, refund, and delivery controls. | P0 | Review, fulfilment, exception, and customer communication actions are attributable and policy-controlled. |
| FARM-006 | Track medicine batch/expiry and proposed delivery states. | P0 | Expired/unavailable stock cannot fulfil; substitutions require approval; delivery states and failures are recorded. |

## Masters and settings

| ID | Requirement | Priority | Acceptance summary |
|---|---|---:|---|
| CFG-001 | Maintain specializations, fee/commission templates, departments, services, payment modes, tax rates, and medicines. | P0 | Active/inactive and effective-date rules are enforced by consumers. |
| CFG-002 | Maintain general, clinic, notification, invoice, and prescription settings/templates. | P1 | Versioned or controlled changes do not corrupt historical documents. |
| CFG-003 | Configure email SMTP, SMS, and WhatsApp providers without exposing secrets. | P1 | Test/failure status is safe and actionable. |
| CFG-004 | Provide backup/restore controls and system logs with restricted access. | P1 | Backup integrity and restore procedure are verified before release. |
| CFG-005 | Configure Cashfree and Razorpay as approved candidate payment providers without exposing credentials. | P0 | Provider credentials remain externalized; sandbox/production enablement requires review and reconciliation tests. |
| CFG-006 | Keep the first release ABDM/ABHA integration-ready but out of scope unless separately approved. | P1 | No unapproved ABDM/ABHA processing is enabled; extension points do not weaken privacy controls. |

## Non-functional requirements

| ID | Requirement | Priority | Acceptance summary |
|---|---|---:|---|
| NFR-001 | Apply CSRF, secure sessions, validation, file controls, scoped queries, and safe logging. | P0 | Security checks pass for all new writes and reads. |
| NFR-002 | Use migrations, transactions, idempotency, and reconciliation for business-critical writes. | P0 | Failure tests leave no unintended partial workflow. |
| NFR-003 | Provide focused tests and documentation for each released workflow. | P0 | CI/test and review evidence exists for the changed requirement IDs. |
| NFR-004 | Keep normal interactive requests responsive under the agreed baseline load. | P1 | Initial target is a 2-second response time, excluding third-party delays; reports are paginated or asynchronous where needed. |
| NFR-005 | Provide backup, restore verification, monitoring, and operational recovery procedures. | P1 | Recovery-point and recovery-time targets are approved and demonstrated before production. |
| NFR-006 | Support Indian-standard payment failure, refund, cancellation, dispute, settlement, reconciliation, and idempotency controls. | P0 | Local financial state never reports false success and matches provider reconciliation evidence. |
| NFR-007 | Encrypt backups and restrict access to health, identity, payment, and audit data in storage and transit. | P0 | Encryption, access, key ownership, restore testing, and transfer controls are verified before release. |
| NFR-008 | Apply the mandatory shared UI/UX standard across all four panels. | P0 | Screens use consistent shared components, responsive layouts, accessible interactions, searchable large-data controls, pagination/server-side search where needed, and explicit loading/empty/error/success states. |

## Requirement lifecycle and traceability

Each requirement must have one of these implementation states: `Planned`, `In progress`, `Implemented`, `Verified`, or `Deferred`. A requirement is complete only when its route/UI or API behavior, authorization, validation, persistence, failure handling, tests, and documentation are verified. Development tasks, migrations, tests, and review notes should reference the requirement ID.

Requirement changes must update this register, `docs/SRS.md`, and—when architecture or scope changes—`docs/DECISIONS.md` and `docs/MODULE-STATUS.md`.

## UI/UX acceptance traceability

`NFR-008` applies to every `CORE`, `ADM`, `DOC`, `PAT`, `FARM`, and `CFG` workflow with a user interface. Evidence must cover shared layout/navigation, responsive behavior, accessibility, searchable/filterable large-data selection, pagination or server-side search, validation, safe errors, loading/empty/success states, permission and clinic scope, and duplicate-submit handling. A workflow without this evidence cannot be marked `Verified`.

## Baseline classifications

| Classification | Requirement baseline |
|---|---|
| `DECIDED BASELINE` | India is the initial jurisdiction; Cashfree and Razorpay are approved candidate providers; Indian privacy, health-data, telemedicine, payment, tax, pharmacy/drug, consumer-protection, and e-commerce requirements are review inputs. |
| `PROPOSED` | Farmassist is an internal clinic/platform pharmacy/fulfilment role; external legal-entity support is out of scope for the baseline. |
| `PROPOSED` | Prescription states are `draft` → `submitted` → `clarification_requested` → `approved`/`rejected` → `cancelled`/`fulfilled`; doctor-authored medicine, dosage, duration, and instructions are preserved. |
| `PROPOSED` | Inventory batches and expiry are tracked; expired/unavailable stock cannot fulfil; substitutions require approval; delivery states are `pending`, `packed`, `dispatched`, `out_for_delivery`, `delivered`, `failed`, and `returned`. |
| `PROPOSED` | Backups are encrypted, daily-verified, and periodically restore-tested; initial RPO 24 hours/RTO 8 hours is proposed only. |
| `PROPOSED` | ABDM/ABHA integration is out of scope for the first release but the design remains integration-ready. |
| `DECIDED BASELINE` | Retention, deletion, and withdrawal are policy-controlled and configurable; no legal period is hard-coded before review, with controlled preservation for audit, financial, and clinically required records. |

## Open requirement decisions and reviews

- `[NEEDS_REVIEW]` India deployment privacy/medical-record rules, compliance mapping, and applicable legal obligations under DPDP/ABDM/telemedicine/RBI/tax/pharmacy/consumer requirements.
- `[NEEDS_DECISION]` Patient identity verification, duplicate detection, and controlled merge policy.
- `[NEEDS_REVIEW]` Cashfree/Razorpay production configuration, merchant onboarding, contracts, credentials/key ownership, settlement, disputes, refunds, reconciliation, grievance ownership, and production enablement.
- `[NEEDS_REVIEW]` Deployment readiness for Twilio Video, Twilio Messaging/WhatsApp, Twilio SendGrid, internal ROOTINS pharmacy, and Shiprocket, including contracts, credentials, configuration, consent/template/DLT, serviceability, webhooks, reconciliation, and final compliance review.
- `[NEEDS_REVIEW]` Legal/medical confirmation of Farmassist authority and proposed prescription states/reversal/amendment authority.
- `[NEEDS_REVIEW]` Exact pharmacy/drug, inventory, batch/expiry, substitution, delivery, returns, cancellation, refund, and customer-support rules.
- `[NEEDS_REVIEW]` Notification consent, opt-out, template, retry, and sensitive-content rules.
- `[NEEDS_REVIEW]` Configurable retention periods, deletion/withdrawal exceptions, backup encryption/key ownership, restore frequency, and final recovery targets.
- `[NEEDS_REVIEW]` Exact dashboard KPI/report definitions, owners, filters, targets, and performance baseline load.

## Owner baseline extensions

| ID | Requirement | Priority | Acceptance summary |
|---|---|---:|---|
| CORE-011 | Keep ABDM/ABHA integration-ready while excluding ABHA linking/exchange from the first release. | P1 | No ABDM/ABHA data exchange is enabled before sandbox, security, consent, and compliance readiness. |
| DOC-006 | Support Twilio Video with room-scoped short-lived tokens and no recording by default. | P1 | Access is limited to authorized participants, consent is recorded, and session access is auditable. |
| PAT-007 | Support Twilio Messaging/WhatsApp notifications with Indian sender/template/DLT and consent/opt-out controls. | P1 | Notifications respect consent and provider delivery requirements; secrets are externalized. |
| ADM-008 | Support Twilio SendGrid email through a provider abstraction. | P1 | SMTP/API secrets and deliverability settings are deployment-controlled and never exposed. |
| FARM-007 | Operate internal ROOTINS pharmacy/inventory/fulfilment for the first release and defer external commerce synchronization. | P0 | Inventory and fulfilment remain provider-neutral; external commerce is not enabled in release one. |
| FARM-008 | Integrate Shiprocket through a shipping adapter for delivery workflows. | P1 | Serviceability, labels, webhooks, reconciliation, failures, and returns are recorded safely. |
