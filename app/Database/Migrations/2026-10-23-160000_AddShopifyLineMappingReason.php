<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyLineMappingReason extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_order_import_lines')) {
            return;
        }

        if (! $this->db->fieldExists('mapping_reason', 'shopify_order_import_lines')) {
            $this->forge->addColumn('shopify_order_import_lines', [
                'mapping_reason' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('shopify_order_import_lines') && $this->db->fieldExists('mapping_reason', 'shopify_order_import_lines')) {
            $this->forge->dropColumn('shopify_order_import_lines', 'mapping_reason');
        }
    }
}
