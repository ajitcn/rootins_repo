# ROOTINS Release Checklist and Evidence Matrix

**Baseline:** 0.1
**Audit date:** 2026-08-29
**Scope:** Repository and development-readiness audit only; no production approval

This checklist records repository evidence; `PASS` is not production approval. `PARTIAL` means a bounded implementation exists. `FAIL` means a release-blocking capability is absent. `UNVERIFIED` means evidence requires runtime, UAT, integration, or operational confirmation.

## 1. Current evidence matrix

| Area / workflow | Status | Evidence checked | Remaining evidence or work |
|---|---|---|---|
| Admin authentication | PARTIAL | `AuthService`, login controller, session contract, `auth` route filter | Runtime login/logout/expiry and negative-path tests |
| Admin permissions | PARTIAL | Permission filter, permission seed data, explicit route filters including `admin/reports/overview`; denial unit test | Complete permission matrix, scope tests, browser/UAT proof |
| Admin identity/access UI | PARTIAL | Separate Users, Roles, and read-oriented Permissions screens; role/user assignment controls; explicit auth/permission filters | Database-backed IAM tests, clinic-scope policy, browser/UAT |
| Clinic management | UNVERIFIED | Admin clinic controller/model/views/routes/migration | CRUD, authorization, validation, clinic-scope tests |
| Doctor management | UNVERIFIED | Admin doctor domain controllers/models/views/routes/migrations | Conflict, document-access, scope, and workflow tests |
| Patient management | UNVERIFIED | Admin patient controllers/models/views/routes/migrations | Admin acceptance plus patient isolation tests |
| Appointment/queue | UNVERIFIED | Admin appointment routes/controllers/models/views | Conflict/race, transition, and booking policy tests |
| Consultation/prescription | UNVERIFIED | Admin consultation/prescription routes/controllers/models/views | Completion, finalization/history, authorization, and linkage tests |
| Doctor module | PARTIAL | Authenticated scoped dashboard, appointment detail, and clinical workspace; queue, consultation, prescription, availability/leave, and history boundaries enforce doctor/clinic/patient scope | Database-backed workflow tests, telehealth, billing, and UAT |
| Patient module | PARTIAL | Authenticated clinic-scoped portal supports profile, doctor browsing, appointment booking/rescheduling/cancellation, consultation/prescription history, and private document operations | Database/browser isolation tests, invoice/payment-history contract, durable notifications, and UAT |
| Farmassist review | PARTIAL | Authenticated queue and approve/reject/clarification action; role and cross-record clinic checks | Runtime/UAT evidence and durable audit |
| Medicine inventory/orders | PARTIAL | Unapplied schema foundation, batch/expiry allocation, duplicate-safe order creation, and clinic scope exist | Migration review/application and runtime fulfilment tests |
| Billing/payments | PARTIAL | Provider-neutral Cashfree/Razorpay payment-status reconciliation with duplicate handling exists | Invoice totals, callbacks, refunds, tax, settlement reconciliation, and production onboarding |
| Notifications | PARTIAL | Dry-run provider-independent dispatcher; idempotency/retry metadata; unit tests | Consent, durable delivery tracking, provider configuration, live integration |
| Audit/security | PARTIAL | CSRF/auth/permission filters, correlation IDs, masking, structured Admin access logging | Durable audit storage, complete event coverage, security regression suite |
| Backup/recovery | FAIL / MISSING | No verified implementation or restore evidence | Encrypted backups, verification, restore drill, approved RPO/RTO |
| Production readiness | UNVERIFIED / NOT APPROVED | No deployment/UAT/provider/legal sign-off evidence | Complete all deployment, compliance, operational, and sign-off gates |

## 2. Exact verification evidence

- `php spark routes` completed successfully; Admin, Doctor, and Farmassist routes were listed with CSRF/auth protection, and Admin clinic/doctor areas also showed permission filters.
- PHP lint passed for the changed/current PHP files, including configuration, filters, routes, controllers, services, migration syntax, models, and focused tests.
- `vendor/bin/phpunit tests/unit --testdox` passed: 16 tests and 47 assertions. Full PHPUnit passed 17 non-database tests; 2 CI4 example database tests errored because the `sqlite3` extension is unavailable. Coverage was not measured because no coverage driver is installed.
- `php spark migrate:status` completed without applying migrations. All listed application migrations through `CreateMedicines` are marked migrated; `2026-08-29-100000_CreateMedicineOrdersAndInventory` is present but unapplied and requires review/rehearsal before deployment.
- PHPStan/Psalm are not installed in the repository; static-analysis evidence is therefore unavailable.
- `git diff --check` passed; only Git line-ending warnings were emitted.
- Read-only secret scan found configuration variable names only; no secret values were printed. Provider credentials remain deployment responsibilities.
- Git status showed existing uncommitted application, migration, service, module, test, and documentation changes. They were preserved.
- No migrations, database writes, destructive operations, provider calls, or deployment changes were performed during this audit.

