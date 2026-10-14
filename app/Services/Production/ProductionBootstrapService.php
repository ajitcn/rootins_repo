<?php

namespace App\Services\Production;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Idempotent production bootstrap for identity, authorization, and reference
 * catalogs. It deliberately does not create clinics, clinical records,
 * financial records, inventory, API credentials, or Shopify records.
 */
final class ProductionBootstrapService
{
    private const ROLES = [
        ['role_name' => 'Super Admin', 'role_code' => 'SUPER_ADMIN', 'is_system' => 1],
        ['role_name' => 'Admin', 'role_code' => 'ADMIN', 'is_system' => 1],
        ['role_name' => 'Doctor', 'role_code' => 'DOCTOR', 'is_system' => 1],
        ['role_name' => 'Receptionist', 'role_code' => 'RECEPTIONIST', 'is_system' => 1],
        ['role_name' => 'Telecaller', 'role_code' => 'TELECALLER', 'is_system' => 1],
        ['role_name' => 'Pharmacy Assistant', 'role_code' => 'PHARMACY_ASSISTANT', 'is_system' => 1],
        ['role_name' => 'Accountant', 'role_code' => 'ACCOUNTANT', 'is_system' => 1],
    ];

    /** These are the established application permission keys, not new grants to non-admin roles. */
    private const PERMISSION_KEYS = [
        'clinic.view', 'clinic.create', 'clinic.edit', 'clinic.delete',
        'user.view', 'user.create', 'user.edit', 'user.delete',
        'doctor.view', 'doctor.create', 'doctor.edit', 'doctor.delete',
        'doctor_schedule.view', 'doctor_schedule.create', 'doctor_schedule.edit', 'doctor_schedule.update', 'doctor_schedule.delete',
        'doctor_leave.view', 'doctor_leave.create', 'doctor_leave.update', 'doctor_leave.delete',
        'doctor_fee.view', 'doctor_fee.create', 'doctor_fee.edit', 'doctor_fee.update', 'doctor_fee.delete',
        'doctor_commission.view', 'doctor_commission.create', 'doctor_commission.edit', 'doctor_commission.update', 'doctor_commission.delete',
        'doctor_document.view', 'doctor_document.create', 'doctor_document.edit', 'doctor_document.update', 'doctor_document.delete',
        'patient.view', 'patient.create', 'patient.edit', 'patient.delete',
        'appointment.view', 'appointment.create', 'appointment.edit', 'appointment.delete',
        'consultation.view', 'consultation.create', 'consultation.edit', 'consultation.delete',
        'prescription.view', 'prescription.create', 'prescription.edit', 'prescription.delete',
        'medicine.view', 'medicine.create', 'medicine.edit', 'medicine.delete',
        'role.view', 'role.manage', 'settings.manage', 'audit.view',
        'billing.view', 'billing.create', 'billing.edit',
        'delivery_provider.view', 'delivery_provider.manage',
        'payment_status.view', 'payment_status.manage', 'payment_provider.view', 'payment_provider.manage',
        'core_api.view', 'core_api.create', 'core_api.manage',
        'shopify.integration.view', 'shopify.integration.manage', 'shopify.order.import', 'shopify.order.reconcile',
        'pharmacy.dashboard.view', 'pharmacy.order.view', 'pharmacy.order.create', 'pharmacy.order.cancel',
        'pharmacy.inventory.view', 'pharmacy.inventory.batch.create', 'pharmacy.inventory.batch.adjust',
        'pharmacy.medicine.edit', 'pharmacy.prescription.review.view',
        'pharmacy.billing.view', 'pharmacy.billing.payment.update', 'pharmacy.billing.refund',
        'pharmacy.delivery.view', 'pharmacy.delivery.update', 'pharmacy.delivery.override',
        'pharmacy.notification.view', 'pharmacy.audit.view', 'pharmacy.order.label.view', 'pharmacy.order.label.print',
        'crm.lead.view', 'crm.lead.create', 'crm.lead.edit', 'crm.lead.delete', 'crm.lead.convert',
        'crm.followup.view', 'crm.followup.create', 'crm.followup.edit', 'crm.followup.complete', 'crm.followup.cancel',
        'crm.history.view', 'crm.master.view', 'crm.master.create', 'crm.master.edit', 'crm.master.archive',
        'crm.master.lead_status.view', 'crm.master.lead_status.create', 'crm.master.lead_status.edit', 'crm.master.lead_status.archive',
        'crm.master.lead_source.view', 'crm.master.lead_source.create', 'crm.master.lead_source.edit', 'crm.master.lead_source.archive',
        'crm.master.lead_priority.view', 'crm.master.lead_priority.create', 'crm.master.lead_priority.edit', 'crm.master.lead_priority.archive',
        'crm.master.followup_type.view', 'crm.master.followup_type.create', 'crm.master.followup_type.edit', 'crm.master.followup_type.archive',
        'crm.master.followup_status.view', 'crm.master.followup_status.create', 'crm.master.followup_status.edit', 'crm.master.followup_status.archive',
    ];

