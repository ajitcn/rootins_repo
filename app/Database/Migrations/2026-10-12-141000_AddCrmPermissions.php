<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCrmPermissions extends Migration
{
    public function up()
    {
        $permissions = ['crm.lead.view', 'crm.lead.create', 'crm.lead.edit', 'crm.lead.delete', 'crm.lead.convert', 'crm.followup.view', 'crm.followup.create', 'crm.followup.edit', 'crm.followup.complete', 'crm.followup.cancel', 'crm.history.view'];
        foreach ($permissions as $key) {
            if ($this->db->table('permissions')->where('permission_key', $key)->countAllResults() === 0) $this->db->table('permissions')->insert(['module' => 'CRM', 'permission_name' => ucwords(str_replace(['crm.', '.', '_'], ['- ', ' ', ' '], $key)), 'permission_key' => $key, 'description' => 'CRM access: ' . $key, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $admin = $this->db->table('roles')->where('role_name', 'Admin')->get()->getRowArray();
        if ($admin) foreach ($permissions as $key) { $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray(); if ($permission && $this->db->table('role_permissions')->where(['role_id' => $admin['id'], 'permission_id' => $permission['id']])->countAllResults() === 0) $this->db->table('role_permissions')->insert(['role_id' => $admin['id'], 'permission_id' => $permission['id'], 'created_at' => date('Y-m-d H:i:s')]); }
    }
    public function down() { }
}
