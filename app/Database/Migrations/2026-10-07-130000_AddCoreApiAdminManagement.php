<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCoreApiAdminManagement extends Migration
{
    private const PERMISSIONS = [
        ['Integration', 'View Core API', 'core_api.view'],
        ['Integration', 'Create Core API Credential', 'core_api.create'],
        ['Integration', 'Manage Core API Credential', 'core_api.manage'],
    ];

    public function up(): void
    {
        if ($this->db->tableExists('core_api_credentials') && ! $this->db->fieldExists('last_used_at', 'core_api_credentials')) {
            $this->forge->addColumn('core_api_credentials', ['last_used_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'rate_limit_per_minute']]);
        }

        foreach (self::PERMISSIONS as [$module, $name, $key]) {
            if ($this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray() === null) {
                $this->db->table('permissions')->insert(['module' => $module, 'permission_name' => $name, 'permission_key' => $key, 'description' => 'Manage the ROOTINS Core API integration.', 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }

        $roles = $this->db->table('roles')->whereIn('role_code', ['SUPER_ADMIN', 'ADMIN'])->get()->getResultArray();
        foreach ($roles as $role) {
            foreach (self::PERMISSIONS as $permission) {
                $record = $this->db->table('permissions')->where('permission_key', $permission[2])->get()->getRowArray();
                if ($record !== null && $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission_id', (int) $record['id'])->countAllResults() === 0) {
                    $this->db->table('role_permissions')->insert(['role_id' => (int) $role['id'], 'permission_id' => (int) $record['id'], 'created_at' => date('Y-m-d H:i:s')]);
                }
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('core_api_credentials') && $this->db->fieldExists('last_used_at', 'core_api_credentials')) {
            $this->forge->dropColumn('core_api_credentials', 'last_used_at');
        }
        $permissionIds = $this->db->table('permissions')->select('id')->whereIn('permission_key', array_column(self::PERMISSIONS, 2))->get()->getResultArray();
        foreach ($permissionIds as $permission) {
            $this->db->table('role_permissions')->where('permission_id', (int) $permission['id'])->delete();
        }
        $this->db->table('permissions')->whereIn('permission_key', array_column(self::PERMISSIONS, 2))->delete();
    }
}
