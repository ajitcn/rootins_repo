<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddFarmassistOrderLabelPermissions extends Migration
{
    private const PERMISSIONS = [
        ['Order', 'View Pharmacy Order Labels', 'order.label.view'],
        ['Order', 'Print Pharmacy Order Labels', 'order.label.print'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as [$module, $name, $key]) {
            $permission = $this->db->table('permissions')
                ->where('permission_key', $key)
                ->get()
                ->getRowArray();

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

        $role = $this->db->table('roles')
            ->where('role_code', 'FARMASSIST')
            ->get()
            ->getRowArray();

        if ($role === null) {
            return;
        }

        foreach (self::PERMISSIONS as [, , $key]) {
            $permission = $this->db->table('permissions')
                ->where('permission_key', $key)
                ->get()
                ->getRowArray();

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

    public function down(): void
    {
        $role = $this->db->table('roles')
            ->where('role_code', 'FARMASSIST')
            ->get()
            ->getRowArray();
        $keys = array_column(self::PERMISSIONS, 2);
        $permissions = $this->db->table('permissions')
            ->select('id')
            ->whereIn('permission_key', $keys)
            ->get()
            ->getResultArray();

        if ($role !== null) {
            foreach ($permissions as $permission) {
                $this->db->table('role_permissions')
                    ->where('role_id', (int) $role['id'])
                    ->where('permission_id', (int) $permission['id'])
                    ->delete();
            }
        }

        foreach ($permissions as $permission) {
            $references = $this->db->table('role_permissions')
                ->where('permission_id', (int) $permission['id'])
                ->countAllResults();

            if ($references === 0) {
                $this->db->table('permissions')
                    ->where('id', (int) $permission['id'])
                    ->delete();
            }
        }
    }
}
