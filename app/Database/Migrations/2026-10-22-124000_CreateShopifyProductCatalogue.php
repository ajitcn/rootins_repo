<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyProductCatalogue extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_product_catalogue')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
                'shopify_product_id' => ['type' => 'VARCHAR', 'constraint' => 100],
                'product_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'product_handle' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'product_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'product_image_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'prescription_required' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
                'shopify_updated_at' => ['type' => 'DATETIME', 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['store_domain', 'shopify_product_id'], 'shopify_catalogue_product_identity');
            $this->forge->addKey(['store_domain', 'is_active'], false, false, 'shopify_catalogue_product_status');
            $this->forge->createTable('shopify_product_catalogue', true);
        }

        if (! $this->db->tableExists('shopify_variant_catalogue')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
                'shopify_product_id' => ['type' => 'VARCHAR', 'constraint' => 100],
                'shopify_variant_id' => ['type' => 'VARCHAR', 'constraint' => 100],
                'product_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'product_handle' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'product_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'product_image_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'variant_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'sku' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'barcode' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'price_snapshot' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
                'prescription_required' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
                'shopify_updated_at' => ['type' => 'DATETIME', 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['store_domain', 'shopify_product_id', 'shopify_variant_id'], 'shopify_catalogue_variant_identity');
            $this->forge->addKey(['store_domain', 'is_active'], false, false, 'shopify_catalogue_variant_status');
            $this->forge->addForeignKey(['store_domain', 'shopify_product_id'], 'shopify_product_catalogue', ['store_domain', 'shopify_product_id'], 'CASCADE', 'CASCADE', 'shopify_catalogue_variant_product_fk');
            $this->forge->createTable('shopify_variant_catalogue', true);
        }

        if (! $this->db->tableExists('shopify_product_syncs')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20],
                'error_category' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'products_fetched' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'variants_fetched' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'created_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'updated_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'unchanged_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'deactivated_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'already_mapped_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'needs_mapping_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'failed_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'started_at' => ['type' => 'DATETIME', 'null' => true],
                'completed_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['store_domain', 'started_at']);
            $this->forge->createTable('shopify_product_syncs', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_product_syncs', true);
        $this->forge->dropTable('shopify_variant_catalogue', true);
        $this->forge->dropTable('shopify_product_catalogue', true);
    }
}
