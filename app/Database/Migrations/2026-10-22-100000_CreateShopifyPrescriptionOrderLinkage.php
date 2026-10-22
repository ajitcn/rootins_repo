<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds private order linkage and a safe bundle reference for Shopify Rx uploads. */
final class CreateShopifyPrescriptionOrderLinkage extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('patient_documents')) {
            foreach ([
                'shopify_bundle_reference' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
                'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            ] as $field => $definition) {
                if (! $this->db->fieldExists($field, 'patient_documents')) $this->forge->addColumn('patient_documents', [$field => $definition]);
            }
        }
        if ($this->db->tableExists('shopify_order_imports')) {
            foreach ([
                'prescription_reference' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
                'prescription_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'awaiting_review'],
            ] as $field => $definition) {
                if (! $this->db->fieldExists($field, 'shopify_order_imports')) $this->forge->addColumn('shopify_order_imports', [$field => $definition]);
            }
            if ($this->db->DBDriver === 'MySQLi') {
                try { $this->db->query('ALTER TABLE shopify_order_imports ADD INDEX shopify_order_import_prescription (prescription_reference)'); } catch (\Throwable) {}
            }
        }
    }

    public function down(): void
    {
        foreach (['shopify_bundle_reference', 'shopify_order_id'] as $field) if ($this->db->tableExists('patient_documents') && $this->db->fieldExists($field, 'patient_documents')) $this->forge->dropColumn('patient_documents', $field);
        foreach (['prescription_reference', 'prescription_status'] as $field) if ($this->db->tableExists('shopify_order_imports') && $this->db->fieldExists($field, 'shopify_order_imports')) $this->forge->dropColumn('shopify_order_imports', $field);
    }
}