    private const REFERENCE_DATA = [
        'medicine_forms' => [['name' => 'Tablet'], ['name' => 'Capsule'], ['name' => 'Syrup'], ['name' => 'Injection'], ['name' => 'Cream'], ['name' => 'Drops']],
        'medicine_units' => [['name' => 'mg'], ['name' => 'g'], ['name' => 'ml'], ['name' => 'tablet'], ['name' => 'capsule'], ['name' => 'piece']],
        'medicine_manufacturers' => [['name' => 'Generic']],
        'medicine_strengths' => [['name' => '250 mg'], ['name' => '500 mg'], ['name' => '5 ml']],
        'medicine_status_options' => [['code' => 'active', 'name' => 'Active'], ['code' => 'inactive', 'name' => 'Inactive']],
        'doctor_specializations' => [['name' => 'General Medicine'], ['name' => 'Cardiology']],
        'follow_up_types' => [['name' => 'Routine'], ['name' => 'Review'], ['name' => 'Other']],
        'follow_up_statuses' => [['code' => 'draft', 'name' => 'Draft'], ['code' => 'in_progress', 'name' => 'In progress'], ['code' => 'completed', 'name' => 'Completed'], ['code' => 'cancelled', 'name' => 'Cancelled']],
        'crm_lead_statuses' => [['code' => 'new', 'name' => 'New'], ['code' => 'contacted', 'name' => 'Contacted'], ['code' => 'interested', 'name' => 'Interested'], ['code' => 'appointment_booked', 'name' => 'Appointment booked'], ['code' => 'converted', 'name' => 'Converted'], ['code' => 'not_interested', 'name' => 'Not interested'], ['code' => 'lost', 'name' => 'Lost'], ['code' => 'closed', 'name' => 'Closed']],
        'crm_lead_sources' => [['code' => 'website', 'name' => 'Website'], ['code' => 'referral', 'name' => 'Referral'], ['code' => 'phone', 'name' => 'Phone'], ['code' => 'walk_in', 'name' => 'Walk in'], ['code' => 'social_media', 'name' => 'Social media'], ['code' => 'campaign', 'name' => 'Campaign'], ['code' => 'other', 'name' => 'Other']],
        'crm_lead_priorities' => [['code' => 'low', 'name' => 'Low'], ['code' => 'normal', 'name' => 'Normal'], ['code' => 'high', 'name' => 'High'], ['code' => 'urgent', 'name' => 'Urgent']],
        'prescription_durations' => [['code' => '1', 'name' => '1 day'], ['code' => '3', 'name' => '3 days'], ['code' => '7', 'name' => '7 days'], ['code' => '14', 'name' => '14 days'], ['code' => '30', 'name' => '30 days']],
        'prescription_frequencies' => [['code' => 'once_daily', 'name' => 'Once daily'], ['code' => 'twice_daily', 'name' => 'Twice daily'], ['code' => 'as_needed', 'name' => 'As needed']],
        'prescription_doses' => [['code' => '1_tablet', 'name' => '1 tablet'], ['code' => '1_capsule', 'name' => '1 capsule'], ['code' => '5_ml', 'name' => '5 ml']],
        'prescription_intakes' => [['code' => 'before_food', 'name' => 'Before food'], ['code' => 'after_food', 'name' => 'After food'], ['code' => 'with_food', 'name' => 'With food']],
        'prescription_status_options' => [['code' => 'draft', 'name' => 'Draft'], ['code' => 'submitted', 'name' => 'Submitted']],
        'admin_departments' => [['name' => 'Outpatient'], ['name' => 'Pharmacy']],
        'admin_services' => [['name' => 'General Consultation'], ['name' => 'Tele-consultation']],
        'payment_modes' => [['name' => 'Cash'], ['name' => 'Online']],
        'tax_rates' => [['name' => 'No Tax', 'code' => 'no_tax', 'amount' => 0]],
    ];

    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    /** @return array{created:int,existing:int,skipped:int,failed:int} */
    public function run(array $input, bool $dryRun = false): array
    {
        $this->validateInput($input);
        $db = $this->db ?? db_connect();
        $summary = ['created' => 0, 'existing' => 0, 'skipped' => 0, 'failed' => 0];
        $transactionStarted = false;

        try {
            $this->requireTables($db);
            $clinic = $this->activeClinic($db, (int) $input['clinic_id']);
            if ($clinic === null) {
                throw new InvalidArgumentException('ROOTINS_ADMIN_CLINIC_ID must reference an active clinic.');
            }

            if (! $dryRun) {
                $db->transBegin();
                $transactionStarted = true;
            }

            $roles = $this->ensureRoles($db, $summary, $dryRun);
            $permissions = $this->ensurePermissions($db, $summary, $dryRun);
            // A dry run may be evaluating a completely empty database, so use a
            // non-persisted placeholder for dependency planning only.
            $superAdminRoleId = (int) ($roles['SUPER_ADMIN'] ?? ($dryRun ? 1 : 0));
            if ($superAdminRoleId < 1) {
                throw new RuntimeException('The Super Admin role could not be resolved.');
            }
            $this->ensureSuperAdminPermissions($db, $superAdminRoleId, $permissions, $summary, $dryRun);
            $userId = $this->ensureAdminUser($db, $input, $summary, $dryRun);
            $this->ensureUserRole($db, $userId, $superAdminRoleId, $summary, $dryRun);
            $this->ensureReferences($db, $summary, $dryRun);

            if ($transactionStarted) {
                if ($db->transStatus() === false) {
                    throw new RuntimeException('Production bootstrap transaction failed.');
                }
                $db->transCommit();
            }

            return $summary;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $db->transRollback();
            }
            throw $exception;
        }
    }

    private function validateInput(array $input): void
    {
        foreach (['name', 'email', 'password', 'clinic_id'] as $key) {
            if (! isset($input[$key]) || trim((string) $input[$key]) === '') {
                throw new InvalidArgumentException('Missing required bootstrap environment variable: ROOTINS_ADMIN_' . strtoupper($key) . '.');
            }
        }
        if (! filter_var($input['email'], FILTER_VALIDATE_EMAIL) || strlen((string) $input['email']) > 150) {
            throw new InvalidArgumentException('ROOTINS_ADMIN_EMAIL is invalid.');
        }
        if (strlen((string) $input['password']) < 10) {
            throw new InvalidArgumentException('ROOTINS_ADMIN_PASSWORD must be at least 10 characters.');
        }
        if (! ctype_digit((string) $input['clinic_id']) || (int) $input['clinic_id'] < 1) {
            throw new InvalidArgumentException('ROOTINS_ADMIN_CLINIC_ID is invalid.');
        }
    }

    private function requireTables(BaseConnection $db): void
    {
        foreach (['clinics', 'roles', 'permissions', 'role_permissions', 'users', 'user_roles'] as $table) {
            if (! $db->tableExists($table)) {
                throw new RuntimeException('Required migrations are not complete. Missing bootstrap table: ' . $table . '.');
            }
        }
    }

    private function activeClinic(BaseConnection $db, int $clinicId): ?array
    {
        return $db->table('clinics')->select('id')->where('id', $clinicId)->where('status', 1)->get(1)->getRowArray() ?: null;
    }

    /** @return array<string, int> */
    private function ensureRoles(BaseConnection $db, array &$summary, bool $dryRun): array
    {
        $roles = [];
        foreach (self::ROLES as $role) {
            $row = $db->table('roles')->where('role_code', $role['role_code'])->get(1)->getRowArray();
            if ($row !== null) {
                $roles[$role['role_code']] = (int) $row['id'];
                $summary['existing']++;
                continue;
            }
            $summary['created']++;
            if (! $dryRun) {
                $db->table('roles')->insert($role + ['description' => null, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                $roles[$role['role_code']] = (int) $db->insertID();
            } else {
                $roles[$role['role_code']] = 0;
            }
        }
        return $roles;
    }

    /** @return array<string, int> */
    private function ensurePermissions(BaseConnection $db, array &$summary, bool $dryRun): array
    {
        $permissions = [];
        foreach (self::PERMISSION_KEYS as $key) {
            $row = $db->table('permissions')->where('permission_key', $key)->get(1)->getRowArray();
            if ($row !== null) {
                $permissions[$key] = (int) $row['id'];
                $summary['existing']++;
                continue;
            }
            $summary['created']++;
            if (! $dryRun) {
                $name = ucwords(str_replace(['.', '_'], [' ', ' '], $key));
                $db->table('permissions')->insert(['module' => ucwords(strtok($key, '.')), 'permission_name' => $name, 'permission_key' => $key, 'description' => 'System permission: ' . $key, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                $permissions[$key] = (int) $db->insertID();
            }
        }
        return $permissions;
    }

    private function ensureSuperAdminPermissions(BaseConnection $db, int $roleId, array $permissions, array &$summary, bool $dryRun): void
    {
        // Existing migrations may contain additional established permissions. The
        // bootstrap grants the complete current permission catalog only to the
        // Super Admin role; it never grants anything to other roles.
        if (! $dryRun) {
            foreach ($db->table('permissions')->select('id')->where('status', 1)->get()->getResultArray() as $row) {
                $permissions['__existing_' . (int) $row['id']] = (int) $row['id'];
            }
        }
        foreach ($permissions as $permissionId) {
            if ($permissionId < 1) {
                $summary['skipped']++;
                continue;
            }
            $exists = $db->table('role_permissions')->where(['role_id' => $roleId, 'permission_id' => $permissionId])->countAllResults() > 0;
            if ($exists) {
                $summary['existing']++;
                continue;
            }
            $summary['created']++;
            if (! $dryRun) {
                $db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => date('Y-m-d H:i:s')]);
            }
        }
    }

    private function ensureAdminUser(BaseConnection $db, array $input, array &$summary, bool $dryRun): int
    {
        $email = strtolower(trim((string) $input['email']));
        $existing = $db->table('users')->where('email', $email)->get(1)->getRowArray();
        if ($existing !== null) {
            $summary['existing']++;
            return (int) $existing['id'];
        }
        $username = $this->uniqueUsername($db, (string) $input['name'], $email);
        $summary['created']++;
        if ($dryRun) {
            return 0;
        }
        $db->table('users')->insert([
            'username' => $username,
            'password' => password_hash((string) $input['password'], PASSWORD_DEFAULT),
            'email' => $email,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->insertID();
    }

    private function ensureUserRole(BaseConnection $db, int $userId, int $roleId, array &$summary, bool $dryRun): void
    {
        if ($userId < 1 || $roleId < 1) {
            $summary['skipped']++;
            return;
        }
        $exists = $db->table('user_roles')->where(['user_id' => $userId, 'role_id' => $roleId])->countAllResults() > 0;
        if ($exists) {
            $summary['existing']++;
            return;
        }
        $summary['created']++;
        if (! $dryRun) {
            $db->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    private function ensureReferences(BaseConnection $db, array &$summary, bool $dryRun): void
    {
        foreach (self::REFERENCE_DATA as $table => $rows) {
            if (! $db->tableExists($table)) {
                $summary['skipped']++;
                continue;
            }
            foreach ($rows as $row) {
                $criterion = $this->referenceCriterion($db, $table, $row);
                $builder = $db->table($table)->where($criterion);
                if ($db->fieldExists('scope_key', $table)) {
                    $builder->where('scope_key', 'global');
                }
                if ($builder->countAllResults() > 0) {
                    $summary['existing']++;
                    continue;
                }
                $summary['created']++;
                if (! $dryRun) {
                    $this->insertReference($db, $table, $row);
                }
            }
        }
    }

    private function insertReference(BaseConnection $db, string $table, array $row): void
    {
        $now = date('Y-m-d H:i:s');
        $data = [];
        foreach ($row as $field => $value) {
            if ($db->fieldExists($field, $table)) {
                $data[$field] = $value;
            }
        }
        if ($db->fieldExists('specialization_name', $table) && isset($row['name'])) {
            $data['specialization_name'] = $row['name'];
        }
        if ($db->fieldExists('normalized_name', $table) && isset($row['name'])) $data['normalized_name'] = strtolower(trim((string) $row['name']));
        if ($db->fieldExists('normalized_code', $table) && isset($row['code'])) $data['normalized_code'] = strtolower(trim((string) $row['code']));
        foreach (['status', 'scope_key', 'created_at', 'updated_at'] as $field) {
            if ($db->fieldExists($field, $table)) $data[$field] = $field === 'scope_key' ? 'global' : ($field === 'status' ? 1 : $now);
        }
        if ($db->fieldExists('clinic_id', $table)) $data['clinic_id'] = null;
        $db->table($table)->insert($data);
    }

    private function referenceCriterion(BaseConnection $db, string $table, array $row): array
    {
        if (isset($row['code'])) {
            if ($db->fieldExists('normalized_code', $table)) {
                return ['normalized_code' => strtolower((string) $row['code'])];
            }
            if ($db->fieldExists('code', $table)) {
                return ['code' => (string) $row['code']];
            }
        }
        if ($db->fieldExists('normalized_name', $table)) {
            return ['normalized_name' => strtolower((string) ($row['name'] ?? ''))];
        }
        if ($db->fieldExists('name', $table)) {
            return ['name' => (string) ($row['name'] ?? '')];
        }
        if ($db->fieldExists('specialization_name', $table)) {
            return ['specialization_name' => (string) ($row['name'] ?? '')];
        }
        return ['id' => -1];
    }

    private function uniqueUsername(BaseConnection $db, string $name, string $email): string
    {
        $base = strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]+/i', '_', $base) ?: strtolower((string) strtok($email, '@'));
        $base = trim($base, '_');
        $base = substr($base !== '' ? $base : 'rootins_admin', 0, 90);
        $candidate = $base;
        $suffix = 1;
        while ($db->table('users')->where('username', $candidate)->countAllResults() > 0) {
            $candidate = substr($base, 0, 85) . '_' . $suffix++;
        }
        return $candidate;
    }
}
