# Shopify App Proxy

The App Proxy is part of the ROOTINS CodeIgniter application. Configure it at `/admin/integrations/shopify/proxy-setup` with the `shopify.integration.view` and `shopify.integration.manage` permissions.

The Core API App Proxy is a separate Shopify app from the existing Shopify Webhooks / Order Integration app. Its settings are stored in `shopify_core_api_proxy_configurations`; it never reads or writes the webhook app's store domain, client credentials, webhook secret, subscriptions, customer linking, or order-import configuration.

Default Shopify values are:

- prefix `apps`
- subpath `rootins`
- backend path `/shopify-proxy`
- storefront URL `https://rootins.myshopify.com/apps/rootins`
- ROOTINS URL `https://rootins.newrootssoftware.in/public/shopify-proxy`

Shopify requests are accepted only when the proxy is enabled, the `shop` domain matches the stored Shopify domain, the timestamp is within five minutes, and the App Proxy HMAC signature validates with the server-side Shopify client secret. POST requests require `Idempotency-Key`. Customer identity links, clinic scope, Core API rate limiting, audit events, and existing Core API business services are applied at the proxy boundary.

Routes:

- `GET /shopify-proxy/appointments/availability`
- `POST /shopify-proxy/appointments/book`
- `POST /shopify-proxy/patients/register`
- `POST /shopify-proxy/prescriptions/upload`

The Core API client ID and client secret are stored encrypted in the separate Core API proxy configuration record. Secrets are never returned to storefront clients, rendered into HTML/JavaScript, placed in URLs, or logged. Secret rotation is available from the Core API proxy section.
