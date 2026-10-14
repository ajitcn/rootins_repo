<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class RenameDeliveryOverridePermissionAndAssignRoles extends Migration
{
    private const NEW_KEY = 'delivery.status.override';
    private const OLD_KEY = 'pharmacy.delivery.override';
    private const AUTHORIZED_ROLES = ['SUPER_ADMIN', 'ADMIN', 'PHARMACY_ASSISTANT'];

    public function up(): void
    {
        $this->db->transStart();
        $permission = $this->ensurePermission();
        $roles = $this->db->table('roles')->groupStart()->whereIn('role_code', self::AUTHORIZED_ROLES)->orWhere('role_name', 'Pharmacy Assistant')->groupEnd()->where('status', 1)->get()->getResultArray();

        foreach ($roles as $role) {
            $exists = $this->db->table('role_permissions')->where(['role_id' => (int) $role['id'], 'permission_id' => (int) $permission['id']])->countAllResults() > 0;
            if (!$exists) {
                $this->db->table('role_permissions')->insert(['role_id' => (int) $role['id'], 'permission_id' => (int) $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
            }
        }

        $old = $this->db->table('permissions')->where('permission_key', self::OLD_KEY)->get(1)->getRowArray();
        if ($old !== null) {
            $this->db->table('role_permissions')->where('permission_id', (int) $old['id'])->delete();
            $this->db->table('permissions')->where('id', (int) $old['id'])->delete();
        }
        $this->db->transComplete();
    }

    public function down(): void
    {
        $this->db->transStart();
        $new = $this->db->table('permissions')->where('permission_key', self::NEW_KEY)->get(1)->getRowArray();
        if ($new !== null) {
            $this->db->table('role_permissions')->where('permission_id', (int) $new['id'])->delete();
            $this->db->table('permissions')->where('id', (int) $new['id'])->delete();
        }
        if ($this->db->table('permissions')->where('permission_key', self::OLD_KEY)->countAllResults() === 0) {
            $this->db->table('permissions')->insert(['module' => 'Pharmacy Delivery', 'permission_name' => 'Override Delivery Status', 'permission_key' => self::OLD_KEY, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->db->transComplete();
    }

    private function ensurePermission(): array
    {
        $permission = $this->db->table('permissions')->where('permission_key', self::NEW_KEY)->get(1)->getRowArray();
        if ($permission === null) {
            $this->db->table('permissions')->insert(['module' => 'Pharmacy Delivery', 'permission_name' => 'Override Delivery Status', 'permission_key' => self::NEW_KEY, 'description' => 'Allow authorized Pharmacy staff to override delivery status transitions.', 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            $permission = $this->db->table('permissions')->where('permission_key', self::NEW_KEY)->get(1)->getRowArray();
        }
        return $permission;
    }
}
