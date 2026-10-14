<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddFarmassistInventoryPermissions extends Migration
{
    private const PERMISSIONS = [
        ['Inventory', 'Create Inventory Batches', 'inventory.batch.create'],
        ['Inventory', 'Update Inventory Batches', 'inventory.batch.update'],
        ['Inventory', 'Adjust Inventory Batches', 'inventory.batch.adjust'],
        ['Inventory', 'Delete Inventory Batches', 'inventory.batch.delete'],
    ];

    public function up(): void
    {
        $role = $this->db->table('roles')->where('role_code', 'FARMASSIST')->get()->getRowArray();

        foreach (self::PERMISSIONS as [$module, $name, $key]) {
            $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();

            if ($permission === null) {
                $this->db->table('permissions')->insert(['module' => $module, 'permission_name' => $name, 'permission_key' => $key, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                $permission = ['id' => $this->db->insertID()];
            }

            if ($role !== null && $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission_id', (int) $permission['id'])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert(['role_id' => (int) $role['id'], 'permission_id' => (int) $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
            }
        }
    }

    public function down(): void
    {
        $keys = array_column(self::PERMISSIONS, 2);
        $permissions = $this->db->table('permissions')->select('id')->whereIn('permission_key', $keys)->get()->getResultArray();

        foreach ($permissions as $permission) {
            $this->db->table('role_permissions')->where('permission_id', (int) $permission['id'])->delete();
        }
        $this->db->table('permissions')->whereIn('permission_key', $keys)->delete();
    }
}
