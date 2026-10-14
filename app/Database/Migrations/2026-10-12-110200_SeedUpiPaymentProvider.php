<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class SeedUpiPaymentProvider extends Migration
{
    public function up(): void
    {
        $table = $this->db->table('payment_providers');
        if (!$table->where('provider_code', 'upi')->countAllResults()) {
            $now = date('Y-m-d H:i:s');
            $table->insert([
                'provider_name' => 'UPI',
                'provider_code' => 'upi',
                'provider_type' => 'manual',
                'default_currency' => 'INR',
                'status' => 1,
                'sort_order' => 35,
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('payment_providers')->where('provider_code', 'upi')->delete();
    }
}
