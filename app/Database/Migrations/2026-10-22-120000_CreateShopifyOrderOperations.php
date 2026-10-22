<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyOrderOperations extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_order_imports')) {
            $fields = [
                'shopify_customer_id' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
                'customer_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'customer_email_snapshot' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'customer_mobile_snapshot' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'patient_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'mapping_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'quarantine_reason' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'last_error' => ['type' => 'TEXT', 'null' => true],
                'last_attempted_at' => ['type' => 'DATETIME', 'null' => true],
            ];
            foreach ($fields as $name => $definition) {
                if (! $this->db->fieldExists($name, 'shopify_order_imports')) {
                    $this->forge->addColumn('shopify_order_imports', [$name => $definition]);
                }
            }
            foreach (['shopify_customer_id', 'mapping_status', 'patient_status', 'quarantine_reason'] as $field) {
                try { $this->db->query('ALTER TABLE shopify_order_imports ADD INDEX shopify_order_operations_' . $field . ' (' . $field . ')'); } catch (\Throwable) {}
            }
        }

        if ($this->db->tableExists('shopify_medicine_mappings')) {
            foreach ([
                'shopify_product_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'shopify_variant_title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'shopify_sku' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            ] as $name => $definition) {
                if (! $this->db->fieldExists($name, 'shopify_medicine_mappings')) $this->forge->addColumn('shopify_medicine_mappings', [$name => $definition]);
            }
        }

        if ($this->db->tableExists('shopify_patient_identity_links')) {
            foreach ([
                'registration_attempt_at' => ['type' => 'DATETIME', 'null' => true],
                'registration_error' => ['type' => 'TEXT', 'null' => true],
            ] as $name => $definition) {
                if (! $this->db->fieldExists($name, 'shopify_patient_identity_links')) $this->forge->addColumn('shopify_patient_identity_links', [$name => $definition]);
            }
        }

        if (! $this->db->tableExists('shopify_order_import_history')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'import_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'event_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'action' => ['type' => 'VARCHAR', 'constraint' => 60],
                'from_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'to_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'error_message' => ['type' => 'TEXT', 'null' => true],
                'actor_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['import_id', 'created_at']);
            $this->forge->addForeignKey('import_id', 'shopify_order_imports', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('shopify_order_import_history', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_order_import_history', true);
        foreach (['shopify_customer_id', 'customer_name_snapshot', 'customer_email_snapshot', 'customer_mobile_snapshot', 'patient_status', 'mapping_status', 'quarantine_reason', 'last_error', 'last_attempted_at'] as $field) {
            if ($this->db->tableExists('shopify_order_imports') && $this->db->fieldExists($field, 'shopify_order_imports')) {
                $this->forge->dropColumn('shopify_order_imports', $field);
            }
        }
        foreach (['shopify_product_title', 'shopify_variant_title', 'shopify_sku'] as $field) {
            if ($this->db->tableExists('shopify_medicine_mappings') && $this->db->fieldExists($field, 'shopify_medicine_mappings')) $this->forge->dropColumn('shopify_medicine_mappings', $field);
        }
        foreach (['registration_attempt_at', 'registration_error'] as $field) {
            if ($this->db->tableExists('shopify_patient_identity_links') && $this->db->fieldExists($field, 'shopify_patient_identity_links')) $this->forge->dropColumn('shopify_patient_identity_links', $field);
        }
    }
}
