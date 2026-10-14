# Delivery provider tracking URLs

## Manual delivery status changes

The Pharmacy delivery modal lists all approved delivery statuses. Server-side
validation accepts only the approved status values. Normal sequential changes
continue to work with `pharmacy.delivery.update`; backward, terminal, failed,
returned, or otherwise non-sequential changes require the
`delivery.status.override` permission. Change reasons are optional and blank
values are stored as `NULL`. These changes remain local manual
updates when no external adapter is configured.

The Delivery Provider master accepts an optional HTTP or HTTPS tracking page URL. A URL may include `{tracking}`, `{tracking_reference}`, or `{{tracking_reference}}`; placeholders are replaced with the URL-encoded tracking reference when a tracking link is previewed. URLs without a placeholder are used unchanged as the provider tracking page. Empty URLs remain allowed for providers that do not publish an online tracking page.
