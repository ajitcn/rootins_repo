# Local Admin UAT access

This setup is for local development only. It is refused unless CodeIgniter is
running with `ENVIRONMENT=development` and it never prints or stores the test
password in application logs or the UI.

## Create or refresh access

Set temporary environment variables in the local shell, then run:

```powershell
$env:ROOTINS_TEST_ADMIN_EMAIL = 'admin-uat@example.test'
$env:ROOTINS_TEST_ADMIN_PASSWORD = '<local-only-password>'
php spark db:seed LocalAdminUatSeeder
```

The seeder is idempotent. It uses the first active local clinic and a doctor in
that clinic because the current `users` schema derives clinic context through
trusted doctor/patient relationships rather than a direct `users.clinic_id`.
It assigns the `ADMIN` role and only `settings.manage`, which is the permission
needed for the Master/Settings UAT pages.

Baseline roles, permissions, an active clinic, and a clinic doctor must exist
before running it.

## Disable or remove access

To disable the account without deleting records, set its `status` to `0` using
the Admin Users screen or a controlled local database maintenance procedure.
To remove the local UAT identity and its role assignment, use the Admin Users
screen or the approved local cleanup procedure. Do not run this seeder with
production credentials or against a production database.
