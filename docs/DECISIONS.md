# ROOTINS Architecture and Product Decisions

**Status:** Baseline decision log v0.5
**Date:** 2026-08-29

| ID | Decision | Rationale | Consequence / follow-up |
|---|---|---|---|
| ADR-001 | Keep a modular CodeIgniter 4 application with Admin, Doctor, Patient, and Farmassist modules. | Matches the existing repository and role-oriented product model. | New features belong to the owning module; shared behavior belongs in `app/` services only when genuinely shared. |
| ADR-002 | Use explicit routes and route-level authentication/permission filters. | Reduces accidental public exposure and matches current routing configuration. | Every new action needs a route, permission decision, and synchronized client endpoint if applicable. |
| ADR-003 | Retain the server-rendered UI plus jQuery/AJAX pattern for adjacent work. | Existing Admin implementation already uses this pattern. | Avoid introducing a competing frontend stack without a separately approved decision. |
| ADR-004 | Treat patient, clinical, prescription, and financial records as confidential and scope access by role and clinic. | Healthcare data requires least-privilege access and operational separation. | Queries, downloads, reports, dashboards, and logs must all enforce scope. |
| ADR-005 | Use database transactions for multi-table business writes and idempotency at integration boundaries. | Prevents partial consultation/prescription/order workflows and duplicate webhooks. | External providers require reconciliation because they cannot share the local transaction. |
| ADR-006 | Use migrations for all schema changes and preserve applied migrations. | Keeps environments reproducible and avoids destructive production changes. | Every schema change needs a new timestamped migration and rollback consideration. |
| ADR-007 | Preserve prescription history and protect finalized clinical/financial records. | Downstream fulfilment and audit depend on stable historical data. | Corrections require controlled amendment/reversal semantics, to be specified before implementation. |
| ADR-008 | Use the existing storage abstraction for private uploads and keep object keys in the database. | Centralizes R2/storage behavior and avoids local-path contracts. | Upload, database commit, and cleanup failure handling must remain ordered and logged safely. |
| ADR-009 | Keep external providers configurable rather than hard-coding providers in core domain code. | Provider candidates and contractual/operational approvals may change by deployment. | Add provider adapters behind configuration; Cashfree/Razorpay remain candidate choices pending production enablement and review. |
| ADR-010 | Record this documentation set as the baseline product contract, while marking implementation status separately. | Prevents planned scope from being mistaken for delivered functionality. | Update `MODULE-STATUS.md` whenever a module or requirement materially changes. |
| ADR-011 | Enforce PSR-12 coding style and CodeIgniter 4 conventions. | Ensures a consistent and maintainable codebase. | Run PHP linting and configured static analysis, such as PHPStan or Psalm, before merge. |
| ADR-012 | Require focused unit and integration tests for released workflows. | Improves reliability and regression safety. | CI/test evidence must cover changed requirement IDs; coverage thresholds require separate approval. |
| ADR-013 | Paginate and filter lists by default and optimize data access. | Prevents performance bottlenecks in dashboards and reports. | Avoid N+1 queries with joins, eager-loading patterns, or batched queries; cache only safe, stable lookups. |
| ADR-014 | Return safe error responses and use correlation IDs for diagnosable failures. | Protects sensitive data while enabling support and troubleshooting. | Standardize error handling and correlation-ID propagation across modules and integrations without logging secrets. |
| ADR-015 | Maintain module documentation and a changelog for migrations, rules, and release-impacting behavior. | Preserves governance and traceability over time. | Update `REQUIREMENTS.md` and `MODULE-STATUS.md` with each material release; document migration and rule changes. |
| ADR-016 | Use India as the product jurisdiction baseline. | Owner provided India as the initial operating jurisdiction. | Apply Indian privacy, health-data, telemedicine, payment, pharmacy, tax, and consumer-protection review before release; exact legal controls remain `NEEDS_REVIEW`. |
| ADR-017 | Treat Cashfree and Razorpay as approved candidate payment providers. | Owner selected providers suitable for the India baseline. | Build provider adapters only after credential/configuration approval, merchant onboarding, contract review, and final compliance review. |
| ADR-018 | Apply an India-aligned baseline for consent, purpose limitation, least privilege, audit, secure transfers/storage, retention, refunds, reconciliation, idempotency, and encrypted backups. | These controls reduce privacy, clinical, financial, and operational risk. | Retention/deletion is policy-controlled and configurable; exact refund/cancellation policy, backup targets, encryption/key ownership, and operational ownership remain `NEEDS_REVIEW`. |
| ADR-019 | Use DPDP Act/Rules, ABDM health-data and consent guidance, applicable Indian telemedicine guidance, and RBI payment-aggregator requirements as high-level review references. | Provides a shared compliance vocabulary without treating a reference as legal advice or certification. | Legal, medical, security, and payment specialists must confirm applicability and implementation evidence. |

## Baseline classifications

