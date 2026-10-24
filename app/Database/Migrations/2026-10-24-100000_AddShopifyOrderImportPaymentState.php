<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShopifyOrderImportPaymentState extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_order_imports')) return;

        $fields = [
            'payment_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'paid_at' => ['type' => 'DATETIME', 'null' => true],
            'error_message' => ['type' => 'TEXT', 'null' => true],
        ];
        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, 'shopify_order_imports')) {
                $this->forge->addColumn('shopify_order_imports', [$name => $definition]);
            }
        }
    }

    public function down(): void
    {
        foreach (['payment_status', 'paid_at', 'error_message'] as $field) {
            if ($this->db->tableExists('shopify_order_imports') && $this->db->fieldExists($field, 'shopify_order_imports')) {
                $this->forge->dropColumn('shopify_order_imports', $field);
            }
        }
    }
}
