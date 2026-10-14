<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPaymentProviderPermissions extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module' => 'Payment Provider', 'permission_name' => 'View Payment Providers', 'permission_key' => 'payment_provider.view'],
            ['module' => 'Payment Provider', 'permission_name' => 'Manage Payment Providers', 'permission_key' => 'payment_provider.manage'],
        ];
        foreach ($permissions as $permission) {
            if (!$this->db->table('permissions')->where('permission_key', $permission['permission_key'])->countAllResults()) {
                $this->db->table('permissions')->insert($permission);
            }
        }
        $admin = $this->db->table('roles')->where('role_name', 'ADMIN')->get()->getRowArray();
        if (!$admin) return;
        foreach ($permissions as $permission) {
            $row = $this->db->table('permissions')->where('permission_key', $permission['permission_key'])->get()->getRowArray();
            if ($row && !$this->db->table('role_permissions')->where(['role_id' => $admin['id'], 'permission_id' => $row['id']])->countAllResults()) {
                $this->db->table('role_permissions')->insert(['role_id' => $admin['id'], 'permission_id' => $row['id']]);
            }
        }
    }

    public function down(): void
    {
        // Permission rows are intentionally retained for reversible deployments.
    }
}