| Classification | Decision |
|---|---|
| `DECIDED BASELINE` | India is the initial jurisdiction baseline. Cashfree and Razorpay are approved candidate payment providers. Indian privacy, security, health-data, telemedicine, payment, tax, pharmacy/drug, consumer-protection, and e-commerce requirements are the review baseline. |
| `PROPOSED` | Farmassist is an internal pharmacy/fulfilment role operated by the clinic/platform owner. External legal-entity support is out of scope for the baseline and would require contracts and adapters. |
| `PROPOSED` | Prescription flow: `draft` → `submitted` → `clarification_requested` → `approved`/`rejected` → `cancelled`/`fulfilled`; doctor-authored medicine, dosage, duration, and instructions are preserved. |
| `PROPOSED` | Reversal/amendment authority: doctor before fulfilment; authorized medical/admin role after review, subject to medical/legal review. |
| `PROPOSED` | Track inventory batches and expiry; expired/unavailable stock cannot fulfil; substitutions require recorded approval; delivery states are `pending`, `packed`, `dispatched`, `out_for_delivery`, `delivered`, with `failed`/`returned` exception states. |
| `PROPOSED` | Initial recovery baseline is encrypted backups, daily backup verification, periodic restore testing, RPO 24 hours, and RTO 8 hours. |
| `PROPOSED` | First-release ABDM/ABHA integration is out of scope but the design should remain integration-ready. |
| `PROPOSED` | Standard KPI/report baseline covers appointments, queue wait, consultations, prescriptions, approval turnaround, revenue/collections/refunds, medicine fulfilment/delivery, active users, notification failures, and audit/security events. |
| `DECIDED BASELINE` | Retention, deletion, and withdrawal are policy-controlled and configurable; no legal retention period is hard-coded before review. Audit, financial, and clinically required records require controlled preservation. |

## Pending decisions and reviews

- `[NEEDS_REVIEW]` Which Indian privacy rules, retention periods, consent model, and jurisdiction-specific obligations apply?
- `[NEEDS_REVIEW]` Which compliance framework mapping and legal/medical interpretation must the production deployment satisfy?
- `[NEEDS_REVIEW]` Final Cashfree/Razorpay production enablement, contracts, merchant configuration, credential/key ownership, settlement, disputes, refunds, reconciliation, grievance ownership, and provider applicability.
- `[NEEDS_REVIEW]` Deployment readiness for Twilio Video, Twilio Messaging/WhatsApp, Twilio SendGrid, internal ROOTINS pharmacy, and Shiprocket, including contracts, credentials, configuration, consent/template/DLT, serviceability, webhooks, reconciliation, and final compliance review.
- `[NEEDS_REVIEW]` Legal/medical confirmation of the proposed internal Farmassist role and any authorization boundary.
- `[NEEDS_REVIEW]` Legal/medical confirmation of the proposed prescription states and reversal/amendment authority.
- `[NEEDS_REVIEW]` Exact inventory, batch, expiry, substitution, refund, cancellation, delivery, and pharmacy/drug operating rules.
- `[NEEDS_REVIEW]` Final recovery targets, encryption/key ownership, backup retention, and restore-test frequency; the RPO/RTO values above remain proposed.
- `[NEEDS_REVIEW]` Exact KPI/report definitions, dashboard ownership, filters, targets, and retention of reporting data.
- `[NEEDS_REVIEW]` Whether ROOTINS or a payment partner is the regulated payment aggregator/payment service party for each flow, and what merchant onboarding, settlement, dispute, and grievance obligations apply.
- `[NEEDS_REVIEW]` Whether the proposed ABDM/ABHA out-of-scope, integration-ready position is appropriate for the first release.
- `[NEEDS_REVIEW]` Exact configurable retention periods and deletion/withdrawal exceptions for clinical, billing, audit, consent, and communication records.

## Owner-selected provider baseline

| Area | Baseline | Classification / follow-up |
|---|---|---|
| Telehealth | Twilio Video first provider | `[PROPOSED]`; room-scoped short-lived tokens, no recording by default, consent and audit required; provider and Indian telemedicine review remain `[NEEDS_REVIEW]`. |
| SMS/WhatsApp | Twilio Messaging/WhatsApp first provider | `[PROPOSED]`; Indian sender/template/DLT and WhatsApp consent/opt-out remain `[NEEDS_REVIEW]`. |
| Email | Twilio SendGrid first provider | `[PROPOSED]`; secrets, configuration, deliverability, and deployment setup remain `[NEEDS_REVIEW]`. |
| Pharmacy | Internal ROOTINS pharmacy/inventory/fulfilment | `[PROPOSED]` first-release scope; external pharmacy/commerce integration is deferred and provider-neutral. |
| Shipping | Shiprocket first provider behind an adapter | `[PROPOSED]`; credentials, serviceability, labels, webhooks, reconciliation, failures, and returns remain `[NEEDS_REVIEW]`. |
| Commerce | External commerce synchronization | `[PROPOSED]` out of scope for first release; adapter-ready only. |

## Implementation milestone decisions

| ID | Change | Evidence | Remaining work |
|---|---|---|---|
| IM-001 | Protect the Admin dashboard route with the existing `auth` filter. | `php spark routes` shows `admin` with `csrf auth`; route syntax passes PHP lint. | Runtime unauthenticated/authorized behavior and any dashboard-specific permission remain to be verified or decided. |
| IM-002 | Protect Clinic and Doctor Admin route groups with existing `auth` and seeded `clinic.*`/`doctor.*` permission filters. | `php spark routes` shows the route groups with `csrf auth permission:...`; route syntax passes PHP lint. | Runtime denial/scope tests remain required; no new permission names or business rules were introduced. |
