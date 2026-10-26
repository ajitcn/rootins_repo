<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyLineReconciliation extends Migration
{
    public function up(): void
    {
        foreach ([
            'shopify_order_import_line_refunds' => ['shopify_refund_id' => ['type' => 'VARCHAR', 'constraint' => 100], 'shopify_line_item_id' => ['type' => 'VARCHAR', 'constraint' => 100], 'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0], 'amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0]],
            'shopify_order_import_line_fulfillments' => ['shopify_fulfillment_id' => ['type' => 'VARCHAR', 'constraint' => 100], 'shopify_line_item_id' => ['type' => 'VARCHAR', 'constraint' => 100], 'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0]],
        ] as $table => $extra) {
            if ($this->db->tableExists($table)) continue;
            $fields = ['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'import_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190], 'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100]] + $extra + ['status' => ['type' => 'VARCHAR', 'constraint' => 30], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];
            $this->forge->addField($fields); $this->forge->addKey('id', true); $identity = $table === 'shopify_order_import_line_refunds' ? ['import_id', 'shopify_refund_id', 'shopify_line_item_id'] : ['import_id', 'shopify_fulfillment_id', 'shopify_line_item_id']; $this->forge->addUniqueKey($identity, $table . '_identity'); $this->forge->addForeignKey('import_id', 'shopify_order_imports', 'id', 'CASCADE', 'CASCADE'); $this->forge->createTable($table, true);
        }
    }

    public function down(): void { $this->forge->dropTable('shopify_order_import_line_fulfillments', true); $this->forge->dropTable('shopify_order_import_line_refunds', true); }
}
