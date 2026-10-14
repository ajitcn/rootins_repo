# ROOTINS Core API v1

The Core API is independent of the Shopify module. Shopify webhooks and
Shopify-specific synchronization remain under `App\Modules\Shopify`.

## Authentication

Each request uses:

- `X-API-Key-Id`: public credential identifier.
- `X-API-Timestamp`: Unix timestamp, accepted within five minutes.
- `X-API-Signature`: base64 HMAC-SHA256 signature.
- `Idempotency-Key`: required for every `POST` request.

The server constructs the signed message exactly as follows:

```text
timestamp + "\n" + uppercase_method + "\n" + "/api/v1/path" + "\n" + raw_request_body
```

Important client rules:

- `timestamp` is Unix time in whole seconds as ASCII digits, not milliseconds.
- The signed path is the route path only, such as `/api/v1/patients/register`.
  Do not include the deployment prefix `/rootins/public` in the signed path.
- The body is the exact bytes sent on the wire. JSON must be encoded once and
  the identical string must be used for both signing and the request body.
- The digest is raw HMAC-SHA256 (`hash_hmac(..., true)`) encoded with base64.
  It is not hexadecimal.
- Header names are `X-API-Key-Id`, `X-API-Timestamp`, and
  `X-API-Signature`. Values are trimmed; the signature comparison is
  constant-time and case-sensitive.

Verified PHP/cURL example:

```php
<?php
$baseUrl = 'http://127.0.0.1/rootins/public';
$path = '/api/v1/patients/register';
$apiKeyId = 'YOUR_API_KEY_ID';
$secret = 'YOUR_API_SECRET';
$body = json_encode([
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'country_code' => '91',
    'mobile_number' => '9876543210',
    'date_of_birth' => '01-01-1990',
    'clinic_id' => 7, // business context; never used for API authentication
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$timestamp = (string) time();
$canonical = $timestamp . "\n" . "POST" . "\n" . $path . "\n" . $body;
$signature = base64_encode(hash_hmac('sha256', $canonical, $secret, true));
$idempotencyKey = 'shopify-patient-' . bin2hex(random_bytes(12));

$curl = curl_init($baseUrl . $path);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key-Id: ' . $apiKeyId,
        'X-API-Timestamp: ' . $timestamp,
        'X-API-Signature: ' . $signature,
        'Idempotency-Key: ' . $idempotencyKey,
    ],
    CURLOPT_POSTFIELDS => $body,
]);
echo curl_exec($curl);
curl_close($curl);
```

The signing secret is encrypted in `core_api_credentials` and is never sent
to clients or written to logs. The credential is application-wide. Clinic
context is resolved and validated separately from the authorized patient,
doctor, appointment, or other domain relationship; it is never read from the
credential and cannot be used to bypass data isolation.

## Endpoints

<!-- BEGIN CORE API ENDPOINT CATALOG -->

| Method | Path | Purpose | Required headers | Parameters | Response fields | Errors |
|---|---|---|---|---|---|---|
| POST | /api/v1/patients/register | Register a patient in the explicitly selected active clinic. | X-API-Key-Id, X-API-Timestamp, X-API-Signature, Idempotency-Key | clinic_id, first_name, last_name, country_code, mobile_number, date_of_birth; optional email, gender, address, source_reference | patient_reference | API_AUTH_REQUIRED, API_TIMESTAMP_INVALID, API_SIGNATURE_INVALID, IDEMPOTENCY_KEY_REQUIRED, CLINIC_REQUIRED, VALIDATION_FAILED |
| GET | /api/v1/appointments/availability | List application-wide public doctor profiles and dated appointment slots. | X-API-Key-Id, X-API-Timestamp, X-API-Signature | optional date/dates (dd-mm-yyyy), clinic_id, doctor_reference, specialization_reference, service_reference, search, page, page_size | date, doctors, services, pagination | API_AUTH_REQUIRED, API_TIMESTAMP_INVALID, API_SIGNATURE_INVALID, API_RATE_LIMITED, DOCTOR_NOT_FOUND, SERVICE_NOT_FOUND, SPECIALIZATION_NOT_FOUND, CLINIC_NOT_FOUND, VALIDATION_FAILED |
| POST | /api/v1/appointments/book | Book an appointment in the patient clinic; submitted clinic_id cannot override the patient relationship. | X-API-Key-Id, X-API-Timestamp, X-API-Signature, Idempotency-Key | patient_reference, doctor_reference, service_reference, appointment_date, appointment_time; optional clinic_id, appointment_type, reason_for_visit | appointment_reference, status | API_AUTH_REQUIRED, API_SIGNATURE_INVALID, IDEMPOTENCY_KEY_REUSED, PATIENT_NOT_FOUND, CLINIC_SCOPE_INVALID, SLOT_UNAVAILABLE, VALIDATION_FAILED |
| POST | /api/v1/prescriptions/upload | Upload a prescription for the patient clinic resolved from patient_reference. | X-API-Key-Id, X-API-Timestamp, X-API-Signature, Idempotency-Key | multipart: document, patient_reference, external_upload_reference; optional clinic_id, title | upload_reference, status, received_at | API_AUTH_REQUIRED, API_SIGNATURE_INVALID, IDEMPOTENCY_KEY_REUSED, PATIENT_NOT_FOUND, CLINIC_SCOPE_INVALID, FILE_INVALID, FILE_TYPE_INVALID, VALIDATION_FAILED |
| GET | /api/v1/prescriptions/{public_reference} | Retrieve uploaded prescription details. | X-API-Key-Id, X-API-Timestamp, X-API-Signature | path: public_reference (required) | prescription_reference, document_reference, patient_reference, original_filename, file_type, file_size, uploaded_at, status, status_message, reviewed_at, clarification_message, download_url, download_url_expires_at | API_AUTH_REQUIRED, API_TIMESTAMP_INVALID, API_SIGNATURE_INVALID, API_RATE_LIMITED, PRESCRIPTION_NOT_FOUND |

