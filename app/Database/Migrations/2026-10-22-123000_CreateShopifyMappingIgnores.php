<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyMappingIgnores extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_mapping_ignores')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
            'shopify_product_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'shopify_variant_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['store_domain', 'shopify_product_id', 'shopify_variant_id'], 'shopify_mapping_ignore_identity');
        $this->forge->addKey(['store_domain', 'created_at']);
        $this->forge->createTable('shopify_mapping_ignores', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_mapping_ignores', true);
    }
}
