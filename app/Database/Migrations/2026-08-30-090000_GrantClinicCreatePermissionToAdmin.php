<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
/**
 * Grants the baseline clinic-create capability to the built-in Admin role.
 * The route remains protected by permission:clinic.create.
 */
class GrantClinicCreatePermissionToAdmin extends Migration
{
    public function up()
    {
        $role = $this->db->table('roles')->where('role_code', 'ADMIN')->get()->getRowArray();
        $permission = $this->db->table('permissions')->where('permission_key', 'clinic.create')->get()->getRowArray();
        if ($role === null || $permission === null) {
            return;
        }
        $mapping = $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission_id', (int) $permission['id'])->get()->getRowArray();
        if ($mapping === null) {
            $this->db->table('role_permissions')->insert(['role_id' => (int) $role['id'], 'permission_id' => (int) $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
        }
    }
    public function down()
    {
        $role = $this->db->table('roles')->where('role_code', 'ADMIN')->get()->getRowArray();
        $permission = $this->db->table('permissions')->where('permission_key', 'clinic.create')->get()->getRowArray();
        if ($role === null || $permission === null) {
            return;
        }
        $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission_id', (int) $permission['id'])->delete();
    }
}
