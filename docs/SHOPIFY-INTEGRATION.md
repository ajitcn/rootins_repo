# Shopify integration foundation and appointment boundary

This document describes the Shopify foundation and Phase 3 appointment
availability/booking and prescription-upload boundaries. Order import, payment
synchronization, inventory synchronization, and delivery synchronization are
not implemented.

## Environment variables

Configure these values in the server environment. Do not commit secrets to
source control, views, JavaScript, or logs.

```dotenv
SHOPIFY_STORE_DOMAIN=example.myshopify.com
SHOPIFY_ADMIN_API_ACCESS_TOKEN=replace-on-server-only
SHOPIFY_API_VERSION=2025-01
SHOPIFY_WEBHOOK_SECRET=replace-on-server-only
SHOPIFY_APP_URL=https://app.example.com/integrations/shopify
SHOPIFY_ENCRYPTION_KEY=replace-with-a-managed-secret
SHOPIFY_CLINIC_ID=1
SHOPIFY_AUTO_CREATE_PATIENTS=false
```

The readiness screen exposes only the store domain, API version, app URL, and
boolean presence flags. It never exposes the token, webhook secret, or
encryption key.

`SHOPIFY_CLINIC_ID` is required before automatic matching or administrative
linking can cross into ROOTINS patient data. Automatic patient creation is
disabled by default and should remain disabled until the clinic, consent,
required fields, and retention policy are approved.

## Shopify app setup

Create a Shopify app for the approved store and environment, configure the
server-side Admin API token, register webhook callback URLs, and verify the
callback using a real Shopify HMAC signature before enabling traffic.

Current routes:

- `GET /admin/integrations/shopify`
- `POST /admin/integrations/shopify/test-connection`
- `POST /integrations/shopify/webhooks/{topic}`
- `GET /integrations/shopify/appointments/availability`
- `POST /integrations/shopify/appointments/book`
- `POST /integrations/shopify/appointments/{appointment_id}/cancel`
- `POST /integrations/shopify/appointments/{appointment_id}/reschedule`
- `POST /integrations/shopify/prescription-uploads`
- `GET /integrations/shopify/prescription-uploads/{document_id}/download`
- `POST /integrations/shopify/prescription-uploads/review`

The readiness screen is protected by `shopify.integration.view`; the
connection test requires `shopify.integration.manage`.

## Appointment booking boundary

Availability and booking require a signed, short-lived Patient handoff issued
for a linked Shopify customer. Availability verifies the handoff without
consuming it; a new booking consumes the nonce after the idempotency lookup.
Repeated requests with the same store/idempotency key return the original
appointment confirmation instead of creating another appointment.

ROOTINS remains authoritative for clinics, doctors, services, schedules,
leaves, appointments, and patient identity. The booking service resolves the
patient and clinic from the handoff and configured `SHOPIFY_CLINIC_ID`; posted
patient, clinic, status, and ownership fields are ignored. The core appointment
uses `booking_source=shopify`, while `appointment_services` stores the approved
service relationship. External references are stored in
`shopify_appointment_bookings` with unique store/reference and store/idempotency
keys.

Dates crossing this boundary use `dd-mm-yyyy`; persisted appointment dates use
`yyyy-mm-dd` and times use `HH:mm:ss`. Availability exposes only active,
online-bookable doctors, active scoped services, and slot states (`available`,
`full`, `unavailable`, or `leave`). It does not expose permissions, audit data,
private clinical notes, stock, or inventory details.

The Phase 3 migration must be applied before live UAT. The current local
environment must also be able to load `.env` through the XAMPP PHP runtime;
until that bootstrap issue is corrected, migration and live endpoint checks
remain deployment blockers.

## Prescription uploads

Shopify uploads require the signed Patient handoff and an external upload
reference. The existing `patient_documents` table stores the private R2 object
metadata plus clinic, source, customer, status, and retention fields added by
the reversible Phase 4 migration. Only PDF, JPEG, and PNG files are accepted;
the detected file-content MIME type must match the extension and the file must
not exceed 10 MB.

R2 object keys are never returned to Shopify or rendered as public paths.
Patient, permitted Doctor, and permitted Pharmacy Assistant users reach the download
controller only after server-side patient/clinic authorization; the controller
redirects to a short-lived private R2 URL. Uploads begin as `uploaded`, may be
moved to `under_review`, and then to `accepted`, `rejected`, or
`clarification_required`. Review does not create a clinical prescription.

Upload metadata and review transitions are audited. Reviewer notifications
are best-effort and failure is logged without rolling back a successfully
stored upload. `SHOPIFY_PRESCRIPTION_RETENTION_DAYS` controls retention and
defaults to 365 days.

## Required API scopes

The exact scopes require approval. The foundation does not request or assume
broad scopes. The initial proposal is:

- `read_customers`
- `read_products`
- `read_orders`
- `read_fulfillments`

Write scopes must not be enabled until ownership of appointments, orders,
payments, inventory, and fulfilment is approved.

## Webhook topics

The enabled topics are customers create/update, orders create/update/cancel,
fulfillments create/update, refunds create, and app uninstall.

The webhook endpoint verifies the raw request-body HMAC, store configuration,
and external event ID before persisting only event metadata and a SHA-256
payload hash. For customer create/update events it also stores a minimized
customer payload containing only the external ID, name, email, phone, and
safe default-address fields needed for patient synchronization. Full webhook
payloads are never stored. Customer events idempotently match or create a
global ROOTINS patient and maintain a store-scoped identity link; updates
change profile fields only and never clinical fields.

## Data ownership

- ROOTINS remains authoritative for patients, appointments, prescriptions,
  invoices, central inventory, and local audit records.
- Shopify external IDs must be stored in link tables and never treated as
  ROOTINS primary keys.
- ROOTINS central inventory remains the stock authority unless a later
  approved decision changes this policy.
- Payment and delivery status changes require verified provider events and
  existing ROOTINS transition contracts.

## Retry and reconciliation strategy

Webhook events are deduplicated by store and Shopify event ID. API operations
use deterministic operation keys in the local idempotency table. Failed
operations remain observable with a retry count and safe error message. Failed
customer events remain retryable through the existing event status flow;
duplicate event IDs do not repeat patient or link writes. The Admin Shopify
customer view exposes the store, customer ID, patient reference, link status,
last sync time, and safe sync error.

## Security model

- HMAC verification uses the raw request body and constant-time comparison.
- Webhook routes are excluded from browser CSRF because HMAC is their request
  authentication boundary.
- Admin integration settings require dedicated permissions.
- Tokens, webhook secrets, encryption keys, passwords, OTPs, and patient data
  are never returned to the browser or written to logs.
- Patient, clinic, order, medicine, and appointment identifiers must be
  resolved server-side in later phases.
- Uploads require ownership, type, size, malware, retention, and secure
  storage controls before they are connected to clinical workflows.
