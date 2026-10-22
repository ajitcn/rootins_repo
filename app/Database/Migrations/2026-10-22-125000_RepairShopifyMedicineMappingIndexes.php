<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class RepairShopifyMedicineMappingIndexes extends Migration
{
    public function up(): void
    {
        $table = 'shopify_medicine_mappings';
        if (! $this->db->tableExists($table)) return;

        $indexes = $this->db->query('SHOW INDEX FROM ' . $table)->getResultArray();
        $names = [];
        $badStatusIndex = false;
        foreach ($indexes as $index) {
            $name = (string) ($index['Key_name'] ?? '');
            $names[$name] = true;
            if ($name === 'store_domain_status' && (int) ($index['Non_unique'] ?? 1) === 0) $badStatusIndex = true;
        }

        if ($badStatusIndex) {
            $this->db->query('ALTER TABLE ' . $table . ' DROP INDEX store_domain_status');
            unset($names['store_domain_status']);
        }
        if (! isset($names['shopify_medicine_mapping_status'])) {
            $this->db->query('ALTER TABLE ' . $table . ' ADD INDEX shopify_medicine_mapping_status (store_domain, status)');
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('shopify_medicine_mappings')) return;
        try { $this->db->query('ALTER TABLE shopify_medicine_mappings DROP INDEX shopify_medicine_mapping_status'); } catch (\Throwable) {}
    }
}
