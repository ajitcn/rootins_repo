<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyHairAnalysisConfigurations extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_hair_analysis_configurations')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'version' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'prompt_text' => ['type' => 'LONGTEXT'],
            'schema_json' => ['type' => 'LONGTEXT'],
            'model' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'gpt-4.1-mini'],
            'temperature' => ['type' => 'DECIMAL', 'constraint' => '3,2', 'default' => '0.20'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('version');
        $this->forge->addKey(['is_active', 'version']);
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shopify_hair_analysis_configurations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_hair_analysis_configurations', true);
    }
}
