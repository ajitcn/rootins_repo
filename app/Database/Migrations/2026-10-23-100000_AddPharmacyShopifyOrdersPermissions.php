<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPharmacyShopifyOrdersPermissions extends Migration
{
    private const PERMISSIONS = [
        ['Pharmacy Shopify Orders', 'View Pharmacy Shopify Orders', 'pharmacy.shopify_orders.view'],
        ['Pharmacy Shopify Orders', 'Manage Pharmacy Shopify Orders', 'pharmacy.shopify_orders.manage'],
    ];

    public function up(): void
    {
        $role = $this->db->table('roles')->where('role_code', 'PHARMACY_ASSISTANT')->get()->getRowArray();
        if ($role === null) return;

        foreach (self::PERMISSIONS as [$module, $name, $key]) {
            $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();
            if ($permission === null) {
                $this->db->table('permissions')->insert([
                    'module' => $module,
                    'permission_name' => $name,
                    'permission_key' => $key,
                    'description' => 'Access to clinic-scoped imported Shopify order operations.',
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();
            }
            if ($permission === null) continue;
            if ($this->db->table('role_permissions')->where(['role_id' => (int) $role['id'], 'permission_id' => (int) $permission['id']])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert(['role_id' => (int) $role['id'], 'permission_id' => (int) $permission['id'], 'created_at' => date('Y-m-d H:i:s')]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::PERMISSIONS as [, , $key]) {
            $permission = $this->db->table('permissions')->where('permission_key', $key)->get()->getRowArray();
            if ($permission === null) continue;
            $this->db->table('role_permissions')->where('permission_id', (int) $permission['id'])->delete();
            $this->db->table('permissions')->where('id', (int) $permission['id'])->delete();
        }
    }
}
