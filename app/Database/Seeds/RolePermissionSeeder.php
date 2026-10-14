<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        // Super Admin gets every permission
        $permissions = $db->table('permissions')->get()->getResultArray();
        $rows = [];

        foreach ($permissions as $permission) {
            $rows[] = [
                'role_id' => 1,
                // Super Admin/Admin
                'permission_id' => $permission['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        $db->table('role_permissions')->insertBatch($rows);
    }
}
