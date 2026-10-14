<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePaymentProviders extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('payment_providers')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'provider_name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'provider_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'provider_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'manual'],
                'default_currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'null' => true],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'archived_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('provider_name');
            $this->forge->addUniqueKey('provider_code');
            $this->forge->addKey(['status', 'archived_at']);
            $this->forge->createTable('payment_providers', true);
        }

        $now = date('Y-m-d H:i:s');
        $table = $this->db->table('payment_providers');
        foreach ([
            ['provider_name' => 'Manual verification', 'provider_code' => 'manual', 'provider_type' => 'manual', 'default_currency' => 'INR', 'sort_order' => 10],
            ['provider_name' => 'Cash', 'provider_code' => 'cash', 'provider_type' => 'manual', 'default_currency' => 'INR', 'sort_order' => 20],
            ['provider_name' => 'Bank Transfer', 'provider_code' => 'bank_transfer', 'provider_type' => 'manual', 'default_currency' => 'INR', 'sort_order' => 30],
            ['provider_name' => 'Cashfree', 'provider_code' => 'cashfree', 'provider_type' => 'gateway', 'default_currency' => 'INR', 'sort_order' => 40],
            ['provider_name' => 'Razorpay', 'provider_code' => 'razorpay', 'provider_type' => 'gateway', 'default_currency' => 'INR', 'sort_order' => 50],
        ] as $seed) {
            if (!$table->where('provider_code', $seed['provider_code'])->countAllResults()) {
                $table->insert($seed + ['status' => 1, 'archived_at' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('payment_providers', true);
    }
}
