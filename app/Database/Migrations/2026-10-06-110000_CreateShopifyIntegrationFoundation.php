<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyIntegrationFoundation extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_webhook_events')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'store_domain' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'external_event_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 191,
                ],
                'topic' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                ],
                'payload_hash' => [
                    'type' => 'CHAR',
                    'constraint' => 64,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'received',
                ],
                'processed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'retry_count' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 0,
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
            $this->forge->addUniqueKey(['store_domain', 'external_event_id']);
            $this->forge->addKey(['topic', 'status']);
            $this->forge->createTable('shopify_webhook_events', true);
        }

        if (! $this->db->tableExists('shopify_operation_idempotency')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'operation_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 191,
                ],
                'operation_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'processing',
                ],
                'response_hash' => [
                    'type' => 'CHAR',
                    'constraint' => 64,
                    'null' => true,
                ],
                'completed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'retry_count' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 0,
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
            $this->forge->addUniqueKey('operation_key');
            $this->forge->addKey(['status', 'operation_type']);
            $this->forge->createTable('shopify_operation_idempotency', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_operation_idempotency', true);
        $this->forge->dropTable('shopify_webhook_events', true);
    }
}
