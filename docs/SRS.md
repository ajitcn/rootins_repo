# ROOTINS Software Requirements Specification

**Version:** 0.4 baseline  
**Date:** 2026-08-29  
**Product:** ROOTINS healthcare consultation and medicine-fulfilment platform

## 1. Purpose and vision

ROOTINS provides one controlled workflow from patient registration and appointment booking through consultation, prescription, billing, and prescription-based medicine delivery. It supports four role-oriented modules, each with its own dashboard, while maintaining a shared operational record.

## 2. Scope

### In scope

- clinic and organization administration;
- doctor onboarding, schedules, leave, fees, commissions, and documents;
- patient registration, identity, medical history, documents, visits, and reports;
- appointment booking, calendar, check-in, waiting queue, consultation, and tele-consultation support;
- prescription creation, upload, review, approval/cancellation, PDF/templates, and fulfilment handoff;
- medicine master and prescription-based order/billing workflow;
- users, roles, permissions, audit logs, notifications, settings, reporting, and operational dashboards;
- integration boundaries for email, SMS, WhatsApp, storage, payments, and commerce.

### Out of scope for the baseline

- autonomous diagnosis or treatment recommendations;
- replacing a jurisdiction's legal medical-record or accounting system without validation;
- production enablement or contractual approval of payment, pharmacy, shipping, telehealth, messaging, or commerce providers;
- production infrastructure changes.

## 3. Actors and access model

| Actor | Main responsibility |
|---|---|
| Super Admin/Admin | Configure clinics, masters, users, access, operations, reporting, and audit |
| Doctor | Manage assigned appointments, consultations, clinical notes, prescriptions, and availability |
| Patient | Maintain profile, upload prescriptions/documents, book and attend visits, view permitted records and bills |
| Farmassist | Review prescription fulfilment, approve/cancel according to policy, manage medicine/billing/order steps |
| System/Integration | Send notifications, process callbacks/webhooks, create audit events, and reconcile external status |

Access is role- and scope-based. A role does not automatically grant access to every clinic or patient record.

### 3.1 Baseline permission model

| Capability | Admin | Doctor | Patient | Farmassist |
|---|---:|---:|---:|---:|
| Manage system configuration and access | Yes | No | No | No |
| Manage clinics, doctors, patients, and masters | Yes | Limited assigned data | Own profile only | Limited fulfilment data |
| View/manage assigned appointments | Yes | Yes | Own appointments | Operational read access |
| Create clinical notes and prescriptions | Controlled/admin support | Yes | Upload only | No |
| Review prescription fulfilment | Yes/oversight | View status | View own status | Yes |
| Manage medicine fulfilment and billing | Oversight | View permitted billing | View/pay own bills | Yes |
| View audit and system logs | Authorized admin | No by default | No | No by default |

The final permission matrix must be implemented as granular `resource.action` permissions and constrained by clinic, doctor, patient, and operational scope. “Yes” indicates capability, not unrestricted access.

## 4. Module requirements at a glance

### Admin dashboard

Show operational KPIs and alerts for clinics, doctors, patients, appointments, queue, consultations, prescriptions, medicines, billing, failed notifications, and audit/security events. Provide navigation to all authorized administration areas.

### Doctor dashboard

Show today's schedule, queue, upcoming appointments, pending consultations, prescription drafts/follow-ups, availability, fees/earnings where permitted, and actionable alerts. Doctor actions must be limited to assigned or authorized patients and clinics.

### Patient dashboard

Show upcoming and past appointments, visit timeline, consultation reports, prescriptions, uploaded documents, bills/payments, notifications, and profile/clinic information. Support appointment booking for available in-person, video, and telephone/tele-consultation services.

### Farmassist dashboard

Show prescriptions awaiting review, approved/cancelled items, medicine availability, fulfilment/order status, billing work, exceptions, and communication tasks. Preserve doctor prescription content and require explicit, attributable actions for review outcomes.

For the baseline, Farmassist is a `[PROPOSED]` internal pharmacy/fulfilment role operated by the clinic/platform owner. External legal-entity support is out of scope for the baseline and would require separately approved contracts, scope, and adapters.

## 5. Core workflows

### 5.1 Registration and clinic assignment

