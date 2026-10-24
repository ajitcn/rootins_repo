<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyExternalReconciliation extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_order_import_refunds')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'import_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
                'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100],
                'shopify_refund_id' => ['type' => 'VARCHAR', 'constraint' => 100],
                'amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
                'currency' => ['type' => 'CHAR', 'constraint' => 3, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'processed'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['import_id', 'shopify_refund_id'], 'shopify_refund_identity');
            $this->forge->addForeignKey('import_id', 'shopify_order_imports', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('shopify_order_import_refunds', true);
        }

        if (! $this->db->tableExists('shopify_order_import_fulfillments')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'import_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
                'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100],
                'shopify_fulfillment_id' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'tracking_company' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'tracking_number' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
                'tracking_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'fulfilled_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['import_id', 'shopify_fulfillment_id'], 'shopify_fulfillment_identity');
            $this->forge->addForeignKey('import_id', 'shopify_order_imports', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('shopify_order_import_fulfillments', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_order_import_fulfillments', true);
        $this->forge->dropTable('shopify_order_import_refunds', true);
    }
}
