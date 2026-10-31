<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class RepairShopifyAiCrmLinkageSchema extends Migration
{
    public function up(): void
    {
        $this->ensureColumn('shopify_hair_analysis_leads', 'crm_lead_id', [
            'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true,
        ]);
        $this->ensureColumn('shopify_skin_analysis_leads', 'crm_lead_id', [
            'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true,
        ]);
        $this->ensureColumn('shopify_ai_analysis_callback_requests', 'follow_up_id', [
            'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true,
        ]);
        $this->ensureColumn('crm_lead_followups', 'priority', [
            'type' => 'VARCHAR', 'constraint' => 20, 'default' => 'normal',
        ]);

        $this->ensureIndex('shopify_hair_analysis_leads', 'shopify_hair_analysis_leads_crm_lead_idx', 'crm_lead_id');
        $this->ensureIndex('shopify_skin_analysis_leads', 'shopify_skin_analysis_leads_crm_lead_idx', 'crm_lead_id');
        $this->ensureIndex('shopify_ai_analysis_callback_requests', 'shopify_ai_callback_follow_up_idx', 'follow_up_id');

        $this->ensureForeignKey('shopify_hair_analysis_leads', 'shopify_hair_analysis_leads_crm_lead_fk', 'crm_lead_id', 'crm_leads');
        $this->ensureForeignKey('shopify_skin_analysis_leads', 'shopify_skin_analysis_leads_crm_lead_fk', 'crm_lead_id', 'crm_leads');
        $this->ensureForeignKey('shopify_ai_analysis_callback_requests', 'shopify_ai_callback_follow_up_fk', 'follow_up_id', 'crm_lead_followups');
    }

    private function ensureColumn(string $table, string $column, array $definition): void
    {
        if ($this->db->tableExists($table) && ! $this->db->fieldExists($column, $table)) {
            $this->forge->addColumn($table, [$column => $definition]);
        }
    }

    private function ensureIndex(string $table, string $index, string $column): void
    {
        if (! $this->db->tableExists($table) || $this->indexExists($table, $index)) {
            return;
        }
        $this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$column}`)");
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])->getResultArray();
        return $rows !== [];
    }

    private function ensureForeignKey(string $table, string $constraint, string $column, string $referencedTable): void
    {
        if (! $this->db->tableExists($table) || ! $this->db->tableExists($referencedTable)) {
            return;
        }
        $database = $this->db->getDatabase();
        $exists = $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
            [$database, $table, $constraint]
        )->getRowArray();
        if ($exists) {
            return;
        }
        $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`id`) ON UPDATE CASCADE ON DELETE SET NULL");
    }

    public function down(): void
    {
        // Intentionally non-destructive: this repair migration must not remove production linkage.
    }
}
