# Shopify App Proxy

The App Proxy is part of the ROOTINS CodeIgniter application. Configure it at `/admin/integrations/shopify/proxy-setup` with the `shopify.integration.view` and `shopify.integration.manage` permissions.

The Core API App Proxy is a separate Shopify app from the existing Shopify Webhooks / Order Integration app. Its settings are stored in `shopify_core_api_proxy_configurations`; it never reads or writes the webhook app's store domain, client credentials, webhook secret, subscriptions, customer linking, or order-import configuration.

Default Shopify values are:

- prefix `apps`
- subpath `rootins`
- backend path `/rootins-proxy`
- storefront URL `https://rootins.myshopify.com/apps/rootins`
- ROOTINS URL `https://rootins.newrootssoftware.in/public/rootins-proxy`

Shopify requests are accepted only when the proxy is enabled, the `shop` domain matches the stored Shopify domain, the timestamp is within five minutes, and the App Proxy HMAC signature validates with the server-side Shopify client secret. POST requests require `Idempotency-Key`. Customer identity links, clinic scope, Core API rate limiting, audit events, and existing Core API business services are applied at the proxy boundary.

Routes:

- `GET /rootins-proxy/appointments/availability`
- `POST /rootins-proxy/appointments/book`
- `POST /rootins-proxy/patients/register`
- `POST /rootins-proxy/prescriptions/upload`

Availability without date filters returns only today and tomorrow in the
application timezone. Storefront clients may provide an explicit `date`,
`dates`, or date range; explicit ranges remain capped at 31 days. Availability
does not require a linked patient and continues to exclude unavailable,
booked, past, leave, break, and payment-held slots.

Availability returns stable public references for direct booking. Each doctor
has `doctor_id`, `doctor_reference`, and `doctor_code`; each service has
`service_reference`, `service_name`, `duration_minutes`, `fee`, and `currency`;
and each available slot has `slot_reference`, `date`, `start_time`, `end_time`,
`timezone`, and `is_available`. Shopify should submit those returned references
to `POST /rootins-proxy/appointments/book`, including `slot_reference`.
The booking aliases `doctor_id` and `service_id` are accepted only when they
contain those public references, for example `doctor_id: "AC"` and
`service_id: "service-2"`; internal numeric IDs are not accepted.

Appointment booking requires an `Idempotency-Key` header and the server-resolved
doctor, service, date, start time, and end time. The proxy resolves the patient
from the signed-in Shopify customer link and resolves doctor, service, fee,
clinic, and slot data in ROOTINS. Client-submitted fee, patient, source, doctor
display data, and calculated amounts are ignored. A valid request creates only a
`pending_payment` intent and returns `rootins_appointment_intent` checkout
metadata. The `orders/paid` webhook rechecks the held slot before creating the
final appointment.

Proxy errors use HTTP 422 for invalid fields, 403 for an unlinked Shopify
customer, and 409 for an unavailable or already-held slot. Error responses
include a safe `error.code`, field-level `error.fields` when applicable, and a
request `error.correlation_id`; secrets and submitted values are not logged.

The Core API client ID and client secret are stored encrypted in the separate Core API proxy configuration record. Secrets are never returned to storefront clients, rendered into HTML/JavaScript, placed in URLs, or logged. Secret rotation is available from the Core API proxy section.
