# ROOTINS application-wide audit inventory

Audit date: 2026-10-12

This inventory records evidence gathered from the current working tree, route output, static scans, local browser smoke checks, migration status, and PHPUnit. Existing uncommitted work was preserved.

## Module inventory

| Area | Routes | Controllers | Models | Services | Views | Initial result |
|---|---:|---:|---:|---:|---:|---|
| Admin | present | 25 | 59 | 2 | 61 | Explicit protected routes and permission filters found |
| Doctor | present | 10 | 12 | 0 | 22 | Explicit authenticated routes and permission filters found |
| Patient | present | 5 | 1 | 1 | 25 | Dedicated patient authentication and scope filter found |
| Pharmacy | present | 9 | 2 | 16 | 46 | Dedicated pharmacy scope/permission workflow found |
| Core API | present | 1 | 2 | 8 | n/a | Signed API boundary and idempotency layer found |
| Shopify | present | 5 | 9 | 0 | n/a | HMAC webhook, handoff, and duplicate-event controls found |

## Findings by severity

### High

| Finding | Module | Evidence | Fix applied | Verification | Remaining limitation |
|---|---|---|---|---|---|
| Admin patient-document download redirected through the storage public-URL helper, which could bypass future application authorization if the bucket/public URL is reachable. | Admin / shared storage | `app/Modules/Admin/Controllers/Patient/PatientDocument.php::download()` calls `R2StorageLibrary::getUrl()`; Doctor document endpoints had the same pattern. | Fixed at the shared storage boundary: `getUrl()` now delegates to a 10-minute signed temporary URL, and Doctor document JSON/download paths explicitly use `getTemporaryUrl()`. | PHP lint and source scan confirm no application caller still receives a permanent document URL; browser download was not executed against real documents. | Re-run authorized/unauthorized document-download tests with provisioned R2/test fixtures. |

### Medium

| Finding | Module | Evidence | Fix applied | Verification | Remaining limitation |
|---|---|---|---|---|---|
| Core API idempotency claims remain in `processing` after validation or unexpected write failures. A retry with the same key receives `REQUEST_IN_PROGRESS` until the record expires. | Core API | `CoreApiIdempotencyService::begin()` inserts `processing`; `V1::write()` and `V1::upload()` call `complete()` only on success and do not release/mark failed claims in catch paths. | Not changed in this audit because retry-after-partial-write semantics need an explicit product decision and focused integration fixtures. | Confirmed by source trace; write/upload error paths were inspected. | Add a failure/retry state or safe claim release with transaction/idempotency tests before production use. |
| Core API per-minute rate-limit increments are read-then-write and are not atomic under concurrent requests. | Core API | `CoreApiAuthenticationService` reads cache count, compares, then saves count + 1. | Not changed; requires a cache-driver-compatible atomic increment contract and concurrency test. | Confirmed by source inspection. | Under concurrency, a credential may temporarily exceed its configured limit. |
| Shopify prescription-review route has only the `auth` route filter while the service performs the permission and Pharmacy scope check. | Shopify / Pharmacy | `app/Modules/Shopify/Config/Routes.php` review route uses `filter => auth`; `ShopifyPrescriptionUploadService::review()` enforces permission and clinic/source scope. | Not changed; service-side authorization is currently authoritative and changing route filters could affect existing integration callers. | Confirmed by route/service comparison. | Add route-level defense-in-depth after confirming the supported reviewer role matrix. |
| The full PHPUnit run is not clean: 5 errors and 20 failures, including missing SQLite support, stale UI/source assertions, and several previously broken string-interpolation assertions. | Tests / all modules | `php vendor/bin/phpunit` completed 359 tests with 5 errors, 20 failures, and 5 skips. | Corrected the three undefined-variable interpolation errors in Shopify unit tests; unrelated existing failures remain. | PHP syntax and JS syntax passes; PHPUnit result captured. | CI needs the SQLite extension and the remaining stale assertions need ownership-based triage; no blind mass rewrite was made. |

### Low / operational

| Finding | Module | Evidence | Fix applied | Verification | Remaining limitation |
|---|---|---|---|---|---|
| CSS validation tool is not installed in the repository/toolchain. | Shared assets | `stylelint`/package-based CSS validator is unavailable. | None. | PHP and JS checks passed; CSS validation could not be run. | Run the project’s approved CSS validator in CI or install one through the normal dependency process. |
| Existing working-tree changes make broad diff-based attribution unsafe. | Repository-wide | `git status --short` shows extensive modified and untracked application, test, migration, and asset files. | Preserved all unrelated changes. | Targeted diffs were inspected. | A clean baseline or commit partition is needed for a reliable complete regression attribution. |

## Controls confirmed present

- Global CSRF is enabled for server-rendered state-changing routes; Core API and Shopify webhook exceptions use their own authentication boundaries.
- Admin, Doctor, Patient, and Pharmacy route groups use explicit routes rather than improved auto-routing.
- Staff authentication rejects Patient sessions; Patient authentication uses a dedicated session marker and `PatientScope`.
- Permission filter refreshes permissions from the database and returns JSON 403 responses for AJAX callers.
- Core API requires key id, timestamp, HMAC signature, active credential, timestamp freshness, credential-scoped idempotency for POST requests, and cache-based rate limiting.
- Shopify webhook verification uses the raw request body and constant-time HMAC comparison; webhook events use unique external event identifiers for duplicate delivery handling.
- Shopify handoff tokens are signed, short-lived, store/patient/customer scoped, nonce-backed, and single-use for mutating appointment flows.
- Shopify prescription downloads use authorization followed by a temporary URL and do not return R2 object keys in safe responses.
- Upload paths inspected use extension/MIME/size validation and clean up newly uploaded objects when database writes fail.
- Migration status completed successfully through the current migration set; no pending migrations were reported.
- Browser unauthenticated smoke check at `http://127.0.0.1/rootins/public/` reached the Admin Login boundary.

## Verification limitations

- Authorized and unauthorized browser workflows requiring credentials were not executed because no test credentials were supplied in the request.
- No external Shopify webhook or payment provider call was made.
- CSS validation remains unavailable until the project supplies a validator.
- A full broken-link proof requires a clean route-to-view baseline and authenticated crawling; the static route scan found no safe basis for a blind rewrite.

## Follow-up remediation — 2026-10-12

The three remaining security findings were remediated:

- Core API idempotency claims are released on failed pre-completion requests, expired processing claims are safely reclaimable, and completed responses remain replayable under the credential/key/request-hash uniqueness contract.
- Core API rate limiting now uses an atomic MySQL conditional upsert keyed by credential and UTC minute, with stable `429`, `Retry-After`, and safe `retry_after` response data. The limiter fails closed if an atomic database driver is unavailable.
- The Shopify prescription-review route now has explicit `auth` and `pharmacy.prescription.review.view` route filters in addition to service authorization. Shopify webhook routes remain outside normal user-permission filters and continue using HMAC verification.

Follow-up verification: the rate-limit migration was applied successfully as migration 71; route listing shows the new Shopify permission filter; focused security tests pass apart from the standard PHPUnit “no code coverage driver” warning.
