# ROOTINS production bootstrap

Run all migrations first. The bootstrap creates or reuses only the required
roles, permissions, Super Admin account, role mappings, and safe reference
catalog entries. It does not create a clinic or any patients, doctors,
appointments, prescriptions, orders, invoices, payments, inventory, API
credentials, or Shopify records.

Set these values in the server environment or an untracked `.env` file:

| Variable | Purpose |
| --- | --- |
| `ROOTINS_ADMIN_NAME` | Initial Super Admin display name |
| `ROOTINS_ADMIN_EMAIL` | Initial Super Admin email; existing users are preserved |
| `ROOTINS_ADMIN_PASSWORD` | Initial password; never printed or logged |
| `ROOTINS_ADMIN_CLINIC_ID` | Existing active clinic used as a safety check |

Validate without writing:

```text
php spark production:bootstrap --dry-run
```

Write only after reviewing the dry-run summary:

```text
php spark production:bootstrap --confirm-production
```

The command is idempotent and uses a transaction for writes. Existing users,
roles, permissions, mappings, and reference records are preserved. The current
schema does not have a `clinic_id` column on `users`, so the Super Admin remains
global; the supplied clinic ID is still required to resolve an active clinic
before any write.

Only aggregate counts and non-sensitive failure metadata are logged. Passwords,
hashes, tokens, API credentials, encryption keys, and request data are never
printed or logged.