An authorized user creates or imports a patient, verifies required identity/contact information, assigns an allowed clinic, and records registration/transfer history. Duplicate detection and controlled merge handling are required before production.

### 5.2 Appointment and consultation

The user selects a clinic, service, doctor, date/time, and consultation mode. The system checks doctor availability, leave, fee rules, conflicts, clinic scope, and booking policy. The appointment moves through configured states such as requested, confirmed, checked-in, queued, in-consultation, completed, cancelled, or no-show. A completed consultation may produce reports and a prescription.

#### Appointment state rules

- `requested` may become `confirmed` or `cancelled` after policy checks.
- `confirmed` may become `checked-in`, `cancelled`, or `no-show` according to timing and cancellation policy.
- `checked-in` may become `queued` or `cancelled` by an authorized operational user.
- `queued` may become `in-consultation`, `cancelled`, or `no-show` according to queue rules.
- `in-consultation` may become `completed` or an explicitly documented interrupted state.
- `completed`, `cancelled`, and `no-show` are terminal for ordinary users; corrections require an authorized amendment or reversal workflow.

The system must reject invalid transitions, record the actor and timestamp, and prevent duplicate booking or check-in operations.

### 5.3 Prescription fulfilment

A patient may upload a prescription for review, or a doctor may create one from a consultation. The system validates ownership and file/content requirements, routes it to Farmassist, records review decisions, supports approved/cancelled outcomes, calculates billable items, and hands off to the configured medicine/order workflow. Repeated submissions and duplicate processing must be safe.

The `[PROPOSED]` baseline prescription states are `draft` → `submitted` → `clarification_requested` → `approved`/`rejected` → `cancelled`/`fulfilled`. Exact state semantics and authority are `[NEEDS_REVIEW]`. The system must preserve doctor-authored medicine, dosage, duration, and instructions. Proposed reversal/amendment authority is the doctor before fulfilment and an authorized medical/admin role after review; this requires medical/legal confirmation.

#### Prescription review rules

- Uploaded prescriptions enter a pending-review state and retain the original file/content.
- Farmassist may approve, cancel, or request clarification when those actions are enabled by policy; every action requires an actor, reason where applicable, and timestamp.
- Farmassist must not silently change the doctor's medicine, dosage, duration, or clinical instructions. Any clarification or substitution follows an approved business policy and remains visible in the record.
- Approved prescriptions may enter billing/fulfilment states; cancelled prescriptions cannot be fulfilled without an authorized new submission or reversal process.
- Finalized prescriptions are read-only to ordinary users. Amendments create an attributable revision rather than erasing the original.

### 5.4 Billing and payment

The system calculates charges from consultation fees, medicines, services, taxes, discounts, commissions, and payment modes according to configured policy. It issues an invoice, records payment attempts and outcomes, and prevents unauthorized edits to finalized financial records.

#### Billing exception rules

Cancellation, rescheduling, partial payment, failed payment, refund, discount correction, tax correction, and order cancellation must each have a defined policy, actor permission, reason, and audit event. A failed external payment or order call must not mark the local invoice as paid or fulfilled.

### 5.5 Notifications

Configured events may notify users through in-app, email, SMS, or WhatsApp. Delivery status, failure reason, retry state, template version, and consent/opt-out rules must be recorded without exposing sensitive content unnecessarily.

### 5.6 Inventory and delivery baseline

The `[PROPOSED]` standard baseline tracks medicine batch and expiry. Expired or unavailable stock cannot fulfil an order. Substitutions require recorded approval and must not silently change clinical instructions. Proposed delivery states are `pending`, `packed`, `dispatched`, `out_for_delivery`, `delivered`, `failed`, and `returned`. Exact pharmacy/drug, inventory, delivery, cancellation, refund, and customer-support rules are `[NEEDS_REVIEW]`.

## 6. Masters and settings

Masters: doctor specializations, consultation fee templates, commission templates, departments, services, payment modes, tax rates, medicines, clinics, locations, and status values.

Settings: general, clinic, SMTP email, SMS gateway, WhatsApp API, notifications, invoice templates, prescription templates, backup/restore, and system logs. Secrets are environment/configuration data and must not be shown in ordinary UI or logs.

## 7. India operating and compliance baseline

