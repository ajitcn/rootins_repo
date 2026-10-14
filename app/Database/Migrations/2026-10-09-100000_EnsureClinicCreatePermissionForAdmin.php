<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Repairs the Admin clinic-create mapping for installations where the
 * original permission grant migration was already recorded without its row.
 */
class EnsureClinicCreatePermissionForAdmin extends Migration
{
    public function up()
    {
        $role = $this->db->table('roles')
            ->select('id')
            ->where('role_code', 'ADMIN')
            ->where('status', 1)
            ->get(1)
            ->getRowArray();
        $permission = $this->db->table('permissions')
            ->select('id')
            ->where('permission_key', 'clinic.create')
            ->where('status', 1)
            ->get(1)
            ->getRowArray();

        if ($role === null || $permission === null) {
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
        $role = $this->db->table('roles')->select('id')->where('role_code', 'ADMIN')->get(1)->getRowArray();
        $permission = $this->db->table('permissions')->select('id')->where('permission_key', 'clinic.create')->get(1)->getRowArray();

        if ($role === null || $permission === null) {
            return;
        }

        $this->db->table('role_permissions')
            ->where('role_id', (int) $role['id'])
            ->where('permission_id', (int) $permission['id'])
            ->delete();
    }
}
