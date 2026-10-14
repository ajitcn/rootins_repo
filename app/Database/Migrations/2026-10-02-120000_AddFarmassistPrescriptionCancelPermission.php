<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddFarmassistPrescriptionCancelPermission extends Migration
{
    public function up(): void
    {
        $permissions = $this->db->table('permissions');
        $permission = $permissions->where('permission_key', 'prescription.review.cancel')->get()->getRowArray();

        if ($permission === null) {
            $permissions->insert([
                'module' => 'Prescription Review',
                'permission_name' => 'Cancel Prescription Review',
                'permission_key' => 'prescription.review.cancel',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $permission = ['id' => $this->db->insertID()];
        }

        $role = $this->db->table('roles')->where('role_code', 'FARMASSIST')->get()->getRowArray();

        if ($role === null) {
            return;
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

    public function down(): void
    {
        $permission = $this->db->table('permissions')
            ->where('permission_key', 'prescription.review.cancel')
            ->get()->getRowArray();

        if ($permission === null) {
            return;
        }

        $this->db->table('role_permissions')
            ->where('permission_id', (int) $permission['id'])
            ->delete();
        $this->db->table('permissions')
            ->where('id', (int) $permission['id'])
            ->delete();
    }
}
