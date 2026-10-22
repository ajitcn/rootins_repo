<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class RepairShopifyProductCatalogueIndexes extends Migration
{
    public function up(): void
    {
        foreach (['shopify_product_catalogue', 'shopify_variant_catalogue'] as $table) {
            if (! $this->db->tableExists($table)) continue;
            try {
                $indexes = array_map(static fn (array $row): string => (string) ($row['Key_name'] ?? ''), $this->db->query('SHOW INDEX FROM ' . $table)->getResultArray());
                if (in_array('store_domain_is_active', $indexes, true)) {
                    $this->db->query('ALTER TABLE ' . $table . ' DROP INDEX store_domain_is_active');
                    $indexes = array_values(array_diff($indexes, ['store_domain_is_active']));
                }
                if (! in_array('shopify_catalogue_status', $indexes, true)
                    && ! in_array('shopify_catalogue_product_status', $indexes, true)
                    && ! in_array('shopify_catalogue_variant_status', $indexes, true)) {
                    $this->db->query('ALTER TABLE ' . $table . ' ADD INDEX shopify_catalogue_status (store_domain, is_active)');
                }
            } catch (\Throwable) {}
        }
    }

    public function down(): void
    {
        foreach (['shopify_product_catalogue', 'shopify_variant_catalogue'] as $table) {
            if (! $this->db->tableExists($table)) continue;
            try { $this->db->query('ALTER TABLE ' . $table . ' DROP INDEX shopify_catalogue_status'); } catch (\Throwable) {}
        }
    }
}