## 3. Development completion versus release readiness

The Admin, Doctor, Patient, and Farmassist code contains bounded routed workflows, but each remains `PARTIAL` until database-backed acceptance, authorization/isolation regression, browser/UAT, and operational evidence are complete. The following are development-complete boundaries for this baseline: Patient portal profile/appointments/records/private documents; Doctor queue/consultation/prescription workspace; Farmassist review/order/payment-status/delivery boundaries; and dry-run notification/audit services. They are not production approvals.

The unapplied medicine-order/inventory migration is a deployment-controlled prerequisite for runtime Farmassist order flows. It must be reviewed against the target database, backed up, rehearsed, applied during a change window, and verified before enabling those routes.

## 3. Development blockers

- Complete Patient database/browser acceptance with strict patient-record isolation and secure private document access.
- Complete Doctor database-backed acceptance/UAT, richer prescription handling, telehealth, and billing with doctor/clinic/patient scope.
- Add feature/integration tests for authentication, authorization, CSRF, scope, conflicts, clinical finalization, review transitions, duplicate requests, and file access.
- Complete Farmassist inventory batch/expiry validation, medicine orders, delivery transitions, and durable audit events.
- Implement billing/payment persistence and reconciliation without inventing unreviewed tax, refund, or cancellation policy.
- Add durable audit storage and cover clinical, financial, configuration, and status-sensitive events.

## 4. Deployment and legal blockers

- Complete India legal/medical/security review for DPDP Act/Rules, ABDM health-data and consent guidance, telemedicine guidance, RBI payment directions, tax, pharmacy/drug, consumer, and e-commerce obligations. This project makes no certification claim.
- Approve consent notices, purposes, withdrawal/deletion workflow, grievance ownership, and configurable retention schedules.
- Complete Cashfree/Razorpay merchant onboarding, contracts, webhook ownership, settlement, dispute, refund, reconciliation, and secret-management review.
- Complete Twilio Video, Twilio Messaging/WhatsApp, SendGrid, and Shiprocket contracts, credentials, sender/template/DLT, consent, serviceability, webhook, retry, and reconciliation setup.
- Approve the proposed internal Farmassist operating model and prescription amendment/reversal authority.
- Approve pharmacy licensing, substitution, batch/expiry, returns, cancellation, refund, delivery, and support procedures.
- Establish encrypted backup ownership, key management, retention, restore drills, monitoring, and final RPO/RTO. The 24-hour RPO and 8-hour RTO remain `PROPOSED`.

## 5. Deployment checklist

- [ ] Production environment and PHP/CI4 versions match the supported baseline.
- [ ] Secrets are supplied through deployment secret storage; no credentials are committed or logged.
- [ ] Database backup exists; migrations are reviewed, rehearsed, and reversible before execution.
- [ ] HTTPS, secure cookies, session settings, CSRF, error handling, logging, and file-storage permissions are verified.
- [ ] Queue/cron/worker, mail, SMS/WhatsApp, payment, telehealth, shipping, and monitoring configuration is tested in non-production first.
- [ ] Health checks, alerts, correlation IDs, rollback plan, and incident contacts are documented.
- [ ] Authenticated browser/UAT sign-off is complete for every enabled role and clinic scope.

## 5a. Rollback and change-window checklist

- [ ] Release owner records the exact application commit, migration versions, configuration version, and deployment timestamp.
- [ ] A tested database backup and private-object backup are available before deployment; restore location and owner are recorded.
- [ ] The unapplied medicine-order/inventory migration is rehearsed on a production-like copy and its `down()` path is reviewed without using it as an unplanned production rollback.
- [ ] Application rollback procedure is tested: restore prior artifact, disable newly enabled routes/features if needed, preserve audit evidence, and reconcile external callbacks.
- [ ] Database rollback procedure is separately approved; destructive reversal is never attempted without backup validation and owner approval.
- [ ] Smoke tests and health checks are defined for login, role access, clinic scope, patient isolation, appointment booking, consultation, prescription review, order/payment status, delivery, notifications, and audit.
- [ ] Incident owner, escalation contacts, maintenance communication, and go/no-go decision record are present.

