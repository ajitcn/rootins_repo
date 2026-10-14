<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCrmMasterPermissions extends Migration
{
    public function up()
    {
        $permissions = ['crm.master.view', 'crm.master.create', 'crm.master.edit', 'crm.master.archive'];
        foreach (['lead_status', 'lead_source', 'lead_priority', 'followup_type', 'followup_status'] as $resource) {
            foreach (['view', 'create', 'edit', 'archive'] as $action) $permissions[] = 'crm.' . $resource . '.' . $action;
        }
        foreach ($permissions as $key) {
            if (! $this->db->table('permissions')->where('permission_key', $key)->countAllResults()) $this->db->table('permissions')->insert(['module' => 'CRM', 'permission_name' => ucwords(str_replace(['crm.', '.', '_'], ['', ' ', ' '], $key)), 'permission_key' => $key, 'description' => 'CRM master access: ' . $key, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $admin = $this->db->table('roles')->where('role_name', 'Admin')->get()->getRowArray();
        if ($admin) foreach ($permissions as $key) {
            $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();
            if ($permission && ! $this->db->table('role_permissions')->where(['role_id' => $admin['id'], 'permission_id' => $permission['id']])->countAllResults()) $this->db->table('role_permissions')->insert(['role_id' => $admin['id'], 'permission_id' => $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down() {}
}
