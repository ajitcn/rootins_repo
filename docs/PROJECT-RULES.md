# ROOTINS Project Rules

**Status:** Baseline v0.4  
**Date:** 2026-08-29  
**Applies to:** All ROOTINS application code, database changes, integrations, and documentation

## 1. Purpose

ROOTINS is a healthcare operations platform for clinic administration, doctor consultation, patient care records, prescriptions, and prescription-based medicine fulfilment. These rules are the project-level guardrails for implementing the requirements in `SRS.md` and `REQUIREMENTS.md`.

## 2. Architecture rules

- Use CodeIgniter 4.7.4-compatible patterns and PHP 8.2+.
- Maintain the modular HMVC structure: shared application code in `app/`, feature code in `app/Modules/`.
- The primary modules are `Admin`, `Doctor`, `Patient`, and `Farmassist`; each module owns its dashboard and user-facing workflows.
- Use explicit routes. Do not introduce auto-routed public actions.
- Follow the existing server-rendered views plus jQuery/AJAX conventions unless a separately approved architectural change is made.
- Reuse existing controllers, models, services, views, validation, and AJAX helpers before creating new components.
- Keep changes small, backward-compatible, and isolated to the requested feature.
- Follow PSR-12 coding style and CodeIgniter 4 conventions.
- Run configured PHP linting and static analysis, such as PHPStan or Psalm, before merge.

## 3. Healthcare and data rules

- Patient, consultation, prescription, document, payment, and audit data is confidential.
- Enforce least-privilege access at both route and business-operation levels.
- Do not expose private medical data in logs, URLs, JavaScript, error pages, exports, or unauthorized responses.
- Preserve an attributable history for clinically or financially important changes; do not silently overwrite finalized records.
- A prescription must remain linked to its patient, authoring doctor, consultation (when applicable), status, and item history.
- Clinical staff remain responsible for clinical decisions. The system may support workflows but must not silently diagnose, prescribe, or change clinical instructions.
- Retention, consent, privacy, medical-record, tax, and communication requirements must be confirmed for the deployment jurisdiction before production release.
- India is the current jurisdiction baseline. Treat DPDP Act/Rules, ABDM health-data and consent guidance, applicable Indian telemedicine guidance, RBI payment-aggregator requirements, and relevant pharmacy/tax/consumer rules as review inputs, not automatic proof of compliance.
- Consent must be purpose-bound, attributable, reviewable, and respected by downstream sharing and notification workflows.
- Apply purpose limitation, least privilege, secure storage/transfers, audit logging, approved retention, and controlled withdrawal/deletion handling.

## 4. Authorization rules

- Authentication uses the existing session contract: `isLoggedIn`, `user`, `roles`, and `permissions`.
- Protected module functionality must use the appropriate base controller and route filters.
- Permission keys use `resource.action` naming, for example `appointment.view` and `prescription.update`.
- Admin users may configure roles and permissions, but permission changes must be auditable and must not bypass authentication.
- A user may only see records belonging to permitted clinics, departments, or operational scopes.

## 5. Database and workflow rules

- Use new migrations for schema changes; never modify an already-applied migration.
- Use lowercase plural snake_case tables, `id` primary keys, foreign keys, and `created_at`/`updated_at` timestamps where appropriate.
- Use transactions for multi-table business writes.
- Validate all input server-side. Client-side validation is supplemental only.
- Protect appointment booking, queue movement, payment, prescription approval, and stock-affecting operations against duplicate submission and race conditions.
- Preserve status transitions and timestamps. Finalized invoices, prescriptions, and consultations require controlled correction or reversal workflows.
- External operations and local writes require idempotency, retry, reconciliation, or compensating cleanup where a shared transaction is impossible.
- Cashfree and Razorpay are approved candidate providers only; never enable production credentials or provider configuration without owner approval and final compliance review.
- Payment workflows must support failed-payment, refund, cancellation, dispute, settlement-reconciliation, and idempotency controls; do not store prohibited payment credentials.
- Backups must be encrypted, access-controlled, and restore-tested. Any RPO/RTO is `PROPOSED` until approved by the policy owner.
- Avoid N+1 queries by using joins, eager-loading patterns, or batched queries appropriate to the data access layer.
- Paginate and filter list, search, report, and dashboard detail queries by default; unbounded result sets require explicit justification.

## 6. Security and integration rules

- Preserve global CSRF protection and the existing AJAX CSRF header/cookie contract.
- Never hard-code or log credentials, tokens, secrets, passwords, or authorization headers.
- Validate and authenticate inbound webhooks before business processing; tolerate duplicate and out-of-order delivery.
- Store uploaded objects through the existing storage abstraction. For replacements, upload first, commit the database change, then clean up the old object.
- Validate upload type, size, ownership, and access authorization before serving files.
- Do not make production, DNS, database, queue, webhook, Shopify, R2, SMS, WhatsApp, or email configuration changes as part of ordinary feature work.
- Treat provider credentials, merchant onboarding, telehealth configuration, retention schedules, and compliance settings as `NEEDS_REVIEW` until formally approved.
- Farmassist is an internal clinic/platform fulfilment role for the `[PROPOSED]` baseline; external legal-entity fulfilment is out of scope until contracts, adapters, and legal review are approved.
- Use the `[PROPOSED]` prescription states `draft`, `submitted`, `clarification_requested`, `approved`, `rejected`, `cancelled`, and `fulfilled`; preserve doctor instructions and require medical/admin authority for post-review amendment or reversal.
- Track medicine batch/expiry and prevent fulfilment from expired or unavailable stock. Require recorded substitution approval and use the `[PROPOSED]` delivery states `pending`, `packed`, `dispatched`, `out_for_delivery`, `delivered`, `failed`, and `returned`.
- Treat retention, deletion, and withdrawal as policy-controlled and configurable; never hard-code a legal retention period before Indian legal/compliance review. Preserve audit, financial, and clinically required records through controlled workflows.
- ABDM/ABHA integration is `[PROPOSED]` out of scope for the first release but must remain integration-ready; do not enable unapproved health-data exchange.

