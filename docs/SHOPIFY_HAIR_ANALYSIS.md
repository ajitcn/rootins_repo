# Shopify AI Hair Analysis

## Setup

1. Apply migrations with `php spark migrate`.
2. Set `OPENAI_API_KEY` in the server environment. It is never stored in the database or returned to Shopify.
3. In Admin → Shopify → App Proxy setup, enable:
   - `hair_analysis.submit`
   - `hair_analysis.status`
4. Configure and save the prompt/schema at Admin → Shopify → AI Hair Analysis.

## Submit request

The Shopify theme sends a signed multipart App Proxy request to:

```text
POST /apps/rootins/hair-analysis/submit
```

Required header:

```text
Idempotency-Key: <unique-request-key>
```

Multipart fields:

- `first_name` required
- `age`, `gender`, `concern`, `health_list` optional
- `angles` optional comma-separated values such as `front,top,crown`
- `photos[]` one to five JPG, PNG, or WebP images, maximum 10 MB each

Successful response:

```json
{
  "status": true,
  "data": {
    "reference": "HAI-ABC123456789",
    "status": "completed",
    "created_at": "2026-10-31 12:00:00"
  }
}
```

## Read result

```text
GET /apps/rootins/hair-analysis/status?reference=HAI-ABC123456789
```

The response is scoped to the signed Shopify store and `logged_in_customer_id`. A completed result is returned under `data.result`; processing and failure states return only safe status/error metadata.

## Data ownership

Hair-analysis leads are global records and are not clinic-dependent. They remain separate from ROOTINS patients until an authorized staff workflow explicitly converts or links a lead later. Images are private R2 objects and are never exposed as permanent public URLs.
