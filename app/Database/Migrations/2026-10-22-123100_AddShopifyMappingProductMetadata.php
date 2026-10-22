<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyMappingProductMetadata extends Migration
{
    public function up(): void
    {
        foreach (['shopify_order_import_lines' => ['shopify_sku' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'shopify_barcode' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'product_image_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true]], 'shopify_medicine_mappings' => ['shopify_barcode' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'product_image_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true]]] as $table => $fields) {
            if (! $this->db->tableExists($table)) continue;
            foreach ($fields as $name => $definition) if (! $this->db->fieldExists($name, $table)) $this->forge->addColumn($table, [$name => $definition]);
        }
    }

    public function down(): void
    {
        foreach (['shopify_order_import_lines' => ['shopify_sku', 'shopify_barcode', 'product_image_url'], 'shopify_medicine_mappings' => ['shopify_barcode', 'product_image_url']] as $table => $fields) if ($this->db->tableExists($table)) foreach ($fields as $field) if ($this->db->fieldExists($field, $table)) $this->forge->dropColumn($table, $field);
    }
}
