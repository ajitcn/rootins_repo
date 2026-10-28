<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyPrescriptionCartStatusFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_prescription_cart_pushes')) return;

        foreach ([
            'items_json' => ['type' => 'TEXT', 'null' => true],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'request_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
        ] as $field => $definition) {
            if (! $this->db->fieldExists($field, 'shopify_prescription_cart_pushes')) {
                $this->forge->addColumn('shopify_prescription_cart_pushes', [$field => $definition]);
            }
        }
        try {
            $this->db->query('ALTER TABLE shopify_prescription_cart_pushes ADD UNIQUE KEY shopify_prescription_cart_push_idempotency (store_domain, shopify_customer_id, idempotency_key)');
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('shopify_prescription_cart_pushes')) return;
        foreach (['items_json', 'idempotency_key', 'request_hash'] as $field) {
            if ($this->db->fieldExists($field, 'shopify_prescription_cart_pushes')) $this->forge->dropColumn('shopify_prescription_cart_pushes', $field);
        }
    }
}