<!-- END CORE API ENDPOINT CATALOG -->

## Legacy compatibility endpoint

`POST /api/v1/patients` is retained for existing clients and is not part of
the signed Core API v1 catalog above. It requires the legacy
`Authorization: Bearer <ROOTINS_API_TOKEN>` configuration, applies its own
rate limit and payload validation, and preserves the existing response
contract. New integrations must use the signed `/api/v1/patients/register`
endpoint. Do not remove this route without coordinating client migration.

The endpoint catalog above is generated from
App\Modules\CoreApi\Config\EndpointDefinitions. Run
php scripts/generate-core-api-docs.php after adding an endpoint so the Admin
Core API page and this document remain synchronized.

Public references are used instead of internal database IDs. Booking and
upload resolve `patient_reference` server-side and enforce the patient clinic.
Registration requires a valid active `clinic_id` as business context. The
availability endpoint is application-wide and does not require `clinic_id`;
an optional active `clinic_id` may be supplied as a business filter. A
mismatched or inactive clinic filter is rejected. Doctor and service references
are resolved server-side and internal IDs are never returned.
The API returns stable error objects with an error `code`, safe `message`, and
optional field-level `fields`.

### Appointment availability

`GET /api/v1/appointments/availability` accepts these query parameters:

- `date=dd-mm-yyyy` (defaults to the current application-timezone date).
- `dates=dd-mm-yyyy,dd-mm-yyyy` or repeated `dates[]` for up to 31 dates.
- `doctor_reference`, `specialization_reference`, and `service_reference`.
- `search` for a public doctor name, reference, or qualification search.
- `clinic_id` as an optional business filter; it is not an authentication field.
- `page` and `page_size` (1–100; default page size is 20).

The response contains `doctors`, `services`, and `pagination`. Each doctor
contains only public profile fields (`reference`, `name`, `profile_image`,
`qualification`, `specializations`, `experience`, `consultation_fee`, and
`services`) plus `availability_status` and a `dates` array. Each date contains
the `dd-mm-yyyy` date, working-day and leave status, a safe generic
`leave_message`, and slots with `slot_duration`, `start_time`, `end_time`,
`slot_available`, `remaining_capacity`, and `booking_status`.

`booking_status` is one of `available`, `full`, `unavailable`, `leave`, or
`past`. Breaks, disabled/non-working schedules, approved leave, capacity,
active appointment conflicts, and expired times are evaluated using the same
application timezone and active appointment rules as booking. Invalid doctor,
specialization, service, or clinic filters return stable errors such as
`DOCTOR_NOT_FOUND`, `SPECIALIZATION_NOT_FOUND`, `SERVICE_NOT_FOUND`, or
`CLINIC_NOT_FOUND`.

### Prescription details

`GET /api/v1/prescriptions/{public_reference}` uses the same HMAC, timestamp,
rate-limit, and audit flow as the other Core API endpoints. The reference is
the public upload reference returned by the upload flow; patient and clinic
authorization are resolved from the stored document association. Client
`patient_id` and `clinic_id` values are not accepted for authorization.

The response includes only the public document reference, patient-safe
reference, original filename, MIME type, file size, application-timezone
upload/review timestamps, the status (`uploaded`, `under_review`, `accepted`,
`rejected`, or `clarification_required`), and sanitized reviewer status text.
When the document is active and within retention, `download_url` is a
short-lived signed URL and `download_url_expires_at` is its expiry timestamp.
Missing, unauthorized, or expired documents return the stable 404 error code
`PRESCRIPTION_NOT_FOUND`. Storage keys, private paths, credentials, and audit
metadata are never returned.

## Provisioning

Provision credentials through the server-side
`App\Modules\CoreApi\Services\CoreApiCredentialService`. Do not expose that
operation as an unauthenticated endpoint. The new migration must be applied
before provisioning credentials.
