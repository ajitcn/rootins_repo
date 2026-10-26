<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyOrderReviewWorkflow extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_order_imports')) {
            foreach ([
                'review_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'review_reason' => ['type' => 'TEXT', 'null' => true],
                'reviewed_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            ] as $name => $definition) {
                if (! $this->db->fieldExists($name, 'shopify_order_imports')) $this->forge->addColumn('shopify_order_imports', [$name => $definition]);
            }
            try { $this->db->query('ALTER TABLE shopify_order_imports ADD INDEX shopify_order_review_status (review_status)'); } catch (\Throwable) {}
        }

        if ($this->db->tableExists('shopify_order_import_lines')) {
            foreach ([
                'review_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'review_reason' => ['type' => 'TEXT', 'null' => true],
                'substitute_medicine_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'substitute_note' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'approved_quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'reviewed_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            ] as $name => $definition) {
                if (! $this->db->fieldExists($name, 'shopify_order_import_lines')) $this->forge->addColumn('shopify_order_import_lines', [$name => $definition]);
            }
            try { $this->db->query('ALTER TABLE shopify_order_import_lines ADD INDEX shopify_order_line_review_status (review_status)'); } catch (\Throwable) {}
        }

        if (! $this->db->tableExists('shopify_order_notifications')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
                'import_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'shopify_customer_id' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
                'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'notification_type' => ['type' => 'VARCHAR', 'constraint' => 80],
                'channel' => ['type' => 'VARCHAR', 'constraint' => 20],
                'template_key' => ['type' => 'VARCHAR', 'constraint' => 120],
                'safe_payload_json' => ['type' => 'TEXT', 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
                'sent_at' => ['type' => 'DATETIME', 'null' => true],
                'retry_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'last_error' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['import_id', 'notification_type', 'channel', 'template_key'], 'shopify_order_notification_identity');
            $this->forge->addKey(['status', 'retry_count'], false, 'shopify_order_notification_queue');
            $this->forge->addForeignKey('import_id', 'shopify_order_imports', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('shopify_order_notifications', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_order_notifications', true);
        foreach (['review_status', 'review_reason', 'reviewed_by', 'reviewed_at'] as $field) {
            if ($this->db->tableExists('shopify_order_imports') && $this->db->fieldExists($field, 'shopify_order_imports')) $this->forge->dropColumn('shopify_order_imports', $field);
        }
        foreach (['review_status', 'review_reason', 'substitute_medicine_id', 'substitute_note', 'approved_quantity', 'reviewed_by', 'reviewed_at'] as $field) {
            if ($this->db->tableExists('shopify_order_import_lines') && $this->db->fieldExists($field, 'shopify_order_import_lines')) $this->forge->dropColumn('shopify_order_import_lines', $field);
        }
    }
}
