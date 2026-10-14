<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyPatientIdentityLinks extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_patient_identity_links')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'shopify_store_domain' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'shopify_customer_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 191,
                ],
                'patient_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                ],
                'email_snapshot' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'mobile_snapshot' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => true,
                ],
                'link_status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'unmatched',
                ],
                'matched_by' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => true,
                ],
                'active_patient_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 191,
                    'null' => true,
                ],
                'linked_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['shopify_store_domain', 'shopify_customer_id']);
            $this->forge->addUniqueKey('active_patient_key');
            $this->forge->addKey(['shopify_store_domain', 'link_status']);
            $this->forge->addKey('patient_id');
            $this->forge->addForeignKey('patient_id', 'patients', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('shopify_patient_identity_links', true);
        }

        if (! $this->db->tableExists('shopify_handoff_nonces')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'nonce' => [
                    'type' => 'VARCHAR',
                    'constraint' => 96,
                ],
                'shopify_store_domain' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'shopify_customer_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 191,
                ],
                'patient_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'expires_at' => [
                    'type' => 'DATETIME',
                ],
                'used_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('nonce');
            $this->forge->addKey(['shopify_store_domain', 'shopify_customer_id']);
            $this->forge->addForeignKey('patient_id', 'patients', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('shopify_handoff_nonces', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_handoff_nonces', true);
        $this->forge->dropTable('shopify_patient_identity_links', true);
    }
}
