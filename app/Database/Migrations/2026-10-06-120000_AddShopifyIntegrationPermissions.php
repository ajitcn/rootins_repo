<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyIntegrationPermissions extends Migration
{
    private const PERMISSIONS = [
        ['Integration', 'View Shopify Integration', 'shopify.integration.view'],
        ['Integration', 'Manage Shopify Integration', 'shopify.integration.manage'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as [$module, $name, $key]) {
            $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();

            if ($permission === null) {
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

        $roles = $this->db->table('roles')->whereIn('role_code', ['SUPER_ADMIN', 'ADMIN'])->get()->getResultArray();

        foreach ($roles as $role) {
            foreach (self::PERMISSIONS as [$module, $name, $key]) {
                $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();

                if ($permission === null) {
                    continue;
                }

                $exists = $this->db->table('role_permissions')
                    ->where('role_id', (int) $role['id'])
                    ->where('permission_id', (int) $permission['id'])
                    ->countAllResults();

                if ($exists === 0) {
                    $this->db->table('role_permissions')->insert([
                        'role_id' => (int) $role['id'],
                        'permission_id' => (int) $permission['id'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permissions = $this->db->table('permissions')->select('id')->whereIn('permission_key', array_column(self::PERMISSIONS, 2))->get()->getResultArray();

        foreach ($permissions as $permission) {
            $this->db->table('role_permissions')->where('permission_id', (int) $permission['id'])->delete();
        }

        $this->db->table('permissions')->whereIn('permission_key', array_column(self::PERMISSIONS, 2))->delete();
    }
}
