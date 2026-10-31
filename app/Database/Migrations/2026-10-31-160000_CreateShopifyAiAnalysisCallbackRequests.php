<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

final class CreateShopifyAiAnalysisCallbackRequests extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_ai_analysis_callback_requests')) return;
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','constraint'=>20,'unsigned'=>true,'auto_increment'=>true],
            'analysis_type'=>['type'=>'VARCHAR','constraint'=>10],
            'lead_id'=>['type'=>'BIGINT','constraint'=>20,'unsigned'=>true],
            'lead_reference'=>['type'=>'VARCHAR','constraint'=>40],
            'analysis_id'=>['type'=>'VARCHAR','constraint'=>80],
            'store_domain'=>['type'=>'VARCHAR','constraint'=>255],
            'shopify_customer_id'=>['type'=>'VARCHAR','constraint'=>80],
            'preferred_time'=>['type'=>'VARCHAR','constraint'=>120,'null'=>true],
            'note'=>['type'=>'TEXT','null'=>true],
            'status'=>['type'=>'VARCHAR','constraint'=>30,'default'=>'pending'],
            'priority'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'high'],
            'handled_by'=>['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true],
            'handled_at'=>['type'=>'DATETIME','null'=>true],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['store_domain','shopify_customer_id','analysis_type','lead_reference']);
        $this->forge->addKey(['status','priority','created_at']);
        $this->forge->addKey(['analysis_type','lead_id']);
        $this->forge->addForeignKey('handled_by','users','id','SET NULL','CASCADE');
        $this->forge->createTable('shopify_ai_analysis_callback_requests', true);
    }
    public function down(): void { $this->forge->dropTable('shopify_ai_analysis_callback_requests', true); }
}
