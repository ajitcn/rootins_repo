<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Repairs Pharmacy Assistant grants after the Farmassist identity migration.
 *
 * This is intentionally separate from the rename migration so installations
 * that already recorded the rename can safely repair incomplete mappings.
 */
final class RepairPharmacyRolePermissions extends Migration
{
    private const PERMISSIONS = [
        'pharmacy.dashboard.view',
        'pharmacy.prescription.review.view',
        'pharmacy.prescription.review.approve',
        'pharmacy.prescription.review.reject',
        'pharmacy.prescription.review.clarify',
        'pharmacy.prescription.review.cancel',
        'pharmacy.prescription.view',
        'pharmacy.prescription.edit',
        'pharmacy.inventory.view',
        'pharmacy.inventory.batch.create',
        'pharmacy.inventory.batch.update',
        'pharmacy.inventory.batch.adjust',
        'pharmacy.inventory.batch.delete',
        'pharmacy.order.view',
        'pharmacy.order.create',
        'pharmacy.order.cancel',
        'pharmacy.order.label.view',
        'pharmacy.order.label.print',
        'pharmacy.billing.view',
        'pharmacy.billing.payment.update',
        'pharmacy.billing.refund',
        'pharmacy.delivery.view',
        'pharmacy.delivery.update',
        'pharmacy.notification.view',
        'pharmacy.audit.view',
        'pharmacy.medicine.create',
        'pharmacy.medicine.edit',
    ];

    public function up(): void
    {
        $this->db->transStart();
        $this->createRepairLogTable();

        $role = $this->db->table('roles')
            ->where('role_code', 'PHARMACY_ASSISTANT')
            ->get()->getRowArray();

        if ($role === null) {
            $role = $this->db->table('roles')
                ->groupStart()
                    ->whereIn('role_code', ['FARMASSIST'])
                    ->orWhereIn('role_name', ['FarmAssist', 'Farmassist', 'Pharmacy Assistant'])
                ->groupEnd()
                ->get()->getRowArray();
        }

        if ($role !== null) {
            $roleId = (int) $role['id'];
            $this->db->table('roles')->where('id', $roleId)->update([
                'role_name' => 'Pharmacy Assistant',
                'role_code' => 'PHARMACY_ASSISTANT',
            ]);

            foreach (self::PERMISSIONS as $permissionKey) {
                $permission = $this->db->table('permissions')
                    ->where('permission_key', $permissionKey)
                    ->get()->getRowArray();

                if ($permission === null) {
                    $this->db->table('permissions')->insert([
                        'module' => 'Pharmacy',
                        'permission_name' => $this->label($permissionKey),
                        'permission_key' => $permissionKey,
                        'description' => null,
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $permission = $this->db->table('permissions')
                        ->where('permission_key', $permissionKey)
                        ->get()->getRowArray();
                }

                if ($permission === null) {
                    continue;
                }

                $permissionId = (int) $permission['id'];
                $exists = $this->db->table('role_permissions')
                    ->where(['role_id' => $roleId, 'permission_id' => $permissionId])
                    ->countAllResults() > 0;

                if (!$exists) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->db->table('pharmacy_permission_repair_log')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        $this->db->transComplete();
    }

    public function down(): void
    {
        $this->db->transStart();

        if ($this->db->tableExists('pharmacy_permission_repair_log')) {
            $repairs = $this->db->table('pharmacy_permission_repair_log')->get()->getResultArray();
            foreach ($repairs as $repair) {
                $this->db->table('role_permissions')->where([
                    'role_id' => (int) $repair['role_id'],
                    'permission_id' => (int) $repair['permission_id'],
                ])->delete();
            }
            $this->forge->dropTable('pharmacy_permission_repair_log', true);
        }

        $this->db->transComplete();
    }

    private function createRepairLogTable(): void
    {
        if ($this->db->tableExists('pharmacy_permission_repair_log')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'role_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'permission_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['role_id', 'permission_id']);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pharmacy_permission_repair_log', true);
    }

    private function label(string $permissionKey): string
    {
        return ucwords(str_replace(['.', '_'], ' ', preg_replace('/^pharmacy\./', '', $permissionKey)));
    }
}
