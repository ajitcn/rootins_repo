<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureShopifyOrderImportIdString extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_order_imports') && $this->db->fieldExists('shopify_order_id', 'shopify_order_imports')) {
            $this->forge->modifyColumn('shopify_order_imports', [
                'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('shopify_order_imports') && $this->db->fieldExists('shopify_order_id', 'shopify_order_imports')) {
            $this->forge->modifyColumn('shopify_order_imports', [
                'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            ]);
        }
    }
}
