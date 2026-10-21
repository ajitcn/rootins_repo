<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddDoctorMasterPermissions extends Migration
{
    private const PERMISSIONS = [
        ['Doctor Languages', 'doctor_language.view', 'View doctor language master'],
        ['Doctor Languages', 'doctor_language.manage', 'Manage doctor language master'],
        ['Popular Treatments', 'popular_treatment.view', 'View popular treatment master'],
        ['Popular Treatments', 'popular_treatment.manage', 'Manage popular treatment master'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as [$module, $key, $description]) {
            if (! $this->db->table('permissions')->where('permission_key', $key)->countAllResults()) {
                $this->db->table('permissions')->insert(['module' => $module, 'permission_name' => ucwords(str_replace(['.', '_'], [' ', ' '], $key)), 'permission_key' => $key, 'description' => $description, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $admin = $this->db->table('roles')->where('role_name', 'Admin')->get()->getRowArray();
        if ($admin) foreach (self::PERMISSIONS as [, $key]) {
            $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();
            if ($permission && ! $this->db->table('role_permissions')->where(['role_id' => $admin['id'], 'permission_id' => $permission['id']])->countAllResults()) $this->db->table('role_permissions')->insert(['role_id' => $admin['id'], 'permission_id' => $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down(): void
    {
        $keys = array_column(self::PERMISSIONS, 1);
        $ids = $this->db->table('permissions')->select('id')->whereIn('permission_key', $keys)->get()->getResultArray();
        if ($ids) $this->db->table('role_permissions')->whereIn('permission_id', array_column($ids, 'id'))->delete();
        $this->db->table('permissions')->whereIn('permission_key', $keys)->delete();
    }
}