## 6. Security checklist

- [ ] Every protected route has authentication and the intended permission/role/scope check.
- [ ] Patients cannot read or download another patient’s clinical, document, or billing records.
- [ ] Doctors and Farmassist users cannot cross clinic or assigned-record boundaries.
- [ ] All writes enforce server-side validation, CSRF, transactions, duplicate protection, and safe errors.
- [ ] Private uploads use authorized download endpoints and storage keys, not public paths.
- [ ] Logs redact credentials, tokens, contact data, and clinical content; correlation IDs are bounded and safe.
- [ ] Webhooks authenticate signatures, tolerate duplicates/out-of-order delivery, and never expose secrets.
- [ ] Security, dependency, static-analysis, and penetration findings are reviewed before production.

## 7. Backup and restore checklist

- [ ] Backup scope includes database, required private objects, configuration metadata, and audit records.
- [ ] Backups are encrypted in transit and at rest with documented key ownership and rotation.
- [ ] Backup success/failure is monitored and daily verification is recorded.
- [ ] Restore is tested in an isolated environment and data integrity is checked.
- [ ] Recovery procedures document dependencies, responsible owners, communications, and rollback.
- [ ] Policy owner approves retention, final RPO, and final RTO; proposed 24h/8h targets are not yet approved.

## 8. Provider onboarding checklist

| Provider boundary | Candidate | Required before production |
|---|---|---|
| Payments | Cashfree, Razorpay | Merchant account, contracts, secrets, webhook verification, settlement/refund/reconciliation UAT |
| Telehealth | Twilio Video | Room/token security, consent, no-recording default, retention and incident review |
| SMS/WhatsApp | Twilio Messaging/WhatsApp | India sender/template/DLT, opt-in/opt-out, delivery callbacks, failure handling |
| Email | Twilio SendGrid | Domain authentication, secrets, deliverability, bounce/complaint handling |
| Shipping | Shiprocket | Serviceability, labels, credentials, webhooks, duplicate/out-of-order reconciliation |
| Commerce | None for first release | External synchronization remains deferred and adapter-ready only |

Sandbox sign-off requires non-production credentials, verified signature/authentication behavior, idempotent duplicate callbacks, failure/retry evidence, masked logs, and an explicit owner for reconciliation. No live provider call is authorized by this checklist.

## 9. Test evidence checklist

- [x] PHP syntax checks passed for audited changed/current PHP files.
- [x] Route listing passed.
- [x] Focused unit tests passed: 14 tests, 38 assertions, including Patient, Farmassist, notification, audit, and permission boundary checks.
- [x] Diff whitespace check passed.
- [x] Migration status inspected without applying migrations; one new medicine-order/inventory migration remains unapplied.
- [ ] Authentication and permission integration tests for every protected module.
- [ ] Clinic scope and patient isolation tests, including private file access.
- [ ] Appointment conflicts/transitions and consultation/prescription finalization tests.
- [ ] Farmassist review, inventory, order, payment, delivery, webhook, and duplicate handling tests.
- [ ] Browser/UAT, static analysis, performance, backup/restore, and provider sandbox evidence.

Acceptance evidence must attach test data, actor/role, clinic scope, expected result, actual result, timestamp, and any audit/correlation identifier. A unit test alone cannot close a workflow acceptance row.

## 10. Sign-off criteria

Release sign-off requires all release-blocking rows above to be `PASS` or formally accepted with a documented risk owner and mitigation. It also requires:

1. Product owner approval of workflows, KPIs, roles, and operational policies.
2. Medical/legal/compliance approval for India applicability and consent/retention rules.
3. Security approval of authorization, isolation, uploads, secrets, audit, and incident controls.
4. Operations approval of deployment, monitoring, backups, restore, support, and recovery targets.
5. QA/UAT evidence for each enabled role and requirement ID in `docs/REQUIREMENTS.md`.

The current repository is **not release-ready**. ABDM/ABHA, external commerce synchronization, and advanced KPI polish may remain deferred without blocking ordinary application development, subject to the boundaries recorded in `docs/DECISIONS.md` and `docs/MODULE-STATUS.md`.
