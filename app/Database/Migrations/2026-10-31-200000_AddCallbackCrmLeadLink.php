<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCallbackCrmLeadLink extends Migration
{
    public function up(): void
    {
        $table = 'shopify_ai_analysis_callback_requests';
        if (! $this->db->tableExists($table)) {
            return;
        }

        if (! $this->db->fieldExists('crm_lead_id', $table)) {
            $this->forge->addColumn($table, [
                'crm_lead_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                ],
            ]);
        }

        $index = $this->db->query(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            ['shopify_ai_callback_crm_lead_idx']
        )->getResultArray();
        if ($index === []) {
            $this->db->query("ALTER TABLE `{$table}` ADD INDEX `shopify_ai_callback_crm_lead_idx` (`crm_lead_id`)");
        }

        if ($this->db->tableExists('crm_leads')) {
            $foreignKey = $this->db->query(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
                [$this->db->getDatabase(), $table, 'shopify_ai_callback_crm_lead_fk']
            )->getRowArray();
            if (! $foreignKey) {
                $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `shopify_ai_callback_crm_lead_fk` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON UPDATE CASCADE ON DELETE SET NULL");
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive for production callback records.
    }
}
