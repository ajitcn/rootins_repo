<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddDeliveryOverridePermission extends Migration
{
    public function up(): void
    {
        $permission = $this->db->table('permissions')->where('permission_key', 'pharmacy.delivery.override')->get(1)->getRowArray();
        if ($permission === null) {
            $this->db->table('permissions')->insert(['module' => 'Pharmacy Delivery', 'permission_name' => 'Override Delivery Status', 'permission_key' => 'pharmacy.delivery.override', 'status' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down(): void
    {
        $this->db->table('permissions')->where('permission_key', 'pharmacy.delivery.override')->delete();
    }
}
