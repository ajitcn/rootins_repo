<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

final class LinkShopifyAiLeadsToCrm extends Migration
{
    public function up(): void
    {
        foreach (['shopify_hair_analysis_leads','shopify_skin_analysis_leads'] as $table) {
            if ($this->db->tableExists($table) && ! $this->db->fieldExists('crm_lead_id',$table)) {
                $this->forge->addColumn($table,['crm_lead_id'=>['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true]]);
                $this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$table}_crm_lead_idx` (`crm_lead_id`)");
                $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_crm_lead_fk` FOREIGN KEY (`crm_lead_id`) REFERENCES `crm_leads` (`id`) ON UPDATE CASCADE ON DELETE SET NULL");
            }
        }
        if ($this->db->tableExists('shopify_ai_analysis_callback_requests') && ! $this->db->fieldExists('follow_up_id','shopify_ai_analysis_callback_requests')) {
            $this->forge->addColumn('shopify_ai_analysis_callback_requests',['follow_up_id'=>['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true]]);
            $this->db->query('ALTER TABLE `shopify_ai_analysis_callback_requests` ADD INDEX `shopify_ai_callback_follow_up_idx` (`follow_up_id`)');
            $this->db->query('ALTER TABLE `shopify_ai_analysis_callback_requests` ADD CONSTRAINT `shopify_ai_callback_follow_up_fk` FOREIGN KEY (`follow_up_id`) REFERENCES `crm_lead_followups` (`id`) ON UPDATE CASCADE ON DELETE SET NULL');
        }
        if ($this->db->tableExists('crm_lead_followups') && ! $this->db->fieldExists('priority','crm_lead_followups')) $this->forge->addColumn('crm_lead_followups',['priority'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'normal']]);
    }
    public function down(): void { }
}
