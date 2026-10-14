<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyConfigurations extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_configurations')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 255],
            'admin_api_access_token_encrypted' => ['type' => 'TEXT'],
            'api_version' => ['type' => 'VARCHAR', 'constraint' => 20],
            'webhook_secret_encrypted' => ['type' => 'TEXT'],
            'app_url' => ['type' => 'VARCHAR', 'constraint' => 500],
            'encryption_key_encrypted' => ['type' => 'TEXT'],
            'auto_create_patients' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clinic_id', 'shopify_configurations_clinic_unique');
        $this->forge->addUniqueKey('store_domain', 'shopify_configurations_domain_unique');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shopify_configurations');
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_configurations', true);
    }
}
