<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ensures the clinic-edit permission exists and is assigned only to the
 * active built-in Admin role.
 */
class EnsureClinicEditPermissionRecord extends Migration
{
    public function up()
    {
        $permission = $this->db->table('permissions')
            ->select('id')
            ->where('permission_key', 'clinic.edit')
            ->get(1)
            ->getRowArray();

        if ($permission === null) {
            $this->db->table('permissions')->insert([
                'module' => 'Clinic',
                'permission_name' => 'Edit Clinic',
                'permission_key' => 'clinic.edit',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $permission = ['id' => $this->db->insertID()];
        }

        $role = $this->db->table('roles')
            ->select('id')
            ->where('role_code', 'ADMIN')
            ->where('status', 1)
            ->get(1)
            ->getRowArray();

        if ($role === null) {
            return;
        }

        $mapping = $this->db->table('role_permissions')
            ->where('role_id', (int) $role['id'])
            ->where('permission_id', (int) $permission['id'])
            ->get(1)
            ->getRowArray();

        if ($mapping === null) {
            $this->db->table('role_permissions')->insert([
                'role_id' => (int) $role['id'],
                'permission_id' => (int) $permission['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $permission = $this->db->table('permissions')
            ->select('id')
            ->where('permission_key', 'clinic.edit')
            ->get(1)
            ->getRowArray();
        $role = $this->db->table('roles')
            ->select('id')
            ->where('role_code', 'ADMIN')
            ->get(1)
            ->getRowArray();

        if ($permission === null || $role === null) {
            return;
        }

        $this->db->table('role_permissions')
            ->where('role_id', (int) $role['id'])
            ->where('permission_id', (int) $permission['id'])
            ->delete();

        if ($this->db->table('role_permissions')
            ->where('permission_id', (int) $permission['id'])
            ->countAllResults() === 0) {
            $this->db->table('permissions')
                ->where('id', (int) $permission['id'])
                ->delete();
        }
    }
}
