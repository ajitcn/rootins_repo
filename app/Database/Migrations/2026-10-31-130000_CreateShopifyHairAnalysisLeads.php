<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyHairAnalysisLeads extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_hair_analysis_leads')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'lead_reference' => ['type' => 'VARCHAR', 'constraint' => 40],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 255],
            'shopify_customer_id' => ['type' => 'VARCHAR', 'constraint' => 80],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 191],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'age' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'gender' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'concern' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'health_list' => ['type' => 'TEXT', 'null' => true],
            'questionnaire_json' => ['type' => 'LONGTEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'result_json' => ['type' => 'LONGTEXT', 'null' => true],
            'error_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'error_message' => ['type' => 'TEXT', 'null' => true],
            'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('lead_reference');
        $this->forge->addUniqueKey(['store_domain', 'shopify_customer_id', 'idempotency_key']);
        $this->forge->addKey(['store_domain', 'shopify_customer_id', 'status']);
        $this->forge->addKey('patient_id');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shopify_hair_analysis_leads', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'lead_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'angle' => ['type' => 'VARCHAR', 'constraint' => 30],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 500],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'file_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['lead_id', 'angle']);
        $this->forge->addForeignKey('lead_id', 'shopify_hair_analysis_leads', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shopify_hair_analysis_images', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_hair_analysis_images', true);
        $this->forge->dropTable('shopify_hair_analysis_leads', true);
    }
}
