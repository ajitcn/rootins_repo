# Shopify prescription-to-order contract

The Core API App Proxy endpoint is `POST /rootins-proxy/prescriptions/upload`.
It accepts a multipart `file` plus an `Idempotency-Key`. Shopify must send
the signed App Proxy query parameters, including `logged_in_customer_id`.

Shopify developer request (the canonical multipart field is `file`):

`POST /apps/rootins/prescriptions/upload` (Shopify App Proxy mapping to
`/rootins-proxy/prescriptions/upload`)

```text
Form-data:

file: <binary>
product_handles: dummy-product-02

Header:

Idempotency-Key: <unique-uuid>
```

The older `document` multipart field remains accepted only as a backward-
compatible alias. New Shopify storefront code must use `file`.

The response contains only `prescription_reference`, status, timestamp, MIME
type, and byte size. The R2 object key is private and is never returned.

Prescription uploads are global clinical records and do not require a patient
`clinic_id`. Clinic scope is resolved later only for billing, invoicing,
financial reporting, or order fulfilment. If that later step cannot resolve a
clinic, it returns `BILLING_CLINIC_UNAVAILABLE`; upload/file failures use
`FILE_INVALID` or `PRESCRIPTION_DEPENDENCY_UNAVAILABLE` as appropriate.

## Cart and order data

Use the Shopify cart metafield `rootins.prescription_reference` (type
`single_line_text_field`) and configure the matching order metafield with
cart-to-order copy enabled. Ajax-cart storefronts may instead send a private
private cart attribute named `rootins.prescription_reference`; the order webhook also
accepts the private order attribute fallback. A bundle may contain multiple
references separated by the storefront's bundle reference contract, but no
file URL or medical payload may be written to Shopify.

The order webhook reads the copied metafield or private attribute, scopes it to
the verified store and Shopify customer, and links the order to the private
ROOTINS upload. Missing, invalid, or expired references quarantine the order.
Valid uploads are marked `awaiting_review`; fulfilment remains blocked until a
pharmacist accepts the prescription.

Webhook verification uses the raw request body, the Shopify webhook HMAC,
configured store domain, and `X-Shopify-Webhook-Id` idempotency. Core API Proxy
configuration and webhook configuration remain separate.