## 7. UI and API contract rules

- Preserve established JSON shapes: successful AJAX responses use `status` and `msg`; validation failures may include `errors`.
- Preserve existing HTML-option response contracts and route constants when changing consumers.
- Escape database-backed output in views.
- Use existing layout, navigation, modal, loader, alert, and form-error helpers.
- Use accessible labels, keyboard-friendly controls, clear status text, and confirmation for destructive or irreversible actions.

## 8. Mandatory UI/UX standard

This is a project-wide release requirement for Admin, Doctor, Patient, and Farmassist. A module is not UI-complete merely because its route, controller, or database workflow exists.

- Provide a modern, clean, interactive experience using the established server-rendered Bootstrap/jQuery stack. Keep layout, navigation, breadcrumbs, page titles, cards, forms, tables, buttons, badges, alerts, modals, validation, and status language consistent across panels.
- Every interactive screen must provide intentional loading, empty, validation, success, permission-denied, and recoverable-error states. Safe user-facing messages must not expose exceptions, identifiers, medical data, credentials, or provider secrets.
- Use shared layout partials, centralized CSS/design tokens, and reusable components/helpers. Do not introduce ad-hoc per-page styling or duplicate interaction patterns when an existing project component can be reused.
- Large datasets must use the project’s established searchable/filterable `bootstrap-select` (or approved equivalent) and must provide pagination and/or server-side search where appropriate. Do not use unwieldy unfiltered plain dropdowns for clinics, users, doctors, patients, medicines, permissions, or other large datasets.
- Screens must work at mobile, tablet, and desktop widths with responsive tables/forms and usable navigation collapse behavior. Avoid horizontal overflow and preserve the primary task on small screens.
- Meet accessibility expectations: explicit labels and field associations, logical keyboard order, visible focus, keyboard-operable controls, sufficient contrast, non-colour-only status meaning, accessible modal/collapse behavior, and status messages that are announced or otherwise perceivable.
- UI actions must respect route-level authentication, permission checks, clinic/data scope, CSRF, and server-side validation. Hide unavailable actions where appropriate, but rely on server enforcement; never treat UI visibility as authorization.
- Keep interactions performant and maintainable: debounce server-side search, avoid unnecessary requests, paginate results, prevent duplicate submissions, use non-blocking alerts/loaders, and keep feature-specific JavaScript small and reusable.

### UI review checklist

Before marking a module or workflow `Implemented` or `Verified`, reviewers must record evidence for: responsive layout; shared components/styles; searchable/filterable large-data controls; pagination or server-side search; loading/empty/error/success states; validation and safe errors; keyboard/focus/contrast checks; permission and clinic-scope behavior; duplicate-submit protection; and desktop/tablet/mobile smoke coverage. Any unmet item remains `Partial`, `Unverified`, or `NEEDS_REVIEW` in `MODULE-STATUS.md`.

## 9. Quality gates

Every change must include, as applicable:

1. a dependency-flow review before modifying an existing workflow;
2. focused tests or validation for the changed behavior;
3. migration and route checks;
4. a review of changed files for unrelated side effects;
5. documentation updates when behavior, permissions, status transitions, or architecture changes.
6. performance checks covering pagination, query efficiency, and obvious N+1 regressions;
7. security regression tests covering authorization, record scoping, CSRF, uploads, and sensitive responses;
8. PHP linting, PSR-12 review, and configured static-analysis checks.

## 10. Definition of done

A feature is done only when its requirements, authorization, validation, persistence, UI states, error handling, audit impact, tests, performance checks, static checks, and documentation are addressed. “Implemented” means usable through the intended route and role, not merely that a table or controller exists. CI/test evidence must identify the changed requirement IDs.

## 11. India provider and release-scope rules

- Use provider abstractions for Cashfree/Razorpay payments, Twilio Video, Twilio Messaging/WhatsApp, Twilio SendGrid, and Shiprocket. Provider credentials, merchant onboarding, sender/template/DLT setup, serviceability, contracts, webhooks, settlement, reconciliation, and production enablement remain deployment-controlled and `[NEEDS_REVIEW]`.
- Twilio Video sessions must use room-scoped short-lived access tokens, recording disabled by default, explicit consent, participant identity checks, and access/session audit events.
- Twilio Messaging/WhatsApp must enforce Indian sender/template/DLT requirements and consent/opt-out controls. SendGrid deliverability and secrets must be configured without exposing sensitive values.
- ROOTINS internal pharmacy/inventory/fulfilment is the `[PROPOSED]` first-release baseline. External pharmacy and commerce synchronization is deferred and provider-neutral; Shiprocket is the `[PROPOSED]` first shipping adapter.
- ABDM/ABHA linking and consent-based exchange are `[PROPOSED]` out of scope for the first release and require sandbox, security, privacy, and compliance readiness before activation.
