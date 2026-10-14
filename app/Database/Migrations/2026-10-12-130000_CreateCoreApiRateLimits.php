<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCoreApiRateLimits extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('core_api_rate_limits')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'credential_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'window_start' => ['type' => 'DATETIME'],
            'attempts' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['credential_id', 'window_start']);
        $this->forge->addKey(['window_start', 'credential_id']);
        $this->forge->addForeignKey('credential_id', 'core_api_credentials', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('core_api_rate_limits', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('core_api_rate_limits', true);
    }
}
