<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class AddClinicViewPermissionForAdmins extends Migration
{
    public function up()
    {
        $permissions = $this->db->table('permissions');
        $permission = $permissions->where('permission_key', 'clinic.view')->get()->getRowArray();
        if ($permission === null) {
            $permissions->insert(['module' => 'Clinic', 'permission_name' => 'View Clinics', 'permission_key' => 'clinic.view', 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            $permission = ['id' => $this->db->insertID()];
        }
        foreach (['SUPER_ADMIN', 'ADMIN'] as $roleCode) {
            $role = $this->db->table('roles')->where('role_code', $roleCode)->get()->getRowArray();
            if ($role === null) {
                continue;
            }
            $mapping = $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission_id', (int) $permission['id'])->get()->getRowArray();
            if ($mapping === null) {
                $this->db->table('role_permissions')->insert(['role_id' => (int) $role['id'], 'permission_id' => (int) $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
            }
        }
    }
    public function down()
    {
        $permission = $this->db->table('permissions')->where('permission_key', 'clinic.view')->get()->getRowArray();
        if ($permission === null) {
            return;
        }
        $roles = $this->db->table('roles')->whereIn('role_code', ['SUPER_ADMIN', 'ADMIN'])->get()->getResultArray();
        foreach ($roles as $role) {
            $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission_id', (int) $permission['id'])->delete();
        }
        $this->db->table('permissions')->where('id', (int) $permission['id'])->delete();
    }
}