India is the owner-approved jurisdiction baseline for the initial product scope. This is an implementation baseline, not a legal opinion or a declaration of compliance.

### 7.1 Privacy and health-data controls

ROOTINS must be designed for consent-aware processing, purpose limitation, data minimization, least-privilege access, secure storage and transfers, attributable audit logs, grievance/rights handling, and controlled retention/deletion. Health-record sharing must be explicit, purpose-bound, and revocable where applicable. The final notice, consent language, retention schedule, deletion exceptions, data roles, cross-border/storage decisions, and grievance process are `[NEEDS_REVIEW]` and require the policy owner and qualified counsel.

The design review should consider the Digital Personal Data Protection Act, 2023 and current Digital Personal Data Protection Rules/notifications, together with ABDM Health Data Management Policy and consent guidance. Useful official references are the [MeitY DPDP Rules page](https://www.meity.gov.in/documents/act-and-policies/digital-personal-data-protection-rules-2025-gDOxUjMtQWa?hl=en-US) and [ABDM health-data and consent guidance](https://abdm.gov.in/abdm).

### 7.2 Telemedicine and prescription controls

The baseline follows applicable Indian telemedicine operating principles: identify the patient and registered medical practitioner, capture appropriate consent, record the communication mode and clinical context, preserve clinician judgment, and do not allow software to diagnose or silently alter treatment. Prescription issuance, amendment, review, and fulfilment rules must be reviewed against the applicable Indian professional and medical guidance. The [Indian Telemedicine Practice Guidelines](https://esanjeevani.mohfw.gov.in/assets/guidelines/Telemedicine_Practice_Guidelines.pdf) are a high-level review reference; applicability to each workflow is `[NEEDS_REVIEW]`.

### 7.3 Payments, refunds, and reconciliation

Cashfree and Razorpay are approved candidate providers for the India baseline. Credentials, webhook secrets, merchant configuration, production enablement, provider contracts, and final compliance review remain pending. Payment flows must support Indian-standard handling for consent/authorization, failed transactions, refunds, cancellations, disputes, customer communication, settlement reconciliation, idempotency, and safe payment-data handling. ROOTINS must not store prohibited card data and must rely on provider-hosted/tokenized mechanisms as applicable.

Payment implementation must be reviewed against current RBI payment-aggregator/payment-system requirements, including applicable rules for authorization, merchant/consumer protection, settlement, failed transactions, disputes, refunds, reconciliation, and security. See the [RBI payment-aggregator notification reference](https://www.rbi.org.in/scripts/NotificationUser.aspx?Id=11996). This reference does not establish that ROOTINS or either provider is compliant for a particular deployment.

### 7.4 Pharmacy, delivery, backup, and reporting baseline

- Pharmacy and delivery workflows use an Indian-standard operating baseline for prescription review, medicine availability, fulfilment, delivery, returns, cancellations, and customer communication. Exact licensing, substitution, batch/expiry, delivery, refund, and record-retention rules are `[NEEDS_REVIEW]`.
- Backups must be encrypted, access-controlled, monitored, and restore-tested. A proposed initial target is `RPO 24 hours` and `RTO 8 hours`; these are `[PROPOSED]`, not approved service levels.
- Standard baseline KPIs/reports are proposed for appointments, consultations, prescriptions, fulfilment, revenue, payment failures/refunds, notification delivery, security events, and operational exceptions. Exact definitions, owners, filters, and targets are `[NEEDS_REVIEW]`.

## 8. Non-functional requirements

- Security: authentication, authorization, CSRF, input validation, secure file access, secret handling, auditability, and safe error responses. Security testing is required before production.
- Privacy: minimum necessary access, scoped queries, protected documents, retention controls, consent handling, and jurisdiction-specific privacy review before go-live.
- Reliability: transactional writes, idempotent callbacks, recoverable failures, monitoring, and reconciliation for integrations. Critical operations must have retry or compensating-failure behavior.
- Performance: dashboards and lists must be paginated/filterable; expensive reports must not block interactive requests. Initial target: normal interactive requests should complete within 2 seconds at the agreed baseline load, excluding third-party delays.
- Usability: responsive role-specific dashboards, clear statuses, accessible forms, and actionable validation messages. Critical workflows must be usable on supported desktop and mobile breakpoints.
- Maintainability: module boundaries, explicit routes, migrations, reusable services, focused tests, and documentation. New business workflows require requirement IDs and acceptance evidence.
- Availability/recovery: backup, restore verification, logs, and a documented recovery procedure must be defined before production. Recovery-point and recovery-time targets require project approval.
- India baseline: backups must be encrypted, access-controlled, daily-verified, and periodically restore-tested. `[PROPOSED]` initial targets are RPO 24 hours and RTO 8 hours; encryption/key ownership, frequency, and final targets are `[NEEDS_REVIEW]`.

## 9. Acceptance principles

Each feature must demonstrate: authorized access, valid and invalid input handling, correct status transitions, persistence and rollback behavior, audit events where applicable, safe duplicate submission handling, exception handling, and a usable UI response. Every released feature must reference one or more IDs in `docs/REQUIREMENTS.md` and include implementation/test evidence.

## 10. Traceability and open decisions

Detailed requirement IDs are maintained in `docs/REQUIREMENTS.md`; architecture and product decisions are maintained in `docs/DECISIONS.md`; implementation progress is maintained in `docs/MODULE-STATUS.md`. These documents must be updated together when scope or behavior changes.

The following items remain open: `[NEEDS_REVIEW]` supported compliance obligations and interpretation; identity verification and duplicate/merge policy; Cashfree/Razorpay production configuration, merchant onboarding, settlement, and refund policy; Twilio Video/Messaging/WhatsApp/SendGrid and Shiprocket deployment readiness; pharmacy, inventory, batch, expiry, substitution, and delivery rules; notification consent; invoice/tax rules; backup encryption/key ownership and recovery targets; exact configurable retention/deletion/withdrawal rules; and exact dashboard KPI definitions.

## 11. Glossary

- **Farmassist:** Role responsible for prescription review, medicine fulfilment, and related billing/order workflow.
- **Appointment states:** Lifecycle statuses for a booked visit, including requested, confirmed, checked-in, queued, in-consultation, completed, cancelled, and no-show.
- **Consultation:** The clinical interaction associated with an appointment; it may produce notes, reports, follow-up instructions, and a prescription.
- **Prescription revision:** An attributable amendment that preserves the original finalized prescription and records the reason and actor.
- **ABDM:** Ayushman Bharat Digital Mission; its policies and consent/data-sharing guidance are review references where applicable.
- **RPO/RTO:** Recovery Point Objective and Recovery Time Objective; the values in this baseline are proposed only.

## 12. Owner-approved provider and release-scope baseline

- `[DECIDED BASELINE]` India remains the jurisdiction baseline. DPDP Act/Rules, ABDM health-data and consent guidance, applicable Indian telemedicine guidance, RBI payment directions, and applicable Indian tax, pharmacy/drug, consumer-protection, and e-commerce requirements are control-mapping references. They are not a certification claim; legal applicability remains `[NEEDS_REVIEW]`.
- `[PROPOSED]` ABDM/ABHA is integration-ready but out of scope for the first release. A later phase may add ABHA linking and consent-based exchange only after sandbox, security, privacy, and compliance readiness.
- `[DECIDED BASELINE]` Cashfree and Razorpay are supported candidate providers behind a payment abstraction. Production onboarding, contracts, merchant setup, credentials, settlement, disputes, refunds, reconciliation, and grievance ownership remain deployment/review tasks.
- `[PROPOSED]` Twilio Video is the first telehealth provider. Use room-scoped, short-lived access tokens; recording is disabled by default; consent, participant identity, access audit, and session traceability are required. Provider setup and Indian telemedicine review remain `[NEEDS_REVIEW]`.
- `[PROPOSED]` Twilio Messaging/WhatsApp is the first SMS/WhatsApp provider and Twilio SendGrid is the first email provider. India sender/template/DLT, WhatsApp consent/opt-out, secrets, deliverability, and deployment configuration remain `[NEEDS_REVIEW]`.
- `[PROPOSED]` ROOTINS operates internal pharmacy, inventory, and fulfilment for the first release. External pharmacy/commerce synchronization is deferred and provider-neutral; Shiprocket is the first shipping provider behind an adapter, subject to credentials, serviceability, labels, webhooks, and reconciliation review.
