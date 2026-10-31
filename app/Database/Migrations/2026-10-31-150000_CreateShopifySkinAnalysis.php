<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

final class CreateShopifySkinAnalysis extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_skin_analysis_configurations')) {
            $this->forge->addField([
                'id' => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'auto_increment'=>true],
                'version' => ['type'=>'INT','constraint'=>10,'unsigned'=>true],
                'prompt_text' => ['type'=>'LONGTEXT'],
                'schema_json' => ['type'=>'LONGTEXT'],
                'model' => ['type'=>'VARCHAR','constraint'=>100,'default'=>'gpt-4.1-mini'],
                'temperature' => ['type'=>'DECIMAL','constraint'=>'3,2','default'=>'0.20'],
                'is_active' => ['type'=>'TINYINT','constraint'=>1,'default'=>1],
                'created_by' => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true],
                'created_at' => ['type'=>'DATETIME','null'=>true],
                'updated_at' => ['type'=>'DATETIME','null'=>true],
            ]);
            $this->forge->addKey('id', true); $this->forge->addUniqueKey('version'); $this->forge->addKey(['is_active','version']); $this->forge->addForeignKey('created_by','users','id','SET NULL','CASCADE'); $this->forge->createTable('shopify_skin_analysis_configurations', true);
        }
        if (! $this->db->tableExists('shopify_skin_analysis_leads')) {
            $this->forge->addField([
                'id'=>['type'=>'BIGINT','constraint'=>20,'unsigned'=>true,'auto_increment'=>true],
                'lead_reference'=>['type'=>'VARCHAR','constraint'=>40],
                'store_domain'=>['type'=>'VARCHAR','constraint'=>255],
                'shopify_customer_id'=>['type'=>'VARCHAR','constraint'=>80],
                'idempotency_key'=>['type'=>'VARCHAR','constraint'=>191],
                'first_name'=>['type'=>'VARCHAR','constraint'=>100],
                'last_name'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true],
                'age'=>['type'=>'SMALLINT','constraint'=>5,'unsigned'=>true,'null'=>true],
                'gender'=>['type'=>'VARCHAR','constraint'=>20,'null'=>true],
                'email'=>['type'=>'VARCHAR','constraint'=>191,'null'=>true],
                'mobile_number'=>['type'=>'VARCHAR','constraint'=>30,'null'=>true],
                'country_code'=>['type'=>'VARCHAR','constraint'=>10,'null'=>true],
                'date_of_birth'=>['type'=>'DATE','null'=>true],
                'concern'=>['type'=>'VARCHAR','constraint'=>160,'null'=>true],
                'health_list'=>['type'=>'TEXT','null'=>true],
                'questionnaire_json'=>['type'=>'LONGTEXT','null'=>true],
                'status'=>['type'=>'VARCHAR','constraint'=>30,'default'=>'processing'],
                'result_json'=>['type'=>'LONGTEXT','null'=>true],
                'error_code'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true],
                'error_message'=>['type'=>'TEXT','null'=>true],
                'patient_id'=>['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true],
                'processed_at'=>['type'=>'DATETIME','null'=>true],
                'created_at'=>['type'=>'DATETIME','null'=>true],
                'updated_at'=>['type'=>'DATETIME','null'=>true],
            ]);
            $this->forge->addKey('id',true); $this->forge->addUniqueKey('lead_reference'); $this->forge->addUniqueKey(['store_domain','shopify_customer_id','idempotency_key']); $this->forge->addKey(['store_domain','shopify_customer_id','status']); $this->forge->addForeignKey('patient_id','patients','id','SET NULL','CASCADE'); $this->forge->createTable('shopify_skin_analysis_leads',true);
        }
        if (! $this->db->tableExists('shopify_skin_analysis_images')) {
            $this->forge->addField(['id'=>['type'=>'BIGINT','constraint'=>20,'unsigned'=>true,'auto_increment'=>true],'lead_id'=>['type'=>'BIGINT','constraint'=>20,'unsigned'=>true],'angle'=>['type'=>'VARCHAR','constraint'=>30],'storage_key'=>['type'=>'VARCHAR','constraint'=>500],'mime_type'=>['type'=>'VARCHAR','constraint'=>100],'file_size'=>['type'=>'BIGINT','constraint'=>20,'unsigned'=>true],'created_at'=>['type'=>'DATETIME','null'=>true]]);
            $this->forge->addKey('id',true); $this->forge->addUniqueKey(['lead_id','angle']); $this->forge->addForeignKey('lead_id','shopify_skin_analysis_leads','id','CASCADE','CASCADE'); $this->forge->createTable('shopify_skin_analysis_images',true);
        }
    }
    public function down(): void { $this->forge->dropTable('shopify_skin_analysis_images',true); $this->forge->dropTable('shopify_skin_analysis_leads',true); $this->forge->dropTable('shopify_skin_analysis_configurations',true); }
}
