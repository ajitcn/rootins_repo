<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyOrderImport extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
            'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'shopify_order_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'rootins_order_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'received'],
            'failure_reason' => ['type' => 'TEXT', 'null' => true],
            'financial_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'fulfillment_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'total_snapshot' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'shipping_address_snapshot' => ['type' => 'TEXT', 'null' => true],
            'payload_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'last_event_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'retry_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['store_domain', 'shopify_order_id'], 'shopify_order_import_identity');
        $this->forge->addKey(['store_domain', 'status'], false, 'shopify_order_import_status');
        $this->forge->addKey('rootins_order_id', false, 'shopify_order_import_rootins');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('last_event_id', 'shopify_webhook_events', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shopify_order_imports', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'import_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'shopify_line_item_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'shopify_product_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'shopify_variant_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'medicine_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'title_snapshot' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'shopify_price_snapshot' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'rootins_order_item_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['import_id', 'shopify_line_item_id'], 'shopify_order_line_identity');
        $this->forge->addKey(
            ['shopify_product_id', 'shopify_variant_id'],
            false,
            'shopify_order_line_product',
        );
        $this->forge->addForeignKey('import_id', 'shopify_order_imports', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shopify_order_import_lines', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
            'shopify_product_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'shopify_variant_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'medicine_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['store_domain', 'shopify_product_id', 'shopify_variant_id'], 'shopify_medicine_mapping_identity');
        $this->forge->addKey(['store_domain', 'status'], false, 'shopify_medicine_mapping_status');
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('shopify_medicine_mappings', true);

        foreach ([
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'internal'],
            'external_store_domain' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'external_order_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'external_order_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ] as $field => $definition) {
            if (!$this->db->fieldExists($field, 'medicine_orders')) {
                $this->forge->addColumn('medicine_orders', [$field => $definition]);
            }
        }

        try {
            $this->db->query('ALTER TABLE medicine_orders ADD UNIQUE KEY medicine_orders_external_identity (external_store_domain, external_order_id)');
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        try {
            $this->db->query('ALTER TABLE medicine_orders DROP INDEX medicine_orders_external_identity');
        } catch (\Throwable) {
        }

        foreach (['source_type', 'external_store_domain', 'external_order_id', 'external_order_number'] as $field) {
            if ($this->db->fieldExists($field, 'medicine_orders')) {
                $this->forge->dropColumn('medicine_orders', $field);
            }
        }

        $this->forge->dropTable('shopify_medicine_mappings', true);
        $this->forge->dropTable('shopify_order_import_lines', true);
        $this->forge->dropTable('shopify_order_imports', true);
    }
}
