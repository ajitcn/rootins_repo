<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCoreApiFoundation extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('core_api_credentials')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'key_id' => ['type' => 'VARCHAR', 'constraint' => 100],
                'secret_encrypted' => ['type' => 'TEXT'],
                'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
                'rate_limit_per_minute' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 60],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('key_id');
            $this->forge->addKey(['clinic_id', 'status']);
            $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('core_api_credentials', true);
        }

        if (! $this->db->tableExists('core_api_idempotency')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'credential_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 191],
                'request_hash' => ['type' => 'CHAR', 'constraint' => 64],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'processing'],
                'response_status' => ['type' => 'SMALLINT', 'constraint' => 5, 'null' => true],
                'response_json' => ['type' => 'LONGTEXT', 'null' => true],
                'expires_at' => ['type' => 'DATETIME'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['credential_id', 'idempotency_key']);
            $this->forge->addKey(['status', 'expires_at']);
            $this->forge->addForeignKey('credential_id', 'core_api_credentials', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('core_api_idempotency', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('core_api_idempotency', true);
        $this->forge->dropTable('core_api_credentials', true);
    }
}
