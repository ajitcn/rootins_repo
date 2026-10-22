<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyOrderOperationsPermission extends Migration
{
    public function up(): void
    {
        $permission = $this->db->table('permissions')->where('permission_key', 'shopify.order.operations')->get()->getRowArray();
        if ($permission === null) {
            $this->db->table('permissions')->insert([
                'module' => 'Integration',
                'permission_name' => 'Shopify Order Operations',
                'permission_key' => 'shopify.order.operations',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $permission = $this->db->table('permissions')->where('permission_key', 'shopify.order.operations')->get()->getRowArray();
        }
        if ($permission === null) return;

        foreach ($this->db->table('roles')->whereIn('role_code', ['SUPER_ADMIN', 'ADMIN'])->get()->getResultArray() as $role) {
            $exists = $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission_id', (int) $permission['id'])->countAllResults();
            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => (int) $role['id'],
                    'permission_id' => (int) $permission['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permission = $this->db->table('permissions')->where('permission_key', 'shopify.order.operations')->get()->getRowArray();
        if ($permission !== null) {
            $this->db->table('role_permissions')->where('permission_id', (int) $permission['id'])->delete();
            $this->db->table('permissions')->where('id', (int) $permission['id'])->delete();
        }
    }
}
