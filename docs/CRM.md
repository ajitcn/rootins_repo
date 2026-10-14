# ROOTINS CRM module

CRM is exposed through the dedicated CRM namespace and module routes:

- /admin/crm/leads
- /admin/crm/follow-ups
- /crm/leads and /crm/follow-ups compatibility aliases

Lead, follow-up, and history records remain in the existing crm_* tables. Joined list queries use bounded count + limit/offset pagination with the CI4 Pager service; history uses the model pager. Existing crm.* permissions, clinic scope, CSRF protection, audit events, and conversion workflows remain authoritative.
