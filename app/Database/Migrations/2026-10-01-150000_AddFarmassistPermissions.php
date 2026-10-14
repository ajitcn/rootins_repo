<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddFarmassistPermissions extends Migration
{
    private const PERMISSIONS = [
        ['Farmassist', 'View Farmassist Dashboard', 'farmassist.dashboard.view'],
        ['Prescription Review', 'View Prescription Review', 'prescription.review.view'],
        ['Prescription Review', 'Approve Prescription Review', 'prescription.review.approve'],
        ['Prescription Review', 'Reject Prescription Review', 'prescription.review.reject'],
        ['Prescription Review', 'Clarify Prescription Review', 'prescription.review.clarify'],
        ['Inventory', 'View Inventory', 'inventory.view'],
        ['Order', 'View Orders', 'order.view'],
        ['Order', 'Create Orders', 'order.create'],
        ['Order', 'Cancel Orders', 'order.cancel'],
        ['Billing', 'View Pharmacy Billing', 'billing.view'],
        ['Billing', 'Update Farmassist Payments', 'billing.payment.update'],
        ['Billing', 'Refund Farmassist Payments', 'billing.refund'],
        ['Delivery', 'View Deliveries', 'delivery.view'],
        ['Delivery', 'Update Deliveries', 'delivery.update'],
        ['Notification', 'View Notifications', 'notification.view'],
    ];

    // Existing Farmassist routes still use these generic permissions. They are
    // granted only to FarmAssist to preserve those route contracts.
    private const COMPATIBILITY_PERMISSIONS = [
        'prescription.view',
        'prescription.edit',
        'medicine.create',
        'medicine.edit',
        'audit.view',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as [$module, $name, $key]) {
            $this->ensurePermission($module, $name, $key);
        }

        $farmAssist = $this->db->table('roles')->where('role_code', 'FARMASSIST')->get()->getRowArray();

        if ($farmAssist === null) {
            return;
        }

        $keys = array_merge(array_column(self::PERMISSIONS, 2), self::COMPATIBILITY_PERMISSIONS);

        foreach ($keys as $key) {
            $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();

            if ($permission === null) {
                continue;
            }
            $exists = $this->db->table('role_permissions')
                ->where('role_id', (int) $farmAssist['id'])
                ->where('permission_id', (int) $permission['id'])
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => (int) $farmAssist['id'],
                    'permission_id' => (int) $permission['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        $farmAssist = $this->db->table('roles')->where('role_code', 'FARMASSIST')->get()->getRowArray();

        if ($farmAssist !== null) {
            $keys = array_merge(array_column(self::PERMISSIONS, 2), self::COMPATIBILITY_PERMISSIONS);
            $permissions = $this->db->table('permissions')->select('id')->whereIn('permission_key', $keys)->get()->getResultArray();

            foreach ($permissions as $permission) {
                $this->db->table('role_permissions')->where('role_id', (int) $farmAssist['id'])->where('permission_id', (int) $permission['id'])->delete();
            }
        }

        $this->db->table('permissions')->whereIn('permission_key', array_column(self::PERMISSIONS, 2))->delete();
    }

    private function ensurePermission(string $module, string $name, string $key): void
    {
        $exists = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();

        if ($exists !== null) {
            return;
        }
        $this->db->table('permissions')->insert([
            'module' => $module,
            'permission_name' => $name,
            'permission_key' => $key,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
