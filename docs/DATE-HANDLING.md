# ROOTINS date handling

- User-facing forms, filters, modals, and datepickers use `dd-mm-yyyy`.
- Database queries and internal storage use validated `yyyy-mm-dd` values.
- Core API and Shopify contracts retain their documented boundary format; the service normalizes once before database access.
- Shared PHP helpers are `rootins_parse_date()` and `rootins_format_date()` in `app/Helpers/common_helper.php`.
- Shared browser helpers are `window.rootinsDateToIso()` and `window.setSharedDateValue()` in the root layout.
- Invalid dates are rejected strictly, including impossible calendar dates and non-leap-year February 29 values.
- Empty optional dates remain `null`/empty and time fields are not converted.
